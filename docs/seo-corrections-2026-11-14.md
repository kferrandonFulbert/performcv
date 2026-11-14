# Corrections SEO des cinq constats modérés — 14 novembre 2026

Périmètre : SEO-T-01, SEO-T-03, SEO-T-05, SEO-C-01 et SEO-C-02 de l’audit indépendant du 14 novembre. L’audit initial reste un état historique ; les changements ci-dessous sont locaux, sans déploiement.

| Constat | Correction | Vérification | État |
|---|---|---|---|
| SEO-T-01 — variantes www | Normalisation Apache vers `https://performcv.fr` avec conservation du chemin encodé et des paramètres. Suppression du détour par `index.php` dans le même ensemble de règles. Les hôtes locaux ne sont pas redirigés vers la production. | Serveur Apache 2.4.66 temporaire sur loopback, fichiers fictifs et certificat local : 11 requêtes HTTP/HTTPS contrôlées, toutes conformes. | Corrigé et vérifié localement ; retest d’hébergement après déploiement. |
| SEO-T-03 — recherches indexables | Recherches non vides et entrées invalides en `X-Robots-Tag: noindex, follow`. Les listes sans recherche restent indexables avec leur propre canonique, y compris pagination. Aucun blocage robots.txt. | Tests de recherche avec résultat, sans résultat, espaces, entrée invalide et pagination. | Corrigé et vérifié localement. |
| SEO-T-05 — URL multiples d’un CV | `CvSeo` choisit la fiche d’annuaire si le CV est admissible, sinon le plus ancien partage actif sans protection ni hash de la même instance. Les profils gardent leur propre canonique car ils ne représentent pas nécessairement le contenu d’un CV. Les partages protégés restent sans canonique et en noindex, même après déverrouillage. | Tests de publication/retrait, expiration, révocation, plusieurs liens, présence d’un hash, CV distinct avec d’autres rubriques, profil public et partage protégé d’un CV également publié. | Corrigé et vérifié localement. |
| SEO-C-01 — exemples à approfondir | Exemples complets pour les huit guides, avec le rendu Classique du créateur, choix des rubriques, formulation avant/après et démarche de préparation. Deux exemples BTS SIO distincts : SLAM et SISR. Les données restent fictives et ne sont jamais enregistrées en base. | Rendu HTTP des huit guides avec Twig strict ; présence des exemples, d’un seul H1 de page et des deux variantes BTS. Syntaxe des templates partagés contrôlée. | Corrigé et vérifié localement ; utilité et résultats de recherche à suivre. |
| SEO-C-02 — responsabilité et sources | Nom de l’éditeur issu de `performcv.legal`, date de révision explicite, contact de correction et références Europass, France Travail, France compétences et Onisep selon les sujets. Pas de relecture professionnelle ni de qualification inventée. | Références consultées lors de la modification ; métadonnées éditoriales vérifiées dans les huit rendus. | Corrigé et vérifié localement. |

## Contrôles exécutés

- `php bin/phpunit tests/Functional/SeoTest.php` : **6 tests, 338 assertions, réussis**.
- Tests fonctionnels ciblés (`testShareCanonicals`, `testDirectorySearch`, `testDirectoryValidates`, `testPasswordProtected`, `testProfileVisibility`, `testSocialCards`, `testDirectorySeo`) : **8 tests, 105 assertions, réussis**.
- Total de ces deux exécutions finales : **14 tests, 443 assertions**.
- Syntaxe PHP : les huit fichiers PHP applicatifs/tests modifiés ou ajoutés ont passé `php -l`.
- `php bin/local-console lint:twig templates --env=test` : **40 fichiers Twig valides**.
- `git diff --check` : exécuté, sans erreur.
- Apache local : HTTP vers HTTPS, www HTTP/HTTPS, index.php, chemin et paramètres, encodage du chemin, localhost et absence de boucle sur l’origine HTTPS canonique. Les premières tentatives de ce banc local ont échoué car sa configuration minimale ne chargeait pas le module MIME ; le banc complété a passé les 11 contrôles. Les serveurs de test ont été arrêtés. Aucun serveur de production n’a été modifié.

## Limites et erreurs hors périmètre

`php bin/phpunit` a été tenté : la collecte est bloquée par le fichier préexistant absent `migrations/Version20261112000200.php`, requis par `tests/Integration/AuditMigrationTest.php`. Aucune migration n’a été recréée ni appliquée pour contourner ce problème.

L’exécution de tout `tests/Functional` a également été tentée : 71 tests exécutés, avec une attente ancienne sur les recherches corrigée ensuite et un échec sur `testTemplateCatalogueSeedIsIdempotentAndHasEightModels`, dont le sélecteur de lien « Modifier le CV » est vide. Le template de profil et son contrôleur n’ont pas été modifiés par cette intervention. La suite fonctionnelle entière n’est donc pas annoncée comme réussie ; les scénarios SEO concernés ont été relancés et passent.

Les tests de base utilisent exclusivement la connexion SQLite dédiée imposée par `tests/bootstrap.php`, distincte de la base locale et de la production. Aucun fichier de dépendances ni de verrouillage n’a été modifié.

Le déploiement et la configuration de terminaison TLS/proxy de l’hébergement restent à vérifier. Aucun classement, gain de trafic, changement d’indexation Google ou validation humaine des conseils n’est affirmé. Les constats mineurs et les mesures non disponibles de l’audit initial ne sont pas inclus dans cette demande.
