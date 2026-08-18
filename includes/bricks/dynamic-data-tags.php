<?php

namespace PostCalendar\Bricks;

use PostCalendar\Event_Sources\Event_Config;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Dynamic_Data_Tags {

	public function __construct() {
		add_filter( 'bricks/dynamic_tags_list', array( $this, 'register_tags' ) );
		add_filter( 'bricks/dynamic_data/render_tag', array( $this, 'render_tag' ), 20, 3 );
		add_filter( 'bricks/dynamic_data/render_content', array( $this, 'render_content' ), 20, 3 );
		add_filter( 'bricks/frontend/render_data', array( $this, 'render_content' ), 20, 2 );
	}

	/**
	 * Register Post Calendar dynamic data tags in Bricks builder
	 *
	 * Syntax examples:
	 * - {post_event_start}
	 * - {post_event_start:Y-m-d}
	 * - {post_event_start:F j, Y \a\t g:i A}
	 * - {post_event_end}
	 * - {post_event_end:Y-m-d}
	 * - {post_event_label}
	 * - {post_has_events}
	 * - {post_events_range_start}
	 * - {post_events_range_start:Y-m-d}
	 * - {post_events_range_end}
	 * - {post_events_range_end:Y-m-d}
	 *
	 * @param array $tags Existing dynamic tags list.
	 * @return array Updated tags list.
	 */
	public function register_tags( $tags ) {
		if ( ! is_array( $tags ) ) {
			return $tags;
		}

		$tags[] = [
			'name' => '{post_event_start}',
			'label' => 'Post Calendar - Event Start Date',
			'group' => 'Post Calendar',
		];

		$tags[] = [
			'name' => '{post_event_end}',
			'label' => 'Post Calendar - Event End Date',
			'group' => 'Post Calendar',
		];

		$tags[] = [
			'name' => '{post_event_label}',
			'label' => 'Post Calendar - Event Label',
			'group' => 'Post Calendar',
		];

		$tags[] = [
			'name' => '{post_has_events}',
			'label' => 'Post Calendar - Has Events',
			'group' => 'Post Calendar',
		];

		$tags[] = [
			'name' => '{post_events_range_start}',
			'label' => 'Post Calendar - Events Range Start',
			'group' => 'Post Calendar',
		];

		$tags[] = [
			'name' => '{post_events_range_end}',
			'label' => 'Post Calendar - Events Range End',
			'group' => 'Post Calendar',
		];

		return $tags;
	}

	/**
	 * Render individual dynamic tags
	 *
	 * @param mixed  $tag The tag to render (may include arguments like "post_event_start:Y-m-d").
	 * @param object $post The post object.
	 * @param string $context The context ('text', 'html', etc.).
	 * @return mixed The rendered value or original tag if not recognized.
	 */
	public function render_tag( $tag, $post, $context = 'text' ) {
		if ( ! is_string( $tag ) ) {
			return $tag;
		}

		// Clean tag name (remove outer braces if present).
		$clean_tag = str_replace( [ '{', '}' ], '', $tag );

		// Get post ID.
		$post_id = is_object( $post ) ? $post->ID : $post;

		if ( ! $post_id ) {
			return $tag;
		}

		// Handle tags with format arguments (e.g., "post_event_start:Y-m-d").
		if ( strpos( $clean_tag, 'post_event_start:' ) === 0 ) {
			$format = str_replace( 'post_event_start:', '', $clean_tag );
			return $this->get_formatted_meta_date( $post_id, Event_Config::EVENT_START_META, $format );
		}

		if ( strpos( $clean_tag, 'post_event_end:' ) === 0 ) {
			$format = str_replace( 'post_event_end:', '', $clean_tag );
			return $this->get_formatted_meta_date( $post_id, Event_Config::EVENT_END_META, $format );
		}

		if ( strpos( $clean_tag, 'post_events_range_start:' ) === 0 ) {
			$format = str_replace( 'post_events_range_start:', '', $clean_tag );
			return $this->get_formatted_meta_date( $post_id, Event_Config::EVENT_RANGE_START_META, $format );
		}

		if ( strpos( $clean_tag, 'post_events_range_end:' ) === 0 ) {
			$format = str_replace( 'post_events_range_end:', '', $clean_tag );
			return $this->get_formatted_meta_date( $post_id, Event_Config::EVENT_RANGE_END_META, $format );
		}

		// Handle simple tags without arguments.
		if ( $clean_tag === 'post_event_start' ) {
			return $this->get_meta_value( $post_id, Event_Config::EVENT_START_META );
		}

		if ( $clean_tag === 'post_event_end' ) {
			return $this->get_meta_value( $post_id, Event_Config::EVENT_END_META );
		}

		if ( $clean_tag === 'post_event_label' ) {
			return $this->get_meta_value( $post_id, Event_Config::EVENT_LABEL_META );
		}

		if ( $clean_tag === 'post_has_events' ) {
			return $this->get_meta_value( $post_id, Event_Config::EVENT_HAS_EVENTS_META );
		}

		if ( $clean_tag === 'post_events_range_start' ) {
			return $this->get_meta_value( $post_id, Event_Config::EVENT_RANGE_START_META );
		}

		if ( $clean_tag === 'post_events_range_end' ) {
			return $this->get_meta_value( $post_id, Event_Config::EVENT_RANGE_END_META );
		}

		return $tag;
	}

	/**
	 * Render tags within content strings (frontend rendering)
	 *
	 * @param string $content The content potentially containing dynamic tags.
	 * @param object $post The post object.
	 * @param string $context The context ('text', 'html', etc.).
	 * @return string The content with tags replaced.
	 */
	public function render_content( $content, $post, $context = 'text' ) {
		if ( ! is_string( $content ) ) {
			return $content;
		}

		$post_id = is_object( $post ) ? $post->ID : (int) $post;

		if ( ! $post_id ) {
			return $content;
		}

		// Check if content contains any Post Calendar tags.
		if ( strpos( $content, '{post_' ) === false ) {
			return $content;
		}

		// Handle formatted date tags: {post_event_start:format}, {post_event_end:format}, etc.
		$content = $this->replace_formatted_date_tags( $content, $post_id, 'post_event_start', Event_Config::EVENT_START_META );
		$content = $this->replace_formatted_date_tags( $content, $post_id, 'post_event_end', Event_Config::EVENT_END_META );
		$content = $this->replace_formatted_date_tags( $content, $post_id, 'post_events_range_start', Event_Config::EVENT_RANGE_START_META );
		$content = $this->replace_formatted_date_tags( $content, $post_id, 'post_events_range_end', Event_Config::EVENT_RANGE_END_META );

		// Handle simple tags.
		$simple_tags = [
			'post_event_start' => Event_Config::EVENT_START_META,
			'post_event_end' => Event_Config::EVENT_END_META,
			'post_event_label' => Event_Config::EVENT_LABEL_META,
			'post_has_events' => Event_Config::EVENT_HAS_EVENTS_META,
			'post_events_range_start' => Event_Config::EVENT_RANGE_START_META,
			'post_events_range_end' => Event_Config::EVENT_RANGE_END_META,
		];

		foreach ( $simple_tags as $tag_name => $meta_key ) {
			$tag = '{' . $tag_name . '}';
			if ( strpos( $content, $tag ) !== false ) {
				$value = $this->get_meta_value( $post_id, $meta_key );
				$content = str_replace( $tag, $value, $content );
			}
		}

		return $content;
	}

	/**
	 * Replace formatted date tags in content
	 *
	 * @param string $content The content to search.
	 * @param int    $post_id The post ID.
	 * @param string $tag_name The tag name (without braces).
	 * @param string $meta_key The meta key to fetch.
	 * @return string The content with tags replaced.
	 */
	private function replace_formatted_date_tags( $content, $post_id, $tag_name, $meta_key ) {
		$pattern = '/\{' . preg_quote( $tag_name, '/' ) . ':([^}]+)\}/';

		if ( ! preg_match_all( $pattern, $content, $matches ) ) {
			return $content;
		}

		foreach ( $matches[1] as $key => $format ) {
			$tag = $matches[0][ $key ];
			$value = $this->get_formatted_meta_date( $post_id, $meta_key, $format );
			$content = str_replace( $tag, $value, $content );
		}

		return $content;
	}

	/**
	 * Get a simple meta value
	 *
	 * @param int    $post_id The post ID.
	 * @param string $meta_key The meta key.
	 * @return string The meta value or empty string if not found.
	 */
	private function get_meta_value( $post_id, $meta_key ) {
		$value = get_post_meta( $post_id, $meta_key, true );
		return is_string( $value ) ? $value : (string) ( $value ?? '' );
	}

	/**
	 * Get a formatted date from meta
	 *
	 * @param int    $post_id The post ID.
	 * @param string $meta_key The meta key containing the date.
	 * @param string $format PHP date format string.
	 * @return string The formatted date or empty string if not found/invalid.
	 */
	private function get_formatted_meta_date( $post_id, $meta_key, $format = 'Y-m-d H:i:s' ) {
		$value = get_post_meta( $post_id, $meta_key, true );

		if ( empty( $value ) ) {
			return '';
		}

		try {
			$date = new \DateTime( $value );
			return $date->format( $format );
		} catch (\Exception $e) {
			// Return the raw value if parsing fails.
			return $value;
		}
	}
}
