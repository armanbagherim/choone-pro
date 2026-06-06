<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WBP_WhatsApp {
	public static function normalize_iran_phone( string $phone ): string {
		$phone = preg_replace( '/[^0-9]/', '', $phone );
		if ( 0 === strpos( $phone, '09' ) ) {
			return '98' . substr( $phone, 1 );
		}
		if ( 0 === strpos( $phone, '9' ) ) {
			return '98' . $phone;
		}
		if ( 0 === strpos( $phone, '0098' ) ) {
			return substr( $phone, 2 );
		}
		return $phone;
	}

	public static function admin_link( string $phone, string $message = '' ): string {
		$normalized = self::normalize_iran_phone( $phone );
		return add_query_arg(
			array(
				'phone' => rawurlencode( $normalized ),
				'text'  => rawurlencode( $message ),
			),
			'https://wa.me/'
		);
	}
}
