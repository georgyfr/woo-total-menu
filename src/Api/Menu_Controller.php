<?php
/**
 * REST API controller for wtm_menu.
 *
 * @package WooTotalMenu
 */

namespace WooTotalMenu\Api;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WooTotalMenu\Core\CPT_Manager;
use WooTotalMenu\Core\Schema_Validator;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Error;

/**
 * Class Menu_Controller
 *
 * Exposes CRUD endpoints for wtm_menu posts at /wp-json/wtm/v1/menus.
 *
 * Unlike the default WP REST API (/wp/v2/wtm_menus), this controller:
 *   - returns a clean schema (no WP internals like guid, _links, etc.)
 *   - decodes JSON meta (_wtm_config, _wtm_header_config, _wtm_footer_config)
 *     into proper JSON objects in responses
 *   - validates the JSON config schema before saving
 *   - exposes a /duplicate action endpoint
 *   - allows filtering by menu_type, location, status
 */
class Menu_Controller {

	const REST_NAMESPACE = WTM_REST_NAMESPACE;
	const REST_BASE      = 'menus';
	const CAPABILITY     = 'wtm_manage_menus';

	/**
	 * Constructor — registers hooks.
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register all routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		// Collection endpoints.
		register_rest_route(
			self::REST_NAMESPACE,
			'/' . self::REST_BASE,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_menus' ),
					'permission_callback' => array( $this, 'check_read_permission' ),
					'args'                => $this->get_collection_params(),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_menu' ),
					'permission_callback' => array( $this, 'check_write_permission' ),
					'args'                => $this->get_endpoint_args_for_item_schema( true ),
				),
			)
		);

		// Single-item endpoints.
		register_rest_route(
			self::REST_NAMESPACE,
			'/' . self::REST_BASE . '/(?P<id>[\d]+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_menu' ),
					'permission_callback' => array( $this, 'check_read_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_menu' ),
					'permission_callback' => array( $this, 'check_write_permission' ),
					'args'                => $this->get_endpoint_args_for_item_schema( false ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_menu' ),
					'permission_callback' => array( $this, 'check_write_permission' ),
				),
			)
		);

		// Duplicate action.
		register_rest_route(
			self::REST_NAMESPACE,
			'/' . self::REST_BASE . '/(?P<id>[\d]+)/duplicate',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'duplicate_menu' ),
					'permission_callback' => array( $this, 'check_write_permission' ),
				),
			)
		);

		// Schema endpoint.
		register_rest_route(
			self::REST_NAMESPACE,
			'/' . self::REST_BASE . '/schema',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_schema' ),
					'permission_callback' => array( $this, 'check_read_permission' ),
				),
			)
		);
	}

	/**
	 * Check read permission: requires wtm_manage_menus (private CPT).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function check_read_permission( $request ) {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return new WP_Error(
				'wtm_rest_forbidden',
				__( 'Vous n\'avez pas la permission de consulter les menus Woo Total Menu.', 'woo-total-menu' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}
		return true;
	}

	/**
	 * Check write permission: requires wtm_manage_menus.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function check_write_permission( $request ) {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return new WP_Error(
				'wtm_rest_forbidden',
				__( 'Vous n\'avez pas la permission de modifier les menus Woo Total Menu.', 'woo-total-menu' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}
		return true;
	}

	/**
	 * Collection params for the list endpoint.
	 *
	 * @return array
	 */
	public function get_collection_params() {
		return array(
			'page' => array(
				'description'       => __( 'Page courante de la collection.', 'woo-total-menu' ),
				'type'              => 'integer',
				'default'           => 1,
				'sanitize_callback' => 'absint',
				'minimum'           => 1,
			),
			'per_page' => array(
				'description'       => __( 'Nombre maximum d\'éléments par page.', 'woo-total-menu' ),
				'type'              => 'integer',
				'default'           => 20,
				'sanitize_callback' => 'absint',
				'minimum'           => 1,
				'maximum'           => 100,
			),
			'search' => array(
				'description'       => __( 'Recherche plein texte dans les titres.', 'woo-total-menu' ),
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'menu_type' => array(
				'description'       => __( 'Filtrer par type de menu.', 'woo-total-menu' ),
				'type'              => 'string',
				'enum'              => array_keys( CPT_Manager::get_menu_types() ),
				'sanitize_callback' => 'sanitize_key',
			),
			'location' => array(
				'description'       => __( 'Filtrer par emplacement.', 'woo-total-menu' ),
				'type'              => 'string',
				'enum'              => array_keys( CPT_Manager::get_locations() ),
				'sanitize_callback' => 'sanitize_key',
			),
			'status' => array(
				'description'       => __( 'Filtrer par statut (publish, draft, any).', 'woo-total-menu' ),
				'type'              => 'string',
				'default'           => 'any',
				'enum'              => array( 'publish', 'draft', 'any' ),
				'sanitize_callback' => 'sanitize_key',
			),
			'orderby' => array(
				'description'       => __( 'Trier par champ.', 'woo-total-menu' ),
				'type'              => 'string',
				'default'           => 'date',
				'enum'              => array( 'date', 'modified', 'title', 'id' ),
				'sanitize_callback' => 'sanitize_key',
			),
			'order' => array(
				'description'       => __( 'Ordre de tri.', 'woo-total-menu' ),
				'type'              => 'string',
				'default'           => 'desc',
				'enum'              => array( 'asc', 'desc' ),
				'sanitize_callback' => 'sanitize_key',
			),
		);
	}

	/**
	 * Get endpoint args for item create/update.
	 *
	 * @param bool $create True for create (more fields required), false for update.
	 * @return array
	 */
	public function get_endpoint_args_for_item_schema( $create = false ) {
		$args = array(
			'title' => array(
				'description'       => __( 'Titre du menu.', 'woo-total-menu' ),
				'type'              => 'string',
				'required'          => $create,
				'sanitize_callback' => 'sanitize_text_field',
			),
			'slug' => array(
				'description'       => __( 'Identifiant URL (slug).', 'woo-total-menu' ),
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_title',
			),
			'status' => array(
				'description'       => __( 'Statut du menu.', 'woo-total-menu' ),
				'type'              => 'string',
				'enum'              => array( 'publish', 'draft' ),
				'default'           => 'publish',
				'sanitize_callback' => 'sanitize_key',
			),
			'menu_type' => array(
				'description'       => __( 'Type de menu.', 'woo-total-menu' ),
				'type'              => 'string',
				'enum'              => array_keys( CPT_Manager::get_menu_types() ),
				'default'           => 'horizontal',
				'sanitize_callback' => 'sanitize_key',
			),
			'location' => array(
				'description'       => __( 'Emplacement du menu.', 'woo-total-menu' ),
				'type'              => 'string',
				'enum'              => array_keys( CPT_Manager::get_locations() ),
				'default'           => 'primary',
				'sanitize_callback' => 'sanitize_key',
			),
			'config' => array(
				'description'       => __( 'Configuration JSON du menu.', 'woo-total-menu' ),
				'type'              => array( 'object', 'string' ),
				'default'           => array( 'version' => 1, 'items' => array() ),
			),
			'header_config' => array(
				'description'       => __( 'Configuration JSON du header (optionnel).', 'woo-total-menu' ),
				'type'              => array( 'object', 'string' ),
			),
			'footer_config' => array(
				'description'       => __( 'Configuration JSON du footer (optionnel).', 'woo-total-menu' ),
				'type'              => array( 'object', 'string' ),
			),
			'version' => array(
				'description'       => __( 'Version du schéma de données.', 'woo-total-menu' ),
				'type'              => 'integer',
				'default'           => WTM_DB_VERSION,
				'sanitize_callback' => 'absint',
			),
		);
		return $args;
	}

	/**
	 * Format a wtm_menu post as a clean API response.
	 *
	 * @param \WP_Post $post Post object.
	 * @return array
	 */
	public function format_item( $post ) {
		$config_raw        = get_post_meta( $post->ID, '_wtm_config', true );
		$header_config_raw = get_post_meta( $post->ID, '_wtm_header_config', true );
		$footer_config_raw = get_post_meta( $post->ID, '_wtm_footer_config', true );

		$config        = $config_raw ? json_decode( $config_raw, true ) : null;
		$header_config = $header_config_raw ? json_decode( $header_config_raw, true ) : null;
		$footer_config = $footer_config_raw ? json_decode( $footer_config_raw, true ) : null;

		return array(
			'id'            => (int) $post->ID,
			'title'         => $post->post_title,
			'slug'          => $post->post_name,
			'status'        => $post->post_status,
			'menu_type'     => get_post_meta( $post->ID, '_wtm_menu_type', true ) ?: 'horizontal',
			'location'      => get_post_meta( $post->ID, '_wtm_location', true ) ?: 'primary',
			'config'        => $config ?: array( 'version' => 1, 'items' => array() ),
			'header_config' => $header_config ?: null,
			'footer_config' => $footer_config ?: null,
			'version'       => (int) ( get_post_meta( $post->ID, '_wtm_version', true ) ?: WTM_DB_VERSION ),
			'date_created'  => mysql2date( 'c', $post->post_date_gmt, false ),
			'date_modified' => mysql2date( 'c', $post->post_modified_gmt, false ),
			'author'        => (int) $post->post_author,
			'edit_url'      => admin_url( 'post.php?post=' . $post->ID . '&action=edit' ),
		);
	}

	/**
	 * LIST: GET /wtm/v1/menus
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function list_menus( $request ) {
		$page      = $request->get_param( 'page' );
		$per_page  = $request->get_param( 'per_page' );
		$search    = $request->get_param( 'search' );
		$menu_type = $request->get_param( 'menu_type' );
		$location  = $request->get_param( 'location' );
		$status    = $request->get_param( 'status' );
		$orderby   = $request->get_param( 'orderby' );
		$order     = strtoupper( $request->get_param( 'order' ) );

		// Build query.
		$args = array(
			'post_type'      => WTM_CPT_MENU,
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'orderby'        => $orderby,
			'order'          => $order,
			'post_status'    => ( 'any' === $status ) ? 'any' : $status,
		);
		if ( $search ) {
			$args['s'] = $search;
		}

		$query = new \WP_Query( $args );
		$menus = $query->posts;

		// Filter by meta (menu_type, location) — done in PHP.
		if ( $menu_type ) {
			$menus = array_filter(
				$menus,
				function ( $m ) use ( $menu_type ) {
					return ( get_post_meta( $m->ID, '_wtm_menu_type', true ) ?: 'horizontal' ) === $menu_type;
				}
			);
		}
		if ( $location ) {
			$menus = array_filter(
				$menus,
				function ( $m ) use ( $location ) {
					return ( get_post_meta( $m->ID, '_wtm_location', true ) ?: 'primary' ) === $location;
				}
			);
		}

		// Format response.
		$items = array();
		foreach ( $menus as $menu ) {
			$items[] = $this->format_item( $menu );
		}

		// Build response with pagination headers.
		$response = new WP_REST_Response( $items, 200 );
		$response->header( 'X-WP-Total', (int) $query->found_posts );
		$response->header( 'X-WP-TotalPages', (int) $query->max_num_pages );

		return $response;
	}

	/**
	 * CREATE: POST /wtm/v1/menus
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_menu( $request ) {
		$title     = $request->get_param( 'title' );
		$slug      = $request->get_param( 'slug' );
		$status    = $request->get_param( 'status' ) ?: 'publish';
		$menu_type = $request->get_param( 'menu_type' ) ?: 'horizontal';
		$location  = $request->get_param( 'location' ) ?: 'primary';
		$config    = $request->get_param( 'config' );
		$header    = $request->get_param( 'header_config' );
		$footer    = $request->get_param( 'footer_config' );

		if ( empty( $title ) ) {
			return new WP_Error(
				'wtm_missing_title',
				__( 'Le titre est obligatoire.', 'woo-total-menu' ),
				array( 'status' => 400 )
			);
		}

		// Validate config.
		$config_decoded = is_string( $config )
			? Schema_Validator::decode_and_validate_config( $config )
			: Schema_Validator::decode_and_validate_config( wp_json_encode( $config ) );
		if ( is_wp_error( $config_decoded ) ) {
			return $config_decoded;
		}

		// Validate header/footer (optional).
		$header_decoded = null;
		$footer_decoded = null;
		if ( null !== $header && '' !== $header ) {
			$header_decoded = is_string( $header )
				? Schema_Validator::decode_and_validate_layout( $header )
				: Schema_Validator::decode_and_validate_layout( wp_json_encode( $header ) );
			if ( is_wp_error( $header_decoded ) ) {
				return $header_decoded;
			}
		}
		if ( null !== $footer && '' !== $footer ) {
			$footer_decoded = is_string( $footer )
				? Schema_Validator::decode_and_validate_layout( $footer )
				: Schema_Validator::decode_and_validate_layout( wp_json_encode( $footer ) );
			if ( is_wp_error( $footer_decoded ) ) {
				return $footer_decoded;
			}
		}

		// Insert post.
		$post_id = wp_insert_post(
			array(
				'post_type'   => WTM_CPT_MENU,
				'post_title'  => $title,
				'post_name'   => $slug,
				'post_status' => $status,
			),
			true
		);
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		// Save meta.
		update_post_meta( $post_id, '_wtm_menu_type', $menu_type );
		update_post_meta( $post_id, '_wtm_location',  $location );
		update_post_meta( $post_id, '_wtm_version',   WTM_DB_VERSION );
		update_post_meta( $post_id, '_wtm_config',    wp_slash( wp_json_encode( $config_decoded ) ) );
		if ( $header_decoded ) {
			update_post_meta( $post_id, '_wtm_header_config', wp_slash( wp_json_encode( $header_decoded ) ) );
		}
		if ( $footer_decoded ) {
			update_post_meta( $post_id, '_wtm_footer_config', wp_slash( wp_json_encode( $footer_decoded ) ) );
		}

		// Invalidate cache.
		$this->invalidate_menu_cache( $post_id );

		$post     = get_post( $post_id );
		$response = new WP_REST_Response( $this->format_item( $post ), 201 );
		$response->header( 'Location', rest_url( self::REST_NAMESPACE . '/' . self::REST_BASE . '/' . $post_id ) );
		return $response;
	}

	/**
	 * READ: GET /wtm/v1/menus/{id}
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_menu( $request ) {
		$post_id = (int) $request->get_param( 'id' );
		$post    = get_post( $post_id );

		if ( ! $post || WTM_CPT_MENU !== $post->post_type ) {
			return new WP_Error(
				'wtm_menu_not_found',
				__( 'Menu introuvable.', 'woo-total-menu' ),
				array( 'status' => 404 )
			);
		}

		return new WP_REST_Response( $this->format_item( $post ), 200 );
	}

	/**
	 * UPDATE: PUT/PATCH /wtm/v1/menus/{id}
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_menu( $request ) {
		$post_id = (int) $request->get_param( 'id' );
		$post    = get_post( $post_id );

		if ( ! $post || WTM_CPT_MENU !== $post->post_type ) {
			return new WP_Error(
				'wtm_menu_not_found',
				__( 'Menu introuvable.', 'woo-total-menu' ),
				array( 'status' => 404 )
			);
		}

		$update_args = array( 'ID' => $post_id );

		if ( null !== $request->get_param( 'title' ) ) {
			$update_args['post_title'] = sanitize_text_field( $request->get_param( 'title' ) );
		}
		if ( null !== $request->get_param( 'slug' ) ) {
			$update_args['post_name'] = sanitize_title( $request->get_param( 'slug' ) );
		}
		if ( null !== $request->get_param( 'status' ) ) {
			$update_args['post_status'] = sanitize_key( $request->get_param( 'status' ) );
		}

		$result = wp_update_post( $update_args, true );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Update meta.
		if ( null !== $request->get_param( 'menu_type' ) ) {
			update_post_meta( $post_id, '_wtm_menu_type', sanitize_key( $request->get_param( 'menu_type' ) ) );
		}
		if ( null !== $request->get_param( 'location' ) ) {
			update_post_meta( $post_id, '_wtm_location', sanitize_key( $request->get_param( 'location' ) ) );
		}

		// Update config (with validation).
		if ( null !== $request->get_param( 'config' ) ) {
			$config = $request->get_param( 'config' );
			$config_decoded = is_string( $config )
				? Schema_Validator::decode_and_validate_config( $config )
				: Schema_Validator::decode_and_validate_config( wp_json_encode( $config ) );
			if ( is_wp_error( $config_decoded ) ) {
				return $config_decoded;
			}
			update_post_meta( $post_id, '_wtm_config', wp_slash( wp_json_encode( $config_decoded ) ) );
		}

		if ( null !== $request->get_param( 'header_config' ) ) {
			$header = $request->get_param( 'header_config' );
			if ( '' === $header || null === $header ) {
				delete_post_meta( $post_id, '_wtm_header_config' );
			} else {
				$header_decoded = is_string( $header )
					? Schema_Validator::decode_and_validate_layout( $header )
					: Schema_Validator::decode_and_validate_layout( wp_json_encode( $header ) );
				if ( is_wp_error( $header_decoded ) ) {
					return $header_decoded;
				}
				update_post_meta( $post_id, '_wtm_header_config', wp_slash( wp_json_encode( $header_decoded ) ) );
			}
		}

		if ( null !== $request->get_param( 'footer_config' ) ) {
			$footer = $request->get_param( 'footer_config' );
			if ( '' === $footer || null === $footer ) {
				delete_post_meta( $post_id, '_wtm_footer_config' );
			} else {
				$footer_decoded = is_string( $footer )
					? Schema_Validator::decode_and_validate_layout( $footer )
					: Schema_Validator::decode_and_validate_layout( wp_json_encode( $footer ) );
				if ( is_wp_error( $footer_decoded ) ) {
					return $footer_decoded;
				}
				update_post_meta( $post_id, '_wtm_footer_config', wp_slash( wp_json_encode( $footer_decoded ) ) );
			}
		}

		// Invalidate cache.
		$this->invalidate_menu_cache( $post_id );

		$post = get_post( $post_id );
		return new WP_REST_Response( $this->format_item( $post ), 200 );
	}

	/**
	 * DELETE: DELETE /wtm/v1/menus/{id}
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_menu( $request ) {
		$post_id = (int) $request->get_param( 'id' );
		$post    = get_post( $post_id );

		if ( ! $post || WTM_CPT_MENU !== $post->post_type ) {
			return new WP_Error(
				'wtm_menu_not_found',
				__( 'Menu introuvable.', 'woo-total-menu' ),
				array( 'status' => 404 )
			);
		}

		$previous = $this->format_item( $post );

		// Invalidate cache before deletion.
		$this->invalidate_menu_cache( $post_id );

		$result = wp_delete_post( $post_id, true );
		if ( ! $result ) {
			return new WP_Error(
				'wtm_delete_failed',
				__( 'Échec de la suppression du menu.', 'woo-total-menu' ),
				array( 'status' => 500 )
			);
		}

		return new WP_REST_Response(
			array(
				'deleted'  => true,
				'previous' => $previous,
			),
			200
		);
	}

	/**
	 * DUPLICATE: POST /wtm/v1/menus/{id}/duplicate
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function duplicate_menu( $request ) {
		$post_id = (int) $request->get_param( 'id' );
		$src     = get_post( $post_id );

		if ( ! $src || WTM_CPT_MENU !== $src->post_type ) {
			return new WP_Error(
				'wtm_menu_not_found',
				__( 'Menu source introuvable.', 'woo-total-menu' ),
				array( 'status' => 404 )
			);
		}

		$new_title = $request->get_param( 'title' );
		if ( ! $new_title ) {
			$new_title = sprintf(
				/* translators: %s original title */
				__( '%s (copie)', 'woo-total-menu' ),
				$src->post_title
			);
		}

		$new_id = wp_insert_post(
			array(
				'post_type'   => WTM_CPT_MENU,
				'post_title'  => $new_title,
				'post_status' => 'publish',
			),
			true
		);
		if ( is_wp_error( $new_id ) ) {
			return $new_id;
		}

		// Copy all meta.
		foreach ( array( '_wtm_location', '_wtm_menu_type', '_wtm_config', '_wtm_header_config', '_wtm_footer_config', '_wtm_version' ) as $key ) {
			$val = get_post_meta( $post_id, $key, true );
			if ( '' !== $val && null !== $val ) {
				update_post_meta( $new_id, $key, $val );
			}
		}

		// Invalidate cache.
		$this->invalidate_menu_cache( $new_id );

		$new_post = get_post( $new_id );
		$response = new WP_REST_Response( $this->format_item( $new_post ), 201 );
		$response->header( 'Location', rest_url( self::REST_NAMESPACE . '/' . self::REST_BASE . '/' . $new_id ) );
		return $response;
	}

	/**
	 * SCHEMA: GET /wtm/v1/menus/schema
	 *
	 * @return WP_REST_Response
	 */
	public function get_schema() {
		$schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'wtm_menu',
			'type'       => 'object',
			'properties' => array(
				'id' => array(
					'description' => __( 'Identifiant unique du menu.', 'woo-total-menu' ),
					'type'        => 'integer',
					'readonly'    => true,
				),
				'title' => array(
					'description' => __( 'Titre du menu.', 'woo-total-menu' ),
					'type'        => 'string',
					'required'    => true,
				),
				'slug' => array(
					'description' => __( 'Slug URL.', 'woo-total-menu' ),
					'type'        => 'string',
				),
				'status' => array(
					'description' => __( 'Statut du menu.', 'woo-total-menu' ),
					'type'        => 'string',
					'enum'        => array( 'publish', 'draft' ),
					'default'     => 'publish',
				),
				'menu_type' => array(
					'description' => __( 'Type de menu.', 'woo-total-menu' ),
					'type'        => 'string',
					'enum'        => array_keys( CPT_Manager::get_menu_types() ),
					'default'     => 'horizontal',
				),
				'location' => array(
					'description' => __( 'Emplacement.', 'woo-total-menu' ),
					'type'        => 'string',
					'enum'        => array_keys( CPT_Manager::get_locations() ),
					'default'     => 'primary',
				),
				'config' => array(
					'description' => __( 'Configuration JSON du menu.', 'woo-total-menu' ),
					'type'        => 'object',
					'properties'  => array(
						'version'  => array( 'type' => 'integer', 'default' => 1 ),
						'items'    => array( 'type' => 'array', 'default' => array() ),
						'settings' => array( 'type' => 'object' ),
					),
				),
				'header_config' => array(
					'description' => __( 'Configuration JSON du header.', 'woo-total-menu' ),
					'type'        => array( 'object', 'null' ),
				),
				'footer_config' => array(
					'description' => __( 'Configuration JSON du footer.', 'woo-total-menu' ),
					'type'        => array( 'object', 'null' ),
				),
				'version' => array(
					'description' => __( 'Version du schéma.', 'woo-total-menu' ),
					'type'        => 'integer',
					'default'     => WTM_DB_VERSION,
				),
				'date_created' => array(
					'description' => __( 'Date de création (ISO 8601).', 'woo-total-menu' ),
					'type'        => 'string',
					'format'      => 'date-time',
					'readonly'    => true,
				),
				'date_modified' => array(
					'description' => __( 'Date de modification (ISO 8601).', 'woo-total-menu' ),
					'type'        => 'string',
					'format'      => 'date-time',
					'readonly'    => true,
				),
				'author' => array(
					'description' => __( 'ID de l\'auteur.', 'woo-total-menu' ),
					'type'        => 'integer',
					'readonly'    => true,
				),
				'edit_url' => array(
					'description' => __( 'URL d\'édition dans wp-admin.', 'woo-total-menu' ),
					'type'        => 'string',
					'format'      => 'uri',
					'readonly'    => true,
				),
			),
		);
		return new WP_REST_Response( $schema, 200 );
	}

	/**
	 * Invalidate cache for a menu.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	private function invalidate_menu_cache( $post_id ) {
		$cache = wtm()->get( 'cache' );
		if ( $cache ) {
			$cache->invalidate_menu( $post_id );
		}
	}
}
