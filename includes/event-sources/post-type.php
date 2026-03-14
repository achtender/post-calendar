<?php

namespace PostCalendar\Event_Sources;

use WP_Query;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers a virtual proxy post type that allows template systems and page builders
 * to query calendar events across all source post types via a single, uniform
 * `post_type` slug.
 */
class Post_Type {
	public const SLUG = 'post_calendar_event';
	public const SOURCE_TYPES_QUERY_VAR = 'post_calendar_source_types';

	private const EVENT_ENABLED_META = '_post_is_event';
	private const EVENT_START_META   = '_post_start_date';

	public function __construct() {
		add_action( 'init', array( $this, 'register_post_type' ), 5 );
		add_action( 'pre_get_posts', array( $this, 'intercept_query' ) );
		add_filter( 'post_calendar_excluded_post_types', array( $this, 'exclude_from_settings' ) );
	}

	public function register_post_type(): void {
		register_post_type(
			self::SLUG,
			array(
				'label'               => esc_html__( 'Post Calendar Events', 'post-calendar' ),
				'labels'              => array(
					'name'          => esc_html__( 'Post Calendar Events', 'post-calendar' ),
					'singular_name' => esc_html__( 'Post Calendar Event', 'post-calendar' ),
					'menu_name'     => esc_html__( 'Post Calendar Events', 'post-calendar' ),
				),
				'description'         => esc_html__( 'A virtual query type for Post Calendar events. Query this type to retrieve event posts from all source post types.', 'post-calendar' ),
				'public'              => true,
				'publicly_queryable'  => true,
				'show_ui'             => true,
				'show_in_menu'        => false,
				'show_in_admin_bar'   => false,
				'show_in_nav_menus'   => false,
				'exclude_from_search' => true,
				'has_archive'         => false,
				'rewrite'             => false,
				'show_in_rest'        => true,
				'rest_base'           => self::SLUG,
				'query_var'           => false,
				'map_meta_cap'        => true,
				'capabilities'        => array(
					'create_posts'           => 'do_not_allow',
					'edit_posts'             => 'do_not_allow',
					'edit_published_posts'   => 'do_not_allow',
					'delete_posts'           => 'do_not_allow',
					'delete_published_posts' => 'do_not_allow',
					'publish_posts'          => 'do_not_allow',
					'read_private_posts'     => 'read',
					'read'                   => 'read',
				),
				'supports'            => array( 'title', 'custom-fields' ),
			)
		);
	}

	public function intercept_query( WP_Query $query ): void {
		$post_type  = $query->get( 'post_type' );
		$post_types = is_array( $post_type ) ? $post_type : array( $post_type );

		if ( ! in_array( self::SLUG, $post_types, true ) ) {
			return;
		}

		$source_types = $this->resolve_source_types( $query );

		if ( empty( $source_types ) ) {
			$query->set( 'post_type', 'post' );
			$query->set( 'post__in', array( 0 ) );
			return;
		}

		$query->set( 'post_type', $source_types );

		$caller_meta = $query->get( 'meta_query' );
		$meta_query  = array(
			'relation' => 'AND',
			array(
				'key'   => self::EVENT_ENABLED_META,
				'value' => '1',
			),
		);

		if ( ! empty( $caller_meta ) ) {
			$meta_query[] = $caller_meta;
		}

		$query->set( 'meta_query', $meta_query );

		if ( ! $query->get( 'orderby' ) ) {
			$query->set( 'orderby', 'meta_value' );
			$query->set( 'meta_key', self::EVENT_START_META );
			$query->set( 'meta_type', 'DATETIME' );
			$query->set( 'order', 'ASC' );
		}
	}

	private function resolve_source_types( WP_Query $query ): array {
		$requested_source_types = $query->get( self::SOURCE_TYPES_QUERY_VAR );

		return Settings_Page::resolve_event_source_post_types( $requested_source_types );
	}

	public function exclude_from_settings( array $excluded ): array {
		$excluded[] = self::SLUG;
		return $excluded;
	}
}