<?php
/**
 * تنظیمات افزونه.
 *
 * @author  SparKnife <155084046+sparkknife@users.noreply.github.com>
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
	 * اثر انگشت گزینه در لحظهٔ پر شدن کش.
	 *
	 * @var string|null
	 */
	private static $cache_signature = null;

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
	 * کلیدهای متعلق به هر تب تنظیمات.
	 *
	 * هر تب فرم جداگانه‌ای دارد و فقط فیلدهای خودش را ارسال می‌کند. برای
	 * اینکه ذخیره یک تب، چک‌باکس‌های تب‌های دیگر را خاموش نکند، دامنه
	 * به‌روزرسانی با این نگاشت محدود می‌شود.
	 *
	 * تب `display` و `general` هر دو در یک فرم هستند، پس کلیدهای هر دو
	 * یکجا برمی‌گردد.
	 *
	 * @param string $section نام بخش.
	 * @return array<int, string>
	 */
	public static function section_keys( $section ) {
		$display_and_general = array(
			'use_persian_digits',
			'calendar',
			'thousands_separator',
			'per_page',
			'reference_prefix',
			'default_transaction_type',
			'require_void_reason',
			'allow_person_delete',
			'show_welcome_panel',
		);

		$map = array(
			'currency' => array(
				'currency_type',
				'custom_currency_label',
				'custom_currency_symbol',
				'custom_currency_code',
				'currency_decimals',
				'currency_position',
			),
			'display'  => $display_and_general,
			'general'  => $display_and_general,
			'data'     => array( 'delete_data_on_uninstall' ),
		);

		return isset( $map[ $section ] ) ? $map[ $section ] : array();
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
		if ( null !== self::$cache && self::$cache_signature === self::signature() ) {
			return self::$cache;
		}

		$stored = get_option( self::OPTION_KEY, array() );

		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		self::$cache           = array_merge( self::defaults(), $stored );
		self::$cache_signature = self::signature();

		return self::$cache;
	}

	/**
	 * اثر انگشت ارزان گزینهٔ تنظیمات.
	 *
	 * چرا لازم است: `self::$cache` قبلاً فقط با `flush_cache()` صریح پاک
	 * می‌شد. اگر گزینه از راه دیگری عوض می‌شد، افزونه تا پایان همان
	 * درخواست مقدار کهنه را برمی‌گرداند.
	 *
	 * این حالت فرضی نیست:
	 *
	 *   - `uninstall.php` مقدار را مستقیم عوض می‌کند؛
	 *   - مهاجرت‌ها و WP-CLI ممکن است بین دو خواندن بنویسند؛
	 *   - افزونهٔ دیگری که تنظیمات را مهاجرت می‌دهد.
	 *
	 * پیامد در یک نرم‌افزار مالی جدی است: کاربر واحد پول اشتباه روی همهٔ
	 * مبالغ می‌بیند و تصمیم غلط می‌گیرد. آزمون تجربی: دیتابیس `rial`
	 * ولی نمایش «یورو».
	 *
	 * `get_option()` خودش از آپشن‌کش وردپرس می‌آید و در درخواست‌های
	 * عادی از حافظه می‌خواند، پس این بررسی هزینهٔ کوئری اضافه ندارد.
	 * اثر انگشت شامل تعداد کلیدها و یک هش از سریال‌سازی است تا حتی
	 * تغییر یک مقدار (نه فقط افزودن/حذف کلید) هم دیده شود.
	 *
	 * @return string
	 */
	private static function signature() {
		$stored = get_option( self::OPTION_KEY, array() );

		if ( ! is_array( $stored ) ) {
			return 'none';
		}

		return count( $stored ) . ':' . md5( (string) wp_json_encode( $stored ) );
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
			$value = Sparkknife_Ledger_Sanitizer::limit( $value, $limit );

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

		/*
		 * محافظت: فرم‌ها فقط فیلدهای تب فعال را ارسال می‌کنند؛ چک‌باکسِ ارسال‌نشده
		 * به معنی «خاموش» نیست، به معنی «در این فرم نبوده» است.
		 *
		 * فرم تنظیمات فیلد `active_tab` را می‌فرستد. اگر بخش مشخص شده باشد،
		 * فقط کلیدهای همان بخش با ورودی جدید به‌روزرسانی می‌شوند و بقیه مقدار
		 * قبلی خود را نگه می‌دارند.
		 */
		$sections = 'all';

		if ( isset( $input['settings_section'] ) ) {
			$sections = sanitize_key( $input['settings_section'] );
		} elseif ( isset( $input['active_tab'] ) ) {
			$sections = sanitize_key( $input['active_tab'] );
		}

		if ( 'all' !== $sections ) {
			$section_keys = self::section_keys( $sections );

			if ( ! empty( $section_keys ) ) {
				$kept = $current;

				foreach ( $section_keys as $key ) {
					if ( array_key_exists( $key, $sanitized ) ) {
						$kept[ $key ] = $sanitized[ $key ];
					}
				}

				$sanitized = $kept;
			} else {
				/*
				 * تب ناشناخته: هیچ چیز تغییر نمی‌کند.
				 *
				 * `section_keys()` برای نام ناشناخته آرایه خالی برمی‌گرداند
				 * و شرط بالا آن را رد می‌کرد، پس `$sanitized` دست‌نخورده
				 * می‌ماند و همه چک‌باکس‌ها صفر می‌شدند — همان باگی که پاس
				 * اول برای تب‌های شناخته‌شده رفع کرده بود، از راه دیگری.
				 * یک فرم دست‌کاری‌شده یا افزونه‌ای که `active_tab` غیرمنتظره
				 * بفرستد می‌توانست تنظیمات ظاهری و امنیتی کاربر را خاموش کند.
				 */
				return new WP_Error(
					'sparkknife_ledger_unknown_settings_section',
					__( 'بخش تنظیمات شناخته نشد؛ چیزی ذخیره نشد.', 'sparkknife-ledger' )
				);
			}
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

		/*
		 * گزینه مستقل `delete_data_on_uninstall` را با آرایه تنظیمات هم‌گام
		 * نگه می‌داریم؛ uninstall.php هر دو را می‌خواند و ناهم‌گامی باعث
		 * می‌شد تنظیم روشن، در زمان حذف افزونه نادیده گرفته شود.
		 */
		update_option(
			'sparkknife_ledger_delete_data_on_uninstall',
			empty( $sanitized['delete_data_on_uninstall'] ) ? 'no' : 'yes',
			false
		);

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
		self::$cache           = null;
		self::$cache_signature = null;
	}
}
