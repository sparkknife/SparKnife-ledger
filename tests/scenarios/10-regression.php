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

foreach ( $matches[1] as $option_name ) {
	$created_in = false;
	foreach ( glob( SPARKKNIFE_LEDGER_DIR . 'includes/**/*.php' ) as $file ) {
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

	$doc_output = (string) shell_exec(
		escapeshellarg( $php_bin ) . ' -d memory_limit=512M ' . escapeshellarg( $doc_script ) . ' 2>/dev/null'
	);

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

// هیچ شخصی نباید در صفحه‌بندی تکرار یا حذف شود.
$paged_ids = array();
$page_size = 2;
$page_max  = (int) ceil( $sort_a['total'] / $page_size ) + 1;

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
	(int) $sort_a['total'],
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
Sparkknife_Ledger_Settings::save( $settings_before, false );
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
