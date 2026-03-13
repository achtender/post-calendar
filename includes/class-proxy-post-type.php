<?php

namespace PostCalendar;

use PostCalendar\Admin\Settings_Page;
use WP_Query;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers a virtual proxy post type that allows template systems and page builders
 * to query calendar events across all source post types via a single, uniform
 * `post_type` slug.
 *
 * No proxy posts are ever stored in the database. Instead, any WP_Query targeting the
 * proxy type is intercepted in `pre_get_posts` and transparently rerouted to the real
 * event source post types with the required event meta constraints applied. The posts
 * returned are the actual source posts, so all standard template functions (get_field,
 * the_permalink, the_excerpt, etc.) work without modification.
 *
 * Usage (template / builder loop):
 *
 *   new WP_Query( [
 *       'post_type'      => 'post_calendar_event',
 *       'posts_per_page' => 10,
 *   ] );
 *
 * Additional meta_query / tax_query / date_query clauses are merged alongside the
 * injected event-enabled guard and passed through to the underlying query unchanged.
 *
 * Default ordering is by event start date (ascending) when the caller does not specify
 * an explicit `orderby`.
 */
class Proxy_Post_Type {
	public const SLUG = 'post_calendar_event';
	public const SOURCE_TYPES_QUERY_VAR = 'post_calendar_source_types';

	private const EVENT_ENABLED_META = '_post_is_event';
	private const EVENT_START_META   = '_post_start_date';

	public function __construct() {
		add_action( 'init', array( $this, 'register_post_type' ), 5 );
		add_action( 'pre_get_posts', array( $this, 'intercept_query' ) );
		add_filter( 'post_calendar_excluded_post_types', array( $this, 'exclude_from_settings' ) );
	}

	/**
	 * Registers the proxy post type.
	 *
	 * Visibility is tuned so page-builder interfaces and REST consumers discover the
	 * type, while it remains hidden from the WordPress admin sidebar, admin bar,
	 * nav-menu builder, search results, and the front-end archive URL space.
	 */
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
				// Accessible via URL and queryable via WP_Query in PHP.
				'public'              => true,
				'publicly_queryable'  => true,
				// Visible to builder post-type selectors that enumerate show_ui types.
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
				// Block all write operations — this type only exists for querying.
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

	/**
	 * Intercepts WP_Query calls targeting the proxy type and reroutes them to the real
	 * event source post types, injecting the event-enabled meta constraint.
	 *
	 * Caller-supplied `meta_query`, `tax_query`, `date_query`, pagination, and other
	 * query vars are passed through unchanged; the injected constraint is AND-combined
	 * with any existing meta_query to preserve caller intent.
	 */
	public function intercept_query( WP_Query $query ): void {
		$post_type  = $query->get( 'post_type' );
		$post_types = is_array( $post_type ) ? $post_type : array( $post_type );

		if ( ! in_array( self::SLUG, $post_types, true ) ) {
			return;
		}

		$source_types = $this->resolve_source_types( $query );

		if ( empty( $source_types ) ) {
			// No source types configured — return an empty result set.
			$query->set( 'post_type', 'post' );
			$query->set( 'post__in', array( 0 ) );
			return;
		}

		$query->set( 'post_type', $source_types );

		// Build a new meta_query that AND-combines the event-enabled guard with
		// whatever meta constraints the caller may have already supplied.
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

		// Apply a sensible default ordering (event start ascending) when the caller
		// has not specified an explicit orderby.
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

	/**
	 * Keeps the proxy slug out of the plugin's selectable event source list so it
	 * cannot be chosen as a source for itself.
	 *
	 * @param  string[] $excluded
	 * @return string[]
	 */
	public function exclude_from_settings( array $excluded ): array {
		$excluded[] = self::SLUG;
		return $excluded;
	}
}
