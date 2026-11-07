#!/usr/bin/env node
/**
 * بررسی نحوی همهٔ فایل‌های PHP افزونه.
 *
 * چرا این ابزار: محیط توسعه همیشه PHP ندارد و اجرای swhir در
 * PHP-WASM شکننده است. `php-parser` یک تجزیه‌کنندهٔ کامل PHP در
 * جاوااسکریپت است که بدون وابستگی به PHP کار می‌کند و همان خطاهای
 * نحوی `php -l` را می‌گیرد.
 *
 * نصب:  npm install php-parser
 * اجرا: node tools/lint.mjs [مسیر-دیگر]
 *
 * خروج ۰ یعنی هیچ فایل شکسته‌ای نیست.
 */
import PhpParser from 'php-parser';
import fs from 'fs';
import path from 'path';
import { execSync } from 'child_process';

const root = path.resolve(
	process.argv[ 2 ] || path.join( import.meta.dirname, '..', 'sparkknife-ledger' )
);

if ( ! fs.existsSync( root ) ) {
	console.error( 'مسیر پیدا نشد: ' + root );
	process.exit( 2 );
}

const engine = new PhpParser( {
	parser: { extractDoc: true, suppressErrors: false },
	ast: { withPositions: true },
} );

const files = execSync( 'find ' + JSON.stringify( root ) + " -name '*.php' -type f" )
	.toString()
	.trim()
	.split( '\n' )
	.filter( Boolean )
	.sort();

if ( ! files.length ) {
	console.error( 'هیچ فایل PHP پیدا نشد در ' + root );
	process.exit( 2 );
}

let errors = 0;

for ( const file of files ) {
	const rel = path.relative( root, file );
	try {
		engine.parseCode( fs.readFileSync( file, 'utf8' ), file );
	} catch ( e ) {
		errors++;
		console.log( '✗ ' + rel );
		console.log( '    ' + String( e.message ).split( '\n' )[ 0 ].slice( 0, 160 ) );
	}
}

console.log( `\n${ files.length } فایل | ${ errors } خطا` );
process.exit( errors ? 1 : 0 );
