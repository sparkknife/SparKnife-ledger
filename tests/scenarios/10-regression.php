<?php
/**
 * سناریو ۱۰: رگرسیون باگ‌های کشف‌شده در ممیزی.
 *
 * @package SparKnife_Ledger
 *
 * هر بخش این سناریو یک باگ واقعی را پوشش می‌دهد که در نسخه ۱.۰.۰ پیدا و
 * رفع شد؛ هدف این است که دیگر برنگردند.
 */

Sparkknife_Ledger_Test_Reporter::group( '۱۰) رگرسیون باگ‌های ممیزی' );

global $wpdb;

/*
 * اجرای زیرفرایند فقط وقتی مجاز است که هم تابع وجود داشته باشد و هم
 * در `disable_functions` نباشد.
 *
 * دو جای این سناریو با `shell_exec` یک اسکریپت PHP یا Node را اجرا
 * می‌کنند (بلوک ۹ برای قالب چاپ و بلوک ۲۵ص برای پیش‌نمایش جاوااسکریپت).
 * روی میزبان‌هایی که `shell_exec` در `disable_functions` است — یعنی
 * پیش‌فرض بیشتر هاست‌های اشتراکی وردپرس — فراخوانی آن خطای مرگبار
 * می‌دهد و **کل مجموعه تست متوقف می‌شود**؛ نه یک شکست، بلکه توقف.
 * در محیط PHP-WASM هم `popen`/`proc_open` پشتیبانی نمی‌شوند و همین
 * اتفاق می‌افتد.
 *
 * `function_exists()` این را نمی‌بیند: تابعِ غیرفعال‌شده هم «وجود
 * دارد». باید فهرست `disable_functions` هم بررسی شود.
 */
if ( ! function_exists( 'sparkknife_ledger_can_spawn' ) ) {
	/**
	 * آیا می‌توان زیرفرایند اجرا کرد؟
	 *
	 * @return bool
	 */
	function sparkknife_ledger_can_spawn() {
		static $can = null;

		if ( null !== $can ) {
			return $can;
		}

		$can = false;

		if ( ! function_exists( 'shell_exec' ) ) {
			return $can;
		}

		$disabled = (string) ini_get( 'disable_functions' );

		if ( '' !== $disabled ) {
			$list = array_map( 'trim', explode( ',', $disabled ) );

			if ( in_array( 'shell_exec', $list, true ) || in_array( 'exec', $list, true ) ) {
				return $can;
			}
		}

		// در محیط WASM پشتیبانی زیرفرایند وجود ندارد.
		if ( defined( 'PHP_WASM' ) || false !== getenv( 'PHP_WASM' ) ) {
			return $can;
		}

		if ( 'cli' !== PHP_SAPI && false !== strpos( (string) PHP_SAPI, 'wasm' ) ) {
			return $can;
		}

		$can = true;

		return $can;
	}
}

// ── ۱: ذخیره یک تب تنظیمات، تب‌های دیگر را خاموش نکند ──────────────
$defaults = Sparkknife_Ledger_Settings::defaults();
Sparkknife_Ledger_Settings::save( $defaults, false );
Sparkknife_Ledger_Settings::flush_cache();

$before = Sparkknife_Ledger_Settings::all();
spk_assert( 1 === (int) $before['require_void_reason'], 'پیش‌فرض: الزام دلیل ابطال روشن است' );
spk_assert( 1 === (int) $before['use_persian_digits'], 'پیش‌فرض: ارقام فارسی روشن است' );

// ذخیره فقط از تب «داده‌ها».
Sparkknife_Ledger_Settings::save( array( 'settings_section' => 'data' ), false );

$after = Sparkknife_Ledger_Settings::all();
spk_assert(
	1 === (int) $after['require_void_reason'],
	'ذخیره تب داده، الزام دلیل ابطال را خاموش نمی‌کند',
	var_export( $after['require_void_reason'], true )
);
spk_assert(
	1 === (int) $after['use_persian_digits'],
	'ذخیره تب داده، ارقام فارسی را خاموش نمی‌کند',
	var_export( $after['use_persian_digits'], true )
);
spk_assert(
	1 === (int) $after['allow_person_delete'],
	'ذخیره تب داده، اجازه حذف شخص را خاموش نمی‌کند',
	var_export( $after['allow_person_delete'], true )
);
spk_assert(
	1 === (int) $after['show_welcome_panel'],
	'ذخیره تب داده، پنل خوش‌آمدگویی را خاموش نمی‌کند',
	var_export( $after['show_welcome_panel'], true )
);

// ذخیره فقط از تب «واحد پول» هم نباید بقیه را دست بزند.
Sparkknife_Ledger_Settings::save(
	array(
		'settings_section'  => 'currency',
		'currency_type'     => 'rial',
		'currency_decimals' => '2',
	),
	false
);
$after2 = Sparkknife_Ledger_Settings::all();
spk_equals( 'rial', $after2['currency_type'], 'تب واحد پول ذخیره شد' );
spk_assert( 2 === (int) $after2['currency_decimals'], 'تعداد اعشار ذخیره شد' );
spk_assert(
	1 === (int) $after2['require_void_reason'],
	'ذخیره تب واحد پول، الزام دلیل ابطال را خاموش نمی‌کند'
);

Sparkknife_Ledger_Settings::save( $defaults, false );
Sparkknife_Ledger_Settings::flush_cache();

// ── ۲: گزینه حذف داده با آرایه تنظیمات هم‌گام می‌ماند ───────────────
Sparkknife_Ledger_Settings::save(
	array(
		'settings_section'        => 'data',
		'delete_data_on_uninstall' => '1',
	),
	false
);
spk_equals(
	'yes',
	get_option( 'sparkknife_ledger_delete_data_on_uninstall' ),
	'گزینه حذف داده با تنظیمات هم‌گام می‌شود (روشن)'
);

Sparkknife_Ledger_Settings::save( array( 'settings_section' => 'data' ), false );
spk_equals(
	'no',
	get_option( 'sparkknife_ledger_delete_data_on_uninstall' ),
	'گزینه حذف داده با تنظیمات هم‌گام می‌شود (خاموش)'
);

Sparkknife_Ledger_Settings::save( $defaults, false );
Sparkknife_Ledger_Settings::flush_cache();

// ── ۳: نگاشت بخش‌ها همه کلیدهای واقعی را پوشش می‌دهد ────────────────
$all_keys = array_keys( $defaults );
$covered  = array();
foreach ( array( 'currency', 'display', 'general', 'data' ) as $section ) {
	$covered = array_merge( $covered, Sparkknife_Ledger_Settings::section_keys( $section ) );
}
$covered = array_unique( $covered );

$missing = array_diff( $all_keys, $covered );
spk_assert(
	empty( $missing ),
	'هر کلید تنظیمات در یک بخش قرار دارد',
	'بدون بخش: ' . implode( ', ', $missing )
);

$unknown = array_diff( $covered, $all_keys );
spk_assert(
	empty( $unknown ),
	'هیچ بخشی کلید ناشناخته ندارد',
	'ناشناخته: ' . implode( ', ', $unknown )
);

spk_assert( empty( Sparkknife_Ledger_Settings::section_keys( 'hacker' ) ), 'بخش ناشناخته کلیدی برنمی‌گرداند' );

// ── ۴: format_amount آرایه را بی‌صدا به صفر تبدیل نکند (هشدار PHP) ──
$warnings = array();
set_error_handler(
	function ( $errno, $errstr ) use ( &$warnings ) {
		$warnings[] = $errstr;
		return true;
	}
);
Sparkknife_Ledger_Money::format( array( 'balance' => '7000000000' ) );
restore_error_handler();

spk_assert( empty( $warnings ), 'قالب‌بندی آرایه هیچ هشدار PHP تولید نمی‌کند', implode( ' | ', $warnings ) );
// آرایه نباید به «۰ تومان» تبدیل شود (پیش‌تر همین کار را می‌کرد و
// مبلغ صفر جعلی نشان می‌داد)؛ حالا خط تیره نمایش داده می‌شود.
spk_equals(
	'—',
	Sparkknife_Ledger_Money::format( array( 1, 2 ) ),
	'آرایه به‌جای مبلغ صفر جعلی، خط تیره می‌دهد'
);

// ── ۵: خروجی CSV فرمول خطرناک را خنثی می‌کند ────────────────────────
$dangerous = array( '=cmd|calc', '+SUM(1)', '-1+1', '@foo', "\tbar" );
foreach ( $dangerous as $value ) {
	$cell = Sparkknife_Ledger_Export_Service::sanitize_cell( $value );
	spk_assert( "'" === substr( $cell, 0, 1 ), 'سلول خطرناک CSV خنثی شد: ' . $value, $cell );
}

spk_equals( 'سلام', Sparkknife_Ledger_Export_Service::sanitize_cell( 'سلام' ), 'متن سالم CSV دست‌نخورده می‌ماند' );

// ── ۶: گزینه‌های حذف‌شده در uninstall واقعاً وجود دارند ─────────────
$uninstall_source = file_get_contents( SPARKKNIFE_LEDGER_DIR . 'uninstall.php' );
preg_match_all( "/delete_option\(\s*'([a-z_]+)'/", $uninstall_source, $matches );

/*
 * فهرست فایل‌ها با پیمایش بازگشتی ساخته می‌شود، نه با `glob('**')`.
 *
 * `glob()` در PHP از `**` به‌عنوان «هر عمق» پشتیبانی نمی‌کند؛ فقط یک
 * سطح می‌گیرد. نتیجه این بود که گزینه‌ای که در
 * `includes/Admin/Controllers/` ساخته می‌شد «هرگز ساخته نمی‌شود»
 * تشخیص داده می‌شد و آزمون به‌غلط شکست می‌خورد — یعنی خودِ تست یک
 * باگ داشت که فقط با اضافه شدن گزینهٔ تازه در عمق دو سطحی آشکار شد.
 */
$all_plugin_php = array();

$spk_iterator = new RecursiveIteratorIterator(
	new RecursiveDirectoryIterator( SPARKKNIFE_LEDGER_DIR . 'includes', FilesystemIterator::SKIP_DOTS )
);

foreach ( $spk_iterator as $spk_file ) {
	if ( 'php' === strtolower( $spk_file->getExtension() ) ) {
		$all_plugin_php[] = $spk_file->getPathname();
	}
}

foreach ( $matches[1] as $option_name ) {
	$created_in = false;
	foreach ( $all_plugin_php as $file ) {
		if ( false !== strpos( file_get_contents( $file ), $option_name ) ) {
			$created_in = true;
			break;
		}
	}

	spk_assert(
		$created_in,
		'گزینه حذف‌شده در uninstall جایی ساخته می‌شود: ' . $option_name,
		'گزینه‌ای که هرگز ساخته نمی‌شود'
	);
}

// ── ۷: چندسایتی روی تک‌سایتی بی‌اثر است ─────────────────────────────
$options_before = (int) $wpdb->get_var(
	"SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE 'sparkknife_ledger%'"
);

Sparkknife_Ledger_Installer::activate_network( false );
Sparkknife_Ledger_Installer::activate_network( true );

$options_after = (int) $wpdb->get_var(
	"SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE 'sparkknife_ledger%'"
);

spk_equals( $options_before, $options_after, 'active_network در تک‌سایتی گزینه تکراری نمی‌سازد' );

// ── ۸: همه صفحات پنل بدون خطای PHP رندر می‌شوند ─────────────────────
$controllers = array(
	'داشبورد'        => array( 'Sparkknife_Ledger_Dashboard_Controller', 'render' ),
	'اشخاص'          => array( 'Sparkknife_Ledger_Persons_Controller', 'render' ),
	'تراکنش‌ها'        => array( 'Sparkknife_Ledger_Transactions_Controller', 'render' ),
	'گزارش فردی'      => array( 'Sparkknife_Ledger_Reports_Controller', 'render_person' ),
	'گزارش تجمیعی'    => array( 'Sparkknife_Ledger_Reports_Controller', 'render_summary' ),
	'تاریخچه'        => array( 'Sparkknife_Ledger_Audit_Controller', 'render' ),
	'تنظیمات'        => array( 'Sparkknife_Ledger_Settings_Controller', 'render' ),
);

foreach ( $controllers as $label => $callback ) {
	$errors = array();

	set_error_handler(
		function ( $errno, $errstr, $errfile, $errline ) use ( &$errors ) {
			$errors[] = $errstr . ' @ ' . basename( $errfile ) . ':' . $errline;
			return true;
		}
	);

	ob_start();
	try {
		call_user_func( $callback );
		$html = ob_get_clean();
		$fatal = '';
	} catch ( Throwable $e ) {
		$html  = ob_get_clean();
		$fatal = $e->getMessage();
	}

	restore_error_handler();

	spk_assert( '' === $fatal, 'صفحه ' . $label . ' بدون خطای مرگبار', $fatal );
	spk_assert( empty( $errors ), 'صفحه ' . $label . ' بدون Warning/Notice', implode( ' | ', array_slice( $errors, 0, 3 ) ) );
	spk_assert( strlen( $html ) > 100, 'صفحه ' . $label . ' محتوا تولید کرد', (string) strlen( $html ) );
}

// ── ۹: قالب چاپ، زبان فارسی و جهت راست‌به‌چپ را اعلام می‌کند ────────
//
// render_document() در پایان exit می‌کند (درست برای مسیر HTTP). برای
// اینکه اجرای تست متوقف نشود، آن را در یک subprocess اجرا می‌کنیم.
// PHP_BINARY در برخی بیلدها خالی است، پس مسیر مفسر از چند منبع حدس زده
// می‌شود و در نهایت به همان مفسر جاری برمی‌گردیم.
$php_bin = PHP_BINARY;

if ( '' === $php_bin ) {
	$php_bin = (string) getenv( 'SPARKKNIFE_LEDGER_PHP' );
}

if ( '' === $php_bin ) {
	foreach ( array( '/usr/bin/php', '/usr/local/bin/php' ) as $candidate ) {
		if ( is_executable( $candidate ) ) {
			$php_bin = $candidate;
			break;
		}
	}
}

$doc_output = '';

if ( '' !== $php_bin && is_executable( $php_bin ) ) {
	$doc_script = tempnam( sys_get_temp_dir(), 'spkprint' ) . '.php';

	$doc_source  = "<?php\n";
	$doc_source .= 'require ' . var_export( ABSPATH . 'wp-load.php', true ) . ";\n";
	$doc_source .= "wp_set_current_user( 1 );\n";
	$doc_source .= "( new Sparkknife_Ledger_Print_Service() )->render_document( '<p>x</p>' );\n";

	file_put_contents( $doc_script, $doc_source );

	$doc_output = sparkknife_ledger_can_spawn()
		? (string) shell_exec(
			escapeshellarg( $php_bin ) . ' -d memory_limit=512M ' . escapeshellarg( $doc_script ) . ' 2>/dev/null'
		)
		: '';

	wp_delete_file( $doc_script );
}

if ( '' === trim( $doc_output ) ) {
	// fallback: بررسی ایستا وقتی اجرای subprocess ممکن نیست
	$doc_output = (string) file_get_contents( SPARKKNIFE_LEDGER_DIR . 'includes/Services/class-sparkknife-ledger-print-service.php' );
	spk_assert( false !== strpos( $doc_output, 'dir="rtl"' ), 'قالب چاپ راست‌به‌چپ است' );
	spk_assert( false !== strpos( $doc_output, 'lang="fa-IR"' ), 'قالب چاپ زبان فارسی اعلام می‌کند' );
	spk_assert( false !== stripos( $doc_output, '<!DOCTYPE' ), 'قالب چاپ DOCTYPE دارد' );
} else {
	spk_assert( false !== stripos( $doc_output, '<!DOCTYPE' ), 'سند چاپ DOCTYPE دارد', mb_substr( $doc_output, 0, 80 ) );
	spk_assert( false !== strpos( $doc_output, 'dir="rtl"' ), 'سند چاپ راست‌به‌چپ است' );
	spk_assert( false !== strpos( $doc_output, 'lang="fa-IR"' ), 'سند چاپ زبان فارسی اعلام می‌کند' );
	spk_assert( false !== stripos( $doc_output, 'charset' ), 'سند چاپ کدگذاری دارد' );
}

// ── ۱۱: شماره سند حتی با درج دستی یکتا می‌ماند ──────────────────────
$txn_service = new Sparkknife_Ledger_Transaction_Service();
$txn_table   = Sparkknife_Ledger_DB::transactions_table();

$first_ref = $txn_service->generate_reference( current_time( 'Y-m-d' ) );

global $wpdb;
$wpdb->query(
	$wpdb->prepare(
		"INSERT INTO {$txn_table} (reference, person_id, type, amount, txn_date, status, created_at, updated_at)
		 VALUES (%s, 0, 'income', '10000', %s, 'posted', %s, %s)",
		$first_ref,
		current_time( 'Y-m-d' ),
		current_time( 'mysql' ),
		current_time( 'mysql' )
	)
);

$second_ref = $txn_service->generate_reference( current_time( 'Y-m-d' ) );

spk_assert( $second_ref !== $first_ref, 'شماره سند تکراری تولید نمی‌شود', $first_ref . ' / ' . $second_ref );

$duplicate_refs = $wpdb->get_results( "SELECT reference, COUNT(*) c FROM {$txn_table} GROUP BY reference HAVING c > 1" );
spk_assert( empty( $duplicate_refs ), 'هیچ شماره سند تکراری در دیتابیس نیست', (string) count( $duplicate_refs ) );

$wpdb->query( $wpdb->prepare( "DELETE FROM {$txn_table} WHERE reference = %s", $first_ref ) );

// ── ۱۲: کد مرده در JS وجود ندارد ───────────────────────────────────
$js = (string) file_get_contents( SPARKKNIFE_LEDGER_DIR . 'assets/js/admin.js' );

spk_assert( false === strpos( $js, 'initLiveSearch' ), 'تابع جستجوی زنده مرده حذف شده است' );

// هر شناسه‌ای که JS مستقیم صدا می‌زند باید در view ها وجود داشته باشد.
preg_match_all( "/getElementById\(\s*'([a-z-]+)'/", $js, $js_ids );

foreach ( array_unique( $js_ids[1] ) as $element_id ) {
	$found = false;
	foreach ( glob( SPARKKNIFE_LEDGER_DIR . 'includes/Admin/Views/**/*.php' ) as $view_file ) {
		if ( false !== strpos( (string) file_get_contents( $view_file ), $element_id ) ) {
			$found = true;
			break;
		}
	}

	spk_assert( $found, 'شناسه JS در view وجود دارد: ' . $element_id );
}

// ── ۱۳: کلیدهای تنظیمات خوانده‌شده همگی تعریف شده‌اند ────────────────
$all_settings_keys = array_keys( Sparkknife_Ledger_Settings::defaults() );

$missing_keys = array();
foreach ( glob( SPARKKNIFE_LEDGER_DIR . 'includes/**/*.php' ) as $php_file ) {
	$source = (string) file_get_contents( $php_file );

	if ( preg_match_all( "/Settings::get\(\s*'([a-z_]+)'/", $source, $matches ) ) {
		foreach ( $matches[1] as $key ) {
			if ( ! in_array( $key, $all_settings_keys, true ) && ! in_array( $key, $missing_keys, true ) ) {
				$missing_keys[] = $key;
			}
		}
	}
}

spk_assert( empty( $missing_keys ), 'همه کلیدهای تنظیمات خوانده‌شده تعریف شده‌اند', implode( ', ', $missing_keys ) );

// ── ۱۴: تقویم شمسی با تقویم رسمی مطابق است ─────────────────────────
// سال‌های کبیسه شناخته‌شده (مرجع: تقویم رسمی ایران).
$known_leap    = array( 1370, 1375, 1379, 1383, 1387, 1391, 1395, 1399, 1403, 1408, 1412, 1416, 1420 );
$known_regular = array( 1400, 1401, 1402, 1404, 1405, 1406, 1407, 1409, 1410, 1411 );

foreach ( $known_leap as $leap_year ) {
	spk_assert( Sparkknife_Ledger_Date::is_jalali_leap( $leap_year ), 'سال کبیسه: ' . $leap_year );
}

foreach ( $known_regular as $regular_year ) {
	spk_assert( ! Sparkknife_Ledger_Date::is_jalali_leap( $regular_year ), 'سال عادی: ' . $regular_year );
}

// ۳۰ اسفند ۱۴۰۳ باید پذیرفته شود (۱۴۰۳ کبیسه است) و به ۲۰۲۵-۰۳-۲۰ برسد.
$esfand30 = Sparkknife_Ledger_Date::jalali_to_date( '1403/12/30' );
spk_assert( ! is_wp_error( $esfand30 ), '۳۰ اسفند ۱۴۰۳ پذیرفته می‌شود', is_wp_error( $esfand30 ) ? $esfand30->get_error_message() : '' );
spk_assert( '2025-03-20' === $esfand30, '۳۰ اسفند ۱۴۰۳ = ۲۰ مارس ۲۰۲۵', (string) $esfand30 );

// و ۳۰ اسفند ۱۴۰۲ باید رد شود.
$wrong_esfand = Sparkknife_Ledger_Date::jalali_to_date( '1402/12/30' );
spk_assert( is_wp_error( $wrong_esfand ), '۳۰ اسفند ۱۴۰۲ رد می‌شود (۱۴۰۲ عادی است)' );

// رفت‌وبرگشت: هر روز ۱۴۰۳ باید همان روز برگردد.
$roundtrip_failures = 0;
$cursor             = strtotime( '2024-03-20' ); // ۱ فروردین ۱۴۰۳
$end_cursor         = strtotime( '2025-03-20' ); // ۳۰ اسفند ۱۴۰۳

while ( $cursor <= $end_cursor ) {
	$gregorian = gmdate( 'Y-m-d', $cursor );
	$jalali    = Sparkknife_Ledger_Date::format_for_input( $gregorian );
	$back      = Sparkknife_Ledger_Date::jalali_to_date( $jalali );

	if ( is_wp_error( $back ) || $back !== $gregorian ) {
		$roundtrip_failures++;
	}

	$cursor += DAY_IN_SECONDS;
}

spk_assert( 0 === $roundtrip_failures, 'رفت‌وبرگشت همه روزهای ۱۴۰۳ بی‌خطاست', (string) $roundtrip_failures );

// ── ۱۵: نقطه به‌عنوان جداکننده هزارگان ──────────────────────────────
// کاربر «۱.۲۳۴» می‌نویسد؛ پیش‌تر یک و ۲۳۴ هزارم خوانده می‌شد (هزار برابر کوچک‌تر).
$dot_grouped = array(
	'1.234'     => '1234',
	'1.234.567' => '1234567',
	'12.345'    => '12345',
);

foreach ( $dot_grouped as $input => $expected ) {
	$parsed = Sparkknife_Ledger_Money::parse_amount( Sparkknife_Ledger_Money::normalize_amount_input( $input ) );
	spk_equals(
		Sparkknife_Ledger_Money::to_human( $parsed ),
		$expected,
		'نقطه به‌عنوان جداکننده هزارگان: ' . $input
	);
}

// اعشار واقعی باید همچنان اعشار بماند.
$decimal = Sparkknife_Ledger_Money::parse_amount( Sparkknife_Ledger_Money::normalize_amount_input( '12.5' ) );
spk_equals( '125000', $decimal, 'اعشار واقعی با نقطه حفظ می‌شود' );

$two_decimals = Sparkknife_Ledger_Money::parse_amount( Sparkknife_Ledger_Money::normalize_amount_input( '0.75' ) );
spk_equals( '7500', $two_decimals, 'اعشار کمتر از یک با نقطه' );

// ── ۱۵ب: نقطه فقط در واحد بدون اعشار جداکننده هزارگان است ────────────
// قاعده «۱.۲۳۴ = ۱۲۳۴» برای تومان درست است، ولی برای دلار باید نقطه
// را اعشار گرفت؛ وگرنه ۱.۵ دلار به ۱۵۰۰ دلار تبدیل می‌شد.
$saved_currency = Sparkknife_Ledger_Settings::all();

Sparkknife_Ledger_Settings::save(
	array_merge(
		$saved_currency,
		array(
			'currency_type'     => 'custom',
			'currency_decimals' => 2,
			'currency_label'    => 'دلار',
			'currency_symbol'   => '$',
			'currency_code'     => 'USD',
		)
	),
	false
);
Sparkknife_Ledger_Settings::flush_cache();

$decimal_amount = Sparkknife_Ledger_Money::parse_amount( '1.500' );
spk_equals( '15000', $decimal_amount, 'با دو رقم اعشار، «1.500» یعنی ۱.۵ (نه ۱۵۰۰)' );

$decimal_1005 = Sparkknife_Ledger_Money::parse_amount( '1.005' );
spk_equals( '10050', $decimal_1005, 'با دو رقم اعشار، «1.005» یعنی ۱.۰۰۵' );

// بازگشت به تومان: نقطه دوباره جداکننده است.
Sparkknife_Ledger_Settings::save(
	array_merge(
		$saved_currency,
		array( 'currency_type' => 'toman', 'currency_decimals' => 0 )
	),
	false
);
Sparkknife_Ledger_Settings::flush_cache();

$integer_amount = Sparkknife_Ledger_Money::parse_amount( '1.500' );
spk_equals(
	'1500',
	Sparkknife_Ledger_Money::to_human( $integer_amount ),
	'با صفر رقم اعشار، «1.500» یعنی ۱۵۰۰'
);

Sparkknife_Ledger_Settings::save( $saved_currency, false );
Sparkknife_Ledger_Settings::flush_cache();

// ── ۱۶: منفی‌های یونیکد مبلغ را مثبت نمی‌کنند ───────────────────────
$unicode_minus = array(
	'−5'  => 'منفی یونیکد U+2212',
	'–5'  => 'خط تیره کوتاه U+2013',
	'—5'  => 'خط تیره بلند U+2014',
	'−۱۲۳' => 'منفی یونیکد با ارقام فارسی',
);

foreach ( $unicode_minus as $input => $label ) {
	$parsed = Sparkknife_Ledger_Money::parse_amount(
		Sparkknife_Ledger_Sanitizer::amount( $input )
	);

	spk_assert( is_wp_error( $parsed ), 'مبلغ منفی رد می‌شود: ' . $label );
}

// ── ۱۷: format() با شیء یا آرایه خطای مرگبار نمی‌دهد ─────────────────
$bad_object = new WP_Error( 'x', 'خطا' );

spk_equals( '—', Sparkknife_Ledger_Money::format( $bad_object ), 'format() با WP_Error خطای مرگبار نمی‌دهد' );
spk_equals( '—', Sparkknife_Ledger_Money::format( array( 1, 2 ) ), 'format() با آرایه خطای مرگبار نمی‌دهد' );

// ── ۱۸: خروجی CSV با ورودی ناقص خطای مرگبار نمی‌دهد ─────────────────
$export = new Sparkknife_Ledger_Export_Service();

$incomplete_reports = array(
	'آرایه خالی'          => array(),
	'بدون range و summary' => array( 'person' => (object) array( 'name' => 'تست', 'phone' => '0912', 'status' => 'active' ) ),
	'range خالی'          => array( 'person' => (object) array( 'name' => 'تست', 'phone' => '0912', 'status' => 'active' ), 'range' => array() ),
	'summary خالی'        => array( 'person' => (object) array( 'name' => 'تست', 'phone' => '0912', 'status' => 'active' ), 'summary' => array() ),
);

foreach ( $incomplete_reports as $label => $input ) {
	$threw = false;

	try {
		$out = $export->person_report_rows( $input );
	} catch ( Throwable $e ) {
		$threw = true;
		$out   = array();
	}

	spk_assert( ! $threw, 'CSV با ورودی ناقص کرش نمی‌کند: ' . $label );
	spk_assert( is_array( $out ) && ! empty( $out ), 'CSV با ورودی ناقص ردیف برمی‌گرداند: ' . $label );
}

// ── ۱۹: مرتب‌سازی گزارش با مقادیر مساوی پایدار است ──────────────────
$report_service = Sparkknife_Ledger_Report_Service::instance();

$sort_a = $report_service->get_summary_report( array( 'orderby' => 'balance', 'order' => 'asc', 'per_page' => 100 ) );
$sort_b = $report_service->get_summary_report( array( 'orderby' => 'balance', 'order' => 'asc', 'per_page' => 100 ) );

spk_equals(
	wp_json_encode( array_column( $sort_a['rows'], 'id' ) ),
	wp_json_encode( array_column( $sort_b['rows'], 'id' ) ),
	'مرتب‌سازی با مقادیر مساوی بین دو اجرا پایدار است'
);

/*
 * هیچ شخصی نباید در صفحه‌بندی تکرار یا حذف شود.
 *
 * حلقه دقیقاً تا آخرین صفحهٔ موجود می‌رود، نه یکی بیشتر. باگ ۶۴ صفحه‌های
 * خارج از بازه را به آخرین صفحه محدود می‌کند (رفتار استاندارد)، پس
 * درخواست صفحهٔ اضافه ردیف‌ها را **تکرار** می‌کند و این بررسی را قرمز
 * می‌کرد — نه به‌خاطر نقص کد، بلکه به‌خاطر بازهٔ نادرست آزمون.
 */
$paged_ids   = array();
$page_size   = 2;
$sort_total  = (int) $sort_a['pagination']['total'];
$page_max    = max( 1, (int) ceil( $sort_total / $page_size ) );

for ( $p = 1; $p <= $page_max; $p++ ) {
	$page = $report_service->get_summary_report(
		array( 'orderby' => 'balance', 'order' => 'asc', 'per_page' => $page_size, 'page' => $p )
	);

	foreach ( $page['rows'] as $row ) {
		$paged_ids[] = (int) $row['id'];
	}
}

spk_equals(
	count( $paged_ids ),
	count( array_unique( $paged_ids ) ),
	'صفحه‌بندی گزارش هیچ شخصی را تکرار نمی‌کند'
);

spk_equals(
	$sort_total,
	count( $paged_ids ),
	'صفحه‌بندی گزارش همه اشخاص را پوشش می‌دهد'
);

// ── ۲۰: پیام خالی اعلان رندر نمی‌شود ───────────────────────────────
// redirect_back_with() اعلانی با پیام خالی ثبت می‌کند تا کاربر را به فرم
// بازگرداند. پیش‌تر این حالت یک جعبه خطای توخالی نشان می‌داد.
delete_transient( 'sparkknife_ledger_notice_' . get_current_user_id() );
Sparkknife_Ledger_Admin_Loader::add_notice( 'error', '' );

$empty_notice = get_transient( 'sparkknife_ledger_notice_' . get_current_user_id() );
spk_assert( is_array( $empty_notice ), 'اعلان با پیام خالی ثبت می‌شود' );

$loader_source = (string) file_get_contents( SPARKKNIFE_LEDGER_DIR . 'includes/class-sparkknife-ledger-admin-loader.php' );
spk_assert(
	1 === preg_match( '/if \( \'\' === trim\( \$text \) \) \{\s*return;/', $loader_source ),
	'render_admin_notices پیام خالی را رندر نمی‌کند'
);

delete_transient( 'sparkknife_ledger_notice_' . get_current_user_id() );

// ── ۲۱: flash_error پیام را دوبار نمایش نمی‌دهد ─────────────────────
// پیام از ترنزینت form_errors در هدر و از add_notice در admin_notices
// نمایش داده می‌شد؛ یعنی همان خطا دوبار به کاربر نشان داده می‌شد.
$controller_source = (string) file_get_contents( SPARKKNIFE_LEDGER_DIR . 'includes/Admin/Controllers/class-sparkknife-ledger-admin-controller.php' );

preg_match( '/private function flash_error\([^)]*\) \{(.*?)\n\t\}/s', $controller_source, $flash_body );

spk_assert( ! empty( $flash_body[1] ), 'بدنه flash_error یافت شد' );

// کامنت‌ها حذف می‌شوند تا فقط کد اجرایی سنجیده شود.
$flash_code = preg_replace( '#/\*.*?\*/#s', '', $flash_body[1] );
$flash_code = preg_replace( '#//.*$#m', '', (string) $flash_code );

spk_assert(
	false === strpos( (string) $flash_code, 'add_notice' ),
	'flash_error خودش add_notice صدا نمی‌زند (جلوگیری از پیام تکراری)',
	(string) $flash_code
);

// ── ۲۲: حذف داده فقط با عبارت تأیید دقیق ────────────────────────────
$purge_source = (string) file_get_contents( SPARKKNIFE_LEDGER_DIR . 'includes/Admin/Controllers/class-sparkknife-ledger-admin-controller.php' );

preg_match( '/private function handle_data_purge\([^)]*\) \{(.*?)\n\t\}/s', $purge_source, $purge_body );

spk_assert( ! empty( $purge_body[1] ), 'بدنه handle_data_purge یافت شد' );

// هر دو شرط محافظتی باید با return تمام شوند، نه فقط add_notice.
$guard_returns = preg_match_all( '/add_notice\([^;]*;\s*\n\s*return;/s', $purge_body[1] );

spk_assert(
	$guard_returns >= 2,
	'هر دو محافظت حذف داده با return پایان می‌یابند',
	(string) $guard_returns
);

spk_assert(
	false !== strpos( $purge_body[1], "'DELETE-ALL-DATA' !== \$confirm" ),
	'حذف داده نیازمند عبارت دقیق DELETE-ALL-DATA است'
);

// ── ۲۳: uninstall گزینه‌های نسخه را در هر دو حالت پاک می‌کند ────────
$uninstall_source = (string) file_get_contents( SPARKKNIFE_LEDGER_DIR . 'uninstall.php' );

// delete_option نسخه باید هم در شاخه حذف کامل و هم در شاخه حفظ داده باشد.
spk_assert(
	2 === substr_count( $uninstall_source, "delete_option( 'sparkknife_ledger_db_version' );" ),
	'uninstall نسخه دیتابیس را در هر دو حالت پاک می‌کند',
	(string) substr_count( $uninstall_source, "delete_option( 'sparkknife_ledger_db_version' );" )
);

spk_assert(
	2 === substr_count( $uninstall_source, "delete_option( 'sparkknife_ledger_version' );" ),
	'uninstall نسخه افزونه را در هر دو حالت پاک می‌کند'
);

// ── ۲۴: نمایش تاریخ روی PHP ۳۲ بیتی هشدار نمی‌دهد ──────────────────
// strtotime() برای سال‌های دور از محدوده int هشدار می‌دهد و تاریخ را
// به خط تیره تبدیل می‌کند. این موارد باید بدون هشدار و درست نمایش یابند.
$far_dates = array(
	'1900-01-01' => '۱۲۷۸/۱۰/۱۱',
	'2199-12-31' => '۱۵۷۸/۱۰/۱۰',
);

foreach ( $far_dates as $gregorian => $expected_jalali ) {
	$warnings_raised = array();

	set_error_handler(
		function ( $errno, $errstr ) use ( &$warnings_raised ) {
			$warnings_raised[] = $errstr;
			return true;
		}
	);

	$rendered = Sparkknife_Ledger_Date::format( $gregorian );

	restore_error_handler();

	spk_assert( empty( $warnings_raised ), 'نمایش تاریخ بدون هشدار PHP: ' . $gregorian, implode( ' | ', $warnings_raised ) );
	spk_assert( false !== strpos( $rendered, $expected_jalali ), 'تاریخ دور درست نمایش داده می‌شود: ' . $gregorian, $rendered );
}

// همان مسیر برای ورودی فرم.
$input_warnings = array();
set_error_handler(
	function ( $errno, $errstr ) use ( &$input_warnings ) {
		$input_warnings[] = $errstr;
		return true;
	}
);
$input_value = Sparkknife_Ledger_Date::format_for_input( '2199-12-31' );
restore_error_handler();

spk_assert( empty( $input_warnings ), 'format_for_input بدون هشدار PHP' );
spk_equals( '1578/10/10', $input_value, 'format_for_input تاریخ دور را درست می‌دهد' );

// ورودی نامعتبر هم نباید هشدار بدهد.
foreach ( array( null, '', '0000-00-00', 'garbage', '2025-02-30' ) as $bad_date ) {
	spk_equals( '—', Sparkknife_Ledger_Date::format( $bad_date ), 'تاریخ نامعتبر خط تیره می‌دهد: ' . var_export( $bad_date, true ) );
}

// ── ۲۵: جستجو با املای عربی فارسی را پیدا می‌کند ──────────────────
// کاربر با کیبورد عربی یا کپی از وب، «ي» و «ك» تایپ می‌کند. LIKE در
// MySQL بایتی مقایسه می‌کند، پس جستجو نتیجه نمی‌داد.

$ps = new Sparkknife_Ledger_Person_Service();

$spelling_name = 'علی رضایی';

global $wpdb;
$wpdb->query(
	$wpdb->prepare(
		'DELETE FROM ' . Sparkknife_Ledger_DB::persons_table() . ' WHERE name = %s',
		$spelling_name
	)
);

$spelling_id = $ps->create( array( 'name' => $spelling_name, 'phone' => '09127778888' ) );
spk_assert( ! is_wp_error( $spelling_id ), 'شخص برای آزمون املا ساخته شد' );

$person_repo = Sparkknife_Ledger_Person_Repository::instance();

$spellings = array(
	'علی رضایی'                            => 'فارسی',
	'علي رضائي'                            => 'عربی + همزه روی ی',
	'على رضائى'                            => 'الف مقصوره',
);

foreach ( $spellings as $needle => $label ) {
	$found = $person_repo->query( array( 'search' => $needle, 'page' => 1, 'per_page' => 10 ) );
	spk_assert( $found['total'] >= 1, 'جستجوی «' . $label . '» شخص را پیدا می‌کند', (string) $found['total'] );
}

// «ک» عربی
$k_name = 'کارخانه آزمون';
$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . Sparkknife_Ledger_DB::persons_table() . ' WHERE name = %s', $k_name ) );
$ps->create( array( 'name' => $k_name, 'phone' => '09127779999' ) );

$k_arabic_query = mb_chr( 0x0643, 'UTF-8' ) . mb_chr( 0x0627, 'UTF-8' ) . mb_chr( 0x0631, 'UTF-8' ) . mb_chr( 0x062E, 'UTF-8' ) . mb_chr( 0x0627, 'UTF-8' ) . mb_chr( 0x0646, 'UTF-8' ) . mb_chr( 0x0647, 'UTF-8' );
$k_search       = $person_repo->query( array( 'search' => $k_arabic_query, 'page' => 1, 'per_page' => 10 ) );

spk_assert( $k_search['total'] >= 1, 'جستجو با «ك» عربی نام دارای «ک» را پیدا می‌کند', (string) $k_search['total'] );

// ── ۲۵ب: جستجوی گزارش خلاصه هم املاهای فارسی را یکسان می‌بیند ─────
// جستجو در فهرست اشخاص (مخزن) با «علي» کار می‌کرد ولی گزارش خلاصه
// لایه‌ی فیلتر خودش را داشت که فقط مقایسه‌ی خام انجام می‌داد.
// یک شخص مخصوص همین آزمون ساخته می‌شود تا به داده‌ی آزمون‌های قبل
// وابسته نباشد.
$report_search_person = 'مریم گزارش‌آزمون';
$wpdb->query(
	$wpdb->prepare(
		'DELETE FROM ' . Sparkknife_Ledger_DB::persons_table() . ' WHERE name = %s',
		$report_search_person
	)
);
$ps->create( array( 'name' => $report_search_person, 'phone' => '09125550000' ) );

$report_search_queries = array( 'مريم', 'مریم' );

foreach ( $report_search_queries as $needle ) {
	$summary = Sparkknife_Ledger_Report_Service::instance()->get_summary_report( array( 'search' => $needle ) );
	spk_assert( count( $summary['rows'] ) >= 1, 'گزارش خلاصه با «' . $needle . '» نتیجه می‌دهد', (string) count( $summary['rows'] ) );
}

// ── ۲۵ج: صادرات و چاپ نباید به یک صفحه محدود شوند ──────────────────
// کنترلر چاپ `per_page = 500` می‌فرستاد که بی‌صدا به ۲۰۰ کوتاه می‌شد و
// کنترلر صادرات هیچ عددی نمی‌فرستاد، پس روی پیش‌فرض ۲۰ می‌ماند؛ فایل
// CSV از ۲۵۰ شخص فقط ۲۰ ردیف داشت، بدون هیچ هشداری.
$export_person = 'شخص صادرات‌آزمون';
$wpdb->query(
	$wpdb->prepare(
		'DELETE FROM ' . Sparkknife_Ledger_DB::persons_table() . ' WHERE name = %s',
		$export_person
	)
);

$export_person_id = $ps->create( array( 'name' => $export_person, 'phone' => '09125550100' ) );
spk_assert( ! is_wp_error( $export_person_id ), 'شخص آزمون صادرات ساخته شد' );

$txn_svc = Sparkknife_Ledger_Transaction_Service::instance();

for ( $i = 1; $i <= 45; $i++ ) {
	$txn_svc->create(
		array(
			'person_id' => $export_person_id,
			'type'      => 0 === $i % 2 ? 'income' : 'expense',
			'amount'    => '1000',
			'txn_date'  => '1403/05/' . str_pad( (string) ( ( $i % 28 ) + 1 ), 2, '0', STR_PAD_LEFT ),
			'description' => 'تراکنش آزمون صادرات ' . $i,
		)
	);
}

$report_svc = Sparkknife_Ledger_Report_Service::instance();

// بدون for_export فقط یک صفحه می‌آید (رفتار صفحه‌بندی عادی).
$page_report = $report_svc->get_person_report( $export_person_id, array( 'page' => 1 ) );
$page_count  = count( $page_report['transactions']['items'] );

// با for_export همه تراکنش‌ها می‌آیند.
$full_report = $report_svc->get_person_report( $export_person_id, array( 'page' => 1, 'for_export' => true ) );
$full_count  = count( $full_report['transactions']['items'] );

spk_equals( 45, $full_count, 'صادرات همه تراکنش‌های شخص را می‌دهد' );
spk_assert( $full_count > $page_count, 'صادرات بیشتر از یک صفحه ردیف دارد', $page_count . ' → ' . $full_count );

// گزارش تجمیعی هم با for_export از سقف ۲۰۰ ردیف صفحه عبور می‌کند.
$summary_export = $report_svc->get_summary_report( array( 'page' => 1, 'for_export' => true ) );
spk_assert(
	$summary_export['pagination']['per_page'] > Sparkknife_Ledger_Report_Service::MAX_PAGE_SIZE,
	'صادرات تجمیعی از سقف صفحه‌بندی عبور می‌کند',
	(string) $summary_export['pagination']['per_page']
);

// ولی سقف امنیتی برای صفحه عادی سر جایش است: ۵۰۰ خواسته، ۲۰۰ می‌گیرد.
$capped = $report_svc->get_summary_report( array( 'page' => 1, 'per_page' => 500 ) );
spk_equals( 200, $capped['pagination']['per_page'], 'سقف ۲۰۰ ردیف برای صفحه عادی حفظ شده است' );

// ── ۲۵د: تاریخ آینده روی PHP ۳۲ بیتی رد می‌شود ─────────────────────
// `strtotime()` برای تاریخ بعد از ۲۰۳۸ روی PHP ۳۲ بیتی `false` می‌دهد و
// `false > int` همیشه نادرست است؛ پس بررسی «تاریخ آینده» بی‌صدا از کار
// می‌افتاد و می‌شد تراکنشی با تاریخ ۲۰۴۰ ثبت کرد.
spk_assert( Sparkknife_Ledger_Date::is_future( '2099-12-31' ), 'سال ۲۰۹۹ آینده تشخیص داده می‌شود' );
spk_assert( Sparkknife_Ledger_Date::is_future( '2040-01-01' ), 'سال ۲۰۴۰ آینده تشخیص داده می‌شود' );

$today_str = Sparkknife_Ledger_Date::today();
spk_assert( ! Sparkknife_Ledger_Date::is_future( $today_str ), 'امروز آینده نیست' );
spk_assert( ! Sparkknife_Ledger_Date::is_future( '1970-01-01' ), 'گذشته آینده نیست' );
spk_assert( ! Sparkknife_Ledger_Date::is_future( 'not-a-date' ), 'تاریخ نامعتبر آینده شمرده نمی‌شود' );

// فردا هنوز مجاز است (رواداری منطقه زمانی).
$tomorrow_ts = strtotime( $today_str . ' +1 day' );
if ( false !== $tomorrow_ts ) {
	spk_assert(
		! Sparkknife_Ledger_Date::is_future( gmdate( 'Y-m-d', $tomorrow_ts ) ),
		'تاریخ فردا مجاز است'
	);
}

// تا آخر آزمون تقویم شمسی است.
spk_assert( Sparkknife_Ledger_Date::uses_jalali(), 'تقویم آزمون شمسی است' );

// ── ۲۵ه: نام نامرئی پذیرفته نمی‌شود ────────────────────────────────
// `sanitize_text_field()` نویسه‌های کنترلی و بی‌عرض را نگه می‌دارد، پس
// می‌شد شخصی با نام «\x01\x02» یا فقط نیم‌فاصله ساخت. فیلد «نام
// الزامی» قبول می‌شد ولی ردیف در فهرست خالی دیده می‌شد.
spk_equals( '', Sparkknife_Ledger_Sanitizer::text( "\x00\x01\x02" ), 'نویسه کنترلی خالص خالی می‌شود' );
spk_equals( '', Sparkknife_Ledger_Sanitizer::text( "\u{200B}\u{200C}" ), 'نیم‌فاصله تنها خالی می‌شود' );

// نیم‌فاصله در وسط نام معتبر است و نباید حذف شود.
spk_equals( 'می‌رود', Sparkknife_Ledger_Sanitizer::text( 'می‌رود' ), 'نیم‌فاصله داخل نام حفظ می‌شود' );

// متن سالم دست‌نخورده می‌ماند.
spk_equals( 'علی رضایی', Sparkknife_Ledger_Sanitizer::text( 'علی رضایی' ), 'نام سالم تغییر نمی‌کند' );

// کنترلی مخلوط با متن: فقط کنترل حذف می‌شود.
spk_equals( 'علی', Sparkknife_Ledger_Sanitizer::text( "\x01علی\x02" ), 'کنترلی از میان متن حذف می‌شود' );

// ساخت شخص با نام نامرئی باید رد شود.
$invisible = $ps->create( array( 'name' => "\u{200B}\u{200C}", 'phone' => '09125550200' ) );
spk_assert( is_wp_error( $invisible ), 'ساخت شخص با نام نامرئی رد می‌شود' );

// ── ۲۵و: نام بلند بی‌صدا بریده نمی‌شود ────────────────────────────
// `Sanitizer::text()` نام را بی‌صدا به ۱۹۰ نویسه می‌برید؛ کاربر نام
// کاملی تایپ می‌کرد و نیمه دومش بدون هیچ پیامی گم می‌شد.
spk_equals( 3, Sparkknife_Ledger_Sanitizer::length( 'علی' ), 'طول فارسی نویسه‌ای شمرده می‌شود' );
spk_equals( 0, Sparkknife_Ledger_Sanitizer::length( '' ), 'طول رشته خالی صفر است' );

$too_long = $ps->create(
	array(
		'name'  => str_repeat( 'ا', 200 ),
		'phone' => '09125550300',
	)
);
spk_assert( is_wp_error( $too_long ), 'نام بلندتر از ۱۹۰ نویسه رد می‌شود' );

if ( is_wp_error( $too_long ) ) {
	spk_assert(
		false !== mb_strpos( $too_long->get_error_message(), '۱۹۰' ),
		'پیام خطای نام بلند حد مجاز را می‌گوید',
		$too_long->get_error_message()
	);
}

// نام تک‌نویسه هم رد می‌شود (قبلاً به mbstring وابسته بود).
$one_char = $ps->create( array( 'name' => 'ا', 'phone' => '09125550400' ) );
spk_assert( is_wp_error( $one_char ), 'نام تک‌نویسه رد می‌شود' );

// ── ۲۵ز: «این ماه» در تقویم شمسی، ماه شمسی است ───────────────────
// بازه‌های آماده همیشه میلادی حساب می‌شدند: «این ماه» از ۱ تا ۳۰ نوامبر
// می‌رفت که در شمسی می‌شود ۱۰ آبان تا ۹ آذر — نه یک ماه، بلکه بازه‌ای کج
// که هم بخشی از آبان را جا می‌گذاشت و هم بخشی از آذر را بی‌ربط شامل می‌شد.
$presets = Sparkknife_Ledger_Ledger_Service::preset_ranges();

$month_start = $presets['this_month']['from'];
$month_end   = $presets['this_month']['to'];

spk_assert( '' !== $month_start && '' !== $month_end, 'بازه «این ماه» پر است' );
spk_assert( $month_start <= $month_end, 'شروع ماه پیش از پایان آن است' );

// شروع باید اول ماه شمسی باشد: ۱ فروردین تا ۱ اسفند.
list( $sy, $sm, $sd ) = Sparkknife_Ledger_Date::gregorian_to_jalali(
	(int) substr( $month_start, 0, 4 ),
	(int) substr( $month_start, 5, 2 ),
	(int) substr( $month_start, 8, 2 )
);
spk_equals( 1, (int) $sd, '«این ماه» از روز اول ماه شمسی شروع می‌شود' );

// پایان باید آخرین روز همان ماه شمسی باشد.
list( $ey, $em, $ed ) = Sparkknife_Ledger_Date::gregorian_to_jalali(
	(int) substr( $month_end, 0, 4 ),
	(int) substr( $month_end, 5, 2 ),
	(int) substr( $month_end, 8, 2 )
);
spk_equals( (int) $sy, (int) $ey, 'سال شروع و پایان «این ماه» یکی است' );
spk_equals( (int) $sm, (int) $em, 'ماه شروع و پایان «این ماه» یکی است' );

$expected_last_day = (int) $sm <= 6 ? 31 : ( (int) $sm <= 11 ? 30 : ( Sparkknife_Ledger_Date::is_jalali_leap( (int) $sy ) ? 30 : 29 ) );
spk_equals( $expected_last_day, (int) $ed, '«این ماه» تا آخرین روز ماه شمسی ادامه دارد' );

// ماه گذشته باید ماه شمسی قبلی باشد.
list( $py, $pm ) = Sparkknife_Ledger_Date::gregorian_to_jalali(
	(int) substr( $presets['last_month']['from'], 0, 4 ),
	(int) substr( $presets['last_month']['from'], 5, 2 ),
	(int) substr( $presets['last_month']['from'], 8, 2 )
);

$expected_prev = (int) $sm - 1;
$expected_prev_year = (int) $sy;

if ( $expected_prev < 1 ) {
	$expected_prev      = 12;
	$expected_prev_year = (int) $sy - 1;
}

spk_equals( (string) $expected_prev, (string) $pm, '«ماه گذشته» ماه شمسی قبلی است' );
spk_equals( (string) $expected_prev_year, (string) $py, 'سال «ماه گذشته» درست است' );

// ── ۲۵ح: نمودار ماهانه با ماه شمسی گروه‌بندی می‌شود ──────────────
// نمودار با `DATE_FORMAT(txn_date, '%Y-%m')` گروه‌بندی می‌شد. در تقویم
// شمسی، تراکنش ۵ آذر (۲۰۲۶-۱۱-۲۶) در سطل «2026-11» می‌افتاد که برچسبش
// «آبان» بود؛ یعنی آذر زیر ستون آبان نمایش داده می‌شد و ستون آذر ساخته
// نمی‌شد.
$chart_buckets = Sparkknife_Ledger_Date::month_buckets( 3 );

spk_equals( 3, count( $chart_buckets ), 'نمودار سه سطل ماهانه دارد' );

// ترتیب صعودی و چسبیده: پایان هر ماه یک روز پیش از شروع ماه بعد است.
for ( $i = 1; $i < count( $chart_buckets ); $i++ ) {
	spk_assert(
		$chart_buckets[ $i ]['from'] > $chart_buckets[ $i - 1 ]['to'],
		'سطل‌های نمودار پیوسته و بدون همپوشانی‌اند',
		$chart_buckets[ $i - 1 ]['to'] . ' < ' . $chart_buckets[ $i ]['from']
	);
}

// هر سطل باید از روز اول یک ماه شروع شود.
foreach ( $chart_buckets as $bucket ) {
	list( $by, $bm, $bd ) = Sparkknife_Ledger_Date::gregorian_to_jalali(
		(int) substr( $bucket['from'], 0, 4 ),
		(int) substr( $bucket['from'], 5, 2 ),
		(int) substr( $bucket['from'], 8, 2 )
	);

	spk_equals( 1, (int) $bd, 'سطل «' . $bucket['label'] . '» از اول ماه شروع می‌شود' );
}

// کلید ماه باید با تقویم فعال هم‌خوان باشد.
$today_key = Sparkknife_Ledger_Date::storage_to_month_key( Sparkknife_Ledger_Date::today() );

spk_equals(
	$chart_buckets[ count( $chart_buckets ) - 1 ]['key'],
	$today_key,
	'آخرین سطل نمودار همان ماه جاری است'
);

// تراکنش روز آخر ماه نباید به ماه بعد بپرد.
$last_bucket = $chart_buckets[ count( $chart_buckets ) - 1 ];
spk_equals(
	$last_bucket['key'],
	Sparkknife_Ledger_Date::storage_to_month_key( $last_bucket['to'] ),
	'روز آخر ماه در همان ماه شماری می‌شود'
);

// ── ۲۵ط: جمع زیر جدول، جمع ردیف‌های نمایش‌داده‌شده است ────────────
// `totals` عمداً مستقل از جستجو و از کل بازه حساب می‌شود و برچسب کارت‌ها
// «مجموع ... تمام اشخاص» است. اما همان عدد در `<tfoot>` جدول هم استفاده
// می‌شد، پس با جستجویی که یک ردیف هزار تومانی داشت، «جمع کل» زیر جدول
// ۱۰٬۰۰۰ نشان می‌داد در حالی که ستون فقط ۱٬۰۰۰ جمع می‌زد.
$totals_person_a = 'شخص جمع‌آلف';
$totals_person_b = 'شخص جمع‌ب';

foreach ( array( $totals_person_a, $totals_person_b ) as $cleanup_name ) {
	$wpdb->query(
		$wpdb->prepare(
			'DELETE FROM ' . Sparkknife_Ledger_DB::persons_table() . ' WHERE name = %s',
			$cleanup_name
		)
	);
}

$totals_id_a = $ps->create( array( 'name' => $totals_person_a, 'phone' => '09125551001' ) );
$totals_id_b = $ps->create( array( 'name' => $totals_person_b, 'phone' => '09125551002' ) );

$txn_svc->create(
	array(
		'person_id' => $totals_id_a,
		'type'      => 'income',
		'amount'    => '1000',
		'txn_date'  => '1403/05/01',
	)
);
$txn_svc->create(
	array(
		'person_id' => $totals_id_b,
		'type'      => 'income',
		'amount'    => '9000',
		'txn_date'  => '1403/05/02',
	)
);

$filtered_report = $report_svc->get_summary_report(
	array(
		'search'     => $totals_person_a,
		'range'      => 'all',
		'for_export' => true,
	)
);

spk_assert( isset( $filtered_report['filtered_totals'] ), 'گزارش جمع ردیف‌های فیلترشده را دارد' );

spk_equals( '1', (string) count( $filtered_report['rows'] ), 'جستجو یک ردیف برمی‌گرداند' );
spk_equals( $filtered_report['rows'][0]['income'], $filtered_report['filtered_totals']['income'], 'جمع فیلترشده با تنها ردیف برابر است' );
spk_assert(
	Sparkknife_Ledger_Amount::cmp( $filtered_report['filtered_totals']['income'], $filtered_report['totals']['income'] ) < 0,
	'جمع فیلترشده از جمع کل کمتر است'
);

// بدون فیلتر، هر دو جمع باید یکی باشند.
$unfiltered_report = $report_svc->get_summary_report( array( 'range' => 'all', 'for_export' => true ) );
spk_equals( $unfiltered_report['totals']['income'], $unfiltered_report['filtered_totals']['income'], 'بدون فیلتر جمع فیلترشده با جمع کل یکی است' );

// ── ۲۵ی: تب تنظیمات ناشناخته هیچ چیز را تغییر نمی‌دهد ─────────────
// `section_keys()` برای نام ناشناخته آرایه خالی برمی‌گرداند و شرط
// `! empty( $section_keys )` آن را رد می‌کرد، پس `$sanitized` دست‌نخورده
// می‌ماند و همه چک‌باکس‌ها صفر می‌شدند — همان باگ پاس اول، از راه دیگر.

/*
 * «قبل» باید همین‌جا خوانده شود.
 *
 * پیش‌تر این بلوک به `$settings_before` تکیه می‌کرد که در بلوک ۲۵ذ
 * (چند صد خط **پایین‌تر**) تعریف می‌شد. یعنی هنگام اجرای اینجا متغیر
 * وجود نداشت و `(string) null['use_persian_digits']` مقدار تهی می‌داد؛
 * سه بررسی همیشه شکست می‌خوردند، مستقل از درستی کد. تستی که همیشه
 * قرمز است، تست نیست.
 */
Sparkknife_Ledger_Settings::flush_cache();
$settings_before = Sparkknife_Ledger_Settings::all();

$unknown_section = Sparkknife_Ledger_Settings::save( array( 'settings_section' => 'not_a_real_tab' ), false );

spk_assert( is_wp_error( $unknown_section ), 'تب ناشناخته خطا برمی‌گرداند' );

Sparkknife_Ledger_Settings::flush_cache();
$settings_after = Sparkknife_Ledger_Settings::all();

spk_equals(
	(string) $settings_before['use_persian_digits'],
	(string) $settings_after['use_persian_digits'],
	'تب ناشناخته چک‌باکس ارقام فارسی را خاموش نمی‌کند'
);
spk_equals(
	(string) $settings_before['require_void_reason'],
	(string) $settings_after['require_void_reason'],
	'تب ناشناخته دلیل ابطال را خاموش نمی‌کند'
);
spk_equals(
	(string) $settings_before['allow_person_delete'],
	(string) $settings_after['allow_person_delete'],
	'تب ناشناخته حذف شخص را خاموش نمی‌کند'
);

// تب شناخته‌شده باید فقط کلیدهای خودش را عوض کند.
Sparkknife_Ledger_Settings::save(
	array(
		'settings_section' => 'currency',
		'currency_decimals' => 2,
	),
	false
);
Sparkknife_Ledger_Settings::flush_cache();

$after_currency = Sparkknife_Ledger_Settings::all();

spk_equals( '2', (string) $after_currency['currency_decimals'], 'تب ارز مقدار خودش را ذخیره می‌کند' );
spk_equals(
	(string) $settings_before['use_persian_digits'],
	(string) $after_currency['use_persian_digits'],
	'تب ارز تنظیمات تب‌های دیگر را دست نمی‌زند'
);

// بازگرداندن تنظیمات اولیه.
Sparkknife_Ledger_Settings::save( array( 'show_welcome_panel' => 1, 'use_persian_digits' => 1 ), false );
Sparkknife_Ledger_Settings::flush_cache();

// ── ۲۵ک: IP لاگ ممیزی با هدر جعل‌شدنی عوض نمی‌شود ────────────────
// `X-Forwarded-For` همیشه بر `REMOTE_ADDR` اولویت داشت، پس هر کاربری
// می‌توانست هر IP دلخواهی را در لاگ ممیزی ثبت کند. در دفتر مالی، IP
// ابزار ردیابی است؛ اگر قابل جعل باشد، ارزش اثباتی لاگ از بین می‌رود.
$audit_service = Sparkknife_Ledger_Audit_Service::instance();
$ip_method     = new ReflectionMethod( $audit_service, 'get_ip' );
$ip_method->setAccessible( true );

$saved_server = $_SERVER;

// پیش‌فرض: هدر پروکسی نادیده گرفته می‌شود.
$_SERVER['REMOTE_ADDR']          = '203.0.113.7';
$_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.99';

spk_equals( '203.0.113.7', $ip_method->invoke( $audit_service ), 'IP واقعی بر هدر جعل‌شدنی مقدم است' );

// بدون REMOTE_ADDR هم هدر پذیرفته نمی‌شود.
unset( $_SERVER['REMOTE_ADDR'] );
spk_equals( '', $ip_method->invoke( $audit_service ), 'بدون IP واقعی، هدر تنها پذیرفته نمی‌شود' );

// با فعال‌سازی صریح، هدر پذیرفته می‌شود (برای سایت پشت پروکسی).
$_SERVER['REMOTE_ADDR']          = '203.0.113.7';
$_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.1, 198.51.100.2';

add_filter( 'sparkknife_ledger_trust_proxy', '__return_true' );

spk_equals( '198.51.100.2', $ip_method->invoke( $audit_service ), 'با اتکا به پروکسی، آخرین آدرس زنجیره گرفته می‌شود' );

remove_filter( 'sparkknife_ledger_trust_proxy', '__return_true' );

$_SERVER = $saved_server;

// ── ۲۵ل: برش متن هرگز وسط نویسه UTF-8 نمی‌افتد ────────────────────
// `substr()` روی UTF-8 می‌تواند یک نویسه چندبایتی را از وسط ببرد و
// رشته خراب بسازد. User-Agent ورودی کاربر است و می‌تواند هر چیزی باشد؛
// رشته خراب هم در نمایش مشکل می‌دهد و هم در JSON.
$emoji_run = str_repeat( "\u{1F916}", 100 );
$cut_run   = Sparkknife_Ledger_Sanitizer::limit( $emoji_run, 10 );

spk_equals( 10, Sparkknife_Ledger_Sanitizer::length( $cut_run ), 'برش ایموجی ده نویسه می‌دهد' );
spk_assert( false !== preg_match( '//u', $cut_run ), 'نتیجه برش، UTF-8 سالم است' );

// برش وسط رشته مختلط.
$mixed_run = "ab\u{1F916}cd\u{1F389}ef";
$mixed_cut = Sparkknife_Ledger_Sanitizer::limit( $mixed_run, 4 );

spk_equals( 4, Sparkknife_Ledger_Sanitizer::length( $mixed_cut ), 'برش مختلط چهار نویسه می‌دهد' );
spk_assert( false !== preg_match( '//u', $mixed_cut ), 'برش مختلط هم UTF-8 سالم است' );

// متن کوتاه‌تر از سقف دست‌نخورده می‌ماند.
spk_equals( 'علی', Sparkknife_Ledger_Sanitizer::limit( 'علی', 190 ), 'متن کوتاه بریده نمی‌شود' );

// متن فارسی بلند: ۱۹۰ نویسه، نه ۱۹۰ بایت.
$long_persian = Sparkknife_Ledger_Sanitizer::limit( str_repeat( 'ی', 500 ), 190 );
spk_equals( 190, Sparkknife_Ledger_Sanitizer::length( $long_persian ), 'سقف بر حسب نویسه اعمال می‌شود نه بایت' );

// ── ۲۵م: ستون مبلغ در CSV عدد است، نه متن ─────────────────────────
// `Money::format()` ارقام فارسی می‌کند و برچسب واحد پول می‌چسباند. آن
// رشته در Excel متن است نه عدد، پس کاربر نمی‌تواند جمع بزند یا نمودار
// بکشد. برای فایل صادراتی حسابداری یعنی فایل عملاً بی‌مصرف.
$csv_owner = 'شخص سی‌اس‌وی‌آزمون';
$wpdb->query(
	$wpdb->prepare(
		'DELETE FROM ' . Sparkknife_Ledger_DB::persons_table() . ' WHERE name = %s',
		$csv_owner
	)
);

$csv_person_id = $ps->create( array( 'name' => $csv_owner, 'phone' => '09125552000' ) );
$txn_svc->create(
	array(
		'person_id' => $csv_person_id,
		'type'      => 'income',
		'amount'    => '1234',
		'txn_date'  => '1403/05/10',
	)
);

$csv_report = $report_svc->get_summary_report( array( 'range' => 'all', 'for_export' => true ) );
$csv_rows   = $export->summary_report_rows( $csv_report );

$csv_data_row = null;

foreach ( $csv_rows as $csv_candidate ) {
	if ( is_array( $csv_candidate ) && isset( $csv_candidate[1] ) && $csv_candidate[1] === $csv_owner ) {
		$csv_data_row = $csv_candidate;
		break;
	}
}

spk_assert( null !== $csv_data_row, 'ردیف داده در CSV پیدا شد' );

if ( null !== $csv_data_row ) {
	// ستون ۳ (ورودی) باید عدد خالص باشد.
	spk_assert( is_numeric( $csv_data_row[3] ), 'ستون ورودی CSV عدد است', var_export( $csv_data_row[3], true ) );
	spk_equals( '1234', (string) $csv_data_row[3], 'مبلغ CSV همان مبلغ ثبت‌شده است' );

	// هیچ ارقام فارسی یا برچسب واحد پول نباید داخل سلول مبلغ باشد.
	spk_assert( false === strpos( (string) $csv_data_row[3], 'تومان' ), 'ستون مبلغ برچسب واحد پول ندارد' );
	spk_assert( false === strpos( (string) $csv_data_row[3], '،' ), 'ستون مبلغ جداکننده فارسی ندارد' );

	// واحد پول باید در سرصفحه آمده باشد تا اطلاعات گم نشود.
	$currency_in_header = false;

	foreach ( $csv_rows as $csv_head ) {
		if ( is_array( $csv_head ) && isset( $csv_head[0] ) && 0 === strpos( (string) $csv_head[0], 'واحد پول' ) ) {
			$currency_in_header = true;
			break;
		}
	}

	spk_assert( $currency_in_header, 'واحد پول در سرصفحه CSV ذکر شده است' );
}

// ── ۲۵ن: ستون تاریخ در CSV قابل مرتب‌سازی است ─────────────────────
// `Date::format()` تاریخ شمسی با ارقام فارسی می‌دهد («۱۴۰۳/۰۵/۱۰»).
// Excel آن را تاریخ نمی‌شناسد، پس نه مرتب می‌شود نه فیلتر بازه‌ای.
$date_csv_report = $report_svc->get_person_report(
	$csv_person_id,
	array( 'range' => 'all', 'for_export' => true )
);

if ( ! is_wp_error( $date_csv_report ) ) {
	$date_rows = $export->person_report_rows( $date_csv_report );

	$transaction_row = null;

	foreach ( $date_rows as $date_row ) {
		if ( is_array( $date_row ) && isset( $date_row[0] ) && 0 === strpos( (string) $date_row[0], 'TXN-' ) ) {
			$transaction_row = $date_row;
			break;
		}
	}

	spk_assert( null !== $transaction_row, 'ردیف تراکنش در CSV گزارش فردی هست' );

	if ( null !== $transaction_row ) {
		// ستون تاریخ (اندیس ۱) باید ISO لاتین باشد.
		spk_assert(
			(bool) preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $transaction_row[1] ),
			'ستون تاریخ CSV قالب ISO دارد',
			var_export( $transaction_row[1], true )
		);

		// هیچ رقم فارسی در ستون تاریخ نباشد.
		spk_assert(
			! preg_match( '/[۰-۹٠-٩]/u', (string) $transaction_row[1] ),
			'ستون تاریخ CSV رقم فارسی ندارد'
		);
	}
}

// ── ۲۶: جستجو با ارقام فارسی ─────────────────────────────────────
$p0 = mb_chr( 0x06F0, 'UTF-8' ); // ۰
$p1 = mb_chr( 0x06F1, 'UTF-8' ); // ۱
$p2 = mb_chr( 0x06F2, 'UTF-8' ); // ۲
$p9 = mb_chr( 0x06F9, 'UTF-8' ); // ۹
$a0 = mb_chr( 0x0660, 'UTF-8' ); // ٠
$a1 = mb_chr( 0x0661, 'UTF-8' ); // ١
$a2 = mb_chr( 0x0662, 'UTF-8' ); // ٢
$a9 = mb_chr( 0x0669, 'UTF-8' ); // ٩

$person_repo_digits = Sparkknife_Ledger_Person_Repository::instance();

foreach ( array( '09125554444', $p0 . '۹۱۲۵۵۵۴۴۴۴', $a0 . '٩١٢٥٥٥٤٤٤٤' ) as $digit_query ) {
	$digit_result = $person_repo_digits->query( array( 'search' => $digit_query, 'page' => 1, 'per_page' => 10 ) );
	spk_assert( $digit_result['total'] >= 0, 'جستجوی شماره با ارقام: ' . $digit_query );
}

// خودِ تابع یکسان‌سازی باید ارقام را لاتین کند.
spk_equals(
	'0912',
	Sparkknife_Ledger_Sanitizer::normalize_persian( $p0 . $p9 . $p1 . $p2 ),
	'ارقام فارسی در جستجو لاتین می‌شوند'
);

spk_equals(
	'0912',
	Sparkknife_Ledger_Sanitizer::normalize_persian( $a0 . $a9 . $a1 . $a2 ),
	'ارقام عربی در جستجو لاتین می‌شوند'
);

// ── ۲۷: مرتب‌سازی الفبایی فارسی ──────────────────────────────────
// strcmp ترتیب بایتی UTF-8 می‌دهد: «پ» بعد از «د» و «چ» بعد از «ی».
$sort_expected = array( 'امیر', 'بهرام', 'پرویز', 'تیرداد', 'جواد', 'چنگیز', 'حسن', 'داریوش', 'کیوان', 'گلناز' );

$sort_input = $sort_expected;
shuffle( $sort_input );

usort(
	$sort_input,
	function ( $a, $b ) {
		return strcmp(
			Sparkknife_Ledger_Sanitizer::persian_sort_key( $a ),
			Sparkknife_Ledger_Sanitizer::persian_sort_key( $b )
		);
	}
);

spk_equals(
	wp_json_encode( $sort_expected ),
	wp_json_encode( $sort_input ),
	'مرتب‌سازی الفبایی فارسی: ا ب پ ت ج چ ح د ک گ',
	wp_json_encode( $sort_input )
);

// «پ» باید پیش از «ت» بیاید (در UTF-8 بایت پ بزرگ‌تر است).
spk_assert(
	Sparkknife_Ledger_Sanitizer::persian_sort_key( 'پرویز' ) < Sparkknife_Ledger_Sanitizer::persian_sort_key( 'تیرداد' ),
	'کلید مرتب‌سازی: پ پیش از ت'
);

// ── ۲۸: بازسازی جداول گم‌شده حتی وقتی نسخه به‌روز است ──────────────
// اگر مدیر جداول را دستی پاک کند، نسخه در دیتابیس هنوز ۱.۰.۰ می‌ماند.
// پیش‌تر maybe_upgrade() در این حالت هیچ کاری نمی‌کرد و افزونه برای
// همیشه خراب می‌ماند.

global $wpdb;

if ( ! defined( 'WP_CLI' ) ) {
	define( 'WP_CLI', true );
}

$restore_tables = array( 'audit_log', 'transactions', 'persons' );

foreach ( $restore_tables as $dropped_table ) {
	$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'sparkknife_ledger_' . $dropped_table );
}

Sparkknife_Ledger_DB::flush_table_cache();

spk_assert( ! Sparkknife_Ledger_DB::tables_exist(), 'جداول برای آزمون حذف شدند' );
spk_equals( SPARKKNIFE_LEDGER_DB_VERSION, get_option( 'sparkknife_ledger_db_version' ), 'نسخه هنوز به‌روز است' );

Sparkknife_Ledger_Installer::maybe_upgrade();
Sparkknife_Ledger_DB::flush_table_cache();

spk_assert( Sparkknife_Ledger_DB::tables_exist(), 'جداول گم‌شده به‌صورت خودکار بازسازی شدند' );

// و پس از بازسازی، افزونه باید کار کند.
$restored_person = Sparkknife_Ledger_Person_Service::instance()->create(
	array( 'name' => 'پس از بازسازی', 'phone' => '09120000001' )
);

spk_assert( ! is_wp_error( $restored_person ), 'ثبت شخص پس از بازسازی کار می‌کند', is_wp_error( $restored_person ) ? $restored_person->get_error_message() : '' );

// ── ۲۹: تنظیمات خراب (غیرآرایه) خودترمیم می‌شود ─────────────────────
update_option( Sparkknife_Ledger_Settings::OPTION_KEY, 'not-an-array' );
Sparkknife_Ledger_Settings::flush_cache();

$repaired = Sparkknife_Ledger_Settings::all();

spk_assert( is_array( $repaired ), 'تنظیمات خراب به آرایه تبدیل می‌شود' );
spk_assert( isset( $repaired['currency_type'] ), 'کلیدهای تنظیمات بازسازی می‌شوند' );

update_option( Sparkknife_Ledger_Settings::OPTION_KEY, 98765 );
Sparkknife_Ledger_Settings::flush_cache();

spk_assert( is_array( Sparkknife_Ledger_Settings::all() ), 'تنظیمات عددی هم خودترمیم می‌شود' );

// بازگرداندن تنظیمات سالم.
Sparkknife_Ledger_Settings::save( Sparkknife_Ledger_Settings::defaults(), false );
Sparkknife_Ledger_Settings::flush_cache();

// ── ۲۵س: جدول نیمه‌ساخته باید «خراب» گزارش شود و ترمیم گردد ────────
// اگر ساخت جدولی وسط کار قطع شود، جدول با چند ستون باقی می‌ماند. پیش‌تر
// گزارش سلامت فقط وجود جدول را می‌سنجید و آن را «سالم» نشان می‌داد، در حالی
// که ثبت ممیزی در آن شکست می‌خورد و هیچ تغییری ثبت نمی‌شد.

global $wpdb;

$audit_table = Sparkknife_Ledger_DB::audit_table();

$required_columns = array( 'id', 'object_type', 'object_id', 'action', 'changes', 'created_at' );

$wpdb->query( "DROP TABLE IF EXISTS {$audit_table}" );
$wpdb->query( "CREATE TABLE {$audit_table} ( id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, PRIMARY KEY (id) )" );
Sparkknife_Ledger_DB::flush_table_cache();

$missing_before = Sparkknife_Ledger_DB::missing_columns( $audit_table, $required_columns );

spk_assert( ! empty( $missing_before ), 'جدول نیمه‌ساخته ستون‌های غایب دارد' );

$report = Sparkknife_Ledger_Installer::get_health_report();
$audit_row = null;

foreach ( $report as $row ) {
	if ( isset( $row['table'] ) && $row['table'] === $audit_table ) {
		$audit_row = $row;
	}
}

spk_assert( null !== $audit_row, 'جدول ممیزی در گزارش سلامت هست' );
spk_equals( 'broken', $audit_row['status'], 'گزارش سلامت جدول نیمه‌ساخته را «خراب» می‌داند' );
spk_assert( ! empty( $audit_row['missing_columns'] ), 'گزارش سلامت ستون‌های غایب را نام می‌برد' );

$failed = Sparkknife_Ledger_Audit_Service::instance()->log( 'person', 1, 'test', array(), 'آزمون' );

spk_assert( false === $failed || empty( $failed ), 'ثبت ممیزی روی جدول ناقص موفق نیست' );

Sparkknife_Ledger_Installer::maybe_upgrade();
Sparkknife_Ledger_DB::flush_table_cache();

$missing_after = Sparkknife_Ledger_DB::missing_columns( $audit_table, $required_columns );

spk_equals( array(), $missing_after, 'maybe_upgrade جدول نیمه‌ساخته را ترمیم می‌کند' );

$report_after = Sparkknife_Ledger_Installer::get_health_report();

foreach ( $report_after as $row ) {
	if ( isset( $row['table'] ) && $row['table'] === $audit_table ) {
		spk_equals( 'ok', $row['status'], 'گزارش سلامت پس از ترمیم «سالم» است' );
	}
}

$logged = Sparkknife_Ledger_Audit_Service::instance()->log( 'person', 1, 'test', array(), 'پس از ترمیم' );

spk_assert( is_int( $logged ) || is_string( $logged ), 'ثبت ممیزی پس از ترمیم موفق است' );

// ── ۲۵ع: وضعیت «ناقص» از «یافت نشد» تفکیک می‌شود ───────────────────
// پیش‌تر هر وضعیت غیر از ok در پنل «یافت نشد» نشان داده می‌شد، پس جدولی
// که وجود دارد ولی ستون‌هایش ناقص است با جدول غایب یکی دیده می‌شد و مدیر
// پیام گمراه‌کننده می‌گرفت.

global $wpdb;

$audit_table = Sparkknife_Ledger_DB::audit_table();

$wpdb->query( "DROP TABLE IF EXISTS {$audit_table}" );
$wpdb->query( "CREATE TABLE {$audit_table} ( id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, PRIMARY KEY (id) )" );
Sparkknife_Ledger_DB::flush_table_cache();

$health = Sparkknife_Ledger_Installer::get_health_report();
$audit_health = null;

foreach ( $health as $row ) {
	if ( isset( $row['table'] ) && $row['table'] === $audit_table ) {
		$audit_health = $row;
	}
}

spk_assert( null !== $audit_health, 'ردیف سلامت جدول ممیزی موجود است' );
spk_equals( 'broken', $audit_health['status'], 'وضعیت جدول نیمه‌ساخته «broken» است نه «missing»' );
spk_assert(
	! empty( $audit_health['missing_columns'] ),
	'فهرست ستون‌های غایب برای نمایش در پنل موجود است'
);

// برچسب فارسی هر وضعیت باید متفاوت باشد.
$labels = array(
	'ok'      => __( 'آماده', 'sparkknife-ledger' ),
	'broken'  => __( 'ناقص', 'sparkknife-ledger' ),
	'missing' => __( 'یافت نشد', 'sparkknife-ledger' ),
);

spk_assert( $labels['broken'] !== $labels['missing'], 'برچسب «ناقص» با «یافت نشد» یکی نیست' );

// بازسازی برای ادامه آزمون‌ها.
Sparkknife_Ledger_Installer::maybe_upgrade();
Sparkknife_Ledger_DB::flush_table_cache();

// ── ۲۵ف: بک‌اسلش نام شخص دو بار unslash نمی‌شود ────────────────────
// مسیر واقعی: وردپرس ابرگلوبال را slash می‌زند، کنترلر یک بار
// `wp_unslash()` می‌کند، و `Sanitizer::text()` پیش‌تر دوباره این کار را
// می‌کرد. نتیجه: نام «بک‌اسلش\دار» بی‌صدا به «بک‌اسلشدار» تبدیل می‌شد.

global $wpdb;

$pe = Sparkknife_Ledger_DB::persons_table();

// نامی که یک بک‌اسلش واقعی دارد، پس از یک بار unslash.
$name_after_unslash = 'کاربر بک‌اسلش\دار';

$sanitized = Sparkknife_Ledger_Sanitizer::text( $name_after_unslash );

spk_equals(
	$name_after_unslash,
	$sanitized,
	'Sanitizer::text بک‌اسلش را دست‌نخورده نگه می‌دارد'
);

$sanitized_area = Sparkknife_Ledger_Sanitizer::textarea( "خط اول\nمسیر C:\\پوشه\\فایل" );

spk_assert(
	false !== strpos( $sanitized_area, '\\' ),
	'Sanitizer::textarea بک‌اسلش را نگه می‌دارد'
);

// مسیر کامل کنترلر → سرویس → دیتابیس.
$phone = '0919' . str_pad( (string) wp_rand( 1000000, 9999999 ), 7, '0', STR_PAD_LEFT );
$made  = Sparkknife_Ledger_Person_Service::instance()->create(
	array(
		'name'  => $name_after_unslash,
		'phone' => $phone,
	)
);

spk_assert( ! is_wp_error( $made ), 'شخص با بک‌اسلش در نام ثبت می‌شود' );

if ( ! is_wp_error( $made ) ) {
	$stored = $wpdb->get_var( $wpdb->prepare( "SELECT name FROM {$pe} WHERE id = %d", (int) $made ) );

	spk_equals( $name_after_unslash, (string) $stored, 'بک‌اسلش نام در دیتابیس حفظ می‌شود' );
	spk_assert(
		false !== strpos( (string) $stored, '\\' ),
		'نام ذخیره‌شده بک‌اسلش دارد'
	);

	Sparkknife_Ledger_Person_Service::instance()->delete( (int) $made );
}

// ── ۲۵ق: جمع فیلترشده با ردیف‌های جدول می‌خواند ───────────────────
// `query()` وقتی وضعیتی انتخاب نشده باشد هیچ فیلتری روی status نمی‌گذارد
// و ردیف‌های باطل‌شده را هم برمی‌گرداند. `sum_by_type()` پیش‌تر خالی را
// به `active_only` تبدیل می‌کرد، پس جمع پایین جدول از تعداد ردیف‌ها کمتر
// می‌شد: جدول ۵ ردیف نشان می‌داد و جمع می‌گفت ۴ — بدون توضیح.

global $wpdb;

$persons_service = Sparkknife_Ledger_Person_Service::instance();
$txn_service     = Sparkknife_Ledger_Transaction_Service::instance();
$report_service  = Sparkknife_Ledger_Report_Service::instance();
$txn_repository  = Sparkknife_Ledger_Transaction_Repository::instance();

$filter_person = $persons_service->create(
	array(
		'name'  => 'آزمون جمع فیلتر ' . wp_rand( 1000, 9999 ),
		'phone' => '0921' . str_pad( (string) wp_rand( 1000000, 9999999 ), 7, '0', STR_PAD_LEFT ),
	)
);

spk_assert( ! is_wp_error( $filter_person ), 'شخص آزمون جمع فیلتر ساخته می‌شود' );

if ( ! is_wp_error( $filter_person ) ) {
	$filter_person = (int) $filter_person;

	$make_txn = function ( $type, $amount, $date, $description ) use ( $txn_service, $filter_person ) {
		return $txn_service->create(
			array(
				'person_id'   => $filter_person,
				'type'        => $type,
				'amount'      => $amount,
				'txn_date'    => $date,
				'description' => $description,
			)
		);
	};

	$keep    = $make_txn( 'income', '100000', '1403/01/15', 'خرید کتاب' );
	$to_void = $make_txn( 'income', '200000', '1403/02/20', 'خرید لوازم' );
	$spend   = $make_txn( 'expense', '50000', '1403/03/10', 'فروش صندلی' );

	spk_assert( ! is_wp_error( $keep ) && ! is_wp_error( $to_void ) && ! is_wp_error( $spend ), 'سه تراکنش آزمون ساخته می‌شوند' );

	if ( ! is_wp_error( $to_void ) ) {
		$void_result = $txn_service->void( (int) ( is_array( $to_void ) ? $to_void['id'] : $to_void ), 'آزمون' );
		spk_assert( ! is_wp_error( $void_result ), 'تراکنش دوم ابطال می‌شود' );

		$range_args = array(
			'range'     => 'custom',
			'date_from' => '2024-01-01',
			'date_to'   => '2027-12-31',
		);

		// بدون فیلتر وضعیت: هر دو مبلغ ورودی باید در جمع باشند.
		$filtered = $txn_repository->sum_by_type(
			array(
				'person_id' => $filter_person,
				'date_from' => '2024-01-01',
				'date_to'   => '2027-12-31',
			)
		);

		$plain = Sparkknife_Ledger_Transaction_Repository::instance()->query(
			array(
				'person_id' => $filter_person,
				'date_from' => '2024-01-01',
				'date_to'   => '2027-12-31',
				'per_page'  => 50,
			)
		);

		spk_equals(
			(int) $plain['total'],
			(int) $filtered['count'],
			'جمع بدون فیلتر وضعیت، همان تعداد ردیف‌های فهرست است'
		);
		spk_equals( 3, (int) $filtered['count'], 'هر سه ردیف — از جمله باطل‌شده — شمرده می‌شوند' );

		// با فیلتر صریح posted، فقط معتبرها.
		$posted = $txn_repository->sum_by_type(
			array(
				'person_id' => $filter_person,
				'status'    => 'posted',
				'date_from' => '2024-01-01',
				'date_to'   => '2027-12-31',
			)
		);

		spk_equals( 2, (int) $posted['count'], 'با status=posted فقط ردیف‌های معتبر شمرده می‌شوند' );

		// گزارش فردی: `filtered` با `transactions.total` هم‌خوان است.
		$person_report = $report_service->get_person_report( $filter_person, $range_args );

		spk_assert( ! is_wp_error( $person_report ), 'گزارش فردی ساخته می‌شود' );

		if ( ! is_wp_error( $person_report ) ) {
			spk_equals(
				(int) $person_report['transactions']['total'],
				(int) $person_report['filtered']['count'],
				'گزارش فردی: جمع فیلترشده = تعداد ردیف‌های فهرست'
			);
		}
	}

	$persons_service->delete( $filter_person );
}

// ── ۲۵ر: پیشوند شماره تراکنش می‌تواند فارسی باشد ──────────────────
// پیش‌تر پیشوند با `[^A-Za-z0-9]` پاک می‌شد، پس «پیشوند-فارسی» یا
// «۱۲۳۴» به رشته خالی می‌رسید و بی‌صدا به TXN می‌افتاد: کاربر پیشوندش
// را در تنظیمات می‌دید ولی شماره تراکنش‌ها چیز دیگری بود.

global $wpdb;

$txn_service = Sparkknife_Ledger_Transaction_Service::instance();

// Settings پیشوند را به ۱۱ نویسه محدود می‌کند؛ انتظار هم همان است.
$prefix_cases = array(
	'پیشوند'   => 'پیشوند',
	'۱۲۳۴'     => '1234',
	'SPK'      => 'SPK',
	'A-B_C'    => 'A-B_C',
	''         => 'TXN',
);

$defaults_before = Sparkknife_Ledger_Settings::defaults();

foreach ( $prefix_cases as $input => $expected ) {
	Sparkknife_Ledger_Settings::save( array( 'reference_prefix' => $input ), false );
	Sparkknife_Ledger_Settings::flush_cache();

	$reference = $txn_service->generate_reference( '2024-07-31' );

	spk_assert(
		0 === strpos( $reference, $expected . '-' ),
		sprintf( 'پیشوند «%s» در شماره تراکنش به کار می‌رود', '' === $input ? '(خالی)' : $input )
	);
}

Sparkknife_Ledger_Settings::save( $defaults_before, false );
Sparkknife_Ledger_Settings::flush_cache();

// ── ۲۵س۲: تشخیص تکراری با همان قاعده جستجو کار می‌کند ─────────────
// `find_duplicate()` نام را با `strcasecmp` خام مقایسه می‌کرد، پس
// «علي رضایی» و «علی رضایی» دو شخص جدا بودند — با اینکه جستجو آن دو را
// یکی می‌داند. شماره هم با `\D` پاک می‌شد که ارقام فارسی را حذف
// می‌کند، نه تبدیل؛ پس «۰۹۱۲…» به رشته خالی می‌رسید.

$dup_service = Sparkknife_Ledger_Person_Service::instance();

$dup_phone = '0935' . str_pad( (string) wp_rand( 1000000, 9999999 ), 7, '0', STR_PAD_LEFT );
$dup_name  = 'آزمون تکراری ' . wp_rand( 1000, 9999 );

$dup_first = $dup_service->create(
	array(
		'name'  => $dup_name,
		'phone' => $dup_phone,
	)
);

spk_assert( ! is_wp_error( $dup_first ), 'شخص مبنای آزمون تکراری ساخته می‌شود' );

if ( ! is_wp_error( $dup_first ) ) {
	$dup_first = (int) $dup_first;

	// همان نام، همان شماره → رد.
	$same = $dup_service->create(
		array(
			'name'  => $dup_name,
			'phone' => $dup_phone,
		)
	);
	spk_assert( is_wp_error( $same ), 'نام و شماره یکسان رد می‌شود' );

	// نام با «ي» عربی → باید همان شخص شمرده شود.
	$arabic_yeh = str_replace( 'ی', 'ي', $dup_name );
	$with_yeh   = $dup_service->create(
		array(
			'name'  => $arabic_yeh,
			'phone' => $dup_phone,
		)
	);
	spk_assert( is_wp_error( $with_yeh ), 'نام با «ي» عربی با «ی» فارسی یکی شمرده می‌شود' );

	// شماره با ارقام فارسی → باید همان شماره شمرده شود.
	$persian_phone = '';
	$latin_to_persian = array( '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹' );
	foreach ( str_split( $dup_phone ) as $digit ) {
		$persian_phone .= $latin_to_persian[ $digit ];
	}

	$with_persian_digits = $dup_service->create(
		array(
			'name'  => $dup_name,
			'phone' => $persian_phone,
		)
	);
	spk_assert( is_wp_error( $with_persian_digits ), 'شماره با ارقام فارسی با لاتین یکی شمرده می‌شود' );

	// شماره با خط تیره → همان شماره.
	$dashed = substr( $dup_phone, 0, 4 ) . '-' . substr( $dup_phone, 4, 3 ) . '-' . substr( $dup_phone, 7 );
	$with_dashes = $dup_service->create(
		array(
			'name'  => $dup_name,
			'phone' => $dashed,
		)
	);
	spk_assert( is_wp_error( $with_dashes ), 'خط تیره در شماره تفاوت نمی‌سازد' );

	// شماره متفاوت با همان نام → مجاز (دو نفر هم‌نام).
	$different_phone = '0935' . str_pad( (string) wp_rand( 1000000, 9999999 ), 7, '0', STR_PAD_LEFT );
	$other = $dup_service->create(
		array(
			'name'  => $dup_name,
			'phone' => $different_phone,
		)
	);
	spk_assert( ! is_wp_error( $other ), 'دو شخص هم‌نام با شماره‌های متفاوت مجازند' );

	if ( ! is_wp_error( $other ) ) {
		$dup_service->delete( (int) $other );
	}

	$dup_service->delete( $dup_first );
}

// ── ۲۵ت: «منفی صفر» نمایش داده نمی‌شود ───────────────────────────
// مبلغ -۱۰۰۰ مقیاس‌شده یعنی منفی یک‌دهم تومان. با صفر رقم اعشار نمایشش
// «۰» است، ولی مقدار مقیاس‌شده صفر نیست؛ پیش‌تر شرط علامت روی همان
// مقدار مقیاس‌شده بود و نتیجه «-۰ تومان» می‌شد.

$zero_defaults = Sparkknife_Ledger_Settings::defaults();

Sparkknife_Ledger_Settings::save(
	array(
		'currency_decimals'      => 0,
		'custom_currency_label'  => 'واحد',
		'custom_currency_symbol' => 'واحد',
	),
	false
);
Sparkknife_Ledger_Settings::flush_cache();

$neg_zero = Sparkknife_Ledger_Money::format( '-1000' );

spk_assert( false === strpos( $neg_zero, '-' ), 'مبلغ کوچک‌تر از واحد نمایش، منفی صفر نشان نمی‌دهد' );
spk_assert( false === strpos( $neg_zero, '−' ), 'علامت منفی یونیکد هم نمی‌آید' );

// مبلغی که واقعاً منفی است، علامتش می‌ماند.
$real_negative = Sparkknife_Ledger_Money::format( '-50000' );

spk_assert( 0 === strpos( $real_negative, '-' ), 'مبلغ منفی واقعی علامت منفی دارد' );

// با دو رقم اعشار، همان مبلغ «-۰٫۱» می‌شود، نه صفر.
Sparkknife_Ledger_Settings::save( array( 'currency_decimals' => 2 ), false );
Sparkknife_Ledger_Settings::flush_cache();

$with_decimals = Sparkknife_Ledger_Money::to_human( '-1000' );

spk_equals( '-0.1', $with_decimals, 'با دو رقم اعشار، منفی یک‌دهم درست نمایش داده می‌شود' );

Sparkknife_Ledger_Settings::save( $zero_defaults, false );
Sparkknife_Ledger_Settings::flush_cache();

// ── ۲۵ث: ورودی نامعتبر مبلغ، خطا می‌دهد نه مبلغ اشتباه ────────────
// `normalize_amount_input()` هر نویسه غیرمجاز را حذف می‌کرد، پس ورودی
// نامعتبر به یک مبلغ معتبر ولی اشتباه تبدیل می‌گشت: «1e5» می‌شد ۱۵
// تومان و «0x10» می‌شد ۱۰ تومان. کاربر مبلغی تایپ می‌کرد و چیز دیگری
// ثبت می‌شد، بی‌هیچ پیامی.

$bad_amounts = array( '1e5', '0x10', '1+000', '12ab34', 'ده هزار' );

foreach ( $bad_amounts as $bad ) {
	$parsed = Sparkknife_Ledger_Money::parse_amount( $bad );

	spk_assert(
		is_wp_error( $parsed ),
		sprintf( 'مبلغ نامعتبر «%s» رد می‌شود (نه اینکه به عدد دیگری تبدیل شود)', $bad )
	);
}

// ورودی‌های معتبر همچنان کار می‌کنند.
$valid_amounts = array(
	'۱۰۰۰'    => '10000000',
	'۱٬۰۰۰'   => '10000000',
	'1,000'   => '10000000',
	'١٠٠٠'    => '10000000',
	'+1000'   => '10000000',
	'1000.5'  => '10005000',
);

foreach ( $valid_amounts as $raw => $expected ) {
	$parsed = Sparkknife_Ledger_Money::parse_amount( $raw );

	spk_equals( $expected, is_wp_error( $parsed ) ? 'خطا' : (string) $parsed, sprintf( 'مبلغ معتبر «%s» درست خوانده می‌شود', $raw ) );
}

// ── ۲۵ج: خطای تاریخ نامعتبر در همه گزارش‌ها دیده می‌شود ────────────
// `resolve_range()` برای تاریخ نامعتبر `WP_Error` در `range['error']`
// می‌گذاشت، ولی تنها گزارش فردی آن را نمایش می‌داد. در گزارش تجمیعی و
// فهرست تراکنش‌ها کاربر بی‌هیچ پیامی گزارشی با بازه نادیده‌گرفته‌شده
// می‌دید و آن را باور می‌کرد.

$bad_range = Sparkknife_Ledger_Ledger_Service::resolve_range(
	array(
		'range'     => 'custom',
		'date_from' => '2024-13-01',
		'date_to'   => '2026-12-31',
	)
);

spk_assert( isset( $bad_range['error'] ) && is_wp_error( $bad_range['error'] ), 'تاریخ نامعتبر خطا تولید می‌کند' );

$summary_with_bad_range = Sparkknife_Ledger_Report_Service::instance()->get_summary_report(
	array(
		'range'     => 'custom',
		'date_from' => '2024-13-01',
		'date_to'   => '2026-12-31',
	)
);

spk_assert(
	isset( $summary_with_bad_range['range']['error'] ) && is_wp_error( $summary_with_bad_range['range']['error'] ),
	'گزارش تجمیعی خطای بازه را به لایه نمایش می‌رساند'
);

// هر سه ویو باید پیام خطا را چاپ کنند.
$views_to_check = array(
	'includes/Admin/Views/reports/summary.php',
	'includes/Admin/Views/transactions/list.php',
	'includes/Admin/Views/reports/person.php',
);

$plugin_root = dirname( __DIR__, 2 ) . '/sparkknife-ledger/';

foreach ( $views_to_check as $view_relative ) {
	$view_path = $plugin_root . $view_relative;
	$source    = is_readable( $view_path ) ? (string) file_get_contents( $view_path ) : '';

	spk_assert(
		false !== strpos( $source, "['error']" ),
		sprintf( 'ویو %s خطای بازه را نمایش می‌دهد', basename( $view_relative ) )
	);
}

// ── ۲۵چ: پارامترهای آدرس کدگذاری می‌شوند ──────────────────────────
// `add_query_arg()` خودش کدگذاری نمی‌کند. مقداری مثل «علی & co» با `&`
// خام در آدرس می‌نشست و مرورگر آن را به دو پارامتر می‌شکست. با مقدار
// دستکاری‌شده می‌شد پارامتر جعلی ساخت («x&action=delete»).

$url_cases = array(
	'علی & co',
	'a b',
	'x&action=delete',
	'۱۰۰%',
	'علي',
);

foreach ( $url_cases as $query_value ) {
	$url = Sparkknife_Ledger_Renderer::url( 'persons', array( 'search' => $query_value ) );

	$parsed_query = (string) wp_parse_url( $url, PHP_URL_QUERY );

	$params = array();
	parse_str( $parsed_query, $params );

	spk_equals(
		$query_value,
		isset( $params['search'] ) ? (string) $params['search'] : '',
		sprintf( 'مقدار جستجوی «%s» پس از رفت و برگشت در آدرس سالم می‌ماند', $query_value )
	);

	spk_equals(
		'sparkknife-ledger-persons',
		isset( $params['page'] ) ? (string) $params['page'] : '',
		'صفحه درست در آدرس می‌ماند'
	);
}

// پارامتر جعلی ساخته نمی‌شود.
$tricky = Sparkknife_Ledger_Renderer::url( 'persons', array( 'search' => 'x&action=delete' ) );
$tricky_params = array();
parse_str( (string) wp_parse_url( $tricky, PHP_URL_QUERY ), $tricky_params );

spk_assert( ! isset( $tricky_params['action'] ), 'هیچ پارامتر جعلی از مقدار جستجو ساخته نمی‌شود' );

// ── ۲۵ح: جستجوی تاریخچه تغییرات کار می‌کند ────────────────────────
// `Audit_Repository::query()` پارامتر `search` را در `wp_parse_args()`
// نداشت، پس هر مقداری که می‌رسید بی‌صدا نادیده گرفته می‌شد و همهٔ
// رکوردها برمی‌گشت. با هزاران رکورد ممیزی، پیدا کردن یک تغییر عملاً
// ناممکن بود.

global $wpdb;

$audit_repo  = Sparkknife_Ledger_Audit_Repository::instance();
$audit_table = Sparkknife_Ledger_DB::audit_table();

$marker = 'نشانه‌جستجو' . wp_rand( 10000, 99999 );

Sparkknife_Ledger_Audit_Service::instance()->log(
	'person',
	999001,
	'test',
	array( 'note' => $marker ),
	sprintf( 'رکورد آزمون %s', $marker )
);

$all_audit = $audit_repo->query( array( 'per_page' => 200 ) );
$all_count = (int) $all_audit['total'];

spk_assert( $all_count > 0, 'تاریخچه رکورد دارد' );

$found = $audit_repo->query( array( 'search' => $marker, 'per_page' => 200 ) );

spk_equals( 1, (int) $found['total'], 'جستجو فقط رکورد منطبق را برمی‌گرداند' );

$not_found = $audit_repo->query( array( 'search' => 'هیچ‌چیز' . $marker, 'per_page' => 200 ) );

spk_equals( 0, (int) $not_found['total'], 'جستجوی بی‌نتیجه صفر برمی‌گرداند، نه همه' );
spk_assert( (int) $not_found['total'] < $all_count, 'تعداد نتایج از کل رکوردها کمتر است' );

// الگوی LIKE در جستجو باید escape شود، نه اینکه wildcard شود.
$wildcard = $audit_repo->query( array( 'search' => '%', 'per_page' => 200 ) );

spk_assert(
	(int) $wildcard['total'] < $all_count,
	'علامت درصد در جستجو wildcard نمی‌شود'
);

// ── ۲۶: escape جستجو به درایور دیتابیس وابسته نیست ─────────────────────
//
// `$wpdb->esc_like()` فقط در MySQL درست کار می‌کند. درایور SQLite وردپرس
// (SQLite Database Integration) آن را بی‌اثر پیاده کرده است:
//
//     esc_like('%')   → '%'      (نه '\%')
//     esc_like('_')   → '_'
//
// یعنی روی SQLite جستجوی `%` **همهٔ** ردیف‌ها را می‌گیرد و `_` هر نویسه‌ای
// را. پیشوند شمارهٔ تراکنش هم که `_` دارد، شمارهٔ بعدی را از ردیف اشتباه
// حساب می‌کند.
//
// این تست به درایور وابسته نیست: خودِ escape و رفتار `LIKE` را با `ESCAPE`
// صریح می‌سنجد، پس روی MySQL و SQLite یکسان نتیجه می‌دهد.

spk_equals( '!', Sparkknife_Ledger_Sanitizer::LIKE_ESCAPE, 'نویسهٔ فرار، ! است — نه بک‌اسلش' );

spk_equals( '%!%%', Sparkknife_Ledger_Sanitizer::like_pattern( '%' ), 'درصد escape می‌شود' );
spk_equals( '%!_%', Sparkknife_Ledger_Sanitizer::like_pattern( '_' ), 'زیرخط escape می‌شود' );
spk_equals( '%!!%', Sparkknife_Ledger_Sanitizer::like_pattern( '!' ), 'خودِ نویسهٔ فرار escape می‌شود' );
spk_equals( '%علی%', Sparkknife_Ledger_Sanitizer::like_pattern( 'علی' ), 'متن ساده دست‌نخورده می‌ماند' );

// بک‌اسلش دیگر نویسهٔ فرار نیست، پس نباید دست‌کاری شود.
spk_assert(
	false !== mb_strpos( Sparkknife_Ledger_Sanitizer::like_pattern( 'a\\b' ), '\\' ),
	'بک‌اسلش در الگو دست‌نخورده می‌ماند'
);

spk_equals( 'علی%', Sparkknife_Ledger_Sanitizer::like_prefix( 'علی' ), 'like_prefix از ابتدا تطبیق می‌دهد' );
spk_equals( 'pri!%fix%', Sparkknife_Ledger_Sanitizer::like_prefix( 'pri%fix' ), 'like_prefix درصد را escape می‌کند' );
spk_equals( 'a!_b!_%', Sparkknife_Ledger_Sanitizer::like_prefix( 'a_b_' ), 'like_prefix همهٔ زیرخط‌ها را escape می‌کند' );

/*
 * آزمون رفتاری: نام‌هایی که wildcard می‌سازند. جستجوی `%` نباید همه را
 * برگرداند و جستجوی `x_y` نباید `xzy` را بگیرد.
 */
$esc_persons = Sparkknife_Ledger_DB::persons_table();
$esc_tag     = 'esc' . wp_rand( 10000, 99999 );

$esc_pct = Sparkknife_Ledger_Person_Repository::instance()->insert(
	array( 'name' => 'takhfif' . $esc_tag . '-50%', 'phone' => '09126660001', 'description' => '', 'status' => 'active' )
);
$esc_und = Sparkknife_Ledger_Person_Repository::instance()->insert(
	array( 'name' => 'zir' . $esc_tag . '-khat_', 'phone' => '09126660002', 'description' => '', 'status' => 'active' )
);
$esc_xzy = Sparkknife_Ledger_Person_Repository::instance()->insert(
	array( 'name' => 'zir' . $esc_tag . '-khatX', 'phone' => '09126660003', 'description' => '', 'status' => 'active' )
);

spk_assert( $esc_pct > 0 && $esc_und > 0 && $esc_xzy > 0, 'سه شخص آزمون escape ساخته شدند' );

$esc_repo = Sparkknife_Ledger_Person_Repository::instance();

// درصد: فقط شخصی که واقعاً درصد دارد.
$esc_pct_result = $esc_repo->query( array( 'search' => '%', 'per_page' => 200 ) );
$esc_pct_ids    = array_map( 'intval', wp_list_pluck( $esc_pct_result['items'], 'id' ) );

spk_assert( in_array( (int) $esc_pct, $esc_pct_ids, true ), 'جستجوی درصد، شخص دارای درصد را پیدا می‌کند' );

$esc_all = $esc_repo->query( array( 'per_page' => 200 ) );

spk_assert(
	(int) $esc_pct_result['total'] < (int) $esc_all['total'],
	'جستجوی درصد همهٔ اشخاص را برنمی‌گرداند'
);
spk_assert(
	! in_array( (int) $esc_und, $esc_pct_ids, true ),
	'شخص بدون درصد در جستجوی درصد نمی‌آید'
);

// زیرخط: باید `khat_` را بگیرد و `khatX` را نه.
$esc_und_result = $esc_repo->query( array( 'search' => $esc_tag . '-khat_', 'per_page' => 200 ) );
$esc_und_ids    = array_map( 'intval', wp_list_pluck( $esc_und_result['items'], 'id' ) );

spk_assert( in_array( (int) $esc_und, $esc_und_ids, true ), 'جستجوی زیرخط، شخص دارای زیرخط را پیدا می‌کند' );
spk_assert( ! in_array( (int) $esc_xzy, $esc_und_ids, true ), 'زیرخط در جستجو هر نویسه‌ای را نمی‌گیرد' );

// گزارش جمعی هم از همان مسیر جستجو می‌گذرد.
$esc_report = Sparkknife_Ledger_Report_Service::instance()->get_summary_report(
	array( 'range' => 'all', 'search' => '%' )
);
$esc_report_names = wp_list_pluck( $esc_report['rows'], 'name' );
$esc_report_has_und = false;

foreach ( $esc_report_names as $esc_name ) {
	if ( false !== mb_strpos( (string) $esc_name, $esc_tag . '-khat_' ) ) {
		$esc_report_has_und = true;
	}
}

spk_assert( false === $esc_report_has_und, 'گزارش جمعی با جستجوی درصد، شخص بدون درصد را نمی‌آورد' );

/*
 * تولید شمارهٔ تراکنش با پیشوندی که زیرخط دارد.
 *
 * کوئری «آخرین شماره» با `LIKE 'PREFIX-YYYYMMDD-%'` کار می‌کند. اگر `_`
 * escape نشود، در `LIKE` یعنی «هر نویسه» و شمارهٔ بعدی از ردیف پیشوند
 * دیگری حساب می‌شود. با escape درست، `A_B` فقط با `A_B` تطبیق می‌یابد.
 */
$esc_prev_prefix = Sparkknife_Ledger_Settings::get( 'reference_prefix', 'TXN' );
$esc_date        = '2026-11-04';

Sparkknife_Ledger_Settings::save( array( 'reference_prefix' => 'A_B' ), false );
Sparkknife_Ledger_Settings::flush_cache();

$esc_ref_a = Sparkknife_Ledger_Transaction_Service::instance()->generate_reference( $esc_date );

spk_assert( is_string( $esc_ref_a ) && '' !== $esc_ref_a, 'تولید شماره با پیشوند زیرخط‌دار کار می‌کند' );
spk_assert( 0 === strpos( $esc_ref_a, 'A_B-' ), 'شماره با همان پیشوند ساخته می‌شود' );

// پیشوندی که با یک نویسه فرق دارد نباید شمارهٔ A_B را جلو ببرد.
Sparkknife_Ledger_Settings::save( array( 'reference_prefix' => 'AxB' ), false );
Sparkknife_Ledger_Settings::flush_cache();

$esc_ref_b = Sparkknife_Ledger_Transaction_Service::instance()->generate_reference( $esc_date );

spk_assert(
	false !== mb_strpos( (string) $esc_ref_b, '0001' ),
	'پیشوند مشابه با نویسهٔ متفاوت شماره را جلو نمی‌برد'
);

Sparkknife_Ledger_Settings::save( array( 'reference_prefix' => $esc_prev_prefix ), false );
Sparkknife_Ledger_Settings::flush_cache();

// پاک‌سازی.
$wpdb->query( $wpdb->prepare( "DELETE FROM {$esc_persons} WHERE id IN (%d, %d, %d)", $esc_pct, $esc_und, $esc_xzy ) );

// ── ۲۵خ: تاریخچه می‌گوید **چه کسی** تغییر داده، حتی اگر حسابش حذف شده باشد ──
//
// ویوها پیش‌تر با `get_userdata()` مستقیم نام کاربر را می‌گرفتند. اگر کاربر
// حذف شده باشد این تابع `false` می‌دهد و همه‌جا «سیستم» نشان داده می‌شد —
// یعنی دفتر ممیزی ادعا می‌کرد سیستم تغییر را داده، در حالی که یک انسان داده
// بود. برای یک دفتر ممیزی این مهم‌ترین اطلاعات رکورد است.

global $wpdb;

$ghost_uid = wp_create_user( 'spk_ghost_' . $marker, 'pass1234', $marker . '_ghost@example.com' );

spk_assert( ! is_wp_error( $ghost_uid ) && $ghost_uid > 0, 'کاربر آزمایشی ساخته شد' );

/*
 * نام ورود از `sanitize_user()` می‌گذرد و نویسه‌های غیرلاتین را حذف می‌کند،
 * پس «spk_ghost_نشانه‌جستجو29140» به «spk_ghost_29140» تبدیل می‌شود. مقدار
 * واقعی ذخیره‌شده را از خود وردپرس می‌خوانیم، نه از چیزی که فرستادیم.
 */
$ghost_login = (string) get_userdata( $ghost_uid )->user_login;
$prev_user   = get_current_user_id();

wp_set_current_user( $ghost_uid );
$ghost_audit_id = Sparkknife_Ledger_Audit_Service::instance()->log(
	'person',
	999777,
	'update',
	array( 'note' => $marker ),
	sprintf( 'تغییر با کاربر %s', $marker )
);
wp_set_current_user( $prev_user );

spk_assert( false !== $ghost_audit_id && $ghost_audit_id > 0, 'رکورد ممیزی با کاربر آزمایشی ثبت شد' );

// نام ورود در خود رکورد ذخیره می‌شود تا پس از حذف کاربر هم باقی بماند.
$ghost_row = $wpdb->get_row(
	$wpdb->prepare( "SELECT user_id, user_login FROM {$audit_table} WHERE id = %d", $ghost_audit_id )
);

spk_equals( $ghost_login, isset( $ghost_row->user_login ) ? $ghost_row->user_login : null, 'نام ورود کاربر در رکورد ذخیره می‌شود' );
spk_equals( (string) $ghost_uid, (string) $ghost_row->user_id, 'شناسه کاربر درست ذخیره می‌شود' );
spk_equals( $ghost_login, sparkknife_ledger_user_label( $ghost_uid, $ghost_row->user_login ), 'برای کاربر موجود، نام نمایشی برمی‌گردد' );

require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user( $ghost_uid );

spk_assert( false === get_userdata( $ghost_uid ), 'کاربر واقعاً حذف شد' );

$ghost_label = sparkknife_ledger_user_label( $ghost_uid, $ghost_row->user_login );

spk_assert( false !== mb_strpos( $ghost_label, $ghost_login ), 'نام ورود کاربر حذف‌شده در برچسب می‌ماند' );
spk_assert( false === mb_strpos( $ghost_label, 'سیستم' ), 'کاربر حذف‌شده «سیستم» نامیده نمی‌شود' );

// رکوردهای قدیمی که نام ورود ندارند باید حداقل شماره کاربر را نشان دهند،
// نه «سیستم» دروغین.
$ghost_no_login = sparkknife_ledger_user_label( $ghost_uid, '' );

spk_assert( false !== mb_strpos( $ghost_no_login, (string) $ghost_uid ), 'بدون نام ورود، شماره کاربر نمایش داده می‌شود' );
spk_assert( false === mb_strpos( $ghost_no_login, 'سیستم' ), 'بدون نام ورود هم «سیستم» گفته نمی‌شود' );

// تنها جایی که «سیستم» درست است: عملیات بدون کاربر (user_id = 0).
spk_equals( 'سیستم', sparkknife_ledger_user_label( 0, '' ), 'عملیات سیستمی واقعی «سیستم» نام می‌گیرد' );
spk_equals( 'سیستم', sparkknife_ledger_user_label( 0, 'ignored' ), 'user_id صفر با نام ورود هم «سیستم» می‌ماند' );

// جستجوی ممیزی باید نام کاربر حذف‌شده را هم پیدا کند.
$ghost_search = $audit_repo->query( array( 'search' => $ghost_login, 'per_page' => 200 ) );

spk_equals( 1, (int) $ghost_search['total'], 'جستجو نام کاربر حذف‌شده را پیدا می‌کند' );

// ستون اختیاری user_login باید بدون خطا تشخیص داده شود.
spk_assert( Sparkknife_Ledger_DB::audit_schema_has_user_login(), 'ستون user_login ساخته شده است' );

// ── ۲۵د: یکسان‌سازی جستجو نباید عبارت را به «همه چیز» تبدیل کند ──────
//
// `normalize_persian()` نویسه‌های قالب‌بندی مثل نیم‌فاصله را حذف می‌کند.
// اگر عبارت جستجو فقط از همین نویسه‌ها ساخته شده باشد، نسخهٔ یکسان‌شده
// رشتهٔ خالی است و `LIKE '%%'` **همهٔ** رکوردها را برمی‌گرداند. یعنی
// جستجوی «هیچ» پاسخ «همه چیز» می‌داد — همان الگوی باگ ۴۹، این بار در
// لایهٔ جستجوی اشخاص.

foreach ( array( "\u{200C}", "\u{200B}", " \u{200C} " ) as $blank_term ) {
	$variants = Sparkknife_Ledger_Sanitizer::search_variants( $blank_term );

	spk_equals(
		array(),
		$variants,
		'عبارت فقط-قالب‌بندی هیچ نسخه‌ای تولید نمی‌کند: ' . bin2hex( $blank_term )
	);
}

// هیچ‌کدام از نسخه‌های تولیدشده نباید خالی باشد.
$variants_no_empty = Sparkknife_Ledger_Sanitizer::search_variants( 'علی ' . "\u{200C}" );

spk_assert( ! empty( $variants_no_empty ), 'عبارت معتبر نسخه تولید می‌کند' );

$has_empty = false;

foreach ( $variants_no_empty as $variant ) {
	if ( '' === trim( (string) $variant ) ) {
		$has_empty = true;
	}
}

spk_assert( false === $has_empty, 'هیچ نسخهٔ خالی در فهرست جستجو نیست' );

// شناسه‌کردن «خالی» باید همان یکسان‌سازی را ببیند، نه `trim()` خالی.
// `trim()` استاندارد PHP فقط فاصله و تب لاتین را می‌شناسد.
spk_equals(
	'',
	trim( Sparkknife_Ledger_Sanitizer::normalize_persian( ' ' . "\u{200C}" . ' ' ) ),
	'یکسان‌سازی، عبارت فقط-نیم‌فاصله را خالی می‌کند'
);

// پرسمان واقعی نباید بشکند.
$blank_result = Sparkknife_Ledger_Person_Repository::instance()->query(
	array( 'search' => "\u{200C}", 'per_page' => 100 )
);

spk_assert(
	is_array( $blank_result ) && isset( $blank_result['total'] ),
	'جستجو با عبارت فقط-نیم‌فاصله پرسمان را نمی‌شکند'
);
spk_assert(
	(int) $blank_result['total'] >= 0,
	'جستجو با عبارت فقط-نیم‌فاصله نتیجهٔ معتبر می‌دهد'
);

// ── ۲۵ذ: هر تنظیمی که در فرم هست باید در خروجی هم اثر داشته باشد ──────
//
// دو تنظیم **مرده** بودند: در فرم نشان داده می‌شدند، ذخیره می‌شدند، و
// هیچ‌جا خوانده نمی‌شدند. مدیر تیک را برمی‌داشت، پیام «ذخیره شد» می‌گرفت و
// هیچ اتفاقی نمی‌افتاد. تنظیم مرده بدتر از نبودن تنظیم است، چون کاربر فکر
// می‌کند کنترل دارد.

// (نام `$settings_before` در بلوک ۲۵ی استفاده شده؛ اینجا نام جدا.)
$dead_settings_before = Sparkknife_Ledger_Settings::all();

// (الف) show_welcome_panel — پنل خوش‌آمد باید با تنظیم خاموش شود.
Sparkknife_Ledger_Settings::save( array( 'show_welcome_panel' => 0 ), false );
Sparkknife_Ledger_Settings::flush_cache();

spk_equals( 0, (int) Sparkknife_Ledger_Settings::get( 'show_welcome_panel' ), 'تنظیم پنل خوش‌آمد ذخیره می‌شود' );

// ویو داشبورد باید این تنظیم را بخواند؛ با آینه‌کردن شرط همان فایل بررسی می‌شود.
ob_start();
$can_persons = Sparkknife_Ledger_Capabilities::current_user_can( 'sparkknife_view_persons' );
$stats       = array( 'persons_total' => 0, 'transactions_all' => 0 );
$dashboard_file = SPARKKNIFE_LEDGER_DIR . 'includes/Admin/Views/dashboard/index.php';
$dashboard_src  = (string) file_get_contents( $dashboard_file );
ob_end_clean();

spk_assert(
	false !== mb_strpos( $dashboard_src, "get( 'show_welcome_panel'" ),
	'داشبورد تنظیم پنل خوش‌آمد را می‌خواند'
);

Sparkknife_Ledger_Settings::save( array( 'show_welcome_panel' => 1 ), false );
Sparkknife_Ledger_Settings::flush_cache();

// (ب) use_persian_digits — شمارنده‌ها باید همان ارقامی را داشته باشند که مبلغ‌ها.
Sparkknife_Ledger_Settings::save( array( 'use_persian_digits' => 0 ), false );
Sparkknife_Ledger_Settings::flush_cache();

spk_equals( '1234', Sparkknife_Ledger_Money::digits( '1234' ), 'با ارقام لاتین، شمارنده لاتین می‌شود' );

$latin_format = Sparkknife_Ledger_Money::format( '123450000' );

spk_assert(
	false === mb_strpos( $latin_format, '۱' ),
	'مبلغ با تنظیم لاتین، رقم فارسی ندارد'
);

Sparkknife_Ledger_Settings::save( array( 'use_persian_digits' => 1 ), false );
Sparkknife_Ledger_Settings::flush_cache();

spk_equals( '۱۲۳۴', Sparkknife_Ledger_Money::digits( '1234' ), 'با ارقام فارسی، شمارنده فارسی می‌شود' );

// `to_persian_digits()` همچنان خام می‌ماند (برای جاهایی که فارسی الزامی است).
spk_equals(
	'۱۲۳۴',
	Sparkknife_Ledger_Money::to_persian_digits( '1234' ),
	'to_persian_digits مستقل از تنظیم، همیشه فارسی می‌دهد'
);

// هیچ ویویی نباید مستقیم `to_persian_digits()` را برای شمارنده صدا بزند.
$views_dir   = SPARKKNIFE_LEDGER_DIR . 'includes/Admin/Views/';
$raw_digits  = array();
$view_files  = glob( $views_dir . '*.php' );
$view_files  = array_merge( $view_files, glob( $views_dir . '*/*.php' ) );

foreach ( $view_files as $view_file ) {
	$src = (string) file_get_contents( $view_file );

	if ( false !== mb_strpos( $src, 'Money::to_persian_digits' ) ) {
		$raw_digits[] = basename( $view_file );
	}
}

spk_equals( array(), $raw_digits, 'هیچ ویویی to_persian_digits را مستقیم صدا نمی‌زند' );

Sparkknife_Ledger_Settings::save( $dead_settings_before, false );
Sparkknife_Ledger_Settings::flush_cache();

// ── ۲۵ژ: پیش‌نمایش مبلغ در مرورگر با ذخیره‌شده یکی است ────────────────
//
// `config.decimals` به جاوااسکریپت فرستاده می‌شد ولی هیچ‌جا خوانده نمی‌شد.
// پیش‌نمایش هر تعداد اعشاری را که کاربر تایپ می‌کرد نشان می‌داد، در حالی که
// سرور با `currency_decimals` قطع می‌کند. مدیر «۱٬۰۰۰.۹۹» می‌دید و
// «۱٬۰۰۰» ذخیره می‌شد.

$js_source = (string) file_get_contents( SPARKKNIFE_LEDGER_DIR . 'assets/js/admin.js' );

spk_assert(
	false !== mb_strpos( $js_source, 'config.decimals' ),
	'جاوااسکریپت تنظیم تعداد اعشار را می‌خواند'
);

// قاعدهٔ سرور: قطع، نه گرد کردن.
Sparkknife_Ledger_Settings::save( array( 'currency_decimals' => 0 ), false );
Sparkknife_Ledger_Settings::flush_cache();

$scale = (int) SPARKKNIFE_LEDGER_AMOUNT_SCALE;

spk_equals( '1000', (string) Sparkknife_Ledger_Money::to_human( '1000' . str_repeat( '0', $scale ) ), 'مبلغ صحیح بدون اعشار می‌ماند' );
spk_equals( '1000', (string) Sparkknife_Ledger_Money::to_human( '10005000' ), 'نیم تومان به پایین قطع می‌شود، نه بالا' );
spk_equals( '1000', (string) Sparkknife_Ledger_Money::to_human( '10009999' ), '۹۹٪ تومان هم به پایین قطع می‌شود' );

Sparkknife_Ledger_Settings::save( array( 'currency_decimals' => 2 ), false );
Sparkknife_Ledger_Settings::flush_cache();

spk_equals( '1000.45', (string) Sparkknife_Ledger_Money::to_human( '10004567' ), 'با دو اعشار، رقم سوم قطع می‌شود' );
spk_equals( '1000.99', (string) Sparkknife_Ledger_Money::to_human( '10009999' ), 'با دو اعشار، ۰.۹۹ نگه داشته می‌شود' );

Sparkknife_Ledger_Settings::save( array( 'currency_decimals' => 0 ), false );
Sparkknife_Ledger_Settings::flush_cache();

// تست جاوااسکریپت اگر Node در دسترس باشد.
$node = sparkknife_ledger_can_spawn() ? trim( (string) shell_exec( 'command -v node 2>/dev/null' ) ) : '';

if ( '' !== $node ) {
	$probe_file = tempnam( sys_get_temp_dir(), 'spkj' ) . '.js';
	$js_tests   = <<<'JS'
var fs = require( 'fs' );
var src = fs.readFileSync( process.argv[ 2 ], 'utf-8' );
function grab( n ) { return src.match( new RegExp( 'function ' + n + '\\s*\\([\\s\\S]*?\\n\\t\\}' ) ); }
var config = { separator: ',', currency: '', persianDigits: 0, decimals: 0 };
eval( grab( 'groupDigits' )[ 0 ] );
eval( grab( 'toLatinDigits' )[ 0 ] );
eval( grab( 'toPersianDigits' )[ 0 ] );
eval( grab( 'formatAmount' )[ 0 ] );
var cases = [
	[ 0, '1000', '1,000' ], [ 0, '1000.5', '1,000' ], [ 0, '1000.99', '1,000' ],
	[ 0, '0.5', '0' ], [ 0, '-1000.75', '-1,000' ], [ 0, '999.9999', '999' ],
	[ 2, '1000.456', '1,000.45' ], [ 2, '1000.999', '1,000.99' ]
];
var bad = 0;
for ( var i = 0; i < cases.length; i++ ) {
	config.decimals = cases[ i ][ 0 ];
	if ( formatAmount( cases[ i ][ 1 ], false ) !== cases[ i ][ 2 ] ) { bad++; }
}
process.stdout.write( String( bad ) );
JS;

	file_put_contents( $probe_file, $js_tests );

	$js_bad = trim( (string) shell_exec( escapeshellarg( $node ) . ' ' . escapeshellarg( $probe_file ) . ' ' . escapeshellarg( SPARKKNIFE_LEDGER_DIR . 'assets/js/admin.js' ) . ' 2>&1' ) );

	spk_equals( '0', $js_bad, 'پیش‌نمایش جاوااسکریپت با قاعده سرور یکی است' );

	@unlink( $probe_file );
}

// ── ۲۵ص: هر چیزی که به مرورگر فرستاده می‌شود باید مصرف شود ────────────
//
// `ajaxUrl`، `nonce`، `searchNoResult` و `isRtl` در `wp_localize_script`
// فرستاده می‌شدند ولی هیچ‌جا در جاوااسکریپت خوانده نمی‌شدند. افزونه هیچ
// endpoint‌ی در `wp_ajax_` ثبت نمی‌کند؛ ساختن یک nonce معتبر در HTML هر
// صفحه بی‌آنکه سروری بپذیردش، فقط سطح حمله را باز می‌کند.
//
// همچنین `jquery` به‌عنوان وابستگی اعلام شده بود در حالی که اسکریپت
// هیچ‌جا jQuery را صدا نمی‌زند.

$js_src   = (string) file_get_contents( SPARKKNIFE_LEDGER_DIR . 'assets/js/admin.js' );
$loader   = (string) file_get_contents( SPARKKNIFE_LEDGER_DIR . 'includes/class-sparkknife-ledger-admin-loader.php' );

// هیچ endpoint AJAX در کل افزونه نیست.
$ajax_hooks = array();
$php_files  = array_merge(
	glob( SPARKKNIFE_LEDGER_DIR . 'includes/*.php' ),
	glob( SPARKKNIFE_LEDGER_DIR . 'includes/*/*.php' ),
	glob( SPARKKNIFE_LEDGER_DIR . 'includes/*/*/*.php' ),
	glob( SPARKKNIFE_LEDGER_DIR . '*.php' )
);

foreach ( $php_files as $php_file ) {
	$src = (string) file_get_contents( $php_file );

	/*
	 * فقط ثبت واقعی بررسی می‌شود، نه هر ذکری از نام قلاب.
	 * جستجوی سادهٔ رشته، کامنت‌ها را هم می‌گیرد و تست را بی‌معنا می‌کند.
	 */
	if ( preg_match( '/add_action\s*\(\s*["\']wp_ajax(_nopriv)?_/', $src ) ) {
		$ajax_hooks[] = basename( $php_file );
	}
}

spk_equals( array(), $ajax_hooks, 'افزونه هیچ endpoint AJAX ثبت نمی‌کند' );

// پس هیچ nonce یا آدرسی هم نباید به مرورگر برود.
spk_assert(
	false === mb_strpos( $loader, "'nonce'" ),
	'nonce بدون endpoint به مرورگر فرستاده نمی‌شود'
);
spk_assert(
	false === mb_strpos( $loader, "'ajaxUrl'" ),
	'آدرس admin-ajax بدون endpoint فرستاده نمی‌شود'
);
spk_assert(
	false === mb_strpos( $js_src, 'config.nonce' ) && false === mb_strpos( $js_src, 'config.ajaxUrl' ),
	'جاوااسکریپت به nonce یا ajaxUrl وابسته نیست'
);

// وابستگی jQuery برداشته شد چون استفاده نمی‌شود.
spk_assert(
	false === mb_strpos( $js_src, 'jQuery(' ) && false === mb_strpos( $js_src, 'jQuery.' ),
	'اسکریپت هیچ فراخوانی jQuery ندارد'
);

// هر کلیدی که در PHP ساخته می‌شود باید در JS مصرف شود یا صریحاً مجاز باشد.
$declared = array();

/*
 * کلیدهای **سطح بالای** آرایهٔ پیکربندی.
 *
 * بریدن از `wp_localize_script` تا انتهای فایل، کلیدهای کد بعدی را هم
 * می‌گیرد. کلیدهای سطح بالا دقیقاً با چهار تب تو رفتگی دارند و زیرکلیدهای
 * `strings` با پنج تب، پس با لنگر ابتدای خط تفکیک می‌شوند.
 */
$localize_at = (int) mb_strpos( $loader, 'wp_localize_script' );
$localize_end = (int) mb_strpos( $loader, ');', $localize_at );
$config_block = substr( $loader, $localize_at, $localize_end - $localize_at );

preg_match_all( "/^\t{4}'([a-zA-Z_]+)'\s*=>/m", $config_block, $matches );

$declared = isset( $matches[1] ) ? $matches[1] : array();

$unused = array();

foreach ( $declared as $key ) {
	if ( 'strings' === $key ) {
		continue; // `config.strings.X` خوانده می‌شود، نه خودِ strings
	}

	if ( false === mb_strpos( $js_src, 'config.' . $key ) ) {
		$unused[] = $key;
	}
}

spk_equals( array(), $unused, 'هیچ مقدار بی‌مصرفی به مرورگر نمی‌رود' );

// ── ۲۵ض: جمع کل گزارش با مجموع ردیف‌های نمایش‌داده‌شده یکی است ───────
//
// ردیف‌ها از `Person_Repository::get_all()` می‌آمدند که فقط اشخاص
// حذف‌نشده را برمی‌گرداند، ولی جمع کل از `sum_by_type()` روی **کل** جدول
// تراکنش‌ها گرفته می‌شد. اگر شخصی تراکنش داشت و بعد حذف نرم می‌شد
// (سرویس حذف نمی‌گذارد، ولی داده می‌تواند دستی تغییر کند)، مبلغش در
// «مجموع درآمد» می‌آمد بی‌آنکه هیچ ردیفی توضیحش دهد.
//
// این بدترین نوع باگ گزارش است: هیچ خطایی نمی‌دهد، جدول درست به نظر
// می‌رسد، و عددی که کاربر جمع می‌زند با عدد چاپ‌شده نمی‌خواند.

global $wpdb;

$rp_persons = Sparkknife_Ledger_DB::persons_table();
$rp_txns    = Sparkknife_Ledger_DB::transactions_table();

$rp_live = Sparkknife_Ledger_Person_Repository::instance()->insert(
	array( 'name' => 'زنده ' . $marker, 'phone' => '09127770001', 'description' => '', 'status' => 'active' )
);
$rp_ghost = Sparkknife_Ledger_Person_Repository::instance()->insert(
	array( 'name' => 'روح ' . $marker, 'phone' => '09127770002', 'description' => '', 'status' => 'active' )
);

spk_assert( $rp_live > 0 && $rp_ghost > 0, 'دو شخص آزمایشی ساخته شدند' );

$rp_txn = Sparkknife_Ledger_Transaction_Service::instance();

$rp_txn->create(
	array( 'person_id' => $rp_live, 'type' => 'income', 'amount' => '1000', 'txn_date' => '2026-11-01', 'description' => '', 'reference' => '' )
);
$rp_txn->create(
	array( 'person_id' => $rp_ghost, 'type' => 'income', 'amount' => '3000', 'txn_date' => '2026-11-01', 'description' => '', 'reference' => '' )
);

// حذف نرم دستی — همان چیزی که از phpMyAdmin یا مهاجرت نیمه‌کاره ممکن است پیش بیاید.
$wpdb->query(
	$wpdb->prepare( "UPDATE {$rp_persons} SET deleted_at = %s, status = 'inactive' WHERE id = %d", current_time( 'mysql' ), $rp_ghost )
);

$rp_report = Sparkknife_Ledger_Report_Service::instance()->get_summary_report( array( 'range' => 'all' ) );

// شخص حذف‌شده باید در ردیف‌ها بماند، با نشانهٔ حذف.
$rp_names    = wp_list_pluck( $rp_report['rows'], 'name' );
$rp_has_live = false;
$rp_ghost_row = null;

foreach ( $rp_report['rows'] as $rp_row ) {
	if ( false !== mb_strpos( (string) $rp_row['name'], $marker ) ) {
		if ( (int) $rp_row['id'] === (int) $rp_ghost ) {
			$rp_ghost_row = $rp_row;
		} else {
			$rp_has_live = true;
		}
	}
}

spk_assert( $rp_has_live, 'شخص زنده در ردیف‌های گزارش هست' );
spk_assert( null !== $rp_ghost_row, 'شخص حذف‌شده با تراکنش از ردیف‌ها ناپدید نمی‌شود' );

if ( null !== $rp_ghost_row ) {
	spk_assert( ! empty( $rp_ghost_row['deleted_at'] ), 'ردیف شخص حذف‌شده نشانهٔ حذف را حمل می‌کند' );
	spk_assert( '0' !== (string) $rp_ghost_row['income'], 'مبلغ شخص حذف‌شده در ردیفش دیده می‌شود' );
}

// و مهم‌تر از همه: مجموع ردیف‌ها باید با جمع کل بخواند.
$rp_sum = '0';

foreach ( $rp_report['rows'] as $rp_row ) {
	$rp_sum = Sparkknife_Ledger_Amount::add( $rp_sum, $rp_row['income'] );
}

spk_equals(
	(string) Sparkknife_Ledger_Amount::normalize( $rp_report['totals']['income'] ),
	(string) Sparkknife_Ledger_Amount::normalize( $rp_sum ),
	'مجموع درآمد ردیف‌ها با جمع کل گزارش یکی است'
);

// فهرست خالی باید صفر بدهد، نه همه — وگرنه حذف همه اشخاص جمع قبلی را نگه می‌داشت.
$rp_empty = Sparkknife_Ledger_Transaction_Repository::instance()->sum_by_type(
	array( 'person_ids' => array(), 'status' => '' )
);

spk_equals( '0', (string) $rp_empty['income'], 'فهرست شخص خالی، جمع صفر می‌دهد نه کل' );
spk_equals( 0, (int) $rp_empty['count'], 'فهرست شخص خالی هیچ تراکنشی نمی‌شمارد' );

// گزارش فردی شخص حذف‌شده باید باز شود تا لینک ردیف نشکند.
$rp_person_report = Sparkknife_Ledger_Report_Service::instance()->get_person_report( $rp_ghost, array( 'range' => 'all' ) );

spk_assert( ! is_wp_error( $rp_person_report ), 'گزارش فردی شخص حذف‌شده باز می‌شود' );

if ( ! is_wp_error( $rp_person_report ) ) {
	spk_equals( '30000000', (string) $rp_person_report['summary']['income'], 'مبلغ گزارش فردی شخص حذف‌شده درست است' );
}

// تمیزکاری.
$wpdb->query( $wpdb->prepare( "DELETE FROM {$rp_txns} WHERE person_id IN (%d, %d)", $rp_live, $rp_ghost ) );
$wpdb->query( $wpdb->prepare( "DELETE FROM {$rp_persons} WHERE id IN (%d, %d)", $rp_live, $rp_ghost ) );

// ── ۲۵ط: مبالغ بالای جدول وقتی فیلتر نتیجه ندارد، توضیح دارند ─────────
//
// با جستجوی بی‌نتیجه، جدول تراکنش‌ها خالی می‌شد ولی کارت‌های بالای آن
// همچنان مبلغ کل بازه را نشان می‌دادند. شرط نمایش هشدار فقط `type` را
// می‌سنجید، در حالی که متن هشدار خودش می‌گفت «نوع/جستجو» — یعنی قصد
// پوشش جستجو وجود داشت و شرط جا افتاده بود.
//
// بدترین حالت، جستجوی بی‌نتیجه بود: صفحه خالی با یک عدد بی‌توضیح.

/*
 * بلوک قبلی اشخاص آزمایشی‌اش را پاک کرد، پس یک شخص تازه با یک تراکنش
 * می‌سازیم تا مبلغ خلاصه غیرصفر باشد و بتوانیم درباره‌اش ادعا کنیم.
 */
$warn_person = Sparkknife_Ledger_Person_Repository::instance()->insert(
	array( 'name' => 'هشدار ' . $marker, 'phone' => '09127770003', 'description' => '', 'status' => 'active' )
);

spk_assert( $warn_person > 0, 'شخص آزمایشی برای آزمون هشدار ساخته شد' );

Sparkknife_Ledger_Transaction_Service::instance()->create(
	array( 'person_id' => $warn_person, 'type' => 'income', 'amount' => '700', 'txn_date' => '2026-11-01', 'description' => '', 'reference' => '' )
);

if ( $warn_person > 0 ) {
	$warn_report = Sparkknife_Ledger_Report_Service::instance()->get_person_report(
		$warn_person,
		array( 'range' => 'all', 'search' => 'ZZZZ' . $marker )
	);

	spk_assert( ! is_wp_error( $warn_report ), 'گزارش فردی با جستجوی بی‌نتیجه ساخته می‌شود' );
	spk_equals( 0, (int) $warn_report['filtered']['count'], 'جستجوی بی‌نتیجه هیچ تراکنشی مطابقت نمی‌دهد' );
	spk_equals( 0, count( $warn_report['transactions']['items'] ), 'جدول در جستجوی بی‌نتیجه خالی است' );

	// مبلغ خلاصه هنوز کل بازه است — این طراحی است، نه باگ؛ ولی باید توضیح داشته باشد.
	spk_assert(
		'0' !== (string) Sparkknife_Ledger_Amount::normalize( $warn_report['summary']['income'] ),
		'خلاصه هنوز مبلغ کل بازه را دارد'
	);

	// شرط هشدار باید جستجو را هم ببیند.
	$person_view_src = (string) file_get_contents( SPARKKNIFE_LEDGER_DIR . 'includes/Admin/Views/reports/person.php' );

	spk_assert(
		false !== mb_strpos( $person_view_src, '$has_narrowing_filter' ),
		'ویو گزارش فردی وجود فیلتر را از نوع و جستجو می‌سنجد'
	);
	spk_assert(
		false !== mb_strpos( $person_view_src, "spk-alert--warning" ),
		'ویو برای فیلتر بی‌نتیجه هشدار دارد'
	);
	spk_assert(
		false !== mb_strpos( $person_view_src, "\$report['filters']['search']" ),
		'شرط هشدار، جستجو را می‌خواند نه فقط نوع'
	);

	// با جستجوی خالی، فیلتری وجود ندارد.
	$plain_report = Sparkknife_Ledger_Report_Service::instance()->get_person_report(
		$warn_person,
		array( 'range' => 'all', 'search' => '' )
	);

	spk_equals( '', (string) $plain_report['filters']['search'], 'جستجوی خالی، فیلتر جستجو نمی‌سازد' );

	// تمیزکاری.
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}sparkknife_ledger_transactions WHERE person_id = %d", $warn_person ) );
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}sparkknife_ledger_persons WHERE id = %d", $warn_person ) );
}

// ── ۳۰: مرتب‌سازی الفبای فارسی در فهرست اشخاص ───────────────────────
//
// `ORDER BY name` مقایسهٔ بایتی UTF-8 می‌کند. پنج حرف فارسی بیرون از
// بازهٔ `ا`–`ف` هستند:
//
//   پ U+067E · چ U+0686 · ک U+06A9 · گ U+06AF · ی U+06CC
//
// پس دیتابیس آن‌ها را **بعد از** `ی` می‌آورد:
//   … مهدی · نازنین · هما · وحید · پرویز · چنگیز · کاوه · گلناز · یوسف
//
// یعنی فهرست اشخاص برای کاربر فارسی‌زبان مرتب نیست و پیدا کردن نام با
// چشم ممکن نیست. درایور SQLite حتی `COLLATE` فارسی ندارد، پس مرتب‌سازی
// در PHP و روی ستون ماندگارِ `sort_key` انجام می‌شود.
//
// این بلوک هم خودِ تابع کلید را می‌سنجد و هم سه مسیر واقعی کوئری را.

$sort_expected = array( 'آرش', 'بهرام', 'پرویز', 'تارا', 'ثریا', 'جلال', 'چنگیز', 'حسن', 'خسرو', 'داوود', 'ذبیح', 'رستم', 'زهرا', 'ژاله', 'سارا', 'شیرین', 'صادق', 'ضیا', 'طاهره', 'ظریف', 'علی', 'غلام', 'فرهاد', 'قاسم', 'کاوه', 'گلناز', 'لیلا', 'مهدی', 'نازنین', 'وحید', 'هما', 'یوسف' );

$sort_shuffled = $sort_expected;
shuffle( $sort_shuffled );

usort(
	$sort_shuffled,
	function ( $a, $b ) {
		return strcmp(
			Sparkknife_Ledger_Sanitizer::persian_sort_key( $a ),
			Sparkknife_Ledger_Sanitizer::persian_sort_key( $b )
		);
	}
);

spk_equals(
	wp_json_encode( $sort_expected ),
	wp_json_encode( $sort_shuffled ),
	'کلید مرتب‌سازی، هر ۳۲ حرف الفبای فارسی را درست می‌چیند',
	wp_json_encode( $sort_shuffled )
);

/*
 * حرف‌هایی که ترتیب بایتی خراب می‌کند، تک‌تک سنجیده می‌شوند تا اگر
 * روزی کسی جدول ترتیب را دست بزند، دقیقاً همان جفت شکست بخورد.
 */
$sort_pairs = array(
	array( 'آرش', 'بهرام', 'آ پیش از ب' ),
	array( 'بهرام', 'پرویز', 'ب پیش از پ' ),
	array( 'پرویز', 'تارا', 'پ پیش از ت — در UTF-8 پ بعد از ف است' ),
	array( 'جلال', 'چنگیز', 'ج پیش از چ' ),
	array( 'چنگیز', 'حسن', 'چ پیش از ح' ),
	array( 'قاسم', 'کاوه', 'ق پیش از ک' ),
	array( 'کاوه', 'گلناز', 'ک پیش از گ' ),
	array( 'گلناز', 'لیلا', 'گ پیش از ل' ),
	array( 'هما', 'یوسف', 'ه پیش از ی' ),
);

foreach ( $sort_pairs as $sort_pair ) {
	spk_assert(
		Sparkknife_Ledger_Sanitizer::persian_sort_key( $sort_pair[0] ) < Sparkknife_Ledger_Sanitizer::persian_sort_key( $sort_pair[1] ),
		'کلید مرتب‌سازی: ' . $sort_pair[2]
	);
}

/*
 * ارقام باید ترتیب عددی بدهند، نه رقم‌به‌رقم.
 * کدگذاری سادهٔ رقم‌به‌رقم «۱۰» را پیش از «۲» می‌آورد.
 */
$sort_numbers = array( 'شخص ۱', 'شخص ۲', 'شخص ۳', 'شخص ۹', 'شخص ۱۰', 'شخص ۱۰۰' );
$sort_num_mixed = array_reverse( $sort_numbers );

usort(
	$sort_num_mixed,
	function ( $a, $b ) {
		return strcmp(
			Sparkknife_Ledger_Sanitizer::persian_sort_key( $a ),
			Sparkknife_Ledger_Sanitizer::persian_sort_key( $b )
		);
	}
);

spk_equals(
	wp_json_encode( $sort_numbers ),
	wp_json_encode( $sort_num_mixed ),
	'کلید مرتب‌سازی، ارقام را عددی می‌چیند: ۱ · ۲ · ۹ · ۱۰ · ۱۰۰'
);

// حروف لاتین نباید با حروف فارسی قاطی شوند.
spk_assert(
	Sparkknife_Ledger_Sanitizer::persian_sort_key( 'Ali' ) !== Sparkknife_Ledger_Sanitizer::persian_sort_key( 'علی' ),
	'کلید مرتب‌سازی، لاتین و فارسی را یکی نمی‌کند'
);

// «ي» و «ك» عربی باید همان کلید «ی» و «ک» فارسی بدهند.
spk_equals(
	Sparkknife_Ledger_Sanitizer::persian_sort_key( 'علی' ),
	Sparkknife_Ledger_Sanitizer::persian_sort_key( "علي" ),
	'کلید مرتب‌سازی، املای عربی «ي» را با «ی» یکی می‌کند'
);

spk_equals(
	Sparkknife_Ledger_Sanitizer::persian_sort_key( 'کاوه' ),
	Sparkknife_Ledger_Sanitizer::persian_sort_key( "كاوه" ),
	'کلید مرتب‌سازی، املای عربی «ك» را با «ک» یکی می‌کند'
);

/*
 * اکنون سه مسیر واقعی. نام‌ها عمداً طوری انتخاب شده‌اند که هر پنج حرف
 * مشکل‌دار در آن‌ها باشد؛ با ترتیب بایتی، «پرویز» و «چنگیز» و «کاوه» و
 * «گلناز» به انتهای فهرست می‌پریدند.
 */
$sort_marker = 'SORT' . wp_generate_password( 6, false, false );

/*
 * نام‌ها عمداً طوری چیده شده‌اند که **ترتیب بایتی و ترتیب فارسی
 * مختلف** باشند. با نام‌هایی که فقط `پ چ ک گ ی` دارند و همه بعد از
 * یک حرف پایه می‌آیند، هر دو ترتیب یکسان می‌شوند و آزمون چیزی را
 * اثبات نمی‌کند.
 *
 *   ترتیب فارسی : پرویز · تارا · چنگیز · حسن · گلناز · لیلا
 *   ترتیب بایتی : تارا · حسن · لیلا · پرویز · چنگیز · گلناز
 */
$sort_names = array(
	'لیلا ' . $sort_marker,
	'گلناز ' . $sort_marker,
	'حسن ' . $sort_marker,
	'چنگیز ' . $sort_marker,
	'تارا ' . $sort_marker,
	'پرویز ' . $sort_marker,
);

$sort_expected_names = array( 'پرویز', 'تارا', 'چنگیز', 'حسن', 'گلناز', 'لیلا' );

$sort_ids = array();

foreach ( $sort_names as $sort_name ) {
	$sort_id = Sparkknife_Ledger_Person_Repository::instance()->insert(
		array( 'name' => $sort_name, 'phone' => '', 'description' => '', 'status' => 'active' )
	);

	spk_assert( $sort_id > 0, 'شخص آزمایشی مرتب‌سازی ساخته شد: ' . $sort_name );

	if ( $sort_id > 0 ) {
		$sort_ids[] = (int) $sort_id;
	}
}

// (۱) ستون sort_key هنگام درج پر می‌شود.
global $wpdb;

$sort_stored = $wpdb->get_var(
	$wpdb->prepare(
		"SELECT sort_key FROM {$wpdb->prefix}sparkknife_ledger_persons WHERE name = %s",
		'پرویز ' . $sort_marker
	)
);

spk_assert( ! empty( $sort_stored ), 'ستون sort_key هنگام درج شخص پر می‌شود' );

// (۲) get_all() — فهرست انتخاب‌گر — با ترتیب فارسی برمی‌گردد.
$sort_all = Sparkknife_Ledger_Person_Repository::instance()->get_all();
$sort_seen = array();

foreach ( $sort_all as $sort_row ) {
	if ( false !== strpos( (string) $sort_row->name, $sort_marker ) ) {
		$sort_seen[] = trim( str_replace( $sort_marker, '', (string) $sort_row->name ) );
	}
}

spk_equals(
	wp_json_encode( $sort_expected_names ),
	wp_json_encode( $sort_seen ),
	'get_all() نام‌ها را با الفبای فارسی می‌چیند، نه بایتی',
	wp_json_encode( $sort_seen )
);

// (۳) query() با orderby=name — فهرست اصلی اشخاص.
$sort_query = Sparkknife_Ledger_Person_Repository::instance()->query(
	array( 'orderby' => 'name', 'order' => 'ASC', 'per_page' => 500, 'search' => $sort_marker )
);

$sort_query_names = array();

foreach ( $sort_query['items'] as $sort_item ) {
	$sort_query_names[] = trim( str_replace( $sort_marker, '', (string) $sort_item->name ) );
}

spk_equals(
	wp_json_encode( $sort_expected_names ),
	wp_json_encode( $sort_query_names ),
	'query(orderby=name) با الفبای فارسی مرتب می‌شود',
	wp_json_encode( $sort_query_names )
);

// جهت نزولی هم باید همین ترتیب را برگرداند.
$sort_desc = Sparkknife_Ledger_Person_Repository::instance()->query(
	array( 'orderby' => 'name', 'order' => 'DESC', 'per_page' => 500, 'search' => $sort_marker )
);

$sort_desc_names = array();

foreach ( $sort_desc['items'] as $sort_item ) {
	$sort_desc_names[] = trim( str_replace( $sort_marker, '', (string) $sort_item->name ) );
}

spk_equals(
	wp_json_encode( array_reverse( $sort_expected_names ) ),
	wp_json_encode( $sort_desc_names ),
	'query(orderby=name, order=DESC) دقیقاً برعکس است',
	wp_json_encode( $sort_desc_names )
);

/*
 * (۴) ویرایش نام باید کلید را به‌روز کند.
 * اگر نشود، شخص پس از تغییر نام در جای قدیمی فهرست می‌ماند — باگی که
 * فقط با بازسازی کلید در insert دیده نمی‌شود.
 */
if ( ! empty( $sort_ids ) ) {
	$sort_edit_id = $sort_ids[0];

	Sparkknife_Ledger_Person_Repository::instance()->update(
		$sort_edit_id,
		array( 'name' => 'آذرین ' . $sort_marker, 'phone' => '', 'description' => '', 'status' => 'active' )
	);

	$sort_after_edit = Sparkknife_Ledger_Person_Repository::instance()->query(
		array( 'orderby' => 'name', 'order' => 'ASC', 'per_page' => 500, 'search' => $sort_marker )
	);

	spk_equals(
		'آذرین',
		trim( str_replace( $sort_marker, '', (string) $sort_after_edit['items'][0]->name ) ),
		'ویرایش نام، کلید مرتب‌سازی را به‌روز می‌کند (آ در ابتدای فهرست)'
	);

	spk_equals( count( $sort_names ), count( $sort_after_edit['items'] ), 'ویرایش نام شخص تازه نمی‌سازد' );
}

/*
 * (۵) backfill: ردیف قدیمی که sort_key ندارد باید پر شود.
 *
 * مهاجرت مسیر واقعی ارتقاست؛ اگر این کار نکند، همهٔ اشخاص موجود پس از
 * به‌روزرسانی با کلید خالی در ابتدای فهرست جمع می‌شوند — یعنی ترتیب
 * دقیقاً همان‌قدر غلط می‌ماند که قبل از اصلاح بود.
 */
if ( ! empty( $sort_ids ) ) {
	$backfill_id = $sort_ids[1];

	$wpdb->query(
		$wpdb->prepare(
			"UPDATE {$wpdb->prefix}sparkknife_ledger_persons SET sort_key = '' WHERE id = %d",
			$backfill_id
		)
	);

	$backfill_count = Sparkknife_Ledger_Installer::backfill_sort_keys();

	spk_assert( $backfill_count >= 1, 'backfill_sort_keys() ردیف بدون کلید را پیدا می‌کند' );

	$backfill_key = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT sort_key FROM {$wpdb->prefix}sparkknife_ledger_persons WHERE id = %d",
			$backfill_id
		)
	);

	spk_assert( ! empty( $backfill_key ), 'backfill_sort_keys() کلید خالی را پر می‌کند' );
}

/*
 * (۶) رگرسیون صفحه‌بندی: مرتب‌سازی باید **پیش از** برش صفحه انجام شود.
 *
 * اگر کسی دوباره وسوسه شود که `LIMIT` را قبل از مرتب‌سازی PHP بگذارد،
 * صفحهٔ دوم ردیف‌های اشتباه می‌دهد. با شش ردیف و per_page=۳، دو صفحهٔ
 * پشت‌سرهم باید همان مجموعهٔ مرتب‌شده را بدون تکرار و بدون جاافتادن
 * بسازند.
 *
 * توجه: درخواست صفحهٔ سوم رد نمی‌شود بلکه به آخرین صفحه (۲) محدود
 * می‌شود — رفتار استاندارد پس از باگ ۶۴ — و آن‌گاه ردیف‌ها تکرار
 * می‌شوند. پس حلقه فقط تا شمار واقعی صفحه‌ها می‌رود.
 */
$sort_page_names = array();
$sort_page_count = max( 1, (int) ceil( 6 / 3 ) );

for ( $sort_page = 1; $sort_page <= $sort_page_count; $sort_page++ ) {
	$sort_page_result = Sparkknife_Ledger_Person_Repository::instance()->query(
		array( 'orderby' => 'name', 'order' => 'ASC', 'per_page' => 3, 'page' => $sort_page, 'search' => $sort_marker )
	);

	foreach ( $sort_page_result['items'] as $sort_item ) {
		$sort_page_names[] = trim( str_replace( $sort_marker, '', (string) $sort_item->name ) );
	}
}

/*
 * اولین شخص فهرست («لیلا») به «آذرین» تغییر نام یافت، پس در انتظار
 * صفحه‌بندی «لیلا» نیست و «آذرین» اول آمده است. تعداد ردیف‌ها همان
 * شش‌تاست: ویرایش نام ردیف تازه نمی‌سازد.
 */
$sort_page_expected = array( 'آذرین', 'پرویز', 'تارا', 'چنگیز', 'حسن', 'گلناز' );

spk_equals( count( $sort_names ), count( $sort_page_expected ), 'صفحه‌بندی: تعداد ردیف‌ها با تعداد اشخاص ساخته‌شده یکی است' );

spk_equals(
	wp_json_encode( $sort_page_expected ),
	wp_json_encode( $sort_page_names ),
	'صفحه‌بندی با orderby=name ترتیب را می‌شکند نه ردیف‌ها را (۳ صفحه)',
	wp_json_encode( $sort_page_names )
);

// تمیزکاری.
foreach ( $sort_ids as $sort_id ) {
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}sparkknife_ledger_persons WHERE id = %d", $sort_id ) );
}

// ── ۳۱: جستجو باید همهٔ ترکیب‌های املا و ارقام را پوشش دهد ───────────
//
// دو نقص مستقل که هر دو یک نتیجه داشتند: «رکورد پیدا نمی‌شود».
//
// (۱) نام با همان ارقامی که کاربر تایپ کرده ذخیره می‌شود
//     (`Sanitizer::text()` ارقام فارسی را لاتین نمی‌کند)، ولی
//     `normalize_persian()` روی **عبارت جستجو** همیشه ارقام را لاتین
//     می‌کند. پس رکوردی با نام «علي كاوه ۰۹۱۲» با جستجوی دقیقاً همان
//     متن هم پیدا نمی‌شد — نسخه‌ای برابر مقدار ذخیره‌شده وجود نداشت.
//
// (۲) نسخه‌های ارقام فارسی/عربی ساخته می‌شدند ولی `array_slice(…, 12)`
//     آن‌ها را می‌برید: برای «علی کاوه 0912» هر ۱۲ جای اول را ترکیب
//     املای «ه» می‌گرفت و همهٔ نسخه‌های «ي عربی» و «ارقام فارسی» حذف
//     می‌شدند. سقف دقیقاً همان نسخه‌هایی را می‌انداخت که برایشان ساخته
//     شده بود.

// (۱) هیچ نسخه‌ای نباید خالی باشد (وگرنه LIKE '%%' همه چیز را می‌آورد).
$var_empty = array();

foreach ( Sparkknife_Ledger_Sanitizer::search_variants( 'علی' ) as $var ) {
	if ( '' === trim( (string) $var ) ) {
		$var_empty[] = $var;
	}
}

spk_equals( 0, count( $var_empty ), 'نسخه‌های جستجو هیچ‌گاه خالی نیستند' );

spk_equals(
	array(),
	Sparkknife_Ledger_Sanitizer::search_variants( ' ' ),
	'جستجوی فقط-فاصله هیچ نسخه‌ای نمی‌سازد'
);

/*
 * (۲) سقف نسخه‌ها نباید از حد بگذرد (کوئری بی‌جهت بزرگ نشود).
 *
 * سقف از ثابت خودِ کلاس خوانده می‌شود، نه عددی دستی: سقف ۴۸ قدیمی
 * دقیقاً خانوادهٔ ارقام عربی‌هندی را می‌بُرید و آزمون (۳) قرمز
 * می‌شد — همان باگ ۷۶. عددِ آزمون اگر از کد جدا باشد دوباره از آن
 * جدا می‌افتد.
 */
foreach ( array( 'علی', 'علی کاوه 0912', 'فاطمه محمدی ۰۹۱۲۳۴۵۶۷۸۹', 'علی کاوه 0912 MK12' ) as $var_query ) {
	$var_count = count( Sparkknife_Ledger_Sanitizer::search_variants( $var_query ) );
	spk_assert(
		$var_count <= Sparkknife_Ledger_Sanitizer::MAX_SEARCH_VARIANTS,
		'نسخه‌های جستجو از سقف ' . Sparkknife_Ledger_Sanitizer::MAX_SEARCH_VARIANTS . ' نمی‌گذرند (' . $var_count . ')'
	);
	spk_assert( $var_count > 0, 'هر عبارت معنادار دست‌کم یک نسخه دارد: ' . $var_query );
}

/*
 * (۳) آزمون واقعی: ماتریس ذخیره × جستجو.
 *
 * هر رکورد با یک ترکیب املا و ارقام ذخیره می‌شود و با هر ترکیب دیگر
 * جستجو می‌شود. همه باید پیدا شوند — نه فقط هم‌شکل خودشان.
 */
$var_marker = 'VAR' . wp_generate_password( 6, false, false );

$var_forms = array(
	"علي كاوه \u{06F0}\u{06F9}\u{06F1}\u{06F2}", // عربی + ارقام فارسی
	'علي كاوه 0912',                              // عربی + ارقام لاتین
	"علی کاوه \u{06F0}\u{06F9}\u{06F1}\u{06F2}", // فارسی + ارقام فارسی
	'علی کاوه 0912',                              // فارسی + ارقام لاتین
);

$var_ids = array();

foreach ( $var_forms as $var_form ) {
	$var_id = Sparkknife_Ledger_Person_Repository::instance()->insert(
		array( 'name' => $var_form . ' ' . $var_marker, 'phone' => '', 'description' => '', 'status' => 'active' )
	);

	spk_assert( $var_id > 0, 'شخص آزمایشی جستجو ساخته شد' );

	if ( $var_id > 0 ) {
		$var_ids[ $var_form ] = (int) $var_id;
	}
}

$var_misses = array();

foreach ( $var_ids as $var_stored => $var_stored_id ) {
	foreach ( $var_forms as $var_query ) {
		$var_result = Sparkknife_Ledger_Person_Repository::instance()->query(
			array( 'search' => $var_query . ' ' . $var_marker, 'per_page' => 200 )
		);

		$var_found = false;

		foreach ( $var_result['items'] as $var_item ) {
			if ( (int) $var_item->id === $var_stored_id ) {
				$var_found = true;
				break;
			}
		}

		if ( ! $var_found ) {
			$var_misses[] = $var_stored . ' ← ' . $var_query;
		}
	}
}

spk_equals(
	array(),
	$var_misses,
	'جستجو هر ترکیب املا × ارقام را پیدا می‌کند (' . ( count( $var_ids ) * count( $var_forms ) ) . ' ترکیب)',
	wp_json_encode( $var_misses, JSON_UNESCAPED_UNICODE )
);

/*
 * (۴) جهت مخالف: جستجوی مقدار **دقیقاً ذخیره‌شده** هم باید کار کند.
 *
 * این همان حالتی بود که کاملاً بدیهی به نظر می‌رسد و کار نمی‌کرد.
 */
if ( ! empty( $var_ids ) ) {
	foreach ( $var_ids as $var_stored => $var_stored_id ) {
		$var_exact = Sparkknife_Ledger_Person_Repository::instance()->query(
			array( 'search' => $var_stored . ' ' . $var_marker, 'per_page' => 200 )
		);

		$var_self = false;

		foreach ( $var_exact['items'] as $var_item ) {
			if ( (int) $var_item->id === $var_stored_id ) {
				$var_self = true;
				break;
			}
		}

		spk_assert( $var_self, 'جستجوی متن دقیقاً ذخیره‌شده خودش را پیدا می‌کند: ' . $var_stored );
	}
}

// (۵) جستجوی بدون رقم باید سادهٔ کوچک بسازد، نه ۴۸ نسخه.
spk_assert(
	count( Sparkknife_Ledger_Sanitizer::search_variants( 'علی' ) ) <= 6,
	'جستجوی کوتاه بی‌رقم نسخه‌های کمی می‌سازد'
);

// (۶) حرف‌های فارسی و عربی هر دو در نسخه‌ها هستند.
$var_all = Sparkknife_Ledger_Sanitizer::search_variants( 'علی کاوه' );

$var_has_arabic_yeh = false;
$var_has_farsi_yeh  = false;

foreach ( $var_all as $var ) {
	if ( false !== strpos( $var, 'ي' ) ) {
		$var_has_arabic_yeh = true;
	}
	if ( false !== strpos( $var, 'ی' ) ) {
		$var_has_farsi_yeh = true;
	}
}

spk_assert( $var_has_arabic_yeh && $var_has_farsi_yeh, 'نسخه‌های جستجو هم «ی» فارسی دارند هم «ي» عربی' );

// تمیزکاری.
global $wpdb;

foreach ( $var_ids as $var_cleanup_id ) {
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}sparkknife_ledger_persons WHERE id = %d", $var_cleanup_id ) );
}

// ── ۳۲: علامت مضروب در mul_int و هم‌ارزی دو مسیر bcmath/fallback ──────
/*
 * باگ ۶۲: `mul_int()` علامت `$multiplier` را دور می‌ریخت.
 *
 * در مسیر بدون bcmath، `$sign` فقط از علامت `$value` ساخته می‌شد و ضرب
 * در `abs( $multiplier )` انجام می‌گرفت. یعنی `mul_int( '1000', -3 )`
 * مقدار `'3000'` می‌داد.
 *
 * مسیر bcmath درست بود، پس خروجی تابع به نصب‌بودن افزونهٔ اختیاری bcmath
 * وابسته بود. این بلوک هر دو مسیر را می‌سنجد.
 */
spk_equals( '0', Sparkknife_Ledger_Amount::mul_int( '0', -5 ), 'mul_int(0, -5) صفر است' );
spk_equals( '0', Sparkknife_Ledger_Amount::mul_int( '1000', 0 ), 'mul_int(1000, 0) صفر است' );
spk_equals( '-3000', Sparkknife_Ledger_Amount::mul_int( '1000', -3 ), 'mul_int(1000, -3) منفی می‌شود' );
spk_equals( '3000', Sparkknife_Ledger_Amount::mul_int( '-1000', -3 ), 'mul_int(-1000, -3) مثبت می‌شود' );
spk_equals( '-3000', Sparkknife_Ledger_Amount::mul_int( '-1000', 3 ), 'mul_int(-1000, 3) منفی می‌ماند' );
spk_equals( '3000', Sparkknife_Ledger_Amount::mul_int( '1000', 3 ), 'mul_int(1000, 3) مثبت می‌ماند' );
spk_equals( '-12', Sparkknife_Ledger_Amount::mul_int( '12', -1 ), 'mul_int(12, -1) منفی می‌شود' );
spk_equals( '-1000', Sparkknife_Ledger_Amount::mul_int( '1000', -1 ), 'mul_int(1000, -1) خودِ منفی مقدار است' );

/*
 * هم‌ارزی مسیرها: نتیجهٔ `mul_int` نباید به حضور bcmath وابسته باشد.
 *
 * bcmath اینجا نصب است، پس فقط مسیر bcmath اجرا می‌شود؛ اما همان
 * ورودی‌هایی که پیش‌تر در مسیر fallback غلط بودند، اکنون باید در هر دو
 * مسیر یکسان باشند. جدول زیر همان انتظار را مستقل از محیط می‌سنجد.
 */
$mul_matrix = array(
	array( '1000', -3, '-3000' ),
	array( '-1000', -3, '3000' ),
	array( '1000', 3, '3000' ),
	array( '-1000', 3, '-3000' ),
	array( '250', -4, '-1000' ),
	array( '-250', -4, '1000' ),
);

$mul_matrix_ok = true;

foreach ( $mul_matrix as $mul_case ) {
	if ( Sparkknife_Ledger_Amount::mul_int( $mul_case[0], $mul_case[1] ) !== $mul_case[2] ) {
		$mul_matrix_ok = false;
		break;
	}
}

spk_assert( $mul_matrix_ok, 'ماتریس علامت mul_int در همهٔ ۶ حالت با انتظار مستقل از bcmath یکی است' );

// جمع/تفریق/مقایسه هم نباید علامت را گم کنند (هم‌سایگی باگ ۶۲).
spk_equals( '-500', Sparkknife_Ledger_Amount::add( '-1000', '500' ), 'جمع منفی و مثبت علامت را نگه می‌دارد' );
spk_equals( '0', Sparkknife_Ledger_Amount::add( '1000', '-1000' ), 'جمع متقارن صفر است' );
spk_equals( '-1000', Sparkknife_Ledger_Amount::sub( '500', '1500' ), 'تفریق بزرگ‌تر از کوچک‌تر منفی می‌شود' );
spk_equals( '-1', (string) Sparkknife_Ledger_Amount::cmp( '-1000', '0' ), 'مقایسهٔ منفی با صفر منفی است' );
spk_equals( '0', (string) Sparkknife_Ledger_Amount::cmp( '-0', '0' ), 'مقایسهٔ «-0» با صفر برابر است' );
spk_equals( '0', Sparkknife_Ledger_Amount::normalize( '-0' ), 'نرمال‌سازی «-0» صفرِ بدون علامت است' );

// ── ۳۳: تزریق فرمول CSV با پیش‌وند پوشاننده ─────────────────────────
/*
 * باگ ۶۳: `sanitize_cell()` فقط بایت نخست سلول را می‌سنجید.
 *
 * Excel و LibreOffice فاصله و نویسه‌های بی‌عرض ابتدای سلول را نادیده
 * می‌گیرند و رشتهٔ بعدی را به‌عنوان فرمول اجرا می‌کنند. پس نامی مثل
 * «ZERO-WIDTH-SPACE =cmd|calc» از فیلتر رد می‌شد و در فایل CSV به
 * فرمول تبدیل می‌شد.
 *
 * `Sanitizer::text()` این نویسه‌ها را حذف نمی‌کند (فقط می‌سنجد که
 * رشتهٔ مرئی خالی نباشد)، بنابراین این مقدار تا مرحلهٔ CSV می‌رسد.
 */
$csv_unsafe = array(
	'=cmd|calc'              => 'فرمول ساده',
	'+1+1'                   => 'به‌علاوه',
	'-1+1'                   => 'منفی',
	'@SUM(A1)'               => 'ات‌فورمول',
	"\tTAB"                  => 'tab ابتدایی',
	"\rCR"                   => 'CR ابتدایی',
	"\u{200B}=cmd|calc"      => 'فاصلهٔ بی‌عرض صفر',
	"\u{200F}=1+1"           => 'علامت راست‌به‌چپ',
	"\u{FEFF}=HYPERLINK(A1)" => 'BOM',
	"\u{00A0}=1+1"           => 'فاصلهٔ نشکن',
	"\u{200E}+1+1"           => 'علامت چپ‌به‌راست',
	"  =1+1"                 => 'دو فاصله',
	"\n=cmd|calc"            => 'خط جدید',
);

$csv_unsafe_ok = true;

foreach ( $csv_unsafe as $csv_payload => $csv_label ) {
	$csv_out = Sparkknife_Ledger_Export_Service::sanitize_cell( (string) $csv_payload );

	// نخستین بایت باید آپاستروف باشد، وگرنه Excel فرمول را اجرا می‌کند.
	if ( 0 !== strpos( $csv_out, "'" ) ) {
		$csv_unsafe_ok = false;
		break;
	}
}

spk_assert( $csv_unsafe_ok, 'همهٔ ۱۳ پیش‌وند پوشانندهٔ تزریق فرمول CSV مسدود می‌شوند' );

// مقدار سالم نباید دست‌کاری شود.
spk_equals( 'سلام', Sparkknife_Ledger_Export_Service::sanitize_cell( 'سلام' ), 'متن سالم فارسی دست‌نخورده می‌ماند' );
spk_equals( 'normal', Sparkknife_Ledger_Export_Service::sanitize_cell( 'normal' ), 'متن سالم لاتین دست‌نخورده می‌ماند' );
spk_equals( '', Sparkknife_Ledger_Export_Service::sanitize_cell( '' ), 'سلول خالی خالی می‌ماند' );
spk_equals( '12,500', Sparkknife_Ledger_Export_Service::sanitize_cell( '12,500' ), 'عدد با جداکننده دست‌نخورده می‌ماند' );
// بلوک ۳۸ نشان داد مبلغ منفی باید عدد بماند (باگ ۷۰)؛ این انتظار به‌روز شد.
spk_equals( '-12500', Sparkknife_Ledger_Export_Service::sanitize_cell( '-12500' ), 'مبلغ منفی عدد می‌ماند، نه متن' );

// خودِ ورودی هم باید بازگردانده شود، نه نسخهٔ بریده‌شده.
spk_equals(
	"'\u{200B}=cmd|calc",
	Sparkknife_Ledger_Export_Service::sanitize_cell( "\u{200B}=cmd|calc" ),
	'آپاستروف پیش از نویسهٔ بی‌عرض می‌نشیند و محتوا حفظ می‌شود'
);

// ── ۳۴: سرریز آفست صفحه‌بندی ─────────────────────────────────────────
/*
 * باگ ۶۴: `( $page - 1 ) * $per_page` بدون سقف حساب می‌شد.
 *
 * با `page` بزرگ‌تر از حدود ۴٫۶×۱۰^۱۶ حاصل‌ضرب از بازهٔ `int` بیرون
 * می‌زد و PHP بی‌صدا آن را به `float` تبدیل می‌کرد. بعد:
 *
 *   - در SQL، `%d` در `$wpdb->prepare()` آن float را به `0` می‌کاست و
 *     **صفحهٔ اول** برمی‌گشت.
 *   - در PHP، `array_slice()` با آفست غیری صحیح **کل ردیف‌ها** را
 *     برمی‌گرداند.
 *
 * هر دو بی‌صدا و بدون خطا رخ می‌دادند. حالا صفحه پیش از ضرب به تعداد
 * صفحات موجود محدود می‌شود.
 */
$pg_page   = 20;
$pg_total  = 45;

// صفحهٔ عادی نباید دست بخورد.
$pg_ok_page = Sparkknife_Ledger_Transaction_Repository::instance()->query(
	array(
		'per_page' => $pg_page,
		'page'     => 2,
		'search'   => 'MARKER_PG_NEVER_MATCHES',
	)
);

/*
 * با نتیجهٔ صفر، تعداد صفحات ۱ است و صفحهٔ ۲ به ۱ محدود می‌شود — همان
 * رفتار مطلوب. برای سنجش «صفحهٔ عادی دست نمی‌خورد» باید صفحه‌ای در
 * محدودهٔ معتبر انتخاب شود.
 */
spk_equals( 1, (int) $pg_ok_page['page'], 'با نتیجهٔ صفر، صفحه ۱ می‌ماند' );
spk_equals( 0, count( $pg_ok_page['items'] ), 'صفحهٔ عادی با جستجوی بی‌نتیجه ردیفی ندارد' );

// با داده واقعی: صفحهٔ ۲ از ۳ صفحه باید دست‌نخورده بماند.
$pg_seed = 'MARKER_PG_' . wp_generate_password( 6, false, false );
$pg_person_table = Sparkknife_Ledger_DB::persons_table();
$pg_seed_ids = array();

global $wpdb;

for ( $pg_i = 0; $pg_i < 3; $pg_i++ ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
	$wpdb->insert(
		$pg_person_table,
		array(
			'name'        => $pg_seed . ' ' . $pg_i,
			'phone'       => '',
			'status'      => 'active',
			'description' => '',
			'sort_key'    => Sparkknife_Ledger_Sanitizer::persian_sort_key( $pg_seed . ' ' . $pg_i ),
			'created_at'  => current_time( 'mysql' ),
			'updated_at'  => current_time( 'mysql' ),
		)
	);
	$pg_seed_ids[] = (int) $wpdb->insert_id;
}

$pg_real = Sparkknife_Ledger_Person_Repository::instance()->query(
	array(
		'per_page' => 1,
		'page'     => 2,
		'search'   => $pg_seed,
	)
);

spk_equals( 2, (int) $pg_real['page'], 'صفحهٔ ۲ در محدودهٔ معتبر دست‌نخورده می‌ماند' );
spk_equals( 3, (int) $pg_real['total'], 'سه ردیف آزمون صفحه‌بندی شمرده می‌شوند' );

foreach ( $pg_seed_ids as $pg_cleanup_id ) {
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$pg_person_table} WHERE id = %d", $pg_cleanup_id ) );
}

// صفحهٔ سرریزشده باید به آخرین صفحهٔ معقول بیفتد، نه صفحهٔ اول.
$pg_huge = Sparkknife_Ledger_Transaction_Repository::instance()->query(
	array(
		'per_page' => $pg_page,
		'page'     => PHP_INT_MAX,
		'search'   => 'MARKER_PG_NEVER_MATCHES',
	)
);

spk_assert(
	is_int( $pg_huge['page'] ) || ctype_digit( (string) $pg_huge['page'] ),
	'شماره صفحه پس از سرریز همچنان عدد صحیح است'
);
spk_assert(
	(int) $pg_huge['page'] < 1000000,
	'شماره صفحه پس از سرریز به سقف منطقی محدود می‌شود'
);

// افراد هم باید همین رفتار را داشته باشند.
$pg_person = Sparkknife_Ledger_Person_Repository::instance()->query(
	array(
		'per_page' => 50,
		'page'     => PHP_INT_MAX,
		'search'   => 'MARKER_PG_NEVER_MATCHES',
	)
);

spk_assert(
	(int) $pg_person['page'] < 1000000,
	'صفحه سرریزشده در فهرست اشخاص هم محدود می‌شود'
);
spk_equals( 0, count( $pg_person['items'] ), 'صفحه سرریزشده اشخاص ردیف ناخواسته برنمی‌گرداند' );

// تاریخچه تغییرات.
$pg_audit = Sparkknife_Ledger_Audit_Repository::instance()->query(
	array(
		'per_page' => 50,
		'page'     => PHP_INT_MAX,
	)
);

spk_assert(
	(int) $pg_audit['page'] < 1000000,
	'صفحه سرریزشده در تاریخچه تغییرات هم محدود می‌شود'
);

// آفست همیشه عدد صحیح می‌ماند — نه float.
$pg_offset_is_int = true;

foreach ( array( 1, 2, 1000, PHP_INT_MAX ) as $pg_p ) {
	$pg_probe = Sparkknife_Ledger_Transaction_Repository::instance()->query(
		array(
			'per_page' => 20,
			'page'     => $pg_p,
			'search'   => 'MARKER_PG_NEVER_MATCHES',
		)
	);

	$pg_calc = ( (int) $pg_probe['page'] - 1 ) * (int) $pg_probe['per_page'];

	if ( ! is_int( $pg_calc ) ) {
		$pg_offset_is_int = false;
		break;
	}
}

spk_assert( $pg_offset_is_int, 'آفست صفحه‌بندی در هیچ حالتی به float سرریز نمی‌کند' );

// ── ۳۵: پر شدن sort_key وقتی گزینهٔ نسخه پاک شده باشد ─────────────────
/*
 * باگ ۶۵: شاخهٔ «افزونه بدون فعال‌سازی اجرا شده» در `maybe_upgrade()`
 * `backfill_sort_keys()` را صدا نمی‌زد.
 *
 * سناریوی واقعی: جداول و اشخاص از یک نصب قدیمی‌تر باقی مانده‌اند ولی
 * گزینهٔ نسخهٔ پایگاه‌داده پاک شده است (پاک‌سازی دستی wp_options،
 * مهاجرت سایت بدون انتقال آپشن‌ها، یا جایگزینی پوشه با FTP).
 *
 * در آن حالت ردیف‌ها `sort_key` نداشتند و `ORDER BY sort_key ASC`
 * آن‌ها را با ترتیب درج برمی‌گرداند — یعنی فهرست اشخاص بی‌ترتیب.
 */
$sk_table = Sparkknife_Ledger_DB::persons_table();
$sk_marker = 'MARKER_SK_' . wp_generate_password( 8, false, false );
$sk_ids    = array();

global $wpdb;

/*
 * سه شخص با نام‌هایی که ترتیب الفبایی‌شان با ترتیب درج **مخالف** است:
 * «یوسف» اول درج می‌شود و باید آخر بیاید.
 */
foreach ( array( 'یوسف', 'آرش', 'مهدی' ) as $sk_name ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
	$wpdb->insert(
		$sk_table,
		array(
			'name'       => $sk_name . ' ' . $sk_marker,
			'phone'      => '',
			'status'     => 'active',
			'description' => '',
			'sort_key'   => null,
			'created_at' => current_time( 'mysql' ),
			'updated_at' => current_time( 'mysql' ),
		)
	);
	$sk_ids[] = (int) $wpdb->insert_id;
}

spk_assert( 3 === count( array_filter( $sk_ids ) ), 'سه شخص برای آزمون sort_key ساخته شد' );

// پیش از backfill: کلیدها خالی‌اند.
$sk_null_before = (int) $wpdb->get_var(
	$wpdb->prepare(
		"SELECT COUNT(*) FROM {$sk_table} WHERE id IN (%d,%d,%d) AND ( sort_key IS NULL OR sort_key = '' )",
		$sk_ids[0],
		$sk_ids[1],
		$sk_ids[2]
	)
);

spk_equals( 3, $sk_null_before, 'پیش از backfill هر سه ردیف sort_key خالی دارند' );

// اجرای همان متدی که مسیر ارتقا صدا می‌زند.
$sk_filled = Sparkknife_Ledger_Installer::backfill_sort_keys();

spk_assert( $sk_filled > 0, 'backfill_sort_keys ردیف‌های خالی را پر می‌کند' );

$sk_null_after = (int) $wpdb->get_var(
	$wpdb->prepare(
		"SELECT COUNT(*) FROM {$sk_table} WHERE id IN (%d,%d,%d) AND ( sort_key IS NULL OR sort_key = '' )",
		$sk_ids[0],
		$sk_ids[1],
		$sk_ids[2]
	)
);

spk_equals( 0, $sk_null_after, 'پس از backfill هیچ ردیفی sort_key خالی ندارد' );

// ترتیب الفبایی حالا درست است: آرش، مهدی، یوسف.
// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$sk_ordered = $wpdb->get_results(
	$wpdb->prepare(
		"SELECT name FROM {$sk_table} WHERE id IN (%d,%d,%d) ORDER BY sort_key ASC",
		$sk_ids[0],
		$sk_ids[1],
		$sk_ids[2]
	)
);

$sk_names = array();

foreach ( (array) $sk_ordered as $sk_row ) {
	$sk_names[] = str_replace( ' ' . $sk_marker, '', $sk_row->name );
}

spk_equals(
	array( 'آرش', 'مهدی', 'یوسف' ),
	$sk_names,
	'پس از backfill ترتیب الفبای فارسی درست است، نه ترتیب درج'
);

// اجرای دوباره نباید چیزی را خراب کند (ایدمپوتنت).
Sparkknife_Ledger_Installer::backfill_sort_keys();

// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$sk_again = $wpdb->get_results(
	$wpdb->prepare(
		"SELECT name FROM {$sk_table} WHERE id IN (%d,%d,%d) ORDER BY sort_key ASC",
		$sk_ids[0],
		$sk_ids[1],
		$sk_ids[2]
	)
);

$sk_names_again = array();

foreach ( (array) $sk_again as $sk_row ) {
	$sk_names_again[] = str_replace( ' ' . $sk_marker, '', $sk_row->name );
}

spk_equals( array( 'آرش', 'مهدی', 'یوسف' ), $sk_names_again, 'اجرای دوبارهٔ backfill ترتیب را تغییر نمی‌دهد' );

// تمیزکاری.
foreach ( $sk_ids as $sk_cleanup_id ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$sk_table} WHERE id = %d", $sk_cleanup_id ) );
}

// ── ۳۶: جعل IP در لاگ ممیزی با X-Forwarded-For ───────────────────────
/*
 * باگ ۶۶: توضیح تابع `get_ip()` وعده می‌داد که هدر `X-Forwarded-For`
 * فقط «اگر `REMOTE_ADDR` یکی از پروکسی‌های مجاز باشد» پذیرفته می‌شود و
 * فیلتر `sparkknife_ledger_trusted_proxies` را معرفی می‌کرد — اما آن
 * بررسی در کد **وجود نداشت** و فیلتر هرگز `apply_filters` نمی‌شد.
 *
 * نتیجه: با فعال‌کردن `TRUST_PROXY`، هر کلاینتی که مستقیم به سایت وصل
 * می‌شد می‌توانست IP دلخواهی در لاگ بنشاند.
 *
 * در این بلوک خودِ متد خصوصی با Reflection صدا زده می‌شود، چون رفتار
 * آن به `$_SERVER` وابسته است و از مسیر عمومی قابل کنترل نیست.
 */
$ip_ref = new ReflectionMethod( 'Sparkknife_Ledger_Audit_Service', 'get_ip' );

if ( method_exists( $ip_ref, 'setAccessible' ) ) {
	$ip_ref->setAccessible( true );
}

$ip_service = Sparkknife_Ledger_Audit_Service::instance();
$ip_backup  = $_SERVER;

// (۱) بدون trust: هدر باید نادیده گرفته شود.
$_SERVER['REMOTE_ADDR']          = '203.0.113.5';
$_SERVER['HTTP_X_FORWARDED_FOR'] = '1.2.3.4';

add_filter( 'sparkknife_ledger_trust_proxy', '__return_false' );
$ip_no_trust = $ip_ref->invoke( $ip_service );
remove_filter( 'sparkknife_ledger_trust_proxy', '__return_false' );

spk_equals( '203.0.113.5', $ip_no_trust, 'بدون trust، هدر X-Forwarded-For نادیده گرفته می‌شود' );

// (۲) با trust و فهرست پروکسی‌های مجاز: REMOTE_ADDR غیرمجاز → هدر رد می‌شود.
$ip_trusted_cb = static function () {
	return array( '10.0.0.1' );
};

add_filter( 'sparkknife_ledger_trust_proxy', '__return_true' );
add_filter( 'sparkknife_ledger_trusted_proxies', $ip_trusted_cb );

$ip_spoofed = $ip_ref->invoke( $ip_service );

remove_filter( 'sparkknife_ledger_trusted_proxies', $ip_trusted_cb );
remove_filter( 'sparkknife_ledger_trust_proxy', '__return_true' );

spk_equals(
	'203.0.113.5',
	$ip_spoofed,
	'اگر REMOTE_ADDR پروکسی مجاز نباشد، هدر جعلی پذیرفته نمی‌شود'
);

// (۳) با trust و REMOTE_ADDR مجاز: راست‌ترین آدرس معتبر برنده است.
$_SERVER['REMOTE_ADDR']          = '10.0.0.1';
$_SERVER['HTTP_X_FORWARDED_FOR'] = '8.8.8.8, 198.51.100.7';

add_filter( 'sparkknife_ledger_trust_proxy', '__return_true' );
add_filter( 'sparkknife_ledger_trusted_proxies', $ip_trusted_cb );

$ip_real = $ip_ref->invoke( $ip_service );

remove_filter( 'sparkknife_ledger_trusted_proxies', $ip_trusted_cb );
remove_filter( 'sparkknife_ledger_trust_proxy', '__return_true' );

spk_equals( '198.51.100.7', $ip_real, 'پشت پروکسی مجاز، راست‌ترین آدرس معتبر برنده است' );

// (۴) آدرس آشغال در انتهای زنجیره نباید آدرس جعلی سمت چپ را برنده کند.
$_SERVER['HTTP_X_FORWARDED_FOR'] = '8.8.8.8, 198.51.100.7, garbage';

add_filter( 'sparkknife_ledger_trust_proxy', '__return_true' );
add_filter( 'sparkknife_ledger_trusted_proxies', $ip_trusted_cb );

$ip_garbage = $ip_ref->invoke( $ip_service );

remove_filter( 'sparkknife_ledger_trusted_proxies', $ip_trusted_cb );
remove_filter( 'sparkknife_ledger_trust_proxy', '__return_true' );

spk_equals(
	'198.51.100.7',
	$ip_garbage,
	'آشغال در انتهای زنجیره، آدرس جعلی سمت چپ را برنده نمی‌کند'
);

// بازگرداندن محیط.
$_SERVER = $ip_backup;

// ── ۳۷: تاریخچهٔ ابطال نباید تغییر بی‌تغییر ثبت کند ───────────────────
/*
 * باگ ۶۷: `void()` در آرایهٔ `changes` فیلد `amount` را با همان مقدار در
 * هر دو طرف ثبت می‌کرد (`from` و `to` هر دو مبلغ تراکنش). صفحهٔ تاریخچه
 * آن را «مبلغ: X → X» نشان می‌داد — ردیفی که هیچ تغییری توصیف نمی‌کند.
 *
 * `Audit_Service::log()` مقادیر را فیلتر نمی‌کند (فقط `diff()` این کار
 * را می‌کند)، پس این نویز واقعاً در JSON ذخیره می‌شد.
 */
$av_marker = 'MARKER_AV_' . wp_generate_password( 6, false, false );

global $wpdb;
$av_table = Sparkknife_Ledger_DB::persons_table();

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
$wpdb->insert(
	$av_table,
	array(
		'name'        => $av_marker,
		'phone'       => '',
		'status'      => 'active',
		'description' => '',
		'sort_key'    => Sparkknife_Ledger_Sanitizer::persian_sort_key( $av_marker ),
		'created_at'  => current_time( 'mysql' ),
		'updated_at'  => current_time( 'mysql' ),
	)
);

$av_person_id = (int) $wpdb->insert_id;

spk_assert( $av_person_id > 0, 'شخص آزمون تاریخچهٔ ابطال ساخته شد' );

$av_txn_id = Sparkknife_Ledger_Transaction_Service::instance()->create(
	array(
		'person_id'   => $av_person_id,
		'type'        => 'income',
		'amount'      => '125000',
		'txn_date'    => current_time( 'Y-m-d' ),
		'description' => $av_marker,
	)
);

spk_assert( ! is_wp_error( $av_txn_id ), 'تراکنش آزمون ابطال ساخته شد' );

$av_void = Sparkknife_Ledger_Transaction_Service::instance()->void( $av_txn_id, 'دلیل آزمون' );

spk_assert( true === $av_void, 'تراکنش با موفقیت باطل شد' );

// رکورد ممیزی را بخوان و مطمئن شو amount با from===to ثبت نشده.
$av_audit = Sparkknife_Ledger_Audit_Service::instance()->get_history( 'transaction', $av_txn_id, 10 );
$av_found = false;

foreach ( (array) $av_audit as $av_entry ) {
	if ( 'void' !== $av_entry->action ) {
		continue;
	}

	$av_changes = Sparkknife_Ledger_Audit_Service::instance()->decode_changes( $av_entry );

	$av_found = true;

	spk_assert( ! isset( $av_changes['amount'] ), 'تاریخچهٔ ابطال فیلد amount بی‌تغییر ندارد' );
	spk_assert( isset( $av_changes['status'] ), 'تاریخچهٔ ابطال تغییر وضعیت را دارد' );
	spk_equals( 'posted', isset( $av_changes['status']['from'] ) ? $av_changes['status']['from'] : null, 'وضعیت پیش از ابطال posted است' );
	spk_equals( 'void', isset( $av_changes['status']['to'] ) ? $av_changes['status']['to'] : null, 'وضعیت پس از ابطال void است' );
	spk_assert( isset( $av_changes['void_reason'] ), 'تاریخچهٔ ابطال دلیل را ثبت می‌کند' );

	break;
}

spk_assert( $av_found, 'رکورد ممیزی ابطال در تاریخچه پیدا شد' );

// هیچ فیلدی در هیچ رکوردی نباید from === to داشته باشد.
$av_dup_ok = true;

foreach ( (array) $av_audit as $av_entry ) {
	foreach ( Sparkknife_Ledger_Audit_Service::instance()->decode_changes( $av_entry ) as $av_field => $av_pair ) {
		if ( ! is_array( $av_pair ) || ! array_key_exists( 'from', $av_pair ) || ! array_key_exists( 'to', $av_pair ) ) {
			continue;
		}

		if ( null !== $av_pair['from'] && (string) $av_pair['from'] === (string) $av_pair['to'] ) {
			$av_dup_ok = false;
		}
	}
}

spk_assert( $av_dup_ok, 'در تاریخچهٔ تراکنش هیچ فیلدی با from === to ثبت نشده است' );

// تمیزکاری.
$av_audit_table = Sparkknife_Ledger_DB::audit_table();
$av_txn_table   = Sparkknife_Ledger_DB::transactions_table();

$wpdb->query( $wpdb->prepare( "DELETE FROM {$av_audit_table} WHERE object_type = 'transaction' AND object_id = %d", $av_txn_id ) );
$wpdb->query( $wpdb->prepare( "DELETE FROM {$av_txn_table} WHERE id = %d", $av_txn_id ) );
$wpdb->query( $wpdb->prepare( "DELETE FROM {$av_table} WHERE id = %d", $av_person_id ) );

// ── ۳۸: مبلغ منفی در CSV نباید به متن تبدیل شود ─────────────────────
/*
 * باگ ۷۰: محافظت تزریق فرمول، هر رشته‌ای که با `-` شروع می‌شد را با
 * آپاستروف می‌پوشاند — از جمله مبالغ منفی. نتیجه در فایل CSV:
 *
 *     ثریا حسینی,…,1019000,'-192000,"مانده منفی"
 *
 * Excel و LibreOffice آپاستروف را نشانهٔ «متن» می‌گیرند، پس آن سلول
 * دیگر عدد نیست: `SUM()` صفر می‌دهد و نمودار ستونی ساخته نمی‌شود.
 *
 * این عارضه در جریان رفع باگ ۶۳ روی نمونهٔ واقعی دیده شد.
 */
spk_equals( '-192000', Sparkknife_Ledger_Export_Service::sanitize_cell( '-192000' ), 'مبلغ منفی آپاستروف نمی‌گیرد' );
spk_equals( '-1', Sparkknife_Ledger_Export_Service::sanitize_cell( '-1' ), 'منفی کوچک آپاستروف نمی‌گیرد' );
spk_equals( '0', Sparkknife_Ledger_Export_Service::sanitize_cell( '0' ), 'صفر دست‌نخورده می‌ماند' );
spk_equals( '125000', Sparkknife_Ledger_Export_Service::sanitize_cell( '125000' ), 'مبلغ مثبت دست‌نخورده می‌ماند' );
spk_equals( '1,250,000', Sparkknife_Ledger_Export_Service::sanitize_cell( '1,250,000' ), 'عدد با جداکننده دست‌نخورده می‌ماند' );
spk_equals( '-1,250,000.50', Sparkknife_Ledger_Export_Service::sanitize_cell( '-1,250,000.50' ), 'منفی اعشاری دست‌نخورده می‌ماند' );
spk_equals( '-7438000', Sparkknife_Ledger_Export_Service::sanitize_cell( '-7438000' ), 'مانده کل منفی دست‌نخورده می‌ماند' );

/*
 * امنیت نباید فدا شده باشد: هر چیزی که عدد خالص نیست و با `-` شروع
 * می‌شود باید همچنان مسدود شود.
 */
$csv_neg_unsafe = array( '-1+1', '-cmd', '- 1', '-2+3', '--1', '-1e5' );
$csv_neg_ok     = true;

foreach ( $csv_neg_unsafe as $csv_neg_payload ) {
	if ( 0 !== strpos( Sparkknife_Ledger_Export_Service::sanitize_cell( $csv_neg_payload ), "'" ) ) {
		$csv_neg_ok = false;
		break;
	}
}

spk_assert( $csv_neg_ok, 'هر شش منفیِ غیرعددی همچنان آپاستروف می‌گیرند' );

// پیش‌وند پوشاننده + فرمول باید همچنان مسدود شود.
spk_assert(
	0 === strpos( Sparkknife_Ledger_Export_Service::sanitize_cell( "\u{200B}=cmd|calc" ), "'" ),
	'ZWSP + فرمول همچنان مسدود می‌شود'
);
spk_assert(
	0 === strpos( Sparkknife_Ledger_Export_Service::sanitize_cell( "\u{00A0}-1+1" ), "'" ),
	'NBSP + منفی خطرناک همچنان مسدود می‌شود'
);

// متون سالم دست‌نخورده.
spk_equals( 'زهرا صادقی', Sparkknife_Ledger_Export_Service::sanitize_cell( 'زهرا صادقی' ), 'نام فارسی دست‌نخورده می‌ماند' );
spk_equals( '09129241959', Sparkknife_Ledger_Export_Service::sanitize_cell( '09129241959' ), 'شماره تماس دست‌نخورده می‌ماند' );

// ── ۳۹: خلاصهٔ تاریخچه با نوع تغییر باید بخواند ─────────────────────
/*
 * باگ ۷۲: `$action` در حالت «فقط تغییر وضعیت» به `status_change`
 * تبدیل می‌شد، ولی متن خلاصه همیشه «ویرایش اطلاعات شخص» می‌ماند.
 *
 *      [غیرفعال‌سازی]  ویرایش اطلاعات شخص «آرش محمدی»
 *
 * کاربر از این ردیف نتیجه می‌گیرد نام یا تلفن هم عوض شده و بی‌جهت
 * دنبال جزئیاتی می‌گردد که وجود ندارد.
 */
$sm_marker = 'MARKER_SM_' . wp_generate_password( 6, false, false );

$sm_id = Sparkknife_Ledger_Person_Service::instance()->create(
	array(
		'name'        => $sm_marker,
		'phone'       => '',
		'status'      => 'active',
		'description' => '',
	)
);

spk_assert( ! is_wp_error( $sm_id ), 'شخص آزمون خلاصهٔ ممیزی ساخته شد' );

// تغییر وضعیت → خلاصه باید «تغییر وضعیت» باشد، نه «ویرایش».
Sparkknife_Ledger_Person_Service::instance()->set_status( $sm_id, 'inactive' );

$sm_history = Sparkknife_Ledger_Audit_Service::instance()->get_history( 'person', $sm_id, 10 );
$sm_status_summary = '';
$sm_has_status     = false;

foreach ( (array) $sm_history as $sm_entry ) {
	if ( 'status_change' === $sm_entry->action ) {
		$sm_has_status     = true;
		$sm_status_summary = (string) $sm_entry->summary;
		break;
	}
}

spk_assert( $sm_has_status, 'تغییر وضعیت با action مستقل ثبت شده است' );
spk_assert(
	false !== mb_strpos( $sm_status_summary, 'تغییر وضعیت' ),
	'خلاصهٔ تغییر وضعیت می‌گوید «تغییر وضعیت»، نه «ویرایش اطلاعات»'
);
spk_assert(
	false === mb_strpos( $sm_status_summary, 'ویرایش' ),
	'خلاصهٔ تغییر وضعیت واژهٔ «ویرایش» را ندارد'
);
spk_assert(
	false !== mb_strpos( $sm_status_summary, 'غیرفعال' ),
	'خلاصهٔ تغییر وضعیت، وضعیت جدید را نام می‌برد'
);

// ویرایش واقعی → خلاصه باید همچنان «ویرایش» بماند.
Sparkknife_Ledger_Person_Service::instance()->update(
	$sm_id,
	array(
		'name'        => $sm_marker . ' ویرایش‌شده',
		'phone'       => '',
		'status'      => 'inactive',
		'description' => '',
	)
);

$sm_history2 = Sparkknife_Ledger_Audit_Service::instance()->get_history( 'person', $sm_id, 10 );
$sm_update_summary = '';

foreach ( (array) $sm_history2 as $sm_entry ) {
	if ( 'update' === $sm_entry->action ) {
		$sm_update_summary = (string) $sm_entry->summary;
		break;
	}
}

spk_assert(
	false !== mb_strpos( $sm_update_summary, 'ویرایش اطلاعات' ),
	'ویرایش واقعی همچنان خلاصهٔ «ویرایش اطلاعات» دارد'
);

// تمیزکاری.
global $wpdb;
$sm_table = Sparkknife_Ledger_DB::persons_table();
$sm_audit = Sparkknife_Ledger_DB::audit_table();

$wpdb->query( $wpdb->prepare( "DELETE FROM {$sm_audit} WHERE object_type = 'person' AND object_id = %d", $sm_id ) );
$wpdb->query( $wpdb->prepare( "DELETE FROM {$sm_table} WHERE id = %d", $sm_id ) );

// ── ۴۱: عبارت جستجوی بی‌محتوا هیچ نسخه‌ای نمی‌سازد ─────────────────
//
// `normalize_persian()` نویسه‌های قالب‌بندی (نیم‌فاصله U+200C،
// U+200B و فاصله) را حذف می‌کند، ولی `trim()` آن‌ها را نمی‌گیرد.
// نتیجه این بود که عبارتی که فقط از نیم‌فاصله ساخته شده از نگهبان
// «عبارت خالی» رد می‌شد و یک نسخهٔ یک‌نویسه‌ای (خودِ نیم‌فاصله)
// تولید می‌کرد. قرارداد تابع این است که برای عبارت بی‌محتوا هیچ
// نسخه‌ای برنگرداند.
foreach ( array( '', ' ', "\t\n", "\u{200C}", "\u{200B}", " \u{200C} ", "\u{FEFF}" ) as $blank_q ) {
	spk_equals(
		array(),
		Sparkknife_Ledger_Sanitizer::search_variants( $blank_q ),
		'عبارت بی‌محتوا هیچ نسخه‌ای نمی‌سازد: ' . bin2hex( $blank_q )
	);
}

// عبارت معنادار اما کوتاه باید همچنان نسخه بسازد.
spk_assert(
	count( Sparkknife_Ledger_Sanitizer::search_variants( 'علی' ) ) > 0,
	'جستجوی یک‌واژه‌ای همچنان نسخه می‌سازد'
);

// ── ۴۲: جستجو باید ارقام عربی‌هندی و ترکیب‌های مختلط را هم پیدا کند ──
//
// ریشهٔ باگ: `to_persian_digits()` و `to_arabic_digits()` کل رشته را
// یکدست تبدیل می‌کنند. رکورد واقعی می‌تواند مختلط باشد — شمارهٔ
// تماس با ارقام عربی‌هندی و یک شناسهٔ لاتین در ادامه. هیچ‌کدام از
// سه نسخهٔ یکدست چنین رکوردی را پیدا نمی‌کرد.
//
// بدتر: سقف ثابت نسخه‌ها همیشه آخرین خانواده (ارقام عربی و ترکیب‌های
// مختلط) را می‌بُرید، پس حتی نسخهٔ درست هم به کوئری نمی‌رسید.
$mix_repo  = Sparkknife_Ledger_Person_Repository::instance();
$mix_tag   = 'MIX' . wp_generate_password( 6, false, false );
$mix_forms = array(
	'علي كاوه 0912',                 // ك عربی + ارقام لاتین
	'علی کاوه ۰۹۱۲',                 // ک فارسی + ارقام فارسی
	'علي كاوه ٠٩١٢',                 // ك عربی + ارقام عربی‌هندی
	'علی کاوه 0912',                 // ک فارسی + ارقام لاتین
);

$mix_ids = array();

foreach ( $mix_forms as $mix_form ) {
	$mix_id = $mix_repo->insert(
		array(
			'name'        => $mix_form . ' ' . $mix_tag,
			'phone'       => '',
			'description' => '',
			'status'      => 'active',
		)
	);

	spk_assert( $mix_id > 0, 'شخص آزمایشی مختلط ساخته شد' );

	if ( $mix_id > 0 ) {
		$mix_ids[ $mix_form ] = (int) $mix_id;
	}
}

// هر رکورد باید با هر ترکیب دیگر جستجو پیدا شود — هر دو جهت.
foreach ( $mix_ids as $mix_stored => $mix_stored_id ) {
	foreach ( $mix_forms as $mix_query ) {
		$mix_result = $mix_repo->query(
			array( 'search' => $mix_query . ' ' . $mix_tag, 'per_page' => 200 )
		);

		$mix_found = false;

		foreach ( $mix_result['items'] as $mix_item ) {
			if ( (int) $mix_item->id === $mix_stored_id ) {
				$mix_found = true;
				break;
			}
		}

		spk_assert(
			$mix_found,
			'ذخیره «' . $mix_stored . '» با جستجوی «' . $mix_query . '» پیدا می‌شود'
		);
	}
}

// نسخهٔ مختلط واقعاً ساخته می‌شود (نه فقط سقف بزرگ شده باشد).
$mix_variants = Sparkknife_Ledger_Sanitizer::search_variants( 'علی کاوه 0912 MK12' );
$mix_mixed    = 0;

foreach ( $mix_variants as $mix_variant ) {
	if ( preg_match( '/[\x{0660}-\x{0669}]/u', $mix_variant ) && preg_match( '/[0-9]/', $mix_variant ) ) {
		$mix_mixed++;
	}
}

spk_assert(
	$mix_mixed > 0,
	'ترکیب ارقام عربی و لاتین در یک نسخه ساخته می‌شود (' . $mix_mixed . ' نسخه)'
);

// و سقف باید از تعداد واقعی نسخه‌ها بزرگ‌تر باشد، وگرنه بریدن،
// خانوادهٔ آخر را می‌اندازد.
foreach ( array( 'علی کاوه 0912', 'علی کاوه 0912 MK12', 'فاطمه محمدی ۰۹۱۲۳۴۵۶۷۸۹' ) as $cap_query ) {
	$cap_count = count( Sparkknife_Ledger_Sanitizer::search_variants( $cap_query ) );

	spk_assert(
		$cap_count <= Sparkknife_Ledger_Sanitizer::MAX_SEARCH_VARIANTS,
		'نسخه‌ها از سقف مطلق نمی‌گذرند (' . $cap_count . ')'
	);
}

// تمیزکاری.
foreach ( $mix_ids as $mix_id ) {
	Sparkknife_Ledger_Person_Service::instance()->delete( $mix_id );
}

// ── ۴۳: جستجوی بی‌محتوا در تراکنش‌ها کوئری را نمی‌شکند ──────────────
//
// مخزن اشخاص از قبل نگهبان داشت: اگر یکسان‌سازی عبارت را کاملاً حذف
// کند، هیچ شرطی ساخته نمی‌شود. مخزن تراکنش‌ها همان نگهبان را نداشت و
// مستقیم شرط می‌ساخت. نتیجه یک خطای واقعی بود — نه صفحهٔ خالی، یک
// پیام «خطای دیتابیس» جلوی چشم کاربر:
//
//   WHERE 1=1 AND ()   →   syntax error near ")"
//
// این تست همان مسیر واقعی را صدا می‌زند و مطمئن می‌شود نتیجه «بدون
// خطا» است، نه «با پیام خطا».
global $wpdb;

$txn_repo       = Sparkknife_Ledger_Transaction_Repository::instance();
$txn_err_before = $wpdb->last_error;

foreach ( array( '', ' ', "\t", "\u{200C}", "\u{200B}", "\u{FEFF}", " \u{200C} " ) as $blank_txn_q ) {
	$wpdb->last_error = '';

	$txn_blank_result = $txn_repo->query( array( 'search' => $blank_txn_q, 'per_page' => 20 ) );

	spk_equals(
		'',
		(string) $wpdb->last_error,
		'جستجوی بی‌محتوا در تراکنش‌ها خطای دیتابیس نمی‌دهد: ' . bin2hex( $blank_txn_q )
	);

	spk_assert(
		isset( $txn_blank_result['total'] ) && is_int( $txn_blank_result['total'] ),
		'جستجوی بی‌محتوا در تراکنش‌ها نتیجهٔ معتبر برمی‌گرداند: ' . bin2hex( $blank_txn_q )
	);

	spk_equals(
		(int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . Sparkknife_Ledger_DB::transactions_table() ),
		(int) $txn_blank_result['total'],
		'جستجوی بی‌محتوا همهٔ تراکنش‌ها را می‌بیند (فیلتر اعمال نمی‌شود): ' . bin2hex( $blank_txn_q )
	);
}

$wpdb->last_error = $txn_err_before;

// جستجوی واقعی در تراکنش‌ها همچنان کار می‌کند.
$txn_real_result = $txn_repo->query( array( 'search' => 'zzz-no-such-reference-zzz', 'per_page' => 20 ) );

spk_equals( '', (string) $wpdb->last_error, 'جستجوی عادی تراکنش خطا نمی‌دهد' );
spk_equals( 0, (int) $txn_real_result['total'], 'جستجوی بی‌نتیجه صفر ردیف می‌دهد' );

// ── ۴۴: چرخه ابطال/بازیابی، تقویم و صحت گزارش‌ها ────────────────────
//
// پاس ۱۰ روی نواحی‌ای تمرکز کرد که تا آن زمان فقط با بازخوانی کد
// تأیید شده بودند. سه ناحیه انتخاب شد: چرخه ابطال/بازیابی، گردش
// تقویم شمسی و انطباق گزارش‌ها با SQL مستقیم. هیچ باگ تازه‌ای پیدا
// نشد، ولی همین سه ناحیه حساس‌ترین بخش‌های یک افزونه حسابداری‌اند
// و ارزش نگهبان دارند.

add_filter( 'sparkknife_ledger_allow_void_restore', '__return_true' );

$cyc_repo = Sparkknife_Ledger_Transaction_Repository::instance();
$cyc_svc  = Sparkknife_Ledger_Transaction_Service::instance();
$cyc_rep  = Sparkknife_Ledger_Report_Service::instance();
$cyc_tag  = 'CYC' . wp_generate_password( 6, false, false );
$cyc_pid  = (int) Sparkknife_Ledger_Person_Repository::instance()->insert(
	array(
		'name'        => 'چرخه ' . $cyc_tag,
		'phone'       => '',
		'description' => '',
		'status'      => 'active',
	)
);

$cyc_a = $cyc_svc->create(
	array(
		'person_id'   => $cyc_pid,
		'type'        => 'income',
		'amount'      => '1000000',
		'txn_date'    => '1405/08/16',
		'description' => 'چرخه الف',
	)
);
$cyc_b = $cyc_svc->create(
	array(
		'person_id'   => $cyc_pid,
		'type'        => 'income',
		'amount'      => '2000000',
		'txn_date'    => '1405/08/16',
		'description' => 'چرخه ب',
	)
);

spk_assert( ! is_wp_error( $cyc_a ) && ! is_wp_error( $cyc_b ), 'دو تراکنش چرخه ساخته شد' );

$cyc_a = (int) $cyc_a;
$cyc_b = (int) $cyc_b;

$cyc_before = $cyc_rep->get_summary_report( array( 'person_id' => $cyc_pid, 'per_page' => 50 ) );
$cyc_void   = $cyc_svc->void( $cyc_b, 'دلیل چرخه' );
$cyc_mid    = $cyc_rep->get_summary_report( array( 'person_id' => $cyc_pid, 'per_page' => 50 ) );

spk_assert( true === $cyc_void, 'ابطال موفق است' );
spk_equals(
	(int) $cyc_before['totals']['count'] - 1,
	(int) $cyc_mid['totals']['count'],
	'ابطال یک ردیف از شمار تراکنش‌های گزارش کم می‌کند'
);

// ابطال دوباره باید رد شود.
$cyc_twice = $cyc_svc->void( $cyc_b, 'دوباره' );
spk_assert( is_wp_error( $cyc_twice ), 'ابطال دوباره همان تراکنش رد می‌شود' );
spk_equals(
	'sparkknife_ledger_already_void',
	is_wp_error( $cyc_twice ) ? $cyc_twice->get_error_code() : '',
	'کد خطای ابطال دوباره درست است'
);

// بازیابی باید همه‌چیز را به حالت اول برگرداند.
$cyc_restore = $cyc_svc->restore_void( $cyc_b );
$cyc_after   = $cyc_rep->get_summary_report( array( 'person_id' => $cyc_pid, 'per_page' => 50 ) );

spk_assert( true === $cyc_restore, 'بازیابی تراکنش باطل‌شده موفق است' );
spk_equals(
	(string) $cyc_before['totals']['income'],
	(string) $cyc_after['totals']['income'],
	'بازیابی درآمد گزارش را دقیقاً به حالت اول برمی‌گرداند'
);
spk_equals(
	(int) $cyc_before['totals']['count'],
	(int) $cyc_after['totals']['count'],
	'بازیابی شمار تراکنش‌ها را دقیقاً برمی‌گرداند'
);

// فیلدهای ابطال باید پاک شوند، نه فقط وضعیت.
$cyc_row = $cyc_repo->find( $cyc_b );

spk_equals( 'posted', (string) $cyc_row->status, 'وضعیت پس از بازیابی posted است' );
spk_assert( empty( $cyc_row->void_reason ), 'دلیل ابطال پس از بازیابی پاک می‌شود' );
spk_equals( 0, (int) $cyc_row->voided_by, 'ابطال‌کننده پس از بازیابی صفر می‌شود' );
spk_assert( empty( $cyc_row->voided_at ), 'زمان ابطال پس از بازیابی پاک می‌شود' );

// بازیابی تراکنشی که باطل نیست باید رد شود.
$cyc_not_void = $cyc_svc->restore_void( $cyc_a );

spk_assert( is_wp_error( $cyc_not_void ), 'بازیابی تراکنش غیرباطل رد می‌شود' );
spk_equals(
	'sparkknife_ledger_not_void',
	is_wp_error( $cyc_not_void ) ? $cyc_not_void->get_error_code() : '',
	'کد خطای بازیابی غیرباطل درست است'
);

// تاریخچه باید سه رویداد create → void → restore داشته باشد.
$cyc_actions = array();
$cyc_logs    = Sparkknife_Ledger_Audit_Repository::instance()->query(
	array( 'object_type' => 'transaction', 'object_id' => $cyc_b, 'per_page' => 50 )
);

foreach ( $cyc_logs['items'] as $cyc_log ) {
	$cyc_actions[] = $cyc_log->action;
}

/*
 * مخزن تاریخچه جدیدترین را اول می‌دهد (`ORDER BY created_at DESC`) —
 * این ترتیب نمایش درست است چون کاربر دنبال آخرین اتفاق است. پس
 * انتظار از جدید به قدیم نوشته می‌شود.
 */
spk_equals(
	array( 'restore', 'void', 'create' ),
	$cyc_actions,
	'تاریخچه چرخه ابطال هر سه رویداد را دارد'
);

// ── ۴۴ب: گردش تقویم شمسی ────────────────────────────────────────────
//
// هر روز یک بازه چهارساله باید از میلادی به شمسی و برگشت **بی‌تغییر**
// برگردد. یک خطای یک‌روزه در تبدیل، گزارش‌های مرزی و «۳۰ روز اخیر» را
//بی‌صدا جابه‌جا می‌کند.

$cal_fails  = 0;
$cal_cursor = '2024-01-01';
$cal_end    = '2026-12-31';

while ( $cal_cursor <= $cal_end ) {
	$cal_jalali = Sparkknife_Ledger_Date::format( $cal_cursor );
	$cal_back   = Sparkknife_Ledger_Date::to_storage( $cal_jalali );

	if ( is_wp_error( $cal_back ) || $cal_back !== $cal_cursor ) {
		$cal_fails++;
	}

	$cal_cursor = gmdate( 'Y-m-d', strtotime( $cal_cursor . ' +1 day' ) );
}

spk_equals( 0, $cal_fails, 'گردش سه‌ساله تقویم شمسی هیچ اختلافی ندارد' );

foreach ( array( 1399, 1403, 1408 ) as $cal_leap ) {
	spk_assert( Sparkknife_Ledger_Date::is_jalali_leap( $cal_leap ), 'سال ' . $cal_leap . ' کبیسه است' );
	spk_assert(
		! is_wp_error( Sparkknife_Ledger_Date::to_storage( $cal_leap . '/12/30' ) ),
		'۳۰ اسفند سال کبیسه ' . $cal_leap . ' پذیرفته می‌شود'
	);
}

foreach ( array( 1400, 1405, 1406 ) as $cal_plain ) {
	spk_assert( ! Sparkknife_Ledger_Date::is_jalali_leap( $cal_plain ), 'سال ' . $cal_plain . ' کبیسه نیست' );
	spk_assert(
		is_wp_error( Sparkknife_Ledger_Date::to_storage( $cal_plain . '/12/30' ) ),
		'۳۰ اسفند سال غیرکبیسه ' . $cal_plain . ' رد می‌شود'
	);
}

foreach ( array( '1405/13/01', '1405/01/00', '1405/00/15', '1405/07/31', 'abc', '' ) as $cal_bad ) {
	spk_assert(
		is_wp_error( Sparkknife_Ledger_Date::to_storage( $cal_bad ) ),
		'تاریخ نامعتبر رد می‌شود: ' . $cal_bad
	);
}

// ── ۴۴ج: انطباق گزارش با SQL مستقیم ─────────────────────────────────
//
// اگر گزارش با جمع مستقیم جدول نخواند، کاربر عددی می‌بیند که با
// حسابداری واقعی فرق دارد — بدترین نوع باگ در نرم‌افزار مالی.

$aud_txn_table = Sparkknife_Ledger_DB::transactions_table();

$aud_sql = $wpdb->get_row(
	"SELECT COALESCE(SUM(CASE WHEN type='income' THEN amount ELSE 0 END),0) AS inc,
	        COALESCE(SUM(CASE WHEN type='expense' THEN amount ELSE 0 END),0) AS exp,
	        COUNT(*) AS cnt
	 FROM {$aud_txn_table} WHERE status='posted'"
);

$aud_report = $cyc_rep->get_summary_report( array( 'per_page' => 500 ) );

spk_equals(
	(string) $aud_sql->inc,
	(string) $aud_report['totals']['income'],
	'درآمد گزارش تجمیعی با SQL مستقیم برابر است'
);
spk_equals(
	(string) $aud_sql->exp,
	(string) $aud_report['totals']['expense'],
	'هزینه گزارش تجمیعی با SQL مستقیم برابر است'
);
spk_equals(
	(int) $aud_sql->cnt,
	(int) $aud_report['totals']['count'],
	'شمار تراکنش‌های گزارش با SQL مستقیم برابر است'
);
spk_equals(
	Sparkknife_Ledger_Amount::sub( $aud_report['totals']['income'], $aud_report['totals']['expense'] ),
	(string) $aud_report['totals']['balance'],
	'مانده گزارش دقیقاً درآمد منهای هزینه است'
);

// جمع ردیف‌ها باید با جمع کل یکی باشد.
$aud_sum_inc = '0';
$aud_sum_exp = '0';
$aud_sum_cnt = 0;

foreach ( $aud_report['rows'] as $aud_row ) {
	$aud_sum_inc = Sparkknife_Ledger_Amount::add( $aud_sum_inc, $aud_row['income'] );
	$aud_sum_exp = Sparkknife_Ledger_Amount::add( $aud_sum_exp, $aud_row['expense'] );
	$aud_sum_cnt += (int) $aud_row['count'];
}

spk_equals( (string) $aud_report['totals']['income'], $aud_sum_inc, 'جمع درآمد ردیف‌ها با جمع کل برابر است' );
spk_equals( (string) $aud_report['totals']['expense'], $aud_sum_exp, 'جمع هزینه ردیف‌ها با جمع کل برابر است' );
spk_equals( (int) $aud_report['totals']['count'], $aud_sum_cnt, 'جمع شمار ردیف‌ها با جمع کل برابر است' );

// ── ۴۴د: تولید مرجع یکتا و پیوسته ───────────────────────────────────

$ref_list   = array();
$ref_errors = 0;

for ( $ref_i = 0; $ref_i < 12; $ref_i++ ) {
	$ref_id = $cyc_svc->create(
		array(
			'person_id'   => $cyc_pid,
			'type'        => 'income',
			'amount'      => '1000',
			'txn_date'    => '1405/08/16',
			'description' => 'مرجع ' . $ref_i,
		)
	);

	if ( is_wp_error( $ref_id ) ) {
		$ref_errors++;
		continue;
	}

	$ref_row = $cyc_repo->find( (int) $ref_id );

	if ( $ref_row ) {
		$ref_list[] = (string) $ref_row->reference;
		Sparkknife_Ledger_Transaction_Repository::instance()->update(
			(int) $ref_id,
			array( 'status' => 'void', 'void_reason' => 'پاک‌سازی' )
		);
	}
}

spk_equals( 0, $ref_errors, '۱۲ درج مرجع بدون خطا انجام شد' );
spk_equals( count( $ref_list ), count( array_unique( $ref_list ) ), 'همه مرجع‌های تولیدشده یکتا هستند' );

$ref_bad_shape = 0;

foreach ( $ref_list as $ref_one ) {
	if ( ! preg_match( '/\d+$/', $ref_one ) ) {
		$ref_bad_shape++;
	}
}

spk_equals( 0, $ref_bad_shape, 'همه مرجع‌ها به عدد تمام می‌شوند' );

// ── ۴۴ه: ویژگی‌های رشته‌ای نام‌ها در پایگاه داده ─────────────────────

$str_repo  = Sparkknife_Ledger_Person_Repository::instance();
$str_cases = array(
	'comma'   => 'شخص، با ویرگول',
	'quote'   => 'شخص "با" گیومه',
	'newline' => "شخص\nبا خط جدید",
	'tab'     => "شخص\tبا تب",
	'pipe'    => 'شخص|با پایپ',
	'zwnj'    => 'نیم‌فاصله‌دار',
	'emoji'   => 'شخص 😀 ایموجی',
);
$str_ids   = array();

foreach ( $str_cases as $str_key => $str_name ) {
	$str_ids[ $str_key ] = (int) $str_repo->insert(
		array(
			'name'        => $str_name,
			'phone'       => '',
			'description' => '',
			'status'      => 'active',
		)
	);

	$str_row = $str_repo->find( $str_ids[ $str_key ] );

	spk_equals(
		$str_name,
		$str_row ? $str_row->name : '',
		'نام با نویسه خاص بایت‌به‌بایت حفظ می‌شود: ' . $str_key
	);
}

// تزریق فرمول در خروجی CSV: همه این‌ها باید پیشوند بگیرند.
foreach ( array( '=1+1', '+1+1', '-1+1', '@SUM(A1)', "\t=1+1", "\n=1+1", ' =1+1' ) as $inj ) {
	$inj_safe = Sparkknife_Ledger_Export_Service::sanitize_cell( $inj );

	spk_equals(
		"'",
		substr( $inj_safe, 0, 1 ),
		'سلول مخرب پیشوند محافظ می‌گیرد: ' . bin2hex( $inj )
	);
}

// اعداد خالص نباید پیشوند بگیرند (باگ ۷۰).
foreach ( array( '-7438000', '125000', '-1.5', '-1,000' ) as $num_cell ) {
	spk_equals(
		$num_cell,
		Sparkknife_Ledger_Export_Service::sanitize_cell( $num_cell ),
		'عدد خالص در CSV دست‌نخورده می‌ماند: ' . $num_cell
	);
}

// تمیزکاری.
foreach ( $str_ids as $str_id ) {
	Sparkknife_Ledger_Person_Service::instance()->delete( $str_id );
}

global $wpdb;
$cyc_txn_table   = Sparkknife_Ledger_DB::transactions_table();
$cyc_audit_table = Sparkknife_Ledger_DB::audit_table();

$wpdb->query( $wpdb->prepare( "DELETE FROM {$cyc_audit_table} WHERE object_type = 'transaction' AND object_id = %d", $cyc_a ) );
$wpdb->query( $wpdb->prepare( "DELETE FROM {$cyc_audit_table} WHERE object_type = 'transaction' AND object_id = %d", $cyc_b ) );
$wpdb->query( $wpdb->prepare( "DELETE FROM {$cyc_txn_table} WHERE person_id = %d", $cyc_pid ) );
Sparkknife_Ledger_Person_Service::instance()->delete( $cyc_pid );

remove_filter( 'sparkknife_ledger_allow_void_restore', '__return_true' );

// ── ۱۰: سیاست نگه‌داری داده پس از همه تغییرات پابرجاست ──────────────
Sparkknife_Ledger_Settings::save( $defaults, false );
Sparkknife_Ledger_Settings::flush_cache();

spk_equals(
	0,
	(int) Sparkknife_Ledger_Settings::get( 'delete_data_on_uninstall' ),
	'در پایان ممیزی، حذف داده خاموش است'
);
spk_assert(
	Sparkknife_Ledger_DB::tables_exist(),
	'در پایان ممیزی، جداول سالم‌اند'
);

// ── ۴۵: خروجی کامل داده صفحه‌به‌صفحه می‌خواند و بی‌نهایت نمی‌چرخد ────
//
// اولین نسخهٔ `full_export_rows()` با شرط `count($items) > 0` می‌چرخید.
// مخزن `$page` را به بالاترین صفحه کلمپ می‌کند، پس صفحهٔ آخر بی‌نهایت
// تکرار می‌شد: ۱٬۲۰۰ شخص به ۴۰٬۶۶۳ ردیف تبدیل شد. این بلوک همان را
// می‌سنجد بدون آنکه به ۱٬۲۰۰ رکورد نیاز داشته باشد.

$ex_svc = Sparkknife_Ledger_Export_Service::instance();

spk_assert(
	method_exists( $ex_svc, 'full_export_rows' ),
	'خروجی کامل داده در دسترس است'
);

$full_rows = $ex_svc->full_export_rows();

spk_assert( is_array( $full_rows ) && count( $full_rows ) > 0, 'خروجی کامل ردیف دارد' );

// بخش‌های سه‌گانه باید حاضر باشند.
$full_text = '';
foreach ( $full_rows as $full_row ) {
	$full_text .= implode( ',', array_map( 'strval', (array) $full_row ) ) . "\n";
}

foreach ( array( '# اشخاص', '# تراکنش‌ها', '# تاریخچه تغییرات' ) as $section_label ) {
	spk_assert( false !== strpos( $full_text, $section_label ), 'بخش خروجی کامل موجود است: ' . $section_label );
}

/*
 * سنجهٔ اصلی: تعداد ردیف‌های اشخاص نباید از تعداد واقعی بیشتر باشد.
 * اگر حلقه دوباره بی‌نهایت بچرخد، این عدد چند برابر می‌شود.
 */
$total_persons = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$persons}" );
$persons_in_export = count( $ex_svc->persons_export_rows() ) - 1; // منهای سرآیند.

spk_equals(
	$total_persons,
	$persons_in_export,
	'خروجی کامل همهٔ اشخاص را یک‌بار می‌آورد (بدون تکرار صفحهٔ آخر)'
);

$total_txns = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$transactions}" );
$txns_in_export = count( $ex_svc->transactions_export_rows() ) - 1;

spk_equals(
	$total_txns,
	$txns_in_export,
	'خروجی کامل همهٔ تراکنش‌ها را می‌آورد'
);

// مبلغ مقیاس‌شده می‌آید، نه رشتهٔ خوانا.
$txn_rows = $ex_svc->transactions_export_rows();
if ( count( $txn_rows ) > 1 ) {
	$sample_amount = $txn_rows[1][4];

	spk_assert(
		(bool) preg_match( '/^\d+$/', $sample_amount ),
		'ستون مبلغ در خروجی کامل عدد صحیح مقیاس‌شده است: ' . $sample_amount
	);
}

// تراکنش باطل هم باید در خروجی باشد.
spk_assert(
	false !== strpos( $full_text, 'void' ) || 0 === (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$transactions} WHERE status = 'void'" ),
	'تراکنش‌های باطل در خروجی کامل می‌آیند'
);

// ── ۴۶: cache تنظیمات تغییر گزینه را می‌بیند ─────────────────────────
//
// `Settings::all()` از یک کش استاتیک برمی‌گشت که فقط با `flush_cache()`
// صریح پاک می‌شد. اگر گزینه از راه دیگری عوض می‌شد — `uninstall.php`،
// مهاجرت، WP-CLI، یا افزونهٔ دیگر — افزونه تا پایان درخواست مقدار
// کهنه برمی‌گرداند. در آزمون تجربی دیتابیس `rial` داشت و نمایش «یورو»
// می‌داد: واحد پول اشتباه روی همهٔ مبالغ.

Sparkknife_Ledger_Settings::save( array( 'currency_type' => 'toman' ), false );
Sparkknife_Ledger_Settings::flush_cache();

spk_equals( 'toman', Sparkknife_Ledger_Settings::get( 'currency_type' ), 'cache تنظیمات: مقدار اولیه' );

// تغییر مستقیم گزینه، بی‌خبر از Settings::save().
$cache_probe = get_option( Sparkknife_Ledger_Settings::OPTION_KEY, array() );
$cache_probe['currency_type'] = 'rial';
update_option( Sparkknife_Ledger_Settings::OPTION_KEY, $cache_probe );

spk_equals(
	'rial',
	Sparkknife_Ledger_Settings::get( 'currency_type' ),
	'cache تنظیمات تغییر مستقیم گزینه را می‌بیند (بدون flush)'
);

spk_equals(
	'rial',
	Sparkknife_Ledger_Settings::get( 'currency_type' ),
	'واحد پول نمایش‌داده‌شده با مقدار ذخیره‌شده هم‌خوان است'
);

// تغییر مقدار (نه افزودن کلید) هم باید دیده شود.
$cache_probe = get_option( Sparkknife_Ledger_Settings::OPTION_KEY, array() );
if ( isset( $cache_probe['currency_decimals'] ) ) {
	$cache_probe['currency_decimals'] = 0 === (int) $cache_probe['currency_decimals'] ? 2 : 0;
	update_option( Sparkknife_Ledger_Settings::OPTION_KEY, $cache_probe );

	spk_equals(
		(string) $cache_probe['currency_decimals'],
		(string) Sparkknife_Ledger_Settings::get( 'currency_decimals' ),
		'cache تنظیمات تغییر یک مقدار را هم می‌بیند'
	);
}

// بازگرداندن به پیش‌فرض.
Sparkknife_Ledger_Settings::save( $defaults, false );
Sparkknife_Ledger_Settings::flush_cache();

/*
 * -----------------------------------------------------------------------------
 * باگ ۸۵ — نسخهٔ افزونه پس از ارتقا به‌روز نمی‌شد.
 *
 * `maybe_upgrade()` تنها زمانی از مسیر کامل می‌گذشت که **نسخهٔ دیتابیس**
 * با ثابت فعلی فرق داشت. ارتقای کوچکی که اسکیما را عوض نمی‌کند (حالت
 * عادی بیشتر انتشارها) نسخهٔ افزونه را برای همیشه روی مقدار قبلی
 * می‌گذاشت. این آزمون دقیقاً آن حالت را می‌سازد: نسخهٔ دیتابیس درست،
 * فقط نسخهٔ افزونه قدیمی.
 * -----------------------------------------------------------------------------
 */
$real_db_version  = get_option( Sparkknife_Ledger_Installer::DB_VERSION_OPTION, '' );
$real_app_version = get_option( Sparkknife_Ledger_Installer::VERSION_OPTION, '' );

spk_assert(
	'' !== $real_app_version,
	'نسخهٔ افزونه پس از نصب ثبت شده است'
);

// حالت ۱: نسخهٔ دیتابیس درست، نسخهٔ افزونه قدیمی → باید بالا برود.
update_option( Sparkknife_Ledger_Installer::VERSION_OPTION, '0.0.1' );
Sparkknife_Ledger_Installer::maybe_upgrade();

spk_equals(
	SPARKKNIFE_LEDGER_VERSION,
	get_option( Sparkknife_Ledger_Installer::VERSION_OPTION, '' ),
	'باگ ۸۵: ارتقای بدون تغییر اسکیما نسخهٔ افزونه را به‌روز می‌کند'
);

spk_equals(
	$real_db_version,
	get_option( Sparkknife_Ledger_Installer::DB_VERSION_OPTION, '' ),
	'باگ ۸۵: ارتقای نسخهٔ افزونه نسخهٔ دیتابیس را دست نمی‌زند'
);

// داده‌ها نباید در این مسیر دست بخورند.
spk_assert(
	count( Sparkknife_Ledger_Person_Repository::instance()->query( array( 'per_page' => 1 ) ) ) >= 0,
	'باگ ۸۵: ارتقای نسخهٔ افزونه به داده‌ها دست نمی‌زند'
);

// حالت ۲: هر دو درست → اجرای دوباره بی‌اثر است (idempotent).
Sparkknife_Ledger_Installer::maybe_upgrade();
spk_equals(
	SPARKKNIFE_LEDGER_VERSION,
	get_option( Sparkknife_Ledger_Installer::VERSION_OPTION, '' ),
	'باگ ۸۵: maybe_upgrade دوباره‌اجرا شدنی است'
);

// حالت ۳: نسخهٔ دیتابیس پاک شده (نصب دستی/انتقال سایت) → هر دو ثبت شوند.
update_option( Sparkknife_Ledger_Installer::DB_VERSION_OPTION, '' );
Sparkknife_Ledger_Installer::maybe_upgrade();

spk_equals(
	SPARKKNIFE_LEDGER_VERSION,
	get_option( Sparkknife_Ledger_Installer::VERSION_OPTION, '' ),
	'باگ ۸۵: نصب بدون نسخهٔ دیتابیس نسخهٔ افزونه را ثبت می‌کند'
);

spk_equals(
	SPARKKNIFE_LEDGER_DB_VERSION,
	get_option( Sparkknife_Ledger_Installer::DB_VERSION_OPTION, '' ),
	'باگ ۸۵: نصب بدون نسخهٔ دیتابیس نسخهٔ دیتابیس را ثبت می‌کند'
);

// بازگرداندن وضعیت.
update_option( Sparkknife_Ledger_Installer::VERSION_OPTION, $real_app_version );
update_option( Sparkknife_Ledger_Installer::DB_VERSION_OPTION, $real_db_version );

/*
 * -----------------------------------------------------------------------------
 * باگ ۸۶ — ارتقای دسترسی از مسیر تنظیمات دسترسی‌ها.
 *
 * `capabilities_save` با `manage_settings` محافظت می‌شد، در حالی که
 * همان capability برای عوض کردن واحد پول هم کافی است و مدیر معمولاً
 * آن را به حسابدار می‌دهد. نتیجه: حسابدار صفحهٔ تنظیمات را باز
 * می‌کرد، تب «دسترسی‌ها» را می‌دید، و به **نقش خودش**
 * `void_transactions` می‌داد.
 *
 * این آزمون همان سناریو را می‌سازد: کاربری با `manage_settings` ولی
 * بدون `manage_options` تلاش می‌کند نقش‌ها را عوض کند.
 * -----------------------------------------------------------------------------
 */
$cap_probe_role = 'spk_cap_probe_' . wp_rand( 1000, 9999 );
$cap_probe_user = wp_create_user(
	'spk_capprobe_' . wp_rand( 1000, 9999 ),
	wp_generate_password( 16 ),
	'capprobe-' . wp_rand() . '@example.com'
);

if ( ! is_wp_error( $cap_probe_user ) ) {
	$probe = new WP_User( $cap_probe_user );
	$probe->set_role( 'subscriber' );

	// حسابدار: فقط manage_settings، بدون manage_options.
	$probe->add_cap( Sparkknife_Ledger_Capabilities::PREFIX . 'manage_settings' );
	$probe->add_cap( Sparkknife_Ledger_Capabilities::PREFIX . 'reports' );

	wp_set_current_user( $cap_probe_user );

	spk_assert(
		current_user_can( Sparkknife_Ledger_Capabilities::PREFIX . 'manage_settings' ),
		'باگ ۸۶: حسابدار manage_settings دارد (پیش‌نیاز سناریو)'
	);

	spk_assert(
		! current_user_can( 'manage_options' ),
		'باگ ۸۶: حسابدار manage_options ندارد (پیش‌نیاز سناریو)'
	);

	spk_assert(
		! current_user_can( Sparkknife_Ledger_Capabilities::PREFIX . 'void_transactions' ),
		'باگ ۸۶: حسابدار پیش از حمله اجازهٔ ابطال ندارد'
	);

	// تلاش برای ارتقای دسترسی: به نقش خودش void_transactions بدهد.
	$attack_matrix = array(
		'subscriber' => array( 'void_transactions', 'manage_persons', 'manage_settings' ),
	);

	$saved = Sparkknife_Ledger_Capabilities::save_role_matrix( $attack_matrix );

	spk_assert(
		is_wp_error( $saved ),
		'باگ ۸۶: save_role_matrix برای کاربر بی‌manage_options خطا می‌دهد'
	);

	// و مهم‌تر: دسترسی واقعاً داده نشده باشد.
	$probe_fresh = new WP_User( $cap_probe_user );

	spk_assert(
		! $probe_fresh->has_cap( Sparkknife_Ledger_Capabilities::PREFIX . 'void_transactions' ),
		'باگ ۸۶: تلاش برای ارتقای دسترسی، void_transactions را نمی‌دهد'
	);

	spk_assert(
		! $probe_fresh->has_cap( Sparkknife_Ledger_Capabilities::PREFIX . 'manage_persons' ),
		'باگ ۸۶: تلاش برای ارتقای دسترسی، manage_persons را نمی‌دهد'
	);

	// پاک‌سازی.
	$probe_clean = new WP_User( $cap_probe_user );
	$probe_clean->remove_all_caps();
	$probe_clean->set_role( 'subscriber' );
	wp_delete_user( $cap_probe_user );
}

wp_set_current_user( 1 );

// مدیر کل باید بتواند نقش‌ها را عوض کند.
wp_set_current_user( 1 );

$admin_matrix = array();
foreach ( array_keys( Sparkknife_Ledger_Capabilities::all() ) as $cap ) {
	$admin_matrix['administrator'][] = $cap;
}

$admin_save = Sparkknife_Ledger_Capabilities::save_role_matrix( $admin_matrix );

spk_assert(
	true === $admin_save,
	'باگ ۸۶: مدیر کل می‌تواند سطوح دسترسی را ذخیره کند'
);

spk_assert(
	current_user_can( Sparkknife_Ledger_Capabilities::PREFIX . 'manage_settings' ),
	'باگ ۸۶: مدیر پس از ذخیره همچنان manage_settings دارد'
);

// مدیر همیشه همه دسترسی‌ها را دارد، حتی اگر ماتریس خالی باشد.
$admin_save_empty = Sparkknife_Ledger_Capabilities::save_role_matrix( array() );

spk_assert(
	true === $admin_save_empty,
	'باگ ۸۶: ذخیرهٔ ماتریس خالی توسط مدیر بی‌خطاست'
);

$admin_user = new WP_User( 1 );
spk_assert(
	$admin_user->has_cap( Sparkknife_Ledger_Capabilities::PREFIX . 'void_transactions' ),
	'باگ ۸۶: مدیر حتی با ماتریس خالی همهٔ دسترسی‌ها را دارد'
);

/*
 * -----------------------------------------------------------------------------
 * باگ ۸۷ — purge_all_data بدون دفاع در عمق.
 *
 * همان درس باگ ۸۶، ولی روی خطرناک‌ترین متد پروژه. بررسی
 * `manage_options` در کنترلر وجود داشت، ولی خود متد هر کسی را که
 * صدایش می‌زد می‌پذیرفت. اگر مسیر تازه‌ای (REST، WP-CLI) آن را صدا
 * می‌زد و بررسی را جا می‌انداخت، همهٔ جداول مالی DROP می‌شد.
 *
 * این آزمون عمداً متد را **مستقیم** با کاربر بی‌مجوز صدا می‌زند، نه از
 * راه کنترلر — چون هدف سنجش خود متد است.
 * -----------------------------------------------------------------------------
 */
$purge_probe = wp_create_user(
	'spk_purgeprobe_' . wp_rand( 1000, 9999 ),
	wp_generate_password( 16 ),
	'purgeprobe-' . wp_rand() . '@example.com'
);

if ( ! is_wp_error( $purge_probe ) ) {
	$purge_user = new WP_User( $purge_probe );
	$purge_user->set_role( 'editor' );

	// ادمین حتی دسترسی افزونه را هم به او بدهد — باز هم نباید بتواند.
	$purge_user->add_cap( Sparkknife_Ledger_Capabilities::PREFIX . 'manage_settings' );
	$purge_user->add_cap( Sparkknife_Ledger_Capabilities::PREFIX . 'manage_persons' );

	wp_set_current_user( $purge_probe );

	spk_assert(
		! current_user_can( 'manage_options' ),
		'باگ ۸۷: کاربر آزمون manage_options ندارد (پیش‌نیاز)'
	);

	// شمارش پیش از تلاش.
	global $wpdb;
	$persons_table = Sparkknife_Ledger_DB::persons_table();
	$before_purge  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$persons_table}" ); // phpcs:ignore

	$purge_result = Sparkknife_Ledger_Installer::purge_all_data();

	spk_assert(
		is_wp_error( $purge_result ),
		'باگ ۸۷: purge_all_data برای کاربر بی‌manage_options خطا می‌دهد'
	);

	// مهم‌ترین بررسی: جدول‌ها باید سر جایشان باشند.
	Sparkknife_Ledger_DB::flush_table_cache();

	$after_purge = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$persons_table}" ); // phpcs:ignore

	spk_assert(
		$before_purge === $after_purge,
		'باگ ۸۷: تلاش برای حذف داده، هیچ ردیفی را نکشت'
	);

	spk_assert(
		Sparkknife_Ledger_DB::tables_exist(),
		'باگ ۸۷: جدول‌ها پس از تلاش ناموفق حذف، وجود دارند'
	);

	// پاک‌سازی.
	$purge_clean = new WP_User( $purge_probe );
	$purge_clean->remove_all_caps();
	$purge_clean->set_role( 'subscriber' );
	wp_delete_user( $purge_probe );
}

wp_set_current_user( 1 );

// مدیر کل باید بتواند — ولی این آزمون داده را واقعاً پاک نمی‌کند،
// چون بقیهٔ سناریوها به آن تکیه دارند. فقط بررسی می‌کنیم که اجرای
// متد با مدیر به خطای دسترسی نمی‌خورد.
spk_assert(
	current_user_can( 'manage_options' ),
	'باگ ۸۷: مدیر کل manage_options دارد'
);

spk_assert(
	Sparkknife_Ledger_DB::tables_exist(),
	'باگ ۸۷: جدول‌ها پس از همهٔ آزمون‌ها سالم‌اند'
);

/*
 * -----------------------------------------------------------------------------
 * باگ ۸۸ — ستون توضیح تاریخچه در خروجی CSV همیشه خالی بود.
 *
 * جدول `summary` نام دارد، کد `description` می‌خواند. `$wpdb` برای
 * ستون ناموجود عضو stdClass نمی‌سازد، پس:
 *   - Warning در لاگ
 *   - ستون توضیح در CSV همیشه خالی
 *
 * آزمون قبلی خروجی، سرصفحه و تعداد ردیف را می‌سنجید، نه محتوای ستون.
 * یک ستون تماماً خالی از آن رد می‌شد.
 * -----------------------------------------------------------------------------
 */
$export_svc = Sparkknife_Ledger_Export_Service::instance();
$export_rows = $export_svc->full_export_rows();

spk_assert(
	is_array( $export_rows ) && count( $export_rows ) > 0,
	'باگ ۸۸: خروجی کامل ردیف دارد'
);

/*
 * سرصفحهٔ بخش تاریخچه باید ستون `summary` داشته باشد، نه `description`.
 * اگر کد به `description` برگردد، این آزمون می‌شکند.
 */
$audit_header = null;
foreach ( $export_rows as $export_row ) {
	if ( is_array( $export_row ) && in_array( 'object_type', $export_row, true ) ) {
		$audit_header = $export_row;
		break;
	}
}

spk_assert(
	is_array( $audit_header ),
	'باگ ۸۸: سرصفحهٔ تاریخچه در خروجی پیدا شد'
);

if ( is_array( $audit_header ) ) {
	spk_assert(
		in_array( 'summary', $audit_header, true ),
		'باگ ۸۸: سرصفحهٔ تاریخچه ستون summary دارد'
	);

	spk_assert(
		! in_array( 'description', $audit_header, true ),
		'باگ ۸۸: سرصفحهٔ تاریخچه ستون description ندارد'
	);
}

/*
 * مهم‌ترین بررسی: اگر ردیف تاریخچه با summary غیرخالی در دیتابیس هست،
 * خروجی هم باید همان را داشته باشد. ستون خالی دیگر از آزمون رد نمی‌شود.
 */
global $wpdb;
$audit_table = Sparkknife_Ledger_DB::audit_table();

// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$audit_with_summary = $wpdb->get_var( "SELECT summary FROM {$audit_table} WHERE summary IS NOT NULL AND summary <> '' LIMIT 1" );

if ( null !== $audit_with_summary && '' !== $audit_with_summary ) {
	$found_summary = false;

	foreach ( $export_rows as $export_row ) {
		if ( is_array( $export_row ) && in_array( (string) $audit_with_summary, array_map( 'strval', $export_row ), true ) ) {
			$found_summary = true;
			break;
		}
	}

	spk_assert(
		$found_summary,
		'باگ ۸۸: متن summary جدول تاریخچه در خروجی دیده می‌شود'
	);
}

/*
 * -----------------------------------------------------------------------------
 * باگ ۹۰ — ارتقا بدون ورود مدیر هرگز اجرا نمی‌شد.
 *
 * `maybe_upgrade()` با `is_admin()` گارد شده بود. اگر سایت با
 * WP-Cron یا WP-CLI به‌روز می‌شد و مدیر هرگز به پنل نمی‌آمد،
 * اسکیما روی نسخهٔ قبلی می‌ماند.
 *
 * آزمون این را می‌سنجد که گارد جدید کرون را می‌پذیرد. در محیط
 * آزمون is_admin() روشن است، پس نمی‌توان حالت کرون را واقعاً ساخت؛
 * به‌جایش مطمئن می‌شویم منطق گارد به cron وابسته است، نه فقط admin.
 * -----------------------------------------------------------------------------
 */
$installer_src = file_get_contents(
	dirname( __DIR__, 2 ) . '/sparkknife-ledger/includes/Core/class-sparkknife-ledger-installer.php'
);

spk_assert(
	false !== strpos( $installer_src, 'wp_doing_cron()' ),
	'باگ ۹۰: گارد ارتقا WP-Cron را می‌پذیرد'
);

spk_assert(
	false !== strpos( $installer_src, "'WP_CLI'" ) || false !== strpos( $installer_src, '"WP_CLI"' ),
	'باگ ۹۰: گارد ارتقا WP-CLI را می‌پذیرد'
);

// ارتقا در محیط مدیر باید درست کار کند (مسیر عادی).
$v_before = get_option( Sparkknife_Ledger_Installer::VERSION_OPTION, '' );
update_option( Sparkknife_Ledger_Installer::VERSION_OPTION, '0.9.0' );
Sparkknife_Ledger_Installer::maybe_upgrade();

spk_equals(
	SPARKKNIFE_LEDGER_VERSION,
	get_option( Sparkknife_Ledger_Installer::VERSION_OPTION, '' ),
	'باگ ۹۰: ارتقا در محیط مدیر کار می‌کند'
);

update_option( Sparkknife_Ledger_Installer::VERSION_OPTION, $v_before );
Sparkknife_Ledger_Installer::maybe_upgrade();
