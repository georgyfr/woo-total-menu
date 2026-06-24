<?php
/**
 * Cache manager.
 *
 * @package WooTotalMenu
 */

namespace WooTotalMenu\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Cache_Manager
 *
 * Thin wrapper around WordPress object cache and transients,
 * with a unified API for the rest of the plugin.
 */
class Cache_Manager {

	const GROUP = 'wtm';

	/**
	 * Get a cached value.
	 *
	 * @param string $key Cache key.
	 * @return mixed|null
	 */
	public function get( $key ) {
		$value = wp_cache_get( $key, self::GROUP );
		return false === $value ? null : $value;
	}

	/**
	 * Store a value in cache.
	 *
	 * @param string $key     Cache key.
	 * @param mixed  $value   Value.
	 * @param int    $expires TTL in seconds (0 = no expiration).
	 * @return bool
	 */
	public function set( $key, $value, $expires = 0 ) {
		return wp_cache_set( $key, $value, self::GROUP, $expires );
	}

	/**
	 * Delete a cached value.
	 *
	 * @param string $key Cache key.
	 * @return bool
	 */
	public function delete( $key ) {
		return wp_cache_delete( $key, self::GROUP );
	}

	/**
	 * Get a transient (persistent across requests).
	 *
	 * @param string $key Transient key (will be prefixed).
	 * @return mixed|null
	 */
	public function get_transient( $key ) {
		$value = get_transient( 'wtm_' . $key );
		return false === $value ? null : $value;
	}

	/**
	 * Store a transient.
	 *
	 * @param string $key      Transient key.
	 * @param mixed  $value    Value.
	 * @param int    $duration Duration in seconds.
	 * @return bool
	 */
	public function set_transient( $key, $value, $duration = 3600 ) {
		return set_transient( 'wtm_' . $key, $value, $duration );
	}

	/**
	 * Delete a transient.
	 *
	 * @param string $key Transient key.
	 * @return bool
	 */
	public function delete_transient( $key ) {
		return delete_transient( 'wtm_' . $key );
	}

	/**
	 * Invalidate all caches related to a specific menu.
	 *
	 * @param int $menu_id Menu post ID.
	 * @return void
	 */
	public function invalidate_menu( $menu_id ) {
		$this->delete( "menu_config_{$menu_id}" );
		$this->delete_transient( "menu_config_{$menu_id}" );
		$this->delete_transient( "menu_html_{$menu_id}" );
		$this->delete( "menu_css_{$menu_id}" );
	}
}
