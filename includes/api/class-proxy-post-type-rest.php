<?php

namespace PostCalendar\API;

use DateTimeImmutable;
use PostCalendar\Proxy_Post_Type;
use WP_Post;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Proxy_Post_Type_Rest {
	private const DEFAULT_PER_PAGE = 1000;

	/**
	 * @var Event_Query_Service
	 */
	private $event_query_service;

	public function __construct( ?Event_Query_Service $event_query_service = null ) {
		$this->event_query_service = $event_query_service ?: new Event_Query_Service();

		add_filter( 'rest_' . Proxy_Post_Type::SLUG . '_collection_params', array( $this, 'filter_collection_params' ) );
		add_filter( 'rest_' . Proxy_Post_Type::SLUG . '_query', array( $this, 'filter_collection_query' ), 10, 2 );
		add_filter( 'rest_prepare_' . Proxy_Post_Type::SLUG, array( $this, 'prepare_item_for_response' ), 10, 3 );
	}

	public function filter_collection_params( array $params ): array {
		$params['post_types'] = array(
			'description'       => __( 'Restrict the collection to a comma-separated list of source post types.', 'post-calendar' ),
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
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
		$range_start  = $this->event_query_service->parse_request_date( $request->get_param( 'start' ) );
		$range_end    = $this->event_query_service->parse_request_date( $request->get_param( 'end' ) );

		$args['post_status']      = 'publish';
		$args['posts_per_page'] = $this->resolve_posts_per_page( $request );
		$args[ Proxy_Post_Type::SOURCE_TYPES_QUERY_VAR ] = $source_types;
		$args['meta_query']      = $this->merge_meta_query_constraints(
			$args['meta_query'] ?? array(),
			$this->build_rest_meta_constraints( $range_start, $range_end )
		);

		if ( ! $request->has_param( 'orderby' ) ) {
			$args['orderby']   = 'meta_value';
			$args['meta_key']  = Event_Query_Service::EVENT_START_META;
			$args['meta_type'] = 'DATETIME';
			$args['order']     = 'ASC';
		}

		return $args;
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

	private function resolve_posts_per_page( WP_REST_Request $request ): int {
		$per_page = absint( $request->get_param( 'per_page' ) );

		if ( $per_page > 0 ) {
			return min( $per_page, self::DEFAULT_PER_PAGE );
		}

		return self::DEFAULT_PER_PAGE;
	}
}