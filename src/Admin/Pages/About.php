<?php
/**
 * Admin "About" page — entry point of the plugin in WP admin.
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
 * Adds the main "Woo Total Menu" menu in the WP admin sidebar
 * and renders the "About / Getting Started" page.
 */
class About {

	const PAGE_SLUG = 'wtm-about';
	const CAPABILITY = 'wtm_manage_menus';

	/**
	 * Constructor — registers hooks.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_styles' ) );
	}

	/**
	 * Register the top-level admin menu.
	 *
	 * @return void
	 */
	public function register_menu() {
		add_menu_page(
			__( 'Woo Total Menu', 'woo-total-menu' ),
			__( 'Woo Total Menu', 'woo-total-menu' ),
			self::CAPABILITY,
			self::PAGE_SLUG,
			array( $this, 'render_page' ),
			'dashicons-menu', // icon
			58 // position after WooCommerce
		);
	}

	/**
	 * Render the About page.
	 *
	 * @return void
	 */
	public function render_page() {
		?>
		<div class="wrap wtm-about">
			<div class="wtm-about__header">
				<h1>
					<span class="dashicons dashicons-menu"></span>
					<?php esc_html_e( 'Woo Total Menu', 'woo-total-menu' ); ?>
					<span class="wtm-version">v<?php echo esc_html( WTM_VERSION ); ?></span>
				</h1>
				<p class="wtm-tagline">
					<?php esc_html_e( 'Créez des méga menus, headers et footers WooCommerce avancés via un builder visuel glisser-déposer.', 'woo-total-menu' ); ?>
				</p>
			</div>

			<div class="wtm-about__grid">
				<div class="wtm-card">
					<h2><span class="dashicons dashicons-info"></span> <?php esc_html_e( 'À propos de cette version', 'woo-total-menu' ); ?></h2>
					<p>
						<?php
						printf(
							/* translators: %s version number */
							esc_html__( 'Vous utilisez la version %s. Cette version initiale pose les fondations du plugin :', 'woo-total-menu' ),
							'<strong>v' . esc_html( WTM_VERSION ) . '</strong>'
						);
						?>
					</p>
					<ul class="wtm-list">
						<li><?php esc_html_e( 'Squelette PHP du plugin et autoloader PSR-4', 'woo-total-menu' ); ?></li>
						<li><?php esc_html_e( 'Système de permissions et capacités personnalisées', 'woo-total-menu' ); ?></li>
						<li><?php esc_html_e( 'Gestionnaire de cache (objet + transients)', 'woo-total-menu' ); ?></li>
						<li><?php esc_html_e( 'Réglages globaux par défaut', 'woo-total-menu' ); ?></li>
						<li><?php esc_html_e( 'Page d\'accueil de l\'administration', 'woo-total-menu' ); ?></li>
					</ul>
				</div>

				<div class="wtm-card">
					<h2><span class="dashicons dashicons-clock"></span> <?php esc_html_e( 'Prochaines étapes', 'woo-total-menu' ); ?></h2>
					<ol class="wtm-roadmap">
						<li><strong>v1.0.1</strong> — Custom Post Type <code>wtm_menu</code></li>
						<li><strong>v1.0.2</strong> — Pages admin (Dashboard, Menus, Réglages)</li>
						<li><strong>v1.0.3</strong> — API REST CRUD menus</li>
						<li><strong>v1.0.4</strong> — Schéma JSON de configuration</li>
						<li><strong>v1.1.x</strong> — Builder visuel React</li>
						<li><strong>v1.2.x</strong> — Rendu frontend</li>
						<li><strong>v1.3.x</strong> — Widgets WooCommerce</li>
					</ol>
				</div>

				<div class="wtm-card">
					<h2><span class="dashicons dashicons-admin-tools"></span> <?php esc_html_e( 'Environnement', 'woo-total-menu' ); ?></h2>
					<table class="wtm-table">
						<tbody>
							<tr><th>PHP</th><td><?php echo esc_html( PHP_VERSION ); ?></td></tr>
							<tr><th>WordPress</th><td><?php echo esc_html( get_bloginfo( 'version' ) ); ?></td></tr>
							<tr><th>WooCommerce</th><td><?php echo class_exists( 'WooCommerce' ) ? esc_html( WC()->version ) : '—'; ?></td></tr>
							<tr><th>Thème actif</th><td><?php echo esc_html( wp_get_theme()->get( 'Name' ) ); ?></td></tr>
							<tr><th>DB Version</th><td><?php echo esc_html( (string) get_option( WTM_OPTION_DB_VERSION ) ); ?></td></tr>
						</tbody>
					</table>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Enqueue minimal admin styles for the About page.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_styles( $hook ) {
		if ( 'toplevel_page_' . self::PAGE_SLUG !== $hook ) {
			return;
		}
		// Inline CSS — minimal, no external deps for v1.0.0.
		$css = <<<'CSS'
		.wtm-about__header { background: linear-gradient(135deg, #6C5CE7, #8E7CF5); color: #fff; padding: 24px 32px; border-radius: 8px; margin: 16px 0; }
		.wtm-about__header h1 { color: #fff; margin: 0 0 8px; display: flex; align-items: center; gap: 8px; }
		.wtm-about__header h1 .dashicons { font-size: 32px; width: 32px; height: 32px; }
		.wtm-version { font-size: 12px; background: rgba(255,255,255,0.25); padding: 2px 10px; border-radius: 12px; margin-left: 8px; }
		.wtm-tagline { margin: 0; opacity: 0.95; }
		.wtm-about__grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 16px; margin-top: 16px; }
		.wtm-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 20px 24px; box-shadow: 0 1px 2px rgba(0,0,0,0.04); }
		.wtm-card h2 { margin-top: 0; font-size: 16px; display: flex; align-items: center; gap: 6px; }
		.wtm-card h2 .dashicons { color: #6C5CE7; }
		.wtm-list, .wtm-roadmap { margin-left: 18px; padding-left: 0; }
		.wtm-list li, .wtm-roadmap li { margin: 6px 0; }
		.wtm-table { width: 100%; border-collapse: collapse; }
		.wtm-table th { text-align: left; padding: 6px 0; color: #6b7280; font-weight: 500; width: 35%; }
		.wtm-table td { padding: 6px 0; font-family: monospace; }
		code { background: #f3f4f6; padding: 1px 6px; border-radius: 3px; font-size: 12px; }
CSS;
		wp_register_style( 'wtm-about', false, array(), WTM_VERSION );
		wp_enqueue_style( 'wtm-about' );
		wp_add_inline_style( 'wtm-about', $css );
	}
}
