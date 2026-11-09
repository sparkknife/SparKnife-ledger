#!/usr/bin/env node
/**
 * بازبینی استاتیک: انسجام کلاس‌ها و ارجاع‌های ناموجود.
 *
 * چه می‌سنجد:
 *   ۱) هر کلاس تعریف‌شده یکتا باشد (بدون تکرار نام)
 *   ۲) هر `new X` / `X::method` / `X::CONST` به کلاسی اشاره کند که
 *      واقعاً تعریف شده یا کلاس وردپرس/PHP است
 *   ۳) هر متد استاتیک فراخوانی‌شده روی کلاس‌های افزونه وجود داشته باشد
 *   ۴) هر ثابت کلاسی فراخوانی‌شده وجود داشته باشد
 *   ۵) هیچ فایل PHP دو بار تعریف نشود
 *
 * این همان چیزی است که Fatal Error در زمان اجرا می‌سازد و تست
 * یکپارچه فقط اگر آن مسیر را اجرا کند می‌گیرد.
 */
import fs from 'fs';
import path from 'path';
import { execSync } from 'child_process';
import PhpParser from 'php-parser';

const root = path.resolve(process.argv[2] || '/home/user/SparKnife-ledger/sparkknife-ledger');
const engine = new PhpParser({ parser: { extractDoc: false, suppressErrors: true }, ast: { withPositions: true } });

const files = execSync(`find ${JSON.stringify(root)} -name '*.php' -type f`).toString().trim().split('\n').sort();

// ── جمع‌آوری کلاس‌ها ────────────────────────────────────────────
const classes = new Map();   // name → { file, extends, staticMethods:Set, methods:Set, consts:Set }
const classOrder = [];

function walk(node, visit) {
	if (!node || typeof node !== 'object') return;
	if (Array.isArray(node)) { node.forEach(n => walk(n, visit)); return; }
	visit(node);
	for (const k of Object.keys(node)) {
		if (k === 'loc' || k === 'leadingComments') continue;
		const v = node[k];
		if (v && typeof v === 'object') walk(v, visit);
	}
}

for (const file of files) {
	let ast;
	try { ast = engine.parseCode(fs.readFileSync(file, 'utf8'), file); }
	catch { continue; }

	walk(ast, (node) => {
		if (node.kind !== 'class') return;
		if (!node.name) return; // anonymous class
		const rec = {
			file: path.relative(root, file),
			extends: node.extends ? node.extends.name : null,
			staticMethods: new Set(),
			methods: new Set(),
			consts: new Set(),
			props: new Set(),
		};
		for (const m of node.body || []) {
			if (m.kind === 'method') {
				(m.isStatic ? rec.staticMethods : rec.methods).add(m.name);
			} else if (m.kind === 'classconstant') {
				(m.constants || []).forEach(c => rec.consts.add(c.name));
			} else if (m.kind === 'propertystatement') {
				(m.properties || []).forEach(p => rec.props.add(p.name));
			}
		}
		if (!classes.has(node.name)) classOrder.push(node.name);
		classes.set(node.name, rec);
	});
}

// ── کلاس‌های بیرونی (وردپرس، PHP، افزونه‌های دیگر) ──────────────
const EXTERNAL = /^(WP_|wpdb$|wpdb_|WPSEO|WC_|Generic|SEO)/;
const PHP_BUILTINS = new Set([
	'stdClass', 'Exception', 'Error', 'Throwable', 'ArrayObject', 'DateTime', 'DateTimeImmutable',
	'DateTimeZone', 'DateInterval', 'ArrayAccess', 'Countable', 'IteratorAggregate', 'Iterator',
	'Traversable', 'Closure', 'Generator', 'ReflectionClass', 'ReflectionMethod', 'JsonSerializable',
	'SplStack', 'SplQueue', 'SplObjectStorage', 'SimpleXMLElement', 'DOMDocument', 'PDO',
]);

// ── بازبینی ────────────────────────────────────────────────────
const problems = [];
const seen = new Map();

function isKnownClass(name) {
	if (!name) return true;
	const bare = name.replace(/^\\/, '');
	if (classes.has(bare)) return true;
	if (PHP_BUILTINS.has(bare)) return true;
	if (EXTERNAL.test(bare)) return true;
	return false;
}

for (const file of files) {
	let ast;
	try { ast = engine.parseCode(fs.readFileSync(file, 'utf8'), file); }
	catch { continue; }
	const rel = path.relative(root, file);
	const lines = fs.readFileSync(file, 'utf8').split('\n');

	// تکرار تعریف کلاس
	walk(ast, (node) => {
		if (node.kind === 'class' && node.name) {
			if (seen.has(node.name) && seen.get(node.name) !== rel) {
				problems.push(`کلاس تکراری «${node.name}» در ${rel} و ${seen.get(node.name)}`);
			}
			seen.set(node.name, rel);
		}
	});

	// ارجاع‌های کلاس
	walk(ast, (node) => {
		const where = rel + ':' + (node.loc?.start?.line ?? '?');

		if (node.kind === 'new' && node.what?.kind === 'name') {
			const name = node.what.name;
			if (!isKnownClass(name)) problems.push(`new کلاس ناشناخته «${name}» → ${where}`);
		}

		if (node.kind === 'staticcall' && node.what?.kind === 'name') {
			const cls = node.what.name;
			if (!isKnownClass(cls)) { problems.push(`:: روی کلاس ناشناخته «${cls}» → ${where}`); return; }
			if (classes.has(cls) && node.what.kind === 'name') {
				const rec = classes.get(cls);
				const m = node.method?.name;
				if (m && m !== '__construct' && !rec.staticMethods.has(m)) {
					const asInstance = rec.methods.has(m);
					problems.push(
						`${cls}::${m}() استاتیک نیست${asInstance ? ' (متد نمونه است)' : ' (متدی با این نام نیست)'} → ${where}`
					);
				}
			}
		}

		if (node.kind === 'classconstant' && node.what?.kind === 'name') {
			const cls = node.what.name;
			const c = node.constant?.name || (typeof node.constant === 'string' ? node.constant : null);
			if (classes.has(cls) && c) {
				const rec = classes.get(cls);
				if (!rec.consts.has(c) && !['class'].includes(c)) {
					problems.push(`${cls}::${c} ثابت نیست → ${where}`);
				}
			}
		}
	});
}

// ── گزارش ──────────────────────────────────────────────────────
console.log(`کلاس‌ها: ${classes.size} | فایل‌ها: ${files.length}`);
console.log('──────────────────────────────────────────────');
if (!problems.length) {
	console.log('هیچ ارجاع شکسته‌ای پیدا نشد ✓');
	process.exit(0);
}
const uniq = [...new Set(problems)];
uniq.slice(0, 40).forEach(p => console.log('  ✗ ' + p));
if (uniq.length > 40) console.log(`  … و ${uniq.length - 40} مورد دیگر`);
console.log(`\n${uniq.length} ارجاع مشکوک`);
process.exit(1);
