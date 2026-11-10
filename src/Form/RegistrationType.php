<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\{EmailType, PasswordType, RepeatedType, TextType};
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

class RegistrationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstName', TextType::class, ['label' => 'Prénom', 'constraints' => [new Assert\NotBlank(), new Assert\Length(max: 100)]])
            ->add('lastName', TextType::class, ['label' => 'Nom', 'constraints' => [new Assert\NotBlank(), new Assert\Length(max: 100)]])
            ->add('email', EmailType::class, ['label' => 'Adresse email', 'constraints' => [new Assert\NotBlank(), new Assert\Email(), new Assert\Length(max: 255)]])
            ->add('password', RepeatedType::class, [
                'type' => PasswordType::class,
                'first_options' => ['label' => 'Mot de passe', 'attr' => ['autocomplete' => 'new-password']],
                'second_options' => ['label' => 'Confirmer le mot de passe', 'attr' => ['autocomplete' => 'new-password']],
                'invalid_message' => 'Les mots de passe doivent être identiques.',
                'constraints' => [new Assert\NotBlank(), new Assert\Length(min: 12, max: 128)],
            ]);
    }
}
