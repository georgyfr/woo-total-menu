<?php
/**
 * Revisions REST API controller for wtm_menu.
 *
 * Exposes WordPress revisions of the wtm_menu CPT so the React Builder
 * can list, inspect and restore past configurations (spec §6.6, §7.6, §9.9).
 *
 * Routes:
 *   GET  /wtm/v1/menus/{id}/revisions                    → list revisions
 *   GET  /wtm/v1/menus/{id}/revisions/{revision_id}       → fetch one revision
 *   POST /wtm/v1/menus/{id}/revisions/{revision_id}/restore → restore a revision
 *
 * @package WooTotalMenu
 * @since 1.1.5
 */

namespace WooTotalMenu\Api;

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

use WooTotalMenu\Core\CPT_Manager;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Error;

/**
 * Class Revisions_Controller
 *
 * Reads from the WordPress revision system (post_type = 'revision',
 * post_parent = menu ID) and exposes the WTM-specific metadata
 * (_wtm_config, _wtm_menu_type, …) for each revision.
 *
 * For WordPress 6.4+, the metadata is automatically versioned when
 * registered with `revisions_enabled => true` (see Meta_Boxes::register_meta).
 * For older WordPress versions, only the post_title and post_modified are
 * returned — restoring will not bring back the old config in that case.
 */
class Revisions_Controller {

        const REST_NAMESPACE = WTM_REST_NAMESPACE;
        const REST_BASE      = 'menus';
        const CAPABILITY     = 'wtm_manage_menus';

        /**
         * The WTM meta keys tracked in revisions. Must match the keys
         * registered with `revisions_enabled => true` in Meta_Boxes.
         *
         * @var string[]
         */
        const REVISION_META_KEYS = array(
                '_wtm_location',
                '_wtm_menu_type',
                '_wtm_config',
                '_wtm_header_config',
                '_wtm_footer_config',
                '_wtm_version',
        );

        /**
         * Constructor — registers hooks.
         */
        public function __construct() {
                add_action( 'rest_api_init', array( $this, 'register_routes' ) );
        }

        /**
         * Register all revisions routes.
         *
         * @return void
         */
        public function register_routes() {
                // List revisions of a menu.
                register_rest_route(
                        self::REST_NAMESPACE,
                        '/' . self::REST_BASE . '/(?P<id>[\d]+)/revisions',
                        array(
                                array(
                                        'methods'             => WP_REST_Server::READABLE,
                                        'callback'            => array( $this, 'list_revisions' ),
                                        'permission_callback' => array( $this, 'check_permission' ),
                                        'args'                => array(
                                                'page' => array(
                                                        'type'              => 'integer',
                                                        'default'           => 1,
                                                        'sanitize_callback' => 'absint',
                                                        'minimum'           => 1,
                                                ),
                                                'per_page' => array(
                                                        'type'              => 'integer',
                                                        'default'           => 20,
                                                        'sanitize_callback' => 'absint',
                                                        'minimum'           => 1,
                                                        'maximum'           => 100,
                                                ),
                                        ),
                                ),
                        )
                );

                // Fetch a single revision.
                register_rest_route(
                        self::REST_NAMESPACE,
                        '/' . self::REST_BASE . '/(?P<id>[\d]+)/revisions/(?P<revision_id>[\d]+)',
                        array(
                                array(
                                        'methods'             => WP_REST_Server::READABLE,
                                        'callback'            => array( $this, 'get_revision' ),
                                        'permission_callback' => array( $this, 'check_permission' ),
                                ),
                        )
                );

                // Restore a revision.
                register_rest_route(
                        self::REST_NAMESPACE,
                        '/' . self::REST_BASE . '/(?P<id>[\d]+)/revisions/(?P<revision_id>[\d]+)/restore',
                        array(
                                array(
                                        'methods'             => WP_REST_Server::CREATABLE,
                                        'callback'            => array( $this, 'restore_revision' ),
                                        'permission_callback' => array( $this, 'check_permission' ),
                                ),
                        )
                );
        }

        /**
         * Permission callback — requires wtm_manage_menus.
         *
         * @param WP_REST_Request $request Request.
         * @return true|WP_Error
         */
        public function check_permission( $request ) {
                if ( ! current_user_can( self::CAPABILITY ) ) {
                        return new WP_Error(
                                'wtm_rest_forbidden',
                                __( 'Vous n\'avez pas la permission de gérer les révisions de ce menu.', 'woo-total-menu' ),
                                array( 'status' => rest_authorization_required_code() )
                        );
                }
                return true;
        }

        /**
         * Get the parent menu post and validate it.
         *
         * @param int $post_id Post ID.
         * @return WP_Post|WP_Error
         */
        private function get_parent_menu( $post_id ) {
                $post = get_post( $post_id );
                if ( ! $post || WTM_CPT_MENU !== $post->post_type ) {
                        return new WP_Error(
                                'wtm_menu_not_found',
                                __( 'Menu introuvable.', 'woo-total-menu' ),
                                array( 'status' => 404 )
                        );
                }
                return $post;
        }

        /**
         * Format a revision post as a clean API response.
         *
         * Includes the decoded WTM config snapshot so the Builder can preview
         * the revision before restoring.
         *
         * @param \WP_Post $revision Revision post.
         * @return array
         */
        public function format_revision( $revision ) {
                $config_raw        = get_metadata( 'post', $revision->ID, '_wtm_config', true );
                $header_config_raw = get_metadata( 'post', $revision->ID, '_wtm_header_config', true );
                $footer_config_raw = get_metadata( 'post', $revision->ID, '_wtm_footer_config', true );

                $config        = $config_raw ? json_decode( $config_raw, true ) : null;
                $header_config = $header_config_raw ? json_decode( $header_config_raw, true ) : null;
                $footer_config = $footer_config_raw ? json_decode( $footer_config_raw, true ) : null;

                // Compute a short summary of the items count for quick display.
                $items_count = 0;
                if ( is_array( $config ) && isset( $config['items'] ) && is_array( $config['items'] ) ) {
                        $items_count = $this->count_items_recursive( $config['items'] );
                }

                $author = get_userdata( (int) $revision->post_author );

                return array(
                        'id'             => (int) $revision->ID,
                        'parent_id'      => (int) $revision->post_parent,
                        'title'          => $revision->post_title,
                        'author'         => (int) $revision->post_author,
                        'author_name'    => $author ? $author->display_name : '',
                        'author_avatar'  => $author ? get_avatar_url( $author->ID, array( 'size' => 32 ) ) : '',
                        'date_created'   => mysql2date( 'c', $revision->post_date_gmt, false ),
                        'date_modified'  => mysql2date( 'c', $revision->post_modified_gmt, false ),
                        'date_display'   => wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $revision->post_modified_gmt ) ),
                        'relative_date'  => human_time_diff( strtotime( $revision->post_modified_gmt ), time() ),
                        'items_count'    => $items_count,
                        'menu_type'      => get_metadata( 'post', $revision->ID, '_wtm_menu_type', true ) ?: 'horizontal',
                        'location'       => get_metadata( 'post', $revision->ID, '_wtm_location', true ) ?: 'primary',
                        'config'         => $config ?: array( 'version' => 1, 'items' => array() ),
                        'header_config'  => $header_config ?: null,
                        'footer_config'  => $footer_config ?: null,
                        'version'        => (int) ( get_metadata( 'post', $revision->ID, '_wtm_version', true ) ?: 0 ),
                        'restore_url'    => rest_url(
                                sprintf(
                                        '%s/%s/%d/revisions/%d/restore',
                                        self::REST_NAMESPACE,
                                        self::REST_BASE,
                                        $revision->post_parent,
                                        $revision->ID
                                )
                        ),
                );
        }

        /**
         * Count items recursively (including nested children).
         *
         * @param array $items Items tree.
         * @return int
         */
        private function count_items_recursive( $items ) {
                $count = 0;
                foreach ( $items as $item ) {
                        $count++;
                        if ( ! empty( $item['children'] ) && is_array( $item['children'] ) ) {
                                $count += $this->count_items_recursive( $item['children'] );
                        }
                }
                return $count;
        }

        /**
         * LIST: GET /wtm/v1/menus/{id}/revisions
         *
         * @param WP_REST_Request $request Request.
         * @return WP_REST_Response|WP_Error
         */
        public function list_revisions( $request ) {
                $post_id = (int) $request->get_param( 'id' );
                $parent  = $this->get_parent_menu( $post_id );
                if ( is_wp_error( $parent ) ) {
                        return $parent;
                }

                $page     = (int) $request->get_param( 'page' );
                $per_page = (int) $request->get_param( 'per_page' );

                // Fetch revisions (WP returns them most-recent-first by default).
                $revisions = wp_get_post_revisions(
                        $post_id,
                        array(
                                'posts_per_page' => $per_page,
                                'paged'          => $page,
                                'orderby'        => 'date',
                                'order'          => 'DESC',
                        )
                );

                $total       = count( wp_get_post_revisions( $post_id ) );
                $total_pages = max( 1, (int) ceil( $total / $per_page ) );

                $items = array();
                foreach ( $revisions as $revision ) {
                        $items[] = $this->format_revision( $revision );
                }

                $response = new WP_REST_Response( $items, 200 );
                $response->header( 'X-WP-Total', (int) $total );
                $response->header( 'X-WP-TotalPages', (int) $total_pages );

                return $response;
        }

        /**
         * GET: /wtm/v1/menus/{id}/revisions/{revision_id}
         *
         * @param WP_REST_Request $request Request.
         * @return WP_REST_Response|WP_Error
         */
        public function get_revision( $request ) {
                $post_id      = (int) $request->get_param( 'id' );
                $revision_id  = (int) $request->get_param( 'revision_id' );

                $parent = $this->get_parent_menu( $post_id );
                if ( is_wp_error( $parent ) ) {
                        return $parent;
                }

                $revision = get_post( $revision_id );
                if ( ! $revision || 'revision' !== $revision->post_type || (int) $revision->post_parent !== $post_id ) {
                        return new WP_Error(
                                'wtm_revision_not_found',
                                __( 'Révision introuvable pour ce menu.', 'woo-total-menu' ),
                                array( 'status' => 404 )
                        );
                }

                return new WP_REST_Response( $this->format_revision( $revision ), 200 );
        }

        /**
         * RESTORE: POST /wtm/v1/menus/{id}/revisions/{revision_id}/restore
         *
         * Restores the menu post + its WTM metadata from a past revision.
         *
         * Implementation note: `wp_restore_post_revision()` only restores the
         * post fields (title, content, etc.). It does NOT restore post meta
         * automatically. We manually copy the WTM meta from the revision to
         * the parent post so the menu configuration is fully restored.
         *
         * @param WP_REST_Request $request Request.
         * @return WP_REST_Response|WP_Error
         */
        public function restore_revision( $request ) {
                $post_id      = (int) $request->get_param( 'id' );
                $revision_id  = (int) $request->get_param( 'revision_id' );

                $parent = $this->get_parent_menu( $post_id );
                if ( is_wp_error( $parent ) ) {
                        return $parent;
                }

                $revision = get_post( $revision_id );
                if ( ! $revision || 'revision' !== $revision->post_type || (int) $revision->post_parent !== $post_id ) {
                        return new WP_Error(
                                'wtm_revision_not_found',
                                __( 'Révision introuvable pour ce menu.', 'woo-total-menu' ),
                                array( 'status' => 404 )
                        );
                }

                // 1. Restore the post fields (title, status, etc.) via WP core.
                // wp_restore_post_revision() returns the restored post ID on success.
                $restored_id = wp_restore_post_revision( $revision_id );
                if ( ! $restored_id ) {
                        return new WP_Error(
                                'wtm_restore_failed',
                                __( 'Échec de la restauration de la révision.', 'woo-total-menu' ),
                                array( 'status' => 500 )
                        );
                }

                // 2. Manually restore the WTM metadata from the revision.
                // WP core does not restore meta automatically, even when
                // `revisions_enabled => true` is set on the meta registration.
                // We use `get_metadata( 'post', $revision_id, … )` (not get_post_meta)
                // to access the revision's meta directly.
                foreach ( self::REVISION_META_KEYS as $meta_key ) {
                        $rev_value = get_metadata( 'post', $revision_id, $meta_key, true );
                        if ( '' !== $rev_value && null !== $rev_value ) {
                                update_post_meta( $post_id, $meta_key, wp_slash( $rev_value ) );
                        }
                }

                // 3. Invalidate cache.
                $this->invalidate_menu_cache( $post_id );

                // 4. Return the restored menu (using Menu_Controller::format_item
                // for consistency with the GET /menus/{id} response).
                $menu_controller = new Menu_Controller();
                $restored_post   = get_post( $post_id );
                $menu_data       = $menu_controller->format_item( $restored_post );

                return new WP_REST_Response(
                        array(
                                'success'    => true,
                                'menu'       => $menu_data,
                                'revision_id' => $revision_id,
                        ),
                        200
                );
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
