<?php
/**
 * سرویس تاریخچه تغییرات (Audit Log).
 *
 * هر تغییر مهم با اطلاعات کاربر، IP، User-Agent و اختلاف فیلدها ثبت
 * می‌شود و با زنجیره هش SHA-256 در برابر دستکاری محافظت می‌شود.
 *
 * @author  SparKnife <155084046+sparkknife@users.noreply.github.com>
 *
 * @package SparKnife_Ledger
 */

defined( 'ABSPATH' ) || exit;

/**
 * نوشتن و خواندن تاریخچه.
 */
class Sparkknife_Ledger_Audit_Service {

	/**
	 * نمونه یکتا.
	 *
	 * @var Sparkknife_Ledger_Audit_Service|null
	 */
	private static $instance = null;

	/**
	 * دریافت نمونه.
	 *
	 * @return Sparkknife_Ledger_Audit_Service
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * فهرست types قابل ثبت.
	 *
	 * @return array<int, string>
	 */
	public static function object_types() {
		return array( 'person', 'transaction', 'settings' );
	}

	/**
	 * فهرست actionها با برچسب فارسی.
	 *
	 * @return array<string, string>
	 */
	public static function actions() {
		return array(
			'create'              => __( 'ایجاد', 'sparkknife-ledger' ),
			'update'              => __( 'ویرایش', 'sparkknife-ledger' ),
			'void'                => __( 'ابطال', 'sparkknife-ledger' ),
			'delete'              => __( 'حذف', 'sparkknife-ledger' ),
			'restore'             => __( 'بازگردانی', 'sparkknife-ledger' ),
			'status_change'       => __( 'تغییر وضعیت', 'sparkknife-ledger' ),
			'settings_update'     => __( 'تغییر تنظیمات', 'sparkknife-ledger' ),
			'capabilities_update' => __( 'تغییر دسترسی‌ها', 'sparkknife-ledger' ),
			'purge_data'          => __( 'پاک‌سازی داده‌ها', 'sparkknife-ledger' ),
			'export'              => __( 'خروجی گزارش', 'sparkknife-ledger' ),
		);
	}

	/**
	 * ثبت یک رویداد در تاریخچه.
	 *
	 * ثبت تاریخچه هرگز نباید عملیات اصلی را متوقف کند؛ خطاها بی‌صدا نادیده
	 * گرفته می‌شوند اما در حالت دیباگ لاگ می‌شوند.
	 *
	 * @param string               $object_type نوع موجودیت.
	 * @param int                  $object_id   شناسه موجودیت.
	 * @param string               $action      نوع عملیات.
	 * @param array<string, mixed> $changes     تغییرات (قبل/بعد).
	 * @param string               $summary     توضیح خوانا.
	 * @return int|false شناسه ردیف تاریخچه.
	 */
	public function log( $object_type, $object_id, $action, array $changes = array(), $summary = '' ) {
		global $wpdb;

		if ( ! Sparkknife_Ledger_DB::tables_exist() ) {
			return false;
		}

		/*
		 * وجود جدول کافی نیست: اگر ساختش نصفه ماند (مثلاً فقط ستون id)
		 * درج در آن شکست می‌خورد و `$wpdb` خطای SQL را روی صفحه چاپ
		 * می‌کند. پیش‌تر صفحه مدیر پر از HTML خطا می‌شد. اکنون ساختار
		 * یک بار در هر درخواست سنجیده می‌شود و در صورت نقص، ثبت ممیزی
		 * بی‌صدا کنار گذاشته می‌شود.
		 */
		if ( ! Sparkknife_Ledger_DB::audit_schema_is_intact() ) {
			return false;
		}

		// فیلدهای حساس هرگز ذخیره نمی‌شوند.
		$changes = $this->scrub_sensitive( $changes );

		$user_id = get_current_user_id();
		$user    = $user_id ? get_userdata( $user_id ) : null;

		/*
		 * همه برش‌ها نویسه‌ای است، نه بایتی.
		 *
		 * `substr()` روی UTF-8 می‌تواند یک نویسه را از وسط ببرد و رشته‌ای
		 * خراب بسازد که نه درست نمایش داده می‌شود نه در JSON سالم است.
		 * User-Agent هم ورودی کاربر است و می‌تواند هر چیزی باشد.
		 */
		$payload = array(
			'object_type' => Sparkknife_Ledger_Sanitizer::limit( (string) $object_type, 30 ),
			'object_id'   => (int) $object_id,
			'action'      => Sparkknife_Ledger_Sanitizer::limit( (string) $action, 40 ),
			'user_id'     => $user_id,
			'changes'     => $changes,
			'summary'     => Sparkknife_Ledger_Sanitizer::textarea( $summary, 500 ),
			'ip_address'  => $this->get_ip(),
			'user_agent'  => isset( $_SERVER['HTTP_USER_AGENT'] )
				? Sparkknife_Ledger_Sanitizer::limit( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ), 255 )
				: '',
			'user_login'  => $user ? $user->user_login : '',
			'created_at'  => Sparkknife_Ledger_Date::now(),
		);

		$json = wp_json_encode( $changes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		$json = is_string( $json ) ? $json : '{}';

		/*
		 * اگر جدول ممیزی ناقص یا غایب باشد (مثلاً ساختش نصفه ماند)،
		 * کوئری‌های زیر خطای SQL می‌دهند. `$wpdb` این خطاها را روی صفحه
		 * چاپ می‌کند و صفحه مدیر را پر از HTML خطا می‌کند، در حالی که
		 * ثبت ممیزی نباید هرگز جلوی کار اصلی را بگیرد یا صفحه را بشکند.
		 * خطا سرکوب می‌شود؛ نتیجه همچنان `false` برمی‌گردد و گزارش سلامت
		 * جدول را «خراب» نشان می‌دهد.
		 */
		$suppress_errors = $wpdb->suppress_errors( true );

		$last = Sparkknife_Ledger_Audit_Repository::instance()->last_hash();
		$hash    = hash( 'sha256', $last . '|' . $json . '|' . $payload['created_at'] . '|' . $user_id . '|' . wp_json_encode( $payload['object_type'] . $payload['object_id'] . $payload['action'], JSON_UNESCAPED_UNICODE ) );

		$row = array(
			'object_type' => $payload['object_type'],
			'object_id'   => $payload['object_id'],
			'action'      => $payload['action'],
			'user_id'     => $payload['user_id'],
			'changes'     => $json,
			'summary'     => $payload['summary'],
			'ip_address'  => $payload['ip_address'],
			'user_agent'  => $payload['user_agent'],
			'user_login'  => $payload['user_login'],
			'hash'        => $hash,
		);

		$id = Sparkknife_Ledger_Audit_Repository::instance()->insert( $row );

		$wpdb->suppress_errors( $suppress_errors );

		if ( false === $id ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'SparKnife Ledger: ثبت تاریخچه ناموفق بود.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
			return false;
		}

		/**
		 * پس از ثبت رویداد تاریخچه.
		 *
		 * @param string               $object_type نوع موجودیت.
		 * @param int                  $object_id   شناسه.
		 * @param string               $action      عملیات.
		 * @param array<string, mixed> $changes     تغییرات.
		 */
		do_action( 'sparkknife_ledger_audit_logged', $object_type, $object_id, $action, $changes );

		return $id;
	}

	/**
	 * محاسبه اختلاف بین دو نسخه از داده.
	 *
	 * @param array<string, mixed> $before داده قبل.
	 * @param array<string, mixed> $after  داده بعد.
	 * @param array<int, string>   $fields فیلدهای مورد بررسی.
	 * @return array<string, array<string, mixed>>
	 */
	public function diff( array $before, array $after, array $fields ) {
		$changes = array();

		foreach ( $fields as $field ) {
			$old = array_key_exists( $field, $before ) ? $before[ $field ] : null;
			$new = array_key_exists( $field, $after ) ? $after[ $field ] : null;

			if ( is_scalar( $old ) && is_scalar( $new ) ) {
				if ( (string) $old === (string) $new ) {
					continue;
				}
			} elseif ( $old === $new ) {
				continue;
			}

			$changes[ $field ] = array(
				'from' => $this->present_value( $field, $old ),
				'to'   => $this->present_value( $field, $new ),
			);
		}

		return $changes;
	}

	/**
	 * تبدیل مقدار برای نمایش در تاریخچه (مبلغ → مبلغ خوانا).
	 *
	 * @param string $field نام فیلد.
	 * @param mixed  $value مقدار.
	 * @return mixed
	 */
	private function present_value( $field, $value ) {
		if ( 'amount' === $field && is_numeric( $value ) ) {
			// بدون تبدیل به int: در PHP ۳۲-بیتی مبالغ بزرگ سرریز می‌شوند.
			return Sparkknife_Ledger_Money::to_human( (string) $value );
		}

		if ( is_null( $value ) ) {
			return null;
		}

		if ( is_scalar( $value ) ) {
			return (string) $value;
		}

		return $value;
	}

	/**
	 * حذف فیلدهای حساس از داده ثبت‌شده.
	 *
	 * @param mixed $data داده.
	 * @return mixed
	 */
	private function scrub_sensitive( $data ) {
		if ( ! is_array( $data ) ) {
			return $data;
		}

		$blocked = array( 'password', 'pass', 'token', 'secret', 'nonce', '_wpnonce', 'api_key', 'cookie' );

		foreach ( $data as $key => $value ) {
			if ( is_string( $key ) && in_array( strtolower( $key ), $blocked, true ) ) {
				unset( $data[ $key ] );
				continue;
			}

			if ( is_array( $value ) ) {
				$data[ $key ] = $this->scrub_sensitive( $value );
			} elseif ( is_string( $value ) && strlen( $value ) > 1000 ) {
				$data[ $key ] = substr( $value, 0, 1000 ) . '…';
			}
		}

		return $data;
	}

	/**
	 * دریافت IP کاربر با رعایت پروکسی‌های معتبر.
	 *
	 * @return string
	 */
	private function get_ip() {
		$ip = '';

		if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}

		/*
		 * `X-Forwarded-For` تنها وقتی پذیرفته می‌شود که سایت صریحاً اعلام
		 * کند پشت پروکسی است.
		 *
		 * پیش‌تر این هدر همیشه بر `REMOTE_ADDR` اولویت داشت و هر کاربری
		 * می‌توانست با فرستادن آن، هر IP دلخواهی را در لاگ ممیزی ثبت کند.
		 * در دفتر مالی، IP ابزار ردیابی تغییرات است؛ اگر قابل جعل باشد،
		 * ارزش اثباتی لاگ از بین می‌رود.
		 *
		 * برای فعال‌سازی، در `wp-config.php` بگذارید:
		 *
		 *     define( 'SPARKKNIFE_LEDGER_TRUST_PROXY', true );
		 *
		 * یا با فیلتر:
		 *
		 *     add_filter( 'sparkknife_ledger_trust_proxy', '__return_true' );
		 *
		 * و اگر پروکسی از نام میزبان خاصی می‌آید، آدرس‌های مجاز را با
		 * فیلتر `sparkknife_ledger_trusted_proxies` محدود کنید.
		 */
		if ( ! $this->trusts_proxy() ) {
			return $ip;
		}

		if ( empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			return $ip;
		}

		/*
		 * هدر فقط وقتی پذیرفته می‌شود که درخواست واقعاً از یک پروکسی
		 * مجاز آمده باشد.
		 *
		 * این بررسی پیش‌تر **وجود نداشت**، در حالی که توضیح بالای همین
		 * تابع آن را وعده می‌داد و فیلتر `sparkknife_ledger_trusted_proxies`
		 * را هم معرفی می‌کرد. نتیجه: با فعال‌کردن `TRUST_PROXY`، هر
		 * کلاینتی که مستقیماً به سایت وصل می‌شد می‌توانست با فرستادن
		 * `X-Forwarded-For` هر IP دلخواهی را در لاگ ممیزی بنشاند —
		 * همان جعل هویتی که کل این منطق برای جلوگیری از آن نوشته شده.
		 *
		 * اگر فهرست پروکسی‌های مجاز خالی باشد، هدر پذیرفته می‌شود تا
		 * نصب‌های پشت CDN که فهرست ثابت ندارند نشکنند.
		 */
		$trusted_proxies = apply_filters( 'sparkknife_ledger_trusted_proxies', array() );
		$trusted_proxies = is_array( $trusted_proxies ) ? array_filter( array_map( 'trim', $trusted_proxies ) ) : array();

		if ( ! empty( $trusted_proxies ) && ! in_array( $ip, $trusted_proxies, true ) ) {
			return $ip;
		}

		$forwarded = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );

		/*
		 * فقط آخرین (راست‌ترین) آدرس در زنجیره قابل اتکاست، چون بقیه را
		 * خود کلاینت نوشته است. پس از راست به چپ می‌گردیم و نخستین
		 * آدرس معتبر را برمی‌داریم؛ پیش‌تر حلقه از چپ به راست می‌رفت و
		 * «آخرین معتبر» را می‌گرفت که در زنجیرهٔ «جعل، واقعی، آشغال»
		 * همان آدرس جعلی می‌شد.
		 */
		$parts = array_reverse( array_map( 'trim', explode( ',', $forwarded ) ) );

		foreach ( $parts as $candidate ) {
			if ( filter_var( $candidate, FILTER_VALIDATE_IP ) ) {
				return $candidate;
			}
		}

		return $ip;
	}

	/**
	 * آیا هدر پروکسی قابل اتکاست؟
	 *
	 * @return bool
	 */
	private function trusts_proxy() {
		$trust = defined( 'SPARKKNIFE_LEDGER_TRUST_PROXY' ) && SPARKKNIFE_LEDGER_TRUST_PROXY;

		/**
		 * امکان اتکا به هدر `X-Forwarded-For`.
		 *
		 * @param bool $trust مقدار پیش‌فرض.
		 */
		return (bool) apply_filters( 'sparkknife_ledger_trust_proxy', $trust );
	}

	/**
	 * تاریخچه یک موجودیت.
	 *
	 * @param string $object_type نوع.
	 * @param int    $object_id   شناسه.
	 * @param int    $limit       تعداد.
	 * @return array<int, object>
	 */
	public function get_history( $object_type, $object_id, $limit = 50 ) {
		$result = Sparkknife_Ledger_Audit_Repository::instance()->query(
			array(
				'object_type' => $object_type,
				'object_id'   => absint( $object_id ),
				'per_page'    => max( 1, min( 200, (int) $limit ) ),
				'page'        => 1,
			)
		);

		return $result['items'];
	}

	/**
	 * خواندن تغییرات یک ردیف تاریخچه.
	 *
	 * @param object $row ردیف.
	 * @return array<string, mixed>
	 */
	public function decode_changes( $row ) {
		if ( empty( $row->changes ) ) {
			return array();
		}

		$decoded = json_decode( (string) $row->changes, true );

		return is_array( $decoded ) ? $decoded : array();
	}

	/**
	 * برچسب خوانا برای فیلدها.
	 *
	 * @param string $field نام فیلد.
	 * @return string
	 */
	public function field_label( $field ) {
		$labels = array(
			'name'        => __( 'نام', 'sparkknife-ledger' ),
			'phone'       => __( 'شماره تماس', 'sparkknife-ledger' ),
			'description' => __( 'توضیحات', 'sparkknife-ledger' ),
			'status'      => __( 'وضعیت', 'sparkknife-ledger' ),
			'person_id'   => __( 'شخص', 'sparkknife-ledger' ),
			'type'        => __( 'نوع تراکنش', 'sparkknife-ledger' ),
			'amount'      => __( 'مبلغ', 'sparkknife-ledger' ),
			'txn_date'    => __( 'تاریخ تراکنش', 'sparkknife-ledger' ),
			'void_reason' => __( 'دلیل ابطال', 'sparkknife-ledger' ),
			'reference'   => __( 'شناسه تراکنش', 'sparkknife-ledger' ),
			'currency_type'       => __( 'واحد پول', 'sparkknife-ledger' ),
			'currency_decimals'   => __( 'تعداد اعشار', 'sparkknife-ledger' ),
			'currency_position'   => __( 'جایگاه نماد پول', 'sparkknife-ledger' ),
			'thousands_separator' => __( 'جداکننده هزارگان', 'sparkknife-ledger' ),
			'calendar'            => __( 'تقویم', 'sparkknife-ledger' ),
			'per_page'            => __( 'تعداد ردیف در صفحه', 'sparkknife-ledger' ),
		);

		return isset( $labels[ $field ] ) ? $labels[ $field ] : $field;
	}

	/**
	 * مقدار خوانا برای نمایش در تاریخچه.
	 *
	 * @param string $field نام فیلد.
	 * @param mixed  $value مقدار.
	 * @return string
	 */
	public function present_for_display( $field, $value ) {
		if ( null === $value || '' === $value ) {
			return '—';
		}

		if ( ! is_scalar( $value ) ) {
			return wp_json_encode( $value, JSON_UNESCAPED_UNICODE );
		}

		$value = (string) $value;

		if ( 'type' === $field ) {
			return Sparkknife_Ledger_Money::transaction_type_label( $value );
		}

		if ( 'status' === $field ) {
			$labels = array(
				'active'   => __( 'فعال', 'sparkknife-ledger' ),
				'inactive' => __( 'غیرفعال', 'sparkknife-ledger' ),
				'posted'   => __( 'ثبت‌شده', 'sparkknife-ledger' ),
				'void'     => __( 'باطل‌شده', 'sparkknife-ledger' ),
				'1'        => __( 'فعال', 'sparkknife-ledger' ),
				'0'        => __( 'غیرفعال', 'sparkknife-ledger' ),
			);

			return isset( $labels[ $value ] ) ? $labels[ $value ] : $value;
		}

		if ( 'person_id' === $field ) {
			$person = Sparkknife_Ledger_Person_Repository::instance()->find( absint( $value ), true );
			return $person ? $person->name : $value;
		}

		if ( 'txn_date' === $field ) {
			return Sparkknife_Ledger_Date::format( $value );
		}

		if ( 'amount' === $field && is_numeric( $value ) ) {
			return Sparkknife_Ledger_Money::format( (int) $value );
		}

		return $value;
	}
}
