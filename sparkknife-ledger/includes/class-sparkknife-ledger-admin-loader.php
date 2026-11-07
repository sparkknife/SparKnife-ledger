<?php
/**
 * راه‌انداز بخش مدیریت: منوها، دارایی‌ها و مسیریابی کنترلرها.
 *
 * @author  SparKnife <155084046+sparkknife@users.noreply.github.com>
 *
 * @package SparKnife_Ledger
 */

defined( 'ABSPATH' ) || exit;

/**
 * ثبت منوها و دارایی‌های پنل.
 */
class Sparkknife_Ledger_Admin_Loader {

	/**
	 * نمونه یکتا.
	 *
	 * @var Sparkknife_Ledger_Admin_Loader|null
	 */
	private static $instance = null;

	/**
	 * اسلاگ صفحه اصلی.
	 */
	const MAIN_PAGE = 'sparkknife-ledger';

	/**
	 * دریافت نمونه.
	 *
	 * @return Sparkknife_Ledger_Admin_Loader
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * راه‌اندازی.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'register_menus' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_notices', array( $this, 'render_admin_notices' ) );
		add_action( 'admin_head', array( $this, 'render_admin_head' ) );
		add_filter( 'plugin_action_links_' . SPARKKNIFE_LEDGER_BASENAME, array( $this, 'plugin_action_links' ) );
		add_action( 'admin_init', array( $this, 'maybe_redirect_after_activation' ) );

		Sparkknife_Ledger_Admin_Controller::instance()->init();
	}

	/**
	 * ثبت منوهای پنل.
	 *
	 * @return void
	 */
	public function register_menus() {
		// منوی اصلی: داشبورد.
		add_menu_page(
			__( 'SparKnife Ledger', 'sparkknife-ledger' ),
			__( 'دفتر حساب', 'sparkknife-ledger' ),
			Sparkknife_Ledger_Capabilities::normalize( 'reports' ),
			self::MAIN_PAGE,
			array( 'Sparkknife_Ledger_Dashboard_Controller', 'render' ),
			'dashicons-chart-area',
			26
		);

		add_submenu_page(
			self::MAIN_PAGE,
			__( 'داشبورد', 'sparkknife-ledger' ),
			__( 'داشبورد', 'sparkknife-ledger' ),
			Sparkknife_Ledger_Capabilities::normalize( 'reports' ),
			self::MAIN_PAGE,
			array( 'Sparkknife_Ledger_Dashboard_Controller', 'render' )
		);

		add_submenu_page(
			self::MAIN_PAGE,
			__( 'اشخاص', 'sparkknife-ledger' ),
			__( 'اشخاص', 'sparkknife-ledger' ),
			Sparkknife_Ledger_Capabilities::normalize( 'manage_persons' ),
			'sparkknife-ledger-persons',
			array( 'Sparkknife_Ledger_Persons_Controller', 'render' )
		);

		add_submenu_page(
			self::MAIN_PAGE,
			__( 'تراکنش‌ها', 'sparkknife-ledger' ),
			__( 'تراکنش‌ها', 'sparkknife-ledger' ),
			Sparkknife_Ledger_Capabilities::normalize( 'manage_transactions' ),
			'sparkknife-ledger-transactions',
			array( 'Sparkknife_Ledger_Transactions_Controller', 'render' )
		);

		add_submenu_page(
			self::MAIN_PAGE,
			__( 'گزارش فردی', 'sparkknife-ledger' ),
			__( 'گزارش فردی', 'sparkknife-ledger' ),
			Sparkknife_Ledger_Capabilities::normalize( 'reports' ),
			'sparkknife-ledger-reports',
			array( 'Sparkknife_Ledger_Reports_Controller', 'render_person' )
		);

		add_submenu_page(
			self::MAIN_PAGE,
			__( 'گزارش تجمیعی', 'sparkknife-ledger' ),
			__( 'گزارش تجمیعی', 'sparkknife-ledger' ),
			Sparkknife_Ledger_Capabilities::normalize( 'reports' ),
			'sparkknife-ledger-summary',
			array( 'Sparkknife_Ledger_Reports_Controller', 'render_summary' )
		);

		add_submenu_page(
			self::MAIN_PAGE,
			__( 'تاریخچه تغییرات', 'sparkknife-ledger' ),
			__( 'تاریخچه تغییرات', 'sparkknife-ledger' ),
			Sparkknife_Ledger_Capabilities::normalize( 'view_audit' ),
			'sparkknife-ledger-audit',
			array( 'Sparkknife_Ledger_Audit_Controller', 'render' )
		);

		add_submenu_page(
			self::MAIN_PAGE,
			__( 'تنظیمات', 'sparkknife-ledger' ),
			__( 'تنظیمات', 'sparkknife-ledger' ),
			Sparkknife_Ledger_Capabilities::normalize( 'manage_settings' ),
			'sparkknife-ledger-settings',
			array( 'Sparkknife_Ledger_Settings_Controller', 'render' )
		);

		/*
		 * صفحهٔ ویزارد راه‌اندازی — از منو مخفی است.
		 *
		 * `null` به‌عنوان تابع منو یعنی صفحه ثبت می‌شود ولی در نوار
		 * کناری دیده نمی‌شود؛ کاربر فقط با هدایت خودکار یا نشانی
		 * مستقیم به آن می‌رسد.
		 */
		add_submenu_page(
			null,
			__( 'راه‌اندازی', 'sparkknife-ledger' ),
			__( 'راه‌اندازی', 'sparkknife-ledger' ),
			'manage_options',
			Sparkknife_Ledger_Onboarding_Controller::PAGE,
			array( 'Sparkknife_Ledger_Onboarding_Controller', 'render' )
		);

		/*
		 * صفحهٔ سلامت و امنیت.
		 *
		 * دسترسی `manage_options` است، نه `manage_settings` محلی، چون
		 * این صفحه نتیجهٔ بررسی‌های سطح سایت (HTTPS، نسخه PHP،
		 * دسترسی فایل) و وضعیت لایسنس را نشان می‌دهد — چیزی که فقط
		 * مدیر کل باید ببیند.
		 */
		add_submenu_page(
			self::MAIN_PAGE,
			__( 'سلامت و امنیت', 'sparkknife-ledger' ),
			__( 'سلامت و امنیت', 'sparkknife-ledger' ),
			'manage_options',
			'sparkknife-ledger-health',
			array( 'Sparkknife_Ledger_Health_Controller', 'render' )
		);
	}

	/**
	 * بارگذاری CSS و JS فقط در صفحات افزونه.
	 *
	 * @param string $hook_suffix پسوند صفحه.
	 * @return void
	 */
	public function enqueue_assets( $hook_suffix ) {
		if ( false === strpos( (string) $hook_suffix, 'sparkknife-ledger' ) ) {
			return;
		}

		wp_enqueue_style(
			'sparkknife-ledger-admin',
			SPARKKNIFE_LEDGER_URL . 'assets/css/admin.css',
			array(),
			SPARKKNIFE_LEDGER_VERSION
		);

		wp_enqueue_script(
			'sparkknife-ledger-admin',
			SPARKKNIFE_LEDGER_URL . 'assets/js/admin.js',
			/*
			 * بدون وابستگی. اسکریپت خودش نوشته شده و هیچ‌جا jQuery را صدا
			 * نمی‌زند؛ وابستگی اعلام‌شده فقط jQuery را در هر صفحهٔ پنل این
			 * افزونه بارگذاری می‌کرد.
			 */
			array(),
			SPARKKNIFE_LEDGER_VERSION,
			true
		);

		wp_localize_script(
			'sparkknife-ledger-admin',
			'sparkknifeLedger',
			array(
				/*
				 * `ajaxUrl`، `nonce` و `searchNoResult` پیش‌تر فرستاده
				 * می‌شدند ولی هیچ‌جا در جاوااسکریپت خوانده نمی‌شدند:
				 * افزونه هیچ endpoint‌ی در `wp_ajax_` ثبت نمی‌کند و جستجوی
				 * زنده عمداً حذف شده است. ساختن یک nonce معتبر در HTML هر
				 * صفحه، بی‌آنکه سروری آن را بپذیرد، فقط سطح حمله را باز
				 * می‌کند.
				 */
				'currency'      => Sparkknife_Ledger_Money::currency_symbol(),
				'separator'     => Sparkknife_Ledger_Money::thousands_separator(),
				'persianDigits' => Sparkknife_Ledger_Money::uses_persian_digits() ? 1 : 0,
				'decimals'      => Sparkknife_Ledger_Money::decimals(),
				'strings'       => array(
					'confirmVoid'    => __( 'آیا از ابطال این تراکنش اطمینان دارید؟ این عملیات قابل بازگشت نیست.', 'sparkknife-ledger' ),
					'confirmDelete'  => __( 'آیا از حذف این مورد اطمینان دارید؟', 'sparkknife-ledger' ),
					'confirmPurge'   => __( 'هشدار: تمام داده‌های مالی حذف خواهند شد. ادامه می‌دهید؟', 'sparkknife-ledger' ),
					'required'       => __( 'این فیلد الزامی است.', 'sparkknife-ledger' ),
					'invalidAmount'  => __( 'مبلغ وارد‌شده معتبر نیست.', 'sparkknife-ledger' ),
					'saved'          => __( 'ذخیره شد.', 'sparkknife-ledger' ),
				),
			)
		);
	}

	/**
	 * نمایش اعلان‌های ذخیره‌شده در transient.
	 *
	 * @return void
	 */
	public function render_admin_notices() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( ! $screen || false === strpos( (string) $screen->id, 'sparkknife-ledger' ) ) {
			return;
		}

		$user_id = get_current_user_id();
		$notice  = get_transient( 'sparkknife_ledger_notice_' . $user_id );

		if ( ! $notice || ! is_array( $notice ) ) {
			return;
		}

		delete_transient( 'sparkknife_ledger_notice_' . $user_id );

		$type = isset( $notice['type'] ) && in_array( $notice['type'], array( 'success', 'error', 'warning', 'info' ), true ) ? $notice['type'] : 'info';
		$text = isset( $notice['message'] ) ? (string) $notice['message'] : '';

		/*
		 * پیام خالی رندر نمی‌شود.
		 *
		 * redirect_back_with() عمداً اعلانی با پیام خالی ثبت می‌کند تا
		 * فقط کاربر به فرم بازگردانده شود؛ پیش‌تر این حالت یک جعبه
		 * خطای توخالی در بالای صفحه نمایش می‌داد.
		 */
		if ( '' === trim( $text ) ) {
			return;
		}

		printf(
			'<div class="notice notice-%1$s is-dismissible sparkknife-ledger-notice"><p>%2$s</p></div>',
			esc_attr( $type ),
			esc_html( $text )
		);
	}

	/**
	 * استایل‌های سربرگ برای پشتیبانی از RTL و متغیرهای پایه.
	 *
	 * @return void
	 */
	public function render_admin_head() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( ! $screen || false === strpos( (string) $screen->id, 'sparkknife-ledger' ) ) {
			return;
		}

		echo '<style id="sparkknife-ledger-vars">:root{--spk-brand:#0f766e;--spk-brand-dark:#115e59;--spk-ok:#15803d;--spk-bad:#b91c1c;--spk-warn:#b45309;}</style>';
	}

	/**
	 * افزودن لینک‌های سریع به فهرست افزونه‌ها.
	 *
	 * @param array<int, string> $links لینک‌ها.
	 * @return array<int, string>
	 */
	public function plugin_action_links( $links ) {
		$custom = array(
			'<a href="' . esc_url( admin_url( 'admin.php?page=sparkknife-ledger' ) ) . '">' . esc_html__( 'داشبورد', 'sparkknife-ledger' ) . '</a>',
			'<a href="' . esc_url( admin_url( 'admin.php?page=sparkknife-ledger-settings' ) ) . '">' . esc_html__( 'تنظیمات', 'sparkknife-ledger' ) . '</a>',
		);

		return array_merge( $custom, $links );
	}

	/**
	 * انتقال به داشبورد پس از فعال‌سازی.
	 *
	 * @return void
	 */
	public function maybe_redirect_after_activation() {
		if ( ! get_transient( 'sparkknife_ledger_activated_redirect' ) ) {
			return;
		}

		delete_transient( 'sparkknife_ledger_activated_redirect' );

		if ( wp_doing_ajax() || isset( $_GET['activate-multi'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- بررسی صرف.
			return;
		}

		if ( ! Sparkknife_Ledger_Capabilities::current_user_can( 'view_reports' ) ) {
			return;
		}

		/*
		 * اگر کاربر تازه‌کار است و هیچ داده‌ای ندارد، به ویزارد ببر؛
		 * وگرنه به داشبورد. کاربری که داده دارد نباید ویزارد ببیند.
		 */
		if ( Sparkknife_Ledger_Onboarding_Controller::needs_setup() ) {
			wp_safe_redirect( admin_url( 'admin.php?page=' . Sparkknife_Ledger_Onboarding_Controller::PAGE ) );
			exit;
		}

		wp_safe_redirect( admin_url( 'admin.php?page=sparkknife-ledger' ) );
		exit;
	}

	/**
	 * ثبت پیام برای درخواست بعدی کاربر جاری.
	 *
	 * @param string $type     نوع پیام.
	 * @param string $message  متن.
	 * @param array  $args     پارامترهای تغییر مسیر.
	 * @param string $redirect نشانی مقصد (در صورت خالی، همان صفحه).
	 * @return void
	 */
	public static function add_notice( $type, $message, $args = array(), $redirect = '' ) {
		$user_id = get_current_user_id();

		if ( $user_id ) {
			set_transient(
				'sparkknife_ledger_notice_' . $user_id,
				array(
					'type'    => $type,
					'message' => $message,
				),
				60
			);
		}

		if ( '' !== $redirect ) {
			wp_safe_redirect( $redirect );
			exit;
		}

		if ( ! empty( $args ) ) {
			wp_safe_redirect( add_query_arg( $args ) );
			exit;
		}
	}
}
