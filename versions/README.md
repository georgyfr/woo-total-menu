# 📦 Historique détaillé des versions — Woo Total Menu

Ce dossier contient la documentation détaillée de chaque version du plugin **Woo Total Menu**, depuis la v1.0.0 initiale jusqu'à la version courante.

Chaque fichier `vX.Y.Z.md` décrit :
- la date de sortie
- les nouveautés ajoutées (Added)
- les modifications (Changed)
- les corrections (Fixed)
- les éléments retirés ou dépréciés (Removed / Deprecated)
- les fichiers créés / modifiés
- les tests réalisés
- les instructions de mise à jour (le cas échéant)
- les URLs GitHub (tag, release, ZIP)

## 📋 Sommaire

| Version | Date | Statut | Description courte |
|---------|------|--------|--------------------|
| [v1.0.0](./v1.0.0.md) | 2026-06-24 | ✅ Livrée | Squelette du plugin — Bootstrap, Cache, Permissions, page About |
| [v1.0.1](./v1.0.1.md) | 2026-06-24 | ✅ Livrée | CPT `wtm_menu` + méta-boxes + 4 locations |
| [v1.0.2](./v1.0.2.md) | 2026-06-24 | ✅ Livrée | Pages admin : Dashboard + Liste des menus + Réglages globaux |
| [v1.0.3](./v1.0.3.md) | 2026-06-24 | ✅ Livrée | API REST CRUD `/wtm/v1/menus` (8 endpoints) + Schema_Validator |
| v1.0.4 | à venir | 📋 Planifiée | Schéma JSON de configuration + validateur complet |
| v1.1.x | à venir | 📋 Planifiée | Builder visuel React (SPA `@wordpress/scripts`) |
| v1.2.x | à venir | 📋 Planifiée | Rendu frontend (Menu_Walker, méga menu, off-canvas mobile) |
| v1.3.x | à venir | 📋 Planifiée | Widgets WooCommerce (catégories, produits, mini-panier) |
| v1.4.x | à venir | 📋 Planifiée | Header & Footer Builder |
| v1.5.x | à venir | 📋 Planifiée | Système de templates (12+ templates intégrés) |
| v1.6.x | à venir | 📋 Planifiée | Rôles, blocs Gutenberg, compatibilité Elementor/Bricks/Oxygen, multisite |
| v1.7.x | à venir | 📋 Planifiée | Menus conditionnels, analytics simple |

## 🔗 Liens utiles

- 🏠 **Dépôt GitHub** : <https://github.com/georgyfr/woo-total-menu>
- 🏷️ **Toutes les releases** : <https://github.com/georgyfr/woo-total-menu/releases>
- 📋 **Changelog technique** : [`/CHANGELOG.md`](../CHANGELOG.md)
- 📖 **Documentation utilisateur** : [`/readme.txt`](../readme.txt)
- 🛠️ **Script de publication** : `/home/z/my-project/scripts/publish-release.sh`

## 🧭 Convention de versionnement

Ce projet suit une adaptation du **Semantic Versioning** :

- **v1.0.x** — Phase Fondations : socle technique, CPT, API REST, schéma JSON
- **v1.1.x** — Phase Builder UI : interface React
- **v1.2.x** — Phase Rendu Frontend : Menu_Walker, méga menu, off-canvas
- **v1.3.x** — Phase Widgets : widgets WooCommerce
- **v1.4.x** — Phase Header/Footer : builders complémentaires
- **v1.5.x** — Phase Templates : bibliothèque de templates
- **v1.6.x** — Phase Polish : rôles, blocs, compatibilité, multisite
- **v1.7.x** — Phase Bonus : menus conditionnels, analytics simple

Le numéro de **patch** (3e chiffre) est incrémenté pour chaque version livrée au sein d'une même phase.
