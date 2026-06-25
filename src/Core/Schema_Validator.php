<?php
/**
 * JSON schema validator for menu configurations.
 *
 * @package WooTotalMenu
 */

namespace WooTotalMenu\Core;

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

/**
 * Class Schema_Validator
 *
 * Validates the JSON structure of _wtm_config, _wtm_header_config and
 * _wtm_footer_config meta-values before they are stored.
 *
 * v1.0.4 introduces STRICT validation per item type:
 *
 *   link           : requires label + url ; optional target/icon/badge
 *   mega_container : requires label + children (columns)
 *   column         : optional width (1-12) ; optional children
 *   widget         : requires widget_type + widget_settings
 *   title          : requires label
 *   separator      : no required field besides id + type
 *
 * Widget types supported (will be implemented in v1.3.x):
 *   category_grid, product_grid, mini_cart, search,
 *   banner, html, custom_link, title
 *
 * Header/Footer layouts have their own structure: rows → columns → modules.
 * Supported modules: logo, menu, search, cart, button, html, social, newsletter.
 */
class Schema_Validator {

        /**
         * Current schema version.
         *
         * @var int
         */
        const SCHEMA_VERSION = 1;

        /**
         * Allowed top-level item types.
         *
         * @var array<int,string>
         */
        const ITEM_TYPES = array(
                'link',
                'mega_container',
                'column',
                'widget',
                'title',
                'separator',
        );

        /**
         * Allowed widget types.
         *
         * v1.2.0 — initial 7 widgets (category_grid, product_grid, mini_cart,
         *           search, banner, html, custom_link, title).
         * v1.3.0 — adds recent_posts, social_icons, newsletter, filters.
         *
         * @var array<int,string>
         */
        const WIDGET_TYPES = array(
                'category_grid', // Grille de catégories WooCommerce.
                'product_grid',  // Grille de produits (mis en avant, ventes, nouveautés).
                'mini_cart',     // Mini-panier avec fragments AJAX + drawer (v1.3.0).
                'search',        // Barre de recherche produits + suggestions AJAX (v1.3.0).
                'banner',        // Bannière promotionnelle (image + lien).
                'html',          // HTML libre.
                'custom_link',   // Lien simple (alias de link mais dans une colonne).
                'title',         // Titre de section.
                'recent_posts',  // Articles récents WordPress (v1.3.0).
                'social_icons',  // Icônes réseaux sociaux (v1.3.0).
                'newsletter',    // Formulaire d'abonnement email (v1.3.0).
                'filters',       // Filtres WooCommerce layered nav (v1.3.0).
        );

        /**
         * Allowed module types in header/footer layouts.
         *
         * @var array<int,string>
         */
        const MODULE_TYPES = array(
                'logo',
                'menu',
                'search',
                'cart',
                'button',
                'html',
                'social',
                'newsletter',
                'text',
        );

        /**
         * Allowed link targets.
         *
         * @var array<int,string>
         */
        const LINK_TARGETS = array( '_self', '_blank' );

        /**
         * Allowed mega container triggers.
         *
         * @var array<int,string>
         */
        const MEGA_TRIGGERS = array( 'hover', 'click' );

        /**
         * Allowed mobile behaviors.
         *
         * @var array<int,string>
         */
        const MOBILE_BEHAVIORS = array( 'offcanvas', 'accordion', 'dropdown' );

        /**
         * Allowed mobile visibility values.
         *
         * @var array<int,string>
         */
        const VISIBILITY_VALUES = array( 'show', 'hide', 'show_on_mobile', 'hide_on_mobile' );

        /**
         * Validate a _wtm_config value.
         *
         * @param mixed $value Decoded JSON value (array or object).
         * @return true|\WP_Error True on success, WP_Error on failure.
         */
        public static function validate_config( $value ) {
                if ( ! is_array( $value ) ) {
                        return new \WP_Error(
                                'wtm_invalid_config_type',
                                __( 'La configuration du menu doit être un objet JSON.', 'woo-total-menu' ),
                                array( 'status' => 400 )
                        );
                }

                // version field.
                if ( ! isset( $value['version'] ) ) {
                        $value['version'] = self::SCHEMA_VERSION;
                }
                if ( ! is_numeric( $value['version'] ) ) {
                        return new \WP_Error(
                                'wtm_invalid_version',
                                __( 'Le champ "version" doit être un entier.', 'woo-total-menu' ),
                                array( 'status' => 400 )
                        );
                }

                // items field.
                if ( ! isset( $value['items'] ) ) {
                        $value['items'] = array();
                }
                if ( ! is_array( $value['items'] ) ) {
                        return new \WP_Error(
                                'wtm_invalid_items',
                                __( 'Le champ "items" doit être un tableau.', 'woo-total-menu' ),
                                array( 'status' => 400 )
                        );
                }

                // settings field (optional).
                if ( isset( $value['settings'] ) ) {
                        $settings_check = self::validate_settings( $value['settings'] );
                        if ( is_wp_error( $settings_check ) ) {
                                return $settings_check;
                        }
                }

                // Recursively validate items (STRICT mode in v1.0.4).
                foreach ( $value['items'] as $index => $item ) {
                        $check = self::validate_item( $item, "items[{$index}]" );
                        if ( is_wp_error( $check ) ) {
                                return $check;
                        }
                }

                return true;
        }

        /**
         * Validate the top-level "settings" object of a config.
         *
         * @param mixed $settings Settings value.
         * @return true|\WP_Error
         */
        public static function validate_settings( $settings ) {
                if ( ! is_array( $settings ) ) {
                        return new \WP_Error(
                                'wtm_invalid_settings',
                                __( 'Le champ "settings" doit être un objet.', 'woo-total-menu' ),
                                array( 'status' => 400 )
                        );
                }

                // Check known settings keys (unknown keys are tolerated for forward-compat).
                if ( isset( $settings['mobile_behavior'] ) && ! in_array( $settings['mobile_behavior'], self::MOBILE_BEHAVIORS, true ) ) {
                        return new \WP_Error(
                                'wtm_invalid_mobile_behavior',
                                sprintf(
                                        /* translators: %s allowed values */
                                        __( 'Le paramètre "mobile_behavior" doit être une des valeurs : %s.', 'woo-total-menu' ),
                                        implode( ', ', self::MOBILE_BEHAVIORS )
                                ),
                                array( 'status' => 400 )
                        );
                }

                if ( isset( $settings['mobile_breakpoint'] ) && ! is_numeric( $settings['mobile_breakpoint'] ) ) {
                        return new \WP_Error(
                                'wtm_invalid_breakpoint',
                                __( 'Le paramètre "mobile_breakpoint" doit être un entier.', 'woo-total-menu' ),
                                array( 'status' => 400 )
                        );
                }

                if ( isset( $settings['sticky'] ) && ! is_bool( $settings['sticky'] ) ) {
                        return new \WP_Error(
                                'wtm_invalid_sticky',
                                __( 'Le paramètre "sticky" doit être un booléen.', 'woo-total-menu' ),
                                array( 'status' => 400 )
                        );
                }

                return true;
        }

        /**
         * Validate a single menu item — STRICT mode (v1.0.4).
         *
         * Dispatches to type-specific validators.
         *
         * @param mixed  $item Item to validate.
         * @param string $path Path for error messages.
         * @return true|\WP_Error
         */
        public static function validate_item( $item, $path = 'item' ) {
                if ( ! is_array( $item ) ) {
                        return new \WP_Error(
                                'wtm_invalid_item',
                                sprintf(
                                        /* translators: %s item path */
                                        __( 'L\'élément %s doit être un objet.', 'woo-total-menu' ),
                                        $path
                                ),
                                array( 'status' => 400 )
                        );
                }

                // id field (required, must be a non-empty string).
                if ( ! isset( $item['id'] ) || ! is_string( $item['id'] ) || '' === trim( $item['id'] ) ) {
                        return new \WP_Error(
                                'wtm_missing_item_id',
                                sprintf(
                                        /* translators: %s item path */
                                        __( 'L\'élément %s doit avoir un champ "id" (string non vide).', 'woo-total-menu' ),
                                        $path
                                ),
                                array( 'status' => 400 )
                        );
                }

                // type field (required, must be in ITEM_TYPES).
                if ( ! isset( $item['type'] ) || ! in_array( $item['type'], self::ITEM_TYPES, true ) ) {
                        return new \WP_Error(
                                'wtm_invalid_item_type',
                                sprintf(
                                        /* translators: 1: item path, 2: allowed types */
                                        __( 'L\'élément %1$s doit avoir un "type" parmi : %2$s.', 'woo-total-menu' ),
                                        $path,
                                        implode( ', ', self::ITEM_TYPES )
                                ),
                                array( 'status' => 400 )
                        );
                }

                // Common optional fields validation.
                if ( isset( $item['visibility'] ) && ! in_array( $item['visibility'], self::VISIBILITY_VALUES, true ) ) {
                        return new \WP_Error(
                                'wtm_invalid_visibility',
                                sprintf(
                                        /* translators: 1: item path, 2: allowed values */
                                        __( 'L\'élément %1$s : "visibility" doit être une des valeurs : %2$s.', 'woo-total-menu' ),
                                        $path,
                                        implode( ', ', self::VISIBILITY_VALUES )
                                ),
                                array( 'status' => 400 )
                        );
                }

                if ( isset( $item['classes'] ) && ! is_array( $item['classes'] ) ) {
                        return new \WP_Error(
                                'wtm_invalid_classes',
                                sprintf(
                                        /* translators: %s item path */
                                        __( 'L\'élément %s : "classes" doit être un tableau de strings.', 'woo-total-menu' ),
                                        $path
                                ),
                                array( 'status' => 400 )
                        );
                }

                // Dispatch to type-specific validator.
                switch ( $item['type'] ) {
                        case 'link':
                                return self::validate_item_link( $item, $path );
                        case 'mega_container':
                                return self::validate_item_mega_container( $item, $path );
                        case 'column':
                                return self::validate_item_column( $item, $path );
                        case 'widget':
                                return self::validate_item_widget( $item, $path );
                        case 'title':
                                return self::validate_item_title( $item, $path );
                        case 'separator':
                                return self::validate_item_separator( $item, $path );
                }

                return true;
        }

        /**
         * Validate a "link" item.
         *
         * Required: label, url
         * Optional: target (_self | _blank), icon, badge, children
         *
         * @param array  $item Item.
         * @param string $path Path.
         * @return true|\WP_Error
         */
        private static function validate_item_link( $item, $path ) {
                // label (required string).
                if ( ! isset( $item['label'] ) || ! is_string( $item['label'] ) ) {
                        return new \WP_Error(
                                'wtm_link_missing_label',
                                sprintf(
                                        /* translators: %s item path */
                                        __( 'L\'élément link %s doit avoir un champ "label" (string).', 'woo-total-menu' ),
                                        $path
                                ),
                                array( 'status' => 400 )
                        );
                }

                // url (required string).
                if ( ! isset( $item['url'] ) || ! is_string( $item['url'] ) ) {
                        return new \WP_Error(
                                'wtm_link_missing_url',
                                sprintf(
                                        /* translators: %s item path */
                                        __( 'L\'élément link %s doit avoir un champ "url" (string).', 'woo-total-menu' ),
                                        $path
                                ),
                                array( 'status' => 400 )
                        );
                }

                // target (optional, enum).
                if ( isset( $item['target'] ) && ! in_array( $item['target'], self::LINK_TARGETS, true ) ) {
                        return new \WP_Error(
                                'wtm_link_invalid_target',
                                sprintf(
                                        /* translators: 1: item path, 2: allowed values */
                                        __( 'L\'élément link %1$s : "target" doit être une des valeurs : %2$s.', 'woo-total-menu' ),
                                        $path,
                                        implode( ', ', self::LINK_TARGETS )
                                ),
                                array( 'status' => 400 )
                        );
                }

                // icon (optional string).
                if ( isset( $item['icon'] ) && ! is_string( $item['icon'] ) ) {
                        return new \WP_Error(
                                'wtm_link_invalid_icon',
                                sprintf(
                                        /* translators: %s item path */
                                        __( 'L\'élément link %s : "icon" doit être une string.', 'woo-total-menu' ),
                                        $path
                                ),
                                array( 'status' => 400 )
                        );
                }

                // badge (optional object with text + color).
                if ( isset( $item['badge'] ) ) {
                        $badge_check = self::validate_badge( $item['badge'], "{$path}.badge" );
                        if ( is_wp_error( $badge_check ) ) {
                                return $badge_check;
                        }
                }

                // children (optional, recursive — but link children must be links).
                if ( isset( $item['children'] ) ) {
                        if ( ! is_array( $item['children'] ) ) {
                                return new \WP_Error(
                                        'wtm_link_invalid_children',
                                        sprintf(
                                                /* translators: %s item path */
                                                __( 'L\'élément link %s : "children" doit être un tableau.', 'woo-total-menu' ),
                                                $path
                                        ),
                                        array( 'status' => 400 )
                                );
                        }
                        foreach ( $item['children'] as $index => $child ) {
                                $check = self::validate_item( $child, "{$path}.children[{$index}]" );
                                if ( is_wp_error( $check ) ) {
                                        return $check;
                                }
                        }
                }

                return true;
        }

        /**
         * Validate a "mega_container" item.
         *
         * Required: label, children (array of columns)
         * Optional: trigger (hover | click), width (int | "full"), settings
         *
         * @param array  $item Item.
         * @param string $path Path.
         * @return true|\WP_Error
         */
        private static function validate_item_mega_container( $item, $path ) {
                // label (required string).
                if ( ! isset( $item['label'] ) || ! is_string( $item['label'] ) ) {
                        return new \WP_Error(
                                'wtm_mega_missing_label',
                                sprintf(
                                        /* translators: %s item path */
                                        __( 'L\'élément mega_container %s doit avoir un champ "label" (string).', 'woo-total-menu' ),
                                        $path
                                ),
                                array( 'status' => 400 )
                        );
                }

                // children (required, must be array of columns).
                if ( ! isset( $item['children'] ) || ! is_array( $item['children'] ) ) {
                        return new \WP_Error(
                                'wtm_mega_missing_children',
                                sprintf(
                                        /* translators: %s item path */
                                        __( 'L\'élément mega_container %s doit avoir un tableau "children" (colonnes).', 'woo-total-menu' ),
                                        $path
                                ),
                                array( 'status' => 400 )
                        );
                }

                if ( empty( $item['children'] ) ) {
                        return new \WP_Error(
                                'wtm_mega_empty_children',
                                sprintf(
                                        /* translators: %s item path */
                                        __( 'L\'élément mega_container %s doit avoir au moins une colonne dans "children".', 'woo-total-menu' ),
                                        $path
                                ),
                                array( 'status' => 400 )
                        );
                }

                // trigger (optional, enum).
                if ( isset( $item['trigger'] ) && ! in_array( $item['trigger'], self::MEGA_TRIGGERS, true ) ) {
                        return new \WP_Error(
                                'wtm_mega_invalid_trigger',
                                sprintf(
                                        /* translators: 1: item path, 2: allowed values */
                                        __( 'L\'élément mega_container %1$s : "trigger" doit être une des valeurs : %2$s.', 'woo-total-menu' ),
                                        $path,
                                        implode( ', ', self::MEGA_TRIGGERS )
                                ),
                                array( 'status' => 400 )
                        );
                }

                // width (optional, int 200-2000 or "full").
                if ( isset( $item['width'] ) ) {
                        if ( 'full' !== $item['width'] && ( ! is_numeric( $item['width'] ) || $item['width'] < 200 || $item['width'] > 2000 ) ) {
                                return new \WP_Error(
                                        'wtm_mega_invalid_width',
                                        sprintf(
                                                /* translators: %s item path */
                                                __( 'L\'élément mega_container %s : "width" doit être un entier entre 200 et 2000, ou "full".', 'woo-total-menu' ),
                                                $path
                                        ),
                                        array( 'status' => 400 )
                                );
                        }
                }

                // Validate children (must be columns).
                foreach ( $item['children'] as $index => $child ) {
                        if ( ! is_array( $child ) || ! isset( $child['type'] ) || 'column' !== $child['type'] ) {
                                return new \WP_Error(
                                        'wtm_mega_invalid_child',
                                        sprintf(
                                                /* translators: %s item path */
                                                __( 'L\'élément mega_container %s : chaque child doit être de type "column".', 'woo-total-menu' ),
                                                "{$path}.children[{$index}]"
                                        ),
                                        array( 'status' => 400 )
                                );
                        }
                        $check = self::validate_item( $child, "{$path}.children[{$index}]" );
                        if ( is_wp_error( $check ) ) {
                                return $check;
                        }
                }

                return true;
        }

        /**
         * Validate a "column" item.
         *
         * Required: id, type="column"
         * Optional: width (1-12), children (array of widgets/links/titles)
         *
         * @param array  $item Item.
         * @param string $path Path.
         * @return true|\WP_Error
         */
        private static function validate_item_column( $item, $path ) {
                // width (optional, int 1-12 — Bootstrap-like grid).
                if ( isset( $item['width'] ) ) {
                        if ( ! is_numeric( $item['width'] ) || $item['width'] < 1 || $item['width'] > 12 ) {
                                return new \WP_Error(
                                        'wtm_column_invalid_width',
                                        sprintf(
                                                /* translators: %s item path */
                                                __( 'L\'élément column %s : "width" doit être un entier entre 1 et 12.', 'woo-total-menu' ),
                                                $path
                                        ),
                                        array( 'status' => 400 )
                                );
                        }
                }

                // children (optional array of widgets/links/titles/separators).
                if ( isset( $item['children'] ) ) {
                        if ( ! is_array( $item['children'] ) ) {
                                return new \WP_Error(
                                        'wtm_column_invalid_children',
                                        sprintf(
                                                /* translators: %s item path */
                                                __( 'L\'élément column %s : "children" doit être un tableau.', 'woo-total-menu' ),
                                                $path
                                        ),
                                        array( 'status' => 400 )
                                );
                        }
                        $allowed_child_types = array( 'widget', 'link', 'title', 'separator' );
                        foreach ( $item['children'] as $index => $child ) {
                                if ( ! is_array( $child ) || ! isset( $child['type'] ) || ! in_array( $child['type'], $allowed_child_types, true ) ) {
                                        return new \WP_Error(
                                                'wtm_column_invalid_child_type',
                                                sprintf(
                                                        /* translators: 1: item path, 2: allowed types */
                                                        __( 'L\'élément column %1$s : chaque child doit être un de : %2$s.', 'woo-total-menu' ),
                                                        "{$path}.children[{$index}]",
                                                        implode( ', ', $allowed_child_types )
                                                ),
                                                array( 'status' => 400 )
                                        );
                                }
                                $check = self::validate_item( $child, "{$path}.children[{$index}]" );
                                if ( is_wp_error( $check ) ) {
                                        return $check;
                                }
                        }
                }

                return true;
        }

        /**
         * Validate a "widget" item.
         *
         * Required: widget_type, widget_settings
         * Optional: label, children
         *
         * @param array  $item Item.
         * @param string $path Path.
         * @return true|\WP_Error
         */
        private static function validate_item_widget( $item, $path ) {
                // widget_type (required, enum).
                if ( ! isset( $item['widget_type'] ) || ! in_array( $item['widget_type'], self::WIDGET_TYPES, true ) ) {
                        return new \WP_Error(
                                'wtm_widget_missing_type',
                                sprintf(
                                        /* translators: 1: item path, 2: allowed types */
                                        __( 'L\'élément widget %1$s doit avoir un "widget_type" parmi : %2$s.', 'woo-total-menu' ),
                                        $path,
                                        implode( ', ', self::WIDGET_TYPES )
                                ),
                                array( 'status' => 400 )
                        );
                }

                // widget_settings (required object).
                if ( ! isset( $item['widget_settings'] ) || ! is_array( $item['widget_settings'] ) ) {
                        return new \WP_Error(
                                'wtm_widget_missing_settings',
                                sprintf(
                                        /* translators: %s item path */
                                        __( 'L\'élément widget %s doit avoir un objet "widget_settings".', 'woo-total-menu' ),
                                        $path
                                ),
                                array( 'status' => 400 )
                        );
                }

                // Type-specific settings validation.
                $check = self::validate_widget_settings( $item['widget_type'], $item['widget_settings'], "{$path}.widget_settings" );
                if ( is_wp_error( $check ) ) {
                        return $check;
                }

                // label (optional string).
                if ( isset( $item['label'] ) && ! is_string( $item['label'] ) ) {
                        return new \WP_Error(
                                'wtm_widget_invalid_label',
                                sprintf(
                                        /* translators: %s item path */
                                        __( 'L\'élément widget %s : "label" doit être une string.', 'woo-total-menu' ),
                                        $path
                                ),
                                array( 'status' => 400 )
                        );
                }

                // children (optional — for nested widgets).
                if ( isset( $item['children'] ) ) {
                        if ( ! is_array( $item['children'] ) ) {
                                return new \WP_Error(
                                        'wtm_widget_invalid_children',
                                        sprintf(
                                                /* translators: %s item path */
                                                __( 'L\'élément widget %s : "children" doit être un tableau.', 'woo-total-menu' ),
                                                $path
                                        ),
                                        array( 'status' => 400 )
                                );
                        }
                        foreach ( $item['children'] as $index => $child ) {
                                $check = self::validate_item( $child, "{$path}.children[{$index}]" );
                                if ( is_wp_error( $check ) ) {
                                        return $check;
                                }
                        }
                }

                return true;
        }

        /**
         * Validate widget_settings per widget_type.
         *
         * @param string $widget_type Widget type.
         * @param array  $settings    Settings.
         * @param string $path        Path.
         * @return true|\WP_Error
         */
        private static function validate_widget_settings( $widget_type, $settings, $path ) {
                switch ( $widget_type ) {
                        case 'category_grid':
                                // columns (int 1-6), categories (array of IDs), show_images (bool), show_counts (bool).
                                if ( isset( $settings['columns'] ) && ( ! is_numeric( $settings['columns'] ) || $settings['columns'] < 1 || $settings['columns'] > 6 ) ) {
                                        return new \WP_Error( 'wtm_widget_invalid_columns', sprintf( __( '%s : "columns" doit être entre 1 et 6.', 'woo-total-menu' ), $path ), array( 'status' => 400 ) );
                                }
                                if ( isset( $settings['categories'] ) && ! is_array( $settings['categories'] ) ) {
                                        return new \WP_Error( 'wtm_widget_invalid_categories', sprintf( __( '%s : "categories" doit être un tableau d\'IDs.', 'woo-total-menu' ), $path ), array( 'status' => 400 ) );
                                }
                                break;

                        case 'product_grid':
                                // columns (1-6), product_source (featured|best_selling|recent|on_sale|custom), limit (1-12).
                                if ( isset( $settings['columns'] ) && ( ! is_numeric( $settings['columns'] ) || $settings['columns'] < 1 || $settings['columns'] > 6 ) ) {
                                        return new \WP_Error( 'wtm_widget_invalid_columns', sprintf( __( '%s : "columns" doit être entre 1 et 6.', 'woo-total-menu' ), $path ), array( 'status' => 400 ) );
                                }
                                $valid_sources = array( 'featured', 'best_selling', 'recent', 'on_sale', 'custom' );
                                if ( isset( $settings['product_source'] ) && ! in_array( $settings['product_source'], $valid_sources, true ) ) {
                                        return new \WP_Error( 'wtm_widget_invalid_source', sprintf( __( '%s : "product_source" doit être un de : %s.', 'woo-total-menu' ), $path, implode( ', ', $valid_sources ) ), array( 'status' => 400 ) );
                                }
                                if ( isset( $settings['limit'] ) && ( ! is_numeric( $settings['limit'] ) || $settings['limit'] < 1 || $settings['limit'] > 12 ) ) {
                                        return new \WP_Error( 'wtm_widget_invalid_limit', sprintf( __( '%s : "limit" doit être entre 1 et 12.', 'woo-total-menu' ), $path ), array( 'status' => 400 ) );
                                }
                                break;

                        case 'mini_cart':
                                // v1.2.0: show_subtotal (bool), show_checkout_button (bool), show_thumbnail (bool).
                                // v1.3.0: display_mode ('link'|'drawer'), drawer_position ('right'|'left').
                                foreach ( array( 'show_subtotal', 'show_checkout_button', 'show_thumbnail' ) as $bool_field ) {
                                        if ( isset( $settings[ $bool_field ] ) && ! is_bool( $settings[ $bool_field ] ) ) {
                                                return new \WP_Error( 'wtm_widget_invalid_bool', sprintf( __( '%s : "%s" doit être un booléen.', 'woo-total-menu' ), $path, $bool_field ), array( 'status' => 400 ) );
                                        }
                                }
                                if ( isset( $settings['display_mode'] ) ) {
                                        $valid_modes = array( 'link', 'drawer' );
                                        if ( ! in_array( $settings['display_mode'], $valid_modes, true ) ) {
                                                return new \WP_Error( 'wtm_widget_invalid_display_mode', sprintf( __( '%s : "display_mode" doit être "link" ou "drawer".', 'woo-total-menu' ), $path ), array( 'status' => 400 ) );
                                        }
                                }
                                if ( isset( $settings['drawer_position'] ) ) {
                                        $valid_pos = array( 'right', 'left' );
                                        if ( ! in_array( $settings['drawer_position'], $valid_pos, true ) ) {
                                                return new \WP_Error( 'wtm_widget_invalid_position', sprintf( __( '%s : "drawer_position" doit être "right" ou "left".', 'woo-total-menu' ), $path ), array( 'status' => 400 ) );
                                        }
                                }
                                break;

                        case 'search':
                                // v1.2.0: placeholder (string), show_category_filter (bool), limit (int 1-20).
                                // v1.3.0: live_suggestions (bool), min_chars (int 2-5).
                                if ( isset( $settings['placeholder'] ) && ! is_string( $settings['placeholder'] ) ) {
                                        return new \WP_Error( 'wtm_widget_invalid_placeholder', sprintf( __( '%s : "placeholder" doit être une string.', 'woo-total-menu' ), $path ), array( 'status' => 400 ) );
                                }
                                foreach ( array( 'show_category_filter', 'live_suggestions' ) as $bool_field ) {
                                        if ( isset( $settings[ $bool_field ] ) && ! is_bool( $settings[ $bool_field ] ) ) {
                                                return new \WP_Error( 'wtm_widget_invalid_bool', sprintf( __( '%s : "%s" doit être un booléen.', 'woo-total-menu' ), $path, $bool_field ), array( 'status' => 400 ) );
                                        }
                                }
                                if ( isset( $settings['min_chars'] ) && ( ! is_numeric( $settings['min_chars'] ) || $settings['min_chars'] < 2 || $settings['min_chars'] > 5 ) ) {
                                        return new \WP_Error( 'wtm_widget_invalid_min_chars', sprintf( __( '%s : "min_chars" doit être entre 2 et 5.', 'woo-total-menu' ), $path ), array( 'status' => 400 ) );
                                }
                                break;

                        case 'banner':
                                // image_url (required string), link_url (string), alt (string), target (enum).
                                if ( ! isset( $settings['image_url'] ) || ! is_string( $settings['image_url'] ) ) {
                                        return new \WP_Error( 'wtm_widget_missing_image', sprintf( __( '%s : "image_url" est requis pour le widget banner.', 'woo-total-menu' ), $path ), array( 'status' => 400 ) );
                                }
                                if ( isset( $settings['target'] ) && ! in_array( $settings['target'], self::LINK_TARGETS, true ) ) {
                                        return new \WP_Error( 'wtm_widget_invalid_target', sprintf( __( '%s : "target" doit être _self ou _blank.', 'woo-total-menu' ), $path ), array( 'status' => 400 ) );
                                }
                                break;

                        case 'html':
                                // content (required string).
                                if ( ! isset( $settings['content'] ) || ! is_string( $settings['content'] ) ) {
                                        return new \WP_Error( 'wtm_widget_missing_content', sprintf( __( '%s : "content" est requis pour le widget html.', 'woo-total-menu' ), $path ), array( 'status' => 400 ) );
                                }
                                break;

                        case 'custom_link':
                                // label (required string), url (required string), target (enum).
                                if ( ! isset( $settings['label'] ) || ! is_string( $settings['label'] ) ) {
                                        return new \WP_Error( 'wtm_widget_missing_label', sprintf( __( '%s : "label" est requis pour le widget custom_link.', 'woo-total-menu' ), $path ), array( 'status' => 400 ) );
                                }
                                if ( ! isset( $settings['url'] ) || ! is_string( $settings['url'] ) ) {
                                        return new \WP_Error( 'wtm_widget_missing_url', sprintf( __( '%s : "url" est requis pour le widget custom_link.', 'woo-total-menu' ), $path ), array( 'status' => 400 ) );
                                }
                                break;

                        case 'title':
                                // text (required string), level (1-6).
                                if ( ! isset( $settings['text'] ) || ! is_string( $settings['text'] ) ) {
                                        return new \WP_Error( 'wtm_widget_missing_text', sprintf( __( '%s : "text" est requis pour le widget title.', 'woo-total-menu' ), $path ), array( 'status' => 400 ) );
                                }
                                if ( isset( $settings['level'] ) && ( ! is_numeric( $settings['level'] ) || $settings['level'] < 1 || $settings['level'] > 6 ) ) {
                                        return new \WP_Error( 'wtm_widget_invalid_level', sprintf( __( '%s : "level" doit être entre 1 et 6.', 'woo-total-menu' ), $path ), array( 'status' => 400 ) );
                                }
                                break;

                        case 'recent_posts':
                                // limit (1-12), columns (1-6), show_image (bool), show_date (bool),
                                // show_excerpt (bool), category (int|string "" = all), orderby (date|title|comment_count).
                                if ( isset( $settings['limit'] ) && ( ! is_numeric( $settings['limit'] ) || $settings['limit'] < 1 || $settings['limit'] > 12 ) ) {
                                        return new \WP_Error( 'wtm_widget_invalid_limit', sprintf( __( '%s : "limit" doit être entre 1 et 12.', 'woo-total-menu' ), $path ), array( 'status' => 400 ) );
                                }
                                if ( isset( $settings['columns'] ) && ( ! is_numeric( $settings['columns'] ) || $settings['columns'] < 1 || $settings['columns'] > 6 ) ) {
                                        return new \WP_Error( 'wtm_widget_invalid_columns', sprintf( __( '%s : "columns" doit être entre 1 et 6.', 'woo-total-menu' ), $path ), array( 'status' => 400 ) );
                                }
                                $valid_orders = array( 'date', 'title', 'comment_count', 'rand' );
                                if ( isset( $settings['orderby'] ) && ! in_array( $settings['orderby'], $valid_orders, true ) ) {
                                        return new \WP_Error( 'wtm_widget_invalid_orderby', sprintf( __( '%s : "orderby" doit être un de : %s.', 'woo-total-menu' ), $path, implode( ', ', $valid_orders ) ), array( 'status' => 400 ) );
                                }
                                foreach ( array( 'show_image', 'show_date', 'show_excerpt' ) as $bool_field ) {
                                        if ( isset( $settings[ $bool_field ] ) && ! is_bool( $settings[ $bool_field ] ) ) {
                                                return new \WP_Error( 'wtm_widget_invalid_bool', sprintf( __( '%s : "%s" doit être un booléen.', 'woo-total-menu' ), $path, $bool_field ), array( 'status' => 400 ) );
                                        }
                                }
                                break;

                        case 'social_icons':
                                // items: array of { network: string, url: string, label?: string }.
                                // networks whitelist — extensible via filter at render time.
                                if ( isset( $settings['items'] ) ) {
                                        if ( ! is_array( $settings['items'] ) ) {
                                                return new \WP_Error( 'wtm_widget_invalid_items', sprintf( __( '%s : "items" doit être un tableau pour social_icons.', 'woo-total-menu' ), $path ), array( 'status' => 400 ) );
                                        }
                                        foreach ( $settings['items'] as $i => $soc ) {
                                                if ( ! is_array( $soc ) || empty( $soc['network'] ) || empty( $soc['url'] ) ) {
                                                        return new \WP_Error( 'wtm_widget_invalid_social_item', sprintf( __( '%s : item %d doit avoir "network" et "url".', 'woo-total-menu' ), $path, $i ), array( 'status' => 400 ) );
                                                }
                                        }
                                }
                                if ( isset( $settings['size'] ) && ( ! is_numeric( $settings['size'] ) || $settings['size'] < 12 || $settings['size'] > 64 ) ) {
                                        return new \WP_Error( 'wtm_widget_invalid_size', sprintf( __( '%s : "size" doit être entre 12 et 64 px.', 'woo-total-menu' ), $path ), array( 'status' => 400 ) );
                                }
                                break;

                        case 'newsletter':
                                // placeholder (string), button_label (string), provider (none|mailchimp),
                                // list_id (string), success_message (string), layout (inline|stacked).
                                if ( isset( $settings['provider'] ) ) {
                                        $valid_providers = array( 'none', 'mailchimp', 'internal' );
                                        if ( ! in_array( $settings['provider'], $valid_providers, true ) ) {
                                                return new \WP_Error( 'wtm_widget_invalid_provider', sprintf( __( '%s : "provider" doit être un de : %s.', 'woo-total-menu' ), $path, implode( ', ', $valid_providers ) ), array( 'status' => 400 ) );
                                        }
                                }
                                if ( isset( $settings['layout'] ) ) {
                                        $valid_layouts = array( 'inline', 'stacked' );
                                        if ( ! in_array( $settings['layout'], $valid_layouts, true ) ) {
                                                return new \WP_Error( 'wtm_widget_invalid_layout', sprintf( __( '%s : "layout" doit être "inline" ou "stacked".', 'woo-total-menu' ), $path ), array( 'status' => 400 ) );
                                        }
                                }
                                break;

                        case 'filters':
                                // show_categories (bool), show_price (bool), show_attributes (bool),
                                // attributes (array of taxonomy slugs), columns (1-4).
                                foreach ( array( 'show_categories', 'show_price', 'show_attributes' ) as $bool_field ) {
                                        if ( isset( $settings[ $bool_field ] ) && ! is_bool( $settings[ $bool_field ] ) ) {
                                                return new \WP_Error( 'wtm_widget_invalid_bool', sprintf( __( '%s : "%s" doit être un booléen.', 'woo-total-menu' ), $path, $bool_field ), array( 'status' => 400 ) );
                                        }
                                }
                                if ( isset( $settings['columns'] ) && ( ! is_numeric( $settings['columns'] ) || $settings['columns'] < 1 || $settings['columns'] > 4 ) ) {
                                        return new \WP_Error( 'wtm_widget_invalid_columns', sprintf( __( '%s : "columns" doit être entre 1 et 4.', 'woo-total-menu' ), $path ), array( 'status' => 400 ) );
                                }
                                if ( isset( $settings['attributes'] ) && ! is_array( $settings['attributes'] ) ) {
                                        return new \WP_Error( 'wtm_widget_invalid_attributes', sprintf( __( '%s : "attributes" doit être un tableau de slugs.', 'woo-total-menu' ), $path ), array( 'status' => 400 ) );
                                }
                                break;
                }

                return true;
        }

        /**
         * Validate a "title" item.
         *
         * Required: label
         *
         * @param array  $item Item.
         * @param string $path Path.
         * @return true|\WP_Error
         */
        private static function validate_item_title( $item, $path ) {
                if ( ! isset( $item['label'] ) || ! is_string( $item['label'] ) ) {
                        return new \WP_Error(
                                'wtm_title_missing_label',
                                sprintf(
                                        /* translators: %s item path */
                                        __( 'L\'élément title %s doit avoir un champ "label" (string).', 'woo-total-menu' ),
                                        $path
                                ),
                                array( 'status' => 400 )
                        );
                }
                return true;
        }

        /**
         * Validate a "separator" item.
         *
         * No additional fields required.
         *
         * @param array  $item Item.
         * @param string $path Path.
         * @return true|\WP_Error
         */
        private static function validate_item_separator( $item, $path ) {
                return true;
        }

        /**
         * Validate a badge object (used in link items).
         *
         * Required: text
         * Optional: color (hex), background (hex)
         *
         * @param mixed  $badge Badge.
         * @param string $path  Path.
         * @return true|\WP_Error
         */
        private static function validate_badge( $badge, $path ) {
                if ( ! is_array( $badge ) ) {
                        return new \WP_Error(
                                'wtm_badge_invalid',
                                sprintf(
                                        /* translators: %s path */
                                        __( '%s doit être un objet.', 'woo-total-menu' ),
                                        $path
                                ),
                                array( 'status' => 400 )
                        );
                }
                if ( ! isset( $badge['text'] ) || ! is_string( $badge['text'] ) ) {
                        return new \WP_Error(
                                'wtm_badge_missing_text',
                                sprintf(
                                        /* translators: %s path */
                                        __( '%s doit avoir un champ "text" (string).', 'woo-total-menu' ),
                                        $path
                                ),
                                array( 'status' => 400 )
                        );
                }
                if ( isset( $badge['color'] ) && ! self::is_valid_hex_color( $badge['color'] ) ) {
                        return new \WP_Error(
                                'wtm_badge_invalid_color',
                                sprintf(
                                        /* translators: %s path */
                                        __( '%s : "color" doit être un code hexadécimal valide (#RRGGBB).', 'woo-total-menu' ),
                                        $path
                                ),
                                array( 'status' => 400 )
                        );
                }
                if ( isset( $badge['background'] ) && ! self::is_valid_hex_color( $badge['background'] ) ) {
                        return new \WP_Error(
                                'wtm_badge_invalid_background',
                                sprintf(
                                        /* translators: %s path */
                                        __( '%s : "background" doit être un code hexadécimal valide (#RRGGBB).', 'woo-total-menu' ),
                                        $path
                                ),
                                array( 'status' => 400 )
                        );
                }
                return true;
        }

        /**
         * Validate a header/footer layout config.
         *
         * Structure: { version, rows: [ { id, columns: [ { id, width, modules: [ {id, type, settings} ] } ] } ], settings }
         *
         * @param mixed $value Decoded JSON value.
         * @return true|\WP_Error
         */
        public static function validate_layout( $value ) {
                if ( ! is_array( $value ) ) {
                        return new \WP_Error(
                                'wtm_invalid_layout_type',
                                __( 'La configuration header/footer doit être un objet JSON.', 'woo-total-menu' ),
                                array( 'status' => 400 )
                        );
                }

                // version field.
                if ( ! isset( $value['version'] ) ) {
                        $value['version'] = self::SCHEMA_VERSION;
                }
                if ( ! is_numeric( $value['version'] ) ) {
                        return new \WP_Error(
                                'wtm_invalid_layout_version',
                                __( 'Le champ "version" doit être un entier.', 'woo-total-menu' ),
                                array( 'status' => 400 )
                        );
                }

                // rows field (optional in v1.0.4, required in v1.4.x).
                if ( isset( $value['rows'] ) ) {
                        if ( ! is_array( $value['rows'] ) ) {
                                return new \WP_Error(
                                        'wtm_invalid_rows',
                                        __( 'Le champ "rows" doit être un tableau.', 'woo-total-menu' ),
                                        array( 'status' => 400 )
                                );
                        }
                        foreach ( $value['rows'] as $index => $row ) {
                                $check = self::validate_layout_row( $row, "rows[{$index}]" );
                                if ( is_wp_error( $check ) ) {
                                        return $check;
                                }
                        }
                }

                // settings field (optional).
                if ( isset( $value['settings'] ) ) {
                        if ( ! is_array( $value['settings'] ) ) {
                                return new \WP_Error(
                                        'wtm_invalid_layout_settings',
                                        __( 'Le champ "settings" doit être un objet.', 'woo-total-menu' ),
                                        array( 'status' => 400 )
                                );
                        }
                        $settings_check = self::validate_settings( $value['settings'] );
                        if ( is_wp_error( $settings_check ) ) {
                                return $settings_check;
                        }
                }

                return true;
        }

        /**
         * Validate a row in a header/footer layout.
         *
         * @param mixed  $row  Row.
         * @param string $path Path.
         * @return true|\WP_Error
         */
        private static function validate_layout_row( $row, $path ) {
                if ( ! is_array( $row ) ) {
                        return new \WP_Error(
                                'wtm_invalid_row',
                                sprintf(
                                        /* translators: %s path */
                                        __( '%s doit être un objet.', 'woo-total-menu' ),
                                        $path
                                ),
                                array( 'status' => 400 )
                        );
                }

                // id (required string).
                if ( ! isset( $row['id'] ) || ! is_string( $row['id'] ) ) {
                        return new \WP_Error(
                                'wtm_row_missing_id',
                                sprintf(
                                        /* translators: %s path */
                                        __( '%s doit avoir un champ "id" (string).', 'woo-total-menu' ),
                                        $path
                                ),
                                array( 'status' => 400 )
                        );
                }

                // columns (required array).
                if ( ! isset( $row['columns'] ) || ! is_array( $row['columns'] ) ) {
                        return new \WP_Error(
                                'wtm_row_missing_columns',
                                sprintf(
                                        /* translators: %s path */
                                        __( '%s doit avoir un tableau "columns".', 'woo-total-menu' ),
                                        $path
                                ),
                                array( 'status' => 400 )
                        );
                }

                foreach ( $row['columns'] as $index => $column ) {
                        $check = self::validate_layout_column( $column, "{$path}.columns[{$index}]" );
                        if ( is_wp_error( $check ) ) {
                                return $check;
                        }
                }

                return true;
        }

        /**
         * Validate a column in a header/footer layout.
         *
         * @param mixed  $column Column.
         * @param string $path   Path.
         * @return true|\WP_Error
         */
        private static function validate_layout_column( $column, $path ) {
                if ( ! is_array( $column ) ) {
                        return new \WP_Error(
                                'wtm_invalid_column',
                                sprintf(
                                        /* translators: %s path */
                                        __( '%s doit être un objet.', 'woo-total-menu' ),
                                        $path
                                ),
                                array( 'status' => 400 )
                        );
                }

                // id (required string).
                if ( ! isset( $column['id'] ) || ! is_string( $column['id'] ) ) {
                        return new \WP_Error(
                                'wtm_column_missing_id',
                                sprintf(
                                        /* translators: %s path */
                                        __( '%s doit avoir un champ "id" (string).', 'woo-total-menu' ),
                                        $path
                                ),
                                array( 'status' => 400 )
                        );
                }

                // width (optional, 1-12).
                if ( isset( $column['width'] ) && ( ! is_numeric( $column['width'] ) || $column['width'] < 1 || $column['width'] > 12 ) ) {
                        return new \WP_Error(
                                'wtm_column_invalid_width',
                                sprintf(
                                        /* translators: %s path */
                                        __( '%s : "width" doit être entre 1 et 12.', 'woo-total-menu' ),
                                        $path
                                ),
                                array( 'status' => 400 )
                        );
                }

                // modules (required array).
                if ( ! isset( $column['modules'] ) || ! is_array( $column['modules'] ) ) {
                        return new \WP_Error(
                                'wtm_column_missing_modules',
                                sprintf(
                                        /* translators: %s path */
                                        __( '%s doit avoir un tableau "modules".', 'woo-total-menu' ),
                                        $path
                                ),
                                array( 'status' => 400 )
                        );
                }

                foreach ( $column['modules'] as $index => $module ) {
                        $check = self::validate_layout_module( $module, "{$path}.modules[{$index}]" );
                        if ( is_wp_error( $check ) ) {
                                return $check;
                        }
                }

                return true;
        }

        /**
         * Validate a module in a header/footer column.
         *
         * @param mixed  $module Module.
         * @param string $path   Path.
         * @return true|\WP_Error
         */
        private static function validate_layout_module( $module, $path ) {
                if ( ! is_array( $module ) ) {
                        return new \WP_Error(
                                'wtm_invalid_module',
                                sprintf(
                                        /* translators: %s path */
                                        __( '%s doit être un objet.', 'woo-total-menu' ),
                                        $path
                                ),
                                array( 'status' => 400 )
                        );
                }

                // id (required).
                if ( ! isset( $module['id'] ) || ! is_string( $module['id'] ) ) {
                        return new \WP_Error(
                                'wtm_module_missing_id',
                                sprintf(
                                        /* translators: %s path */
                                        __( '%s doit avoir un champ "id" (string).', 'woo-total-menu' ),
                                        $path
                                ),
                                array( 'status' => 400 )
                        );
                }

                // type (required, must be in MODULE_TYPES).
                if ( ! isset( $module['type'] ) || ! in_array( $module['type'], self::MODULE_TYPES, true ) ) {
                        return new \WP_Error(
                                'wtm_module_invalid_type',
                                sprintf(
                                        /* translators: 1: path, 2: allowed types */
                                        __( '%1$s : "type" doit être un de : %2$s.', 'woo-total-menu' ),
                                        $path,
                                        implode( ', ', self::MODULE_TYPES )
                                ),
                                array( 'status' => 400 )
                        );
                }

                // settings (optional object).
                if ( isset( $module['settings'] ) && ! is_array( $module['settings'] ) ) {
                        return new \WP_Error(
                                'wtm_module_invalid_settings',
                                sprintf(
                                        /* translators: %s path */
                                        __( '%s : "settings" doit être un objet.', 'woo-total-menu' ),
                                        $path
                                ),
                                array( 'status' => 400 )
                        );
                }

                return true;
        }

        /**
         * Check if a string is a valid hex color (#RGB or #RRGGBB).
         *
         * @param string $color Color.
         * @return bool
         */
        private static function is_valid_hex_color( $color ) {
                return is_string( $color ) && 1 === preg_match( '/^#([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})$/', $color );
        }

        /**
         * Normalize a config value: ensure version + items are present.
         *
         * @param array $value Decoded config.
         * @return array
         */
        public static function normalize_config( $value ) {
                if ( ! is_array( $value ) ) {
                        return array(
                                'version' => self::SCHEMA_VERSION,
                                'items'   => array(),
                        );
                }
                if ( ! isset( $value['version'] ) ) {
                        $value['version'] = self::SCHEMA_VERSION;
                }
                if ( ! isset( $value['items'] ) ) {
                        $value['items'] = array();
                }
                return $value;
        }

        /**
         * Normalize a header/footer layout config.
         *
         * @param array $value Decoded layout.
         * @return array
         */
        public static function normalize_layout( $value ) {
                if ( ! is_array( $value ) ) {
                        return array(
                                'version' => self::SCHEMA_VERSION,
                                'rows'    => array(),
                        );
                }
                if ( ! isset( $value['version'] ) ) {
                        $value['version'] = self::SCHEMA_VERSION;
                }
                if ( ! isset( $value['rows'] ) ) {
                        $value['rows'] = array();
                }
                return $value;
        }

        /**
         * JSON-decode a string and validate as a menu config.
         *
         * @param string $raw Raw JSON string.
         * @return array|\WP_Error Decoded config on success, WP_Error on failure.
         */
        public static function decode_and_validate_config( $raw ) {
                if ( '' === $raw || null === $raw ) {
                        return array(
                                'version' => self::SCHEMA_VERSION,
                                'items'   => array(),
                        );
                }
                $decoded = json_decode( $raw, true );
                if ( null === $decoded && JSON_ERROR_NONE !== json_last_error() ) {
                        return new \WP_Error(
                                'wtm_invalid_json',
                                sprintf(
                                        /* translators: %s JSON error message */
                                        __( 'JSON invalide : %s', 'woo-total-menu' ),
                                        json_last_error_msg()
                                ),
                                array( 'status' => 400 )
                        );
                }
                $check = self::validate_config( $decoded );
                if ( is_wp_error( $check ) ) {
                        return $check;
                }
                return self::normalize_config( $decoded );
        }

        /**
         * JSON-decode a string and validate as a header/footer layout.
         *
         * @param string $raw Raw JSON string.
         * @return array|\WP_Error
         */
        public static function decode_and_validate_layout( $raw ) {
                if ( '' === $raw || null === $raw ) {
                        return array();
                }
                $decoded = json_decode( $raw, true );
                if ( null === $decoded && JSON_ERROR_NONE !== json_last_error() ) {
                        return new \WP_Error(
                                'wtm_invalid_json',
                                sprintf(
                                        /* translators: %s JSON error message */
                                        __( 'JSON invalide : %s', 'woo-total-menu' ),
                                        json_last_error_msg()
                                ),
                                array( 'status' => 400 )
                        );
                }
                $check = self::validate_layout( $decoded );
                if ( is_wp_error( $check ) ) {
                        return $check;
                }
                return self::normalize_layout( $decoded );
        }

        /**
         * Get the full JSON Schema (draft-04) describing a wtm_menu config.
         *
         * Used by the /wtm/v1/menus/schema endpoint to expose a machine-readable
         * description of the accepted shapes.
         *
         * @return array
         */
        public static function get_full_schema() {
                return array(
                        '$schema'    => 'http://json-schema.org/draft-04/schema#',
                        'title'      => 'wtm_menu_config',
                        'description'=> __( 'Schéma de configuration JSON d\'un menu Woo Total Menu.', 'woo-total-menu' ),
                        'type'       => 'object',
                        'properties' => array(
                                'version' => array(
                                        'type'        => 'integer',
                                        'default'     => self::SCHEMA_VERSION,
                                        'description' => __( 'Version du schéma.', 'woo-total-menu' ),
                                ),
                                'items'   => array(
                                        'type'        => 'array',
                                        'default'     => array(),
                                        'description' => __( 'Arborescence des items du menu.', 'woo-total-menu' ),
                                        'items'       => array(
                                                '$ref' => '#/definitions/item',
                                        ),
                                ),
                                'settings' => array(
                                        'type'        => 'object',
                                        'description' => __( 'Réglages du menu (sticky, mobile_behavior, etc.).', 'woo-total-menu' ),
                                        'properties'  => array(
                                                'sticky'           => array( 'type' => 'boolean' ),
                                                'mobile_behavior'  => array( 'type' => 'string', 'enum' => self::MOBILE_BEHAVIORS ),
                                                'mobile_breakpoint' => array( 'type' => 'integer' ),
                                        ),
                                        'additionalProperties' => true,
                                ),
                        ),
                        'definitions' => array(
                                'item' => array(
                                        'type'       => 'object',
                                        'required'   => array( 'id', 'type' ),
                                        'properties' => array(
                                                'id'         => array( 'type' => 'string', 'minLength' => 1 ),
                                                'type'       => array( 'type' => 'string', 'enum' => self::ITEM_TYPES ),
                                                'label'      => array( 'type' => 'string' ),
                                                'url'        => array( 'type' => 'string' ),
                                                'target'     => array( 'type' => 'string', 'enum' => self::LINK_TARGETS ),
                                                'icon'       => array( 'type' => 'string' ),
                                                'trigger'    => array( 'type' => 'string', 'enum' => self::MEGA_TRIGGERS ),
                                                'width'      => array( 'oneOf' => array(
                                                        array( 'type' => 'integer', 'minimum' => 200, 'maximum' => 2000 ),
                                                        array( 'type' => 'string', 'enum' => array( 'full' ) ),
                                                        array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 12 ),
                                                ) ),
                                                'widget_type'    => array( 'type' => 'string', 'enum' => self::WIDGET_TYPES ),
                                                'widget_settings'=> array( 'type' => 'object' ),
                                                'visibility' => array( 'type' => 'string', 'enum' => self::VISIBILITY_VALUES ),
                                                'classes'    => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
                                                'badge'      => array( '$ref' => '#/definitions/badge' ),
                                                'children'   => array(
                                                        'type'  => 'array',
                                                        'items' => array( '$ref' => '#/definitions/item' ),
                                                ),
                                        ),
                                        'additionalProperties' => true,
                                ),
                                'badge' => array(
                                        'type'       => 'object',
                                        'required'   => array( 'text' ),
                                        'properties' => array(
                                                'text'       => array( 'type' => 'string' ),
                                                'color'      => array( 'type' => 'string', 'pattern' => '^#([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})$' ),
                                                'background' => array( 'type' => 'string', 'pattern' => '^#([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})$' ),
                                        ),
                                        'additionalProperties' => true,
                                ),
                                'layout' => array(
                                        'type'       => 'object',
                                        'properties' => array(
                                                'version' => array( 'type' => 'integer' ),
                                                'rows'    => array(
                                                        'type'  => 'array',
                                                        'items' => array( '$ref' => '#/definitions/row' ),
                                                ),
                                                'settings' => array( 'type' => 'object' ),
                                        ),
                                ),
                                'row' => array(
                                        'type'       => 'object',
                                        'required'   => array( 'id', 'columns' ),
                                        'properties' => array(
                                                'id'      => array( 'type' => 'string' ),
                                                'columns' => array(
                                                        'type'  => 'array',
                                                        'items' => array( '$ref' => '#/definitions/column' ),
                                                ),
                                        ),
                                ),
                                'column' => array(
                                        'type'       => 'object',
                                        'required'   => array( 'id', 'modules' ),
                                        'properties' => array(
                                                'id'      => array( 'type' => 'string' ),
                                                'width'   => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 12 ),
                                                'modules' => array(
                                                        'type'  => 'array',
                                                        'items' => array( '$ref' => '#/definitions/module' ),
                                                ),
                                        ),
                                ),
                                'module' => array(
                                        'type'       => 'object',
                                        'required'   => array( 'id', 'type' ),
                                        'properties' => array(
                                                'id'       => array( 'type' => 'string' ),
                                                'type'     => array( 'type' => 'string', 'enum' => self::MODULE_TYPES ),
                                                'settings' => array( 'type' => 'object' ),
                                        ),
                                ),
                        ),
                );
        }
}
