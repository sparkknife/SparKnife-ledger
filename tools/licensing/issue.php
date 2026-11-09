#!/usr/bin/env php
<?php
/**
 * ابزار صدور کلید لایسنس SparKnife Ledger.
 *
 * این فایل **در بستهٔ افزونه نیست** و نباید باشد. فقط روی دستگاه خود
 * فروشنده اجرا می‌شود و کلید خصوصی را می‌خواند.
 *
 * ## استفاده
 *
 * ```bash
 * php tools/licensing/issue.php --domain=example.com --edition=pro
 * php tools/licensing/issue.php --domain='*.example.com' --edition=agency --expiry=2027-12-31
 * php tools/licensing/issue.php --verify=SPK1.xxx.yyy
 * ```
 *
 * ## ساخت جفت‌کلید (تنها یک بار، در آغاز)
 *
 * ```bash
 * openssl genrsa -out tools/licensing/private_key.pem 2048
 * openssl rsa -in tools/licensing/private_key.pem -pubout -out tools/licensing/public_key.pem
 * ```
 *
 * سپس کلید عمومی را در `class-sparkknife-ledger-license.php` (ثابت
 * `PUBLIC_KEY`) قرار دهید.
 *
 * @package SparKnife_Ledger
 */

if ( 'cli' !== PHP_SAPI ) {
	fwrite( STDERR, "این ابزار فقط از خط فرمان اجرا می‌شود.\n" );
	exit( 1 );
}

$base = __DIR__;

/**
 * خواندن آرگومان‌های `--name=value`.
 *
 * @return array<string, string>
 */
function spk_args() {
	$out = array();

	foreach ( array_slice( $GLOBALS['argv'], 1 ) as $arg ) {
		if ( 0 !== strpos( $arg, '--' ) ) {
			continue;
		}

		$arg = substr( $arg, 2 );
		$pos = strpos( $arg, '=' );

		if ( false === $pos ) {
			$out[ $arg ] = '1';
			continue;
		}

		$out[ substr( $arg, 0, $pos ) ] = substr( $arg, $pos + 1 );
	}

	return $out;
}

/**
 * چاپ راهنما.
 *
 * @return void
 */
function spk_help() {
	echo <<<TXT
ابزار صدور کلید لایسنس SparKnife Ledger

  --domain=DOMAIN    دامنهٔ مجاز. `*` یعنی نامحدود، `*.example.com` یعنی هر زیردامنه.
  --edition=EDITION  pro یا agency (پیش‌فرض: pro)
  --expiry=DATE      تاریخ انقضا YYYY-MM-DD (پیش‌فرض: بدون انقضا)
  --serial=SERIAL    شناسهٔ یکتا (پیش‌فرض: ساخته می‌شود)
  --verify=KEY       بررسی یک کلید موجود بدون صدور کلید تازه

نمونه:
  php issue.php --domain=example.com --edition=pro --expiry=2027-12-31
  php issue.php --verify=SPK1.xxxx.yyyy

TXT;
}

$args = spk_args();

if ( isset( $args['help'] ) || isset( $args['h'] ) ) {
	spk_help();
	exit( 0 );
}

$public_pem  = $base . '/public_key.pem';
$private_pem = $base . '/private_key.pem';

if ( ! file_exists( $public_pem ) ) {
	fwrite( STDERR, "کلید عمومی پیدا نشد: {$public_pem}\n" );
	fwrite( STDERR, "با openssl بسازید (روش در بالای همین فایل).\n" );
	exit( 1 );
}

// ── بررسی کلید موجود ────────────────────────────────────────────────
if ( isset( $args['verify'] ) ) {
	$key   = trim( $args['verify'] );
	$parts = explode( '.', $key );

	if ( 3 !== count( $parts ) || 'SPK1' !== $parts[0] ) {
		fwrite( STDERR, "شکل کلید درست نیست.\n" );
		exit( 1 );
	}

	$payload = base64_decode( strtr( $parts[1], '-_', '+/' ), true );
	$sig_raw = base64_decode( strtr( $parts[2], '-_', '+/' ), true );
	$pub     = openssl_pkey_get_public( file_get_contents( $public_pem ) );
	$result  = openssl_verify( (string) $payload, (string) $sig_raw, $pub, OPENSSL_ALGO_SHA256 );

	if ( 1 === $result ) {
		$f = explode( '|', (string) $payload );
		echo "معتبر ✓\n";
		echo "  دامنه  : {$f[0]}\n";
		echo "  انقضا   : " . ( '' !== $f[1] ? $f[1] : 'بدون انقضا' ) . "\n";
		echo "  نسخه    : {$f[2]}\n";
		echo "  سریال   : {$f[3]}\n";
		exit( 0 );
	}

	fwrite( STDERR, "امضا معتبر نیست.\n" );
	exit( 1 );
}

// ── صدور کلید تازه ──────────────────────────────────────────────────
if ( ! file_exists( $private_pem ) ) {
	fwrite( STDERR, "کلید خصوصی پیدا نشد: {$private_pem}\n" );
	exit( 1 );
}

$domain = isset( $args['domain'] ) ? strtolower( trim( $args['domain'] ) ) : '';

if ( '' === $domain ) {
	fwrite( STDERR, "دامنه لازم است: --domain=example.com  (یا --domain=*)\n" );
	spk_help();
	exit( 1 );
}

$edition = isset( $args['edition'] ) ? strtolower( trim( $args['edition'] ) ) : 'pro';

if ( ! in_array( $edition, array( 'pro', 'agency' ), true ) ) {
	fwrite( STDERR, "نسخه باید pro یا agency باشد.\n" );
	exit( 1 );
}

$expiry = isset( $args['expiry'] ) ? trim( $args['expiry'] ) : '';

if ( '' !== $expiry && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $expiry ) ) {
	fwrite( STDERR, "تاریخ انقضا باید YYYY-MM-DD باشد.\n" );
	exit( 1 );
}

$serial = isset( $args['serial'] ) ? trim( $args['serial'] ) : strtoupper( bin2hex( random_bytes( 4 ) ) );

$payload = $domain . '|' . $expiry . '|' . $edition . '|' . $serial;

$private = openssl_pkey_get_private( file_get_contents( $private_pem ) );

if ( false === $private ) {
	fwrite( STDERR, "کلید خصوصی خوانا نیست.\n" );
	exit( 1 );
}

$signature = '';

if ( ! openssl_sign( $payload, $signature, $private, OPENSSL_ALGO_SHA256 ) ) {
	fwrite( STDERR, "امضا کردن ناموفق بود.\n" );
	exit( 1 );
}

$b64 = static function ( $raw ) {
	return rtrim( strtr( base64_encode( $raw ), '+/', '-_' ), '=' );
};

$key = 'SPK1.' . $b64( $payload ) . '.' . $b64( $signature );

echo "کلید لایسنس صادر شد:\n\n";
echo $key . "\n\n";
echo "  دامنه  : {$domain}\n";
echo "  نسخه   : {$edition}\n";
echo "  انقضا  : " . ( '' !== $expiry ? $expiry : 'بدون انقضا' ) . "\n";
echo "  سریال  : {$serial}\n\n";

// بررسی خودکار بلافاصله پس از صدور.
$pub    = openssl_pkey_get_public( file_get_contents( $public_pem ) );
$verify = openssl_verify( $payload, $signature, $pub, OPENSSL_ALGO_SHA256 );

if ( 1 === $verify ) {
	echo "بررسی خودکار: معتبر ✓\n";
	exit( 0 );
}

fwrite( STDERR, "بررسی خودکار شکست خورد! کلید را استفاده نکنید.\n" );
exit( 1 );
