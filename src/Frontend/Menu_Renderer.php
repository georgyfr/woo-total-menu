<?php
/**
 * Frontend menu renderer.
 *
 * Walks a wtm_menu JSON configuration tree and produces semantic,
 * accessible HTML (<nav><ul>…) for the four menu types:
 *   - horizontal  (méga menu horizontal — spec §5.4)
 *   - vertical    (sidebar — spec §5.5)
 *   - offcanvas   (panneau latéral mobile — spec §5.6)
 *   - footer      (colonnes de pied de page — spec §5.7)
 *
 * Spec reference: §2.4 (pipeline de rendu frontend), §5 (maquettes),
 * §3.4 (nesting rules), §7.5 (relations et emplacements).
 *
 * @package WooTotalMenu
 * @since 1.2.0
 */

namespace WooTotalMenu\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

/**
 * Class Menu_Renderer
 *
 * Pure-PHP renderer: takes a wtm_menu post (or its config array) and
 * returns an HTML string. Has no side effects (does not echo, does not
 * enqueue assets — that's Assets_Loader's job).
 */
class Menu_Renderer {

        /**
         * Item types that can have children (used for nesting validation).
         *
         * @var array<string,bool>
         */
        const CONTAINER_TYPES = array(
                'link'             => true,
                'mega_container'   => true,
                'column'           => true,
                'accordion_parent' => true,
        );

        /**
         * Widget subtypes handled by render_widget().
         *
         * @var array<string,bool>
         */
        const WIDGET_TYPES = array(
                'html'          => true,
                'banner'        => true,
                'product_grid'  => true,
                'category_grid' => true,
                'mini_cart'     => true,
                'search'        => true,
                'custom_link'   => true,
        );

        /**
         * Global plugin settings (cached).
         *
         * @var array|null
         */
        private $settings = null;

        /**
         * Render a wtm_menu by post ID.
         *
         * @param int    $menu_id    Post ID of the wtm_menu.
         * @param string $location   Optional location slug (overrides stored meta).
         * @return string HTML markup (empty string if menu not found/empty).
         */
        public function render_by_id( $menu_id, $location = '' ) {
                $menu_id = (int) $menu_id;
                if ( $menu_id <= 0 ) {
                        return '';
                }

                $post = get_post( $menu_id );
                if ( ! $post || 'wtm_menu' !== $post->post_type || 'publish' !== $post->post_status ) {
                        return '';
                }

                $config     = json_decode( (string) get_post_meta( $menu_id, '_wtm_config', true ), true );
                $menu_type  = get_post_meta( $menu_id, '_wtm_menu_type', true ) ?: 'horizontal';
                $meta_loc   = get_post_meta( $menu_id, '_wtm_location', true ) ?: 'primary';
                $location   = $location ? $location : $meta_loc;

                if ( ! is_array( $config ) || empty( $config['items'] ) ) {
                        return '';
                }

                /**
                 * Filter the menu config before rendering (spec §2.8.4 — wtm_menu_config).
                 *
                 * @since 1.2.0
                 *
                 * @param array  $config Decoded JSON config.
                 * @param int    $menu_id Post ID.
                 * @param string $location Location slug.
                 */
                $config = apply_filters( 'wtm_menu_config', $config, $menu_id, $location );

                return $this->render( $config, $menu_type, $location, $post );
        }

        /**
         * Render a menu from its decoded config array.
         *
         * @param array    $config    Decoded _wtm_config JSON.
         * @param string   $menu_type horizontal|vertical|offcanvas|footer.
         * @param string   $location  Location slug.
         * @param \WP_Post $post      Optional post object (for title attr).
         * @return string HTML markup.
         */
        public function render( array $config, $menu_type, $location, $post = null ) {
                $items = is_array( $config['items'] ?? null ) ? $config['items'] : array();
                if ( empty( $items ) ) {
                        return '';
                }

                $settings  = is_array( $config['settings'] ?? null ) ? $config['settings'] : array();
                $menu_type = in_array( $menu_type, array( 'horizontal', 'vertical', 'offcanvas', 'footer' ), true ) ? $menu_type : 'horizontal';
                $location  = sanitize_key( (string) $location );
                $title     = $post ? get_the_title( $post ) : '';

                $nav_classes = array(
                        'wtm-menu',
                        'wtm-menu--' . $menu_type,
                        'wtm-menu--loc-' . $location,
                );
                if ( ! empty( $settings['sticky'] ) ) {
                        $nav_classes[] = 'wtm-menu--sticky';
                }
                if ( ! empty( $settings['align'] ) ) {
                        $nav_classes[] = 'wtm-menu--align-' . sanitize_html_class( $settings['align'] );
                }
                if ( ! empty( $settings['fullwidth_mega'] ) ) {
                        $nav_classes[] = 'wtm-menu--mega-fullwidth';
                }

                /**
                 * Filter the nav element classes (spec §2.8.4 — wtm_render_item context).
                 *
                 * @since 1.2.0
                 */
                $nav_classes = apply_filters( 'wtm_menu_classes', $nav_classes, $menu_type, $location, $config );
                $nav_classes = array_map( 'sanitize_html_class', $nav_classes );
                $nav_classes = array_filter( $nav_classes );

                $aria_label = $title ? sprintf( /* translators: %s: menu title */ __( 'Menu : %s', 'woo-total-menu' ), $title ) : __( 'Menu Woo Total', 'woo-total-menu' );

                // Build inner HTML by menu type.
                switch ( $menu_type ) {
                        case 'vertical':
                                $inner = $this->render_vertical_items( $items );
                                break;
                        case 'offcanvas':
                                $inner = $this->render_offcanvas( $items, $settings, $location, $title );
                                break;
                        case 'footer':
                                $inner = $this->render_footer_items( $items );
                                break;
                        case 'horizontal':
                        default:
                                $inner = $this->render_horizontal_items( $items, $settings );
                                break;
                }

                if ( 'offcanvas' === $menu_type ) {
                        // Off-canvas wraps the entire chrome (hamburger + drawer + overlay).
                        return $inner;
                }

                $nav_id = 'wtm-menu-' . $location;

                return sprintf(
                        '<nav id="%s" class="%s" aria-label="%s" data-wtm-menu data-wtm-type="%s" data-wtm-location="%s">%s</nav>',
                        esc_attr( $nav_id ),
                        esc_attr( implode( ' ', $nav_classes ) ),
                        esc_attr( $aria_label ),
                        esc_attr( $menu_type ),
                        esc_attr( $location ),
                        $inner
                );
        }

        // =========================================================================
        // Horizontal menu (méga menu) — spec §5.3, §5.4
        // =========================================================================

        /**
         * Render top-level items of an horizontal menu.
         *
         * @param array $items    Top-level items.
         * @param array $settings Per-menu settings.
         * @return string
         */
        private function render_horizontal_items( array $items, array $settings ) {
                $out = array();
                foreach ( $items as $item ) {
                        $out[] = $this->render_item_horizontal( $item, 0, $settings );
                }
                return '<ul class="wtm-menu__list wtm-menu__list--root">' . implode( '', $out ) . '</ul>';
        }

        /**
         * Render a single item in an horizontal menu.
         *
         * @param array   $item     Item config.
         * @param int     $depth    Current depth (0 = root).
         * @param array   $settings Per-menu settings.
         * @return string
         */
        private function render_item_horizontal( array $item, $depth, array $settings ) {
                if ( ! $this->is_item_visible( $item ) ) {
                        return '';
                }

                $type    = $item['type'] ?? 'link';
                $handler = 'render_h_' . $type;
                if ( ! method_exists( $this, $handler ) ) {
                        $handler = 'render_h_link';
                }

                /**
                 * Filter the HTML of an individual item (spec §2.8.4 — wtm_render_item).
                 *
                 * @since 1.2.0
                 *
                 * @param string $html  Item HTML (empty by default — bypasses internal renderer).
                 * @param array  $item  Item config.
                 * @param int    $depth Current depth.
                 */
                $pre = apply_filters( 'wtm_render_item', '', $item, $depth );
                if ( '' !== $pre ) {
                        return $pre;
                }

                return $this->$handler( $item, $depth, $settings );
        }

        /**
         * Render a `link` item in an horizontal menu.
         *
         * @param array $item     Item config.
         * @param int   $depth    Current depth.
         * @param array $settings Per-menu settings.
         * @return string
         */
        private function render_h_link( array $item, $depth, array $settings ) {
                $label   = esc_html( $item['label'] ?? '' );
                $url     = esc_url( $item['url'] ?? '#' );
                $target  = ! empty( $item['target'] ) && '_blank' === $item['target'] ? '_blank' : '_self';
                $rel     = '_blank' === $target ? ' rel="noopener noreferrer"' : '';
                $icon    = $this->render_icon( $item['icon'] ?? '' );
                $badge   = $this->render_badge( $item['badge'] ?? null );
                $classes = $this->item_classes( $item, $depth );
                $has_sub = ! empty( $item['children'] );
                $arrow   = $has_sub ? '<span class="wtm-menu__caret" aria-hidden="true"></span>' : '';

                $link_html = sprintf(
                        '<a href="%s" class="wtm-menu__link" target="%s"%s>%s<span class="wtm-menu__label">%s</span>%s%s</a>',
                        $url,
                        esc_attr( $target ),
                        $rel,
                        $icon,
                        $label,
                        $badge,
                        $arrow
                );

                $children_html = '';
                if ( $has_sub ) {
                        $children_html = $this->render_sub_menu( $item['children'], $depth + 1, $settings );
                }

                return sprintf(
                        '<li class="%s">%s%s</li>',
                        esc_attr( implode( ' ', $classes ) ),
                        $link_html,
                        $children_html
                );
        }

        /**
         * Render a `mega_container` item — top-level item that opens a wide panel.
         *
         * @param array $item     Item config.
         * @param int   $depth    Current depth.
         * @param array $settings Per-menu settings.
         * @return string
         */
        private function render_h_mega_container( array $item, $depth, array $settings ) {
                $label    = esc_html( $item['label'] ?? __( 'Méga menu', 'woo-total-menu' ) );
                $trigger  = $item['trigger'] ?? 'hover';
                $classes  = $this->item_classes( $item, $depth );
                $classes[] = 'wtm-menu__item--mega';
                $classes[] = 'wtm-menu__item--trigger-' . $trigger;
                $icon     = $this->render_icon( $item['icon'] ?? '' );
                $badge    = $this->render_badge( $item['badge'] ?? null );

                $btn_id = 'wtm-mega-trigger-' . sanitize_html_class( (string) ( $item['id'] ?? uniqid() ) );

                $trigger_html = sprintf(
                        '<button type="button" id="%s" class="wtm-menu__link wtm-menu__mega-trigger" aria-expanded="false" aria-haspopup="true" data-wtm-mega-trigger>%s<span class="wtm-menu__label">%s</span>%s<span class="wtm-menu__caret" aria-hidden="true"></span></button>',
                        esc_attr( $btn_id ),
                        $icon,
                        $label,
                        $badge
                );

                $panel_html = '';
                if ( ! empty( $item['children'] ) ) {
                        $panel_html = $this->render_mega_panel( $item['children'], $depth + 1, $settings, $btn_id );
                }

                return sprintf(
                        '<li class="%s">%s%s</li>',
                        esc_attr( implode( ' ', $classes ) ),
                        $trigger_html,
                        $panel_html
                );
        }

        /**
         * Render a `separator` item.
         *
         * @param array $item     Item config.
         * @param int   $depth    Current depth.
         * @param array $settings Per-menu settings.
         * @return string
         */
        private function render_h_separator( array $item, $depth, array $settings ) {
                return '<li class="wtm-menu__separator" role="separator" aria-hidden="true"></li>';
        }

        /**
         * Render a `title` item (column heading inside a mega panel).
         *
         * @param array $item     Item config.
         * @param int   $depth    Current depth.
         * @param array $settings Per-menu settings.
         * @return string
         */
        private function render_h_title( array $item, $depth, array $settings ) {
                $label = esc_html( $item['label'] ?? '' );
                return sprintf( '<li class="wtm-menu__title"><span>%s</span></li>', $label );
        }

        /**
         * Render a `widget` item.
         *
         * @param array $item     Item config.
         * @param int   $depth    Current depth.
         * @param array $settings Per-menu settings.
         * @return string
         */
        private function render_h_widget( array $item, $depth, array $settings ) {
                return $this->render_widget( $item, $depth );
        }

        /**
         * Render the mega panel — a wide absolute-positioned container with columns.
         *
         * @param array   $columns  Children (expected: column items).
         * @param int     $depth    Current depth.
         * @param array   $settings Per-menu settings.
         * @param string  $ctrl_id  ID of the trigger button (for aria-labelledby).
         * @return string
         */
        private function render_mega_panel( array $columns, $depth, array $settings, $ctrl_id ) {
                $cols_html = array();
                $col_count = 0;
                foreach ( $columns as $col ) {
                        if ( ( $col['type'] ?? '' ) !== 'column' ) {
                                // Be permissive: treat as a column wrapper.
                                $col = array( 'type' => 'column', 'children' => array( $col ) );
                        }
                        $cols_html[] = $this->render_mega_column( $col, $depth + 1, $settings );
                        $col_count++;
                }

                $cols_attr = $col_count > 0 ? ' data-wtm-cols="' . (int) $col_count . '"' : '';

                return sprintf(
                        '<div class="wtm-menu__mega-panel" role="region" aria-labelledby="%s" data-wtm-mega-panel%s><div class="wtm-menu__mega-grid">%s</div></div>',
                        esc_attr( $ctrl_id ),
                        $cols_attr,
                        implode( '', $cols_html )
                );
        }

        /**
         * Render a single column inside a mega panel.
         *
         * @param array $col      Column config (type=column, children=[]).
         * @param int   $depth    Current depth.
         * @param array $settings Per-menu settings.
         * @return string
         */
        private function render_mega_column( array $col, $depth, array $settings ) {
                $children = $col['children'] ?? array();
                $items    = array();
                foreach ( $children as $child ) {
                        $items[] = $this->render_item_horizontal( $child, $depth, $settings );
                }
                return '<div class="wtm-menu__mega-col"><ul class="wtm-menu__list wtm-menu__list--sub">' . implode( '', $items ) . '</ul></div>';
        }

        /**
         * Render a sub-menu (children of a link, depth 2+).
         *
         * @param array $children Child items.
         * @param int   $depth    Current depth.
         * @param array $settings Per-menu settings.
         * @return string
         */
        private function render_sub_menu( array $children, $depth, array $settings ) {
                $items = array();
                foreach ( $children as $child ) {
                        $items[] = $this->render_item_horizontal( $child, $depth, $settings );
                }
                return sprintf(
                        '<ul class="wtm-menu__list wtm-menu__list--sub" data-depth="%d">%s</ul>',
                        (int) $depth,
                        implode( '', $items )
                );
        }

        // =========================================================================
        // Vertical menu — spec §5.5
        // =========================================================================

        /**
         * Render the items of a vertical menu.
         *
         * @param array $items Top-level items.
         * @return string
         */
        private function render_vertical_items( array $items ) {
                $out = array();
                foreach ( $items as $item ) {
                        $out[] = $this->render_item_vertical( $item, 0 );
                }
                return '<ul class="wtm-menu__list wtm-menu__list--root wtm-menu__list--vertical">' . implode( '', $out ) . '</ul>';
        }

        /**
         * Render an item in a vertical menu (accordion / flyout).
         *
         * @param array $item  Item config.
         * @param int   $depth Current depth.
         * @return string
         */
        private function render_item_vertical( array $item, $depth ) {
                if ( ! $this->is_item_visible( $item ) ) {
                        return '';
                }
                $type = $item['type'] ?? 'link';

                if ( 'separator' === $type ) {
                        return '<li class="wtm-menu__separator" role="separator" aria-hidden="true"></li>';
                }
                if ( 'title' === $type ) {
                        return sprintf( '<li class="wtm-menu__title"><span>%s</span></li>', esc_html( $item['label'] ?? '' ) );
                }
                if ( 'widget' === $type ) {
                        return $this->render_widget( $item, $depth );
                }

                $label   = esc_html( $item['label'] ?? '' );
                $url     = esc_url( $item['url'] ?? '#' );
                $target  = ! empty( $item['target'] ) && '_blank' === $item['target'] ? '_blank' : '_self';
                $rel     = '_blank' === $target ? ' rel="noopener noreferrer"' : '';
                $icon    = $this->render_icon( $item['icon'] ?? '' );
                $badge   = $this->render_badge( $item['badge'] ?? null );
                $classes = $this->item_classes( $item, $depth );
                $classes[] = 'wtm-menu__item--vertical';
                $has_sub = ! empty( $item['children'] );
                $caret   = $has_sub ? '<span class="wtm-menu__caret wtm-menu__caret--right" aria-hidden="true"></span>' : '';

                $link_html = sprintf(
                        '<a href="%s" class="wtm-menu__link" target="%s"%s>%s<span class="wtm-menu__label">%s</span>%s%s</a>',
                        $url,
                        esc_attr( $target ),
                        $rel,
                        $icon,
                        $label,
                        $badge,
                        $caret
                );

                $children_html = '';
                if ( $has_sub ) {
                        $children_html = $this->render_vertical_sub( $item['children'], $depth + 1 );
                }

                return sprintf( '<li class="%s">%s%s</li>', esc_attr( implode( ' ', $classes ) ), $link_html, $children_html );
        }

        /**
         * Render vertical sub-items.
         *
         * @param array $children Child items.
         * @param int   $depth    Current depth.
         * @return string
         */
        private function render_vertical_sub( array $children, $depth ) {
                $items = array();
                foreach ( $children as $child ) {
                        $items[] = $this->render_item_vertical( $child, $depth );
                }
                return sprintf( '<ul class="wtm-menu__list wtm-menu__list--sub wtm-menu__list--vertical" data-depth="%d">%s</ul>', (int) $depth, implode( '', $items ) );
        }

        // =========================================================================
        // Off-canvas menu — spec §5.6
        // =========================================================================

        /**
         * Render an off-canvas menu (hamburger + drawer + overlay).
         *
         * @param array  $items     Top-level items.
         * @param array  $settings  Per-menu settings.
         * @param string $location  Location slug.
         * @param string $title     Menu title (for drawer header).
         * @return string
         */
        private function render_offcanvas( array $items, array $settings, $location, $title ) {
                $drawer_id   = 'wtm-offcanvas-' . $location;
                $trigger_lbl = __( 'Ouvrir le menu', 'woo-total-menu' );
                $close_lbl   = __( 'Fermer le menu', 'woo-total-menu' );
                $title_html  = $title ? '<span class="wtm-offcanvas__title">' . esc_html( $title ) . '</span>' : '';

                // Hamburger button.
                $button = sprintf(
                        '<button type="button" class="wtm-offcanvas__toggle" aria-controls="%s" aria-expanded="false" aria-label="%s" data-wtm-offcanvas-toggle><span class="wtm-offcanvas__bars" aria-hidden="true"></span></button>',
                        esc_attr( $drawer_id ),
                        esc_attr( $trigger_lbl )
                );

                // Drawer content — vertical list of items.
                $items_html = array();
                foreach ( $items as $item ) {
                        $items_html[] = $this->render_item_vertical( $item, 0 );
                }
                $list_html = '<ul class="wtm-menu__list wtm-menu__list--root wtm-menu__list--vertical wtm-offcanvas__list">' . implode( '', $items_html ) . '</ul>';

                // Drawer.
                $drawer = sprintf(
                        '<div class="wtm-offcanvas" id="%s" role="dialog" aria-modal="true" aria-label="%s" data-wtm-offcanvas aria-hidden="true"><div class="wtm-offcanvas__header">%s<button type="button" class="wtm-offcanvas__close" aria-label="%s" data-wtm-offcanvas-close><span aria-hidden="true"></span></button></div><div class="wtm-offcanvas__body">%s</div></div>',
                        esc_attr( $drawer_id ),
                        esc_attr( $title ? $title : __( 'Menu', 'woo-total-menu' ) ),
                        $title_html,
                        esc_attr( $close_lbl ),
                        $list_html
                );

                // Overlay.
                $overlay = '<div class="wtm-offcanvas__overlay" data-wtm-offcanvas-overlay aria-hidden="true"></div>';

                return $button . $drawer . $overlay;
        }

        // =========================================================================
        // Footer menu — spec §5.7
        // =========================================================================

        /**
         * Render a footer menu (grid of columns).
         *
         * @param array $items Top-level items (each becomes a column).
         * @return string
         */
        private function render_footer_items( array $items ) {
                $cols = array();
                foreach ( $items as $col ) {
                        $cols[] = $this->render_footer_column( $col, 0 );
                }
                return '<div class="wtm-menu__footer-grid">' . implode( '', $cols ) . '</div>';
        }

        /**
         * Render a single footer column.
         *
         * @param array $col   Column config.
         * @param int   $depth Current depth.
         * @return string
         */
        private function render_footer_column( array $col, $depth ) {
                $title = '';
                if ( ! empty( $col['label'] ) ) {
                        $title = sprintf( '<h3 class="wtm-menu__footer-title">%s</h3>', esc_html( $col['label'] ) );
                }

                $children = $col['children'] ?? array();
                $items    = array();
                foreach ( $children as $child ) {
                        if ( 'separator' === ( $child['type'] ?? '' ) ) {
                                $items[] = '<li class="wtm-menu__separator" role="separator"></li>';
                                continue;
                        }
                        if ( 'title' === ( $child['type'] ?? '' ) ) {
                                $items[] = sprintf( '<li class="wtm-menu__title"><span>%s</span></li>', esc_html( $child['label'] ?? '' ) );
                                continue;
                        }
                        if ( 'widget' === ( $child['type'] ?? '' ) ) {
                                $items[] = $this->render_widget( $child, $depth + 1 );
                                continue;
                        }
                        $items[] = $this->render_item_vertical( $child, $depth + 1 );
                }

                $list = '<ul class="wtm-menu__list wtm-menu__list--footer">' . implode( '', $items ) . '</ul>';
                return '<div class="wtm-menu__footer-col">' . $title . $list . '</div>';
        }

        // =========================================================================
        // Widgets — spec §5.9, §3.5
        // =========================================================================

        /**
         * Render a widget item by its subtype.
         *
         * @param array $item  Widget item config (must have widget_type + settings).
         * @param int   $depth Current depth.
         * @return string
         */
        private function render_widget( array $item, $depth ) {
                $widget_type = $item['widget_type'] ?? 'html';
                $settings    = is_array( $item['settings'] ?? null ) ? $item['settings'] : array();
                $label       = $item['label'] ?? '';
                $classes     = $this->item_classes( $item, $depth );
                $classes[]   = 'wtm-menu__widget';
                $classes[]   = 'wtm-menu__widget--' . sanitize_html_class( $widget_type );

                $handler = 'render_widget_' . $widget_type;
                if ( ! method_exists( $this, $handler ) ) {
                        $handler = 'render_widget_html';
                }

                $body = $this->$handler( $settings, $label );
                $label_html = $label ? sprintf( '<div class="wtm-menu__widget-label">%s</div>', esc_html( $label ) ) : '';

                return sprintf(
                        '<li class="%s">%s<div class="wtm-menu__widget-body">%s</div></li>',
                        esc_attr( implode( ' ', $classes ) ),
                        $label_html,
                        $body
                );
        }

        /**
         * HTML widget — wp_kses_post for safety (spec §7.10).
         *
         * @param array  $settings Widget settings.
         * @param string $label    Widget label.
         * @return string
         */
        private function render_widget_html( array $settings, $label ) {
                $html = $settings['html'] ?? '';
                if ( ! is_string( $html ) ) {
                        return '';
                }
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — wp_kses_post below.
                return wp_kses_post( $html );
        }

        /**
         * Banner widget — colored call-to-action block.
         *
         * @param array  $settings Widget settings (text, url, bg, color).
         * @param string $label    Widget label.
         * @return string
         */
        private function render_widget_banner( array $settings, $label ) {
                $text  = esc_html( $settings['text'] ?? $label );
                $url   = ! empty( $settings['url'] ) ? esc_url( $settings['url'] ) : '';
                $bg    = $this->sanitize_color( $settings['background'] ?? '#6C5CE7' );
                $color = $this->sanitize_color( $settings['color'] ?? '#FFFFFF' );

                $style = sprintf( 'background:%s;color:%s;', esc_attr( $bg ), esc_attr( $color ) );
                $inner = '<span class="wtm-banner__text">' . $text . '</span>';
                if ( $url ) {
                        return sprintf( '<a class="wtm-banner" href="%s" style="%s">%s</a>', $url, $style, $inner );
                }
                return sprintf( '<div class="wtm-banner" style="%s">%s</div>', $style, $inner );
        }

        /**
         * Product grid widget — featured/on-sale products via WC.
         * Uses transient cache (spec §7.8 — default 12h, filterable).
         *
         * @param array  $settings Widget settings (limit, columns, orderby).
         * @param string $label    Widget label.
         * @return string
         */
        private function render_widget_product_grid( array $settings, $label ) {
                if ( ! class_exists( 'WooCommerce' ) ) {
                        return '<div class="wtm-widget-empty">' . esc_html__( 'WooCommerce requis.', 'woo-total-menu' ) . '</div>';
                }

                $limit   = max( 1, min( 12, (int) ( $settings['limit'] ?? 4 ) ) );
                $cols    = max( 1, min( 4, (int) ( $settings['columns'] ?? 2 ) ) );
                $orderby = $settings['orderby'] ?? 'date';

                $cache_key = 'wtm_w_products_' . md5( wp_json_encode( $settings ) );
                $cached    = get_transient( $cache_key );
                if ( false !== $cached ) {
                        return $cached;
                }

                // Use wc_get_products() — handles visibility + caching internally (WC 3.0+).
                // The old `_visibility` meta key was deprecated; visibility is now a
                // taxonomy term (product_visibility).
                $wc_args = array(
                        'status' => 'publish',
                        'limit'  => $limit,
                        'orderby' => 'date',
                        'order'   => 'DESC',
                        'return'  => 'ids',
                );

                switch ( $orderby ) {
                        case 'price-asc':
                                $wc_args['orderby'] = 'price';
                                $wc_args['order']   = 'ASC';
                                break;
                        case 'price-desc':
                                $wc_args['orderby'] = 'price';
                                $wc_args['order']   = 'DESC';
                                break;
                        case 'popularity':
                                $wc_args['orderby'] = 'popularity';
                                $wc_args['order']   = 'DESC';
                                break;
                        case 'rating':
                                $wc_args['orderby'] = 'rating';
                                $wc_args['order']   = 'DESC';
                                break;
                        case 'date':
                        default:
                                $wc_args['orderby'] = 'date';
                                $wc_args['order']   = 'DESC';
                                break;
                }

                /**
                 * Filter the WC product query args for the product_grid widget.
                 *
                 * @since 1.2.0
                 *
                 * @param array $wc_args  Args for wc_get_products().
                 * @param array $settings Widget settings.
                 */
                $wc_args = apply_filters( 'wtm_product_grid_query', $wc_args, $settings );

                $ids = function_exists( 'wc_get_products' ) ? wc_get_products( $wc_args ) : array();

                if ( empty( $ids ) ) {
                        $out = '<div class="wtm-widget-empty">' . esc_html__( 'Aucun produit.', 'woo-total-menu' ) . '</div>';
                        set_transient( $cache_key, $out, 12 * HOUR_IN_SECONDS );
                        return $out;
                }

                $cards = array();
                foreach ( $ids as $pid ) {
                        $product = wc_get_product( $pid );
                        if ( ! $product ) {
                                continue;
                        }
                        $cards[] = $this->render_product_card( $product );
                }

                $out = sprintf(
                        '<div class="wtm-product-grid" style="--wtm-pg-cols:%d">%s</div>',
                        (int) $cols,
                        implode( '', $cards )
                );

                /**
                 * Filter the cache duration for the product_grid widget.
                 *
                 * @since 1.2.0
                 *
                 * @param int $duration Default 12 * HOUR_IN_SECONDS.
                 */
                $ttl = (int) apply_filters( 'wtm_widget_cache_duration', 12 * HOUR_IN_SECONDS, 'product_grid' );
                set_transient( $cache_key, $out, $ttl );

                return $out;
        }

        /**
         * Render a single product card.
         *
         * @param \WC_Product $product Product object.
         * @return string
         */
        private function render_product_card( $product ) {
                $pid    = $product->get_id();
                $permalink = esc_url( $product->get_permalink() );
                $title  = esc_html( $product->get_name() );
                $price  = wp_kses_post( $product->get_price_html() );
                $img    = $product->get_image( 'thumbnail', array( 'class' => 'wtm-product-card__img' ), false );
                $img    = $img ? $img : '<span class="wtm-product-card__img wtm-product-card__img--placeholder"></span>';

                $add_url = wp_nonce_url( add_query_arg( 'add-to-cart', $pid, $permalink ), 'add-to-cart-' . $pid );
                $btn     = sprintf(
                        '<a href="%s" class="wtm-product-card__btn" data-product_id="%d" data-wtm-add-to-cart>%s</a>',
                        esc_url( $add_url ),
                        (int) $pid,
                        esc_html__( 'Ajouter', 'woo-total-menu' )
                );

                return sprintf(
                        '<div class="wtm-product-card"><a href="%s" class="wtm-product-card__media">%s</a><div class="wtm-product-card__body"><a href="%s" class="wtm-product-card__title">%s</a><div class="wtm-product-card__price">%s</div>%s</div></div>',
                        $permalink,
                        $img,
                        $permalink,
                        $title,
                        $price,
                        $btn
                );
        }

        /**
         * Category grid widget — product categories via WC.
         *
         * @param array  $settings Widget settings (limit, columns, hide_empty).
         * @param string $label    Widget label.
         * @return string
         */
        private function render_widget_category_grid( array $settings, $label ) {
                if ( ! class_exists( 'WooCommerce' ) ) {
                        return '<div class="wtm-widget-empty">' . esc_html__( 'WooCommerce requis.', 'woo-total-menu' ) . '</div>';
                }

                $limit      = max( 1, min( 12, (int) ( $settings['limit'] ?? 4 ) ) );
                $cols       = max( 1, min( 4, (int) ( $settings['columns'] ?? 2 ) ) );
                $hide_empty = ! empty( $settings['hide_empty'] );

                $cache_key = 'wtm_w_cats_' . md5( wp_json_encode( $settings ) );
                $cached    = get_transient( $cache_key );
                if ( false !== $cached ) {
                        return $cached;
                }

                $cats = get_terms(
                        array(
                                'taxonomy'   => 'product_cat',
                                'hide_empty' => $hide_empty,
                                'number'     => $limit,
                                'orderby'    => 'count',
                                'order'      => 'DESC',
                        )
                );

                if ( is_wp_error( $cats ) || empty( $cats ) ) {
                        $out = '<div class="wtm-widget-empty">' . esc_html__( 'Aucune catégorie.', 'woo-total-menu' ) . '</div>';
                        set_transient( $cache_key, $out, 12 * HOUR_IN_SECONDS );
                        return $out;
                }

                $cards = array();
                foreach ( $cats as $cat ) {
                        $cards[] = $this->render_category_card( $cat );
                }

                $out = sprintf(
                        '<div class="wtm-category-grid" style="--wtm-cg-cols:%d">%s</div>',
                        (int) $cols,
                        implode( '', $cards )
                );

                $ttl = (int) apply_filters( 'wtm_widget_cache_duration', 12 * HOUR_IN_SECONDS, 'category_grid' );
                set_transient( $cache_key, $out, $ttl );

                return $out;
        }

        /**
         * Render a single category card.
         *
         * @param \WP_Term $cat Category term object.
         * @return string
         */
        private function render_category_card( $cat ) {
                $link  = esc_url( get_term_link( $cat, 'product_cat' ) );
                $name  = esc_html( $cat->name );
                $thumb = '';
                $tid   = get_term_meta( $cat->term_id, 'thumbnail_id', true );
                if ( $tid ) {
                        $img = wp_get_attachment_image( (int) $tid, 'thumbnail', false, array( 'class' => 'wtm-category-card__img' ) );
                        if ( $img ) {
                                $thumb = $img;
                        }
                }
                if ( ! $thumb ) {
                        $thumb = '<span class="wtm-category-card__img wtm-category-card__img--placeholder"></span>';
                }

                return sprintf(
                        '<a href="%s" class="wtm-category-card"><span class="wtm-category-card__media">%s</span><span class="wtm-category-card__name">%s</span></a>',
                        $link,
                        $thumb,
                        $name
                );
        }

        /**
         * Mini-cart widget — link to cart page with item count + total.
         *
         * @param array  $settings Widget settings.
         * @param string $label    Widget label.
         * @return string
         */
        private function render_widget_mini_cart( array $settings, $label ) {
                if ( ! class_exists( 'WooCommerce' ) ) {
                        return '<div class="wtm-widget-empty">' . esc_html__( 'WooCommerce requis.', 'woo-total-menu' ) . '</div>';
                }

                $count = WC()->cart ? WC()->cart->get_cart_contents_count() : 0;
                $total = WC()->cart ? WC()->cart->get_cart_total() : '';
                $url   = esc_url( wc_get_cart_url() );
                $lbl   = $label ? esc_html( $label ) : esc_html__( 'Panier', 'woo-total-menu' );

                $count_html = sprintf(
                        '<span class="wtm-mini-cart__count" data-wtm-cart-count>%d</span>',
                        (int) $count
                );

                $total_html = $total ? sprintf( '<span class="wtm-mini-cart__total" data-wtm-cart-total>%s</span>', wp_kses_post( $total ) ) : '';

                return sprintf(
                        '<a href="%s" class="wtm-mini-cart" data-wtm-mini-cart><span class="wtm-mini-cart__icon" aria-hidden="true"></span><span class="wtm-mini-cart__label">%s</span>%s%s</a>',
                        $url,
                        $lbl,
                        $count_html,
                        $total_html
                );
        }

        /**
         * Search widget — WC product search form.
         *
         * @param array  $settings Widget settings (placeholder).
         * @param string $label    Widget label.
         * @return string
         */
        private function render_widget_search( array $settings, $label ) {
                $placeholder = ! empty( $settings['placeholder'] ) ? $settings['placeholder'] : __( 'Rechercher un produit…', 'woo-total-menu' );

                if ( class_exists( 'WooCommerce' ) ) {
                        $form = '<form role="search" method="get" class="wtm-search" action="' . esc_url( home_url( '/' ) ) . '">';
                        $form .= '<label class="screen-reader-text" for="wtm-search-' . esc_attr( sanitize_key( $placeholder ) ) . '">' . esc_html__( 'Recherche :', 'woo-total-menu' ) . '</label>';
                        $form .= '<input type="search" class="wtm-search__input" placeholder="' . esc_attr( $placeholder ) . '" value="' . esc_attr( get_search_query() ) . '" name="s" />';
                        $form .= '<input type="hidden" name="post_type" value="product" />';
                        $form .= '<button type="submit" class="wtm-search__btn" aria-label="' . esc_attr__( 'Rechercher', 'woo-total-menu' ) . '"><span aria-hidden="true"></span></button>';
                        $form .= '</form>';
                        return $form;
                }

                // Fallback to WP search.
                return get_search_form( array( 'echo' => false ) );
        }

        /**
         * Custom_link widget — same as a link but flagged as widget.
         *
         * @param array  $settings Widget settings (url, label).
         * @param string $label    Widget label.
         * @return string
         */
        private function render_widget_custom_link( array $settings, $label ) {
                $url   = esc_url( $settings['url'] ?? '#' );
                $text  = esc_html( $settings['label'] ?? $label );
                $bg    = $this->sanitize_color( $settings['background'] ?? '#6C5CE7' );
                $color = $this->sanitize_color( $settings['color'] ?? '#FFFFFF' );
                $style = 'background:' . esc_attr( $bg ) . ';color:' . esc_attr( $color ) . ';';
                return sprintf( '<a href="%s" class="wtm-custom-link" style="%s">%s</a>', $url, $style, $text );
        }

        // =========================================================================
        // Helpers
        // =========================================================================

        /**
         * Check whether an item should be visible on the current request.
         *
         * @param array $item Item config.
         * @return bool
         */
        private function is_item_visible( array $item ) {
                $vis = $item['visibility'] ?? 'show';
                if ( 'hide' === $vis ) {
                        return false;
                }
                $is_mobile = wp_is_mobile();
                if ( 'show_on_mobile' === $vis && ! $is_mobile ) {
                        return false;
                }
                if ( 'hide_on_mobile' === $vis && $is_mobile ) {
                        return false;
                }
                return true;
        }

        /**
         * Compute the CSS classes for an item.
         *
         * @param array $item  Item config.
         * @param int   $depth Current depth.
         * @return array<string>
         */
        private function item_classes( array $item, $depth ) {
                $classes = array(
                        'wtm-menu__item',
                        'wtm-menu__item--' . sanitize_html_class( $item['type'] ?? 'link' ),
                        'wtm-menu__item--depth-' . (int) $depth,
                );
                if ( ! empty( $item['children'] ) ) {
                        $classes[] = 'wtm-menu__item--has-children';
                }
                if ( ! empty( $item['classes'] ) && is_array( $item['classes'] ) ) {
                        foreach ( $item['classes'] as $c ) {
                                $c = sanitize_html_class( (string) $c );
                                if ( $c ) {
                                        $classes[] = $c;
                                }
                        }
                }
                return $classes;
        }

        /**
         * Render an SVG icon (named from Phosphor icons set) or dashicon.
         *
         * For v1.2.0: returns a span with the icon name as data attribute; the
         * CSS picks up the glyph. This avoids embedding large SVG strings.
         *
         * @param string $icon Icon name or empty.
         * @return string
         */
        private function render_icon( $icon ) {
                if ( empty( $icon ) ) {
                        return '';
                }
                // If it's a URL (svg upload), embed it as <img>.
                if ( preg_match( '#^https?://#', $icon ) ) {
                        return sprintf( '<img src="%s" class="wtm-menu__icon" alt="" aria-hidden="true" />', esc_url( $icon ) );
                }
                // Otherwise treat as a dashicon name.
                return sprintf( '<span class="wtm-menu__icon wtm-menu__icon--%s" aria-hidden="true"></span>', esc_attr( sanitize_html_class( $icon ) ) );
        }

        /**
         * Render a badge.
         *
         * @param array|null $badge Badge config {text, color, background}.
         * @return string
         */
        private function render_badge( $badge ) {
                if ( empty( $badge ) || empty( $badge['text'] ) ) {
                        return '';
                }
                $bg    = $this->sanitize_color( $badge['background'] ?? '#6C5CE7' );
                $color = $this->sanitize_color( $badge['color'] ?? '#FFFFFF' );
                $text  = esc_html( $badge['text'] );
                $style = 'background:' . esc_attr( $bg ) . ';color:' . esc_attr( $color ) . ';';
                return sprintf( '<span class="wtm-menu__badge" style="%s">%s</span>', $style, $text );
        }

        /**
         * Sanitize a hex / rgb color.
         *
         * @param string $color Color value.
         * @return string Sanitized color or empty.
         */
        private function sanitize_color( $color ) {
                $color = trim( (string) $color );
                if ( '' === $color ) {
                        return '';
                }
                // Allow hex (#RGB / #RRGGBB) and rgb()/rgba().
                if ( preg_match( '/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $color ) ) {
                        return $color;
                }
                if ( preg_match( '/^rgba?\(\s*\d+\s*,\s*\d+\s*,\s*\d+\s*(,\s*[\d.]+\s*)?\)$/', $color ) ) {
                        return $color;
                }
                return '';
        }
}
