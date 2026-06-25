<?php
/**
 * Templates Controller — Woo Total Menu.
 *
 * Endpoint REST `/wtm/v1/templates` exposant le catalogue des templates
 * intégrés du plugin. Trois routes :
 *
 *  - `GET /wtm/v1/templates`           — Liste filtrable (type, category, q).
 *  - `GET /wtm/v1/templates/{id}`      — Détail d'un template (config complète).
 *  - `POST /wtm/v1/templates/{id}/apply` — Applique le template à un menu.
 *
 * Toutes les routes sont publiques en lecture (utiles pour prévisualisation
 * côté visiteur si besoin) mais l'action `apply` requiert la capacité
 * `edit_posts` (filter `wtm_manage_menus`).
 *
 * @package WooTotalMenu\Api
 */

namespace WooTotalMenu\Api;

use WooTotalMenu\Core\Template_Registry;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Error;

/**
 * Class Templates_Controller.
 *
 * Registre des routes /templates. Instancié par Bootstrap sur `rest_api_init`.
 */
final class Templates_Controller {

	/**
	 * Namespace REST du plugin.
	 *
	 * @var string
	 */
	const REST_NAMESPACE = 'wtm/v1';

	/**
	 * Route de base pour les templates.
	 *
	 * @var string
	 */
	const REST_BASE = 'templates';

	/**
	 * Constructeur — enregistre les hooks REST.
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Enregistre les 3 routes REST liées aux templates.
	 *
	 * @return void
	 */
	public function register_routes() {
		// 1. GET /wtm/v1/templates — liste filtrable.
		register_rest_route(
			self::REST_NAMESPACE,
			'/' . self::REST_BASE,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_templates' ),
					'permission_callback' => array( $this, 'check_read_permission' ),
					'args'                => array(
						'type'     => array(
							'description'       => __( 'Filtrer par type de template (menu, header, footer).', 'woo-total-menu' ),
							'type'              => 'string',
							'enum'              => array( 'menu', 'header', 'footer' ),
							'sanitize_callback' => 'sanitize_key',
							'default'           => '',
						),
						'category' => array(
							'description'       => __( 'Filtrer par catégorie métier (ecommerce, blog, corporate, minimal, electronics).', 'woo-total-menu' ),
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_key',
							'default'           => '',
						),
						'search'   => array(
							'description'       => __( 'Recherche plein-texte dans name/description/tags.', 'woo-total-menu' ),
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
							'default'           => '',
						),
					),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);

		// 2. GET /wtm/v1/templates/{id} — détail.
		register_rest_route(
			self::REST_NAMESPACE,
			'/' . self::REST_BASE . '/(?P<id>[a-z0-9\-]+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_template' ),
					'permission_callback' => array( $this, 'check_read_permission' ),
					'args'                => array(
						'id' => array(
							'description'       => __( 'Identifiant (slug) du template.', 'woo-total-menu' ),
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_key',
						),
					),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);

		// 3. POST /wtm/v1/templates/{id}/apply — applique à un menu.
		register_rest_route(
			self::REST_NAMESPACE,
			'/' . self::REST_BASE . '/(?P<id>[a-z0-9\-]+)/apply',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'apply_template' ),
					'permission_callback' => array( $this, 'check_write_permission' ),
					'args'                => array(
						'id'     => array(
							'description'       => __( 'Identifiant (slug) du template à appliquer.', 'woo-total-menu' ),
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_key',
						),
						'menu_id' => array(
							'description'       => __( 'ID du menu cible (post_type wtm_menu).', 'woo-total-menu' ),
							'type'              => 'integer',
							'required'          => true,
							'sanitize_callback' => 'absint',
							'validate_callback' => array( $this, 'validate_menu_id' ),
						),
						'mode'    => array(
							'description'       => __( 'Mode d\'application : menu (écrase _wtm_config), header ou footer.', 'woo-total-menu' ),
							'type'              => 'string',
							'enum'              => array( 'menu', 'header', 'footer' ),
							'default'           => 'menu',
							'sanitize_callback' => 'sanitize_key',
						),
					),
				),
			)
		);
	}

	/**
	 * Permission lecture : ouverte (le catalogue n'est pas secret).
	 *
	 * @param WP_REST_Request $request Requête courante.
	 * @return true|\WP_Error true si autorisé.
	 */
	public function check_read_permission( $request ) {
		// Lecture publique du catalogue (utile pour prévisualisation).
		return true;
	}

	/**
	 * Permission écriture : requiert la capacité `edit_posts`.
	 *
	 * @param WP_REST_Request $request Requête courante.
	 * @return true|\WP_Error
	 */
	public function check_write_permission( $request ) {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return new WP_Error(
				'wtm_rest_forbidden',
				__( 'Vous n\'avez pas l\'autorisation d\'appliquer un template.', 'woo-total-menu' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}
		return true;
	}

	/**
	 * Valide qu'un menu_id pointe bien vers un post `wtm_menu` existant.
	 *
	 * @param mixed           $value   Valeur à valider.
	 * @param WP_REST_Request $request Requête courante.
	 * @param string          $param   Nom du paramètre.
	 * @return true|\WP_Error True si valide.
	 */
	public function validate_menu_id( $value, $request, $param ) {
		$menu_id = absint( $value );
		if ( ! $menu_id ) {
			return new WP_Error( 'rest_invalid_param', __( 'menu_id invalide.', 'woo-total-menu' ), array( 'status' => 400 ) );
		}
		if ( 'wtm_menu' !== get_post_type( $menu_id ) ) {
			return new WP_Error( 'rest_invalid_param', __( 'menu_id ne pointe pas vers un menu WTM.', 'woo-total-menu' ), array( 'status' => 400 ) );
		}
		return true;
	}

	/**
	 * GET /templates — liste filtrable.
	 *
	 * @param WP_REST_Request $request Requête.
	 * @return WP_REST_Response Réponse REST (tableau de templates formatés).
	 */
	public function list_templates( $request ) {
		$type     = $request->get_param( 'type' );
		$category = $request->get_param( 'category' );
		$search   = $request->get_param( 'search' );

		// Filtre par type.
		if ( $type ) {
			$templates = Template_Registry::by_type( $type );
		} else {
			$templates = Template_Registry::all();
		}

		// Filtre par catégorie.
		if ( $category ) {
			$templates = array_filter(
				$templates,
				static function ( $t ) use ( $category ) {
					return isset( $t['category'] ) && $t['category'] === $category;
				}
			);
		}

		// Recherche plein-texte.
		if ( $search ) {
			$search_lower = mb_strtolower( $search );
			$templates    = array_filter(
				$templates,
				static function ( $t ) use ( $search_lower ) {
					$haystack = mb_strtolower(
						( $t['name'] ?? '' ) . ' ' .
						( $t['description'] ?? '' ) . ' ' .
						( $t['preview'] ?? '' ) . ' ' .
						implode( ' ', ( $t['tags'] ?? array() ) )
					);
					return false !== mb_strpos( $haystack, $search_lower );
				}
			);
		}

		// Formatage (sans la config complète pour alléger la liste).
		$formatted = array_map( array( $this, 'format_summary' ), array_values( $templates ) );

		return rest_ensure_response( $formatted );
	}

	/**
	 * GET /templates/{id} — détail (config incluse).
	 *
	 * @param WP_REST_Request $request Requête.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function get_template( $request ) {
		$id       = $request->get_param( 'id' );
		$template = Template_Registry::get( $id );
		if ( ! $template ) {
			return new WP_Error(
				'wtm_template_not_found',
				/* translators: %s template id */
				sprintf( __( 'Template "%s" introuvable.', 'woo-total-menu' ), $id ),
				array( 'status' => 404 )
			);
		}
		return rest_ensure_response( $this->format_detail( $template ) );
	}

	/**
	 * POST /templates/{id}/apply — applique un template à un menu.
	 *
	 * Body JSON attendu : `{ "menu_id": 42, "mode": "header" }`.
	 * Réussite → 200 + message + meta_key mis à jour. Échec → 4xx/500 + error_data.
	 *
	 * @param WP_REST_Request $request Requête.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function apply_template( $request ) {
		$id      = $request->get_param( 'id' );
		$menu_id = absint( $request->get_param( 'menu_id' ) );
		$mode    = $request->get_param( 'mode' ) ?: 'menu';

		$result = Template_Registry::apply_to_menu( $menu_id, $id, $mode );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// On renvoie le template formaté en détail pour rafraîchir l'UI.
		$template = Template_Registry::get( $id );
		$payload  = array(
			'success'  => true,
			'message'  => __( 'Template appliqué avec succès.', 'woo-total-menu' ),
			'menu_id'  => $menu_id,
			'mode'     => $mode,
			'template' => $template ? $this->format_detail( $template ) : null,
		);

		return rest_ensure_response( $payload );
	}

	/**
	 * Formate un template en vue "résumé" (sans la config complète).
	 *
	 * @param array $template Template brut.
	 * @return array Template formaté.
	 */
	public function format_summary( $template ) {
		return array(
			'id'          => $template['id'],
			'name'        => $template['name'],
			'description' => $template['description'],
			'type'        => $template['type'],
			'category'    => $template['category'],
			'thumbnail'   => $template['thumbnail'] ?? '',
			'preview'     => $template['preview'] ?? '',
			'tags'        => $template['tags'] ?? array(),
		);
	}

	/**
	 * Formate un template en vue "détail" (config incluse).
	 *
	 * @param array $template Template brut.
	 * @return array Template formaté.
	 */
	public function format_detail( $template ) {
		$summary = $this->format_summary( $template );
		$summary['config'] = $template['config'] ?? array();
		return $summary;
	}

	/**
	 * Schéma JSON public d'un template (utilisé par /wp-json/wtm/v1/templates).
	 *
	 * @return array Schéma.
	 */
	public function get_public_item_schema() {
		return array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'wtm_template',
			'type'       => 'object',
			'properties' => array(
				'id'          => array( 'type' => 'string', 'description' => __( 'Slug du template.', 'woo-total-menu' ) ),
				'name'        => array( 'type' => 'string', 'description' => __( 'Nom affiché.', 'woo-total-menu' ) ),
				'description' => array( 'type' => 'string', 'description' => __( 'Description courte.', 'woo-total-menu' ) ),
				'type'        => array( 'type' => 'string', 'enum' => array( 'menu', 'header', 'footer' ) ),
				'category'    => array( 'type' => 'string' ),
				'thumbnail'   => array( 'type' => 'string' ),
				'preview'     => array( 'type' => 'string' ),
				'tags'        => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
				'config'      => array( 'type' => 'object', 'description' => __( 'Configuration complète (uniquement sur /templates/{id}).', 'woo-total-menu' ) ),
			),
		);
	}
}
