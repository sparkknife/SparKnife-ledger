<?php
/**
 * سناریو ۴: تراکنش‌ها و محاسبات مالی.
 *
 * @package SparKnife_Ledger
 */

Sparkknife_Ledger_Test_Reporter::group( '۴) تراکنش‌ها و محاسبات مالی' );

$persons = $GLOBALS['spk_test_persons'];
$service = Sparkknife_Ledger_Transaction_Service::instance();

// تراکنش ورودی.
$income = $service->create(
	array(
		'person_id'   => $persons['ali'],
		'type'        => 'income',
		'amount'      => '۱٬۲۵۰٬۰۰۰',
		'txn_date'    => Sparkknife_Ledger_Date::today_for_input(),
		'description' => 'واریز وجه',
	)
);

spk_assert( ! is_wp_error( $income ) && $income > 0, 'ثبت تراکنش ورودی', is_wp_error( $income ) ? $income->get_error_message() : $income );

$row = Sparkknife_Ledger_Transaction_Repository::instance()->find( $income );
spk_equals( 'income', $row->type, 'نوع تراکنش ورودی' );
spk_equals( '12500000000', $row->amount, 'مبلغ دقیق ذخیره شد' );
spk_equals( 'posted', $row->status, 'وضعیت ثبت‌شده' );
spk_assert( ! empty( $row->reference ) && false !== strpos( $row->reference, 'TXN-' ), 'شناسه تراکنش تولید شد', $row->reference );
spk_equals( (string) get_current_user_id(), (string) $row->created_by, 'کاربر ثبت‌کننده ذخیره شد' );
spk_equals( 'IRT', $row->currency, 'کد واحد پول در تراکنش ثبت شد' );

// تراکنش خروجی.
$expense = $service->create(
	array(
		'person_id'   => $persons['ali'],
		'type'        => 'expense',
		'amount'      => '250000',
		'txn_date'    => Sparkknife_Ledger_Date::today_for_input(),
		'description' => 'پرداخت بابت خرید',
	)
);

spk_assert( ! is_wp_error( $expense ), 'ثبت تراکنش خروجی', is_wp_error( $expense ) ? $expense->get_error_message() : '' );

// محاسبه مانده: 1,250,000 - 250,000 = 1,000,000.
$summary = Sparkknife_Ledger_Ledger_Service::instance()->get_person_summary( $persons['ali'] );
spk_equals( '12500000000', $summary['income'], 'مجموع ورودی‌ها' );
spk_equals( '2500000000', $summary['expense'], 'مجموع خروجی‌ها' );
spk_equals( '10000000000', $summary['balance'], 'مانده = ورودی − خروجی (۱٬۰۰۰٬۰۰۰ تومان)' );
spk_equals( 'positive', $summary['account_status'], 'وضعیت حساب: مانده مثبت' );
spk_equals( 2, $summary['count'], 'تعداد تراکنش‌های معتبر' );
spk_assert( false !== strpos( $summary['balance_formatted'], '۱,۰۰۰,۰۰۰' ), 'نمایش مانده با قالب فارسی', $summary['balance_formatted'] );

// مانده منفی.
// مریم در سناریوی قبل غیرفعال شده بود؛ ثبت تراکنش برای شخص غیرفعال مجاز نیست،
// پس ابتدا او را دوباره فعال می‌کنیم.
Sparkknife_Ledger_Person_Service::instance()->set_status( $persons['maryam'], 'active' );

$expense2 = $service->create(
	array(
		'person_id' => $persons['maryam'],
		'type'      => 'expense',
		'amount'    => '500000',
		'txn_date'  => Sparkknife_Ledger_Date::today_for_input(),
	)
);

$maryam_summary = Sparkknife_Ledger_Ledger_Service::instance()->get_person_summary( $persons['maryam'] );
spk_equals( '-5000000000', $maryam_summary['balance'], 'مانده منفی محاسبه شد' );
spk_equals( 'negative', $maryam_summary['account_status'], 'وضعیت حساب: مانده منفی' );

// تسویه‌شده.
$service->create(
	array(
		'person_id' => $persons['pars'],
		'type'      => 'income',
		'amount'    => '500000',
		'txn_date'  => Sparkknife_Ledger_Date::today_for_input(),
	)
);
$service->create(
	array(
		'person_id' => $persons['pars'],
		'type'      => 'expense',
		'amount'    => '500000',
		'txn_date'  => Sparkknife_Ledger_Date::today_for_input(),
	)
);

$pars_summary = Sparkknife_Ledger_Ledger_Service::instance()->get_person_summary( $persons['pars'] );
spk_equals( '0', $pars_summary['balance'], 'مانده صفر' );
spk_equals( 'settled', $pars_summary['account_status'], 'وضعیت حساب: تسویه‌شده' );

// اعتبارسنجی تراکنش.
$no_person = $service->create( array( 'person_id' => 0, 'type' => 'income', 'amount' => '1000', 'txn_date' => '1403/05/12' ) );
spk_assert( is_wp_error( $no_person ), 'تراکنش بدون شخص رد می‌شود' );

$bad_type = $service->create( array( 'person_id' => $persons['ali'], 'type' => 'transfer', 'amount' => '1000', 'txn_date' => Sparkknife_Ledger_Date::today_for_input() ) );
spk_assert( is_wp_error( $bad_type ), 'نوع تراکنش نامعتبر رد می‌شود' );

$zero_amount = $service->create( array( 'person_id' => $persons['ali'], 'type' => 'income', 'amount' => '0', 'txn_date' => Sparkknife_Ledger_Date::today_for_input() ) );
spk_assert( is_wp_error( $zero_amount ), 'مبلغ صفر رد می‌شود' );

$future = $service->create(
	array(
		'person_id' => $persons['ali'],
		'type'      => 'income',
		'amount'    => '1000',
		'txn_date'  => Sparkknife_Ledger_Date::format_for_input( gmdate( 'Y-m-d', strtotime( '+10 days' ) ) ),
	)
);
spk_assert( is_wp_error( $future ), 'تاریخ آینده رد می‌شود' );

// شخص غیرفعال.
$inactive_person = Sparkknife_Ledger_Person_Service::instance()->create( array( 'name' => 'شخص غیرفعال تست', 'status' => 'inactive' ) );
$inactive_txn    = $service->create(
	array(
		'person_id' => $inactive_person,
		'type'      => 'income',
		'amount'    => '1000',
		'txn_date'  => Sparkknife_Ledger_Date::today_for_input(),
	)
);
spk_assert( is_wp_error( $inactive_txn ), 'ثبت تراکنش برای شخص غیرفعال مجاز نیست' );

// ویرایش تراکنش.
$edit_result = $service->update(
	$expense,
	array(
		'person_id'   => $persons['ali'],
		'type'        => 'expense',
		'amount'      => '300000',
		'txn_date'    => Sparkknife_Ledger_Date::today_for_input(),
		'description' => 'پرداخت اصلاح‌شده',
	)
);
spk_assert( ! is_wp_error( $edit_result ), 'ویرایش تراکنش', is_wp_error( $edit_result ) ? $edit_result->get_error_message() : '' );

$edited = Sparkknife_Ledger_Transaction_Repository::instance()->find( $expense );
spk_equals( '3000000000', $edited->amount, 'مبلغ جدید ذخیره شد' );
spk_equals( 'پرداخت اصلاح‌شده', $edited->description, 'توضیحات جدید ذخیره شد' );

$summary_after_edit = Sparkknife_Ledger_Ledger_Service::instance()->get_person_summary( $persons['ali'] );
spk_equals( '9500000000', $summary_after_edit['balance'], 'مانده پس از ویرایش مبلغ' );

// تاریخچه ویرایش شامل تغییر مبلغ.
$txn_history  = Sparkknife_Ledger_Audit_Service::instance()->get_history( 'transaction', $expense );
$edit_entries = array_values( array_filter( $txn_history, function ( $entry ) { return 'update' === $entry->action; } ) );
spk_assert( count( $edit_entries ) >= 1, 'ویرایش تراکنش در تاریخچه ثبت شد' );

$changes = Sparkknife_Ledger_Audit_Service::instance()->decode_changes( $edit_entries[0] );
spk_assert( isset( $changes['amount'] ), 'تغییر مبلغ در تاریخچه قابل پیگیری است' );
spk_equals( '250000', $changes['amount']['from'], 'مقدار قبلی مبلغ در تاریخچه (خوانا)' );
spk_equals( '300000', $changes['amount']['to'], 'مقدار جدید مبلغ در تاریخچه (خوانا)' );

// شناسه یکتا.
$references = array( $row->reference, $edited->reference );
spk_assert( $references[0] !== $references[1], 'شناسه تراکنش‌ها یکتاست' );

// فیلترها.
$filtered = Sparkknife_Ledger_Transaction_Repository::instance()->query( array( 'person_id' => $persons['ali'], 'type' => 'income' ) );
spk_equals( 1, $filtered['total'], 'فیلتر بر اساس شخص و نوع' );

$date_filtered = Sparkknife_Ledger_Transaction_Repository::instance()->query(
	array(
		'date_from' => '1900-01-01',
		'date_to'   => '1900-12-31',
	)
);
spk_equals( 0, $date_filtered['total'], 'فیلتر بازه زمانی خارج از داده' );

$search_filtered = Sparkknife_Ledger_Transaction_Repository::instance()->query( array( 'search' => 'واریز' ) );
spk_equals( 1, $search_filtered['total'], 'جستجو در توضیحات تراکنش' );

// ذخیره برای سناریوی بعد.
$GLOBALS['spk_test_txns'] = array(
	'income'  => $income,
	'expense' => $expense,
	'maryam'  => $expense2,
);
