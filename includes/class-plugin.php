<?php

namespace PostCalendar;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once POST_CALENDAR_PLUGIN_DIR . 'includes/class-acf-fields.php';
require_once POST_CALENDAR_PLUGIN_DIR . 'includes/class-assets.php';
require_once POST_CALENDAR_PLUGIN_DIR . 'includes/class-shortcode.php';
require_once POST_CALENDAR_PLUGIN_DIR . 'includes/admin/class-settings-page.php';
require_once POST_CALENDAR_PLUGIN_DIR . 'includes/api/class-events-endpoint.php';
require_once POST_CALENDAR_PLUGIN_DIR . 'includes/bricks/class-bricks-integration.php';

class Plugin {
	/**
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * @var Admin\Settings_Page
	 */
	private $settings_page;

	/**
	 * @var ACF_Fields
	 */
	private $acf_fields;

	/**
	 * @var Bricks\Bricks_Integration
	 */
	private $bricks_integration;

	/**
	 * @var Assets
	 */
	private $assets;

	/**
	 * @var API\Events_Endpoint
	 */
	private $events_endpoint;

	/**
	 * @var Shortcode
	 */
	private $shortcode;

	public static function boot(): void {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
	}

	public static function instance(): ?self {
		return self::$instance;
	}

	private function __construct() {
		$this->acf_fields         = new ACF_Fields();
		$this->assets             = new Assets();
		$this->settings_page      = new Admin\Settings_Page();
		$this->events_endpoint    = new API\Events_Endpoint();
		$this->shortcode          = new Shortcode();
		$this->bricks_integration = new Bricks\Bricks_Integration();

		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
	}

	public function assets(): Assets {
		return $this->assets;
	}

	public function load_textdomain(): void {
		load_plugin_textdomain( 'post-calendar', false, dirname( plugin_basename( POST_CALENDAR_PLUGIN_FILE ) ) . '/languages' );
	}
}