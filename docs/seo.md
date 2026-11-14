# Référencement public et retest SEO-T-01 / SEO-T-02

Retest local du 11 novembre 2026, après l'audit `audits/2026-11-11/audit-seo.md`.

## Configuration

`performcv.public_origin` dans `config/services.yaml` fixe l'origine à
`https://performcv.fr`, d'après le domaine déclaré dans les informations de
l'éditeur. Cette valeur ne dépend ni de Host ni des en-têtes de proxy reçus.
Confirmer cette origine à la mise en production, ainsi que les redirections
HTTP vers HTTPS et www vers le domaine retenu au niveau de l'hébergement.
Ces redirections n'ont pas été testées sur le domaine public.

`PublicSeo` centralise la liste des routes publiées : accueil, mentions légales,
confidentialité, conditions d'utilisation et cookies. Les canonical et le
sitemap utilisent cette même liste. Les paramètres de requête sont omis.
Les pages de connexion, d'inscription, les profils, CV et liens de partage
ne sont pas ajoutés au sitemap et ne reçoivent pas de canonical marketing.

`/robots.txt` autorise l'exploration et annonce l'adresse absolue de
`/sitemap.xml`. Il ne bloque pas les chemins privés ou partagés : les robots
peuvent ainsi lire les réponses de l’application.
Robots.txt ne constitue pas un contrôle d'accès ou une garantie de désindexation.

## Résultats

- SEO-T-01 : corrigé et vérifié localement sur les cinq pages publiques,
  y compris avec paramètres de suivi et hôte arbitraire.
- SEO-T-02 : corrigé et vérifié localement ; réponses 200, types MIME,
  XML et liste exacte des cinq URL contrôlés, sans jeton ni donnée métier.
- `php bin/phpunit` : 43 tests, 296 assertions réussis sous PHP 8.5.
  Le bootstrap impose la base dédiée `.runtime/performcv_suite_test.db`.
- Syntaxe PHP des trois fichiers PHP ajoutés et `git diff --check` : réussis.
- Premier essai dans le bac à sable bloqué par l'écriture du cache Symfony ;
  suite ensuite exécutée avec les permissions nécessaires.

Le déploiement, les redirections du domaine réel et l'indexation par les moteurs
restent non vérifiés. Aucun changement de ces services externes n'a été effectué.
L'audit initial est conservé comme état historique.

Sources officielles consultées le 11 novembre 2026 :

- [Google : canonical](https://developers.google.com/search/docs/crawling-indexing/consolidate-duplicate-urls)
- [Google : création de sitemap](https://developers.google.com/search/docs/crawling-indexing/sitemaps/build-sitemap)
- [Symfony 7.4 : variables globales Twig](https://symfony.com/doc/7.4/templates.html#global-variables)
- [Symfony 7.4 : génération des URL](https://symfony.com/doc/7.4/routing.html#generating-urls)

## Annuaire ajouté le 13 novembre 2026

Les constats ci-dessus décrivent le retest historique. L’annuaire `/annuaire-cv` et les fiches `/cv-public/{id}` ajoutent désormais des pages publiques sur publication volontaire. Les recherches n’imposent aucune directive robots, les fiches admissibles ont une canonique sur l’origine de confiance et sont découvertes via `/sitemap-cv.xml`. Les liens `/s/{token}` utilisent uniquement `noarchive`. Voir [annuaire, classement et limites](annuaire-cv.md).
## Partage sur les réseaux sociaux

Toutes les pages HTML héritant de `base.html.twig` incluent Open Graph et
Twitter Card `summary_large_image`. Les titres et descriptions des pages
publiques sont repris dans les deux formats, avec une URL sur l’origine de
confiance de `PublicSeo`, sans paramètres de suivi.

Les profils publics et liens de partage actifs sans mot de passe utilisent
leur URL propre après contrôle d’accès. Les fiches de l’annuaire utilisent
leur canonique. Les pages privées et les partages protégés, même déverrouillés,
présentent une carte générique renvoyant à l’accueil, sans données personnelles.
Les fragments d’aperçu ne sont pas des documents HTML complets.

Le PNG commun `public/images/performcv-social.png` mesure 1 200 × 630 pixels.
Il ne contient aucune photo de profil. Le bloc Twig texte `page_description`
alimente la description HTML et sociale ; conserver l’échappement des attributs.
Les réseaux doivent pouvoir accéder à l’URL et à l’image après déploiement.
Leur cache peut retarder l’actualisation des aperçus ; le rendu final dépend du réseau.

## Corrections des constats modérés — 14 novembre 2026

Cette section remplace la politique d’indexation des recherches et des partages
décrite dans le paragraphe historique du 13 novembre ci-dessus.

- `public/.htaccess` normalise les hôtes de production `performcv.fr` et
  `www.performcv.fr` vers `https://performcv.fr` ; le chemin et les paramètres
  proviennent de la requête initiale, avant les réécritures internes. Les accès
  explicites via `index.php` sont normalisés dans le même saut. Les autres hôtes,
  notamment localhost, conservent leur fonctionnement local. La configuration
  du proxy d’hébergement reste à vérifier au déploiement.
- Les recherches non vides et les requêtes invalides de `/annuaire-cv` ont
  `X-Robots-Tag: noindex, follow`, sans canonique de remplacement ni blocage robots.
  La liste sans recherche garde sa canonique, propre à chaque page.
- `CvSeo` consolide uniquement les partages d’une même instance de CV. Si cette
  instance est admissible à l’annuaire, sa fiche est la référence. Sinon, le plus
  ancien partage actif sans protection ni hash est la référence. Une révocation
  ou expiration entraîne une nouvelle sélection ; aucun jeton révoqué n’est conservé.
  Un profil et des CV différents ne sont pas fusionnés, car leurs contenus peuvent
  différer. Les partages protégés n’ont pas de canonique et restent en `noindex`,
  même après déverrouillage, avec des métadonnées sociales génériques.
- `GuideExamples` contient les parcours fictifs complets des huit guides. Leur
  présentation utilise le même template Classique que le créateur, sans créer de
  profil en base. Les variantes SLAM et SISR sont toutes deux illustrées. Les
  rubriques, formulations avant/après et étapes de préparation sont expliquées.
- Les guides indiquent la responsabilité éditoriale de l’éditeur, la date réelle
  de cette révision et les références consultées. Aucune qualification de recruteur
  ni relecture professionnelle n’est revendiquée. Les liens externes n’impliquent
  aucune validation du produit par les organismes cités.

Ces changements sont préparés dans le dépôt. Leur présence sur le domaine public
ne doit pas être annoncée avant déploiement et retest HTTP.
