<?php
/**
 * هسته راه‌اندازی افزونه.
 *
 * @author  SparKnife <155084046+sparkknife@users.noreply.github.com>
 *
 * @package SparKnife_Ledger
 */

defined( 'ABSPATH' ) || exit;

/**
 * کلاس اصلی افزونه (Singleton).
 *
 * سرویس‌ها به‌صورت تنبل (lazy) ساخته می‌شوند تا در هر درخواست فقط
 * بخش‌های مورد نیاز بارگذاری شوند.
 */
final class Sparkknife_Ledger_Plugin {

	/**
	 * نمونه یکتا.
	 *
	 * @var Sparkknife_Ledger_Plugin|null
	 */
	private static $instance = null;

	/**
	 * سرویس‌های ساخته‌شده.
	 *
	 * @var array<string, object>
	 */
	private $services = array();

	/**
	 * دریافت نمونه.
	 *
	 * @return Sparkknife_Ledger_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * سازنده خصوصی.
	 */
	private function __construct() {
		$this->boot();
	}

	/**
	 * راه‌اندازی هوک‌ها.
	 *
	 * @return void
	 */
	private function boot() {
		add_action( 'init', array( $this, 'load_textdomain' ), 1 );
		add_action( 'init', array( $this, 'maybe_log_export_request' ), 20 );

		/*
		 * چندسایتی: سایت تازه‌ساخته‌شده در شبکه، جداول و تنظیمات خودش را
		 * می‌گیرد. بدون این هوک، فعال‌سازی شبکه فقط سایت جاری را آماده
		 * می‌کرد و سایت‌های بعدی با خطای «جدول یافت نشد» روبه‌رو می‌شدند.
		 */
		if ( is_multisite() ) {
			add_action( 'wp_initialize_site', array( 'Sparkknife_Ledger_Installer', 'initialize_site' ), 100 );
		}

		if ( is_admin() ) {
			Sparkknife_Ledger_Admin_Loader::instance()->init();
		}

		/**
		 * پس از آماده‌سازی هسته افزونه.
		 *
		 * @param Sparkknife_Ledger_Plugin $plugin نمونه افزونه.
		 */
		do_action( 'sparkknife_ledger_loaded', $this );
	}

	/**
	 * بارگذاری فایل ترجمه.
	 *
	 * @return void
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'sparkknife-ledger', false, dirname( SPARKKNIFE_LEDGER_BASENAME ) . '/languages' );
	}

	/**
	 * دسترسی به سرویس‌ها به‌صورت تنبل.
	 *
	 * @param string $name نام سرویس.
	 * @return object|null
	 */
	public function service( $name ) {
		$name = strtolower( (string) $name );

		if ( isset( $this->services[ $name ] ) ) {
			return $this->services[ $name ];
		}

		$map = array(
			'persons'      => 'Sparkknife_Ledger_Person_Service',
			'transactions' => 'Sparkknife_Ledger_Transaction_Service',
			'ledger'       => 'Sparkknife_Ledger_Ledger_Service',
			'reports'      => 'Sparkknife_Ledger_Report_Service',
			'audit'        => 'Sparkknife_Ledger_Audit_Service',
			'export'       => 'Sparkknife_Ledger_Export_Service',
		);

		if ( ! isset( $map[ $name ] ) || ! class_exists( $map[ $name ] ) ) {
			return null;
		}

		$this->services[ $name ] = call_user_func( array( $map[ $name ], 'instance' ) );

		return $this->services[ $name ];
	}

	/**
	 * ثبت رویداد درخواست خروجی در تاریخچه (رویداد حساس).
	 *
	 * تنها زمانی که پارامتر export در صفحه افزونه وجود داشته باشد.
	 *
	 * @return void
	 */
	public function maybe_log_export_request() {
		if ( ! is_admin() || ! isset( $_GET['export'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- فقط ثبت لاگ، بدون تغییر داده.
			return;
		}
	}
}
