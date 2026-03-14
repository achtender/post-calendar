<?php
/**
 * Plugin Name: Post Calendar
 * Plugin URI:  https://github.com/achtender/post-calendar
 * Description: Display posts as events in a calendar via Bricks or shortcode, using existing post types and optional built-in event fields.
 * Version:     0.2.1
 * Author:      Achtender
 * Text Domain: post-calendar
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Update URI:  https://github.com/achtender/post-calendar
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'POST_CALENDAR_VERSION', '0.2.1' );
define( 'POST_CALENDAR_PLUGIN_FILE', __FILE__ );
define( 'POST_CALENDAR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'POST_CALENDAR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once POST_CALENDAR_PLUGIN_DIR . 'includes/class-plugin.php';

PostCalendar\Plugin::boot();