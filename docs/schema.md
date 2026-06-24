# Schéma JSON de configuration Woo Total Menu

Ce document décrit le format JSON exact attendu par les champs `_wtm_config`, `_wtm_header_config` et `_wtm_footer_config` des menus Woo Total Menu.

**Version actuelle du schéma** : `1`
**Validé par** : `WooTotalMenu\Core\Schema_Validator` (depuis v1.0.4)
**Endpoint de référence** : `GET /wp-json/wtm/v1/menus/schema`

---

## Sommaire

1. [Config d'un menu (`_wtm_config`)](#1-config-dun-menu-_wtm_config)
2. [Items de menu](#2-items-de-menu)
   - 2.1 [link](#21-link)
   - 2.2 [mega_container](#22-mega_container)
   - 2.3 [column](#23-column)
   - 2.4 [widget](#24-widget)
   - 2.5 [title](#25-title)
   - 2.6 [separator](#26-separator)
3. [Types de widgets](#3-types-de-widgets)
4. [Badges](#4-badges)
5. [Réglages (`settings`)](#5-réglages-settings)
6. [Layout header/footer (`_wtm_header_config` / `_wtm_footer_config`)](#6-layout-headerfooter)
7. [Codes d'erreur](#7-codes-derreur)
8. [Exemples complets](#8-exemples-complets)

---

## 1. Config d'un menu (`_wtm_config`)

```json
{
  "version": 1,
  "items": [ ... ],
  "settings": { ... }   // optionnel
}
```

| Champ | Type | Requis | Défaut | Description |
|-------|------|--------|--------|-------------|
| `version` | integer | non | `1` | Version du schéma |
| `items` | array | non | `[]` | Arborescence des items du menu |
| `settings` | object | non | — | Réglages globaux du menu (sticky, mobile, etc.) |

---

## 2. Items de menu

Tous les items partagent ces champs communs :

| Champ | Type | Requis | Description |
|-------|------|--------|-------------|
| `id` | string (non vide) | ✅ | Identifiant unique de l'item |
| `type` | string (enum) | ✅ | Type d'item (voir ci-dessous) |
| `visibility` | string (enum) | non | `show` / `hide` / `show_on_mobile` / `hide_on_mobile` |
| `classes` | array<string> | non | Classes CSS additionnelles |
| `children` | array | non | Items enfants (selon le type) |

### 2.1 `link`

Un lien simple dans le menu.

| Champ | Type | Requis | Description |
|-------|------|--------|-------------|
| `id` | string | ✅ | — |
| `type` | `"link"` | ✅ | — |
| `label` | string | ✅ | Texte affiché |
| `url` | string | ✅ | URL cible |
| `target` | enum | non | `_self` (défaut) ou `_blank` |
| `icon` | string | non | Nom de l'icône (Phosphor/FA) ou URL SVG |
| `badge` | object | non | Voir [Badges](#4-badges) |
| `children` | array<link> | non | Sous-menu (récursif) |

**Exemple** :
```json
{
  "id": "link-accueil",
  "type": "link",
  "label": "Accueil",
  "url": "/",
  "icon": "house",
  "badge": {
    "text": "Nouveau",
    "color": "#FFFFFF",
    "background": "#6C5CE7"
  }
}
```

### 2.2 `mega_container`

Un conteneur de méga menu. Au survol ou au clic, ouvre un panneau multi-colonnes.

| Champ | Type | Requis | Description |
|-------|------|--------|-------------|
| `id` | string | ✅ | — |
| `type` | `"mega_container"` | ✅ | — |
| `label` | string | ✅ | Texte du trigger |
| `trigger` | enum | non | `hover` (défaut) ou `click` |
| `width` | integer \| `"full"` | non | Largeur du panneau en px (200-2000) ou `"full"` |
| `children` | array<column> | ✅ | Liste des colonnes (au moins 1) |

⚠️ **Contrainte** : les enfants doivent tous être de type `column`.

**Exemple** :
```json
{
  "id": "mega-femmes",
  "type": "mega_container",
  "label": "Femmes",
  "trigger": "hover",
  "width": 800,
  "children": [
    { "id": "col-1", "type": "column", "width": 4, "children": [...] },
    { "id": "col-2", "type": "column", "width": 8, "children": [...] }
  ]
}
```

### 2.3 `column`

Une colonne à l'intérieur d'un `mega_container`. Contient des widgets, liens, titres ou séparateurs.

| Champ | Type | Requis | Description |
|-------|------|--------|-------------|
| `id` | string | ✅ | — |
| `type` | `"column"` | ✅ | — |
| `width` | integer | non | Largeur de 1 à 12 (grille Bootstrap-like). Défaut : `auto` |
| `children` | array | non | Items de type `widget`, `link`, `title` ou `separator` |

### 2.4 `widget`

Un widget dynamique à l'intérieur d'une colonne. Le type de widget détermine les `widget_settings` attendus.

| Champ | Type | Requis | Description |
|-------|------|--------|-------------|
| `id` | string | ✅ | — |
| `type` | `"widget"` | ✅ | — |
| `widget_type` | enum | ✅ | Voir [Types de widgets](#3-types-de-widgets) |
| `widget_settings` | object | ✅ | Configuration spécifique au widget |
| `label` | string | non | Titre optionnel au-dessus du widget |
| `children` | array | non | Sous-items (rarement utilisé) |

### 2.5 `title`

Un titre de section dans une colonne (généralement en gras, sans lien).

| Champ | Type | Requis | Description |
|-------|------|--------|-------------|
| `id` | string | ✅ | — |
| `type` | `"title"` | ✅ | — |
| `label` | string | ✅ | Texte du titre |

### 2.6 `separator`

Un séparateur visuel (ligne horizontale).

| Champ | Type | Requis | Description |
|-------|------|--------|-------------|
| `id` | string | ✅ | — |
| `type` | `"separator"` | ✅ | — |

Aucun autre champ requis.

---

## 3. Types de widgets

Les 8 types de widgets supportés (implémentation du rendu en v1.3.x) :

### `category_grid` — Grille de catégories WooCommerce

| Setting | Type | Requis | Défaut | Description |
|---------|------|--------|--------|-------------|
| `columns` | integer (1-6) | non | `3` | Nombre de colonnes |
| `categories` | array<integer> | non | — | IDs de catégories à afficher |
| `show_images` | boolean | non | `true` | Afficher les images |
| `show_counts` | boolean | non | `false` | Afficher le nombre de produits |

### `product_grid` — Grille de produits

| Setting | Type | Requis | Défaut | Description |
|---------|------|--------|--------|-------------|
| `columns` | integer (1-6) | non | `4` | Nombre de colonnes |
| `product_source` | enum | non | `featured` | `featured` / `best_selling` / `recent` / `on_sale` / `custom` |
| `limit` | integer (1-12) | non | `4` | Nombre de produits |
| `product_ids` | array<integer> | non | — | Si `product_source=custom` |

### `mini_cart` — Mini-panier

| Setting | Type | Requis | Défaut | Description |
|---------|------|--------|--------|-------------|
| `show_subtotal` | boolean | non | `true` | Afficher le sous-total |
| `show_checkout_button` | boolean | non | `true` | Bouton "Commander" |
| `show_thumbnail` | boolean | non | `true` | Vignettes des produits |

### `search` — Barre de recherche produits

| Setting | Type | Requis | Défaut | Description |
|---------|------|--------|--------|-------------|
| `placeholder` | string | non | `"Rechercher..."` | Placeholder de l'input |
| `show_category_filter` | boolean | non | `false` | Sélecteur de catégorie avant l'input |
| `limit` | integer (1-20) | non | `10` | Nombre de suggestions affichées |

### `banner` — Bannière promotionnelle

| Setting | Type | Requis | Description |
|---------|------|--------|-------------|
| `image_url` | string | ✅ | URL de l'image |
| `link_url` | string | non | URL cible au clic |
| `alt` | string | non | Texte alternatif |
| `target` | enum | non | `_self` ou `_blank` |

### `html` — HTML libre

| Setting | Type | Requis | Description |
|---------|------|--------|-------------|
| `content` | string | ✅ | Contenu HTML (sanitisé à l'affichage) |

### `custom_link` — Lien dans une colonne

| Setting | Type | Requis | Description |
|---------|------|--------|-------------|
| `label` | string | ✅ | Texte du lien |
| `url` | string | ✅ | URL cible |
| `target` | enum | non | `_self` ou `_blank` |

### `title` — Titre de section (widget)

| Setting | Type | Requis | Description |
|---------|------|--------|-------------|
| `text` | string | ✅ | Texte du titre |
| `level` | integer (1-6) | non | Niveau HTML (h1-h6). Défaut : `4` |

---

## 4. Badges

Petit marqueur coloré à côté d'un lien (ex : "Nouveau", "-20%").

| Champ | Type | Requis | Description |
|-------|------|--------|-------------|
| `text` | string | ✅ | Texte affiché |
| `color` | string (hex) | non | Couleur du texte (`#RGB` ou `#RRGGBB`) |
| `background` | string (hex) | non | Couleur de fond |

**Exemple** :
```json
{
  "text": "-25%",
  "color": "#FFFFFF",
  "background": "#E74C3C"
}
```

---

## 5. Réglages (`settings`)

Le champ `settings` de la config accepte les clés suivantes :

| Clé | Type | Défaut | Description |
|-----|------|--------|-------------|
| `sticky` | boolean | `false` | Menu sticky en haut de page au scroll |
| `mobile_behavior` | enum | `offcanvas` | `offcanvas` / `accordion` / `dropdown` |
| `mobile_breakpoint` | integer | `768` | Largeur de bascule en mode mobile (px) |

Toutes les autres clés sont tolérées (forward-compatibilité).

---

## 6. Layout header/footer

Les champs `_wtm_header_config` et `_wtm_footer_config` suivent une structure différente basée sur des **lignes → colonnes → modules**.

```json
{
  "version": 1,
  "rows": [
    {
      "id": "row-1",
      "columns": [
        {
          "id": "col-1",
          "width": 3,
          "modules": [
            { "id": "logo-1", "type": "logo", "settings": { "image_id": 42 } }
          ]
        }
      ]
    }
  ],
  "settings": { ... }
}
```

### Structure

| Niveau | Champs requis | Champs optionnels |
|--------|---------------|-------------------|
| **Row** | `id`, `columns` | — |
| **Column** | `id`, `modules` | `width` (1-12) |
| **Module** | `id`, `type` | `settings` |

### Types de modules (9)

| Type | Description |
|------|-------------|
| `logo` | Logo du site |
| `menu` | Un menu WTM (par `menu_id`) |
| `search` | Barre de recherche |
| `cart` | Mini-panier |
| `button` | Bouton CTA |
| `html` | HTML libre |
| `social` | Icônes réseaux sociaux |
| `newsletter` | Formulaire newsletter |
| `text` | Texte libre |

---

## 7. Codes d'erreur

Le validateur renvoie des `WP_Error` avec les codes suivants :

### Erreurs générales

| Code | Description |
|------|-------------|
| `wtm_invalid_json` | JSON mal formé |
| `wtm_invalid_config_type` | La config n'est pas un objet |
| `wtm_invalid_version` | `version` n'est pas un entier |
| `wtm_invalid_items` | `items` n'est pas un tableau |
| `wtm_invalid_settings` | `settings` n'est pas un objet |

### Erreurs d'items

| Code | Description |
|------|-------------|
| `wtm_invalid_item` | L'item n'est pas un objet |
| `wtm_missing_item_id` | `id` manquant ou vide |
| `wtm_invalid_item_type` | `type` non autorisé |
| `wtm_invalid_visibility` | `visibility` non autorisée |
| `wtm_invalid_classes` | `classes` n'est pas un tableau |

### Erreurs link

| Code | Description |
|------|-------------|
| `wtm_link_missing_label` | `label` manquant |
| `wtm_link_missing_url` | `url` manquant |
| `wtm_link_invalid_target` | `target` invalide |
| `wtm_link_invalid_icon` | `icon` n'est pas une string |
| `wtm_link_invalid_children` | `children` n'est pas un tableau |

### Erreurs mega_container

| Code | Description |
|------|-------------|
| `wtm_mega_missing_label` | `label` manquant |
| `wtm_mega_missing_children` | `children` manquant |
| `wtm_mega_empty_children` | `children` vide |
| `wtm_mega_invalid_child` | Un child n'est pas de type `column` |
| `wtm_mega_invalid_trigger` | `trigger` invalide |
| `wtm_mega_invalid_width` | `width` invalide |

### Erreurs column

| Code | Description |
|------|-------------|
| `wtm_column_invalid_width` | `width` hors de [1, 12] |
| `wtm_column_invalid_children` | `children` n'est pas un tableau |
| `wtm_column_invalid_child_type` | Type d'enfant non autorisé dans une colonne |

### Erreurs widget

| Code | Description |
|------|-------------|
| `wtm_widget_missing_type` | `widget_type` manquant ou inconnu |
| `wtm_widget_missing_settings` | `widget_settings` manquant |
| `wtm_widget_invalid_label` | `label` n'est pas une string |
| `wtm_widget_invalid_children` | `children` n'est pas un tableau |
| `wtm_widget_invalid_columns` | `columns` hors de [1, 6] |
| `wtm_widget_invalid_categories` | `categories` n'est pas un tableau |
| `wtm_widget_invalid_source` | `product_source` invalide |
| `wtm_widget_invalid_limit` | `limit` hors de [1, 12] |
| `wtm_widget_invalid_bool` | Un champ booléen a une mauvaise valeur |
| `wtm_widget_invalid_placeholder` | `placeholder` n'est pas une string |
| `wtm_widget_missing_image` | `image_url` manquant pour `banner` |
| `wtm_widget_invalid_target` | `target` invalide |
| `wtm_widget_missing_content` | `content` manquant pour `html` |
| `wtm_widget_missing_label` | `label` manquant pour `custom_link` |
| `wtm_widget_missing_url` | `url` manquant pour `custom_link` |
| `wtm_widget_missing_text` | `text` manquant pour `title` |
| `wtm_widget_invalid_level` | `level` hors de [1, 6] |

### Erreurs badge

| Code | Description |
|------|-------------|
| `wtm_badge_invalid` | Le badge n'est pas un objet |
| `wtm_badge_missing_text` | `text` manquant |
| `wtm_badge_invalid_color` | `color` n'est pas un hex valide |
| `wtm_badge_invalid_background` | `background` n'est pas un hex valide |

### Erreurs layout (header/footer)

| Code | Description |
|------|-------------|
| `wtm_invalid_layout_type` | Le layout n'est pas un objet |
| `wtm_invalid_layout_version` | `version` invalide |
| `wtm_invalid_rows` | `rows` n'est pas un tableau |
| `wtm_invalid_layout_settings` | `settings` n'est pas un objet |
| `wtm_invalid_row` | Une row n'est pas un objet |
| `wtm_row_missing_id` | `id` manquant |
| `wtm_row_missing_columns` | `columns` manquant |
| `wtm_invalid_column` | Une colonne n'est pas un objet |
| `wtm_column_missing_id` | `id` manquant |
| `wtm_column_missing_modules` | `modules` manquant |
| `wtm_invalid_module` | Un module n'est pas un objet |
| `wtm_module_missing_id` | `id` manquant |
| `wtm_module_invalid_type` | `type` non autorisé |
| `wtm_module_invalid_settings` | `settings` n'est pas un objet |

---

## 8. Exemples complets

### 8.1 Menu horizontal simple avec 3 liens

```json
{
  "version": 1,
  "items": [
    { "id": "i1", "type": "link", "label": "Accueil", "url": "/" },
    { "id": "i2", "type": "link", "label": "Boutique", "url": "/shop/" },
    { "id": "i3", "type": "link", "label": "Contact", "url": "/contact/" }
  ]
}
```

### 8.2 Méga menu complet pour boutique de mode

```json
{
  "version": 1,
  "items": [
    {
      "id": "mega-femmes",
      "type": "mega_container",
      "label": "Femmes",
      "trigger": "hover",
      "width": 1000,
      "children": [
        {
          "id": "col-1",
          "type": "column",
          "width": 3,
          "children": [
            { "id": "t1", "type": "title", "label": "Vêtements" },
            { "id": "l1", "type": "link", "label": "Robes", "url": "/categorie/robes/" },
            { "id": "l2", "type": "link", "label": "Jupes", "url": "/categorie/jupes/" },
            { "id": "l3", "type": "link", "label": "Pantalons", "url": "/categorie/pantalons/" }
          ]
        },
        {
          "id": "col-2",
          "type": "column",
          "width": 3,
          "children": [
            { "id": "t2", "type": "title", "label": "Accessoires" },
            { "id": "l4", "type": "link", "label": "Sacs", "url": "/categorie/sacs/" },
            { "id": "l5", "type": "link", "label": "Ceintures", "url": "/categorie/ceintures/" }
          ]
        },
        {
          "id": "col-3",
          "type": "column",
          "width": 6,
          "children": [
            {
              "id": "w1",
              "type": "widget",
              "widget_type": "product_grid",
              "widget_settings": {
                "columns": 2,
                "product_source": "featured",
                "limit": 2
              },
              "label": "Sélection de la semaine"
            },
            {
              "id": "w2",
              "type": "widget",
              "widget_type": "banner",
              "widget_settings": {
                "image_url": "/wp-content/uploads/2026/promo-femmes.jpg",
                "link_url": "/promotions/femmes/",
                "alt": "Promo Femmes -25%"
              }
            }
          ]
        }
      ]
    },
    {
      "id": "mega-hommes",
      "type": "mega_container",
      "label": "Hommes",
      "trigger": "hover",
      "width": 800,
      "children": [
        {
          "id": "col-4",
          "type": "column",
          "width": 6,
          "children": [
            { "id": "t3", "type": "title", "label": "Vêtements" },
            { "id": "l6", "type": "link", "label": "Chemises", "url": "/categorie/chemises/" },
            { "id": "l7", "type": "link", "label": "T-shirts", "url": "/categorie/t-shirts/" }
          ]
        },
        {
          "id": "col-5",
          "type": "column",
          "width": 6,
          "children": [
            {
              "id": "w3",
              "type": "widget",
              "widget_type": "category_grid",
              "widget_settings": {
                "columns": 2,
                "categories": [18, 19, 20, 21],
                "show_images": true,
                "show_counts": true
              }
            }
          ]
        }
      ]
    },
    { "id": "i-blog", "type": "link", "label": "Blog", "url": "/blog/" },
    { "id": "i-contact", "type": "link", "label": "Contact", "url": "/contact/" }
  ],
  "settings": {
    "sticky": true,
    "mobile_behavior": "offcanvas",
    "mobile_breakpoint": 768
  }
}
```

### 8.3 Layout de header

```json
{
  "version": 1,
  "rows": [
    {
      "id": "row-top",
      "columns": [
        {
          "id": "col-top-info",
          "width": 12,
          "modules": [
            {
              "id": "text-info",
              "type": "text",
              "settings": { "content": "Livraison offerte dès 50€" }
            }
          ]
        }
      ]
    },
    {
      "id": "row-main",
      "columns": [
        {
          "id": "col-logo",
          "width": 3,
          "modules": [
            { "id": "logo", "type": "logo", "settings": { "image_id": 42 } }
          ]
        },
        {
          "id": "col-menu",
          "width": 6,
          "modules": [
            { "id": "main-menu", "type": "menu", "settings": { "menu_id": 12 } }
          ]
        },
        {
          "id": "col-actions",
          "width": 3,
          "modules": [
            { "id": "search", "type": "search", "settings": { "placeholder": "Rechercher..." } },
            { "id": "cart", "type": "cart", "settings": { "show_subtotal": true } },
            { "id": "account", "type": "button", "settings": { "label": "Mon compte", "url": "/mon-compte/" } }
          ]
        }
      ]
    }
  ],
  "settings": {
    "sticky": true
  }
}
```

### 8.4 Layout de footer

```json
{
  "version": 1,
  "rows": [
    {
      "id": "row-main-footer",
      "columns": [
        {
          "id": "col-about",
          "width": 3,
          "modules": [
            { "id": "logo-footer", "type": "logo" },
            { "id": "text-about", "type": "text", "settings": { "content": "Boutique créée en 2026..." } },
            { "id": "social", "type": "social", "settings": { "networks": ["facebook", "instagram", "twitter"] } }
          ]
        },
        {
          "id": "col-links-1",
          "width": 3,
          "modules": [
            { "id": "menu-links-1", "type": "menu", "settings": { "menu_id": 13 } }
          ]
        },
        {
          "id": "col-links-2",
          "width": 3,
          "modules": [
            { "id": "menu-links-2", "type": "menu", "settings": { "menu_id": 14 } }
          ]
        },
        {
          "id": "col-newsletter",
          "width": 3,
          "modules": [
            { "id": "newsletter", "type": "newsletter", "settings": { "placeholder": "Votre email" } }
          ]
        }
      ]
    }
  ]
}
```

---

## Références

- **Code source** : `src/Core/Schema_Validator.php`
- **Tests unitaires** : 57 tests dans `/home/z/my-project/server/scripts/test-schema-validator.php`
- **Endpoint REST** : `GET /wp-json/wtm/v1/menus/schema` (retourne le schéma JSON Schema draft-04)
- **Documentation API** : voir `versions/v1.0.3.md`
