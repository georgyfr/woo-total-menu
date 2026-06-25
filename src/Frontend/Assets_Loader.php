<?php
/**
 * Frontend assets loader.
 *
 * Spec §2.6.1: "Le plugin n'enqueue ses CSS/JS que lorsqu'un menu wtm_menu
 * est actif sur la page. Pour le frontend, un fichier CSS minifié (~15 Ko)
 * est chargé. Le JavaScript frontend est optionnel et très léger (~5 Ko,
 * vanilla JS, pas de jQuery)."
 *
 * @package WooTotalMenu
 * @since 1.2.0
 */

namespace WooTotalMenu\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Assets_Loader
 *
 * Conditionally enqueues:
 *   - Dynamic CSS (global settings + per-menu)
 *   - Base frontend CSS (wtm-frontend.css)
 *   - Frontend JS (wtm-frontend.js) — only when a menu is rendered
 *
 * Listens to `wtm_rendered_location` to know whether a menu was rendered.
 */
class Assets_Loader {

	/**
	 * Dynamic_CSS instance (injected).
	 *
	 * @var Dynamic_CSS
	 */
	private $dynamic_css;

	/**
	 * Whether at least one WTM menu was rendered on this request.
	 *
	 * @var bool
	 */
	private $has_rendered_menu = false;

	/**
	 * Constructor.
	 *
	 * @param Dynamic_CSS $dynamic_css Dynamic CSS generator.
	 */
	public function __construct( Dynamic_CSS $dynamic_css ) {
		$this->dynamic_css = $dynamic_css;

		// Listen for menus being rendered (via shortcode or location interceptor).
		add_action( 'wtm_rendered_location', array( $this, 'mark_rendered' ), 10, 2 );

		// Enqueue assets — late, after we know whether a menu was rendered.
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue' ), 100 );
	}

	/**
	 * Mark that a menu was rendered on this request.
	 *
	 * @param int    $menu_id  Menu post ID.
	 * @param string $location Location slug.
	 * @return void
	 */
	public function mark_rendered( $menu_id, $location ) {
		$this->has_rendered_menu = true;
	}

	/**
	 * Enqueue frontend assets if a WTM menu is active on the page.
	 *
	 * @return void
	 */
	public function maybe_enqueue() {
		/**
		 * Allow forcing asset loading even when no menu was rendered
		 * (useful for shortcodes in AJAX-loaded content).
		 *
		 * @since 1.2.0
		 *
		 * @param bool $force Whether to force enqueue.
		 */
		$force = (bool) apply_filters( 'wtm_force_enqueue_assets', false );

		if ( ! $this->has_rendered_menu && ! $force ) {
			return;
		}

		// 1. Dynamic CSS (per-site, regenerated on settings/menu save).
		$dynamic_url = $this->dynamic_css->get_url();
		if ( $dynamic_url ) {
			wp_enqueue_style(
				'wtm-dynamic',
				$dynamic_url,
				array(),
				null // Cache-busting hash already in URL.
			);
		}

		// 2. Base frontend CSS.
		wp_enqueue_style(
			'wtm-frontend',
			WTM_PLUGIN_URL . 'assets/front/wtm-frontend.css',
			array( 'wtm-dynamic' ),
			WTM_VERSION
		);

		// 3. Frontend JS (vanilla, no jQuery — spec §2.6.1).
		wp_enqueue_script(
			'wtm-frontend',
			WTM_PLUGIN_URL . 'assets/front/wtm-frontend.js',
			array(),
			WTM_VERSION,
			true // In footer.
		);

		// Localize script with breakpoint + i18n strings.
		$settings    = get_option( WTM_OPTION_SETTINGS );
		$responsive  = is_array( $settings ) ? ( $settings['responsive'] ?? array() ) : array();
		$breakpoint  = (int) ( $responsive['mobile_breakpoint'] ?? 768 );
		$mobile_bhvr = $responsive['mobile_behavior'] ?? 'offcanvas';

		wp_localize_script(
			'wtm-frontend',
			'wtmFrontend',
			array(
				'breakpoint'       => $breakpoint,
				'mobileBehavior'   => $mobile_bhvr,
				'i18n'             => array(
					'openMenu'  => __( 'Ouvrir le menu', 'woo-total-menu' ),
					'closeMenu' => __( 'Fermer le menu', 'woo-total-menu' ),
					'openSub'   => __( 'Ouvrir le sous-menu', 'woo-total-menu' ),
					'closeSub'  => __( 'Fermer le sous-menu', 'woo-total-menu' ),
				),
				'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
				'wooCartFragments' => class_exists( 'WooCommerce' ) ? 'yes' : 'no',
			)
		);
	}
}
