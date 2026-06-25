<?php
/**
 * REST controller for menu conditions.
 *
 * v1.7.0 — Exposes conditions CRUD at /wtm/v1/menus/{id}/conditions.
 *
 * Routes:
 *   GET    /wtm/v1/menus/{id}/conditions        → read
 *   PUT    /wtm/v1/menus/{id}/conditions        → replace (full update)
 *   DELETE /wtm/v1/menus/{id}/conditions        → clear
 *   POST   /wtm/v1/menus/{id}/conditions/test   → evaluate against current request (preview)
 *
 * @package WooTotalMenu
 * @since 1.7.0
 */

namespace WooTotalMenu\Api;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WooTotalMenu\Core\Condition_Evaluator;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Error;

/**
 * Class Conditions_Controller
 */
class Conditions_Controller {

	const REST_NAMESPACE = WTM_REST_NAMESPACE;
	const REST_BASE      = 'menus';
	const CAPABILITY     = 'wtm_manage_menus';

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
		// Read conditions.
		register_rest_route(
			self::REST_NAMESPACE,
			'/' . self::REST_BASE . '/(?P<id>[\d]+)/conditions',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_conditions' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_conditions' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'args'                => array(
						'logic' => array(
							'type'              => 'string',
							'enum'              => array( 'all', 'any' ),
							'default'           => 'all',
							'sanitize_callback' => 'sanitize_key',
						),
						'rules' => array(
							'type'              => 'array',
							'default'           => array(),
							'sanitize_callback' => array( $this, 'sanitize_rules' ),
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_conditions' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
			)
		);

		// Test evaluation (preview).
		register_rest_route(
			self::REST_NAMESPACE,
			'/' . self::REST_BASE . '/(?P<id>[\d]+)/conditions/test',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'test_conditions' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array(
					'logic' => array(
						'type'              => 'string',
						'enum'              => array( 'all', 'any' ),
						'default'           => 'all',
						'sanitize_callback' => 'sanitize_key',
					),
					'rules' => array(
						'type'              => 'array',
						'default'           => array(),
						'sanitize_callback' => array( $this, 'sanitize_rules' ),
					),
				),
			)
		);
	}

	/**
	 * Sanitize the rules array.
	 *
	 * @param mixed $value Raw rules.
	 * @return array
	 */
	public function sanitize_rules( $value ) {
		if ( ! is_array( $value ) ) {
			return array();
		}
		$allowed_types = array(
			'page_type', 'post_id', 'post_type', 'taxonomy', 'user_state',
			'user_role', 'device', 'date_range', 'url_param', 'language',
		);
		$clean = array();
		foreach ( $value as $rule ) {
			if ( ! is_array( $rule ) || empty( $rule['type'] ) ) {
				continue;
			}
			$type = sanitize_key( $rule['type'] );
			if ( ! in_array( $type, $allowed_types, true ) ) {
				continue;
			}
			$val = isset( $rule['value'] ) ? sanitize_text_field( (string) $rule['value'] ) : '';
			if ( '' === $val ) {
				continue;
			}
			$clean[] = array(
				'type'  => $type,
				'value' => $val,
			);
		}
		return $clean;
	}

	/**
	 * Check whether the current user can manage conditions for this menu.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool|\WP_Error
	 */
	public function check_permission( $request ) {
		$id = (int) $request->get_param( 'id' );
		if ( ! $id ) {
			return false;
		}
		$post = get_post( $id );
		if ( ! $post || WTM_CPT_MENU !== $post->post_type ) {
			return new WP_Error( 'wtm_menu_not_found', __( 'Menu introuvable.', 'woo-total-menu' ), array( 'status' => 404 ) );
		}
		return current_user_can( self::CAPABILITY, $id );
	}

	/**
	 * GET /menus/{id}/conditions
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_conditions( $request ) {
		$id      = (int) $request->get_param( 'id' );
		$raw     = get_post_meta( $id, Condition_Evaluator::META_KEY, true );
		$decoded = is_string( $raw ) ? json_decode( $raw, true ) : $raw;

		if ( ! is_array( $decoded ) ) {
			$decoded = array( 'logic' => 'all', 'rules' => array() );
		}

		return new WP_REST_Response( $decoded, 200 );
	}

	/**
	 * PUT /menus/{id}/conditions
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_conditions( $request ) {
		$id    = (int) $request->get_param( 'id' );
		$logic = $request->get_param( 'logic' );
		$rules = $request->get_param( 'rules' );

		$conditions = array(
			'logic' => 'any' === $logic ? 'any' : 'all',
			'rules' => is_array( $rules ) ? $rules : array(),
		);

		if ( empty( $conditions['rules'] ) ) {
			// No rules = always render — delete the meta to save a DB row.
			delete_post_meta( $id, Condition_Evaluator::META_KEY );
			return new WP_REST_Response( array( 'logic' => 'all', 'rules' => array() ), 200 );
		}

		$encoded = wp_slash( wp_json_encode( $conditions ) );
		update_post_meta( $id, Condition_Evaluator::META_KEY, $encoded );

		// Invalidate caches that may have stored the previous visibility.
		$cache = function_exists( 'wtm' ) ? wtm()->get( 'cache' ) : null;
		if ( $cache && method_exists( $cache, 'invalidate_menu' ) ) {
			$cache->invalidate_menu( $id );
		}

		return new WP_REST_Response( $conditions, 200 );
	}

	/**
	 * DELETE /menus/{id}/conditions
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function delete_conditions( $request ) {
		$id = (int) $request->get_param( 'id' );
		delete_post_meta( $id, Condition_Evaluator::META_KEY );

		$cache = function_exists( 'wtm' ) ? wtm()->get( 'cache' ) : null;
		if ( $cache && method_exists( $cache, 'invalidate_menu' ) ) {
			$cache->invalidate_menu( $id );
		}

		return new WP_REST_Response( array( 'deleted' => true ), 200 );
	}

	/**
	 * POST /menus/{id}/conditions/test — evaluate the supplied conditions
	 * against the current request and return per-rule results.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function test_conditions( $request ) {
		$id    = (int) $request->get_param( 'id' );
		$logic = $request->get_param( 'logic' );
		$rules = $request->get_param( 'rules' );

		$conditions = array(
			'logic' => 'any' === $logic ? 'any' : 'all',
			'rules' => is_array( $rules ) ? $rules : array(),
		);

		// Use the evaluator's static validate to normalize.
		$clean = Condition_Evaluator::validate( $conditions );
		if ( is_wp_error( $clean ) ) {
			return $clean;
		}

		// Force-evaluate by writing a temporary meta (we don't persist).
		$evaluator = new Condition_Evaluator();

		// Reflection trick: temporarily inject the conditions into cache so
		// should_render uses them. Easier: just call evaluate_rule on each
		// rule via a fresh reflection-free approach.
		$rule_results = array();
		$all_passed   = true;
		$any_passed   = false;

		$reflector = new \ReflectionClass( $evaluator );
		$method    = $reflector->getMethod( 'evaluate_rule' );
		$method->setAccessible( true );

		foreach ( $clean['rules'] as $rule ) {
			$ok = (bool) $method->invoke( $evaluator, $rule );
			$rule_results[] = array(
				'type'   => $rule['type'],
				'value'  => $rule['value'],
				'passed' => $ok,
			);
			if ( $ok ) {
				$any_passed = true;
			} else {
				$all_passed = false;
			}
		}

		$overall = ( 'any' === $clean['logic'] ) ? $any_passed : $all_passed;
		if ( empty( $clean['rules'] ) ) {
			$overall = true;
		}

		return new WP_REST_Response(
			array(
				'menu_id'       => $id,
				'logic'         => $clean['logic'],
				'rules'         => $rule_results,
				'overall_match' => $overall,
			),
			200
		);
	}
}
