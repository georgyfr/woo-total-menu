<?php
/**
 * Frontend assets loader.
 *
 * @package WooTotalMenu
 */

namespace WooTotalMenu\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Assets_Loader
 *
 * Responsible for enqueuing frontend CSS/JS only when a WTM menu
 * is actually used on the current page.
 *
 * In v1.0.0, this is a stub — real asset loading will come in v1.2.x.
 */
class Assets_Loader {

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Hooked late so menus are registered first.
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue' ), 20 );
	}

	/**
	 * Enqueue frontend assets conditionally.
	 *
	 * @return void
	 */
	public function maybe_enqueue() {
		// Stub for v1.0.0 — will check if any WTM menu is assigned to a location
		// before loading assets. No-op for now.
	}
}
