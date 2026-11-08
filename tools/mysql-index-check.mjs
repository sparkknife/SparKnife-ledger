#!/usr/bin/env node
/**
 * بررسی سازگاری ایندکس‌های اسکیما با MySQL + utf8mb4.
 *
 * چرا: MySQL/InnoDB کلید ایندکس را حداکثر ۳۰۷۲ بایت می‌پذیرد و در
 * utf8mb4 هر نویسه ۴ بایت است. یعنی یک `varchar(255)` تنها ۱۰۲۰
 * بایت است، ولی دو ستون `varchar(255)` روی هم از سقف می‌گذرند و
 * `CREATE TABLE` شکست می‌خورد.
 *
 * این خطا روی SQLite هرگز دیده نمی‌شود، پس در محیط توسعه بی‌صدا
 * می‌ماند و خریدار اول از همه به آن می‌خورد: نصب، «خطا در ساخت
 * جدول»، بدون توضیح.
 *
 * استفاده:
 *   node tools/mysql-index-check.mjs
 *
 * خروج صفر = همهٔ ایندکس‌ها جا می‌شوند.
 */
import { readFileSync } from 'fs';
import { fileURLToPath } from 'url';
import path from 'path';

const LIMIT = 3072; // سقف InnoDB با DYNAMIC row format.
const CHARSET = 4;  // utf8mb4

const here = path.dirname( fileURLToPath( import.meta.url ) );
const file = path.join( here, '..', 'sparkknife-ledger', 'includes', 'Core', 'class-sparkknife-ledger-installer.php' );
const src = readFileSync( file, 'utf8' );

/*
 * استخراج بلوک هر CREATE TABLE.
 *
 * عمداً از regex چندخطی استفاده نمی‌کنم: تعریف جدول‌ها چند ده خط
 * است و هر الگوی ساده‌ای یا بیش از حد حریص می‌شود یا هیچ چیز پیدا
 * نمی‌کند. پیدا کردن مرزهای رشته با شمردن برش، قابل اعتمادتر است.
 */
const tables = [];
const startRe = /\$sql_(\w+)\s*=\s*"CREATE TABLE/g;
let m;

while ( ( m = startRe.exec( src ) ) !== null ) {
	const open = src.indexOf( '"', m.index + m[ 0 ].indexOf( 'CREATE' ) - 1 );
	let i = open + 1;
	let body = '';

	while ( i < src.length && src[ i ] !== '"' ) {
		if ( src[ i ] === '\\' ) {
			body += src[ i ] + src[ i + 1 ];
			i += 2;
			continue;
		}
		body += src[ i ];
		i++;
	}

	tables.push( { name: m[ 1 ], body } );
}

if ( tables.length === 0 ) {
	console.error( '✘ هیچ تعریف جدولی پیدا نشد — ساختار فایل عوض شده؟' );
	process.exit( 2 );
}

/* بایت‌های تقریبی یک ستون در ایندکس utf8mb4. */
const typeBytes = ( line ) => {
	if ( /\b(bigint|int)\b/.test( line ) ) return 8;
	if ( /\b(datetime|timestamp)\b/.test( line ) ) return 8;
	if ( /\bdate\b/.test( line ) ) return 3;
	if ( /\b(tinyint|smallint)\b/.test( line ) ) return 2;

	const vc = line.match( /varchar\((\d+)\)/ );
	if ( vc ) return parseInt( vc[ 1 ], 10 ) * CHARSET;

	if ( /\b(longtext|text)\b/.test( line ) ) return null; // روی TEXT بی‌پیشوند ایندکس نمی‌شود.

	return 255 * CHARSET;
};

let failed = 0;
let total = 0;

console.log( `بررسی ایندکس‌های MySQL (utf8mb4، سقف ${ LIMIT } بایت)\n` );

for ( const t of tables ) {
	const lines = t.body
		.split( '\n' )
		.map( ( l ) => l.trim().replace( /,$/, '' ) )
		.filter( Boolean );

	const cols = {};

	for ( const l of lines ) {
		const c = l.match( /^(\w+)\s+(varchar|bigint|int|datetime|timestamp|date|tinyint|smallint|longtext|text)\b/i );
		if ( c ) cols[ c[ 1 ] ] = l;
	}

	for ( const l of lines ) {
		const k = l.match( /^(?:UNIQUE\s+)?KEY\s+(\w+)\s*\(([^)]+)\)/i );
		if ( ! k ) continue;

		total++;

		const names = k[ 2 ].split( ',' ).map( ( s ) => s.trim().split( '(' )[ 0 ] );
		let bytes = 0;
		let hasText = false;

		for ( const n of names ) {
			const b = cols[ n ] ? typeBytes( cols[ n ] ) : 8;
			if ( b === null ) hasText = true;
			else bytes += b;
		}

		const label = `${ t.name }.${ k[ 1 ] }`;
		const pad = ' '.repeat( Math.max( 0, 34 - label.length ) );

		if ( hasText ) {
			failed++;
			console.log( `✘ ${ label }${ pad }TEXT در ایندکس — MySQL رد می‌کند` );
		} else if ( bytes > LIMIT ) {
			failed++;
			console.log( `✘ ${ label }${ pad }${ bytes } بایت — از سقف می‌گذرد` );
		} else {
			console.log( `✓ ${ label }${ pad }${ bytes } بایت` );
		}
	}
}

console.log( '\n' + '─'.repeat( 62 ) );

if ( failed === 0 ) {
	console.log( `✓ هر ${ total } ایندکس با MySQL utf8mb4 سازگار است` );
	process.exit( 0 );
}

console.log( `✘ ${ failed } از ${ total } ایندکس روی MySQL شکست می‌خورد` );
process.exit( 1 );
