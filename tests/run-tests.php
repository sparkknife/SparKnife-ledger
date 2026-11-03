<?php
/**
 * مجموعه تست یکپارچه افزونه SparKnife Ledger.
 *
 * این فایل با بوت کامل وردپرس اجرا می‌شود و همه قابلیت‌های اصلی را
 * واقعاً فراخوانی و بررسی می‌کند (نه mock).
 *
 * روش اجرا:
 *   php tests/run-tests.php /path/to/wp-load.php
 *
 * متغیر محیطی SPARKKNIFE_LEDGER_TEST_WP مسیر wp-load.php را تعیین می‌کند.
 *
 * @package SparKnife_Ledger
 */

/**
 * راه‌اندازی محیط.
 */
$wp_load = getenv( 'SPARKKNIFE_LEDGER_TEST_WP' );

if ( ! $wp_load && isset( $argv[1] ) ) {
	$wp_load = $argv[1];
}

if ( ! $wp_load || ! is_readable( $wp_load ) ) {
	fwrite( STDERR, "مسیر wp-load.php مشخص نیست. استفاده: php tests/run-tests.php /path/wp-load.php\n" );
	exit( 1 );
}

if ( ! defined( 'WP_USE_THEMES' ) ) {
	define( 'WP_USE_THEMES', false );
}

require_once $wp_load;

require_once ABSPATH . 'wp-admin/includes/plugin.php';

// اطمینان از فعال بودن افزونه.
if ( ! is_plugin_active( 'sparkknife-ledger/sparkknife-ledger.php' ) ) {
	$activation = activate_plugin( 'sparkknife-ledger/sparkknife-ledger.php' );

	if ( is_wp_error( $activation ) ) {
		fwrite( STDERR, 'فعال‌سازی افزونه ناموفق: ' . $activation->get_error_message() . "\n" );
		exit( 1 );
	}
}

// کاربر مدیر برای اجرای تست‌ها.
$admins = get_users(
	array(
		'role'   => 'administrator',
		'number' => 1,
		'fields' => 'ID',
	)
);

if ( empty( $admins ) ) {
	fwrite( STDERR, "کاربر مدیر یافت نشد.\n" );
	exit( 1 );
}

wp_set_current_user( (int) $admins[0] );

/**
 * کلاس گزارش تست.
 */
class Sparkknife_Ledger_Test_Reporter {

	/** @var int */
	public static $passed = 0;

	/** @var int */
	public static $failed = 0;

	/** @var array<int, string> */
	public static $failures = array();

	/** @var string */
	public static $group = '';

	/**
	 * شروع گروه.
	 *
	 * @param string $name نام.
	 * @return void
	 */
	public static function group( $name ) {
		self::$group = $name;
		echo "\n\033[1;36m▶ " . $name . "\033[0m\n";
	}

	/**
	 * اثبات شرط.
	 *
	 * @param bool   $condition شرط.
	 * @param string $message   پیام.
	 * @param mixed  $actual    مقدار واقعی (برای پیام خطا).
	 * @return bool
	 */
	public static function assert( $condition, $message, $actual = null ) {
		if ( $condition ) {
			self::$passed++;
			echo "  \033[32m✔\033[0m " . $message . "\n";
			return true;
		}

		self::$failed++;
		$detail = '';

		if ( null !== $actual ) {
			$detail = ' → ' . ( is_scalar( $actual ) ? (string) $actual : wp_json_encode( $actual, JSON_UNESCAPED_UNICODE ) );
		}

		self::$failures[] = self::$group . ' :: ' . $message . $detail;
		echo "  \033[31m✘ " . $message . $detail . "\033[0m\n";

		return false;
	}

	/**
	 * برابری.
	 *
	 * @param mixed  $expected انتظار.
	 * @param mixed  $actual   واقعی.
	 * @param string $message  پیام.
	 * @return bool
	 */
	public static function equals( $expected, $actual, $message ) {
		$ok = ( is_array( $expected ) || is_array( $actual ) )
			? $expected == $actual
			: (string) $expected === (string) $actual;

		return self::assert( $ok, $message, is_scalar( $actual ) ? $actual : wp_json_encode( $actual, JSON_UNESCAPED_UNICODE ) );
	}

	/**
	 * خلاصه.
	 *
	 * @return int کد خروج.
	 */
	public static function summary() {
		echo "\n" . str_repeat( '─', 60 ) . "\n";

		if ( 0 === self::$failed ) {
			echo "\033[1;32mهمه تست‌ها موفق: " . self::$passed . " مورد\033[0m\n";
			return 0;
		}

		echo "\033[1;31mناموفق: " . self::$failed . " از " . ( self::$passed + self::$failed ) . "\033[0m\n";

		foreach ( self::$failures as $failure ) {
			echo "  • " . $failure . "\n";
		}

		return 1;
	}
}

/**
 * میان‌بر assert.
 *
 * @param bool   $condition شرط.
 * @param string $message   پیام.
 * @param mixed  $actual    مقدار.
 * @return void
 */
function spk_assert( $condition, $message, $actual = null ) {
	Sparkknife_Ledger_Test_Reporter::assert( $condition, $message, $actual );
}

/**
 * میان‌بر equals.
 *
 * @param mixed  $expected انتظار.
 * @param mixed  $actual   واقعی.
 * @param string $message  پیام.
 * @return void
 */
function spk_equals( $expected, $actual, $message ) {
	Sparkknife_Ledger_Test_Reporter::equals( $expected, $actual, $message );
}

// بارگذاری سناریوها.
require_once __DIR__ . '/scenarios/01-install.php';
require_once __DIR__ . '/scenarios/02-money.php';
require_once __DIR__ . '/scenarios/03-persons.php';
require_once __DIR__ . '/scenarios/04-transactions.php';
require_once __DIR__ . '/scenarios/05-void-audit.php';
require_once __DIR__ . '/scenarios/06-reports-export.php';
require_once __DIR__ . '/scenarios/07-security.php';
require_once __DIR__ . '/scenarios/08-ui-render.php';
require_once __DIR__ . '/scenarios/09-uninstall.php';
require_once __DIR__ . '/scenarios/10-regression.php';

exit( Sparkknife_Ledger_Test_Reporter::summary() );
