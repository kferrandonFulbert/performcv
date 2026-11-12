# Instructions pour les agents IA — PerformCV

## Portée et méthode de travail

Ce fichier cadre les interventions dans tout le dépôt. Communiquer en français et conserver les noms techniques du code en anglais. Lire les fichiers concernés avant de modifier le projet. Privilégier des changements ciblés et respecter les modifications locales déjà présentes.

Le cadrage métier ci-dessous est déduit des entités de `src/Entity/`. Les parcours implémentés sont décrits dans `README.md` et vérifiés dans `tests/`. Vérifier le code avant d'affirmer qu'un parcours utilisateur existe. Mettre ce document à jour lorsque le modèle ou les conventions évoluent.

## Finalité du projet

PerformCV est une application de gestion de profils professionnels, de composition de CV personnalisés à partir de modèles, de partage de CV et de suivi des consultations.

Le parcours métier suggéré par les données est : compte utilisateur → profil professionnel et rubriques → instance de CV utilisant un modèle → lien de partage → consultations.

`subscription_type` ne porte aucune règle d'abonnement. Les modèles du catalogue comprennent deux gratuits et six à 390 centimes ; les achats uniques via Stripe Checkout sont implémentés, mais nécessitent les clés et le webhook de l'environnement cible. Ne pas annoncer de paiement réel actif sans vérification. Voir `docs/modeles-et-paiements.md`.

## Socle technique constaté

- PHP `8.5.x` en développement et en production, Symfony `7.4.*` LTS ; dépendances déclarées dans `composer.json`, versions installées dans `composer.lock`. La plateforme Composer est fixée à PHP 8.5.0. Aucune compatibilité PHP 8.2/8.3/8.4 n'est ciblée.
- Doctrine ORM `^3.7.6`, DBAL `^3.10.7`, Doctrine Migrations ; mapping par attributs PHP.
- Twig, AssetMapper et Importmap ; Stimulus et Turbo. Conserver ce socle pour les développements frontend ordinaires.
- PHPUnit `^9.6.38` et Symfony PHPUnit Bridge ; configuration dans `phpunit.xml.dist`.
- Namespace applicatif `App\`, tests `App\Tests\` ; autoload PSR-4.
- Connexion à la base via `DATABASE_URL`. Le développement et la production utilisent MySQL avec InnoDB ; le mode local isolé et les tests par défaut utilisent SQLite.
- `bin/local-console` et `public/local.php` proposent un mode local isolé avec SQLite dans `.runtime/`. Les tests imposent une autre base dédiée et les caches sont séparés par version PHP. Les objets paresseux natifs sont activés dans `config/packages/doctrine.yaml` pour PHP 8.5.

Ne pas ajouter un framework frontend, une chaîne Node, une dépendance ou une montée de version sans nécessité liée à la tâche.

## Carte du domaine

Toutes les entités ont un identifiant `id` entier généré. Les liens indiqués sont des associations ORM `ManyToOne` unidirectionnelles obligatoires, avec colonnes SQL `*_id` conservées.

| Entité | Rôle et données principales | Liens métier |
| --- | --- | --- |
| `Users` | Email, `password_hash`, nom, prénom, téléphone, dates de création/modification, `subscription_type` initialisé à `free` | Propriétaire des profils via `Profiles.user_id` |
| `Profiles` | Titre, résumé, localisation, site et liens sociaux, photo, visibilité, slug | `user_id` → `Users` |
| `Experiences` | Entreprise, poste, description, dates, poste actuel, localisation, ordre | `profile_id` → `Profiles` |
| `Educations` | Établissement, diplôme, domaine d'études, dates, description, ordre | `profile_id` → `Profiles` |
| `Certifications` | Nom, organisme, dates d'obtention/expiration, identifiant et URL du justificatif | `profile_id` → `Profiles` |
| `Projects` | Titre, description, URL, image, technologies, dates, ordre | `profile_id` → `Profiles` |
| `Languages` | Langue et niveau | `profile_id` → `Profiles` |
| `Skills` | Catalogue de compétences : nom et catégorie | Référencé par `ProfileSkills.skill_id` |
| `ProfileSkills` | Compétence d'un profil, niveau, années d'expérience | `profile_id` → `Profiles`, `skill_id` → `Skills` |
| `CvTemplates` | Nom, description, slug unique nullable, prix en centimes, activité, orientation ATS, aperçu, styles CSS, structure HTML, indicateur premium | Référencé par `CvInstances.template_id` |
| `TemplatePurchases` | Montant, devise, session Checkout unique, paiement Stripe unique, création et confirmation nullable | `user_id` → `Users`, `template_id` → `CvTemplates`, couple unique |
| `CvInstances` | CV personnalisé : styles, visibilité des sections en JSON, palette, police, dates | `profile_id` → `Profiles`, `template_id` → `CvTemplates` |
| `CvShares` | Jeton, visibilité, protection par mot de passe, hash, expiration, création | `cv_instance_id` → `CvInstances` |
| `CvViews` | Consultation : IP, user-agent, date, référent | `cv_share_id` → `CvShares` |

Ne pas imposer un seul profil par utilisateur, un seul CV par profil ou un seul partage par CV : ces unicités ne sont pas déclarées. Une instance de CV référence un profil ; aucun instantané des rubriques n'est stocké dans cette entité. Ne pas promettre qu'un CV restera figé lorsque le profil change.

## Particularités du modèle à préserver

- Les classes sont au pluriel (`Users`, `Profiles`, etc.), les propriétés en `snake_case`, les accesseurs en camelCase et les setters renvoient `static`. Respecter les noms et signatures existants lors d'une intervention ciblée.
- Les propriétés `user`, `profile`, `skill`, `template`, `cv_instance` et `cv_share` portent des associations `ManyToOne` unidirectionnelles, chargées paresseusement. Les setters reçoivent les entités ; les getters `get*Id()` calculent l'identifiant depuis l'association sans champ scalaire dupliqué. Les critères et DQL utilisent les noms d'association, le SQL brut conserve les colonnes `*_id`. Aucune cascade ORM ni collection inverse n'est ajoutée.
- La migration `Version20261112000100` ajoute les 14 clés étrangères restrictives du mapping après vérification des orphelins. Vérifier son application avant de supposer ces garanties. Les suppressions restent explicites et transactionnelles dans `WorkspaceWriter`. Voir `docs/associations-doctrine.md`.
- Une conversion vers des associations ORM constitue une évolution explicite du modèle : examiner les appels existants, le mapping, les migrations et les tests concernés.
- Unicités déclarées : `Users.email`, `Profiles.slug`, `CvShares.share_token`. Aucune unicité composite n'est déclarée sur `ProfileSkills`.
- Le catalogue ajoute `CvTemplates.slug` unique nullable. `TemplatePurchases` impose l'unicité du couple compte/modèle et des identifiants Stripe ; ses liens sont des associations obligatoires vers `Users` et `CvTemplates`. `purchased_at` doit être renseigné pour accorder le droit d'utilisation.
- Plusieurs propriétés PHP sont initialisées à `null` alors que leur colonne est obligatoire. Lire les attributs `ORM\Column` et les signatures des setters ; un getter nullable ne rend pas la valeur facultative à l'enregistrement.
- Les indicateurs `is_public`, `is_current`, `password_protected`, `is_premium` sont actuellement des entiers nullables. Préserver leur contrat ; définir explicitement le traitement de `null` dans les nouveaux parcours.
- `Profiles.is_public` vaut initialement `0`, `CvShares.is_public` vaut initialement `1`. Ce sont deux niveaux de visibilité distincts ; ne pas déduire une politique d'accès de ces seuls défauts.
- Les dates utilisent `DateTimeInterface` avec des types Doctrine mutables. Ne pas les convertir silencieusement en chaînes ou en types immuables.
- Des dates déclarent un défaut SQL `CURRENT_TIMESTAMP`, mais les entités ne contiennent pas de mécanisme de mise à jour automatique. Ne pas supposer que `updated_at` évolue seul.
- `sections_visibility` est un tableau JSON nullable dont les clés ne sont pas définies dans le modèle. `technologies_used` reste du texte. Les niveaux de langue/compétence et les offres ne sont pas des enums déclarées.

## Organisation des développements

- `GuideController` et `templates/guides/` portent huit pages publiques éditoriales à routes explicites, liées depuis l’accueil. `PublicSeo` autorise leurs canoniques et leur présence au sitemap. La page de lettre de motivation fournit un exemple à personnaliser ; aucun générateur de lettres n’est implémenté.

- `src/Entity/` : modèle persistant et mapping ; `src/Repository/WorkspaceRepository.php` : lectures métier. Les repositories par entité restent principalement des squelettes de `ServiceEntityRepository`.
- `src/Controller/`, `src/Form/`, `src/Service/`, `src/Security/` : parcours HTTP, formulaires, services métier et voter de propriété. Les suppressions passent par les transactions de `WorkspaceWriter`.
- `config/` : configuration de l'application ; `migrations/` : évolutions versionnées du schéma ; `tests/` : vérifications automatisées.
- `templates/` : vues Twig ; `assets/` et `importmap.php` : ressources frontend ; `public/` : point d'entrée et ressources publiques.
- Pour de nouvelles fonctionnalités, garder des contrôleurs courts, placer l'orchestration métier dans des services injectés et centraliser les requêtes dans les repositories. Créer les répertoires nécessaires sans présumer de leur contenu actuel.
- Valider les entrées aux frontières de l'application. Utiliser des requêtes paramétrées et des listes de champs autorisés pour les tris et mises à jour exposés au client.
- Ne pas modifier `vendor/`, les caches de `var/` ou les fichiers générés pour corriger du code applicatif. Ne pas écraser les fichiers de verrouillage hors du périmètre de la tâche.

## Règles pour les futurs parcours métier

Ces règles guident les implémentations ; les simples getters et setters ne suffisent pas à les garantir. Réutiliser les formulaires et services qui les appliquent déjà.

- Vérifier l'existence des références avant de créer ou modifier une rubrique, une instance ou un partage.
- Vérifier la propriété du profil et de toutes ses ressources pour chaque lecture privée, écriture et suppression. Un identifiant fourni par le client ne constitue pas une autorisation.
- Définir et valider la cohérence des dates lorsqu'elles sont renseignées : début/fin, obtention/expiration et expérience en cours. Ne pas inventer une date absente.
- Pour les rubriques ayant `order_index`, appliquer un tri explicite et stable ; définir le traitement des valeurs nulles avant de créer un réordonnancement.
- Préserver la confidentialité des sections masquées dans tous les rendus et exports concernés, pas uniquement par un masquage visuel CSS.
- Définir le sort des ressources liées avant d'implémenter une suppression. Utiliser une transaction pour les écritures qui doivent rester cohérentes ensemble.
- Ne pas ajouter de génération de texte par IA, d'export PDF, de facturation ou de statistiques avancées sur la seule base du nom du projet ou d'un champ.
- Le catalogue est initialisé par `app:templates:seed`. L'aperçu des modèles payants est autorisé uniquement après contrôle de propriété, sans écriture. L'enregistrement et les rendus enregistrés contrôlent les droits via `TemplateAccess`. Les styles viennent de la liste de confiance `TemplateCatalog`, jamais des champs HTML/CSS stockés. Seul un webhook Stripe signé et payé confirme un achat ; le retour Checkout ne l'accorde pas.

## Authentification, partage et données personnelles

État actuel : `Users` implémente les interfaces Symfony Security, avec un fournisseur Doctrine par email et le rôle fixe `ROLE_USER`. Le pare-feu utilise `form_login`, protège connexion et déconnexion par CSRF, et réserve `/app` aux comptes connectés. `ProfileVoter` contrôle la propriété ; les contrôleurs vérifient les associations et la propriété avant l'accès aux ressources liées. La session sérialise une empreinte CRC32C du hash de mot de passe pour permettre son invalidation sans stocker le hash complet.

La limitation des tentatives utilise un subscriber et des fichiers verrouillés par IP (empreinte dans le nom), sur un seul hôte. Ne pas la considérer comme distribuée. Le partage protégé est déverrouillé pour une heure et son état est revérifié à chaque accès. Les sections masquées sont exclues des lectures du rendu. Les données HTML/CSS libres sont ignorées par les présentations internes.

Les informations de l’éditeur sont centralisées dans le paramètre `performcv.legal`. Les pages `/mentions-legales`, `/confidentialite`, `/conditions-utilisation` et `/cookies` décrivent le service, également accessible aux mineurs. Le compteur de consultations fonctionne côté serveur sans consentement, cookie de mesure ni repère de comptage en session. Chaque affichage GET réussi d’un CV partagé enregistre uniquement le lien et la date, sans IP, user-agent, référent ni identifiant du visiteur. Les rechargements sont comptés ; les totaux ne représentent pas des visiteurs uniques. La page `/cookies` est informative ; les anciens cookies `performcv_consent` sont ignorés pour le comptage. Le marketing et les balises Google restent désactivés ; toute activation exige une nouvelle information et une nouvelle version de consentement. Les tâches de conservation et leurs limites d’exploitation sont documentées dans `docs/exploitation-donnees-personnelles.md` ; ne pas annoncer une tâche cron ou une expiration de sauvegardes comme active sans preuve d’hébergement.

Pour toute fonctionnalité concernée :

- Hacher les mots de passe avec un mécanisme adapté ; ne jamais stocker de mot de passe en clair. Exclure les hashes, secrets et jetons des sérialisations et journaux ordinaires.
- Protéger les actions modifiant des données contre les accès non autorisés et, pour les formulaires utilisant une session, contre les attaques CSRF.
- Générer des jetons de partage imprévisibles et respecter leur unicité. Vérifier expiration, visibilité et protection par mot de passe avant de fournir le contenu partagé.
- Ne pas confondre accès par slug, accès propriétaire et accès par lien de partage. Formaliser leur articulation dans le code et les tests du parcours concerné.
- Échapper les contenus utilisateur dans les vues. Les champs `html_structure`, `css_styles` et `custom_styles` exigent une politique explicite de confiance et de filtrage ; ne pas les rendre arbitrairement avec `raw` et ne pas exécuter un template fourni par un utilisateur.
- Valider les URL et les fichiers reçus avant usage. Une URL enregistrée ne justifie pas son téléchargement côté serveur.
- Limiter collecte, exposition et conservation des IP, user-agents et référents de `CvViews` au besoin du parcours. Utiliser des données fictives dans les tests.
- Ne pas afficher ni versionner les secrets de connexion ou le contenu des fichiers d'environnement locaux.

## Documentation des bibliothèques avec Context7

Pour une question ou une modification dépendant d'une API de bibliothèque, d'un framework, d'un SDK, d'un outil CLI ou d'un service cloud, consulter Context7 avant de choisir l'implémentation, même pour une API connue.

1. Commencer par `resolve-library-id` avec le nom et le besoin, sauf si un identifiant exact `/org/project` est fourni.
2. Choisir la source la plus pertinente et la version compatible avec les dépendances du projet.
3. Appeler `query-docs` avec une question précise par concept ; regrouper seulement les concepts dont l'interaction constitue la question.
4. Si une signature, un défaut ou un comportement interne reste ambigu et que `query-code` est disponible, rechercher le symbole ou le message puis lire les lignes indiquées. Citer le fichier et les lignes utilisés. Limiter à six appels ; si l'indexation est en cours, réessayer au maximum deux fois.

Cette recherche n'est pas nécessaire pour une simple lecture du modèle, une modification documentaire ou un refactoring indépendant d'une API externe. Ne pas appliquer automatiquement une recommandation destinée à une version plus récente.

## Journalisation des actions

`AuditEvents` / `audit_events` contient uniquement date UTC, acteur nullable, action et référence de ressource, identifiant de corrélation. Ses références sont scalaires, sans clé étrangère, pour préserver les traces après suppression ; cette exception volontaire ne modifie pas les associations métier. Le modèle est en lecture seule, sans setters. Les identifiants du journal restent des données de traçabilité, pas des statistiques anonymes.

`WorkspaceWriter` et `TemplatePayment` écrivent la trace métier dans la même transaction que l’opération. Toute nouvelle écriture directe ou en masse doit intégrer explicitement `AuditTrail`. L’inscription est attribuée au nouveau compte ; un webhook validé a un acteur nul. Les consultations publiques, aperçus et formulaires invalides ne produisent pas de succès métier. Les suppressions de parents couvrent leurs descendants sans une ligne par enfant supprimé en masse.

`SecurityAuditSubscriber` journalise les connexions, déconnexions, réponses 403 et 429 dans le canal JSON `audit_security`, hors du flux conditionnel des erreurs. Ne jamais ajouter email saisi, contenu de CV, mot de passe/hash, IP, URL, cookie, token ou requête complète au contexte des journaux dédiés. Une panne de ce fichier n’empêche pas la déconnexion ; une panne de l’audit en base annule l’écriture métier.

La migration `Version20261112000200` ajoute la table. Les commandes `app:audit:list` (filtre acteur, 100 lignes maximum) et `app:audit:purge` (`--dry-run`) sont réservées à l’exploitation. Conservation cible : six mois, avec purge des fichiers de sécurité anciens. Aucune route web d’audit, aucun cron et aucun collecteur externe ne sont supposés actifs. Voir `docs/journalisation.md` pour les limites d’intégrité et les droits d’exploitation.

## Vérification et livraison

- Examiner le diff et vérifier que le changement reste dans le périmètre demandé.
- Pour du PHP, vérifier la syntaxe des fichiers modifiés. Pour une fonctionnalité, lancer les tests pertinents via le lanceur existant `bin/phpunit` et compléter les scénarios métier utiles.
- Pour une modification de mapping, vérifier sa cohérence et examiner la migration générée avant toute application. Ne pas utiliser une synchronisation forcée du schéma comme remplacement d'une migration versionnée.
- Exécuter les tests de base de données uniquement sur une base dédiée aux tests, après vérification de la connexion. Ne pas appliquer de migration à une base partagée ou de production sans autorisation explicite.
- Ne pas présenter la présence de `tests/bootstrap.php` ou un lancement sans tests comme une couverture fonctionnelle. Une modification documentaire seule ne nécessite pas de tests applicatifs.
- Indiquer dans le compte rendu les fichiers modifiés, les vérifications réellement effectuées et les limites rencontrées. Ne jamais annoncer un test réussi sans l'avoir exécuté.

## Photo de profil

Le formulaire de profil accepte une photo JPEG, PNG ou WebP (2 Mo et 4 000 × 4 000 pixels maximum). `ProfilePhoto` stocke les fichiers sous `var/profile-photos/<environnement>/`, hors du dossier public ; ce dossier doit être conservé lors des déploiements. `profile_picture_url` contient uniquement le nom généré. La clé `photo` de `sections_visibility` commande son inclusion dans chaque CV, avec absence équivalente à `false`. Les rendus autorisés intègrent les données de l’image seulement si cette option est activée ; le profil public seul ne publie pas la photo. Le remplacement, le retrait et la suppression du profil ou du compte effacent les fichiers concernés.
