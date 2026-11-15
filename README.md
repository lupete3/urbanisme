# DOSSIERUrba

Application qui permet aux particuliers et aux professionnels de constituer eux-mêmes leurs dossiers de demande d'autorisation d'urbanisme : Déclaration Préalable (DP), Autorisation Préalable d'enseigne (AP) et Permis de Démolir (PD).

- **Site web** : PHP 8 + MySQL, sans framework. Hébergement Hostinger, déployé depuis GitHub.
- **Application mobile** (à venir) : Capacitor, sur le Play Store puis l'App Store. Elle utilise l'API JSON du même serveur.

## Documentation

| Document | Contenu |
|---|---|
| [docs/architecture.md](docs/architecture.md) | Fichiers, base de données, migrations, dossier de données |
| [docs/acces-et-tarifs.md](docs/acces-et-tarifs.md) | Formules d'accès (gratuit, limité, essai, abonnement, paiement par dossier) |
| [docs/rgpd-consentement.md](docs/rgpd-consentement.md) | Consentement, textes légaux, droits des utilisateurs, exigences des stores |
| [docs/api.md](docs/api.md) | API JSON de l'application mobile |
| [docs/plan-mobile.md](docs/plan-mobile.md) | Cahier des charges et avancement du projet mobile |
| [docs/journal.md](docs/journal.md) | Historique des évolutions |

## Installation

1. Créer une base MySQL.
2. Déposer les fichiers sur le serveur.
3. Ouvrir `install.php` et remplir le formulaire. Il crée `config.php` et le compte administrateur, puis se supprime.

## Déploiement (Hostinger)

hPanel → **Avancé → GIT** → **Déployer**. Branche `main`.

⚠️ Les données créées sur le serveur ne sont **pas** dans le dépôt : images des dossiers, bibliothèque, journal d'erreurs, marqueurs de migration. Elles sont rangées dans le dossier **`dossierurba-data`**, à côté du dossier du site (voir [docs/architecture.md](docs/architecture.md)). Le déploiement n'y touche pas.

`config.php` contient les identifiants de la base. Il est actuellement suivi par Git, sur décision du propriétaire du projet.
