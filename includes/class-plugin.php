<?php

namespace PostCalendar;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once POST_CALENDAR_PLUGIN_DIR . 'includes/class-acf-fields.php';
require_once POST_CALENDAR_PLUGIN_DIR . 'includes/class-assets.php';
require_once POST_CALENDAR_PLUGIN_DIR . 'includes/class-proxy-post-type.php';
require_once POST_CALENDAR_PLUGIN_DIR . 'includes/class-shortcode.php';
require_once POST_CALENDAR_PLUGIN_DIR . 'includes/admin/class-settings-page.php';
require_once POST_CALENDAR_PLUGIN_DIR . 'includes/api/class-event-query-service.php';
require_once POST_CALENDAR_PLUGIN_DIR . 'includes/api/class-proxy-post-type-rest.php';
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
	 * @var Proxy_Post_Type
	 */
	private $proxy_post_type;

	/**
	 * @var Bricks\Bricks_Integration
	 */
	private $bricks_integration;

	/**
	 * @var Assets
	 */
	private $assets;

	/**
	 * @var API\Proxy_Post_Type_Rest
	 */
	private $proxy_post_type_rest;

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
		$this->proxy_post_type    = new Proxy_Post_Type();
		$this->acf_fields         = new ACF_Fields();
		$this->assets             = new Assets();
		$this->settings_page      = new Admin\Settings_Page();
		$this->proxy_post_type_rest = new API\Proxy_Post_Type_Rest();
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