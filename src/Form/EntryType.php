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
            'experiences' => [
                'companyName' => ['Entreprise', 'Atelier Horizon', 'Écrivez le nom utilisé publiquement par votre employeur. Gardez le nom du service pour la description : le recruteur pourra ainsi identifier l’entreprise.', true],
                'position' => ['Poste', 'Chargée de clientèle', 'Écrivez l’intitulé en toutes lettres : « Responsable des ressources humaines (RH) » est plus explicite que « Resp. RH ». Gardez un titre fidèle à votre rôle.'],
                'description' => ['Description', 'Créé un guide de prise en main utilisé pour former six collègues.', 'Commencez par une action, précisez ce que vous avez réalisé et donnez un résultat vérifiable. Par exemple : « Formé six collègues au nouvel outil de gestion des commandes. »', false, 'text'],
                'location' => ['Lieu', 'Lyon', 'Indiquez la ville et, pour une candidature internationale, le pays. Si le poste était à distance, précisez « Télétravail ». L’adresse complète est inutile.'],
            ],
            'educations' => [
                'institution' => ['Établissement', 'Université de Lille', 'Utilisez le nom officiel de l’école ou de l’université. Si elle est surtout connue par un sigle, ajoutez-le entre parenthèses.', true],
                'degree' => ['Diplôme', 'Licence informatique', 'Recopiez le nom complet du diplôme. S’il est en préparation, dites-le clairement : « Master informatique (en cours) ».'],
                'fieldOfStudy' => ['Domaine d’études', 'Génie logiciel', 'Nommez la spécialité suivie, par exemple « Génie logiciel ». Utilisez le vocabulaire de l’offre seulement s’il correspond à votre formation.'],
                'description' => ['Description', 'Projet de fin de licence : développer un outil de suivi des stocks.', 'Choisissez un travail qui illustre un savoir-faire attendu : « Analysé les ventes d’une boutique avec SQL dans le cadre du mémoire. »', false, 'text'],
            ],
            'certifications' => [
                'name' => ['Certification', 'DELF B2', 'Reprenez l’intitulé officiel et son sigle usuel, par exemple « Diplôme d’études en langue française (DELF) B2 ». Le titre doit rester compréhensible sans ouvrir le justificatif.', true],
                'issuingOrganization' => ['Organisme', 'France Éducation international', 'Nommez l’organisme qui délivre la certification, tel qu’il figure sur le document. Le centre de formation peut être différent.'],
                'credentialId' => ['Identifiant du justificatif', 'CERT-12345', 'Si l’organisme fournit une référence publique de vérification, recopiez-la exactement. Sinon, laissez ce champ vide.'],
                'credentialUrl' => ['Lien du justificatif', 'https://example.org/certificats/12345', 'Collez le lien public de vérification de la certification. Vérifiez qu’un recruteur peut l’ouvrir sans compte.', false, 'url'],
            ],
            'projects' => [
                'title' => ['Titre', 'Site de réservation pour une association', 'Donnez un titre qui indique l’usage du projet, par exemple « Application de réservation de salles ». Un nom de code seul renseigne peu le recruteur.', true],
                'description' => ['Description', 'Conçu le formulaire qui permet aux adhérents de réserver un créneau en ligne.', 'Expliquez le besoin, votre contribution personnelle et ce que le projet a permis. Le lecteur doit comprendre votre rôle sans ouvrir le lien.', false, 'text'],
                'url' => ['Lien du projet', 'https://example.org/projets/reservation', 'Choisissez un lien public vers une démonstration, un portfolio ou un dépôt documenté. Résumez aussi le projet dans la description.', false, 'url'],
                'technologiesUsed' => ['Technologies', 'PHP, Symfony, SQLite', 'Listez les outils réellement utilisés, sous leur nom habituel et séparés par des virgules : « Python, PostgreSQL, Git ». Retenez ceux qui éclairent votre contribution.', false, 'text'],
            ],
            'languages' => ['language' => ['Langue', 'Anglais', 'Écrivez le nom de la langue en toutes lettres, par exemple « Anglais ». Un drapeau ou un code pays ne désigne pas toujours une langue.', true, 'short']],
            'skills' => [],
        };
        foreach ($fields as $name => $definition) {
            [$label, $example, $help, $required, $kind] = array_pad($definition, 5, null);
            $type = match ($kind) { 'text' => TextareaType::class, 'url' => UrlType::class, default => TextType::class };
            $constraints = [new Assert\Length(max: $kind === 'text' ? 5000 : ($kind === 'short' ? 100 : 255))];
            if ($required) {
                $constraints[] = new Assert\NotBlank();
            }
            $fieldOptions = ['label' => $label, 'help' => $help, 'required' => (bool) $required, 'constraints' => $constraints, 'attr' => ['placeholder' => 'Ex. : '.$example]];
            if ($kind === 'url') {
                $fieldOptions['default_protocol'] = null;
                $fieldOptions['constraints'][] = new Assert\Url(protocols: ['https', 'http'], requireTld: true);
            }
            $builder->add($name, $type, $fieldOptions);
        }
        if (in_array($options['section'], ['experiences', 'educations', 'projects', 'certifications'], true)) {
            $dates = $options['section'] === 'certifications' ? ['issueDate' => "Date d’obtention", 'expirationDate' => "Date d’expiration"] : ['startDate' => 'Début', 'endDate' => 'Fin'];
            $dateHelp = [
                'startDate' => 'Indiquez le début réel de cette période pour situer votre parcours. Si vous ne connaissez pas la date exacte, laissez le champ vide.',
                'endDate' => 'Indiquez la date de fin réelle. Si cette activité se poursuit, laissez le champ vide.',
                'issueDate' => 'Reportez la date de délivrance indiquée sur le certificat : elle peut être différente de la date de l’examen.',
                'expirationDate' => 'Indiquez l’échéance inscrite sur le certificat. Si sa validité est permanente, laissez le champ vide.',
            ];
            foreach ($dates as $name => $label) {
                $builder->add($name, DateType::class, ['label' => $label, 'help' => $dateHelp[$name], 'widget' => 'single_text', 'input' => 'datetime', 'required' => false]);
            }
        }
        if ($options['section'] === 'experiences') {
            $builder->add('isCurrent', ChoiceType::class, ['label' => 'Poste actuel', 'help' => 'Choisissez « Oui » seulement si vous occupez toujours ce poste et laissez alors la date de fin vide.', 'choices' => ['Non' => 0, 'Oui' => 1]]);
        }
        if (SectionCatalog::SECTIONS[$options['section']]['ordered']) {
            $builder->add('orderIndex', IntegerType::class, ['label' => "Ordre d’affichage", 'attr' => ['placeholder' => 'Ex. : 1'], 'required' => false, 'constraints' => [new Assert\Range(min: 0, max: 9999)], 'help' => 'Les plus petits nombres apparaissent en premier. Pour faciliter la lecture, donnez par exemple le numéro 1 à l’élément le plus récent.']);
        }
        if ($options['section'] === 'languages') {
            $builder->add('level', ChoiceType::class, ['label' => 'Niveau', 'help' => 'Utilisez l’échelle A1 à C2 si vous connaissez votre niveau. Par exemple, B2 indique que vous pouvez soutenir une discussion et expliquer votre point de vue.', 'placeholder' => 'Choisissez votre niveau', 'required' => false, 'choices' => array_combine(['A1', 'A2', 'B1', 'B2', 'C1', 'C2', 'Natif'], ['A1', 'A2', 'B1', 'B2', 'C1', 'C2', 'Natif'])]);
        }
        if ($options['section'] === 'skills') {
            $builder->add('skillName', TextType::class, ['label' => 'Compétence', 'help' => 'Nommez un savoir-faire précis, par exemple « Analyse de données avec Excel ». Reprenez les termes de l’offre si vous pouvez les illustrer par une expérience.', 'attr' => ['placeholder' => 'Ex. : Animation de réunions'], 'mapped' => false, 'data' => $options['skill_name'], 'constraints' => [new Assert\NotBlank(), new Assert\Length(max: 100)]])
                ->add('level', ChoiceType::class, ['label' => 'Niveau', 'help' => 'Choisissez un niveau que vous pourriez illustrer en entretien : réalisez-vous les tâches avec de l’aide, en autonomie ou en accompagnant des collègues ?', 'placeholder' => 'Choisissez votre niveau', 'required' => false, 'choices' => ['Débutant' => 'Débutant', 'Intermédiaire' => 'Intermédiaire', 'Confirmé' => 'Confirmé', 'Expert' => 'Expert']])
                ->add('yearsExperience', IntegerType::class, ['label' => "Années d’expérience", 'help' => 'Comptez les années pendant lesquelles vous avez pratiqué cette compétence. Deux projets menés la même année ne comptent pas pour deux ans.', 'attr' => ['placeholder' => 'Ex. : 3'], 'required' => false, 'constraints' => [new Assert\Range(min: 0, max: 80)]]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired('section');
        $resolver->setAllowedValues('section', array_keys(SectionCatalog::SECTIONS));
        $resolver->setDefaults(['skill_name' => null, 'data_class' => null]);
    }
}
