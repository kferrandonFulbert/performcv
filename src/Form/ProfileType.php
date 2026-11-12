<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Profiles;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\{CheckboxType, ChoiceType, FileType, TextareaType, TextType, UrlType};
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
        $builder->add('photo', FileType::class, ['label' => 'Photo de profil', 'mapped' => false, 'required' => false, 'help' => 'JPEG, PNG ou WebP, 2 Mo maximum et 4 000 × 4 000 pixels. L’affichage se règle dans chaque CV.', 'attr' => ['accept' => 'image/jpeg,image/png,image/webp'], 'constraints' => [new Assert\Image(maxSize: '2M', mimeTypes: ['image/jpeg', 'image/png', 'image/webp'], maxWidth: 4000, maxHeight: 4000)]])
            ->add('removePhoto', CheckboxType::class, ['label' => 'Supprimer la photo actuelle', 'mapped' => false, 'required' => false]);
        $builder->add('isPublic', ChoiceType::class, ['label' => 'Visibilité du profil', 'choices' => ['Privé' => 0, 'Public via son adresse' => 1], 'help' => 'Un profil public affiche ses rubriques, votre nom et vos coordonnées. Les CV partagés disposent de leurs propres réglages.']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Profiles::class]);
    }
}
