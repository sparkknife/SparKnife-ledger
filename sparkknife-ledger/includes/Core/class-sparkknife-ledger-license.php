<?php
/**
 * سیستم لایسنس.
 *
 * @author  SparKnife <155084046+sparkknife@users.noreply.github.com>
 *
 * @package SparKnife_Ledger
 */

defined( 'ABSPATH' ) || exit;

/**
 * بررسی و مدیریت لایسنس افزونه.
 *
 * ## چرا امضای دیجیتال و نه تماس با سرور
 *
 * اینکه افزونه با هر بار بارگذاری به سرور فروشنده زنگ بزند، هم کند است
 * و هم اگر سرور از دسترس خارج شود سایت خریدار از کار می‌افتد — برای یک
 * نرم‌افزار مالی که سوابق مشتری در آن است، این ریسک پذیرفتنی نیست.
 *
 * کلید لایسنس یک **امضای دیجیتال** است که با کلید خصوصی فروشنده ساخته
 * می‌شود و افزونه آن را با کلید عمومیِ ثابت در همین فایل بررسی می‌کند.
 * هیچ تماس شبکه‌ای لازم نیست، هیچ سروری لازم نیست، و کلید دستکاری‌شده
 * بی‌بروک تشخیص داده می‌شود.
 *
 * ## شکل کلید
 *
 * ```
 * SPK1.<payload>.<signature>
 * ```
 *
 * هر دو بخش Base64-url هستند. `payload` این چهار میدان را با `|` جدا
 * می‌کند:
 *
 * ```
 * domain|expiry|edition|serial
 * ```
 *
 * - `domain` : دامنه‌ای که لایسنس برایش صادر شده (`*` = نامحدود).
 * - `expiry` : تاریخ انقضا به `YYYY-MM-DD` (`` = همیشه).
 * - `edition`: `pro` یا `agency`.
 * - `serial` : شناسه یکتای لایسنس برای پیگیری فروشنده.
 *
 * ## محدودیت‌های امنیتی که باید صادقانه بدانید
 *
 * هر سیستم لایسنسی که کد آن در دسترس خریدار باشد **قابل دور زدن** است؛
 * کسی که PHP بخواند می‌تواند بررسی را حذف کند. این پیاده‌سازی جلوی
 * استفادهٔ عادی و توزیع کلید بین چند سایت را می‌گیرد، ولی نمی‌تواند
 * جلوی کسی که کد را تغییر می‌دهد بگیرد. هر ادعای خلاف این، فروش
 * نادرست است.
 *
 * @since 1.0.0
 */
class Sparkknife_Ledger_License {

	/**
	 * کلید عمومی فروشنده (PEM).
	 *
	 * این کلید عمداً ثابت و در کد است: افزونه باید بتواند بدون هیچ
	 * تنظیمی امضا را بررسی کند. کلید خصوصی متناظر هرگز در مخزن، بسته
	 * یا هیچ جای عمومی نیست.
	 */
	/*
	 * کلید عمومی لایسنس.
	 *
	 * این کلید جفتِ tools/licensing/private_key.pem است. کلید خصوصی
	 * **هرگز** در مخزن، در بسته، یا روی سرور قرار نمی‌گیرد — فقط روی
	 * دستگاه خود SparKnife. با آن، لایسنس و امضای بسته ساخته می‌شود.
	 *
	 * ⚠️ اگر روزی جفت کلید را عوض کردید، **این مقدار هم باید با آن**
	 * عوض شود. جفت نبودن این دو، تمام لایسنس‌ها را باطل می‌کند و
	 * پیام خطایش هم گمراه‌کننده است: «لایسنس نامعتبر».
	 */
	const PUBLIC_KEY = '-----BEGIN PUBLIC KEY-----
MIIBojANBgkqhkiG9w0BAQEFAAOCAY8AMIIBigKCAYEAvf3JMl07YUdG9Gjtsfiy
sgqkMvTDaWGcwIcbMquHqH2S7aBYvvt3W7pRSoXGjSBO+rumJ1nIMdKFmWIMmxa9
4T/TA+gyfwudbv3yQFL2DPPt9QM0ffwnjKRtNqo8M0ATU8BwgZ8L7v9iqf24tKZn
GD7uCQ0SMERHSYhlyAtwTfnpZLaTmQ9fXA19rkoK3U0YVrQV7MYaHQba6Vj0f+5h
IgIIaO4vMDfmyCK3EedjEs+Nl9zXgJZiAlQndBaCnxCahzNVri5jhXnGq3N7WWgv
5qfJYOho2rcNgbfqMLQ784riO9bTxqSsLzJHH3Eq99uL7K3nWUzd1ze1QOuVWGuN
NPXZMZg7JhfK5S90xkxtIQRvABKu9L+fqf14BKbAl596cyunj+xCda98f3pVTGvo
YxpKVmH8RvqPZb7NOQuITwdhk+GFfPeDuFTZa2W/blCHf40VD8pkJouXBp5+V/Tg
Dc9/n4z9YmMIaOFNbzTEtuEiYnLINAu5d7WgDnxdvG2HAgMBAAE=
-----END PUBLIC KEY-----';

	/**
	 * پیشوند نسخهٔ کلید. اگر روزی شکل کلید عوض شود، کلیدهای قدیمی
	 * بی‌روک با پیام واضح رد می‌شوند، نه با ابهام.
	 */
	const KEY_PREFIX = 'SPK1';

	/**
	 * کلید گزینه‌ای که کلید فعال در آن ذخیره می‌شود.
	 */
	const OPTION_KEY = 'sparkknife_ledger_license';

	/**
	 * کلید گزینهٔ کش وضعیت (برای اینکه هر بار امضا بررسی نشود).
	 */
	const OPTION_CACHE = 'sparkknife_ledger_license_state';

	/**
	 * مدت اعتبار کش، به ثانیه.
	 *
	 * بررسی امضا ارزان است، ولی هر بار در هر بارگذاری صفحه انجام شود،
	 * مجموعش به چشم می‌آید. شش ساعت کافی است: تغییر لایسنس بلافاصله
	 * با `flush()` اثر می‌کند و انقضا هم حداکثر شش ساعت دیرتر دیده
	 * می‌شود.
	 */
	const CACHE_TTL = 21600;

	/**
	 * وضعیت بررسی‌شده در همان درخواست.
	 *
	 * @var array|null
	 */
	private static $memo = null;

	/**
	 * کلید فعال را برمی‌گرداند.
	 *
	 * @return string
	 */
	public static function key() {
		$key = get_option( self::OPTION_KEY, '' );

		return is_string( $key ) ? trim( $key ) : '';
	}

	/**
	 * آیا نسخهٔ Pro فعال است؟
	 *
	 * @return bool
	 */
	public static function is_active() {
		$state = self::state();

		return ! empty( $state['valid'] );
	}

	/**
	 * وضعیت کامل لایسنس.
	 *
	 * @param bool $force نادیده گرفتن کش.
	 * @return array{valid: bool, reason: string, domain: string, expiry: string, edition: string, serial: string, message: string}
	 */
	public static function state( $force = false ) {
		if ( $force ) {
			self::$memo = null;
		}

		if ( null !== self::$memo ) {
			return self::$memo;
		}

		$cached = get_transient( self::OPTION_CACHE );

		if ( ! $force && is_array( $cached ) && isset( $cached['valid'] ) ) {
			self::$memo = $cached;

			return $cached;
		}

		$state = self::evaluate( self::key() );

		set_transient( self::OPTION_CACHE, $state, self::CACHE_TTL );
		self::$memo = $state;

		return $state;
	}

	/**
	 * کش را پاک می‌کند. پس از ذخیره یا حذف کلید لازم است.
	 *
	 * @return void
	 */
	public static function flush() {
		self::$memo = null;
		delete_transient( self::OPTION_CACHE );

		/**
		 * پس از تغییر وضعیت لایسنس.
		 *
		 * @param array $state وضعیت تازه.
		 */
		do_action( 'sparkknife_ledger_license_changed', self::state( true ) );
	}

	/**
	 * یک کلید را ذخیره می‌کند. کلید نامعتبر ذخیره **نمی‌شود**.
	 *
	 * @param string $key کلید.
	 * @return true|WP_Error
	 */
	public static function save_key( $key ) {
		$key = trim( (string) $key );

		if ( '' === $key ) {
			return new WP_Error(
				'sparkknife_ledger_license_empty',
				__( 'کلید لایسنس را وارد کنید.', 'sparkknife-ledger' )
			);
		}

		$state = self::evaluate( $key );

		/*
		 * کلید نامعتبر ذخیره نمی‌شود تا کاربر نداند «کلید ذخیره شد ولی
		 * کار نمی‌کند» — همان ابهامی که پشتیبانی را می‌کشد.
		 */
		if ( empty( $state['valid'] ) ) {
			return new WP_Error( 'sparkknife_ledger_license_invalid', $state['message'] );
		}

		update_option( self::OPTION_KEY, $key, false );
		self::flush();

		return true;
	}

	/**
	 * کلید را حذف می‌کند.
	 *
	 * @return void
	 */
	public static function remove_key() {
		delete_option( self::OPTION_KEY );
		self::flush();
	}

	/**
	 * نسخهٔ نمایشی لایسنس یا `free`.
	 *
	 * @return string
	 */
	public static function edition() {
		$state = self::state();

		return ! empty( $state['valid'] ) && ! empty( $state['edition'] ) ? $state['edition'] : 'free';
	}

	/**
	 * هستهٔ بررسی: امضا، دامنه و انقضا.
	 *
	 * @param string $key کلید.
	 * @return array
	 */
	private static function evaluate( $key ) {
		$invalid = static function ( $reason, $message ) {
			return array(
				'valid'   => false,
				'reason'  => $reason,
				'domain'  => '',
				'expiry'  => '',
				'edition' => '',
				'serial'  => '',
				'message' => $message,
			);
		};

		if ( '' === $key ) {
			return $invalid(
				'empty',
				__( 'لایسنس وارد نشده است. نسخهٔ رایگان فعال است.', 'sparkknife-ledger' )
			);
		}

		$parts = explode( '.', $key );

		if ( 3 !== count( $parts ) || self::KEY_PREFIX !== $parts[0] ) {
			return $invalid(
				'format',
				__( 'شکل کلید لایسنس درست نیست.', 'sparkknife-ledger' )
			);
		}

		$payload = self::b64_decode( $parts[1] );
		$sig_raw = self::b64_decode( $parts[2] );

		if ( '' === $payload || '' === $sig_raw ) {
			return $invalid(
				'encoding',
				__( 'کلید لایسنس خوانا نیست؛ احتمالاً هنگام کپی ناقص شده است.', 'sparkknife-ledger' )
			);
		}

		/*
		 * بررسی امضا با کلید عمومی. `openssl_verify` سه حالت دارد:
		 * ۱ معتبر، ۰ امضای نادرست، ۱- خطای داخلی. هر دو حالت غیر از ۱
		 * یعنی «قبول نکن».
		 */
		if ( ! function_exists( 'openssl_verify' ) ) {
			return $invalid(
				'no_openssl',
				__( 'افزونهٔ OpenSSL روی این سرور فعال نیست و بررسی لایسنس ممکن نشد.', 'sparkknife-ledger' )
			);
		}

		/*
		 * پاک‌سازی پیش از تحویل به OpenSSL.
		 *
		 * PEM باید newline واقعی داشته باشد. اگر کسی کلید را با ابزاری
		 * در فایل بنویسد که `\n` را متن خام بگذارد (مثلاً جای‌گذاری با
		 * sed در رشتهٔ تک‌کوتیشن PHP)، مقدار `PUBLIC_KEY` همان کاراکترهای
		 * backslash و n می‌شود، نه شکست خط. OpenSSL آن را نمی‌فهمد و فقط
		 * «ناموفق» می‌دهد — بدون هیچ سرنخی که مشکل از قالب کلید است.
		 *
		 * این حالت یک بار واقعاً رخ داد: لایسنس روی محیط توسعه معتبر
		 * بود ولی روی سایت واقعی «کلید عمومی افزونه بارگذاری نشد»
		 * می‌داد.
		 *
		 * به‌جای تکیه بر اینکه نویسندهٔ کلید درست انجام می‌دهد، همان‌جا
		 * درستش می‌کنیم: `\n` متنی به شکست خط تبدیل می‌شود. PEM
		 * استاندارد است و تغییر دیگری لازم نیست.
		 */
		$public_pem = str_replace( '\\n', "\n", (string) self::PUBLIC_KEY );
		$public_pem = str_replace( "\r\n", "\n", $public_pem );
		$public_pem = trim( $public_pem );

		$public = openssl_pkey_get_public( $public_pem );

		if ( false === $public ) {
			return $invalid(
				'bad_public_key',
				__( 'کلید عمومی افزونه بارگذاری نشد.', 'sparkknife-ledger' )
			);
		}

		$verified = openssl_verify( $payload, $sig_raw, $public, OPENSSL_ALGO_SHA256 );

		if ( 1 !== $verified ) {
			return $invalid(
				'signature',
				__( 'امضای لایسنس معتبر نیست.', 'sparkknife-ledger' )
			);
		}

		$fields = explode( '|', $payload );

		if ( 4 !== count( $fields ) ) {
			return $invalid(
				'payload',
				__( 'محتوای لایسنس ناقص است.', 'sparkknife-ledger' )
			);
		}

		list( $domain, $expiry, $edition, $serial ) = $fields;

		$domain  = strtolower( trim( $domain ) );
		$expiry  = trim( $expiry );
		$edition = strtolower( trim( $edition ) );
		$serial  = trim( $serial );

		if ( ! in_array( $edition, array( 'pro', 'agency' ), true ) ) {
			return $invalid(
				'edition',
				__( 'نسخهٔ لایسنس شناخته نشد.', 'sparkknife-ledger' )
			);
		}

		$match = self::domain_match( $domain );

		if ( ! $match ) {
			return array(
				'valid'   => false,
				'reason'  => 'domain',
				'domain'  => $domain,
				'expiry'  => $expiry,
				'edition' => $edition,
				'serial'  => $serial,
				'message' => sprintf(
					/* translators: %s: licensed domain */
					__( 'این لایسنس برای دامنهٔ «%s» صادر شده و با این سایت نمی‌خواند.', 'sparkknife-ledger' ),
					$domain
				),
			);
		}

		if ( '' !== $expiry ) {
			$ts = strtotime( $expiry . ' 23:59:59' );

			if ( false === $ts ) {
				return $invalid(
					'expiry_format',
					__( 'تاریخ انقضای لایسنس خوانا نیست.', 'sparkknife-ledger' )
				);
			}

			if ( time() > $ts ) {
				return array(
					'valid'   => false,
					'reason'  => 'expired',
					'domain'  => $domain,
					'expiry'  => $expiry,
					'edition' => $edition,
					'serial'  => $serial,
					'message' => sprintf(
						/* translators: %s: expiry date */
						__( 'لایسنس در تاریخ %s منقضی شده است.', 'sparkknife-ledger' ),
						$expiry
					),
				);
			}
		}

		return array(
			'valid'   => true,
			'reason'  => 'ok',
			'domain'  => $domain,
			'expiry'  => $expiry,
			'edition' => $edition,
			'serial'  => $serial,
			'message' => 'agency' === $edition
				? __( 'لایسنس آژانس فعال است.', 'sparkknife-ledger' )
				: __( 'لایسنس فعال است.', 'sparkknife-ledger' ),
		);
	}

	/**
	 * آیا دامنهٔ لایسنس با این سایت می‌خواند؟
	 *
	 * مقایسه سهل‌گیرانه است، نه سخت‌گیرانه: خریدار معمولاً لایسنس را
	 * برای `example.com` می‌خرد و سایت با `www.example.com` یا
	 * `example.com` در دسترس است. رد کردن یکی از این دو، پشتیبانی
	 * بی‌مورد می‌سازد. زیردامنه‌های ناشناخته (مثل
	 * `customer1.example.com` برای یک آژانس) **قبول نمی‌شوند** مگر
	 * لایسنس با `*.` شروع شود.
	 *
	 * @param string $licensed دامنهٔ مجاز در لایسنس.
	 * @return bool
	 */
	private static function domain_match( $licensed ) {
		if ( '*' === $licensed ) {
			return true;
		}

		$host = self::current_host();

		if ( '' === $host ) {
			/*
			 * بدون میزبان (مثلاً اجرا از CLI یا PHPUnit) بررسی دامنه
			 * ممکن نیست. رد کردن لایسنس در این حالت نادرست است، چون
			 * سایت واقعی سالم است و فقط زمینهٔ اجرا میزبان ندارد.
			 */
			return true;
		}

		$licensed = ltrim( $licensed, '.' );

		// `*.example.com` یعنی هر زیردامنه.
		if ( 0 === strpos( $licensed, '*.' ) ) {
			$base = substr( $licensed, 2 );

			return $host === $base || substr( $host, -strlen( '.' . $base ) ) === '.' . $base;
		}

		$host     = preg_replace( '/^www\./', '', $host );
		$licensed = preg_replace( '/^www\./', '', $licensed );

		return $host === $licensed;
	}

	/**
	 * میزبان جاری، بدون پورت و با www حذف‌شده.
	 *
	 * @return string
	 */
	public static function current_host() {
		$home = home_url();

		if ( '' === $home ) {
			return '';
		}

		$host = wp_parse_url( $home, PHP_URL_HOST );

		return is_string( $host ) ? strtolower( $host ) : '';
	}

	/**
	 * رمزگشایی Base64-url با تحمل کوتاه‌شدگی.
	 *
	 * @param string $value مقدار.
	 * @return string
	 */
	private static function b64_decode( $value ) {
		$value = strtr( (string) $value, '-_', '+/' );
		$pad   = strlen( $value ) % 4;

		if ( $pad ) {
			$value .= str_repeat( '=', 4 - $pad );
		}

		$decoded = base64_decode( $value, true );

		return is_string( $decoded ) ? $decoded : '';
	}
}
