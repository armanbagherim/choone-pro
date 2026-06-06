<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WBP_Database {
	public static function offers_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'wbp_offers';
	}

	public static function messages_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'wbp_offer_messages';
	}

	public static function sms_logs_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'wbp_sms_logs';
	}

	public static function create_tables(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();
		$offers  = self::offers_table();
		$msgs    = self::messages_table();
		$logs    = self::sms_logs_table();

		dbDelta(
			"CREATE TABLE {$offers} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				product_id BIGINT UNSIGNED NOT NULL,
				variation_id BIGINT UNSIGNED NULL,
				user_id BIGINT UNSIGNED NULL,
				guest_name VARCHAR(191) NULL,
				guest_email VARCHAR(191) NULL,
				guest_phone VARCHAR(50) NULL,
				original_price DECIMAL(18,2) NOT NULL,
				offered_price DECIMAL(18,2) NOT NULL,
				counter_price DECIMAL(18,2) NULL,
				accepted_price DECIMAL(18,2) NULL,
				status VARCHAR(50) NOT NULL DEFAULT 'pending',
				token VARCHAR(191) NOT NULL,
				checkout_url TEXT NULL,
				expires_at DATETIME NULL,
				ip_address VARCHAR(100) NULL,
				user_agent TEXT NULL,
				created_at DATETIME NOT NULL,
				updated_at DATETIME NOT NULL,
				PRIMARY KEY (id),
				UNIQUE KEY token (token),
				KEY product_id (product_id),
				KEY user_id (user_id),
				KEY status (status)
			) {$charset};"
		);

		dbDelta(
			"CREATE TABLE {$msgs} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				offer_id BIGINT UNSIGNED NOT NULL,
				sender_type VARCHAR(50) NOT NULL,
				sender_id BIGINT UNSIGNED NULL,
				message TEXT NOT NULL,
				created_at DATETIME NOT NULL,
				PRIMARY KEY (id),
				KEY offer_id (offer_id)
			) {$charset};"
		);

		dbDelta(
			"CREATE TABLE {$logs} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				offer_id BIGINT UNSIGNED NULL,
				provider VARCHAR(100) NULL,
				phone VARCHAR(50) NOT NULL,
				message TEXT NOT NULL,
				status VARCHAR(50) NOT NULL,
				response TEXT NULL,
				created_at DATETIME NOT NULL,
				PRIMARY KEY (id),
				KEY offer_id (offer_id)
			) {$charset};"
		);
	}
}
