<?php
/**
 * JSON schema validator for menu configurations.
 *
 * @package WooTotalMenu
 */

namespace WooTotalMenu\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Schema_Validator
 *
 * Validates the JSON structure of _wtm_config, _wtm_header_config and
 * _wtm_footer_config meta-values before they are stored.
 *
 * The schema is intentionally permissive in v1.0.3 (we only check the
 * top-level shape), but will be tightened in v1.0.4 to enforce the
 * full structure of items and widgets.
 */
class Schema_Validator {

	/**
	 * Current schema version.
	 *
	 * @var int
	 */
	const SCHEMA_VERSION = 1;

	/**
	 * Validate a _wtm_config value.
	 *
	 * @param mixed $value Decoded JSON value (array or object).
	 * @return true|\WP_Error True on success, WP_Error on failure.
	 */
	public static function validate_config( $value ) {
		if ( ! is_array( $value ) ) {
			return new \WP_Error(
				'wtm_invalid_config_type',
				__( 'La configuration du menu doit être un objet JSON.', 'woo-total-menu' ),
				array( 'status' => 400 )
			);
		}

		// version field.
		if ( ! isset( $value['version'] ) ) {
			$value['version'] = self::SCHEMA_VERSION;
		}
		if ( ! is_numeric( $value['version'] ) ) {
			return new \WP_Error(
				'wtm_invalid_version',
				__( 'Le champ "version" doit être un entier.', 'woo-total-menu' ),
				array( 'status' => 400 )
			);
		}

		// items field.
		if ( ! isset( $value['items'] ) ) {
			$value['items'] = array();
		}
		if ( ! is_array( $value['items'] ) ) {
			return new \WP_Error(
				'wtm_invalid_items',
				__( 'Le champ "items" doit être un tableau.', 'woo-total-menu' ),
				array( 'status' => 400 )
			);
		}

		// settings field (optional).
		if ( isset( $value['settings'] ) && ! is_array( $value['settings'] ) ) {
			return new \WP_Error(
				'wtm_invalid_settings',
				__( 'Le champ "settings" doit être un objet.', 'woo-total-menu' ),
				array( 'status' => 400 )
			);
		}

		// Recursively validate items (lightweight — full validation in v1.0.4).
		foreach ( $value['items'] as $index => $item ) {
			$check = self::validate_item( $item, "items[{$index}]" );
			if ( is_wp_error( $check ) ) {
				return $check;
			}
		}

		return true;
	}

	/**
	 * Validate a single menu item (lightweight).
	 *
	 * @param mixed  $item Item to validate.
	 * @param string $path Path for error messages.
	 * @return true|\WP_Error
	 */
	public static function validate_item( $item, $path = 'item' ) {
		if ( ! is_array( $item ) ) {
			return new \WP_Error(
				'wtm_invalid_item',
				sprintf(
					/* translators: %s item path */
					__( 'L\'élément %s doit être un objet.', 'woo-total-menu' ),
					$path
				),
				array( 'status' => 400 )
			);
		}

		// id field.
		if ( ! isset( $item['id'] ) || ! is_string( $item['id'] ) ) {
			return new \WP_Error(
				'wtm_missing_item_id',
				sprintf(
					/* translators: %s item path */
					__( 'L\'élément %s doit avoir un champ "id" (string).', 'woo-total-menu' ),
					$path
				),
				array( 'status' => 400 )
			);
		}

		// type field.
		$allowed_types = array( 'link', 'mega_container', 'column', 'widget', 'title', 'separator' );
		if ( ! isset( $item['type'] ) || ! in_array( $item['type'], $allowed_types, true ) ) {
			return new \WP_Error(
				'wtm_invalid_item_type',
				sprintf(
					/* translators: 1: item path, 2: allowed types */
					__( 'L\'élément %1$s doit avoir un "type" parmi : %2$s.', 'woo-total-menu' ),
					$path,
					implode( ', ', $allowed_types )
				),
				array( 'status' => 400 )
			);
		}

		// children (optional, must be array).
		if ( isset( $item['children'] ) && ! is_array( $item['children'] ) ) {
			return new \WP_Error(
				'wtm_invalid_children',
				sprintf(
					/* translators: %s item path */
					__( 'L\'élément %s : "children" doit être un tableau.', 'woo-total-menu' ),
					$path
				),
				array( 'status' => 400 )
			);
		}

		// Recurse on children.
		if ( isset( $item['children'] ) ) {
			foreach ( $item['children'] as $index => $child ) {
				$check = self::validate_item( $child, "{$path}.children[{$index}]" );
				if ( is_wp_error( $check ) ) {
					return $check;
				}
			}
		}

		return true;
	}

	/**
	 * Validate a _wtm_header_config or _wtm_footer_config value.
	 *
	 * Header/Footer configs have a different shape (rows/columns/modules)
	 * than the menu tree. We do a lightweight check here.
	 *
	 * @param mixed $value Decoded JSON value.
	 * @return true|\WP_Error
	 */
	public static function validate_layout( $value ) {
		if ( ! is_array( $value ) ) {
			return new \WP_Error(
				'wtm_invalid_layout_type',
				__( 'La configuration header/footer doit être un objet JSON.', 'woo-total-menu' ),
				array( 'status' => 400 )
			);
		}

		// version field.
		if ( ! isset( $value['version'] ) ) {
			$value['version'] = self::SCHEMA_VERSION;
		}

		// rows field (optional in v1.0.3, will be required in v1.4.x).
		if ( isset( $value['rows'] ) && ! is_array( $value['rows'] ) ) {
			return new \WP_Error(
				'wtm_invalid_rows',
				__( 'Le champ "rows" doit être un tableau.', 'woo-total-menu' ),
				array( 'status' => 400 )
			);
		}

		// settings field (optional).
		if ( isset( $value['settings'] ) && ! is_array( $value['settings'] ) ) {
			return new \WP_Error(
				'wtm_invalid_layout_settings',
				__( 'Le champ "settings" doit être un objet.', 'woo-total-menu' ),
				array( 'status' => 400 )
			);
		}

		return true;
	}

	/**
	 * Normalize a config value: ensure version + items are present.
	 *
	 * @param array $value Decoded config.
	 * @return array
	 */
	public static function normalize_config( $value ) {
		if ( ! is_array( $value ) ) {
			return array(
				'version' => self::SCHEMA_VERSION,
				'items'   => array(),
			);
		}
		if ( ! isset( $value['version'] ) ) {
			$value['version'] = self::SCHEMA_VERSION;
		}
		if ( ! isset( $value['items'] ) ) {
			$value['items'] = array();
		}
		return $value;
	}

	/**
	 * Normalize a header/footer layout config.
	 *
	 * @param array $value Decoded layout.
	 * @return array
	 */
	public static function normalize_layout( $value ) {
		if ( ! is_array( $value ) ) {
			return array(
				'version' => self::SCHEMA_VERSION,
				'rows'    => array(),
			);
		}
		if ( ! isset( $value['version'] ) ) {
			$value['version'] = self::SCHEMA_VERSION;
		}
		if ( ! isset( $value['rows'] ) ) {
			$value['rows'] = array();
		}
		return $value;
	}

	/**
	 * JSON-decode a string and validate as a menu config.
	 *
	 * @param string $raw Raw JSON string.
	 * @return array|\WP_Error Decoded config on success, WP_Error on failure.
	 */
	public static function decode_and_validate_config( $raw ) {
		if ( '' === $raw || null === $raw ) {
			return array(
				'version' => self::SCHEMA_VERSION,
				'items'   => array(),
			);
		}
		$decoded = json_decode( $raw, true );
		if ( null === $decoded && JSON_ERROR_NONE !== json_last_error() ) {
			return new \WP_Error(
				'wtm_invalid_json',
				sprintf(
					/* translators: %s JSON error message */
					__( 'JSON invalide : %s', 'woo-total-menu' ),
					json_last_error_msg()
				),
				array( 'status' => 400 )
			);
		}
		$check = self::validate_config( $decoded );
		if ( is_wp_error( $check ) ) {
			return $check;
		}
		return self::normalize_config( $decoded );
	}

	/**
	 * JSON-decode a string and validate as a header/footer layout.
	 *
	 * @param string $raw Raw JSON string.
	 * @return array|\WP_Error
	 */
	public static function decode_and_validate_layout( $raw ) {
		if ( '' === $raw || null === $raw ) {
			return array();
		}
		$decoded = json_decode( $raw, true );
		if ( null === $decoded && JSON_ERROR_NONE !== json_last_error() ) {
			return new \WP_Error(
				'wtm_invalid_json',
				sprintf(
					/* translators: %s JSON error message */
					__( 'JSON invalide : %s', 'woo-total-menu' ),
					json_last_error_msg()
				),
				array( 'status' => 400 )
			);
		}
		$check = self::validate_layout( $decoded );
		if ( is_wp_error( $check ) ) {
			return $check;
		}
		return self::normalize_layout( $decoded );
	}
}
