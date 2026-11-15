# Données personnelles et consentement

Code : `core.php` (`recordConsent()`, `needsConsent()`, `legalText()`, `exportData()`, `deleteAccount()`). Back-office : **Admin → Textes légaux** (`index.php?a=legal`).

⚠️ Les textes fournis sont **des brouillons**. Ils doivent être complétés (responsable du traitement, contact, hébergeur, durées) et relus par un juriste ou un DPO avant la publication de l'application.

## Parcours de l'utilisateur

1. **Inscription** (site ou appli) : case **obligatoire** « J'accepte les conditions d'utilisation et la politique de confidentialité », plus deux cases **facultatives** (mesure d'audience anonyme, informations par e-mail). Sans la case obligatoire, l'inscription est refusée.
2. **Comptes existants** et **nouvelle version des textes** : après connexion, un écran bloquant demande d'accepter avant tout usage. L'API renvoie l'erreur `403 consent_required` tant que ce n'est pas fait.
3. **Mon profil → Mes données et consentements** : modifier les choix facultatifs, télécharger ses données (JSON), relire les textes.
4. **Supprimer mon compte** : suppression immédiate et complète (dossiers, images, bibliothèque, consentements, jetons). C'est aussi possible depuis l'appli (`POST /api/me/delete`).

## Pages publiques

Elles sont accessibles sans connexion. Leurs URL sont à renseigner dans les fiches Google Play et App Store.

| Page | URL |
|---|---|
| Conditions générales d'utilisation | `index.php?a=cgu` |
| Politique de confidentialité | `index.php?a=privacy` |
| Suppression du compte (exigée par Google Play) | `index.php?a=suppression` |

## Versions des textes

- La version en vigueur est la clé `legal_version` (« 1 » au départ).
- Dans **Textes légaux**, la case « Publier comme nouvelle version » crée une nouvelle version datée. **Tous les utilisateurs devront accepter à nouveau** à leur prochaine connexion.
- Sans cette case, on corrige le texte sans redemander l'accord. À réserver aux corrections mineures.
- Variables utilisables dans les textes : `{APP}`, `{RESPONSABLE}`, `{CONTACT}`, `{HEBERGEUR}`, `{VERSION}`.

## Registre des consentements

Table `consents`. Chaque acceptation ou modification de choix ajoute une ligne : utilisateur, version, choix (audience / e-mails), source (`web` / `mobile`), IP, date. Les 20 dernières sont visibles dans **Textes légaux**. Le registre sert de preuve du consentement.

## Droits RGPD : où ils sont couverts

| Droit | Comment |
|---|---|
| Information | Écran de consentement, pages CGU et confidentialité |
| Accès / portabilité | Mon profil → Télécharger mes données (JSON). API : `GET /api/me/export` |
| Rectification | Mon profil → Informations personnelles. API : `PUT /api/me` |
| Effacement | Mon profil → Supprimer mon compte. API : `POST /api/me/delete`. Page publique `?a=suppression` |
| Retrait du consentement | Mon profil → Mes données et consentements. API : `POST /api/me/consent` |

## Choix facultatifs

Ils sont enregistrés pour l'avenir. Aujourd'hui, l'appli n'utilise **aucun outil de mesure d'audience** et **n'envoie aucun e-mail d'information**. Si l'un d'eux est ajouté, il ne doit concerner que les utilisateurs qui ont coché la case (`users.opt_analytics`, `users.opt_marketing`).

## Check-list stores (phase mobile)

- [ ] Textes complétés et relus, puis publiés comme nouvelle version
- [ ] URL de la politique de confidentialité dans la fiche Play Store
- [ ] URL de suppression du compte dans la fiche Play Store
- [ ] Formulaire « Sécurité des données » de Google Play rempli selon la politique
- [ ] Justification de l'accès à l'appareil photo (pièces des dossiers)
