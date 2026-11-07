<?php
/**
 * مدیریت سطوح دسترسی.
 *
 * @author  SparKnife <155084046+sparkknife@users.noreply.github.com>
 *
 * @package SparKnife_Ledger
 */

defined( 'ABSPATH' ) || exit;

/**
 * تعریف و بررسی Capabilityهای افزونه.
 */
class Sparkknife_Ledger_Capabilities {

	/**
	 * پیشوند اختصاصی Capabilityها.
	 */
	const PREFIX = 'sparkknife_ledger_';

	/**
	 * فهرست Capabilityها با برچسب فارسی.
	 *
	 * @return array<string, string>
	 */
	public static function all() {
		return array(
			'manage_persons'      => __( 'مدیریت اشخاص (افزودن/ویرایش/غیرفعال‌سازی)', 'sparkknife-ledger' ),
			'manage_transactions' => __( 'ثبت و ویرایش تراکنش‌ها', 'sparkknife-ledger' ),
			'void_transactions'   => __( 'ابطال تراکنش‌ها', 'sparkknife-ledger' ),
			'reports'             => __( 'مشاهده داشبورد و گزارش‌ها', 'sparkknife-ledger' ),
			'export_reports'      => __( 'خروجی گرفتن از گزارش‌ها', 'sparkknife-ledger' ),
			'view_audit'          => __( 'مشاهده تاریخچه تغییرات', 'sparkknife-ledger' ),
			'manage_settings'     => __( 'مدیریت تنظیمات افزونه', 'sparkknife-ledger' ),
		);
	}

	/**
	 * افزودن Capabilityها به نقش مدیر در زمان فعال‌سازی.
	 *
	 * @return void
	 */
	public static function add_capabilities() {
		$role = get_role( 'administrator' );

		if ( ! $role ) {
			return;
		}

		foreach ( array_keys( self::all() ) as $capability ) {
			$role->add_cap( self::PREFIX . $capability );
		}
	}

	/**
	 * حذف Capabilityها از همه نقش‌ها (در زمان غیرفعال‌سازی).
	 *
	 * @return void
	 */
	public static function remove_capabilities() {
		$roles = wp_roles();

		if ( ! $roles ) {
			return;
		}

		foreach ( array_keys( $roles->roles ) as $role_name ) {
			$role = get_role( $role_name );

			if ( ! $role ) {
				continue;
			}

			foreach ( array_keys( self::all() ) as $capability ) {
				$role->remove_cap( self::PREFIX . $capability );
			}
		}
	}

	/**
	 * بررسی دسترسی کاربر جاری.
	 *
	 * @param string $capability نام کوتاه یا کامل Capability.
	 * @return bool
	 */
	public static function current_user_can( $capability ) {
		$capability = self::normalize( $capability );

		if ( ! $capability ) {
			return false;
		}

		$allowed = current_user_can( $capability );

		// مدیر کل سایت (در Multisite) همیشه دسترسی دارد.
		if ( ! $allowed && is_multisite() && is_super_admin() ) {
			$allowed = true;
		}

		/**
		 * فیلتر بررسی دسترسی.
		 *
		 * @param bool   $allowed    نتیجه.
		 * @param string $capability نام کامل Capability.
		 */
		return (bool) apply_filters( 'sparkknife_ledger_user_can', $allowed, $capability );
	}

	/**
	 * بررسی دسترسی و توقف در صورت نداشتن اجازه.
	 *
	 * @param string $capability نام Capability.
	 * @return void
	 */
	public static function require_capability( $capability ) {
		if ( ! self::current_user_can( $capability ) ) {
			wp_die(
				esc_html__( 'شما اجازه دسترسی به این بخش را ندارید.', 'sparkknife-ledger' ),
				esc_html__( 'دسترسی غیرمجاز', 'sparkknife-ledger' ),
				array( 'response' => 403 )
			);
		}
	}

	/**
	 * عادی‌سازی نام Capability.
	 *
	 * @param string $capability نام.
	 * @return string
	 */
	public static function normalize( $capability ) {
		$capability = sanitize_key( (string) $capability );

		if ( '' === $capability ) {
			return '';
		}

		if ( 0 === strpos( $capability, self::PREFIX ) ) {
			return $capability;
		}

		// نام‌های قدیمی/مترادف.
		$aliases = array(
			'view_reports' => 'reports',
			'summary'      => 'reports',
			'dashboard'    => 'reports',
			'settings'     => 'manage_settings',
		);

		if ( isset( $aliases[ $capability ] ) ) {
			$capability = $aliases[ $capability ];
		}

		if ( array_key_exists( $capability, self::all() ) ) {
			// تنظیمات فقط برای مدیر کل با manage_options؛ در غیر این صورت Capability اختصاصی.
			return 'manage_settings' === $capability ? 'manage_options' : self::PREFIX . $capability;
		}

		return $capability;
	}

	/**
	 * نقش‌هایی که می‌توانند Capabilityهای افزونه را دریافت کنند.
	 *
	 * @return array<string, string>
	 */
	public static function assignable_roles() {
		$roles  = wp_roles();
		$result = array();

		if ( ! $roles ) {
			return $result;
		}

		foreach ( $roles->role_objects as $slug => $role ) {
			if ( 'administrator' === $slug ) {
				continue;
			}

			$result[ $slug ] = translate_user_role( $role->name );
		}

		return $result;
	}

	/**
	 * ذخیره تخصیص Capabilityها به نقش‌ها.
	 *
	 * @param array<string, array<int, string>> $matrix نقش => فهرست Capabilityهای کوتاه.
	 * @return void
	 */
	public static function save_role_matrix( array $matrix ) {
		$all_caps = array_keys( self::all() );
		$roles    = wp_roles();

		if ( ! $roles ) {
			return;
		}

		$before = self::get_role_matrix();

		foreach ( array_keys( $roles->roles ) as $role_name ) {
			$role = get_role( $role_name );

			if ( ! $role ) {
				continue;
			}

			foreach ( $all_caps as $capability ) {
				$role->remove_cap( self::PREFIX . $capability );
			}

			// مدیر همیشه تمام دسترسی‌ها را دارد.
			if ( 'administrator' === $role_name ) {
				foreach ( $all_caps as $capability ) {
					$role->add_cap( self::PREFIX . $capability );
				}
				continue;
			}

			if ( empty( $matrix[ $role_name ] ) || ! is_array( $matrix[ $role_name ] ) ) {
				continue;
			}

			foreach ( $matrix[ $role_name ] as $capability ) {
				$capability = sanitize_key( $capability );

				if ( in_array( $capability, $all_caps, true ) ) {
					$role->add_cap( self::PREFIX . $capability );
				}
			}
		}

		Sparkknife_Ledger_Audit_Service::instance()->log(
			'settings',
			0,
			'capabilities_update',
			array(
				'before' => $before,
				'after'  => self::get_role_matrix(),
			),
			__( 'تغییر سطوح دسترسی نقش‌ها', 'sparkknife-ledger' )
		);
	}

	/**
	 * وضعیت فعلی تخصیص Capabilityها.
	 *
	 * @return array<string, array<int, string>>
	 */
	public static function get_role_matrix() {
		$roles  = wp_roles();
		$result = array();

		if ( ! $roles ) {
			return $result;
		}

		foreach ( array_keys( $roles->roles ) as $role_name ) {
			$role = get_role( $role_name );

			if ( ! $role ) {
				continue;
			}

			$result[ $role_name ] = array();

			foreach ( array_keys( self::all() ) as $capability ) {
				if ( $role->has_cap( self::PREFIX . $capability ) ) {
					$result[ $role_name ][] = $capability;
				}
			}
		}

		return $result;
	}
}
