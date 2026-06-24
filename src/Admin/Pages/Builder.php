<?php
/**
 * Builder page — full-screen React app for editing a menu.
 *
 * @package WooTotalMenu
 */

namespace WooTotalMenu\Admin\Pages;

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

/**
 * Class Builder
 *
 * Renders a full-screen container that mounts the React builder.
 * The actual menu_id (or 'new') is read from the query string.
 */
class Builder {

        const PAGE_SLUG = 'wtm-builder';

        /**
         * Render the page.
         *
         * @return void
         */
        public static function render() {
                // phpcs:disable WordPress.Security.NonceVerification.Recommended
                $menu_id = isset( $_GET['menu_id'] ) ? absint( $_GET['menu_id'] ) : 0;
                $is_new  = isset( $_GET['new'] ) ? (bool) $_GET['new'] : false;
                // phpcs:enable

                // Hide the WP admin bar and menu while in builder mode.
                echo '<style>
                        #wpadminbar { display: none !important; }
                        #adminmenuback, #adminmenuwrap { display: none !important; }
                        #wpcontent { margin-left: 0 !important; padding: 0 !important; }
                        #wpfooter { display: none !important; }
                        .wrap { padding: 0 !important; margin: 0 !important; }
                        #wtm-builder-root { position: fixed; top: 0; left: 0; right: 0; bottom: 0; z-index: 99999; }
                </style>';

                ?>
                <div id="wtm-builder-root" data-menu-id="<?php echo esc_attr( (string) $menu_id ); ?>" data-is-new="<?php echo esc_attr( $is_new ? '1' : '0' ); ?>"></div>
                <?php
        }
}
