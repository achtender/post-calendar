<?php

namespace PostCalendar\Bricks;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Elements {
	private $dependency_missing = false;

	public function __construct() {
		add_action( 'init', array( $this, 'init_elements' ), 11 );
		add_action( 'admin_notices', array( $this, 'render_dependency_notice' ) );
	}

	public function init_elements(): void {
		if ( ! $this->is_bricks_available() ) {
			$this->dependency_missing = true;
			return;
		}

		$element_names = array(
			'post-calendar',
			'post-calendar-view-panel',
		);

		foreach ( $element_names as $element_name ) {
			$file = POST_CALENDAR_PLUGIN_DIR . "includes/bricks/elements/{$element_name}.php";
			$class_name = str_replace( '-', '_', $element_name );
			$class_name = ucwords( $class_name, '_' );
			$class_name = "\\PostCalendar\\Bricks\\Elements\\Element_{$class_name}";

			\Bricks\Elements::register_element( $file, $element_name, $class_name );
		}
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