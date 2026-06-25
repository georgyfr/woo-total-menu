<?php
/**
 * Frontend location interceptor.
 *
 * Hooks into wp_nav_menu_args to detect when a theme requests a registered
 * WTM location (e.g. wtm_primary) and substitutes our Menu_Renderer output
 * for the native walker.
 *
 * Spec reference: §2.4.1 (logique de remplacement), §7.5 (relations et
 * emplacements — "_wtm_location = slug de l'emplacement WordPress").
 *
 * @package WooTotalMenu
 * @since 1.2.0
 */

namespace WooTotalMenu\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

/**
 * Class Location_Interceptor
 *
 * Listens to wp_nav_menu_args (priority 20, after theme registers its
 * own locations) and pre-renders a wtm_menu when one is published for
 * the requested location.
 */
class Location_Interceptor {

        /**
         * Menu_Renderer instance (injected).
         *
         * @var Menu_Renderer
         */
        private $renderer;

        /**
         * Condition_Evaluator instance (lazy).
         *
         * @var \WooTotalMenu\Core\Condition_Evaluator|null
         */
        private $conditions = null;

        /**
         * Track which locations we already rendered, for asset-enqueue decisions.
         *
         * @var array<int,string>
         */
        private $rendered_locations = array();

        /**
         * Constructor.
         *
         * @param Menu_Renderer $renderer Menu renderer.
         */
        public function __construct( Menu_Renderer $renderer ) {
                $this->renderer  = $renderer;
                $this->conditions = new \WooTotalMenu\Core\Condition_Evaluator();
                add_filter( 'wp_nav_menu_args', array( $this, 'intercept' ), 20 );
        }

        /**
         * Intercept wp_nav_menu() calls.
         *
         * If the theme_location corresponds to a registered WTM location AND
         * a wtm_menu post is published for that location, replace the walker
         * with our renderer via a custom fallback callback.
         *
         * @param array $args Nav menu args.
         * @return array Filtered args.
         */
        public function intercept( $args ) {
                $location = $args['theme_location'] ?? '';
                if ( ! $location ) {
                        return $args;
                }

                // Normalize: themes may use 'wtm_primary' directly or 'primary' if
                // they are using the WTM convention. We support both.
                $wtm_location = $this->normalize_location( $location );
                if ( ! $wtm_location ) {
                        return $args;
                }

                $menu_id = $this->find_menu_for_location( $wtm_location );
                if ( ! $menu_id ) {
                        return $args;
                }

                // v1.7.0 — Conditional menus: skip this menu if its conditions
                // don't match the current request.
                if ( ! $this->conditions->should_render( $menu_id ) ) {
                        return $args;
                }

                // Pre-render the menu HTML and stash it for the fallback callback.
                $html = $this->renderer->render_by_id( $menu_id, $wtm_location );
                if ( '' === $html ) {
                        return $args;
                }

                // Mark this location as rendered for asset-loader decisions.
                $this->rendered_locations[] = $wtm_location;

                /**
                 * Fires when a WTM menu is rendered for a theme location.
                 *
                 * @since 1.2.0
                 *
                 * @param int    $menu_id  Post ID.
                 * @param string $location Location slug.
                 */
                do_action( 'wtm_rendered_location', $menu_id, $location );

                // Inject our pre-rendered HTML via the fallback_cb.
                $args['menu']           = '';
                $args['fallback_cb']    = function () use ( $html ) {
                        echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — already escaped in renderer.
                };
                // Force walker to a no-op so WP doesn't try to walk an empty menu.
                $args['walker']         = new \stdClass(); // Will fall back to default if not a Walker subclass.
                $args['echo']           = true;

                return $args;
        }

        /**
         * Normalize a theme_location to a wtm_* location slug.
         *
         * Returns the WTM internal slug ('primary', 'footer', …) or empty
         * string if this isn't a WTM location.
         *
         * @param string $location Theme location slug.
         * @return string
         */
        private function normalize_location( $location ) {
                // Direct wtm_* match.
                if ( 0 === strpos( $location, 'wtm_' ) ) {
                        return substr( $location, 4 );
                }
                // Match against the plugin's own LOCATIONS constant.
                $known = array( 'primary', 'footer', 'sidebar', 'mobile' );
                if ( in_array( $location, $known, true ) ) {
                        return $location;
                }
                /**
                 * Allow themes to map custom theme_locations to WTM locations.
                 *
                 * @since 1.2.0
                 *
                 * @param string $wtm_loc WTM location slug (empty if no match).
                 * @param string $location Original theme_location.
                 */
                return (string) apply_filters( 'wtm_map_theme_location', '', $location );
        }

        /**
         * Find the published wtm_menu post assigned to a given WTM location.
         *
         * Spec §7.5: "Un même emplacement ne peut avoir qu'un seul menu actif
         * à la fois." — we just pick the first match.
         *
         * @param string $wtm_location WTM location slug (primary, footer…).
         * @return int Post ID or 0.
         */
        private function find_menu_for_location( $wtm_location ) {
                // Try cache first (transient).
                $cache_key = 'wtm_loc_' . sanitize_key( $wtm_location );
                $cached    = wp_cache_get( $cache_key, 'wtm_locations' );
                if ( false !== $cached ) {
                        return (int) $cached;
                }

                $menus = get_posts(
                        array(
                                'post_type'      => 'wtm_menu',
                                'post_status'    => 'publish',
                                'meta_key'       => '_wtm_location', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
                                'meta_value'     => $wtm_location, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
                                'posts_per_page' => 1,
                                'fields'         => 'ids',
                                'no_found_rows'  => true,
                        )
                );

                $id = ! empty( $menus ) ? (int) $menus[0] : 0;
                wp_cache_set( $cache_key, $id, 'wtm_locations', 5 * MINUTE_IN_SECONDS );
                return $id;
        }

        /**
         * Get the list of locations actually rendered on this request.
         *
         * Used by Assets_Loader to decide whether to enqueue assets.
         *
         * @return array<int,string>
         */
        public function get_rendered_locations() {
                return $this->rendered_locations;
        }
}
