<?php

namespace PostCalendar\Event_Sources;

use DateTimeImmutable;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Query;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Rest_Controller {
	public const REST_NAMESPACE = 'post-calendar/v1';
	public const REST_ROUTE     = '/events';

	private const DEFAULT_PER_PAGE = 1000;

	/**
	 * @var Event_Query_Service
	 */
	private $event_query_service;

	public function __construct( ?Event_Query_Service $event_query_service = null ) {
		$this->event_query_service = $event_query_service ?: new Event_Query_Service();

		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			self::REST_ROUTE,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_events' ),
					'permission_callback' => '__return_true',
					'args'                => $this->get_collection_params(),
				),
			)
		);
	}

	public function get_collection_params(): array {
		return array(
			'post_types' => array(
			'description'       => __( 'Restrict the collection to a comma-separated list of source post types.', 'post-calendar' ),
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			),
			'query_vars' => array(
			'description'       => __( 'Restrict the collection with a JSON-encoded subset of Bricks query vars.', 'post-calendar' ),
			'type'              => 'string',
			'sanitize_callback' => array( $this, 'sanitize_query_vars_param' ),
			),
			'start' => array(
			'description'       => __( 'Limit results to events that overlap the supplied ISO 8601 start date.', 'post-calendar' ),
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			),
			'end' => array(
			'description'       => __( 'Limit results to events that overlap the supplied ISO 8601 end date.', 'post-calendar' ),
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			),
			'per_page' => array(
				'description'       => __( 'Limit the number of source posts evaluated for the response.', 'post-calendar' ),
				'type'              => 'integer',
				'default'           => self::DEFAULT_PER_PAGE,
				'minimum'           => 1,
				'maximum'           => self::DEFAULT_PER_PAGE,
				'sanitize_callback' => 'absint',
			),
		);
	}

	public function get_events( WP_REST_Request $request ): WP_REST_Response {
		$source_types = $this->event_query_service->resolve_post_types( $request->get_param( 'post_types' ) );
		$query_vars   = $this->parse_query_vars_param( $request->get_param( 'query_vars' ) );
		$range_start  = $this->event_query_service->parse_request_date( $request->get_param( 'start' ) );
		$range_end    = $this->event_query_service->parse_request_date( $request->get_param( 'end' ) );
		$source_types = $this->merge_source_types_from_query_vars( $source_types, $query_vars );

		if ( empty( $source_types ) ) {
			return new WP_REST_Response( array() );
		}

		$args = $this->merge_supported_query_vars(
			array(
				'post_type'                 => $source_types,
				'post_status'               => 'publish',
				'posts_per_page'            => $this->resolve_posts_per_page( $request ),
				'no_found_rows'             => true,
				'update_post_term_cache'    => false,
				'ignore_sticky_posts'       => true,
				'post_calendar_source_types' => $source_types,
			),
			$query_vars
		);

		$args['meta_query'] = $this->merge_meta_query_constraints(
			$args['meta_query'] ?? array(),
			$this->build_rest_meta_constraints( $range_start, $range_end )
		);

		if ( ! $request->has_param( 'orderby' ) && empty( $query_vars['orderby'] ) ) {
			$args['orderby']   = 'meta_value';
			$args['meta_key']  = Event_Query_Service::EVENT_RANGE_START_META;
			$args['meta_type'] = 'DATETIME';
			$args['order']     = 'ASC';
		}

		$query  = new WP_Query( $args );
		$events = $this->event_query_service->build_events_for_posts( $query->posts, $range_start, $range_end );

		return new WP_REST_Response( $events );
	}

	public function sanitize_query_vars_param( $value ): string {
		return is_string( $value ) ? wp_unslash( $value ) : '';
	}

	private function build_rest_meta_constraints( ?DateTimeImmutable $range_start, ?DateTimeImmutable $range_end ): array {
		return $this->event_query_service->build_range_meta_query( $range_start, $range_end );
	}

	private function merge_meta_query_constraints( $existing_meta_query, array $range_meta_query ): array {
		$meta_query = array(
			'relation' => 'AND',
		);

		if ( ! empty( $existing_meta_query ) ) {
			$meta_query[] = $existing_meta_query;
		}

		foreach ( $range_meta_query as $constraint ) {
			$meta_query[] = $constraint;
		}

		return $meta_query;
	}

	private function parse_query_vars_param( $value ): array {
		if ( ! is_string( $value ) || '' === $value ) {
			return array();
		}

		$decoded = json_decode( $value, true );

		if ( ! is_array( $decoded ) ) {
			return array();
		}

		$sanitized      = array();

		foreach ( Event_Config::get_supported_query_var_keys() as $key ) {
			if ( ! array_key_exists( $key, $decoded ) ) {
				continue;
			}

			$sanitized[ $key ] = $this->sanitize_query_var_value( $decoded[ $key ] );
		}

		return $sanitized;
	}

	private function sanitize_query_var_value( $value ) {
		if ( is_array( $value ) ) {
			$sanitized = array();

			foreach ( $value as $key => $item ) {
				$sanitized_key = is_string( $key ) ? sanitize_key( $key ) : $key;
				$sanitized[ $sanitized_key ] = $this->sanitize_query_var_value( $item );
			}

			return $sanitized;
		}

		if ( is_bool( $value ) || is_int( $value ) || is_float( $value ) ) {
			return $value;
		}

		if ( is_string( $value ) ) {
			return sanitize_text_field( $value );
		}

		return null;
	}

	private function merge_source_types_from_query_vars( array $source_types, array &$query_vars ): array {
		if ( empty( $query_vars['post_type'] ) ) {
			return $source_types;
		}

		$query_source_types = $this->event_query_service->resolve_post_types( is_array( $query_vars['post_type'] ) ? implode( ',', $query_vars['post_type'] ) : (string) $query_vars['post_type'] );
		unset( $query_vars['post_type'] );

		if ( empty( $query_source_types ) ) {
			return array();
		}

		$intersected = array_values( array_intersect( $source_types, $query_source_types ) );

		return ! empty( $intersected ) ? $intersected : array( '__post_calendar_no_results__' );
	}

	private function merge_supported_query_vars( array $args, array $query_vars ): array {
		if ( empty( $query_vars ) ) {
			return $args;
		}

		foreach ( Event_Config::get_supported_query_var_list_keys() as $key ) {
			if ( empty( $query_vars[ $key ] ) || ! is_array( $query_vars[ $key ] ) ) {
				continue;
			}

			$args[ $key ] = array_values( array_filter( array_map( 'absint', $query_vars[ $key ] ) ) );
		}

		foreach ( Event_Config::get_supported_query_var_direct_keys() as $key ) {
			if ( ! array_key_exists( $key, $query_vars ) || '' === $query_vars[ $key ] || null === $query_vars[ $key ] ) {
				continue;
			}

			$args[ $key ] = $query_vars[ $key ];
		}

		foreach ( array( 'tax_query', 'meta_query', 'date_query' ) as $key ) {
			if ( empty( $query_vars[ $key ] ) || ! is_array( $query_vars[ $key ] ) ) {
				continue;
			}

			$args[ $key ] = $query_vars[ $key ];
		}

		return $args;
	}

	private function resolve_posts_per_page( WP_REST_Request $request ): int {
		$per_page = absint( $request->get_param( 'per_page' ) );

		if ( $per_page > 0 ) {
			return min( $per_page, self::DEFAULT_PER_PAGE );
		}

		return self::DEFAULT_PER_PAGE;
	}
}