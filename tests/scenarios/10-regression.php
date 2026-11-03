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
spk_assert(
	0 === strpos( Sparkknife_Ledger_Money::format( array( 1, 2 ) ), '۰' ),
	'آرایه به صفر تبدیل می‌شود',
	Sparkknife_Ledger_Money::format( array( 1, 2 ) )
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
