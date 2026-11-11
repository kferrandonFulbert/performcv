# Installer, mettre à jour et exploiter PerformCV en production

Procédure pour PHP **8.5.x** et Symfony **7.4 LTS**, vérifiée sur le code du
11 novembre 2026. Les exemples de terminal utilisent un shell Linux/SSH, adapté
à un hébergement tel que cPanel. Toutes les commandes partent de la racine du
projet, là où se trouvent `composer.json` et `bin/console`.

`php` doit désigner le binaire CLI PHP 8.5 et `composer` doit utiliser ce même
PHP. Si l’hébergeur fournit des chemins spécifiques, remplacer les exécutables
par leurs chemins absolus. Les chemins `/chemin/...` et `/home/COMPTE/...` sont
des exemples à remplacer, pas des chemins déduits de l’hébergement réel.

Cette documentation ne lance aucune opération en production. Une modification
de schéma nécessite une sauvegarde, une revue du SQL et une autorisation de
l’exploitant. Arrêter la procédure à la première erreur et en conserver la cause
sans copier de secret dans les journaux ou les tickets.

## 1. Préparer l’hébergement

- Configurer PHP 8.5.x pour le domaine et pour SSH/cron. Vérifier les extensions
  exigées par Composer et le pilote PDO correspondant au moteur réel de la base.
- Faire pointer le document root du domaine vers **`public/`**, jamais vers la
  racine du dépôt. Le point d’entrée est `public/index.php`.
- Préparer une base et un utilisateur dédiés via l’outil de l’hébergeur, avec les
  permissions nécessaires aux migrations. Ne pas supposer MySQL ou PostgreSQL
  à partir d’un exemple du projet.
- Activer HTTPS, choisir l’hôte public et configurer ses redirections HTTP/HTTPS
  et www. Confirmer les proxies/hôtes de confiance si un intermédiaire est utilisé.
- Donner au processus web les droits nécessaires sur `var/` et sur le stockage
  des sessions. Le compte de déploiement doit pouvoir écrire les ressources
  compilées. Ne pas utiliser de permissions globales `777`.
- Préparer les sauvegardes de la base et des fichiers, puis vérifier la possibilité
  de restauration. Ne pas exposer `.env*`, `config/`, `vendor/`, `var/` ou `.runtime/`.

L’historique livré contient deux migrations :

| Version | Objet |
|---|---|
| `Version20261110000100` | Création du schéma initial de l’application |
| `Version20261111000200` | Catalogue enrichi et achats de modèles Stripe |

Livrer l’ensemble de `migrations/`, y compris les anciennes versions déjà
exécutées. Ne pas utiliser `doctrine:schema:update --force` comme déploiement.

## 2. Configurer les variables d’environnement

Définir les variables dans l’environnement du serveur ou dans un fichier local
non versionné, par exemple `.env.prod.local`. La configuration doit être accessible
au processus web, au terminal et à cron ; un export SSH seul ne configure pas PHP-FPM.

| Variable | Valeur ou rôle |
|---|---|
| `APP_ENV` | `prod` |
| `APP_DEBUG` | `0` |
| `APP_SECRET` | Secret aléatoire propre à la production, conservé entre les déploiements |
| `DATABASE_URL` | Connexion à la base de production réellement choisie |
| `STRIPE_SECRET_KEY` | Clé Stripe secrète, facultative tant que les achats sont désactivés |
| `STRIPE_WEBHOOK_SECRET` | Secret de signature de la destination webhook, requis avec la clé pour acheter |
| `MESSENGER_TRANSPORT_DSN` | `sync://` si aucun traitement asynchrone n’est souhaité |
| `MAILER_DSN` | `null://null` si aucun envoi d’email n’est configuré |

`sync://` et `null://null` sont aussi les valeurs de repli définies par le code,
mais un fichier d’environnement peut les remplacer. Aucun envoi de récupération
de mot de passe ou de facture n’est implémenté par cette configuration.

Pour générer un nouveau secret, exécuter sur un terminal privé puis le stocker
dans la configuration sécurisée, sans le recopier dans Git :

```sh
php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'
```

Changer `APP_SECRET` invalide notamment les préférences de cookies signées.
Ne pas le régénérer à chaque mise à jour. Ne pas ajouter de vraies clés dans
les exemples de documentation ou dans des commandes conservées en historique.

Vérifier également `config/services.yaml` :

- `performcv.public_origin`, actuellement `https://performcv.fr`, pour les
  canonical, le sitemap et les retours Stripe ;
- `performcv.legal`, pour les informations publiques de l’éditeur.

Le site ne déduit pas son origine de référence du Host reçu. Les informations
juridiques et commerciales doivent correspondre au service effectivement publié.

## 3. Première installation sur une base vide

Déposer une version complète et validée du projet avec son `composer.lock`,
ses deux migrations et sa configuration locale de production. Garder le site
hors trafic pendant l’installation.

### A. Vérifier PHP et installer les dépendances

```sh
cd /chemin/absolu/performcv
export APP_ENV=prod
export APP_DEBUG=0
php --version
composer --version
composer validate --no-check-publish
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction --no-scripts
composer check-platform-reqs --no-dev
php bin/console about --env=prod --no-debug
```

PHP doit être en 8.5.x et `about` doit indiquer l’environnement `prod` avec le debug
désactivé. `--no-scripts` reporte les scripts automatiques du projet ; les ressources
et le cache sont traités explicitement ci-dessous. Utiliser `install`, pas `update`,
pour conserver les versions validées dans le verrou. L’installation de production
n’inclut pas PHPUnit ni les autres dépendances de développement.

### B. Examiner puis appliquer les migrations

Après vérification privée de la cible `DATABASE_URL` et de la sauvegarde :

```sh
php bin/console doctrine:migrations:status --env=prod --no-debug
php bin/console doctrine:migrations:migrate --dry-run --no-interaction --env=prod --no-debug -vv
```

Lire les requêtes affichées avant de continuer. `--dry-run` n’applique pas les
modifications métier du schéma, mais démarre l’application et peut initialiser
le stockage technique des versions Doctrine. Ce n’est pas un test de restauration.
Sur une base vide, le plan doit contenir le schéma initial puis l’enrichissement
du catalogue. Après validation explicite du plan par l’exploitant :

```sh
php bin/console doctrine:migrations:migrate --no-interaction --env=prod --no-debug
php bin/console doctrine:schema:validate --env=prod --no-debug
```

**Base existante sans historique de migrations :** ne pas lancer le schéma initial
sur les tables existantes et ne pas marquer aveuglément toutes les versions comme
exécutées. Faire comparer le schéma réel au schéma attendu, puis préparer une
reprise d’historique ou une migration adaptée. Cette situation requiert une revue
spécifique ; il n’existe pas de commande universelle sûre de bascule.

### C. Initialiser le catalogue

```sh
php bin/console app:templates:seed --env=prod --no-debug
```

La commande crée ou actualise les huit modèles et réutilise l’ancien Classique
si possible. Elle ne crée pas de compte ni de CV de démonstration. Elle est
idempotente, mais actualise aussi les noms, descriptions, prix, indicateurs ATS
et **réactive les huit modèles**. Ne pas la rejouer sans tenir compte des choix
d’exploitation concernant les modèles désactivés.

### D. Préparer les ressources et le cache

```sh
php bin/console importmap:install --env=prod --no-debug --no-interaction
php bin/console assets:install public --env=prod --no-debug --no-interaction
php bin/console cache:clear --env=prod --no-debug --no-interaction
php bin/console asset-map:compile --env=prod --no-debug --no-interaction
```

`importmap:install` télécharge les ressources référencées dans l’importmap si
elles manquent. Les ressources peuvent aussi être préparées dans un environnement
de build autorisé puis livrées avec la version. `asset-map:compile` écrit les fichiers
versionnés dans `public/assets/`, notamment les CSS des huit modèles et `ui.js`.
Ces fichiers doivent être servis par le serveur web et livrés avec la même version
du code. `cache:clear` réchauffe aussi le cache par défaut : une commande de warmup
supplémentaire n’est pas nécessaire dans cette séquence.

### E. Contrôler avant ouverture

```sh
php bin/console doctrine:migrations:status --env=prod --no-debug
php bin/console doctrine:schema:validate --env=prod --no-debug
php bin/console lint:container --env=prod --no-debug
php bin/console lint:twig templates --env=prod --no-debug
```

Vérifier que toutes les migrations attendues sont exécutées, sans version exécutée
absente du code livré, et que le schéma correspond au mapping. Un résultat sans
erreur en CLI ne remplace pas les vérifications HTTP de la section 7.

## 4. Mettre à jour une installation existante

1. Valider la nouvelle version et ses tests en développement/CI, sur une base de
   test dédiée. Préparer un retour arrière et une sauvegarde vérifiée.
2. Préparer la nouvelle version dans un répertoire de livraison distinct lorsque
   l’hébergement le permet. Garder les secrets et les données persistantes séparés.
3. Reprendre l’étape **3.A** pour installer les dépendances verrouillées, sans scripts.
4. Examiner `doctrine:migrations:status` et le `migrate --dry-run` de **3.B**.
   Mettre les écritures en pause via l’infrastructure pendant les opérations de
   schéma et la bascule si leur compatibilité avec le code actif n’est pas garantie.
   Aucun mode maintenance applicatif n’est implémenté dans le projet.
5. Appliquer uniquement le plan relu, puis valider le schéma. Ne pas recréer la base.
6. Exécuter le seed si cette livraison introduit ou actualise le catalogue, en
   tenant compte de sa réactivation des modèles ; reprendre **3.D** pour les ressources
   et le cache.
7. Basculer vers la nouvelle version, recharger PHP/OPcache selon les moyens de
   l’hébergeur, puis effectuer les contrôles HTTP. Réouvrir les écritures après succès.

Conserver le code et les ressources de la version précédente pour le retour
arrière prévu. Revenir au code précédent ne restaure pas la base : une migration
descendante peut supprimer des données, en particulier les achats. Ne pas lancer
`migrate prev` ou `down` automatiquement. Évaluer les écritures intervenues depuis
la sauvegarde et les événements Stripe avant toute restauration.

## 5. Activer les achats Stripe

Les aperçus et les modèles gratuits fonctionnent sans Stripe. Pour activer l’achat :

1. Configurer `STRIPE_SECRET_KEY` et `STRIPE_WEBHOOK_SECRET` dans le même mode,
   test d’abord, puis réel après validation du parcours.
2. Créer une destination webhook **V1** HTTPS sur le compte Stripe de l’exploitant,
   vers `https://performcv.fr/stripe/webhook` si cette origine est retenue.
3. Sélectionner `checkout.session.completed` et
   `checkout.session.async_payment_succeeded`, avec une version d’événement
   compatible avec le SDK installé. Utiliser le secret de cette destination.
4. Faire un achat de test : le droit n’est accordé qu’après un événement signé
   confirmant un paiement `paid` de 390 centimes EUR pour la session attendue.
5. Vérifier l’annulation, la répétition d’un événement, le réemploi du modèle sur
   un autre CV du même compte et son refus pour un autre compte.

Un GET ou un POST non signé sur le webhook n’est pas un test de bon fonctionnement.
Une réponse 400 signale notamment une signature ou un événement invalide ; une
réponse 503 peut signaler une configuration absente ou une confirmation à retenter.
Contrôler la livraison et les nouvelles tentatives dans Stripe sans publier le
contenu des événements, les signatures ou les clés.

Le navigateur est redirigé par Checkout mais ce retour ne débloque pas le modèle.
Les remboursements et contestations restent manuels. Les informations de vente,
la fiscalité, les pièces comptables, les modalités applicables de remboursement
et la documentation des données de paiement doivent être finalisées avant vente
réelle. Voir le [guide des modèles et paiements](modeles-et-paiements.md).

Après changement de configuration de l’environnement, reconstruire le cache si
nécessaire et recharger PHP selon l’hébergement. Si un fichier `.env.local.php`
a été généré ailleurs, sa mise à jour doit faire partie du déploiement : ne pas
conserver un ancien fichier figé contenant une configuration devenue incorrecte.

## 6. Maintenance quotidienne et cron

La commande suivante supprime les consultations de plus de 90 jours ou sans date,
ainsi que les fichiers de compteurs de sécurité expirés. Elle modifie les données
et ne dispose pas d’option de simulation :

```sh
php bin/console app:views:purge --env=prod --no-debug --no-interaction
```

Exemple de tâche quotidienne à **03:15 dans le fuseau du planificateur**, après
adaptation des chemins et création du dossier de journaux hors `public/` :

```cron
15 3 * * * cd /home/COMPTE/performcv && APP_ENV=prod APP_DEBUG=0 /chemin/php85 bin/console app:views:purge --env=prod --no-debug --no-interaction >> /home/COMPTE/logs/performcv-purge.log 2>&1
```

Dans cPanel, saisir séparément la planification et la commande si l’interface le
demande. Vérifier le fuseau de l’hébergeur, l’accès aux variables et le PHP CLI
effectif. Conserver le code de sortie et la date du dernier succès ; alerter après
26 heures sans succès. Le cron n’est pas créé automatiquement par le dépôt.
Configurer aussi la rotation et la conservation de son journal.

Les journaux applicatifs de production sont dirigés vers **stderr** ; vérifier
où l’hébergeur les collecte. Les sauvegardes, la rotation des journaux et les
alertes ne sont pas configurées automatiquement par l’application. Voir
[l’exploitation des données personnelles](exploitation-donnees-personnelles.md).

Par défaut, aucun worker Messenger n’est nécessaire avec `MESSENGER_TRANSPORT_DSN=sync://`.
Un transport asynchrone réellement choisi nécessite une procédure distincte de
supervision des workers ; ne pas lancer de worker uniquement parce que le composant
est installé. Le déblocage Stripe est traité directement par la requête webhook.

## 7. Vérifications HTTP après déploiement

Utiliser les pages publiques puis un compte et des données de test expressément
autorisés pour cette recette. Ne pas publier de CV réel pour vérifier un déploiement.

| Contrôle | Résultat attendu |
|---|---|
| Accueil, connexion et pages légales | Pages accessibles, sans barre de debug ni trace d’exception |
| HTTP et variantes www | Redirection cohérente vers l’origine HTTPS retenue |
| CSS/JS, cartes de modèles | Ressources versionnées accessibles ; sélection et aperçu fonctionnels |
| `/robots.txt` et `/sitemap.xml` | Réponses 200 et URL absolues du domaine retenu, sans jeton de partage |
| Canonical de l’accueil et des pages d’information | Origine configurée, sans paramètres de suivi |
| `/app` sans connexion | Redirection vers la connexion |
| Formulaire et session en HTTPS | Cookie de session Secure, HttpOnly et SameSite adapté ; aucune valeur à copier dans les preuves |
| Création de CV gratuit | Enregistrement, consultation et impression depuis le navigateur possibles |
| Modèle payant non acheté | Aperçu possible, enregistrement/rendu enregistré refusés sans droit |
| Lien de CV de test | Rubriques masquées absentes ; mot de passe/expiration respectés |
| Stripe, si activé | Événement signé confirmé et accès accordé au bon compte uniquement |

Il n’existe pas de route dédiée de health check ni de garantie d’indexation SEO
immédiate. Les tests PHPUnit se lancent sur une base de test, pas sur celle de production.

## 8. Commandes à ne pas confondre

| Commande ou action | Usage |
|---|---|
| `bin/console ... --env=prod --no-debug` | Exploitation de la production configurée |
| `bin/local-console` | Mode isolé SQLite dans `.runtime/`, jamais une commande de production |
| `public/local.php`, `php -S ...` | Serveur local uniquement |
| `app:templates:seed` | Installation/actualisation du catalogue ; pas de création de tables |
| `app:demo` | Réservé à local/test ; dépend encore du modèle historique Horizon et n’est pas un démarrage garanti sur le nouveau catalogue |
| `composer update` | Évolution des dépendances en développement, pas une étape de déploiement |
| `doctrine:schema:update --force` | Ne remplace pas les migrations versionnées |
| Suppression d’un historique ou marquage de versions | Reprise spécifique, jamais une correction automatique d’une erreur de migration |

## 9. Diagnostic rapide

| Problème | Vérification ciblée |
|---|---|
| Erreur PHP/objets paresseux Doctrine | Vérifier PHP 8.5 sur le web, SSH, Composer et cron |
| Installation sans classes de debug | Vérifier `APP_ENV=prod`, `APP_DEBUG=0` et le cache de la version livrée |
| Permission refusée sur le cache | Vérifier propriétaire et droits de `var/` pour web et CLI |
| Table/colonne absente | Vérifier la connexion en privé, la livraison de `migrations/`, son état et le SQL prévu |
| Version de migration exécutée mais absente | Restaurer le fichier de la livraison correspondante ; ne pas effacer l’historique pour masquer l’écart |
| Styles ou aperçu non actualisés | Recompiler les assets, livrer leur manifeste et vérifier les caches HTTP/OPcache |
| Aucun modèle sélectionnable | Après validation du schéma, exécuter `app:templates:seed` |
| Achat indisponible | Vérifier les deux secrets Stripe, le mode et les droits du compte Stripe |
| Paiement confirmé chez Stripe mais modèle bloqué | Vérifier la destination, la signature, la livraison et les réponses du webhook ; ne pas débloquer sur le seul retour navigateur |
| Purge non exécutée | Vérifier chemins absolus, fuseau, variables, droits et journal cron |

## Références et portée de la vérification

Documentation officielle consultée via Context7 le 11 novembre 2026 :
[déploiement Symfony 7.4](https://symfony.com/doc/7.4/deployment.html),
[AssetMapper 7.4](https://symfony.com/doc/7.4/frontend/asset_mapper.html),
[configuration Symfony 7.4](https://symfony.com/doc/7.4/configuration.html),
[Doctrine Migrations 3.9](https://www.doctrine-project.org/projects/doctrine-migrations/en/3.9/reference/managing-migrations.html).
Les commandes et options principales ont été contrôlées via l’aide des outils
installés en mode local. Les deux migrations ont été restaurées avec accord de
l’utilisateur. Aucune de ces commandes n’a été exécutée sur un serveur de production
pour rédiger ce guide ; le moteur de base, les chemins cPanel, les secrets et le
processus de bascule doivent être confirmés sur l’hébergement réel.
