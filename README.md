# PerformCV

Application Symfony 7.4 LTS / PHP 8.2+ pour gérer des profils professionnels, composer des CV et les partager. Interface Twig en français, responsive, sans chaîne Node.

## Fonctionnalités

- Inscription, connexion, déconnexion, coordonnées, changement de mot de passe et suppression du compte avec vérification du mot de passe.
- Plusieurs profils par compte, privés par défaut ; publication facultative via une adresse unique.
- Création, modification et suppression des expériences, formations, certifications, projets, langues et compétences. Ordre d'affichage pour les rubriques qui possèdent `order_index`.
- Plusieurs CV par profil : présentations Classique, Atelier et Horizon, trois palettes, deux familles de polices et sélection des rubriques visibles.
- Impression et enregistrement en PDF via la boîte d'impression du navigateur.
- Liens indépendants de la visibilité du profil : expiration facultative, mot de passe facultatif et révocation.
- Comptage des consultations par session sur 24 heures, sans IP, user-agent ou référent dans les statistiques.

Les CV utilisent les données actuelles du profil : ce ne sont pas des instantanés. Un profil public expose toutes ses rubriques ainsi que le nom et les coordonnées du compte. Un CV partagé expose ses rubriques sélectionnées ainsi que son en-tête et ses coordonnées.

## Démarrage local indépendant

Prérequis : Composer et PHP 8.2+ avec PDO SQLite, intl, mbstring, XML, ctype et iconv. Les parcours sont vérifiés avec PHP 8.3 et PHP 8.5. Doctrine utilise les objets paresseux natifs dès PHP 8.4 ; les caches locaux sont séparés par version PHP.

Depuis la racine du projet :

```powershell
composer install --no-scripts
php bin/local-console doctrine:migrations:migrate --no-interaction
php bin/local-console app:templates:seed
php bin/local-console app:demo
php -S 127.0.0.1:8765 -t public public/local.php
```

Ouvrir [PerformCV en local](http://127.0.0.1:8765).

Le mode `local` ignore les fichiers `.env` et utilise exclusivement `.runtime/performcv_local.db`. Il ne modifie pas la base déclarée dans votre configuration. Les commandes peuvent être relancées ; la démonstration n'écrase pas un compte existant.

Compte fictif facultatif : `demo@performcv.test` / `Demo-performcv-2026`. Il contient un profil privé et un CV sans lien public. La commande `app:demo` refuse les environnements autres que `local` et `test`.

Le routeur `public/local.php`, sa clé de développement et le serveur intégré PHP sont destinés à un essai sur l'adresse de boucle locale. Le point d'entrée de déploiement est `public/index.php`.

## Configuration habituelle et base existante

Le point d'entrée standard et `bin/console` conservent le fonctionnement Symfony avec les variables existantes. Définir une clé `APP_SECRET` propre à l'environnement, `DATABASE_URL` et les paramètres d'infrastructure nécessaires ; ne pas versionner de secrets locaux.

La migration `Version20261110000100` crée les tables du domaine et du transport Doctrine Messenger à l'aide du schéma portable DBAL. Son application a été vérifiée sur une nouvelle base SQLite locale. Les autres moteurs n'ont pas été testés : examiner le SQL avec `doctrine:migrations:migrate --dry-run` sur l'environnement prévu avant application.

**Base déjà existante :** cette migration est une initialisation, pas une reprise automatique de données. Comparer le schéma et établir explicitement la version de référence avant application. Les noms d'index sont désormais préfixés par leur table pour éviter les collisions. Ne pas forcer la synchronisation du schéma ni supprimer les tables existantes.

Après une initialisation autorisée de la base cible, lancer `app:templates:seed`. Les modèles sont ajoutés sans écraser les enregistrements existants.

## Vérifications

```powershell
composer validate --strict --no-check-publish
php bin/local-console --env=test lint:container
php bin/local-console --env=test lint:yaml config
php bin/local-console --env=test lint:twig templates
php bin/phpunit
composer audit --locked
```

Les tests imposent `.runtime/performcv_suite_test.db`, sans lire les identifiants locaux, et vérifient le chemin avant de recréer le schéma dédié. Ne pas lancer plusieurs suites simultanément sur ce fichier.

La suite couvre règles de dates, inscription, connexion réelle, rubriques, CV, partage protégé, accès entre comptes, CSRF, entrées interdites, échappement HTML, sections masquées, limitation des tentatives et suppressions liées. Aucun outil de style ou d'analyse statique complet n'était configuré dans le dépôt ; ces contrôles ne les remplacent pas et ne constituent pas une certification de sécurité.

## Architecture et décisions

- `src/Controller/` : parcours HTTP et autorisations ; `src/Form/` : champs autorisés, conversion, validation et CSRF.
- `src/Repository/WorkspaceRepository.php` : lectures métier bornées et filtrées.
- `src/Service/` : règles de dates, accès au partage, présentation, écritures et suppressions transactionnelles.
- `src/Security/ProfileVoter.php` : vérification du propriétaire à travers le profil parent.
- `src/EventSubscriber/` : limitation des tentatives sensibles et en-têtes HTTP.
- `templates/`, `assets/styles/app.css`, `assets/ui.js` : interface, impression et interactions. AssetMapper sert les ressources.
- `AGENTS.md` : cadrage métier et instructions pour les interventions IA.

Les entités et leurs identifiants scalaires sont conservés. Il n'existe pas d'associations ORM, de cascades automatiques ni de garanties de clés étrangères ajoutées par cette implémentation. Les suppressions passent par `WorkspaceWriter` et ses transactions.

Le HTML/CSS libre stocké en base n'est ni exécuté ni injecté dans les vues. Les présentations utilisent les templates internes et des palettes autorisées. Les champs de photo et d'image sont conservés sans gestion de téléversement. Premium, facturation, récupération de mot de passe par email et vérification d'adresse email ne sont pas implémentés.

Bornes applicatives : 100 éléments par rubrique, 50 CV par profil, 20 liens par CV ; profils paginés par 20. Ces limites ne sont pas des quotas commerciaux transactionnels. Les doublons de compétences ne sont pas interdits par une contrainte composite.

## Exploitation

- Servir uniquement `public/`, avec HTTPS, `APP_ENV=prod` et `APP_DEBUG=0`. Définir la version du serveur de base de données. Ne pas exposer le profiler.
- Compiler les ressources AssetMapper et préparer le cache dans l'environnement de déploiement. Configurer les hôtes/proxies de confiance selon l'infrastructure réelle.
- Planifier quotidiennement `php bin/console app:views:purge --env=prod` pour supprimer les consultations de plus de 90 jours et les compteurs de tentatives dont la fenêtre est terminée.
- La limitation regroupe connexion, inscription et saisie du mot de passe d'un partage : 20 soumissions par IP sur une fenêtre fixe de 15 minutes. Elle utilise un fichier verrouillé, nommé par empreinte de l'IP, hors répertoire public. Les compteurs expirés sont effacés par la purge quotidienne. Pour plusieurs nœuds, prévoir un limiteur central adapté ; la protection actuelle est prévue pour un hôte.
- Le déverrouillage d'un CV protégé dure une heure par session ; expiration et révocation sont revérifiées à chaque requête.
- Le kernel impose UTC pour les dates persistées ; les formulaires d'expiration indiquent explicitement UTC.
- Réponses privées sans stockage HTTP, CSP restrictive, protection contre les iframes et référent `no-referrer`.
- Les mots de passe sont hachés par Symfony. La session conserve une empreinte du hash pour son invalidation après changement de mot de passe.
- Les statistiques fondées sur les sessions sont indicatives et ne représentent pas des visiteurs uniques garantis.
- Renseigner les informations sur l'exploitant et les modalités d'exercice des droits avant publication. La page de confidentialité décrit les comportements applicatifs sans remplacer ces informations.

## Références

[Bonnes pratiques Symfony](https://symfony.com/doc/7.4/best_practices.html), [standards](https://symfony.com/doc/7.4/contributing/code/standards.html), [Symfony 7.4 LTS](https://symfony.com/releases/7.4), [sécurité](https://symfony.com/doc/7.4/security.html). Les API ont été vérifiées dans Context7 et, pour les détails de compatibilité, dans les sources installées.
