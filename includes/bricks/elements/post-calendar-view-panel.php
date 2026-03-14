<?php

namespace PostCalendar\Bricks\Elements;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Element_Post_Calendar_View_Panel extends \Bricks\Element {
	public $category = 'general';
	public $name     = 'post-calendar-view-panel';
	public $icon     = 'ti-layout-width-default';
	public $css_selector = '.post-calendar-view-panel';
	public $nestable = true;

	public function get_label() {
		return esc_html__( 'Calendar View Panel', 'post-calendar' );
	}

	public function get_keywords() {
		return array( 'post', 'calendar', 'view', 'panel', 'content' );
	}

	public function set_controls() {
		$this->controls['view'] = array(
			'tab'     => 'content',
			'label'   => esc_html__( 'View', 'post-calendar' ),
			'type'    => 'select',
			'options' => Element_Post_Calendar::get_view_options(),
			'default' => 'month',
		);
	}

	public function render() {
		$view = sanitize_key( (string) ( $this->settings['view'] ?? 'month' ) );

		$this->set_attribute( '_root', 'class', 'post-calendar-view-panel' );
		$this->set_attribute( '_root', 'data-post-calendar-view-panel', $view );
		$this->set_attribute( '_root', 'hidden', 'hidden' );

		echo '<div ' . $this->render_attributes( '_root' ) . '>';
		echo \Bricks\Frontend::render_children( $this );
		echo '</div>';
	}

	public static function render_builder() { ?>
		<script type="text/x-template" id="tmpl-bricks-element-post-calendar-view-panel">
			<component :is="tag" class="post-calendar-view-panel">
				<div class="post-calendar-element-placeholder">{{ settings.view ? settings.view : 'month' }} view metadata</div>
				<bricks-element-children :element="element"/>
			</component>
		</script>
	<?php }
}