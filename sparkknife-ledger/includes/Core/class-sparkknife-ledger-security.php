<?php
/**
 * سخت‌سازی امنیتی.
 *
 * @package SparKnife_Ledger
 */

defined( 'ABSPATH' ) || exit;

/**
 * لایهٔ امنیتی افزونه.
 *
 * سه کار انجام می‌دهد:
 *
 * ۱) **محدودیت تلاش** روی اکشن‌های حساس (`settings_save`،
 *    `data_purge`، `capabilities_save`). اگر کسی nonce دزدیده‌شده یا
 *    نشست دزدیده‌شده داشته باشد، بی‌نهایت تلاش نمی‌تواند بکند.
 *
 * ۲) **گزارش رویدادهای حساس** تا مدیر بداند چه اتفاقی افتاده. این
 *    جدا از تاریخچهٔ داده است: تاریخچه می‌گوید «مبلغ عوض شد»، گزارش
 *    امنیتی می‌گوید «چه کسی از چه IP‌ای چند بار تلاش کرد».
 *
 * ۳) **جلوگیری از افشا**: پیام‌های خطای دیتابیس نباید به کاربر عادی
 *    (حتی مدیر) نشان داده شوند، چون ساختار جدول‌ها را لو می‌دهند.
 *
 * ## چرا محدودیت بر اساس «کاربر + IP» است
 *
 * اگر فقط بر اساس IP باشد، یک مهاجم بدون نشست، مدیر را قفل می‌کند
 * (حملهٔ محروم‌سازی). اگر فقط بر اساس کاربر باشد، مهاجم با چند حساب
 * دور می‌زند. ترکیب `user_id + IP` هر دو را پوشش می‌دهد.
 *
 * @since 1.0.0
 */
class Sparkknife_Ledger_Security {

	/**
	 * گزینه‌ای که گزارش امنیتی در آن ذخیره می‌شود.
	 */
	const OPTION_LOG = 'sparkknife_ledger_security_log';

	/**
	 * حداکثر رویداد نگه‌داری‌شده. بیش از این، قدیمی‌ترین حذف می‌شود.
	 *
	 * گزارش امنیتی برای بررسی دستی است، نه آرشیو. صد رویداد آخر برای
	 * تشخیص الگوی حمله کافی است و از رشد بی‌نهایت گزینه جلوگیری می‌کند.
	 */
	const LOG_MAX = 100;

	/**
	 * محدودیت‌های پیش‌فرض اکشن‌ها: `action => [حداکثر تلاش, پنجره به ثانیه]`.
	 *
	 * @return array<string, array{0:int,1:int}>
	 */
	public static function default_limits() {
		$limits = array(
			// پاک‌سازی داده، برگشت‌ناپذیر است: بسیار محدود.
			'data_purge'        => array( 3, 900 ),
			// تغییر دسترسی نقش‌ها: محدود.
			'capabilities_save' => array( 10, 900 ),
			// تنظیمات: عادی است ولی محدود.
			'settings_save'     => array( 30, 600 ),
			// ابطال تراکنش: کاربر ممکن است واقعاً پشت‌سرهم چند تا بزند.
			'transaction_void'  => array( 60, 600 ),
		);

		/**
		 * فیلتر محدودیت‌ها.
		 *
		 * @param array<string, array{0:int,1:int}> $limits محدودیت‌ها.
		 */
		return apply_filters( 'sparkknife_ledger_rate_limits', $limits );
	}

	/**
	 * کلید ترنزینت محدودیت.
	 *
	 * @param string $action اکشن.
	 * @return string
	 */
	private static function rate_key( $action ) {
		$user = get_current_user_id();
		$ip   = self::client_ip();

		return 'spk_rl_' . md5( $action . '|' . $user . '|' . $ip );
	}

	/**
	 * آیا این اکشن از حد گذشته است؟
	 *
	 * پیش از انجام کار صدا زده می‌شود. اگر `true` برگرداند، فراخوان
	 * باید با خطا متوقف شود.
	 *
	 * @param string $action اکشن.
	 * @return true|WP_Error
	 */
	public static function check_rate( $action ) {
		$limits = self::default_limits();

		if ( ! isset( $limits[ $action ] ) ) {
			return true;
		}

		list( $max, $window ) = $limits[ $action ];

		$key   = self::rate_key( $action );
		$state = get_transient( $key );
		$state = is_array( $state ) ? $state : array(
			'count' => 0,
			'start' => time(),
		);

		// پنجره گذشته: شمارش از نو.
		if ( ( time() - (int) $state['start'] ) > $window ) {
			$state = array(
				'count' => 0,
				'start' => time(),
			);
		}

		if ( (int) $state['count'] >= $max ) {
			self::log(
				'rate_limited',
				'warning',
				array(
					'action' => $action,
					'count'  => (int) $state['count'],
					'max'    => $max,
				)
			);

			$wait = max( 1, $window - ( time() - (int) $state['start'] ) );

			return new WP_Error(
				'sparkknife_ledger_rate_limited',
				sprintf(
					/* translators: %d: seconds */
					__( 'تعداد تلاش‌ها بیش از حد مجاز است. %d ثانیه دیگر دوباره امتحان کنید.', 'sparkknife-ledger' ),
					$wait
				)
			);
		}

		++$state['count'];
		set_transient( $key, $state, $window );

		return true;
	}

	/**
	 * ثبت یک رویداد امنیتی.
	 *
	 * @param string               $event   نام رویداد.
	 * @param string               $level   `info` یا `warning`.
	 * @param array<string, mixed> $context زمینه.
	 * @return void
	 */
	public static function log( $event, $level = 'info', array $context = array() ) {
		$log = get_option( self::OPTION_LOG, array() );
		$log = is_array( $log ) ? $log : array();

		$entry = array(
			'time'    => time(),
			'event'   => (string) $event,
			'level'   => (string) $level,
			'user_id' => get_current_user_id(),
			'ip'      => self::client_ip(),
			'context' => self::scrub( $context ),
		);

		array_unshift( $log, $entry );

		if ( count( $log ) > self::LOG_MAX ) {
			$log = array_slice( $log, 0, self::LOG_MAX );
		}

		// autoload=false: این گزینه در هر بارگذاری خوانده نمی‌شود.
		update_option( self::OPTION_LOG, $log, false );

		/**
		 * پس از ثبت رویداد امنیتی.
		 *
		 * @param array $entry رویداد.
		 */
		do_action( 'sparkknife_ledger_security_event', $entry );
	}

	/**
	 * گزارش امنیتی، جدیدترین اول.
	 *
	 * @param int $limit حداکثر.
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_log( $limit = 50 ) {
		$log = get_option( self::OPTION_LOG, array() );

		if ( ! is_array( $log ) ) {
			return array();
		}

		return array_slice( $log, 0, max( 1, (int) $limit ) );
	}

	/**
	 * پاک کردن گزارش.
	 *
	 * @return void
	 */
	public static function clear_log() {
		delete_option( self::OPTION_LOG );
	}

	/**
	 * حذف داده‌های حساس از زمینهٔ رویداد.
	 *
	 * گزارش امنیتی نباید خودش به منبع نشت تبدیل شود؛ اگر روزی کسی یک
	 * کلید یا رمز را در زمینهٔ رویداد بگذارد، این‌جا حذف می‌شود.
	 *
	 * @param array $context زمینه.
	 * @return array
	 */
	private static function scrub( array $context ) {
		$blocked = array( 'password', 'pass', 'pwd', 'secret', 'token', 'nonce', 'key', 'license', 'authorization', 'cookie' );
		$out     = array();

		foreach ( $context as $k => $v ) {
			$lower = strtolower( (string) $k );

			foreach ( $blocked as $word ) {
				if ( false !== strpos( $lower, $word ) ) {
					$out[ $k ] = '[حذف‌شده]';
					continue 2;
				}
			}

			if ( is_scalar( $v ) || null === $v ) {
				$out[ $k ] = is_string( $v ) ? substr( $v, 0, 120 ) : $v;
			} else {
				$out[ $k ] = '[ساختار]';
			}
		}

		return $out;
	}

	/**
	 * IP کلاینت با احترام به پروکسی معتبر.
	 *
	 * تنها وقتی هدر پروکسی خوانده می‌شود که مدیر صریحاً با فیلتر
	 * `sparkknife_ledger_trust_proxy` اعلام کرده باشد پشت پروکسی است.
	 * بدون آن، هر کسی می‌تواند با هدر جعلی، محدودیت را دور بزند یا
	 * IP دیگری را در گزارش بدنام کند.
	 *
	 * @return string
	 */
	public static function client_ip() {
		$trust = (bool) apply_filters( 'sparkknife_ledger_trust_proxy', false );

		if ( $trust && ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$raw   = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
			$parts = explode( ',', $raw );
			// راست‌ترین آدرس را خود پروکسی اضافه کرده و قابل جعل نیست.
			$candidate = trim( (string) end( $parts ) );

			if ( filter_var( $candidate, FILTER_VALIDATE_IP ) ) {
				return $candidate;
			}
		}

		$remote = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

		return filter_var( $remote, FILTER_VALIDATE_IP ) ? $remote : '0.0.0.0';
	}

	/**
	 * بررسی‌های سلامت امنیتی، برای نمایش در صفحهٔ سلامت.
	 *
	 * @return array<int, array{label:string, status:string, detail:string}>
	 */
	public static function health_checks() {
		$checks = array();

		// ۱) HTTPS روی پنل مدیریت.
		$is_ssl = is_ssl();

		$checks[] = array(
			'label'  => __( 'اتصال امن (HTTPS)', 'sparkknife-ledger' ),
			'status' => $is_ssl ? 'ok' : 'warn',
			'detail' => $is_ssl
				? __( 'پنل مدیریت روی HTTPS باز می‌شود.', 'sparkknife-ledger' )
				: __( 'سایت روی HTTP است. رمز عبور و داده‌های مالی روی شبکهٔ باز منتقل می‌شوند. برای نصب واقعی HTTPS فعال کنید.', 'sparkknife-ledger' ),
		);

		// ۲) نسخهٔ PHP.
		$php_ok = version_compare( PHP_VERSION, '7.4', '>=' );
		$php_new = version_compare( PHP_VERSION, '8.1', '>=' );

		$checks[] = array(
			'label'  => __( 'نسخهٔ PHP', 'sparkknife-ledger' ),
			'status' => $php_new ? 'ok' : ( $php_ok ? 'warn' : 'fail' ),
			'detail' => sprintf(
				/* translators: %s: PHP version */
				__( 'PHP %s', 'sparkknife-ledger' ),
				PHP_VERSION
			) . ( $php_new ? '' : ' — ' . __( 'PHP 8.1 یا بالاتر امن‌تر و سریع‌تر است.', 'sparkknife-ledger' ) ),
		);

		// ۳) افزونهٔ OpenSSL (برای بررسی لایسنس).
		$has_openssl = function_exists( 'openssl_verify' );

		$checks[] = array(
			'label'  => __( 'OpenSSL', 'sparkknife-ledger' ),
			'status' => $has_openssl ? 'ok' : 'warn',
			'detail' => $has_openssl
				? __( 'برای بررسی امضای لایسنس در دسترس است.', 'sparkknife-ledger' )
				: __( 'در دسترس نیست؛ اگر لایسنس دارید بررسی نمی‌شود. با میزبان خود تماس بگیرید.', 'sparkknife-ledger' ),
		);

		// ۴) دسترسی نوشتن به پوشهٔ آپلود برای پشتیبان‌گیری.
		$uploads = wp_upload_dir();
		$writable = empty( $uploads['error'] ) && is_dir( $uploads['basedir'] ) && wp_is_writable( $uploads['basedir'] );

		$checks[] = array(
			'label'  => __( 'پوشهٔ آپلود قابل نوشتن', 'sparkknife-ledger' ),
			'status' => $writable ? 'ok' : 'warn',
			'detail' => $writable
				? __( 'برای گرفتن پشتیبان پیش از عملیات حساس آماده است.', 'sparkknife-ledger' )
				: __( 'قابل نوشتن نیست؛ گرفتن پشتیبان ممکن نیست. دسترسی پوشه را بررسی کنید.', 'sparkknife-ledger' ),
		);

		// ۵) نمایش خطای دیتابیس.
		$db_errors = defined( 'WP_DEBUG_DISPLAY' ) && WP_DEBUG_DISPLAY;

		$checks[] = array(
			'label'  => __( 'نمایش خطای دیتابیس', 'sparkknife-ledger' ),
			'status' => $db_errors ? 'warn' : 'ok',
			'detail' => $db_errors
				? __( 'خطاهای دیتابیس نمایش داده می‌شوند و ساختار جدول‌ها را لو می‌دهند. روی سایت واقعی WP_DEBUG_DISPLAY را خاموش کنید.', 'sparkknife-ledger' )
				: __( 'خطاهای دیتابیس به کاربر نشان داده نمی‌شوند.', 'sparkknife-ledger' ),
		);

		// ۶) لایسنس.
		$license = Sparkknife_Ledger_License::state();

		$checks[] = array(
			'label'  => __( 'لایسنس', 'sparkknife-ledger' ),
			'status' => ! empty( $license['valid'] ) ? 'ok' : 'info',
			'detail' => $license['message'],
		);

		// ۷) پیشوند جدول.
		global $wpdb;
		$weak_prefix = 'wp_' === $wpdb->prefix;

		$checks[] = array(
			'label'  => __( 'پیشوند جدول', 'sparkknife-ledger' ),
			'status' => $weak_prefix ? 'warn' : 'ok',
			'detail' => $weak_prefix
				? __( 'پیشوند پیش‌فرض wp_ است. تغییرش امنیت واقعی اضافه نمی‌کند ولی اسکن خودکار را کمی سخت‌تر می‌کند.', 'sparkknife-ledger' )
				: __( 'پیشوند جدول پیش‌فرض نیست.', 'sparkknife-ledger' ),
		);

		/**
		 * افزودن بررسی سفارشی.
		 *
		 * @param array $checks بررسی‌ها.
		 */
		return apply_filters( 'sparkknife_ledger_security_checks', $checks );
	}
}
