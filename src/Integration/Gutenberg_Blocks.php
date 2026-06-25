<?php
/**
 * Gutenberg Blocks — Woo Total Menu.
 *
 * Enregistre 3 blocs dynamiques (server-rendered) :
 *
 *   - `wtm/menu`    — affiche un menu WTM (utilise Menu_Renderer).
 *   - `wtm/header`  — affiche un header WTM (utilise Header_Footer_Renderer).
 *   - `wtm/footer`  — affiche un footer WTM (utilise Header_Footer_Renderer).
 *
 * Pas de build JS séparé : les blocs sont déclarés via `register_block_type`
 * avec un `render_callback` PHP. L'éditeur affiche un placeholder simple
 * géré par `wp.blocks.registerBlockType` côté JS (un fichier `blocks.js`
 * minimal est chargé uniquement en mode édition).
 *
 * @package WooTotalMenu\Integration
 * @since 1.6.0
 */

namespace WooTotalMenu\Integration;

use WooTotalMenu\Frontend\Menu_Renderer;
use WooTotalMenu\Frontend\Header_Footer_Renderer;
use WP_Query;

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

/**
 * Class Gutenberg_Blocks.
 *
 * Instanciée par Bootstrap sur `init`. Injecte Menu_Renderer et
 * Header_Footer_Renderer pour le rendu serveur.
 */
final class Gutenberg_Blocks {

        /**
         * Menu_Renderer instance.
         *
         * @var Menu_Renderer
         */
        private $menu_renderer;

        /**
         * Header_Footer_Renderer instance.
         *
         * @var Header_Footer_Renderer
         */
        private $hf_renderer;

        /**
         * Constructeur.
         *
         * @param Menu_Renderer           $menu_renderer Pour le rendu des menus.
         * @param Header_Footer_Renderer  $hf_renderer   Pour le rendu des headers/footers.
         */
        public function __construct( Menu_Renderer $menu_renderer, Header_Footer_Renderer $hf_renderer ) {
                $this->menu_renderer = $menu_renderer;
                $this->hf_renderer   = $hf_renderer;

                add_action( 'init', array( $this, 'register_blocks' ) );
                add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_assets' ) );
        }

        /**
         * Récupère la liste des menus WTM publiés.
         *
         * Format : tableau de [ 'value' => ID, 'label' => "Nom (ID)" ].
         *
         * @return array<int,array{value:int,label:string}>
         */
        public static function get_menu_options() {
                $query = new WP_Query(
                        array(
                                'post_type'      => WTM_CPT_MENU,
                                'post_status'    => 'publish',
                                'posts_per_page' => 100,
                                'orderby'        => 'title',
                                'order'          => 'ASC',
                                'no_found_rows'  => true,
                        )
                );

                $options = array();
                foreach ( $query->posts as $post ) {
                        $options[] = array(
                                'value' => (int) $post->ID,
                                'label' => sprintf( '%s (#%d)', $post->post_title, $post->ID ),
                        );
                }
                return $options;
        }

        /**
         * Enregistre les 3 blocs dynamiques.
         *
         * @return void
         */
        public function register_blocks() {
                // Bloc `wtm/menu` — affiche un menu.
                register_block_type(
                        'wtm/menu',
                        array(
                                'attributes'      => array(
                                        'menuId'   => array(
                                                'type'    => 'number',
                                                'default' => 0,
                                        ),
                                        'location' => array(
                                                'type'    => 'string',
                                                'default' => '',
                                        ),
                                ),
                                'render_callback' => array( $this, 'render_menu_block' ),
                                'editor_script'   => 'wtm-blocks-editor',
                                'editor_style'    => 'wtm-blocks-editor',
                        )
                );

                // Bloc `wtm/header` — affiche un header.
                register_block_type(
                        'wtm/header',
                        array(
                                'attributes'      => array(
                                        'menuId' => array(
                                                'type'    => 'number',
                                                'default' => 0,
                                        ),
                                ),
                                'render_callback' => array( $this, 'render_header_block' ),
                                'editor_script'   => 'wtm-blocks-editor',
                                'editor_style'    => 'wtm-blocks-editor',
                        )
                );

                // Bloc `wtm/footer` — affiche un footer.
                register_block_type(
                        'wtm/footer',
                        array(
                                'attributes'      => array(
                                        'menuId' => array(
                                                'type'    => 'number',
                                                'default' => 0,
                                        ),
                                ),
                                'render_callback' => array( $this, 'render_footer_block' ),
                                'editor_script'   => 'wtm-blocks-editor',
                                'editor_style'    => 'wtm-blocks-editor',
                        )
                );
        }

        /**
         * Render callback — bloc `wtm/menu`.
         *
         * @param array $atts Attributs du bloc.
         * @return string HTML.
         */
        public function render_menu_block( $atts ) {
                $menu_id  = (int) ( $atts['menuId'] ?? 0 );
                $location = sanitize_key( (string) ( $atts['location'] ?? '' ) );

                if ( $menu_id <= 0 && empty( $location ) ) {
                        return $this->render_placeholder( __( 'Aucun menu sélectionné.', 'woo-total-menu' ) );
                }

                $html = $this->menu_renderer->render_by_id( $menu_id, $location );

                if ( '' === $html ) {
                        return $this->render_placeholder( __( 'Menu introuvable. Vérifiez l\'ID ou l\'emplacement.', 'woo-total-menu' ) );
                }

                /**
                 * Fires when a WTM menu is rendered via Gutenberg block.
                 *
                 * @since 1.6.0
                 *
                 * @param int    $menu_id  Menu post ID.
                 * @param string $location Location slug.
                 */
                do_action( 'wtm_rendered_location', $menu_id, $location );

                return $html;
        }

        /**
         * Render callback — bloc `wtm/header`.
         *
         * @param array $atts Attributs du bloc.
         * @return string HTML.
         */
        public function render_header_block( $atts ) {
                $menu_id = (int) ( $atts['menuId'] ?? 0 );

                if ( $menu_id <= 0 ) {
                        return $this->render_placeholder( __( 'Aucun menu-header sélectionné.', 'woo-total-menu' ) );
                }

                $html = $this->hf_renderer->render_header_by_id( $menu_id );

                if ( '' === $html ) {
                        return $this->render_placeholder( __( 'Header introuvable ou non configuré.', 'woo-total-menu' ) );
                }

                return sprintf( '<div class="wtm-header-block">%s</div>', $html );
        }

        /**
         * Render callback — bloc `wtm/footer`.
         *
         * @param array $atts Attributs du bloc.
         * @return string HTML.
         */
        public function render_footer_block( $atts ) {
                $menu_id = (int) ( $atts['menuId'] ?? 0 );

                if ( $menu_id <= 0 ) {
                        return $this->render_placeholder( __( 'Aucun menu-footer sélectionné.', 'woo-total-menu' ) );
                }

                $html = $this->hf_renderer->render_footer_by_id( $menu_id );

                if ( '' === $html ) {
                        return $this->render_placeholder( __( 'Footer introuvable ou non configuré.', 'woo-total-menu' ) );
                }

                return sprintf( '<div class="wtm-footer-block">%s</div>', $html );
        }

        /**
         * Affiche un placeholder dans l'éditeur / frontend quand le bloc n'a pas
         * encore été configuré ou que le menu cible n'existe pas.
         *
         * @param string $message Message à afficher.
         * @return string HTML.
         */
        private function render_placeholder( $message ) {
                return sprintf(
                        '<div class="wtm-block-placeholder" style="padding:24px; border:2px dashed #6C5CE7; border-radius:8px; background:#F8F7FF; text-align:center; color:#6C5CE7; font-family:inherit;"><span class="dashicons dashicons-menu" style="font-size:24px; width:24px; height:24px; vertical-align:middle; margin-right:6px;"></span> %s</div>',
                        esc_html( $message )
                );
        }

        /**
         * Enqueue l'editor JS pour les 3 blocs.
         *
         * Un fichier `blocks.js` minimal déclare les blocs côté JS (sidebar
         * controls pour sélectionner un menu) et laisse le rendu au serveur.
         *
         * @return void
         */
        public function enqueue_editor_assets() {
                $asset_file = WTM_PLUGIN_DIR . 'build/blocks.asset.php';
                $asset      = file_exists( $asset_file )
                        ? include $asset_file
                        : array( 'dependencies' => array( 'wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-data' ), 'version' => WTM_VERSION );

                wp_register_script(
                        'wtm-blocks-editor',
                        WTM_PLUGIN_URL . 'build/blocks.js',
                        $asset['dependencies'],
                        $asset['version'],
                        true
                );

                wp_register_style(
                        'wtm-blocks-editor',
                        WTM_PLUGIN_URL . 'build/style-blocks.css',
                        array(),
                        WTM_VERSION
                );

                // Passer la liste des menus au JS.
                wp_localize_script(
                        'wtm-blocks-editor',
                        'wtmBlocksData',
                        array(
                                'menus' => self::get_menu_options(),
                        )
                );
        }
}
