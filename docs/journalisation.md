# Journal d’audit et journaux de sécurité

La journalisation permet de reconstituer les opérations et d’enquêter sur un
incident. Elle ne sert ni au marketing ni à mesurer le comportement des visiteurs.
Le compteur anonyme `CvViews` reste indépendant et ne crée aucun événement d’audit.

## Événements métier en base

La table `audit_events` contient uniquement : identifiant de l’événement, date UTC,
identifiant du compte acteur (nullable), code de l’action, type et identifiant de
la ressource, identifiant aléatoire de corrélation. Elle ne contient aucun champ
libre, contenu de CV, email, mot de passe, hash, IP, user-agent, référent, URL,
jeton de partage, cookie ou identifiant Stripe.

| Parcours validé | Actions |
|---|---|
| Inscription, coordonnées, mot de passe, suppression du compte | `account.created`, `account.updated`, `account.password_changed`, `account.deleted` |
| Création, modification, suppression de profil | `profile.created`, `profile.updated`, `profile.deleted` |
| Rubriques de profil | `entry.created`, `entry.updated`, `entry.deleted` |
| Création, modification, suppression de CV | `cv.created`, `cv.updated`, `cv.deleted` |
| Création et révocation de partage | `share.created`, `share.revoked` |
| Création d’un achat en attente, avant l’appel Stripe | `purchase.created` |
| Enregistrement d’une nouvelle session Checkout | `purchase.checkout_created` |
| Confirmation effective d’un achat par webhook signé | `purchase.confirmed` |

Les types des rubriques sont leurs classes internes autorisées, afin de distinguer
les identifiants qui peuvent se répéter entre tables. Un enregistrement validé
peut produire `updated` même si l’utilisateur renvoie les mêmes valeurs.
Les aperçus, simples lectures, formulaires invalides et écritures refusées ne
produisent pas d’événement métier réussi. Le catalogue de compétences partagé
n’est pas journalisé séparément de la rubrique qui l’utilise.

`WorkspaceWriter` écrit l’action et sa trace dans une transaction commune, avec
la même connexion. Une panne de l’audit annule l’écriture métier. Les suppressions
de parents décrivent aussi la suppression de leurs ressources descendantes, sans
une ligne pour chaque enfant supprimé en masse ; une suppression de compte
produit aussi les événements de ses profils. Toute future écriture directe ou
en masse doit intégrer explicitement l’audit : aucun listener ORM universel ne
remplace cette règle.

L’inscription est attribuée au nouveau compte. Un webhook est attribué au système
(`actor_id = null`), pas au navigateur éventuellement connecté. Les commandes CLI
sans utilisateur connecté ont également un acteur nul. Le `request_id` est généré
côté serveur et partagé entre les événements d’une même requête HTTP ; une valeur
envoyée dans un en-tête par le client n’est pas utilisée.

Les références d’audit sont des nombres sans clé étrangère volontairement : une
suppression de compte ou de CV ne doit ni supprimer ni bloquer la trace. Ces
identifiants restent des données de traçabilité potentiellement personnelles,
conservées pour une durée limitée ; ce journal n’est pas présenté comme anonyme.
Les événements existants restent jusqu’à leur purge, y compris après suppression
du compte. Aucune copie des anciennes valeurs ou des contenus supprimés n’y est
conservée.

## Événements de sécurité en fichiers

Le canal Monolog `audit_security` écrit immédiatement des lignes JSON dans
`%kernel.logs_dir%/audit/%kernel.environment%/audit_security-YYYY-MM-DD.log`, hors du dossier public,
indépendamment du déclenchement des logs d’erreur habituels. Il est exclu des
handlers généraux pour éviter sa duplication. Les fichiers sont verrouillés à
l’écriture et créés avec le mode `0600` lorsque le système le permet ; sur Windows,
vérifier les ACL du dossier.

Événements : connexion réussie ou échouée, déconnexion, réponse 403, limitation 429,
consultation du journal et purge (ou simulation) par les commandes dédiées. Les fichiers sont séparés par environnement pour isoler les tests. Le contexte ne contient que
`actor_id` et `request_id`. Une tentative de connexion échouée a un acteur nul :
l’email saisi n’est ni conservé ni considéré comme une identité prouvée.
Une défaillance du fichier ne bloque pas la déconnexion ou les contrôles de
sécurité ; un message technique fixe est envoyé à `error_log`. Surveiller ce
message ainsi que l’espace disque et les permissions. Les logs techniques
préexistants restent distincts : contrôler également leur configuration et leur
collecte sur l’hébergement.

## Consultation et exploitation

Aucune route web de consultation, modification ou suppression du journal n’est
exposée, et aucun rôle administrateur n’est inventé. Réserver l’accès console,
aux fichiers et à la base aux exploitants habilités avec des comptes individuels.

```console
php bin/console app:audit:list --actor=42 --limit=50 --env=prod --no-debug
php bin/console app:audit:purge --dry-run --env=prod --no-debug
php bin/console app:audit:purge --env=prod --no-debug
```

La consultation renvoie du JSON par ligne, avec une limite de 100 et un filtre
paramétré sur l’acteur. Sans `--actor`, les derniers événements de tous les acteurs
sont retournés, y compris ceux du système. L’usage de cette commande est lui-même
signalé dans le fichier de sécurité ; l’identité de l’opérateur CLI relève des
comptes et traces système de l’hébergement. Ne pas diffuser les sorties de console
ou les exporter dans des outils non autorisés.

## Conservation, intégrité et déploiement

La cible de conservation est de **six mois glissants**. `app:audit:purge` supprime
les événements antérieurs à cette limite et les fichiers de sécurité des jours
entièrement antérieurs à la limite. Son option `--dry-run` ne modifie rien.
La rotation Monolog plafonne aussi à 185 fichiers ; elle ne remplace pas la purge
calendaire et ne supprime pas les fichiers à elle seule si aucune écriture ne
survient. Programmer la purge quotidiennement et superviser son résultat sur
l’hébergement. Aucun cron ni collecteur externe n’est installé par ce changement.

Avant de déployer le code qui écrit l’audit, examiner et appliquer la migration
`Version20261112000200` avec la procédure de déploiement habituelle, sous un compte
de migration distinct. Elle ajoute uniquement la table et ses index. Aucune
migration de production n’est exécutée pendant le développement. En cas de retour
arrière du schéma, exporter les preuves nécessaires dans un stockage autorisé :
la migration descendante supprime le journal.

Le modèle en lecture seule et l’absence de setters ou d’API de modification ne
garantissent pas l’inviolabilité de la base. Pour la production, limiter le compte
web à `INSERT` sur cette table ; réserver `SELECT` à l’exploitation et `DELETE` à
la purge avec des identifiants de maintenance distincts. Vérifier que des droits
globaux sur la base n’annulent pas cette séparation. Si un même compte est
provisoirement utilisé, cette limite doit être documentée.

Pour résister à une compromission du serveur ou de la base, acheminer les traces
vers un collecteur indépendant protégé contre la modification et superviser
l’absence de réception. Cette infrastructure dépend de l’hébergement et n’est
pas configurée ici. La conservation des sauvegardes et des copies externes doit
être cohérente avec celle du journal ; leur expiration n’est pas garantie par la
commande de purge applicative.

## Justification et références

L’intérêt poursuivi est la protection du service et l’investigation des incidents,
avec une collecte minimale et un accès limité. La durée retenue correspond au
bas de la plage recommandée par la CNIL. Le journal ne sert pas à suivre les
lectures des visiteurs ni à reconstituer le contenu des CV. Réexaminer ce choix,
les habilitations et le besoin de conservation lors des changements du service.

Références consultées le 12 novembre 2026 :

- [CNIL — recommandations de journalisation](https://www.cnil.fr/fr/la-cnil-publie-une-recommandation-relative-aux-mesures-de-journalisation).
- [OWASP — Logging Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Logging_Cheat_Sheet.html).
- [Doctrine ORM 3.7 — transactions](https://www.doctrine-project.org/projects/doctrine-orm/en/3.7/reference/transactions-and-concurrency.html).
- [Symfony 7.4 — événements de sécurité](https://symfony.com/doc/7.4/security.html#security-events).
