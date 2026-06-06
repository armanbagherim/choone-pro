<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WBP_Installer {
	public static function activate(): void {
		self::maybe_fail_without_woocommerce();
		WBP_Database::create_tables();
		WBP_Settings::add_defaults();
		WBP_Cron::schedule();
		flush_rewrite_rules();
	}

	public static function deactivate(): void {
		WBP_Cron::unschedule();
		flush_rewrite_rules();
	}

	protected static function maybe_fail_without_woocommerce(): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			deactivate_plugins( plugin_basename( WBP_FILE ) );
			wp_die( esc_html__( 'Woo Bargain Pro requires WooCommerce to be active.', 'woo-bargain-pro' ) );
		}
	}
}
