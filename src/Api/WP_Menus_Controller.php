<?php
/**
 * WP Native Menus Controller — Woo Total Menu.
 *
 * Endpoint REST `/wtm/v1/wp-menus` exposant la liste des menus WordPress
 * natifs (taxonomy=nav_menu) créés via /wp-admin/nav-menus.php. Une seule
 * route en lecture seule :
 *
 *   - `GET /wtm/v1/wp-menus` — Liste tous les nav_menus avec leur term_id,
 *     nom, slug, count et emplacements (locations) assignés.
 *
 * Permet au Builder React de proposer ces menus dans le dropdown du module
 * `menu` (Header/Footer Builder) aux côtés des wtm_menu posts.
 *
 * @package WooTotalMenu\Api
 * @since 1.7.1
 */

namespace WooTotalMenu\Api;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Error;

/**
 * Class WP_Menus_Controller.
 *
 * Registre de la route /wp-menus. Instancié par Bootstrap sur `rest_api_init`.
 */
final class WP_Menus_Controller {

	/**
	 * Namespace REST du plugin.
	 *
	 * @var string
	 */
	const REST_NAMESPACE = 'wtm/v1';

	/**
	 * Route de base pour les menus WordPress natifs.
	 *
	 * @var string
	 */
	const REST_BASE = 'wp-menus';

	/**
	 * Constructeur — enregistre les hooks REST.
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Enregistre la route REST /wp-menus.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			self::REST_NAMESPACE,
			'/' . self::REST_BASE,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_menus' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
			)
		);
	}

	/**
	 * Vérifie la permission : capacité wtm_manage_menus requise.
	 *
	 * @return true|WP_Error
	 */
	public function check_permission() {
		if ( ! current_user_can( 'wtm_manage_menus' ) ) {
			return new WP_Error(
				'wtm_rest_forbidden',
				__( 'Vous n\'avez pas la permission de consulter les menus WordPress natifs.', 'woo-total-menu' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}
		return true;
	}

	/**
	 * LIST: GET /wtm/v1/wp-menus
	 *
	 * Retourne tous les nav_menus natifs avec leur term_id, nom, slug,
	 * nombre d'items et emplacements assignés (locations).
	 *
	 * @return WP_REST_Response
	 */
	public function list_menus() {
		$menus = wp_get_nav_menus();
		if ( ! is_array( $menus ) ) {
			$menus = array();
		}

		// Map locations → term_id pour connaître les emplacements assignés.
		$locations      = get_nav_menu_locations();
		$locations_by_id = array();
		foreach ( $locations as $location_slug => $term_id ) {
			if ( ! isset( $locations_by_id[ $term_id ] ) ) {
				$locations_by_id[ $term_id ] = array();
			}
			$locations_by_id[ $term_id ][] = $location_slug;
		}

		// Liste des emplacements enregistrés (pour info côté UI).
		$registered_locations = get_registered_nav_menus();

		$items = array();
		foreach ( $menus as $menu ) {
			$items[] = array(
				'id'         => (int) $menu->term_id,
				'name'       => $menu->name,
				'slug'       => $menu->slug,
				'count'      => (int) $menu->count,
				'locations'  => isset( $locations_by_id[ $menu->term_id ] ) ? $locations_by_id[ $menu->term_id ] : array(),
				'edit_url'   => admin_url( 'nav-menus.php?menu=' . $menu->term_id ),
			);
		}

		$response = new WP_REST_Response(
			array(
				'menus'     => $items,
				'locations' => $registered_locations,
			),
			200
		);

		return $response;
	}
}
