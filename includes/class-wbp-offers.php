<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WBP_Offers {
	protected WBP_SMS $sms;

	public function __construct() {
		$this->sms = new WBP_SMS();
	}

	public function statuses(): array {
		return array(
			'pending'                  => __( 'در انتظار بررسی', 'woo-bargain-pro' ),
			'accepted'                 => __( 'تایید شده', 'woo-bargain-pro' ),
			'rejected'                 => __( 'رد شده', 'woo-bargain-pro' ),
			'countered'                => __( 'پیشنهاد متقابل', 'woo-bargain-pro' ),
			'customer_accepted_counter'=> __( 'تایید مشتری', 'woo-bargain-pro' ),
			'expired'                  => __( 'منقضی شده', 'woo-bargain-pro' ),
			'converted_to_order'       => __( 'تبدیل به سفارش', 'woo-bargain-pro' ),
		);
	}

	public function is_enabled_for_product( int $product_id ): bool {
		if ( 'yes' === WBP_Settings::get( 'enable_all_products', 'no' ) ) {
			return true;
		}

		return 'yes' === get_post_meta( $product_id, '_wbp_enabled', true );
	}

	public function get_product_rules( int $product_id ): array {
		$defaults = array(
			'enabled'        => $this->is_enabled_for_product( $product_id ) ? 'yes' : 'no',
			'min_amount'     => '',
			'min_percent'    => WBP_Settings::get( 'default_min_percent', 10 ),
			'auto_accept'    => '',
			'auto_reject'    => '',
			'expiration'     => WBP_Settings::get( 'default_expiration', 24 ),
			'max_offers'     => WBP_Settings::get( 'default_max_offers', 3 ),
			'custom_message' => '',
		);

		foreach ( $defaults as $key => $value ) {
			$stored = get_post_meta( $product_id, '_wbp_' . $key, true );
			if ( '' !== $stored && null !== $stored ) {
				$defaults[ $key ] = $stored;
			}
		}

		return $defaults;
	}

	public function minimum_allowed_price( int $product_id, float $original_price ): float {
		$rules   = $this->get_product_rules( $product_id );
		$minimum = ! empty( $rules['min_amount'] ) ? (float) $rules['min_amount'] : 0;

		if ( ! empty( $rules['min_percent'] ) ) {
			$percent_min = $original_price - ( $original_price * ( (float) $rules['min_percent'] / 100 ) );
			$minimum     = max( $minimum, $percent_min );
		}

		return max( 0, round( $minimum, 2 ) );
	}

	public function create_offer( array $data ) {
		global $wpdb;
		$product = wc_get_product( $data['product_id'] );
		if ( ! $product ) {
			return new WP_Error( 'invalid_product', __( 'محصول معتبر نیست.', 'woo-bargain-pro' ) );
		}

		$original_price = (float) $product->get_price();
		$offered_price  = (float) $data['offered_price'];
		$rules          = $this->get_product_rules( (int) $data['product_id'] );
		if ( ! $this->can_submit_offer( (int) $data['product_id'], $rules ) ) {
			return new WP_Error( 'rate_limit', __( 'سقف مجاز ثبت پیشنهاد برای این محصول تکمیل شده است.', 'woo-bargain-pro' ) );
		}

		if ( $offered_price <= 0 ) {
			return new WP_Error( 'invalid_price', __( 'مبلغ پیشنهاد معتبر نیست.', 'woo-bargain-pro' ) );
		}

		$status         = 'pending';
		$accepted_price = null;
		$counter_price  = null;
		$expires_at     = null;

		if ( '' !== $rules['auto_reject'] && $offered_price < (float) $rules['auto_reject'] ) {
			$status = 'rejected';
		} elseif ( '' !== $rules['auto_accept'] && $offered_price >= (float) $rules['auto_accept'] ) {
			$status         = 'accepted';
			$accepted_price = $offered_price;
			$expires_at     = $this->expiration_time( (int) $rules['expiration'] );
		}

		$token = wp_generate_password( 14, false, false ) . time();
		$now   = current_time( 'mysql' );

		$wpdb->insert(
			WBP_Database::offers_table(),
			array(
				'product_id'      => (int) $data['product_id'],
				'variation_id'    => ! empty( $data['variation_id'] ) ? (int) $data['variation_id'] : null,
				'user_id'         => get_current_user_id() ?: null,
				'guest_name'      => $data['guest_name'] ?? null,
				'guest_email'     => $data['guest_email'] ?? null,
				'guest_phone'     => $data['guest_phone'] ?? null,
				'original_price'  => $original_price,
				'offered_price'   => $offered_price,
				'counter_price'   => $counter_price,
				'accepted_price'  => $accepted_price,
				'status'          => $status,
				'token'           => $token,
				'checkout_url'    => '',
				'expires_at'      => $expires_at,
				'ip_address'      => $this->user_ip(),
				'user_agent'      => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '',
				'created_at'      => $now,
				'updated_at'      => $now,
			),
			array( '%d', '%d', '%d', '%s', '%s', '%s', '%f', '%f', '%f', '%f', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		$offer_id = (int) $wpdb->insert_id;

		if ( $offer_id && 'accepted' === $status ) {
			$checkout_url = $this->generate_checkout_url( $offer_id, $token, (int) $data['product_id'] );
			$this->update_offer( $offer_id, array( 'checkout_url' => $checkout_url ) );
		}

		$offer = $this->get_offer( $offer_id );
		do_action( 'wbp_offer_created', $offer_id, $offer );

		if ( in_array( $status, array( 'accepted', 'rejected' ), true ) ) {
			do_action( 'accepted' === $status ? 'wbp_offer_accepted' : 'wbp_offer_rejected', $offer_id, $offer );
		}

		return $offer;
	}

	public function get_offer( int $offer_id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . WBP_Database::offers_table() . ' WHERE id = %d', $offer_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	public function get_offer_by_token( string $token ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . WBP_Database::offers_table() . ' WHERE token = %s', $token ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	public function get_offers( array $args = array() ): array {
		global $wpdb;
		$where   = array( '1=1' );
		$prepare = array();

		if ( ! empty( $args['status'] ) ) {
			$where[]   = 'status = %s';
			$prepare[] = $args['status'];
		}
		if ( ! empty( $args['product_id'] ) ) {
			$where[]   = 'product_id = %d';
			$prepare[] = (int) $args['product_id'];
		}
		if ( ! empty( $args['user_id'] ) ) {
			$where[]   = 'user_id = %d';
			$prepare[] = (int) $args['user_id'];
		}
		if ( ! empty( $args['search'] ) ) {
			$search    = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where[]   = '(guest_name LIKE %s OR guest_email LIKE %s OR guest_phone LIKE %s OR token LIKE %s)';
			$prepare[] = $search;
			$prepare[] = $search;
			$prepare[] = $search;
			$prepare[] = $search;
		}

		$sql = 'SELECT * FROM ' . WBP_Database::offers_table() . ' WHERE ' . implode( ' AND ', $where ) . ' ORDER BY created_at DESC'; // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if ( $prepare ) {
			$sql = $wpdb->prepare( $sql, $prepare );
		}

		return $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	public function update_offer( int $offer_id, array $data ): bool {
		global $wpdb;
		$data['updated_at'] = current_time( 'mysql' );
		return false !== $wpdb->update( WBP_Database::offers_table(), $data, array( 'id' => $offer_id ) );
	}

	public function add_message( int $offer_id, string $sender_type, string $message, ?int $sender_id = null ): bool {
		global $wpdb;
		return false !== $wpdb->insert(
			WBP_Database::messages_table(),
			array(
				'offer_id'     => $offer_id,
				'sender_type'  => $sender_type,
				'sender_id'    => $sender_id,
				'message'      => $message,
				'created_at'   => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%d', '%s', '%s' )
		);
	}

	public function get_messages( int $offer_id ): array {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . WBP_Database::messages_table() . ' WHERE offer_id = %d ORDER BY created_at ASC', $offer_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	public function get_latest_offer_for_context( int $product_id, ?string $token = null ) {
		if ( $token ) {
			$offer = $this->get_offer_by_token( $token );
			if ( $offer && (int) $offer->product_id === $product_id ) {
				return $offer;
			}
		}

		$args = array( 'product_id' => $product_id );
		if ( is_user_logged_in() ) {
			$args['user_id'] = get_current_user_id();
		}

		$offers = $this->get_offers( $args );
		if ( is_user_logged_in() ) {
			return $offers[0] ?? null;
		}

		foreach ( $offers as $offer ) {
			if ( $offer->ip_address === $this->user_ip() ) {
				return $offer;
			}
		}

		return null;
	}

	public function set_status( int $offer_id, string $status, array $extra = array() ): bool {
		$offer = $this->get_offer( $offer_id );
		if ( ! $offer ) {
			return false;
		}

		$data = array( 'status' => $status );
		if ( 'accepted' === $status ) {
			$data['accepted_price'] = $extra['accepted_price'] ?? $offer->offered_price;
			$data['expires_at']     = $extra['expires_at'] ?? $this->expiration_time();
			$data['checkout_url']   = $this->generate_checkout_url( $offer_id, $offer->token, (int) $offer->product_id );
		}
		if ( 'countered' === $status ) {
			$data['counter_price'] = $extra['counter_price'] ?? $offer->counter_price;
			$data['expires_at']    = $extra['expires_at'] ?? $this->expiration_time();
			$data['checkout_url']  = $this->generate_checkout_url( $offer_id, $offer->token, (int) $offer->product_id );
		}
		if ( 'customer_accepted_counter' === $status ) {
			$data['accepted_price'] = $extra['accepted_price'] ?? $offer->counter_price;
		}

		$result = $this->update_offer( $offer_id, $data );
		$offer  = $this->get_offer( $offer_id );

		if ( $result ) {
			do_action( 'wbp_offer_' . $status, $offer_id, $offer );
			$this->notify_customer_status( $offer );
		}

		return $result;
	}

	public function notify_customer_status( object $offer ): void {
		$phone = $offer->guest_phone ?? '';
		if ( empty( $phone ) && $offer->user_id ) {
			$phone = get_user_meta( (int) $offer->user_id, 'billing_phone', true );
		}
		if ( $phone ) {
			$this->sms->send( $phone, sprintf( 'وضعیت پیشنهاد شما: %s - کد پیگیری: %s', $offer->status, $offer->token ), (int) $offer->id );
		}
	}

	public function can_submit_offer( int $product_id, array $rules ): bool {
		$max = (int) $rules['max_offers'];
		if ( $max < 1 ) {
			return true;
		}

		global $wpdb;
		if ( is_user_logged_in() ) {
			$count = (int) $wpdb->get_var(
				$wpdb->prepare(
					'SELECT COUNT(*) FROM ' . WBP_Database::offers_table() . ' WHERE product_id = %d AND user_id = %d',
					$product_id,
					get_current_user_id()
				)
			); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		} else {
			$count = (int) $wpdb->get_var(
				$wpdb->prepare(
					'SELECT COUNT(*) FROM ' . WBP_Database::offers_table() . ' WHERE product_id = %d AND ip_address = %s',
					$product_id,
					$this->user_ip()
				)
			); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		return $count < $max;
	}

	public function generate_checkout_url( int $offer_id, string $token, int $product_id ): string {
		return add_query_arg(
			array(
				'add-to-cart'     => $product_id,
				'wbp_offer_token' => $token,
				'wbp_offer_id'    => $offer_id,
			),
			wc_get_cart_url()
		);
	}

	public function expiration_time( ?int $hours = null ): string {
		$hours = $hours ?: (int) WBP_Settings::get( 'default_expiration', 24 );
		return gmdate( 'Y-m-d H:i:s', time() + ( HOUR_IN_SECONDS * $hours ) );
	}

	public function user_ip(): string {
		return isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '0.0.0.0';
	}

	public function maybe_expire_offers(): void {
		global $wpdb;
		$wpdb->query(
			$wpdb->prepare(
				'UPDATE ' . WBP_Database::offers_table() . ' SET status = %s, updated_at = %s WHERE status IN (%s,%s) AND expires_at IS NOT NULL AND expires_at < %s',
				'expired',
				current_time( 'mysql' ),
				'accepted',
				'countered',
				current_time( 'mysql', 1 )
			)
		); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}
}
