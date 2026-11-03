<?php
/**
 * مدیریت واحد پول، تبدیل مبالغ و قالب‌بندی نمایش.
 *
 * اصل طراحی: مبلغ همیشه به‌صورت عدد صحیح مقیاس‌شده ذخیره می‌شود
 * (مبلغ × ۱۰^۴) و هرگز از float برای محاسبات مالی استفاده نمی‌شود.
 * منطق محاسبات از منطق نمایش جدا است.
 *
 * @package SparKnife_Ledger
 */

defined( 'ABSPATH' ) || exit;

/**
 * ابزارهای پول.
 */
class Sparkknife_Ledger_Money {

	/**
	 * واحدهای پول آماده.
	 *
	 * @return array<string, array<string, string>>
	 */
	public static function currency_presets() {
		return array(
			'toman'  => array(
				'label'  => __( 'تومان', 'sparkknife-ledger' ),
				'symbol' => __( 'تومان', 'sparkknife-ledger' ),
				'code'   => 'IRT',
			),
			'rial'   => array(
				'label'  => __( 'ریال', 'sparkknife-ledger' ),
				'symbol' => __( 'ریال', 'sparkknife-ledger' ),
				'code'   => 'IRR',
			),
			'custom' => array(
				'label'  => __( 'واحد پول دلخواه', 'sparkknife-ledger' ),
				'symbol' => '',
				'code'   => '',
			),
		);
	}

	/**
	 * نوع واحد پول فعال.
	 *
	 * @return string
	 */
	public static function currency_type() {
		$type = (string) Sparkknife_Ledger_Settings::get( 'currency_type', 'toman' );
		return array_key_exists( $type, self::currency_presets() ) ? $type : 'toman';
	}

	/**
	 * برچسب واحد پول.
	 *
	 * @return string
	 */
	public static function currency_label() {
		if ( 'custom' === self::currency_type() ) {
			$label = trim( (string) Sparkknife_Ledger_Settings::get( 'custom_currency_label', '' ) );
			return '' !== $label ? $label : __( 'واحد', 'sparkknife-ledger' );
		}

		$presets = self::currency_presets();
		return $presets[ self::currency_type() ]['label'];
	}

	/**
	 * نماد نمایشی واحد پول.
	 *
	 * @return string
	 */
	public static function currency_symbol() {
		if ( 'custom' === self::currency_type() ) {
			$symbol = trim( (string) Sparkknife_Ledger_Settings::get( 'custom_currency_symbol', '' ) );
			return '' !== $symbol ? $symbol : self::currency_label();
		}

		$presets = self::currency_presets();
		return $presets[ self::currency_type() ]['symbol'];
	}

	/**
	 * کد ISO واحد پول (برای خروجی‌ها).
	 *
	 * @return string
	 */
	public static function currency_code() {
		if ( 'custom' === self::currency_type() ) {
			$code = strtoupper( sanitize_key( (string) Sparkknife_Ledger_Settings::get( 'custom_currency_code', '' ) ) );
			return '' !== $code ? $code : 'CUSTOM';
		}

		$presets = self::currency_presets();
		return $presets[ self::currency_type() ]['code'];
	}

	/**
	 * تعداد اعشار نمایش.
	 *
	 * @return int
	 */
	public static function decimals() {
		$decimals = (int) Sparkknife_Ledger_Settings::get( 'currency_decimals', 0 );
		return max( 0, min( 4, $decimals ) );
	}

	/**
	 * موقعیت نماد واحد پول.
	 *
	 * @return string suffix|prefix
	 */
	public static function symbol_position() {
		$position = (string) Sparkknife_Ledger_Settings::get( 'currency_position', 'suffix' );
		return in_array( $position, array( 'suffix', 'prefix' ), true ) ? $position : 'suffix';
	}

	/**
	 * جداکننده هزارگان.
	 *
	 * @return string
	 */
	public static function thousands_separator() {
		$sep = (string) Sparkknife_Ledger_Settings::get( 'thousands_separator', ',' );
		return in_array( $sep, array( ',', '.', '٬', ' ' ), true ) ? $sep : ',';
	}

	/**
	 * استفاده از ارقام فارسی در نمایش.
	 *
	 * @return bool
	 */
	public static function uses_persian_digits() {
		return (bool) Sparkknife_Ledger_Settings::get( 'use_persian_digits', true );
	}

	/**
	 * ضریب مقیاس به‌صورت رشته (برای جلوگیری از سرریز روی PHP ۳۲-بیتی).
	 *
	 * @return string
	 */
	public static function scale_factor_string() {
		return '1' . str_repeat( '0', (int) SPARKKNIFE_LEDGER_AMOUNT_SCALE );
	}

	/**
	 * ضریب مقیاس (فقط برای محاسبات کوچک؛ در مسیرهای مالی از
	 * scale_factor_string استفاده می‌شود).
	 *
	 * @return int
	 */
	public static function scale_factor() {
		return (int) pow( 10, (int) SPARKKNIFE_LEDGER_AMOUNT_SCALE );
	}

	/**
	 * تبدیل ارقام فارسی/عربی به لاتین.
	 *
	 * @param string $value ورودی.
	 * @return string
	 */
	public static function to_latin_digits( $value ) {
		$persian = array( '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' );
		$arabic  = array( '٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩' );
		$latin   = array( '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' );

		$value = str_replace( $persian, $latin, (string) $value );
		return str_replace( $arabic, $latin, $value );
	}

	/**
	 * تبدیل ارقام لاتین به فارسی برای نمایش.
	 *
	 * @param string $value ورودی.
	 * @return string
	 */
	public static function to_persian_digits( $value ) {
		$persian = array( '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' );
		$latin   = array( '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' );

		return str_replace( $latin, $persian, (string) $value );
	}

	/**
	 * تبدیل ارقام لاتین به ارقام عربی.
	 *
	 * برای جستجو لازم است: برخی نصب‌های وردپرس با زبان عربی، شماره‌ها را
	 * با این ارقام ذخیره می‌کنند.
	 *
	 * @param string $value مقدار.
	 * @return string
	 */
	public static function to_arabic_digits( $value ) {
		$latin  = array( '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' );
		$arabic = array( "٠", "١", "٢", "٣", "٤", "٥", "٦", "٧", "٨", "٩" );

		return str_replace( $latin, $arabic, (string) $value );
	}

	/**
	 * پاک‌سازی رشته مبلغ ورودی کاربر.
	 *
	 * ارقام فارسی، جداکننده هزارگان، فاصله و نماد واحد پول حذف/تبدیل می‌شود.
	 * ممیز اعشار (نقطه یا ممیز فارسی) حفظ و به نقطه تبدیل می‌شود.
	 *
	 * @param string $raw ورودی خام.
	 * @return string رشته عددی لاتین یا رشته خالی.
	 */
	public static function normalize_amount_input( $raw ) {
		$value = self::to_latin_digits( $raw );

		/*
		 * منفی یونیکد (U+2212) و منفی کشیده (U+2013/U+2014) به منفی ASCII
		 * تبدیل می‌شوند. پیش‌تر این کاراکترها در مرحله پاک‌سازی حذف می‌شدند
		 * و «−۵» به «۵» تبدیل می‌شد — یعنی مبلغ منفی، مثبت ثبت می‌شد.
		 */
		$value = str_replace( array( '−', '–', '—', '﹣' ), '-', $value );

		$value = str_replace( array( '٫', '،', '٬' ), array( '.', ',', '' ), $value );

		// حذف نماد/برچسب واحد پول.
		$symbol = self::currency_symbol();
		if ( '' !== $symbol ) {
			$value = str_replace( $symbol, '', $value );
		}

		$value = preg_replace( '/[\s\x{200c}]+/u', '', $value );

		// حذف جداکننده هزارگان؛ اما اگر جداکننده اعشار باشد (مثل 12.5) حفظ می‌شود.
		if ( ',' === self::thousands_separator() ) {
			$value = str_replace( ',', '', $value );
		} elseif ( '.' === self::thousands_separator() ) {
			// نقطه هزارگان: تنها اگر بیش از یک نقطه باشد یا نقطه در انتها با ۳ رقم، جداکننده است.
			if ( substr_count( $value, '.' ) > 1 || preg_match( '/\.\d{3}$/', $value ) ) {
				$value = str_replace( '.', '', $value );
			}
		} else {
			$value = str_replace( self::thousands_separator(), '', $value );
		}

		/*
		 * نقطه در جای جداکننده هزارگان.
		 *
		 * کاربران ایرانی «۱.۲۳۴» را به‌جای «۱٬۲۳۴» می‌نویسند و کیبورد عددی
		 * نیز همین را می‌دهد. الگوی نقطه‌ی درست‌چیده‌شده در گروه‌های سه‌رقمی
		 * بی‌ابهام است و به‌عنوان جداکننده حذف می‌شود. بدون این کار «1.234»
		 * به‌عنوان یک و دو‌دهم‌و‌سیصدو‌سی‌وچهار هزارم خوانده می‌شد و مبلغی
		 * هزار برابر کوچک‌تر از خواسته کاربر ذخیره می‌شد.
		 */
		if ( preg_match( '/^\d{1,3}(?:\.\d{3})+$/', $value ) ) {
			$value = str_replace( '.', '', $value );
		}

		// فقط رقم، نقطه و علامت منفی مجاز است؛ هر کاراکتر دیگر حذف می‌شود.
		$value = preg_replace( '/[^0-9.\-]/', '', $value );

		return is_string( $value ) ? $value : '';
	}

	/**
	 * تبدیل مبلغ انسانی به عدد صحیح مقیاس‌شده (به‌صورت رشته).
	 *
	 * خروجی همیشه رشته عدد صحیح متعارف است (روی PHP ۳۲-بیتی هم سرریز نمی‌کند).
	 *
	 * @param string|int|float $raw مبلغ ورودی (مثلاً "۱٬۲۵۰٬۰۰۰").
	 * @return string|WP_Error مبلغ مقیاس‌شده یا خطا.
	 */
	public static function parse_amount( $raw ) {
		if ( is_int( $raw ) ) {
			$normalized = (string) $raw;
		} else {
			$normalized = self::normalize_amount_input( (string) $raw );
		}

		if ( '' === $normalized || '-' === $normalized || '.' === $normalized ) {
			return new WP_Error( 'sparkknife_ledger_invalid_amount', __( 'مبلغ وارد‌شده معتبر نیست.', 'sparkknife-ledger' ) );
		}

		if ( ! preg_match( '/^\d{1,15}(\.\d{1,4})?$/', $normalized ) ) {
			return new WP_Error(
				'sparkknife_ledger_invalid_amount',
				__( 'مبلغ باید فقط شامل ارقام باشد (حداکثر چهار رقم اعشار و پانزده رقم صحیح).', 'sparkknife-ledger' )
			);
		}

		$parts     = explode( '.', $normalized, 2 );
		$whole     = ltrim( $parts[0], '0' );
		$whole     = '' === $whole ? '0' : $whole;
		$fraction  = isset( $parts[1] ) ? $parts[1] : '';
		$fraction  = str_pad( substr( $fraction, 0, (int) SPARKKNIFE_LEDGER_AMOUNT_SCALE ), (int) SPARKKNIFE_LEDGER_AMOUNT_SCALE, '0' );

		// مبلغ مقیاس‌شده = بخش صحیح × 10^scale + بخش اعشار (به‌صورت رشته).
		$scaled = Sparkknife_Ledger_Amount::add( $whole . str_repeat( '0', (int) SPARKKNIFE_LEDGER_AMOUNT_SCALE ), $fraction );
		$scaled = Sparkknife_Ledger_Amount::normalize( $scaled );

		if ( Sparkknife_Ledger_Amount::is_zero( $scaled ) ) {
			return new WP_Error(
				'sparkknife_ledger_amount_not_positive',
				__( 'مبلغ باید بزرگ‌تر از صفر باشد.', 'sparkknife-ledger' )
			);
		}

		/**
		 * سقف مجاز مبلغ (به واحد پول انسانی)؛ برای جلوگیری از اشتباه تایپی.
		 *
		 * @param string $max_amount حداکثر مبلغ.
		 */
		$max_amount = (string) apply_filters( 'sparkknife_ledger_max_amount', 1000000000000 ); // ۱۰^۱۲
		$max_scaled = $max_amount . str_repeat( '0', (int) SPARKKNIFE_LEDGER_AMOUNT_SCALE );

		if ( Sparkknife_Ledger_Amount::cmp( $scaled, $max_scaled ) > 0 ) {
			return new WP_Error(
				'sparkknife_ledger_amount_too_large',
				__( 'مبلغ وارد‌شده بیش از حد مجاز است.', 'sparkknife-ledger' )
			);
		}

		return $scaled;
	}

	/**
	 * تبدیل مبلغ ذخیره‌شده (مقیاس‌شده) به رشته عددی انسانی.
	 *
	 * @param mixed $scaled   مبلغ مقیاس‌شده (رشته یا int).
	 * @param int   $decimals تعداد اعشار (پیش‌فرض: تنظیمات).
	 * @return string
	 */
	public static function to_human( $scaled, $decimals = null ) {
		$decimals = null === $decimals ? self::decimals() : max( 0, min( 4, (int) $decimals ) );

		return Sparkknife_Ledger_Amount::scaled_to_decimal_string( $scaled, (int) SPARKKNIFE_LEDGER_AMOUNT_SCALE, $decimals );
	}

	/**
	 * قالب‌بندی مبلغ برای نمایش.
	 *
	 * @param mixed $amount        مبلغ.
	 * @param bool  $scaled        آیا مقدار مقیاس‌شده است؟ (پیش‌فرض بله)
	 * @param bool  $with_currency افزودن نماد/برچسب واحد.
	 * @return string
	 */
	public static function format( $amount, $scaled = true, $with_currency = true ) {
		/*
		 * اگر به‌اشتباه WP_Error یا شیئی به اینجا برسد، پیش‌تر با
		 * «Object could not be converted to string» صفحه از کار می‌افتاد.
		 * حالا به‌جای خطای مرگبار، خط تیره نمایش داده می‌شود.
		 */
		if ( is_object( $amount ) || is_array( $amount ) ) {
			return '—';
		}

		$value = $scaled ? self::to_human( $amount ) : (string) $amount;

		$result = Sparkknife_Ledger_Amount::group( $value, self::thousands_separator() );

		if ( $with_currency ) {
			$symbol   = self::currency_symbol();
			$position = self::symbol_position();

			if ( 'prefix' === $position ) {
				$result = $symbol . ' ' . $result;
			} else {
				$result = $result . ' ' . $symbol;
			}
		}

		if ( self::uses_persian_digits() ) {
			$result = self::to_persian_digits( $result );
		}

		/**
		 * فیلتر نهایی قالب‌بندی مبلغ.
		 *
		 * @param string $result        خروجی.
		 * @param mixed  $amount        مبلغ ورودی.
		 * @param bool   $with_currency نمایش واحد.
		 */
		return apply_filters( 'sparkknife_ledger_format_amount', $result, $amount, $with_currency );
	}

	/**
	 * قالب‌بندی مبلغ برای نمایش در فرم (بدون جداکننده و بدون ارقام فارسی).
	 *
	 * @param mixed $scaled مبلغ مقیاس‌شده.
	 * @return string
	 */
	public static function format_for_input( $scaled ) {
		return self::to_human( $scaled, (int) SPARKKNIFE_LEDGER_AMOUNT_SCALE );
	}

	/**
	 * محاسبه جمع مبالغ به‌صورت دقیق و بدون سرریز.
	 *
	 * @param array<int|string> $amounts فهرست مبالغ مقیاس‌شده.
	 * @return string مجموع.
	 */
	public static function sum( array $amounts ) {
		$total = '0';

		foreach ( $amounts as $amount ) {
			$total = Sparkknife_Ledger_Amount::add( $total, $amount );
		}

		return $total;
	}

	/**
	 * تفاضل دو مبلغ مقیاس‌شده.
	 *
	 * @param mixed $income  جمع ورودی.
	 * @param mixed $expense جمع خروجی.
	 * @return string
	 */
	public static function difference( $income, $expense ) {
		return Sparkknife_Ledger_Amount::sub( $income, $expense );
	}

	/**
	 * مقایسه دو مبلغ.
	 *
	 * @param mixed $a اول.
	 * @param mixed $b دوم.
	 * @return int
	 */
	public static function compare( $a, $b ) {
		return Sparkknife_Ledger_Amount::cmp( $a, $b );
	}

	/**
	 * آیا مبلغ بزرگ‌تر از صفر است؟
	 *
	 * @param mixed $amount مبلغ.
	 * @return bool
	 */
	public static function is_positive( $amount ) {
		return Sparkknife_Ledger_Amount::is_positive( $amount );
	}

	/**
	 * آیا مبلغ کوچک‌تر از صفر است؟
	 *
	 * @param mixed $amount مبلغ.
	 * @return bool
	 */
	public static function is_negative( $amount ) {
		return Sparkknife_Ledger_Amount::is_negative( $amount );
	}

	/**
	 * آیا مبلغ صفر است؟
	 *
	 * @param mixed $amount مبلغ.
	 * @return bool
	 */
	public static function is_zero( $amount ) {
		return Sparkknife_Ledger_Amount::is_zero( $amount );
	}

	/**
	 * تبدیل ارقام لاتین به فارسی برای نمایش (میان‌بر).
	 *
	 * @param string $value مقدار.
	 * @return string
	 */
	public static function digits_for_display( $value ) {
		return self::uses_persian_digits() ? self::to_persian_digits( (string) $value ) : (string) $value;
	}

	/**
	 * درصد نسبی یک مقدار به بیشینه (برای نمودارها) به‌صورت عدد صحیح ۰..۱۰۰.
	 *
	 * @param mixed $value مقدار.
	 * @param mixed $maximum بیشینه.
	 * @return int
	 */
	public static function percent_of( $value, $maximum ) {
		$value   = Sparkknife_Ledger_Amount::abs( $value );
		$maximum = Sparkknife_Ledger_Amount::abs( $maximum );

		if ( Sparkknife_Ledger_Amount::is_zero( $maximum ) ) {
			return 0;
		}

		// value × 100 ÷ maximum با محاسبات دقیق رشته‌ای.
		$scaled   = Sparkknife_Ledger_Amount::mul_int( $value, 100 );
		$quotient = Sparkknife_Ledger_Amount::divide_strings( $scaled, $maximum );

		return max( 0, min( 100, (int) $quotient ) );
	}

	/**
	 * بیشینه چند مبلغ.
	 *
	 * @param array<int, mixed> $amounts مبالغ.
	 * @return string
	 */
	public static function max_of( array $amounts ) {
		$max = '0';

		foreach ( $amounts as $amount ) {
			if ( Sparkknife_Ledger_Amount::cmp( $amount, $max ) > 0 ) {
				$max = Sparkknife_Ledger_Amount::normalize( $amount );
			}
		}

		return $max;
	}

	/**
	 * مجموع مبالغ (میان‌بر برای viewها).
	 *
	 * @param array<int, mixed> $amounts مبالغ.
	 * @return string
	 */
	public static function total_of( array $amounts ) {
		return self::sum( $amounts );
	}

	/**
	 * برچسب نوع تراکنش.
	 *
	 * @param string $type income|expense.
	 * @return string
	 */
	public static function transaction_type_label( $type ) {
		$labels = array(
			'income'  => __( 'ورودی', 'sparkknife-ledger' ),
			'expense' => __( 'خروجی', 'sparkknife-ledger' ),
		);

		return isset( $labels[ $type ] ) ? $labels[ $type ] : __( 'نامشخص', 'sparkknife-ledger' );
	}
}
