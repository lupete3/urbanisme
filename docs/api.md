# API de l'application mobile

Fichier : `api.php`. Logique métier partagée avec le site : `core.php`.

## Bases

- **Adresse** : `https://travaux.pftecho.com/api/<route>` (réécrite par `.htaccess`). Équivalent : `api.php?r=<route>`.
- **Format** : JSON en entrée (`Content-Type: application/json`) ou `multipart/form-data` pour l'envoi d'images. JSON en sortie, sauf pour les images et le PDF.
- **Authentification** : `Authorization: Bearer <jeton>`. Le jeton est reçu à l'inscription ou à la connexion. Il est valable 90 jours, prolongés à chaque utilisation. Le serveur n'en garde que l'empreinte SHA-256.
- **CORS** : origines autorisées `https://localhost`, `http://localhost`, `capacitor://localhost`, `ionic://localhost`, plus la liste du réglage `api_origins` (séparée par des virgules).
- **Erreurs** : `{"ok": false, "error": "message lisible", "code": "…"}` avec le code HTTP adapté.

| Code HTTP | `code` | Signification |
|---|---|---|
| 401 | `auth_required` | Jeton absent, invalide ou expiré : retour à l'écran de connexion |
| 403 | `consent_required` | Conditions à accepter : afficher l'écran de consentement, puis `POST me/consent` |
| 404 | `not_found` | Route inconnue |
| 409 | — | Action impossible dans l'état actuel (dossier incomplet, déjà débloqué…) |
| 429 | — | Trop de tentatives (15 minutes) |

## Routes

### Public

| Méthode | Route | Description |
|---|---|---|
| GET | `config` | Types de dossiers et leurs pièces, Cerfa, émojis, formule d'accès, version et URL des textes légaux, versions minimales de l'appli, message |
| GET | `legal/cgu`, `legal/privacy` | Texte légal (`text` brut et `html`) avec sa version |
| POST | `auth/register` | `{name, email, password, cgu: true, analytics?, marketing?, device?}` → `201 {token, user}` |
| POST | `auth/login` | `{email, password, device?}` → `{token, user}` |
| POST | `auth/forgot` | `{email}` : envoie un lien de réinitialisation valable 1 heure. La réponse est toujours `ok`, pour ne pas révéler si le compte existe. Le nouveau mot de passe se choisit sur le site (`index.php?a=reset`), ce qui déconnecte aussi l'appli sur tous les appareils |

### Compte (jeton requis)

| Méthode | Route | Description |
|---|---|---|
| GET | `me` | Profil, état du consentement, accès (`access.ok`, `access.via`, `access.text`) |
| PUT | `me` | `{name?, email?, phone?}` |
| POST | `me/password` | `{current, new}`. Déconnecte les autres appareils |
| POST | `me/consent` | `{cgu: true, analytics, marketing}` |
| GET | `me/export` | Export complet des données personnelles |
| POST | `me/delete` | `{password}` : suppression définitive du compte |
| POST | `auth/logout` | Révoque le jeton courant |

`GET me`, `me/consent`, `me/export`, `me/delete` et `auth/logout` fonctionnent même si le consentement est à renouveler. Les autres routes renvoient alors `403 consent_required`.

### Dossiers

| Méthode | Route | Description |
|---|---|---|
| GET | `dossiers` | Liste avec progression `{done, required}` et `access` |
| POST | `dossiers` | `{type: DP\|AP\|PD, titre, adresse, parcelle}` → `201 {dossier}` |
| GET | `dossiers/{id}` | Détail : pièces (titre, aide, facultative, faite), Cerfa, pièces manquantes, prix |
| PUT | `dossiers/{id}/description` | `{description}` : texte de la notice (DP11 / PD9) |
| POST | `dossiers/{id}/pieces/{code}` | multipart : `sheet` (planche JPEG 1654×1169, obligatoire), `base` (image de fond JPEG, facultative), `data` (annotations JSON), `desc` (pour la pièce texte) |
| GET | `dossiers/{id}/pieces/{code}` | `{done, has_base, data}` pour rouvrir l'éditeur |
| GET | `dossiers/{id}/pieces/{code}/sheet` | Planche (JPEG, filigrane BROUILLON si non débloqué) |
| GET | `dossiers/{id}/pieces/{code}/base` | Image de fond (JPEG) |
| POST | `dossiers/{id}/submit` | Envoi : débloqué si la formule le permet, sinon en attente de paiement. Renvoie `message` |
| POST | `dossiers/{id}/reopen` | Repasse un dossier en attente en brouillon |
| DELETE | `dossiers/{id}` | Supprime un dossier non débloqué |
| GET | `dossiers/{id}/pdf` | PDF complet (filigrané tant que non débloqué) |

**Avant l'envoi**, comme sur le site, l'appli génère et envoie la **page de garde** (code `<TYPE>0`, ex. `PD0`) et la **planche de la notice** (DP11 / PD9). `assets/editor.js` contient le code de rendu à réutiliser.

### Notifications et bibliothèque

| Méthode | Route | Description |
|---|---|---|
| GET | `notifications` | 50 dernières + nombre de non lues |
| POST | `notifications/read` | Tout marquer comme lu |
| GET | `library` | Objets personnels et partagés |
| GET | `library/{id}/image` | Image PNG de l'objet |
| DELETE | `library/{id}` | Supprime un objet (le sien, ou n'importe lequel pour un admin) |

## Exemple

```bash
curl -X POST https://travaux.pftecho.com/api/auth/login -H "Content-Type: application/json" -d '{"email":"moi@exemple.fr","password":"…","device":"Pixel 8"}'
curl https://travaux.pftecho.com/api/dossiers -H "Authorization: Bearer <jeton>"
```

## Tests effectués (15/11/2026, environnement local)

Inscription sans consentement refusée (400), inscription + jeton, accès refusé sans jeton (401), mauvais mot de passe (401), création d'un PD, envoi des 5 planches, envoi en mode gratuit → débloqué, PDF sans filigrane, liste, export, textes légaux, route inconnue (404). Mode limité à 1 : 1er dossier débloqué, 2e en attente avec le prix. Accès offert → débloqué. Nouvelle version des textes → 403 `consent_required`, puis ré-acceptation. Suppression avec un mauvais mot de passe (403), puis suppression effective et jeton invalidé (401).
