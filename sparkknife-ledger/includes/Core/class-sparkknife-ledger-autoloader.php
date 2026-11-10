<?php
/**
 * Autoloader ساده و بدون وابستگی افزونه.
 *
 * نگاشت بر اساس پسوند نام کلاس انجام می‌شود:
 *   Sparkknife_Ledger_Person_Service      → includes/Services/class-sparkknife-ledger-person-service.php
 *   Sparkknife_Ledger_Person_Repository   → includes/Repositories/class-sparkknife-ledger-person-repository.php
 *   Sparkknife_Ledger_Persons_Controller  → includes/Admin/Controllers/class-sparkknife-ledger-persons-controller.php
 *   Sparkknife_Ledger_Money               → includes/Core/class-sparkknife-ledger-money.php
 *
 * @author  SparKnife <155084046+sparkknife@users.noreply.github.com>
 *
 * @package SparKnife_Ledger
 */

defined( 'ABSPATH' ) || exit;

/**
 * بارگذاری خودکار کلاس‌ها.
 */
class Sparkknife_Ledger_Autoloader {

	/**
	 * کلاس‌های لایه Core که مسیرشان از نام قابل استخراج نیست.
	 *
	 * @var array<int, string>
	 */
	private static $core_classes = array(
		'Sparkknife_Ledger_Amount',
		'Sparkknife_Ledger_DB',
		'Sparkknife_Ledger_Money',
		'Sparkknife_Ledger_Date',
		'Sparkknife_Ledger_Settings',
		'Sparkknife_Ledger_Capabilities',
		'Sparkknife_Ledger_Sanitizer',
		'Sparkknife_Ledger_Validator',
		'Sparkknife_Ledger_Installer',
		'Sparkknife_Ledger_Gate',
		'Sparkknife_Ledger_Updater',
	);

	/**
	 * ثبت autoloader.
	 *
	 * @return void
	 */
	public static function register() {
		spl_autoload_register( array( __CLASS__, 'load' ) );
	}

	/**
	 * بارگذاری کلاس.
	 *
	 * @param string $class_name نام کلاس.
	 * @return void
	 */
	public static function load( $class_name ) {
		if ( 0 !== strpos( $class_name, 'Sparkknife_Ledger' ) ) {
			return;
		}

		$directory = self::directory_for( $class_name );
		$slug      = strtolower( str_replace( '_', '-', $class_name ) );
		$candidates = array(
			SPARKKNIFE_LEDGER_DIR . 'includes/' . $directory . 'class-' . $slug . '.php',
		);

		// مسیرهای جایگزین برای سازگاری با ساختار قدیمی‌تر.
		if ( 'Core/' !== $directory ) {
			$candidates[] = SPARKKNIFE_LEDGER_DIR . 'includes/Core/class-' . $slug . '.php';
		}
		$candidates[] = SPARKKNIFE_LEDGER_DIR . 'includes/class-' . $slug . '.php';

		foreach ( $candidates as $file ) {
			if ( is_readable( $file ) ) {
				require_once $file;
				return;
			}
		}
	}

	/**
	 * تشخیص پوشه کلاس بر اساس پسوند نام.
	 *
	 * @param string $class_name نام کلاس.
	 * @return string مسیر نسبی با اسلش پایانی (یا رشته خالی).
	 */
	private static function directory_for( $class_name ) {
		if ( in_array( $class_name, self::$core_classes, true ) ) {
			return 'Core/';
		}

		if ( '_Controller' === substr( $class_name, -11 ) ) {
			return 'Admin/Controllers/';
		}

		if ( '_Repository' === substr( $class_name, -11 ) ) {
			return 'Repositories/';
		}

		if ( '_Service' === substr( $class_name, -8 ) ) {
			return 'Services/';
		}

		if ( '_Ajax' === substr( $class_name, -5 ) ) {
			return 'Admin/';
		}

		return '';
	}
}
