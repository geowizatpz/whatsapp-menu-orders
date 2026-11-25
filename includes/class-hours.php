<?php
/**
 * Opening hours: open/closed state, labels and the next opening time.
 *
 * Pure calculation, no hooks. All times are interpreted in the site timezone
 * and ranges whose closing time is earlier than their opening time cross
 * midnight into the following day.
 *
 * @package WFO
 */

namespace WFO;

defined( 'ABSPATH' ) || exit;

/**
 * Opening hours helper.
 */
class Hours {

	/**
	 * Canonical weekday keys, Monday first.
	 */
	const DAYS = array( 'mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun' );

	/**
	 * Is the restaurant open right now (or always, when hours are disabled)?
	 *
	 * @param int|null $ts Unix timestamp to check; defaults to now.
	 * @return bool
	 */
	public static function is_open( $ts = null ) {
		$state = self::analyze( $ts );

		return (bool) $state['open'];
	}

	/**
	 * One call for the three values templates and payloads need.
	 *
	 * @param int|null $ts Unix timestamp to check; defaults to now.
	 * @return array{open:bool,label:string,next:string}
	 */
	public static function state( $ts = null ) {
		$state = self::analyze( $ts );

		return array(
			'open'  => (bool) $state['open'],
			'label' => self::build_label( $state ),
			'next'  => null === $state['next_ts'] ? '' : self::format_opening( $state['next_ts'], $state['now'] ),
		);
	}

	/**
	 * Human readable opening state, e.g. "Open now · closes 22:00".
	 *
	 * @param int|null $ts Unix timestamp to check; defaults to now.
	 * @return string
	 */
	public static function label( $ts = null ) {
		$state = self::analyze( $ts );

		return self::build_label( $state );
	}

	/**
	 * Next opening time as a human string, e.g. "tomorrow at 11:00".
	 *
	 * @param int|null $ts Unix timestamp to start from; defaults to now.
	 * @return string Empty when there is no upcoming opening.
	 */
	public static function next_opening( $ts = null ) {
		$state = self::analyze( $ts );

		return null === $state['next_ts'] ? '' : self::format_opening( $state['next_ts'], $state['now'] );
	}

	/**
	 * Normalized hours for display, all seven days included.
	 *
	 * @return array<int,array{day:string,label:string,ranges:array,hours:string,closed:bool,overnight:bool,today:bool}>
	 */
	public static function week() {
		$hours = self::normalized_hours();
		$today = self::to_datetime( null, wp_timezone() )->format( 'D' );
		$today = strtolower( $today );
		$week  = array();

		foreach ( self::DAYS as $day ) {
			$ranges    = isset( $hours[ $day ] ) ? $hours[ $day ] : array();
			$parts     = array();
			$overnight = false;

			foreach ( $ranges as $range ) {
				$parts[] = sprintf(
					/* translators: 1: opening time (H:i), 2: closing time (H:i). */
					__( '%1$s – %2$s', 'whatsapp-menu-orders' ),
					$range['open'],
					$range['close']
				);

				if ( self::to_minutes( $range['close'] ) <= self::to_minutes( $range['open'] ) ) {
					$overnight = true;
				}
			}

			$week[] = array(
				'day'       => $day,
				'label'     => self::day_label( $day ),
				'ranges'    => $ranges,
				'hours'     => $parts ? implode( ', ', $parts ) : __( 'Closed', 'whatsapp-menu-orders' ),
				'closed'    => ! $parts,
				'overnight' => $overnight,
				'today'     => $day === $today,
			);
		}

		return $week;
	}

	/**
	 * Analyze the opening state at a moment.
	 *
	 * @param int|null $ts Unix timestamp; defaults to now.
	 * @return array{now:\DateTimeImmutable,enabled:bool,hours:array,open:bool,closing_ts:int|null,next_ts:int|null}
	 */
	private static function analyze( $ts = null ) {
		$timezone = wp_timezone();
		$now      = self::to_datetime( $ts, $timezone );
		$now_ts   = $now->getTimestamp();
		$enabled  = ! empty( Settings::get( 'opening_hours_enabled', 0 ) );
		$hours    = self::normalized_hours();

		$state = array(
			'now'        => $now,
			'enabled'    => $enabled,
			'hours'      => $hours,
			'open'       => true,
			'closing_ts' => null,
			'next_ts'    => null,
		);

		// Hours disabled, or enabled with nothing configured: always open.
		if ( ! $enabled || empty( $hours ) ) {
			return $state;
		}

		$now_minutes = ( (int) $now->format( 'G' ) ) * 60 + ( (int) $now->format( 'i' ) );
		$today       = strtolower( $now->format( 'D' ) );
		$yesterday   = self::step_day( $today, -1 );
		$open        = false;
		$closing     = null;

		// Today's ranges, including the evening part of an overnight range.
		if ( ! empty( $hours[ $today ] ) ) {
			foreach ( $hours[ $today ] as $range ) {
				$open_minute  = self::to_minutes( $range['open'] );
				$close_minute = self::to_minutes( $range['close'] );

				if ( $open_minute < 0 || $close_minute < 0 ) {
					continue;
				}

				if ( $close_minute > $open_minute ) {
					if ( $now_minutes >= $open_minute && $now_minutes < $close_minute ) {
						$open    = true;
						$closing = self::latest( $closing, self::time_on_day( $now, 0, $close_minute ) );
					}
				} elseif ( $now_minutes >= $open_minute ) {
					// Overnight range: still open tonight, closes tomorrow.
					$open    = true;
					$closing = self::latest( $closing, self::time_on_day( $now, 1, $close_minute ) );
				}
			}
		}

		// Yesterday's overnight ranges that are still running this morning.
		if ( ! empty( $hours[ $yesterday ] ) ) {
			foreach ( $hours[ $yesterday ] as $range ) {
				$open_minute  = self::to_minutes( $range['open'] );
				$close_minute = self::to_minutes( $range['close'] );

				if ( $open_minute < 0 || $close_minute < 0 || $close_minute > $open_minute ) {
					continue;
				}

				if ( $now_minutes < $close_minute ) {
					$open    = true;
					$closing = self::latest( $closing, self::time_on_day( $now, 0, $close_minute ) );
				}
			}
		}

		$next = null;

		// Find the earliest range start in the next week. Offset 0 needs a
		// start strictly after "now" so an already-open range is not repeated.
		for ( $offset = 0; $offset <= 7; $offset++ ) {
			$day = self::step_day( $today, $offset );

			if ( empty( $hours[ $day ] ) ) {
				continue;
			}

			foreach ( $hours[ $day ] as $range ) {
				$open_minute = self::to_minutes( $range['open'] );

				if ( $open_minute < 0 ) {
					continue;
				}

				$candidate = self::time_on_day( $now, $offset, $open_minute );

				if ( $candidate > $now_ts && ( null === $next || $candidate < $next ) ) {
					$next = $candidate;
				}
			}
		}

		$state['open']       = $open;
		$state['closing_ts'] = $closing;
		$state['next_ts']    = $next;

		return $state;
	}

	/**
	 * Build the translated label for a state.
	 *
	 * @param array $state Result of analyze().
	 * @return string
	 */
	private static function build_label( $state ) {
		if ( empty( $state['hours'] ) ) {
			// Disabled, or enabled with no ranges: the restaurant is always open.
			return __( 'Open now', 'whatsapp-menu-orders' );
		}

		if ( ! empty( $state['open'] ) ) {
			if ( null !== $state['closing_ts'] ) {
				return sprintf(
					/* translators: %s: closing time, e.g. 22:00. */
					__( 'Open now · closes %s', 'whatsapp-menu-orders' ),
					wp_date( 'H:i', $state['closing_ts'] )
				);
			}

			return __( 'Open now', 'whatsapp-menu-orders' );
		}

		if ( null !== $state['next_ts'] ) {
			return sprintf(
				/* translators: %s: next opening, e.g. "tomorrow at 11:00". */
				__( 'Closed · opens %s', 'whatsapp-menu-orders' ),
				self::format_opening( $state['next_ts'], $state['now'] )
			);
		}

		return __( 'Closed', 'whatsapp-menu-orders' );
	}

	/**
	 * Format an opening timestamp relative to a reference moment.
	 *
	 * @param int                $next_ts Opening timestamp.
	 * @param \DateTimeImmutable $now     Reference moment in the site timezone.
	 * @return string
	 */
	private static function format_opening( $next_ts, $now ) {
		$next    = self::to_datetime( $next_ts, wp_timezone() );
		$time    = $next->format( 'H:i' );
		$next_day = $next->format( 'Y-m-d' );

		if ( $now->format( 'Y-m-d' ) === $next_day ) {
			return sprintf(
				/* translators: %s: time, e.g. 18:00. */
				__( 'today at %s', 'whatsapp-menu-orders' ),
				$time
			);
		}

		if ( $now->modify( '+1 day' )->format( 'Y-m-d' ) === $next_day ) {
			return sprintf(
				/* translators: %s: time, e.g. 11:00. */
				__( 'tomorrow at %s', 'whatsapp-menu-orders' ),
				$time
			);
		}

		return sprintf(
			/* translators: 1: weekday name, 2: time, e.g. "Monday at 11:00". */
			__( '%1$s at %2$s', 'whatsapp-menu-orders' ),
			wp_date( 'l', $next_ts ),
			$time
		);
	}

	/**
	 * Normalize the stored opening hours, dropping malformed entries.
	 *
	 * @return array<string,array<int,array{open:string,close:string}>>
	 */
	private static function normalized_hours() {
		$raw = Settings::get( 'opening_hours', array() );

		if ( ! is_array( $raw ) ) {
			return array();
		}

		$hours = array();

		foreach ( self::DAYS as $day ) {
			if ( empty( $raw[ $day ] ) || ! is_array( $raw[ $day ] ) ) {
				continue;
			}

			$ranges = array();

			foreach ( $raw[ $day ] as $range ) {
				if ( ! is_array( $range ) ) {
					continue;
				}

				$open  = self::normalize_time( isset( $range['open'] ) ? $range['open'] : '' );
				$close = self::normalize_time( isset( $range['close'] ) ? $range['close'] : '' );

				if ( '' === $open || '' === $close || $open === $close ) {
					continue;
				}

				$ranges[] = array(
					'open'  => $open,
					'close' => $close,
				);
			}

			if ( $ranges ) {
				$hours[ $day ] = $ranges;
			}
		}

		return $hours;
	}

	/**
	 * Normalize one H:i time string.
	 *
	 * @param mixed $value Raw time.
	 * @return string Empty when invalid.
	 */
	private static function normalize_time( $value ) {
		if ( ! is_scalar( $value ) ) {
			return '';
		}

		$value = trim( (string) $value );

		if ( ! preg_match( '/^([01]?\d|2[0-3]):([0-5]\d)$/', $value, $matches ) ) {
			return '';
		}

		return sprintf( '%02d:%02d', (int) $matches[1], (int) $matches[2] );
	}

	/**
	 * Minutes since midnight for an H:i string.
	 *
	 * @param string $time H:i time.
	 * @return int -1 when invalid.
	 */
	private static function to_minutes( $time ) {
		if ( ! preg_match( '/^([01]?\d|2[0-3]):([0-5]\d)$/', (string) $time, $matches ) ) {
			return -1;
		}

		return ( (int) $matches[1] ) * 60 + (int) $matches[2];
	}

	/**
	 * Move a weekday key by a number of days, wrapping the week.
	 *
	 * @param string $day  Weekday key.
	 * @param int    $step Days to add (may be negative).
	 * @return string
	 */
	private static function step_day( $day, $step ) {
		$index = array_search( $day, self::DAYS, true );

		if ( false === $index ) {
			$index = 0;
		}

		$index = ( $index + (int) $step ) % 7;

		if ( $index < 0 ) {
			$index += 7;
		}

		return self::DAYS[ $index ];
	}

	/**
	 * Timestamp of a wall-clock time on a day relative to $now.
	 *
	 * Built with DateTime::setTime() rather than seconds-since-midnight so
	 * daylight saving transitions keep the clock time correct.
	 *
	 * @param \DateTimeImmutable $now     Reference moment.
	 * @param int                $days    Day offset.
	 * @param int                $minutes Minutes since midnight.
	 * @return int Unix timestamp.
	 */
	private static function time_on_day( $now, $days, $minutes ) {
		$minutes = max( 0, (int) $minutes );
		$day     = $now->setTime( 0, 0, 0 )->modify( (int) $days . ' days' );

		return $day->setTime( intdiv( $minutes, 60 ), $minutes % 60, 0 )->getTimestamp();
	}

	/**
	 * Keep the later of two optional timestamps.
	 *
	 * @param int|null $current Current value.
	 * @param int      $candidate New candidate.
	 * @return int
	 */
	private static function latest( $current, $candidate ) {
		return null === $current ? (int) $candidate : max( (int) $current, (int) $candidate );
	}

	/**
	 * Build a DateTimeImmutable in the site timezone.
	 *
	 * @param int|null      $ts       Unix timestamp, or null for now.
	 * @param \DateTimeZone $timezone Site timezone.
	 * @return \DateTimeImmutable
	 */
	private static function to_datetime( $ts, $timezone ) {
		if ( null === $ts ) {
			return new \DateTimeImmutable( 'now', $timezone );
		}

		return ( new \DateTimeImmutable( '@' . (int) $ts ) )->setTimezone( $timezone );
	}

	/**
	 * Translated weekday label.
	 *
	 * @param string $day Weekday key.
	 * @return string
	 */
	private static function day_label( $day ) {
		$labels = array(
			'mon' => __( 'Monday', 'whatsapp-menu-orders' ),
			'tue' => __( 'Tuesday', 'whatsapp-menu-orders' ),
			'wed' => __( 'Wednesday', 'whatsapp-menu-orders' ),
			'thu' => __( 'Thursday', 'whatsapp-menu-orders' ),
			'fri' => __( 'Friday', 'whatsapp-menu-orders' ),
			'sat' => __( 'Saturday', 'whatsapp-menu-orders' ),
			'sun' => __( 'Sunday', 'whatsapp-menu-orders' ),
		);

		return isset( $labels[ $day ] ) ? $labels[ $day ] : $day;
	}
}
