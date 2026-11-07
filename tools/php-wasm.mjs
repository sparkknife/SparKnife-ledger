#!/usr/bin/env node
/**
 * اجرای PHP-WASM برای آزمون بدون نیاز به PHP نصب‌شده.
 *
 * چرا این ابزار: محیط توسعه و بسیاری از ماشین‌ها PHP ندارند، ولی
 * افزونه وردپرسی بدون اجرای واقعی روی PHP قابل اعتماد نیست. این
 * اسکریپت PHP 8.2 را در WebAssembly بالا می‌آورد و میزبان را در
 * مسیر `/` مونت می‌کند، پس اسکریپت‌های PHP می‌توانند به فایل‌های
 * واقعی دسترسی داشته باشند.
 *
 * نکتهٔ فنی مهم: `loadNodeRuntime()` یک **شمارهٔ runtime** برمی‌گرداند،
 * نه شیء PHP. باید با `new PHP(id)` از `@php-wasm/universal`
 * بسته‌بندی شود. این همان چیزی بود که مدت‌ها «خرابی» به نظر می‌رسید.
 *
 * نصب:
 *   npm install @php-wasm/node @php-wasm/universal
 *
 * استفاده:
 *   node tools/php-wasm.mjs <اسکریپت.php>
 *   node tools/php-wasm.mjs <اسکریپت.php> --version 8.2
 *
 * خروجی اسکریپت روی stdout و خطاها روی stderr.
 * کد خروج صفر = اجرا موفق (فارغ از نتیجهٔ اسکریپت).
 */
import { loadNodeRuntime, useHostFilesystem } from '@php-wasm/node';
import { PHP } from '@php-wasm/universal';
import { readFileSync } from 'fs';

const script = process.argv[ 2 ];

if ( ! script ) {
	console.error( 'استفاده: node tools/php-wasm.mjs <اسکریپت.php>' );
	process.exit( 2 );
}

const vIdx = process.argv.indexOf( '--version' );
const version = vIdx !== -1 && process.argv[ vIdx + 1 ] ? process.argv[ vIdx + 1 ] : '8.2';

let source;
try {
	source = readFileSync( script, 'utf8' );
} catch ( e ) {
	console.error( `اسکریپت خوانده نشد: ${ script }` );
	process.exit( 2 );
}

const php = new PHP( await loadNodeRuntime( version, { emscriptenOptions: { processId: 1 } } ) );

// میزبان را در ریشه مونت کن تا include به فایل‌های واقعی برسد.
useHostFilesystem( php );

const vpath = '/tmp/__spk_run.php';
php.writeFile( vpath, source );

const out = await php.run( { scriptPath: vpath } );

if ( out.text ) process.stdout.write( out.text );
if ( out.errors && out.errors.trim() ) process.stderr.write( out.errors );
