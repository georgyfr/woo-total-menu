<?php
/**
 * Frontend shortcode.
 *
 * Spec §2.8.2: "Un shortcode [wtm_menu id="123"] permet d'insérer un menu
 * n'importe où, y compris dans des pages Elementor."
 *
 * @package WooTotalMenu
 * @since 1.2.0
 */

namespace WooTotalMenu\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Shortcode
 *
 * Registers the [wtm_menu] shortcode and renders menus via Menu_Renderer.
 */
class Shortcode {

	/**
	 * Menu_Renderer instance (injected).
	 *
	 * @var Menu_Renderer
	 */
	private $renderer;

	/**
	 * Track rendered menu IDs for asset-enqueue decisions.
	 *
	 * @var array<int,int>
	 */
	private $rendered_menu_ids = array();

	/**
	 * Constructor.
	 *
	 * @param Menu_Renderer $renderer Menu renderer.
	 */
	public function __construct( Menu_Renderer $renderer ) {
		$this->renderer = $renderer;
		add_shortcode( 'wtm_menu', array( $this, 'render_shortcode' ) );
	}

	/**
	 * [wtm_menu] shortcode callback.
	 *
	 * Attributes:
	 *   - id       (int)    Menu post ID. Either this or `location` is required.
	 *   - location (string) WTM location slug. Falls back to assigned menu.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public function render_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'id'       => 0,
				'location' => '',
			),
			$atts,
			'wtm_menu'
		);

		$menu_id  = (int) $atts['id'];
		$location = sanitize_key( (string) $atts['location'] );

		if ( $menu_id <= 0 && ! $location ) {
			return '';
		}

		// If location given but no id, find the published menu for that location.
		if ( $menu_id <= 0 && $location ) {
			$menus = get_posts(
				array(
					'post_type'      => 'wtm_menu',
					'post_status'    => 'publish',
					'meta_key'       => '_wtm_location', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
					'meta_value'     => $location, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
					'posts_per_page' => 1,
					'fields'         => 'ids',
					'no_found_rows'  => true,
				)
			);
			if ( empty( $menus ) ) {
				return '';
			}
			$menu_id = (int) $menus[0];
		}

		$html = $this->renderer->render_by_id( $menu_id, $location );
		if ( '' === $html ) {
			return '';
		}

		$this->rendered_menu_ids[] = $menu_id;

		/**
		 * Fires when a WTM menu is rendered via shortcode.
		 *
		 * @since 1.2.0
		 *
		 * @param int    $menu_id  Post ID.
		 * @param string $location Location slug.
		 */
		do_action( 'wtm_rendered_location', $menu_id, $location );

		return $html;
	}

	/**
	 * Get the list of menu IDs rendered via shortcode on this request.
	 *
	 * @return array<int,int>
	 */
	public function get_rendered_menu_ids() {
		return $this->rendered_menu_ids;
	}
}
