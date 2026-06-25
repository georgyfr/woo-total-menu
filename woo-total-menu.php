<?php
/**
 * Plugin Name:       Woo Total Menu
 * Plugin URI:        https://github.com/woo-total-menu/woo-total-menu
 * Description:       Créez des méga menus, headers et footers WooCommerce avancés via un builder visuel glisser-déposer. Rendu responsive, performant, sans dépendance à un page builder.
 * Version:           1.4.0
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Author:            Woo Total Menu Team
 * Author URI:        https://github.com/woo-total-menu
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       woo-total-menu
 * Domain Path:       /languages
 *
 * @package WooTotalMenu
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

/**
 * Plugin constants.
 */
define( 'WTM_VERSION', '1.4.0' );
define( 'WTM_PLUGIN_FILE', __FILE__ );
define( 'WTM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WTM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WTM_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'WTM_DB_VERSION', 1 );
define( 'WTM_REST_NAMESPACE', 'wtm/v1' );
define( 'WTM_CPT_MENU', 'wtm_menu' );
define( 'WTM_OPTION_SETTINGS', 'wtm_global_settings' );
define( 'WTM_OPTION_TEMPLATES', 'wtm_user_templates' );
define( 'WTM_OPTION_DB_VERSION', 'wtm_db_version' );

/**
 * PSR-4 autoloader.
 *
 * @param string $class Class name.
 */
function wtm_autoload( $class ) {
        $prefix = 'WooTotalMenu\\';
        $len    = strlen( $prefix );
        if ( strncmp( $prefix, $class, $len ) !== 0 ) {
                return;
        }
        $relative = substr( $class, $len );
        $relative = str_replace( '\\', '/', $relative );
        $file     = WTM_PLUGIN_DIR . 'src/' . $relative . '.php';
        if ( file_exists( $file ) ) {
                require $file;
        }
}
spl_autoload_register( 'wtm_autoload' );

/**
 * Retrieve the singleton Bootstrap instance.
 *
 * @return WooTotalMenu\Bootstrap
 */
function wtm() {
        static $instance = null;
        if ( null === $instance ) {
                $instance = new \WooTotalMenu\Bootstrap();
        }
        return $instance;
}

require_once WTM_PLUGIN_DIR . 'src/Bootstrap.php';

// Initialize on plugins_loaded.
add_action( 'plugins_loaded', [ wtm(), 'boot' ], 5 );

// Activation / Deactivation hooks.
register_activation_hook( __FILE__, [ 'WooTotalMenu\\Bootstrap', 'on_activate' ] );
register_deactivation_hook( __FILE__, [ 'WooTotalMenu\\Bootstrap', 'on_deactivate' ] );
