<?php
/**
 * Elementor Integration — Woo Total Menu.
 *
 * Classe controller qui détecte Elementor et enregistre le widget
 * `WTM_Elementor_Widget` (défini dans `Elementor_Widget.php`).
 *
 * Le widget est dans un fichier séparé pour éviter de charger
 * `\Elementor\Widget_Base` quand Elementor n'est pas actif (ce qui
 * planterait l'autoloader PSR-4 du plugin).
 *
 * @package WooTotalMenu\Integration
 * @since 1.6.0
 */

namespace WooTotalMenu\Integration;

use WooTotalMenu\Frontend\Menu_Renderer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Elementor_Integration.
 *
 * Instanciée par Bootstrap si Elementor est actif. Enregistre le widget.
 */
final class Elementor_Integration {

	/**
	 * Menu_Renderer instance.
	 *
	 * @var Menu_Renderer
	 */
	private $menu_renderer;

	/**
	 * Constructeur.
	 *
	 * @param Menu_Renderer $menu_renderer Pour le rendu des menus.
	 */
	public function __construct( Menu_Renderer $menu_renderer ) {
		$this->menu_renderer = $menu_renderer;

		// Injecter le renderer dans le widget (statique car Elementor instancie lui-même).
		WTM_Elementor_Widget::$menu_renderer = $menu_renderer;

		add_action( 'elementor/widgets/register', array( $this, 'register_widget' ) );
	}

	/**
	 * Enregistre le widget Elementor.
	 *
	 * @param \Elementor\Widgets_Manager $widgets_manager Manager Elementor.
	 * @return void
	 */
	public function register_widget( $widgets_manager ) {
		$widgets_manager->register( new WTM_Elementor_Widget() );
	}

	/**
	 * Détecte si Elementor est actif.
	 *
	 * @return bool
	 */
	public static function is_active() {
		return class_exists( '\Elementor\Widget_Base' );
	}
}
