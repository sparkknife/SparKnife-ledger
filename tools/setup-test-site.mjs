#!/usr/bin/env node
/**
 * راه‌اندازی یک سایت وردپرس آزمایشی کامل با SQLite، بدون نیاز به PHP.
 *
 * چه می‌کند:
 *   ۱) وردپرس را از مخزن رسمی گیت‌هاب می‌گیرد
 *   ۲) درایور SQLite را کنار آن می‌گذارد
 *   ۳) wp-config.php می‌سازد
 *   ۴) افزونه را کپی می‌کند
 *   ۵) وردپرس را نصب و افزونه را فعال می‌کند
 *
 * چرا SQLite: نصب MySQL روی ماشین توسعه لازم نیست. افزونه با هر دو
 * کار می‌کند و این برای آزمون کافی است. (برای آزمون نهایی، MySQL
 * واقعی روی هاست خودتان.)
 *
 * استفاده:
 *   node tools/setup-test-site.mjs /tmp/wpsite
 *   node tools/setup-test-site.mjs /tmp/wpsite --wp 6.8.3
 *
 * سپس هر اسکریپت PHP را با ابزار php-wasm اجرا کنید:
 *   node tools/php-wasm.mjs /tmp/wpsite/wp-load.php ...
 *
 * ⚠️ این یک محیط آزمایشی است، نه تولیدی. رمز پیش‌فرض password123.
 */
import { loadNodeRuntime, useHostFilesystem } from '@php-wasm/node';
import { PHP } from '@php-wasm/universal';
import { execSync } from 'child_process';
import fs from 'fs';
import path from 'path';

const target = process.argv[ 2 ] || '/tmp/wpsite';
const wpArg = process.argv.indexOf( '--wp' );
const wpVersion = wpArg !== -1 && process.argv[ wpArg + 1 ] ? process.argv[ wpArg + 1 ] : '6.8.3';

const log = ( m ) => console.log( '→ ' + m );

if ( fs.existsSync( path.join( target, 'wp-load.php' ) ) ) {
	console.log( 'سایت از قبل هست: ' + target );
	console.log( 'برای ساخت تازه، پوشه را پاک کنید:  rm -rf ' + target );
	process.exit( 0 );
}

fs.mkdirSync( target, { recursive: true } );

// ۱) وردپرس
log( `دریافت وردپرس ${ wpVersion } …` );
const tar = '/tmp/__wp.tar.gz';
execSync( `curl -sSL -o ${ tar } "https://codeload.github.com/WordPress/WordPress/tar.gz/refs/tags/${ wpVersion }"`, { stdio: 'inherit' } );
execSync( `tar xzf ${ tar } -C ${ target } --strip-components=1` );
log( 'وردپرس استخراج شد' );

// ۲) SQLite
log( 'دریافت درایور SQLite …' );
const zip = '/tmp/__sqlite.zip';
const sqDir = '/tmp/__sq';
execSync( `curl -sSL -o ${ zip } "https://codeload.github.com/WordPress/sqlite-database-integration/zip/refs/heads/main"` );
fs.rmSync( sqDir, { recursive: true, force: true } );
execSync( `unzip -q ${ zip } -d ${ sqDir }` );

const sqSrc = path.join( sqDir, 'sqlite-database-integration-main' );
const plugins = path.join( target, 'wp-content', 'plugins' );
fs.mkdirSync( plugins, { recursive: true } );
execSync( `cp -r ${ JSON.stringify( sqSrc ) } ${ JSON.stringify( path.join( plugins, 'sqlite-database-integration' ) ) }` );

// drop-in
let dbDrop = fs.readFileSync( path.join( sqSrc, 'db.copy' ), 'utf8' );
dbDrop = dbDrop.replace( '{SQLITE_IMPLEMENTATION_FOLDER_PATH}', path.join( plugins, 'sqlite-database-integration' ) );
fs.writeFileSync( path.join( target, 'wp-content', 'db.php' ), dbDrop );
log( 'درایور SQLite نصب شد' );

// ۳) wp-config
fs.writeFileSync( path.join( target, 'wp-config.php' ), `<?php
define( 'DB_NAME', 'wordpress' );
define( 'DB_USER', '' );
define( 'DB_PASSWORD', '' );
define( 'DB_HOST', 'localhost' );
define( 'DB_CHARSET', 'utf8mb4' );
define( 'DB_COLLATE', '' );
define( 'AUTH_KEY', 'spk-test-1' );
define( 'SECURE_AUTH_KEY', 'spk-test-2' );
define( 'LOGGED_IN_KEY', 'spk-test-3' );
define( 'NONCE_KEY', 'spk-test-4' );
define( 'AUTH_SALT', 'spk-salt-1' );
define( 'SECURE_AUTH_SALT', 'spk-salt-2' );
define( 'LOGGED_IN_SALT', 'spk-salt-3' );
define( 'NONCE_SALT', 'spk-salt-4' );
$table_prefix = 'wp_';
define( 'WP_DEBUG', false );
define( 'WP_HOME', 'http://localhost' );
define( 'WP_SITEURL', 'http://localhost' );
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', __DIR__ . '/' ); }
require_once ABSPATH . 'wp-settings.php';
` );
fs.mkdirSync( path.join( target, 'wp-content', 'uploads' ), { recursive: true } );
log( 'wp-config ساخته شد' );

// ۴) افزونه
const root = path.resolve( path.dirname( new URL( import.meta.url ).pathname ), '..' );
execSync( `cp -a ${ JSON.stringify( path.join( root, 'sparkknife-ledger' ) ) } ${ JSON.stringify( plugins ) }` );
log( 'افزونه کپی شد' );

// ۵) نصب + فعال‌سازی
log( 'نصب وردپرس …' );
const php = new PHP( await loadNodeRuntime( '8.2', { emscriptenOptions: { processId: 1 } } ) );
useHostFilesystem( php );

const install = `<?php
define( 'WP_INSTALLING', true );
define( 'WP_ADMIN', true );
require '${ target }/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/upgrade.php';
if ( ! is_blog_installed() ) {
	wp_install( 'SparKnife Test', 'admin', 'admin@example.com', true, '', 'password123' );
}
require_once ABSPATH . 'wp-admin/includes/plugin.php';
$r = activate_plugin( 'sparkknife-ledger/sparkknife-ledger.php' );
if ( is_wp_error( $r ) ) { echo "خطای فعال‌سازی: " . $r->get_error_message() . "\\n"; exit( 1 ); }
echo "وردپرس: " . get_bloginfo( 'version' ) . "\\n";
echo "PHP: " . PHP_VERSION . "\\n";
echo "افزونه: " . get_option( 'sparkknife_ledger_version' ) . "\\n";
echo "کاربر: admin / password123\\n";
`;

php.writeFile( '/tmp/__install.php', install );
const out = await php.run( { scriptPath: '/tmp/__install.php' } );
process.stdout.write( out.text || '' );
if ( out.errors && out.errors.trim() ) process.stderr.write( out.errors );

console.log( '\n✓ آماده: ' + target );
