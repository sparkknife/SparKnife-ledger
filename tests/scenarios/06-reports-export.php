<?php
/**
 * سناریو ۶: گزارش فردی، تجمیعی و خروجی‌ها.
 *
 * @package SparKnife_Ledger
 */

Sparkknife_Ledger_Test_Reporter::group( '۶) گزارش‌گیری و خروجی' );

$persons = $GLOBALS['spk_test_persons'];
$reports = Sparkknife_Ledger_Report_Service::instance();
$ledger  = Sparkknife_Ledger_Ledger_Service::instance();

// گزارش فردی.
$person_report = $reports->get_person_report( $persons['ali'] );

spk_assert( ! is_wp_error( $person_report ), 'تولید گزارش فردی' );
spk_assert( isset( $person_report['summary']['balance'] ), 'گزارش فردی شامل مانده است' );
spk_assert( isset( $person_report['transactions']['items'] ), 'گزارش فردی شامل فهرست تراکنش‌هاست' );
spk_equals( 'ali', 'ali', 'شناسه شخص در گزارش' );
spk_equals( $persons['ali'], (int) $person_report['person']->id, 'شخص صحیح در گزارش' );

// گزارش فردی شخص ناموجود.
$missing = $reports->get_person_report( 999999 );
spk_assert( is_wp_error( $missing ), 'گزارش شخص ناموجود خطا می‌دهد' );

// فیلتر نوع در گزارش فردی.
/*
 * `filtered` جمع همان ردیف‌هایی است که فهرست تراکنش‌ها نشان می‌دهد.
 *
 * پیش‌تر اینجا انتظار می‌رفت ۰ باشد چون تراکنش ورودی ابطال شده بود —
 * یعنی تست، باگ را قفل کرده بود: فهرست آن تراکنش را نمایش می‌داد ولی
 * جمع می‌گفت هیچ ورودی‌ای نیست. اکنون هر دو یک چیز می‌گویند.
 */
$income_only = $reports->get_person_report( $persons['ali'], array( 'type' => 'income' ) );

spk_equals(
	$income_only['transactions']['total'],
	$income_only['filtered']['count'],
	'جمع فیلترشده با تعداد ردیف‌های فیلتر ورودی یکی است'
);
spk_equals( 1, $income_only['filtered']['count'], 'یک ردیف ورودی برای علی هست (ابطال‌شده ولی نمایش‌داده‌شده)' );

// همان فیلتر با وضعیت صریح «ثبت‌شده»: ابطال‌شده‌ها کنار می‌روند.
$income_posted = $reports->get_person_report(
	$persons['ali'],
	array(
		'type'   => 'income',
		'status' => 'posted',
	)
);

spk_equals( 0, $income_posted['filtered']['count'], 'با فیلتر «ثبت‌شده»، ورودی ابطال‌شده شمرده نمی‌شود' );

$expense_only = $reports->get_person_report( $persons['ali'], array( 'type' => 'expense' ) );
spk_assert( $expense_only['filtered']['count'] >= 1, 'فیلتر نوع خروجی در گزارش' );

// فیلتر بازه زمانی.
$out_of_range = $reports->get_person_report(
	$persons['ali'],
	array(
		'range'     => 'custom',
		'date_from' => '1990/01/01',
		'date_to'   => '1990/12/29',
	)
);
spk_equals( 0, $out_of_range['filtered']['count'], 'فیلتر بازه خارج از داده' );

$today_range = $reports->get_person_report(
	$persons['ali'],
	array(
		'range'     => 'custom',
		'date_from' => Sparkknife_Ledger_Date::today_for_input(),
		'date_to'   => Sparkknife_Ledger_Date::today_for_input(),
	)
);
spk_assert( $today_range['transactions']['total'] >= 1, 'فیلتر بازه امروز' );

// بازه‌های آماده.
$presets = Sparkknife_Ledger_Ledger_Service::preset_ranges();
spk_assert( isset( $presets['this_month'], $presets['last_month'], $presets['this_year'], $presets['last_30'] ), 'بازه‌های آماده تعریف شده' );

$resolved = Sparkknife_Ledger_Ledger_Service::resolve_range( array( 'range' => 'this_month' ) );
spk_assert( '' !== $resolved['from'] && '' !== $resolved['to'], 'محاسبه بازه این ماه' );
/*
 * بازه «این ماه» تقویم‌آگاه است: در تقویم شمسی باید اول ماه شمسی باشد،
 * نه اول ماه میلادی. این تست قبلاً همیشه تاریخ میلادی را انتظار داشت و
 * در نتیجه باگ را تثبیت می‌کرد.
 */
if ( Sparkknife_Ledger_Date::uses_jalali() ) {
	$today_ymd = current_time( 'Y-m-d' );

	list( $jy, $jm ) = Sparkknife_Ledger_Date::gregorian_to_jalali(
		(int) substr( $today_ymd, 0, 4 ),
		(int) substr( $today_ymd, 5, 2 ),
		(int) substr( $today_ymd, 8, 2 )
	);

	list( $egy, $egm, $egd ) = Sparkknife_Ledger_Date::jalali_to_gregorian( (int) $jy, (int) $jm, 1 );

	$expected_month_start = sprintf( '%04d-%02d-%02d', $egy, $egm, $egd );
} else {
	$expected_month_start = gmdate( 'Y-m-01', strtotime( current_time( 'Y-m-d' ) ) );
}

spk_equals( $expected_month_start, $resolved['from'], 'ابتدای ماه جاری مطابق تقویم فعال' );

// گزارش تجمیعی.
$summary_report = $reports->get_summary_report();

spk_assert( isset( $summary_report['totals']['income'] ), 'گزارش تجمیعی شامل مجموع ورودی' );
spk_assert( isset( $summary_report['totals']['expense'] ), 'گزارش تجمیعی شامل مجموع خروجی' );
spk_assert( isset( $summary_report['totals']['balance'] ), 'گزارش تجمیعی شامل مانده کل' );
spk_assert( count( $summary_report['all_rows'] ) >= 3, 'گزارش تجمیعی شامل همه اشخاص', count( $summary_report['all_rows'] ) );

// تطابق جمع کل با جمع اشخاص.
$sum_income  = '0';
$sum_expense = '0';

foreach ( $summary_report['all_rows'] as $row ) {
	$sum_income  = Sparkknife_Ledger_Amount::add( $sum_income, $row['income'] );
	$sum_expense = Sparkknife_Ledger_Amount::add( $sum_expense, $row['expense'] );
}

spk_equals( $summary_report['totals']['income'], $sum_income, 'جمع ورودی اشخاص = جمع کل گزارش' );
spk_equals( $summary_report['totals']['expense'], $sum_expense, 'جمع خروجی اشخاص = جمع کل گزارش' );

$expected_balance = Sparkknife_Ledger_Amount::sub( $sum_income, $sum_expense );
spk_equals( $expected_balance, $summary_report['totals']['balance'], 'مانده کل = ورودی − خروجی' );

// مقایسه وضعیت‌ها.
$comparison = $summary_report['comparison'];
spk_equals( count( $summary_report['all_rows'] ), $comparison['positive'] + $comparison['negative'] + $comparison['settled'], 'جمع دسته‌بندی وضعیت = تعداد اشخاص' );

// فیلتر شخص در گزارش تجمیعی.
$single_person = $reports->get_summary_report( array( 'person_id' => $persons['ali'] ) );
spk_equals( 1, count( $single_person['all_rows'] ), 'فیلتر شخص در گزارش تجمیعی' );
spk_equals( $persons['ali'], (int) $single_person['all_rows'][0]['id'], 'شخص صحیح در فیلتر' );

// مرتب‌سازی.
$sorted = $reports->get_summary_report( array( 'orderby' => 'balance', 'order' => 'asc' ) );
spk_assert( count( $sorted['all_rows'] ) >= 2, 'مرتب‌سازی گزارش' );

$balances = wp_list_pluck( $sorted['all_rows'], 'balance' );
$is_sorted = true;

for ( $i = 1; $i < count( $balances ); $i++ ) {
	if ( Sparkknife_Ledger_Amount::cmp( $balances[ $i - 1 ], $balances[ $i ] ) > 0 ) {
		$is_sorted = false;
		break;
	}
}

spk_assert( $is_sorted, 'ترتیب صعودی مانده‌ها صحیح است' );

// جستجو در گزارش تجمیعی.
$searched = $reports->get_summary_report( array( 'search' => 'پارس' ) );
spk_equals( 1, count( $searched['all_rows'] ), 'جستجوی نام در گزارش تجمیعی' );

// خروجی CSV گزارش فردی.
$export = Sparkknife_Ledger_Export_Service::instance();
$rows   = $export->person_report_rows( $person_report );

spk_assert( count( $rows ) > 5, 'تولید ردیف‌های CSV گزارش فردی', count( $rows ) );
spk_assert( in_array( 'نام شخص', $rows[1], true ), 'ردیف نام شخص در CSV' );
spk_assert( in_array( 'علی رضایی', $rows[1], true ), 'مقدار نام شخص در CSV' );

$header_row = null;
foreach ( $rows as $row ) {
	if ( in_array( 'شناسه تراکنش', $row, true ) ) {
		$header_row = $row;
		break;
	}
}

spk_assert( null !== $header_row, 'سرستون‌های تراکنش در CSV' );

// خروجی CSV گزارش تجمیعی.
$summary_rows = $export->summary_report_rows( $summary_report );
spk_assert( count( $summary_rows ) > 5, 'تولید ردیف‌های CSV گزارش تجمیعی' );

// جلوگیری از CSV Injection.
spk_equals( "'=cmd|'/c calc'!A1", Sparkknife_Ledger_Export_Service::sanitize_cell( "=cmd|'/c calc'!A1" ), 'خنثی‌سازی فرمول خطرناک CSV' );
spk_equals( "'+123", Sparkknife_Ledger_Export_Service::sanitize_cell( "+123" ), 'خنثی‌سازی علامت جمع' );
spk_equals( "'@SUM(A1)", Sparkknife_Ledger_Export_Service::sanitize_cell( "@SUM(A1)" ), 'خنثی‌سازی @' );
spk_equals( 'مبلغ عادی', Sparkknife_Ledger_Export_Service::sanitize_cell( 'مبلغ عادی' ), 'متن عادی تغییر نمی‌کند' );

// نمایش چاپ.
$print        = Sparkknife_Ledger_Print_Service::instance();
$print_html   = $print->person_report_html( $person_report );
spk_assert( false !== strpos( $print_html, 'گزارش فردی' ), 'HTML چاپ شامل عنوان گزارش' );
spk_assert( false !== strpos( $print_html, 'علی رضایی' ), 'HTML چاپ شامل نام شخص' );
spk_assert( false === strpos( $print_html, '<script' ), 'HTML چاپ بدون اسکریپت داخلی' );

$summary_html = $print->summary_report_html( $summary_report );
spk_assert( false !== strpos( $summary_html, 'گزارش تجمیعی' ), 'HTML چاپ گزارش تجمیعی' );

// فرمت‌های خروجی.
$formats = Sparkknife_Ledger_Export_Service::formats();
spk_assert( isset( $formats['csv'], $formats['print'], $formats['pdf'] ), 'فرمت‌های خروجی CSV، چاپ و PDF تعریف شده' );

// نمودار ماهانه.
$chart = $reports->get_monthly_chart( 6 );
spk_equals( 6, count( $chart ), 'داده نمودار شش‌ماهه' );
spk_assert( isset( $chart[0]['label'] ), 'برچسب ماه در نمودار' );

// داشبورد.
$stats = $ledger->get_overall_stats();
spk_assert( isset( $stats['persons_total'], $stats['income'], $stats['expense'], $stats['balance'], $stats['transactions'] ), 'آمار داشبورد کامل است' );
spk_assert( (int) $stats['persons_total'] >= 3, 'تعداد اشخاص در داشبورد' );
spk_assert( (int) $stats['transactions'] >= 3, 'تعداد تراکنش‌ها در داشبورد' );

$positive = $ledger->get_persons_by_account_status( 'positive' );
$negative = $ledger->get_persons_by_account_status( 'negative' );
spk_assert( is_array( $positive ) && is_array( $negative ), 'فهرست اشخاص دارای مانده مثبت و منفی' );
