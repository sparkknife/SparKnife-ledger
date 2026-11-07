<?php
/**
 * حذف افزونه (Uninstall).
 *
 * سیاست داده‌ها:
 * - به‌صورت پیش‌فرض هیچ داده مالی حذف نمی‌شود. جداول اشخاص، تراکنش‌ها و
 *   تاریخچه تغییرات دست‌نخورده باقی می‌مانند تا حذف تصادفی افزونه باعث
 *   از دست رفتن سوابق مالی نشود.
 * - تنها در صورتی که مدیر کل، در «تنظیمات → داده‌ها» گزینه
 *   «حذف داده‌ها هنگام حذف افزونه» را فعال کرده باشد (یا فیلتر
 *   `sparkknife_ledger_uninstall_delete_data` مقدار true برگرداند)،
 *   جداول، تنظیمات و دسترسی‌ها پاک می‌شوند.
 * - همیشه گزینه‌های سبک (نسخه، وضعیت نصب) پاک می‌شوند؛ حذف آن‌ها داده
 *   مالی نیست و فقط باعث نصب تمیز در آینده می‌شود.
 *
 * @package SparKnife_Ledger
 */

// محافظت: این فایل فقط توسط خود وردپرس در فرآیند حذف افزونه اجرا می‌شود.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// بارگذاری هسته افزونه برای دسترسی به نام جداول و کلاس‌ها.
require_once __DIR__ . '/sparkknife-ledger.php';

/**
 * حذف داده‌ها در یک سایت.
 *
 * @return void
 */
function sparkknife_ledger_uninstall_site() {
	global $wpdb;

	$settings = Sparkknife_Ledger_Settings::all();

	$delete_requested = ! empty( $settings['delete_data_on_uninstall'] )
		|| 'yes' === get_option( 'sparkknife_ledger_delete_data_on_uninstall', 'no' );

	/**
	 * اجازه یا منع حذف داده‌ها در زمان حذف افزونه.
	 *
	 * @param bool  $delete_requested درخواست مدیر برای حذف داده‌ها.
	 * @param array $settings         تنظیمات فعلی افزونه.
	 */
	$delete_data = (bool) apply_filters( 'sparkknife_ledger_uninstall_delete_data', $delete_requested, $settings );

	if ( $delete_data ) {
		$tables = array(
			Sparkknife_Ledger_DB::audit_table(),
			Sparkknife_Ledger_DB::transactions_table(),
			Sparkknife_Ledger_DB::persons_table(),
		);

		foreach ( $tables as $table ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching -- نام جدول از افزونه و کنترل‌شده است.
			$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
		}

		Sparkknife_Ledger_DB::flush_table_cache();

		/*
		 * گزینه‌های تنظیمات و پیکربندی.
		 *
		 * فهرست باید کامل باشد. پیش‌تر فقط تنظیمات پاک می‌شد و سه
		 * گزینهٔ تازه‌تر جا می‌ماندند:
		 *
		 *   - `sparkknife_ledger_license` — کلید لایسنس خریدار. ماندنش
		 *     هم نشت اطلاعات است هم اگر سایت با دامنهٔ دیگری بازسازی
		 *     شود، می‌تواند بی‌دلیل معتبر به نظر برسد.
		 *   - `sparkknife_ledger_security_log` — رویدادها با IP و
		 *     شناسهٔ کاربر. این دادهٔ شخصی است و مدیر صریحاً حذف کامل
		 *     خواسته است.
		 *   - `sparkknife_ledger_onboarding_done` — بی‌خطر ولی نصب
		 *     مجدد را بی‌دلیل از ویزارد محروم می‌کند.
		 *
		 * قاعده: هر گزینه‌ای که افزونه می‌سازد باید این‌جا یا در
		 * پاک‌سازی الگویی پایین‌تر پوشش داده شود.
		 */
		delete_option( Sparkknife_Ledger_Settings::OPTION_KEY );
		delete_option( 'sparkknife_ledger_delete_data_on_uninstall' );
		delete_option( 'sparkknife_ledger_license' );
		delete_option( 'sparkknife_ledger_license_state' );
		delete_option( 'sparkknife_ledger_security_log' );
		delete_option( 'sparkknife_ledger_onboarding_done' );

		// دسترسی‌های سفارشی از همه نقش‌ها حذف می‌شود.
		if ( class_exists( 'Sparkknife_Ledger_Capabilities' ) ) {
			Sparkknife_Ledger_Capabilities::remove_capabilities();
		}

		// ترنزینت‌های گزارش/کش و محدودیت تلاش.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				$wpdb->esc_like( '_transient_sparkknife_ledger_' ) . '%',
				$wpdb->esc_like( '_transient_timeout_sparkknife_ledger_' ) . '%'
			)
		);

		/*
		 * تور نهایی: هر گزینهٔ افزونه که در فهرست بالا جا مانده باشد.
		 *
		 * فهرست دستی همیشه از کد عقب می‌ماند — همین باگ ۸۰ از همین
		 * جا آمد. این پرس‌وجو تضمین می‌کند که گزینهٔ فراموش‌شده‌ای
		 * باقی نماند، حتی اگر توسعه‌دهندهٔ بعدی فهرست را به‌روز نکند.
		 *
		 * `sparkknife_ledger_notice_*` هم عمداً پاک می‌شود: پیام‌های
		 * فلش کاربران دیگر بی‌فایده‌اند و حاوی متن خطا هستند.
		 */
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s",
				$wpdb->esc_like( 'sparkknife_ledger_' ) . '%',
				$wpdb->esc_like( '_transient_sparkknife_ledger' ) . '%',
				$wpdb->esc_like( '_transient_timeout_sparkknife_ledger' ) . '%',
				// ترنزینت‌های محدودیت تلاش. پیشوند کوتاه است و با تور
				// `sparkknife_ledger_` گرفته نمی‌شد، پس چهار ردیف با IP
				// و شمارش تلاش باقی می‌ماندند.
				$wpdb->esc_like( '_transient_spk_rl_' ) . '%',
				$wpdb->esc_like( '_transient_timeout_spk_rl_' ) . '%'
			)
		);

		do_action( 'sparkknife_ledger_uninstalled', true );

		// گزینه‌های نسخه در هر دو حالت پاک می‌شوند؛ پیش‌تر return بالا
		// باعث می‌شد در حالت حذف کامل هم باقی بمانند و نصب مجدد تصور
		// کند جداول هنوز وجود دارند.
		delete_option( 'sparkknife_ledger_db_version' );
		delete_option( 'sparkknife_ledger_version' );

		return;
	}

	/*
	 * حالت حفظ داده: فقط گزینه‌های نسخه پاک می‌شوند تا نصب بعدی
	 * مهاجرت‌ها را از ابتدا اجرا نکند. تنظیمات و داده‌ها می‌مانند.
	 */
	delete_option( 'sparkknife_ledger_db_version' );
	delete_option( 'sparkknife_ledger_version' );

	do_action( 'sparkknife_ledger_uninstalled', false );
}

if ( is_multisite() ) {
	$sites = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);

	foreach ( $sites as $site_id ) {
		switch_to_blog( (int) $site_id );
		sparkknife_ledger_uninstall_site();
		restore_current_blog();
	}
} else {
	sparkknife_ledger_uninstall_site();
}
