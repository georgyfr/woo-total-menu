<?php
/**
 * Roles Manager — Woo Total Menu.
 *
 * Centralise la gestion des rôles WordPress et des capacités Woo Total Menu.
 * Ajoute (v1.6.0) la possibilité de créer / supprimer des rôles personnalisés
 * dédiés au plugin, et expose une API statique consommée par :
 *
 *   - Settings page (onglet Permissions) ;
 *   - Roles_Controller (REST /wtm/v1/roles) ;
 *   - Permissions::user_can() côté admin.
 *
 * Caps gérées :
 *
 *   High-level (utilisées dans les pages admin & REST) :
 *     - wtm_manage_menus
 *     - wtm_manage_templates
 *     - wtm_view_analytics
 *     - wtm_manage_settings
 *
 *   CPT primitives (générées par capability_type => array('wtm_menu','wtm_menus')) :
 *     - edit_wtm_menu, edit_wtm_menus, edit_others_wtm_menus, publish_wtm_menus
 *     - read_private_wtm_menus, delete_wtm_menu, delete_wtm_menus,
 *       delete_others_wtm_menus, delete_private_wtm_menus,
 *       delete_published_wtm_menus, edit_private_wtm_menus,
 *       edit_published_wtm_menus, create_wtm_menus
 *
 * @package WooTotalMenu\Core
 * @since 1.6.0
 */

namespace WooTotalMenu\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Roles_Manager.
 *
 * Toutes les méthodes sont statiques — aucune instanciation nécessaire.
 * Le manager s'appuie sur l'API WordPress wp_roles() / get_role() / add_role().
 */
final class Roles_Manager {

	/**
	 * Préfixe pour les rôles personnalisés créés par le plugin.
	 *
	 * Permet d'identifier les rôles gérés par WTM (et seulement les supprimer).
	 *
	 * @var string
	 */
	const CUSTOM_ROLE_PREFIX = 'wtm_';

	/**
	 * Option WordPress qui stocke la liste des rôles personnalisés créés.
	 *
	 * @var string
	 */
	const CUSTOM_ROLES_OPTION = 'wtm_custom_roles';

	/**
	 * Récupère la liste des capacités high-level gérées par WTM.
	 *
	 * @return array<string,string> Map cap_slug => default_required_cap.
	 */
	public static function get_high_level_caps() {
		return Permissions::HIGH_LEVEL_CAPS;
	}

	/**
	 * Récupère la liste des capacités primitives du CPT.
	 *
	 * @return array<int,string>
	 */
	public static function get_cpt_primitive_caps() {
		return Permissions::CPT_PRIMITIVE_CAPS;
	}

	/**
	 * Récupère la liste complète des caps WTM (high-level + primitives).
	 *
	 * @return array<int,string>
	 */
	public static function get_all_caps() {
		return array_merge(
			array_keys( self::get_high_level_caps() ),
			self::get_cpt_primitive_caps()
		);
	}

	/**
	 * Récupère la liste des rôles WordPress avec leurs caps WTM.
	 *
	 * @return array<string,array> {
	 *     @type string   $name        Nom affiché du rôle.
	 *     @type bool     $is_custom   True si créé par WTM.
	 *     @type array    $caps        Map cap => bool des caps WTM possédées.
	 *     @type int      $user_count  Nombre d'utilisateurs avec ce rôle.
	 * }
	 */
	public static function get_all_roles() {
		$roles       = wp_roles()->roles;
		$custom_meta = get_option( self::CUSTOM_ROLES_OPTION, array() );
		$custom_meta = is_array( $custom_meta ) ? $custom_meta : array();
		$all_caps    = self::get_all_caps();
		$out         = array();

		foreach ( $roles as $slug => $info ) {
			$role = get_role( $slug );
			if ( ! $role ) {
				continue;
			}
			$caps = array();
			foreach ( $all_caps as $cap ) {
				$caps[ $cap ] = ! empty( $role->capabilities[ $cap ] );
			}

			// Compter les utilisateurs avec ce rôle (cache via count_users).
			static $user_counts = null;
			if ( null === $user_counts ) {
				$user_counts = count_users();
			}
			$user_count = isset( $user_counts['avail_roles'][ $slug ] ) ? (int) $user_counts['avail_roles'][ $slug ] : 0;

			$out[ $slug ] = array(
				'slug'        => $slug,
				'name'        => $info['name'],
				'is_custom'   => in_array( $slug, $custom_meta, true ),
				'caps'        => $caps,
				'user_count'  => $user_count,
				'is_admin'    => 'administrator' === $slug,
			);
		}
		return $out;
	}

	/**
	 * Récupère un rôle individuel avec ses caps.
	 *
	 * @param string $slug Slug du rôle.
	 * @return array|null Null si le rôle n'existe pas.
	 */
	public static function get_role( $slug ) {
		$slug  = sanitize_key( $slug );
		$roles = self::get_all_roles();
		return $roles[ $slug ] ?? null;
	}

	/**
	 * Crée un nouveau rôle personnalisé avec un subset de caps WTM.
	 *
	 * @param string $slug       Slug du rôle (sera préfixé `wtm_` si non déjà).
	 * @param string $name       Nom affiché.
	 * @param array  $caps       Caps à accorder (high-level + CPT primitives).
	 * @return string|\WP_Error  Slug final du rôle créé, ou WP_Error.
	 */
	public static function create_role( $slug, $name, $caps = array() ) {
		$slug = sanitize_key( $slug );
		$name = sanitize_text_field( $name );

		if ( '' === $slug || '' === $name ) {
			return new \WP_Error( 'wtm_role_invalid', __( 'Slug et nom du rôle sont requis.', 'woo-total-menu' ), array( 'status' => 400 ) );
		}

		// Préfixer les rôles custom avec wtm_ pour éviter collisions.
		if ( 0 !== strpos( $slug, self::CUSTOM_ROLE_PREFIX ) ) {
			$slug = self::CUSTOM_ROLE_PREFIX . $slug;
		}

		// Vérifier qu'un rôle avec ce slug n'existe pas déjà.
		if ( get_role( $slug ) ) {
			return new \WP_Error( 'wtm_role_exists', __( 'Un rôle avec ce slug existe déjà.', 'woo-total-menu' ), array( 'status' => 409 ) );
		}

		// Caps valides.
		$valid_caps = self::get_all_caps();
		$caps       = is_array( $caps ) ? $caps : array();
		$granted    = array();
		foreach ( $caps as $cap ) {
			if ( in_array( $cap, $valid_caps, true ) ) {
				$granted[ $cap ] = true;
			}
		}

		// Toujours accorder `read` (sinon le rôle est inutilisable en wp-admin).
		$granted['read'] = true;

		// Créer le rôle.
		add_role( $slug, $name, $granted );

		// Vérifier que la création a réussi.
		$role = get_role( $slug );
		if ( ! $role ) {
			return new \WP_Error( 'wtm_role_create_failed', __( 'Échec de la création du rôle.', 'woo-total-menu' ), array( 'status' => 500 ) );
		}

		// Enregistrer dans la liste des rôles custom.
		$custom = get_option( self::CUSTOM_ROLES_OPTION, array() );
		if ( ! is_array( $custom ) ) {
			$custom = array();
		}
		if ( ! in_array( $slug, $custom, true ) ) {
			$custom[] = $slug;
			update_option( self::CUSTOM_ROLES_OPTION, $custom );
		}

		/**
		 * Fires after a custom WTM role is created.
		 *
		 * @since 1.6.0
		 *
		 * @param string $slug    Rôle slug.
		 * @param string $name    Rôle display name.
		 * @param array  $granted Caps granted.
		 */
		do_action( 'wtm_role_created', $slug, $name, $granted );

		return $slug;
	}

	/**
	 * Met à jour les caps d'un rôle existant.
	 *
	 * @param string $slug Slug du rôle.
	 * @param array  $caps Map cap => bool. Les caps absents ne sont pas touchés.
	 * @return true|\WP_Error
	 */
	public static function update_role_caps( $slug, $caps ) {
		$slug = sanitize_key( $slug );
		$role = get_role( $slug );
		if ( ! $role ) {
			return new \WP_Error( 'wtm_role_not_found', __( 'Rôle introuvable.', 'woo-total-menu' ), array( 'status' => 404 ) );
		}

		$valid_caps = self::get_all_caps();
		$caps       = is_array( $caps ) ? $caps : array();

		foreach ( $caps as $cap => $grant ) {
			if ( ! in_array( $cap, $valid_caps, true ) ) {
				continue;
			}
			// Administrator a toujours toutes les caps.
			if ( 'administrator' === $slug ) {
				$role->add_cap( $cap );
				continue;
			}
			if ( $grant ) {
				$role->add_cap( $cap );
			} else {
				$role->remove_cap( $cap );
			}
		}

		/**
		 * Fires after a role's caps are updated.
		 *
		 * @since 1.6.0
		 *
		 * @param string $slug Role slug.
		 * @param array  $caps Updated caps map.
		 */
		do_action( 'wtm_role_updated', $slug, $caps );

		return true;
	}

	/**
	 * Supprime un rôle personnalisé créé par WTM.
	 *
	 * Refuse de supprimer les rôles core (administrator, editor, author, contributor, subscriber).
	 * Réassigne les utilisateurs du rôle supprimé vers "subscriber" par sécurité.
	 *
	 * @param string $slug Slug du rôle à supprimer.
	 * @return true|\WP_Error
	 */
	public static function delete_role( $slug ) {
		$slug = sanitize_key( $slug );

		// Protection : ne jamais supprimer les rôles core.
		$protected = array( 'administrator', 'editor', 'author', 'contributor', 'subscriber' );
		if ( in_array( $slug, $protected, true ) ) {
			return new \WP_Error( 'wtm_role_protected', __( 'Les rôles WordPress standards ne peuvent pas être supprimés.', 'woo-total-menu' ), array( 'status' => 403 ) );
		}

		// Vérifier que c'est un rôle custom WTM.
		$custom = get_option( self::CUSTOM_ROLES_OPTION, array() );
		$custom = is_array( $custom ) ? $custom : array();
		if ( ! in_array( $slug, $custom, true ) ) {
			return new \WP_Error( 'wtm_role_not_custom', __( 'Seuls les rôles créés via Woo Total Menu peuvent être supprimés.', 'woo-total-menu' ), array( 'status' => 403 ) );
		}

		// Réassigner les utilisateurs vers "subscriber".
		$users = get_users( array( 'role' => $slug, 'fields' => 'ids' ) );
		foreach ( $users as $user_id ) {
			$user = get_userdata( $user_id );
			if ( $user ) {
				$user->set_role( 'subscriber' );
			}
		}

		// Supprimer le rôle.
		remove_role( $slug );

		// Retirer de la liste des rôles custom.
		$custom = array_diff( $custom, array( $slug ) );
		update_option( self::CUSTOM_ROLES_OPTION, array_values( $custom ) );

		/**
		 * Fires after a custom WTM role is deleted.
		 *
		 * @since 1.6.0
		 *
		 * @param string $slug Role slug deleted.
		 */
		do_action( 'wtm_role_deleted', $slug );

		return true;
	}

	/**
	 * Vérifie si un rôle a été créé par WTM.
	 *
	 * @param string $slug Slug du rôle.
	 * @return bool
	 */
	public static function is_custom_role( $slug ) {
		$slug   = sanitize_key( $slug );
		$custom = get_option( self::CUSTOM_ROLES_OPTION, array() );
		return is_array( $custom ) && in_array( $slug, $custom, true );
	}

	/**
	 * Récupère la liste des slugs des rôles custom WTM.
	 *
	 * @return array<int,string>
	 */
	public static function get_custom_role_slugs() {
		$custom = get_option( self::CUSTOM_ROLES_OPTION, array() );
		return is_array( $custom ) ? array_values( $custom ) : array();
	}
}
