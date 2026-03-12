<?php

namespace PostCalendar;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Assets {
	private const SCRIPT_HANDLE = 'post-calendar-app';
	private const STYLE_HANDLE  = 'post-calendar-app';

	public function has_built_assets(): bool {
		return file_exists( POST_CALENDAR_PLUGIN_DIR . 'dist/post-calendar.js' );
	}

	public function enqueue_calendar_assets(): void {
		if ( ! $this->has_built_assets() ) {
			return;
		}

		$version = $this->get_asset_version();
		$style   = $this->get_style_asset_path();

		if ( $style ) {
			wp_enqueue_style(
				self::STYLE_HANDLE,
				POST_CALENDAR_PLUGIN_URL . 'dist/' . $style,
				array(),
				$version
			);
		}

		wp_enqueue_script(
			self::SCRIPT_HANDLE,
			POST_CALENDAR_PLUGIN_URL . 'dist/post-calendar.js',
			array(),
			$version,
			true
		);

		wp_localize_script( self::SCRIPT_HANDLE, 'PostCalendarRuntime', $this->get_runtime_config() );
	}

	private function get_asset_version(): string {
		$script_path = POST_CALENDAR_PLUGIN_DIR . 'dist/post-calendar.js';

		if ( file_exists( $script_path ) ) {
			return (string) filemtime( $script_path );
		}

		return POST_CALENDAR_VERSION;
	}

	private function get_style_asset_path(): ?string {
		if ( file_exists( POST_CALENDAR_PLUGIN_DIR . 'dist/post-calendar.css' ) ) {
			return 'post-calendar.css';
		}

		if ( file_exists( POST_CALENDAR_PLUGIN_DIR . 'dist/style.css' ) ) {
			return 'style.css';
		}

		return null;
	}

	private function get_runtime_config(): array {
		return array(
			'restUrl'   => esc_url_raw( rest_url( 'post-calendar/v1/events' ) ),
			'restNonce' => wp_create_nonce( 'wp_rest' ),
			'locale'    => determine_locale(),
			'strings'   => $this->get_runtime_strings(),
		);
	}

	private function get_runtime_strings(): array {
		return array(
			'allDay'           => esc_html__( 'All-day', 'post-calendar' ),
			'agenda'           => esc_html__( 'Agenda', 'post-calendar' ),
			'back'             => esc_html__( 'Back', 'post-calendar' ),
			'calendarViews'    => esc_html__( 'Calendar views', 'post-calendar' ),
			'configParseError' => esc_html__( 'Unable to parse the calendar configuration.', 'post-calendar' ),
			'date'             => esc_html__( 'Date', 'post-calendar' ),
			'day'              => esc_html__( 'Day', 'post-calendar' ),
			'event'            => esc_html__( 'Event', 'post-calendar' ),
			'loadError'        => esc_html__( 'Unable to load calendar events right now.', 'post-calendar' ),
			'missingApiUrl'    => esc_html__( 'The calendar API URL is missing.', 'post-calendar' ),
			'month'            => esc_html__( 'Month', 'post-calendar' ),
			'next'             => esc_html__( 'Next', 'post-calendar' ),
			'noEvents'         => esc_html__( 'No events to display.', 'post-calendar' ),
			'showMore'         => esc_html__( 'more', 'post-calendar' ),
			'time'             => esc_html__( 'Time', 'post-calendar' ),
			'today'            => esc_html__( 'Today', 'post-calendar' ),
			'week'             => esc_html__( 'Week', 'post-calendar' ),
		);
	}
}
