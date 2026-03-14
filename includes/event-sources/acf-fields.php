<?php

namespace PostCalendar\Event_Sources;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ACF_Fields {
	public function __construct() {
		add_action( 'acf/init', array( $this, 'register_field_group' ) );
		add_action( 'admin_notices', array( $this, 'render_dependency_notice' ) );
	}

	public function register_field_group(): void {
		if ( ! function_exists( 'acf_add_local_field_group' ) ) {
			return;
		}

		$location = $this->get_location_rules();

		if ( empty( $location ) ) {
			return;
		}

		acf_add_local_field_group(
			array(
				'key'                   => 'group_post_calendar_event_fields',
				'title'                 => esc_html__( 'Post Calendar', 'post-calendar' ),
				'fields'                => array(
					array(
						'key'           => 'field_post_calendar_is_event',
						'label'         => esc_html__( 'Show on calendar', 'post-calendar' ),
						'name'          => '_post_is_event',
						'type'          => 'true_false',
						'ui'            => 1,
						'default_value' => 0,
					),
					array(
						'key'               => 'field_post_calendar_is_all_day',
						'label'             => esc_html__( 'All-day event', 'post-calendar' ),
						'name'              => '_post_is_allday',
						'type'              => 'true_false',
						'ui'                => 1,
						'default_value'     => 0,
						'conditional_logic' => array(
							array(
								array(
									'field'    => 'field_post_calendar_is_event',
									'operator' => '==',
									'value'    => '1',
								),
							),
						),
					),
					array(
						'key'               => 'field_post_calendar_start_date',
						'label'             => esc_html__( 'Start date', 'post-calendar' ),
						'name'              => '_post_start_date',
						'type'              => 'date_time_picker',
						'display_format'    => 'Y-m-d H:i:s',
						'return_format'     => 'Y-m-d H:i:s',
						'first_day'         => 1,
						'conditional_logic' => array(
							array(
								array(
									'field'    => 'field_post_calendar_is_event',
									'operator' => '==',
									'value'    => '1',
								),
							),
						),
					),
					array(
						'key'               => 'field_post_calendar_end_date',
						'label'             => esc_html__( 'End date', 'post-calendar' ),
						'name'              => '_post_end_date',
						'type'              => 'date_time_picker',
						'display_format'    => 'Y-m-d H:i:s',
						'return_format'     => 'Y-m-d H:i:s',
						'first_day'         => 1,
						'conditional_logic' => array(
							array(
								array(
									'field'    => 'field_post_calendar_is_event',
									'operator' => '==',
									'value'    => '1',
								),
							),
						),
					),
				),
				'location'              => $location,
				'position'              => 'side',
				'style'                 => 'default',
				'label_placement'       => 'top',
				'instruction_placement' => 'label',
				'active'                => true,
			)
		);
	}

	public function render_dependency_notice(): void {
		if ( $this->is_acf_available() || ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		printf(
			'<div class="notice notice-warning"><p>%s</p></div>',
			esc_html__( 'Post Calendar requires an ACF-compatible field plugin such as SCF or ACF for its built-in event fields. You can still use the calendar with event meta written by other plugins or custom code.', 'post-calendar' )
		);
	}

	private function get_location_rules(): array {
		$location_rules = array();
		$post_types     = Settings_Page::get_allowed_post_types();

		foreach ( $post_types as $post_type ) {
			$location_rules[] = array(
				array(
					'param'    => 'post_type',
					'operator' => '==',
					'value'    => $post_type,
				),
			);
		}

		return $location_rules;
	}

	private function is_acf_available(): bool {
		return function_exists( 'acf_add_local_field_group' );
	}
}