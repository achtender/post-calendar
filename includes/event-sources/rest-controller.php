<?php

namespace PostCalendar\Event_Sources;

use DateTimeImmutable;
use WP_Post;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Rest_Controller {
	private const DEFAULT_PER_PAGE = 1000;

	/**
	 * @var Event_Query_Service
	 */
	private $event_query_service;

	public function __construct( ?Event_Query_Service $event_query_service = null ) {
		$this->event_query_service = $event_query_service ?: new Event_Query_Service();

		add_filter( 'rest_' . Post_Type::SLUG . '_collection_params', array( $this, 'filter_collection_params' ) );
		add_filter( 'rest_' . Post_Type::SLUG . '_query', array( $this, 'filter_collection_query' ), 10, 2 );
		add_filter( 'rest_prepare_' . Post_Type::SLUG, array( $this, 'prepare_item_for_response' ), 10, 3 );
	}

	public function filter_collection_params( array $params ): array {
		$params['post_types'] = array(
			'description'       => __( 'Restrict the collection to a comma-separated list of source post types.', 'post-calendar' ),
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
		);

		$params['query_vars'] = array(
			'description'       => __( 'Restrict the collection with a JSON-encoded subset of Bricks query vars.', 'post-calendar' ),
			'type'              => 'string',
			'sanitize_callback' => array( $this, 'sanitize_query_vars_param' ),
		);

		$params['start'] = array(
			'description'       => __( 'Limit results to events that overlap the supplied ISO 8601 start date.', 'post-calendar' ),
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
		);

		$params['end'] = array(
			'description'       => __( 'Limit results to events that overlap the supplied ISO 8601 end date.', 'post-calendar' ),
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
		);

		if ( isset( $params['per_page'] ) ) {
			$params['per_page']['default'] = self::DEFAULT_PER_PAGE;
			$params['per_page']['maximum'] = self::DEFAULT_PER_PAGE;
		}

		return $params;
	}

	public function filter_collection_query( array $args, WP_REST_Request $request ): array {
		$source_types = $this->event_query_service->resolve_post_types( $request->get_param( 'post_types' ) );
		$query_vars   = $this->parse_query_vars_param( $request->get_param( 'query_vars' ) );
		$range_start  = $this->event_query_service->parse_request_date( $request->get_param( 'start' ) );
		$range_end    = $this->event_query_service->parse_request_date( $request->get_param( 'end' ) );
		$source_types = $this->merge_source_types_from_query_vars( $source_types, $query_vars );
		$args         = $this->merge_supported_query_vars( $args, $query_vars );

		$args['post_status']                    = 'publish';
		$args['posts_per_page']                 = $this->resolve_posts_per_page( $request );
		$args[ Post_Type::SOURCE_TYPES_QUERY_VAR ] = $source_types;
		$args['meta_query']                    = $this->merge_meta_query_constraints(
			$args['meta_query'] ?? array(),
			$this->build_rest_meta_constraints( $range_start, $range_end )
		);

		if ( ! $request->has_param( 'orderby' ) && empty( $query_vars['orderby'] ) ) {
			$args['orderby']   = 'meta_value';
			$args['meta_key']  = Event_Query_Service::EVENT_START_META;
			$args['meta_type'] = 'DATETIME';
			$args['order']     = 'ASC';
		}

		return $args;
	}

	public function sanitize_query_vars_param( $value ): string {
		return is_string( $value ) ? wp_unslash( $value ) : '';
	}

	public function prepare_item_for_response( WP_REST_Response $response, WP_Post $post, WP_REST_Request $request ): WP_REST_Response {
		$event = $this->event_query_service->build_event( (int) $post->ID );

		if ( null === $event ) {
			$response->set_data( array() );
			return $response;
		}

		$response->set_data( $event );

		return $response;
	}

	private function build_rest_meta_constraints( ?DateTimeImmutable $range_start, ?DateTimeImmutable $range_end ): array {
		return array_merge(
			array(
				array(
					'key'     => Event_Query_Service::EVENT_START_META,
					'compare' => 'EXISTS',
				),
				array(
					'key'     => Event_Query_Service::EVENT_START_META,
					'value'   => '',
					'compare' => '!=',
				),
			),
			$this->event_query_service->build_range_meta_query( $range_start, $range_end )
		);
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

		$supported_keys = array(
			'post_type',
			'post__in',
			'post__not_in',
			'author__in',
			'author__not_in',
			'tax_query',
			'meta_query',
			'date_query',
			'orderby',
			'order',
			'meta_key',
			'meta_type',
			's',
		);
		$sanitized      = array();

		foreach ( $supported_keys as $key ) {
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

		$list_keys   = array( 'post__in', 'post__not_in', 'author__in', 'author__not_in' );
		$direct_keys = array( 'orderby', 'order', 'meta_key', 'meta_type', 's' );

		foreach ( $list_keys as $key ) {
			if ( empty( $query_vars[ $key ] ) || ! is_array( $query_vars[ $key ] ) ) {
				continue;
			}

			$args[ $key ] = array_values( array_filter( array_map( 'absint', $query_vars[ $key ] ) ) );
		}

		foreach ( $direct_keys as $key ) {
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