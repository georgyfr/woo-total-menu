<?php
/**
 * Analytics — privacy-friendly menu analytics.
 *
 * v1.7.0 — Tracks aggregated daily counters for menu views and clicks.
 * NO personal data is stored: no IP, no user ID, no cookie. Only counters
 * keyed by (date, menu_id, event_type, item_id).
 *
 * Storage strategy: WordPress transients are too short-lived; custom tables
 * are heavy for a small plugin. We use a single option per day:
 *   `wtm_analytics_{YYYY-MM-DD}` → array of counters
 *
 * Each counter is keyed as: "{menu_id}:{event}:{item_id}" where item_id is
 * "0" for view events (whole menu) and the item numeric ID (or hash) for
 * click events.
 *
 * The option is autoload=false to avoid bloating WP's autoloaded options.
 * A daily cron consolidates old options into monthly summaries.
 *
 * @package WooTotalMenu
 * @since 1.7.0
 */

namespace WooTotalMenu\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Analytics
 *
 * Privacy-friendly analytics with daily aggregated counters.
 */
class Analytics {

	const OPTION_PREFIX = 'wtm_analytics_';
	const EVENT_VIEW    = 'view';
	const EVENT_CLICK   = 'click';
	const EVENT_HOVER   = 'hover';

	/**
	 * Whether analytics is enabled in site settings.
	 *
	 * @var bool|null
	 */
	private $enabled = null;

	/**
	 * Whether to track logged-in users.
	 *
	 * @var bool|null
	 */
	private $track_logged = null;

	/**
	 * Get the analytics settings (cached per request).
	 *
	 * @return array{enabled:bool,track_logged:bool}
	 */
	private function get_settings() {
		if ( null !== $this->enabled ) {
			return array(
				'enabled'      => $this->enabled,
				'track_logged' => $this->track_logged,
			);
		}
		$option = get_option( WTM_OPTION_SETTINGS );
		$an     = is_array( $option ) ? ( $option['analytics'] ?? array() ) : array();

		$this->enabled      = ! empty( $an['enabled'] );
		$this->track_logged = ! empty( $an['track_logged'] );
		return array(
			'enabled'      => $this->enabled,
			'track_logged' => $this->track_logged,
		);
	}

	/**
	 * Record an event.
	 *
	 * @param int    $menu_id Menu post ID.
	 * @param string $event   One of EVENT_VIEW, EVENT_CLICK, EVENT_HOVER.
	 * @param int    $item_id Item ID (0 for view events).
	 * @return bool True if recorded, false if skipped (disabled / logged-in filter).
	 */
	public function record( $menu_id, $event, $item_id = 0 ) {
		$s = $this->get_settings();
		if ( ! $s['enabled'] ) {
			return false;
		}
		if ( is_user_logged_in() && ! $s['track_logged'] ) {
			return false;
		}

		$menu_id = (int) $menu_id;
		$item_id = (int) $item_id;
		if ( ! $menu_id || ! in_array( $event, array( self::EVENT_VIEW, self::EVENT_CLICK, self::EVENT_HOVER ), true ) ) {
			return false;
		}

		$date = current_time( 'Y-m-d' );
		$key  = self::OPTION_PREFIX . $date;

		// Read existing counters for today.
		$existing = get_option( $key, array() );
		if ( ! is_array( $existing ) ) {
			$existing = array();
		}

		$counter_key = $menu_id . ':' . $event . ':' . $item_id;
		$current     = isset( $existing[ $counter_key ] ) ? (int) $existing[ $counter_key ] : 0;
		$existing[ $counter_key ] = $current + 1;

		update_option( $key, $existing, false );

		/**
		 * Fires after an analytics event has been recorded.
		 *
		 * @since 1.7.0
		 *
		 * @param int    $menu_id Menu post ID.
		 * @param string $event   Event type.
		 * @param int    $item_id Item ID.
		 * @param string $date    Date (Y-m-d).
		 */
		do_action( 'wtm_analytics_recorded', $menu_id, $event, $item_id, $date );

		return true;
	}

	/**
	 * Get aggregated stats for a date range.
	 *
	 * @param array $args {
	 *   @type string $start    Start date (Y-m-d). Default: 7 days ago.
	 *   @type string $end      End date (Y-m-d). Default: today.
	 *   @type int    $menu_id  Filter by menu (0 = all menus).
	 *   @type string $event    Filter by event type (empty = all).
	 *   @type string $group_by "day" | "menu" | "event". Default "day".
	 * }
	 * @return array
	 */
	public function get_stats( $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'start'    => gmdate( 'Y-m-d', strtotime( '-6 days' ) ),
				'end'      => current_time( 'Y-m-d' ),
				'menu_id'  => 0,
				'event'    => '',
				'group_by' => 'day',
			)
		);

		$start = sanitize_text_field( $args['start'] );
		$end   = sanitize_text_field( $args['end'] );
		$menu_filter = (int) $args['menu_id'];
		$event_filter = sanitize_key( $args['event'] );
		$group_by = in_array( $args['group_by'], array( 'day', 'menu', 'event' ), true ) ? $args['group_by'] : 'day';

		// Walk each day in the range.
		$days = array();
		$cursor = strtotime( $start );
		$end_ts = strtotime( $end );
		while ( $cursor <= $end_ts ) {
			$days[] = gmdate( 'Y-m-d', $cursor );
			$cursor = strtotime( '+1 day', $cursor );
		}

		$grouped = array();
		$totals  = array( 'view' => 0, 'click' => 0, 'hover' => 0 );

		foreach ( $days as $day ) {
			$option = get_option( self::OPTION_PREFIX . $day, array() );
			if ( ! is_array( $option ) || empty( $option ) ) {
				continue;
			}

			foreach ( $option as $counter_key => $count ) {
				$parts = explode( ':', $counter_key );
				if ( count( $parts ) < 3 ) {
					continue;
				}
				list( $m_id, $ev, $i_id ) = $parts;
				$m_id = (int) $m_id;
				$i_id = (int) $i_id;

				if ( $menu_filter && $m_id !== $menu_filter ) {
					continue;
				}
				if ( $event_filter && $ev !== $event_filter ) {
					continue;
				}

				$count = (int) $count;
				if ( isset( $totals[ $ev ] ) ) {
					$totals[ $ev ] += $count;
				}

				switch ( $group_by ) {
					case 'day':
						$bucket = $day;
						break;
					case 'menu':
						$bucket = (string) $m_id;
						break;
					case 'event':
						$bucket = $ev;
						break;
					default:
						$bucket = $day;
				}

				if ( ! isset( $grouped[ $bucket ] ) ) {
					$grouped[ $bucket ] = array( 'view' => 0, 'click' => 0, 'hover' => 0 );
				}
				if ( isset( $grouped[ $bucket ][ $ev ] ) ) {
					$grouped[ $bucket ][ $ev ] += $count;
				}
			}
		}

		// Enrich "menu" grouping with titles.
		if ( 'menu' === $group_by ) {
			$enriched = array();
			foreach ( $grouped as $mid => $counts ) {
				$post = get_post( (int) $mid );
				$title = $post ? $post->post_title : __( 'Menu supprimé', 'woo-total-menu' );
				$enriched[ $mid ] = array(
					'title'  => $title,
					'counts' => $counts,
				);
			}
			return array(
				'group_by' => 'menu',
				'days'     => $days,
				'totals'   => $totals,
				'groups'   => $enriched,
			);
		}

		return array(
			'group_by' => $group_by,
			'days'     => $days,
			'totals'   => $totals,
			'groups'   => $grouped,
		);
	}

	/**
	 * Delete analytics data older than the given number of days.
	 *
	 * @param int $days Defaults to 90.
	 * @return int Number of options deleted.
	 */
	public function cleanup( $days = 90 ) {
		$days = max( 7, (int) $days );
		$deleted = 0;
		$cutoff = strtotime( '-' . $days . ' days' );

		// Iterate backwards in time, day by day.
		for ( $i = $days + 1; $i <= $days + 365; $i++ ) {
			$day = gmdate( 'Y-m-d', strtotime( '-' . $i . ' days' ) );
			if ( strtotime( $day ) < $cutoff - YEAR_IN_SECONDS ) {
				break;
			}
			$key = self::OPTION_PREFIX . $day;
			if ( delete_option( $key ) ) {
				$deleted++;
			}
		}
		return $deleted;
	}

	/**
	 * Check whether tracking should fire for the current visitor.
	 *
	 * Exposed so the frontend JS can decide whether to send events.
	 *
	 * @return bool
	 */
	public function should_track() {
		$s = $this->get_settings();
		if ( ! $s['enabled'] ) {
			return false;
		}
		if ( is_user_logged_in() && ! $s['track_logged'] ) {
			return false;
		}
		return true;
	}
}
