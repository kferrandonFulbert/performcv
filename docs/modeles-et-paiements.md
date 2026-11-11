# Catalogue de modèles et achats

## Parcours disponible

L’éditeur de création et de modification d’un CV présente huit modèles :

| Modèle | Prix par compte | Orientation |
|---|---:|---|
| Classique ATS | Gratuit | ATS, une colonne |
| Moderne | Gratuit | ATS, une colonne, accents bleus |
| Executive | 3,90 € | ATS, cadres et dirigeants |
| Tech Developer | 3,90 € | ATS, développeurs et IT |
| Créatif | 3,90 € | Lecture humaine, composition en colonnes |
| Élégance | 3,90 € | Lecture humaine, typographie éditoriale |
| Étudiant | 3,90 € | ATS, formations et projets en premier |
| Timeline | 3,90 € | Lecture humaine, repères chronologiques |

« Orienté ATS » décrit la présentation, sans garantir son interprétation par
tous les logiciels. L’impression / PDF existante depuis le navigateur conserve
son rendu linéaire sur une colonne. Aucun moteur de génération PDF serveur
n’a été ajouté.

Les cartes sélectionnent le modèle et déclenchent un aperçu avec les données
du profil, la palette, la typographie et les rubriques du formulaire.
Les aperçus passent par un POST authentifié avec CSRF ; ils utilisent une copie
du CV et ne modifient pas les données enregistrées. Sans JavaScript, le bouton
Prévisualiser ouvre une page d’aperçu complète. Les sections masquées ne sont
pas lues pour le rendu. Un aperçu payant est explicitement signalé ; sa feuille
de style d’impression invite à enregistrer un modèle autorisé. Ce mécanisme
n’est pas une protection contre la copie du contenu de l’aperçu.

L’achat d’un modèle est permanent pour les CV du compte concerné, sans
abonnement. Pour un nouveau CV, enregistrer d’abord un modèle gratuit avant
d’acheter un modèle payant. Le retour depuis Stripe n’accorde aucun droit :
la confirmation dépend exclusivement d’un webhook signé et payé.

## Architecture et sécurité

- `TemplateCatalog` contient la liste de confiance des huit styles.
- `CvTemplates` est conservée et enrichie de `slug`, `price` en centimes,
  `is_active` et `is_ats_friendly`. `isFree()` est dérivé du prix et de
  l’indicateur premium existant.
- Les styles sont dans `assets/styles/cv/` ; le contenu reste dans le template
  commun échappé `templates/cv/_document.html.twig`. Les champs HTML/CSS libres
  de la base ne sont toujours pas interprétés. Aucun chemin de fichier fourni
  par le client ou par la base n’est inclus comme template.
- `TemplatePurchases` conserve des références scalaires, selon la convention
  du dépôt : compte, modèle, montant, devise, session Checkout, identifiant du
  paiement et dates. `purchased_at` nul signifie que le droit n’est pas acquis.
- Des contraintes uniques protègent le couple compte/modèle, la session et le
  paiement. Une session ouverte est réutilisée ; une session expirée peut être
  remplacée avec une nouvelle clé d’idempotence. Les demandes concurrentes ne
  peuvent pas créer deux achats du même modèle pour un compte.
- `TemplateAccess` contrôle l’enregistrement et `CvPresentation` contrôle tous
  les rendus enregistrés, notamment le HTML servant à l’impression / PDF et
  les liens publics. Le droit est celui du propriétaire du CV, pas du visiteur.
  Le mode aperçu n’est appelé que depuis l’éditeur après contrôle de propriété.
- L’achat est un POST avec CSRF et contrôle de propriété. Le montant est fixé
  côté serveur ; aucune valeur de prix reçue du navigateur n’est utilisée.
- Le SDK officiel `stripe/stripe-php` 22.1.0 vérifie la signature et sa fraîcheur.
  Le mode test/réel doit correspondre à la clé configurée. Le webhook vérifie
  aussi la session, la référence, le compte, le modèle, le montant, la devise,
  le mode paiement et l’identifiant PaymentIntent. Seul `payment_status=paid`
  donne un droit, avec mise à jour conditionnelle atomique pour les répétitions.
- La suppression d’un compte efface ses achats dans la transaction existante.
  Les pièces comptables et données conservées chez Stripe restent distinctes.
  Les remboursements et contestations ne sont pas automatisés dans cette version.

Les anciens modèles et CV ne sont pas supprimés. Le seed réutilise l’identité
de l’ancien « Classique » lorsqu’il existe. Atelier et Horizon restent rendables
pour les anciens CV ; après initialisation, le sélecteur propose les huit
modèles du nouveau catalogue. Un modèle désactivé est retiré du sélecteur, sans
révoquer les droits ni les CV enregistrés.

## Installation sur un environnement cible

La [procédure de production](mise-en-production.md) détaille les variables,
les options `--env=prod --no-debug`, l’ordre des commandes, les ressources et
les contrôles. Les commandes abrégées ci-dessous supposent l’environnement
cible déjà correctement configuré.

Utiliser PHP 8.5.x et installer les dépendances verrouillées. Après sauvegarde
et examen du SQL sur la base cible, appliquer la migration versionnée :

```console
php bin/console doctrine:migrations:migrate --dry-run --no-interaction
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console app:templates:seed
```

La migration et le seed ont été exécutés uniquement dans la base locale isolée
`.runtime/performcv_local.db`. Aucune migration de production n’a été appliquée.
Le seed est idempotent et actualise les noms, prix, descriptions et indicateurs
du catalogue ; il réactive les huit modèles.

## Configuration Stripe

Définir `STRIPE_SECRET_KEY` et `STRIPE_WEBHOOK_SECRET` dans les secrets de
l’environnement ou dans un fichier local non versionné. Leurs valeurs par
défaut sont vides : les aperçus fonctionnent, mais les achats sont indisponibles.
Ne pas mettre les clés dans Twig, JavaScript, Git ou les journaux.

Confirmer `performcv.public_origin` dans `config/services.yaml` : il sert aux
URL de retour, sans faire confiance au Host du navigateur.
En production, créer dans Stripe une destination webhook HTTPS à :

```text
https://performcv.fr/stripe/webhook
```

Configurer les événements V1 `checkout.session.completed` et
`checkout.session.async_payment_succeeded`, avec le secret de signature de
cette destination et les versions d’événement compatibles avec le SDK installé.
Checkout est limité à la carte bancaire dans cette version. L’endpoint est
public, sans CSRF de session ; sa protection est la signature Stripe.
Une confirmation arrivée avant l’enregistrement de sa session reçoit une
réponse 503 pour permettre la nouvelle livraison par Stripe.

Commencer en mode test. Effectuer un paiement de test et vérifier le refus,
l’annulation, la confirmation, la répétition du webhook, l’utilisation sur un
second CV et le refus pour un autre compte avant activation du mode réel.
Les tests automatisés utilisent des clés et signatures entièrement fictives,
sans appel Stripe ni paiement réseau.

Avant vente réelle, finaliser les informations commerciales, le prix affiché,
la fiscalité et les pièces comptables, les conditions de vente et les modalités
de remboursement/rétractation applicables, ainsi que les informations du
prestataire de paiement et des transferts de données. Les pages existantes
présentent le nouveau fonctionnement, sans constituer une validation juridique
de la vente. Aucun compte Stripe ni webhook externe n’a été créé par cette tâche.

## Vérifications

Retest du 11 novembre 2026 : PHP 8.5.1, suite `bin/phpunit` sur la base dédiée
`.runtime/performcv_suite_test.db` : 55 tests et 926 assertions réussis.
Contrôles de syntaxe PHP, lint des 21 templates Twig et conteneur Symfony
réussis. Validation du mapping et du schéma local après migration réussie.
Le navigateur local a confirmé le changement de modèle et l’aperçu dynamique
avec un compte fictif, sans erreur ni avertissement console observé.
Un paiement réel et la réception d’un webhook depuis Stripe restent non testés.

Références utilisées via Context7 : documentation Symfony 7.4, SDK officiel
Stripe et [confirmation des paiements Checkout](https://docs.stripe.com/checkout/fulfillment).
