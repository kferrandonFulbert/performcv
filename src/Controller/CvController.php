<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\{CvInstances, CvShares, Profiles};
use App\Form\{CvType, ShareType};
use App\Repository\WorkspaceRepository;
use App\Service\{CvPresentation, SectionCatalog, WorkspaceWriter};
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/app')]
class CvController extends AbstractController
{
    public function __construct(private WorkspaceRepository $data, private WorkspaceWriter $writer)
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
        $form = $this->createForm(CvType::class, $cv, ['templates' => $templates])->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $visibility = [];
            foreach (array_keys(SectionCatalog::SECTIONS) as $key) {
                $visibility[$key] = $form->get($key)->getData() === true;
            }
            $cv->setSectionsVisibility($visibility)->setUpdatedAt(new \DateTime());
            $this->writer->save($cv);
            $this->addFlash('success', 'Votre CV a été enregistré.');

            return $this->redirectToRoute('app_cv_show', ['id' => $cv->getId()], 303);
        }

        return $this->render('shared/form.html.twig', ['form' => $form, 'title' => 'Un CV à votre image', 'eyebrow' => $profile->getTitle(), 'back' => $this->generateUrl('app_profile_show', ['id' => $profile->getId()]), 'button' => 'Enregistrer le CV']);
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
