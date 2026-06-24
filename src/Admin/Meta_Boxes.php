<?php
/**
 * Meta boxes for the wtm_menu CPT.
 *
 * @package WooTotalMenu
 */

namespace WooTotalMenu\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WooTotalMenu\Core\CPT_Manager;

/**
 * Class Meta_Boxes
 *
 * Registers and renders the meta boxes attached to a wtm_menu post:
 *
 *  - _wtm_location       (primary|footer|sidebar|mobile)
 *  - _wtm_menu_type      (horizontal|vertical|offcanvas|footer)
 *  - _wtm_config         (JSON: full menu tree, items + settings)
 *  - _wtm_header_config  (JSON: header builder layout, optional)
 *  - _wtm_footer_config  (JSON: footer builder layout, optional)
 *  - _wtm_version        (int: schema version for migrations)
 *
 * Each meta is registered via register_post_meta() so it is automatically
 * exposed through the REST API and validated against a schema.
 */
class Meta_Boxes {

	const META_LOCATION      = '_wtm_location';
	const META_MENU_TYPE     = '_wtm_menu_type';
	const META_CONFIG        = '_wtm_config';
	const META_HEADER_CONFIG = '_wtm_header_config';
	const META_FOOTER_CONFIG = '_wtm_footer_config';
	const META_VERSION       = '_wtm_version';

	/**
	 * Constructor — registers hooks.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_meta' ), 20 );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post_' . CPT_Manager::POST_TYPE, array( $this, 'save' ), 10, 2 );
	}

	/**
	 * Register all metadata via register_post_meta() so they're
	 * REST-visible and validated.
	 *
	 * @return void
	 */
	public function register_meta() {
		$default_settings = \WooTotalMenu\Bootstrap::default_settings();

		register_post_meta(
			CPT_Manager::POST_TYPE,
			self::META_LOCATION,
			array(
				'type'         => 'string',
				'single'       => true,
				'default'      => $default_settings['general']['default_location'] ?? 'primary',
				'show_in_rest' => true,
				'sanitize_callback' => array( $this, 'sanitize_location' ),
				'auth_callback'     => array( $this, 'auth_meta' ),
			)
		);

		register_post_meta(
			CPT_Manager::POST_TYPE,
			self::META_MENU_TYPE,
			array(
				'type'         => 'string',
				'single'       => true,
				'default'      => 'horizontal',
				'show_in_rest' => true,
				'sanitize_callback' => array( $this, 'sanitize_menu_type' ),
				'auth_callback'     => array( $this, 'auth_meta' ),
			)
		);

		register_post_meta(
			CPT_Manager::POST_TYPE,
			self::META_CONFIG,
			array(
				'type'         => 'string',
				'single'       => true,
				'default'      => '{"version":1,"items":[]}',
				'show_in_rest' => array(
					'prepare_callback' => array( $this, 'rest_prepare_json' ),
				),
				'sanitize_callback' => array( $this, 'sanitize_json' ),
				'auth_callback'     => array( $this, 'auth_meta' ),
			)
		);

		register_post_meta(
			CPT_Manager::POST_TYPE,
			self::META_HEADER_CONFIG,
			array(
				'type'         => 'string',
				'single'       => true,
				'default'      => '',
				'show_in_rest' => array(
					'prepare_callback' => array( $this, 'rest_prepare_json' ),
				),
				'sanitize_callback' => array( $this, 'sanitize_json_optional' ),
				'auth_callback'     => array( $this, 'auth_meta' ),
			)
		);

		register_post_meta(
			CPT_Manager::POST_TYPE,
			self::META_FOOTER_CONFIG,
			array(
				'type'         => 'string',
				'single'       => true,
				'default'      => '',
				'show_in_rest' => array(
					'prepare_callback' => array( $this, 'rest_prepare_json' ),
				),
				'sanitize_callback' => array( $this, 'sanitize_json_optional' ),
				'auth_callback'     => array( $this, 'auth_meta' ),
			)
		);

		register_post_meta(
			CPT_Manager::POST_TYPE,
			self::META_VERSION,
			array(
				'type'         => 'integer',
				'single'       => true,
				'default'      => WTM_DB_VERSION,
				'show_in_rest' => true,
				'sanitize_callback' => 'absint',
				'auth_callback'     => array( $this, 'auth_meta' ),
			)
		);
	}

	/**
	 * Permission check for editing meta via REST.
	 *
	 * @param bool   $allowed Whether auth is allowed.
	 * @param string $meta_key Meta key.
	 * @param int    $post_id  Post ID.
	 * @return bool
	 */
	public function auth_meta( $allowed, $meta_key = '', $post_id = 0 ) {
		return current_user_can( 'wtm_manage_menus', $post_id );
	}

	/**
	 * Sanitize the _wtm_location meta.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	public function sanitize_location( $value ) {
		$locations = array_keys( CPT_Manager::get_locations() );
		if ( ! in_array( $value, $locations, true ) ) {
			return 'primary';
		}
		return $value;
	}

	/**
	 * Sanitize the _wtm_menu_type meta.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	public function sanitize_menu_type( $value ) {
		$types = array_keys( CPT_Manager::get_menu_types() );
		if ( ! in_array( $value, $types, true ) ) {
			return 'horizontal';
		}
		return $value;
	}

	/**
	 * Sanitize a JSON-encoded string (required).
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	public function sanitize_json( $value ) {
		$decoded = json_decode( $value, true );
		if ( null === $decoded && JSON_ERROR_NONE !== json_last_error() ) {
			return '{"version":1,"items":[]}';
		}
		return wp_slash( wp_json_encode( $decoded ) );
	}

	/**
	 * Sanitize a JSON-encoded string (optional — empty allowed).
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	public function sanitize_json_optional( $value ) {
		if ( '' === $value || null === $value ) {
			return '';
		}
		$decoded = json_decode( $value, true );
		if ( null === $decoded && JSON_ERROR_NONE !== json_last_error() ) {
			return '';
		}
		return wp_slash( wp_json_encode( $decoded ) );
	}

	/**
	 * Prepare a JSON meta for REST output (decode it as object/array).
	 *
	 * @param mixed  $value   Raw meta value.
	 * @param string $request REST request param (unused).
	 * @param array  $args    Args (unused).
	 * @return mixed
	 */
	public function rest_prepare_json( $value, $request = null, $args = array() ) {
		if ( '' === $value || null === $value ) {
			return null;
		}
		$decoded = json_decode( $value, true );
		return null === $decoded ? $value : $decoded;
	}

	/**
	 * Add the meta boxes on the wtm_menu edit screen.
	 *
	 * @return void
	 */
	public function add_meta_boxes() {
		add_meta_box(
			'wtm_menu_settings',
			__( 'Réglages du menu', 'woo-total-menu' ),
			array( $this, 'render_settings_box' ),
			CPT_Manager::POST_TYPE,
			'side',
			'high'
		);

		add_meta_box(
			'wtm_menu_config',
			__( 'Configuration JSON', 'woo-total-menu' ),
			array( $this, 'render_config_box' ),
			CPT_Manager::POST_TYPE,
			'normal',
			'high'
		);
	}

	/**
	 * Render the side settings meta box.
	 *
	 * @param \WP_Post $post Post object.
	 * @return void
	 */
	public function render_settings_box( $post ) {
		wp_nonce_field( 'wtm_save_meta', 'wtm_meta_nonce' );

		$location  = get_post_meta( $post->ID, self::META_LOCATION, true ) ?: 'primary';
		$menu_type = get_post_meta( $post->ID, self::META_MENU_TYPE, true ) ?: 'horizontal';
		$version   = get_post_meta( $post->ID, self::META_VERSION, true ) ?: WTM_DB_VERSION;
		?>
		<p>
			<label for="wtm_location"><strong><?php esc_html_e( 'Emplacement', 'woo-total-menu' ); ?></strong></label><br>
			<select name="wtm_location" id="wtm_location" class="widefat" style="margin-top:4px;">
				<?php foreach ( CPT_Manager::get_locations() as $slug => $label ) : ?>
					<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $location, $slug ); ?>>
						<?php echo esc_html( $label ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</p>
		<p>
			<label for="wtm_menu_type"><strong><?php esc_html_e( 'Type de menu', 'woo-total-menu' ); ?></strong></label><br>
			<select name="wtm_menu_type" id="wtm_menu_type" class="widefat" style="margin-top:4px;">
				<?php foreach ( CPT_Manager::get_menu_types() as $slug => $label ) : ?>
					<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $menu_type, $slug ); ?>>
						<?php echo esc_html( $label ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</p>
		<p>
			<label for="wtm_version"><strong><?php esc_html_e( 'Version du schéma', 'woo-total-menu' ); ?></strong></label><br>
			<input type="number" name="wtm_version" id="wtm_version" class="widefat"
				value="<?php echo esc_attr( (string) $version ); ?>" readonly
				style="margin-top:4px;background:#f0f0f1;">
			<em style="display:block;margin-top:4px;color:#666;font-size:11px;">
				<?php esc_html_e( 'Géré automatiquement par le plugin.', 'woo-total-menu' ); ?>
			</em>
		</p>
		<hr>
		<p style="font-size:12px;color:#666;">
			<span class="dashicons dashicons-info" style="vertical-align:middle;"></span>
			<?php
			echo wp_kses(
				sprintf(
					/* translators: %s builder link */
					__( 'Le builder visuel sera disponible en <strong>v1.1.0</strong>. Pour l\'instant, vous pouvez éditer la configuration JSON ci-dessous.', 'woo-total-menu' )
				),
				array( 'strong' => array() )
			);
			?>
		</p>
		<?php
	}

	/**
	 * Render the JSON config meta box.
	 *
	 * @param \WP_Post $post Post object.
	 * @return void
	 */
	public function render_config_box( $post ) {
		$config        = get_post_meta( $post->ID, self::META_CONFIG, true ) ?: '{"version":1,"items":[]}';
		$header_config = get_post_meta( $post->ID, self::META_HEADER_CONFIG, true ) ?: '';
		$footer_config = get_post_meta( $post->ID, self::META_FOOTER_CONFIG, true ) ?: '';
		?>
		<p>
			<label for="wtm_config"><strong><?php esc_html_e( 'Configuration du menu (_wtm_config)', 'woo-total-menu' ); ?></strong></label>
			<textarea name="wtm_config" id="wtm_config" class="widefat" rows="10"
				style="font-family:Menlo,Monaco,monospace;font-size:12px;"><?php echo esc_textarea( $config ); ?></textarea>
		</p>
		<p>
			<label for="wtm_header_config"><strong><?php esc_html_e( 'Configuration du header (_wtm_header_config) — optionnel', 'woo-total-menu' ); ?></strong></label>
			<textarea name="wtm_header_config" id="wtm_header_config" class="widefat" rows="5"
				placeholder="<?php esc_attr_e( 'Laisser vide si pas de header lié', 'woo-total-menu' ); ?>"
				style="font-family:Menlo,Monaco,monospace;font-size:12px;"><?php echo esc_textarea( $header_config ); ?></textarea>
		</p>
		<p>
			<label for="wtm_footer_config"><strong><?php esc_html_e( 'Configuration du footer (_wtm_footer_config) — optionnel', 'woo-total-menu' ); ?></strong></label>
			<textarea name="wtm_footer_config" id="wtm_footer_config" class="widefat" rows="5"
				placeholder="<?php esc_attr_e( 'Laisser vide si pas de footer lié', 'woo-total-menu' ); ?>"
				style="font-family:Menlo,Monaco,monospace;font-size:12px;"><?php echo esc_textarea( $footer_config ); ?></textarea>
		</p>
		<?php
	}

	/**
	 * Save meta values when the post is saved.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 * @return void
	 */
	public function save( $post_id, $post ) {
		// Don't save on autosave.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		// Check post type.
		if ( CPT_Manager::POST_TYPE !== $post->post_type ) {
			return;
		}
		// Check nonce.
		if ( ! isset( $_POST['wtm_meta_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['wtm_meta_nonce'] ), 'wtm_save_meta' ) ) {
			return;
		}
		// Check capability.
		if ( ! current_user_can( 'wtm_manage_menus', $post_id ) ) {
			return;
		}

		// _wtm_location.
		if ( isset( $_POST['wtm_location'] ) ) {
			update_post_meta( $post_id, self::META_LOCATION, $this->sanitize_location( sanitize_text_field( wp_unslash( $_POST['wtm_location'] ) ) ) );
		}

		// _wtm_menu_type.
		if ( isset( $_POST['wtm_menu_type'] ) ) {
			update_post_meta( $post_id, self::META_MENU_TYPE, $this->sanitize_menu_type( sanitize_text_field( wp_unslash( $_POST['wtm_menu_type'] ) ) ) );
		}

		// _wtm_config (required).
		if ( isset( $_POST['wtm_config'] ) ) {
			$raw = wp_unslash( $_POST['wtm_config'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			update_post_meta( $post_id, self::META_CONFIG, $this->sanitize_json( $raw ) );
		}

		// _wtm_header_config (optional).
		if ( isset( $_POST['wtm_header_config'] ) ) {
			$raw = wp_unslash( $_POST['wtm_header_config'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			update_post_meta( $post_id, self::META_HEADER_CONFIG, $this->sanitize_json_optional( $raw ) );
		}

		// _wtm_footer_config (optional).
		if ( isset( $_POST['wtm_footer_config'] ) ) {
			$raw = wp_unslash( $_POST['wtm_footer_config'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			update_post_meta( $post_id, self::META_FOOTER_CONFIG, $this->sanitize_json_optional( $raw ) );
		}

		// _wtm_version — ensure set.
		if ( ! get_post_meta( $post_id, self::META_VERSION, true ) ) {
			update_post_meta( $post_id, self::META_VERSION, WTM_DB_VERSION );
		}

		// Invalidate cache for this menu.
		if ( function_exists( 'wtm' ) ) {
			$cache = wtm()->get( 'cache' );
			if ( $cache ) {
				$cache->invalidate_menu( $post_id );
			}
		}
	}
}
