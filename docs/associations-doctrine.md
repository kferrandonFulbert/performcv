# Associations Doctrine et intégrité

## Modèle

Les 14 références sont des `ManyToOne` unidirectionnelles : profil vers compte ; rubriques vers profil ; compétence de profil vers compétence ; CV vers profil et modèle ; partage vers CV ; consultation vers partage ; achat vers compte et modèle.

Les colonnes SQL `*_id` et leurs données sont conservées. Les propriétés PHP portent les objets (`profile`, `user`, `template`, `skill`, `cv_instance`, `cv_share`). Utiliser par exemple `setProfile($profile)` ; les anciens setters d'identifiants sont retirés. Les getters `get*Id()` sont des raccourcis de lecture calculés, sans stockage dupliqué. `TemplatePurchases` reçoit le compte et le modèle dans son constructeur.

Les associations sont obligatoires en base (`nullable: false`). Leurs propriétés PHP peuvent être nulles pendant la construction d'une entité ; renseigner toutes les relations avant le flush. Aucun `cascade: persist`, `cascade: remove`, `orphanRemoval` ni collection inverse n'est ajouté. Persister explicitement les nouveaux objets. L'ordre d'insertion est géré par Doctrine.

Les requêtes DQL et les critères de repository utilisent les associations, par exemple `e.profile = :id`. Les projections d'identifiant utilisent `IDENTITY(e.profile)`. Le SQL brut conserve les noms des colonnes. Les filtres sur les sections masquées restent appliqués avant leur lecture.

Le formulaire CV conserve son champ HTTP `templateId` mais le lie à l'association `template` avec des choix d'objets limités au catalogue disponible. Les contrôles de propriété et de droit d'utilisation des modèles restent indispensables.

Le chargement paresseux peut exposer un compte non initialisé. `Users::__serialize()` accède au hash avant de copier les propriétés, puis ne conserve que son empreinte CRC32C dans la session, conformément au fonctionnement existant. Aucune collection d'autres entités n'est ajoutée au compte.

## Migration MySQL

La migration `Version20261112000100` ajoute 14 clés étrangères `ON DELETE RESTRICT`. Le développement et la production utilisent MySQL/InnoDB. Les objets déjà chargés correspondant aux lignes supprimées par DQL sont détachés pour éviter des références obsolètes dans le UnitOfWork. Les suppressions restent orchestrées explicitement dans les transactions de `WorkspaceWriter` : enfants puis parents. Les modèles et compétences encore référencés ne peuvent pas être supprimés directement. Les règles de conservation des achats ne sont pas modifiées.

Avant déploiement : sauvegarder et vérifier la restauration, contrôler la connexion cible et prévoir une fenêtre sans écritures concurrentes. Examiner le SQL et le coût des verrous des `ALTER TABLE` :

```console
php bin/console doctrine:migrations:migrate --dry-run --no-interaction
```

Après validation du déploiement, appliquer les migrations dans l'environnement cible :

```console
php bin/console doctrine:migrations:migrate --no-interaction
```

La migration vérifie InnoDB, `FOREIGN_KEY_CHECKS = 1` et les orphelins avant de préparer les modifications. Aucun nettoyage automatique n'est effectué. Une erreur indique la relation et le nombre d'orphelins sans exposer les données personnelles. Examiner leur sens métier avant correction et reprise.

MySQL ne permet pas le rollback atomique de ces DDL. En cas d'échec partiel, inspecter les contraintes installées avant de relancer ; les contraintes de même nom sont conservées, leur définition doit donc être vérifiée en cas de création manuelle. Le retour arrière retire les contraintes, conserve les données et peut laisser les index de support. Il ne permet pas de supprimer les associations du code sans redéploiement cohérent.

Le middleware SQLite active ses contraintes sans modifier le pilote MySQL. La reconstruction de la seule base de test vérifiée désactive temporairement les contraintes puis les réactive avant les fixtures.

## Tests

```console
php bin/phpunit
```

`EntityAssociationsTest` vérifie le mapping, la correspondance migration/associations, les 14 refus d'orphelins et de suppression de parents, le rollback des données, l'ordre d'insertion ORM et la sérialisation du compte paresseux. Par défaut, il utilise SQLite en mémoire.

Pour ces tests sur MySQL, définir explicitement `PERFORMCV_MYSQL_TEST_SERVER_URL` vers un serveur de test autorisé à créer des bases, puis lancer `php bin/phpunit tests/Integration/EntityAssociationsTest.php`. Chaque test crée une base aléatoire `performcv_associations_test_*` et la supprime ensuite. Ne pas fournir une connexion de production.
