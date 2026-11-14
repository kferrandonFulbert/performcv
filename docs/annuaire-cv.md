# Annuaire public des CV

## Publication volontaire

`CvInstances.directory_listed` vaut `false` par défaut, y compris pour les CV
existants après migration. Depuis la gestion d’un CV, son propriétaire peut
choisir « Publier ce CV dans l’annuaire et autoriser son référencement », puis
retirer cette publication. Les deux actions passent par un POST protégé par
CSRF et sont enregistrées dans l’audit métier.

Un CV est consultable dans l’annuaire uniquement si :

- son propriétaire a activé cette publication ;
- le nom, le prénom et le titre du profil sont renseignés ;
- au moins un de ses liens est actif (`is_public = 1`), explicitement non protégé
  (`password_protected = 0`, sans hash) et non expiré ;
- son modèle est gratuit selon `TemplateAccess`, ou l’achat du modèle est confirmé.

Ces conditions sont revérifiées pour la liste, la recherche, les fiches et les
sitemaps. `null` n’est pas assimilé à une absence de protection. Un seul résultat
est produit par CV, quel que soit son nombre de liens. Plusieurs CV distincts
d’un même profil restent possibles. La visibilité du profil et la publication du
CV sont indépendantes : la publication porte uniquement sur les rubriques visibles
du CV choisi, pas sur toutes les rubriques du profil.

Une suppression ou expiration du dernier lien admissible retire automatiquement
le CV de la liste, des sitemaps et de sa fiche publique. Le choix de publication
reste enregistré : si un nouveau lien admissible est créé, le CV peut réapparaître.
Pour interrompre durablement cette publication, utiliser « Retirer de l’annuaire ».
Les moteurs de recherche et les copies déjà téléchargées ne disparaissent pas
instantanément après un retrait.

## Recherche et classement proposés pour la première version

La recherche porte sur les nom/prénom, titre, résumé, localisation, et sur le
contenu des rubriques visibles : compétences, expériences, formations, projets,
certifications et langues. Une section masquée n’intervient ni dans les résultats
de recherche ni dans la complétude. Les mots peuvent être répartis entre plusieurs
champs ; tous les mots saisis doivent correspondre. Il s’agit d’une recherche par
fragments, sans correction orthographique ni recherche sémantique.

La casse et les accents français courants sont neutralisés de la même façon sur
SQLite et MySQL. Limites : 80 caractères, six mots distincts, 20 résultats par
page. Les valeurs de recherche sont paramétrées, et `%` / `_` restent des
caractères littéraux. Les champs interrogés et les tris proviennent uniquement
de constantes du code.

Le tri est déterministe :

1. Correspondance exacte du nom complet en premier, dans les deux ordres
   prénom/nom et nom/prénom.
2. Score décroissant : `60 × pertinence + 25 × complétude + fraîcheur + bonus payant`.
3. Identifiant croissant du CV pour départager les égalités.

| Critère | Calcul | Maximum |
|---|---|---:|
| Pertinence | Moyenne du meilleur champ pour chaque mot : nom 1, titre 0,9, compétence 0,8, autre contenu visible 0,5 | 60 |
| Complétude visible | Score ci-dessous ramené de 100 à 25 | 25 |
| Fraîcheur du CV | Enregistré depuis moins de 30 jours : 10 ; moins de 90 jours : 5 ; sinon 0 | 10 |
| Modèle payant | Prix positif et droit d’usage confirmé, déjà exigé pour l’éligibilité | 5 |
| Nombre de vues | Désactivé dans cette version | 0 |

La complétude attribue 15 points au titre, 20 à un résumé d’au moins 80 caractères,
25 à la présence d’une expérience **ou** formation **ou** projet visible, 25 à des
compétences visibles, 10 à la localisation et 5 à des langues visibles. Elle ne
favorise pas les CV longs, n’exige pas une expérience professionnelle pour les
étudiants, et n’incite pas à publier photo, téléphone ou adresse pour gagner des
points. Ce score mesure le remplissage, pas l’exactitude ou la qualité rédactionnelle.

Sans recherche, la pertinence est nulle : l’ordre repose sur complétude, fraîcheur
et bonus payant. La fraîcheur utilise `CvInstances.updated_at`, sinon `created_at` ;
elle ne prétend pas dater chaque modification des rubriques du profil.

Un modèle payant bénéficie donc d’un avantage limité ; il ne devient pas
systématiquement premier. Le fonctionnement est expliqué sur l’annuaire. Ces
poids sont un choix produit initial, à réévaluer avec l’usage.

Le compteur existant compte les affichages, y compris rechargements et robots :
l’utiliser dans le classement récompenserait facilement un gonflement artificiel.
Après mise en place d’un signal de popularité plus robuste, une évolution possible
est `60 pertinence + 20 complétude + 10 fraîcheur + 5 payant + 5 popularité`, avec une
popularité récente, logarithmique et plafonnée. Cette évolution n’est pas activée.

## Pages et SEO

- `/annuaire-cv` : liste paginée avec liens HTML explorables ; chaque page de liste
  sans recherche a sa propre URL canonique.
- `/annuaire-cv?q=...` : recherche non vide avec `X-Robots-Tag: noindex, follow`, sans canonique. Les requêtes invalides portent également cette directive ; les recherches vides après suppression des espaces restent des listes normales.
- `/cv-public/{id}` : fiche publique du CV, avec titre, description, canonique sur
  l’origine de confiance `performcv.public_origin` et rubriques visibles uniquement.
- Soumettre `https://performcv.fr/sitemap.xml` dans Google Search Console. Cet index,
  annoncé dans `/robots.txt`, référence `/sitemap-pages.xml` pour l’accueil et les pages légales, et `/sitemap-conseils-cv.xml` pour toutes les pages du menu « Conseils CV ».
- Dès qu’un CV est admissible, l’index ajoute les fichiers `/sitemap-cv-{page}.xml`.
  Chacun contient au plus 1 000 fiches et les pages de liste correspondantes, sans recherche ni jeton.
  Les anciennes adresses `/sitemap-cv.xml` et `/sitemap-cv/{page}.xml` restent accessibles.

Les partages non protégés `/s/{token}` gardent `noarchive`. `CvSeo` leur donne la
canonique de la fiche d’annuaire quand le CV est admissible, sinon celle du plus
ancien partage actif, sans protection ni hash, de cette même instance de CV.
Les partages protégés, même déverrouillés, n’ont pas de canonique et portent
`noindex, follow, noarchive`. Le profil public `/p/{slug}` conserve sa propre
canonique : ses rubriques peuvent différer d’un CV composé à partir du profil. La fiche
publique a une URL distincte. Son affichage GET est compté anonymement sur le lien
admissible de plus petit identifiant, une seule fois par requête ; les requêtes
HEAD, la liste et les sitemaps n’ajoutent pas de consultation.

L’indexation et le classement effectifs dans Google restent décidés par le moteur.
Le score interne de l’annuaire ne constitue pas un signal transmis à Google.

## Exploitation et limites

La migration `Version20261113000100` ajoute uniquement le choix de publication
et un index. L’examiner et l’appliquer avant de déployer le code sur une base
partagée ou de production, selon la procédure habituelle. Aucun CV n’est inscrit
par cette migration. Elle suppose que le schéma applicatif antérieur existe déjà.

Les requêtes travaillent en base et ne chargent qu’une page de résultats. Les
filtres `EXISTS` évitent de multiplier les résultats lors des jointures. SQLite
et MySQL ont des expressions JSON spécifiques pour respecter strictement les
booléens de visibilité. Les tests fonctionnels couvrent SQLite ; le SQL de
migration MySQL est généré et inspecté sans prétendre à un test de serveur MySQL.

La recherche `LIKE` avec normalisation ne bénéficie pas d’un index plein texte.
Pour un annuaire important, mesurer les temps et plans SQL sur les données réelles
puis envisager un index de recherche dérivé, synchronisé transactionnellement,
qui exclut toujours les sections masquées. Aucune nouvelle dépendance ni aucun
moteur de recherche externe n’est ajouté pour cette première version.

Les anciens tests de migration importent actuellement des fichiers retirés du
dossier `migrations/`. Leur restauration éventuelle relève d’une décision séparée ;
la nouvelle migration possède un test indépendant en SQLite mémoire.

Références consultées le 13 novembre 2026 :

- [Google — pagination et liens explorables](https://developers.google.com/search/docs/specialty/ecommerce/pagination-and-incremental-page-loading).
- [Google — contrôle de l’indexation](https://developers.google.com/search/docs/crawling-indexing/block-indexing).
- [Google — URL canoniques](https://developers.google.com/search/docs/crawling-indexing/consolidate-duplicate-urls).
- [SQLite — types retournés par les fonctions JSON](https://www.sqlite.org/json1.html).
- [MySQL 8.4 — fonctions de recherche JSON](https://dev.mysql.com/doc/refman/8.4/en/json-search-functions.html).
