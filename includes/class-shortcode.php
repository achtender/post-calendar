<?php

namespace PostCalendar;

use PostCalendar\Admin\Settings_Page;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Shortcode {
	private const TAG = 'post_calendar';
	private const DEFAULT_VIEW = 'month';
	private const DEFAULT_AGENDA_RANGE_MODE = 'visible-range';
	private const DEFAULT_AGENDA_RANGE_MONTHS = 3;

	public function __construct() {
		add_shortcode( self::TAG, array( $this, 'render' ) );
	}

	/**
	 * @param array<string, mixed>|string $atts
	 */
	public function render( $atts ): string {
		$plugin = Plugin::instance();

		if ( ! $plugin ) {
			return '';
		}

		$assets = $plugin->assets();

		if ( ! $assets->has_built_assets() ) {
			return '<div class="post-calendar-element-placeholder">' . esc_html__( 'The calendar frontend assets are missing. Run the plugin build before using this element.', 'post-calendar' ) . '</div>';
		}

		$attributes = shortcode_atts(
			array(
				'post_types'          => '',
				'default_view'        => self::DEFAULT_VIEW,
				'enabled_views'       => '',
				'show_toolbar'        => '1',
				'agenda_range_mode'   => self::DEFAULT_AGENDA_RANGE_MODE,
				'agenda_range_months' => (string) self::DEFAULT_AGENDA_RANGE_MONTHS,
				'empty_message'       => esc_html__( 'No events to display.', 'post-calendar' ),
			),
			is_array( $atts ) ? $atts : array(),
			self::TAG
		);

		$assets->enqueue_calendar_assets();

		$config = array(
			'postTypes'         => $this->parse_post_types( $attributes['post_types'] ),
			'defaultView'       => $this->parse_default_view( $attributes['default_view'] ),
			'enabledViews'      => $this->parse_enabled_views( $attributes['enabled_views'] ),
			'showToolbar'       => $this->parse_boolean( $attributes['show_toolbar'], true ),
			'agendaRangeMode'   => $this->parse_agenda_range_mode( $attributes['agenda_range_mode'] ),
			'agendaRangeMonths' => $this->parse_positive_integer( $attributes['agenda_range_months'], self::DEFAULT_AGENDA_RANGE_MONTHS ),
			'emptyMessage'      => sanitize_text_field( (string) $attributes['empty_message'] ),
		);

		return sprintf(
			'<div class="post-calendar-shortcode"><div class="js-post-calendar-root" data-config="%1$s"><div class="post-calendar-element-placeholder">%2$s</div></div></div>',
			esc_attr( wp_json_encode( $config ) ),
			esc_html__( 'Loading calendar…', 'post-calendar' )
		);
	}

	/**
	 * @param mixed $value
	 */
	private function parse_positive_integer( $value, int $fallback ): int {
		$number = absint( $value );

		return $number > 0 ? $number : $fallback;
	}

	/**
	 * @param mixed $value
	 */
	private function parse_default_view( $value ): string {
		$allowed_views = array( 'month', 'week', 'day', 'agenda' );
		$parsed_value  = sanitize_key( (string) $value );

		if ( in_array( $parsed_value, $allowed_views, true ) ) {
			return $parsed_value;
		}

		return self::DEFAULT_VIEW;
	}

	/**
	 * @param mixed $value
	 * @return array<int, string>
	 */
	private function parse_enabled_views( $value ): array {
		$allowed = array( 'month', 'week', 'day', 'agenda' );
		$parsed  = Settings_Page::sanitize_slug_list( $value );
		$valid   = array_values( array_intersect( $allowed, $parsed ) );

		return ! empty( $valid ) ? $valid : $allowed;
	}

	/**
	 * @param mixed $value
	 */
	private function parse_agenda_range_mode( $value ): string {
		$allowed_modes = array( 'visible-range', 'upcoming-window' );
		$parsed_mode   = sanitize_text_field( (string) $value );

		if ( in_array( $parsed_mode, $allowed_modes, true ) ) {
			return $parsed_mode;
		}

		return self::DEFAULT_AGENDA_RANGE_MODE;
	}

	/**
	 * @param mixed $value
	 */
	private function parse_post_types( $value ): array {
		$requested_post_types = Settings_Page::sanitize_slug_list( $value );

		if ( empty( $requested_post_types ) ) {
			return array();
		}

		$allowed_post_types = Settings_Page::get_allowed_post_types();

		return array_values( array_intersect( $allowed_post_types, $requested_post_types ) );
	}

	/**
	 * @param mixed $value
	 */
	private function parse_boolean( $value, bool $fallback ): bool {
		if ( is_bool( $value ) ) {
			return $value;
		}

		if ( is_numeric( $value ) ) {
			return (int) $value > 0;
		}

		if ( ! is_string( $value ) ) {
			return $fallback;
		}

		$normalized = strtolower( trim( $value ) );

		if ( in_array( $normalized, array( '1', 'true', 'yes', 'on' ), true ) ) {
			return true;
		}

		if ( in_array( $normalized, array( '0', 'false', 'no', 'off', '' ), true ) ) {
			return false;
		}

		return $fallback;
	}
}
