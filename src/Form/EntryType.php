<?php

declare(strict_types=1);

namespace App\Form;

use App\Service\SectionCatalog;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\{ChoiceType, DateType, IntegerType, TextareaType, TextType, UrlType};
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class EntryType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $fields = match ($options['section']) {
            'experiences' => ['companyName' => ['Entreprise', true], 'position' => ['Poste'], 'description' => ['Description', false, 'text'], 'location' => ['Lieu']],
            'educations' => ['institution' => ['Établissement', true], 'degree' => ['Diplôme'], 'fieldOfStudy' => ["Domaine d’études"], 'description' => ['Description', false, 'text']],
            'certifications' => ['name' => ['Certification', true], 'issuingOrganization' => ['Organisme'], 'credentialId' => ['Identifiant du justificatif'], 'credentialUrl' => ['Lien du justificatif', false, 'url']],
            'projects' => ['title' => ['Titre', true], 'description' => ['Description', false, 'text'], 'url' => ['Lien du projet', false, 'url'], 'technologiesUsed' => ['Technologies', false, 'text']],
            'languages' => ['language' => ['Langue', true, 'short']],
            'skills' => [],
        };
        foreach ($fields as $name => $definition) {
            [$label, $required, $kind] = array_pad($definition, 3, null);
            $type = match ($kind) { 'text' => TextareaType::class, 'url' => UrlType::class, default => TextType::class };
            $constraints = [new Assert\Length(max: $kind === 'text' ? 5000 : ($kind === 'short' ? 100 : 255))];
            if ($required) {
                $constraints[] = new Assert\NotBlank();
            }
            $fieldOptions = ['label' => $label, 'required' => (bool) $required, 'constraints' => $constraints];
            if ($kind === 'url') {
                $fieldOptions['default_protocol'] = null;
                $fieldOptions['constraints'][] = new Assert\Url(protocols: ['https', 'http'], requireTld: true);
            }
            $builder->add($name, $type, $fieldOptions);
        }
        if (in_array($options['section'], ['experiences', 'educations', 'projects', 'certifications'], true)) {
            $dates = $options['section'] === 'certifications' ? ['issueDate' => "Date d’obtention", 'expirationDate' => "Date d’expiration"] : ['startDate' => 'Début', 'endDate' => 'Fin'];
            foreach ($dates as $name => $label) {
                $builder->add($name, DateType::class, ['label' => $label, 'widget' => 'single_text', 'input' => 'datetime', 'required' => false]);
            }
        }
        if ($options['section'] === 'experiences') {
            $builder->add('isCurrent', ChoiceType::class, ['label' => 'Poste actuel', 'choices' => ['Non' => 0, 'Oui' => 1]]);
        }
        if (SectionCatalog::SECTIONS[$options['section']]['ordered']) {
            $builder->add('orderIndex', IntegerType::class, ['label' => "Ordre d’affichage", 'required' => false, 'constraints' => [new Assert\Range(min: 0, max: 9999)], 'help' => 'Les plus petits nombres apparaissent en premier.']);
        }
        if ($options['section'] === 'languages') {
            $builder->add('level', ChoiceType::class, ['label' => 'Niveau', 'required' => false, 'choices' => array_combine(['A1', 'A2', 'B1', 'B2', 'C1', 'C2', 'Natif'], ['A1', 'A2', 'B1', 'B2', 'C1', 'C2', 'Natif'])]);
        }
        if ($options['section'] === 'skills') {
            $builder->add('skillName', TextType::class, ['label' => 'Compétence', 'mapped' => false, 'data' => $options['skill_name'], 'constraints' => [new Assert\NotBlank(), new Assert\Length(max: 100)]])
                ->add('level', ChoiceType::class, ['label' => 'Niveau', 'required' => false, 'choices' => ['Débutant' => 'Débutant', 'Intermédiaire' => 'Intermédiaire', 'Confirmé' => 'Confirmé', 'Expert' => 'Expert']])
                ->add('yearsExperience', IntegerType::class, ['label' => "Années d’expérience", 'required' => false, 'constraints' => [new Assert\Range(min: 0, max: 80)]]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired('section');
        $resolver->setAllowedValues('section', array_keys(SectionCatalog::SECTIONS));
        $resolver->setDefaults(['skill_name' => null, 'data_class' => null]);
    }
}
