# Retest des constats cyber du 11 novembre 2026

Corrections ciblées du rapport historique
`audits/2026-11-11/audit-cybersecurite.md`, dans le dépôt local uniquement.
La cible convenue est PHP 8.5.x en développement et en production.

## CYB-01 — Version PHP annoncée

Résolu par alignement du contrat de plateforme, sans compatibilité PHP 8.3 :

- `composer.json` exige PHP `~8.5.0`, soit PHP 8.5.x, et fixe la plateforme
  de résolution à 8.5.0.
- `composer.lock` a été régénéré par Composer sans installation et sans
  changement de version des dépendances.
- Suppression de `config/packages/doctrine_lazy.php`, redondant avec
  `enable_native_lazy_objects: true` dans `config/packages/doctrine.yaml`.
- Mise à jour du socle documenté dans `AGENTS.md`.

L'échec historique sous PHP 8.3 reste une observation valide de l'audit initial,
mais cette version est désormais explicitement hors du périmètre supporté.

## CYB-02 — Scripts et CSP

La suppression de GTM et des scripts inline était déjà présente avant cette
intervention. Aucun assouplissement de la CSP n'a été nécessaire.
`LegalPagesTest` vérifie désormais sur sept pages publiques rendues :

- présence de `script-src 'self'` ;
- absence de `unsafe-inline`, `unsafe-eval`, scripts inline et attributs
  gestionnaires d'événements `on…` ;
- scripts externes référencés exclusivement par un chemin local ;
- maintien du contrôle existant d'absence de balises Google.

Résolu sur les réponses HTML et en-têtes testés. Le contrôle des violations
CSP et des interactions dans un navigateur reste non effectué.

## Vérifications exécutées

- PHP 8.5.1 : `php bin/phpunit`, 43 tests et 807 assertions réussis.
  Le bootstrap impose `.runtime/performcv_suite_test.db`, base SQLite dédiée.
- `php -l tests/Functional/LegalPagesTest.php` : syntaxe valide.
- `composer update --lock --no-install --no-scripts --no-plugins --no-interaction` :
  aucune version de paquet modifiée, aucun avis de vulnérabilité retourné.
- `composer validate --no-check-publish --no-plugins` : valide avec un
  avertissement préexistant sur la contrainte `*` de `symfony/apache-pack`.
- `composer check-platform-reqs --lock --no-plugins` : exigences satisfaites.
- `git diff --check` : aucune erreur de whitespace.

Le premier appel Composer a échoué à résoudre Packagist dans le bac à sable ;
la commande a ensuite réussi avec l'accès nécessaire.
Les tests ont utilisé les permissions nécessaires à l'écriture du cache local.

## Limites

Aucun déploiement ni test de PHP 8.3 effectué. TLS, cookies Secure réels,
configuration du proxy et du serveur, debug en production, sauvegardes,
alertes et CI/CD restent à contrôler dans l'hébergement. Ce retest ciblé
ne constitue pas un nouvel audit exhaustif de sécurité.

Références consultées le 11 novembre 2026 :

- [MDN : script-src](https://developer.mozilla.org/en-US/docs/Web/HTTP/Reference/Headers/Content-Security-Policy/script-src)
- Documentation Composer consultée via Context7 (`/composer/composer`) pour
  l'actualisation du verrou sans installation et la séparation des exigences
  de plateforme des versions des paquets.
