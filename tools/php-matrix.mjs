#!/usr/bin/env node
/**
 * ماتریس نسخه‌های PHP — اجرای یک اسکریپت روی چند نسخهٔ PHP.
 *
 * چرا: افزونه روی PHP 7.4 تا 8.5 باید کار کند و اعلامش هم همین است.
 * آزمودن دستی هر نسخه غیرممکن است، ولی WebAssembly هر هفت نسخه را
 * همزمان در دسترس دارد. کاری که در محیط عادی به هفت سرور نیاز دارد،
 * این‌جا یک دستور است.
 *
 * استفاده:
 *   node tools/php-matrix.mjs <اسکریپت.php>
 *   node tools/php-matrix.mjs <اسکریپت.php> --versions 7.4,8.2,8.5
 */
import { loadNodeRuntime, useHostFilesystem } from '@php-wasm/node';
import { PHP } from '@php-wasm/universal';
import { readFileSync } from 'fs';

const ALL = [ '7.4', '8.0', '8.1', '8.2', '8.3', '8.4', '8.5' ];

const script = process.argv[ 2 ];
if ( ! script ) {
	console.error( 'استفاده: node tools/php-matrix.mjs <اسکریپت.php>' );
	process.exit( 2 );
}

const vi = process.argv.indexOf( '--versions' );
const versions = vi !== -1 && process.argv[ vi + 1 ] ? process.argv[ vi + 1 ].split( ',' ) : ALL;

const source = readFileSync( script, 'utf8' );
const results = [];

for ( const v of versions ) {
	const t0 = Date.now();
	let text = '';
	let failed = false;
	const warnings = [];

	try {
		const php = new PHP( await loadNodeRuntime( v, { emscriptenOptions: { processId: 1 } } ) );
		useHostFilesystem( php );
		php.writeFile( '/tmp/__m.php', source );
		const out = await php.run( { scriptPath: '/tmp/__m.php' } );

		text = ( out.text || '' ).replace( /\x1b\[[0-9;]*m/g, '' );
		const err = out.errors || '';

		// هر Warning/Deprecated/Notice خطاست، حتی اگر آزمون سبز بماند.
		const re = /(Warning|Deprecated|Notice|Fatal error|Parse error):\s*(.+?) in (.+?) on line (\d+)/g;
		let m;
		while ( ( m = re.exec( text + '\n' + err ) ) !== null ) {
			warnings.push( `${ m[ 1 ] }: ${ m[ 2 ] } @ ${ m[ 3 ].split( '/' ).slice( -2 ).join( '/' ) }:${ m[ 4 ] }` );
		}
		if ( out.exitCode !== 0 ) { failed = true; console.log('   exitCode=' + out.exitCode); console.log('   out=' + JSON.stringify((out.text||'').slice(-300))); console.log('   err=' + JSON.stringify((out.errors||'').slice(-300))); }
	} catch ( e ) {
		failed = true;
		text = String( e.message || e ).slice( 0, 300 ); console.log('   E=' + text.replace(/\n/g,' | ').slice(0,250));
	}

	const secs = ( ( Date.now() - t0 ) / 1000 ).toFixed( 0 );
	results.push( { version: v, failed, warnings: [ ...new Set( warnings ) ], text, secs } );

	const mark = failed ? '✘' : warnings.length ? '⚠' : '✓';
	console.log( `${ mark } PHP ${ v.padEnd( 4 ) }  ${ warnings.length } هشدار  ${ secs }s` );
	warnings.slice( 0, 4 ).forEach( ( w ) => console.log( `     ${ w }` ) );
}

console.log( '\n' + '─'.repeat( 60 ) );
const bad = results.filter( ( r ) => r.failed || r.warnings.length );
if ( bad.length === 0 ) {
	console.log( `✓ هر ${ results.length } نسخهٔ PHP بدون هشدار` );
	process.exit( 0 );
}
console.log( `✘ ${ bad.length } از ${ results.length } نسخه مشکل دارد: ${ bad.map( ( r ) => r.version ).join( ', ' ) }` );
process.exit( 1 );
