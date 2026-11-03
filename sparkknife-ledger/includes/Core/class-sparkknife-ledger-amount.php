<?php
/**
 * محاسبات دقیق مبالغ با دقت دلخواه (بدون float و بدون وابستگی به اندازه int).
 *
 * چرا این کلاس وجود دارد؟
 * مبالغ به‌صورت عدد صحیح مقیاس‌شده (مبلغ × ۱۰^۴) ذخیره می‌شوند. روی PHP
 * ۳۲-بیتی، PHP_INT_MAX برابر ۲٬۱۴۷٬۴۸۳٬۶۴۷ است؛ بنابراین یک مبلغ معمولی
 * «۱۲٬۵۰۰٬۰۰۰ تومان» در قالب int مقیاس‌شده (۱٫۲۵×۱۰^۱۱) سرریز می‌کند.
 * برای تضمین صحت مالی روی همه هاست‌ها، مبالغ در لایه PHP به‌صورت رشته
 * عددی با علامت نمایش داده می‌شوند و عملیات جمع/تفریق/مقایسه به‌صورت
 * دقت‌دلخواه انجام می‌شود:
 *   ۱) اگر bcmath موجود باشد، از آن استفاده می‌شود (سریع و اثبات‌شده).
 *   ۲) در غیر این صورت، الگوریتم‌های رشته‌ای خالص (بدون float) اجرا می‌شوند.
 *
 * همه توابع این کلاس برای رشته عددی متعارف (بدون صفر ابتدایی، با علامت
 * منفی اختیاری) کار می‌کنند و نتیجه همیشه رشته متعارف است.
 *
 * @package SparKnife_Ledger
 */

defined( 'ABSPATH' ) || exit;

/**
 * ریاضیات دقت‌دلخواه روی رشته‌های عدد صحیح.
 */
class Sparkknife_Ledger_Amount {

	/**
	 * آیا bcmath در دسترس است؟
	 *
	 * @return bool
	 */
	public static function has_bcmath() {
		return function_exists( 'bcadd' ) && function_exists( 'bccomp' );
	}

	/**
	 * تبدیل هر مقدار عددی به رشته عدد صحیح متعارف.
	 *
	 * مقادیر اعشاری (مثل خروجی AVG) به نزدیک‌ترین عدد صحیح گرد می‌شوند.
	 * مقادیر غیرعددی به «0» تبدیل می‌شوند.
	 *
	 * @param mixed $value مقدار.
	 * @return string
	 */
	public static function normalize( $value ) {
		if ( is_object( $value ) && method_exists( $value, '__toString' ) ) {
			$value = (string) $value;
		}

		if ( is_bool( $value ) ) {
			return $value ? '1' : '0';
		}

		if ( is_int( $value ) ) {
			return self::canonical( (string) $value );
		}

		if ( is_float( $value ) ) {
			// فقط برای مقادیر محاسباتی غیرمالی (مثل میانگین) استفاده می‌شود.
			if ( ! is_finite( $value ) ) {
				return '0';
			}

			return self::canonical( (string) (int) round( $value ) );
		}

		$value = trim( (string) $value );

		if ( '' === $value ) {
			return '0';
		}

		// حذف جداکننده‌ها و تبدیل ارقام فارسی.
		$value = str_replace( array( ',', '٬', '،', ' ' ), '', $value );
		$latin = self::latin_digits( $value );

		if ( ! preg_match( '/^([+-]?)(\d*)(?:\.(\d*))?$/', $latin, $matches ) ) {
			return '0';
		}

		$sign     = '-' === $matches[1] ? '-' : '';
		$whole    = isset( $matches[2] ) ? $matches[2] : '';
		$fraction = isset( $matches[3] ) ? $matches[3] : '';

		if ( '' === $whole && '' === $fraction ) {
			return '0';
		}

		// گرد کردن نیم‌به‌بالا بر اساس اولین رقم اعشار.
		if ( '' !== $fraction ) {
			$first = (int) $fraction[0];
			$rest  = substr( $fraction, 1 );
			$whole = '' === $whole ? '0' : $whole;

			if ( $first >= 5 ) {
				$whole = self::add( $whole, '1' );
			} elseif ( 0 === $first && '' !== trim( $rest, '0' ) ) {
				// اعشار کوچک‌تر از ۰٫۵: بدون تغییر.
				$whole = $whole;
			}
		}

		return self::canonical( $sign . $whole );
	}

	/**
	 * تبدیل ارقام فارسی/عربی به لاتین.
	 *
	 * @param string $value مقدار.
	 * @return string
	 */
	public static function latin_digits( $value ) {
		$value = (string) $value;

		$value = preg_replace_callback(
			'/[\x{06F0}-\x{06F9}]/u',
			function ( $m ) {
				return (string) ( self::codepoint( $m[0] ) - 0x06F0 );
			},
			$value
		);

		$value = preg_replace_callback(
			'/[\x{0660}-\x{0669}]/u',
			function ( $m ) {
				return (string) ( self::codepoint( $m[0] ) - 0x0660 );
			},
			(string) $value
		);

		return (string) $value;
	}

	/**
	 * کد پوینت اولین کاراکتر UTF-8.
	 *
	 * @param string $char کاراکتر.
	 * @return int
	 */
	private static function codepoint( $char ) {
		if ( function_exists( 'mb_ord' ) ) {
			return (int) mb_ord( $char, 'UTF-8' );
		}

		$bytes = array_values( unpack( 'C*', $char ) );
		$count = count( $bytes );

		if ( 1 === $count ) {
			return $bytes[0];
		}

		if ( 2 === $count ) {
			return ( ( $bytes[0] & 0x1F ) << 6 ) | ( $bytes[1] & 0x3F );
		}

		if ( 3 === $count ) {
			return ( ( $bytes[0] & 0x0F ) << 12 ) | ( ( $bytes[1] & 0x3F ) << 6 ) | ( $bytes[2] & 0x3F );
		}

		return ( ( $bytes[0] & 0x07 ) << 18 ) | ( ( $bytes[1] & 0x3F ) << 12 ) | ( ( $bytes[2] & 0x3F ) << 6 ) | ( $bytes[3] & 0x3F );
	}

	/**
	 * متعارف‌سازی رشته عدد صحیح (حذف صفر ابتدایی و منفی صفر).
	 *
	 * @param string $value رشته.
	 * @return string
	 */
	public static function canonical( $value ) {
		$value = trim( (string) $value );
		$sign  = '';

		if ( '' !== $value && ( '-' === $value[0] || '+' === $value[0] ) ) {
			$sign  = '-' === $value[0] ? '-' : '';
			$value = substr( $value, 1 );
		}

		$value = ltrim( $value, '0' );

		if ( '' === $value ) {
			return '0';
		}

		if ( ! preg_match( '/^\d+$/', $value ) ) {
			return '0';
		}

		return '-' . $value === '-' ? '0' : $sign . $value;
	}

	/**
	 * جمع دو عدد.
	 *
	 * @param mixed ...$values مقادیر.
	 * @return string
	 */
	public static function add( ...$values ) {
		$total = '0';

		foreach ( $values as $value ) {
			$total = self::add_two( $total, self::normalize( $value ) );
		}

		return $total;
	}

	/**
	 * تفریق.
	 *
	 * @param mixed $a عدد اول.
	 * @param mixed $b عدد دوم.
	 * @return string
	 */
	public static function sub( $a, $b ) {
		return self::add( $a, self::neg( $b ) );
	}

	/**
	 * قرینه عدد.
	 *
	 * @param mixed $value مقدار.
	 * @return string
	 */
	public static function neg( $value ) {
		$value = self::normalize( $value );

		if ( '0' === $value ) {
			return '0';
		}

		return '-' === $value[0] ? substr( $value, 1 ) : '-' . $value;
	}

	/**
	 * قدر مطلق.
	 *
	 * @param mixed $value مقدار.
	 * @return string
	 */
	public static function abs( $value ) {
		$value = self::normalize( $value );

		return '-' === $value[0] ? substr( $value, 1 ) : $value;
	}

	/**
	 * مقایسه دو عدد: -۱ کمتر، ۰ برابر، ۱ بیشتر.
	 *
	 * @param mixed $a عدد اول.
	 * @param mixed $b عدد دوم.
	 * @return int
	 */
	public static function cmp( $a, $b ) {
		$a = self::normalize( $a );
		$b = self::normalize( $b );

		if ( self::has_bcmath() ) {
			return (int) bccomp( $a, $b, 0 );
		}

		return self::compare_strings( $a, $b );
	}

	/**
	 * آیا مقدار صفر است؟
	 *
	 * @param mixed $value مقدار.
	 * @return bool
	 */
	public static function is_zero( $value ) {
		return '0' === self::normalize( $value );
	}

	/**
	 * آیا مقدار مثبت است؟
	 *
	 * @param mixed $value مقدار.
	 * @return bool
	 */
	public static function is_positive( $value ) {
		return self::cmp( $value, '0' ) > 0;
	}

	/**
	 * آیا مقدار منفی است؟
	 *
	 * @param mixed $value مقدار.
	 * @return bool
	 */
	public static function is_negative( $value ) {
		return self::cmp( $value, '0' ) < 0;
	}

	/**
	 * ضرب در عدد صحیح کوچک.
	 *
	 * @param mixed $value مقدار.
	 * @param int   $multiplier ضریب.
	 * @return string
	 */
	public static function mul_int( $value, $multiplier ) {
		$value      = self::normalize( $value );
		$multiplier = (int) $multiplier;

		if ( 0 === $multiplier || '0' === $value ) {
			return '0';
		}

		if ( 1 === $multiplier ) {
			return $value;
		}

		$sign = '';
		if ( '-' === $value[0] ) {
			$sign  = '-' === $sign ? '' : '-';
			$value = substr( $value, 1 );
		}

		if ( self::has_bcmath() ) {
			return self::canonical( $sign . bcmul( $value, (string) abs( $multiplier ), 0 ) );
		}

		$result    = '';
		$carry     = 0;
		$mult      = abs( $multiplier );
		$length    = strlen( $value );

		for ( $i = $length - 1; $i >= 0; $i-- ) {
			$product = ( (int) $value[ $i ] ) * $mult + $carry;
			$result  = (string) ( $product % 10 ) . $result;
			$carry   = intdiv( $product, 10 );
		}

		while ( $carry > 0 ) {
			$result = (string) ( $carry % 10 ) . $result;
			$carry  = intdiv( $carry, 10 );
		}

		return self::canonical( $sign . $result );
	}

	/**
	 * تقسیم بر عدد صحیح مثبت با گرد کردن به پایین (برای نمایش میانگین).
	 *
	 * @param mixed $value مقدار.
	 * @param int   $divisor مقسوم‌علیه.
	 * @return string
	 */
	public static function div_int( $value, $divisor ) {
		$divisor = (int) $divisor;

		if ( $divisor <= 0 ) {
			return '0';
		}

		$value = self::normalize( $value );
		$sign  = '';

		if ( '-' === $value[0] ) {
			$sign  = '-';
			$value = substr( $value, 1 );
		}

		if ( self::has_bcmath() ) {
			return self::canonical( $sign . bcdiv( $value, (string) $divisor, 0 ) );
		}

		$quotient = '';
		$remainder = '0';

		for ( $i = 0, $length = strlen( $value ); $i < $length; $i++ ) {
			$current   = self::canonical( $remainder . $value[ $i ] );
			$digit     = 0;

			while ( self::compare_strings( $current, (string) $divisor ) >= 0 ) {
				$current = self::subtract_strings( $current, (string) $divisor );
				$digit++;
			}

			$quotient .= (string) $digit;
			$remainder = $current;
		}

		return self::canonical( $sign . $quotient );
	}

	/**
	 * تقسیم دو عدد نامنفی با نتیجه صحیح (بدون اعشار، گرد به پایین).
	 *
	 * برای محاسبه درصد نمودار استفاده می‌شود.
	 *
	 * @param mixed $dividend مقسوم.
	 * @param mixed $divisor  مقسوم‌علیه.
	 * @return string
	 */
	public static function divide_strings( $dividend, $divisor ) {
		$dividend = self::abs( $dividend );
		$divisor  = self::abs( $divisor );

		if ( self::is_zero( $divisor ) ) {
			return '0';
		}

		if ( self::has_bcmath() ) {
			return self::canonical( bcdiv( $dividend, $divisor, 0 ) );
		}

		$quotient  = '';
		$remainder = '0';

		for ( $i = 0, $length = strlen( $dividend ); $i < $length; $i++ ) {
			$current = self::canonical( $remainder . $dividend[ $i ] );
			$digit   = 0;

			while ( self::compare_strings( $current, $divisor ) >= 0 ) {
				$current = self::subtract_strings( $current, $divisor );
				$digit++;
			}

			$quotient .= (string) $digit;
			$remainder = $current;
		}

		return self::canonical( $quotient );
	}

	/**
	 * جمع دو رشته нормалиزه‌شده.
	 *
	 * @param string $a اول.
	 * @param string $b دوم.
	 * @return string
	 */
	private static function add_two( $a, $b ) {
		if ( self::has_bcmath() ) {
			return self::canonical( bcadd( $a, $b, 0 ) );
		}

		$a_negative = '-' === $a[0];
		$b_negative = '-' === $b[0];

		$a_digits = $a_negative ? substr( $a, 1 ) : $a;
		$b_digits = $b_negative ? substr( $b, 1 ) : $b;

		if ( $a_negative === $b_negative ) {
			$sum = self::add_strings( $a_digits, $b_digits );
			return self::canonical( ( $a_negative && '0' !== $sum ? '-' : '' ) . $sum );
		}

		$comparison = self::compare_strings( $a_digits, $b_digits );

		if ( 0 === $comparison ) {
			return '0';
		}

		if ( $comparison > 0 ) {
			$difference = self::subtract_strings( $a_digits, $b_digits );
			return self::canonical( ( $a_negative ? '-' : '' ) . $difference );
		}

		$difference = self::subtract_strings( $b_digits, $a_digits );

		return self::canonical( ( $b_negative ? '-' : '' ) . $difference );
	}

	/**
	 * جمع دو رشته رقمی بدون علامت.
	 *
	 * @param string $a اول.
	 * @param string $b دوم.
	 * @return string
	 */
	private static function add_strings( $a, $b ) {
		$a      = ltrim( (string) $a, '0' );
		$b      = ltrim( (string) $b, '0' );
		$result = '';
		$carry  = 0;

		$i = strlen( $a ) - 1;
		$j = strlen( $b ) - 1;

		while ( $i >= 0 || $j >= 0 || $carry > 0 ) {
			$sum = $carry;

			if ( $i >= 0 ) {
				$sum += (int) $a[ $i ];
				$i--;
			}

			if ( $j >= 0 ) {
				$sum += (int) $b[ $j ];
				$j--;
			}

			$result = (string) ( $sum % 10 ) . $result;
			$carry  = intdiv( $sum, 10 );
		}

		return ltrim( $result, '0' ) === '' ? '0' : ltrim( $result, '0' );
	}

	/**
	 * تفریق دو رشته رقمی بدون علامت (a ≥ b).
	 *
	 * @param string $a بزرگ‌تر.
	 * @param string $b کوچک‌تر.
	 * @return string
	 */
	private static function subtract_strings( $a, $b ) {
		$result = '';
		$borrow = 0;

		$i = strlen( $a ) - 1;
		$j = strlen( $b ) - 1;

		while ( $i >= 0 ) {
			$digit = (int) $a[ $i ] - $borrow;

			if ( $j >= 0 ) {
				$digit -= (int) $b[ $j ];
				$j--;
			}

			if ( $digit < 0 ) {
				$digit += 10;
				$borrow = 1;
			} else {
				$borrow = 0;
			}

			$result = (string) $digit . $result;
			$i--;
		}

		$result = ltrim( $result, '0' );

		return '' === $result ? '0' : $result;
	}

	/**
	 * مقایسه دو رشته عدد صحیح با علامت.
	 *
	 * @param string $a اول.
	 * @param string $b دوم.
	 * @return int
	 */
	private static function compare_strings( $a, $b ) {
		$a_negative = '-' === $a[0];
		$b_negative = '-' === $b[0];

		$a_digits = $a_negative ? substr( $a, 1 ) : $a;
		$b_digits = $b_negative ? substr( $b, 1 ) : $b;

		$a_digits = ltrim( $a_digits, '0' );
		$b_digits = ltrim( $b_digits, '0' );

		$a_digits = '' === $a_digits ? '0' : $a_digits;
		$b_digits = '' === $b_digits ? '0' : $b_digits;

		if ( $a_negative !== $b_negative ) {
			return $a_negative ? -1 : 1;
		}

		$length_a = strlen( $a_digits );
		$length_b = strlen( $b_digits );

		if ( $length_a !== $length_b ) {
			$result = $length_a > $length_b ? 1 : -1;
			return $a_negative ? -$result : $result;
		}

		$result = strcmp( $a_digits, $b_digits );
		$result = 0 === $result ? 0 : ( $result > 0 ? 1 : -1 );

		return $a_negative ? -$result : $result;
	}

	/**
	 * بخش صحیح و اعشار یک عدد مقیاس‌شده را به رشته انسانی تبدیل می‌کند.
	 *
	 * @param mixed $scaled    مقدار مقیاس‌شده (رشته یا int).
	 * @param int   $scale     تعداد ارقام اعشار ذخیره‌شده.
	 * @param int   $decimals  تعداد ارقام اعشار نمایش.
	 * @return string
	 */
	public static function scaled_to_decimal_string( $scaled, $scale, $decimals ) {
		$scaled = self::normalize( $scaled );

		$negative = '-' === $scaled[0];
		$digits   = $negative ? substr( $scaled, 1 ) : $scaled;

		$scale = max( 0, (int) $scale );

		if ( 0 === $scale ) {
			return ( $negative && '0' !== $digits ? '-' : '' ) . $digits;
		}

		$digits = str_pad( $digits, $scale + 1, '0', STR_PAD_LEFT );

		$whole    = substr( $digits, 0, strlen( $digits ) - $scale );
		$fraction = substr( $digits, strlen( $digits ) - $scale );

		$whole = ltrim( $whole, '0' );
		$whole = '' === $whole ? '0' : $whole;

		$decimals = max( 0, min( $scale, (int) $decimals ) );

		if ( $decimals > 0 ) {
			$fraction = substr( $fraction, 0, $decimals );
			$fraction = rtrim( $fraction, '0' );

			if ( '' !== $fraction ) {
				$whole .= '.' . $fraction;
			}
		}

		return ( $negative && ! self::is_zero( $scaled ) ? '-' : '' ) . $whole;
	}

	/**
	 * افزودن جداکننده هزارگان به بخش صحیح یک رشته عددی.
	 *
	 * @param string $value     مقدار (می‌تواند اعشار و علامت داشته باشد).
	 * @param string $separator جداکننده.
	 * @return string
	 */
	public static function group( $value, $separator = ',' ) {
		$value     = (string) $value;
		$negative  = '' !== $value && '-' === $value[0];
		$unsigned  = $negative ? substr( $value, 1 ) : $value;
		$parts     = explode( '.', $unsigned, 2 );
		$whole     = isset( $parts[0] ) ? $parts[0] : '0';
		$fraction  = isset( $parts[1] ) ? $parts[1] : '';
		$grouped   = preg_replace( '/\B(?=(\d{3})+(?!\d))/', (string) $separator, $whole );

		$result = ( $negative ? '-' : '' ) . $grouped;

		if ( '' !== $fraction ) {
			$result .= '.' . $fraction;
		}

		return $result;
	}
}
