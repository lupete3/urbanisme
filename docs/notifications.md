# Notifications

Il existe deux types de notifications :

1. **Dans l'application et sur le site** : liste « Notifications » (table `notifications`), toujours active. Le compteur de non-lues s'affiche sur l'accueil de l'appli.
2. **Sur le téléphone** (notifications push, même appli fermée) : passent par **Firebase Cloud Messaging**. Elles s'activent quand les deux étapes ci-dessous sont faites.

Chaque appel à `notify()` (dossier débloqué, paiement validé, accès offert…) enregistre la notification **et** l'envoie sur les téléphones de l'utilisateur, si Firebase est configuré. Un échec d'envoi est noté dans le journal d'erreurs et ne bloque jamais l'action.

## Mise en place de Firebase (une seule fois)

### 1. Créer le projet et l'application Android
1. Aller sur https://console.firebase.google.com, avec le compte Google du projet, puis **Créer un projet**, par exemple « DOSSIERUrba ». Google Analytics n'est pas nécessaire.
2. Dans le projet : **Ajouter une application → Android**.
   - Nom du package : `com.dossierurba.app` (exactement).
   - Surnom : DOSSIERUrba.
3. **Télécharger `google-services.json`** et le placer dans le projet mobile : `dossierurba-mobile/android/app/google-services.json`.
   Ce fichier n'est pas versionné (`.gitignore`). Le garder aussi en lieu sûr.
4. Recompiler l'appli (`npm run apk`). Le script détecte le fichier et active les notifications (`www/js/build.js` → `push: true`).

### 2. Autoriser le serveur à envoyer
1. Dans Firebase : **⚙ Paramètres du projet → Comptes de service → Générer une nouvelle clé privée**. Un fichier JSON est téléchargé.
2. Sur le site : **Admin → Accès et tarifs → Notifications sur téléphone (Firebase)**. Coller **tout le contenu** du fichier JSON, puis **Enregistrer la clé**.
3. Cliquer sur **M'envoyer une notification de test**, après vous être connecté dans l'appli sur votre téléphone et avoir accepté les notifications.

⚠️ Cette clé donne le droit d'envoyer des notifications au nom du projet. Elle est rangée dans le dossier de données (`dossierurba-data/fcm-service-account.json`), hors du site et hors de Git. Ne jamais l'envoyer par e-mail ni la publier. Pour la retirer : vider le champ et enregistrer.

## Fonctionnement technique

| Élément | Où |
|---|---|
| Téléphones enregistrés | table `devices` (jeton FCM, utilisateur, plateforme), migration v10 |
| Enregistrement depuis l'appli | `POST /api/me/device {token, platform}` après acceptation de l'autorisation |
| Désinscription | `POST /api/auth/logout {device_token}` : le téléphone ne reçoit plus les notifications du compte |
| Envoi | `pushNotify()` dans `core.php` : jeton OAuth signé RS256 (mis en cache dans `dossierurba-data/fcm-token.json`), puis API FCM HTTP v1 |
| Jetons invalides | supprimés automatiquement (réponse 404 de Firebase) |
| Clic sur une notification | ouvre le dossier concerné dans l'appli (champ `link`, par ex. `a=dossier&id=12`) |

## Tests effectués (15/11/2026, environnement local)

Avec une clé factice : clé acceptée et enregistrée hors du site, jeton signé, requête envoyée à Google (réponse « compte introuvable », attendue), erreur notée au journal sans bloquer, notification enregistrée et visible dans l'appli. Le test avec un vrai projet Firebase reste à faire.
