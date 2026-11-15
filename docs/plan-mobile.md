# Projet mobile : cahier des charges et avancement

## Décisions validées par le client

- Application **cross-plateforme** avec **Capacitor** : Play Store d'abord, App Store ensuite.
- **Accès gratuit au lancement**. Les formules payantes sont prêtes et activables depuis le back-office (voir [acces-et-tarifs.md](acces-et-tarifs.md)).
- **Compte Google Play personnel**.
- **Cerfa** : téléchargement et remplissage manuel pour le moment.
- **Consentement** obligatoire avant toute utilisation (voir [rgpd-consentement.md](rgpd-consentement.md)).

## Phases

| Phase | Contenu | État |
|---|---|---|
| 0. Cadrage | Choix technique, cahier des charges, décisions du client | ✅ Fait |
| 1. Serveur | API JSON, consentement et textes légaux, modèle d'accès, accès offerts, back-office | ✅ Fait (15/11/2026) |
| 2. Appli Android | Projet Capacitor (dépôt `lupete3/urbanisme-mobile`), écrans, éditeur, appareil photo, PDF, notifications | ⏳ En cours : 2.1 projet ✅, 2.2 compte ✅, 2.3 dossiers ✅, 2.4 éditeur ✅, 2.5 envoi et PDF ✅, 2.6 profil ✅, 2.7 finitions ✅ (notifications push actives) |
| 3. Tests et Play Store | Test fermé (compte personnel : 12 testeurs pendant 14 jours), fiche store, publication | — |
| 4. Paiements dans l'appli | Google Play Billing relié à `subscriptions` (quand le client passera au payant) | — |
| 5. iPhone | Adaptations, Apple In-App Purchase, revue App Store | — |

## Phase 2 : ce qu'il faudra de votre part

**Sur votre PC (Windows) :**
1. **Node.js LTS** (version 20 ou plus récente) : https://nodejs.org
2. **Android Studio** (récent), avec le SDK Android et un émulateur : https://developer.android.com/studio
3. **JDK 17 ou plus**. Il est fourni avec Android Studio.
4. **Git** (déjà installé).
5. Un **téléphone Android** avec le « débogage USB » activé, recommandé pour tester l'appareil photo.

**Décisions et contenus :**
6. **Identifiant de l'application** (package), définitif une fois publié. Proposition : `com.dossierurba.app`.
7. **Nom affiché** sur le téléphone et le store : « DOSSIERUrba » ?
8. **Dépôt GitHub** : un nouveau dépôt pour l'appli (proposé : `dossierurba-mobile`) ou un sous-dossier `mobile/` de ce dépôt.
9. **Icône** : le logo actuel convient. Une version carrée haute définition (1024×1024, sans texte) serait idéale.

**Comptes (avant la phase 3) :**
10. **Compte développeur Google Play** personnel : 25 $ une seule fois, pièce d'identité demandée par Google. https://play.google.com/console
11. **Un projet Firebase** (gratuit) pour les notifications. Je vous guiderai pour le créer.
12. **12 testeurs** (adresses Gmail) pour le test fermé obligatoire de 14 jours.

**Textes légaux, à compléter dans Admin → Textes légaux :**
13. Responsable du traitement (nom ou société, adresse), e-mail de contact, hébergeur et pays des serveurs, durées de conservation. Puis relecture par un juriste et publication comme nouvelle version.

## Spécifications de l'appli V1

- Écran d'ouverture, puis consentement au premier lancement (et à chaque nouvelle version des textes).
- Inscription, connexion, mot de passe oublié (fait côté serveur et appli), profil, export des données, suppression du compte.
- Accueil : formule d'accès, bouton « Zone de ma parcelle », liste des dossiers.
- Dossiers DP / AP / PD : pièces, aides, lien Cerfa. Types PC / PL / AT affichés « À venir ».
- Éditeur réutilisé depuis `assets/editor.js` : photo native, galerie, annotations, émojis, bibliothèque, Annuler / Rétablir, filigrane BROUILLON.
- Page de garde et notice générées sur le téléphone, puis envoi du dossier.
- PDF : téléchargement et partage (mail, WhatsApp…).
- Notifications : dossier débloqué, paiement validé.
- Version minimale imposée et message de l'admin (`GET /api/config`).
