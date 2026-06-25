<?php
/**
 * Frontend AJAX controller.
 *
 * Handles the v1.3.0 frontend-facing endpoints:
 *   - GET /wtm/v1/search-suggest        — live product search suggestions (spec §5.9.5).
 *   - GET /wtm/v1/mini-cart-contents    — cart contents for the drawer mode of mini_cart widget.
 *   - admin-ajax action=wtm_newsletter_subscribe — newsletter subscription form handler.
 *
 * All endpoints are public (the cart & search must work for non-logged-in visitors
 * with a WC session). The newsletter endpoint verifies a nonce to mitigate spam.
 *
 * @package WooTotalMenu
 * @since 1.3.0
 */

namespace WooTotalMenu\Api;

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

/**
 * Class Frontend_Controller
 *
 * Registers the REST routes + the admin-ajax handler.
 */
class Frontend_Controller {

        /**
         * Register hooks.
         */
        public function __construct() {
                add_action( 'rest_api_init', array( $this, 'register_routes' ) );
                add_action( 'wp_ajax_wtm_newsletter_subscribe', array( $this, 'handle_newsletter_subscribe' ) );
                add_action( 'wp_ajax_nopriv_wtm_newsletter_subscribe', array( $this, 'handle_newsletter_subscribe' ) );
        }

        /**
         * Register REST routes.
         *
         * @return void
         */
        public function register_routes() {
                $namespace = WTM_REST_NAMESPACE;

                register_rest_route(
                        $namespace,
                        '/search-suggest',
                        array(
                                'methods'             => \WP_REST_Server::READABLE,
                                'callback'            => array( $this, 'search_suggest' ),
                                'permission_callback' => '__return_true',
                                'args'                => array(
                                        's' => array(
                                                'type'              => 'string',
                                                'required'          => true,
                                                'sanitize_callback' => 'sanitize_text_field',
                                                'validate_callback' => function ( $v ) {
                                                        return is_string( $v ) && mb_strlen( trim( $v ) ) >= 2;
                                                },
                                        ),
                                        'limit' => array(
                                                'type'              => 'integer',
                                                'default'           => 5,
                                                'sanitize_callback' => 'absint',
                                                'validate_callback' => function ( $v ) {
                                                        return $v >= 1 && $v <= 20;
                                                },
                                        ),
                                ),
                        )
                );

                register_rest_route(
                        $namespace,
                        '/mini-cart-contents',
                        array(
                                'methods'             => \WP_REST_Server::READABLE,
                                'callback'            => array( $this, 'mini_cart_contents' ),
                                'permission_callback' => '__return_true',
                        )
                );
        }

        /**
         * Live product search suggestions.
         *
         * Returns up to `limit` product matches for the given query string.
         * Each result has: id, title, permalink, price_html, thumbnail (URL or empty).
         *
         * @param \WP_REST_Request $request Request.
         * @return \WP_REST_Response
         */
        public function search_suggest( \WP_REST_Request $request ) {
                if ( ! class_exists( 'WooCommerce' ) ) {
                        return rest_ensure_response( array( 'products' => array() ) );
                }

                $query = trim( $request->get_param( 's' ) );
                $limit = (int) $request->get_param( 'limit' );

                if ( mb_strlen( $query ) < 2 ) {
                        return rest_ensure_response( array( 'products' => array() ) );
                }

                $args = array(
                        'status'           => 'publish',
                        'limit'            => $limit,
                        'orderby'          => 'relevance',
                        's'                => $query,
                        'return'           => 'ids',
                );

                /**
                 * Filter the WC query args for live search suggestions.
                 *
                 * @since 1.3.0
                 *
                 * @param array  $args   Args for wc_get_products().
                 * @param string $query  Search query.
                 */
                $args = apply_filters( 'wtm_search_suggest_query', $args, $query );

                $ids = function_exists( 'wc_get_products' ) ? wc_get_products( $args ) : array();

                $products = array();
                foreach ( $ids as $pid ) {
                        $product = wc_get_product( $pid );
                        if ( ! $product ) {
                                continue;
                        }

                        $thumb_id = $product->get_image_id();
                        $thumb_url = '';
                        if ( $thumb_id ) {
                                $thumb_url = wp_get_attachment_image_url( $thumb_id, 'thumbnail' ) ?: '';
                        }

                        $products[] = array(
                                'id'         => (int) $pid,
                                'title'      => $product->get_name(),
                                'permalink'  => $product->get_permalink(),
                                'price_html' => $product->get_price_html(),
                                'thumbnail'  => $thumb_url,
                                'on_sale'    => $product->is_on_sale(),
                        );
                }

                return rest_ensure_response(
                        array(
                                'query'    => $query,
                                'count'    => count( $products ),
                                'products' => $products,
                        )
                );
        }

        /**
         * Mini-cart contents — render cart line items for the AJAX drawer.
         *
         * Returns: count, total_html, items[] (key, name, permalink, thumbnail,
         * quantity, price_html), cart_url, checkout_url.
         *
         * @param \WP_REST_Request $request Request.
         * @return \WP_REST_Response
         */
        public function mini_cart_contents( \WP_REST_Request $request ) {
                if ( ! class_exists( 'WooCommerce' ) || ! WC()->cart ) {
                        return rest_ensure_response(
                                array(
                                        'count'    => 0,
                                        'total'    => '',
                                        'items'    => array(),
                                        'cart_url' => '',
                                        'checkout_url' => '',
                                )
                        );
                }

                $cart = WC()->cart;
                $cart->calculate_totals();

                $items = array();
                foreach ( $cart->get_cart() as $key => $item ) {
                        $product = $item['data'];
                        if ( ! $product ) {
                                continue;
                        }
                        $thumb_id  = $product->get_image_id();
                        $thumb_url = $thumb_id ? ( wp_get_attachment_image_url( $thumb_id, 'thumbnail' ) ?: '' ) : '';

                        $items[] = array(
                                'key'        => $key,
                                'product_id' => (int) $product->get_id(),
                                'name'       => $product->get_name(),
                                'permalink'  => $product->get_permalink(),
                                'thumbnail'  => $thumb_url,
                                'quantity'   => (int) $item['quantity'],
                                'price_html' => wc_price( $product->get_price() * $item['quantity'] ),
                        );
                }

                return rest_ensure_response(
                        array(
                                'count'        => (int) $cart->get_cart_contents_count(),
                                'total'        => $cart->get_cart_total(),
                                'subtotal'     => $cart->get_cart_subtotal(),
                                'items'        => $items,
                                'cart_url'     => wc_get_cart_url(),
                                'checkout_url' => wc_get_checkout_url(),
                                'is_empty'     => $cart->is_empty(),
                        )
                );
        }

        /**
         * Newsletter subscribe — admin-ajax handler.
         *
         * Expects POST: email, nonce, (optional) list_id, provider.
         * Stores emails in `wtm_newsletter_subscribers` option when provider=internal.
         *
         * @return void  Sends JSON response.
         */
        public function handle_newsletter_subscribe() {
                check_ajax_referer( 'wtm_newsletter', 'nonce' );

                $email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';

                if ( ! is_email( $email ) ) {
                        wp_send_json_error(
                                array( 'message' => __( 'Adresse email invalide.', 'woo-total-menu' ) ),
                                400
                        );
                        return;
                }

                $provider = isset( $_POST['provider'] ) ? sanitize_key( wp_unslash( $_POST['provider'] ) ) : 'internal';
                $list_id  = isset( $_POST['list_id'] ) ? sanitize_text_field( wp_unslash( $_POST['list_id'] ) ) : '';

                /**
                 * Fires before the newsletter subscription is processed.
                 *
                 * Allow a 3rd-party integration (e.g. Mailchimp API call) to take over.
                 * If a truthy value is returned via `wtm_newsletter_subscription_handled`,
                 * the internal storage is skipped.
                 *
                 * @since 1.3.0
                 *
                 * @param string $email   Subscriber email.
                 * @param string $list_id Optional list ID.
                 */
                $handled = apply_filters( 'wtm_newsletter_subscription_handled', false, $email, $list_id, $provider );

                if ( ! $handled && 'internal' === $provider ) {
                        $this->store_subscriber( $email, $list_id );
                }

                /**
                 * Fires after a successful newsletter subscription.
                 *
                 * @since 1.3.0
                 *
                 * @param string $email   Subscriber email.
                 * @param string $provider Provider slug.
                 * @param string $list_id Optional list ID.
                 */
                do_action( 'wtm_newsletter_subscribed', $email, $provider, $list_id );

                wp_send_json_success(
                        array(
                                'message' => __( 'Merci ! Votre inscription a bien été prise en compte.', 'woo-total-menu' ),
                        )
                );
        }

        /**
         * Store a subscriber email in the option (deduplicated).
         *
         * @param string $email   Subscriber email.
         * @param string $list_id Optional list ID.
         * @return void
         */
        private function store_subscriber( $email, $list_id ) {
                $subs = get_option( 'wtm_newsletter_subscribers', array() );
                if ( ! is_array( $subs ) ) {
                        $subs = array();
                }

                // Deduplicate by email+list_id.
                $exists = false;
                foreach ( $subs as $s ) {
                        if ( isset( $s['email'] ) && $s['email'] === $email && ( $s['list_id'] ?? '' ) === $list_id ) {
                                $exists = true;
                                break;
                        }
                }

                if ( ! $exists ) {
                        $subs[] = array(
                                'email'      => $email,
                                'list_id'    => $list_id,
                                'subscribed' => current_time( 'mysql' ),
                                'ip'         => $this->anon_ip(),
                        );
                        update_option( 'wtm_newsletter_subscribers', $subs, false );
                }
        }

        /**
         * Get an anonymized IP (last octet zeroed) for GDPR-friendly storage.
         *
         * @return string
         */
        private function anon_ip() {
                $ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
                if ( ! $ip ) {
                        return '';
                }
                // IPv4 — replace last octet.
                if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
                        $parts = explode( '.', $ip );
                        $parts[3] = '0';
                        return implode( '.', $parts );
                }
                // IPv6 — truncate to /64.
                if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
                        $parts = explode( ':', $ip );
                        return implode( ':', array_slice( $parts, 0, 4 ) ) . '::';
                }
                return '';
        }
}
