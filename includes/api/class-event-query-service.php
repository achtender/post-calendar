<?php

namespace PostCalendar\API;

use DateTimeImmutable;
use DateTimeZone;
use PostCalendar\Admin\Settings_Page;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Event_Query_Service {
	public const EVENT_ENABLED_META = '_post_is_event';
	public const EVENT_ALL_DAY_META = '_post_is_allday';
	public const EVENT_START_META   = '_post_start_date';
	public const EVENT_END_META     = '_post_end_date';

	public function build_range_meta_query( ?DateTimeImmutable $range_start, ?DateTimeImmutable $range_end ): array {
		$range_meta_query = array();

		if ( $range_end ) {
			$range_meta_query[] = array(
				'key'     => self::EVENT_START_META,
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
					'key'     => self::EVENT_END_META,
					'value'   => $formatted_start,
					'compare' => '>=',
					'type'    => 'DATETIME',
				),
				array(
					'relation' => 'AND',
					array(
						'relation' => 'OR',
						array(
							'key'     => self::EVENT_END_META,
							'compare' => 'NOT EXISTS',
						),
						array(
							'key'   => self::EVENT_END_META,
							'value' => '',
						),
					),
					array(
						'key'     => self::EVENT_START_META,
						'value'   => $formatted_start,
						'compare' => '>=',
						'type'    => 'DATETIME',
					),
				),
			);
		}

		return $range_meta_query;
	}

	public function build_event( int $post_id ): ?array {
		$start = $this->create_iso8601_string( get_post_meta( $post_id, self::EVENT_START_META, true ) );
		$end   = $this->create_iso8601_string( get_post_meta( $post_id, self::EVENT_END_META, true ) );

		if ( ! $start ) {
			return null;
		}

		if ( ! $end ) {
			$end = $start;
		}

		return array(
			'id'       => $post_id,
			'title'    => get_the_title( $post_id ),
			'start'    => $start,
			'end'      => $end,
			'allDay'   => '1' === get_post_meta( $post_id, self::EVENT_ALL_DAY_META, true ),
			'url'      => get_permalink( $post_id ),
			'postType' => get_post_type( $post_id ),
			'excerpt'  => $this->get_event_excerpt( $post_id ),
			'tags'     => $this->get_event_terms( $post_id ),
		);
	}

	public function parse_request_date( ?string $value ): ?DateTimeImmutable {
		if ( ! $value ) {
			return null;
		}

		try {
			return new DateTimeImmutable( $value, new DateTimeZone( wp_timezone_string() ?: 'UTC' ) );
		} catch ( \Exception $exception ) {
			return null;
		}
	}

	public function resolve_post_types( ?string $post_types ): array {
		$available_types = Settings_Page::get_allowed_post_types();

		if ( ! $post_types ) {
			return $available_types;
		}

		$requested_types = Settings_Page::sanitize_slug_list( $post_types );

		return array_values( array_intersect( $available_types, $requested_types ) ) ?: $available_types;
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

	private function create_iso8601_string( $value ): ?string {
		if ( ! is_string( $value ) || '' === trim( $value ) ) {
			return null;
		}

		$timezone = wp_timezone();
		$date     = DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $value, $timezone );

		if ( false === $date ) {
			try {
				$date = new DateTimeImmutable( $value, $timezone );
			} catch ( \Exception $exception ) {
				return null;
			}
		}

		return $date->format( DATE_ATOM );
	}

}