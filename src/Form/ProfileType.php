<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Profiles;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\{ChoiceType, TextareaType, TextType, UrlType};
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class ProfileType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('title', TextType::class, ['label' => 'Titre du profil', 'constraints' => [new Assert\NotBlank(), new Assert\Length(max: 255)]])
            ->add('summary', TextareaType::class, ['label' => 'Présentation', 'required' => false, 'constraints' => [new Assert\Length(max: 5000)], 'attr' => ['rows' => 5]])
            ->add('location', TextType::class, ['label' => 'Localisation', 'required' => false, 'constraints' => [new Assert\Length(max: 255)]]);
        foreach (['website' => 'Site web', 'linkedinUrl' => 'LinkedIn', 'githubUrl' => 'GitHub'] as $name => $label) {
            $builder->add($name, UrlType::class, ['label' => $label, 'required' => false, 'default_protocol' => null, 'constraints' => [new Assert\Url(protocols: ['https', 'http'], requireTld: true), new Assert\Length(max: 255)]]);
        }
        $builder->add('isPublic', ChoiceType::class, ['label' => 'Visibilité du profil', 'choices' => ['Privé' => 0, 'Public via son adresse' => 1], 'help' => 'Un profil public affiche ses rubriques, votre nom et vos coordonnées. Les CV partagés disposent de leurs propres réglages.']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Profiles::class]);
    }
}
