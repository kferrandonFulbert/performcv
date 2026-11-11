# PerformCV

Application Symfony de gestion de profils professionnels, de création de CV,
de choix de modèles et de partage par lien. Le catalogue propose deux modèles
gratuits et six modèles en achat unique, avec confirmation Stripe côté serveur.

## Documentation

| Besoin | Guide |
|---|---|
| Créer son compte, renseigner son profil et partager un CV | [Utilisation](docs/utilisation.md) |
| Installer ou mettre à jour le site, commandes de production | [Mise en production](docs/mise-en-production.md) |
| Modèles, droits d’utilisation et configuration Stripe | [Modèles et paiements](docs/modeles-et-paiements.md) |
| Purge, conservation, sauvegardes et demandes de droits | [Exploitation des données personnelles](docs/exploitation-donnees-personnelles.md) |
| Canonical, robots.txt et sitemap | [SEO](docs/seo.md) |

## Socle

PHP **8.5.x** pour le web et la ligne de commande, Symfony **7.4 LTS**, Doctrine,
Twig et AssetMapper. Les versions sont verrouillées dans `composer.lock`.
Stripe nécessite les clés et une destination webhook configurées sur le compte
de l’exploitant. Aucun abonnement, facturation automatique ni moteur PDF serveur
n’est implémenté ; le PDF utilise l’impression du navigateur.

## Démarrage local isolé

Depuis la racine du projet, avec PHP 8.5 et Composer disponibles :

```console
composer install --no-scripts
php bin/local-console importmap:install
php bin/local-console assets:install public
php bin/local-console doctrine:migrations:migrate --dry-run --no-interaction
php bin/local-console doctrine:migrations:migrate --no-interaction
php bin/local-console app:templates:seed
php -S 127.0.0.1:8000 -t public public/local.php
```

Le serveur peut ensuite être ouvert sur [127.0.0.1:8000](http://127.0.0.1:8000).
Choisir un autre port si nécessaire. L’option `--no-scripts` évite de démarrer
l’environnement par défaut pendant l’installation ; les ressources sont ensuite
préparées explicitement avec le lanceur local.

`bin/local-console` et `public/local.php` imposent la base SQLite locale
`.runtime/performcv_local.db`. Créer un compte depuis l’interface. Ne pas utiliser
ces points d’entrée pour exploiter la production.

## Guides publics et SEO

Huit pages éditoriales sont accessibles depuis l’accueil : `/cv-etudiant`,
`/cv-alternance`, `/cv-stage`, `/cv-developpeur-web`, `/cv-sans-experience`,
`/cv-ats`, `/exemple-cv/bts-sio` et `/lettre-motivation/alternance`.
Elles proposent des exemples fictifs distincts et des liens vers la création
de profil (ou l’inscription pour les visiteurs). La page de lettre propose un
exemple à personnaliser : aucun générateur de lettres n’est implémenté.
Les routes sont explicites ; aucune page n’est générée à partir d’un mot-clé libre.
Les canoniques et le sitemap utilisent l’origine de confiance de `PublicSeo`.
Les contenus se maintiennent dans `templates/guides/` ; toute nouvelle page
doit aussi être ajoutée au maillage, à la liste SEO et aux tests.

## Tests

```console
php bin/phpunit
```

Le bootstrap impose `.runtime/performcv_suite_test.db`, distincte des bases
locale et de production. Les tests reconstruisent cette base dédiée avec des
données fictives. La procédure de production utilise une installation sans
dépendances de développement : les tests se lancent en développement ou en CI.
