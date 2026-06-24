<?php
/**
 * Menus list page.
 *
 * @package WooTotalMenu
 */

namespace WooTotalMenu\Admin\Pages;

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

use WooTotalMenu\Admin\Admin_Menu;
use WooTotalMenu\Core\CPT_Manager;

/**
 * Class Menus_List
 *
 * Shows all wtm_menu posts in a table with bulk actions,
 * filters by type/location/status, and quick actions.
 */
class Menus_List {

        /**
         * Render the page.
         *
         * @return void
         */
        public static function render() {
                // Filters (from query string).
                // phpcs:disable WordPress.Security.NonceVerification.Recommended
                $filter_type   = isset( $_GET['filter_type'] ) ? sanitize_key( wp_unslash( $_GET['filter_type'] ) ) : '';
                $filter_status = isset( $_GET['filter_status'] ) ? sanitize_key( wp_unslash( $_GET['filter_status'] ) ) : '';
                $search        = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
                // phpcs:enable

                // Build query.
                $args = array(
                        'post_type'      => WTM_CPT_MENU,
                        'posts_per_page' => -1,
                        'orderby'        => 'date',
                        'order'          => 'DESC',
                );
                if ( '' !== $filter_status ) {
                        $args['post_status'] = $filter_status;
                } else {
                        $args['post_status'] = 'any';
                }
                if ( '' !== $search ) {
                        $args['s'] = $search;
                }

                $all_menus = get_posts( $args );

                // Filter by type (post_meta) — done in PHP since WP_Query meta_query would be heavier.
                if ( '' !== $filter_type ) {
                        $all_menus = array_filter(
                                $all_menus,
                                function ( $m ) use ( $filter_type ) {
                                        return ( get_post_meta( $m->ID, '_wtm_menu_type', true ) ?: 'horizontal' ) === $filter_type;
                                }
                        );
                }

                $types     = CPT_Manager::get_menu_types();
                $locations = CPT_Manager::get_locations();

                $create_url = Admin_Menu::action_url(
                        'create_menu',
                        array(
                                'menu_type'  => 'horizontal',
                                'location'   => 'primary',
                                'menu_title' => __( 'Nouveau menu', 'woo-total-menu' ),
                        )
                );

                // Notices.
                self::render_notices();
                ?>
                <div class="wrap wtm-page">
                        <h1><span class="dashicons dashicons-list-view"></span> <?php esc_html_e( 'Menus', 'woo-total-menu' ); ?></h1>
                        <p class="wtm-page-subtitle">
                                <?php
                                printf(
                                        /* translators: %d number of menus */
                                        esc_html__( 'Tous vos menus, headers et footers (%d au total).', 'woo-total-menu' ),
                                        count( $all_menus )
                                );
                                ?>
                        </p>

                        <p style="margin-bottom:16px;">
                                <a href="<?php echo esc_url( $create_url ); ?>" class="wtm-btn">
                                        <span class="dashicons dashicons-plus-alt2"></span>
                                        <?php esc_html_e( 'Créer un nouveau menu', 'woo-total-menu' ); ?>
                                </a>
                        </p>

                        <!-- Filters -->
                        <form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" style="background:#fff; padding:12px 16px; border:1px solid #e5e7eb; border-radius:8px; margin-bottom:16px; display:flex; gap:12px; align-items:flex-end; flex-wrap:wrap;">
                                <input type="hidden" name="page" value="wtm-menus">

                                <div class="wtm-form-row" style="margin:0;">
                                        <label for="filter_type" style="font-size:12px;"><?php esc_html_e( 'Type', 'woo-total-menu' ); ?></label>
                                        <select name="filter_type" id="filter_type" style="width:auto;">
                                                <option value=""><?php esc_html_e( 'Tous les types', 'woo-total-menu' ); ?></option>
                                                <?php foreach ( $types as $slug => $label ) : ?>
                                                        <option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $filter_type, $slug ); ?>>
                                                                <?php echo esc_html( $label ); ?>
                                                        </option>
                                                <?php endforeach; ?>
                                        </select>
                                </div>

                                <div class="wtm-form-row" style="margin:0;">
                                        <label for="filter_status" style="font-size:12px;"><?php esc_html_e( 'Statut', 'woo-total-menu' ); ?></label>
                                        <select name="filter_status" id="filter_status" style="width:auto;">
                                                <option value=""><?php esc_html_e( 'Tous les statuts', 'woo-total-menu' ); ?></option>
                                                <option value="publish" <?php selected( $filter_status, 'publish' ); ?>><?php esc_html_e( 'Actifs', 'woo-total-menu' ); ?></option>
                                                <option value="draft" <?php selected( $filter_status, 'draft' ); ?>><?php esc_html_e( 'Brouillons', 'woo-total-menu' ); ?></option>
                                        </select>
                                </div>

                                <div class="wtm-form-row" style="margin:0;">
                                        <label for="s" style="font-size:12px;"><?php esc_html_e( 'Recherche', 'woo-total-menu' ); ?></label>
                                        <input type="text" name="s" id="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Rechercher un menu…', 'woo-total-menu' ); ?>" style="width:220px;">
                                </div>

                                <button type="submit" class="wtm-btn is-secondary">
                                        <span class="dashicons dashicons-filter"></span>
                                        <?php esc_html_e( 'Filtrer', 'woo-total-menu' ); ?>
                                </button>
                                <?php if ( '' !== $filter_type || '' !== $filter_status || '' !== $search ) : ?>
                                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=wtm-menus' ) ); ?>" class="wtm-btn is-secondary">
                                                <?php esc_html_e( 'Réinitialiser', 'woo-total-menu' ); ?>
                                        </a>
                                <?php endif; ?>
                        </form>

                        <?php if ( empty( $all_menus ) ) : ?>
                                <div class="wtm-empty-state">
                                        <span class="dashicons dashicons-menu"></span>
                                        <h3><?php esc_html_e( 'Aucun menu pour l\'instant', 'woo-total-menu' ); ?></h3>
                                        <p><?php esc_html_e( 'Créez votre premier menu pour commencer.', 'woo-total-menu' ); ?></p>
                                        <a href="<?php echo esc_url( $create_url ); ?>" class="wtm-btn">
                                                <span class="dashicons dashicons-plus-alt2"></span>
                                                <?php esc_html_e( 'Créer mon premier menu', 'woo-total-menu' ); ?>
                                        </a>
                                </div>
                        <?php else : ?>
                                <table class="wtm-table">
                                        <thead>
                                                <tr>
                                                        <th><?php esc_html_e( 'Titre', 'woo-total-menu' ); ?></th>
                                                        <th><?php esc_html_e( 'Type', 'woo-total-menu' ); ?></th>
                                                        <th><?php esc_html_e( 'Emplacement', 'woo-total-menu' ); ?></th>
                                                        <th><?php esc_html_e( 'Statut', 'woo-total-menu' ); ?></th>
                                                        <th><?php esc_html_e( 'Créé le', 'woo-total-menu' ); ?></th>
                                                        <th><?php esc_html_e( 'Modifié le', 'woo-total-menu' ); ?></th>
                                                        <th style="text-align:right;"><?php esc_html_e( 'Actions', 'woo-total-menu' ); ?></th>
                                                </tr>
                                        </thead>
                                        <tbody>
                                                <?php foreach ( $all_menus as $menu ) : ?>
                                                        <?php
                                                        $type = get_post_meta( $menu->ID, '_wtm_menu_type', true ) ?: 'horizontal';
                                                        $loc  = get_post_meta( $menu->ID, '_wtm_location', true ) ?: 'primary';
                                                        $edit_url      = admin_url( 'post.php?post=' . $menu->ID . '&action=edit' );
                                                        $duplicate_url = Admin_Menu::action_url( 'duplicate_menu', array( 'menu_id' => $menu->ID ) );
                                                        $delete_url    = Admin_Menu::action_url( 'delete_menu', array( 'menu_id' => $menu->ID ) );
                                                        $toggle_url    = Admin_Menu::action_url( 'toggle_status', array( 'menu_id' => $menu->ID ) );
                                                        ?>
                                                        <tr>
                                                                <td>
                                                                        <strong><a href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html( $menu->post_title ); ?></a></strong>
                                                                        <br><span style="color:#9ca3af; font-size:11px;">ID: <?php echo esc_html( (string) $menu->ID ); ?></span>
                                                                </td>
                                                                <td><span class="wtm-badge is-<?php echo esc_attr( $type ); ?>"><?php echo esc_html( $types[ $type ] ?? $type ); ?></span></td>
                                                                <td><?php echo esc_html( $locations[ $loc ] ?? $loc ); ?></td>
                                                                <td>
                                                                        <?php if ( 'publish' === $menu->post_status ) : ?>
                                                                                <span class="wtm-badge is-active"><?php esc_html_e( 'Actif', 'woo-total-menu' ); ?></span>
                                                                        <?php else : ?>
                                                                                <span class="wtm-badge is-inactive"><?php esc_html_e( 'Inactif', 'woo-total-menu' ); ?></span>
                                                                        <?php endif; ?>
                                                                </td>
                                                                <td><?php echo esc_html( wp_date( 'd/m/Y H:i', strtotime( $menu->post_date ) ) ); ?></td>
                                                                <td><?php echo esc_html( wp_date( 'd/m/Y H:i', strtotime( $menu->post_modified ) ) ); ?></td>
                                                                <td style="text-align:right; white-space:nowrap;">
                                                                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=wtm-builder&menu_id=' . $menu->ID ) ); ?>" class="wtm-btn" title="<?php esc_attr_e( 'Ouvrir dans le Builder', 'woo-total-menu' ); ?>" style="background:#6C5CE7;color:#fff;">
                                                                                <span class="dashicons dashicons-admin-customizer"></span>
                                                                                <?php esc_html_e( 'Builder', 'woo-total-menu' ); ?>
                                                                        </a>
                                                                        <a href="<?php echo esc_url( $edit_url ); ?>" class="wtm-btn is-secondary" title="<?php esc_attr_e( 'Modifier (édition classique)', 'woo-total-menu' ); ?>">
                                                                                <span class="dashicons dashicons-edit"></span>
                                                                        </a>
                                                                        <a href="<?php echo esc_url( $toggle_url ); ?>" class="wtm-btn is-secondary" title="<?php echo 'publish' === $menu->post_status ? esc_attr__( 'Désactiver', 'woo-total-menu' ) : esc_attr__( 'Activer', 'woo-total-menu' ); ?>">
                                                                                <span class="dashicons dashicons-<?php echo 'publish' === $menu->post_status ? 'hidden' : 'visibility'; ?>"></span>
                                                                        </a>
                                                                        <a href="<?php echo esc_url( $duplicate_url ); ?>" class="wtm-btn is-secondary" title="<?php esc_attr_e( 'Dupliquer', 'woo-total-menu' ); ?>">
                                                                                <span class="dashicons dashicons-admin-page"></span>
                                                                        </a>
                                                                        <a href="<?php echo esc_url( $delete_url ); ?>" class="wtm-btn is-danger" title="<?php esc_attr_e( 'Supprimer', 'woo-total-menu' ); ?>" onclick="return confirm('<?php echo esc_js( sprintf( /* translators: %s menu title */ __( 'Supprimer définitivement le menu « %s » ?', 'woo-total-menu' ), $menu->post_title ) ); ?>');">
                                                                                <span class="dashicons dashicons-trash"></span>
                                                                        </a>
                                                                </td>
                                                        </tr>
                                                <?php endforeach; ?>
                                        </tbody>
                                </table>
                        <?php endif; ?>
                </div>
                <?php
        }

        /**
         * Render admin notices.
         *
         * @return void
         */
        private static function render_notices() {
                // phpcs:disable WordPress.Security.NonceVerification.Recommended
                if ( isset( $_GET['wtm_deleted'] ) ) {
                        echo '<div class="wtm-notice is-success"><p>' . esc_html__( 'Menu supprimé avec succès.', 'woo-total-menu' ) . '</p></div>';
                }
                if ( isset( $_GET['wtm_duplicated'] ) ) {
                        echo '<div class="wtm-notice is-success"><p>' . esc_html__( 'Menu dupliqué avec succès.', 'woo-total-menu' );'</p></div>';
                }
                if ( isset( $_GET['wtm_toggled'] ) ) {
                        $new_status = isset( $_GET['wtm_new_status'] ) ? sanitize_key( wp_unslash( $_GET['wtm_new_status'] ) ) : '';
                        $msg = ( 'publish' === $new_status )
                                ? __( 'Menu activé avec succès.', 'woo-total-menu' )
                                : __( 'Menu désactivé avec succès.', 'woo-total-menu' );
                        echo '<div class="wtm-notice is-success"><p>' . esc_html( $msg ) . '</p></div>';
                }
                // phpcs:enable
        }
}
