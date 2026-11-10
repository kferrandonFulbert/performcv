<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\{PasswordType, RepeatedType};
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Security\Core\Validator\Constraints\UserPassword;
use Symfony\Component\Validator\Constraints as Assert;

class ChangePasswordType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('currentPassword', PasswordType::class, ['label' => 'Mot de passe actuel', 'constraints' => [new Assert\NotBlank(), new Assert\Length(max: 128), new UserPassword()]])
            ->add('newPassword', RepeatedType::class, ['type' => PasswordType::class,
                'first_options' => ['label' => 'Nouveau mot de passe', 'attr' => ['autocomplete' => 'new-password']],
                'second_options' => ['label' => 'Confirmer le nouveau mot de passe', 'attr' => ['autocomplete' => 'new-password']],
                'invalid_message' => 'Les mots de passe doivent être identiques.',
                'constraints' => [new Assert\NotBlank(), new Assert\Length(min: 12, max: 128)],
            ]);
    }
}
