<?php
/**
 * Elementor Widget — Woo Total Menu.
 *
 * Ce fichier n'est chargé QUE si Elementor est actif (cf. Elementor_Integration).
 * Il contient la classe `WTM_Elementor_Widget` qui étend `\Elementor\Widget_Base`.
 *
 * @package WooTotalMenu\Integration
 * @since 1.6.0
 */

namespace WooTotalMenu\Integration;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use WP_Query;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Guard : si Elementor n'est pas actif, ce fichier ne doit pas être chargé.
if ( ! class_exists( '\Elementor\Widget_Base' ) ) {
	return;
}

/**
 * Class WTM_Elementor_Widget.
 *
 * Hérite de \Elementor\Widget_Base. Enregistrée via `elementor/widgets/register`
 * par Elementor_Integration.
 */
class WTM_Elementor_Widget extends Widget_Base {

	/**
	 * Menu_Renderer instance — injectée via le controller.
	 *
	 * @var Menu_Renderer
	 */
	public static $menu_renderer;

	/**
	 * Slug du widget.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'wtm-menu';
	}

	/**
	 * Titre affiché dans le panel Elementor.
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'Woo Total Menu', 'woo-total-menu' );
	}

	/**
	 * Icône du panel (dashicons).
	 *
	 * @return string
	 */
	public function get_icon() {
		return 'eicon-nav-menu';
	}

	/**
	 * Catégorie Elementor.
	 *
	 * On utilise la catégorie `general` pour rester visible par défaut.
	 *
	 * @return array<string>
	 */
	public function get_categories() {
		return array( 'general' );
	}

	/**
	 * Keywords pour la recherche Elementor.
	 *
	 * @return array<string>
	 */
	public function get_keywords() {
		return array( 'menu', 'woo', 'wtm', 'mega', 'navigation', 'header' );
	}

	/**
	 * Enregistre les contrôles du widget.
	 *
	 * @return void
	 */
	protected function register_controls() {
		$this->start_controls_section(
			'content_section',
			array(
				'label' => __( 'Menu', 'woo-total-menu' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);

		// Récupérer la liste des menus WTM.
		$options = array( '' => __( '— Sélectionner —', 'woo-total-menu' ) );
		$query   = new WP_Query(
			array(
				'post_type'      => WTM_CPT_MENU,
				'post_status'    => 'publish',
				'posts_per_page' => 100,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'no_found_rows'  => true,
			)
		);
		foreach ( $query->posts as $post ) {
			$options[ (int) $post->ID ] = sprintf( '%s (#%d)', $post->post_title, $post->ID );
		}

		$this->add_control(
			'menu_id',
			array(
				'label'   => __( 'Menu', 'woo-total-menu' ),
				'type'    => Controls_Manager::SELECT,
				'options' => $options,
				'default' => '',
			)
		);

		$this->add_control(
			'menu_id_notice',
			array(
				'type'            => Controls_Manager::RAW_HTML,
				'raw'             => __( 'Vous pouvez aussi utiliser le shortcode <code>[wtm_menu id="42"]</code> dans n\'importe quel champ texte.', 'woo-total-menu' ),
				'content_classes' => 'elementor-descriptor',
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Render callback.
	 *
	 * @return void
	 */
	protected function render() {
		$settings = $this->get_settings_for_display();
		$menu_id  = (int) ( $settings['menu_id'] ?? 0 );

		if ( $menu_id <= 0 ) {
			echo '<div class="wtm-elementor-placeholder" style="padding:20px; border:2px dashed #6C5CE7; border-radius:8px; text-align:center; color:#6C5CE7;">';
			echo '<span class="dashicons dashicons-menu" style="vertical-align:middle; margin-right:6px;"></span>';
			echo esc_html__( 'Sélectionnez un menu dans les réglages du widget.', 'woo-total-menu' );
			echo '</div>';
			return;
		}

		if ( ! self::$menu_renderer ) {
			echo '<!-- WTM: Menu_Renderer not available -->';
			return;
		}

		$html = self::$menu_renderer->render_by_id( $menu_id, '' );
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — HTML trusted.

		/**
		 * Fires when a WTM menu is rendered via Elementor widget.
		 *
		 * @since 1.6.0
		 *
		 * @param int $menu_id Menu post ID.
		 */
		do_action( 'wtm_rendered_location', $menu_id, '' );
	}
}
