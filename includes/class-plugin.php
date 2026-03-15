<?php

namespace PostCalendar;

use PostCalendar\Event_Sources\Admin_Editor;
use PostCalendar\Event_Sources\Event_Model_Sync;
use PostCalendar\Event_Sources\Post_Type;
use PostCalendar\Event_Sources\Rest_Controller;
use PostCalendar\Event_Sources\Settings_Page;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once POST_CALENDAR_PLUGIN_DIR . 'includes/class-assets.php';
require_once POST_CALENDAR_PLUGIN_DIR . 'includes/class-update-checker.php';
require_once POST_CALENDAR_PLUGIN_DIR . 'includes/event-sources/event-config.php';
require_once POST_CALENDAR_PLUGIN_DIR . 'includes/event-sources/event-date-parser.php';
require_once POST_CALENDAR_PLUGIN_DIR . 'includes/bricks/elements.php';
require_once POST_CALENDAR_PLUGIN_DIR . 'includes/event-sources/admin-editor.php';
require_once POST_CALENDAR_PLUGIN_DIR . 'includes/event-sources/event-model-sync.php';
require_once POST_CALENDAR_PLUGIN_DIR . 'includes/event-sources/event-query-service.php';
require_once POST_CALENDAR_PLUGIN_DIR . 'includes/event-sources/post-type.php';
require_once POST_CALENDAR_PLUGIN_DIR . 'includes/event-sources/rest-controller.php';
require_once POST_CALENDAR_PLUGIN_DIR . 'includes/event-sources/settings-page.php';
require_once POST_CALENDAR_PLUGIN_DIR . 'includes/shortcode/shortcode.php';

class Plugin {
	/**
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * @var Settings_Page
	 */
	private $settings_page;

	/**
	 * @var Admin_Editor
	 */
	private $admin_editor;

	/**
	 * @var Event_Model_Sync
	 */
	private $event_model_sync;

	/**
	 * @var Post_Type
	 */
	private $proxy_post_type;

	/**
	 * @var Bricks\Elements
	 */
	private $bricks_elements;

	/**
	 * @var Assets
	 */
	private $assets;

	/**
	 * @var Rest_Controller
	 */
	private $proxy_post_type_rest;

	/**
	 * @var Shortcode\Shortcode
	 */
	private $shortcode;

	/**
	 * @var Update_Checker
	 */
	private $update_checker;

	public static function boot(): void {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
	}

	public static function instance(): ?self {
		return self::$instance;
	}

	private function __construct() {
		$this->proxy_post_type      = new Post_Type();
		$this->assets               = new Assets();
		$this->admin_editor         = new Admin_Editor( $this->assets );
		$this->event_model_sync     = new Event_Model_Sync();
		$this->settings_page        = new Settings_Page();
		$this->proxy_post_type_rest = new Rest_Controller();
		$this->shortcode            = new Shortcode\Shortcode();
		$this->bricks_elements      = new Bricks\Elements();
		$this->update_checker       = new Update_Checker();

		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
	}

	public function assets(): Assets {
		return $this->assets;
	}

	public function load_textdomain(): void {
		load_plugin_textdomain( 'post-calendar', false, dirname( plugin_basename( POST_CALENDAR_PLUGIN_FILE ) ) . '/languages' );
	}
}