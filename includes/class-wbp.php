<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WBP {
	protected WBP_Offers $offers;
	protected WBP_Admin $admin;
	protected WBP_Public $public;

	public function __construct() {
		$this->offers = new WBP_Offers();
		$this->admin  = new WBP_Admin( $this->offers );
		$this->public = new WBP_Public( $this->offers );
	}

	public function run(): void {
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
		add_action( 'plugins_loaded', array( $this, 'guard_woocommerce' ) );
		WBP_Cron::register();
		$this->admin->register_hooks();
		$this->public->register_hooks();
	}

	public function load_textdomain(): void {
		load_plugin_textdomain( 'woo-bargain-pro', false, dirname( plugin_basename( WBP_FILE ) ) . '/languages' );
	}

	public function guard_woocommerce(): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action(
				'admin_notices',
				static function () {
					echo '<div class="notice notice-error"><p>' . esc_html__( 'Woo Bargain Pro نیاز به افزونه WooCommerce دارد.', 'woo-bargain-pro' ) . '</p></div>';
				}
			);
		}
	}
}
