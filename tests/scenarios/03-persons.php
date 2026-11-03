<?php
/**
 * سناریو ۳: مدیریت اشخاص.
 *
 * @package SparKnife_Ledger
 */

Sparkknife_Ledger_Test_Reporter::group( '۳) مدیریت اشخاص' );

global $wpdb;

$person_service = Sparkknife_Ledger_Person_Service::instance();

// پاک‌سازی برای اجرای تکرارپذیر.
$wpdb->query( 'DELETE FROM ' . Sparkknife_Ledger_DB::transactions_table() );
$wpdb->query( 'DELETE FROM ' . Sparkknife_Ledger_DB::persons_table() );

// ایجاد.
$person_id = $person_service->create(
	array(
		'name'        => 'علی رضایی',
		'phone'       => '۰۹۱۲۳۴۵۶۷۸۹',
		'description' => 'مشتری نمونه',
		'status'      => 'active',
	)
);

spk_assert( ! is_wp_error( $person_id ) && $person_id > 0, 'ایجاد شخص جدید', is_wp_error( $person_id ) ? $person_id->get_error_message() : $person_id );

$person = Sparkknife_Ledger_Person_Repository::instance()->find( $person_id );
spk_assert( $person !== null, 'شخص ایجادشده قابل بازیابی است' );
spk_equals( 'علی رضایی', $person->name, 'نام شخص ذخیره شد' );
spk_equals( '09123456789', $person->phone, 'شماره تماس فارسی به لاتین تبدیل شد' );
spk_equals( 'active', $person->status, 'وضعیت فعال' );
spk_assert( ! empty( $person->created_at ) && '0000-00-00 00:00:00' !== $person->created_at, 'تاریخ ایجاد ثبت شد' );

// اعتبارسنجی نام خالی.
$invalid = $person_service->create( array( 'name' => '', 'phone' => '' ) );
spk_assert( is_wp_error( $invalid ), 'نام خالی رد می‌شود' );
spk_equals( 'sparkknife_ledger_validation_failed', $invalid->get_error_code(), 'کد خطای اعتبارسنجی' );

// نام کوتاه.
$short = $person_service->create( array( 'name' => 'ا' ) );
spk_assert( is_wp_error( $short ), 'نام یک‌نویسه‌ای رد می‌شود' );

// تلفن نامعتبر.
$bad_phone = $person_service->create( array( 'name' => 'شخص تست تلفن', 'phone' => '12' ) );
spk_assert( is_wp_error( $bad_phone ), 'شماره تماس کوتاه رد می‌شود' );

// تکرار (نام و تلفن یکسان).
$dup = $person_service->create(
	array(
		'name'  => 'علی رضایی',
		'phone' => '09123456789',
	)
);
spk_assert( is_wp_error( $dup ), 'شخص تکراری رد می‌شود' );
spk_equals( 'sparkknife_ledger_person_duplicate', $dup->get_error_code(), 'کد خطای تکراری' );

// شخص دوم و سوم.
$person2 = $person_service->create( array( 'name' => 'شرکت پارس تجارت', 'phone' => '02188776655', 'status' => 'active' ) );
$person3 = $person_service->create( array( 'name' => 'مریم احمدی', 'status' => 'active' ) );
spk_assert( ! is_wp_error( $person2 ) && ! is_wp_error( $person3 ), 'ایجاد چند شخص' );

// ویرایش.
$updated = $person_service->update(
	$person2,
	array(
		'name'        => 'شرکت پارس تجارت (سهامی خاص)',
		'phone'       => '02188776655',
		'description' => 'مشتری عمده',
		'status'      => 'active',
	)
);
spk_assert( ! is_wp_error( $updated ), 'ویرایش شخص', is_wp_error( $updated ) ? $updated->get_error_message() : '' );

$person2_row = Sparkknife_Ledger_Person_Repository::instance()->find( $person2 );
spk_equals( 'شرکت پارس تجارت (سهامی خاص)', $person2_row->name, 'نام ویرایش شد' );
spk_equals( 'مشتری عمده', $person2_row->description, 'توضیحات ویرایش شد' );
spk_assert( $person2_row->updated_at >= $person2_row->created_at, 'تاریخ ویرایش به‌روز شد' );
spk_assert( false !== strpos( $person2_row->name, 'پارس تجارت' ), 'حفظ کاراکترهای فارسی در نام' );

// تغییر وضعیت.
$status_result = $person_service->set_status( $person3, 'inactive' );
spk_assert( ! is_wp_error( $status_result ), 'غیرفعال‌سازی شخص' );
$person3_row = Sparkknife_Ledger_Person_Repository::instance()->find( $person3 );
spk_equals( 'inactive', $person3_row->status, 'وضعیت غیرفعال شد' );

// حذف شخص بدون تراکنش.
$delete_target = $person_service->create( array( 'name' => 'شخص قابل حذف' ) );
$deleted       = $person_service->delete( $delete_target );
spk_assert( ! is_wp_error( $deleted ), 'حذف نرم شخص بدون تراکنش', is_wp_error( $deleted ) ? $deleted->get_error_message() : '' );

$deleted_row = Sparkknife_Ledger_Person_Repository::instance()->find( $delete_target, true );
spk_assert( $deleted_row !== null, 'رکورد حذف‌شده در دیتابیس باقی می‌ماند (Soft Delete)' );
spk_assert( ! empty( $deleted_row->deleted_at ), 'تاریخ حذف ثبت شد' );
spk_assert( null === Sparkknife_Ledger_Person_Repository::instance()->find( $delete_target ), 'شخص حذف‌شده در فهرست عادی نیست' );

// بازگردانی.
$restored = $person_service->restore( $delete_target );
spk_assert( ! is_wp_error( $restored ), 'بازگردانی شخص حذف‌شده' );
spk_assert( null !== Sparkknife_Ledger_Person_Repository::instance()->find( $delete_target ), 'شخص پس از بازگردانی در فهرست است' );

// جستجو و فیلتر.
$search = Sparkknife_Ledger_Person_Repository::instance()->query( array( 'search' => 'پارس' ) );
spk_equals( 1, $search['total'], 'جستجوی نام شخص' );

$inactive = Sparkknife_Ledger_Person_Repository::instance()->query( array( 'status' => 'inactive' ) );
spk_assert( $inactive['total'] >= 1, 'فیلتر بر اساس وضعیت' );

// صفحه‌بندی.
$paged = Sparkknife_Ledger_Person_Repository::instance()->query( array( 'per_page' => 2, 'page' => 1 ) );
spk_equals( 2, count( $paged['items'] ), 'صفحه‌بندی: دو ردیف در صفحه' );
spk_assert( $paged['total'] >= 3, 'تعداد کل در صفحه‌بندی' );

// مرتب‌سازی.
$sorted = Sparkknife_Ledger_Person_Repository::instance()->query( array( 'orderby' => 'name', 'order' => 'asc' ) );
spk_equals( 'created_at', in_array( 'name', Sparkknife_Ledger_Person_Repository::sortable_columns(), true ) ? 'created_at' : 'x', 'ستون نام قابل مرتب‌سازی است' );

// تلاش برای تزریق SQL در پارامتر مرتب‌سازی (باید بی‌اثر باشد).
$injection = Sparkknife_Ledger_Person_Repository::instance()->query( array( 'orderby' => "name; DROP TABLE x;--" ) );
spk_assert( isset( $injection['items'] ), 'پارامتر مرتب‌سازی نامعتبر باعث خطا نمی‌شود' );

// تلاش برای SQL Injection در جستجو.
$sqli = Sparkknife_Ledger_Person_Repository::instance()->query( array( 'search' => "' OR 1=1 --" ) );
spk_equals( 0, $sqli['total'], 'جستجوی تزریقی نتیجه‌ای برنمی‌گرداند' );

// تاریخچه ایجاد شخص.
$history = Sparkknife_Ledger_Audit_Service::instance()->get_history( 'person', $person_id );
spk_assert( count( $history ) >= 1, 'رویداد ایجاد شخص در تاریخچه ثبت شد' );
spk_equals( 'create', $history[0]->action, 'نوع رویداد ایجاد' );

$update_history = Sparkknife_Ledger_Audit_Service::instance()->get_history( 'person', $person2 );
$update_actions = wp_list_pluck( $update_history, 'action' );
spk_assert( in_array( 'update', $update_actions, true ), 'ویرایش شخص در تاریخچه ثبت شد' );

// ذخیره شناسه‌ها برای سناریوهای بعد.
$GLOBALS['spk_test_persons'] = array(
	'ali'   => $person_id,
	'pars'  => $person2,
	'maryam' => $person3,
);
