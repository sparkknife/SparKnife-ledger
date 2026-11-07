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
	 * آماده‌سازی یک سایت تازه در شبکه چندسایتی.
	 *
	 * @param WP_Site $site سایت جدید.
	 * @return void
	 */
	public static function initialize_site( $site ) {
		if ( ! is_multisite() || ! $site instanceof WP_Site ) {
			return;
		}

		switch_to_blog( (int) $site->blog_id );

		self::create_tables();
		Sparkknife_Ledger_Settings::install_defaults();
		Sparkknife_Ledger_Capabilities::add_capabilities();
		self::store_versions();

		restore_current_blog();
	}

	/**
	 * آماده‌سازی همه سایت‌های شبکه.
	 *
	 * هنگام فعال‌سازی شبکه‌ای فراخوانی می‌شود؛ وردپرس در آن حالت
	 * register_activation_hook را فقط یک‌بار و برای سایت جاری اجرا می‌کند.
	 *
	 * @param bool $network_wide فعال‌سازی برای کل شبکه.
	 * @return void
	 */
	public static function activate_network( $network_wide = false ) {
		if ( ! $network_wide || ! is_multisite() ) {
			// تک‌سایتی: register_activation_hook خودش نصب را انجام می‌دهد.
			return;
		}

		$sites = get_sites(
			array(
				'fields' => 'ids',
				'number' => 0,
			)
		);

		foreach ( $sites as $site_id ) {
			switch_to_blog( (int) $site_id );

			self::create_tables();
			Sparkknife_Ledger_Settings::install_defaults();
			Sparkknife_Ledger_Capabilities::add_capabilities();
			self::store_versions();

			restore_current_blog();
		}
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

		/*
		 * نسخه ذخیره‌شده کافی نیست؛ جداول هم باید واقعاً وجود داشته باشند.
		 *
		 * اگر مدیر جداول را دستی از phpMyAdmin پاک کند، یا مهاجرت نیمه‌کاره
		 * بماند، نسخه هنوز به‌روز است ولی جدولی در کار نیست. پیش‌تر این حالت
		 * هرگز ترمیم نمی‌شد و افزونه برای همیشه خراب می‌ماند.
		 */
		$tables_ok  = Sparkknife_Ledger_DB::tables_exist();
		$structure_ok = $tables_ok && self::structure_is_intact();

		if ( $installed === SPARKKNIFE_LEDGER_DB_VERSION && $structure_ok ) {
			return;
		}

		if ( $installed === SPARKKNIFE_LEDGER_DB_VERSION && ! $structure_ok ) {
			/*
			 * نسخه به‌روز است ولی جداول نیستند یا ناقص‌اند: بازسازی بدون
			 * مهاجرت. `dbDelta` جدول موجود را با ستون‌های غایب تکمیل
			 * می‌کند و داده‌های موجود را دست نمی‌زند.
			 */
			self::create_tables();
			Sparkknife_Ledger_DB::flush_table_cache();
			self::backfill_sort_keys();
			Sparkknife_Ledger_Settings::install_defaults();
			Sparkknife_Ledger_Capabilities::add_capabilities();

			return;
		}

		if ( ! $installed ) {
			/*
			 * افزونه بدون فعال‌سازی (مثلاً آپلود دستی) اجرا شده است.
			 *
			 * `backfill_sort_keys()` اینجا هم لازم است: ممکن است جداول از
			 * یک نصب قدیمی‌تر باقی مانده باشند و گزینهٔ نسخه پاک شده باشد
			 * (مثلاً با پاک‌سازی دستی wp_options، یا مهاجرت سایت بدون
			 * انتقال آپشن‌ها). در آن حالت ردیف‌های موجود `sort_key` ندارند
			 * و `ORDER BY sort_key ASC` آن‌ها را با ترتیب دلبخواه — عملاً
			 * ترتیب درج — برمی‌گرداند. فهرست اشخاص بی‌ترتیب می‌شد و
			 * کاربر آن را «الفبای خراب» می‌دید.
			 *
			 * این متد ایدمپوتنت است و فقط ردیف‌های خالی را پر می‌کند.
			 */
			self::create_tables();
			Sparkknife_Ledger_Settings::install_defaults();
			Sparkknife_Ledger_Capabilities::add_capabilities();
			self::backfill_sort_keys();
		} else {
			self::create_tables();
			Sparkknife_Ledger_DB::flush_table_cache();
			self::run_migrations( $installed );
		}

		self::store_versions();
	}

	/**
	 * آیا ساختار همه جدول‌ها کامل است؟
	 *
	 * @return bool
	 */
	private static function structure_is_intact() {
		$required = array(
			Sparkknife_Ledger_DB::persons_table()      => array( 'id', 'name', 'phone', 'status', 'sort_key', 'deleted_at', 'created_at', 'updated_at' ),
			Sparkknife_Ledger_DB::transactions_table() => array( 'id', 'reference', 'person_id', 'type', 'amount', 'txn_date', 'status', 'created_at' ),
			Sparkknife_Ledger_DB::audit_table()        => array( 'id', 'object_type', 'object_id', 'action', 'changes', 'created_at' ),
		);

		foreach ( $required as $table => $columns ) {
			$missing = Sparkknife_Ledger_DB::missing_columns( $table, $columns );

			if ( ! empty( $missing ) ) {
				return false;
			}
		}

		return true;
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
		 * - sort_key: کلید مرتب‌سازی الفبای فارسی (`Sanitizer::persian_sort_key`).
		 *   `ORDER BY name` ترتیب بایتی UTF-8 می‌دهد و `پ چ ک گ ی` را به انتهای
		 *   فهرست می‌پراند (باگ ۵۹). کلید عمداً `text` است و نه `varchar(190)`:
		 *   هر حرف فارسی ۶ نویسه کلید می‌گیرد و نام ۱۹۰ نویسه‌ای ۱۱۴۰ نویسه
		 *   کلید می‌دهد؛ ستون کوتاه کلید را می‌بُرید و ترتیب نام‌های بلند را
		 *   دوباره خراب می‌کرد.
		 * - deleted_at: حذف نرم؛ ردیف‌های حذف‌شده در محاسبات نمی‌آیند اما اطلاعات حفظ می‌شود.
		 */
		$sql_persons = "CREATE TABLE {$persons} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(190) NOT NULL,
			phone varchar(40) DEFAULT NULL,
			description text NULL,
			status varchar(20) NOT NULL DEFAULT 'active',
			created_by bigint(20) unsigned NOT NULL DEFAULT 0,
			sort_key text NULL,
			created_at datetime DEFAULT NULL,
			updated_at datetime DEFAULT NULL,
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
			txn_date date DEFAULT NULL,
			description text NULL,
			status varchar(20) NOT NULL DEFAULT 'posted',
			created_by bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime DEFAULT NULL,
			updated_by bigint(20) unsigned NOT NULL DEFAULT 0,
			updated_at datetime DEFAULT NULL,
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
			user_login varchar(60) DEFAULT NULL,
			hash varchar(64) DEFAULT NULL,
			created_at datetime DEFAULT NULL,
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
		/*
		 * ستون `sort_key` در نسخهٔ ۱.۱.۰ اضافه شد. `dbDelta` ستون را با
		 * پیش‌فرض '' می‌سازد و ردیف‌های موجود مقدار درست ندارند؛ بدون
		 * پر کردن، همهٔ اشخاص کهنه با کلید خالی در ابتدای فهرست جمع
		 * می‌شدند و ترتیب نام باز هم غلط می‌ماند.
		 */
		self::backfill_sort_keys();

		/**
		 * امکان افزودن مهاجرت توسط توسعه‌دهندگان.
		 *
		 * @param string $from_version نسخه قبلی.
		 */
		do_action( 'sparkknife_ledger_migrate', $from_version );
	}

	/**
	 * پر کردن ستون `sort_key` برای ردیف‌هایی که خالی‌اند.
	 *
	 * فقط ردیف‌های خالی به‌روزرسانی می‌شوند: فهرست اشخاص یک دفتر
	 * خانگی است و بازنویسی کل جدول در هر ارتقا لازم نیست. اگر روزی
	 * الگوریتم کلید عوض شود، نسخهٔ اسکیما باید بالا برود و همین‌جا
	 * شرط بازنویسی گسترده‌تر شود.
	 *
	 * @return int تعداد ردیف‌های به‌روزرسانی‌شده.
	 */
	public static function backfill_sort_keys() {
		global $wpdb;

		$table = Sparkknife_Ledger_DB::persons_table();

		$suppress = $wpdb->suppress_errors( true );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results( "SELECT id, name FROM {$table} WHERE sort_key IS NULL OR sort_key = ''" );

		$wpdb->suppress_errors( $suppress );

		if ( empty( $rows ) ) {
			return 0;
		}

		$updated = 0;

		foreach ( $rows as $row ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$result = $wpdb->update(
				$table,
				array( 'sort_key' => Sparkknife_Ledger_Sanitizer::persian_sort_key( (string) $row->name ) ),
				array( 'id' => (int) $row->id ),
				array( '%s' ),
				array( '%d' )
			);

			if ( false !== $result ) {
				$updated++;
			}
		}

		return $updated;
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

		/*
		 * هر دو گزینهٔ نسخه پاک می‌شوند، نه فقط نسخهٔ دیتابیس.
		 *
		 * پیش‌تر `VERSION_OPTION` می‌ماند. `maybe_upgrade()` با
		 * مقایسهٔ همان گزینه تصمیم می‌گیرد که «مهاجرت لازم نیست» —
		 * پس بعد از پاک‌سازی، جداول رفته بودند ولی افزونه فکر
		 * می‌کرد همه چیز به‌روز است. نتیجه: پنل روی جداول ناموجود
		 * خطا می‌داد و هیچ خودترمیمی رخ نمی‌داد. کاربر فقط با حذف و
		 * نصب مجدد دستی نجات می‌یافت.
		 *
		 * این دقیقاً بار اول هم دیده نشد چون آزمون پس از پاک‌سازی
		 * فراخوانی `activate()` را امتحان می‌کرد، نه مسیر واقعی
		 * `maybe_upgrade()` در بارگذاری بعدی.
		 */
		delete_option( self::DB_VERSION_OPTION );
		delete_option( self::VERSION_OPTION );
		delete_option( Sparkknife_Ledger_Settings::OPTION_KEY );

		/*
		 * تنظیمات عمداً بازنصب **نمی‌شوند**.
		 *
		 * «پاک‌سازی داده» یعنی حذف، نه بازنشانی. کاربری که این دکمه را
		 * می‌زند انتظار یک سایت خالی دارد — همان حالتی که پس از
		 * نصب تازه می‌بیند: ویزارد راه‌اندازی باز می‌شود و می‌پرسد
		 * واحد پول و تقویم چه باشد.
		 *
		 * اگر این‌جا `install_defaults()` صدا زده شود، تنظیمات قبلی
		 * (که ممکن بود ریال و میلادی باشد) بی‌صدا با پیش‌فرض
		 * (تومان و شمسی) جایگزین می‌شود و کاربر متوجه نمی‌شود.
		 *
		 * `maybe_upgrade()` در بارگذاری بعدی، مثل نصب تازه، جداول و
		 * تنظیمات را می‌سازد.
		 */
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

		/*
		 * هر جدول با ستون‌های کلیدی‌اش سنجیده می‌شود.
		 *
		 * پیش‌تر فقط وجود جدول بررسی می‌شد. جدولی که ساختش نصفه ماند
		 * (فقط ستون id) وجود داشت، پس گزارش «ok» می‌داد در حالی که
		 * ثبت در آن شکست می‌خورد و ممیزی بی‌صدا گم می‌شد.
		 */
		$tables = array(
			array(
				'label'   => __( 'اشخاص', 'sparkknife-ledger' ),
				'table'   => Sparkknife_Ledger_DB::persons_table(),
				'columns' => array( 'id', 'name', 'phone', 'status', 'deleted_at', 'created_at', 'updated_at' ),
			),
			array(
				'label'   => __( 'تراکنش‌ها', 'sparkknife-ledger' ),
				'table'   => Sparkknife_Ledger_DB::transactions_table(),
				'columns' => array( 'id', 'reference', 'person_id', 'type', 'amount', 'txn_date', 'status', 'created_at' ),
			),
			array(
				'label'   => __( 'تاریخچه تغییرات', 'sparkknife-ledger' ),
				'table'   => Sparkknife_Ledger_DB::audit_table(),
				'columns' => array( 'id', 'object_type', 'object_id', 'action', 'changes', 'created_at' ),
			),
		);

		$report = array();

		foreach ( $tables as $definition ) {
			$label = $definition['label'];
			$table = $definition['table'];

			$suppress = $wpdb->suppress_errors( true );
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- نام جدول داخلی و آماده‌شده.
			$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;
			$wpdb->suppress_errors( $suppress );

			$missing_columns = $exists ? Sparkknife_Ledger_DB::missing_columns( $table, $definition['columns'] ) : array();

			if ( ! $exists ) {
				$status = 'missing';
			} elseif ( ! empty( $missing_columns ) ) {
				$status = 'broken';
			} else {
				$status = 'ok';
			}

			$report[] = array(
				'label'           => $label,
				'table'           => $table,
				'status'          => $status,
				'missing_columns' => $missing_columns,
				'rows'            => 'ok' === $status ? (string) Sparkknife_Ledger_DB::count_rows( $table ) : '—',
			);
		}

		return $report;
	}
}
