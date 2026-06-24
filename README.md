# Woo Total Menu

> Plugin WordPress/WooCommerce de création de méga menus, headers et footers avancés via un builder visuel glisser-déposer.

![Version](https://img.shields.io/badge/version-1.0.1-6C5CE7)
![PHP](https://img.shields.io/badge/PHP-%E2%89%A5%207.4-777BB4)
![WordPress](https://img.shields.io/badge/WordPress-%E2%89%A5%206.3-21759B)
![WooCommerce](https://img.shields.io/badge/WooCommerce-%E2%89%A5%208.0-7F54B3)
![License](https://img.shields.io/badge/license-GPL--2.0+-4E5158)

## 📖 Présentation

Woo Total Menu est pensé pour combler un vide : les solutions de méga menu actuelles sont soit trop complexes (courbe d'apprentissage énorme), soit trop limitées (pas de headers/footers), soit mal intégrées à WooCommerce. Ce plugin propose le couteau suisse de la navigation, capable de créer en quelques clics :

- **Méga menus horizontaux** mettant en valeur les catégories de produits, articles et pages.
- **Menus verticaux** (sidebar navigation, filtres, menu catalogue).
- **Headers complets** (logo, barre de recherche, icônes panier/compte, menu secondaire).
- **Footers complets** (multi-colonnes, widgets, liens légaux, réseaux sociaux).

Le tout avec une approche glisser-déposer visuelle côté admin, et un rendu 100% responsive, moderne et personnalisable côté frontend, sans toucher une ligne de code.

## 📦 Installation

### Méthode 1 : Téléchargement direct

1. Téléchargez la dernière version : [`woo-total-menu-1.0.1.zip`](../../releases/tag/v1.0.1)
2. Dans WordPress : **Extensions → Ajouter → Téléverser une extension**
3. Téléversez le fichier ZIP, cliquez sur **Installer**, puis **Activer**
4. WooCommerce doit être actif (notification sinon)

### Méthode 2 : Manuellement via FTP

1. Décompressez le ZIP
2. Uploadez le dossier `woo-total-menu` dans `/wp-content/plugins/`
3. Activez le plugin via le menu **Extensions** de WordPress

### Méthode 3 : Git (pour développeurs)

```bash
cd /wp-content/plugins/
git clone https://github.com/VOTRE-USER/woo-total-menu.git
# Puis activation dans wp-admin
```

## 🚀 État d'avancement

| Version | Statut | Contenu principal |
|---------|--------|-------------------|
| v1.0.0 | ✅ Livrée | Squelette, Bootstrap, Cache, Permissions, Page About |
| v1.0.1 | ✅ Livrée | CPT `wtm_menu`, 6 méta-keys, 4 types, 4 locations, méta-boxes |
| v1.0.2 | 🚧 À venir | Pages admin (Dashboard, Liste menus, Réglages globaux) |
| v1.0.3 | 📋 Planifié | API REST CRUD `/wtm/v1/menus` |
| v1.0.4 | 📋 Planifié | Schéma JSON de configuration + validateur |
| v1.1.x | 📋 Planifié | Builder visuel React (SPA `@wordpress/scripts`) |
| v1.2.x | 📋 Planifié | Rendu frontend (Menu_Walker, méga menu, off-canvas mobile) |
| v1.3.x | 📋 Planifié | Widgets WooCommerce (catégories, produits, mini-panier) |
| v1.4.x | 📋 Planifié | Header & Footer Builder |
| v1.5.x | 📋 Planifié | Système de templates (12+ templates intégrés) |
| v1.6.x | 📋 Planifié | Rôles, blocs Gutenberg, compatibilité Elementor/Bricks/Oxygen, multisite |
| v1.7.x | 📋 Planifié | Menus conditionnels, analytics simple |

👉 Voir le [`CHANGELOG.md`](CHANGELOG.md) pour le détail de chaque version.

## 🏗️ Architecture technique

```
woo-total-menu/
├── woo-total-menu.php          # Point d'entrée + constantes + autoloader PSR-4
├── readme.txt                  # Doc WordPress.org
├── CHANGELOG.md
├── src/
│   ├── Bootstrap.php           # Classe centrale (boot, deps check, services)
│   ├── Admin/
│   │   ├── Pages/
│   │   │   └── About.php       # Page "À propos" admin
│   │   └── Meta_Boxes.php      # 6 méta-boxes (v1.0.1)
│   ├── Core/
│   │   ├── Cache_Manager.php   # Cache objet + transients
│   │   ├── Permissions.php     # 4 capacités personnalisées
│   │   └── CPT_Manager.php     # CPT wtm_menu + 4 locations (v1.0.1)
│   ├── Frontend/
│   │   └── Assets_Loader.php   # Stub (no-op pour l'instant)
│   ├── Api/                    # (vide, v1.0.3)
│   └── Entities/               # (vide, versions futures)
├── assets/  build/  languages/  templates/   # Dossiers vides
```

**Stack cible** :
- PHP 7.4+ / WordPress 6.3+ / WooCommerce 8.0+
- Autoloader PSR-4 (namespace `WooTotalMenu\`)
- API REST WordPress comme colonne vertébrale (`/wp-json/wtm/v1/`)
- React + `@wordpress/scripts` pour le builder (v1.1.x)
- MariaDB/MySQL via CPT + postmeta (pas de tables custom pour les menus)

## 🔧 Développement local

### Prérequis
- PHP 7.4+ avec extensions : mysqli, pdo_mysql, mbstring, xml, gd, intl, curl, zip, bcmath
- MySQL 5.7+ ou MariaDB 10.4+
- Un serveur web (Apache/Nginx) ou le serveur intégré PHP

### Installation rapide (avec WP-CLI)

```bash
# 1. Cloner le plugin dans wp-content/plugins/
cd wp-content/plugins/
git clone https://github.com/VOTRE-USER/woo-total-menu.git

# 2. Activer le plugin
wp plugin activate woo-total-menu

# 3. Vérifier l'installation
wp eval 'echo WTM_VERSION;'   # doit afficher 1.0.1
```

### Tests smoke

```bash
# Vérifier que le CPT est enregistré
wp eval 'var_dump(get_post_type_object("wtm_menu") !== null);'

# Vérifier que les 4 locations sont présentes
wp eval 'foreach(get_registered_nav_menus() as $k=>$v) if(strpos($k,"wtm_")===0) echo "$k\n";'
```

## 🎯 Fonctionnalités planifiées (non encore implémentées)

| Fonctionnalité | Version cible | Statut |
|----------------|---------------|--------|
| CPT `wtm_menu` + méta-boxes | v1.0.1 | ✅ |
| Pages admin complètes | v1.0.2 | 🚧 |
| API REST CRUD menus | v1.0.3 | 📋 |
| Builder visuel React | v1.1.x | 📋 |
| Méga menu horizontal | v1.2.x | 📋 |
| Menu vertical / off-canvas | v1.2.x | 📋 |
| Widgets WooCommerce | v1.3.x | 📋 |
| Header & Footer Builder | v1.4.x | 📋 |
| Templates intégrés | v1.5.x | 📋 |
| Rôles & permissions UI | v1.6.x | 📋 |
| Compatibilité Elementor/Bricks/Oxygen | v1.6.x | 📋 |
| Multisite | v1.6.x | 📋 |
| Menus conditionnels | v1.7.x | 📋 |
| Analytics simple | v1.7.x | 📋 |

## ❌ Hors périmètre (exclu du projet)

Les fonctionnalités suivantes ont été jugées **non réalisables** dans le cadre de ce projet :

- ❌ Assistant IA conversationnel "Chat Menu Designer"
- ❌ Génération d'images via DALL·E / Stable Diffusion
- ❌ Auto-adaptation ML temps réel des menus
- ❌ Heatmaps intégrées
- ❌ WebLLM embarqué côté navigateur
- ❌ Support vocal pour dicter les commandes
- ❌ Promesse "zéro impact performance" (visé : < 25 Ko gzippé)
- ❌ Compatibilité universelle avec tous les thèmes

## 📚 Documentation

- [`CHANGELOG.md`](CHANGELOG.md) — Journal détaillé des versions
- [`readme.txt`](readme.txt) — Documentation WordPress.org
- Specs fonctionnelles et techniques : voir le document de cadrage interne

## 🤝 Contribution

Les contributions sont les bienvenues ! Pour contribuer :

1. Forkez le projet
2. Créez une branche : `git checkout -b feature/ma-feature`
3. Commitez : `git commit -m 'feat: ajout de ma feature'`
4. Pushez : `git push origin feature/ma-feature`
5. Ouvrez une Pull Request

### Conventions de commit (Angular)

- `feat:` Nouvelle fonctionnalité
- `fix:` Correction de bug
- `docs:` Documentation
- `style:` Formatage (sans impact code)
- `refactor:` Refactorisation
- `test:` Ajout de tests
- `chore:` Tâches diverses

## 📜 Licence

GPL-2.0-or-later — Voir [LICENSE](LICENSE) ou <https://www.gnu.org/licenses/gpl-2.0.html>

## 👥 Auteurs

- **Woo Total Menu Team** — [GitHub](https://github.com/woo-total-menu)

---

<p align="center">
  Fait avec ❤️ pour la communauté WordPress/WooCommerce
</p>
