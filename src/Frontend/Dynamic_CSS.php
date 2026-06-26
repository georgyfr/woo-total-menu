<?php
/**
 * Dynamic CSS generator.
 *
 * Spec §2.4.3: "Un fichier CSS unique est généré à la volée via Dynamic_CSS,
 * compilé à partir des réglages globaux (couleurs, typo, espacements) et des
 * paramètres de chaque menu actif. Ce CSS est sauvegardé dans le répertoire
 * uploads/cache/wtm-dynamic.css et régénéré lors de la sauvegarde d'un menu
 * ou de la modification des réglages."
 *
 * @package WooTotalMenu
 * @since 1.2.0
 */

namespace WooTotalMenu\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

/**
 * Class Dynamic_CSS
 *
 * Generates a CSS stylesheet from:
 *   - global plugin settings (colors, typography, breakpoint)
 *   - per-menu settings (sticky, alignment, mega fullwidth)
 *
 * Caches the result to uploads/wtm-cache/dynamic-{hash}.css with a hash
 * query string for cache-busting.
 */
class Dynamic_CSS {

        /**
         * Subdirectory inside uploads/ where the dynamic CSS lives.
         *
         * @var string
         */
        const CACHE_DIR = 'wtm-cache';

        /**
         * Build the dynamic CSS for the entire site (all active menus + globals).
         *
         * @return string CSS source.
         */
        public function build() {
                $settings = get_option( WTM_OPTION_SETTINGS );
                if ( ! is_array( $settings ) ) {
                        $settings = array();
                }

                $styles     = $settings['styles'] ?? array();
                $typo       = $settings['typography'] ?? array();
                $responsive = $settings['responsive'] ?? array();

                // CSS custom properties (root).
                $css = ":root{\n";
                $css .= '  --wtm-primary:' . $this->color( $styles['primary_color'] ?? '#6C5CE7' ) . ";\n";
                $css .= '  --wtm-bg:' . $this->color( $styles['background'] ?? '#FFFFFF' ) . ";\n";
                $css .= '  --wtm-text:' . $this->color( $styles['text_color'] ?? '#2D3436' ) . ";\n";
                $css .= '  --wtm-success:' . $this->color( $styles['success_color'] ?? '#00B894' ) . ";\n";
                $css .= '  --wtm-error:' . $this->color( $styles['error_color'] ?? '#FF7675' ) . ";\n";
                $css .= '  --wtm-radius:' . (int) ( $styles['border_radius'] ?? 6 ) . "px;\n";
                $css .= '  --wtm-font:' . $this->font_family( $typo['font_family'] ?? 'Inter' ) . ";\n";
                $css .= '  --wtm-base-size:' . (int) ( $typo['base_size'] ?? 14 ) . "px;\n";
                $css .= '  --wtm-heading-size:' . (int) ( $typo['heading_size'] ?? 18 ) . "px;\n";
                $css .= '  --wtm-breakpoint:' . (int) ( $responsive['mobile_breakpoint'] ?? 768 ) . "px;\n";
                $css .= "}\n\n";

                // Sticky header support (spec §5.8).
                $css .= ".wtm-menu--sticky{position:sticky;top:0;z-index:999;background:var(--wtm-bg);box-shadow:0 2px 8px rgba(0,0,0,0.04);}\n";

                // Alignment variants.
                $css .= ".wtm-menu--align-left .wtm-menu__list--root{justify-content:flex-start;}\n";
                $css .= ".wtm-menu--align-center .wtm-menu__list--root{justify-content:center;}\n";
                $css .= ".wtm-menu--align-right .wtm-menu__list--root{justify-content:flex-end;}\n";

                // Fullwidth mega panel.
                $css .= ".wtm-menu--mega-fullwidth .wtm-menu__mega-panel{left:0;right:0;max-width:100vw;width:100%;}\n";

                /**
                 * Filter the dynamic CSS (spec §2.8.4 — wtm_dynamic_css).
                 *
                 * @since 1.2.0
                 *
                 * @param string $css      Built CSS.
                 * @param array  $settings Global settings.
                 */
                return apply_filters( 'wtm_dynamic_css', $css, $settings );
        }

        /**
         * Get the URL to the cached dynamic CSS file (regenerates if needed).
         *
         * @return string URL or empty string on failure.
         */
        public function get_url() {
                $css = $this->build();
                if ( '' === $css ) {
                        return '';
                }

                $hash     = substr( md5( $css ), 0, 12 );
                $filename = 'dynamic-' . $hash . '.css';

                // Uploads dir.
                $uploads = wp_upload_dir();
                if ( ! empty( $uploads['error'] ) ) {
                        return '';
                }

                $dir = trailingslashit( $uploads['basedir'] ) . self::CACHE_DIR;
                $url = trailingslashit( $uploads['baseurl'] ) . self::CACHE_DIR . '/' . $filename;

                // Create dir if missing.
                if ( ! file_exists( $dir ) ) {
                        wp_mkdir_p( $dir );
                }

                // Add index.php + .htaccess for security (block direct PHP execution).
                $guard_php = trailingslashit( $dir ) . 'index.php';
                if ( ! file_exists( $guard_php ) ) {
                        file_put_contents( $guard_php, "<?php\n// Silence is golden.\n" );
                }
                $guard_htaccess = trailingslashit( $dir ) . '.htaccess';
                if ( ! file_exists( $guard_htaccess ) ) {
                        file_put_contents( $guard_htaccess, "Options -Indexes\n<Files *.php>\n  Deny from all\n</Files>\n" );
                }

                $path = trailingslashit( $dir ) . $filename;

                // Write only if not already cached (filename contains content hash, so
                // same filename = same content — no need for a filesize comparison).
                if ( ! file_exists( $path ) ) {
                        file_put_contents( $path, $css );
                }

                // Hash as query string for additional cache-busting (spec §2.4.3).
                return add_query_arg( 'ver', $hash, $url );
        }

        /**
         * Purge the dynamic CSS cache (called on menu save + settings save).
         *
         * @return void
         */
        public function purge() {
                $uploads = wp_upload_dir();
                if ( ! empty( $uploads['error'] ) ) {
                        return;
                }
                $dir = trailingslashit( $uploads['basedir'] ) . self::CACHE_DIR;
                if ( ! is_dir( $dir ) ) {
                        return;
                }
                foreach ( (array) glob( trailingslashit( $dir ) . 'dynamic-*.css' ) as $file ) {
                        if ( is_file( $file ) ) {
                                @unlink( $file );
                        }
                }
        }

        /**
         * Sanitize a hex/rgb color for CSS embedding.
         *
         * @param string $color Color value.
         * @return string Safe color or fallback.
         */
        private function color( $color ) {
                $color = trim( (string) $color );
                if ( preg_match( '/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $color ) ) {
                        return $color;
                }
                if ( preg_match( '/^rgba?\(\s*\d+\s*,\s*\d+\s*,\s*\d+\s*(,\s*[\d.]+\s*)?\)$/', $color ) ) {
                        return $color;
                }
                return '#000000';
        }

        /**
         * Sanitize a font-family CSS value.
         *
         * @param string $family Font family name.
         * @return string Safe CSS value.
         */
        private function font_family( $family ) {
                $family = trim( (string) $family );
                // Whitelist common system + Google fonts.
                $allowed = array(
                        'Inter', 'Roboto', 'Open Sans', 'Lato', 'Montserrat',
                        'Poppins', 'Arial', 'Helvetica', 'Georgia', 'Times New Roman',
                        'Noto Sans', 'Noto Serif', 'system-ui', 'sans-serif', 'serif',
                );
                if ( in_array( $family, $allowed, true ) ) {
                        return $family;
                }
                // Allow generic CSS keywords.
                if ( in_array( strtolower( $family ), array( 'serif', 'sans-serif', 'monospace', 'cursive', 'fantasy', 'system-ui' ), true ) ) {
                        return $family;
                }
                return 'Inter';
        }
}
