<?php
/**
 * توابع کمکی عمومی افزونه.
 *
 * همه توابع این فایل با پیشوند sparkknife_ledger_ نام‌گذاری شده‌اند تا احتمال
 * تداخل با سایر افزونه‌ها به حداقل برسد. این توابع API عمومی افزونه هستند.
 *
 * @package SparKnife_Ledger
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'sparkknife_ledger_get_option' ) ) {
	/**
	 * خواندن یک تنظیم افزونه با مقدار پیش‌فرض امن.
	 *
	 * @param string $key     کلید تنظیم.
	 * @param mixed  $default مقدار پیش‌فرض.
	 * @return mixed
	 */
	function sparkknife_ledger_get_option( $key, $default = null ) {
		return Sparkknife_Ledger_Settings::get( $key, $default );
	}
}

if ( ! function_exists( 'sparkknife_ledger_settings' ) ) {
	/**
	 * دریافت تمام تنظیمات.
	 *
	 * @return array<string, mixed>
	 */
	function sparkknife_ledger_settings() {
		return Sparkknife_Ledger_Settings::all();
	}
}

if ( ! function_exists( 'sparkknife_ledger_format_amount' ) ) {
	/**
	 * قالب‌بندی مبلغ برای نمایش (با جداکننده هزارگان و واحد پول).
	 *
	 * @param string|int|float $amount مبلغ ذخیره‌شده (مقیاس‌شده) یا مبلغ انسانی.
	 * @param bool             $scaled آیا مقدار ورودی مقیاس‌شده است؟ پیش‌فرض بله.
	 * @param bool             $with_currency افزودن واحد پول.
	 * @return string
	 */
	function sparkknife_ledger_format_amount( $amount, $scaled = true, $with_currency = true ) {
		return Sparkknife_Ledger_Money::format( $amount, $scaled, $with_currency );
	}
}

if ( ! function_exists( 'sparkknife_ledger_format_date' ) ) {
	/**
	 * قالب‌بندی تاریخ برای نمایش.
	 *
	 * @param string $date   تاریخ میلادی (Y-m-d یا Y-m-d H:i:s).
	 * @param bool   $with_time نمایش ساعت.
	 * @return string
	 */
	function sparkknife_ledger_format_date( $date, $with_time = false ) {
		return Sparkknife_Ledger_Date::format( $date, $with_time );
	}
}

if ( ! function_exists( 'sparkknife_ledger_get_person' ) ) {
	/**
	 * دریافت یک شخص.
	 *
	 * @param int $person_id شناسه شخص.
	 * @return object|null
	 */
	function sparkknife_ledger_get_person( $person_id ) {
		return Sparkknife_Ledger_Person_Repository::instance()->find( absint( $person_id ) );
	}
}

if ( ! function_exists( 'sparkknife_ledger_get_balance' ) ) {
	/**
	 * محاسبه مانده یک شخص.
	 *
	 * @param int $person_id شناسه شخص.
	 * @return array<string, mixed>
	 */
	function sparkknife_ledger_get_balance( $person_id ) {
		return Sparkknife_Ledger_Ledger_Service::instance()->get_person_summary( absint( $person_id ) );
	}
}

if ( ! function_exists( 'sparkknife_ledger_currency_symbol' ) ) {
	/**
	 * نماد واحد پول فعال.
	 *
	 * @return string
	 */
	function sparkknife_ledger_currency_symbol() {
		return Sparkknife_Ledger_Money::currency_symbol();
	}
}

if ( ! function_exists( 'sparkknife_ledger_can' ) ) {
	/**
	 * بررسی دسترسی کاربر جاری.
	 *
	 * @param string $capability نام دسترسی (بدون پیشوند sparkknife_ledger_ هم پذیرفته می‌شود).
	 * @return bool
	 */
	function sparkknife_ledger_can( $capability ) {
		return Sparkknife_Ledger_Capabilities::current_user_can( $capability );
	}
}

if ( ! function_exists( 'sparkknife_ledger_admin_url' ) ) {
	/**
	 * تولید نشانی صفحه‌های پنل مدیریت افزونه.
	 *
	 * @param string $page   اسلاگ صفحه.
	 * @param array  $args   پارامترهای اضافی.
	 * @return string
	 */
	function sparkknife_ledger_admin_url( $page = '', $args = array() ) {
		$page_slug = '' === $page ? 'sparkknife-ledger' : 'sparkknife-ledger-' . $page;

		if ( 'sparkknife-ledger' === $page ) {
			$page_slug = 'sparkknife-ledger';
		}

		$url = admin_url( 'admin.php?page=' . rawurlencode( $page_slug ) );

		if ( ! empty( $args ) ) {
			$url = add_query_arg( $args, $url );
		}

		return $url;
	}
}
