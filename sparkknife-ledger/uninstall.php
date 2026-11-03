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

		// گزینه‌های تنظیمات و پیکربندی.
		delete_option( Sparkknife_Ledger_Settings::OPTION_KEY );
		delete_option( 'sparkknife_ledger_delete_data_on_uninstall' );

		// دسترسی‌های سفارشی از همه نقش‌ها حذف می‌شود.
		if ( class_exists( 'Sparkknife_Ledger_Capabilities' ) ) {
			Sparkknife_Ledger_Capabilities::remove_capabilities();
		}

		// ترنزینت‌های گزارش/کش.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				$wpdb->esc_like( '_transient_sparkknife_ledger_' ) . '%',
				$wpdb->esc_like( '_transient_timeout_sparkknife_ledger_' ) . '%'
			)
		);

		do_action( 'sparkknife_ledger_uninstalled', true );

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
