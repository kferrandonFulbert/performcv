# Photos privées et illustrations publiques

## Nettoyage des photos — CYB-PHOTO-01

`PhotoNormalizer` décode JPEG, PNG et WebP avec GD et ré-encode uniquement les pixels. Les métadonnées EXIF (dont GPS, appareil, date), IPTC, XMP et commentaires de l’original ne sont pas recopiées. L’orientation EXIF des JPEG est appliquée avant suppression ; la transparence PNG/WebP est préservée. La sortie est limitée proportionnellement à 1 600 pixels sur son grand côté. JPEG/WebP sont ré-encodés avec une qualité de 90 : le résultat peut légèrement différer de l’original. Les limites d’import restent 2 Mo et 4 000 × 4 000 pixels.

Les extensions PHP `gd` (avec JPEG, PNG et WebP) et `exif` sont désormais requises dans Composer. Les vérifier sur le PHP CLI **et** le PHP web de l’hébergement. Aucune nouvelle bibliothèque n’a été ajoutée, ni version de paquet changée.

Le fichier stocké après import est nettoyé. Le rendu nettoie aussi les anciennes photos en mémoire, avant de les intégrer en data URI dans un CV autorisé ; si le traitement échoue, aucune version originale n’est publiée. Les restrictions de visibilité existantes demeurent applicables. Aucun endpoint public de photo n’a été ajouté.

### Nettoyer le stockage existant

Après déploiement et vérification des extensions sur o2switch :

```console
php bin/console app:photos:clean --env=prod --no-debug --dry-run
php bin/console app:photos:clean --env=prod --no-debug
```

Ces commandes ne sont **pas exécutées en production par cette intervention**. Elles ciblent uniquement `var/profile-photos/prod/`, sans base de données ni modification des références des profils. Les noms opaques restent identiques. Un fichier est remplacé atomiquement seulement après normalisation réussie ; les liens symboliques et noms non conformes sont ignorés. La sortie ne contient ni nom d’origine ni contenu privé. Un échec produit un code de sortie non nul et conserve le fichier concerné ; son rendu ne publie jamais les octets originaux.

Comme les purges de fichiers de sécurité, cette maintenance de fichiers n’écrit pas dans `AuditTrail` métier en base ; les imports et modifications de profil continuent de passer par `WorkspaceWriter` et sa transaction d’audit. La commande trace seulement simulation, succès ou échec dans `audit_security` (`photos.clean_simulated`, `photos.cleaned`, `photos.clean_failed`), sans chemin ni contenu d’image, avec acteur système nul et corrélation technique.

Opération ponctuelle : ne pas ajouter cette commande au cron. Des ré-encodages successifs JPEG/WebP peuvent dégrader l’image. Utiliser la simulation, contrôler le nombre de fichiers et résoudre les erreurs. L’opérateur doit limiter les accès pendant ce traitement si les photos sont modifiées simultanément. Les copies de sauvegarde et les fichiers déjà téléchargés restent distincts : appliquer les règles de conservation aux sauvegardes et ne pas réintroduire les originaux lors d’une restauration. Aucun effacement de copie chez un tiers n’est garanti.

La suppression concerne les **métadonnées intégrées** ; elle n’anonymise pas le visage ou une information privée visible dans les pixels (badge, adresse, document en arrière-plan).

## SEO des images

Choix confirmé par l’éditeur : uniquement les illustrations et modèles publics, sans ajout de référencement des portraits de CV. Deux SVG éditoriaux locaux illustrent Classique ATS et Moderne sur l’accueil. Il s’agit de schémas fictifs de présentation, pas de captures de CV utilisateur ou d’une promesse de rendu identique pour tout contenu.

- URLs stables et descriptives sous `/images/`, disponibles sans compte ni jeton.
- Balises `img` avec alternatives descriptives, dimensions, chargement différé et légendes proches du contenu pertinent.
- SVG avec titre, description, dimensions et aucun script ni ressource externe.
- Extension image du sitemap existant, avec origine de confiance et liste limitée à ces deux fichiers.
- Aucun nom d’utilisateur, photo téléversée ou lien de partage dans les nouvelles entrées image.

Ces éléments facilitent la découverte et la compréhension des illustrations par les moteurs ; aucune position ni indexation n’est garantie. Après déploiement, vérifier les deux URLs, le sitemap et les performances de recherche d’images dans Search Console. Aucun accès Search Console ni envoi du sitemap n’a été réalisé ici.

Références consultées le 13 novembre 2026 : [Google Images](https://developers.google.com/search/docs/appearance/google-images), [sitemaps d’images](https://developers.google.com/search/docs/crawling-indexing/sitemaps/image-sitemaps), [GD](https://www.php.net/manual/en/function.imagecreatefromstring.php), [EXIF](https://www.php.net/manual/en/function.exif-read-data.php), documentation Symfony Console 7.4 et Composer via Context7.

## Vérification locale du 13 novembre 2026

PHP 8.5.1 ; 24 tests unitaires (156 assertions), 4 tests SEO (123 assertions), 2 tests photo fonctionnels (21 assertions) et 2 tests de pages juridiques (648 assertions) réussis. Les cas synthétiques couvrent GPS/EXIF/XMP, les huit orientations JPEG, la transparence PNG/WebP, le redimensionnement, les dimensions refusées, import/rendu/visibilité/suppression, les fichiers corrompus, la simulation et la journalisation minimale. Syntaxe PHP des fichiers concernés, 36 templates Twig, conteneur Symfony, exigences de plateforme Composer et `git diff --check` vérifiés.

Une photo du stockage `dev` de ce checkout a été nettoyée après simulation ; aucune photo dans le stockage `local`. Aucun accès aux photos de production ni effacement des sauvegardes. La suite globale n’a pas été relancée : les migrations absentes et l’échec de l’aide à l’impression constatés dans l’audit restent des travaux distincts. Aucun test visuel navigateur ni indexation réelle par Google n’a été réalisé sur ces nouveaux éléments.
