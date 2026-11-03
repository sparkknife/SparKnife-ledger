<?php
/**
 * تنظیمات افزونه.
 *
 * @package SparKnife_Ledger
 */

defined( 'ABSPATH' ) || exit;

/**
 * مدیریت تنظیمات در wp_options.
 */
class Sparkknife_Ledger_Settings {

	/**
	 * کلید آپشن.
	 */
	const OPTION_KEY = 'sparkknife_ledger_settings';

	/**
	 * کش داخلی تنظیمات.
	 *
	 * @var array<string, mixed>|null
	 */
	private static $cache = null;

	/**
	 * مقادیر پیش‌فرض.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults() {
		$defaults = array(
			// واحد پول.
			'currency_type'            => 'toman',
			'custom_currency_label'    => '',
			'custom_currency_symbol'   => '',
			'custom_currency_code'     => '',
			'currency_decimals'        => 0,
			'currency_position'        => 'suffix',
			'thousands_separator'      => ',',
			'use_persian_digits'       => 1,
			// تاریخ.
			'calendar'                 => 'jalali',
			// عمومی.
			'per_page'                 => 20,
			'reference_prefix'         => 'TXN',
			'require_void_reason'      => 1,
			'allow_person_delete'      => 1,
			'default_transaction_type' => 'income',
			'show_welcome_panel'       => 1,
			// حریم داده در زمان حذف افزونه.
			'delete_data_on_uninstall' => 0,
		);

		/**
		 * فیلتر مقادیر پیش‌فرض تنظیمات.
		 *
		 * @param array<string, mixed> $defaults پیش‌فرض‌ها.
		 */
		return apply_filters( 'sparkknife_ledger_default_settings', $defaults );
	}

	/**
	 * ذخیره پیش‌فرض‌ها در زمان فعال‌سازی (بدون بازنویسی مقادیر موجود).
	 *
	 * @return void
	 */
	public static function install_defaults() {
		$existing = get_option( self::OPTION_KEY, array() );

		if ( ! is_array( $existing ) ) {
			$existing = array();
		}

		$merged = array_merge( self::defaults(), $existing );

		update_option( self::OPTION_KEY, $merged, false );

		// مقدار پیش‌فرض حذف داده در زمان حذف افزونه.
		if ( false === get_option( 'sparkknife_ledger_delete_data_on_uninstall', false ) ) {
			update_option( 'sparkknife_ledger_delete_data_on_uninstall', 'no', false );
		}

		self::$cache = null;
	}

	/**
	 * دریافت تمام تنظیمات.
	 *
	 * @return array<string, mixed>
	 */
	public static function all() {
		if ( null !== self::$cache ) {
			return self::$cache;
		}

		$stored = get_option( self::OPTION_KEY, array() );

		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		self::$cache = array_merge( self::defaults(), $stored );

		return self::$cache;
	}

	/**
	 * خواندن یک تنظیم.
	 *
	 * @param string $key     کلید.
	 * @param mixed  $default مقدار جایگزین.
	 * @return mixed
	 */
	public static function get( $key, $default = null ) {
		$all = self::all();

		if ( array_key_exists( $key, $all ) ) {
			return $all[ $key ];
		}

		return null === $default ? null : $default;
	}

	/**
	 * ذخیره مجموعه‌ای از تنظیمات با پاک‌سازی نوع‌محور.
	 *
	 * @param array<string, mixed> $input       ورودی خام (پاک‌سازی‌نشده).
	 * @param bool                 $log_change  ثبت در تاریخچه تغییرات.
	 * @return array<string, mixed> تنظیمات ذخیره‌شده.
	 */
	public static function save( array $input, $log_change = true ) {
		$current  = self::all();
		$defaults = self::defaults();
		$sanitized = array();

		$allowed_currency = array_keys( Sparkknife_Ledger_Money::currency_presets() );

		$text_fields = array(
			'custom_currency_label'  => 60,
			'custom_currency_symbol' => 10,
			'custom_currency_code'   => 10,
			'reference_prefix'       => 10,
		);

		foreach ( $text_fields as $field => $limit ) {
			$value = isset( $input[ $field ] ) ? sanitize_text_field( wp_unslash( $input[ $field ] ) ) : '';
			$value = function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $limit ) : substr( $value, 0, $limit );

			if ( '' !== $value || ! isset( $current[ $field ] ) ) {
				$sanitized[ $field ] = $value;
			}
		}

		$sanitized['currency_type'] = isset( $input['currency_type'] ) && in_array( $input['currency_type'], $allowed_currency, true )
			? $input['currency_type']
			: (string) $current['currency_type'];

		$sanitized['currency_decimals'] = isset( $input['currency_decimals'] )
			? max( 0, min( 4, absint( $input['currency_decimals'] ) ) )
			: (int) $current['currency_decimals'];

		$sanitized['currency_position'] = isset( $input['currency_position'] ) && in_array( $input['currency_position'], array( 'suffix', 'prefix' ), true )
			? $input['currency_position']
			: (string) $current['currency_position'];

		$separators = array( ',', '.', '٬', ' ' );
		$sanitized['thousands_separator'] = isset( $input['thousands_separator'] ) && in_array( $input['thousands_separator'], $separators, true )
			? $input['thousands_separator']
			: (string) $current['thousands_separator'];

		$sanitized['calendar'] = isset( $input['calendar'] ) && in_array( $input['calendar'], array( 'jalali', 'gregorian' ), true )
			? $input['calendar']
			: (string) $current['calendar'];

		$sanitized['default_transaction_type'] = isset( $input['default_transaction_type'] ) && in_array( $input['default_transaction_type'], array( 'income', 'expense' ), true )
			? $input['default_transaction_type']
			: (string) $current['default_transaction_type'];

		$sanitized['per_page'] = isset( $input['per_page'] )
			? max( 5, min( 200, absint( $input['per_page'] ) ) )
			: (int) $current['per_page'];

		foreach ( array( 'use_persian_digits', 'require_void_reason', 'allow_person_delete', 'show_welcome_panel', 'delete_data_on_uninstall' ) as $checkbox ) {
			$sanitized[ $checkbox ] = empty( $input[ $checkbox ] ) ? 0 : 1;
		}

		// محافظت: فرم‌هایی که فقط بخشی از فیلدها را دارند مقدار سایر فیلدها را از دست نمی‌دهند.
		$sections = isset( $input['settings_section'] ) ? sanitize_key( $input['settings_section'] ) : 'all';

		if ( 'all' !== $sections ) {
			$kept = $current;
			foreach ( $sanitized as $key => $value ) {
				$kept[ $key ] = $value;
			}
			// فیلدهای مربوط به بخش‌های دیگر، همان مقدار قبلی را نگه می‌دارند.
			foreach ( $defaults as $key => $value ) {
				if ( ! array_key_exists( $key, $sanitized ) ) {
					$kept[ $key ] = array_key_exists( $key, $current ) ? $current[ $key ] : $value;
				}
			}
			$sanitized = $kept;
		}

		$sanitized = array_merge( $defaults, $sanitized );

		$changed = array();
		foreach ( $sanitized as $key => $value ) {
			$old = array_key_exists( $key, $current ) ? $current[ $key ] : null;
			if ( (string) $old !== (string) $value ) {
				$changed[ $key ] = array(
					'from' => $old,
					'to'   => $value,
				);
			}
		}

		update_option( self::OPTION_KEY, $sanitized, false );
		self::$cache = $sanitized;

		if ( $log_change && ! empty( $changed ) ) {
			// برای جلوگیری از ثبت مقادیر حساس، فقط کلیدها و مقادیر غیرحساس ثبت می‌شود.
			Sparkknife_Ledger_Audit_Service::instance()->log(
				'settings',
				0,
				'settings_update',
				array( 'fields' => $changed ),
				__( 'به‌روزرسانی تنظیمات افزونه', 'sparkknife-ledger' )
			);
		}

		/**
		 * پس از ذخیره تنظیمات.
		 *
		 * @param array<string, mixed> $sanitized تنظیمات جدید.
		 * @param array<string, mixed> $current   تنظیمات قبلی.
		 */
		do_action( 'sparkknife_ledger_settings_saved', $sanitized, $current );

		return $sanitized;
	}

	/**
	 * پاک‌سازی کش (مثلاً پس از تغییر تنظیمات توسط افزونه دیگر).
	 *
	 * @return void
	 */
	public static function flush_cache() {
		self::$cache = null;
	}
}
