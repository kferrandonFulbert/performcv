<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\CvShares;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\{ChoiceType, DateTimeType, PasswordType};
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class ShareType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('isPublic', ChoiceType::class, ['label' => 'État du lien', 'choices' => ['Actif' => 1, 'Désactivé' => 0]])
            ->add('expiresAt', DateTimeType::class, ['label' => 'Expiration (UTC)', 'widget' => 'single_text', 'input' => 'datetime', 'model_timezone' => 'UTC', 'view_timezone' => 'UTC', 'required' => false, 'constraints' => [new Assert\GreaterThan('now')]])
            ->add('password', PasswordType::class, ['label' => 'Mot de passe du lien (facultatif)', 'mapped' => false, 'required' => false, 'constraints' => [new Assert\Length(min: 12, max: 128)], 'help' => 'Laisser vide pour créer un lien sans mot de passe.']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => CvShares::class]);
    }
}
