<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$settings = get_option( 'wbp_settings', array() );
if ( empty( $settings['delete_on_uninstall'] ) || 'yes' !== $settings['delete_on_uninstall'] ) {
	return;
}

global $wpdb;
$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'wbp_offers' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'wbp_offer_messages' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'wbp_sms_logs' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
delete_option( 'wbp_settings' );
