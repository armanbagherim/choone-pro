<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WBP_Admin {
	protected WBP_Offers $offers;

	public function __construct( WBP_Offers $offers ) {
		$this->offers = $offers;
	}

	public function register_hooks(): void {
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_init', array( $this, 'handle_actions' ) );
		add_action( 'wp_ajax_wbp_admin_poll', array( $this, 'ajax_admin_poll' ) );
		add_action( 'woocommerce_product_options_pricing', array( $this, 'product_fields' ) );
		add_action( 'woocommerce_process_product_meta', array( $this, 'save_product_fields' ) );
	}

	public function admin_menu(): void {
		$cap = 'manage_woocommerce';
		add_menu_page( __( 'چونه‌زن ووکامرس', 'woo-bargain-pro' ), __( 'چونه‌زن ووکامرس', 'woo-bargain-pro' ), $cap, 'wbp-dashboard', array( $this, 'render_dashboard' ), 'dashicons-money-alt', 56 );
		add_submenu_page( 'wbp-dashboard', __( 'داشبورد', 'woo-bargain-pro' ), __( 'داشبورد', 'woo-bargain-pro' ), $cap, 'wbp-dashboard', array( $this, 'render_dashboard' ) );
		add_submenu_page( 'wbp-dashboard', __( 'پیشنهادها', 'woo-bargain-pro' ), __( 'پیشنهادها', 'woo-bargain-pro' ), $cap, 'wbp-offers', array( $this, 'render_offers_page' ) );
		add_submenu_page( 'wbp-dashboard', __( 'تنظیمات', 'woo-bargain-pro' ), __( 'تنظیمات', 'woo-bargain-pro' ), $cap, 'wbp-settings', array( $this, 'render_settings_page' ) );
		add_submenu_page( 'wbp-dashboard', __( 'پیامک', 'woo-bargain-pro' ), __( 'پیامک', 'woo-bargain-pro' ), $cap, 'wbp-sms', array( $this, 'render_sms_page' ) );
		add_submenu_page( 'wbp-dashboard', __( 'گزارش‌ها', 'woo-bargain-pro' ), __( 'گزارش‌ها', 'woo-bargain-pro' ), $cap, 'wbp-reports', array( $this, 'render_reports_page' ) );
		add_submenu_page( 'wbp-dashboard', __( 'لاگ‌ها', 'woo-bargain-pro' ), __( 'لاگ‌ها', 'woo-bargain-pro' ), $cap, 'wbp-logs', array( $this, 'render_logs_page' ) );
	}

	public function enqueue_assets( string $hook ): void {
		if ( false === strpos( $hook, 'wbp' ) && 'product' !== get_post_type() ) {
			return;
		}
		$admin_css_version = file_exists( WBP_PATH . 'assets/css/admin.css' ) ? (string) filemtime( WBP_PATH . 'assets/css/admin.css' ) : WBP_VERSION;
		$admin_js_version  = file_exists( WBP_PATH . 'assets/js/admin.js' ) ? (string) filemtime( WBP_PATH . 'assets/js/admin.js' ) : WBP_VERSION;
		wp_enqueue_style( 'wbp-admin', WBP_URL . 'assets/css/admin.css', array(), $admin_css_version );
		wp_enqueue_script( 'wbp-admin', WBP_URL . 'assets/js/admin.js', array( 'jquery' ), $admin_js_version, true );
		wp_localize_script(
			'wbp-admin',
			'wbpAdmin',
			array(
				'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
				'nonce'        => wp_create_nonce( 'wbp_admin_poll' ),
				'pollInterval' => 10,
			)
		);
	}

	public function ajax_admin_poll(): void {
		check_ajax_referer( 'wbp_admin_poll', 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error();
		}

		$offers = $this->offers->get_offers();
		$latest = $offers[0] ?? null;
		wp_send_json_success(
			array(
				'latest_id'         => $latest ? (int) $latest->id : 0,
				'latest_created_at' => $latest ? (string) $latest->created_at : '',
				'pending_count'     => count( array_filter( $offers, fn( $offer ) => 'pending' === $offer->status ) ),
			)
		);
	}

	public function render_dashboard(): void {
		$offers         = $this->offers->get_offers();
		$total          = count( $offers );
		$accepted       = count( array_filter( $offers, fn( $offer ) => 'accepted' === $offer->status || 'converted_to_order' === $offer->status ) );
		$pending        = count( array_filter( $offers, fn( $offer ) => 'pending' === $offer->status ) );
		$rejected       = count( array_filter( $offers, fn( $offer ) => 'rejected' === $offer->status ) );
		$converted      = count( array_filter( $offers, fn( $offer ) => 'converted_to_order' === $offer->status ) );
		$conversion     = $total ? round( ( $converted / $total ) * 100, 1 ) : 0;
		$average_disc   = $this->average_discount( $offers );
		$today          = count( array_filter( $offers, fn( $offer ) => gmdate( 'Y-m-d', strtotime( $offer->created_at ) ) === gmdate( 'Y-m-d', current_time( 'timestamp', 1 ) ) ) );
		?>
		<div class="wrap wbp-admin">
			<h1><?php esc_html_e( 'داشبورد چانه‌زنی', 'woo-bargain-pro' ); ?></h1>
			<div class="wbp-cards">
				<?php foreach ( array(
					__( 'کل پیشنهادها', 'woo-bargain-pro' ) => $total,
					__( 'در انتظار', 'woo-bargain-pro' ) => $pending,
					__( 'تایید شده', 'woo-bargain-pro' ) => $accepted,
					__( 'رد شده', 'woo-bargain-pro' ) => $rejected,
					__( 'سفارش نهایی', 'woo-bargain-pro' ) => $converted,
					__( 'نرخ تبدیل', 'woo-bargain-pro' ) => $conversion . '%',
					__( 'میانگین تخفیف', 'woo-bargain-pro' ) => $average_disc . '%',
					__( 'پیشنهادهای امروز', 'woo-bargain-pro' ) => $today,
				) as $label => $value ) : ?>
					<div class="wbp-card">
						<span><?php echo esc_html( $label ); ?></span>
						<strong><?php echo esc_html( (string) $value ); ?></strong>
					</div>
				<?php endforeach; ?>
			</div>
			<div class="wbp-panel">
				<h2><?php esc_html_e( 'پیشنهادهای اخیر', 'woo-bargain-pro' ); ?></h2>
				<?php $this->render_offers_table( array_slice( $offers, 0, 8 ) ); ?>
			</div>
		</div>
		<?php
	}

	public function render_offers_page(): void {
		if ( ! empty( $_GET['offer_id'] ) ) {
			$this->render_offer_detail( (int) $_GET['offer_id'] );
			return;
		}

		$args = array(
			'status' => isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : '',
			'search' => isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '',
		);
		$offers = $this->offers->get_offers( $args );
		?>
		<div class="wrap wbp-admin">
			<h1><?php esc_html_e( 'پیشنهادهای قیمت', 'woo-bargain-pro' ); ?></h1>
			<form method="get" class="wbp-toolbar">
				<input type="hidden" name="page" value="wbp-offers">
				<select name="status">
					<option value=""><?php esc_html_e( 'همه وضعیت‌ها', 'woo-bargain-pro' ); ?></option>
					<?php foreach ( $this->offers->statuses() as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $args['status'], $key ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
				<input type="search" name="s" value="<?php echo esc_attr( $args['search'] ); ?>" placeholder="<?php esc_attr_e( 'جستجو...', 'woo-bargain-pro' ); ?>">
				<button class="button button-primary"><?php esc_html_e( 'فیلتر', 'woo-bargain-pro' ); ?></button>
			</form>
			<div class="wbp-panel">
				<?php $this->render_offers_table( $offers ); ?>
			</div>
		</div>
		<?php
	}

	protected function render_offers_table( array $offers ): void {
		?>
		<table class="widefat striped wbp-table">
			<thead><tr>
				<th><?php esc_html_e( 'شناسه', 'woo-bargain-pro' ); ?></th>
				<th><?php esc_html_e( 'محصول', 'woo-bargain-pro' ); ?></th>
				<th><?php esc_html_e( 'مشتری', 'woo-bargain-pro' ); ?></th>
				<th><?php esc_html_e( 'قیمت اصلی', 'woo-bargain-pro' ); ?></th>
				<th><?php esc_html_e( 'پیشنهاد', 'woo-bargain-pro' ); ?></th>
				<th><?php esc_html_e( 'وضعیت', 'woo-bargain-pro' ); ?></th>
				<th><?php esc_html_e( 'تاریخ', 'woo-bargain-pro' ); ?></th>
				<th><?php esc_html_e( 'عملیات', 'woo-bargain-pro' ); ?></th>
			</tr></thead>
			<tbody>
			<?php if ( empty( $offers ) ) : ?>
				<tr><td colspan="8"><?php esc_html_e( 'پیشنهادی پیدا نشد.', 'woo-bargain-pro' ); ?></td></tr>
			<?php endif; ?>
			<?php foreach ( $offers as $offer ) : ?>
				<tr>
					<td>#<?php echo esc_html( (string) $offer->id ); ?></td>
					<td><?php echo esc_html( get_the_title( (int) $offer->product_id ) ); ?></td>
					<td><?php echo esc_html( $offer->guest_name ?: ( $offer->user_id ? get_the_author_meta( 'display_name', (int) $offer->user_id ) : '-' ) ); ?></td>
					<td><?php echo wp_kses_post( wc_price( (float) $offer->original_price ) ); ?></td>
					<td><?php echo wp_kses_post( wc_price( (float) $offer->offered_price ) ); ?></td>
					<td><span class="wbp-badge status-<?php echo esc_attr( $offer->status ); ?>"><?php echo esc_html( $this->offers->statuses()[ $offer->status ] ?? $offer->status ); ?></span></td>
					<td><?php echo esc_html( wp_date( 'Y/m/d H:i', strtotime( $offer->created_at ) ) ); ?></td>
					<td><a class="button button-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=wbp-offers&offer_id=' . (int) $offer->id ) ); ?>"><?php esc_html_e( 'مشاهده', 'woo-bargain-pro' ); ?></a></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	protected function render_offer_detail( int $offer_id ): void {
		$offer = $this->offers->get_offer( $offer_id );
		if ( ! $offer ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'پیشنهاد پیدا نشد.', 'woo-bargain-pro' ) . '</p></div>';
			return;
		}

		$product  = wc_get_product( (int) $offer->product_id );
		$messages = $this->offers->get_messages( $offer_id );
		$phone    = $offer->guest_phone ?: ( $offer->user_id ? get_user_meta( (int) $offer->user_id, 'billing_phone', true ) : '' );
		?>
		<div class="wrap wbp-admin">
			<div class="wbp-offer-hero">
				<div>
					<h1><?php esc_html_e( 'جزئیات پیشنهاد', 'woo-bargain-pro' ); ?> #<?php echo esc_html( (string) $offer->id ); ?></h1>
					<p><?php echo esc_html( $product ? $product->get_name() : __( 'محصول حذف شده', 'woo-bargain-pro' ) ); ?></p>
				</div>
				<div class="wbp-offer-hero-meta">
					<span class="wbp-badge status-<?php echo esc_attr( $offer->status ); ?>"><?php echo esc_html( $this->offers->statuses()[ $offer->status ] ?? $offer->status ); ?></span>
					<span class="wbp-offer-token"><?php esc_html_e( 'کد رهگیری', 'woo-bargain-pro' ); ?>: <?php echo esc_html( $offer->token ); ?></span>
				</div>
			</div>
			<div class="wbp-offer-stats">
				<div class="wbp-card"><span><?php esc_html_e( 'قیمت اصلی', 'woo-bargain-pro' ); ?></span><strong><?php echo wp_kses_post( wc_price( (float) $offer->original_price ) ); ?></strong></div>
				<div class="wbp-card"><span><?php esc_html_e( 'پیشنهاد مشتری', 'woo-bargain-pro' ); ?></span><strong><?php echo wp_kses_post( wc_price( (float) $offer->offered_price ) ); ?></strong></div>
				<div class="wbp-card"><span><?php esc_html_e( 'پیشنهاد متقابل', 'woo-bargain-pro' ); ?></span><strong><?php echo $offer->counter_price ? wp_kses_post( wc_price( (float) $offer->counter_price ) ) : '-'; ?></strong></div>
				<div class="wbp-card"><span><?php esc_html_e( 'قیمت نهایی', 'woo-bargain-pro' ); ?></span><strong><?php echo $offer->accepted_price ? wp_kses_post( wc_price( (float) $offer->accepted_price ) ) : '-'; ?></strong></div>
			</div>
			<div class="wbp-detail-grid">
				<div class="wbp-panel wbp-detail-panel">
					<h2><?php esc_html_e( 'اطلاعات پیشنهاد', 'woo-bargain-pro' ); ?></h2>
					<ul class="wbp-meta">
						<li><span><?php esc_html_e( 'محصول', 'woo-bargain-pro' ); ?></span><strong><?php echo esc_html( $product ? $product->get_name() : '-' ); ?></strong></li>
						<li><span><?php esc_html_e( 'تاریخ ثبت', 'woo-bargain-pro' ); ?></span><strong><?php echo esc_html( wp_date( 'Y/m/d H:i', strtotime( $offer->created_at ) ) ); ?></strong></li>
						<li><span><?php esc_html_e( 'آخرین بروزرسانی', 'woo-bargain-pro' ); ?></span><strong><?php echo esc_html( wp_date( 'Y/m/d H:i', strtotime( $offer->updated_at ) ) ); ?></strong></li>
						<li><span><?php esc_html_e( 'کد رهگیری', 'woo-bargain-pro' ); ?></span><strong><?php echo esc_html( $offer->token ); ?></strong></li>
					</ul>
				</div>
				<div class="wbp-panel wbp-detail-panel">
					<h2><?php esc_html_e( 'اطلاعات مشتری', 'woo-bargain-pro' ); ?></h2>
					<ul class="wbp-meta wbp-customer-grid">
						<li><span><?php esc_html_e( 'نام', 'woo-bargain-pro' ); ?></span><strong><?php echo esc_html( $offer->guest_name ?: ( $offer->user_id ? get_the_author_meta( 'display_name', (int) $offer->user_id ) : '-' ) ); ?></strong></li>
						<li><span><?php esc_html_e( 'ایمیل', 'woo-bargain-pro' ); ?></span><strong><?php echo esc_html( $offer->guest_email ?: ( $offer->user_id ? get_the_author_meta( 'user_email', (int) $offer->user_id ) : '-' ) ); ?></strong></li>
						<li><span><?php esc_html_e( 'تلفن', 'woo-bargain-pro' ); ?></span><strong><?php echo esc_html( $phone ?: '-' ); ?></strong></li>
					</ul>
					<div class="wbp-actions">
						<form method="post">
							<?php wp_nonce_field( 'wbp_admin_action', 'wbp_nonce' ); ?>
							<input type="hidden" name="offer_id" value="<?php echo esc_attr( (string) $offer->id ); ?>">
							<button name="wbp_action" value="accept" class="button button-primary"><?php esc_html_e( 'تایید', 'woo-bargain-pro' ); ?></button>
							<button name="wbp_action" value="reject" class="button"><?php esc_html_e( 'رد', 'woo-bargain-pro' ); ?></button>
						</form>
						<form method="post" class="wbp-counter-form">
							<?php wp_nonce_field( 'wbp_admin_action', 'wbp_nonce' ); ?>
							<input type="hidden" name="offer_id" value="<?php echo esc_attr( (string) $offer->id ); ?>">
							<input type="number" step="0.01" min="0" name="counter_price" placeholder="<?php esc_attr_e( 'قیمت متقابل', 'woo-bargain-pro' ); ?>">
							<button name="wbp_action" value="counter" class="button button-secondary"><?php esc_html_e( 'ارسال قیمت متقابل', 'woo-bargain-pro' ); ?></button>
						</form>
						<?php if ( $phone ) : ?>
							<a class="button" target="_blank" href="<?php echo esc_url( WBP_WhatsApp::admin_link( $phone, 'سلام، درباره پیشنهاد شما با کد ' . $offer->token . ' در تماس هستیم.' ) ); ?>"><?php esc_html_e( 'واتساپ', 'woo-bargain-pro' ); ?></a>
						<?php endif; ?>
						<?php if ( ! empty( $offer->checkout_url ) ) : ?>
							<a class="button" target="_blank" href="<?php echo esc_url( $offer->checkout_url ); ?>"><?php esc_html_e( 'لینک خرید توافقی', 'woo-bargain-pro' ); ?></a>
						<?php endif; ?>
					</div>
				</div>
			</div>
			<div class="wbp-panel wbp-detail-panel">
				<h2><?php esc_html_e( 'گفت‌وگو', 'woo-bargain-pro' ); ?></h2>
				<div class="wbp-chat">
					<?php if ( empty( $messages ) ) : ?>
						<p><?php esc_html_e( 'هنوز پیامی ثبت نشده است.', 'woo-bargain-pro' ); ?></p>
					<?php endif; ?>
					<?php foreach ( $messages as $message ) : ?>
						<div class="wbp-message role-<?php echo esc_attr( $message->sender_type ); ?>">
							<strong><?php echo esc_html( 'admin' === $message->sender_type ? __( 'مدیر', 'woo-bargain-pro' ) : __( 'مشتری', 'woo-bargain-pro' ) ); ?></strong>
							<p><?php echo esc_html( $message->message ); ?></p>
							<time><?php echo esc_html( wp_date( 'Y/m/d H:i', strtotime( $message->created_at ) ) ); ?></time>
						</div>
					<?php endforeach; ?>
				</div>
				<form method="post" class="wbp-admin-message-form">
					<?php wp_nonce_field( 'wbp_admin_action', 'wbp_nonce' ); ?>
					<input type="hidden" name="offer_id" value="<?php echo esc_attr( (string) $offer->id ); ?>">
					<textarea name="admin_message" placeholder="<?php esc_attr_e( 'پیام به مشتری', 'woo-bargain-pro' ); ?>"></textarea>
					<button name="wbp_action" value="message" class="button button-primary"><?php esc_html_e( 'ارسال پیام', 'woo-bargain-pro' ); ?></button>
				</form>
			</div>
		</div>
		<?php
	}

	public function render_settings_page(): void {
		$config = $this->settings_fields_config();

		if ( isset( $_POST['wbp_settings_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wbp_settings_nonce'] ) ), 'wbp_save_settings' ) ) {
			$updated = array();
			foreach ( $config as $field => $args ) {
				$updated[ $field ] = $this->sanitize_setting_value( $field, $args );
			}
			WBP_Settings::update( wp_parse_args( $updated, WBP_Settings::get_all() ) );
			echo '<div class="updated notice"><p>' . esc_html__( 'تنظیمات ذخیره شد.', 'woo-bargain-pro' ) . '</p></div>';
		}

		$settings = WBP_Settings::get_all();
		$sections = $this->settings_sections();
		?>
		<div class="wrap wbp-admin">
			<div class="wbp-settings-hero">
				<div>
					<h1><?php esc_html_e( 'تنظیمات', 'woo-bargain-pro' ); ?></h1>
					<p><?php esc_html_e( 'ظاهر، رفتار و قواعد چانه‌زنی را از اینجا مدیریت کنید.', 'woo-bargain-pro' ); ?></p>
				</div>
				<div class="wbp-settings-badge"><?php esc_html_e( 'Woo Bargain Pro', 'woo-bargain-pro' ); ?></div>
			</div>
			<form method="post" class="wbp-settings-layout">
				<?php wp_nonce_field( 'wbp_save_settings', 'wbp_settings_nonce' ); ?>
				<?php foreach ( $sections as $section ) : ?>
					<section class="wbp-panel wbp-settings-section">
						<div class="wbp-section-head">
							<h2><?php echo esc_html( $section['title'] ); ?></h2>
							<p><?php echo esc_html( $section['description'] ); ?></p>
						</div>
						<div class="wbp-settings-grid">
							<?php foreach ( $section['fields'] as $field ) : ?>
								<?php $this->render_setting_field( $field, $config[ $field ], $settings[ $field ] ?? '' ); ?>
							<?php endforeach; ?>
						</div>
					</section>
				<?php endforeach; ?>
				<div class="wbp-settings-actions">
					<button class="button button-primary"><?php esc_html_e( 'ذخیره تنظیمات', 'woo-bargain-pro' ); ?></button>
				</div>
			</form>
		</div>
		<?php
	}

	public function render_sms_page(): void {
		echo '<div class="wrap wbp-admin"><h1>' . esc_html__( 'پیامک', 'woo-bargain-pro' ) . '</h1><div class="wbp-panel"><p>' . esc_html__( 'معماری چند ارائه‌دهنده آماده است و Custom Webhook به‌صورت عملیاتی پیاده‌سازی شده است.', 'woo-bargain-pro' ) . '</p></div></div>';
	}

	public function render_reports_page(): void {
		$offers = $this->offers->get_offers();
		echo '<div class="wrap wbp-admin"><h1>' . esc_html__( 'گزارش‌ها', 'woo-bargain-pro' ) . '</h1><div class="wbp-panel"><p>' . esc_html( sprintf( __( 'تعداد کل پیشنهادها: %d', 'woo-bargain-pro' ), count( $offers ) ) ) . '</p></div></div>';
	}

	public function render_logs_page(): void {
		global $wpdb;
		$logs = $wpdb->get_results( 'SELECT * FROM ' . WBP_Database::sms_logs_table() . ' ORDER BY created_at DESC LIMIT 50' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		?>
		<div class="wrap wbp-admin">
			<h1><?php esc_html_e( 'لاگ‌ها', 'woo-bargain-pro' ); ?></h1>
			<div class="wbp-panel">
				<table class="widefat striped">
					<thead><tr><th><?php esc_html_e( 'ارائه‌دهنده', 'woo-bargain-pro' ); ?></th><th><?php esc_html_e( 'تلفن', 'woo-bargain-pro' ); ?></th><th><?php esc_html_e( 'وضعیت', 'woo-bargain-pro' ); ?></th><th><?php esc_html_e( 'تاریخ', 'woo-bargain-pro' ); ?></th></tr></thead>
					<tbody>
					<?php foreach ( $logs as $log ) : ?>
						<tr><td><?php echo esc_html( $log->provider ); ?></td><td><?php echo esc_html( $log->phone ); ?></td><td><?php echo esc_html( $log->status ); ?></td><td><?php echo esc_html( $log->created_at ); ?></td></tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</div>
		<?php
	}

	public function handle_actions(): void {
		if ( empty( $_POST['wbp_action'] ) || empty( $_POST['wbp_nonce'] ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_woocommerce' ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wbp_nonce'] ) ), 'wbp_admin_action' ) ) {
			return;
		}

		$offer_id = isset( $_POST['offer_id'] ) ? (int) $_POST['offer_id'] : 0;
		$action   = sanitize_text_field( wp_unslash( $_POST['wbp_action'] ) );

		if ( 'accept' === $action ) {
			$this->offers->set_status( $offer_id, 'accepted' );
		} elseif ( 'reject' === $action ) {
			$this->offers->set_status( $offer_id, 'rejected' );
		} elseif ( 'counter' === $action && ! empty( $_POST['counter_price'] ) ) {
			$this->offers->set_status( $offer_id, 'countered', array( 'counter_price' => (float) $_POST['counter_price'] ) );
		} elseif ( 'message' === $action && ! empty( $_POST['admin_message'] ) ) {
			$this->offers->add_message( $offer_id, 'admin', sanitize_textarea_field( wp_unslash( $_POST['admin_message'] ) ), get_current_user_id() );
		}

		wp_safe_redirect( wp_get_referer() ?: admin_url( 'admin.php?page=wbp-offers&offer_id=' . $offer_id ) );
		exit;
	}

	public function product_fields(): void {
		echo '<div class="options_group">';
		woocommerce_wp_checkbox( array( 'id' => '_wbp_enabled', 'label' => __( 'فعال‌سازی چانه‌زنی', 'woo-bargain-pro' ) ) );
		woocommerce_wp_text_input( array( 'id' => '_wbp_min_amount', 'label' => __( 'حداقل مبلغ پیشنهادی', 'woo-bargain-pro' ), 'type' => 'number', 'custom_attributes' => array( 'step' => '0.01', 'min' => '0' ) ) );
		woocommerce_wp_text_input( array( 'id' => '_wbp_min_percent', 'label' => __( 'حداقل درصد تخفیف مجاز', 'woo-bargain-pro' ), 'type' => 'number' ) );
		woocommerce_wp_text_input( array( 'id' => '_wbp_auto_accept', 'label' => __( 'مبلغ پذیرش خودکار', 'woo-bargain-pro' ), 'type' => 'number', 'custom_attributes' => array( 'step' => '0.01', 'min' => '0' ) ) );
		woocommerce_wp_text_input( array( 'id' => '_wbp_auto_reject', 'label' => __( 'مبلغ رد خودکار', 'woo-bargain-pro' ), 'type' => 'number', 'custom_attributes' => array( 'step' => '0.01', 'min' => '0' ) ) );
		woocommerce_wp_text_input( array( 'id' => '_wbp_expiration', 'label' => __( 'اعتبار پیشنهاد (ساعت)', 'woo-bargain-pro' ), 'type' => 'number' ) );
		woocommerce_wp_text_input( array( 'id' => '_wbp_max_offers', 'label' => __( 'حداکثر تعداد پیشنهاد', 'woo-bargain-pro' ), 'type' => 'number' ) );
		woocommerce_wp_textarea_input( array( 'id' => '_wbp_custom_message', 'label' => __( 'پیام سفارشی', 'woo-bargain-pro' ) ) );
		echo '</div>';
	}

	public function save_product_fields( int $product_id ): void {
		$fields = array( 'enabled', 'min_amount', 'min_percent', 'auto_accept', 'auto_reject', 'expiration', 'max_offers', 'custom_message' );
		foreach ( $fields as $field ) {
			$key   = '_wbp_' . $field;
			$value = isset( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : '';
			update_post_meta( $product_id, $key, is_string( $value ) ? sanitize_text_field( $value ) : '' );
		}
	}

	protected function settings_sections(): array {
		return array(
			array(
				'title'       => __( 'تنظیمات اصلی', 'woo-bargain-pro' ),
				'description' => __( 'فعال‌سازی کلی افزونه و قوانین اصلی نمایش فرم.', 'woo-bargain-pro' ),
				'fields'      => array( 'enabled', 'enable_all_products', 'box_position', 'allow_guests', 'collect_phone', 'require_phone', 'require_email' ),
			),
			array(
				'title'       => __( 'قواعد پیشنهاد', 'woo-bargain-pro' ),
				'description' => __( 'محدودیت‌ها و رفتار پیش‌فرض برای پیشنهادهای جدید.', 'woo-bargain-pro' ),
				'fields'      => array( 'default_min_percent', 'default_expiration', 'default_max_offers', 'response_wait_minutes', 'poll_interval_seconds', 'prevent_token_reuse', 'show_original_price', 'show_minimum_hint' ),
			),
			array(
				'title'       => __( 'رنگ و تجربه کاربری', 'woo-bargain-pro' ),
				'description' => __( 'ظاهر باکس چانه‌زنی و امکانات ارتباطی مشتری.', 'woo-bargain-pro' ),
				'fields'      => array( 'primary_color', 'secondary_color', 'button_text', 'enable_chat', 'enable_whatsapp', 'email_notifications', 'admin_offer_alert', 'customer_status_alert', 'expiration_reminder' ),
			),
			array(
				'title'       => __( 'تنظیمات پیامک', 'woo-bargain-pro' ),
				'description' => __( 'پیکربندی ارسال پیامک و اطلاعات تماس مدیر.', 'woo-bargain-pro' ),
				'fields'      => array( 'enable_sms', 'sms_notifications', 'sms_provider', 'sms_api_key', 'sms_sender', 'sms_pattern', 'sms_webhook_url', 'admin_phone' ),
			),
			array(
				'title'       => __( 'پیشرفته', 'woo-bargain-pro' ),
				'description' => __( 'کدهای سفارشی و رفتار حذف داده‌ها.', 'woo-bargain-pro' ),
				'fields'      => array( 'delete_on_uninstall', 'custom_css' ),
			),
		);
	}

	protected function settings_fields_config(): array {
		return array(
			'enabled'               => array( 'label' => __( 'فعال‌سازی افزونه', 'woo-bargain-pro' ), 'type' => 'checkbox', 'description' => __( 'کل سیستم چانه‌زنی را در سایت روشن یا خاموش می‌کند.', 'woo-bargain-pro' ) ),
			'enable_all_products'   => array( 'label' => __( 'نمایش برای همه محصولات', 'woo-bargain-pro' ), 'type' => 'checkbox', 'description' => __( 'بدون نیاز به فعال‌سازی جداگانه روی هر محصول، فرم را در همه محصولات نمایش می‌دهد.', 'woo-bargain-pro' ) ),
			'box_position'          => array( 'label' => __( 'محل نمایش باکس', 'woo-bargain-pro' ), 'type' => 'select', 'options' => array( 'after_price' => __( 'زیر قیمت', 'woo-bargain-pro' ), 'after_add_to_cart' => __( 'بعد از دکمه خرید', 'woo-bargain-pro' ) ), 'description' => __( 'برای قالب‌های مختلف محل پیش‌فرض رندر فرم را تعیین می‌کند.', 'woo-bargain-pro' ) ),
			'allow_guests'          => array( 'label' => __( 'اجازه به مهمان', 'woo-bargain-pro' ), 'type' => 'checkbox', 'description' => __( 'کاربران بدون ورود هم بتوانند پیشنهاد ثبت کنند.', 'woo-bargain-pro' ) ),
			'collect_phone'         => array( 'label' => __( 'نمایش فیلد موبایل', 'woo-bargain-pro' ), 'type' => 'checkbox', 'description' => __( 'فیلد شماره موبایل در فرم پیشنهاد نمایش داده شود.', 'woo-bargain-pro' ) ),
			'require_phone'         => array( 'label' => __( 'تلفن اجباری', 'woo-bargain-pro' ), 'type' => 'checkbox', 'description' => __( 'در فرم پیشنهاد، شماره تماس از مشتری گرفته شود.', 'woo-bargain-pro' ) ),
			'require_email'         => array( 'label' => __( 'ایمیل اجباری', 'woo-bargain-pro' ), 'type' => 'checkbox', 'description' => __( 'در فرم پیشنهاد، ایمیل از مشتری گرفته شود.', 'woo-bargain-pro' ) ),
			'default_min_percent'   => array( 'label' => __( 'حداقل درصد تخفیف مجاز', 'woo-bargain-pro' ), 'type' => 'number', 'description' => __( 'پیشنهادها بیشتر از این درصد پایین‌تر از قیمت اصلی نروند.', 'woo-bargain-pro' ) ),
			'default_expiration'    => array( 'label' => __( 'اعتبار پیش‌فرض پیشنهاد', 'woo-bargain-pro' ), 'type' => 'number', 'description' => __( 'مدت اعتبار پیشنهادهای تاییدشده بر حسب ساعت.', 'woo-bargain-pro' ) ),
			'default_max_offers'    => array( 'label' => __( 'حداکثر تعداد پیشنهاد', 'woo-bargain-pro' ), 'type' => 'number', 'description' => __( 'هر محصول چند بار امکان ثبت پیشنهاد داشته باشد.', 'woo-bargain-pro' ) ),
			'response_wait_minutes' => array( 'label' => __( 'زمان انتظار پاسخ', 'woo-bargain-pro' ), 'type' => 'number', 'description' => __( 'مدتی که به کاربر در مودال گفته می‌شود برای پاسخ صبر کند، بر حسب دقیقه.', 'woo-bargain-pro' ) ),
			'poll_interval_seconds' => array( 'label' => __( 'فاصله بررسی خودکار', 'woo-bargain-pro' ), 'type' => 'number', 'description' => __( 'هر چند ثانیه یک‌بار وضعیت پیشنهاد تا زمان باز بودن مودال بررسی شود.', 'woo-bargain-pro' ) ),
			'prevent_token_reuse'   => array( 'label' => __( 'جلوگیری از استفاده مجدد توکن', 'woo-bargain-pro' ), 'type' => 'checkbox', 'description' => __( 'لینک‌های توافقی فقط یک‌بار معتبر بمانند.', 'woo-bargain-pro' ) ),
			'show_original_price'   => array( 'label' => __( 'نمایش قیمت اصلی', 'woo-bargain-pro' ), 'type' => 'checkbox', 'description' => __( 'قیمت اصلی محصول داخل باکس نمایش داده شود.', 'woo-bargain-pro' ) ),
			'show_minimum_hint'     => array( 'label' => __( 'نمایش حداقل پیشنهاد', 'woo-bargain-pro' ), 'type' => 'checkbox', 'description' => __( 'حداقل قیمت قابل‌پیشنهاد به مشتری نشان داده شود.', 'woo-bargain-pro' ) ),
			'primary_color'         => array( 'label' => __( 'رنگ اصلی', 'woo-bargain-pro' ), 'type' => 'color', 'description' => __( 'رنگ دکمه‌ها و عناصر کلیدی فرم.', 'woo-bargain-pro' ) ),
			'secondary_color'       => array( 'label' => __( 'رنگ مکمل', 'woo-bargain-pro' ), 'type' => 'color', 'description' => __( 'رنگ تزئینی برای کارت‌ها و جزئیات.', 'woo-bargain-pro' ) ),
			'button_text'           => array( 'label' => __( 'متن دکمه', 'woo-bargain-pro' ), 'type' => 'text', 'description' => __( 'متنی که روی دکمه باز کردن مودال چانه‌زنی نمایش داده می‌شود.', 'woo-bargain-pro' ) ),
			'enable_chat'           => array( 'label' => __( 'فعال‌سازی گفت‌وگو', 'woo-bargain-pro' ), 'type' => 'checkbox', 'description' => __( 'بخش پیام بین مدیر و مشتری فعال باشد.', 'woo-bargain-pro' ) ),
			'enable_whatsapp'       => array( 'label' => __( 'فعال‌سازی واتساپ', 'woo-bargain-pro' ), 'type' => 'checkbox', 'description' => __( 'لینک‌های ارتباط واتساپ در دسترس باشند.', 'woo-bargain-pro' ) ),
			'email_notifications'   => array( 'label' => __( 'اعلان ایمیلی', 'woo-bargain-pro' ), 'type' => 'checkbox', 'description' => __( 'اعلان‌های ایمیلی مرتبط با پیشنهادها ارسال شود.', 'woo-bargain-pro' ) ),
			'admin_offer_alert'     => array( 'label' => __( 'اعلان پیشنهاد جدید برای مدیر', 'woo-bargain-pro' ), 'type' => 'checkbox', 'description' => __( 'مدیر هنگام ثبت پیشنهاد جدید مطلع شود.', 'woo-bargain-pro' ) ),
			'customer_status_alert' => array( 'label' => __( 'اعلان وضعیت برای مشتری', 'woo-bargain-pro' ), 'type' => 'checkbox', 'description' => __( 'تغییر وضعیت پیشنهاد به مشتری اطلاع داده شود.', 'woo-bargain-pro' ) ),
			'expiration_reminder'   => array( 'label' => __( 'یادآوری انقضا', 'woo-bargain-pro' ), 'type' => 'checkbox', 'description' => __( 'قبل از منقضی شدن پیشنهاد، یادآوری ارسال شود.', 'woo-bargain-pro' ) ),
			'enable_sms'            => array( 'label' => __( 'فعال‌سازی پیامک', 'woo-bargain-pro' ), 'type' => 'checkbox', 'description' => __( 'امکان ارسال پیامک از افزونه روشن شود.', 'woo-bargain-pro' ) ),
			'sms_notifications'     => array( 'label' => __( 'اعلان پیامکی', 'woo-bargain-pro' ), 'type' => 'checkbox', 'description' => __( 'اعلان‌های سیستم از طریق پیامک هم ارسال شوند.', 'woo-bargain-pro' ) ),
			'sms_provider'          => array( 'label' => __( 'ارائه‌دهنده پیامک', 'woo-bargain-pro' ), 'type' => 'text', 'description' => __( 'نام درایور پیامک، مثل custom_webhook.', 'woo-bargain-pro' ) ),
			'sms_api_key'           => array( 'label' => __( 'کلید API پیامک', 'woo-bargain-pro' ), 'type' => 'text', 'description' => __( 'کلید دسترسی سرویس پیامکی.', 'woo-bargain-pro' ) ),
			'sms_sender'            => array( 'label' => __( 'فرستنده پیامک', 'woo-bargain-pro' ), 'type' => 'text', 'description' => __( 'شماره یا شناسه فرستنده پیامک.', 'woo-bargain-pro' ) ),
			'sms_pattern'           => array( 'label' => __( 'پترن پیامک', 'woo-bargain-pro' ), 'type' => 'text', 'description' => __( 'کد الگوی پیامک در سرویس‌دهنده.', 'woo-bargain-pro' ) ),
			'sms_webhook_url'       => array( 'label' => __( 'آدرس وب‌هوک پیامک', 'woo-bargain-pro' ), 'type' => 'url', 'description' => __( 'آدرس endpoint برای ارسال پیامک.', 'woo-bargain-pro' ) ),
			'admin_phone'           => array( 'label' => __( 'تلفن مدیر', 'woo-bargain-pro' ), 'type' => 'text', 'description' => __( 'شماره‌ای که اعلان‌های مهم برای آن ارسال می‌شود.', 'woo-bargain-pro' ) ),
			'delete_on_uninstall'   => array( 'label' => __( 'حذف داده‌ها هنگام پاک‌سازی', 'woo-bargain-pro' ), 'type' => 'checkbox', 'description' => __( 'در حذف افزونه، داده‌های ثبت‌شده هم حذف شوند.', 'woo-bargain-pro' ) ),
			'custom_css'            => array( 'label' => __( 'CSS سفارشی', 'woo-bargain-pro' ), 'type' => 'textarea', 'description' => __( 'برای شخصی‌سازی بیشتر ظاهر فرم، CSS دلخواه وارد کنید.', 'woo-bargain-pro' ) ),
		);
	}

	protected function sanitize_setting_value( string $field, array $args ) {
		if ( 'checkbox' === $args['type'] ) {
			return isset( $_POST[ $field ] ) ? 'yes' : 'no';
		}

		$value = isset( $_POST[ $field ] ) ? wp_unslash( $_POST[ $field ] ) : '';

		if ( 'textarea' === $args['type'] ) {
			return wp_strip_all_tags( (string) $value );
		}

		if ( 'number' === $args['type'] ) {
			return is_numeric( $value ) ? (string) $value : '';
		}

		if ( 'color' === $args['type'] ) {
			return sanitize_hex_color( (string) $value ) ?: '';
		}

		if ( 'url' === $args['type'] ) {
			return esc_url_raw( (string) $value );
		}

		if ( 'select' === $args['type'] ) {
			$allowed = array_keys( $args['options'] ?? array() );
			return in_array( (string) $value, $allowed, true ) ? (string) $value : ( $allowed[0] ?? '' );
		}

		return sanitize_text_field( (string) $value );
	}

	protected function render_setting_field( string $field, array $args, $value ): void {
		$type = $args['type'];
		?>
		<div class="wbp-setting-card type-<?php echo esc_attr( $type ); ?>">
			<div class="wbp-setting-copy">
				<label for="<?php echo esc_attr( $field ); ?>"><?php echo esc_html( $args['label'] ); ?></label>
				<?php if ( ! empty( $args['description'] ) ) : ?>
					<p><?php echo esc_html( $args['description'] ); ?></p>
				<?php endif; ?>
			</div>
			<div class="wbp-setting-control">
				<?php if ( 'checkbox' === $type ) : ?>
					<label class="wbp-ios-switch">
						<input id="<?php echo esc_attr( $field ); ?>" type="checkbox" name="<?php echo esc_attr( $field ); ?>" value="yes" <?php checked( $value, 'yes' ); ?>>
						<span class="wbp-ios-slider" aria-hidden="true"></span>
					</label>
				<?php elseif ( 'textarea' === $type ) : ?>
					<textarea id="<?php echo esc_attr( $field ); ?>" name="<?php echo esc_attr( $field ); ?>" rows="5"><?php echo esc_textarea( (string) $value ); ?></textarea>
				<?php elseif ( 'select' === $type ) : ?>
					<select id="<?php echo esc_attr( $field ); ?>" name="<?php echo esc_attr( $field ); ?>">
						<?php foreach ( $args['options'] as $option_value => $option_label ) : ?>
							<option value="<?php echo esc_attr( $option_value ); ?>" <?php selected( $value, $option_value ); ?>><?php echo esc_html( $option_label ); ?></option>
						<?php endforeach; ?>
					</select>
				<?php else : ?>
					<input id="<?php echo esc_attr( $field ); ?>" type="<?php echo esc_attr( $type ); ?>" name="<?php echo esc_attr( $field ); ?>" value="<?php echo esc_attr( (string) $value ); ?>">
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	protected function average_discount( array $offers ): float {
		if ( empty( $offers ) ) {
			return 0;
		}
		$total = 0;
		foreach ( $offers as $offer ) {
			if ( $offer->original_price > 0 ) {
				$total += ( ( $offer->original_price - $offer->offered_price ) / $offer->original_price ) * 100;
			}
		}
		return round( $total / count( $offers ), 1 );
	}
}
