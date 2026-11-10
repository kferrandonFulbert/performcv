<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\{Profiles, Users};
use App\Form\{EntryType, ProfileType};
use App\Repository\WorkspaceRepository;
use App\Service\{Chronology, SectionCatalog, WorkspaceWriter};
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\Routing\Attribute\Route;

#[Route('/app')]
class ProfileController extends AbstractController
{
    public function __construct(private WorkspaceRepository $data, private WorkspaceWriter $writer)
    {
    }

    #[Route('', name: 'app_dashboard', methods: ['GET'])]
    public function dashboard(Request $request): Response
    {
        $page = max(1, min(10000, $request->query->getInt('page', 1)));
        $profiles = $this->data->profiles($this->getUser()->getId(), $page);

        return $this->render('workspace/dashboard.html.twig', ['profiles' => array_slice($profiles, 0, 20), 'next' => count($profiles) > 20, 'page' => $page]);
    }

    #[Route('/profils/nouveau', name: 'app_profile_new', methods: ['GET', 'POST'])]
    public function create(Request $request): Response
    {
        $profile = (new Profiles())->setUserId($this->getUser()->getId())->setSlug(bin2hex(random_bytes(16)));

        return $this->editProfile($request, $profile, 'Créer un profil');
    }

    #[Route('/profils/{id}/modifier', name: 'app_profile_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, int $id): Response
    {
        return $this->editProfile($request, $this->ownedProfile($id), 'Modifier mon profil');
    }

    #[Route('/profils/{id}', name: 'app_profile_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id): Response
    {
        $profile = $this->ownedProfile($id);
        $content = $this->data->content($id);

        return $this->render('workspace/profile.html.twig', ['profile' => $profile, 'content' => $content, 'sections' => SectionCatalog::SECTIONS, 'skill_names' => $this->data->skillNames($content['skills']), 'cvs' => $this->data->cvs($id)]);
    }

    #[Route('/profils/{id}/supprimer', name: 'app_profile_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, int $id): Response
    {
        $profile = $this->ownedProfile($id);
        $this->checkToken($request, 'delete_profile_'.$id);
        $this->writer->deleteProfile($profile);
        $this->addFlash('success', 'Le profil, ses CV et ses liens ont été supprimés.');

        return $this->redirectToRoute('app_dashboard', status: 303);
    }

    #[Route('/profils/{profileId}/rubriques/{section}/nouveau', name: 'app_entry_new', requirements: ['profileId' => '\d+'], methods: ['GET', 'POST'])]
    public function newEntry(Request $request, int $profileId, string $section, Chronology $chronology): Response
    {
        $profile = $this->ownedProfile($profileId);
        $definition = $this->definition($section);
        if (count($this->data->section($section, $profileId)) >= 100) {
            throw $this->createAccessDeniedException('Cette rubrique contient déjà 100 éléments.');
        }
        $entry = (new $definition['class']())->setProfileId($profileId);

        return $this->editEntry($request, $profile, $section, $entry, $chronology);
    }

    #[Route('/profils/{profileId}/rubriques/{section}/{id}/modifier', name: 'app_entry_edit', requirements: ['profileId' => '\d+', 'id' => '\d+'], methods: ['GET', 'POST'])]
    public function updateEntry(Request $request, int $profileId, string $section, int $id, Chronology $chronology): Response
    {
        $profile = $this->ownedProfile($profileId);

        return $this->editEntry($request, $profile, $section, $this->ownedEntry($profileId, $section, $id), $chronology);
    }

    #[Route('/profils/{profileId}/rubriques/{section}/{id}/supprimer', name: 'app_entry_delete', requirements: ['profileId' => '\d+', 'id' => '\d+'], methods: ['POST'])]
    public function deleteEntry(Request $request, int $profileId, string $section, int $id): Response
    {
        $this->ownedProfile($profileId);
        $entry = $this->ownedEntry($profileId, $section, $id);
        $this->checkToken($request, 'delete_entry_'.$section.'_'.$id);
        $this->writer->remove($entry);
        $this->addFlash('success', 'Élément supprimé.');

        return $this->redirectToRoute('app_profile_show', ['id' => $profileId], 303);
    }

    private function editProfile(Request $request, Profiles $profile, string $title): Response
    {
        $form = $this->createForm(ProfileType::class, $profile)->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->writer->save($profile);
            $this->addFlash('success', 'Profil enregistré.');

            return $this->redirectToRoute('app_profile_show', ['id' => $profile->getId()], 303);
        }

        return $this->render('shared/form.html.twig', ['form' => $form, 'title' => $title, 'eyebrow' => 'Votre parcours', 'back' => $this->generateUrl('app_dashboard'), 'button' => 'Enregistrer le profil']);
    }

    private function editEntry(Request $request, Profiles $profile, string $section, object $entry, Chronology $chronology): Response
    {
        $skillName = $section === 'skills' && $entry->getSkillId() ? ($this->data->skillNames([$entry])[$entry->getSkillId()] ?? '') : null;
        $form = $this->createForm(EntryType::class, $entry, ['section' => $section, 'data_class' => $entry::class, 'skill_name' => $skillName])->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if ($error = $chronology->error($entry)) {
                $form->addError(new FormError($error));
            } else {
                if ($section === 'skills') {
                    $skill = $this->data->skill(trim($form->get('skillName')->getData()));
                    $entry->setSkillId($skill->getId());
                }
                $this->writer->save($entry);
                $this->addFlash('success', 'Rubrique enregistrée.');

                return $this->redirectToRoute('app_profile_show', ['id' => $profile->getId()], 303);
            }
        }

        return $this->render('shared/form.html.twig', ['form' => $form, 'title' => $this->definition($section)['label'], 'eyebrow' => $profile->getTitle(), 'back' => $this->generateUrl('app_profile_show', ['id' => $profile->getId()]), 'button' => 'Enregistrer']);
    }

    private function ownedProfile(int $id): Profiles
    {
        $profile = $this->data->find(Profiles::class, $id) ?? throw $this->createNotFoundException();
        $this->denyAccessUnlessGranted('PROFILE_EDIT', $profile);

        return $profile;
    }

    private function definition(string $section): array
    {
        return SectionCatalog::SECTIONS[$section] ?? throw $this->createNotFoundException();
    }

    private function ownedEntry(int $profileId, string $section, int $id): object
    {
        $entry = $this->data->find($this->definition($section)['class'], $id) ?? throw $this->createNotFoundException();
        if ($entry->getProfileId() !== $profileId) {
            throw $this->createNotFoundException();
        }

        return $entry;
    }

    private function checkToken(Request $request, string $id): void
    {
        if (!$this->isCsrfTokenValid($id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Le formulaire a expiré. Rechargez la page.');
        }
    }
}
