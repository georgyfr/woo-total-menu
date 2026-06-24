<?php
/**
 * Dashboard page.
 *
 * @package WooTotalMenu
 */

namespace WooTotalMenu\Admin\Pages;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WooTotalMenu\Admin\Admin_Menu;
use WooTotalMenu\Core\CPT_Manager;

/**
 * Class Dashboard
 *
 * Overview page showing key stats and quick actions.
 */
class Dashboard {

	/**
	 * Render the page.
	 *
	 * @return void
	 */
	public static function render() {
		// Gather stats.
		$all_menus = get_posts(
			array(
				'post_type'      => WTM_CPT_MENU,
				'posts_per_page' => -1,
				'post_status'    => 'any',
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);

		$total     = count( $all_menus );
		$published = 0;
		$drafts    = 0;
		$by_type   = array(
			'horizontal' => 0,
			'vertical'   => 0,
			'offcanvas'  => 0,
			'footer'     => 0,
		);
		$by_location = array(
			'primary' => 0,
			'footer'  => 0,
			'sidebar' => 0,
			'mobile'  => 0,
		);

		foreach ( $all_menus as $menu ) {
			if ( 'publish' === $menu->post_status ) {
				$published++;
			} else {
				$drafts++;
			}
			$type = get_post_meta( $menu->ID, '_wtm_menu_type', true ) ?: 'horizontal';
			$loc  = get_post_meta( $menu->ID, '_wtm_location', true ) ?: 'primary';
			if ( isset( $by_type[ $type ] ) ) {
				$by_type[ $type ]++;
			}
			if ( isset( $by_location[ $loc ] ) ) {
				$by_location[ $loc ]++;
			}
		}

		$create_url = Admin_Menu::action_url(
			'create_menu',
			array(
				'menu_type'  => 'horizontal',
				'location'   => 'primary',
				'menu_title' => __( 'Nouveau menu', 'woo-total-menu' ),
			)
		);

		// Display notices.
		self::render_notices();
		?>
		<div class="wrap wtm-page">
			<h1><span class="dashicons dashicons-dashboard"></span> <?php esc_html_e( 'Tableau de bord', 'woo-total-menu' ); ?></h1>
			<p class="wtm-page-subtitle">
				<?php esc_html_e( 'Vue d\'ensemble de vos menus, headers et footers.', 'woo-total-menu' ); ?>
			</p>

			<p>
				<a href="<?php echo esc_url( $create_url ); ?>" class="wtm-btn">
					<span class="dashicons dashicons-plus-alt2"></span>
					<?php esc_html_e( 'Créer un nouveau menu', 'woo-total-menu' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wtm-menus' ) ); ?>" class="wtm-btn is-secondary">
					<span class="dashicons dashicons-list-view"></span>
					<?php esc_html_e( 'Voir tous les menus', 'woo-total-menu' ); ?>
				</a>
			</p>

			<div class="wtm-grid">
				<div class="wtm-card">
					<h3><span class="dashicons dashicons-menu"></span> <?php esc_html_e( 'Menus totaux', 'woo-total-menu' ); ?></h3>
					<div class="wtm-card-stat"><?php echo esc_html( (string) $total ); ?></div>
					<div class="wtm-card-label"><?php esc_html_e( 'menus, headers et footers créés', 'woo-total-menu' ); ?></div>
				</div>

				<div class="wtm-card">
					<h3><span class="dashicons dashicons-visibility"></span> <?php esc_html_e( 'Menus actifs', 'woo-total-menu' ); ?></h3>
					<div class="wtm-card-stat"><?php echo esc_html( (string) $published ); ?></div>
					<div class="wtm-card-label"><?php esc_html_e( 'publiés et visibles sur le site', 'woo-total-menu' ); ?></div>
				</div>

				<div class="wtm-card">
					<h3><span class="dashicons dashicons-hidden"></span> <?php esc_html_e( 'Brouillons', 'woo-total-menu' ); ?></h3>
					<div class="wtm-card-stat"><?php echo esc_html( (string) $drafts ); ?></div>
					<div class="wtm-card-label"><?php esc_html_e( 'menus en cours de configuration', 'woo-total-menu' ); ?></div>
				</div>

				<div class="wtm-card">
					<h3><span class="dashicons dashicons-location-alt"></span> <?php esc_html_e( 'Emplacements', 'woo-total-menu' ); ?></h3>
					<div style="margin-top: 8px;">
						<?php foreach ( CPT_Manager::get_locations() as $slug => $label ) : ?>
							<div style="display:flex; justify-content:space-between; padding:4px 0; border-bottom:1px solid #f3f4f6;">
								<span style="color:#374151; font-size:13px;">
									<?php echo esc_html( $label ); ?>
								</span>
								<strong style="color:#6C5CE7; font-size:13px;">
									<?php echo esc_html( (string) ( $by_location[ $slug ] ?? 0 ) ); ?>
								</strong>
							</div>
						<?php endforeach; ?>
					</div>
				</div>

				<div class="wtm-card">
					<h3><span class="dashicons dashicons-category"></span> <?php esc_html_e( 'Types de menus', 'woo-total-menu' ); ?></h3>
					<div style="margin-top: 8px;">
						<?php foreach ( CPT_Manager::get_menu_types() as $slug => $label ) : ?>
							<div style="display:flex; justify-content:space-between; padding:4px 0; border-bottom:1px solid #f3f4f6;">
								<span style="color:#374151; font-size:13px;">
									<?php echo esc_html( $label ); ?>
								</span>
								<strong style="color:#6C5CE7; font-size:13px;">
									<?php echo esc_html( (string) ( $by_type[ $slug ] ?? 0 ) ); ?>
								</strong>
							</div>
						<?php endforeach; ?>
					</div>
				</div>

				<div class="wtm-card">
					<h3><span class="dashicons dashicons-info"></span> <?php esc_html_e( 'Environnement', 'woo-total-menu' ); ?></h3>
					<table style="width:100%; font-size:13px; margin-top:8px;">
						<tbody>
							<tr><td style="color:#6b7280; padding:3px 0;">Plugin</td><td style="text-align:right;"><strong>v<?php echo esc_html( WTM_VERSION ); ?></strong></td></tr>
							<tr><td style="color:#6b7280; padding:3px 0;">PHP</td><td style="text-align:right;"><?php echo esc_html( PHP_VERSION ); ?></td></tr>
							<tr><td style="color:#6b7280; padding:3px 0;">WordPress</td><td style="text-align:right;"><?php echo esc_html( get_bloginfo( 'version' ) ); ?></td></tr>
							<tr><td style="color:#6b7280; padding:3px 0;">WooCommerce</td><td style="text-align:right;"><?php echo class_exists( 'WooCommerce' ) ? esc_html( WC()->version ) : '—'; ?></td></tr>
							<tr><td style="color:#6b7280; padding:3px 0;">Thème</td><td style="text-align:right;"><?php echo esc_html( wp_get_theme()->get( 'Name' ) ); ?></td></tr>
							<tr><td style="color:#6b7280; padding:3px 0;">DB Version</td><td style="text-align:right;"><?php echo esc_html( (string) get_option( WTM_OPTION_DB_VERSION ) ); ?></td></tr>
						</tbody>
					</table>
				</div>
			</div>

			<?php if ( ! empty( $all_menus ) ) : ?>
				<div class="wtm-card" style="margin-top:20px;">
					<h3><span class="dashicons dashicons-clock"></span> <?php esc_html_e( 'Menus récents', 'woo-total-menu' ); ?></h3>
					<table class="wtm-table" style="margin-top:12px;">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Titre', 'woo-total-menu' ); ?></th>
								<th><?php esc_html_e( 'Type', 'woo-total-menu' ); ?></th>
								<th><?php esc_html_e( 'Emplacement', 'woo-total-menu' ); ?></th>
								<th><?php esc_html_e( 'Statut', 'woo-total-menu' ); ?></th>
								<th><?php esc_html_e( 'Modifié', 'woo-total-menu' ); ?></th>
								<th style="text-align:right;"><?php esc_html_e( 'Actions', 'woo-total-menu' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( array_slice( $all_menus, 0, 5 ) as $menu ) : ?>
								<?php
								$type = get_post_meta( $menu->ID, '_wtm_menu_type', true ) ?: 'horizontal';
								$loc  = get_post_meta( $menu->ID, '_wtm_location', true ) ?: 'primary';
								$types     = CPT_Manager::get_menu_types();
								$locations = CPT_Manager::get_locations();
								$edit_url  = admin_url( 'post.php?post=' . $menu->ID . '&action=edit' );
								?>
								<tr>
									<td>
										<strong><a href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html( $menu->post_title ); ?></a></strong>
										<br><span style="color:#9ca3af; font-size:11px;">ID: <?php echo esc_html( (string) $menu->ID ); ?></span>
									</td>
									<td><span class="wtm-badge is-<?php echo esc_attr( $type ); ?>"><?php echo esc_html( $types[ $type ] ?? $type ); ?></span></td>
									<td><?php echo esc_html( $locations[ $loc ] ?? $loc ); ?></td>
									<td>
										<?php if ( 'publish' === $menu->post_status ) : ?>
											<span class="wtm-badge is-active"><?php esc_html_e( 'Actif', 'woo-total-menu' ); ?></span>
										<?php else : ?>
											<span class="wtm-badge is-inactive"><?php esc_html_e( 'Inactif', 'woo-total-menu' ); ?></span>
										<?php endif; ?>
									</td>
									<td><?php echo esc_html( wp_date( 'd/m/Y H:i', strtotime( $menu->post_modified ) ) ); ?></td>
									<td style="text-align:right;">
										<a href="<?php echo esc_url( $edit_url ); ?>" class="wtm-btn is-secondary"><?php esc_html_e( 'Modifier', 'woo-total-menu' ); ?></a>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
					<p style="margin-top:12px;">
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=wtm-menus' ) ); ?>"><?php esc_html_e( 'Voir tous les menus →', 'woo-total-menu' ); ?></a>
					</p>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render admin notices (based on query params).
	 *
	 * @return void
	 */
	private static function render_notices() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['wtm_deleted'] ) ) {
			echo '<div class="wtm-notice is-success"><p>' . esc_html__( 'Menu supprimé avec succès.', 'woo-total-menu' ) . '</p></div>';
		}
		if ( isset( $_GET['wtm_duplicated'] ) ) {
			echo '<div class="wtm-notice is-success"><p>' . esc_html__( 'Menu dupliqué avec succès.', 'woo-total-menu' ) . '</p></div>';
		}
		if ( isset( $_GET['wtm_toggled'] ) ) {
			$new_status = isset( $_GET['wtm_new_status'] ) ? sanitize_key( wp_unslash( $_GET['wtm_new_status'] ) ) : '';
			$msg = ( 'publish' === $new_status )
				? __( 'Menu activé avec succès.', 'woo-total-menu' )
				: __( 'Menu désactivé avec succès.', 'woo-total-menu' );
			echo '<div class="wtm-notice is-success"><p>' . esc_html( $msg ) . '</p></div>';
		}
		// phpcs:enable
	}
}
