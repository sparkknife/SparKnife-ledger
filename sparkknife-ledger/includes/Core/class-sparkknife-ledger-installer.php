<?php
/**
 * نصب، ارتقای اسکیما و پاک‌سازی کامل افزونه.
 *
 * @package SparKnife_Ledger
 */

defined( 'ABSPATH' ) || exit;

/**
 * مدیریت چرخه حیات جداول و تنظیمات.
 */
class Sparkknife_Ledger_Installer {

	/**
	 * کلید آپشن نسخه اسکیما.
	 */
	const DB_VERSION_OPTION = 'sparkknife_ledger_db_version';

	/**
	 * کلید آپشن نسخه افزونه.
	 */
	const VERSION_OPTION = 'sparkknife_ledger_version';

	/**
	 * ذخیره یک گزینه با اطمینان کامل از انجام نوشتن.
	 *
	 * چرا از add_option/update_option استفاده نمی‌شود؟
	 * در جریان «حذف افزونه → فعال‌سازی مجدد» و «پاک‌سازی داده‌ها → نصب
	 * دوباره»، همین گزینه در همان درخواست با delete_option() حذف شده است.
	 * وردپرس آن را در کش منفی (notoptions) نگه می‌دارد و add_option()
	 * بدون انجام INSERT مقدار true برمی‌گرداند.
	 *
	 * نکته مهم دیگر: پس از dbDelta، مقدار rows_affected در $wpdb کهنه
	 * می‌ماند و $wpdb->query() نتیجه نادرست گزارش می‌کند. بنابراین
	 * صحت نوشتن فقط با خواندن مجدد از دیتابیس تأیید می‌شود.
	 *
	 * @param string $key   نام گزینه.
	 * @param mixed  $value مقدار (اسکالر یا آرایه؛ با maybe_serialize).
	 * @return bool
	 */
	private static function store_option( $key, $value ) {
		/*
		 * کش منفی و تک‌گزینه‌ای باید پاک شود، وگرنه get_option() در همین
		 * درخواست ممکن است مقدار قدیمی (یا false) را برگرداند.
		 */
		$notoptions = wp_cache_get( 'notoptions', 'options' );

		if ( ! is_array( $notoptions ) ) {
			$notoptions = array();
		}

		unset( $notoptions[ $key ] );
		wp_cache_set( 'notoptions', $notoptions, 'options' );
		wp_cache_delete( $key, 'options' );

		/*
		 * مقدار خام (نه سریال‌شده) تحویل داده می‌شود؛ update_option خودش
		 * maybe_serialize را اعمال می‌کند و سریال‌سازی دستی این‌جا باعث
		 * سریال‌سازی دوباره و بازگشت رشته به‌جای آرایه می‌شد.
		 */
		update_option( $key, $value, false );

		// تأیید با خواندن مجدد از دیتابیس (بدون کش).
		return maybe_serialize( $value ) === self::read_option_value( $key );
	}

	/**
	 * خواندن مقدار خام گزینه از دیتابیس (بدون هیچ کشی).
	 *
	 * @param string $key نام گزینه.
	 * @return string|null مقدار سریال‌شده یا null.
	 */
	private static function read_option_value( $key ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$value = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1", $key ) );

		return null === $value ? null : (string) $value;
	}

	/**
	 * درج اطلاعات نسخه (اسکیما و افزونه) در دیتابیس.
	 *
	 * @return void
	 */
	private static function store_versions() {
		self::store_option( self::DB_VERSION_OPTION, SPARKKNIFE_LEDGER_DB_VERSION );
		self::store_option( self::VERSION_OPTION, SPARKKNIFE_LEDGER_VERSION );
	}

	/**
	 * اجرا در زمان فعال‌سازی افزونه.
	 *
	 * @return void
	 */
	public static function activate() {
		self::create_tables();
		Sparkknife_Ledger_Settings::install_defaults();
		Sparkknife_Ledger_Capabilities::add_capabilities();
		self::store_versions();
		do_action( 'sparkknife_ledger_activated' );
	}

	/**
	 * اجرا در زمان غیرفعال‌سازی.
	 *
	 * داده‌ها هرگز در زمان غیرفعال‌سازی حذف نمی‌شوند.
	 *
	 * @return void
	 */
	public static function deactivate() {
		do_action( 'sparkknife_ledger_deactivated' );
	}

	/**
	 * بررسی نیاز به ارتقای اسکیما در هر بارگذاری.
	 *
	 * @return void
	 */
	public static function maybe_upgrade() {
		if ( ! is_admin() && ! defined( 'WP_CLI' ) ) {
			return;
		}

		$installed = get_option( self::DB_VERSION_OPTION, '' );

		if ( $installed === SPARKKNIFE_LEDGER_DB_VERSION ) {
			return;
		}

		if ( ! $installed ) {
			// افزونه بدون فعال‌سازی (مثلاً آپلود دستی) اجرا شده است.
			self::create_tables();
			Sparkknife_Ledger_Settings::install_defaults();
			Sparkknife_Ledger_Capabilities::add_capabilities();
		} else {
			self::create_tables();
			self::run_migrations( $installed );
		}

		self::store_versions();
	}

	/**
	 * ساخت یا به‌روزرسانی جداول با dbDelta.
	 *
	 * @return void
	 */
	public static function create_tables() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$persons         = Sparkknife_Ledger_DB::persons_table();
		$transactions    = Sparkknife_Ledger_DB::transactions_table();
		$audit           = Sparkknife_Ledger_DB::audit_table();

		/*
		 * جدول اشخاص
		 * - status: active | inactive
		 * - deleted_at: حذف نرم؛ ردیف‌های حذف‌شده در محاسبات نمی‌آیند اما اطلاعات حفظ می‌شود.
		 */
		$sql_persons = "CREATE TABLE {$persons} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(190) NOT NULL,
			phone varchar(40) DEFAULT NULL,
			description text NULL,
			status varchar(20) NOT NULL DEFAULT 'active',
			created_by bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			updated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			deleted_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY idx_status (status),
			KEY idx_name (name),
			KEY idx_deleted_at (deleted_at),
			KEY idx_created_at (created_at)
		) {$charset_collate};";

		/*
		 * جدول تراکنش‌ها
		 * - amount: مبلغ مقیاس‌شده (مبلغ × 10^AMOUNT_SCALE) به‌صورت BIGINT
		 * - type: income | expense
		 * - status: posted | void
		 * - void_*: اطلاعات ابطال (باطل‌شده‌ها در محاسبات لحاظ نمی‌شوند)
		 */
		$sql_transactions = "CREATE TABLE {$transactions} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			reference varchar(40) NOT NULL,
			person_id bigint(20) unsigned NOT NULL DEFAULT 0,
			type varchar(20) NOT NULL DEFAULT 'income',
			amount bigint(20) NOT NULL DEFAULT 0,
			currency varchar(20) NOT NULL DEFAULT '',
			txn_date date NOT NULL DEFAULT '0000-00-00',
			description text NULL,
			status varchar(20) NOT NULL DEFAULT 'posted',
			created_by bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			updated_by bigint(20) unsigned NOT NULL DEFAULT 0,
			updated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			void_reason text NULL,
			voided_by bigint(20) unsigned NOT NULL DEFAULT 0,
			voided_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY uq_reference (reference),
			KEY idx_person_status (person_id,status),
			KEY idx_person_date (person_id,txn_date),
			KEY idx_type_status (type,status),
			KEY idx_txn_date (txn_date),
			KEY idx_created_at (created_at),
			KEY idx_status (status)
		) {$charset_collate};";

		/*
		 * جدول تاریخچه تغییرات (Audit Log)
		 * - object_type: person | transaction | settings
		 * - action: create | update | void | delete | restore | settings_update | export
		 * - changes: JSON از تغییرات فیلدها (قبل/بعد)
		 * - hash: هش زنجیره‌ای برای تشخیص دستکاری سوابق
		 */
		$sql_audit = "CREATE TABLE {$audit} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			object_type varchar(30) NOT NULL DEFAULT '',
			object_id bigint(20) unsigned NOT NULL DEFAULT 0,
			action varchar(40) NOT NULL DEFAULT '',
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			changes longtext NULL,
			summary text NULL,
			ip_address varchar(90) DEFAULT NULL,
			user_agent varchar(255) DEFAULT NULL,
			hash varchar(64) DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			KEY idx_object (object_type,object_id),
			KEY idx_user (user_id),
			KEY idx_action (action),
			KEY idx_created_at (created_at)
		) {$charset_collate};";

		dbDelta( $sql_persons );
		dbDelta( $sql_transactions );
		dbDelta( $sql_audit );

		// بررسی وجود جداول باید دوباره انجام شود.
		Sparkknife_Ledger_DB::flush_table_cache();

		unset( $sql_persons, $sql_transactions, $sql_audit );

		/*
		 * ثبت نسخه‌ها همین‌جا انجام می‌شود تا اگر create_tables() مستقیماً
		 * صدا زده شود (مهاجرت، تست، اسکریپت CLI) وضعیت نصب همگام بماند.
		 * store_versions ارزان و idempotent است.
		 */
		self::store_versions();
	}

	/**
	 * مهاجرت‌های نسخه‌ای.
	 *
	 * @param string $from_version نسخه اسکیمای نصب‌شده.
	 * @return void
	 */
	private static function run_migrations( $from_version ) {
		/**
		 * امکان افزودن مهاجرت توسط توسعه‌دهندگان.
		 *
		 * @param string $from_version نسخه قبلی.
		 */
		do_action( 'sparkknife_ledger_migrate', $from_version );
	}

	/**
	 * حذف کامل داده‌ها؛ فقط با اجازه صریح مدیر از صفحه تنظیمات.
	 *
	 * این متد تنها از مسیر «تنظیمات → پاک‌سازی داده‌ها» با تأیید دومرحله‌ای
	 * و متن تأیید دستی فراخوانی می‌شود.
	 *
	 * @return void
	 */
	public static function purge_all_data() {
		global $wpdb;

		$audit = Sparkknife_Ledger_DB::audit_table();

		// ثبت رویداد پاک‌سازی پیش از حذف جداول.
		Sparkknife_Ledger_Audit_Service::instance()->log(
			'settings',
			0,
			'purge_data',
			array( 'note' => __( 'حذف کامل داده‌های مالی به درخواست صریح مدیر', 'sparkknife-ledger' ) )
		);

		foreach ( array( Sparkknife_Ledger_DB::transactions_table(), Sparkknife_Ledger_DB::persons_table(), $audit ) as $table ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching -- نام جدول داخلی.
			$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
		}

		delete_option( self::DB_VERSION_OPTION );
		delete_option( Sparkknife_Ledger_Settings::OPTION_KEY );
		Sparkknife_Ledger_DB::flush_table_cache();

		do_action( 'sparkknife_ledger_data_purged' );
	}

	/**
	 * برچسب خوانا برای وضعیت اسکیما (نمایش در تنظیمات سلامت سیستم).
	 *
	 * @return array<int, array<string, string>>
	 */
	public static function get_health_report() {
		global $wpdb;

		$tables = array(
			__( 'اشخاص', 'sparkknife-ledger' )       => Sparkknife_Ledger_DB::persons_table(),
			__( 'تراکنش‌ها', 'sparkknife-ledger' )   => Sparkknife_Ledger_DB::transactions_table(),
			__( 'تاریخچه تغییرات', 'sparkknife-ledger' ) => Sparkknife_Ledger_DB::audit_table(),
		);

		$report = array();

		foreach ( $tables as $label => $table ) {
			$suppress = $wpdb->suppress_errors( true );
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- نام جدول داخلی و آماده‌شده.
			$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;
			$wpdb->suppress_errors( $suppress );

			$report[] = array(
				'label'  => $label,
				'table'  => $table,
				'status' => $exists ? 'ok' : 'missing',
				'rows'   => $exists ? (string) Sparkknife_Ledger_DB::count_rows( $table ) : '—',
			);
		}

		return $report;
	}
}
