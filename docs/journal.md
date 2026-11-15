# Journal des évolutions

## 15/11/2026 : Cartouche des planches
- Le titre long d'une pièce (ex. PD9) réduit sa police pour ne plus déborder sur la date (`frame()` dans `assets/editor.js`, partagé avec l'appli).

## 15/11/2026 : Éditeur partagé avec l'appli
- `assets/editor.js` utilise un adaptateur `IO` : même éditeur pour le site et l'application mobile (API, appareil photo natif).
- API : `POST library` (ajout d'un objet de bibliothèque). Code commun `saveLibraryItem()` dans `core.php`.

## 15/11/2026 : Mot de passe oublié
- Réinitialisation par e-mail : site (`?a=forgot`, `?a=reset`) et API (`POST auth/forgot`). Lien valable 1 heure, à usage unique. Le changement déconnecte l'appli mobile partout.
- Réglage de l'adresse d'expédition des e-mails (Textes légaux).
- Migration v9 (`password_resets`).

## 15/11/2026 : Phase 1 mobile (serveur)
- `core.php` : logique métier commune au site et à l'API.
- `api.php` : API JSON avec jetons pour l'application mobile ([api.md](api.md)).
- Consentement obligatoire (inscription, comptes existants, nouvelles versions), registre des consentements, pages publiques CGU / confidentialité / suppression du compte, export des données ([rgpd-consentement.md](rgpd-consentement.md)).
- Modèle d'accès configurable : gratuit (par défaut), limité, essai, abonnement, paiement par dossier, accès offerts ([acces-et-tarifs.md](acces-et-tarifs.md)).
- Back-office : pages « Accès et tarifs » et « Textes légaux ».
- Suppression du compte possible même avec des dossiers débloqués (exigence des stores).
- Migration v8.

## Novembre 2026 : Stabilité et image de marque
- Données (images, journal, marqueurs) déplacées hors du dossier déployé, dans `dossierurba-data`. Fin des pertes d'images au déploiement.
- Migration v5 désactivée.
- Couleurs du logo, menu latéral avec son propre défilement.

## Octobre 2026 : Étapes 1 à 3 du retour client
- Marque DOSSIERUrba, écran d'ouverture, bouton « zone de la parcelle », menu des 6 types de dossiers.
- DP complétée (DP2, DP3, aides, DP7/DP8 corrigées), Cerfa en téléchargement.
- Nouveaux types AP (enseignes) et PD (démolition).
- Éditeur : Annuler / Rétablir, émojis, bibliothèque partagée gérée par l'admin, filigrane BROUILLON avant paiement.
- Bouton « Installer l'application » (PWA).
