<?php
/**
 * تبدیل و قالب‌بندی تاریخ (میلادی برای ذخیره، شمسی برای نمایش).
 *
 * @package SparKnife_Ledger
 */

defined( 'ABSPATH' ) || exit;

/**
 * ابزارهای تاریخ.
 */
class Sparkknife_Ledger_Date {

	/**
	 * آیا نمایش شمسی فعال است؟
	 *
	 * @return bool
	 */
	public static function uses_jalali() {
		return 'jalali' === Sparkknife_Ledger_Settings::get( 'calendar', 'jalali' );
	}

	/**
	 * تجزیه ایمن تاریخ ذخیره‌شده به اجزای میلادی، بدون timestamp.
	 *
	 * دلیل: strtotime() روی PHP ۳۲ بیتی برای سال‌های دور (پیش از ۱۹۰۱ یا
	 * پس از ۲۰۳۸) هشدار «Epoch doesn't fit in a PHP integer» می‌دهد و
	 * تاریخ را به خط تیره تبدیل می‌کند. این تابع به timestamp نیاز ندارد.
	 *
	 * @param string $date تاریخ Y-m-d یا Y-m-d H:i:s.
	 * @return array{0:int,1:int,2:int,3:int,4:int}|null [سال, ماه, روز, ساعت, دقیقه] یا null.
	 */
	private static function split_stored_date( $date ) {
		$date = trim( (string) $date );

		if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2}))?/', $date, $m ) ) {
			return null;
		}

		$year  = (int) $m[1];
		$month = (int) $m[2];
		$day   = (int) $m[3];
		$hour  = isset( $m[4] ) ? (int) $m[4] : 0;
		$min   = isset( $m[5] ) ? (int) $m[5] : 0;

		if ( $year < 1 || ! checkdate( $month, $day, $year ) ) {
			return null;
		}

		return array( $year, $month, $day, $hour, $min );
	}

	/**
	 * تبدیل تاریخ میلادی به شمسی.
	 *
	 * پیاده‌سازی الگوریتم استاندارد (بر اساس روزهای جولین) بدون وابستگی خارجی.
	 *
	 * @param int $gy سال میلادی.
	 * @param int $gm ماه میلادی (۱ تا ۱۲).
	 * @param int $gd روز میلادی.
	 * @return array{0:int,1:int,2:int} [سال, ماه, روز] شمسی.
	 */
	public static function gregorian_to_jalali( $gy, $gm, $gd ) {
		$g_d_m = array( 0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334 );

		$gy2 = ( $gm > 2 ) ? ( $gy + 1 ) : $gy;

		$days = 355666 + ( 365 * $gy ) + intdiv( $gy2 + 3, 4 ) - intdiv( $gy2 + 99, 100 ) + intdiv( $gy2 + 399, 400 )
			+ $gd + $g_d_m[ $gm - 1 ];

		$jy = -1595 + ( 33 * intdiv( $days, 12053 ) );
		$days %= 12053;

		$jy += 4 * intdiv( $days, 1461 );
		$days %= 1461;

		if ( $days > 365 ) {
			$jy  += intdiv( $days - 1, 365 );
			$days = ( $days - 1 ) % 365;
		}

		if ( $days < 186 ) {
			$jm = 1 + intdiv( $days, 31 );
			$jd = 1 + ( $days % 31 );
		} else {
			$jm = 7 + intdiv( $days - 186, 30 );
			$jd = 1 + ( ( $days - 186 ) % 30 );
		}

		return array( (int) $jy, (int) $jm, (int) $jd );
	}

	/**
	 * تبدیل تاریخ شمسی به میلادی.
	 *
	 * @param int $jy سال شمسی.
	 * @param int $jm ماه شمسی (۱ تا ۱۲).
	 * @param int $jd روز شمسی.
	 * @return array{0:int,1:int,2:int} [سال, ماه, روز] میلادی.
	 */
	public static function jalali_to_gregorian( $jy, $jm, $jd ) {
		$jy += 1595;

		$days = -355668 + ( 365 * $jy ) + ( intdiv( $jy, 33 ) * 8 ) + intdiv( ( $jy % 33 ) + 3, 4 ) + $jd
			+ ( ( $jm < 7 ) ? ( ( $jm - 1 ) * 31 ) : ( ( ( $jm - 7 ) * 30 ) + 186 ) );

		$gy   = 400 * intdiv( $days, 146097 );
		$days %= 146097;

		if ( $days > 36524 ) {
			$gy  += 100 * intdiv( --$days, 36524 );
			$days %= 36524;

			if ( $days >= 365 ) {
				$days++;
			}
		}

		$gy  += 4 * intdiv( $days, 1461 );
		$days %= 1461;

		if ( $days > 365 ) {
			$gy  += intdiv( $days - 1, 365 );
			$days = ( $days - 1 ) % 365;
		}

		$gd = $days + 1;

		$sal_a = array(
			0,
			31,
			( ( 0 === $gy % 4 && 0 !== $gy % 100 ) || 0 === $gy % 400 ) ? 29 : 28,
			31,
			30,
			31,
			30,
			31,
			31,
			30,
			31,
			30,
			31,
		);

		for ( $gm = 1; $gm <= 12; $gm++ ) {
			if ( $gd <= $sal_a[ $gm ] ) {
				break;
			}
			$gd -= $sal_a[ $gm ];
		}

		return array( (int) $gy, (int) $gm, (int) $gd );
	}

	/**
	 * اعتبارسنجی تاریخ میلادی و بازگرداندن آن به قالب Y-m-d.
	 *
	 * @param string $date ورودی.
	 * @return string|WP_Error
	 */
	public static function validate_gregorian( $date ) {
		$date = trim( (string) $date );

		if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $date, $m ) ) {
			return new WP_Error( 'sparkknife_ledger_invalid_date', __( 'قالب تاریخ معتبر نیست.', 'sparkknife-ledger' ) );
		}

		$year  = (int) $m[1];
		$month = (int) $m[2];
		$day   = (int) $m[3];

		if ( ! checkdate( $month, $day, $year ) || $year < 1900 || $year > 2200 ) {
			return new WP_Error( 'sparkknife_ledger_invalid_date', __( 'تاریخ وارد‌شده وجود ندارد.', 'sparkknife-ledger' ) );
		}

		return sprintf( '%04d-%02d-%02d', $year, $month, $day );
	}

	/**
	 * اعتبارسنجی تاریخ شمسی و تبدیل به میلادی.
	 *
	 * @param string $date ورودی مانند 1403-05-12.
	 * @return string|WP_Error تاریخ میلادی Y-m-d.
	 */
	public static function jalali_to_date( $date ) {
		$date = trim( Sparkknife_Ledger_Money::to_latin_digits( (string) $date ) );

		if ( ! preg_match( '/^(\d{4})[-\/](\d{1,2})[-\/](\d{1,2})$/', $date, $m ) ) {
			return new WP_Error( 'sparkknife_ledger_invalid_date', __( 'قالب تاریخ شمسی معتبر نیست (نمونه: ۱۴۰۳/۰۵/۱۲).', 'sparkknife-ledger' ) );
		}

		$jy = (int) $m[1];
		$jm = (int) $m[2];
		$jd = (int) $m[3];

		if ( $jm < 1 || $jm > 12 || $jd < 1 ) {
			return new WP_Error( 'sparkknife_ledger_invalid_date', __( 'تاریخ شمسی وارد‌شده وجود ندارد.', 'sparkknife-ledger' ) );
		}

		$max_day = ( $jm <= 6 ) ? 31 : ( ( $jm <= 11 ) ? 30 : ( self::is_jalali_leap( $jy ) ? 30 : 29 ) );

		if ( $jd > $max_day ) {
			return new WP_Error( 'sparkknife_ledger_invalid_date', __( 'روز وارد‌شده در این ماه شمسی وجود ندارد.', 'sparkknife-ledger' ) );
		}

		list( $gy, $gm, $gd ) = self::jalali_to_gregorian( $jy, $jm, $jd );

		return self::validate_gregorian( sprintf( '%04d-%02d-%02d', $gy, $gm, $gd ) );
	}

	/**
	 * آیا سال شمسی کبیسه است؟
	 *
	 * @param int $jy سال شمسی.
	 * @return bool
	 */
	public static function is_jalali_leap( $jy ) {
		/*
		 * چرخه کبیسه شمسی ۳۳ ساله است و بر خودِ سال شمسی اعمال می‌شود،
		 * نه بر سال جابه‌جاشده. پیش‌تر اینجا ۱۵۹۵ به سال اضافه می‌شد که
		 * باقی‌مانده ۳۳ را کاملاً عوض می‌کرد و نتیجه‌اش برای ۱۴۵ سال از
		 * هر ۳۰۰ سال برعکس بود — از جمله ۱۴۰۳ که کبیسه است.
		 *
		 * فرمول زیر برای سال‌های ۱۲۰۰ تا ۱۵۹۹ با تقویم رسمی مطابق است.
		 */
		$rem = ( (int) $jy % 33 );

		if ( $rem < 0 ) {
			$rem += 33;
		}

		return in_array( $rem, array( 1, 5, 9, 13, 17, 22, 26, 30 ), true );
	}

	/**
	 * تبدیل ورودی تاریخ (شمسی یا میلادی بر اساس تنظیمات) به تاریخ ذخیره‌سازی میلادی.
	 *
	 * @param string $date ورودی کاربر.
	 * @return string|WP_Error
	 */
	public static function to_storage( $date ) {
		$date = trim( Sparkknife_Ledger_Money::to_latin_digits( (string) $date ) );

		if ( '' === $date ) {
			return new WP_Error( 'sparkknife_ledger_empty_date', __( 'تاریخ الزامی است.', 'sparkknife-ledger' ) );
		}

		if ( self::uses_jalali() ) {
			// اگر کاربر تاریخ میلادی کامل وارد کند، همان پذیرفته می‌شود.
			if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) && (int) substr( $date, 0, 4 ) > 1500 ) {
				return self::validate_gregorian( $date );
			}

			return self::jalali_to_date( $date );
		}

		$normalized = str_replace( '/', '-', $date );
		return self::validate_gregorian( $normalized );
	}

	/**
	 * قالب‌بندی تاریخ برای نمایش.
	 *
	 * @param string $date      تاریخ میلادی ذخیره‌شده.
	 * @param bool   $with_time نمایش ساعت.
	 * @return string
	 */
	public static function format( $date, $with_time = false ) {
		if ( empty( $date ) || 0 === strpos( (string) $date, '0000-00-00' ) ) {
			return '—';
		}

		$parts = self::split_stored_date( $date );

		if ( null === $parts ) {
			return '—';
		}

		list( $gy, $gm, $gd, $hour, $minute ) = $parts;

		$time_part = $with_time ? sprintf( ' %02d:%02d', $hour, $minute ) : '';

		if ( self::uses_jalali() ) {
			list( $jy, $jm, $jd ) = self::gregorian_to_jalali( $gy, $gm, $gd );

			$formatted = sprintf( '%04d/%02d/%02d', $jy, $jm, $jd );
		} else {
			$formatted = sprintf( '%04d/%02d/%02d', $gy, $gm, $gd );
		}

		$formatted .= $time_part;

		if ( Sparkknife_Ledger_Money::uses_persian_digits() ) {
			$formatted = Sparkknife_Ledger_Money::to_persian_digits( $formatted );
		}

		return $formatted;
	}

	/**
	 * مقدار امروز به تاریخ میلادی.
	 *
	 * @return string
	 */
	public static function today() {
		return current_time( 'Y-m-d' );
	}

	/**
	 * سطل‌های ماهانه برای نمودار، بر اساس تقویم فعال.
	 *
	 * برای هر ماه یک بازه میلادی (from/to) و یک برچسب خوانا برمی‌گرداند.
	 * ترتیب از قدیم به جدید است.
	 *
	 * @param int $months تعداد ماه (۱ تا ۲۴).
	 * @return array<int, array{key: string, label: string, from: string, to: string}>
	 */
	public static function month_buckets( $months = 6 ) {
		$months = max( 1, min( 24, (int) $months ) );
		$today  = current_time( 'Y-m-d' );

		$years  = (int) substr( $today, 0, 4 );
		$month  = (int) substr( $today, 5, 2 );

		$buckets = array();

		if ( ! self::uses_jalali() ) {
			for ( $i = $months - 1; $i >= 0; $i-- ) {
				$anchor  = sprintf( '%04d-%02d-01', $years, $month );
				$from_ts = strtotime( $anchor . ' -' . $i . ' months' );

				if ( false === $from_ts ) {
					continue;
				}

				$from = gmdate( 'Y-m-01', $from_ts );

				$buckets[] = array(
					'key'   => gmdate( 'Y-m', $from_ts ),
					'label' => self::format( $from ),
					'from'  => $from,
					'to'    => gmdate( 'Y-m-t', $from_ts ),
				);
			}

			return $buckets;
		}

		list( $jy, $jm ) = self::gregorian_to_jalali( $years, $month, (int) substr( $today, 8, 2 ) );

		for ( $i = $months - 1; $i >= 0; $i-- ) {
			$target_year  = (int) $jy;
			$target_month = (int) $jm - $i;

			while ( $target_month < 1 ) {
				$target_month += 12;
				--$target_year;
			}

			while ( $target_month > 12 ) {
				$target_month -= 12;
				++$target_year;
			}

			if ( $target_month <= 6 ) {
				$length = 31;
			} elseif ( $target_month <= 11 ) {
				$length = 30;
			} else {
				$length = self::is_jalali_leap( $target_year ) ? 30 : 29;
			}

			list( $fy, $fm, $fd ) = self::jalali_to_gregorian( $target_year, $target_month, 1 );
			list( $ty, $tm, $td ) = self::jalali_to_gregorian( $target_year, $target_month, $length );

			$buckets[] = array(
				'key'   => sprintf( '%04d-%02d', $target_year, $target_month ),
				'label' => sprintf(
					/* translators: 1: month name, 2: four-digit year */
					__( '%1$s %2$s', 'sparkknife-ledger' ),
					self::jalali_month_name( $target_month ),
					self::to_persian_digits( (string) $target_year )
				),
				'from'  => sprintf( '%04d-%02d-%02d', $fy, $fm, $fd ),
				'to'    => sprintf( '%04d-%02d-%02d', $ty, $tm, $td ),
			);
		}

		return $buckets;
	}

	/**
	 * تبدیل تاریخ ذخیره‌شده به کلید ماه تقویم فعال.
	 *
	 * @param string $date تاریخ Y-m-d.
	 * @return string کلید ماه (YYYY-MM میلادی یا YYYY-MM شمسی).
	 */
	public static function storage_to_month_key( $date ) {
		$date = substr( trim( (string) $date ), 0, 10 );

		if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $date, $m ) ) {
			return '';
		}

		if ( ! self::uses_jalali() ) {
			return $m[1] . '-' . $m[2];
		}

		list( $jy, $jm ) = self::gregorian_to_jalali( (int) $m[1], (int) $m[2], (int) $m[3] );

		return sprintf( '%04d-%02d', $jy, $jm );
	}

	/**
	 * ارقام فارسی (بدون وابستگی به Money).
	 *
	 * @param string $value رشته.
	 * @return string
	 */
	private static function to_persian_digits( $value ) {
		if ( class_exists( 'Sparkknife_Ledger_Money' ) ) {
			return Sparkknife_Ledger_Money::to_persian_digits( $value );
		}

		return (string) $value;
	}

	/**
	 * آیا تاریخ داده‌شده در آینده است؟
	 *
	 * مقایسه رشته‌ای است، نه با `strtotime()`.
	 *
	 * روی PHP ۳۲ بیتی `strtotime()` برای تاریخ‌های بعد از ۱۹ ژانویه ۲۰۳۸
	 * مقدار `false` می‌دهد و `false > int` هم همیشه نادرست است؛ نتیجه این
	 * بود که می‌شد تراکنشی با تاریخ ۲۰۴۰ ثبت کرد و بررسی «تاریخ آینده»
	 * بی‌صدا از کار می‌افتاد. قالب `Y-m-d` با طول ثابت، مرتب‌سازی لغوی
	 * درستی دارد، پس مقایسه مستقیم رشته کافی و مصون از سرریز است.
	 *
	 * یک روز رواداری دارد: ثبت تراکنش با تاریخ فردا (اختلاف منطقه زمانی یا
	 * ثبت شبانه) مجاز است.
	 *
	 * @param string $date تاریخ به قالب Y-m-d.
	 * @return bool
	 */
	public static function is_future( $date ) {
		$date = substr( trim( (string) $date ), 0, 10 );

		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			return false;
		}

		$tomorrow = self::shift_days( self::today(), 1 );

		return '' !== $tomorrow && strcmp( $date, $tomorrow ) > 0;
	}

	/**
	 * جابه‌جایی تاریخ به اندازه چند روز، بدون `strtotime()`.
	 *
	 * @param string $date تاریخ Y-m-d.
	 * @param int    $days تعداد روز (می‌تواند منفی باشد).
	 * @return string تاریخ جدید یا رشته خالی در صورت نامعتبر بودن ورودی.
	 */
	private static function shift_days( $date, $days ) {
		if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', (string) $date, $m ) ) {
			return '';
		}

		$year  = (int) $m[1];
		$month = (int) $m[2];
		$day   = (int) $m[3];

		if ( ! checkdate( $month, $day, $year ) ) {
			return '';
		}

		$day += (int) $days;

		/*
		 * نرمال‌سازی ساده: روز ممکن است از بازه ماه بیرون بزند. چون فقط
		 * به جابه‌جایی یک‌روزه نیاز داریم، همین اندازه کافی است.
		 */
		if ( $day < 1 ) {
			--$month;
			if ( $month < 1 ) {
				$month = 12;
				--$year;
			}
			$day += self::days_in_month( $year, $month );
		} elseif ( $day > self::days_in_month( $year, $month ) ) {
			$day -= self::days_in_month( $year, $month );
			++$month;
			if ( $month > 12 ) {
				$month = 1;
				++$year;
			}
		}

		return sprintf( '%04d-%02d-%02d', $year, $month, $day );
	}

	/**
	 * تعداد روزهای یک ماه میلادی.
	 *
	 * @param int $year  سال.
	 * @param int $month ماه.
	 * @return int
	 */
	private static function days_in_month( $year, $month ) {
		$days = array( 31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31 );

		if ( 2 === (int) $month && self::is_gregorian_leap( $year ) ) {
			return 29;
		}

		return isset( $days[ $month - 1 ] ) ? $days[ $month - 1 ] : 30;
	}

	/**
	 * آیا سال میلادی کبیسه است؟
	 *
	 * @param int $year سال.
	 * @return bool
	 */
	private static function is_gregorian_leap( $year ) {
		$year = (int) $year;

		return ( 0 === $year % 4 && 0 !== $year % 100 ) || 0 === $year % 400;
	}

	/**
	 * تاریخ و زمان فعلی برای ثبت در دیتابیس.
	 *
	 * @return string
	 */
	public static function now() {
		return current_time( 'mysql' );
	}

	/**
	 * مقدار نمایشی امروز برای فرم (شمسی یا میلادی).
	 *
	 * @return string
	 */
	public static function today_for_input() {
		$today = self::today();

		if ( self::uses_jalali() ) {
			list( $jy, $jm, $jd ) = self::gregorian_to_jalali(
				(int) substr( $today, 0, 4 ),
				(int) substr( $today, 5, 2 ),
				(int) substr( $today, 8, 2 )
			);

			return sprintf( '%04d/%02d/%02d', $jy, $jm, $jd );
		}

		return str_replace( '-', '/', $today );
	}

	/**
	 * تبدیل تاریخ ذخیره‌شده به مقدار نمایشی فرم.
	 *
	 * @param string $date تاریخ میلادی Y-m-d.
	 * @return string
	 */
	public static function format_for_input( $date ) {
		if ( empty( $date ) || 0 === strpos( (string) $date, '0000-00-00' ) ) {
			return '';
		}

		$parts = self::split_stored_date( $date );

		if ( null === $parts ) {
			return '';
		}

		list( $gy, $gm, $gd ) = $parts;

		if ( self::uses_jalali() ) {
			list( $jy, $jm, $jd ) = self::gregorian_to_jalali( $gy, $gm, $gd );

			return sprintf( '%04d/%02d/%02d', $jy, $jm, $jd );
		}

		return sprintf( '%04d/%02d/%02d', $gy, $gm, $gd );
	}

	/**
	 * نام ماه شمسی.
	 *
	 * @param int $month شماره ماه.
	 * @return string
	 */
	public static function jalali_month_name( $month ) {
		$months = array(
			__( 'فروردین', 'sparkknife-ledger' ),
			__( 'اردیبهشت', 'sparkknife-ledger' ),
			__( 'خرداد', 'sparkknife-ledger' ),
			__( 'تیر', 'sparkknife-ledger' ),
			__( 'مرداد', 'sparkknife-ledger' ),
			__( 'شهریور', 'sparkknife-ledger' ),
			__( 'مهر', 'sparkknife-ledger' ),
			__( 'آبان', 'sparkknife-ledger' ),
			__( 'آذر', 'sparkknife-ledger' ),
			__( 'دی', 'sparkknife-ledger' ),
			__( 'بهمن', 'sparkknife-ledger' ),
			__( 'اسفند', 'sparkknife-ledger' ),
		);

		$index = (int) $month - 1;

		return isset( $months[ $index ] ) ? $months[ $index ] : '';
	}
}
