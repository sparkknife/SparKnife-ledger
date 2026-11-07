<?php
/**
 * پاک‌سازی متمرکز ورودی‌ها.
 *
 * @author  SparKnife <155084046+sparkknife@users.noreply.github.com>
 *
 * @package SparKnife_Ledger
 */

defined( 'ABSPATH' ) || exit;

/**
 * پاک‌سازی ورودی‌های فرم.
 */
class Sparkknife_Ledger_Sanitizer {

	/**
	 * حداکثر نسخه برای یک عبارت جستجو.
	 *
	 * سقف در حین ساخت هم اعمال می‌شود، نه فقط در پایان: بدون آن،
	 * ساختن نسخه‌ها با رشته‌های بلند **نمایی** می‌شد.
	 *
	 * اندازهٔ سقف از خودِ ضرب‌درسی درمی‌آید، نه از یک عدد گرد.
	 *
	 *   • سه گروه املایی با ۳، ۲ و ۳ عضو → ۳×۲×۳ = ۱۸ املا.
	 *   • هر املا با سه شکل رقم (لاتین، فارسی، عربی) → ۵۴ نسخهٔ یکدست.
	 *   • دو جزیرهٔ رقم رایج (شمارهٔ تماس + شناسهٔ لاتین) → چهار
	 *     ترکیب مختلط برای هر املا، یعنی ۱۸×۴ = ۷۲ نسخهٔ مختلط.
	 *
	 * جمع بدترین حالت ۱۸×۱۱ = ۱۹۸ به‌علاوهٔ ۲۰ نسخهٔ پایه ≈ ۲۱۸.
	 *
	 * این ثابت **سقف مطلق** است، نه سقف کاری: سقف کاری در
	 * `search_variants()` از روی تعداد خودِ املاها حساب می‌شود، چون
	 * بریدن کور همیشه یک خانواده را کامل حذف می‌کند و همان باگ ۷۶
	 * است. عدد قبلی ۴۸ (بعد ۶۰، بعد ۱۴۰) بود و هر بار آخرین
	 * خانواده — ارقام عربی‌هندی و ترکیب‌های مختلط — قربانی می‌شد.
	 */
	const MAX_SEARCH_VARIANTS = 400;

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
	 * `strcmp` روی UTF-8 مقایسه بایتی می‌کند و ترتیب الفبای فارسی نمی‌دهد.
	 * ترتیب بایتی بر اساس کد یونیکد است و پنج حرف فارسی بیرون از
	 * بازهٔ `ا`–`ف` قرار دارند:
	 *
	 *     پ U+067E · چ U+0686 · ک U+06A9 · گ U+06AF · ی U+06CC
	 *
	 * نتیجه اینکه `ORDER BY name` در SQL آن‌ها را **بعد از** `ی`
	 * می‌آورد: «… مهدی · نازنین · هما · وحید · پرویز · چنگیز · کاوه ·
	 * گلناز · یوسف». برای کاربر فارسی‌زبان فهرست اشخاص مرتب به نظر
	 * نمی‌رسد و پیدا کردن یک نام با چشم ممکن نیست.
	 *
	 * این تابع هر نویسه را به یک کد با عرض ثابت تبدیل می‌کند:
	 *
	 *   - حرف فارسی:  `A` + شمارهٔ الفبا با عرض ۵ (`A00001`…`A00034`)
	 *   - رشتهٔ رقم:  `B` + تعداد ارقام با عرض ۳ + خود ارقام
	 *   - فاصله:      `C00000`
	 *   - نویسهٔ دیگر: `D` + کد یونیکد با عرض ۶
	 *
	 * دسته با یک **حرف** جدا می‌شود و نه با یک رقم. اگر کل کلید رقمی
	 * باشد، عملگرهای مقایسهٔ PHP (`<`، `>`، `==`) آن را یک **عدد**
	 * می‌بینند و نه رشته: صفرهای ابتدایی نادیده گرفته می‌شوند و رشتهٔ
	 * بلند به `float` تبدیل می‌شود و دقت را از دست می‌دهد. نتیجه اینکه
	 * `persian_sort_key('پرویز') < persian_sort_key('تارا')` نادرست
	 * می‌شد. با حرف در ابتدای هر دسته، کلید همیشه رشته است و مقایسه
	 * بایتی می‌ماند. (`strcmp` همیشه بایتی است، ولی تابع باید با
	 * عملگرهای ساده هم درست کار کند.)
	 *
	 * درون هر دسته عرض ثابت است، بنابراین «ا» + «ب» با «اب» قاطی نمی‌شود.
	 *
	 * به `intl` و `Collator` وابسته نیست: افزونهٔ `intl` روی همهٔ
	 * میزبان‌های وردپرس نصب نیست و مرتب‌سازی نام نباید به آن بشکند.
	 *
	 * @param string $value متن.
	 * @return string کلیدی که با `strcmp` ترتیب الفبای فارسی می‌دهد.
	 */
	public static function persian_sort_key( $value ) {
		/*
		 * ترتیب الفبای فارسی. «آ» پیش از «ا» می‌آید و «ء» پس از «ی»
		 * (همزه در پایان الفبا) — نه پس از «ا» که ترتیب بایتی آن را
		 * آن‌جا می‌گذارد.
		 */
		static $order = array(
			'آ' => 1,  'ا' => 2,  'ب' => 3,  'پ' => 4,  'ت' => 5,
			'ث' => 6,  'ج' => 7,  'چ' => 8,  'ح' => 9,  'خ' => 10,
			'د' => 11, 'ذ' => 12, 'ر' => 13, 'ز' => 14, 'ژ' => 15,
			'س' => 16, 'ش' => 17, 'ص' => 18, 'ض' => 19, 'ط' => 20,
			'ظ' => 21, 'ع' => 22, 'غ' => 23, 'ف' => 24, 'ق' => 25,
			'ک' => 26, 'گ' => 27, 'ل' => 28, 'م' => 29, 'ن' => 30,
			'و' => 31, 'ه' => 32, 'ی' => 33, 'ء' => 34,
		);

		$value = self::normalize_persian( $value );
		$value = function_exists( 'mb_strtolower' ) ? mb_strtolower( $value, 'UTF-8' ) : strtolower( $value );

		$key   = '';
		$chars = preg_split( '//u', (string) $value, -1, PREG_SPLIT_NO_EMPTY );

		$total = count( (array) $chars );

		for ( $i = 0; $i < $total; $i++ ) {
			$char = $chars[ $i ];

			if ( isset( $order[ $char ] ) ) {
				$key .= 'A' . sprintf( '%05d', $order[ $char ] );
				continue;
			}

			if ( 1 === strlen( $char ) && ctype_digit( $char ) ) {
				/*
				 * رقم‌ها به‌صورت **یک عدد** کد می‌شوند، نه رقم‌به‌رقم.
				 *
				 * کدگذاری رقم‌به‌رقم «۱۰» را پیش از «۲» می‌آورد، چون
				 * رقم نخست ۱ کوچک‌تر از ۲ است. برای عددهای بدون صفر
				 * پیشرو، مرتب‌سازی بر اساس (طول، مقدار بایتی) دقیقاً
				 * همان ترتیب عددی است: «۹» < «۱۰» < «۹۹» < «۱۰۰».
				 */
				$run = '';

				while ( $i < $total && 1 === strlen( $chars[ $i ] ) && ctype_digit( $chars[ $i ] ) ) {
					$run .= $chars[ $i ];
					$i++;
				}

				$i--; // حلقهٔ بیرونی خودش یکی جلو می‌رود.

				$key .= 'B' . sprintf( '%03d', strlen( $run ) ) . $run;
				continue;
			}

			if ( ' ' === $char || "\t" === $char ) {
				$key .= 'C00000';
			} else {
				/*
				 * پیش‌تر اینجا `900 + ( ord( $char ) % 99 )` بود. `ord()` فقط
				 * بایت نخست را می‌گیرد و `% 99` دو نویسهٔ متفاوت را به یک
				 * عدد می‌رساند؛ حروف لاتین و نشانه‌ها با هم قاطی می‌شدند.
				 * کد کامل یونیکد بدون باقیمانده استفاده می‌شود.
				 */
				/*
				 * `mb_ord()` در PHP ۸.۲ هست، ولی اگر میزبان `mbstring` نداشته
				 * باشد صدا زدنش خطای مرگبار می‌دهد. `ord()` فقط بایت نخست را
				 * برمی‌گرداند و برای مقایسه کافی است: نویسه‌های غیرفارسی
				 * هیچ‌گاه با حرف فارسی قاطی نمی‌شوند چون پیشوند دسته (`D`)
				 * جدایشان می‌کند.
				 */
				$codepoint = function_exists( 'mb_ord' ) ? (int) mb_ord( $char, 'UTF-8' ) : ord( $char );

				$key .= 'D' . sprintf( '%06d', $codepoint );
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

		/*
		 * ورودی‌ای که پس از یکسان‌سازی هیچ محتوایی ندارد (خالی، یا فقط
		 * نیم‌فاصله/فاصله) هیچ نسخه‌ای تولید نمی‌کند. `trim()` تنها
		 * فاصله‌های ASCII را می‌گیرد، پس یکسان‌سازی هم شرط است.
		 */
		if ( '' === trim( self::normalize_persian( $search ) ) ) {
			return array();
		}

		$raw = trim( $search );

		/*
		 * عبارت جستجو یکسان‌سازی می‌شود (ی/ک عربی، ارقام فارسی،
		 * نیم‌فاصله) و **مقدار خام** هم نگه داشته می‌شود.
		 *
		 * چرا خام؟ یکسان‌سازی ارقام فارسی را به لاتین تبدیل می‌کند،
		 * ولی نام‌ها همان‌طور که کاربر تایپ کرده ذخیره می‌شوند
		 * (`text()` ارقام را لاتین نمی‌کند). بدون نسخهٔ خام، رکوردی با
		 * نام «علي كاوه ۰۹۱۲» با جستجوی دقیقاً همان متن هم پیدا نمی‌شد.
		 */
		$normalized = self::normalize_persian( $search );

		/*
		 * همهٔ ترکیب‌های املا، از **هر دو** پایه (خام و یکسان‌شده).
		 *
		 * جستجوی «علی کاوه 0912» باید رکورد «علي كاوه ۰۹۱۲» را پیدا
		 * کند: هم املا و هم نوع ارقام متفاوت است. پس نسخه‌ها باید
		 * ضرب‌درسی از هر دو باشند، نه یکی از آن‌ها.
		 */
		$spellings = array_values(
			array_unique(
				array_merge(
					self::spelling_variants( $normalized ),
					self::spelling_variants( $raw )
				)
			)
		);

		/*
		 * نسخه‌های ارقام: هر نسخهٔ املایی به هر سه شکل رقم.
		 *
		 * `to_persian_digits()` و `to_arabic_digits()` فقط لاتین →
		 * فارسی/عربی می‌کنند و رشته‌ای که از قبل ارقام فارسی دارد را
		 * دست‌نخورده می‌گذارند؛ پس اول همه به لاتین برمی‌گردند.
		 *
		 * نکتهٔ مهم (باگ ۷۶): تبدیل روی **کل** رشته اعمال می‌شود، پس
		 * عبارت جستجو همیشه یکدست می‌شد — همهٔ ارقام لاتین یا همه
		 * عربی. اما رکورد ذخیره‌شده می‌تواند **مختلط** باشد: کاربر
		 * شمارهٔ شخص را با ارقام عربی‌هندی تایپ کرده و بعداً یک
		 * شناسه با حروف لاتین به آن چسبیده («… ٠٩١٢ MK1234»). هیچ‌کدام
		 * از سه نسخهٔ یکدست چنین رکوردی را پیدا نمی‌کرد.
		 *
		 * راه‌حل: عبارت به «بخش ارقام» و «بخش غیرارقام» شکسته می‌شود
		 * و هر بخش ارقام جداگانه به سه شکل تبدیل می‌شود، در حالی که
		 * بخش غیررقمی دست‌نخورده می‌ماند. برای عبارت تک‌بخشی همان
		 * سه نسخهٔ قبلی تولید می‌شود، پس رفتار عادی تغییر نمی‌کند.
		 */
		$digit_variants = array();

		foreach ( $spellings as $spelling ) {
			if ( ! preg_match( '/[0-9\x{06F0}-\x{06F9}\x{0660}-\x{0669}]/u', $spelling ) ) {
				continue;
			}

			$latin = Sparkknife_Ledger_Money::to_latin_digits( $spelling );

			$digit_variants[] = $latin;
			$digit_variants[] = Sparkknife_Ledger_Money::to_persian_digits( $latin );
			$digit_variants[] = Sparkknife_Ledger_Money::to_arabic_digits( $latin );

			/*
			 * نسخه‌های مختلط: هر «جزیرهٔ رقم» جداگانه تبدیل می‌شود و
			 * بقیهٔ عبارت همان‌طور می‌ماند. حداکثر چهار جزیره —
			 * سقف ایمنی، چون تعداد ترکیب‌ها با هر جزیره سه برابر
			 * می‌شود (۳^۴ = ۸۱).
			 */
			$digit_variants = array_merge(
				$digit_variants,
				self::mixed_digit_variants( $latin )
			);
		}

		/*
		 * ترتیب اولویت.
		 *
		 *  ۱. خام و یکسان‌شده — بیشترین احتمال تطبیق.
		 *  ۲. نسخه‌های املایی با ارقام لاتین — حالت عادی.
		 *  ۳. نسخه‌های املایی با ارقام فارسی/عربی — رکوردهای قدیمی.
		 *  ۴. بقیهٔ نسخه‌های املایی.
		 *
		 * پیش‌تر یک `array_slice( …, 0, 12 )` کور روی ترتیب ساخت
		 * آرایه اعمال می‌شد: برای «علی کاوه 0912» هر ۱۲ جای اول را
		 * ترکیب املای «ه» می‌گرفت و **همهٔ** نسخه‌های «ي عربی» و
		 * «ارقام فارسی» حذف می‌شدند — یعنی سقف دقیقاً همان نسخه‌هایی
		 * را می‌انداخت که برایشان ساخته شده بود.
		 */
		$priority = array_merge(
			array( $raw, $normalized ),
			array_map( array( 'Sparkknife_Ledger_Money', 'to_latin_digits' ), $spellings ),
			$digit_variants,
			$spellings
		);

		$variants = array();

		foreach ( $priority as $variant ) {
			$variant = (string) $variant;

			/*
			 * یکسان‌سازی می‌تواند **همهٔ** عبارت را حذف کند (ورودی
			 * فقط نیم‌فاصله). `LIKE '%%'` همهٔ رکوردها را برمی‌گرداند،
			 * پس نسخه‌های بی‌محتوا کنار گذاشته می‌شوند.
			 *
			 * توجه: `trim()` نویسه‌های قالب‌بندی یونیکد (U+200C نیم‌فاصله،
			 * U+200B، U+FEFF و مانند آن‌ها) را حذف **نمی‌کند**؛ پس
			 * ورودی‌ای که تنها از نیم‌فاصله ساخته شده از این نگهبان رد
			 * می‌شد و یک نسخهٔ یک‌نویسه‌ای (خودِ نیم‌فاصله) تولید
			 * می‌کرد. این نسخه بعد از `LIKE` عملاً بی‌اثر است ولی
			 * قرارداد تابع «هیچ نسخه‌ای برای عبارت تهی» را می‌شکست و
			 * فراخوان را وادار می‌کرد خودش نگهبان داشته باشد. حالا
			 * با همان معیارِ معنی‌داری سنجیده می‌شود.
			 */
			if ( '' === trim( self::normalize_persian( $variant ) ) ) {
				continue;
			}

			$variants[ $variant ] = true;
		}

		/*
		 * سقف کاری، از روی تعداد واقعی املاها.
		 *
		 * هر املا ۱۱ نسخه تولید می‌کند:
		 *   • سه شکل یکدست رقم: لاتین، فارسی، عربی؛
		 *   • هشت ترکیب مختلط برای دو جزیرهٔ رقم (۳²−۱).
		 *
		 * پس سقف = ۲ نسخهٔ پایه (خام و یکسان‌شده) + ۱ نسخهٔ لاتین
		 * برای هر املا + ۱۱ نسخهٔ رقمی برای هر املا، با حاشیه.
		 *
		 * عدد ثابت این‌جا غلط است: با یک عدد گرد، هر بار که عبارت
		 * پیچیده‌تر می‌شد یک خانوادهٔ کامل از نسخه‌ها بیرون می‌افتاد
		 * و جستجو بی‌صدا نتیجه نمی‌داد — دقیقاً همان باگ ۷۶. عدد ۸
		 * در نسخهٔ قبلی همین محاسبه، هشت ترکیب مختلط را نادیده
		 * می‌گرفت و آن‌ها را قربانی می‌کرد.
		 *
		 * `MAX_SEARCH_VARIANTS` همچنان سقف مطلق است تا کوئری در
		 * بدترین حالت بی‌جهت بزرگ نشود.
		 */
		$cap = min(
			self::MAX_SEARCH_VARIANTS,
			(int) ( 8 + count( $spellings ) * 16 )
		);

		return array_slice( array_keys( $variants ), 0, $cap );
	}

	/**
	 * نسخه‌های مختلط ارقام برای یک عبارت.
	 *
	 * `to_persian_digits()` و `to_arabic_digits()` **کل** رشته را
	 * یکدست می‌کنند، ولی دادهٔ ذخیره‌شده می‌تواند مختلط باشد؛ مثلاً
	 * شمارهٔ شخص با ارقام عربی‌هندی تایپ شده و یک شناسهٔ لاتین به
	 * انتهای آن چسبیده. برای پیدا کردن چنین رکوردی از هر جهت، هر
	 * «جزیرهٔ رقم» جداگانه به هر سه شکل تبدیل می‌شود و بقیهٔ متن
	 * دست‌نخورده می‌ماند.
	 *
	 * @param string $value عبارت با ارقام لاتین.
	 * @return array<int, string> نسخه‌های مختلط (بدون نسخهٔ اصلی).
	 */
	private static function mixed_digit_variants( $value ) {
		/*
		 * هر جزیرهٔ رقم جدا می‌شود و جای آن یک جای‌نگهدار می‌نشیند
		 * تا موقع بازسازی، متن و ترتیب اصلی حفظ شود.
		 */
		$parts = preg_split( '/([0-9]+)/', (string) $value, -1, PREG_SPLIT_DELIM_CAPTURE );

		if ( ! is_array( $parts ) ) {
			return array();
		}

		$islands = array();

		foreach ( $parts as $index => $part ) {
			if ( '' !== $part && preg_match( '/^[0-9]+$/', $part ) ) {
				$islands[] = $index;
			}
		}

		/*
		 * با یک جزیره نسخهٔ مختلط همان سه نسخهٔ یکدست است (تکراری) و
		 * با بیش از چهار جزیره ترکیب‌ها بی‌رویه زیاد می‌شود. در این
		 * حالت‌ها فقط حالت یکدست کافی است.
		 */
		if ( count( $islands ) < 2 || count( $islands ) > 4 ) {
			return array();
		}

		$builders = array(
			array( 'Sparkknife_Ledger_Money', 'to_persian_digits' ),
			array( 'Sparkknife_Ledger_Money', 'to_arabic_digits' ),
		);

		/*
		 * برای هر جزیره یکی از سه شکل (لاتین/فارسی/عربی) انتخاب
		 * می‌شود و همهٔ ترکیب‌ها با شمارش مبنای سه ساخته می‌شوند.
		 * حالتی که همه لاتین‌اند همان نسخهٔ اصلی است و کنار گذاشته
		 * می‌شود؛ بقیه ترکیب‌های واقعاً مختلط‌اند.
		 */
		$total    = (int) pow( 3, count( $islands ) );
		$variants = array();

		for ( $mask = 1; $mask < $total; $mask++ ) {
			$copy   = $parts;
			$cursor = $mask;

			foreach ( $islands as $index ) {
				$shape = $cursor % 3;
				$cursor = (int) ( $cursor / 3 );

				if ( 0 === $shape ) {
					continue; // لاتین.
				}

				$copy[ $index ] = call_user_func( $builders[ $shape - 1 ], $copy[ $index ] );
			}

			$variants[] = implode( '', $copy );
		}

		return $variants;
	}

	/**
	 * همهٔ ترکیب‌های املایی ممکن برای یک عبارت.
	 *
	 * کاربران ایرانی گاهی با کیبورد عربی تایپ می‌کنند یا متن را از
	 * جایی کپی می‌کنند؛ «ي» (U+064A) و «ك» (U+0643) عربی به‌جای
	 * «ی» (U+06CC) و «ک» (U+06A9) فارسی وارد می‌شود. «ة» و «ۀ» هم
	 * به‌جای «ه» می‌آیند. چون این‌ها نویسه‌های متفاوتی در دیتابیس
	 * هستند، جستجو باید هر ترکیبی را امتحان کند.
	 *
	 * @param string $value عبارت.
	 * @return array<int, string> نسخه‌ها؛ همیشه شامل خودِ عبارت.
	 */
	private static function spelling_variants( $value ) {
		$groups = array(
			array( 'ی', 'ي', 'ى' ),
			array( 'ک', 'ك' ),
			array( 'ه', 'ة', 'ۀ' ),
		);

		$variants = array( (string) $value );

		foreach ( $groups as $group ) {
			$expanded = array();

			foreach ( $variants as $variant ) {
				$expanded[] = $variant;

				/*
				 * فقط **یک** جایگاه از هر گروه در هر نسخه عوض می‌شود.
				 *
				 * اگر هر نسخه با هر حرف گروه به همهٔ حرف‌های دیگر بازنویسی
				 * شود، تعداد نسخه‌ها **نمایی** می‌شود: ۳ به توان تعداد
				 * «ی» در رشته. توضیحات یک تراکنش تا ۲۰۰۰ نویسه است و
				 * رشته‌ای با ۴۰۰ «ی» چیزی حدود ۱۰^۱۹۰ نسخه می‌خواست —
				 * یعنی حافظهٔ سرور تمام می‌شد و صفحه سفید می‌ماند.
				 */
				foreach ( $group as $from ) {
					/*
					 * هر حرف گروه از ابتدای رشته جستجو می‌شود.
					 *
					 * پیش‌تر یک `$position` مشترک بین حروف گروه بود و پس از
					 * یافتن «ی» جلو می‌رفت؛ بعد جستجوی «ى» از همان جای
					 * جلوتر شروع می‌شد و نسخه‌های عقب‌افتاده را از دست
					 * می‌داد. با رشتهٔ «علي كاوه ۰۹۱۲» این باعث می‌شد ۶
					 * ترکیب از ۱۶ ترکیب ماتریس پیدا نشوند.
					 */
					$offset = strpos( $variant, $from );

					if ( false === $offset ) {
						continue;
					}

					foreach ( $group as $to ) {
						if ( $to !== $from ) {
							$expanded[] = substr_replace( $variant, $to, $offset, strlen( $from ) );
						}
					}
				}
			}

			$variants = array_values( array_unique( $expanded ) );

			/*
			 * سقف در همین‌جا و نه فقط در پایان: بدون آن، گروه بعدی روی
			 * آرایهٔ رشدکرده کار می‌کند و انفجار ادامه می‌یابد.
			 */
			if ( count( $variants ) > self::MAX_SEARCH_VARIANTS ) {
				$variants = array_slice( $variants, 0, self::MAX_SEARCH_VARIANTS );
			}
		}

		return $variants;
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
