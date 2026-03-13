<?php

namespace PostCalendar\Bricks\Elements;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Element_Post_Calendar extends \Bricks\Element {
	public $category = 'general';
	public $name     = 'post-calendar';
	public $icon     = 'ti-calendar';
	public $css_selector = '.post-calendar-element';

	public function get_label() {
		return esc_html__( 'Post Calendar', 'post-calendar' );
	}

	public function get_keywords() {
		return array( 'calendar', 'post', 'events', 'react' );
	}

	public function set_control_groups() {
		$this->control_groups['content'] = array(
			'title' => esc_html__( 'Content', 'post-calendar' ),
			'tab'   => 'content',
		);

		$this->control_groups['behavior'] = array(
			'title' => esc_html__( 'Behavior', 'post-calendar' ),
			'tab'   => 'content',
		);

		$this->control_groups['layout'] = array(
			'title' => esc_html__( 'Layout', 'post-calendar' ),
			'tab'   => 'style',
		);

		$this->control_groups['colors'] = array(
			'title' => esc_html__( 'Colors', 'post-calendar' ),
			'tab'   => 'style',
		);
	}

	public function set_controls() {
		$post_type_options = $this->get_post_type_control_options();

		$this->controls['postTypes'] = array(
			'tab'         => 'content',
			'group'       => 'content',
			'label'       => esc_html__( 'Post types', 'post-calendar' ),
			'type'        => 'select',
			'options'     => $post_type_options,
			'multiple'    => true,
			'clearable'   => true,
			'searchable'  => true,
			'placeholder' => esc_html__( 'Use allowed post types from Settings > Post Calendar', 'post-calendar' ),
			'description' => esc_html__( 'Choose specific post types for this calendar instance. Leave empty to use the allowed post types from Settings > Post Calendar.', 'post-calendar' ),
		);

		$this->controls['defaultView'] = array(
			'tab'     => 'content',
			'group'   => 'behavior',
			'label'   => esc_html__( 'Default view', 'post-calendar' ),
			'type'    => 'select',
			'options' => array(
				'month'  => esc_html__( 'Month', 'post-calendar' ),
				'week'   => esc_html__( 'Week', 'post-calendar' ),
				'day'    => esc_html__( 'Day', 'post-calendar' ),
				'agenda' => esc_html__( 'Agenda', 'post-calendar' ),
			),
			'default' => 'month',
		);

		$this->controls['enabledViews'] = array(
			'tab'         => 'content',
			'group'       => 'behavior',
			'label'       => esc_html__( 'Enabled views', 'post-calendar' ),
			'type'        => 'select',
			'options'     => array(
				'month'  => esc_html__( 'Month', 'post-calendar' ),
				'week'   => esc_html__( 'Week', 'post-calendar' ),
				'day'    => esc_html__( 'Day', 'post-calendar' ),
				'agenda' => esc_html__( 'Agenda', 'post-calendar' ),
			),
			'multiple'    => true,
			'clearable'   => true,
			'placeholder' => esc_html__( 'All views enabled', 'post-calendar' ),
			'description' => esc_html__( 'Leave empty to show all views. Deselect a view to hide its toolbar button.', 'post-calendar' ),
		);

		$this->controls['showToolbar'] = array(
			'tab'     => 'content',
			'group'   => 'behavior',
			'label'   => esc_html__( 'Show toolbar', 'post-calendar' ),
			'type'    => 'checkbox',
			'default' => true,
		);

		$this->controls['agendaRangeMode'] = array(
			'tab'     => 'content',
			'group'   => 'behavior',
			'label'   => esc_html__( 'Agenda range mode', 'post-calendar' ),
			'type'    => 'select',
			'options' => array(
				'visible-range'  => esc_html__( 'Use visible agenda range', 'post-calendar' ),
				'upcoming-window' => esc_html__( 'Upcoming events window', 'post-calendar' ),
			),
			'default' => 'visible-range',
		);

		$this->controls['agendaRangeMonths'] = array(
			'tab'         => 'content',
			'group'       => 'behavior',
			'label'       => esc_html__( 'Agenda range in months', 'post-calendar' ),
			'type'        => 'number',
			'min'         => 1,
			'unit'        => false,
			'inline'      => true,
			'default'     => '3',
			'description' => esc_html__( 'Used when agenda range mode is set to upcoming events window.', 'post-calendar' ),
			'required'    => array( 'agendaRangeMode', '=', 'upcoming-window' ),
		);

		$this->controls['calendarWidth'] = array(
			'tab'         => 'style',
			'group'       => 'layout',
			'label'       => esc_html__( 'Calendar width', 'post-calendar' ),
			'type'        => 'number',
			'unit'        => 'px',
			'units'       => array( 'px', 'rem', '%' ),
			'min'         => 240,
			'inline'      => true,
			'description' => esc_html__( 'Controls the minimum calendar width used by the month and week layouts.', 'post-calendar' ),
			'css'         => array(
				array(
					'property' => '--post-calendar-w',
					'selector' => '.post-calendar-element',
				),
			),
		);

		$this->controls['calendarHeight'] = array(
			'tab'         => 'style',
			'group'       => 'layout',
			'label'       => esc_html__( 'Calendar height', 'post-calendar' ),
			'type'        => 'number',
			'unit'        => 'px',
			'units'       => array( 'px', 'rem', 'vh' ),
			'min'         => 320,
			'inline'      => true,
			'description' => esc_html__( 'Sets the fixed height used by the month and week layouts.', 'post-calendar' ),
			'css'         => array(
				array(
					'property' => '--post-calendar-h',
					'selector' => '.post-calendar-element',
				),
			),
		);

		$this->controls['calendarRadius'] = array(
			'tab'    => 'style',
			'group'  => 'layout',
			'label'  => esc_html__( 'Surface radius', 'post-calendar' ),
			'type'   => 'number',
			'unit'   => 'px',
			'units'  => array( 'px', 'rem' ),
			'min'    => 0,
			'inline' => true,
			'css'    => array(
				array(
					'property' => '--post-calendar-radius-md',
					'selector' => '.post-calendar-element',
				),
			),
		);

		$this->controls['pillRadius'] = array(
			'tab'    => 'style',
			'group'  => 'layout',
			'label'  => esc_html__( 'Button and event radius', 'post-calendar' ),
			'type'   => 'number',
			'unit'   => 'px',
			'units'  => array( 'px', 'rem' ),
			'min'    => 0,
			'inline' => true,
			'css'    => array(
				array(
					'property' => '--post-calendar-radius-sm',
					'selector' => '.post-calendar-element',
				),
			),
		);

		$this->controls['surfaceColor'] = array(
			'tab'   => 'style',
			'group' => 'colors',
			'label' => esc_html__( 'Surface color', 'post-calendar' ),
			'type'  => 'color',
			'css'   => array(
				array(
					'property' => '--post-calendar-surface',
					'selector' => '.post-calendar-element',
				),
			),
		);

		$this->controls['surfaceMutedColor'] = array(
			'tab'   => 'style',
			'group' => 'colors',
			'label' => esc_html__( 'Muted surface color', 'post-calendar' ),
			'type'  => 'color',
			'css'   => array(
				array(
					'property' => '--post-calendar-surface-muted',
					'selector' => '.post-calendar-element',
				),
			),
		);

		$this->controls['borderColor'] = array(
			'tab'   => 'style',
			'group' => 'colors',
			'label' => esc_html__( 'Border color', 'post-calendar' ),
			'type'  => 'color',
			'css'   => array(
				array(
					'property' => '--post-calendar-border',
					'selector' => '.post-calendar-element',
				),
			),
		);

		$this->controls['textColor'] = array(
			'tab'   => 'style',
			'group' => 'colors',
			'label' => esc_html__( 'Body text color', 'post-calendar' ),
			'type'  => 'color',
			'css'   => array(
				array(
					'property' => '--post-calendar-text-default',
					'selector' => '.post-calendar-element',
				),
			),
		);

		$this->controls['mutedTextColor'] = array(
			'tab'   => 'style',
			'group' => 'colors',
			'label' => esc_html__( 'Muted text color', 'post-calendar' ),
			'type'  => 'color',
			'css'   => array(
				array(
					'property' => '--post-calendar-text-muted',
					'selector' => '.post-calendar-element',
				),
				array(
					'property' => '--post-calendar-text-subtle',
					'selector' => '.post-calendar-element',
				),
			),
		);

		$this->controls['pillColor'] = array(
			'tab'   => 'style',
			'group' => 'colors',
			'label' => esc_html__( 'Event pill color', 'post-calendar' ),
			'type'  => 'color',
			'css'   => array(
				array(
					'property' => '--post-calendar-surface-pill',
					'selector' => '.post-calendar-element',
				),
			),
		);

		$this->controls['pillTextColor'] = array(
			'tab'   => 'style',
			'group' => 'colors',
			'label' => esc_html__( 'Event pill text color', 'post-calendar' ),
			'type'  => 'color',
			'css'   => array(
				array(
					'property' => '--post-calendar-text-strong-pill',
					'selector' => '.post-calendar-element',
				),
			),
		);

		$this->controls['accentColor'] = array(
			'tab'   => 'style',
			'group' => 'colors',
			'label' => esc_html__( 'Active button color', 'post-calendar' ),
			'type'  => 'color',
			'css'   => array(
				array(
					'property' => '--post-calendar-surface-active',
					'selector' => '.post-calendar-element',
				),
			),
		);

		$this->controls['accentForegroundColor'] = array(
			'tab'   => 'style',
			'group' => 'colors',
			'label' => esc_html__( 'Active button text color', 'post-calendar' ),
			'type'  => 'color',
			'css'   => array(
				array(
					'property' => '--post-calendar-surface-active-foreground',
					'selector' => '.post-calendar-element',
				),
			),
		);
	}

	public function render() {
		$plugin = \PostCalendar\Plugin::instance();

		if ( ! $plugin ) {
			return;
		}

		$settings = $this->settings;
		$assets   = $plugin->assets();

		if ( ! $assets->has_built_assets() ) {
			echo '<div class="post-calendar-element-placeholder">' . esc_html__( 'The calendar frontend assets are missing. Run the plugin build before using this element.', 'post-calendar' ) . '</div>';
			return;
		}

		$assets->enqueue_calendar_assets();

		$config = array(
			'postTypes'         => $this->parse_post_types( $settings['postTypes'] ?? '' ),
			'defaultView'       => $this->parse_default_view( $settings['defaultView'] ?? 'month' ),
			'enabledViews'      => $this->parse_enabled_views( $settings['enabledViews'] ?? array() ),
			'showToolbar'       => ! array_key_exists( 'showToolbar', $settings ) || ! empty( $settings['showToolbar'] ),
			'agendaRangeMode'   => $this->parse_agenda_range_mode( $settings['agendaRangeMode'] ?? 'visible-range' ),
			'agendaRangeMonths' => $this->parse_positive_integer( $settings['agendaRangeMonths'] ?? '3', 3 ),
		);

		echo '<div class="post-calendar-element">';
		echo '<div class="js-post-calendar-root" data-config="' . esc_attr( wp_json_encode( $config ) ) . '">';
		echo '<div class="post-calendar-element-placeholder">' . esc_html__( 'Loading calendar…', 'post-calendar' ) . '</div>';
		echo '</div>';
		echo '</div>';
	}

	private function parse_default_view( string $view ): string {
		$allowed_views = array( 'month', 'week', 'day', 'agenda' );

		if ( in_array( $view, $allowed_views, true ) ) {
			return $view;
		}

		return 'month';
	}

	private function parse_agenda_range_mode( string $mode ): string {
		$allowed_modes = array( 'visible-range', 'upcoming-window' );

		if ( in_array( $mode, $allowed_modes, true ) ) {
			return $mode;
		}

		return 'visible-range';
	}

	private function get_post_type_control_options(): array {
		$post_types = \PostCalendar\Admin\Settings_Page::get_selectable_post_types();
		$options    = array();

		foreach ( $post_types as $post_type ) {
			if ( ! is_object( $post_type ) || empty( $post_type->name ) ) {
				continue;
			}

			$label = $post_type->labels->singular_name ?: $post_type->label;

			$options[ $post_type->name ] = sprintf(
				/* translators: 1: post type label, 2: post type slug. */
				esc_html__( '%1$s (%2$s)', 'post-calendar' ),
				$label,
				$post_type->name
			);
		}

		return $options;
	}

	private function parse_positive_integer( $value, int $fallback ): int {
		$number = absint( $value );

		return $number > 0 ? $number : $fallback;
	}

	private function parse_enabled_views( $views ): array {
		$allowed = array( 'month', 'week', 'day', 'agenda' );
		$parsed  = \PostCalendar\Admin\Settings_Page::sanitize_slug_list( $views );
		$valid   = array_values( array_intersect( $allowed, $parsed ) );

		return ! empty( $valid ) ? $valid : $allowed;
	}

	private function parse_post_types( $post_types ): array {
		return \PostCalendar\Admin\Settings_Page::sanitize_slug_list( $post_types );
	}
}