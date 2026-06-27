<?php
/**
 * Settings page.
 *
 * @package WooTotalMenu
 */

namespace WooTotalMenu\Admin\Pages;

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

/**
 * Class Settings
 *
 * Edit the wtm_global_settings option via a tabbed form.
 */
class Settings {

        const OPTION_KEY    = WTM_OPTION_SETTINGS;
        const SETTINGS_GROUP = 'wtm_settings_group';

        /**
         * Tab definitions.
         *
         * @var array
         */
        private static $tabs = array(
                'general'     => array( 'label' => 'Général',     'icon' => 'admin-generic' ),
                'styles'      => array( 'label' => 'Styles',      'icon' => 'art' ),
                'typography'  => array( 'label' => 'Typographie', 'icon' => 'editor-textcolor' ),
                'responsive'  => array( 'label' => 'Responsive',  'icon' => 'smartphone' ),
                'performance'    => array( 'label' => 'Performance',    'icon' => 'performance' ),
                'header_footer' => array( 'label' => 'Header / Footer', 'icon' => 'layout' ),
                'analytics'     => array( 'label' => 'Analytics',     'icon' => 'chart-bar' ),
                'permissions' => array( 'label' => 'Permissions', 'icon' => 'shield-alt' ),
        );

        /**
         * Render the page.
         *
         * @return void
         */
        public static function render() {
                // Save handler.
                if ( isset( $_POST['wtm_settings_submit'] ) ) {
                        self::save_settings();
                }

                $settings = get_option( self::OPTION_KEY );
                if ( ! is_array( $settings ) ) {
                        $settings = \WooTotalMenu\Bootstrap::default_settings();
                }

                // phpcs:disable WordPress.Security.NonceVerification.Recommended
                $active_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general';
                if ( ! isset( self::$tabs[ $active_tab ] ) ) {
                        $active_tab = 'general';
                }
                // phpcs:enable

                ?>
                <div class="wrap wtm-page">
                        <h1><span class="dashicons dashicons-admin-settings"></span> <?php esc_html_e( 'Réglages', 'woo-total-menu' ); ?></h1>
                        <p class="wtm-page-subtitle">
                                <?php esc_html_e( 'Configurez le comportement global de Woo Total Menu.', 'woo-total-menu' ); ?>
                        </p>

                        <?php if ( isset( $_GET['wtm_settings_saved'] ) ) : ?>
                                <div class="wtm-notice is-success"><p><?php esc_html_e( 'Réglages enregistrés avec succès.', 'woo-total-menu' ); ?></p></div>
                        <?php endif; ?>

                        <div class="wtm-tabs">
                                <?php foreach ( self::$tabs as $slug => $tab ) : ?>
                                        <a href="?page=wtm-settings&tab=<?php echo esc_attr( $slug ); ?>" class="<?php echo $active_tab === $slug ? 'is-active' : ''; ?>">
                                                <span class="dashicons dashicons-<?php echo esc_attr( $tab['icon'] ); ?>" style="vertical-align:middle; margin-right:4px; font-size:14px; width:14px; height:14px;"></span>
                                                <?php echo esc_html( $tab['label'] ); ?>
                                        </a>
                                <?php endforeach; ?>
                        </div>

                        <form method="post" action="">
                                <?php wp_nonce_field( 'wtm_save_settings', 'wtm_settings_nonce' ); ?>
                                <input type="hidden" name="wtm_settings_submit" value="1">
                                <input type="hidden" name="wtm_active_tab" value="<?php echo esc_attr( $active_tab ); ?>">

                                <?php
                                switch ( $active_tab ) :
                                        case 'general':
                                                self::render_tab_general( $settings );
                                                break;
                                        case 'styles':
                                                self::render_tab_styles( $settings );
                                                break;
                                        case 'typography':
                                                self::render_tab_typography( $settings );
                                                break;
                                        case 'responsive':
                                                self::render_tab_responsive( $settings );
                                                break;
                                        case 'performance':
                                                self::render_tab_performance( $settings );
                                                break;
                                        case 'header_footer':
                                                self::render_tab_header_footer( $settings );
                                                break;
                                        case 'analytics':
                                                self::render_tab_analytics( $settings );
                                                break;
                                        case 'permissions':
                                                self::render_tab_permissions( $settings );
                                                break;
                                endswitch;
                                ?>

                                <p style="margin-top:20px;">
                                        <button type="submit" class="wtm-btn">
                                                <span class="dashicons dashicons-saved"></span>
                                                <?php esc_html_e( 'Enregistrer les réglages', 'woo-total-menu' ); ?>
                                        </button>
                                </p>
                        </form>
                </div>
                <?php
        }

        /**
         * Tab: General.
         *
         * @param array $s Settings.
         * @return void
         */
        private static function render_tab_general( $s ) {
                $general = $s['general'] ?? array();
                ?>
                <div class="wtm-form-section">
                        <h3><span class="dashicons dashicons-admin-generic"></span> <?php esc_html_e( 'Configuration générale', 'woo-total-menu' ); ?></h3>

                        <div class="wtm-form-row">
                                <label for="general_enabled">
                                        <input type="checkbox" name="general[enabled]" id="general_enabled" value="1" <?php checked( ! empty( $general['enabled'] ) ); ?>>
                                        <?php esc_html_e( 'Activer Woo Total Menu sur le site', 'woo-total-menu' ); ?>
                                </label>
                                <p class="description"><?php esc_html_e( 'Si décoché, aucun menu Woo Total Menu ne sera affiché, même si des menus sont publiés.', 'woo-total-menu' ); ?></p>
                        </div>

                        <div class="wtm-form-row">
                                <label for="general_default_location"><?php esc_html_e( 'Emplacement par défaut pour les nouveaux menus', 'woo-total-menu' ); ?></label>
                                <select name="general[default_location]" id="general_default_location">
                                        <?php foreach ( \WooTotalMenu\Core\CPT_Manager::get_locations() as $slug => $label ) : ?>
                                                <option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $general['default_location'] ?? 'primary', $slug ); ?>>
                                                        <?php echo esc_html( $label ); ?>
                                                </option>
                                        <?php endforeach; ?>
                                </select>
                        </div>
                </div>
                <?php
        }

        /**
         * Tab: Styles.
         *
         * @param array $s Settings.
         * @return void
         */
        private static function render_tab_styles( $s ) {
                $styles = $s['styles'] ?? array();
                ?>
                <div class="wtm-form-section">
                        <h3><span class="dashicons dashicons-art"></span> <?php esc_html_e( 'Couleurs et apparence', 'woo-total-menu' ); ?></h3>
                        <p style="color:#6b7280; margin-top:0;">
                                <?php esc_html_e( 'Ces couleurs sont utilisées par défaut pour tous les menus. Elles peuvent être surchargées individuellement par menu (à partir de la v1.4.x).', 'woo-total-menu' ); ?>
                        </p>

                        <div class="wtm-grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));">
                                <div class="wtm-form-row">
                                        <label for="styles_primary_color"><?php esc_html_e( 'Couleur primaire', 'woo-total-menu' ); ?></label>
                                        <input type="color" name="styles[primary_color]" id="styles_primary_color" value="<?php echo esc_attr( $styles['primary_color'] ?? '#6C5CE7' ); ?>">
                                        <p class="description"><?php esc_html_e( 'Boutons, survols, badges', 'woo-total-menu' ); ?></p>
                                </div>

                                <div class="wtm-form-row">
                                        <label for="styles_background"><?php esc_html_e( 'Couleur de fond', 'woo-total-menu' ); ?></label>
                                        <input type="color" name="styles[background]" id="styles_background" value="<?php echo esc_attr( $styles['background'] ?? '#FFFFFF' ); ?>">
                                </div>

                                <div class="wtm-form-row">
                                        <label for="styles_text_color"><?php esc_html_e( 'Couleur du texte', 'woo-total-menu' ); ?></label>
                                        <input type="color" name="styles[text_color]" id="styles_text_color" value="<?php echo esc_attr( $styles['text_color'] ?? '#2D3436' ); ?>">
                                </div>

                                <div class="wtm-form-row">
                                        <label for="styles_success_color"><?php esc_html_e( 'Couleur de succès', 'woo-total-menu' ); ?></label>
                                        <input type="color" name="styles[success_color]" id="styles_success_color" value="<?php echo esc_attr( $styles['success_color'] ?? '#00B894' ); ?>">
                                </div>

                                <div class="wtm-form-row">
                                        <label for="styles_error_color"><?php esc_html_e( 'Couleur d\'erreur', 'woo-total-menu' ); ?></label>
                                        <input type="color" name="styles[error_color]" id="styles_error_color" value="<?php echo esc_attr( $styles['error_color'] ?? '#FF7675' ); ?>">
                                </div>

                                <div class="wtm-form-row">
                                        <label for="styles_border_radius"><?php esc_html_e( 'Arrondi des coins (px)', 'woo-total-menu' ); ?></label>
                                        <input type="number" min="0" max="50" name="styles[border_radius]" id="styles_border_radius" value="<?php echo esc_attr( (string) ( $styles['border_radius'] ?? 6 ) ); ?>">
                                </div>
                        </div>
                </div>
                <?php
        }

        /**
         * Tab: Typography.
         *
         * @param array $s Settings.
         * @return void
         */
        private static function render_tab_typography( $s ) {
                $t = $s['typography'] ?? array();
                $fonts = array( 'Inter', 'Roboto', 'Open Sans', 'Lato', 'Montserrat', 'Poppins', 'Source Sans Pro', 'Nunito', 'Raleway', 'system-ui' );
                ?>
                <div class="wtm-form-section">
                        <h3><span class="dashicons dashicons-editor-textcolor"></span> <?php esc_html_e( 'Typographie', 'woo-total-menu' ); ?></h3>

                        <div class="wtm-form-row">
                                <label for="typography_font_family"><?php esc_html_e( 'Police d\'écriture', 'woo-total-menu' ); ?></label>
                                <select name="typography[font_family]" id="typography_font_family">
                                        <?php foreach ( $fonts as $font ) : ?>
                                                <option value="<?php echo esc_attr( $font ); ?>" <?php selected( $t['font_family'] ?? 'Inter', $font ); ?>>
                                                        <?php echo esc_html( $font ); ?>
                                                </option>
                                        <?php endforeach; ?>
                                </select>
                                <p class="description"><?php esc_html_e( 'Les polices Google Fonts seront chargées automatiquement côté frontend.', 'woo-total-menu' ); ?></p>
                        </div>

                        <div class="wtm-grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));">
                                <div class="wtm-form-row">
                                        <label for="typography_base_size"><?php esc_html_e( 'Taille de base (px)', 'woo-total-menu' ); ?></label>
                                        <input type="number" min="10" max="24" name="typography[base_size]" id="typography_base_size" value="<?php echo esc_attr( (string) ( $t['base_size'] ?? 14 ) ); ?>">
                                </div>
                                <div class="wtm-form-row">
                                        <label for="typography_heading_size"><?php esc_html_e( 'Taille des titres (px)', 'woo-total-menu' ); ?></label>
                                        <input type="number" min="14" max="36" name="typography[heading_size]" id="typography_heading_size" value="<?php echo esc_attr( (string) ( $t['heading_size'] ?? 18 ) ); ?>">
                                </div>
                        </div>
                </div>
                <?php
        }

        /**
         * Tab: Responsive.
         *
         * @param array $s Settings.
         * @return void
         */
        private static function render_tab_responsive( $s ) {
                $r = $s['responsive'] ?? array();
                ?>
                <div class="wtm-form-section">
                        <h3><span class="dashicons dashicons-smartphone"></span> <?php esc_html_e( 'Comportement responsive', 'woo-total-menu' ); ?></h3>

                        <div class="wtm-grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));">
                                <div class="wtm-form-row">
                                        <label for="responsive_mobile_breakpoint"><?php esc_html_e( 'Breakpoint mobile (px)', 'woo-total-menu' ); ?></label>
                                        <input type="number" min="320" max="1200" name="responsive[mobile_breakpoint]" id="responsive_mobile_breakpoint" value="<?php echo esc_attr( (string) ( $r['mobile_breakpoint'] ?? 768 ) ); ?>">
                                        <p class="description"><?php esc_html_e( 'En dessous de cette largeur, le menu bascule en mode mobile.', 'woo-total-menu' ); ?></p>
                                </div>
                                <div class="wtm-form-row">
                                        <label for="responsive_tablet_breakpoint"><?php esc_html_e( 'Breakpoint tablette (px)', 'woo-total-menu' ); ?></label>
                                        <input type="number" min="600" max="1400" name="responsive[tablet_breakpoint]" id="responsive_tablet_breakpoint" value="<?php echo esc_attr( (string) ( $r['tablet_breakpoint'] ?? 1024 ) ); ?>">
                                </div>
                        </div>

                        <div class="wtm-form-row">
                                <label for="responsive_mobile_behavior"><?php esc_html_e( 'Comportement mobile', 'woo-total-menu' ); ?></label>
                                <select name="responsive[mobile_behavior]" id="responsive_mobile_behavior">
                                        <option value="offcanvas" <?php selected( $r['mobile_behavior'] ?? 'offcanvas', 'offcanvas' ); ?>><?php esc_html_e( 'Off-canvas (panneau latéral)', 'woo-total-menu' ); ?></option>
                                        <option value="accordion" <?php selected( $r['mobile_behavior'] ?? 'offcanvas', 'accordion' ); ?>><?php esc_html_e( 'Accordéon', 'woo-total-menu' ); ?></option>
                                        <option value="dropdown" <?php selected( $r['mobile_behavior'] ?? 'offcanvas', 'dropdown' ); ?>><?php esc_html_e( 'Menu déroulant', 'woo-total-menu' ); ?></option>
                                </select>
                        </div>

                        <div class="wtm-form-row">
                                <label for="responsive_hamburger_position"><?php esc_html_e( 'Position du bouton hamburger', 'woo-total-menu' ); ?></label>
                                <select name="responsive[hamburger_position]" id="responsive_hamburger_position">
                                        <option value="right" <?php selected( $r['hamburger_position'] ?? 'right', 'right' ); ?>><?php esc_html_e( 'À droite', 'woo-total-menu' ); ?></option>
                                        <option value="left"  <?php selected( $r['hamburger_position'] ?? 'right', 'left' ); ?>><?php esc_html_e( 'À gauche', 'woo-total-menu' ); ?></option>
                                </select>
                        </div>
                </div>
                <?php
        }

        /**
         * Tab: Performance.
         *
         * @param array $s Settings.
         * @return void
         */
        private static function render_tab_performance( $s ) {
                $p = $s['performance'] ?? array();
                ?>
                <div class="wtm-form-section">
                        <h3><span class="dashicons dashicons-performance"></span> <?php esc_html_e( 'Performance et cache', 'woo-total-menu' ); ?></h3>

                        <div class="wtm-form-row">
                                <label for="performance_cache_enabled">
                                        <input type="checkbox" name="performance[cache_enabled]" id="performance_cache_enabled" value="1" <?php checked( ! empty( $p['cache_enabled'] ) ); ?>>
                                        <?php esc_html_e( 'Activer le cache objet pour les configurations de menus', 'woo-total-menu' ); ?>
                                </label>
                                <p class="description"><?php esc_html_e( 'Recommandé — accélère le rendu en évitant la désérialisation du JSON à chaque page.', 'woo-total-menu' ); ?></p>
                        </div>

                        <div class="wtm-form-row">
                                <label for="performance_lazy_load_widgets">
                                        <input type="checkbox" name="performance[lazy_load_widgets]" id="performance_lazy_load_widgets" value="1" <?php checked( ! empty( $p['lazy_load_widgets'] ) ); ?>>
                                        <?php esc_html_e( 'Charger les widgets dynamiques en AJAX à l\'ouverture des méga conteneurs', 'woo-total-menu' ); ?>
                                </label>
                                <p class="description"><?php esc_html_e( 'Recommandé pour les boutiques avec beaucoup de produits — réduit le poids initial de la page.', 'woo-total-menu' ); ?></p>
                        </div>

                        <div class="wtm-form-row">
                                <label for="performance_minify_css">
                                        <input type="checkbox" name="performance[minify_css]" id="performance_minify_css" value="1" <?php checked( ! empty( $p['minify_css'] ) ); ?>>
                                        <?php esc_html_e( 'Minifier le CSS dynamique généré', 'woo-total-menu' ); ?>
                                </label>
                        </div>
                </div>
                <?php
        }

        /**
         * Tab: Analytics.
         *
         * @param array $s Settings.
         * @return void
         */
        private static function render_tab_analytics( $s ) {
                $a = $s['analytics'] ?? array();
                ?>
                <div class="wtm-form-section">
                        <h3><span class="dashicons dashicons-chart-bar"></span> <?php esc_html_e( 'Analytique', 'woo-total-menu' ); ?></h3>
                        <p style="color:#6b7280; margin-top:0;">
                                <?php esc_html_e( 'Collecte anonymisée et conforme RGPD : aucun IP, aucun user ID, aucun cookie. Seuls des compteurs agrégés par jour sont stockés.', 'woo-total-menu' ); ?>
                        </p>

                        <div class="wtm-form-row">
                                <label for="analytics_enabled">
                                        <input type="checkbox" name="analytics[enabled]" id="analytics_enabled" value="1" <?php checked( ! empty( $a['enabled'] ) ); ?>>
                                        <?php esc_html_e( 'Activer la collecte d\'analytics (vues et clics sur les menus)', 'woo-total-menu' ); ?>
                                </label>
                                <p class="description"><?php esc_html_e( 'Une fois activé, le JS frontend envoie un événement view (par menu affiché) et un événement click (par item cliqué) vers l\'API REST /wtm/v1/analytics/track.', 'woo-total-menu' ); ?></p>
                        </div>

                        <div class="wtm-form-row">
                                <label for="analytics_track_logged">
                                        <input type="checkbox" name="analytics[track_logged]" id="analytics_track_logged" value="1" <?php checked( ! empty( $a['track_logged'] ) ); ?>>
                                        <?php esc_html_e( 'Tracker également les utilisateurs connectés', 'woo-total-menu' ); ?>
                                </label>
                                <p class="description"><?php esc_html_e( 'Par défaut, seuls les visiteurs anonymes sont trackés pour éviter de polluer les métriques avec les activités des administrateurs.', 'woo-total-menu' ); ?></p>
                        </div>

                        <div class="wtm-form-row">
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=wtm-analytics' ) ); ?>" class="wtm-btn is-secondary">
                                        <span class="dashicons dashicons-chart-area"></span>
                                        <?php esc_html_e( 'Voir le tableau de bord analytics', 'woo-total-menu' ); ?>
                                </a>
                        </div>
                </div>
                <?php
        }

        /**
         * Tab: Header / Footer.
         *
         * Lets the admin enable automatic header/footer replacement via the
         * Header/Footer Builder. When enabled, the selected wtm_menu replaces
         * the theme's default header and/or footer on the front-end.
         *
         * @param array $s Settings.
         * @return void
         */
        private static function render_tab_header_footer( $s ) {
                $hf      = $s['header_footer'] ?? array();
                $enabled = ! empty( $hf['enabled'] );

                // Fetch all published wtm_menu posts for the dropdowns.
                $menus = get_posts(
                        array(
                                'post_type'      => 'wtm_menu',
                                'posts_per_page' => 200,
                                'post_status'    => 'publish',
                                'orderby'        => 'title',
                                'order'          => 'ASC',
                        )
                );

                ?>
                <div class="wtm-form-section">
                        <h3><span class="dashicons dashicons-layout"></span> <?php esc_html_e( 'Injection Header / Footer', 'woo-total-menu' ); ?></h3>
                        <p style="color:#6b7280; margin-top:0;">
                                <?php esc_html_e( 'Remplacez le header et/ou le footer de votre th\xE8me par ceux cr\xE9\xE9s avec le Builder Header/Footer de Woo Total Menu.', 'woo-total-menu' ); ?>
                        </p>

                        <div class="wtm-form-row">
                                <label for="hf_enabled">
                                        <input type="checkbox" name="header_footer[enabled]" id="hf_enabled" value="1" <?php checked( $enabled ); ?>>
                                        <?php esc_html_e( "Activer l'injection automatique du header/footer", 'woo-total-menu' ); ?>
                                </label>
                                <p class="description"><?php esc_html_e( 'Remplace le header et le footer du th\xE8me par les menus WTM s\xE9lectionn\xE9s ci-dessous.', 'woo-total-menu' ); ?></p>
                        </div>

                        <div class="wtm-grid" style="grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); margin-top:16px;">
                                <div class="wtm-form-row">
                                        <label for="hf_header_menu_id"><strong><?php esc_html_e( 'Menu pour le Header', 'woo-total-menu' ); ?></strong></label>
                                        <select name="header_footer[header_menu_id]" id="hf_header_menu_id">
                                                <option value="0"><?php esc_html_e( '\xE2\x80\x94 Aucun (conserver le th\xE8me) \xE2\x80\x94', 'woo-total-menu' ); ?></option>
                                                <?php foreach ( $menus as $menu ) : ?>
                                                        <option value="<?php echo esc_attr( $menu->ID ); ?>" <?php selected( absint( $hf['header_menu_id'] ?? 0 ), $menu->ID ); ?>>
                                                                <?php echo esc_html( $menu->post_title ); ?>
                                                        </option>
                                                <?php endforeach; ?>
                                        </select>
                                        <p class="description"><?php esc_html_e( 'S\xE9lectionnez un menu de type header cr\xE9\xE9 dans le Builder.', 'woo-total-menu' ); ?></p>
                                </div>

                                <div class="wtm-form-row">
                                        <label for="hf_footer_menu_id"><strong><?php esc_html_e( 'Menu pour le Footer', 'woo-total-menu' ); ?></strong></label>
                                        <select name="header_footer[footer_menu_id]" id="hf_footer_menu_id">
                                                <option value="0"><?php esc_html_e( '\xE2\x80\x94 Aucun (conserver le th\xE8me) \xE2\x80\x94', 'woo-total-menu' ); ?></option>
                                                <?php foreach ( $menus as $menu ) : ?>
                                                        <option value="<?php echo esc_attr( $menu->ID ); ?>" <?php selected( absint( $hf['footer_menu_id'] ?? 0 ), $menu->ID ); ?>>
                                                                <?php echo esc_html( $menu->post_title ); ?>
                                                        </option>
                                                <?php endforeach; ?>
                                        </select>
                                        <p class="description"><?php esc_html_e( 'S\xE9lectionnez un menu de type footer cr\xE9\xE9 dans le Builder.', 'woo-total-menu' ); ?></p>
                                </div>
                        </div>

                        <div class="wtm-grid" style="grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); margin-top:16px;">
                                <div class="wtm-form-row">
                                        <label for="hf_hide_theme_header">
                                                <input type="checkbox" name="header_footer[hide_theme_header]" id="hf_hide_theme_header" value="1" <?php checked( ! empty( $hf['hide_theme_header'] ) ); ?>>
                                                <?php esc_html_e( 'Masquer le header du th\xE8me', 'woo-total-menu' ); ?>
                                        </label>
                                        <p class="description"><?php esc_html_e( 'Supprime le header natif du th\xE8me quand un header WTM est actif.', 'woo-total-menu' ); ?></p>
                                </div>

                                <div class="wtm-form-row">
                                        <label for="hf_hide_theme_footer">
                                                <input type="checkbox" name="header_footer[hide_theme_footer]" id="hf_hide_theme_footer" value="1" <?php checked( ! empty( $hf['hide_theme_footer'] ) ); ?>>
                                                <?php esc_html_e( 'Masquer le footer du th\xE8me', 'woo-total-menu' ); ?>
                                        </label>
                                        <p class="description"><?php esc_html_e( 'Supprime le footer natif du th\xE8me quand un footer WTM est actif.', 'woo-total-menu' ); ?></p>
                                </div>
                        </div>
                </div>
                <?php
        }

        /**
         * Tab: Permissions.
         *
         * @param array $s Settings.
         * @return void
         */
        private static function render_tab_permissions( $s ) {
                $p = $s['permissions'] ?? array();
                $roles = wp_roles()->roles;
                $cap_map = array(
                        'wtm_manage_menus'      => __( 'Gérer les menus', 'woo-total-menu' ),
                        'wtm_manage_templates'  => __( 'Gérer les templates', 'woo-total-menu' ),
                        'wtm_view_analytics'    => __( 'Voir les analytics', 'woo-total-menu' ),
                        'wtm_manage_settings'   => __( 'Gérer les réglages', 'woo-total-menu' ),
                );
                ?>
                <div class="wtm-form-section">
                        <h3><span class="dashicons dashicons-shield-alt"></span> <?php esc_html_e( 'Permissions par rôle', 'woo-total-menu' ); ?></h3>
                        <p style="color:#6b7280; margin-top:0;">
                                <?php esc_html_e( 'Cochez les capacités que chaque rôle peut utiliser. Les administrateurs ont toujours toutes les capacités.', 'woo-total-menu' ); ?>
                        </p>

                        <table class="wtm-table" style="margin-top:12px;">
                                <thead>
                                        <tr>
                                                <th><?php esc_html_e( 'Rôle', 'woo-total-menu' ); ?></th>
                                                <?php foreach ( $cap_map as $cap => $label ) : ?>
                                                        <th style="text-align:center;"><?php echo esc_html( $label ); ?></th>
                                                <?php endforeach; ?>
                                        </tr>
                                </thead>
                                <tbody>
                                        <?php foreach ( $roles as $role_slug => $role_info ) : ?>
                                                <?php
                                                $role = get_role( $role_slug );
                                                if ( ! $role ) {
                                                        continue;
                                                }
                                                ?>
                                                <tr>
                                                        <td>
                                                                <strong><?php echo esc_html( $role_info['name'] ); ?></strong>
                                                                <br><span style="color:#9ca3af; font-size:11px;"><?php echo esc_html( $role_slug ); ?></span>
                                                        </td>
                                                        <?php foreach ( $cap_map as $cap => $label ) : ?>
                                                                <td style="text-align:center;">
                                                                        <input type="checkbox" name="permissions[<?php echo esc_attr( $role_slug ); ?>][<?php echo esc_attr( $cap ); ?>]" value="1" <?php checked( $role->has_cap( $cap ) ); ?> <?php disabled( 'administrator' === $role_slug ); ?>>
                                                                </td>
                                                        <?php endforeach; ?>
                                                </tr>
                                        <?php endforeach; ?>
                                </tbody>
                        </table>
                        <p class="description" style="margin-top:12px;">
                                <?php esc_html_e( 'Les modifications sont appliquées immédiatement après sauvegarde.', 'woo-total-menu' ); ?>
                        </p>
                </div>
                <?php
        }

        /**
         * Save settings handler.
         *
         * @return void
         */
        private static function save_settings() {
                // Verify nonce.
                if ( ! isset( $_POST['wtm_settings_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['wtm_settings_nonce'] ), 'wtm_save_settings' ) ) {
                        wp_die( esc_html__( 'Invalid nonce.', 'woo-total-menu' ) );
                }

                // Check capability.
                if ( ! current_user_can( 'wtm_manage_settings' ) ) {
                        wp_die( esc_html__( 'You do not have permission to manage settings.', 'woo-total-menu' ), 403 );
                }

                // Get current settings.
                $settings = get_option( self::OPTION_KEY );
                if ( ! is_array( $settings ) ) {
                        $settings = \WooTotalMenu\Bootstrap::default_settings();
                }

                // Update each section.
                // General.
                $general_input = isset( $_POST['general'] ) ? (array) wp_unslash( $_POST['general'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
                $settings['general']['enabled']          = isset( $general_input['enabled'] );
                $settings['general']['default_location'] = sanitize_key( $general_input['default_location'] ?? 'primary' );

                // Styles.
                $styles_input = isset( $_POST['styles'] ) ? (array) wp_unslash( $_POST['styles'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
                $settings['styles']['primary_color'] = sanitize_hex_color( $styles_input['primary_color'] ?? '#6C5CE7' ) ?: '#6C5CE7';
                $settings['styles']['background']    = sanitize_hex_color( $styles_input['background'] ?? '#FFFFFF' ) ?: '#FFFFFF';
                $settings['styles']['text_color']    = sanitize_hex_color( $styles_input['text_color'] ?? '#2D3436' ) ?: '#2D3436';
                $settings['styles']['success_color'] = sanitize_hex_color( $styles_input['success_color'] ?? '#00B894' ) ?: '#00B894';
                $settings['styles']['error_color']   = sanitize_hex_color( $styles_input['error_color'] ?? '#FF7675' ) ?: '#FF7675';
                $settings['styles']['border_radius'] = absint( $styles_input['border_radius'] ?? 6 );

                // Typography.
                $typo_input = isset( $_POST['typography'] ) ? (array) wp_unslash( $_POST['typography'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
                $settings['typography']['font_family']   = sanitize_text_field( $typo_input['font_family'] ?? 'Inter' );
                $settings['typography']['base_size']     = absint( $typo_input['base_size'] ?? 14 );
                $settings['typography']['heading_size']  = absint( $typo_input['heading_size'] ?? 18 );

                // Responsive.
                $responsive_input = isset( $_POST['responsive'] ) ? (array) wp_unslash( $_POST['responsive'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
                $settings['responsive']['mobile_breakpoint']  = absint( $responsive_input['mobile_breakpoint'] ?? 768 );
                $settings['responsive']['tablet_breakpoint']  = absint( $responsive_input['tablet_breakpoint'] ?? 1024 );
                $settings['responsive']['mobile_behavior']    = in_array( $responsive_input['mobile_behavior'] ?? 'offcanvas', array( 'offcanvas', 'accordion', 'dropdown' ), true ) ? $responsive_input['mobile_behavior'] : 'offcanvas';
                $settings['responsive']['hamburger_position'] = in_array( $responsive_input['hamburger_position'] ?? 'right', array( 'left', 'right' ), true ) ? $responsive_input['hamburger_position'] : 'right';

                // Performance.
                $perf_input = isset( $_POST['performance'] ) ? (array) wp_unslash( $_POST['performance'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
                $settings['performance']['cache_enabled']      = isset( $perf_input['cache_enabled'] );
                $settings['performance']['lazy_load_widgets']  = isset( $perf_input['lazy_load_widgets'] );
                $settings['performance']['minify_css']         = isset( $perf_input['minify_css'] );

                // Analytics (saved but not yet active — see v1.7.1).
                $analytics_input = isset( $_POST['analytics'] ) ? (array) wp_unslash( $_POST['analytics'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
                $settings['analytics']['enabled']      = isset( $analytics_input['enabled'] );
                $settings['analytics']['track_logged'] = isset( $analytics_input['track_logged'] );

                // Header/Footer injection settings.
                $hf_input = isset( $_POST['header_footer'] ) ? (array) wp_unslash( $_POST['header_footer'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
                $settings['header_footer']['enabled']           = isset( $hf_input['enabled'] );
                $settings['header_footer']['header_menu_id']    = absint( $hf_input['header_menu_id'] ?? 0 );
                $settings['header_footer']['footer_menu_id']    = absint( $hf_input['footer_menu_id'] ?? 0 );
                $settings['header_footer']['hide_theme_header'] = isset( $hf_input['hide_theme_header'] );
                $settings['header_footer']['hide_theme_footer'] = isset( $hf_input['hide_theme_footer'] );

                // Permissions — apply caps to roles.
                $perms_input = isset( $_POST['permissions'] ) ? (array) wp_unslash( $_POST['permissions'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
                $all_caps = array_keys( array(
                        'wtm_manage_menus'      => 1,
                        'wtm_manage_templates'  => 1,
                        'wtm_view_analytics'    => 1,
                        'wtm_manage_settings'   => 1,
                ) );

                foreach ( wp_roles()->roles as $role_slug => $role_info ) {
                        $role = get_role( $role_slug );
                        if ( ! $role ) {
                                continue;
                        }
                        foreach ( $all_caps as $cap ) {
                                $has = isset( $perms_input[ $role_slug ][ $cap ] );
                                if ( 'administrator' === $role_slug ) {
                                        $role->add_cap( $cap ); // Always.
                                        continue;
                                }
                                if ( $has ) {
                                        $role->add_cap( $cap );
                                } else {
                                        $role->remove_cap( $cap );
                                }
                        }

                        // Sync CPT primitive caps with the wtm_manage_menus high-level cap.
                        // If the role has wtm_manage_menus, grant all CPT primitive caps;
                        // otherwise remove them so the REST endpoints work correctly.
                        $has_menu_cap = isset( $perms_input[ $role_slug ]['wtm_manage_menus'] );
                        if ( 'administrator' === $role_slug ) {
                                $has_menu_cap = true;
                        }
                        $cpt_caps = \WooTotalMenu\Core\Permissions::CPT_PRIMITIVE_CAPS;
                        foreach ( $cpt_caps as $cpt_cap ) {
                                if ( $has_menu_cap ) {
                                        $role->add_cap( $cpt_cap );
                                } else {
                                        $role->remove_cap( $cpt_cap );
                                }
                        }
                }

                // Update version metadata.
                $settings['version']     = WTM_VERSION;
                $settings['db_version']  = WTM_DB_VERSION;

                // Save.
                update_option( self::OPTION_KEY, $settings );

                /**
                 * Fires after global settings are saved.
                 *
                 * @since 1.0.0
                 * @param array $settings The full settings array.
                 */
                do_action( 'wtm_settings_saved', $settings );

                // Redirect to avoid resubmission.
                $active_tab = isset( $_POST['wtm_active_tab'] ) ? sanitize_key( wp_unslash( $_POST['wtm_active_tab'] ) ) : 'general';
                wp_safe_redirect(
                        add_query_arg(
                                array(
                                        'page'                  => 'wtm-settings',
                                        'tab'                   => $active_tab,
                                        'wtm_settings_saved'    => 1,
                                ),
                                admin_url( 'admin.php' )
                        )
                );
                exit;
        }
}
