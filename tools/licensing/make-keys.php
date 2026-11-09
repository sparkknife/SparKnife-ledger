<?php
/**
 * ساخت جفت کلید لایسنس — بدون نیاز به openssl سیستم.
 *
 * چرا این فایل: روی ویندوز و بعضی سیستم‌ها `openssl` در PATH
 * نیست، یا نسخه‌اش قدیمی است و `genpkey` را ندارد. ولی PHP تقریباً
 * همیشه افزونهٔ openssl را همراه دارد. این اسکریپت همان کاری را
 * می‌کند که سه دستور openssl می‌کردند، با یک دستور.
 *
 * استفاده (از ریشهٔ زیپ، در ترمینال VS Code):
 *
 *   php tools/licensing/make-keys.php
 *
 * خروجی:
 *   tools/licensing/private_key.pem   ← کلید خصوصی. پشتیبان بگیرید!
 *   tools/licensing/public_key.pem    ← کلید عمومی
 *
 * و در پایان، خودش PUBLIC_KEY داخل کد را هم جایگزین می‌کند و
 * تأیید می‌گیرد. یک دستور، تمام.
 *
 * این فایل در بستهٔ افزونه نیست و نباید باشد.
 *
 * @author  SparKnife <155084046+sparkknife@users.noreply.github.com>
 *
 * @package SparKnife_Ledger
 */

/*
 * `STDERR` و `STDIN` فقط در CLI تعریف شده‌اند. اگر کسی این فایل را
 * از مرورگر یا محیط دیگری اجرا کند، به‌جای پیام روشن،
 * `Undefined constant "STDIN"` می‌گیرد — همان باگی که در
 * `tests/run-tests.php` هم بود (باگ ۸۹).
 */
if ( ! defined( 'STDERR' ) ) {
	define( 'STDERR', fopen( 'php://stderr', 'wb' ) );
}

if ( ! defined( 'STDIN' ) ) {
	define( 'STDIN', fopen( 'php://stdin', 'r' ) );
}

if ( 'cli' !== PHP_SAPI ) {
	fwrite( STDERR, "این ابزار فقط از ترمینال اجرا می‌شود.\n" );
	fwrite( STDERR, "در VS Code: منوی Terminal → New Terminal\n" );
	exit( 1 );
}

$dir = __DIR__;

echo "\n";
echo "ساخت جفت کلید SparKnife Ledger\n";
echo str_repeat( '─', 50 ) . "\n\n";

/* ۱) بررسی پیش‌نیاز */
if ( ! function_exists( 'openssl_pkey_new' ) ) {
	fwrite( STDERR, "✘ افزونهٔ OpenSSL در PHP فعال نیست.\n\n" );
	fwrite( STDERR, "  در php.ini خط زیر را از حالت توضیح خارج کنید:\n" );
	fwrite( STDERR, "    extension=openssl\n\n" );
	fwrite( STDERR, "  مسیر php.ini را با این دستور پیدا کنید:\n" );
	fwrite( STDERR, "    php --ini\n\n" );
	exit( 1 );
}

echo "✓ افزونهٔ OpenSSL در PHP فعال است\n";

/* ۲) محافظت: کلید موجود را بی‌اجازه بازنویسی نکن */
$priv_path = $dir . '/private_key.pem';

if ( file_exists( $priv_path ) ) {
	echo "\n⚠️  کلید خصوصی از قبل وجود دارد:\n";
	echo "   $priv_path\n\n";
	echo "   اگر بازنویسی شود، **همهٔ لایسنس‌هایی که با آن صادر کرده‌اید باطل می‌شوند.**\n\n";
	echo "   ادامه می‌دهید؟ برای تأیید بنویسید «بله»: ";

	$answer = trim( (string) fgets( STDIN ) );

	if ( 'بله' !== $answer ) {
		echo "\nمتوقف شد. کلید قبلی دست‌نخورده ماند.\n";
		exit( 0 );
	}

	echo "\n";
}

/* ۳) ساخت کلید */
echo "→ ساخت کلید ۳۰۷۲ بیتی (چند ثانیه طول می‌کشد) … ";

/*
 * فایل config کمینه.
 *
 * روی PHP-openssl معمولی، `openssl_pkey_new()` بدون config کار می‌کند.
 * ولی در محیط‌های محدود (WASM، کانتینر بدون /etc/ssl/openssl.cnf،
 * یا سروری که HOME ندارد) خطای گنگ
 * «system library:fopen:Permission denied» می‌دهد و کاربر نمی‌داند
 * چه کار کند. با یک config صریح، در همه‌جا کار می‌کند.
 */
$cfg = tempnam( sys_get_temp_dir(), 'spk_ssl_' );

if ( false !== $cfg ) {
	file_put_contents( $cfg, "[ req ]\ndistinguished_name = req_distinguished_name\n[ req_distinguished_name ]\n" );
}

$res = openssl_pkey_new(
	array(
		'private_key_bits' => 3072,
		'private_key_type' => OPENSSL_KEYTYPE_RSA,
		'config'           => false === $cfg ? null : $cfg,
	)
);

if ( false === $res ) {
	echo "✘\n";
	fwrite( STDERR, "خطا در ساخت کلید: " . openssl_error_string() . "\n" );
	exit( 1 );
}

echo "✓\n";

/*
 * `openssl_pkey_export()` هم همان مشکل config را دارد و اگر config
 * نگیرد، رشتهٔ خالی برمی‌گرداند — بی‌هیچ خطایی. پیش‌تر همین اتفاق
 * افتاد: کلید ساخته شد ولی «رشتهٔ خالی» ذخیره شد و بخش پشتیبان
 * هیچ چاپ نکرد. کاربر فایل صفر بایتی می‌گرفت و نمی‌فهمید چرا.
 */
$exported = openssl_pkey_export(
	$res,
	$private_pem,
	null,
	false === $cfg ? array() : array( 'config' => $cfg )
);

if ( ! $exported || '' === trim( (string) $private_pem ) ) {
	fwrite( STDERR, "✘ خروجی کلید خصوصی خالی است.\n" );
	fwrite( STDERR, "  افزونهٔ OpenSSL ناقص نصب شده. نسخهٔ PHP را به‌روز کنید.\n" );
	exit( 1 );
}
$details   = openssl_pkey_get_details( $res );
$public_pem = $details['key'];

/* ۴) ذخیره */
file_put_contents( $priv_path, $private_pem );
chmod( $priv_path, 0600 );

file_put_contents( $dir . '/public_key.pem', $public_pem );

echo "✓ کلید خصوصی  → tools/licensing/private_key.pem\n";
echo "✓ کلید عمومی  → tools/licensing/public_key.pem\n";

/* ۵) اثر انگشت */
$fp = openssl_digest( $public_pem, 'sha256' );
echo "\nاثر انگشت (SHA-256):\n";
echo "  $fp\n";
echo "\nاین را یادداشت کنید. در آینده با همین می‌فهمید کلید عوض شده یا نه.\n";

/* ۶) جایگزینی PUBLIC_KEY در کد */
echo "\n" . str_repeat( '─', 50 ) . "\n";
echo "→ جایگزینی PUBLIC_KEY در کد …\n";

$license_file = dirname( $dir, 2 ) . '/sparkknife-ledger/includes/Core/class-sparkknife-ledger-license.php';

if ( ! file_exists( $license_file ) ) {
	fwrite( STDERR, "✘ فایل افزونه پیدا نشد:\n   $license_file\n\n" );
	fwrite( STDERR, "  این اسکریپت باید از ریشهٔ پوشهٔ استخراج‌شده اجرا شود.\n" );
	exit( 1 );
}

$source = file_get_contents( $license_file );

if ( false === strpos( $source, 'const PUBLIC_KEY' ) ) {
	fwrite( STDERR, "✘ ثابت PUBLIC_KEY در فایل افزونه پیدا نشد.\n" );
	exit( 1 );
}

$pem_escaped = str_replace( '\\', '\\\\', trim( $public_pem ) );
$pem_escaped = str_replace( "'", "\\'", $pem_escaped );

$updated = preg_replace(
	"/const PUBLIC_KEY = '.*?';/s",
	"const PUBLIC_KEY = '" . $pem_escaped . "';",
	$source,
	1
);

if ( null === $updated || $updated === $source ) {
	fwrite( STDERR, "✘ جایگزینی انجام نشد — ساختار فایل عوض شده.\n" );
	exit( 1 );
}

file_put_contents( $license_file, $updated );

/* ۷) تأیید عملی: امضا بزن و با کلیدی که داخل کد نشسته بررسی کن */
$check_src = file_get_contents( $license_file );

if ( ! preg_match( "/const PUBLIC_KEY = '(.*?)';/s", $check_src, $m ) ) {
	fwrite( STDERR, "✘ کلید تازه در فایل خوانده نشد.\n" );
	exit( 1 );
}

$from_code = stripslashes( $m[1] );
$probe     = 'sparkknife-self-test';

openssl_sign( $probe, $sig, $res, OPENSSL_ALGO_SHA256 );
$pub_from_code = openssl_pkey_get_public( $from_code );
$verified      = $pub_from_code ? openssl_verify( $probe, $sig, $pub_from_code, OPENSSL_ALGO_SHA256 ) : -1;

if ( 1 !== $verified ) {
	fwrite( STDERR, "✘ تأیید ناموفق: کلیدی که در کد نشست با کلید خصوصی جفت نیست.\n" );
	fwrite( STDERR, "  نتیجهٔ openssl_verify: $verified (باید 1 باشد)\n" );
	exit( 1 );
}

echo "✓ PUBLIC_KEY جایگزین شد\n";
echo "✓ تأیید: امضای آزمایشی با کلید داخل کد بررسی شد و معتبر بود\n";

/* ۸) هشدار پشتیبان */
echo "\n" . str_repeat( '─', 50 ) . "\n";
echo "\n⚠️  همین حالا پشتیبان بگیرید\n\n";
echo "   محتوای کلید خصوصی:\n\n";
echo $private_pem;
echo "\n";
echo "   کل این متن را (از BEGIN تا END) در دو جا ذخیره کنید:\n";
echo "     ۱) گاوصندوق رمز (1Password / Bitwarden)\n";
echo "     ۲) یک USB یا پرینت در جای امن\n\n";
echo "   اگر این کلید گم شود، همهٔ لایسنس‌های صادرشده باطل می‌شوند.\n\n";
echo str_repeat( '─', 50 ) . "\n";
echo "✓ آماده. گام بعد: ساخت بستهٔ تازه (گام ۳ در FROM-ZIP-TO-LIVE.md)\n\n";
