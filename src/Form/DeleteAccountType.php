<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\{CheckboxType, PasswordType};
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Security\Core\Validator\Constraints\UserPassword;
use Symfony\Component\Validator\Constraints as Assert;

class DeleteAccountType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('currentPassword', PasswordType::class, ['label' => 'Votre mot de passe', 'constraints' => [new Assert\NotBlank(), new Assert\Length(max: 128), new UserPassword()]])
            ->add('confirm', CheckboxType::class, ['label' => 'Je confirme la suppression définitive de mon compte et de tous mes profils, CV et liens.', 'constraints' => [new Assert\IsTrue()]]);
    }
}
