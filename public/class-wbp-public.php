<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WBP_Public {
	protected WBP_Offers $offers;
	protected array $rendered_products = array();

	public function __construct( WBP_Offers $offers ) {
		$this->offers = $offers;
	}

	public function register_hooks(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_wbp_submit_offer', array( $this, 'ajax_submit_offer' ) );
		add_action( 'wp_ajax_nopriv_wbp_submit_offer', array( $this, 'ajax_submit_offer' ) );
		add_action( 'wp_ajax_wbp_offer_status', array( $this, 'ajax_offer_status' ) );
		add_action( 'wp_ajax_nopriv_wbp_offer_status', array( $this, 'ajax_offer_status' ) );
		add_action( 'wp_ajax_wbp_add_message', array( $this, 'ajax_add_message' ) );
		add_action( 'wp_ajax_nopriv_wbp_add_message', array( $this, 'ajax_add_message' ) );
		add_shortcode( 'woo_bargain_form', array( $this, 'shortcode_form' ) );
		add_shortcode( 'woo_bargain_button', array( $this, 'shortcode_button' ) );
		add_shortcode( 'woo_bargain_my_offers', array( $this, 'shortcode_my_offers' ) );
		add_action( 'init', array( $this, 'register_account_endpoint' ) );
		add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
		add_filter( 'woocommerce_account_menu_items', array( $this, 'account_menu_item' ) );
		add_filter( 'woocommerce_get_endpoint_url', array( $this, 'account_endpoint_url' ), 10, 4 );
		add_action( 'woocommerce_account_bargain-offers_endpoint', array( $this, 'account_endpoint_content' ) );
		add_action( 'woocommerce_single_product_summary', array( $this, 'render_product_box_after_price' ), 11 );
		add_action( 'woocommerce_after_add_to_cart_form', array( $this, 'render_product_box_fallback' ) );
		add_action( 'template_redirect', array( $this, 'maybe_handle_bargain_token' ) );
		add_filter( 'woocommerce_add_cart_item_data', array( $this, 'attach_cart_item_data' ), 10, 3 );
		add_action( 'woocommerce_before_calculate_totals', array( $this, 'apply_bargain_price_to_cart' ) );
		add_filter( 'woocommerce_get_item_data', array( $this, 'render_cart_meta' ), 10, 2 );
		add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'add_order_item_meta' ), 10, 4 );
		add_action( 'woocommerce_thankyou', array( $this, 'mark_offer_converted' ) );
	}

	public function enqueue_assets(): void {
		$public_css_version = file_exists( WBP_PATH . 'assets/css/public.css' ) ? (string) filemtime( WBP_PATH . 'assets/css/public.css' ) : WBP_VERSION;
		$public_js_version  = file_exists( WBP_PATH . 'assets/js/public.js' ) ? (string) filemtime( WBP_PATH . 'assets/js/public.js' ) : WBP_VERSION;
		wp_enqueue_style( 'wbp-public', WBP_URL . 'assets/css/public.css', array(), $public_css_version );
		wp_enqueue_script( 'wbp-public', WBP_URL . 'assets/js/public.js', array( 'jquery' ), $public_js_version, true );
		wp_localize_script(
			'wbp-public',
			'wbpPublic',
			array(
				'ajaxUrl'             => admin_url( 'admin-ajax.php' ),
				'nonce'               => wp_create_nonce( 'wbp_offer_nonce' ),
				'pollIntervalSeconds' => max( 5, (int) WBP_Settings::get( 'poll_interval_seconds', 10 ) ),
				'waitMinutes'         => max( 1, (int) WBP_Settings::get( 'response_wait_minutes', 10 ) ),
				'accountUrl'          => wc_get_account_endpoint_url( 'bargain-offers' ),
			)
		);

		$custom_css = WBP_Settings::get( 'custom_css', '' );
		if ( $custom_css ) {
			wp_add_inline_style( 'wbp-public', $custom_css );
		}
	}

	public function render_product_box(): void {
		global $product;
		if ( ! $product || ! $this->offers->is_enabled_for_product( $product->get_id() ) ) {
			return;
		}
		if ( isset( $this->rendered_products[ $product->get_id() ] ) ) {
			return;
		}
		$this->rendered_products[ $product->get_id() ] = true;
		echo $this->get_form_markup( $product->get_id() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	public function render_product_box_after_price(): void {
		$this->render_product_box();
	}

	public function render_product_box_fallback(): void {
		if ( did_action( 'woocommerce_single_product_summary' ) ) {
			return;
		}

		$this->render_product_box();
	}

	public function shortcode_form( array $atts = array() ): string {
		$atts       = shortcode_atts( array( 'product_id' => get_the_ID() ), $atts );
		$product_id = (int) $atts['product_id'];
		return $this->get_form_markup( $product_id );
	}

	public function shortcode_button( array $atts = array() ): string {
		$atts       = shortcode_atts(
			array(
				'label'      => WBP_Settings::get( 'button_text', __( 'چونه بزنیم؟', 'woo-bargain-pro' ) ),
				'product_id' => get_the_ID(),
			),
			$atts
		);
		$product_id = (int) $atts['product_id'];

		if ( ! $product_id ) {
			return '';
		}

		return $this->get_form_markup( $product_id, (string) $atts['label'] );
	}

	public function shortcode_my_offers(): string {
		ob_start();
		$this->render_user_offers( get_current_user_id() );
		return (string) ob_get_clean();
	}

	protected function get_form_markup( int $product_id, string $button_label = '' ): string {
		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return '';
		}
		$rules      = $this->offers->get_product_rules( $product_id );
		$min_price  = $this->offers->minimum_allowed_price( $product_id, (float) $product->get_price() );
		$user       = wp_get_current_user();
		$primary_color   = WBP_Settings::get( 'primary_color', '#17412f' );
		$secondary_color = WBP_Settings::get( 'secondary_color', '#d7b56d' );
		$button_label = $button_label ?: (string) WBP_Settings::get( 'button_text', __( 'چونه بزنیم؟', 'woo-bargain-pro' ) );
		$show_original_price = 'yes' === WBP_Settings::get( 'show_original_price', 'yes' );
		$show_minimum_hint   = 'yes' === WBP_Settings::get( 'show_minimum_hint', 'yes' );
		$collect_phone       = 'yes' === WBP_Settings::get( 'collect_phone', 'yes' );
		$require_phone       = 'yes' === WBP_Settings::get( 'require_phone', 'yes' );
		$phone_value         = '';
		if ( $user->exists() ) {
			$phone_value = (string) get_user_meta( $user->ID, 'billing_phone', true );
		}
		$email_value = $user->exists() ? (string) $user->user_email : '';
		ob_start();
		?>
		<div class="wbp-entry" data-product-id="<?php echo esc_attr( (string) $product_id ); ?>" style="<?php echo esc_attr( '--wbp-primary:' . $primary_color . ';--wbp-secondary:' . $secondary_color . ';' ); ?>">
			<button type="button" class="wbp-trigger" data-wbp-open="<?php echo esc_attr( 'wbp-modal-' . $product_id ); ?>">
				<span class="wbp-trigger-text"><?php echo esc_html( $button_label ); ?></span>
				<span class="wbp-trigger-hint"><?php esc_html_e( 'قیمت پیشنهادی خودت را ثبت کن', 'woo-bargain-pro' ); ?></span>
			</button>
			<div class="wbp-modal" id="<?php echo esc_attr( 'wbp-modal-' . $product_id ); ?>" aria-hidden="true">
				<div class="wbp-modal-backdrop" data-wbp-close></div>
				<div class="wbp-box" role="dialog" aria-modal="true" aria-labelledby="<?php echo esc_attr( 'wbp-modal-title-' . $product_id ); ?>">
					<button type="button" class="wbp-modal-close" data-wbp-close aria-label="<?php esc_attr_e( 'بستن', 'woo-bargain-pro' ); ?>">×</button>
					<div class="wbp-box-head">
						<span class="wbp-eyebrow"><?php esc_html_e( 'چانه‌زنی هوشمند', 'woo-bargain-pro' ); ?></span>
						<h3 id="<?php echo esc_attr( 'wbp-modal-title-' . $product_id ); ?>"><?php esc_html_e( 'قیمت پیشنهادیتو بده', 'woo-bargain-pro' ); ?></h3>
						<p><?php esc_html_e( 'پیشنهادت را ثبت کن، ما بررسی می‌کنیم و نتیجه را خیلی زود بهت اطلاع می‌دهیم.', 'woo-bargain-pro' ); ?></p>
					</div>
					<form class="wbp-offer-form">
						<input type="hidden" name="product_id" value="<?php echo esc_attr( (string) $product_id ); ?>">
						<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'wbp_offer_nonce' ) ); ?>">
						<?php if ( $show_original_price || $show_minimum_hint ) : ?>
							<div class="wbp-price-row">
							<?php if ( $show_original_price ) : ?>
								<div><span><?php esc_html_e( 'قیمت فعلی', 'woo-bargain-pro' ); ?></span><strong><?php echo wp_kses_post( wc_price( (float) $product->get_price() ) ); ?></strong></div>
							<?php endif; ?>
							<?php if ( $show_minimum_hint ) : ?>
								<div><span><?php esc_html_e( 'حداقل پیشنهاد', 'woo-bargain-pro' ); ?></span><strong><?php echo wp_kses_post( wc_price( $min_price ) ); ?></strong></div>
							<?php endif; ?>
							</div>
						<?php endif; ?>
						<div class="wbp-form-grid">
							<label class="wbp-field wbp-field-full"><span><?php esc_html_e( 'مبلغ پیشنهادی', 'woo-bargain-pro' ); ?></span><input type="number" min="0" step="0.01" name="offered_price" required></label>
							<?php if ( ! is_user_logged_in() ) : ?>
								<label class="wbp-field"><span><?php esc_html_e( 'نام', 'woo-bargain-pro' ); ?></span><input type="text" name="guest_name" required></label>
							<?php else : ?>
								<div class="wbp-user-chip wbp-field"><strong><?php echo esc_html( $user->display_name ); ?></strong><span><?php esc_html_e( 'حساب شما برای این پیشنهاد استفاده می‌شود.', 'woo-bargain-pro' ); ?></span></div>
							<?php endif; ?>
							<?php if ( $collect_phone ) : ?>
								<label class="wbp-field"><span><?php esc_html_e( 'شماره موبایل', 'woo-bargain-pro' ); ?></span><input type="text" name="guest_phone" value="<?php echo esc_attr( $phone_value ); ?>" <?php echo $require_phone ? 'required' : ''; ?>></label>
							<?php endif; ?>
							<?php if ( 'yes' === WBP_Settings::get( 'require_email', 'no' ) ) : ?>
								<label class="wbp-field"><span><?php esc_html_e( 'ایمیل', 'woo-bargain-pro' ); ?></span><input type="email" name="guest_email" value="<?php echo esc_attr( $email_value ); ?>" required></label>
							<?php endif; ?>
						</div>
						<?php if ( ! empty( $rules['custom_message'] ) ) : ?>
							<div class="wbp-note"><?php echo esc_html( $rules['custom_message'] ); ?></div>
						<?php endif; ?>
						<button type="submit"><?php esc_html_e( 'ثبت پیشنهاد', 'woo-bargain-pro' ); ?></button>
						<div class="wbp-response" aria-live="polite"></div>
					</form>
					<div class="wbp-offer-status" hidden>
						<div class="wbp-offer-status-head">
							<div>
								<span class="wbp-eyebrow"><?php esc_html_e( 'آخرین وضعیت', 'woo-bargain-pro' ); ?></span>
								<h4><?php esc_html_e( 'درخواست شما ثبت شده است', 'woo-bargain-pro' ); ?></h4>
							</div>
							<span class="wbp-badge status-pending" data-wbp-status-badge><?php esc_html_e( 'در انتظار بررسی', 'woo-bargain-pro' ); ?></span>
						</div>
						<div class="wbp-offer-summary" data-wbp-offer-summary></div>
						<div class="wbp-offer-link" data-wbp-offer-link></div>
						<div class="wbp-thread" data-wbp-thread></div>
						<form class="wbp-thread-form">
							<input type="hidden" name="offer_id" value="">
							<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'wbp_offer_nonce' ) ); ?>">
							<textarea name="message" rows="3" placeholder="<?php esc_attr_e( 'اگر لازم است پیام بگذارید...', 'woo-bargain-pro' ); ?>"></textarea>
							<button type="submit"><?php esc_html_e( 'ارسال پیام', 'woo-bargain-pro' ); ?></button>
						</form>
					</div>
				</div>
			</div>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	public function ajax_submit_offer(): void {
		check_ajax_referer( 'wbp_offer_nonce', 'nonce' );

		$product_id = isset( $_POST['product_id'] ) ? (int) $_POST['product_id'] : 0;
		if ( ! $this->offers->is_enabled_for_product( $product_id ) ) {
			wp_send_json_error( array( 'message' => __( 'امکان ثبت پیشنهاد برای این محصول فعال نیست.', 'woo-bargain-pro' ) ) );
		}

		if ( ! is_user_logged_in() && 'yes' !== WBP_Settings::get( 'allow_guests', 'yes' ) ) {
			wp_send_json_error( array( 'message' => __( 'ثبت پیشنهاد فقط برای کاربران عضو فعال است.', 'woo-bargain-pro' ) ) );
		}

		$data = array(
			'product_id'    => $product_id,
			'variation_id'  => isset( $_POST['variation_id'] ) ? (int) $_POST['variation_id'] : 0,
			'offered_price' => isset( $_POST['offered_price'] ) ? (float) $_POST['offered_price'] : 0,
			'guest_name'    => isset( $_POST['guest_name'] ) ? sanitize_text_field( wp_unslash( $_POST['guest_name'] ) ) : '',
			'guest_phone'   => isset( $_POST['guest_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['guest_phone'] ) ) : '',
			'guest_email'   => isset( $_POST['guest_email'] ) ? sanitize_email( wp_unslash( $_POST['guest_email'] ) ) : '',
		);

		$offer = $this->offers->create_offer( $data );
		if ( is_wp_error( $offer ) ) {
			wp_send_json_error( array( 'message' => $offer->get_error_message() ) );
		}

		wp_send_json_success(
			array(
				'message'      => sprintf( __( 'پیشنهاد شما ثبت شد. حدود %d دقیقه منتظر پاسخ بمانید.', 'woo-bargain-pro' ), max( 1, (int) WBP_Settings::get( 'response_wait_minutes', 10 ) ) ),
				'token'        => $offer->token,
				'offer_id'     => (int) $offer->id,
				'status'       => $this->offers->statuses()[ $offer->status ] ?? $offer->status,
				'status_key'   => $offer->status,
				'checkout_url' => $offer->checkout_url,
				'offer'        => $this->prepare_offer_payload( $offer ),
			)
		);
	}

	public function ajax_add_message(): void {
		check_ajax_referer( 'wbp_offer_nonce', 'nonce' );
		$offer_id = isset( $_POST['offer_id'] ) ? (int) $_POST['offer_id'] : 0;
		$message  = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';
		$offer    = $offer_id ? $this->offers->get_offer( $offer_id ) : null;
		if ( ! $offer_id || ! $message || ! $offer || ! $this->can_access_offer( $offer ) ) {
			wp_send_json_error( array( 'message' => __( 'دسترسی به این گفتگو مجاز نیست.', 'woo-bargain-pro' ) ) );
		}
		$this->offers->add_message( $offer_id, 'customer', $message, get_current_user_id() ?: null );
		wp_send_json_success();
	}

	public function ajax_offer_status(): void {
		check_ajax_referer( 'wbp_offer_nonce', 'nonce' );

		$product_id = isset( $_POST['product_id'] ) ? (int) $_POST['product_id'] : 0;
		$token      = isset( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : '';
		if ( ! $product_id ) {
			wp_send_json_error( array( 'message' => __( 'محصول نامعتبر است.', 'woo-bargain-pro' ) ) );
		}

		$offer = $this->offers->get_latest_offer_for_context( $product_id, $token );
		if ( ! $offer ) {
			wp_send_json_success( array( 'offer' => null ) );
		}
		if ( ! $this->can_access_offer( $offer ) ) {
			wp_send_json_error( array( 'message' => __( 'دسترسی به این پیشنهاد مجاز نیست.', 'woo-bargain-pro' ) ) );
		}

		wp_send_json_success( array( 'offer' => $this->prepare_offer_payload( $offer ) ) );
	}

	public function register_account_endpoint(): void {
		add_rewrite_endpoint( 'bargain-offers', EP_ROOT | EP_PAGES );
		if ( get_option( 'wbp_rewrite_ready' ) !== WBP_VERSION ) {
			flush_rewrite_rules( false );
			update_option( 'wbp_rewrite_ready', WBP_VERSION );
		}
	}

	public function add_query_vars( array $vars ): array {
		$vars[] = 'bargain-offers';
		return $vars;
	}

	public function account_menu_item( array $items ): array {
		$logout = $items['customer-logout'] ?? null;
		unset( $items['customer-logout'] );
		$items['bargain-offers'] = __( 'پیشنهادهای من', 'woo-bargain-pro' );
		if ( $logout ) {
			$items['customer-logout'] = $logout;
		}
		return $items;
	}

	public function account_endpoint_url( string $url, string $endpoint, string $value, string $permalink ): string {
		if ( 'bargain-offers' !== $endpoint ) {
			return $url;
		}

		return add_query_arg( 'bargain-offers', '1', wc_get_page_permalink( 'myaccount' ) );
	}

	public function account_endpoint_content(): void {
		$this->render_user_offers( get_current_user_id() );
	}

	protected function render_user_offers( int $user_id ): void {
		if ( ! $user_id ) {
			echo '<p>' . esc_html__( 'برای مشاهده پیشنهادها وارد حساب کاربری شوید.', 'woo-bargain-pro' ) . '</p>';
			return;
		}
		$offers = $this->offers->get_offers( array( 'user_id' => $user_id ) );
		?>
		<div class="wbp-account-list">
			<?php if ( empty( $offers ) ) : ?>
				<p><?php esc_html_e( 'هنوز پیشنهادی ثبت نکرده‌اید.', 'woo-bargain-pro' ); ?></p>
			<?php endif; ?>
			<?php foreach ( $offers as $offer ) : ?>
				<div class="wbp-account-card">
					<div class="wbp-account-top">
						<div>
							<h4><?php echo esc_html( get_the_title( (int) $offer->product_id ) ); ?></h4>
							<p class="wbp-account-token"><?php esc_html_e( 'کد پیگیری', 'woo-bargain-pro' ); ?>: <?php echo esc_html( $offer->token ); ?></p>
						</div>
						<span class="wbp-badge status-<?php echo esc_attr( $offer->status ); ?>"><?php echo esc_html( $this->offers->statuses()[ $offer->status ] ?? $offer->status ); ?></span>
					</div>
					<div class="wbp-account-prices">
						<span><?php esc_html_e( 'اصلی', 'woo-bargain-pro' ); ?>: <?php echo wp_kses_post( wc_price( (float) $offer->original_price ) ); ?></span>
						<span><?php esc_html_e( 'پیشنهادی', 'woo-bargain-pro' ); ?>: <?php echo wp_kses_post( wc_price( (float) $offer->offered_price ) ); ?></span>
						<?php if ( $offer->counter_price ) : ?><span><?php esc_html_e( 'متقابل', 'woo-bargain-pro' ); ?>: <?php echo wp_kses_post( wc_price( (float) $offer->counter_price ) ); ?></span><?php endif; ?>
						<?php if ( $offer->accepted_price ) : ?><span><?php esc_html_e( 'نهایی', 'woo-bargain-pro' ); ?>: <?php echo wp_kses_post( wc_price( (float) $offer->accepted_price ) ); ?></span><?php endif; ?>
					</div>
					<?php if ( ! empty( $offer->guest_phone ) || ! empty( $offer->guest_email ) ) : ?>
						<div class="wbp-account-meta">
							<?php if ( ! empty( $offer->guest_phone ) ) : ?><span><?php esc_html_e( 'موبایل', 'woo-bargain-pro' ); ?>: <?php echo esc_html( $offer->guest_phone ); ?></span><?php endif; ?>
							<?php if ( ! empty( $offer->guest_email ) ) : ?><span><?php esc_html_e( 'ایمیل', 'woo-bargain-pro' ); ?>: <?php echo esc_html( $offer->guest_email ); ?></span><?php endif; ?>
						</div>
					<?php endif; ?>
					<?php $messages = $this->offers->get_messages( (int) $offer->id ); ?>
					<?php if ( ! empty( $messages ) ) : ?>
						<div class="wbp-account-thread">
							<?php foreach ( $messages as $message ) : ?>
								<div class="wbp-account-thread-item role-<?php echo esc_attr( $message->sender_type ); ?>">
									<strong><?php echo esc_html( 'admin' === $message->sender_type ? __( 'فروشنده', 'woo-bargain-pro' ) : __( 'شما', 'woo-bargain-pro' ) ); ?></strong>
									<p><?php echo esc_html( $message->message ); ?></p>
								</div>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
					<div class="wbp-account-actions">
						<?php if ( 'countered' === $offer->status ) : ?>
							<a class="button" href="<?php echo esc_url( add_query_arg( array( 'wbp_accept_counter' => $offer->token ) ) ); ?>"><?php esc_html_e( 'قبول پیشنهاد متقابل', 'woo-bargain-pro' ); ?></a>
						<?php endif; ?>
						<?php if ( $offer->checkout_url && in_array( $offer->status, array( 'accepted', 'countered', 'customer_accepted_counter' ), true ) ) : ?>
							<a class="button button-primary" href="<?php echo esc_url( $offer->checkout_url ); ?>"><?php esc_html_e( 'خرید با قیمت توافقی', 'woo-bargain-pro' ); ?></a>
						<?php endif; ?>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
	}

	public function maybe_handle_bargain_token(): void {
		if ( ! empty( $_GET['wbp_accept_counter'] ) && is_user_logged_in() ) {
			$offer = $this->offers->get_offer_by_token( sanitize_text_field( wp_unslash( $_GET['wbp_accept_counter'] ) ) );
			if ( $offer && (int) $offer->user_id === get_current_user_id() ) {
				$this->offers->set_status( (int) $offer->id, 'customer_accepted_counter', array( 'accepted_price' => (float) $offer->counter_price ) );
				wp_safe_redirect( wc_get_account_endpoint_url( 'bargain-offers' ) );
				exit;
			}
		}
	}

	protected function prepare_offer_payload( object $offer ): array {
		return array(
			'id'               => (int) $offer->id,
			'token'            => (string) $offer->token,
			'product_id'       => (int) $offer->product_id,
			'status'           => (string) $offer->status,
			'status_label'     => $this->offers->statuses()[ $offer->status ] ?? $offer->status,
			'original_price'   => wc_price( (float) $offer->original_price ),
			'offered_price'    => wc_price( (float) $offer->offered_price ),
			'counter_price'    => $offer->counter_price ? wc_price( (float) $offer->counter_price ) : '',
			'accepted_price'   => $offer->accepted_price ? wc_price( (float) $offer->accepted_price ) : '',
			'checkout_url'     => (string) $offer->checkout_url,
			'created_at'       => wp_date( 'Y/m/d H:i', strtotime( $offer->created_at ) ),
			'messages'         => array_map(
				function ( object $message ): array {
					return array(
						'sender_type' => (string) $message->sender_type,
						'label'       => 'admin' === $message->sender_type ? __( 'فروشنده', 'woo-bargain-pro' ) : __( 'شما', 'woo-bargain-pro' ),
						'message'     => (string) $message->message,
						'created_at'  => wp_date( 'Y/m/d H:i', strtotime( $message->created_at ) ),
					);
				},
				$this->offers->get_messages( (int) $offer->id )
			),
		);
	}

	protected function can_access_offer( object $offer ): bool {
		if ( is_user_logged_in() ) {
			return (int) $offer->user_id === get_current_user_id();
		}

		return (string) $offer->ip_address === $this->offers->user_ip();
	}

	public function attach_cart_item_data( array $cart_item_data, int $product_id, int $variation_id ): array {
		if ( empty( $_REQUEST['wbp_offer_token'] ) ) {
			return $cart_item_data;
		}

		$token = sanitize_text_field( wp_unslash( $_REQUEST['wbp_offer_token'] ) );
		$offer = $this->offers->get_offer_by_token( $token );
		if ( ! $offer || (int) $offer->product_id !== $product_id ) {
			return $cart_item_data;
		}

		$valid_statuses = array( 'accepted', 'countered', 'customer_accepted_counter' );
		if ( ! in_array( $offer->status, $valid_statuses, true ) ) {
			return $cart_item_data;
		}

		if ( 'yes' === WBP_Settings::get( 'prevent_token_reuse', 'yes' ) && 'converted_to_order' === $offer->status ) {
			return $cart_item_data;
		}

		$cart_item_data['wbp_offer_id'] = (int) $offer->id;
		$cart_item_data['wbp_price']    = (float) ( $offer->accepted_price ?: $offer->counter_price ?: $offer->offered_price );
		$cart_item_data['wbp_token']    = $offer->token;
		return $cart_item_data;
	}

	public function apply_bargain_price_to_cart( WC_Cart $cart ): void {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}

		foreach ( $cart->get_cart() as $cart_item ) {
			if ( isset( $cart_item['wbp_price'] ) && isset( $cart_item['data'] ) ) {
				$cart_item['data']->set_price( (float) $cart_item['wbp_price'] );
			}
		}
	}

	public function render_cart_meta( array $item_data, array $cart_item ): array {
		if ( ! empty( $cart_item['wbp_token'] ) ) {
			$item_data[] = array(
				'key'   => __( 'کد توافق', 'woo-bargain-pro' ),
				'value' => wc_clean( $cart_item['wbp_token'] ),
			);
		}
		return $item_data;
	}

	public function add_order_item_meta( WC_Order_Item_Product $item, string $cart_item_key, array $values, WC_Order $order ): void {
		if ( ! empty( $values['wbp_offer_id'] ) ) {
			$item->add_meta_data( __( 'Bargain Offer ID', 'woo-bargain-pro' ), (int) $values['wbp_offer_id'] );
			$item->add_meta_data( __( 'Bargain Token', 'woo-bargain-pro' ), wc_clean( $values['wbp_token'] ?? '' ) );
		}
	}

	public function mark_offer_converted( int $order_id ): void {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}
		foreach ( $order->get_items() as $item ) {
			$offer_id = $item->get_meta( 'Bargain Offer ID', true );
			if ( $offer_id ) {
				$this->offers->set_status( (int) $offer_id, 'converted_to_order' );
				do_action( 'wbp_offer_converted_to_order', (int) $offer_id, $order_id, $this->offers->get_offer( (int) $offer_id ) );
			}
		}
	}
}
