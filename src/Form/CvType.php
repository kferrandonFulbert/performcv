<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\CvInstances;
use App\Entity\CvTemplates;
use App\Service\SectionCatalog;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\{ChoiceType, CheckboxType};
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CvType extends AbstractType
{
    public const COLORS = ['Encre' => 'ink', 'Émeraude' => 'emerald', 'Terracotta' => 'terracotta'];
    public const FONTS = ['Moderne' => 'sans', 'Éditoriale' => 'serif'];

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('templateId', ChoiceType::class, [
            'label' => 'Présentation',
            'property_path' => 'template',
            'choices' => $options['templates'],
            'choice_value' => 'id',
            'choice_label' => 'name',
        ])
            ->add('colorScheme', ChoiceType::class, ['label' => 'Palette', 'choices' => self::COLORS])
            ->add('fontFamily', ChoiceType::class, ['label' => 'Typographie', 'choices' => self::FONTS]);
        $cv = $builder->getData();
        $builder->add('photo', CheckboxType::class, ['label' => 'Afficher la photo de profil', 'mapped' => false, 'required' => false, 'data' => ($cv?->getSectionsVisibility()['photo'] ?? false) === true, 'help' => 'Ajoutez ou remplacez votre photo depuis la modification du profil.']);
        foreach (SectionCatalog::SECTIONS as $key => $definition) {
            $builder->add($key, CheckboxType::class, ['label' => $definition['label'], 'mapped' => false, 'required' => false, 'data' => ($cv?->getSectionsVisibility()[$key] ?? true) === true]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => CvInstances::class, 'templates' => []]);
        $resolver->setAllowedTypes('templates', 'array');
    }
}
