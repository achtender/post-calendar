<?php

namespace PostCalendar\Bricks\Elements;

use PostCalendar\Event_Sources\Event_Config;

if (!defined('ABSPATH')) {
	exit;
}

class Element_Post_Calendar extends \Bricks\Element
{
	public $category = 'general';
	public $name = 'post-calendar';
	public $icon = 'ti-calendar';
	public $scripts = array('bricksTabs');
	public $css_selector = '.post-calendar-element';
	public $nestable = true;

	public static function get_view_options(): array
	{
		return array(
			'month' => esc_html__('Month', 'post-calendar'),
			'week' => esc_html__('Week', 'post-calendar'),
			'day' => esc_html__('Day', 'post-calendar'),
			'agenda' => esc_html__('Agenda', 'post-calendar'),
			'year' => esc_html__('Year', 'post-calendar'),
		);
	}

	public static function get_default_nested_view_keys(): array
	{
		return array('year', 'month', 'week', 'day', 'agenda');
	}

	public static function get_default_toolbar_action_label(string $action): string
	{
		switch ($action) {
			case 'prev':
				return esc_html__('Back', 'post-calendar');
			case 'next':
				return esc_html__('Next', 'post-calendar');
			case 'today':
			default:
				return esc_html__('Today', 'post-calendar');
		}
	}

	public static function get_default_view_label(string $view): string
	{
		$view_options = self::get_view_options();

		return $view_options[$view] ?? $view_options['month'];
	}

	public static function get_default_toolbar_label_placeholder(): string
	{
		return esc_html__('Calendar label', 'post-calendar');
	}

	private static function get_supported_query_var_keys(): array
	{
		return Event_Config::get_supported_query_var_keys();
	}

	private static function get_unsupported_query_control_keys(): array
	{
		return array(
			'objectType',
			'paged',
			'posts_per_page',
			'nopaging',
			'offset',
			'ignore_sticky_posts',
			'post_parent',
			'post_parent__in',
			'post_parent__not_in',
			'post_name__in',
			'author',
			'author_name',
			'exact',
			'sentence',
			'meta_value',
			'meta_value_num',
			'meta_compare',
			'cache_results',
			'update_post_term_cache',
			'update_post_meta_cache',
			'no_found_rows',
			'perm',
			'post_mime_type',
			'comment_count',
			'comment_status',
			'post_comment_status',
			'disable_query_merge',
			'useQueryEditor',
			'queryEditor',
			'signature',
			'user_id',
			'time',
			'no_results_template',
			'no_results_text',
			'is_live_search',
			'is_live_search_wrapper_selector',
			'disable_url_params',
			'infinite_scroll_separator',
			'infinite_scroll',
			'infinite_scroll_margin',
			'infinite_scroll_delay',
			'ajax_loader_animation',
			'ajax_loader_selector',
			'ajax_loader_color',
			'ajax_loader_scale',
			'arrayEditor',
			'pagination_enabled',
			'items_per_page',
		);
	}

	public static function get_custom_attribute_setting(string $id, string $name, string $value): array
	{
		return array(
			'id' => sanitize_key($id),
			'name' => $name,
			'value' => $value,
		);
	}

	private static function get_default_view_menu_item_settings(): array
	{
		return array(
			'_display' => 'flex',
			'_alignItems' => 'center',
			'_justifyContent' => 'center',
			'_padding' => array(
				'top' => 5,
				'right' => 10,
				'bottom' => 5,
				'left' => 10,
			),
			'_heightMin' => 30,
			'_border' => array(
				'radius' => array(
					'top' => 4,
					'right' => 4,
					'bottom' => 4,
					'left' => 4,
				),
			),
		);
	}

	private static function get_default_view_menu_settings(): array
	{
		return array(
			'_display' => 'flex',
			'_direction' => 'row',
			'_alignItems' => 'center',
			'_columnGap' => 10,
			'_attributes' => array(
				self::get_custom_attribute_setting('post-calendar-toolbar-region-views', 'data-post-calendar-toolbar-region', 'views'),
				self::get_custom_attribute_setting('post-calendar-view-menu-role', 'role', 'tablist'),
				self::get_custom_attribute_setting('post-calendar-view-menu-label', 'aria-label', esc_html__('Calendar views', 'post-calendar')),
			),
			'_hidden' => array(
				'_cssClasses' => 'tab-menu',
			),
		);
	}

	private static function get_default_toolbar_action_item(string $action): array
	{
		return self::get_default_title_item(
			self::get_default_toolbar_action_label($action),
			'post-calendar-toolbar-button',
			array(
				self::get_custom_attribute_setting('post-calendar-action-' . $action, 'data-post-calendar-action', $action),
				self::get_custom_attribute_setting('post-calendar-action-' . $action . '-role', 'role', 'button'),
				self::get_custom_attribute_setting('post-calendar-action-' . $action . '-tabindex', 'tabindex', '0'),
			),
		);
	}

	public static function get_default_title_item(string $label, string $css_class, array $custom_attributes = array(), array $extra_settings = array()): array
	{
		return array(
			'name' => 'div',
			'label' => esc_html__('Title', 'post-calendar'),
			'settings' => array_merge(
				array(
					'_hidden' => array(
						'_cssClasses' => $css_class,
					),
					'_attributes' => $custom_attributes,
				),
				$extra_settings,
			),
			'children' => array(
				array(
					'name' => 'text-basic',
					'settings' => array(
						'text' => $label,
					),
				),
			),
		);
	}

	public static function get_default_view_menu_child(): array
	{
		$view_menu_children = array();
		$available_view_keys = self::get_default_nested_view_keys();

		foreach ($available_view_keys as $view) {
			$view_menu_children[] = self::get_default_title_item(
				self::get_default_view_label($view),
				'tab-title',
				array(
					self::get_custom_attribute_setting('post-calendar-view-' . $view . '-role', 'role', 'tab'),
					self::get_custom_attribute_setting('post-calendar-view-' . $view . '-selected', 'aria-selected', 'false'),
					self::get_custom_attribute_setting('post-calendar-view-' . $view . '-tabindex', 'tabindex', '-1'),
				),
				self::get_default_view_menu_item_settings(),
			);
		}

		return array(
			'name' => 'block',
			'label' => esc_html__('View menu', 'post-calendar'),
			'settings' => self::get_default_view_menu_settings(),
			'children' => $view_menu_children,
		);
	}

	public static function get_default_view_panel_children(): array
	{
		$view_panel_children = array();
		$available_view_keys = self::get_default_nested_view_keys();

		foreach ($available_view_keys as $view) {
			$view_panel_children[] = array(
				'name' => 'block',
				'label' => esc_html__('Pane', 'post-calendar'),
				'settings' => array(
					'_display' => 'block',
					'_hidden' => array(
						'_cssClasses' => 'tab-pane',
					),
				),
				'children' => array(
					array(
						'name' => 'post-calendar-view-panel',
						'settings' => array(
							'view' => $view,
						),
					),
				),
			);
		}

		return $view_panel_children;
	}

	public function get_label()
	{
		return esc_html__('Post Calendar', 'post-calendar');
	}

	public function get_keywords()
	{
		return array('calendar', 'post', 'events', 'react');
	}

	public function set_control_groups()
	{
		$this->control_groups['layout'] = array(
			'title' => esc_html__('Layout', 'post-calendar'),
			'tab' => 'style',
		);

		$this->control_groups['colors'] = array(
			'title' => esc_html__('Colors', 'post-calendar'),
			'tab' => 'style',
		);
	}

	public function set_controls()
	{
		$this->controls['query'] = array(
			'tab' => 'content',
			'label' => esc_html__('Query', 'post-calendar'),
			'type' => 'query',
			'popup' => true,
			'inline' => true,
			'exclude' => self::get_unsupported_query_control_keys(),
		);

		$this->controls['queryInfo'] = array(
			'tab' => 'content',
			'type' => 'info',
			'content' => esc_html__('The query popup is limited to the subset the calendar applies: post type, include/exclude, taxonomy, author include/exclude, search, ordering, and date/meta constraints. Loop, pagination, query-editor, live-search, and non-post object settings are hidden.', 'post-calendar'),
		);

		$this->controls['openTab'] = array(
			'tab' => 'content',
			'label' => esc_html__('Open tab index', 'post-calendar'),
			'type' => 'text',
			'description' => esc_html__('Index of the view/content item to open on page load, start at 0.', 'post-calendar'),
			'inline' => true,
			'placeholder' => '0',
			'content' => '1',
		);

		$this->controls['calendarWidth'] = array(
			'tab' => 'style',
			'group' => 'layout',
			'label' => esc_html__('Calendar width', 'post-calendar'),
			'type' => 'number',
			'unit' => 'px',
			'units' => array('px', 'rem', '%'),
			'min' => 240,
			'inline' => true,
			'description' => esc_html__('Controls the minimum calendar width used by the month and week layouts.', 'post-calendar'),
			'css' => array(
				array(
					'property' => '--post-calendar-w',
					'selector' => '.post-calendar-element',
				),
			),
		);

		$this->controls['calendarHeight'] = array(
			'tab' => 'style',
			'group' => 'layout',
			'label' => esc_html__('Calendar height', 'post-calendar'),
			'type' => 'number',
			'unit' => 'px',
			'units' => array('px', 'rem', 'vh'),
			'min' => 320,
			'inline' => true,
			'description' => esc_html__('Sets the fixed height used by the month and week layouts.', 'post-calendar'),
			'css' => array(
				array(
					'property' => '--post-calendar-h',
					'selector' => '.post-calendar-element',
				),
			),
		);

		$this->controls['calendarRadius'] = array(
			'tab' => 'style',
			'group' => 'layout',
			'label' => esc_html__('Surface radius', 'post-calendar'),
			'type' => 'number',
			'unit' => 'px',
			'units' => array('px', 'rem'),
			'min' => 0,
			'inline' => true,
			'css' => array(
				array(
					'property' => '--post-calendar-radius-md',
					'selector' => '.post-calendar-element',
				),
			),
		);

		$this->controls['pillRadius'] = array(
			'tab' => 'style',
			'group' => 'layout',
			'label' => esc_html__('Button and event radius', 'post-calendar'),
			'type' => 'number',
			'unit' => 'px',
			'units' => array('px', 'rem'),
			'min' => 0,
			'inline' => true,
			'css' => array(
				array(
					'property' => '--post-calendar-radius-sm',
					'selector' => '.post-calendar-element',
				),
			),
		);

		$this->controls['surfaceColor'] = array(
			'tab' => 'style',
			'group' => 'colors',
			'label' => esc_html__('Surface color', 'post-calendar'),
			'type' => 'color',
			'css' => array(
				array(
					'property' => '--post-calendar-surface',
					'selector' => '.post-calendar-element',
				),
			),
		);

		$this->controls['surfaceMutedColor'] = array(
			'tab' => 'style',
			'group' => 'colors',
			'label' => esc_html__('Muted surface color', 'post-calendar'),
			'type' => 'color',
			'css' => array(
				array(
					'property' => '--post-calendar-surface-muted',
					'selector' => '.post-calendar-element',
				),
			),
		);

		$this->controls['borderColor'] = array(
			'tab' => 'style',
			'group' => 'colors',
			'label' => esc_html__('Border color', 'post-calendar'),
			'type' => 'color',
			'css' => array(
				array(
					'property' => '--post-calendar-border',
					'selector' => '.post-calendar-element',
				),
			),
		);

		$this->controls['textColor'] = array(
			'tab' => 'style',
			'group' => 'colors',
			'label' => esc_html__('Body text color', 'post-calendar'),
			'type' => 'color',
			'css' => array(
				array(
					'property' => '--post-calendar-text-default',
					'selector' => '.post-calendar-element',
				),
			),
		);

		$this->controls['mutedTextColor'] = array(
			'tab' => 'style',
			'group' => 'colors',
			'label' => esc_html__('Muted text color', 'post-calendar'),
			'type' => 'color',
			'css' => array(
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
			'tab' => 'style',
			'group' => 'colors',
			'label' => esc_html__('Event pill color', 'post-calendar'),
			'type' => 'color',
			'css' => array(
				array(
					'property' => '--post-calendar-surface-pill',
					'selector' => '.post-calendar-element',
				),
			),
		);

		$this->controls['pillTextColor'] = array(
			'tab' => 'style',
			'group' => 'colors',
			'label' => esc_html__('Event pill text color', 'post-calendar'),
			'type' => 'color',
			'css' => array(
				array(
					'property' => '--post-calendar-text-strong-pill',
					'selector' => '.post-calendar-element',
				),
			),
		);

		$this->controls['accentColor'] = array(
			'tab' => 'style',
			'group' => 'colors',
			'label' => esc_html__('Active button color', 'post-calendar'),
			'type' => 'color',
			'css' => array(
				array(
					'property' => '--post-calendar-surface-active',
					'selector' => '.post-calendar-element',
				),
			),
		);

		$this->controls['accentForegroundColor'] = array(
			'tab' => 'style',
			'group' => 'colors',
			'label' => esc_html__('Active button text color', 'post-calendar'),
			'type' => 'color',
			'css' => array(
				array(
					'property' => '--post-calendar-surface-active-foreground',
					'selector' => '.post-calendar-element',
				),
			),
		);
	}

	public function get_nestable_children(): array
	{
		return array(
			array(
				'name' => 'div',
				'label' => esc_html__('Calendar Toolbar', 'post-calendar'),
				'settings' => array(
					'_display' => 'grid',
					'_gridGap' => 20,
					'_gridTemplateColumns' => 'minmax(0, auto) 1fr minmax(0, auto)',
					'_alignItems' => 'center',
					'_padding' => array(
						'top' => 0,
						'right' => 0,
						'bottom' => 12,
						'left' => 0,
					),
					'_hidden' => array(
						'_cssClasses' => 'post-calendar-toolbar',
					),
				),
				'children' => array(
					array(
						'name' => 'block',
						'label' => esc_html__('Toolbar Actions', 'post-calendar'),
						'settings' => array(
							'_display' => 'flex',
							'_direction' => 'row',
							'_alignItems' => 'center',
							'_columnGap' => 10,
							'_attributes' => array(
								self::get_custom_attribute_setting('post-calendar-toolbar-region-actions', 'data-post-calendar-toolbar-region', 'actions'),
							),
							'_hidden' => array(
								'_cssClasses' => 'post-calendar-toolbar-actions',
							),
						),
						'children' => array(
							self::get_default_toolbar_action_item('today'),
							self::get_default_toolbar_action_item('prev'),
							self::get_default_toolbar_action_item('next'),
						),
					),
					array(
						'name' => 'block',
						'label' => esc_html__('Toolbar Label', 'post-calendar'),
						'settings' => array(
							'_display' => 'flex',
							'_alignItems' => 'center',
							'_justifyContent' => 'center',
							'_attributes' => array(
								self::get_custom_attribute_setting('post-calendar-toolbar-region-label', 'data-post-calendar-toolbar-region', 'label'),
							),
							'_hidden' => array(
								'_cssClasses' => 'post-calendar-toolbar-label-wrap',
							),
						),
						'children' => array(
							self::get_default_title_item(
								self::get_default_toolbar_label_placeholder(),
								'post-calendar-toolbar-label',
								array(
									self::get_custom_attribute_setting('post-calendar-toolbar-label', 'data-post-calendar-label', 'true'),
								),
							),
						),
					),
					self::get_default_view_menu_child(),
				),
			),
			array(
				'name' => 'block',
				'label' => esc_html__('Calendar Content', 'post-calendar'),
				'settings' => array(
					'_hidden' => array(
						'_cssClasses' => 'tab-content',
					),
				),
				'children' => self::get_default_view_panel_children(),
			),
		);
	}

	public function render()
	{
		$plugin = \PostCalendar\Plugin::instance();

		if (!$plugin) {
			return;
		}

		$settings = $this->settings;
		$assets = $plugin->assets();

		if (!$assets->has_built_assets()) {
			echo '<div class="post-calendar-element-placeholder">' . esc_html__('The calendar frontend assets are missing. Run the plugin build before using this element.', 'post-calendar') . '</div>';
			return;
		}

		$assets->enqueue_calendar_assets();

		$config = array(
			'openTab' => absint($settings['openTab'] ?? 0),
			'queryVars' => $this->parse_supported_query_vars($settings['query'] ?? array()),
		);
		$children_markup = $this->get_children_markup();

		$this->set_attribute('_root', 'class', 'post-calendar-element brxe-tabs-nested');
		$this->set_attribute('_root', 'data-config', wp_json_encode($config));
		$this->set_attribute('_root', 'data-open-tab', (string) absint($config['openTab'] ?? 0));

		$output = '<div ' . $this->render_attributes('_root') . '>';

		if ('' !== $children_markup) {
			$output .= $children_markup;
		}

		$output .= '</div>';

		echo $this->enhance_tabs_accessibility($output);
	}

	public static function render_builder()
	{ ?>
		<script type="text/x-template" id="tmpl-bricks-element-post-calendar">
			<component :is="tag" class="post-calendar-element brxe-tabs-nested">
				<bricks-element-children :element="element"/>
			</component>
		</script>
	<?php }

	private function enhance_tabs_accessibility(string $html_content): string
	{
		$dom = new \DOMDocument();

		libxml_use_internal_errors(true);
		$dom->loadHTML('<?xml encoding="UTF-8">' . $html_content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
		libxml_clear_errors();

		$xpath = new \DOMXPath($dom);
		$tab_titles = $xpath->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' tab-title ')]");
		$tab_panes = $xpath->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' tab-pane ')]");

		foreach ($tab_titles as $index => $title) {
			$this->set_dom_attribute_if_missing($title, 'role', 'tab');

			if ($index === 0) {
				$this->set_dom_attribute_if_missing($title, 'aria-selected', 'true');
				$this->set_dom_attribute_if_missing($title, 'tabindex', '0');
			} else {
				$this->set_dom_attribute_if_missing($title, 'aria-selected', 'false');
				$this->set_dom_attribute_if_missing($title, 'tabindex', '-1');
			}

			$title_id = $title->getAttribute('id');

			if (!$title_id) {
				$title_id = "brx-tab-title-{$this->id}-$index";
				$this->set_dom_attribute_if_missing($title, 'id', $title_id);
			}

			$pane = $tab_panes->item($index);

			if ($pane) {
				$pane_id = $pane->getAttribute('id');

				if (!$pane_id) {
					$pane_id = "brx-tab-pane-{$this->id}-$index";
					$this->set_dom_attribute_if_missing($pane, 'id', $pane_id);
				}

				$this->set_dom_attribute_if_missing($title, 'aria-controls', $pane_id);
			}
		}

		foreach ($tab_panes as $index => $pane) {
			$this->set_dom_attribute_if_missing($pane, 'role', 'tabpanel');

			$pane_id = $pane->getAttribute('id');

			if (!$pane_id) {
				$pane_id = "brx-tab-pane-{$this->id}-$index";
				$this->set_dom_attribute_if_missing($pane, 'id', $pane_id);
			}

			$title = $tab_titles->item($index);

			if ($title) {
				$title_id = $title->getAttribute('id');
				$this->set_dom_attribute_if_missing($pane, 'aria-labelledby', $title_id);
			}

			$this->set_dom_attribute_if_missing($pane, 'tabindex', '0');
		}

		$tab_menu = $xpath->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' tab-menu ')]")->item(0);

		if ($tab_menu) {
			$this->set_dom_attribute_if_missing($tab_menu, 'role', 'tablist');
		}

		$action_nodes = $xpath->query('//*[@data-post-calendar-action]');

		foreach ($action_nodes as $action_node) {
			if (!($action_node instanceof \DOMElement)) {
				continue;
			}

			$button_node = $this->convert_dom_element_to_button($dom, $action_node);
			$this->set_dom_attribute($button_node, 'type', 'button');
			$this->remove_dom_attribute($button_node, 'role');
			$this->remove_dom_attribute($button_node, 'tabindex');
		}

		return $dom->saveHTML();
	}

	private function convert_dom_element_to_button(\DOMDocument $dom, \DOMElement $element): \DOMElement
	{
		if ('button' === strtolower($element->tagName)) {
			return $element;
		}

		$button = $dom->createElement('button');

		foreach ($element->attributes as $attribute) {
			$button->setAttribute($attribute->nodeName, $attribute->nodeValue);
		}

		while ($element->firstChild) {
			$button->appendChild($element->firstChild);
		}

		$element->parentNode?->replaceChild($button, $element);

		return $button;
	}

	private function set_dom_attribute_if_missing(\DOMElement $element, string $attribute, string $value): void
	{
		if (!$element->hasAttribute($attribute)) {
			$element->setAttribute($attribute, $value);
		}
	}

	private function set_dom_attribute(\DOMElement $element, string $attribute, string $value): void
	{
		$element->setAttribute($attribute, $value);
	}

	private function remove_dom_attribute(\DOMElement $element, string $attribute): void
	{
		if ($element->hasAttribute($attribute)) {
			$element->removeAttribute($attribute);
		}
	}

	private function parse_supported_query_vars($query_settings): array
	{
		if (!is_array($query_settings) || empty($query_settings) || !class_exists('\\Bricks\\Query')) {
			return array();
		}

		$settings = array(
			'query' => $query_settings,
		);
		$query_vars = \Bricks\Query::prepare_query_vars_from_settings($settings, $this->id, $this->name, true);

		if (!is_array($query_vars) || empty($query_vars)) {
			return array();
		}

		$normalized = array();

		foreach (self::get_supported_query_var_keys() as $key) {
			if (!array_key_exists($key, $query_vars)) {
				continue;
			}

			$normalized[$key] = $this->sanitize_query_var_value($query_vars[$key]);
		}

		return array_filter(
			$normalized,
			static function ($value) {
				return !(is_array($value) && empty($value)) && '' !== $value && null !== $value;
			}
		);
	}

	private function sanitize_query_var_value($value)
	{
		if (is_array($value)) {
			$sanitized = array();

			foreach ($value as $key => $item) {
				$sanitized_key = is_string($key) ? sanitize_key($key) : $key;
				$sanitized[$sanitized_key] = $this->sanitize_query_var_value($item);
			}

			return $sanitized;
		}

		if (is_bool($value) || is_int($value) || is_float($value)) {
			return $value;
		}

		if (is_string($value)) {
			return sanitize_text_field($value);
		}

		return null;
	}

	private function get_children_markup(): string
	{
		ob_start();
		$returned_markup = \Bricks\Frontend::render_children($this);
		$buffered_markup = (string) ob_get_clean();
		$children_markup = '';

		if (is_string($returned_markup)) {
			$children_markup .= $returned_markup;
		}

		if ('' !== $buffered_markup && false === strpos($children_markup, $buffered_markup)) {
			$children_markup = $buffered_markup . $children_markup;
		}

		return trim($children_markup);
	}
}