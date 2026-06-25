<?php
/**
 * Header & Footer renderer.
 *
 * Walks a wtm_menu _wtm_header_config or _wtm_footer_config JSON tree
 * (rows → columns → modules) and produces semantic, accessible HTML.
 *
 * Spec reference: §3.6 (Header arborescence), §3.7 (Footer arborescence),
 * §4.6.5 (mode Header/Footer Builder), §5.7 (footer frontend),
 * §5.8 (header frontend), §10.2.3 (mini-cart module), §10.2.4 (search module).
 *
 * Module types (Schema_Validator::MODULE_TYPES):
 *   - logo       : Image + link (defaults to home).
 *   - menu       : Renders an existing wtm_menu post by ID, OR a WordPress
 *                  native nav_menu (taxonomy=nav_menu) by term_id when the
 *                  `menu_source` setting is set to "wp" (default "wtm").
 *   - search     : Search bar with live suggestions (reuses the search widget UI).
 *   - cart       : Mini-cart icon with AJAX fragments + drawer.
 *   - button     : CTA button (label, link, style).
 *   - html       : Free HTML/text.
 *   - social     : Social icons (reuses the social_icons widget renderer).
 *   - newsletter : Email subscribe form (reuses the newsletter widget renderer).
 *   - text       : Plain text (slogan, phone number, copyright).
 *
 * @package WooTotalMenu
 * @since 1.4.0
 */

namespace WooTotalMenu\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

/**
 * Class Header_Footer_Renderer
 *
 * Pure-PHP renderer: takes a layout config array and returns an HTML string.
 * Has no side effects (does not echo, does not enqueue assets — that's
 * Assets_Loader's job).
 */
class Header_Footer_Renderer {

        /**
         * Menu_Renderer instance (injected) — used to render `menu` modules.
         *
         * @var Menu_Renderer
         */
        private $menu_renderer;

        /**
         * Global plugin settings (cached per-request).
         *
         * @var array|null
         */
        private $settings = null;

        /**
         * Constructor.
         *
         * @param Menu_Renderer $menu_renderer Menu renderer (for the `menu` module type).
         */
        public function __construct( Menu_Renderer $menu_renderer ) {
                $this->menu_renderer = $menu_renderer;
        }

        /**
         * Render a header layout by menu post ID.
         *
         * @param int    $menu_id Post ID of the wtm_menu carrying the header config.
         * @return string HTML markup (empty if no config found).
         */
        public function render_header_by_id( $menu_id ) {
                $menu_id = (int) $menu_id;
                if ( $menu_id <= 0 ) {
                        return '';
                }

                $post = get_post( $menu_id );
                if ( ! $post || 'wtm_menu' !== $post->post_type || 'publish' !== $post->post_status ) {
                        return '';
                }

                $raw = get_post_meta( $menu_id, '_wtm_header_config', true );
                if ( ! $raw ) {
                        return '';
                }
                $config = json_decode( (string) $raw, true );
                if ( ! is_array( $config ) ) {
                        return '';
                }

                return $this->render( $config, 'header', $menu_id );
        }

        /**
         * Render a footer layout by menu post ID.
         *
         * @param int $menu_id Post ID of the wtm_menu carrying the footer config.
         * @return string HTML markup (empty if no config found).
         */
        public function render_footer_by_id( $menu_id ) {
                $menu_id = (int) $menu_id;
                if ( $menu_id <= 0 ) {
                        return '';
                }

                $post = get_post( $menu_id );
                if ( ! $post || 'wtm_menu' !== $post->post_type || 'publish' !== $post->post_status ) {
                        return '';
                }

                $raw = get_post_meta( $menu_id, '_wtm_footer_config', true );
                if ( ! $raw ) {
                        return '';
                }
                $config = json_decode( (string) $raw, true );
                if ( ! is_array( $config ) ) {
                        return '';
                }

                return $this->render( $config, 'footer', $menu_id );
        }

        /**
         * Render a header/footer layout from its decoded config.
         *
         * @param array  $config Decoded JSON config (rows → columns → modules).
         * @param string $type   'header' or 'footer'.
         * @param int    $menu_id Optional. Source menu post ID (for context/filters).
         * @return string HTML markup.
         */
        public function render( array $config, $type, $menu_id = 0 ) {
                $type = ( 'footer' === $type ) ? 'footer' : 'header';
                $rows = is_array( $config['rows'] ?? null ) ? $config['rows'] : array();
                if ( empty( $rows ) ) {
                        return '';
                }

                /**
                 * Filter the layout config before rendering.
                 *
                 * @since 1.4.0
                 *
                 * @param array  $config  Decoded JSON config.
                 * @param string $type    'header' or 'footer'.
                 * @param int    $menu_id Source menu post ID.
                 */
                $config = apply_filters( 'wtm_layout_config', $config, $type, $menu_id );

                $wrapper_classes = array(
                        'wtm-' . $type,
                        'wtm-' . $type . '--loc-' . sanitize_key( (string) $menu_id ),
                );
                $settings = is_array( $config['settings'] ?? null ) ? $config['settings'] : array();

                if ( ! empty( $settings['sticky'] ) && 'header' === $type ) {
                        $wrapper_classes[] = 'wtm-header--sticky';
                }
                if ( ! empty( $settings['fullwidth'] ) ) {
                        $wrapper_classes[] = 'wtm-' . $type . '--fullwidth';
                }

                /**
                 * Filter the wrapper element classes.
                 *
                 * @since 1.4.0
                 */
                $wrapper_classes = apply_filters( 'wtm_layout_classes', $wrapper_classes, $type, $config, $menu_id );
                $wrapper_classes = array_map( 'sanitize_html_class', $wrapper_classes );
                $wrapper_classes = array_filter( $wrapper_classes );

                $rows_html = array();
                foreach ( $rows as $row ) {
                        $rows_html[] = $this->render_row( $row, $type, $menu_id );
                }
                $rows_html = implode( '', $rows_html );
                if ( '' === $rows_html ) {
                        return '';
                }

                $tag = 'header' === $type ? 'header' : 'footer';
                $aria_label = 'header' === $type
                        ? __( 'En-tête du site', 'woo-total-menu' )
                        : __( 'Pied de page du site', 'woo-total-menu' );

                $id = 'wtm-' . $type . '-' . (int) $menu_id;

                $inline_style = $this->build_inline_style( $settings, $type );

                return sprintf(
                        '<%1$s id="%2$s" class="%3$s" aria-label="%4$s" data-wtm-layout data-wtm-type="%5$s" data-wtm-menu="%6$d"%7$s>%8$s</%1$s>',
                        $tag,
                        esc_attr( $id ),
                        esc_attr( implode( ' ', $wrapper_classes ) ),
                        esc_attr( $aria_label ),
                        esc_attr( $type ),
                        (int) $menu_id,
                        $inline_style ? ' style="' . esc_attr( $inline_style ) . '"' : '',
                        $rows_html
                );
        }

        /**
         * Build inline CSS custom properties from layout settings.
         *
         * @param array  $settings Layout settings.
         * @param string $type     'header' or 'footer'.
         * @return string CSS declarations (e.g. "--wtm-bg:#fff;").
         */
        private function build_inline_style( array $settings, $type ) {
                $css = array();
                if ( ! empty( $settings['background'] ) ) {
                        $css[] = '--wtm-bg:' . $this->sanitize_color( $settings['background'] );
                }
                if ( ! empty( $settings['text_color'] ) ) {
                        $css[] = '--wtm-text:' . $this->sanitize_color( $settings['text_color'] );
                }
                if ( ! empty( $settings['link_color'] ) ) {
                        $css[] = '--wtm-link:' . $this->sanitize_color( $settings['link_color'] );
                }
                if ( isset( $settings['max_width'] ) ) {
                        $mw = (int) $settings['max_width'];
                        if ( $mw > 0 ) {
                                $css[] = '--wtm-maxw:' . $mw . 'px';
                        }
                }
                if ( isset( $settings['padding_y'] ) ) {
                        $py = (int) $settings['padding_y'];
                        if ( $py >= 0 ) {
                                $css[] = '--wtm-py:' . $py . 'px';
                        }
                }
                return implode( ';', $css );
        }

        /**
         * Sanitize a hex/rgb color value.
         *
         * @param string $value Raw value.
         * @return string Sanitized value (may be empty).
         */
        private function sanitize_color( $value ) {
                $value = (string) $value;
                if ( '' === $value ) {
                        return '';
                }
                // Allow hex (#RGB, #RRGGBB, #RRGGBBAA) and rgb()/rgba() functions.
                if ( preg_match( '/^#([0-9a-f]{3}|[0-9a-f]{6}|[0-9a-f]{8})$/i', $value ) ) {
                        return $value;
                }
                if ( preg_match( '/^rgba?\(\s*[\d\s,.%\/]+\s*\)$/i', $value ) ) {
                        return $value;
                }
                return '';
        }

        /**
         * Render a single row.
         *
         * @param array  $row     Row config (id, columns, settings).
         * @param string $type    'header' or 'footer'.
         * @param int    $menu_id Source menu post ID.
         * @return string
         */
        private function render_row( array $row, $type, $menu_id ) {
                $columns = is_array( $row['columns'] ?? null ) ? $row['columns'] : array();
                if ( empty( $columns ) ) {
                        return '';
                }

                $row_settings = is_array( $row['settings'] ?? null ) ? $row['settings'] : array();

                $classes = array(
                        'wtm-' . $type . '__row',
                );
                if ( ! empty( $row_settings['hide_desktop'] ) ) {
                        $classes[] = 'wtm-hide-desktop';
                }
                if ( ! empty( $row_settings['hide_mobile'] ) ) {
                        $classes[] = 'wtm-hide-mobile';
                }
                if ( ! empty( $row_settings['sticky'] ) ) {
                        $classes[] = 'wtm-row--sticky';
                }

                /**
                 * Filter row classes.
                 *
                 * @since 1.4.0
                 */
                $classes = apply_filters( 'wtm_layout_row_classes', $classes, $row, $type, $menu_id );
                $classes = array_map( 'sanitize_html_class', $classes );
                $classes = array_filter( $classes );

                $inline_style = $this->build_row_style( $row_settings );

                $cols_html = array();
                foreach ( $columns as $col ) {
                        $cols_html[] = $this->render_column( $col, $type, $menu_id );
                }

                return sprintf(
                        '<div class="%1$s" data-wtm-row="%2$s"%3$s>%4$s</div>',
                        esc_attr( implode( ' ', $classes ) ),
                        esc_attr( (string) ( $row['id'] ?? '' ) ),
                        $inline_style ? ' style="' . esc_attr( $inline_style ) . '"' : '',
                        implode( '', $cols_html )
                );
        }

        /**
         * Build inline style for a row.
         *
         * @param array $settings Row settings.
         * @return string
         */
        private function build_row_style( array $settings ) {
                $css = array();
                if ( ! empty( $settings['background'] ) ) {
                        $css[] = 'background:' . $this->sanitize_color( $settings['background'] );
                }
                if ( isset( $settings['height'] ) ) {
                        $h = (int) $settings['height'];
                        if ( $h > 0 ) {
                                $css[] = 'min-height:' . $h . 'px';
                        }
                }
                if ( isset( $settings['padding_y'] ) ) {
                        $py = (int) $settings['padding_y'];
                        if ( $py >= 0 ) {
                                $css[] = 'padding-top:' . $py . 'px';
                                $css[] = 'padding-bottom:' . $py . 'px';
                        }
                }
                if ( ! empty( $settings['align'] ) ) {
                        $align_map = array(
                                'left'   => 'flex-start',
                                'center' => 'center',
                                'right'  => 'flex-end',
                                'space-between' => 'space-between',
                        );
                        $align = $align_map[ $settings['align'] ] ?? 'space-between';
                        $css[] = 'justify-content:' . $align;
                }
                return implode( ';', $css );
        }

        /**
         * Render a single column.
         *
         * @param array  $col     Column config (id, width, modules, settings).
         * @param string $type    'header' or 'footer'.
         * @param int    $menu_id Source menu post ID.
         * @return string
         */
        private function render_column( array $col, $type, $menu_id ) {
                $modules = is_array( $col['modules'] ?? null ) ? $col['modules'] : array();
                // Even if no modules, render the column (keeps the grid balanced).
                $width = isset( $col['width'] ) ? max( 1, min( 12, (int) $col['width'] ) ) : 0;
                $col_settings = is_array( $col['settings'] ?? null ) ? $col['settings'] : array();

                $classes = array(
                        'wtm-' . $type . '__col',
                );
                if ( $width > 0 ) {
                        $classes[] = 'wtm-col--w-' . $width;
                }
                if ( ! empty( $col_settings['align'] ) ) {
                        $classes[] = 'wtm-col--align-' . sanitize_html_class( $col_settings['align'] );
                }
                if ( ! empty( $col_settings['valign'] ) ) {
                        $classes[] = 'wtm-col--valign-' . sanitize_html_class( $col_settings['valign'] );
                }

                /**
                 * Filter column classes.
                 *
                 * @since 1.4.0
                 */
                $classes = apply_filters( 'wtm_layout_col_classes', $classes, $col, $type, $menu_id );
                $classes = array_map( 'sanitize_html_class', $classes );
                $classes = array_filter( $classes );

                $mods_html = array();
                foreach ( $modules as $module ) {
                        $mods_html[] = $this->render_module( $module, $type, $menu_id );
                }

                $flex_style = $width > 0 ? ' style="flex: 0 0 ' . ( $width / 12 * 100 ) . '%"' : '';

                return sprintf(
                        '<div class="%1$s" data-wtm-col="%2$s"%3$s>%4$s</div>',
                        esc_attr( implode( ' ', $classes ) ),
                        esc_attr( (string) ( $col['id'] ?? '' ) ),
                        $flex_style,
                        implode( '', $mods_html )
                );
        }

        /**
         * Render a single module.
         *
         * @param array  $module Module config (id, type, settings).
         * @param string $type   'header' or 'footer'.
         * @param int    $menu_id Source menu post ID.
         * @return string
         */
        private function render_module( array $module, $type, $menu_id ) {
                $module_type = $module['type'] ?? '';
                $settings    = is_array( $module['settings'] ?? null ) ? $module['settings'] : array();
                $module_id   = (string) ( $module['id'] ?? '' );

                $handler = 'render_module_' . $module_type;
                if ( ! method_exists( $this, $handler ) ) {
                        // Unknown module type — render nothing rather than a broken placeholder.
                        return '';
                }

                /**
                 * Pre-filter: allow overriding the module HTML entirely.
                 *
                 * @since 1.4.0
                 *
                 * @param string $html     Module HTML (empty = use default renderer).
                 * @param array  $module   Module config.
                 * @param string $type     'header' or 'footer'.
                 * @param int    $menu_id  Source menu post ID.
                 */
                $pre = apply_filters( 'wtm_render_module', '', $module, $type, $menu_id );
                if ( '' !== $pre ) {
                        return $pre;
                }

                $classes = array(
                        'wtm-module',
                        'wtm-module--' . sanitize_html_class( $module_type ),
                );
                if ( ! empty( $settings['hide_desktop'] ) ) {
                        $classes[] = 'wtm-hide-desktop';
                }
                if ( ! empty( $settings['hide_mobile'] ) ) {
                        $classes[] = 'wtm-hide-mobile';
                }
                if ( ! empty( $settings['custom_class'] ) ) {
                        $classes[] = sanitize_html_class( $settings['custom_class'] );
                }

                /**
                 * Filter module classes.
                 *
                 * @since 1.4.0
                 */
                $classes = apply_filters( 'wtm_layout_module_classes', $classes, $module, $type, $menu_id );
                $classes = array_map( 'sanitize_html_class', $classes );
                $classes = array_filter( $classes );

                $inner = $this->$handler( $settings, $menu_id );

                if ( '' === $inner ) {
                        return '';
                }

                $custom_id = ! empty( $settings['custom_id'] ) ? sanitize_html_class( $settings['custom_id'] ) : '';

                return sprintf(
                        '<div class="%1$s" data-wtm-module="%2$s"%3$s>%4$s</div>',
                        esc_attr( implode( ' ', $classes ) ),
                        esc_attr( $module_id ),
                        $custom_id ? ' id="' . esc_attr( $custom_id ) . '"' : '',
                        $inner
                );
        }

        // =========================================================================
        // Module renderers
        // =========================================================================

        /**
         * Render a `logo` module.
         *
         * Settings:
         *   - image_id (int)  Attachment ID. Falls back to site logo (theme_mod custom_logo).
         *   - url      (str)  Link URL. Defaults to home_url('/').
         *   - max_width(int)  Max width in px.
         *   - alt      (str)  Alt text. Defaults to site name.
         *
         * @param array $s Module settings.
         * @param int   $menu_id Source menu post ID (unused).
         * @return string
         */
        private function render_module_logo( array $s, $menu_id ) {
                $image_id = (int) ( $s['image_id'] ?? 0 );
                if ( ! $image_id ) {
                        // Fall back to theme custom logo.
                        $image_id = (int) get_theme_mod( 'custom_logo' );
                }
                if ( ! $image_id ) {
                        // Final fallback: text logo.
                        $url = esc_url( $s['url'] ?? home_url( '/' ) );
                        $alt = esc_html( $s['alt'] ?? get_bloginfo( 'name' ) );
                        return sprintf(
                                '<a href="%1$s" class="wtm-logo wtm-logo--text">%2$s</a>',
                                $url,
                                $alt
                        );
                }

                $url   = esc_url( $s['url'] ?? home_url( '/' ) );
                $alt   = esc_html( $s['alt'] ?? get_bloginfo( 'name' ) );
                $mw    = isset( $s['max_width'] ) ? max( 0, (int) $s['max_width'] ) : 0;
                $style = $mw > 0 ? ' style="max-width:' . $mw . 'px"' : '';

                $img = wp_get_attachment_image( $image_id, 'full', false, array(
                        'class' => 'wtm-logo__img',
                        'alt'   => $alt,
                ) );

                if ( ! $img ) {
                        return '';
                }

                return sprintf(
                        '<a href="%1$s" class="wtm-logo"%2$s>%3$s</a>',
                        $url,
                        $style,
                        $img
                );
        }

        /**
         * Render a `menu` module — delegates to Menu_Renderer for a wtm_menu,
         * OR to wp_nav_menu() for a WordPress native nav_menu.
         *
         * Settings:
         *   - menu_source (str) 'wtm' (default) — references a wtm_menu post by ID.
         *                       'wp'           — references a WordPress native
         *                                        nav_menu by term_id.
         *   - menu_id    (int)  Required. Post ID (wtm) or term_id (wp) to render.
         *   - location   (str)  Optional location slug (wtm only).
         *
         * @param array $s Module settings.
         * @param int   $menu_id Source menu post ID (unused).
         * @return string
         */
        private function render_module_menu( array $s, $menu_id ) {
                $ref_id = (int) ( $s['menu_id'] ?? 0 );
                if ( $ref_id <= 0 ) {
                        return '';
                }
                $source   = isset( $s['menu_source'] ) ? sanitize_key( $s['menu_source'] ) : 'wtm';
                $location = isset( $s['location'] ) ? sanitize_key( $s['location'] ) : '';

                if ( 'wp' === $source ) {
                        // WordPress native nav_menu (taxonomy=nav_menu) — render
                        // through the standard wp_nav_menu() walker so any menu
                        // created at /wp-admin/nav-menus.php is honoured.
                        $html = $this->render_wp_nav_menu( $ref_id, $location );
                } else {
                        $html = $this->menu_renderer->render_by_id( $ref_id, $location );
                }

                /**
                 * Fires when a header/footer `menu` module is rendered.
                 *
                 * @since 1.4.0
                 *
                 * @param int    $ref_id    The referenced menu ID (post_id or term_id).
                 * @param int    $menu_id   The source menu (header/footer carrier).
                 * @param string $source    'wtm' or 'wp'.
                 */
                do_action( 'wtm_rendered_location', $ref_id, $location ?: 'header_footer_module' );

                return $html;
        }

        /**
         * Render a WordPress native nav_menu by term_id.
         *
         * Used by the `menu` module when `menu_source` is "wp". Wraps
         * wp_nav_menu() with a few sane defaults so the markup matches
         * the rest of the plugin's output (semantic <nav>, wtm classes).
         *
         * @since 1.7.1
         *
         * @param int    $term_id  nav_menu term_id.
         * @param string $location Optional location slug (used for the wrapper class).
         * @return string HTML markup (empty if the menu does not exist).
         */
        private function render_wp_nav_menu( $term_id, $location = '' ) {
                $term_id = (int) $term_id;
                if ( $term_id <= 0 ) {
                        return '';
                }

                $term = get_term( $term_id, 'nav_menu' );
                if ( ! $term || is_wp_error( $term ) ) {
                        return '';
                }

                $location_class = $location ? ' wtm-nav--' . sanitize_html_class( $location ) : '';

                /**
                 * Filter the wp_nav_menu() args used to render a native nav_menu
                 * inside a header/footer `menu` module.
                 *
                 * @since 1.7.1
                 *
                 * @param array    $args     wp_nav_menu() arguments.
                 * @param WP_Term  $term     The nav_menu term being rendered.
                 * @param string   $location Optional location slug.
                 */
                $args = apply_filters(
                        'wtm_wp_nav_menu_args',
                        array(
                                'menu'            => $term,
                                'menu_class'      => 'wtm-nav__menu wtm-wp-nav__menu',
                                'menu_id'         => '',
                                'container'       => 'nav',
                                'container_class' => 'wtm-nav wtm-wp-nav' . $location_class,
                                'container_id'    => '',
                                'fallback_cb'     => false,
                                'echo'            => false,
                                'depth'           => 0,
                        ),
                        $term,
                        $location
                );

                $html = wp_nav_menu( $args );

                /**
                 * Filter the rendered HTML of a native nav_menu module.
                 *
                 * @since 1.7.1
                 *
                 * @param string  $html Rendered HTML.
                 * @param WP_Term $term The nav_menu term.
                 */
                return apply_filters( 'wtm_wp_nav_menu_html', $html, $term );
        }

        /**
         * Render a `search` module.
         *
         * Settings:
         *   - placeholder  (str)
         *   - style        (str) inline | overlay
         *   - search_sku   (bool)
         *
         * @param array $s Module settings.
         * @param int   $menu_id Source menu post ID (unused).
         * @return string
         */
        private function render_module_search( array $s, $menu_id ) {
                $placeholder = $s['placeholder'] ?? __( 'Rechercher…', 'woo-total-menu' );
                $style       = isset( $s['style'] ) && 'overlay' === $s['style'] ? 'overlay' : 'inline';

                // Live suggestions (v1.4.0 — reuses the v1.3 search widget JS).
                $live            = ! empty( $s['live_suggestions'] );
                $min_chars       = max( 2, min( 10, (int) ( $s['min_chars'] ?? 3 ) ) );
                $live_class      = $live ? ' wtm-search--live' : '';
                $live_input_attr = $live ? sprintf( ' data-wtm-live-search data-min-chars="%d"', $min_chars ) : '';
                $suggestions_attr = $live ? ' data-wtm-suggestions' : '';

                $action = esc_url( class_exists( 'WooCommerce' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' ) );
                $icon  = '<span class="wtm-search__icon" aria-hidden="true">' . $this->icon_svg( 'search' ) . '</span>';

                // Reuse the search widget markup pattern for consistency (v1.3.0).
                return sprintf(
                        '<form role="search" method="get" class="wtm-search wtm-search--%2$s%7$s" action="%3$s" data-wtm-search data-wtm-sku="%4$s">
                                <label class="screen-reader-text" for="wtm-s-%5$s">%1$s</label>
                                %6$s
                                <input type="search" id="wtm-s-%5$s" class="wtm-search__input" name="s" placeholder="%1$s" autocomplete="off"%8$s />
                                <input type="hidden" name="post_type" value="product" />
                                <div class="wtm-search__suggestions"%9$s role="listbox" aria-hidden="true"></div>
                        </form>',
                        esc_attr( $placeholder ),
                        esc_attr( $style ),
                        $action,
                        ! empty( $s['search_sku'] ) ? '1' : '0',
                        esc_attr( wp_generate_uuid4() ),
                        $icon,
                        $live_class,
                        $live_input_attr,
                        $suggestions_attr
                );
        }

        /**
         * Render a `cart` module (mini cart icon + AJAX drawer).
         *
         * Settings:
         *   - show_total  (bool) Show the price next to the counter.
         *   - behavior    (str)  dropdown | drawer (default: drawer)
         *
         * @param array $s Module settings.
         * @param int   $menu_id Source menu post ID (unused).
         * @return string
         */
        private function render_module_cart( array $s, $menu_id ) {
                if ( ! class_exists( 'WooCommerce' ) ) {
                        return '';
                }

                $show_total = ! empty( $s['show_total'] );
                $behavior   = isset( $s['behavior'] ) && 'dropdown' === $s['behavior'] ? 'dropdown' : 'drawer';

                $count = WC()->cart ? WC()->cart->get_cart_contents_count() : 0;
                $total = WC()->cart ? WC()->cart->get_cart_total() : '';

                $icon = $this->icon_svg( 'cart' );
                $price_html = $show_total ? sprintf( '<span class="wtm-cart__total">%s</span>', $total ) : '';

                return sprintf(
                        '<button type="button" class="wtm-cart" data-wtm-cart data-wtm-cart-behavior="%1$s" aria-label="%2$s">
                                <span class="wtm-cart__icon" aria-hidden="true">%3$s</span>
                                <span class="wtm-cart__count" data-wtm-cart-count>%4$d</span>
                                %5$s
                        </button>',
                        esc_attr( $behavior ),
                        esc_attr__( 'Ouvrir le panier', 'woo-total-menu' ),
                        $icon,
                        (int) $count,
                        $price_html
                );
        }

        /**
         * Render a `button` module (CTA).
         *
         * Settings:
         *   - text   (str)  Required.
         *   - url    (str)  Required.
         *   - target (str)  _self | _blank
         *   - style  (str)  primary | secondary | ghost
         *   - icon   (str)  optional icon key
         *
         * @param array $s Module settings.
         * @param int   $menu_id Source menu post ID (unused).
         * @return string
         */
        private function render_module_button( array $s, $menu_id ) {
                $text   = isset( $s['text'] ) ? trim( $s['text'] ) : '';
                $url    = isset( $s['url'] ) ? trim( $s['url'] ) : '#';
                if ( '' === $text ) {
                        return '';
                }
                $target = ! empty( $s['target'] ) && '_blank' === $s['target'] ? '_blank' : '_self';
                $rel    = '_blank' === $target ? ' rel="noopener noreferrer"' : '';
                $style  = isset( $s['style'] ) ? sanitize_html_class( $s['style'] ) : 'primary';
                $icon   = ! empty( $s['icon'] ) ? $this->icon_svg( $s['icon'] ) : '';

                return sprintf(
                        '<a href="%1$s" class="wtm-button wtm-button--%2$s" target="%3$s"%4$s>%5$s<span class="wtm-button__label">%6$s</span></a>',
                        esc_url( $url ),
                        esc_attr( $style ),
                        esc_attr( $target ),
                        $rel,
                        $icon ? '<span class="wtm-button__icon" aria-hidden="true">' . $icon . '</span>' : '',
                        esc_html( $text )
                );
        }

        /**
         * Render an `html` module — free HTML/text content.
         *
         * Settings:
         *   - content (str) Raw HTML (already sanitized at save time via wp_kses_post).
         *
         * @param array $s Module settings.
         * @param int   $menu_id Source menu post ID (unused).
         * @return string
         */
        private function render_module_html( array $s, $menu_id ) {
                $content = isset( $s['content'] ) ? (string) $s['content'] : '';
                if ( '' === $content ) {
                        return '';
                }
                // Allow only safe HTML tags. This is the same allowlist used by
                // wp_kses_post, which is the standard for post content.
                return '<div class="wtm-html">' . wp_kses_post( $content ) . '</div>';
        }

        /**
         * Render a `social` module — social icons.
         *
         * Settings:
         *   - links (array) [{ network: 'facebook', url: 'https://…' }, …]
         *
         * @param array $s Module settings.
         * @param int   $menu_id Source menu post ID (unused).
         * @return string
         */
        private function render_module_social( array $s, $menu_id ) {
                $links = is_array( $s['links'] ?? null ) ? $s['links'] : array();
                if ( empty( $links ) ) {
                        return '';
                }

                $items = array();
                foreach ( $links as $link ) {
                        $network = isset( $link['network'] ) ? sanitize_key( $link['network'] ) : '';
                        $url     = isset( $link['url'] ) ? esc_url( $link['url'] ) : '#';
                        if ( '' === $network ) {
                                continue;
                        }
                        $label = ucfirst( $network );
                        $svg   = $this->social_icon_svg( $network );
                        $items[] = sprintf(
                                '<a href="%1$s" class="wtm-social__link wtm-social__link--%2$s" target="_blank" rel="noopener noreferrer" aria-label="%3$s">%4$s</a>',
                                $url,
                                esc_attr( $network ),
                                esc_attr( $label ),
                                $svg
                        );
                }

                if ( empty( $items ) ) {
                        return '';
                }

                return '<div class="wtm-social">' . implode( '', $items ) . '</div>';
        }

        /**
         * Render a `newsletter` module — email subscription form.
         *
         * Settings:
         *   - title       (str)
         *   - placeholder (str)
         *   - button_text (str)
         *   - provider    (str) mailchimp | brevo | internal
         *
         * @param array $s Module settings.
         * @param int   $menu_id Source menu post ID (unused).
         * @return string
         */
        private function render_module_newsletter( array $s, $menu_id ) {
                $title       = isset( $s['title'] ) ? trim( $s['title'] ) : '';
                $placeholder = $s['placeholder'] ?? __( 'Votre email', 'woo-total-menu' );
                $btn_text    = $s['button_text'] ?? __( 'S\'abonner', 'woo-total-menu' );
                $provider    = isset( $s['provider'] ) ? sanitize_key( $s['provider'] ) : 'internal';
                $list_id     = isset( $s['list_id'] ) ? sanitize_text_field( $s['list_id'] ) : '';
                $success     = isset( $s['success_message'] ) ? trim( $s['success_message'] ) : __( 'Merci ! Votre inscription a bien été prise en compte.', 'woo-total-menu' );

                $nonce = wp_create_nonce( 'wtm_newsletter' );

                $title_html = $title ? '<p class="wtm-newsletter__title">' . esc_html( $title ) . '</p>' : '';

                // Config payload consumed by wtm-frontend.js (matches the v1.3 widget output).
                $config_json = wp_json_encode( array( 'success' => $success ) );

                return sprintf(
                        '<form class="wtm-newsletter wtm-newsletter--stacked" data-wtm-newsletter data-provider="%1$s" data-list-id="%2$s" data-nonce="%3$s">
                                %4$s
                                <div class="wtm-newsletter__row">
                                        <input type="email" class="wtm-newsletter__input" name="email" placeholder="%5$s" required aria-label="%6$s" />
                                        <button type="submit" class="wtm-newsletter__btn">%7$s</button>
                                </div>
                                <p class="wtm-newsletter__msg" data-wtm-newsletter-message role="status" aria-live="polite" hidden></p>
                                <script type="application/json" data-wtm-newsletter-config>%8$s</script>
                        </form>',
                        esc_attr( $provider ),
                        esc_attr( $list_id ),
                        esc_attr( $nonce ),
                        $title_html,
                        esc_attr( $placeholder ),
                        esc_attr__( 'Adresse email', 'woo-total-menu' ),
                        esc_html( $btn_text ),
                        $config_json
                );
        }

        /**
         * Render a `text` module — plain text (slogan, phone, copyright).
         *
         * Settings:
         *   - content (str) Plain text. Shortcodes supported. [year] → current year.
         *
         * @param array $s Module settings.
         * @param int   $menu_id Source menu post ID (unused).
         * @return string
         */
        private function render_module_text( array $s, $menu_id ) {
                $content = isset( $s['content'] ) ? (string) $s['content'] : '';
                if ( '' === $content ) {
                        return '';
                }
                // Replace [year] with current year (used by copyright modules).
                $content = str_replace( '[year]', (string) gmdate( 'Y' ), $content );
                // Allow shortcodes (but escape HTML by default).
                $content = esc_html( $content );
                $content = do_shortcode( $content );
                return '<div class="wtm-text">' . $content . '</div>';
        }

        // =========================================================================
        // Icon helpers
        // =========================================================================

        /**
         * Get an inline SVG icon by key.
         *
         * @param string $key Icon key (search, cart, user, heart, arrow-right…).
         * @return string SVG markup.
         */
        private function icon_svg( $key ) {
                $key = sanitize_key( $key );
                $icons = array(
                        'search'      => '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>',
                        'cart'        => '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>',
                        'user'        => '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 4-6 8-6s8 2 8 6"/></svg>',
                        'heart'       => '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>',
                        'arrow-right' => '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>',
                        'menu'        => '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>',
                        'phone'       => '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.95.36 1.88.7 2.77a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.31-1.31a2 2 0 0 1 2.11-.45c.89.34 1.82.57 2.77.7A2 2 0 0 1 22 16.92z"/></svg>',
                );
                return $icons[ $key ] ?? '';
        }

        /**
         * Get an inline SVG for a social network.
         *
         * @param string $network Network key (facebook, twitter, instagram, …).
         * @return string
         */
        private function social_icon_svg( $network ) {
                $paths = array(
                        'facebook'  => '<svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M9 8h-3v4h3v12h5v-12h3.642l.358-4h-4v-1.667c0-.955.192-1.333 1.115-1.333h2.885v-5h-3.808c-3.596 0-5.192 1.583-5.192 4.615v3.385z"/></svg>',
                        'twitter'   => '<svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M24 4.557c-.883.392-1.832.656-2.828.775 1.017-.609 1.798-1.574 2.165-2.724-.951.564-2.005.974-3.127 1.195-.897-.957-2.178-1.555-3.594-1.555-3.179 0-5.515 2.966-4.797 6.045-4.091-.205-7.719-2.165-10.148-5.144-1.29 2.213-.669 5.108 1.523 6.574-.806-.026-1.566-.247-2.229-.616-.054 2.281 1.581 4.415 3.949 4.89-.693.188-1.452.232-2.224.084.626 1.956 2.444 3.379 4.6 3.419-2.07 1.623-4.678 2.348-7.29 2.04 2.179 1.397 4.768 2.212 7.548 2.212 9.142 0 14.307-7.721 13.995-14.646.962-.695 1.797-1.562 2.457-2.549z"/></svg>',
                        'instagram' => '<svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>',
                        'linkedin'  => '<svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M4.98 3.5c0 1.381-1.119 2.5-2.5 2.5s-2.5-1.119-2.5-2.5 1.119-2.5 2.5-2.5 2.5 1.119 2.5 2.5zm.02 4.5h-5v16h5v-16zm7.982 0h-4.968v16h4.969v-8.399c0-4.67 6.029-5.052 6.029 0v8.399h4.988v-10.131c0-7.88-8.922-7.593-11.018-3.714v-2.155z"/></svg>',
                        'youtube'   => '<svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M19.615 3.184c-3.604-.246-11.631-.245-15.23 0-3.897.266-4.356 2.62-4.385 8.816.029 6.185.484 8.549 4.385 8.816 3.6.245 11.626.246 15.23 0 3.897-.266 4.356-2.62 4.385-8.816-.029-6.185-.484-8.549-4.385-8.816zm-10.615 12.816v-8l8 3.993-8 4.007z"/></svg>',
                        'pinterest' => '<svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M12 0c-6.627 0-12 5.372-12 12 0 5.084 3.163 9.426 7.627 11.174-.105-.949-.2-2.405.042-3.441.218-.937 1.407-5.965 1.407-5.965s-.359-.719-.359-1.782c0-1.668.967-2.914 2.171-2.914 1.023 0 1.518.769 1.518 1.69 0 1.029-.655 2.568-.994 3.995-.283 1.194.599 2.169 1.777 2.169 2.133 0 3.772-2.249 3.772-5.495 0-2.873-2.064-4.882-5.012-4.882-3.414 0-5.418 2.561-5.418 5.207 0 1.031.397 2.138.893 2.738.098.119.112.224.083.345-.09.375-.293 1.199-.334 1.367-.053.223-.171.269-.401.162-1.499-.698-2.436-2.889-2.436-4.649 0-3.785 2.75-7.262 7.929-7.262 4.163 0 7.398 2.967 7.398 6.931 0 4.136-2.607 7.464-6.227 7.464-1.216 0-2.359-.631-2.75-1.378l-.748 2.853c-.271 1.043-1.002 2.35-1.492 3.146 1.124.347 2.317.535 3.554.535 6.627 0 12-5.373 12-12 0-6.628-5.373-12-12-12z"/></svg>',
                        'tiktok'    => '<svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-.99-.32-2.15-.23-3.02.37-.63.41-1.11 1.04-1.36 1.75-.21.51-.15 1.07-.14 1.61.24 1.64 1.82 3.02 3.5 2.87 1.12-.01 2.19-.66 2.77-1.61.19-.33.4-.67.41-1.06.1-1.79.06-3.57.07-5.36.01-4.03-.01-8.05.02-12.07z"/></svg>',
                );
                return $paths[ $network ] ?? '<svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><circle cx="12" cy="12" r="10"/></svg>';
        }
}
