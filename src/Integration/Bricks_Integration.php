<?php
/**
 * Bricks Integration — Woo Total Menu.
 *
 * Enregistre un élément Bricks "Woo Total Menu" qui expose un sélecteur
 * de menu WTM et rend le HTML via Menu_Renderer.
 *
 * Bricks Builder utilise une architecture différente d'Elementor : chaque
 * élément est une classe qui étend `Bricks\Element`. Le rendu est géré par
 * la méthode `render()`.
 *
 * @package WooTotalMenu\Integration
 * @since 1.6.0
 */

namespace WooTotalMenu\Integration;

use WooTotalMenu\Frontend\Menu_Renderer;
use WP_Query;

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

/**
 * Class Bricks_Integration.
 *
 * Instanciée par Bootstrap si Bricks est actif.
 */
final class Bricks_Integration {

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

                // Bricks hook pour enregistrer un élément custom.
                add_filter( 'bricks/builder/elements', array( $this, 'register_element' ) );

                // Le rendu est effectué via un callback dynamique (Bricks appelle
                // la classe enregistrée sous le nom `wtm-menu`).
                add_action( 'bricks/element/render/wtm-menu', array( $this, 'render_element' ), 10, 2 );
        }

        /**
         * Détecte si Bricks est actif.
         *
         * @return bool
         */
        public static function is_active() {
                return defined( 'BRICKS_VERSION' ) || class_exists( '\Bricks\Element' );
        }

        /**
         * Enregistre l'élément Bricks "wtm-menu".
         *
         * @param array $elements Liste des éléments déjà enregistrés.
         * @return array Liste modifiée.
         */
        public function register_element( $elements ) {
                $elements[] = array(
                        'name'        => 'wtm-menu',
                        'category'    => 'wp',
                        'icon'        => 'ti-menu',
                        'label'       => __( 'Woo Total Menu', 'woo-total-menu' ),
                        'controls'    => array(
                                'menu_id' => array(
                                        'type'    => 'select',
                                        'label'   => __( 'Menu', 'woo-total-menu' ),
                                        'options' => $this->get_menu_options(),
                                ),
                                'menu_id_notice' => array(
                                        'type'    => 'info',
                                        'content' => __( 'Astuce : vous pouvez aussi utiliser le shortcode [wtm_menu id="42"] dans n\'importe quel champ texte Bricks.', 'woo-total-menu' ),
                                ),
                        ),
                );
                return $elements;
        }

        /**
         * Récupère la liste des menus WTM pour les options du contrôle.
         *
         * @return array<int,string> Map menu_id => "Nom (#ID)".
         */
        private function get_menu_options() {
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
                return $options;
        }

        /**
         * Rend l'élément Bricks.
         *
         * @param array $element Settings de l'élément.
         * @param mixed $instance Instance Bricks.
         * @return void
         */
        public function render_element( $element, $instance = null ) {
                $menu_id = (int) ( $element['settings']['menu_id'] ?? 0 );

                if ( $menu_id <= 0 ) {
                        echo '<div class="wtm-bricks-placeholder" style="padding:20px; border:2px dashed #6C5CE7; border-radius:8px; text-align:center; color:#6C5CE7;">';
                        echo '<span class="dashicons dashicons-menu" style="vertical-align:middle; margin-right:6px;"></span>';
                        echo esc_html__( 'Sélectionnez un menu dans les réglages de l\'élément.', 'woo-total-menu' );
                        echo '</div>';
                        return;
                }

                $html = $this->menu_renderer->render_by_id( $menu_id, '' );
                echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — HTML trusted.

                /**
                 * Fires when a WTM menu is rendered via Bricks element.
                 *
                 * @since 1.6.0
                 *
                 * @param int $menu_id Menu post ID.
                 */
                do_action( 'wtm_rendered_location', $menu_id, '' );
        }
}
