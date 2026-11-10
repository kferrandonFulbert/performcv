<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Users;
use App\Form\{AccountType, ChangePasswordType, DeleteAccountType, RegistrationType};
use App\Repository\WorkspaceRepository;
use App\Service\WorkspaceWriter;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class SecurityController extends AbstractController
{
    #[Route('/connexion', name: 'app_login', methods: ['GET', 'POST'])]
    public function login(AuthenticationUtils $authentication): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_dashboard');
        }

        return $this->render('security/login.html.twig', ['last_email' => $authentication->getLastUsername(), 'error' => $authentication->getLastAuthenticationError()]);
    }

    #[Route('/inscription', name: 'app_register', methods: ['GET', 'POST'])]
    public function register(Request $request, WorkspaceRepository $data, WorkspaceWriter $writer, UserPasswordHasherInterface $hasher): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_dashboard');
        }
        $form = $this->createForm(RegistrationType::class)->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $values = $form->getData();
            $email = mb_strtolower(trim($values['email']));
            if ($data->userByEmail($email)) {
                $form->addError(new FormError('Impossible de créer ce compte. Vérifiez vos informations ou connectez-vous.'));
            } else {
                $user = (new Users())->setEmail($email)->setFirstName($values['firstName'])->setLastName($values['lastName'])
                    ->setCreatedAt(new \DateTime())->setUpdatedAt(new \DateTime());
                $user->setPasswordHash($hasher->hashPassword($user, $values['password']));
                try {
                    $writer->save($user);
                    $this->addFlash('success', 'Votre compte est créé. Vous pouvez vous connecter.');

                    return $this->redirectToRoute('app_login', status: 303);
                } catch (UniqueConstraintViolationException) {
                    $form->addError(new FormError('Impossible de créer ce compte. Vérifiez vos informations ou connectez-vous.'));
                }
            }
        }

        return $this->render('shared/form.html.twig', ['form' => $form, 'title' => 'Votre prochain chapitre commence ici.', 'eyebrow' => 'Créer un compte', 'back' => $this->generateUrl('app_login'), 'button' => 'Créer mon compte']);
    }

    #[Route('/app/compte', name: 'app_account', methods: ['GET', 'POST'])]
    public function account(Request $request, WorkspaceWriter $writer): Response
    {
        /** @var Users $user */
        $user = $this->getUser();
        $form = $this->createForm(AccountType::class, $user)->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $user->setUpdatedAt(new \DateTime());
            $writer->save($user);
            $this->addFlash('success', 'Vos coordonnées ont été mises à jour.');

            return $this->redirectToRoute('app_account', status: 303);
        }

        return $this->render('shared/form.html.twig', ['form' => $form, 'title' => 'Mes coordonnées', 'eyebrow' => $user->getEmail(), 'back' => $this->generateUrl('app_dashboard'), 'button' => 'Enregistrer']);
    }

    #[Route('/deconnexion', name: 'app_logout', methods: ['POST'])]
    public function logout(): never
    {
        throw new \LogicException('Handled by the security firewall.');
    }

    #[Route('/app/compte/mot-de-passe', name: 'app_password', methods: ['GET', 'POST'])]
    public function password(Request $request, WorkspaceWriter $writer, UserPasswordHasherInterface $hasher, TokenStorageInterface $tokens): Response
    {
        $form = $this->createForm(ChangePasswordType::class)->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            /** @var Users $user */
            $user = $this->getUser();
            $user->setPasswordHash($hasher->hashPassword($user, $form->get('newPassword')->getData()))->setUpdatedAt(new \DateTime());
            $writer->save($user);
            $tokens->setToken(null);
            $request->getSession()->invalidate();
            $this->addFlash('success', 'Mot de passe modifié. Connectez-vous à nouveau.');

            return $this->redirectToRoute('app_login', status: 303);
        }

        return $this->render('shared/form.html.twig', ['form' => $form, 'title' => 'Changer mon mot de passe', 'eyebrow' => 'Sécurité du compte', 'back' => $this->generateUrl('app_account'), 'button' => 'Modifier le mot de passe']);
    }

    #[Route('/app/compte/supprimer', name: 'app_account_delete', methods: ['GET', 'POST'])]
    public function deleteAccount(Request $request, WorkspaceWriter $writer, TokenStorageInterface $tokens): Response
    {
        $form = $this->createForm(DeleteAccountType::class)->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $writer->deleteAccount($this->getUser());
            $tokens->setToken(null);
            $request->getSession()->invalidate();
            $this->addFlash('success', 'Votre compte et ses données ont été supprimés.');

            return $this->redirectToRoute('app_home', status: 303);
        }

        return $this->render('shared/form.html.twig', ['form' => $form, 'title' => 'Supprimer mon compte', 'eyebrow' => 'Cette suppression est définitive', 'back' => $this->generateUrl('app_account'), 'button' => 'Supprimer définitivement mon compte']);
    }
}
