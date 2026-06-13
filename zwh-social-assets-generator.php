<?php
/**
 * Plugin Name:       ZWH Social Assets Generator
 * Plugin URI:        https://github.com/ziv-was-here/zwh-social-assets-generator
 * Description:       Generate social captions, titles, subject lines, hashtags, and more from any WordPress post using Claude or OpenAI.
 * Version:           1.0.0
 * Author:            Ziv Rozenberg
 * Author URI:        https://zivwashere.com/
 * License:           GPL-2.0-or-later
 * Text Domain:       zwh-social-assets-generator
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SAG_VERSION', '1.0.0' );
define( 'SAG_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SAG_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once SAG_PLUGIN_DIR . 'includes/class-sag-api.php';
require_once SAG_PLUGIN_DIR . 'includes/class-sag-settings.php';
require_once SAG_PLUGIN_DIR . 'includes/class-sag-meta-box.php';

new SAG_Settings();
new SAG_Meta_Box();
