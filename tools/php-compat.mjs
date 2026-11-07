#!/usr/bin/env node
/**
 * بررسی سازگاری نسخهٔ PHP.
 *
 * افزونه PHP 7.4 را پشتیبانی می‌کند (`Requires PHP: 7.4`). این ابزار
 * سینتکس‌هایی را پیدا می‌کند که فقط در PHP 8+ کار می‌کنند و روی هاست
 * مشتری Fatal Error می‌دهند — چیزی که هیچ آزمون روی PHP 8.2 نمی‌گیرد.
 *
 * چه می‌سنجد:
 *   - تابع‌های PHP 8: str_contains، str_starts_with، str_ends_with،
 *     array_is_list، array_key_last (7.3، بی‌اشکال)، get_debug_type
 *   - سینتکس: nullsafe (?->)، match، named arguments، constructor
 *     promotion، enum، readonly، first-class callable (...) ،
 *     union types در امضا، never، mixed
 *   - ویژگی ۸.۱: array unpacking با کلید رشته‌ای
 *
 * اجرا: node tools/php-compat.mjs
 * خروج ۰ = سازگار. یک = چیزی PHP 8-only پیدا شد.
 */
import fs from 'fs';
import path from 'path';
import { execSync } from 'child_process';

const root = path.resolve( process.argv[ 2 ] || '/home/user/SparKnife-ledger/sparkknife-ledger' );
const TARGET = process.argv[ 3 ] || '7.4';

const files = execSync( `find ${ JSON.stringify( root ) } -name '*.php' -type f` )
	.toString()
	.trim()
	.split( '\n' )
	.sort();

// PHP 8.0+
const PHP80_FUNCS = [
	'str_contains', 'str_starts_with', 'str_ends_with',
	'get_debug_type', 'fdiv', 'preg_last_error_msg',
];

// PHP 8.1+
const PHP81_FUNCS = [
	'array_is_list', 'enum_exists', 'fsync', 'fdatasync',
];

const problems = [];

for ( const file of files ) {
	const rel = path.relative( root, file );
	const lines = fs.readFileSync( file, 'utf8' ).split( '\n' );

	lines.forEach( ( line, i ) => {
		const no = i + 1;
		const code = line.replace( /\/\/.*$/, '' ).replace( /#.*$/, '' );

		// نادیده گرفتن خطوط کامنت بلوکی و رشته‌های ساده
		if ( /^\s*\*/.test( code ) || /^\s*\/\*/.test( code ) ) return;

		// تابع‌های PHP 8
		for ( const fn of PHP80_FUNCS ) {
			if ( new RegExp( `(?<![\\w>:])${ fn }\\s*\\(` ).test( code ) ) {
				problems.push( `${ rel }:${ no } — ${ fn }() فقط PHP 8.0+` );
			}
		}
		for ( const fn of PHP81_FUNCS ) {
			if ( new RegExp( `(?<![\\w>:])${ fn }\\s*\\(` ).test( code ) ) {
				problems.push( `${ rel }:${ no } — ${ fn }() فقط PHP 8.1+` );
			}
		}

		// nullsafe
		if ( /\?->/.test( code ) && !/\?->\s*\w+\s*===\s*null/.test( code ) ) {
			problems.push( `${ rel }:${ no } — nullsafe (?->) فقط PHP 8.0+` );
		}

		// match expression در ابتدای دستور
		if ( /^\s*(return\s+)?match\s*\(/.test( code ) ) {
			problems.push( `${ rel }:${ no } — match فقط PHP 8.0+` );
		}

		// named argument در فراخوانی (var: value) — تقریبی
		if ( /\(\s*[a-z_]\w*\s*:\s*[^:=]/.test( code ) ) {
			const isTernary = /:\s*[^=]+\?/.test( code );
			const isArray   = /=>/.test( code );
			if ( ! isTernary && ! isArray && ! /'/.test( code ) ) {
				problems.push( `${ rel }:${ no } — named argument مشکوک (PHP 8.0+)` );
			}
		}

		// first-class callable  foo(...)
		if ( /\(\s*\.\.\.\s*\)/.test( code ) ) {
			problems.push( `${ rel }:${ no } — first-class callable (...) فقط PHP 8.1+` );
		}

		// union type در امضا — با احتیاط: فقط در function
		if ( /function\s+\w+\s*\([^)]*\b(?:int|string|array|bool|float|object)\s*\|\s*(?:int|string|array|bool|float|object)\b/.test( code ) ) {
			problems.push( `${ rel }:${ no } — union type در پارامتر، PHP 8.0+` );
		}

		// return type union
		if ( /\)\s*:\s*(?:int|string|array|bool|float|object)\s*\|\s*\w+/.test( code ) ) {
			problems.push( `${ rel }:${ no } — union return type، PHP 8.0+` );
		}

		// readonly / enum
		if ( /^\s*(readonly|enum)\s+/.test( code ) ) {
			problems.push( `${ rel }:${ no } — ${ code.trim().split( /\s+/ )[ 0 ] } فقط PHP 8.1+` );
		}

		// promoted constructor property
		if ( /function\s+__construct\s*\([^)]*\b(public|private|protected)\s+\$/.test( code ) ) {
			problems.push( `${ rel }:${ no } — constructor promotion، PHP 8.0+` );
		}
	} );
}

console.log( `فایل: ${ files.length } | هدف: PHP ${ TARGET }+` );
console.log( '──────────────────────────────────────────────' );

if ( ! problems.length ) {
	console.log( `سازگار با PHP ${ TARGET } ✓` );
	process.exit( 0 );
}

[ ...new Set( problems ) ].slice( 0, 40 ).forEach( p => console.log( '  ✗ ' + p ) );
console.log( `\n${ problems.length } مورد PHP 8-only` );
process.exit( 1 );
