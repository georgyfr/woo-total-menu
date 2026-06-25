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
| [v1.0.4](./v1.0.4.md) | 2026-06-24 | ✅ Livrée | Schéma JSON strict + validateur par type d'item/widget + 57 tests unitaires |
| [v1.1.0](./v1.1.0.md) | 2026-06-24 | ✅ Livrée | Builder visuel React — squelette (3 colonnes, stores @wordpress/data, build pipeline) |
| [v1.1.1](./v1.1.1.md) | 2026-06-24 | ✅ Livrée | CRUD items dans l'UI (6 types, suppression, renommage inline, édition propriétés) |
| [v1.1.2](./v1.1.2.md) | 2026-06-25 | ✅ Livrée | Drag & drop arborescent + Undo/Redo + raccourcis clavier + a11y ARIA |
| [v1.1.3](./v1.1.3.md) | 2026-06-25 | ✅ Livrée | Fixes UX : indicateur drop temps réel + migration React 18 createRoot + annonce ARIA unique |
| [v1.1.4](./v1.1.4.md) | 2026-06-25 | ✅ Livrée | Live preview via iframe + postMessage (debounce 250 ms, modes responsive desktop/tablet/mobile) |
| [v1.1.5](./v1.1.5.md) | 2026-06-25 | ✅ Livrée | Historique des révisions WordPress — modal Historique, 3 routes REST, filtre `wtm_max_revisions`, prévisualisation de révision, restauration avec confirmation |
| [v1.2.0](./v1.2.0.md) | 2026-06-25 | ✅ Livrée | Rendu frontend complet — Menu_Renderer, Location_Interceptor, Dynamic_CSS, Shortcode `[wtm_menu]`, assets frontend conditionnels, 4 widgets WooCommerce rendus côté visiteur |
| [v1.3.0](./v1.3.0.md) | 2026-06-25 | ✅ Livrée | Widgets WooCommerce avancés — 4 nouveaux widgets (recent_posts, social_icons, newsletter, filters) + upgrades mini_cart (drawer AJAX) et search (suggestions live) + 2 routes REST publiques + 1 admin-ajax handler |
| [v1.4.0](./v1.4.0.md) | 2026-06-26 | ✅ Livrée | Header & Footer Builder — ModulePalette + LayoutCanvas + ModuleProperties, 9 module types, Header_Footer_Renderer + Header_Footer_Injector (wp_body_open + wp_footer), 2 nouvelles meta `_wtm_header_config`/`_wtm_footer_config`, settings `header_footer`, 5 hooks/filters développeurs |
| [v1.5.0](./v1.5.0.md) | 2026-06-26 | ✅ Livrée | Système de templates — 12 templates intégrés (4 menus + 4 headers + 4 footers), Template_Registry + Templates_Controller (3 routes REST /wtm/v1/templates), galerie visuelle Builder avec mini-previews CSS, store Redux wtm/templates, 2 hooks/filters développeurs |
| [v1.6.0](./v1.6.0.md) | 2026-06-26 | ✅ Livrée | Phase Polish — Rôles personnalisés (Roles_Manager + 5 routes REST /wtm/v1/roles), 3 blocs Gutenberg server-rendered (wtm/menu, wtm/header, wtm/footer), intégrations Elementor/Bricks/Oxygen, Multisite_Manager (activation réseau + wpmu_new_blog), 8 hooks développeurs |
| [v1.7.0](./v1.7.0.md) | 2026-06-26 | ✅ Livrée | Menus conditionnels (Condition_Evaluator, 10 types de règles, ET/OU, méta _wtm_conditions, 4 routes REST /conditions, panneau Builder React) + Analytics simple privacy-friendly (compteurs journaliers, 2 routes REST /analytics, tracking JS sendBeacon, page dashboard avec chart HTML/CSS) |

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
- **v1.4.x** — Phase Header/Footer : builders complémentaires ✅
- **v1.5.x** — Phase Templates : bibliothèque de templates ✅
- **v1.6.x** — Phase Polish : rôles, blocs, compatibilité, multisite ✅
- **v1.7.x** — Phase Bonus : menus conditionnels, analytics simple

Le numéro de **patch** (3e chiffre) est incrémenté pour chaque version livrée au sein d'une même phase.
