<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Users;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\{TextType, TelType};
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class AccountType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        foreach (['firstName' => 'Prénom', 'lastName' => 'Nom'] as $name => $label) {
            $builder->add($name, TextType::class, ['label' => $label, 'required' => false, 'constraints' => [new Assert\Length(max: 100)]]);
        }
        $builder->add('phone', TelType::class, ['label' => 'Téléphone', 'required' => false, 'constraints' => [new Assert\Length(max: 20), new Assert\Regex('/^[0-9+().\s-]*$/')]]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Users::class]);
    }
}
