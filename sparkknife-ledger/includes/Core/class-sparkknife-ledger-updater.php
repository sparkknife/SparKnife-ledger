<?php
/**
 * به‌روزرسانی خودکار افزونه از مخزن GitHub.
 *
 * بدون این کلاس، هر نسخهٔ تازه یعنی: کاربر باید پوشه را پاک کند، زیپ
 * تازه را از GitHub بگیرد و دوباره نصب کند. برای کسی که PHP ندارد و
 * فقط با وردپرس کار می‌کند، همین یک گام کافی است که هیچ‌وقت به‌روزرسانی
 * نکند — و نسخه‌های قدیمی با باگ‌های رفع‌شده در گردش بماند.
 *
 * با این کلاس، وردپرس خودش در «پیشخوان → به‌روزرسانی‌ها» نسخهٔ تازه را
 * می‌بیند و با یک کلیک نصب می‌کند.
 *
 * @author  SparKnife <155084046+sparkknife@users.noreply.github.com>
 *
 * @package SparKnife_Ledger
 */

defined( 'ABSPATH' ) || exit;

/**
 * اتصال چرخهٔ به‌روزرسانی وردپرس به مخزن GitHub.
 */
class Sparkknife_Ledger_Updater {

	/**
	 * مخزن GitHub.
	 *
	 * @var string
	 */
	const REPO = 'sparkknife/SparKnife-ledger';

	/**
	 * شاخه‌ای که نسخه‌های منتشرشده از آن ساخته می‌شوند.
	 *
	 * @var string
	 */
	const BRANCH = 'main';

	/**
	 * کلید کش در transient.
	 *
	 * @var string
	 */
	const CACHE_KEY = 'sparkknife_ledger_update_info';

	/**
	 * مدت کش به ثانیه. وردپرس خودش هر ۱۲ ساعت بررسی می‌کند؛ این کش
	 * جلوی چند درخواست HTTP در یک بار بارگذاری صفحه را می‌گیرد.
	 *
	 * @var int
	 */
	const CACHE_TTL = 21600; // ۶ ساعت.

	/**
	 * ثبت هوک‌ها.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'pre_set_site_transient_update_plugins', array( __CLASS__, 'check_for_update' ) );
		add_filter( 'plugins_api', array( __CLASS__, 'plugin_info' ), 20, 3 );
		add_filter( 'upgrader_source_selection', array( __CLASS__, 'fix_source_dir' ), 10, 4 );
		add_action( 'upgrader_process_complete', array( __CLASS__, 'clear_cache' ), 10, 2 );
		add_action( 'admin_init', array( __CLASS__, 'maybe_flush_on_version_change' ) );
	}

	/**
	 * اطلاعات نسخهٔ منتشرشده.
	 *
	 * از فایل `release.json` در شاخهٔ اصلی مخزن خوانده می‌شود. این فایل
	 * تنها منبع حقیقت است: چه نسخه‌ای هست، از کجا دانلود شود، و چه
	 * چیزهایی عوض شده.
	 *
	 * اگر شبکه در دسترس نباشد یا فایل نباشد، `null` برمی‌گردد و وردپرس
	 * رفتار عادی خودش را دارد — هیچ خطایی به کاربر نشان داده نمی‌شود.
	 * افزونه باید بدون اینترنت هم کامل کار کند.
	 *
	 * @param bool $force نادیده گرفتن کش.
	 * @return array<string, mixed>|null
	 */
	public static function release_info( $force = false ) {
		if ( ! $force ) {
			$cached = get_transient( self::CACHE_KEY );

			if ( is_array( $cached ) && isset( $cached['version'] ) ) {
				return $cached;
			}
		}

		$url = sprintf(
			'https://raw.githubusercontent.com/%s/%s/release.json',
			self::REPO,
			self::BRANCH
		);

		$response = wp_remote_get(
			$url,
			array(
				'timeout'    => 10,
				'user-agent' => 'SparKnife-Ledger/' . SPARKKNIFE_LEDGER_VERSION,
				'headers'    => array( 'Accept' => 'application/json' ),
			)
		);

		if ( is_wp_error( $response ) ) {
			// کش کوتاه برای خطا: پشت سر هم تلاش نکن.
			set_transient( self::CACHE_KEY, array( 'version' => '' ), 900 );

			return null;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );

		if ( 200 !== $code ) {
			set_transient( self::CACHE_KEY, array( 'version' => '' ), 900 );

			return null;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $body ) || empty( $body['version'] ) ) {
			set_transient( self::CACHE_KEY, array( 'version' => '' ), 900 );

			return null;
		}

		$info = array(
			'version'      => (string) $body['version'],
			'download_url' => isset( $body['download_url'] ) ? (string) $body['download_url'] : '',
			'requires'     => isset( $body['requires'] ) ? (string) $body['requires'] : '',
			'tested'       => isset( $body['tested'] ) ? (string) $body['tested'] : '',
			'requires_php' => isset( $body['requires_php'] ) ? (string) $body['requires_php'] : '7.4',
			'changelog'    => isset( $body['changelog'] ) ? (string) $body['changelog'] : '',
			'homepage'     => isset( $body['homepage'] ) ? (string) $body['homepage'] : 'https://github.com/' . self::REPO,
			'last_updated' => isset( $body['last_updated'] ) ? (string) $body['last_updated'] : '',
		);

		// نشانی دانلود اگر نسبی باشد، نسبت به مخزن ساخته می‌شود.
		if ( '' !== $info['download_url'] && 0 !== strpos( $info['download_url'], 'http' ) ) {
			$info['download_url'] = 'https://github.com/' . self::REPO . '/raw/' . self::BRANCH . '/' . ltrim( $info['download_url'], '/' );
		}

		set_transient( self::CACHE_KEY, $info, self::CACHE_TTL );

		return $info;
	}

	/**
	 * تزریق نسخهٔ تازه در فهرست به‌روزرسانی‌های وردپرس.
	 *
	 * @param mixed $transient مقدار transient وردپرس.
	 * @return mixed
	 */
	public static function check_for_update( $transient ) {
		if ( ! is_object( $transient ) ) {
			return $transient;
		}

		$info = self::release_info();

		if ( ! is_array( $info ) || empty( $info['version'] ) || empty( $info['download_url'] ) ) {
			return $transient;
		}

		if ( ! version_compare( $info['version'], SPARKKNIFE_LEDGER_VERSION, '>' ) ) {
			return $transient;
		}

		$item = (object) array(
			'id'           => 'github.com/' . self::REPO,
			'slug'         => dirname( SPARKKNIFE_LEDGER_BASENAME ),
			'plugin'       => SPARKKNIFE_LEDGER_BASENAME,
			'new_version'  => $info['version'],
			'url'          => $info['homepage'],
			'package'      => $info['download_url'],
			'icons'        => array(),
			'banners'      => array(),
			'tested'       => $info['tested'],
			'requires'     => $info['requires'],
			'requires_php' => $info['requires_php'],
		);

		if ( ! isset( $transient->response ) || ! is_array( $transient->response ) ) {
			$transient->response = array();
		}

		$transient->response[ SPARKKNIFE_LEDGER_BASENAME ] = $item;

		/*
		 * اگر نسخهٔ نصب‌شده از منبع دیگری جلوتر بود، آیتم جعلی حذف
		 * می‌شود تا وردپرس پیام «به‌روز است» نشان دهد، نه «نسخهٔ تازه».
		 */
		if ( isset( $transient->no_update ) && is_array( $transient->no_update ) ) {
			unset( $transient->no_update[ SPARKKNIFE_LEDGER_BASENAME ] );
		}

		return $transient;
	}

	/**
	 * اطلاعات «مشاهده جزئیات» در صفحهٔ به‌روزرسانی.
	 *
	 * @param false|object|array $result خروجی پیش‌فرض.
	 * @param string             $action نوع درخواست.
	 * @param object             $args   آرگومان‌ها.
	 * @return false|object|array
	 */
	public static function plugin_info( $result, $action, $args ) {
		if ( 'plugin_information' !== $action ) {
			return $result;
		}

		if ( empty( $args->slug ) || dirname( SPARKKNIFE_LEDGER_BASENAME ) !== $args->slug ) {
			return $result;
		}

		$info = self::release_info();

		if ( ! is_array( $info ) ) {
			return $result;
		}

		return (object) array(
			'name'          => 'SparKnife Ledger',
			'slug'          => $args->slug,
			'version'       => $info['version'],
			'author'        => '<a href="https://github.com/sparkknife">SparKnife</a>',
			'homepage'      => $info['homepage'],
			'download_link' => $info['download_url'],
			'requires'      => $info['requires'],
			'tested'        => $info['tested'],
			'requires_php'  => $info['requires_php'],
			'last_updated'  => $info['last_updated'],
			'sections'      => array(
				'description' => __( 'دفتر حساب اشخاص برای وردپرس — بدهکار و بستانکار، ماندهٔ دقیق، تاریخچهٔ تغییرات.', 'sparkknife-ledger' ),
				'changelog'   => '' !== $info['changelog']
					? wp_kses_post( $info['changelog'] )
					: __( 'توضیح تغییرات در مخزن پروژه است.', 'sparkknife-ledger' ),
			),
		);
	}

	/**
	 * اصلاح نام پوشه پس از استخراج.
	 *
	 * GitHub وقتی از یک شاخه زیپ می‌سازد، نام پوشهٔ داخلش
	 * `SparKnife-ledger-main` است، نه `sparkknife-ledger`. وردپرس اگر
	 * نام پوشه را عوض نکند، افزونه را در پوشهٔ تازه نصب می‌کند و نسخهٔ
	 * قبلی سر جایش می‌ماند — نتیجه: دو افزونهٔ فعال با نام یکسان و
	 * خطای «هدر نامعتبر».
	 *
	 * این فیلتر نام پوشه را پیش از نصب به نام فعلی برمی‌گرداند.
	 *
	 * @param string      $source        مسیر پوشهٔ استخراج‌شده.
	 * @param string      $remote_source مسیر منبع.
	 * @param WP_Upgrader $upgrader      شیء به‌روزرسان.
	 * @param array       $hook_extra    اطلاعات اضافی.
	 * @return string|WP_Error
	 */
	public static function fix_source_dir( $source, $remote_source, $upgrader, $hook_extra = array() ) {
		global $wp_filesystem;

		if ( ! isset( $hook_extra['plugin'] ) || SPARKKNIFE_LEDGER_BASENAME !== $hook_extra['plugin'] ) {
			return $source;
		}

		$desired = dirname( SPARKKNIFE_LEDGER_BASENAME );

		if ( basename( $source ) === $desired ) {
			return $source;
		}

		$target = trailingslashit( $remote_source ) . $desired;

		if ( $wp_filesystem && $wp_filesystem->move( $source, $target ) ) {
			return $target;
		}

		return $source;
	}

	/**
	 * پاک کردن کش پس از به‌روزرسانی.
	 *
	 * @param WP_Upgrader $upgrader شیء به‌روزرسان.
	 * @param array       $data     اطلاعات عملیات.
	 * @return void
	 */
	public static function clear_cache( $upgrader, $data ) {
		if ( ! is_array( $data ) || 'update' !== ( $data['action'] ?? '' ) ) {
			return;
		}

		if ( 'plugin' !== ( $data['type'] ?? '' ) ) {
			return;
		}

		delete_transient( self::CACHE_KEY );
	}

	/**
	 * اگر نسخهٔ کد عوض شده، کش را دور بریز.
	 *
	 * بدون این، پس از به‌روزرسانی دستی (کپی پوشه) ممکن است تا ۶ ساعت
	 * پیام «نسخهٔ تازه موجود است» برای نسخه‌ای که همین حالا نصب شده
	 * باقی بماند.
	 *
	 * @return void
	 */
	public static function maybe_flush_on_version_change() {
		$seen = get_option( 'sparkknife_ledger_seen_version', '' );

		if ( $seen === SPARKKNIFE_LEDGER_VERSION ) {
			return;
		}

		delete_transient( self::CACHE_KEY );
		update_option( 'sparkknife_ledger_seen_version', SPARKKNIFE_LEDGER_VERSION, false );
	}
}
