<?php
/**
 * پاک‌سازی متمرکز ورودی‌ها.
 *
 * @package SparKnife_Ledger
 */

defined( 'ABSPATH' ) || exit;

/**
 * پاک‌سازی ورودی‌های فرم.
 */
class Sparkknife_Ledger_Sanitizer {

	/**
	 * یکسان‌سازی نویسه‌های فارسی برای جستجو.
	 *
	 * کاربران ایرانی گاهی با کیبورد عربی یا از روی متن کپی‌شده تایپ
	 * می‌کنند و «ي» (U+064A) و «ك» (U+0643) عربی به‌جای «ی» (U+06CC) و
	 * «ک» (U+06A9) فارسی وارد می‌شود. این دو در دیتابیس نویسه‌های
	 * متفاوتی هستند، بنابراین جستجوی «علي» شخصی با نام «علی» را پیدا
	 * نمی‌کرد.
	 *
	 * همچنین «ة» به «ه» و «ۀ» به «ه» تبدیل می‌شود تا نام‌هایی مثل
	 * «فاطمه» با هر املا پیدا شوند.
	 *
	 * این تابع فقط برای مقایسه استفاده می‌شود، نه برای ذخیره‌سازی؛
	 * نامی که کاربر تایپ کرده همان‌طور که هست ذخیره می‌شود.
	 *
	 * @param string $value متن.
	 * @return string متن یکسان‌شده.
	 */
	public static function normalize_persian( $value ) {
		$map = array(
			"ي" => "ی", // ي عربی
			"ى" => "ی", // ى الف مقصوره
			"ك" => "ک", // ك عربی
			"ة" => "ه", // ة
			"ۀ" => "ه", // ۀ
			"ە" => "ه", // ە
			"ئ" => "ی", // ئ (همزه روی ی) → ی
		);

		$value = strtr( (string) $value, $map );

		/*
		 * نیم‌فاصله (ZWNJ) و سایر فاصله‌های یونیکد حذف می‌شوند تا
		 * «می‌رود» و «میرود» یکسان یابیده شوند.
		 */
		$value = preg_replace( '/[\x{200B}-\x{200F}\x{FEFF}]/u', '', (string) $value );

		/*
		 * ارقام فارسی و عربی به لاتین تبدیل می‌شوند.
		 *
		 * شماره تلفن‌ها با ارقام لاتین ذخیره می‌شوند، ولی کیبورد فارسی
		 * ارقام فارسی تولید می‌کند؛ بدون این تبدیل، جستجوی «۰۹۱۲» هیچ
		 * نتیجه‌ای نمی‌داد در حالی که «0912» کار می‌کرد.
		 */
		$value = Sparkknife_Ledger_Money::to_latin_digits( (string) $value );

		return (string) $value;
	}

	/**
	 * کلید مرتب‌سازی الفبایی فارسی.
	 *
	 * `strcmp` روی UTF-8 مقایسه بایتی می‌کند و برای فارسی ترتیب نادرست
	 * می‌دهد: «پ» بعد از «د» می‌آید و «چ» بعد از «ی». این تابع هر
	 * نویسه را به یک عدد بر اساس ترتیب الفبای فارسی تبدیل می‌کند.
	 *
	 * @param string $value متن.
	 * @return string کلیدی که با strcmp مرتب‌سازی الفبایی فارسی می‌دهد.
	 */
	public static function persian_sort_key( $value ) {
		static $order = null;

		if ( null === $order ) {
			$order = array(
			"آ" => 0,
			"ا" => 1,
			"أ" => 2,
			"إ" => 3,
			"ء" => 4,
			"ب" => 5,
			"پ" => 6,
			"ت" => 7,
			"ث" => 8,
			"ج" => 9,
			"چ" => 10,
			"ح" => 11,
			"خ" => 12,
			"د" => 13,
			"ذ" => 14,
			"ر" => 15,
			"ز" => 16,
			"ژ" => 17,
			"س" => 18,
			"ش" => 19,
			"ص" => 20,
			"ض" => 21,
			"ط" => 22,
			"ظ" => 23,
			"ع" => 24,
			"غ" => 25,
			"ف" => 26,
			"ق" => 27,
			"ک" => 28,
			"گ" => 29,
			"ل" => 30,
			"م" => 31,
			"ن" => 32,
			"و" => 33,
			"ه" => 34,
			"ی" => 35,
			"ئ" => 36,
			);
		}

		$value = self::normalize_persian( $value );
		$value = function_exists( 'mb_strtolower' ) ? mb_strtolower( $value, 'UTF-8' ) : strtolower( $value );

		$key   = '';
		$chars = preg_split( '//u', $value, -1, PREG_SPLIT_NO_EMPTY );

		foreach ( (array) $chars as $char ) {
			if ( isset( $order[ $char ] ) ) {
				// سه رقم برای هر نویسه تا مقایسه بایتی با ترتیب عددی هم‌خوان باشد.
				$key .= sprintf( '%03d', $order[ $char ] );
			} else {
				$key .= sprintf( '%03d', 900 + ( ord( $char ) % 99 ) );
			}
		}

		return $key;
	}

	/**
	 * تولید نسخه‌های جایگزین یک عبارت جستجو برای جستجوی فارسی.
	 *
	 * `LIKE` در MySQL مقایسه بایتی می‌کند و «%s» فارسی و «%s» عربی دو
	 * نویسه متفاوت‌اند؛ بنابراین جستجوی «علي» شخصی با نام «علی» را پیدا
	 * نمی‌کرد. این تابع همه ترکیب‌های ممکن را می‌سازد تا در کوئری با OR
	 * استفاده شوند.
	 *
	 * @param string $search عبارت جستجو.
	 * @return array<int, string> نسخه‌ها؛ همیشه شامل خودِ عبارت.
	 */
	public static function search_variants( $search ) {
		$search = (string) $search;

		if ( '' === trim( $search ) ) {
			return array();
		}

		/*
		 * عبارت جستجو ابتدا یکسان‌سازی می‌شود (ی/ک عربی، ارقام فارسی،
		 * نیم‌فاصله). سپس نسخه‌های جایگزین نویسه‌ای از آن ساخته می‌شوند.
		 */
		$search   = self::normalize_persian( $search );
		$variants = array( $search );

		/*
		 * هر گروه، نویسه‌هایی است که کاربر ممکن است به‌جای هم تایپ کند.
		 * هر دو جهت پوشش داده می‌شود تا جستجو برای داده‌های قدیمی که با
		 * نویسه عربی ذخیره شده‌اند هم کار کند.
		 */
		$groups = array(
			array( 'ی', 'ي', 'ى' ), // ی فارسی، ي عربی، ى
			array( 'ک', 'ك' ),      // ک فارسی، ك عربی
			array( 'ه', 'ة', 'ۀ' ), // ه، ة، ۀ
		);

		foreach ( $groups as $group ) {
			$expanded = array();

			foreach ( $variants as $variant ) {
				$expanded[] = $variant;

				foreach ( $group as $from ) {
					if ( false === strpos( $variant, $from ) ) {
						continue;
					}

					foreach ( $group as $to ) {
						if ( $to !== $from ) {
							$expanded[] = str_replace( $from, $to, $variant );
						}
					}
				}
			}

			$variants = array_values( array_unique( $expanded ) );
		}

		/*
		 * نسخه با ارقام فارسی هم تولید می‌شود تا رکوردهای قدیمی که شماره
		 * را با ارقام فارسی ذخیره کرده‌اند پیدا شوند. جستجو با ارقام
		 * لاتین و فارسی هر دو باید یک نتیجه بدهد.
		 */
		$with_persian_digits = array();

		foreach ( $variants as $variant ) {
			$with_persian_digits[] = Sparkknife_Ledger_Money::to_persian_digits( $variant );
			$with_persian_digits[] = Sparkknife_Ledger_Money::to_arabic_digits( $variant );
		}

		$variants = array_values( array_unique( array_merge( $variants, $with_persian_digits ) ) );

		/*
		 * یکسان‌سازی می‌تواند **همهٔ** عبارت را حذف کند.
		 *
		 * نیم‌فاصله (`U+200C`) و دیگر نویسه‌های کنترلی قالب‌بندی توسط
		 * `normalize_persian()` حذف می‌شوند. اگر کاربر فقط نیم‌فاصله
		 * بفرستد، نتیجه رشتهٔ خالی است و `LIKE '%%'` **همهٔ** رکوردها را
		 * برمی‌گرداند — یعنی جستجوی «هیچ» جواب «همه چیز» می‌داد.
		 *
		 * نسخه‌های خالی کنار گذاشته می‌شوند؛ اگر چیزی نماند، آرایهٔ خالی
		 * می‌ماند و لایهٔ بالاتر هیچ شرط جستجویی نمی‌سازد.
		 */
		$variants = array_values(
			array_filter(
				$variants,
				static function ( $variant ) {
					return '' !== trim( (string) $variant );
				}
			)
		);

		// سقف ایمنی تا کوئری بیش از حد بزرگ نشود.
		return array_slice( $variants, 0, 12 );
	}

	/**
	 * متن ساده تک‌خطی.
	 *
	 * این تابع `wp_unslash()` نمی‌زند؛ حذف بک‌اسلش وظیفه فراخوان است و
	 * باید دقیقاً یک بار، روی ابرگلوبال خام، انجام شود.
	 *
	 * پیش‌تر اینجا هم `wp_unslash()` بود و کنترلر هم آن را صدا می‌زد، پس
	 * نامی مثل «بک‌اسلش\دار» دو بار unslash می‌شد و بک‌اسلشش **بی‌صدا**
	 * حذف می‌گشت. کاربر نام را درست وارد می‌کرد و در فهرست چیز دیگری
	 * می‌دید — بدون هیچ پیامی.
	 *
	 * @param mixed $value ورودی (بک‌اسلش‌هایش از قبل حذف شده).
	 * @param int   $max_length حداکثر طول.
	 * @return string
	 */
	public static function text( $value, $max_length = 190 ) {
		$value = sanitize_text_field( (string) $value );
		$value = self::strip_invisible( $value );
		return self::limit( $value, $max_length );
	}

	/**
	 * متن چندخطی.
	 *
	 * مانند `text()`، اینجا هم `wp_unslash()` زده نمی‌شود.
	 *
	 * @param mixed $value ورودی (بک‌اسلش‌هایش از قبل حذف شده).
	 * @param int   $max_length حداکثر طول.
	 * @return string
	 */
	public static function textarea( $value, $max_length = 2000 ) {
		$value = sanitize_textarea_field( (string) $value );
		$value = self::strip_invisible( $value, true );
		return self::limit( $value, $max_length );
	}

	/**
	 * حذف نویسه‌های کنترلی و نامرئی.
	 *
	 * `sanitize_text_field()` نویسه‌های کنترلی C0 را (به‌جز tab و newline)
	 * نگه می‌دارد، و نویسه‌های بی‌عرض مثل ZWNJ و ZWSP هم دست‌نخورده رد
	 * می‌شوند. نتیجه این بود که می‌شد شخصی با نام «» یا فقط
	 * نیم‌فاصله ساخت: فیلد «نام الزامی» قبول می‌شد ولی در فهرست یک ردیف
	 * خالی دیده می‌شد که نه می‌شد تشخیصش داد نه جستجوش کرد.
	 *
	 * نیم‌فاصله در وسط نام معتبر است («می‌رود»)، پس فقط وقتی حذف می‌شود
	 * که کل رشته از نویسه‌های نامرئی ساخته شده باشد.
	 *
	 * @param string $value       ورودی.
	 * @param bool   $keep_breaks حفظ خط جدید (برای textarea).
	 * @return string
	 */
	private static function strip_invisible( $value, $keep_breaks = false ) {
		$value = (string) $value;

		// نویسه‌های کنترلی C0 و C1، به‌جز tab و (در صورت اجازه) newline.
		$pattern = $keep_breaks ? '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/' : '/[\x00-\x1F\x7F]/';
		$value   = preg_replace( $pattern, '', $value );

		/*
		 * اگر پس از حذف فاصله‌ها و نویسه‌های بی‌عرض چیزی نمانده باشد،
		 * رشته خالی برگردانده می‌شود تا اعتبارسنجی «الزامی» بگیرد.
		 */
		$visible = preg_replace( '/[\s\x{200B}-\x{200F}\x{FEFF}\x{00A0}]+/u', '', (string) $value );

		if ( '' === $visible ) {
			return '';
		}

		return (string) $value;
	}

	/**
	 * شناسه عددی مثبت.
	 *
	 * @param mixed $value ورودی.
	 * @return int
	 */
	public static function id( $value ) {
		return absint( is_scalar( $value ) ? $value : 0 );
	}

	/**
	 * کلید (حروف کوچک، خط تیره).
	 *
	 * @param mixed $value ورودی.
	 * @return string
	 */
	public static function key( $value ) {
		return sanitize_key( wp_unslash( (string) $value ) );
	}

	/**
	 * شماره تماس: ارقام لاتین، فاصله، خط تیره و +.
	 *
	 * @param mixed $value ورودی.
	 * @return string
	 */
	public static function phone( $value ) {
		$value = Sparkknife_Ledger_Money::to_latin_digits( (string) $value );
		$value = preg_replace( '/[^0-9+\-\s()]/', '', $value );
		$value = trim( preg_replace( '/\s+/', ' ', (string) $value ) );

		return self::limit( $value, 40 );
	}

	/**
	 * تاریخ ورودی (پاک‌سازی سبک؛ اعتبارسنجی در Validator).
	 *
	 * @param mixed $value ورودی.
	 * @return string
	 */
	public static function date( $value ) {
		$value = Sparkknife_Ledger_Money::to_latin_digits( (string) $value );
		$value = preg_replace( '/[^0-9\/\-]/', '', $value );

		return self::limit( (string) $value, 10 );
	}

	/**
	 * فیلد ورودی مبلغ (پاک‌سازی سبک؛ تبدیل در Money).
	 *
	 * @param mixed $value ورودی.
	 * @return string
	 */
	public static function amount( $value ) {
		$value = Sparkknife_Ledger_Money::to_latin_digits( (string) $value );

		/*
		 * منفی‌های یونیکد پیش از پاک‌سازی به منفی ASCII تبدیل می‌شوند.
		 * در غیر این صورت در همان مرحله حذف می‌شدند و «−۵» به «۵» تبدیل
		 * می‌شد؛ یعنی مبلغ منفی، به‌عنوان مبلغ مثبت ثبت می‌شد.
		 */
		$value = str_replace( array( '−', '–', '—', '﹣' ), '-', (string) $value );

		$value = str_replace( array( '٫', '،', '٬' ), array( '.', ',', '' ), $value );
		$value = preg_replace( '/[^0-9.,\s\-]/', '', (string) $value );

		return self::limit( $value, 30 );
	}

	/**
	 * طول رشته بر حسب نویسه (نه بایت).
	 *
	 * بدون mbstring، `strlen()` بایت می‌شمارد و یک نام فارسی سه‌حرفی
	 * ۶ بایت است؛ هر مقایسه‌ای که با `strlen()` روی متن فارسی انجام شود
	 * نتیجه نادرست می‌دهد. اینجا شمارش نویسه‌ای با `preg_match_all` انجام
	 * می‌شود که به mbstring نیاز ندارد.
	 *
	 * @param string $value رشته.
	 * @return int
	 */
	public static function length( $value ) {
		$value = (string) $value;

		if ( '' === $value ) {
			return 0;
		}

		if ( function_exists( 'mb_strlen' ) ) {
			return (int) mb_strlen( $value, 'UTF-8' );
		}

		$count = preg_match_all( '/./us', $value );

		return false === $count ? strlen( $value ) : (int) $count;
	}

	/**
	 * کوتاه کردن ایمن رشته.
	 *
	 * @param string $value رشته.
	 * @param int    $max_length حداکثر طول.
	 * @return string
	 */
	public static function limit( $value, $max_length ) {
		$value      = (string) $value;
		$max_length = absint( $max_length );

		if ( $max_length <= 0 || '' === $value ) {
			return $value;
		}

		if ( function_exists( 'mb_strlen' ) ) {
			return mb_strlen( $value ) > $max_length ? mb_substr( $value, 0, $max_length ) : $value;
		}

		/*
		 * بدون mbstring: برش بایتی ممنوع.
		 *
		 * `substr()` وسط یک نویسه UTF-8 چندبایتی می‌برد و رشته خراب
		 * می‌سازد. اینجا نویسه‌ها با الگوی یونیکد شمرده و بریده می‌شوند.
		 */
		$characters = preg_split( '//u', $value, -1, PREG_SPLIT_NO_EMPTY );

		if ( false === $characters ) {
			// اگر حتی تجزیه یونیکد هم نشد، رشته از قبل خراب است.
			return $value;
		}

		if ( count( $characters ) <= $max_length ) {
			return $value;
		}

		return implode( '', array_slice( $characters, 0, $max_length ) );
	}

	/**
	 * نویسهٔ فرار برای `LIKE`.
	 *
	 * `!` انتخاب شد چون در SQL هیچ معنای خاصی ندارد. بک‌اسلش گزینهٔ
	 * بدی است: MySQL و SQLite هر دو آن را در رشته‌های SQL تفسیر می‌کنند
	 * و چند لایهٔ نقل‌قول (PHP → prepare → موتور) آن را ناپایدار
	 * می‌کند.
	 *
	 * @var string
	 */
	const LIKE_ESCAPE = '!';

	/**
	 * آماده‌سازی عبارت برای `LIKE`.
	 *
	 * روی `$wpdb->esc_like()` تکیه نمی‌کنیم. آن تابع فقط در درایور MySQL
	 * درست کار می‌کند؛ درایور SQLite وردپرس (SQLite Database
	 * Integration) همان تابع را عملاً بی‌اثر پیاده کرده است:
	 *
	 *     esc_like('%')   → '%'      (نه '\%')
	 *     esc_like('_')   → '_'
	 *     esc_like('a%b') → 'a%b'
	 *
	 * نتیجه روی SQLite این بود که جستجوی `%` در همهٔ فهرست‌ها **همهٔ**
	 * ردیف‌ها را برمی‌گرداند، `_` هر نویسه‌ای را می‌گرفت، و پیشوند
	 * شمارهٔ تراکنش که `_` دارد شمارهٔ بعدی را از ردیف اشتباه حساب
	 * می‌کرد.
	 *
	 * استفاده:
	 *
	 *     $sql = "… WHERE name LIKE %s ESCAPE '" . Sanitizer::LIKE_ESCAPE . "'";
	 *     $wpdb->prepare( $sql, Sanitizer::like_pattern( $term ) );
	 *
	 * @param string $term عبارت خام کاربر.
	 * @return string الگوی `LIKE` آماده برای `prepare()`.
	 */
	public static function like_pattern( $term ) {
		return '%' . self::like_escape_wildcards( $term ) . '%';
	}

	/**
	 * همان `like_pattern()` برای تطبیق از ابتدا (پیشوندها).
	 *
	 * @param string $term عبارت خام.
	 * @return string
	 */
	public static function like_prefix( $term ) {
		return self::like_escape_wildcards( $term ) . '%';
	}

	/**
	 * جانشینی wildcard ها با نسخهٔ escape‌شده.
	 *
	 * ترتیب مهم است: نویسهٔ فرار **اول** escape می‌شود تا نتیجهٔ
	 * escape‌های بعدی دوباره escape نشود.
	 *
	 * @param string $term عبارت خام.
	 * @return string
	 */
	private static function like_escape_wildcards( $term ) {
		$escape = self::LIKE_ESCAPE;

		return str_replace(
			array( $escape, '%', '_' ),
			array( $escape . $escape, $escape . '%', $escape . '_' ),
			(string) $term
		);
	}

	/**
	 * اعتبارسنجی Nonce و توقف در صورت خطا.
	 *
	 * @param string $action نام action.
	 * @param string $field  نام فیلد nonce.
	 * @return void
	 */
	public static function verify_nonce( $action, $field = '_wpnonce' ) {
		$nonce = isset( $_REQUEST[ $field ] ) ? sanitize_text_field( wp_unslash( $_REQUEST[ $field ] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, $action ) ) {
			wp_die(
				esc_html__( 'اعتبار درخواست منقضی شده است. لطفاً صفحه را دوباره بارگذاری کرده و مجدداً تلاش کنید.', 'sparkknife-ledger' ),
				esc_html__( 'خطای امنیتی', 'sparkknife-ledger' ),
				array( 'response' => 403 )
			);
		}
	}

	/**
	 * خواندن فیلد POST از طریق غیرفعال‌سازی موقت magic quotes (در صورت وجود).
	 *
	 * @param string $key نام فیلد.
	 * @return string
	 */
	public static function raw_post( $key ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- این متد فقط دسترسی به مقدار خام است؛ بررسی nonce در لایه کنترلر انجام می‌شود.
		return isset( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : '';
	}
}
