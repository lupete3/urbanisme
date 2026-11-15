# Accès et tarifs

Réglage : **Admin → Accès et tarifs** (`index.php?a=access`). Code : `core.php` (`access()`, `accMode()`, `submitDossier()`).

## Décision actuelle

Au lancement, l'accès est **gratuit**. C'est le réglage par défaut. Les autres formules sont prêtes et s'activent depuis le back-office, sans modifier le code.

## Les formules

| Formule | Effet à l'envoi d'un dossier complet | Réglages |
|---|---|---|
| **Gratuit** | Débloqué immédiatement (`unlocked_via = gratuit`) | Date de fin facultative, puis formule appliquée ensuite |
| **Limité en nombre** | Débloqué tant que le quota n'est pas atteint (`quota`), sinon en attente de paiement | Nombre de dossiers, par mois ou au total |
| **Période d'essai** | Débloqué pendant X jours après l'inscription (`essai`), sinon en attente de paiement | Durée en jours |
| **Abonnement** | Débloqué si l'utilisateur a un abonnement actif, sinon en attente de paiement | Prix mensuel / annuel, identifiants produits des stores |
| **Paiement par dossier** | En attente de paiement. Le gestionnaire valide (`paiement`) | Prix par type DP / AP / PD |

Règles communes :
- **Un abonnement ou un accès offert actif débloque toujours**, quelle que soit la formule.
- **Un dossier débloqué le reste** quand la formule change.
- Le contrôle se fait **côté serveur**, au moment de l'envoi du dossier, pour le site comme pour l'API.
- L'utilisateur voit sa situation sur l'accueil (« Accès gratuit… », « Il vous reste 2 dossiers gratuits… », « Essai : 12 jours restants »…). L'appli mobile la reçoit dans le champ `access` de l'API.

## Accès offerts

Dans la même page, l'admin peut **offrir un accès** à un utilisateur (par son e-mail), pour un nombre de jours ou sans limite, et le retirer. Il est enregistré dans `subscriptions` avec la plateforme `admin`.

## Réglages stockés (table `settings`)

| Clé | Rôle | Défaut |
|---|---|---|
| `acc_mode` | gratuit, quota, essai, abonnement, acte | `gratuit` |
| `acc_free_until` | Fin de la gratuité (AAAA-MM-JJ) | vide |
| `acc_after` | Formule appliquée après cette date | `acte` |
| `acc_quota_n`, `acc_quota_period` | Quota et période (mois / total) | 3, mois |
| `acc_trial_days` | Durée de l'essai | 30 |
| `acc_price_DP/AP/PD` | Prix par dossier (€) | vide |
| `acc_sub_month`, `acc_sub_year` | Prix des abonnements (€) | vide |
| `acc_prod_sub_month/year` | Identifiants des produits dans les stores | vide |
| `acc_app_min_android/ios`, `acc_app_message` | Version minimale de l'appli, message affiché | vide |

## À venir (phase mobile)

- **Google Play Billing**, puis Apple In-App Purchase : un achat vérifié côté serveur crée ou prolonge une ligne `subscriptions` (plateforme `google` / `apple`, `external_id` = jeton d'achat).
- Réception des notifications des stores (renouvellement, résiliation, remboursement) pour mettre à jour `subscriptions`.
- Rappel : dans l'application, les achats numériques doivent passer par le système de paiement du store (commission d'environ 15 % pour les abonnements). Ces règles sont à revérifier au lancement.
