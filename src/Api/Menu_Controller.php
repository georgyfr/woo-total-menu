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
                                'type'              => array( 'object', 'string', 'null' ),
                        ),
                        'footer_config' => array(
                                'description'       => __( 'Configuration JSON du footer (optionnel).', 'woo-total-menu' ),
                                'type'              => array( 'object', 'string', 'null' ),
                        ),
                        'conditions' => array(
                                'description'       => __( 'Conditions de visibilité du menu (v1.7.0).', 'woo-total-menu' ),
                                'type'              => array( 'object', 'string', 'null' ),
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
        public static function format_item( $post ) {
                $config_raw        = get_post_meta( $post->ID, '_wtm_config', true );
                $header_config_raw = get_post_meta( $post->ID, '_wtm_header_config', true );
                $footer_config_raw = get_post_meta( $post->ID, '_wtm_footer_config', true );
                $conditions_raw    = get_post_meta( $post->ID, '_wtm_conditions', true );

                $config        = $config_raw ? json_decode( $config_raw, true ) : null;
                $header_config = $header_config_raw ? json_decode( $header_config_raw, true ) : null;
                $footer_config = $footer_config_raw ? json_decode( $footer_config_raw, true ) : null;
                $conditions    = $conditions_raw ? json_decode( $conditions_raw, true ) : null;

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
                        'conditions'    => $conditions ?: null,
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
                        $items[] = self::format_item( $menu );
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
                $config     = $request->get_param( 'config' );
                $header     = $request->get_param( 'header_config' );
                $footer     = $request->get_param( 'footer_config' );
                $conditions = $request->get_param( 'conditions' );

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

                // v1.7.0 — Validate conditions (optional).
                $conditions_decoded = null;
                if ( null !== $conditions && '' !== $conditions ) {
                        $conditions_decoded = is_string( $conditions )
                                ? json_decode( $conditions, true )
                                : $conditions;
                        $clean = \WooTotalMenu\Core\Condition_Evaluator::validate( $conditions_decoded );
                        if ( is_wp_error( $clean ) ) {
                                return $clean;
                        }
                        $conditions_decoded = $clean;
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
                // v1.7.0 — Save conditions (only if non-empty rules).
                if ( $conditions_decoded && ! empty( $conditions_decoded['rules'] ) ) {
                        update_post_meta( $post_id, '_wtm_conditions', wp_slash( wp_json_encode( $conditions_decoded ) ) );
                }

                // Invalidate cache.
                $this->invalidate_menu_cache( $post_id );

                $post     = get_post( $post_id );
                $response = new WP_REST_Response( self::format_item( $post ), 201 );
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

                return new WP_REST_Response( self::format_item( $post ), 200 );
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

                // v1.1.5 — Important: the route args declare `default` values for
                // title/menu_type/location/config/header_config/footer_config.
                // That means `$request->get_param('config')` returns the default
                // (NOT null) when the field is not in the request body, and
                // `$request->has_param('config')` ALSO returns true (because the
                // default is added to the params). To detect "field actually
                // provided by client in the JSON body", we use
                // `$request->get_json_params()` and `isset()`. Otherwise every PUT
                // without `config` would silently reset the config to the empty
                // default — a regression from v1.1.4 (where Meta_Boxes was not
                // instantiated in REST context, so sanitize_callback was inactive).
                $json_params = $request->get_json_params();

                if ( isset( $json_params['title'] ) ) {
                        $update_args['post_title'] = sanitize_text_field( $request->get_param( 'title' ) );
                }
                if ( isset( $json_params['slug'] ) ) {
                        $update_args['post_name'] = sanitize_title( $request->get_param( 'slug' ) );
                }
                if ( isset( $json_params['status'] ) ) {
                        $update_args['post_status'] = sanitize_key( $request->get_param( 'status' ) );
                }

                // v1.1.5 — spec §6.6, §7.6 : each save must create a WordPress
                // revision so the History panel can list it. WordPress only creates
                // a revision when a *post field* (title, content, excerpt, etc.)
                // changes — meta-only changes do NOT trigger a revision.
                // To work around this, we store a short signature derived from the
                // config in `post_content` so WP core's `wp_save_post_revision()`
                // always fires on save.
                $config_for_signature = isset( $json_params['config'] )
                        ? $json_params['config']
                        : ( get_post_meta( $post_id, '_wtm_config', true ) ?: '' );
                $signature = is_string( $config_for_signature )
                        ? $config_for_signature
                        : wp_json_encode( $config_for_signature );
                $update_args['post_content'] = 'wtm:' . substr( md5( (string) $signature ), 0, 16 ) . ':' . time();

                // === CRITICAL ORDERING (v1.1.5) ===
                // We must update the META BEFORE calling wp_update_post, because
                // wp_update_post internally calls wp_save_post_revision which
                // _wp_copy_post_meta_to_revision — and that copies the parent's
                // CURRENT meta to the revision. If we update the meta AFTER
                // wp_update_post, the revision captures the OLD meta, not the new
                // one — making the History panel useless (every revision would
                // show the previous state instead of the state at save time).
                //
                // So: update meta first, then wp_update_post (which creates the
                // revision from the now-updated parent state).

                // Update meta — only if explicitly provided in the request body.
                if ( isset( $json_params['menu_type'] ) ) {
                        update_post_meta( $post_id, '_wtm_menu_type', sanitize_key( $request->get_param( 'menu_type' ) ) );
                }
                if ( isset( $json_params['location'] ) ) {
                        update_post_meta( $post_id, '_wtm_location', sanitize_key( $request->get_param( 'location' ) ) );
                }

                // Update config (with validation).
                if ( isset( $json_params['config'] ) ) {
                        $config = $json_params['config'];
                        $config_decoded = is_string( $config )
                                ? Schema_Validator::decode_and_validate_config( $config )
                                : Schema_Validator::decode_and_validate_config( wp_json_encode( $config ) );
                        if ( is_wp_error( $config_decoded ) ) {
                                return $config_decoded;
                        }
                        update_post_meta( $post_id, '_wtm_config', wp_slash( wp_json_encode( $config_decoded ) ) );
                }

                if ( isset( $json_params['header_config'] ) ) {
                        $header = $json_params['header_config'];
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

                if ( isset( $json_params['footer_config'] ) ) {
                        $footer = $json_params['footer_config'];
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

                // v1.7.0 — Update conditions (optional, can be cleared).
                if ( isset( $json_params['conditions'] ) ) {
                        $conditions = $json_params['conditions'];
                        if ( '' === $conditions || null === $conditions || empty( $conditions['rules'] ) ) {
                                delete_post_meta( $post_id, '_wtm_conditions' );
                        } else {
                                $clean = \WooTotalMenu\Core\Condition_Evaluator::validate( $conditions );
                                if ( is_wp_error( $clean ) ) {
                                        return $clean;
                                }
                                if ( ! empty( $clean['rules'] ) ) {
                                        update_post_meta( $post_id, '_wtm_conditions', wp_slash( wp_json_encode( $clean ) ) );
                                } else {
                                        delete_post_meta( $post_id, '_wtm_conditions' );
                                }
                        }
                }

                // Now call wp_update_post — this will create a revision that
                // captures the JUST-UPDATED meta as well as the new post_content
                // signature.
                $result = wp_update_post( $update_args, true );
                if ( is_wp_error( $result ) ) {
                        return $result;
                }

                // Invalidate cache.
                $this->invalidate_menu_cache( $post_id );

                $post = get_post( $post_id );
                return new WP_REST_Response( self::format_item( $post ), 200 );
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

                $previous = self::format_item( $post );

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
                $response = new WP_REST_Response( self::format_item( $new_post ), 201 );
                $response->header( 'Location', rest_url( self::REST_NAMESPACE . '/' . self::REST_BASE . '/' . $new_id ) );
                return $response;
        }

        /**
         * SCHEMA: GET /wtm/v1/menus/schema
         *
         * Returns the full JSON Schema (draft-04) describing wtm_menu configs,
         * including item types, widget types, layout structure, badges, etc.
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
                                'config' => array_merge(
                                        Schema_Validator::get_full_schema(),
                                        array(
                                                'description' => __( 'Configuration JSON du menu (voir definitions pour la structure complète).', 'woo-total-menu' ),
                                        )
                                ),
                                'header_config' => array(
                                        'description' => __( 'Configuration JSON du header (structure rows → columns → modules).', 'woo-total-menu' ),
                                        'type'        => array( 'object', 'null' ),
                                        '$ref'        => '#/definitions/layout',
                                ),
                                'footer_config' => array(
                                        'description' => __( 'Configuration JSON du footer (structure rows → columns → modules).', 'woo-total-menu' ),
                                        'type'        => array( 'object', 'null' ),
                                        '$ref'        => '#/definitions/layout',
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
                        'definitions' => array(
                                'item'    => Schema_Validator::get_full_schema()['definitions']['item'],
                                'badge'   => Schema_Validator::get_full_schema()['definitions']['badge'],
                                'layout'  => Schema_Validator::get_full_schema()['definitions']['layout'],
                                'row'     => Schema_Validator::get_full_schema()['definitions']['row'],
                                'column'  => Schema_Validator::get_full_schema()['definitions']['column'],
                                'module'  => Schema_Validator::get_full_schema()['definitions']['module'],
                        ),
                        'item_types'    => Schema_Validator::ITEM_TYPES,
                        'widget_types'  => Schema_Validator::WIDGET_TYPES,
                        'module_types'  => Schema_Validator::MODULE_TYPES,
                        'link_targets'  => Schema_Validator::LINK_TARGETS,
                        'mega_triggers' => Schema_Validator::MEGA_TRIGGERS,
                        'mobile_behaviors' => Schema_Validator::MOBILE_BEHAVIORS,
                        'visibility_values' => Schema_Validator::VISIBILITY_VALUES,
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
