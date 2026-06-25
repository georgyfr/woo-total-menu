<?php
/**
 * Conditional menus evaluator.
 *
 * v1.7.0 — Adds support for visibility rules attached to a wtm_menu post.
 * Each menu can declare a set of conditions (rules) that must all/any be
 * true for the menu (and its header/footer layouts) to render.
 *
 * Conditions are stored as JSON in the post meta `_wtm_conditions`:
 *
 *   {
 *     "logic": "all",            // "all" (AND) or "any" (OR)
 *     "rules": [
 *       { "type": "page_type", "value": "shop" },
 *       { "type": "user_state", "value": "logged_in" },
 *       { "type": "device", "value": "mobile" }
 *     ]
 *   }
 *
 * Supported rule types:
 *   - page_type    : front_page | home | single | page | archive | search | 404 |
 *                    shop | product | cart | checkout | account | product_category
 *   - post_id      : specific post ID
 *   - post_type    : post | page | product | any CPT slug
 *   - taxonomy     : {taxonomy}:{term_id} or {taxonomy}:slug
 *   - user_state   : logged_in | logged_out
 *   - user_role    : administrator | editor | customer | …
 *   - device       : mobile | tablet | desktop
 *   - date_range   : {start}..{end} (ISO dates, e.g. 2026-01-01..2026-12-31)
 *   - url_param    : {key}={value} (value optional; * = any)
 *   - language     : WPML/Polylang language code (en, fr, …)
 *
 * @package WooTotalMenu
 * @since 1.7.0
 */

namespace WooTotalMenu\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Condition_Evaluator
 *
 * Pure runtime evaluator — no side effects, no DB writes. Designed so it
 * can be called many times per request (cache results in static property).
 */
class Condition_Evaluator {

	const META_KEY = '_wtm_conditions';

	/**
	 * Per-request cache of evaluation results keyed by menu ID.
	 *
	 * @var array<int,bool>
	 */
	private $cache = array();

	/**
	 * Evaluate whether the given menu should render on the current request.
	 *
	 * @param int $menu_id Post ID of the wtm_menu.
	 * @return bool True if the menu should render (no conditions = always true).
	 */
	public function should_render( $menu_id ) {
		$menu_id = (int) $menu_id;
		if ( ! $menu_id ) {
			return true;
		}

		if ( isset( $this->cache[ $menu_id ] ) ) {
			return $this->cache[ $menu_id ];
		}

		$raw = get_post_meta( $menu_id, self::META_KEY, true );
		if ( empty( $raw ) ) {
			$this->cache[ $menu_id ] = true;
			return true;
		}

		$conditions = is_string( $raw ) ? json_decode( $raw, true ) : $raw;
		if ( ! is_array( $conditions ) || empty( $conditions['rules'] ) ) {
			$this->cache[ $menu_id ] = true;
			return true;
		}

		$logic = ( 'any' === ( $conditions['logic'] ?? 'all' ) ) ? 'any' : 'all';
		$rules = $conditions['rules'];

		$result = true;
		foreach ( $rules as $rule ) {
			$ok = $this->evaluate_rule( $rule );

			if ( 'any' === $logic && $ok ) {
				// Short-circuit OR.
				$result = true;
				break;
			}
			if ( 'all' === $logic && ! $ok ) {
				// Short-circuit AND.
				$result = false;
				break;
			}
		}

		/**
		 * Filter the final visibility result for a menu.
		 *
		 * @since 1.7.0
		 *
		 * @param bool  $result Whether the menu should render.
		 * @param int   $menu_id Menu post ID.
		 * @param array $conditions Decoded conditions array.
		 */
		$result = (bool) apply_filters( 'wtm_condition_result', $result, $menu_id, $conditions );

		$this->cache[ $menu_id ] = $result;
		return $result;
	}

	/**
	 * Evaluate a single rule against the current request.
	 *
	 * @param array $rule Rule with `type` and `value` keys.
	 * @return bool
	 */
	private function evaluate_rule( $rule ) {
		$type  = $rule['type'] ?? '';
		$value = $rule['value'] ?? '';

		switch ( $type ) {
			case 'page_type':
				return $this->eval_page_type( $value );
			case 'post_id':
				return $this->eval_post_id( $value );
			case 'post_type':
				return $this->eval_post_type( $value );
			case 'taxonomy':
				return $this->eval_taxonomy( $value );
			case 'user_state':
				return $this->eval_user_state( $value );
			case 'user_role':
				return $this->eval_user_role( $value );
			case 'device':
				return $this->eval_device( $value );
			case 'date_range':
				return $this->eval_date_range( $value );
			case 'url_param':
				return $this->eval_url_param( $value );
			case 'language':
				return $this->eval_language( $value );
			default:
				/**
				 * Allow custom rule types to be evaluated.
				 *
				 * @since 1.7.0
				 *
				 * @param bool  $result Default false (unknown rule).
				 * @param mixed $value  Rule value.
				 */
				return (bool) apply_filters( 'wtm_condition_rule_' . $type, false, $value );
		}
	}

	/**
	 * page_type rule.
	 *
	 * @param string $value One of the supported page types.
	 * @return bool
	 */
	private function eval_page_type( $value ) {
		switch ( $value ) {
			case 'front_page':
				return is_front_page();
			case 'home':
				return is_home();
			case 'single':
				return is_single();
			case 'page':
				return is_page();
			case 'archive':
				return is_archive();
			case 'search':
				return is_search();
			case '404':
				return is_404();
			case 'shop':
				return function_exists( 'is_shop' ) && is_shop();
			case 'product':
				return function_exists( 'is_product' ) && is_product();
			case 'cart':
				return function_exists( 'is_cart' ) && is_cart();
			case 'checkout':
				return function_exists( 'is_checkout' ) && is_checkout();
			case 'account':
				return function_exists( 'is_account_page' ) && is_account_page();
			case 'product_category':
				return function_exists( 'is_product_category' ) && is_product_category();
			case 'product_tag':
				return function_exists( 'is_product_tag' ) && is_product_tag();
		}
		return false;
	}

	/**
	 * post_id rule.
	 *
	 * @param mixed $value Post ID (int or numeric string). Comma-separated list supported.
	 * @return bool
	 */
	private function eval_post_id( $value ) {
		if ( ! is_singular() ) {
			return false;
		}
		$ids = array_filter( array_map( 'absint', explode( ',', (string) $value ) ) );
		return in_array( (int) get_queried_object_id(), $ids, true );
	}

	/**
	 * post_type rule.
	 *
	 * @param string $value Post type slug.
	 * @return bool
	 */
	private function eval_post_type( $value ) {
		if ( ! is_singular() ) {
			return false;
		}
		$post = get_queried_object();
		return $post && isset( $post->post_type ) && $post->post_type === $value;
	}

	/**
	 * taxonomy rule — value format: "{taxonomy}:{term}".
	 *
	 * Term can be a numeric ID or a slug. Wildcard taxonomy "*" matches any.
	 *
	 * @param string $value e.g. "category:news" or "product_cat:15".
	 * @return bool
	 */
	private function eval_taxonomy( $value ) {
		$parts = explode( ':', (string) $value, 2 );
		if ( count( $parts ) < 2 ) {
			return false;
		}
		list( $tax, $term ) = $parts;

		// WooCommerce product category / tag shortcuts.
		if ( 'product_cat' === $tax && function_exists( 'is_product_category' ) ) {
			return is_product_category( $term );
		}
		if ( 'product_tag' === $tax && function_exists( 'is_product_tag' ) ) {
			return is_product_tag( $term );
		}

		if ( '' === $term ) {
			return is_tax( $tax );
		}
		return is_tax( $tax, $term );
	}

	/**
	 * user_state rule.
	 *
	 * @param string $value logged_in | logged_out.
	 * @return bool
	 */
	private function eval_user_state( $value ) {
		$logged_in = is_user_logged_in();
		return 'logged_in' === $value ? $logged_in : ! $logged_in;
	}

	/**
	 * user_role rule.
	 *
	 * @param string $value Role slug. Comma-separated list supported.
	 * @return bool
	 */
	private function eval_user_role( $value ) {
		$user = wp_get_current_user();
		if ( ! $user || empty( $user->ID ) ) {
			return false;
		}
		$roles = array_filter( array_map( 'sanitize_key', explode( ',', (string) $value ) ) );
		return ! empty( array_intersect( $roles, (array) $user->roles ) );
	}

	/**
	 * device rule — uses a lightweight User-Agent heuristic.
	 *
	 * Frontend JS can refine this at runtime, but the server-side check is
	 * sufficient for first-paint decisions (no flash of the wrong menu).
	 *
	 * @param string $value mobile | tablet | desktop.
	 * @return bool
	 */
	private function eval_device( $value ) {
		$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		$is_tablet  = ( false !== strpos( $ua, 'iPad' ) ) || ( false !== strpos( $ua, 'Tablet' ) ) || ( false !== strpos( $ua, 'Silk' ) );
		$is_mobile  = ! $is_tablet && ( ( false !== strpos( $ua, 'Mobile' ) ) || ( false !== strpos( $ua, 'Android' ) ) || ( false !== strpos( $ua, 'iPhone' ) ) );

		switch ( $value ) {
			case 'mobile':
				return $is_mobile;
			case 'tablet':
				return $is_tablet;
			case 'desktop':
				return ! $is_mobile && ! $is_tablet;
		}
		return false;
	}

	/**
	 * date_range rule — value format: "{start}..{end}".
	 *
	 * Either side can be empty (one-sided range). Dates are compared in
	 * the site's timezone.
	 *
	 * @param string $value e.g. "2026-01-01..2026-12-31".
	 * @return bool
	 */
	private function eval_date_range( $value ) {
		$parts = explode( '..', (string) $value, 2 );
		if ( count( $parts ) < 2 ) {
			return false;
		}
		list( $start, $end ) = $parts;
		$now = current_time( 'Y-m-d' );

		if ( '' !== $start && $now < $start ) {
			return false;
		}
		if ( '' !== $end && $now > $end ) {
			return false;
		}
		return true;
	}

	/**
	 * url_param rule — value format: "key=value" or "key=*" or "key".
	 *
	 * @param string $value e.g. "utm_source=newsletter".
	 * @return bool
	 */
	private function eval_url_param( $value ) {
		if ( false === strpos( $value, '=' ) ) {
			return isset( $_GET[ $value ] );
		}
		list( $key, $val ) = explode( '=', $value, 2 );
		if ( ! isset( $_GET[ $key ] ) ) {
			return false;
		}
		if ( '*' === $val ) {
			return true;
		}
		return sanitize_text_field( wp_unslash( $_GET[ $key ] ) ) === $val;
	}

	/**
	 * language rule — supports WPML and Polylang.
	 *
	 * @param string $value Language code (e.g. "en", "fr").
	 * @return bool
	 */
	private function eval_language( $value ) {
		$current = '';
		// WPML.
		if ( defined( 'ICL_SITEPRESS_VERSION' ) && function_exists( 'apply_filters' ) ) {
			$current = (string) apply_filters( 'wpml_current_language', '' );
		}
		// Polylang.
		if ( '' === $current && function_exists( 'pll_current_language' ) ) {
			$current = (string) pll_current_language( 'slug' );
		}
		return $current === $value;
	}

	/**
	 * Validate and sanitize a conditions array.
	 *
	 * Used by the REST controller before saving to post meta.
	 *
	 * @param mixed $input Decoded JSON input.
	 * @return array|\WP_Error Clean conditions array or error.
	 */
	public static function validate( $input ) {
		if ( ! is_array( $input ) ) {
			return new \WP_Error( 'wtm_conditions_invalid', __( 'Conditions: format invalide.', 'woo-total-menu' ) );
		}
		// Empty or no rules = always render.
		if ( empty( $input ) || empty( $input['rules'] ) ) {
			return array( 'logic' => 'all', 'rules' => array() );
		}

		$logic  = ( 'any' === ( $input['logic'] ?? 'all' ) ) ? 'any' : 'all';
		$raw    = $input['rules'];
		if ( ! is_array( $raw ) ) {
			return new \WP_Error( 'wtm_conditions_invalid', __( 'Conditions: rules doit être un tableau.', 'woo-total-menu' ) );
		}

		$allowed_types = array(
			'page_type', 'post_id', 'post_type', 'taxonomy', 'user_state',
			'user_role', 'device', 'date_range', 'url_param', 'language',
		);

		$clean = array();
		foreach ( $raw as $rule ) {
			if ( ! is_array( $rule ) ) {
				continue;
			}
			$type = sanitize_key( $rule['type'] ?? '' );
			if ( ! in_array( $type, $allowed_types, true ) ) {
				continue;
			}
			$value = is_scalar( $rule['value'] ?? '' ) ? sanitize_text_field( (string) $rule['value'] ) : '';
			if ( '' === $value ) {
				continue;
			}
			$clean[] = array(
				'type'  => $type,
				'value' => $value,
			);
		}

		return array(
			'logic' => $logic,
			'rules' => $clean,
		);
	}
}
