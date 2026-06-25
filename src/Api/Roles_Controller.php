<?php
/**
 * Roles Controller — Woo Total Menu.
 *
 * Endpoint REST `/wtm/v1/roles` exposant la gestion des rôles WordPress
 * et de leurs capacités Woo Total Menu.
 *
 *  - `GET    /wtm/v1/roles`              — Liste tous les rôles + leurs caps.
 *  - `GET    /wtm/v1/roles/{slug}`       — Détail d'un rôle.
 *  - `POST   /wtm/v1/roles`              — Crée un nouveau rôle personnalisé.
 *  - `PUT    /wtm/v1/roles/{slug}`       — Met à jour les caps d'un rôle.
 *  - `DELETE /wtm/v1/roles/{slug}`       — Supprime un rôle personnalisé.
 *
 * Toutes les routes requièrent la capacité `wtm_manage_settings`
 * (réservée aux administrateurs par défaut).
 *
 * @package WooTotalMenu\Api
 * @since 1.6.0
 */

namespace WooTotalMenu\Api;

use WooTotalMenu\Core\Roles_Manager;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Error;

/**
 * Class Roles_Controller.
 *
 * Registre des routes /roles. Instancié par Bootstrap sur `rest_api_init`.
 */
final class Roles_Controller {

	/**
	 * Namespace REST du plugin.
	 *
	 * @var string
	 */
	const REST_NAMESPACE = 'wtm/v1';

	/**
	 * Route de base pour les rôles.
	 *
	 * @var string
	 */
	const REST_BASE = 'roles';

	/**
	 * Constructeur — enregistre les hooks REST.
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Enregistre les 5 routes REST liées aux rôles.
	 *
	 * @return void
	 */
	public function register_routes() {
		// 1. GET /wtm/v1/roles — liste.
		register_rest_route(
			self::REST_NAMESPACE,
			'/' . self::REST_BASE,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_roles' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
				'schema' => array( $this, 'get_item_schema' ),
			)
		);

		// 2. GET /wtm/v1/roles/{slug} — détail.
		register_rest_route(
			self::REST_NAMESPACE,
			'/' . self::REST_BASE . '/(?P<slug>[a-z0-9_\-]+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_role' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'args'                => array(
						'slug' => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_key',
						),
					),
				),
			)
		);

		// 3. POST /wtm/v1/roles — créer.
		register_rest_route(
			self::REST_NAMESPACE,
			'/' . self::REST_BASE,
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_role' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'args'                => array(
						'slug' => array(
							'description'       => __( 'Slug du rôle (préfixé automatiquement de `wtm_` si nécessaire).', 'woo-total-menu' ),
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_key',
						),
						'name' => array(
							'description'       => __( 'Nom affiché du rôle.', 'woo-total-menu' ),
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_text_field',
						),
						'caps' => array(
							'description'       => __( 'Tableau des caps WTM à accorder.', 'woo-total-menu' ),
							'type'              => 'array',
							'items'             => array( 'type' => 'string' ),
							'default'           => array(),
							'sanitize_callback' => function ( $v ) {
								return is_array( $v ) ? array_map( 'sanitize_key', $v ) : array();
							},
						),
					),
				),
			)
		);

		// 4. PUT /wtm/v1/roles/{slug} — maj caps.
		register_rest_route(
			self::REST_NAMESPACE,
			'/' . self::REST_BASE . '/(?P<slug>[a-z0-9_\-]+)',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_role' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'args'                => array(
						'slug' => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_key',
						),
						'caps' => array(
							'description'       => __( 'Map cap => bool. Les caps absents ne sont pas modifiés.', 'woo-total-menu' ),
							'type'              => 'object',
							'default'           => array(),
						),
					),
				),
			)
		);

		// 5. DELETE /wtm/v1/roles/{slug} — supprimer.
		register_rest_route(
			self::REST_NAMESPACE,
			'/' . self::REST_BASE . '/(?P<slug>[a-z0-9_\-]+)/delete',
			array(
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_role' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'args'                => array(
						'slug' => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_key',
						),
					),
				),
			)
		);
	}

	/**
	 * Permission : requiert la capacité `wtm_manage_settings`.
	 *
	 * @return true|\WP_Error
	 */
	public function check_permission() {
		if ( ! current_user_can( 'wtm_manage_settings' ) ) {
			return new WP_Error(
				'wtm_rest_forbidden',
				__( 'Vous n\'avez pas l\'autorisation de gérer les rôles.', 'woo-total-menu' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}
		return true;
	}

	/**
	 * GET /roles — liste tous les rôles.
	 *
	 * @param WP_REST_Request $request Requête.
	 * @return WP_REST_Response
	 */
	public function list_roles( $request ) {
		$roles = Roles_Manager::get_all_roles();
		return rest_ensure_response( array_values( $roles ) );
	}

	/**
	 * GET /roles/{slug} — détail d'un rôle.
	 *
	 * @param WP_REST_Request $request Requête.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function get_role( $request ) {
		$slug = $request->get_param( 'slug' );
		$role = Roles_Manager::get_role( $slug );
		if ( ! $role ) {
			return new WP_Error(
				'wtm_role_not_found',
				/* translators: %s role slug */
				sprintf( __( 'Rôle "%s" introuvable.', 'woo-total-menu' ), $slug ),
				array( 'status' => 404 )
			);
		}
		return rest_ensure_response( $role );
	}

	/**
	 * POST /roles — créer un rôle personnalisé.
	 *
	 * @param WP_REST_Request $request Requête.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function create_role( $request ) {
		$slug = $request->get_param( 'slug' );
		$name = $request->get_param( 'name' );
		$caps = $request->get_param( 'caps' );

		$result = Roles_Manager::create_role( $slug, $name, $caps );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => __( 'Rôle créé avec succès.', 'woo-total-menu' ),
				'slug'    => $result,
				'role'    => Roles_Manager::get_role( $result ),
			)
		);
	}

	/**
	 * PUT /roles/{slug} — maj caps.
	 *
	 * @param WP_REST_Request $request Requête.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function update_role( $request ) {
		$slug = $request->get_param( 'slug' );
		$caps = $request->get_param( 'caps' );
		if ( ! is_array( $caps ) ) {
			$caps = array();
		}

		$result = Roles_Manager::update_role_caps( $slug, $caps );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => __( 'Capacités mises à jour avec succès.', 'woo-total-menu' ),
				'role'    => Roles_Manager::get_role( $slug ),
			)
		);
	}

	/**
	 * DELETE /roles/{slug}/delete — supprimer.
	 *
	 * @param WP_REST_Request $request Requête.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function delete_role( $request ) {
		$slug = $request->get_param( 'slug' );

		$result = Roles_Manager::delete_role( $slug );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => __( 'Rôle supprimé avec succès. Les utilisateurs ont été réassignés au rôle Abonné.', 'woo-total-menu' ),
				'slug'    => $slug,
			)
		);
	}

	/**
	 * Schéma JSON public d'un rôle.
	 *
	 * @return array
	 */
	public function get_item_schema() {
		return array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'wtm_role',
			'type'       => 'object',
			'properties' => array(
				'slug'       => array( 'type' => 'string' ),
				'name'       => array( 'type' => 'string' ),
				'is_custom'  => array( 'type' => 'boolean' ),
				'is_admin'   => array( 'type' => 'boolean' ),
				'user_count' => array( 'type' => 'integer' ),
				'caps'       => array(
					'type'                 => 'object',
					'additionalProperties' => array( 'type' => 'boolean' ),
				),
			),
		);
	}
}
