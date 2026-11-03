<?php
/**
 * سناریو ۵: ابطال تراکنش، تأثیر بر محاسبات و Audit Log.
 *
 * @package SparKnife_Ledger
 */

Sparkknife_Ledger_Test_Reporter::group( '۵) ابطال تراکنش و تاریخچه تغییرات' );

$persons  = $GLOBALS['spk_test_persons'];
$txns     = $GLOBALS['spk_test_txns'];
$service  = Sparkknife_Ledger_Transaction_Service::instance();
$ledger   = Sparkknife_Ledger_Ledger_Service::instance();

$balance_before = $ledger->get_person_summary( $persons['ali'] );

// ابطال بدون دلیل (دلیل الزامی است).
$no_reason = $service->void( $txns['income'], '' );
spk_assert( is_wp_error( $no_reason ), 'ابطال بدون دلیل رد می‌شود' );
spk_equals( 'sparkknife_ledger_void_reason_required', $no_reason->get_error_code(), 'کد خطای دلیل الزامی' );

// ابطال با دلیل کوتاه.
$short_reason = $service->void( $txns['income'], 'خط' );
spk_assert( is_wp_error( $short_reason ), 'دلیل خیلی کوتاه رد می‌شود' );

// ابطال صحیح.
$void_result = $service->void( $txns['income'], 'اشتباه در ثبت مبلغ اولیه' );
spk_assert( ! is_wp_error( $void_result ), 'ابطال تراکنش با دلیل', is_wp_error( $void_result ) ? $void_result->get_error_message() : '' );

$voided = Sparkknife_Ledger_Transaction_Repository::instance()->find( $txns['income'] );
spk_equals( 'void', $voided->status, 'وضعیت تراکنش باطل شد' );
spk_equals( 'اشتباه در ثبت مبلغ اولیه', $voided->void_reason, 'دلیل ابطال ذخیره شد' );
spk_equals( (string) get_current_user_id(), (string) $voided->voided_by, 'کاربر ابطال‌کننده ثبت شد' );
spk_assert( ! empty( $voided->voided_at ), 'تاریخ ابطال ثبت شد' );
spk_equals( '12500000000', $voided->amount, 'اطلاعات اصلی تراکنش حفظ شد (مبلغ)' );
spk_equals( 'واریز وجه', $voided->description, 'اطلاعات اصلی تراکنش حفظ شد (توضیحات)' );

// تأثیر ابطال بر محاسبات: ورودی صفر می‌شود، فقط خروجی ۳۰۰٬۰۰۰ باقی می‌ماند.
$balance_after = $ledger->get_person_summary( $persons['ali'] );
spk_equals( '0', $balance_after['income'], 'تراکنش باطل‌شده از مجموع ورودی حذف شد' );
spk_equals( '-3000000000', $balance_after['balance'], 'مانده پس از ابطال بازمحاسبه شد' );
spk_equals( 'negative', $balance_after['account_status'], 'وضعیت حساب پس از ابطال: منفی' );
spk_equals( 1, $balance_after['count'], 'تعداد تراکنش‌های معتبر کاهش یافت' );
spk_equals( 1, $balance_after['void_count'], 'تعداد تراکنش‌های باطل‌شده' );
spk_assert( '12500000000' === $balance_before['income'], 'مبلغ ورودی قبل از ابطال' );

// ابطال دوباره.
$double_void = $service->void( $txns['income'], 'تلاش دوباره' );
spk_assert( is_wp_error( $double_void ), 'ابطال مجدد رد می‌شود' );
spk_equals( 'sparkknife_ledger_already_void', $double_void->get_error_code(), 'کد خطای ابطال قبلی' );

// ویرایش تراکنش باطل‌شده.
$edit_void = $service->update(
	$txns['income'],
	array(
		'person_id' => $persons['ali'],
		'type'      => 'income',
		'amount'    => '999',
		'txn_date'  => Sparkknife_Ledger_Date::today_for_input(),
	)
);
spk_assert( is_wp_error( $edit_void ), 'تراکنش باطل‌شده قابل ویرایش نیست' );
spk_equals( 'sparkknife_ledger_transaction_is_void', $edit_void->get_error_code(), 'کد خطای ویرایش تراکنش باطل' );

// تراکنش باطل‌شده در فهرست با فیلتر وضعیت.
$void_list = Sparkknife_Ledger_Transaction_Repository::instance()->query( array( 'status' => 'void' ) );
spk_assert( $void_list['total'] >= 1, 'امکان مشاهده تراکنش‌های باطل‌شده وجود دارد' );

$posted_list = Sparkknife_Ledger_Transaction_Repository::instance()->query( array( 'status' => 'posted' ) );
spk_assert( $posted_list['total'] >= 1, 'فیلتر تراکنش‌های ثبت‌شده' );

// تاریخچه ابطال.
$history     = Sparkknife_Ledger_Audit_Service::instance()->get_history( 'transaction', $txns['income'] );
$void_events = array_values( array_filter( $history, function ( $entry ) { return 'void' === $entry->action; } ) );
spk_assert( count( $void_events ) >= 1, 'رویداد ابطال در تاریخچه ثبت شد' );

$void_changes = Sparkknife_Ledger_Audit_Service::instance()->decode_changes( $void_events[0] );
spk_assert( isset( $void_changes['status'] ), 'تغییر وضعیت در تاریخچه ثبت شد' );
spk_equals( 'posted', $void_changes['status']['from'], 'وضعیت قبلی در تاریخچه' );
spk_equals( 'void', $void_changes['status']['to'], 'وضعیت جدید در تاریخچه' );
spk_assert( isset( $void_changes['void_reason'] ), 'دلیل ابطال در تاریخچه ثبت شد' );

// زنجیره هش: ردیف‌ها hash دارند و یکتا هستند.
$all_audit = $wpdb->get_results( 'SELECT hash, created_at FROM ' . Sparkknife_Ledger_DB::audit_table() );
$hashes    = wp_list_pluck( $all_audit, 'hash' );
spk_assert( count( array_filter( $hashes ) ) === count( $hashes ), 'همه ردیف‌های تاریخچه هش دارند' );
spk_assert( count( array_unique( $hashes ) ) === count( $hashes ), 'هش ردیف‌ها یکتاست' );
spk_equals( 64, strlen( (string) $hashes[0] ), 'طول هش SHA-256' );

// تاریخچه تغییرات تنظیمات.
$original = Sparkknife_Ledger_Settings::all();
Sparkknife_Ledger_Settings::save( array_merge( $original, array( 'per_page' => 25 ) ) );
$settings_events = Sparkknife_Ledger_Audit_Repository::instance()->query( array( 'object_type' => 'settings' ) );
spk_assert( $settings_events['total'] >= 1, 'تغییر تنظیمات در تاریخچه ثبت شد' );

Sparkknife_Ledger_Settings::save( $original, false );

// تاریخچه تغییر مبلغ/نوع/شخص قابل پیگیری است.
$type_change = $service->create(
	array(
		'person_id' => $persons['pars'],
		'type'      => 'income',
		'amount'    => '100000',
		'txn_date'  => Sparkknife_Ledger_Date::today_for_input(),
	)
);
$service->update(
	$type_change,
	array(
		'person_id' => $persons['maryam'],
		'type'      => 'expense',
		'amount'    => '150000',
		'txn_date'  => Sparkknife_Ledger_Date::today_for_input(),
	)
);

$change_history = Sparkknife_Ledger_Audit_Service::instance()->get_history( 'transaction', $type_change );
$update_entry   = null;

foreach ( $change_history as $entry ) {
	if ( 'update' === $entry->action ) {
		$update_entry = $entry;
		break;
	}
}

spk_assert( null !== $update_entry, 'ویرایش نوع/شخص/مبلغ ثبت شد' );

$tracked = Sparkknife_Ledger_Audit_Service::instance()->decode_changes( $update_entry );
spk_assert( isset( $tracked['type'] ), 'تغییر نوع تراکنش قابل پیگیری است' );
spk_assert( isset( $tracked['person_id'] ), 'تغییر شخص مرتبط قابل پیگیری است' );
spk_assert( isset( $tracked['amount'] ), 'تغییر مبلغ قابل پیگیری است' );
spk_equals( 'income', $tracked['type']['from'], 'نوع قبلی' );
spk_equals( 'expense', $tracked['type']['to'], 'نوع جدید' );

// ابطال تراکنش باعث تغییر محاسبات مانده تجمیعی می‌شود.
$before_stats = $ledger->get_overall_stats();
$service->void( $type_change, 'اصلاح اشتباه ثبت' );
$after_stats = $ledger->get_overall_stats();

spk_assert( Sparkknife_Ledger_Amount::cmp( $after_stats['income'], $before_stats['income'] ) <= 0, 'ابطال باعث افزایش ورودی نمی‌شود' );
spk_assert( Sparkknife_Ledger_Amount::cmp( $after_stats['expense'], $before_stats['expense'] ) <= 0, 'ابطال باعث افزایش خروجی نمی‌شود' );
