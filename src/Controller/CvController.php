<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\{CvInstances, CvShares, CvTemplates, Profiles};
use App\Form\{CvType, ShareType};
use App\Repository\WorkspaceRepository;
use App\Service\{CvPresentation, SectionCatalog, WorkspaceWriter, TemplateAccess, StripeCheckout};
use Symfony\Component\Form\FormError;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/app')]
class CvController extends AbstractController
{
    public function __construct(private WorkspaceRepository $data, private WorkspaceWriter $writer, private TemplateAccess $templateAccess, private CvPresentation $presentation, private StripeCheckout $stripe)
    {
    }

    #[Route('/profils/{profileId}/cv/nouveau', name: 'app_cv_new', requirements: ['profileId' => '\d+'], methods: ['GET', 'POST'])]
    public function create(Request $request, int $profileId): Response
    {
        $profile = $this->data->find(Profiles::class, $profileId) ?? throw $this->createNotFoundException();
        $this->denyAccessUnlessGranted('PROFILE_EDIT', $profile);
        if (count($this->data->cvs($profileId)) >= 50) {
            throw $this->createAccessDeniedException('La limite de 50 CV par profil est atteinte.');
        }
        $cv = (new CvInstances())->setProfileId($profileId)->setColorScheme('ink')->setFontFamily('sans')->setCreatedAt(new \DateTime());

        return $this->editCv($request, $cv, $profile);
    }

    #[Route('/cv/{id}/modifier', name: 'app_cv_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, int $id): Response
    {
        [$cv, $profile] = $this->ownedCv($id);

        return $this->editCv($request, $cv, $profile);
    }

    #[Route('/cv/{id}', name: 'app_cv_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id, CvPresentation $presentation): Response
    {
        [$cv, $profile] = $this->ownedCv($id);
        $shares = $this->data->shares($id);

        return $this->render('cv/manage.html.twig', $presentation->build($profile, $cv) + ['cv' => $cv, 'shares' => $shares, 'counts' => $this->data->viewCounts($shares)]);
    }

    #[Route('/cv/{id}/supprimer', name: 'app_cv_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, int $id): Response
    {
        [$cv, $profile] = $this->ownedCv($id);
        $this->checkToken($request, 'delete_cv_'.$id);
        $this->writer->deleteCv($cv);
        $this->addFlash('success', 'Le CV et ses liens de partage ont été supprimés.');

        return $this->redirectToRoute('app_profile_show', ['id' => $profile->getId()], 303);
    }

    #[Route('/cv/{id}/partager', name: 'app_share_new', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function share(Request $request, int $id, PasswordHasherFactoryInterface $hashers): Response
    {
        [$cv, $profile] = $this->ownedCv($id);
        if (count($this->data->shares($id)) >= 20) {
            throw $this->createAccessDeniedException('La limite de 20 liens par CV est atteinte.');
        }
        $share = (new CvShares())->setCvInstanceId($id)->setShareToken(bin2hex(random_bytes(32)))->setCreatedAt(new \DateTime());
        $form = $this->createForm(ShareType::class, $share)->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $password = $form->get('password')->getData();
            if (is_string($password) && $password !== '') {
                $share->setPasswordProtected(1)->setPasswordHash($hashers->getPasswordHasher(\App\Entity\Users::class)->hash($password));
            }
            $this->writer->save($share);
            $this->addFlash('success', 'Votre lien de partage est prêt.');

            return $this->redirectToRoute('app_cv_show', ['id' => $id], 303);
        }

        return $this->render('shared/form.html.twig', ['form' => $form, 'title' => 'Partager votre CV', 'eyebrow' => $profile->getTitle(), 'back' => $this->generateUrl('app_cv_show', ['id' => $id]), 'button' => 'Créer le lien']);
    }

    #[Route('/cv/{id}/liens/{shareId}/supprimer', name: 'app_share_delete', requirements: ['id' => '\d+', 'shareId' => '\d+'], methods: ['POST'])]
    public function revoke(Request $request, int $id, int $shareId): Response
    {
        $this->ownedCv($id);
        $share = $this->data->find(CvShares::class, $shareId) ?? throw $this->createNotFoundException();
        if ($share->getCvInstanceId() !== $id) {
            throw $this->createNotFoundException();
        }
        $this->checkToken($request, 'delete_share_'.$shareId);
        $this->writer->deleteShare($share);
        $this->addFlash('success', 'Le lien a été révoqué.');

        return $this->redirectToRoute('app_cv_show', ['id' => $id], 303);
    }

    private function editCv(Request $request, CvInstances $cv, Profiles $profile): Response
    {
        $templates = $this->data->templates();
        if (!$templates) {
            $this->addFlash('error', 'Les modèles doivent être initialisés avec la commande app:templates:seed.');

            return $this->redirectToRoute('app_profile_show', ['id' => $profile->getId()]);
        }
        $preview = $request->isMethod('POST') && $request->request->getBoolean('_preview');
        // Bind a copy: invalid or preview submissions must never dirty a managed CV.
        $working = clone $cv;
        $form = $this->createForm(CvType::class, $working, ['templates' => $templates])->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $visibility = [];
            foreach (array_keys(SectionCatalog::SECTIONS) as $key) {
                $visibility[$key] = $form->get($key)->getData() === true;
            }
            $working->setSectionsVisibility($visibility)->setUpdatedAt(new \DateTime());
            if ($preview) {
                return $this->render($request->isXmlHttpRequest() ? 'cv/preview.html.twig' : 'cv/preview-page.html.twig', $this->presentation->build($profile, $working, true));
            }
            $template = $this->data->find(CvTemplates::class, $working->getTemplateId());
            if (!$template || !$template->isActive() || !$this->templateAccess->allowed($template, $profile->getUserId())) {
                $form->get('templateId')->addError(new FormError('Achetez ce modèle pour l’utiliser. Vous pouvez le prévisualiser gratuitement.'));
            } else {
                $cv->setTemplateId($working->getTemplateId())->setColorScheme($working->getColorScheme())
                    ->setFontFamily($working->getFontFamily())->setSectionsVisibility($working->getSectionsVisibility())
                    ->setUpdatedAt(new \DateTime());
                $this->writer->save($cv);
                $this->addFlash('success', 'Votre CV a été enregistré.');

                return $this->redirectToRoute('app_cv_show', ['id' => $cv->getId()], 303);
            }
        }

        if ($preview) { return new Response('L’aperçu n’a pas pu être actualisé. Vérifiez les champs puis réessayez.', 422); }
        $rights = [];
        foreach ($templates as $template) { $rights[$template->getId()] = $this->templateAccess->allowed($template, $profile->getUserId()); }
        $initial = clone $working;
        if (!$initial->getTemplateId()) { $initial->setTemplateId($templates[0]->getId()); }
        return $this->render('cv/editor.html.twig', $this->presentation->build($profile, $initial, true) + [
            'form' => $form, 'cv' => $cv, 'templates' => $templates, 'template_rights' => $rights, 'payments_ready' => $this->stripe->ready(),
        ], new Response(status: $form->isSubmitted() && !$form->isValid() ? 422 : 200));
    }

    private function ownedCv(int $id): array
    {
        $cv = $this->data->find(CvInstances::class, $id) ?? throw $this->createNotFoundException();
        $profile = $this->data->find(Profiles::class, $cv->getProfileId()) ?? throw $this->createNotFoundException();
        $this->denyAccessUnlessGranted('PROFILE_EDIT', $profile);

        return [$cv, $profile];
    }

    private function checkToken(Request $request, string $id): void
    {
        if (!$this->isCsrfTokenValid($id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Le formulaire a expiré. Rechargez la page.');
        }
    }
}
