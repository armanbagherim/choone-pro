<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WBP_Settings {
	const OPTION_KEY = 'wbp_settings';

	public static function add_defaults(): void {
		if ( get_option( self::OPTION_KEY ) ) {
			return;
		}

		add_option( self::OPTION_KEY, self::defaults() );
	}

	public static function defaults(): array {
		return array(
			'enabled'                 => 'yes',
			'enable_all_products'     => 'no',
			'allow_guests'            => 'yes',
			'collect_phone'           => 'yes',
			'require_phone'           => 'yes',
			'require_email'           => 'no',
			'default_expiration'      => 24,
			'default_min_percent'     => 10,
			'default_max_offers'      => 3,
			'response_wait_minutes'   => 10,
			'poll_interval_seconds'   => 10,
			'enable_chat'             => 'yes',
			'enable_whatsapp'         => 'yes',
			'enable_sms'              => 'no',
			'delete_on_uninstall'     => 'no',
			'primary_color'           => '#17412f',
			'secondary_color'         => '#d7b56d',
			'button_text'             => 'چونه بزنیم؟',
			'box_position'            => 'after_add_to_cart',
			'show_original_price'     => 'yes',
			'show_minimum_hint'       => 'yes',
			'custom_css'              => '',
			'email_notifications'     => 'yes',
			'sms_notifications'       => 'no',
			'admin_offer_alert'       => 'yes',
			'customer_status_alert'   => 'yes',
			'expiration_reminder'     => 'yes',
			'sms_provider'            => 'custom_webhook',
			'sms_api_key'             => '',
			'sms_sender'              => '',
			'sms_pattern'             => '',
			'sms_webhook_url'         => '',
			'admin_phone'             => '',
			'prevent_token_reuse'     => 'yes',
		);
	}

	public static function get_all(): array {
		return wp_parse_args( get_option( self::OPTION_KEY, array() ), self::defaults() );
	}

	public static function get( string $key, $default = null ) {
		$settings = self::get_all();
		return $settings[ $key ] ?? $default;
	}

	public static function update( array $settings ): void {
		update_option( self::OPTION_KEY, wp_parse_args( $settings, self::defaults() ) );
	}
}
