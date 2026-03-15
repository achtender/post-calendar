<?php

namespace PostCalendar\Event_Sources;

use DateInterval;
use DateTimeImmutable;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Event_Query_Service {
	public const EVENTS_META                = Event_Config::EVENTS_META;
	public const EVENT_HAS_EVENTS_META      = Event_Config::EVENT_HAS_EVENTS_META;
	public const EVENT_RANGE_START_META     = Event_Config::EVENT_RANGE_START_META;
	public const EVENT_RANGE_END_META       = Event_Config::EVENT_RANGE_END_META;
	public const EVENT_START_META           = Event_Config::EVENT_START_META;
	public const EVENT_END_META             = Event_Config::EVENT_END_META;

	private const DEFAULT_EXPANSION_WINDOW = 'P1Y';
	public const REPEAT_NONE               = 'none';
	public const REPEAT_WEEKLY             = 'weekly';
	public const REPEAT_MONTHLY            = 'monthly';
	public const REPEAT_YEARLY             = 'yearly';
	private const MAX_OCCURRENCES          = 500;

	public function build_range_meta_query( ?DateTimeImmutable $range_start, ?DateTimeImmutable $range_end ): array {
		$range_meta_query = array(
			array(
				'key'   => self::EVENT_HAS_EVENTS_META,
				'value' => '1',
			),
			array(
				'key'     => self::EVENT_RANGE_START_META,
				'compare' => 'EXISTS',
			),
			array(
				'key'     => self::EVENT_RANGE_START_META,
				'value'   => '',
				'compare' => '!=',
			),
		);

		if ( $range_end ) {
			$range_meta_query[] = array(
				'key'     => self::EVENT_RANGE_START_META,
				'value'   => $this->format_request_date_for_meta( $range_end ),
				'compare' => '<=',
				'type'    => 'DATETIME',
			);
		}

		if ( $range_start ) {
			$formatted_start    = $this->format_request_date_for_meta( $range_start );
			$range_meta_query[] = array(
				'relation' => 'OR',
				array(
					'key'     => self::EVENT_RANGE_END_META,
					'value'   => $formatted_start,
					'compare' => '>=',
					'type'    => 'DATETIME',
				),
				array(
					'relation' => 'OR',
					array(
						'key'     => self::EVENT_RANGE_END_META,
						'compare' => 'NOT EXISTS',
					),
					array(
						'key'   => self::EVENT_RANGE_END_META,
						'value' => '',
					),
				),
			);
		}

		return $range_meta_query;
	}

	public function build_events_for_posts( array $posts, ?DateTimeImmutable $range_start, ?DateTimeImmutable $range_end ): array {
		$events = array();

		foreach ( $posts as $post ) {
			$post_id = is_object( $post ) && isset( $post->ID ) ? (int) $post->ID : (int) $post;

			if ( $post_id <= 0 ) {
				continue;
			}

			$events = array_merge( $events, $this->build_events_for_post( $post_id, $range_start, $range_end ) );
		}

		usort(
			$events,
			static function ( array $left, array $right ): int {
				$start_comparison = strcmp( (string) $left['start'], (string) $right['start'] );

				if ( 0 !== $start_comparison ) {
					return $start_comparison;
				}

				return strcmp( (string) $left['title'], (string) $right['title'] );
			}
		);

		return $events;
	}

	public function build_events_for_post( int $post_id, ?DateTimeImmutable $range_start, ?DateTimeImmutable $range_end ): array {
		$events      = array();
		$definitions = $this->get_event_definitions_for_post( $post_id );

		foreach ( $definitions as $definition ) {
			if ( self::REPEAT_NONE === $definition['repeat'] ) {
				$events = array_merge( $events, $this->build_single_event_set( $definition, $definition['start'], $definition['end'], $range_start, $range_end ) );
				continue;
			}

			switch ( $definition['repeat'] ) {
				case self::REPEAT_WEEKLY:
					$events = array_merge( $events, $this->build_weekly_events( $definition, $range_start, $range_end ) );
					break;

				case self::REPEAT_MONTHLY:
					$events = array_merge( $events, $this->build_monthly_events( $definition, $range_start, $range_end ) );
					break;

				case self::REPEAT_YEARLY:
					$events = array_merge( $events, $this->build_yearly_events( $definition, $range_start, $range_end ) );
					break;

				default:
					$events = array_merge( $events, $this->build_single_event_set( $definition, $definition['start'], $definition['end'], $range_start, $range_end ) );
			}
		}

		return $events;
	}

	public function get_event_definitions_for_post( int $post_id ): array {
		$rows = $this->get_event_rows( $post_id );

		if ( empty( $rows ) ) {
			return array();
		}

		$base_definition = array(
			'post_id'    => $post_id,
			'title'      => get_the_title( $post_id ),
			'url'        => get_permalink( $post_id ),
			'post_type'  => get_post_type( $post_id ),
			'excerpt'    => $this->get_event_excerpt( $post_id ),
			'tags'       => $this->get_event_terms( $post_id ),
		);
		$definitions     = array();

		foreach ( array_values( $rows ) as $event_index => $row ) {
			$definition = $this->normalize_event_definition( $base_definition, is_array( $row ) ? $row : array(), $event_index );

			if ( null === $definition ) {
				continue;
			}

			$definitions[] = $definition;
		}

		return $definitions;
	}

	public function parse_request_date( ?string $value ): ?DateTimeImmutable {
		return Event_Date_Parser::parse( $value );
	}

	public function resolve_post_types( ?string $post_types ): array {
		$available_types = Settings_Page::get_event_source_post_types();

		if ( ! $post_types ) {
			return $available_types;
		}

		return Settings_Page::resolve_event_source_post_types( $post_types ) ?: $available_types;
	}

	private function build_single_event_set( array $definition, DateTimeImmutable $occurrence_start, DateTimeImmutable $occurrence_end, ?DateTimeImmutable $range_start, ?DateTimeImmutable $range_end ): array {
		if ( ! $this->event_overlaps_range( $occurrence_start, $occurrence_end, $range_start, $range_end ) ) {
			return array();
		}

		return array(
			$this->format_occurrence_event( $definition, $occurrence_start, $occurrence_end )
		);
	}

	private function build_weekly_events( array $definition, ?DateTimeImmutable $range_start, ?DateTimeImmutable $range_end ): array {
		$events          = array();
		$duration        = $definition['end']->getTimestamp() - $definition['start']->getTimestamp();
		$search_end      = $this->resolve_expansion_end( $definition, $range_start, $range_end );
		$base_week_start = $this->get_week_start( $definition['start'] );
		$weekday_codes   = $definition['repeat_byday'];
		$start_at        = $this->resolve_weekly_start_index( $definition['start'], $range_start, $definition['repeat_interval'] );

		for ( $occurrence_count = 0, $week_index = $start_at; $occurrence_count < self::MAX_OCCURRENCES; $week_index += $definition['repeat_interval'] ) {
			$week_start = $base_week_start->modify( '+' . ( $week_index * 7 ) . ' days' );

			foreach ( $weekday_codes as $weekday_code ) {
				$occurrence_start = $this->create_weekday_occurrence( $definition['start'], $week_start, $weekday_code );

				if ( $occurrence_start < $definition['start'] ) {
					continue;
				}

				if ( $definition['repeat_until'] && $occurrence_start > $definition['repeat_until'] ) {
					return $events;
				}

				if ( $occurrence_start > $search_end ) {
					return $events;
				}

				$occurrence_end = $occurrence_start->modify( sprintf( '+%d seconds', max( 0, $duration ) ) );

				if ( $this->event_overlaps_range( $occurrence_start, $occurrence_end, $range_start, $range_end ) ) {
					$events[] = $this->format_occurrence_event( $definition, $occurrence_start, $occurrence_end );
				}

				++$occurrence_count;
			}
		}

		return $events;
	}

	private function resolve_weekly_start_index( DateTimeImmutable $base_start, ?DateTimeImmutable $range_start, int $interval ): int {
		if ( ! $range_start || $range_start <= $base_start ) {
			return 0;
		}

		$base_week_start  = $this->get_week_start( $base_start );
		$range_week_start = $this->get_week_start( $range_start );
		$days_difference  = (int) floor( ( $range_week_start->getTimestamp() - $base_week_start->getTimestamp() ) / 86400 );
		$weeks_difference = max( 0, intdiv( max( 0, $days_difference ), 7 ) );

		return intdiv( $weeks_difference, $interval ) * $interval;
	}

	private function build_monthly_events( array $definition, ?DateTimeImmutable $range_start, ?DateTimeImmutable $range_end ): array {
		$events     = array();
		$duration   = $definition['end']->getTimestamp() - $definition['start']->getTimestamp();
		$search_end = $this->resolve_expansion_end( $definition, $range_start, $range_end );
		$start_at   = $this->resolve_monthly_start_index( $definition['start'], $range_start, $definition['repeat_interval'] );

		for ( $occurrence_count = 0, $month_index = $start_at; $occurrence_count < self::MAX_OCCURRENCES; $month_index += $definition['repeat_interval'], ++$occurrence_count ) {
			$occurrence_start = $this->create_monthly_occurrence( $definition['start'], $month_index );

			if ( $definition['repeat_until'] && $occurrence_start > $definition['repeat_until'] ) {
				break;
			}

			if ( $occurrence_start > $search_end ) {
				break;
			}

			$occurrence_end = $occurrence_start->modify( sprintf( '+%d seconds', max( 0, $duration ) ) );

			if ( $this->event_overlaps_range( $occurrence_start, $occurrence_end, $range_start, $range_end ) ) {
				$events[] = $this->format_occurrence_event( $definition, $occurrence_start, $occurrence_end );
			}
		}

		return $events;
	}

	private function build_yearly_events( array $definition, ?DateTimeImmutable $range_start, ?DateTimeImmutable $range_end ): array {
		$events     = array();
		$duration   = $definition['end']->getTimestamp() - $definition['start']->getTimestamp();
		$search_end = $this->resolve_expansion_end( $definition, $range_start, $range_end );
		$start_at   = $this->resolve_yearly_start_index( $definition['start'], $range_start, $definition['repeat_interval'] );

		for ( $occurrence_count = 0, $year_index = $start_at; $occurrence_count < self::MAX_OCCURRENCES; $year_index += $definition['repeat_interval'], ++$occurrence_count ) {
			$occurrence_start = $this->create_yearly_occurrence( $definition['start'], $year_index );

			if ( $definition['repeat_until'] && $occurrence_start > $definition['repeat_until'] ) {
				break;
			}

			if ( $occurrence_start > $search_end ) {
				break;
			}

			$occurrence_end = $occurrence_start->modify( sprintf( '+%d seconds', max( 0, $duration ) ) );

			if ( $this->event_overlaps_range( $occurrence_start, $occurrence_end, $range_start, $range_end ) ) {
				$events[] = $this->format_occurrence_event( $definition, $occurrence_start, $occurrence_end );
			}
		}

		return $events;
	}

	private function normalize_event_definition( array $base_definition, array $row, int $event_index ): ?array {
		$start = Event_Date_Parser::parse( $this->get_row_value( $row, 'start_date' ) );

		if ( ! $start ) {
			return null;
		}

		$end = Event_Date_Parser::parse( $this->get_row_value( $row, 'end_date' ) );

		if ( ! $end || $end < $start ) {
			$end = $start;
		}

		$repeat = $this->normalize_repeat_value( $this->get_row_value( $row, 'repeat' ) );

		return array(
			'post_id'         => $base_definition['post_id'],
			'event_index'     => $event_index,
			'definition_id'   => $base_definition['post_id'] . ':' . $event_index,
			'title'           => $this->normalize_event_title( $this->get_row_value( $row, 'label' ), $base_definition['title'] ),
			'start'           => $start,
			'end'             => $end,
			'all_day'         => $this->normalize_boolean( $this->get_row_value( $row, 'all_day' ) ),
			'url'             => $base_definition['url'],
			'post_type'       => $base_definition['post_type'],
			'excerpt'         => $base_definition['excerpt'],
			'tags'            => $base_definition['tags'],
			'repeat'          => $repeat,
			'repeat_interval' => max( 1, absint( $this->get_row_value( $row, 'repeat_interval' ) ) ),
			'repeat_until'    => Event_Date_Parser::parse( $this->get_row_value( $row, 'repeat_until' ) ),
			'repeat_byday'    => $this->normalize_repeat_byday( $this->get_row_value( $row, 'repeat_byday' ), $start ),
		);
	}

	private function format_occurrence_event( array $definition, DateTimeImmutable $occurrence_start, DateTimeImmutable $occurrence_end ): array {
		return array(
			'id'         => $this->build_occurrence_id( (int) $definition['post_id'], (int) $definition['event_index'], $occurrence_start ),
			'title'    => $definition['title'],
			'start'    => $occurrence_start->format( DATE_ATOM ),
			'end'      => $occurrence_end->format( DATE_ATOM ),
			'allDay'   => $definition['all_day'],
			'url'      => $definition['url'],
			'postType' => $definition['post_type'],
			'excerpt'  => $definition['excerpt'],
			'tags'     => $definition['tags'],
			'eventIndex' => (int) $definition['event_index'],
		);
	}

	private function build_occurrence_id( int $post_id, int $event_index, DateTimeImmutable $occurrence_start ): string {
		return $post_id . ':' . $event_index . ':' . $occurrence_start->format( 'Y-m-d\TH:i:sP' );
	}

	private function normalize_repeat_value( $value ): string {
		$repeat = is_string( $value ) ? sanitize_key( $value ) : self::REPEAT_NONE;

		if ( in_array( $repeat, array( self::REPEAT_WEEKLY, self::REPEAT_MONTHLY, self::REPEAT_YEARLY ), true ) ) {
			return $repeat;
		}

		return self::REPEAT_NONE;
	}

	private function normalize_repeat_byday( $value, DateTimeImmutable $start ): array {
		$weekday_codes = array();

		if ( is_array( $value ) ) {
			$weekday_codes = $value;
		} elseif ( is_string( $value ) && '' !== trim( $value ) ) {
			$weekday_codes = array_map( 'trim', explode( ',', $value ) );
		}

		$weekday_codes = array_values(
			array_filter(
				array_unique(
					array_map(
						static function ( $weekday_code ): string {
							return strtoupper( sanitize_key( (string) $weekday_code ) );
						},
						$weekday_codes
					)
				)
			)
		);

		if ( empty( $weekday_codes ) ) {
			$weekday_codes[] = $this->get_weekday_code( $start );
		}

		usort(
			$weekday_codes,
			array( $this, 'compare_weekday_codes' )
		);

		return $weekday_codes;
	}

	private function compare_weekday_codes( string $left, string $right ): int {
		return $this->get_weekday_number_from_code( $left ) <=> $this->get_weekday_number_from_code( $right );
	}

	private function get_weekday_code( DateTimeImmutable $date ): string {
		$weekday_map = array(
			1 => 'MO',
			2 => 'TU',
			3 => 'WE',
			4 => 'TH',
			5 => 'FR',
			6 => 'SA',
			7 => 'SU',
		);

		return $weekday_map[ (int) $date->format( 'N' ) ] ?? 'MO';
	}

	private function get_weekday_number_from_code( string $weekday_code ): int {
		$weekday_map = array(
			'MO' => 1,
			'TU' => 2,
			'WE' => 3,
			'TH' => 4,
			'FR' => 5,
			'SA' => 6,
			'SU' => 7,
		);

		return $weekday_map[ $weekday_code ] ?? 1;
	}

	private function resolve_expansion_end( array $definition, ?DateTimeImmutable $range_start, ?DateTimeImmutable $range_end ): DateTimeImmutable {
		$expansion_end = $range_end;

		if ( ! $expansion_end ) {
			$anchor        = $range_start ?: $definition['start'];
			$expansion_end = $anchor->add( new DateInterval( self::DEFAULT_EXPANSION_WINDOW ) );
		}

		if ( $definition['repeat_until'] && $definition['repeat_until'] < $expansion_end ) {
			return $definition['repeat_until'];
		}

		return $expansion_end;
	}

	private function resolve_monthly_start_index( DateTimeImmutable $base_start, ?DateTimeImmutable $range_start, int $interval ): int {
		if ( ! $range_start || $range_start <= $base_start ) {
			return 0;
		}

		$month_difference = ( ( (int) $range_start->format( 'Y' ) - (int) $base_start->format( 'Y' ) ) * 12 ) + ( (int) $range_start->format( 'n' ) - (int) $base_start->format( 'n' ) );

		return max( 0, intdiv( max( 0, $month_difference ), $interval ) * $interval );
	}

	private function resolve_yearly_start_index( DateTimeImmutable $base_start, ?DateTimeImmutable $range_start, int $interval ): int {
		if ( ! $range_start || $range_start <= $base_start ) {
			return 0;
		}

		$year_difference = (int) $range_start->format( 'Y' ) - (int) $base_start->format( 'Y' );

		return max( 0, intdiv( max( 0, $year_difference ), $interval ) * $interval );
	}

	private function get_week_start( DateTimeImmutable $date ): DateTimeImmutable {
		$days_from_monday = (int) $date->format( 'N' ) - 1;

		return $date->modify( sprintf( '-%d days', $days_from_monday ) );
	}

	private function create_weekday_occurrence( DateTimeImmutable $base_start, DateTimeImmutable $week_start, string $weekday_code ): DateTimeImmutable {
		$day_offset = $this->get_weekday_number_from_code( $weekday_code ) - 1;
		$time       = $base_start->format( 'H:i:s' );

		return $week_start
			->modify( '+' . $day_offset . ' days' )
			->setTime( (int) substr( $time, 0, 2 ), (int) substr( $time, 3, 2 ), (int) substr( $time, 6, 2 ) );
	}

	private function create_monthly_occurrence( DateTimeImmutable $base_start, int $month_index ): DateTimeImmutable {
		$base_month_index = ( (int) $base_start->format( 'Y' ) * 12 ) + ( (int) $base_start->format( 'n' ) - 1 ) + $month_index;
		$year             = intdiv( $base_month_index, 12 );
		$month            = ( $base_month_index % 12 ) + 1;
		$day              = min( (int) $base_start->format( 'j' ), cal_days_in_month( CAL_GREGORIAN, $month, $year ) );

		return $base_start->setDate( $year, $month, $day );
	}

	private function create_yearly_occurrence( DateTimeImmutable $base_start, int $year_index ): DateTimeImmutable {
		$year  = (int) $base_start->format( 'Y' ) + $year_index;
		$month = (int) $base_start->format( 'n' );
		$day   = min( (int) $base_start->format( 'j' ), cal_days_in_month( CAL_GREGORIAN, $month, $year ) );

		return $base_start->setDate( $year, $month, $day );
	}

	private function event_overlaps_range( DateTimeImmutable $event_start, DateTimeImmutable $event_end, ?DateTimeImmutable $range_start, ?DateTimeImmutable $range_end ): bool {
		if ( $range_start && $event_end < $range_start ) {
			return false;
		}

		if ( $range_end && $event_start > $range_end ) {
			return false;
		}

		return true;
	}

	private function format_request_date_for_meta( DateTimeImmutable $date ): string {
		return $date->setTimezone( wp_timezone() )->format( 'Y-m-d H:i:s' );
	}

	private function get_event_excerpt( int $post_id ): string {
		$excerpt = get_the_excerpt( $post_id );

		if ( ! is_string( $excerpt ) ) {
			return '';
		}

		return wp_strip_all_tags( $excerpt );
	}

	private function get_event_terms( int $post_id ): array {
		$taxonomy_names = get_object_taxonomies( get_post_type( $post_id ), 'names' );
		$labels         = array();

		foreach ( $taxonomy_names as $taxonomy_name ) {
			if ( 'post_format' === $taxonomy_name ) {
				continue;
			}

			$terms = wp_get_post_terms(
				$post_id,
				$taxonomy_name,
				array(
					'fields' => 'names',
				)
			);

			if ( is_wp_error( $terms ) || empty( $terms ) ) {
				continue;
			}

			foreach ( $terms as $term_name ) {
				if ( ! in_array( $term_name, $labels, true ) ) {
					$labels[] = $term_name;
				}

				if ( count( $labels ) >= 3 ) {
					return $labels;
				}
			}
		}

		return $labels;
	}

	private function get_event_rows( int $post_id ): array {
		$stored_rows = get_post_meta( $post_id, self::EVENTS_META, true );

		if ( is_array( $stored_rows ) ) {
			return $stored_rows;
		}

		return array();
	}

	private function get_row_value( array $row, string $key ) {
		return array_key_exists( $key, $row ) ? $row[ $key ] : null;
	}

	private function normalize_boolean( $value ): bool {
		if ( is_bool( $value ) ) {
			return $value;
		}

		if ( is_numeric( $value ) ) {
			return 1 === (int) $value;
		}

		if ( is_string( $value ) ) {
			return in_array( strtolower( $value ), array( '1', 'true', 'yes', 'on' ), true );
		}

		return false;
	}

	private function normalize_event_title( $value, string $fallback ): string {
		if ( is_string( $value ) ) {
			$label = trim( $value );

			if ( '' !== $label ) {
				return $label;
			}
		}

		return $fallback;
	}

}