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
	 * کش سلامت ساختار جدول ممیزی.
	 *
	 * @var bool|null
	 */
	private static $audit_schema_cache = null;

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

		/*
		 * هر سه جدول بررسی می‌شوند، نه فقط جدول اشخاص.
		 *
		 * پیش‌تر فقط `persons` نگاه می‌شد. اگر جدول ممیزی ناقص یا غایب
		 * بود (مثلاً ساختش نصفه قطع شده بود)، این تابع «همه چیز خوب است»
		 * می‌گفت و:
		 *
		 *   - `Audit_Service::log()` بی‌صدا `false` برمی‌گرداند، پس هیچ
		 *     تغییری ثبت نمی‌شد؛
		 *   - گزارش سلامت جدول ممیزی را `ok` نشان می‌داد؛
		 *   - `maybe_upgrade()` ترمیم را شروع نمی‌کرد.
		 *
		 * یعنی کاربر می‌توانست ماه‌ها کار کند و هیچ سابقه‌ای نداشته باشد،
		 * در حالی که پنل می‌گفت سالم است.
		 */
		$suppress = $wpdb->suppress_errors( true );

		self::$tables_exist_cache = true;

		foreach ( self::all_tables() as $table ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- نام جدول از منبع داخلی و امن است.
			$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );

			if ( $found !== $table ) {
				self::$tables_exist_cache = false;
				break;
			}
		}

		$wpdb->suppress_errors( $suppress );

		return self::$tables_exist_cache;
	}

	/**
	 * نام همه جدول‌های افزونه.
	 *
	 * @return array<int, string>
	 */
	public static function all_tables() {
		return array(
			self::persons_table(),
			self::transactions_table(),
			self::audit_table(),
		);
	}

	/**
	 * بررسی ساختار یک جدول: آیا ستون‌های لازم را دارد؟
	 *
	 * `tables_exist()` فقط وجود جدول را می‌سنجد. جدولی که ساختش نصفه
	 * ماند (مثلاً درخواست وسط کار قطع شد) وجود دارد ولی ستون‌هایش را
	 * ندارد؛ کوئری‌های بعدی خطا می‌دهند و ممیزی بی‌صدا گم می‌شود.
	 *
	 * @param string               $table   نام جدول.
	 * @param array<int, string>   $columns ستون‌های لازم.
	 * @return array<int, string> ستون‌های غایب (آرایه خالی یعنی سالم).
	 */
	public static function missing_columns( $table, array $columns ) {
		global $wpdb;

		$suppress = $wpdb->suppress_errors( true );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- نام جدول از منبع داخلی است.
		$rows = $wpdb->get_results( "SHOW COLUMNS FROM {$table}", ARRAY_A );

		$wpdb->suppress_errors( $suppress );

		if ( empty( $rows ) ) {
			return $columns;
		}

		$present = array();

		foreach ( $rows as $row ) {
			if ( isset( $row['Field'] ) ) {
				$present[ strtolower( $row['Field'] ) ] = true;
			}
		}

		$missing = array();

		foreach ( $columns as $column ) {
			if ( ! isset( $present[ strtolower( $column ) ] ) ) {
				$missing[] = $column;
			}
		}

		return $missing;
	}

	/**
	 * آیا ساختار جدول ممیزی کامل است؟
	 *
	 * نتیجه در همان درخواست کش می‌شود؛ `Audit_Service::log()` در هر
	 * عملیات نوشتن صدا زده می‌شود و نباید هر بار `SHOW COLUMNS` بزند.
	 *
	 * @return bool
	 */
	public static function audit_schema_is_intact() {
		if ( null !== self::$audit_schema_cache ) {
			return self::$audit_schema_cache;
		}

		$columns = self::missing_columns(
			self::audit_table(),
			array( 'id', 'object_type', 'object_id', 'action', 'changes', 'created_at' )
		);

		self::$audit_schema_cache = empty( $columns );

		return self::$audit_schema_cache;
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
		self::$audit_schema_cache = null;
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
