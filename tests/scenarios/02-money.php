<?php
/**
 * سناریو ۲: واحد پول و دقت محاسبات.
 *
 * @package SparKnife_Ledger
 */

Sparkknife_Ledger_Test_Reporter::group( '۲) واحد پول و دقت محاسبات' );

// تبدیل ورودی‌های انسانی.
spk_equals( '12500000000', Sparkknife_Ledger_Money::parse_amount( '1250000' ), 'تبدیل مبلغ لاتین' );
spk_equals( '12500000000', Sparkknife_Ledger_Money::parse_amount( '۱٬۲۵۰٬۰۰۰' ), 'تبدیل مبلغ با ارقام فارسی و جداکننده' );
spk_equals( '12500000000', Sparkknife_Ledger_Money::parse_amount( '1,250,000' ), 'تبدیل مبلغ با کاما' );
spk_equals( '125000', Sparkknife_Ledger_Money::parse_amount( '12.5' ), 'پشتیبانی از اعشار' );
spk_equals( '5000', Sparkknife_Ledger_Money::parse_amount( '0.5' ), 'اعشار کمتر از یک' );

spk_assert( is_wp_error( Sparkknife_Ledger_Money::parse_amount( '0' ) ), 'مبلغ صفر رد می‌شود' );
spk_assert( is_wp_error( Sparkknife_Ledger_Money::parse_amount( '-500' ) ), 'مبلغ منفی رد می‌شود' );
spk_assert( is_wp_error( Sparkknife_Ledger_Money::parse_amount( 'abc' ) ), 'مبلغ غیرعددی رد می‌شود' );
spk_assert( is_wp_error( Sparkknife_Ledger_Money::parse_amount( '' ) ), 'مبلغ خالی رد می‌شود' );

// نمایش.
spk_assert( false !== strpos( Sparkknife_Ledger_Money::format( '12500000000' ), '۱,۲۵۰,۰۰۰' ), 'قالب‌بندی هزارگان و ارقام فارسی به‌همراه واحد پول', Sparkknife_Ledger_Money::format( '12500000000' ) );
spk_assert( false !== strpos( Sparkknife_Ledger_Money::format( '-2500000' ), '-' ) || false !== strpos( Sparkknife_Ledger_Money::format( '-2500000' ), '−' ), 'نمایش مبلغ منفی' );

// جمع و تفریق دقیق.
spk_equals( '300000', Sparkknife_Ledger_Money::sum( array( '100000', '200000' ) ), 'جمع مبالغ' );
spk_equals( '0', Sparkknife_Ledger_Money::difference( '100000', '100000' ), 'تفاضل برابر صفر' );
spk_equals( '99999999999999999999', Sparkknife_Ledger_Amount::add( '99999999999999999998', '1' ), 'جمع اعداد بزرگ‌تر از int ۳۲-بیتی' );
spk_equals( '-1', Sparkknife_Ledger_Amount::sub( '0', '1' ), 'تفریق منفی' );
spk_equals( 1, Sparkknife_Ledger_Amount::cmp( '2', '1' ), 'مقایسه بزرگ‌تر' );
spk_equals( -1, Sparkknife_Ledger_Amount::cmp( '-5', '3' ), 'مقایسه منفی' );

// بررسی مانع سرریز: مبلغ ۱۰۰ میلیارد تومان.
$big = Sparkknife_Ledger_Money::parse_amount( '100000000000' );
spk_assert( ! is_wp_error( $big ), 'پذیرش مبلغ ۱۰۰ میلیارد تومان (بدون سرریز)' );
spk_equals( '1000000000000000', $big, 'مبلغ ۱۰۰ میلیارد به‌درستی مقیاس شده' );

// تغییر واحد پول و تأثیر آن بر نمایش.
$original = Sparkknife_Ledger_Settings::all();

Sparkknife_Ledger_Settings::save( array_merge( $original, array( 'currency_type' => 'rial' ) ), false );
spk_equals( 'ریال', Sparkknife_Ledger_Money::currency_label(), 'تغییر واحد پول به ریال' );
spk_assert( false !== strpos( Sparkknife_Ledger_Money::format( '10000' ), 'ریال' ), 'نمایش ریال در مبالغ' );

Sparkknife_Ledger_Settings::save(
	array_merge(
		$original,
		array(
			'currency_type'          => 'custom',
			'custom_currency_label'  => 'دینار',
			'custom_currency_symbol' => 'د.ع',
			'custom_currency_code'   => 'IQD',
		)
	),
	false
);
spk_equals( 'دینار', Sparkknife_Ledger_Money::currency_label(), 'واحد پول دلخواه (برچسب)' );
spk_equals( 'IQD', Sparkknife_Ledger_Money::currency_code(), 'کد واحد پول دلخواه' );
spk_assert( false !== strpos( Sparkknife_Ledger_Money::format( '10000' ), 'د.ع' ), 'نمایش نماد واحد دلخواه' );

Sparkknife_Ledger_Settings::save( array_merge( $original, array( 'currency_type' => 'toman', 'currency_decimals' => 2 ) ), false );
spk_assert( false !== strpos( Sparkknife_Ledger_Money::format( '15000', true, false ), '۱.۵' ), 'نمایش اعشار با تنظیم دو رقم' );

// بازگرداندن تنظیمات.
Sparkknife_Ledger_Settings::save( $original, false );
spk_equals( 'toman', Sparkknife_Ledger_Settings::get( 'currency_type' ), 'بازگردانی واحد پول' );

// تاریخ شمسی.
spk_equals( '۱۴۰۳/۰۱/۰۱', Sparkknife_Ledger_Date::format( '2024-03-20' ), 'تبدیل ۲۰ مارس ۲۰۲۴ به ۱ فروردین ۱۴۰۳' );
spk_equals( '۱۴۰۴/۰۱/۰۱', Sparkknife_Ledger_Date::format( '2025-03-21' ), 'تبدیل نوروز ۱۴۰۴' );
spk_assert( ! is_wp_error( Sparkknife_Ledger_Date::jalali_to_date( '۱۴۰۳/۰۵/۱۲' ) ), 'پذیرش تاریخ شمسی فارسی' );
spk_equals( '2024-08-02', Sparkknife_Ledger_Date::jalali_to_date( '1403/05/12' ), 'تبدیل صحیح ۱۲ مرداد ۱۴۰۳ به ۲ اوت ۲۰۲۴' );

$round_trip = Sparkknife_Ledger_Date::jalali_to_date( '1402/12/29' );
spk_equals( '2024-03-19', $round_trip, 'تبدیل ۲۹ اسفند ۱۴۰۲ (سال غیرکبیسه)' );
spk_equals( '۱۴۰۲/۱۲/۲۹', Sparkknife_Ledger_Date::format( $round_trip ), 'رفت و برگشت تاریخ شمسی' );
