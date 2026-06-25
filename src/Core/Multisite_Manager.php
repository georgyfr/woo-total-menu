<?php
/**
 * Multisite Manager — Woo Total Menu.
 *
 * Gère l'activation / désactivation du plugin en mode multisite WordPress.
 *
 * Hooks principaux :
 *
 *   - Activation réseau (`activate_{$plugin}` sur `network_admin_notices`)
 *     → initialisation des options sur chaque sous-site du réseau.
 *   - `wpmu_new_blog` → initialise un nouveau sous-site créé après activation.
 *   - Désactivation réseau → cleanup léger (pas de suppression de données,
 *     juste un flush rewrite rules sur tous les blogs).
 *   - Désinstallation réseau → suppression des options + caps sur tous les blogs.
 *
 * @package WooTotalMenu\Core
 * @since 1.6.0
 */

namespace WooTotalMenu\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Multisite_Manager.
 *
 * Toutes les méthodes sont statiques — pas d'instanciation nécessaire.
 */
final class Multisite_Manager {

	/**
	 * Vérifie si WordPress est en mode multisite.
	 *
	 * @return bool
	 */
	public static function is_multisite() {
		return is_multisite();
	}

	/**
	 * Parcourt tous les blogs du réseau et applique un callback.
	 *
	 * En multisite, switch_to_blog() est utilisé pour basculer de contexte.
	 * En single-site, exécute simplement le callback sur le blog courant.
	 *
	 * @param callable $callback Callback recevant le blog_id en paramètre.
	 * @return void
	 */
	public static function for_each_blog( $callback ) {
		if ( ! is_callable( $callback ) ) {
			return;
		}

		if ( ! is_multisite() ) {
			call_user_func( $callback, get_current_blog_id() );
			return;
		}

		$sites = get_sites(
			array(
				'fields'   => 'ids',
				'deleted'  => 0,
				'archived' => 0,
				'spam'     => 0,
			)
		);

		foreach ( $sites as $blog_id ) {
			switch_to_blog( (int) $blog_id );
			try {
				call_user_func( $callback, (int) $blog_id );
			} finally {
				restore_current_blog();
			}
		}
	}

	/**
	 * Initialise les options + caps du plugin sur un blog donné.
	 *
	 * Idempotent : ne crée les options que si elles n'existent pas.
	 *
	 * @param int $blog_id Blog ID.
	 * @return void
	 */
	public static function setup_blog( $blog_id ) {
		// Default settings.
		if ( false === get_option( WTM_OPTION_SETTINGS ) ) {
			add_option( WTM_OPTION_SETTINGS, \WooTotalMenu\Bootstrap::default_settings() );
		}
		// DB version.
		if ( false === get_option( WTM_OPTION_DB_VERSION ) ) {
			add_option( WTM_OPTION_DB_VERSION, WTM_DB_VERSION );
		}

		// Register capabilities on this blog.
		$permissions = new Permissions();
		$permissions->register_caps();

		// Flush rewrite rules on this blog.
		flush_rewrite_rules( false );

		/**
		 * Fires after a blog is set up for WTM.
		 *
		 * @since 1.6.0
		 *
		 * @param int $blog_id Blog ID.
		 */
		do_action( 'wtm_multisite_blog_setup', $blog_id );
	}

	/**
	 * Nettoie les options + caps du plugin sur un blog donné.
	 *
	 * Utilisé à la désinstallation (network-wide) — supprime toutes les données.
	 *
	 * @param int $blog_id Blog ID.
	 * @return void
	 */
	public static function cleanup_blog( $blog_id ) {
		// Supprimer les options.
		delete_option( WTM_OPTION_SETTINGS );
		delete_option( WTM_OPTION_DB_VERSION );
		delete_option( WTM_OPTION_TEMPLATES );
		delete_option( Roles_Manager::CUSTOM_ROLES_OPTION );

		// Supprimer les caps.
		$permissions = new Permissions();
		$permissions->remove_caps();

		// Flush rewrite rules.
		flush_rewrite_rules( false );

		/**
		 * Fires after a blog is cleaned up for WTM.
		 *
		 * @since 1.6.0
		 *
		 * @param int $blog_id Blog ID.
		 */
		do_action( 'wtm_multisite_blog_cleanup', $blog_id );
	}

	/**
	 * Activation réseau — initialise tous les blogs du réseau.
	 *
	 * Appelé par Bootstrap::on_activate() quand is_multisite() && is_network_admin().
	 *
	 * @param bool $network_wide True si activation réseau.
	 * @return void
	 */
	public static function on_network_activate( $network_wide ) {
		if ( ! $network_wide || ! is_multisite() ) {
			// Activation simple sur le blog courant.
			\WooTotalMenu\Bootstrap::on_activate();
			return;
		}

		self::for_each_blog( array( __CLASS__, 'setup_blog' ) );

		/**
		 * Fires after WTM is network-activated.
		 *
		 * @since 1.6.0
		 */
		do_action( 'wtm_network_activated' );
	}

	/**
	 * Hook wpmu_new_blog — initialise un nouveau sous-site créé après activation.
	 *
	 * @param int $blog_id Nouveau blog ID.
	 * @return void
	 */
	public static function on_new_blog( $blog_id ) {
		if ( ! is_plugin_active_for_network( WTM_PLUGIN_BASENAME ) ) {
			return;
		}

		switch_to_blog( (int) $blog_id );
		try {
			self::setup_blog( (int) $blog_id );
		} finally {
			restore_current_blog();
		}
	}

	/**
	 * Récupère des statistiques réseau (en multisite uniquement).
	 *
	 * @return array {
	 *     @type int    $total_blogs       Nombre total de blogs actifs.
	 *     @type int    $total_menus       Nombre total de menus WTM sur le réseau.
	 *     @type int    $active_blogs      Nombre de blogs avec au moins 1 menu.
	 *     @type array  $per_blog          Détail par blog.
	 * }
	 */
	public static function get_network_stats() {
		$stats = array(
			'total_blogs'    => 0,
			'total_menus'    => 0,
			'active_blogs'   => 0,
			'per_blog'       => array(),
		);

		if ( ! is_multisite() ) {
			$count = wp_count_posts( WTM_CPT_MENU );
			$total = (int) ( $count->publish ?? 0 ) + (int) ( $count->draft ?? 0 );
			$stats['total_blogs']  = 1;
			$stats['total_menus']  = $total;
			$stats['active_blogs'] = $total > 0 ? 1 : 0;
			$stats['per_blog'][]   = array(
				'blog_id'   => get_current_blog_id(),
				'name'      => get_bloginfo( 'name' ),
				'menu_count' => $total,
			);
			return $stats;
		}

		$sites = get_sites(
			array(
				'fields'   => 'ids',
				'deleted'  => 0,
				'archived' => 0,
				'spam'     => 0,
			)
		);

		foreach ( $sites as $blog_id ) {
			switch_to_blog( (int) $blog_id );
			try {
				$count = wp_count_posts( WTM_CPT_MENU );
				$total = (int) ( $count->publish ?? 0 ) + (int) ( $count->draft ?? 0 );
				$stats['total_blogs']++;
				$stats['total_menus'] += $total;
				if ( $total > 0 ) {
					$stats['active_blogs']++;
				}
				$stats['per_blog'][] = array(
					'blog_id'    => (int) $blog_id,
					'name'       => get_bloginfo( 'name' ),
					'menu_count' => $total,
				);
			} finally {
				restore_current_blog();
			}
		}

		return $stats;
	}
}
