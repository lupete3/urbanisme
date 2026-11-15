# Architecture

## Fichiers

| Fichier | Rôle |
|---|---|
| `index.php` | Site web : pages, formulaires, back-office (routeur `?a=...`) |
| `api.php` | API JSON de l'application mobile (`/api/...` ou `api.php?r=...`), voir [api.md](api.md) |
| `core.php` | Fonctions métier communes au site et à l'API : dossiers, accès, consentement, compte. Migration v8 |
| `lib.php` | Socle : configuration, base de données, session, mise en page, rendu des planches, PDF, migrations v2 à v7. Charge `core.php` |
| `install.php` | Installation initiale. Se supprime après usage |
| `assets/editor.js` | Éditeur de planches (canvas), page de garde, préparation du PDF. **Partagé avec l'appli mobile** : les échanges passent par l'objet `IO` (pages du site par défaut, API dans l'appli via `window.EDITOR_IO`). L'appli l'expose par `window.DPEditor`. Toute modification doit rester compatible avec les deux ; l'appli en récupère une copie par `npm run sync` |
| `assets/ui.js`, `theme.js`, `app.css` | Interface, boîtes de dialogue, thème clair / sombre, bouton d'installation PWA |
| `sw.js`, `manifest.webmanifest` | Application web installable (PWA) |
| `.htaccess` | HTTPS forcé, en-têtes de sécurité, accès refusé à `config.php` / `lib.php` / `core.php`, réécriture `/api/` |

## Dossier de données (`DATA`)

Défini dans `lib.php`. Par défaut, il s'agit de `../dossierurba-data`, à côté du dossier du site. On peut le changer avec la clé `data` dans `config.php`. S'il n'est pas accessible en écriture, l'appli se rabat sur `storage/`.

```
dossierurba-data/
  img/         planches et images de fond des dossiers (JPEG)
  lib/         objets de la bibliothèque (PNG)
  error.log    journal d'erreurs (visible dans Admin → Journal d'erreurs)
  .v2 … .v8    marqueurs de migration
  .htaccess    accès web refusé
```

**Pourquoi hors du site :** le déploiement Git de Hostinger efface les fichiers qui ne sont pas dans le dépôt. Avant ce changement, les images étaient perdues et les migrations se relançaient à chaque déploiement.

## Base de données

| Table | Contenu |
|---|---|
| `users` | Comptes : rôle (declarant, gestionnaire, admin), téléphone, version des conditions acceptée (`consent_version`), choix facultatifs (`opt_analytics`, `opt_marketing`) |
| `dossiers` | Type (DP/AP/PD), intitulé, adresse, parcelle, description, statut (`brouillon` → `en_attente` → `paye`), `unlocked_via` (gratuit, quota, essai, abonnement, paiement), `unlocked_at` |
| `pieces` | Une ligne par planche : `file` (image de fond), `sheet` (planche rendue), `data` (annotations JSON) |
| `library` | Objets de bibliothèque (personnels ou partagés par l'admin) |
| `notifications` | Notifications internes |
| `settings` | Réglages clé/valeur modifiables dans le back-office (émojis, accès, tarifs, textes légaux) |
| `consents` | Registre des consentements : version, choix, source (web/mobile), IP, date |
| `subscriptions` | Abonnements et accès offerts : formule, plateforme (admin, google, apple), dates, statut |
| `api_tokens` | Jetons de connexion de l'application mobile (empreinte SHA-256, expiration glissante de 90 jours) |
| `password_resets` | Liens de réinitialisation du mot de passe (empreinte SHA-256, 1 heure, usage unique) |
| `devices` | Téléphones inscrits aux notifications push (jeton Firebase) |
| `login_fails` | Limitation des tentatives de connexion et d'inscription |

## Migrations

Elles s'exécutent automatiquement au premier chargement de page. Chacune ne tourne qu'une fois : un marqueur est créé dans `DATA`.

| Version | Contenu |
|---|---|
| v2 | Colonnes `sheet` et `data` des pièces |
| v3 | Table `library` |
| v4 | Téléphone, notifications, limitation des tentatives, index |
| v5 | Désactivée. Elle inversait DP7/DP8 et effaçait des planches à chaque déploiement |
| v6 | Type de dossier (DP/AP/PD) |
| v7 | Table `settings` |
| v8 | Consentement, `unlocked_via`, `consents`, `subscriptions`, `api_tokens` (dans `core.php`) |
| v9 | `password_resets` (dans `core.php`) |
| v10 | `devices` (dans `core.php`) |

Pour ajouter une migration : écrire une fonction `migrateN()` qui crée son marqueur `DATA.'/.vN'`. Elle doit pouvoir être relancée sans dégât (`IF NOT EXISTS`, `try/catch` sur les `ALTER`).

## Statuts d'un dossier

```
brouillon ──envoi──▶ paye (débloqué)       si la formule d'accès le permet
          └─envoi──▶ en_attente ──validation du paiement (gestionnaire)──▶ paye
en_attente ──« Modifier le dossier »──▶ brouillon
```

Tant que le dossier n'est pas `paye`, les planches affichées et le PDF portent un filigrane.
