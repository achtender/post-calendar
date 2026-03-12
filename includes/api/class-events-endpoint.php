<?php

namespace PostCalendar\API;

use DateTimeImmutable;
use DateTimeZone;
use WP_Query;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Events_Endpoint {
	private const EVENT_ENABLED_META = '_post_is_event';
	private const EVENT_ALL_DAY_META = '_post_is_allday';
	private const EVENT_START_META   = '_post_start_date';
	private const EVENT_END_META     = '_post_end_date';

	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes(): void {
		register_rest_route(
			'post-calendar/v1',
			'/events',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_events' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'post_types' => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'start'      => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'end'        => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	public function get_events( WP_REST_Request $request ): WP_REST_Response {
		$post_types  = $this->resolve_post_types( $request->get_param( 'post_types' ) );
		$range_start = $this->parse_request_date( $request->get_param( 'start' ) );
		$range_end   = $this->parse_request_date( $request->get_param( 'end' ) );

		if ( empty( $post_types ) ) {
			return rest_ensure_response(
				array(
					'events' => array(),
				)
			);
		}

		$query = new WP_Query(
			array(
				'post_type'      => $post_types,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'meta_key'       => self::EVENT_START_META,
				'orderby'        => 'meta_value',
				'meta_type'      => 'DATETIME',
				'order'          => 'ASC',
				'fields'         => 'ids',
				'meta_query'     => $this->build_meta_query( $range_start, $range_end ),
			)
		);

		$events = array();

		foreach ( $query->posts as $post_id ) {
			$event = $this->build_event( (int) $post_id );

			if ( ! $event ) {
				continue;
			}

			if ( ! $this->is_in_requested_range( $event, $range_start, $range_end ) ) {
				continue;
			}

			$events[] = $event;
		}

		return rest_ensure_response(
			array(
				'events' => $events,
			)
		);
	}

	private function build_meta_query( ?DateTimeImmutable $range_start, ?DateTimeImmutable $range_end ): array {
		$meta_query = array(
			'relation' => 'AND',
			array(
				'key'   => self::EVENT_ENABLED_META,
				'value' => '1',
			),
		);

		if ( $range_end ) {
			$meta_query[] = array(
				'key'     => self::EVENT_START_META,
				'value'   => $this->format_request_date_for_meta( $range_end ),
				'compare' => '<=',
				'type'    => 'DATETIME',
			);
		}

		if ( $range_start ) {
			$formatted_start = $this->format_request_date_for_meta( $range_start );

			$meta_query[] = array(
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

		return $meta_query;
	}

	private function build_event( int $post_id ): ?array {
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

	private function is_in_requested_range( array $event, ?DateTimeImmutable $range_start, ?DateTimeImmutable $range_end ): bool {
		$event_start = $this->parse_request_date( $event['start'] );
		$event_end   = $this->parse_request_date( $event['end'] );

		if ( ! $event_start || ! $event_end ) {
			return false;
		}

		if ( $range_start && $event_end < $range_start ) {
			return false;
		}

		if ( $range_end && $event_start > $range_end ) {
			return false;
		}

		return true;
	}

	private function parse_request_date( ?string $value ): ?DateTimeImmutable {
		if ( ! $value ) {
			return null;
		}

		try {
			return new DateTimeImmutable( $value, new DateTimeZone( wp_timezone_string() ?: 'UTC' ) );
		} catch ( \Exception $exception ) {
			return null;
		}
	}

	private function format_request_date_for_meta( DateTimeImmutable $date ): string {
		return $date->setTimezone( wp_timezone() )->format( 'Y-m-d H:i:s' );
	}

	private function resolve_post_types( ?string $post_types ): array {
		$available_types = \PostCalendar\Admin\Settings_Page::get_allowed_post_types();

		if ( ! $post_types ) {
			return $available_types;
		}

		$requested_types = \PostCalendar\Admin\Settings_Page::sanitize_slug_list( $post_types );

		return array_values( array_intersect( $available_types, $requested_types ) ) ?: $available_types;
	}
}
