<?php
/**
 * Permissions & capabilities manager.
 *
 * @package WooTotalMenu
 */

namespace WooTotalMenu\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Permissions
 *
 * Registers custom capabilities and provides helpers for permission checks.
 */
class Permissions {

	/**
	 * List of custom capabilities managed by the plugin.
	 *
	 * @var array<string,string>
	 */
	const CAPABILITIES = array(
		'wtm_manage_menus'      => 'manage_options',
		'wtm_manage_templates'  => 'manage_options',
		'wtm_view_analytics'    => 'manage_options',
		'wtm_manage_settings'   => 'manage_options',
	);

	/**
	 * Add capabilities to roles on activation.
	 *
	 * @return void
	 */
	public function register_caps() {
		foreach ( self::CAPABILITIES as $cap => $from ) {
			$roles = wp_roles()->roles;
			foreach ( $roles as $role_name => $role_info ) {
				$role = get_role( $role_name );
				if ( $role && isset( $role_info['capabilities'][ $from ] ) && $role_info['capabilities'][ $from ] ) {
					$role->add_cap( $cap );
				}
			}
		}
	}

	/**
	 * Remove capabilities from roles on uninstall.
	 *
	 * @return void
	 */
	public function remove_caps() {
		foreach ( self::CAPABILITIES as $cap => $from ) {
			$roles = wp_roles()->roles;
			foreach ( array_keys( $roles ) as $role_name ) {
				$role = get_role( $role_name );
				if ( $role ) {
					$role->remove_cap( $cap );
				}
			}
		}
	}

	/**
	 * Check whether the current user has a capability.
	 *
	 * @param string $cap Capability slug.
	 * @return bool
	 */
	public function user_can( $cap ) {
		return current_user_can( $cap );
	}

	/**
	 * Ensure the current user can manage menus, die otherwise.
	 *
	 * @return void
	 */
	public function assert_can_manage_menus() {
		if ( ! $this->user_can( 'wtm_manage_menus' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage Woo Total Menu.', 'woo-total-menu' ), 403 );
		}
	}
}
