<?php
/**
 * Analytics dashboard page.
 *
 * v1.7.0 — Privacy-friendly analytics dashboard showing daily counters
 * for menu views and clicks over the last 7 / 30 days.
 *
 * @package WooTotalMenu
 * @since 1.7.0
 */

namespace WooTotalMenu\Admin\Pages;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WooTotalMenu\Core\Analytics;
use WooTotalMenu\Core\CPT_Manager;

/**
 * Class Analytics_Page
 *
 * Renders the /wp-admin/admin.php?page=wtm-analytics page.
 */
class Analytics_Page {

	/**
	 * Render the page.
	 *
	 * @return void
	 */
	public static function render() {
		// Range selection (default 7 days).
		$days = isset( $_GET['days'] ) ? absint( $_GET['days'] ) : 7; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! in_array( $days, array( 7, 14, 30, 90 ), true ) ) {
			$days = 7;
		}
		$menu_filter = isset( $_GET['menu_id'] ) ? absint( $_GET['menu_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$end   = current_time( 'Y-m-d' );
		$start = gmdate( 'Y-m-d', strtotime( '-' . ( $days - 1 ) . ' days' ) );

		$analytics = new Analytics();
		$stats     = $analytics->get_stats(
			array(
				'start'    => $start,
				'end'      => $end,
				'menu_id'  => $menu_filter,
				'group_by' => 'day',
			)
		);

		$by_menu = $analytics->get_stats(
			array(
				'start'    => $start,
				'end'      => $end,
				'menu_id'  => 0,
				'group_by' => 'menu',
			)
		);

		$totals      = $stats['totals'];
		$daily_groups = $stats['groups'];
		$menu_groups  = $by_menu['groups'];

		// Settings check.
		$settings    = get_option( WTM_OPTION_SETTINGS );
		$an          = is_array( $settings ) ? ( $settings['analytics'] ?? array() ) : array();
		$enabled     = ! empty( $an['enabled'] );

		// All menus for the filter dropdown.
		$menus = get_posts(
			array(
				'post_type'      => WTM_CPT_MENU,
				'posts_per_page' => -1,
				'post_status'    => 'any',
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
		?>
		<div class="wrap wtm-page">
			<h1><span class="dashicons dashicons-chart-bar"></span> <?php esc_html_e( 'Analytics', 'woo-total-menu' ); ?></h1>
			<p class="wtm-page-subtitle">
				<?php esc_html_e( 'Vues et clics sur vos menus, en agrégats journaliers anonymes.', 'woo-total-menu' ); ?>
			</p>

			<?php if ( ! $enabled ) : ?>
				<div class="wtm-notice is-warning" style="margin:16px 0;">
					<p>
						<span class="dashicons dashicons-warning" style="vertical-align:middle; margin-right:6px;"></span>
						<?php
						echo wp_kses(
							sprintf(
								/* translators: %s settings URL */
								__( 'La collecte d\'analytics est actuellement <strong>désactivée</strong>. <a href="%s">Activez-la dans les réglages</a> pour commencer à accumuler des données.', 'woo-total-menu' ),
								esc_url( admin_url( 'admin.php?page=wtm-settings&tab=analytics' ) )
							),
							array(
								'strong' => array(),
								'a'      => array( 'href' => array() ),
							)
						);
						?>
					</p>
				</div>
			<?php endif; ?>

			<form method="get" action="" style="margin:16px 0;">
				<input type="hidden" name="page" value="wtm-analytics">
				<label for="filter-days" style="margin-right:12px;">
					<strong><?php esc_html_e( 'Période', 'woo-total-menu' ); ?>:</strong>
					<select name="days" id="filter-days">
						<option value="7" <?php selected( $days, 7 ); ?>><?php esc_html_e( '7 derniers jours', 'woo-total-menu' ); ?></option>
						<option value="14" <?php selected( $days, 14 ); ?>><?php esc_html_e( '14 derniers jours', 'woo-total-menu' ); ?></option>
						<option value="30" <?php selected( $days, 30 ); ?>><?php esc_html_e( '30 derniers jours', 'woo-total-menu' ); ?></option>
						<option value="90" <?php selected( $days, 90 ); ?>><?php esc_html_e( '90 derniers jours', 'woo-total-menu' ); ?></option>
					</select>
				</label>
				<label for="filter-menu">
					<strong><?php esc_html_e( 'Menu', 'woo-total-menu' ); ?>:</strong>
					<select name="menu_id" id="filter-menu">
						<option value="0"><?php esc_html_e( 'Tous les menus', 'woo-total-menu' ); ?></option>
						<?php foreach ( $menus as $m ) : ?>
							<option value="<?php echo esc_attr( (string) $m->ID ); ?>" <?php selected( $menu_filter, $m->ID ); ?>>
								<?php echo esc_html( $m->post_title ); ?> (ID #<?php echo esc_html( (string) $m->ID ); ?>)
							</option>
						<?php endforeach; ?>
					</select>
				</label>
				<button type="submit" class="wtm-btn is-secondary" style="margin-left:8px;">
					<?php esc_html_e( 'Filtrer', 'woo-total-menu' ); ?>
				</button>
			</form>

			<div class="wtm-grid">
				<div class="wtm-card">
					<h3><span class="dashicons dashicons-visibility"></span> <?php esc_html_e( 'Vues totales', 'woo-total-menu' ); ?></h3>
					<div class="wtm-card-stat"><?php echo esc_html( number_format_i18n( $totals['view'] ) ); ?></div>
					<div class="wtm-card-label"><?php esc_html_e( 'fois où un menu a été affiché', 'woo-total-menu' ); ?></div>
				</div>

				<div class="wtm-card">
					<h3><span class="dashicons dashicons-mouse"></span> <?php esc_html_e( 'Clics totaux', 'woo-total-menu' ); ?></h3>
					<div class="wtm-card-stat"><?php echo esc_html( number_format_i18n( $totals['click'] ) ); ?></div>
					<div class="wtm-card-label"><?php esc_html_e( 'clics sur des items de menu', 'woo-total-menu' ); ?></div>
				</div>

				<div class="wtm-card">
					<h3><span class="dashicons dashicons-chart-line"></span> <?php esc_html_e( 'Taux de clic (CTR)', 'woo-total-menu' ); ?></h3>
					<div class="wtm-card-stat">
						<?php
						$ctr = $totals['view'] > 0 ? round( ( $totals['click'] / $totals['view'] ) * 100, 1 ) : 0;
						echo esc_html( $ctr . '%' );
						?>
					</div>
					<div class="wtm-card-label"><?php esc_html_e( 'clics divisés par vues', 'woo-total-menu' ); ?></div>
				</div>
			</div>

			<div class="wtm-card" style="margin-top:20px;">
				<h3><span class="dashicons dashicons-chart-area"></span> <?php esc_html_e( 'Tendance journalière', 'woo-total-menu' ); ?></h3>
				<?php
				// Build a simple bar chart with pure HTML/CSS (no JS dependency).
				$max = 1;
				foreach ( $stats['days'] as $day ) {
					$d = $daily_groups[ $day ] ?? array( 'view' => 0, 'click' => 0 );
					$max = max( $max, $d['view'], $d['click'] );
				}
				?>
				<div class="wtm-analytics-chart">
					<?php foreach ( $stats['days'] as $day ) : ?>
						<?php
						$d         = $daily_groups[ $day ] ?? array( 'view' => 0, 'click' => 0 );
						$view_h    = $max > 0 ? round( ( $d['view'] / $max ) * 100 ) : 0;
						$click_h   = $max > 0 ? round( ( $d['click'] / $max ) * 100 ) : 0;
						$label     = gmdate( 'd/m', strtotime( $day ) );
						?>
						<div class="wtm-analytics-bar-group">
							<div class="wtm-analytics-bars">
								<div class="wtm-analytics-bar wtm-analytics-bar--view"
									style="height:<?php echo esc_attr( (string) max( $view_h, 2 ) ); ?>%;"
									title="<?php echo esc_attr( sprintf( __( 'Vues: %d', 'woo-total-menu' ), $d['view'] ) ); ?>">
								</div>
								<div class="wtm-analytics-bar wtm-analytics-bar--click"
									style="height:<?php echo esc_attr( (string) max( $click_h, 2 ) ); ?>%;"
									title="<?php echo esc_attr( sprintf( __( 'Clics: %d', 'woo-total-menu' ), $d['click'] ) ); ?>">
								</div>
							</div>
							<div class="wtm-analytics-label"><?php echo esc_html( $label ); ?></div>
						</div>
					<?php endforeach; ?>
				</div>
				<div class="wtm-analytics-legend">
					<span class="wtm-analytics-legend-item">
						<span class="wtm-analytics-swatch wtm-analytics-swatch--view"></span>
						<?php esc_html_e( 'Vues', 'woo-total-menu' ); ?>
					</span>
					<span class="wtm-analytics-legend-item">
						<span class="wtm-analytics-swatch wtm-analytics-swatch--click"></span>
						<?php esc_html_e( 'Clics', 'woo-total-menu' ); ?>
					</span>
				</div>
			</div>

			<div class="wtm-card" style="margin-top:20px;">
				<h3><span class="dashicons dashicons-list-view"></span> <?php esc_html_e( 'Par menu', 'woo-total-menu' ); ?></h3>
				<?php if ( empty( $menu_groups ) ) : ?>
					<p style="color:#6b7280;">
						<?php esc_html_e( 'Aucune donnée sur la période sélectionnée.', 'woo-total-menu' ); ?>
					</p>
				<?php else : ?>
					<table class="wtm-table" style="margin-top:12px;">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Menu', 'woo-total-menu' ); ?></th>
								<th style="text-align:right;"><?php esc_html_e( 'Vues', 'woo-total-menu' ); ?></th>
								<th style="text-align:right;"><?php esc_html_e( 'Clics', 'woo-total-menu' ); ?></th>
								<th style="text-align:right;"><?php esc_html_e( 'CTR', 'woo-total-menu' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $menu_groups as $mid => $info ) : ?>
								<?php
								$c       = $info['counts'];
								$ctr_row = $c['view'] > 0 ? round( ( $c['click'] / $c['view'] ) * 100, 1 ) : 0;
								?>
								<tr>
									<td>
										<strong><?php echo esc_html( $info['title'] ); ?></strong>
										<br><span style="color:#9ca3af; font-size:11px;">ID: <?php echo esc_html( (string) $mid ); ?></span>
									</td>
									<td style="text-align:right;"><?php echo esc_html( number_format_i18n( $c['view'] ) ); ?></td>
									<td style="text-align:right;"><?php echo esc_html( number_format_i18n( $c['click'] ) ); ?></td>
									<td style="text-align:right;"><strong><?php echo esc_html( $ctr_row . '%' ); ?></strong></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>

			<div class="wtm-card" style="margin-top:20px;">
				<h3><span class="dashicons dashicons-info"></span> <?php esc_html_e( 'Confidentialité', 'woo-total-menu' ); ?></h3>
				<p style="color:#6b7280;">
					<?php esc_html_e( 'Aucune donnée personnelle n\'est collectée : ni adresse IP, ni identifiant utilisateur, ni cookie. Les événements sont agrégés sous forme de compteurs journaliers stockés dans la table options de WordPress. Les données sont automatiquement purgées après 90 jours.', 'woo-total-menu' ); ?>
				</p>
			</div>
		</div>
		<?php
	}
}
