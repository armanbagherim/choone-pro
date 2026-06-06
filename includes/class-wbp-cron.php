<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WBP_Cron {
	const HOOK = 'wbp_expire_offers_event';

	public static function register(): void {
		add_action( self::HOOK, array( new WBP_Offers(), 'maybe_expire_offers' ) );
	}

	public static function schedule(): void {
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', self::HOOK );
		}
	}

	public static function unschedule(): void {
		$timestamp = wp_next_scheduled( self::HOOK );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::HOOK );
		}
	}
}
