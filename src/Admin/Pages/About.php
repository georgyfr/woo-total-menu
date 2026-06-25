<?php
/**
 * About page.
 *
 * @package WooTotalMenu
 */

namespace WooTotalMenu\Admin\Pages;

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

/**
 * Class About
 *
 * Renders the "About / Getting Started" page as a submenu of
 * Woo Total Menu. Uses the shared admin styles provided by Admin_Menu.
 */
class About {

        /**
         * Render the page (called by Admin_Menu).
         *
         * @return void
         */
        public static function render() {
                $roadmap = array(
                        array( 'v1.0.0', 'Squelette du plugin (Bootstrap, Cache, Permissions, page About)', 'done' ),
                        array( 'v1.0.1', 'Custom Post Type wtm_menu + méta-boxes + 4 locations', 'done' ),
                        array( 'v1.0.2', 'Pages admin (Dashboard, Liste des menus, Réglages globaux)', 'done' ),
                        array( 'v1.0.3', 'API REST CRUD /wtm/v1/menus', 'done' ),
                        array( 'v1.0.4', 'Schéma JSON de configuration + validateur', 'done' ),
                        array( 'v1.1.0', 'Builder visuel React — squelette (layout 3 colonnes, stores @wordpress/data)', 'done' ),
                        array( 'v1.1.1', 'CRUD items du builder (ajout, suppression, renommage, édition propriétés)', 'done' ),
                        array( 'v1.1.2', 'Drag & drop arborescent + Undo/Redo + raccourcis clavier + a11y ARIA', 'done' ),
                        array( 'v1.1.3', 'Fixes UX : indicateur drop temps réel + migration React 18 + ARIA propre', 'done' ),
                        array( 'v1.1.4', 'Live preview via iframe + postMessage', 'done' ),
                        array( 'v1.1.5', 'Undo/Redo révisions WordPress + historique', 'done' ),
                        array( 'v1.2.x', 'Rendu frontend (Menu_Walker, méga menu, off-canvas mobile)', 'done' ),
                        array( 'v1.3.x', 'Widgets WooCommerce avancés (recent_posts, social_icons, newsletter, filters, mini_cart drawer, search live)', 'done' ),
                        array( 'v1.4.x', 'Header & Footer Builder', 'todo' ),
                        array( 'v1.5.x', 'Système de templates (12+ templates intégrés)', 'todo' ),
                        array( 'v1.6.x', 'Rôles, blocs Gutenberg, compatibilité Elementor/Bricks/Oxygen, multisite', 'todo' ),
                        array( 'v1.7.x', 'Menus conditionnels, analytics simple', 'todo' ),
                );
                ?>
                <div class="wrap wtm-page">
                        <div style="background: linear-gradient(135deg, #6C5CE7, #8E7CF5); color:#fff; padding:32px; border-radius:8px; margin:16px 0;">
                                <h1 style="color:#fff; margin:0 0 8px; display:flex; align-items:center; gap:10px;">
                                        <span class="dashicons dashicons-menu" style="font-size:32px; width:32px; height:32px;"></span>
                                        <?php esc_html_e( 'Woo Total Menu', 'woo-total-menu' ); ?>
                                        <span style="font-size:12px; background:rgba(255,255,255,0.25); padding:2px 10px; border-radius:12px; margin-left:8px;">
                                                v<?php echo esc_html( WTM_VERSION ); ?>
                                        </span>
                                </h1>
                                <p style="margin:0; opacity:0.95; font-size:14px;">
                                        <?php esc_html_e( 'Créez des méga menus, headers et footers WooCommerce avancés via un builder visuel glisser-déposer.', 'woo-total-menu' ); ?>
                                </p>
                        </div>

                        <div class="wtm-grid">
                                <div class="wtm-card">
                                        <h3><span class="dashicons dashicons-info"></span> <?php esc_html_e( 'À propos de cette version', 'woo-total-menu' ); ?></h3>
                                        <p>
                                                <?php
                                                printf(
                                                        /* translators: %s version number */
                                                        esc_html__( 'Vous utilisez la version %s. Voici ce qui est disponible actuellement :', 'woo-total-menu' ),
                                                        '<strong>v' . esc_html( WTM_VERSION ) . '</strong>'
                                                );
                                                ?>
                                        </p>
                                        <ul style="margin-left:18px; padding-left:0;">
                                                <li><?php esc_html_e( 'Squelette PHP du plugin et autoloader PSR-4', 'woo-total-menu' ); ?></li>
                                                <li><?php esc_html_e( 'Système de permissions (4 capacités personnalisées)', 'woo-total-menu' ); ?></li>
                                                <li><?php esc_html_e( 'Gestionnaire de cache (objet + transients)', 'woo-total-menu' ); ?></li>
                                                <li><?php esc_html_e( 'Custom Post Type wtm_menu + 6 méta-keys + méta-boxes', 'woo-total-menu' ); ?></li>
                                                <li><?php esc_html_e( '4 types de menus et 4 emplacements enregistrés', 'woo-total-menu' ); ?></li>
                                                <li><?php esc_html_e( 'Tableau de bord avec statistiques', 'woo-total-menu' ); ?></li>
                                                <li><?php esc_html_e( 'Liste des menus avec filtres et actions', 'woo-total-menu' ); ?></li>
                                                <li><?php esc_html_e( 'Réglages globaux (7 onglets : général, styles, typo, responsive, performance, analytics, permissions)', 'woo-total-menu' ); ?></li>
                                                <li><?php esc_html_e( 'Actions : créer / modifier / dupliquer / activer / supprimer un menu', 'woo-total-menu' ); ?></li>
                                        </ul>
                                </div>

                                <div class="wtm-card">
                                        <h3><span class="dashicons dashicons-clock"></span> <?php esc_html_e( 'Roadmap', 'woo-total-menu' ); ?></h3>
                                        <ol style="margin-left:18px; padding-left:0;">
                                                <?php foreach ( $roadmap as $item ) : ?>
                                                        <?php
                                                        list( $version, $desc, $state ) = $item;
                                                        $icon_class = 'dashicons-minus';
                                                        $color      = '#9ca3af';
                                                        if ( 'done' === $state ) {
                                                                $icon_class = 'dashicons-yes-alt';
                                                                $color      = '#00B894';
                                                        } elseif ( 'current' === $state ) {
                                                                $icon_class = 'dashicons-arrow-right-alt2';
                                                                $color      = '#6C5CE7';
                                                        }
                                                        ?>
                                                        <li style="margin:6px 0; display:flex; align-items:flex-start; gap:8px;">
                                                                <span class="dashicons <?php echo esc_attr( $icon_class ); ?>" style="color:<?php echo esc_attr( $color ); ?>; font-size:16px; width:16px; height:16px; margin-top:2px;"></span>
                                                                <span>
                                                                        <strong><?php echo esc_html( $version ); ?></strong> —
                                                                        <?php echo esc_html( $desc ); ?>
                                                                </span>
                                                        </li>
                                                <?php endforeach; ?>
                                        </ol>
                                </div>

                                <div class="wtm-card">
                                        <h3><span class="dashicons dashicons-admin-tools"></span> <?php esc_html_e( 'Environnement', 'woo-total-menu' ); ?></h3>
                                        <table class="wtm-table">
                                                <tbody>
                                                        <tr><th style="text-align:left; padding:6px 0; color:#6b7280; width:35%;">PHP</th><td style="padding:6px 0; font-family:monospace;"><?php echo esc_html( PHP_VERSION ); ?></td></tr>
                                                        <tr><th style="text-align:left; padding:6px 0; color:#6b7280;">WordPress</th><td style="padding:6px 0; font-family:monospace;"><?php echo esc_html( get_bloginfo( 'version' ) ); ?></td></tr>
                                                        <tr><th style="text-align:left; padding:6px 0; color:#6b7280;">WooCommerce</th><td style="padding:6px 0; font-family:monospace;"><?php echo class_exists( 'WooCommerce' ) ? esc_html( WC()->version ) : '—'; ?></td></tr>
                                                        <tr><th style="text-align:left; padding:6px 0; color:#6b7280;">Thème actif</th><td style="padding:6px 0; font-family:monospace;"><?php echo esc_html( wp_get_theme()->get( 'Name' ) ); ?></td></tr>
                                                        <tr><th style="text-align:left; padding:6px 0; color:#6b7280;">DB Version</th><td style="padding:6px 0; font-family:monospace;"><?php echo esc_html( (string) get_option( WTM_OPTION_DB_VERSION ) ); ?></td></tr>
                                                </tbody>
                                        </table>
                                </div>
                        </div>

                        <div class="wtm-card" style="margin-top:16px;">
                                <h3><span class="dashicons dashicons-book"></span> <?php esc_html_e( 'Liens utiles', 'woo-total-menu' ); ?></h3>
                                <p>
                                        <a href="https://github.com/georgyfr/woo-total-menu" target="_blank" class="wtm-btn is-secondary">
                                                <span class="dashicons dashicons-mark-github"></span>
                                                <?php esc_html_e( 'Code source sur GitHub', 'woo-total-menu' ); ?>
                                        </a>
                                        <a href="https://github.com/georgyfr/woo-total-menu/releases" target="_blank" class="wtm-btn is-secondary">
                                                <span class="dashicons dashicons-tag"></span>
                                                <?php esc_html_e( 'Toutes les releases', 'woo-total-menu' ); ?>
                                        </a>
                                        <a href="https://github.com/georgyfr/woo-total-menu/blob/main/CHANGELOG.md" target="_blank" class="wtm-btn is-secondary">
                                                <span class="dashicons dashicons-list-view"></span>
                                                <?php esc_html_e( 'Changelog complet', 'woo-total-menu' ); ?>
                                        </a>
                                </p>
                        </div>
                </div>
                <?php
        }
}
