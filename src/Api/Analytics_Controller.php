<?php
/**
 * REST controller for analytics.
 *
 * v1.7.0 — Exposes 2 routes:
 *   POST /wtm/v1/analytics/track   → public, record an event
 *   GET  /wtm/v1/analytics/stats   → admin-only, fetch aggregated stats
 *
 * @package WooTotalMenu
 * @since 1.7.0
 */

namespace WooTotalMenu\Api;

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

use WooTotalMenu\Core\Analytics;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Error;

/**
 * Class Analytics_Controller
 */
class Analytics_Controller {

        const REST_NAMESPACE = WTM_REST_NAMESPACE;
        const REST_BASE      = 'analytics';

        /**
         * Constructor.
         */
        public function __construct() {
                add_action( 'rest_api_init', array( $this, 'register_routes' ) );
        }

        /**
         * Register routes.
         *
         * @return void
         */
        public function register_routes() {
                // POST /analytics/track — public endpoint (uses nonce).
                register_rest_route(
                        self::REST_NAMESPACE,
                        '/' . self::REST_BASE . '/track',
                        array(
                                'methods'             => WP_REST_Server::CREATABLE,
                                'callback'            => array( $this, 'track_event' ),
                                'permission_callback' => array( $this, 'track_permission' ),
                                'args'                => array(
                                        'menu_id' => array(
                                                'type'              => 'integer',
                                                'required'          => true,
                                                'sanitize_callback' => 'absint',
                                        ),
                                        'event' => array(
                                                'type'              => 'string',
                                                'required'          => true,
                                                'enum'              => array( 'view', 'click', 'hover' ),
                                                'sanitize_callback' => 'sanitize_key',
                                        ),
                                        'item_id' => array(
                                                'type'              => 'integer',
                                                'default'           => 0,
                                                'sanitize_callback' => 'absint',
                                        ),
                                ),
                        )
                );

                // GET /analytics/stats — admin-only.
                register_rest_route(
                        self::REST_NAMESPACE,
                        '/' . self::REST_BASE . '/stats',
                        array(
                                'methods'             => WP_REST_Server::READABLE,
                                'callback'            => array( $this, 'get_stats' ),
                                'permission_callback' => array( $this, 'view_permission' ),
                                'args'                => array(
                                        'start' => array(
                                                'type'              => 'string',
                                                'default'           => gmdate( 'Y-m-d', strtotime( '-6 days' ) ),
                                                'sanitize_callback' => 'sanitize_text_field',
                                        ),
                                        'end' => array(
                                                'type'              => 'string',
                                                'default'           => '',
                                                'sanitize_callback' => 'sanitize_text_field',
                                        ),
                                        'menu_id' => array(
                                                'type'              => 'integer',
                                                'default'           => 0,
                                                'sanitize_callback' => 'absint',
                                        ),
                                        'event' => array(
                                                'type'              => 'string',
                                                'default'           => '',
                                                'sanitize_callback' => 'sanitize_key',
                                        ),
                                        'group_by' => array(
                                                'type'              => 'string',
                                                'default'           => 'day',
                                                'enum'              => array( 'day', 'menu', 'event' ),
                                                'sanitize_callback' => 'sanitize_key',
                                        ),
                                ),
                        )
                );
        }

        /**
         * Permission callback for the track endpoint.
         *
         * Public, but requires a valid WP REST nonce (so anonymous abuse is
         * limited to one-request-per-page-load patterns).
         *
         * @param WP_REST_Request $request Request.
         * @return bool
         */
        public function track_permission( $request ) {
                // Verify the REST nonce to mitigate drive-by abuse.
                $nonce = $request->get_header( 'x-wp-nonce' );
                if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
                        // Fall back to a custom nonce for legacy clients.
                        $custom = $request->get_param( '_nonce' );
                        if ( ! $custom || ! wp_verify_nonce( $custom, 'wtm_analytics' ) ) {
                                return false;
                        }
                }

                // Quick check analytics enabled flag without full instantiation.
                $option = get_option( WTM_OPTION_SETTINGS );
                $an     = is_array( $option ) ? ( $option['analytics'] ?? array() ) : array();
                if ( empty( $an['enabled'] ) ) {
                        return false;
                }
                if ( is_user_logged_in() && empty( $an['track_logged'] ) ) {
                        return false;
                }
                return true;
        }

        /**
         * Permission callback for the stats endpoint.
         *
         * @return bool
         */
        public function view_permission() {
                return current_user_can( 'wtm_view_analytics' );
        }

        /**
         * POST /analytics/track
         *
         * @param WP_REST_Request $request Request.
         * @return WP_REST_Response|WP_Error
         */
        public function track_event( $request ) {
                $menu_id = (int) $request->get_param( 'menu_id' );
                $event   = $request->get_param( 'event' );
                $item_id = (int) $request->get_param( 'item_id' );

                // Verify the menu exists.
                $post = get_post( $menu_id );
                if ( ! $post || WTM_CPT_MENU !== $post->post_type ) {
                        return new WP_Error( 'wtm_menu_not_found', __( 'Menu introuvable.', 'woo-total-menu' ), array( 'status' => 404 ) );
                }

                $analytics = new Analytics();
                $recorded  = $analytics->record( $menu_id, $event, $item_id );

                return new WP_REST_Response(
                        array(
                                'recorded' => $recorded,
                                'menu_id'  => $menu_id,
                                'event'    => $event,
                                'item_id'  => $item_id,
                        ),
                        200
                );
        }

        /**
         * GET /analytics/stats
         *
         * @param WP_REST_Request $request Request.
         * @return WP_REST_Response
         */
        public function get_stats( $request ) {
                $args = array(
                        'start'    => $request->get_param( 'start' ),
                        'end'      => $request->get_param( 'end' ) ?: current_time( 'Y-m-d' ),
                        'menu_id'  => $request->get_param( 'menu_id' ),
                        'event'    => $request->get_param( 'event' ),
                        'group_by' => $request->get_param( 'group_by' ),
                );

                $analytics = new Analytics();
                $stats     = $analytics->get_stats( $args );

                return new WP_REST_Response( $stats, 200 );
        }
}
