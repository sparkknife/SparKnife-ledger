<?php
/**
 * سناریو ۹: حذف افزونه و سیاست نگه‌داری داده‌ها.
 *
 * @package SparKnife_Ledger
 *
 * این سناریو در پایان اجرا می‌شود چون جداول را حذف می‌کند.
 * ترتیب: ابتدا حالت «حفظ داده» و در انتها بازسازی کامل.
 */

Sparkknife_Ledger_Test_Reporter::group( '۹) حذف افزونه و نگه‌داری داده‌ها' );

global $wpdb;

$transactions = Sparkknife_Ledger_DB::transactions_table();
$persons      = Sparkknife_Ledger_DB::persons_table();
$audit        = Sparkknife_Ledger_DB::audit_table();

$row_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$transactions}" );
spk_assert( $row_count > 0, 'داده‌های مالی برای آزمون حذف موجود است', (string) $row_count );

// ── حالت ۱: بدون اجازه حذف ─────────────────────────────────────────
spk_equals( 0, (int) Sparkknife_Ledger_Settings::get( 'delete_data_on_uninstall' ), 'گزینه حذف داده به‌صورت پیش‌فرض خاموش است' );

$uninstall_file = SPARKKNIFE_LEDGER_DIR . 'uninstall.php';
spk_assert( is_readable( $uninstall_file ), 'فایل uninstall.php وجود دارد' );

// شبیه‌سازی ثابت وردپرس در فرآیند حذف افزونه.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	define( 'WP_UNINSTALL_PLUGIN', 'sparkknife-ledger/sparkknife-ledger.php' );
}

$settings_before = get_option( Sparkknife_Ledger_Settings::OPTION_KEY );
$rows_before     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$transactions}" );

require $uninstall_file;

spk_assert( Sparkknife_Ledger_DB::tables_exist( true ), 'در حالت پیش‌فرض جداول پس از حذف افزونه باقی می‌مانند' );
spk_equals( $rows_before, (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$transactions}" ), 'تراکنش‌ها پس از حذف افزونه حفظ می‌شوند' );
spk_assert( false !== get_option( Sparkknife_Ledger_Settings::OPTION_KEY ), 'تنظیمات پس از حذف افزونه حفظ می‌شود' );
spk_assert( get_role( 'administrator' )->has_cap( 'sparkknife_ledger_manage_persons' ), 'دسترسی‌ها در حالت حفظ داده باقی می‌مانند' );
spk_assert( is_array( $settings_before ) && count( $settings_before ) > 5, 'تنظیمات قبل از حذف معتبر بود' );

// ── حالت ۲: با اجازه صریح مدیر ─────────────────────────────────────
add_filter( 'sparkknife_ledger_uninstall_delete_data', '__return_true' );

// دوباره اجرا؛ فایل uninstall یک‌بار در همین درخواست اجرا شده، پس منطق را مستقیم صدا می‌زنیم.
Sparkknife_Ledger_Installer::purge_all_data();

spk_assert( ! Sparkknife_Ledger_DB::tables_exist( true ), 'با اجازه صریح، جداول حذف می‌شوند' );
// بررسی با کوئری مستقیم: درایور SQLite از SHOW TABLES LIKE پشتیبانی نمی‌کند.
$wpdb->suppress_errors( true );
$persons_left = $wpdb->get_var( "SELECT COUNT(*) FROM {$persons}" );
$audit_left   = $wpdb->get_var( "SELECT COUNT(*) FROM {$audit}" );
$wpdb->suppress_errors( false );

spk_assert( null === $persons_left, 'جدول اشخاص حذف شد', var_export( $persons_left, true ) );
spk_assert( null === $audit_left, 'جدول تاریخچه حذف شد', var_export( $audit_left, true ) );
spk_assert( false === get_option( Sparkknife_Ledger_Settings::OPTION_KEY ), 'تنظیمات حذف شد' );

remove_filter( 'sparkknife_ledger_uninstall_delete_data', '__return_true' );

// ── بازسازی برای اجرای تکرارپذیر ───────────────────────────────────
Sparkknife_Ledger_Installer::create_tables();
Sparkknife_Ledger_Settings::install_defaults();
Sparkknife_Ledger_Capabilities::add_capabilities();

spk_assert( Sparkknife_Ledger_DB::tables_exist( true ), 'نصب مجدد پس از حذف کامل' );
spk_equals( 'toman', Sparkknife_Ledger_Settings::get( 'currency_type' ), 'تنظیمات پیش‌فرض پس از نصب مجدد' );
spk_assert( get_role( 'administrator' )->has_cap( 'sparkknife_ledger_manage_persons' ), 'دسترسی‌ها پس از نصب مجدد' );
