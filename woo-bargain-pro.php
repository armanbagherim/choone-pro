<?php
/**
 * Plugin Name: Woo Bargain Pro
 * Plugin URI: https://example.com/
 * Description: Professional bargaining and make-an-offer system for WooCommerce.
 * Version: 1.0.0
 * Author: Codex
 * Text Domain: woo-bargain-pro
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.1
 * WC requires at least: 8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WBP_VERSION', '1.0.0' );
define( 'WBP_FILE', __FILE__ );
define( 'WBP_PATH', plugin_dir_path( __FILE__ ) );
define( 'WBP_URL', plugin_dir_url( __FILE__ ) );

require_once WBP_PATH . 'includes/class-wbp-installer.php';
require_once WBP_PATH . 'includes/class-wbp-settings.php';
require_once WBP_PATH . 'includes/class-wbp-database.php';
require_once WBP_PATH . 'includes/class-wbp-sms.php';
require_once WBP_PATH . 'includes/class-wbp-whatsapp.php';
require_once WBP_PATH . 'includes/class-wbp-offers.php';
require_once WBP_PATH . 'includes/class-wbp-cron.php';
require_once WBP_PATH . 'admin/class-wbp-admin.php';
require_once WBP_PATH . 'public/class-wbp-public.php';
require_once WBP_PATH . 'includes/class-wbp.php';

register_activation_hook( __FILE__, array( 'WBP_Installer', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'WBP_Installer', 'deactivate' ) );

function wbp_boot() {
	$plugin = new WBP();
	$plugin->run();
}

wbp_boot();
