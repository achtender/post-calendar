<?php

namespace PostCalendar\Bricks;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Bricks_Integration {
	private $dependency_missing = false;

	public function __construct() {
		add_action( 'init', array( $this, 'register_element' ), 11 );
		add_action( 'admin_notices', array( $this, 'render_dependency_notice' ) );
	}

	public function register_element(): void {
		if ( ! $this->is_bricks_available() ) {
			$this->dependency_missing = true;
			return;
		}

		\Bricks\Elements::register_element(
			POST_CALENDAR_PLUGIN_DIR . 'includes/bricks/elements/class-element-post-calendar.php',
			'post-calendar',
			'\\PostCalendar\\Bricks\\Elements\\Element_Post_Calendar'
		);
	}

	public function render_dependency_notice(): void {
		if ( ! $this->dependency_missing || ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		printf(
			'<div class="notice notice-warning"><p>%s</p></div>',
			esc_html__( 'Post Calendar could not register its Bricks element because Bricks is not active or its API is unavailable.', 'post-calendar' )
		);
	}

	private function is_bricks_available(): bool {
		return class_exists( '\\Bricks\\Element' ) && class_exists( '\\Bricks\\Elements' ) && method_exists( '\\Bricks\\Elements', 'register_element' );
	}
}