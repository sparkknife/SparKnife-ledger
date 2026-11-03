<?php
/**
 * لایه دسترسی به اطلاعات جداول و تراکنش دیتابیس.
 *
 * تنها این کلاس‌ها و Repositoryها با $wpdb کار می‌کنند.
 *
 * @package SparKnife_Ledger
 */

defined( 'ABSPATH' ) || exit;

/**
 * ابزارهای مشترک دیتابیس.
 */
class Sparkknife_Ledger_DB {

	/**
	 * نام کامل جدول اشخاص.
	 *
	 * @return string
	 */
	public static function persons_table() {
		global $wpdb;
		return $wpdb->prefix . SPARKKNIFE_LEDGER_TABLE_PREFIX . 'persons';
	}

	/**
	 * نام کامل جدول تراکنش‌ها.
	 *
	 * @return string
	 */
	public static function transactions_table() {
		global $wpdb;
		return $wpdb->prefix . SPARKKNIFE_LEDGER_TABLE_PREFIX . 'transactions';
	}

	/**
	 * نام کامل جدول تاریخچه تغییرات.
	 *
	 * @return string
	 */
	public static function audit_table() {
		global $wpdb;
		return $wpdb->prefix . SPARKKNIFE_LEDGER_TABLE_PREFIX . 'audit_log';
	}

	/**
	 * اجرای یک callback در تراکنش اتمیک دیتابیس.
	 *
	 * در صورت خطا، تمام تغییرات بازگردانی می‌شوند. اگر دیتابیس از تراکنش
	 * پشتیبانی نکند (مثلاً موتور MyISAM)، callback مستقیماً اجرا می‌شود تا
	 * عملیات دچار شکست غیرمنتظره نشود.
	 *
	 * @param callable $callback عملیات.
	 * @return mixed|WP_Error نتیجه callback یا خطای تراکنش.
	 */
	public static function transaction( $callback ) {
		global $wpdb;

		$started = $wpdb->query( 'START TRANSACTION' );

		if ( false === $started ) {
			// دیتابیس تراکنش را پشتیبانی نمی‌کند؛ اجرای مستقیم.
			return call_user_func( $callback );
		}

		try {
			$result = call_user_func( $callback );
		} catch ( Throwable $e ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error(
				'sparkknife_ledger_transaction_failed',
				__( 'خطای غیرمنتظره در ثبت اطلاعات رخ داد؛ هیچ تغییری ذخیره نشد.', 'sparkknife-ledger' ),
				array( 'detail' => $e->getMessage() )
			);
		}

		if ( is_wp_error( $result ) ) {
			$wpdb->query( 'ROLLBACK' );
			return $result;
		}

		if ( false === $wpdb->query( 'COMMIT' ) ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error(
				'sparkknife_ledger_commit_failed',
				__( 'ثبت نهایی اطلاعات با خطا مواجه شد؛ هیچ تغییری ذخیره نشد.', 'sparkknife-ledger' )
			);
		}

		return $result;
	}

	/**
	 * آیا جداول افزونه نصب شده‌اند؟
	 *
	 * @return bool
	 */
	/**
	 * وضعیت نصب جداول افزونه.
	 *
	 * نتیجه در حافظه کش می‌شود؛ پس از ساخت یا حذف جداول باید
	 * flush_table_cache() صدا زده شود.
	 *
	 * @var bool|null
	 */
	private static $tables_exist_cache = null;

	/**
	 * آیا جداول افزونه نصب شده‌اند؟
	 *
	 * @param bool $force_recheck نادیده‌گرفتن کش.
	 * @return bool
	 */
	public static function tables_exist( $force_recheck = false ) {
		global $wpdb;

		if ( ! $force_recheck && null !== self::$tables_exist_cache ) {
			return self::$tables_exist_cache;
		}

		$table    = self::persons_table();
		$suppress = $wpdb->suppress_errors( true );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- نام جدول از منبع داخلی و امن است.
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		$wpdb->suppress_errors( $suppress );

		self::$tables_exist_cache = ( $found === $table );

		return self::$tables_exist_cache;
	}

	/**
	 * باطل‌کردن کش وضعیت جداول.
	 *
	 * پس از ساخت یا حذف جداول (نصب، مهاجرت، پاک‌سازی داده‌ها، حذف افزونه)
	 * لازم است؛ در غیر این صورت داشبورد و گزارش‌ها تا پایان همان درخواست
	 * وضعیت کهنه می‌بینند.
	 *
	 * @return void
	 */
	public static function flush_table_cache() {
		self::$tables_exist_cache = null;
	}

	/**
	 * تعداد ردیف‌های یک جدول با شرط دلخواه.
	 *
	 * @param string $table جدول کامل (فقط از متدهای همین کلاس).
	 * @param string $where شرط آماده‌شده (شامل WHERE نیست).
	 * @param array  $args  مقادیر آماده‌سازی.
	 * @return int
	 */
	public static function count_rows( $table, $where = '1=1', $args = array() ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- نام جدول داخلی؛ کوئری آماده‌سازی شده است.
		$sql = "SELECT COUNT(*) FROM {$table} WHERE {$where}";

		if ( ! empty( $args ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- آرگومان‌ها به prepare سپرده می‌شوند.
			$sql = $wpdb->prepare( $sql, $args );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var( $sql );
	}
}
