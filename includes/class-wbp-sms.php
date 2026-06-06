<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WBP_SMS {
	public function send( string $phone, string $message, ?int $offer_id = null ): array {
		$provider = WBP_Settings::get( 'sms_provider', 'custom_webhook' );
		$result   = array(
			'success'  => false,
			'response' => '',
		);

		if ( 'yes' !== WBP_Settings::get( 'enable_sms', 'no' ) ) {
			$result['response'] = 'SMS disabled';
			$this->log( $phone, $message, 'disabled', $result['response'], $offer_id, $provider );
			return $result;
		}

		if ( 'custom_webhook' === $provider ) {
			$webhook = WBP_Settings::get( 'sms_webhook_url', '' );
			if ( empty( $webhook ) ) {
				$result['response'] = 'Webhook not configured';
				$this->log( $phone, $message, 'failed', $result['response'], $offer_id, $provider );
				return $result;
			}

			$response = wp_remote_post(
				$webhook,
				array(
					'timeout' => 15,
					'headers' => array( 'Content-Type' => 'application/json' ),
					'body'    => wp_json_encode(
						array(
							'phone'   => $phone,
							'message' => $message,
							'offer_id'=> $offer_id,
						)
					),
				)
			);

			$result['success']  = ! is_wp_error( $response ) && (int) wp_remote_retrieve_response_code( $response ) < 300;
			$result['response'] = is_wp_error( $response ) ? $response->get_error_message() : wp_remote_retrieve_body( $response );
		} else {
			$result['response'] = 'Provider scaffold available but not implemented yet.';
		}

		$this->log( $phone, $message, $result['success'] ? 'sent' : 'failed', $result['response'], $offer_id, $provider );
		return $result;
	}

	protected function log( string $phone, string $message, string $status, string $response, ?int $offer_id, string $provider ): void {
		global $wpdb;
		$wpdb->insert(
			WBP_Database::sms_logs_table(),
			array(
				'offer_id'    => $offer_id,
				'provider'    => $provider,
				'phone'       => $phone,
				'message'     => $message,
				'status'      => $status,
				'response'    => $response,
				'created_at'  => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
	}
}
