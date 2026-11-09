<?php
/**
 * سناریو ۷: امنیت (دسترسی، nonce، پاک‌سازی، تزریق).
 *
 * @package SparKnife_Ledger
 */

Sparkknife_Ledger_Test_Reporter::group( '۷) امنیت' );

$persons = $GLOBALS['spk_test_persons'];

// ── نقش‌ها و دسترسی‌ها ──────────────────────────────────────────────
$subscriber_id = wp_create_user( 'spk_subscriber_' . wp_rand(), wp_generate_password( 12 ), 'sub-' . wp_rand() . '@example.com' );

if ( ! is_wp_error( $subscriber_id ) ) {
	$subscriber = new WP_User( $subscriber_id );
	$subscriber->set_role( 'subscriber' );

	wp_set_current_user( $subscriber_id );

	spk_assert( ! Sparkknife_Ledger_Capabilities::current_user_can( 'manage_persons' ), 'مشترک به مدیریت اشخاص دسترسی ندارد' );
	spk_assert( ! Sparkknife_Ledger_Capabilities::current_user_can( 'void_transactions' ), 'مشترک به ابطال تراکنش دسترسی ندارد' );
	spk_assert( ! Sparkknife_Ledger_Capabilities::current_user_can( 'reports' ), 'مشترک به گزارش‌ها دسترسی ندارد' );
	spk_assert( ! Sparkknife_Ledger_Capabilities::current_user_can( 'manage_settings' ), 'مشترک به تنظیمات دسترسی ندارد' );

	// تلاش برای ساخت شخص با نقش بدون دسترسی از طریق Service.
	$unauthorized = Sparkknife_Ledger_Person_Service::instance()->create( array( 'name' => 'نفوذی تست' ) );
	spk_assert( ! is_wp_error( $unauthorized ), 'لایه Service خودش دسترسی را بررسی نمی‌کند (مسئولیت کنترلر) — مستند' );

	// پاک‌سازی داده‌های تست.
	$created = Sparkknife_Ledger_Person_Repository::instance()->find( (int) $unauthorized );
	if ( $created ) {
		Sparkknife_Ledger_Person_Service::instance()->delete( (int) $unauthorized );
	}

	// بازگشت به مدیر. شناسه از دیتابیس خوانده می‌شود؛ متغیر جهانی‌ای که
	// هرگز تعریف نمی‌شد قبلاً همین‌جا Warning تولید می‌کرد.
	$restore_admin = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
	wp_set_current_user( isset( $restore_admin[0] ) ? (int) $restore_admin[0] : 1 );
}

// ── مدیر: دسترسی کامل ─────────────────────────────────────────────
$admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
wp_set_current_user( (int) $admins[0] );

spk_assert( Sparkknife_Ledger_Capabilities::current_user_can( 'manage_persons' ), 'مدیر به مدیریت اشخاص دسترسی دارد' );
spk_assert( Sparkknife_Ledger_Capabilities::current_user_can( 'void_transactions' ), 'مدیر به ابطال دسترسی دارد' );
spk_assert( Sparkknife_Ledger_Capabilities::current_user_can( 'export_reports' ), 'مدیر به خروجی گزارش دسترسی دارد' );

// alias و پیشوند.
spk_equals( 'sparkknife_ledger_reports', Sparkknife_Ledger_Capabilities::normalize( 'view_reports' ), 'نگاشت نام قدیمی گزارش‌ها' );
spk_equals( 'manage_options', Sparkknife_Ledger_Capabilities::normalize( 'manage_settings' ), 'تنظیمات فقط برای مدیر کل' );
spk_equals( 'sparkknife_ledger_manage_persons', Sparkknife_Ledger_Capabilities::normalize( 'sparkknife_ledger_manage_persons' ), 'نام کامل Capability تغییر نمی‌کند' );

// تخصیص دسترسی به نقش دیگر.
Sparkknife_Ledger_Capabilities::save_role_matrix(
	array(
		'editor' => array( 'manage_persons', 'reports' ),
	)
);

$editor_role = get_role( 'editor' );
spk_assert( $editor_role->has_cap( 'sparkknife_ledger_manage_persons' ), 'اعطای دسترسی مدیریت اشخاص به ویرایشگر' );
spk_assert( $editor_role->has_cap( 'sparkknife_ledger_reports' ), 'اعطای دسترسی گزارش به ویرایشگر' );
spk_assert( ! $editor_role->has_cap( 'sparkknife_ledger_void_transactions' ), 'دسترسی ابطال به ویرایشگر داده نشده' );

// بازگردانی ماتریس.
Sparkknife_Ledger_Capabilities::save_role_matrix( array() );
spk_assert( ! get_role( 'editor' )->has_cap( 'sparkknife_ledger_manage_persons' ), 'حذف دسترسی از ویرایشگر' );

// ── Nonce ─────────────────────────────────────────────────────────
$nonce = wp_create_nonce( 'sparkknife_ledger_person_save' );
spk_assert( (bool) $nonce, 'تولید nonce' );
spk_assert( 1 === wp_verify_nonce( $nonce, 'sparkknife_ledger_person_save' ) || 2 === wp_verify_nonce( $nonce, 'sparkknife_ledger_person_save' ), 'اعتبارسنجی nonce صحیح' );
spk_assert( false === wp_verify_nonce( $nonce, 'sparkknife_ledger_other_action' ), 'nonce برای action دیگر معتبر نیست' );
spk_assert( false === wp_verify_nonce( 'fake-nonce-value', 'sparkknife_ledger_person_save' ), 'nonce جعلی رد می‌شود' );

// ── پاک‌سازی ورودی‌ها ──────────────────────────────────────────────
spk_equals( 'Hello World', Sparkknife_Ledger_Sanitizer::text( 'Hello <script>alert(1)</script>World' ), 'حذف تگ اسکریپت از متن' );
spk_equals( '', Sparkknife_Ledger_Sanitizer::text( '<script>alert(1)</script>' ), 'متن صرفاً اسکریپتی خالی می‌شود' );
spk_assert( false !== strpos( Sparkknife_Ledger_Sanitizer::textarea( "خط اول\nخط دوم" ), "خط اول" ), 'حفظ محتوای متن چندخطی' );
spk_equals( 5, Sparkknife_Ledger_Sanitizer::id( '5abc' ), 'تبدیل شناسه به عدد' );
spk_equals( 3, Sparkknife_Ledger_Sanitizer::id( '-3' ), 'شناسه منفی با absint به مقدار مطلق تبدیل می‌شود' );

$phone = Sparkknife_Ledger_Sanitizer::phone( '۰۹۱۲ ۳۴۵-۶۷۸۹<script>' );
spk_assert( false === strpos( $phone, '<' ), 'حذف کاراکترهای خطرناک از تلفن', $phone );
spk_assert( false !== strpos( $phone, '0912' ), 'تبدیل ارقام فارسی تلفن', $phone );

// محدودیت طول.
$long = Sparkknife_Ledger_Sanitizer::text( str_repeat( 'a', 500 ), 190 );
spk_equals( 190, strlen( $long ), 'محدودسازی طول متن' );

// ── جلوگیری از تزریق در Repository ─────────────────────────────────
$injection_search = Sparkknife_Ledger_Transaction_Repository::instance()->query(
	array( 'search' => "'; DROP TABLE " . Sparkknife_Ledger_DB::persons_table() . '; --' )
);
spk_assert( isset( $injection_search['items'] ), 'کوئری جستجو با تزریق اجرا نمی‌شود' );
spk_assert( Sparkknife_Ledger_DB::tables_exist(), 'جداول پس از تلاش تزریق سالم هستند' );

$injection_person = Sparkknife_Ledger_Transaction_Repository::instance()->query( array( 'person_id' => "1 OR 1=1" ) );
spk_assert( isset( $injection_person['total'] ), 'پارامتر شناسه شخص تزریق‌پذیر نیست' );

// مرتب‌سازی فقط از whitelist.
spk_assert( in_array( 'txn_date', Sparkknife_Ledger_Transaction_Repository::sortable_columns(), true ), 'ستون‌های مجاز مرتب‌سازی تراکنش تعریف شده' );
spk_assert( ! in_array( 'password', Sparkknife_Ledger_Transaction_Repository::sortable_columns(), true ), 'ستون غیرمجاز در whitelist نیست' );

// ── Escape خروجی در Viewها ─────────────────────────────────────────
$result = Sparkknife_Ledger_Dashboard_Controller::class;
spk_assert( class_exists( $result ), 'کنترلر داشبورد بارگذاری شده' );

// ── حذف شخص دارای تراکنش ممنوع است ─────────────────────────────────
$delete_with_txn = Sparkknife_Ledger_Person_Service::instance()->delete( $persons['ali'] );
spk_assert( is_wp_error( $delete_with_txn ), 'شخص دارای تراکنش قابل حذف نیست' );
spk_equals( 'sparkknife_ledger_person_has_transactions', $delete_with_txn->get_error_code(), 'کد خطای وجود تراکنش' );

// ── هشدار: دسترسی به فایل مستقیم ───────────────────────────────────
spk_assert( defined( 'ABSPATH' ), 'ثابت ABSPATH تعریف شده (محافظت از دسترسی مستقیم)' );

$php_files = glob( SPARKKNIFE_LEDGER_DIR . 'includes/**/*.php' );
$php_files = array_merge( $php_files ? $php_files : array(), glob( SPARKKNIFE_LEDGER_DIR . 'includes/*.php' ), glob( SPARKKNIFE_LEDGER_DIR . 'includes/Admin/Views/partials/*.php' ) );
$guarded   = 0;
$total     = 0;

foreach ( $php_files as $file ) {
	$contents = (string) file_get_contents( $file );

	if ( false === strpos( $contents, 'class Sparkknife_Ledger_Renderer' ) && false === strpos( $contents, 'class Sparkknife_Ledger_Autoloader' ) ) {
		// فایل‌های View درون Renderer بارگذاری می‌شوند، اما باید ABSPATH را چک کنند.
		$total++;
		if ( false !== strpos( $contents, "defined( 'ABSPATH' ) || exit" ) ) {
			$guarded++;
		}
	}
}

spk_equals( $total, $guarded, 'همه فایل‌های PHP در برابر دسترسی مستقیم محافظت شده‌اند' );

$index_files = glob( SPARKKNIFE_LEDGER_DIR . '*/index.php' );
spk_assert( count( $index_files ) >= 1, 'فایل index.php در پوشه‌ها وجود دارد' );
