<?php
/**
 * سناریو ۱: نصب، جداول و تنظیمات پیش‌فرض.
 *
 * @package SparKnife_Ledger
 */

global $wpdb;

Sparkknife_Ledger_Test_Reporter::group( '۱) نصب و ساختار دیتابیس' );

Sparkknife_Ledger_Installer::create_tables();

$persons_table      = Sparkknife_Ledger_DB::persons_table();
$transactions_table = Sparkknife_Ledger_DB::transactions_table();
$audit_table        = Sparkknife_Ledger_DB::audit_table();

spk_equals( $wpdb->prefix . 'sparkknife_ledger_persons', $persons_table, 'نام جدول اشخاص با پیشوند وردپرس' );
spk_equals( $wpdb->prefix . 'sparkknife_ledger_transactions', $transactions_table, 'نام جدول تراکنش‌ها' );
spk_equals( $wpdb->prefix . 'sparkknife_ledger_audit_log', $audit_table, 'نام جدول تاریخچه' );

spk_assert( Sparkknife_Ledger_DB::tables_exist(), 'جداول افزونه شناسایی می‌شوند' );

spk_equals( SPARKKNIFE_LEDGER_DB_VERSION, get_option( 'sparkknife_ledger_db_version' ), 'نسخه اسکیما ثبت شده است' );

// ستون‌های کلیدی جدول تراکنش‌ها.
$columns = $wpdb->get_col( "PRAGMA table_info({$transactions_table})" );

if ( empty( $columns ) ) {
	$columns = wp_list_pluck( $wpdb->get_results( "SHOW COLUMNS FROM {$transactions_table}" ), 'Field' );
}

$expected_columns = array( 'id', 'reference', 'person_id', 'type', 'amount', 'currency', 'txn_date', 'description', 'status', 'created_by', 'created_at', 'updated_by', 'updated_at', 'void_reason', 'voided_by', 'voided_at' );

foreach ( $expected_columns as $column ) {
	spk_assert( in_array( $column, $columns, true ), 'ستون ' . $column . ' در جدول تراکنش‌ها وجود دارد' );
}

// تنظیمات پیش‌فرض.
$settings = Sparkknife_Ledger_Settings::all();
spk_equals( 'toman', $settings['currency_type'], 'واحد پول پیش‌فرض تومان است' );
spk_equals( 'jalali', $settings['calendar'], 'تقویم پیش‌فرض شمسی است' );
spk_assert( isset( $settings['per_page'] ), 'تعداد ردیف صفحه تعریف شده است' );

// Capabilityها.
$admin_role = get_role( 'administrator' );
spk_assert( $admin_role && $admin_role->has_cap( 'sparkknife_ledger_manage_persons' ), 'دسترسی مدیریت اشخاص به مدیر داده شده' );
spk_assert( $admin_role && $admin_role->has_cap( 'sparkknife_ledger_void_transactions' ), 'دسترسی ابطال تراکنش به مدیر داده شده' );
spk_assert( Sparkknife_Ledger_Capabilities::current_user_can( 'manage_settings' ), 'مدیر به تنظیمات دسترسی دارد' );
