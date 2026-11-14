<?php

declare(strict_types=1);

namespace App\Service;

/** Curated, fictional editorial data. Never persisted as user profiles. */
final class GuideExamples
{
    public function forPage(string $page): array
    {
        $examples = match ($page) {
            'etudiant' => [[
                'name' => ['Camille', 'Martin'], 'title' => 'Employée de librairie — job étudiant',
                'summary' => 'Étudiante en lettres, disponible le samedi et deux fins d’après-midi par semaine. Je souhaite contribuer à l’accueil des lecteurs et au rangement des ouvrages.',
                'sections' => [
                    'educations' => [['degree' => 'Licence de lettres — deuxième année en cours', 'institution' => 'Université de démonstration', 'startDate' => '2025-09-01', 'description' => 'Rédaction de synthèses, recherche documentaire et présentations orales.']],
                    'experiences' => [['position' => 'Bénévole — collecte de livres', 'companyName' => 'Association fictive Lire ensemble', 'startDate' => '2026-03-01', 'endDate' => '2026-04-01', 'description' => 'Classement des dons par genre, préparation des lots et accueil des donateurs avec trois bénévoles.']],
                    'projects' => [['title' => 'Dossier de lecture collectif', 'description' => 'Sélection de cinq ouvrages et rédaction de notices pour une présentation en cours.']],
                    'languages' => [['language' => 'Anglais', 'level' => 'B1 — échanges simples et lecture de documents courants']],
                ],
                'before' => 'Étudiante sérieuse, disponible.',
                'after' => 'Étudiante en lettres, disponible le samedi ; classement et préparation de lots lors d’une collecte de livres.',
                'reason' => 'La formation ouvre le document ; le bénévolat prouve une activité utile en librairie. Les disponibilités sont précises, sans inventer d’expérience de vente.',
            ]],
            'alternance', 'lettre-alternance' => [[
                'name' => ['Inès', 'Bernard'], 'title' => 'Assistante communication — alternance',
                'summary' => 'Admission confirmée en bachelor communication pour septembre 2027. Rythme annoncé par l’école : trois jours en entreprise et deux jours en cours. Intérêt pour la rédaction et la préparation de contenus associatifs.',
                'sections' => [
                    'educations' => [['degree' => 'Bachelor communication — admission confirmée', 'institution' => 'École de démonstration', 'startDate' => '2027-09-01', 'description' => 'Formation à venir ; calendrier à transmettre à l’entreprise.']],
                    'projects' => [['title' => 'Campagne pédagogique pour une association fictive', 'description' => 'Rédaction de six publications, réalisation de visuels simples et préparation du calendrier de diffusion. Présentation des choix au groupe.', 'technologiesUsed' => 'Traitement de texte, outil de création visuelle et tableur']],
                    'experiences' => [['position' => 'Bénévole — accueil au forum étudiant', 'companyName' => 'Collectif étudiant fictif', 'startDate' => '2026-10-01', 'endDate' => '2026-10-01', 'description' => 'Orientation des visiteurs et remise du programme aux exposants.']],
                    'languages' => [['language' => 'Anglais', 'level' => 'B1 — lecture de contenus courts']],
                ],
                'before' => 'Recherche une alternance dans une entreprise dynamique.',
                'after' => 'Assistante communication en alternance à partir de septembre 2027 ; rythme de trois jours en entreprise et deux jours en cours.',
                'reason' => 'Le titre précise la mission, puis le calendrier permet de vérifier la faisabilité. Le projet apporte une preuve concrète de rédaction. Les mêmes modalités doivent figurer dans la lettre.',
            ]],
            'stage' => [[
                'name' => ['Noah', 'Petit'], 'title' => 'Stage assistant de laboratoire — six semaines',
                'summary' => 'Étudiant en deuxième année de licence de biologie. Stage recherché entre mai et juin 2027, avec convention proposée par l’université. Objectif : participer à la préparation des analyses sous supervision.',
                'sections' => [
                    'educations' => [['degree' => 'Licence de biologie — deuxième année', 'institution' => 'Université de démonstration', 'startDate' => '2025-09-01', 'description' => 'Travaux pratiques : préparation de solutions, observation au microscope et compte rendu de protocole.']],
                    'projects' => [['title' => 'Suivi de cultures — exercice encadré', 'description' => 'Tenue du carnet de laboratoire, relevé des mesures et mise en forme dans un tableur. Travail collectif réalisé en formation, hors production.', 'technologiesUsed' => 'Tableur et carnet de laboratoire']],
                    'languages' => [['language' => 'Anglais', 'level' => 'B1 — lecture accompagnée de protocoles simples']],
                ],
                'before' => 'Maîtrise du laboratoire et recherche de stage.',
                'after' => 'Préparation de solutions et observation au microscope en travaux pratiques encadrés ; recherche d’un stage de six semaines.',
                'reason' => 'La formation et les travaux pratiques viennent en premier. Une expérience salariée vide est omise ; le texte distingue acquis encadrés et autonomie professionnelle.',
            ]],
            'developpeur-web' => [[
                'name' => ['Lina', 'Moreau'], 'title' => 'Développeuse web PHP / Symfony junior',
                'summary' => 'Développement d’applications de gestion : formulaires, données relationnelles et contrôle des accès. Recherche d’un premier poste backend pour poursuivre cette pratique en équipe.',
                'sections' => [
                    'projects' => [
                        ['title' => 'Réservation de matériel — projet personnel', 'description' => 'Développement du catalogue et du formulaire de réservation. Vérification des conflits de dates et tests des refus d’accès aux réservations d’un autre compte.', 'technologiesUsed' => 'PHP, Symfony, Twig, SQL, Git'],
                        ['title' => 'Documentation de la démonstration', 'description' => 'Rédaction du README : installation, jeux de données fictives et limites connues. Aucun dépôt confidentiel ni secret publié.'],
                    ],
                    'educations' => [['degree' => 'Formation au développement web', 'institution' => 'Organisme fictif', 'startDate' => '2025-09-01', 'endDate' => '2026-06-01', 'description' => 'Programmation orientée objet, bases relationnelles et travail avec Git.']],
                    'languages' => [['language' => 'Anglais technique', 'level' => 'Lecture de documentation avec recherche de vocabulaire']],
                ],
                'before' => 'Symfony : 90 %, Git : 80 %.',
                'after' => 'Formulaire de réservation développé avec Symfony ; tests des conflits de dates et des accès entre comptes.',
                'reason' => 'Les projets précèdent la formation pour montrer le travail réalisé. Une contribution vérifiable remplace les pourcentages de maîtrise ; les liens publics ne sont ajoutés qu’après vérification.',
            ]],
            'sans-experience' => [[
                'name' => ['Alex', 'Robin'], 'title' => 'Préparateur de commandes débutant',
                'summary' => 'Recherche d’un premier emploi en logistique à partir de juillet 2027. Participation au tri et au rangement lors d’une collecte alimentaire associative.',
                'sections' => [
                    'experiences' => [['position' => 'Bénévole — collecte alimentaire', 'companyName' => 'Association fictive Entraide locale', 'startDate' => '2026-11-01', 'endDate' => '2026-11-01', 'description' => 'Tri des produits selon les consignes, vérification des dates et rangement des cartons avec l’équipe. Participation ponctuelle, sans responsabilité d’encadrement.']],
                    'projects' => [['title' => 'Vente personnelle de livres d’occasion', 'description' => 'Inventaire, classement par catégorie et suivi des articles vendus dans un tableau.', 'technologiesUsed' => 'Tableur']],
                    'educations' => [['degree' => 'Baccalauréat général', 'institution' => 'Lycée de démonstration', 'endDate' => '2026-06-01']],
                ],
                'before' => 'Aucune expérience, mais très motivé.',
                'after' => 'Collecte alimentaire bénévole : tri, contrôle des dates et rangement des cartons selon les consignes.',
                'reason' => 'L’activité bénévole est clairement nommée ; elle ne devient pas un emploi inventé. Les tâches donnent au recruteur des points concrets à aborder en entretien.',
            ]],
            'ats' => [[
                'name' => ['Morgan', 'Leroy'], 'title' => 'Chargé de support client',
                'summary' => 'Expérience en traitement des demandes par email, qualification des incidents et rédaction de fiches d’aide. Recherche d’un poste de support au sein d’une équipe de services.',
                'sections' => [
                    'experiences' => [['position' => 'Chargé de support client', 'companyName' => 'Entreprise fictive Services Exemple', 'startDate' => '2024-09-01', 'endDate' => '2025-08-01', 'description' => 'Réponse aux demandes par email, qualification des incidents dans un outil de suivi et rédaction de fiches d’aide pour les questions récurrentes.']],
                    'educations' => [['degree' => 'Formation relation client', 'institution' => 'Centre de démonstration', 'endDate' => '2024-06-01']],
                    'languages' => [['language' => 'Anglais', 'level' => 'B1 — réponses écrites simples']],
                ],
                'before' => 'Magicien du client — outils divers.',
                'after' => 'Chargé de support client — demandes par email, qualification des incidents et fiches d’aide.',
                'reason' => 'Des intitulés usuels et des dates explicites rendent le parcours compréhensible. Le modèle est le rendu Classique de PerformCV ; sa lecture doit encore être vérifiée dans le PDF obtenu.',
            ]],
            'bts-sio' => [
                [
                    'name' => ['Sam', 'Dubois'], 'title' => 'Stage développement — BTS SIO option SLAM',
                    'summary' => 'Deuxième année du BTS Services informatiques aux organisations, option Solutions logicielles et applications métiers. Recherche d’un stage de développement ; dates à adapter au calendrier de l’établissement.',
                    'sections' => [
                        'educations' => [['degree' => 'BTS SIO — option SLAM, deuxième année', 'institution' => 'Lycée de démonstration', 'startDate' => '2025-09-01', 'description' => 'Formation en cours : développement d’applications et utilisation de bases relationnelles.']],
                        'projects' => [['title' => 'Gestion de tickets — projet pédagogique', 'description' => 'Équipe de trois. Contribution personnelle : formulaire de création, validation des champs et recherche par statut. Tests des changements de statut et présentation du schéma des données.', 'technologiesUsed' => 'Programmation orientée objet, SQL, Git']],
                        'languages' => [['language' => 'Anglais technique', 'level' => 'Lecture de notices et de documentation avec aide']],
                    ],
                    'before' => 'Informatique : développement et réseau.',
                    'after' => 'BTS SIO SLAM : formulaire de tickets, validation des champs et requêtes SQL paramétrées dans un projet pédagogique.',
                    'reason' => 'L’option et la contribution applicative orientent la candidature vers le développement, sans prétendre couvrir toutes les compétences du diplôme.',
                ],
                [
                    'name' => ['Jules', 'Simon'], 'title' => 'Stage systèmes et réseaux — BTS SIO option SISR',
                    'summary' => 'Deuxième année du BTS Services informatiques aux organisations, option Solutions d’infrastructure, systèmes et réseaux. Recherche d’un stage de support et d’administration sous supervision.',
                    'sections' => [
                        'educations' => [['degree' => 'BTS SIO — option SISR, deuxième année', 'institution' => 'Lycée de démonstration', 'startDate' => '2025-09-01', 'description' => 'Formation en cours : services informatiques, systèmes et réseaux. Dates de stage à confirmer avec l’établissement.']],
                        'projects' => [['title' => 'Laboratoire réseau pédagogique', 'description' => 'Configuration de comptes et de deux segments réseau dans un environnement virtualisé. Tests de connectivité et vérification des accès autorisés. Documentation de la procédure de restauration, hors infrastructure de production.', 'technologiesUsed' => 'Linux, virtualisation, adressage IP et outils de diagnostic']],
                        'languages' => [['language' => 'Anglais technique', 'level' => 'Compréhension de messages de diagnostic courants']],
                    ],
                    'before' => 'Administrateur de tout le réseau.',
                    'after' => 'Laboratoire virtualisé : configuration de comptes, segmentation et tests de connectivité sous encadrement.',
                    'reason' => 'Le laboratoire montre une démarche de configuration et de vérification. Les limites sont explicites ; aucune expérience de production n’est inventée.',
                ],
            ],
            default => throw new \InvalidArgumentException('Unknown editorial guide.'),
        };

        return array_map($this->context(...), $examples);
    }

    private function context(array $example): array
    {
        $defaults = array_fill_keys(['position', 'companyName', 'location', 'startDate', 'endDate', 'description', 'degree', 'institution', 'fieldOfStudy', 'title', 'technologiesUsed', 'url', 'language', 'level'], null) + ['isCurrent' => 0];
        $sections = [];
        foreach ($example['sections'] as $key => $entries) {
            $sections[$key] = array_map(static fn (array $entry): array => $entry + $defaults, $entries);
        }

        return $example + ['render' => [
            'profile' => ['title' => $example['title'], 'summary' => $example['summary'], 'location' => 'France — lieu à personnaliser', 'website' => null, 'linkedinUrl' => null, 'githubUrl' => null],
            'owner' => ['firstName' => $example['name'][0], 'lastName' => $example['name'][1], 'email' => 'candidature@example.test', 'phone' => null],
            'content' => $sections, 'skill_names' => [], 'theme' => 'classique', 'layout' => 'classic', 'color' => 'ink', 'font' => 'sans', 'is_preview' => true, 'editorial_example' => true,
        ]];
    }
}
