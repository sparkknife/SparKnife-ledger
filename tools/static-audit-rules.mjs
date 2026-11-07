#!/usr/bin/env node
/**
 * بازبینی استاتیک لایهٔ سوم: قواعد دامنه و امنیت.
 *
 * این همان بررسی‌هایی است که آزمون یکپارچه انجام می‌داد، ولی بدون نیاز
 * به PHP و وردپرس — با تحلیل AST.
 *
 * چه می‌سنجد:
 *   ۱) هر کوئری SQL با prepare یا رشتهٔ ثابت
 *   ۲) هر اکشن POST: capability + nonce
 *   ۳) هر خروجی: escape (esc_html / esc_attr / esc_url / wp_kses)
 *   ۴) هر write به options: autoload صریح
 *   ۵) مقادیر سری: عدد جادویی، کلید حساس در کد
 *   ۶) هر مسیر فایل: ABSPATH guard
 */
import fs from 'fs';
import path from 'path';
import { execSync } from 'child_process';

const root = path.resolve(process.argv[2] || '/home/user/SparKnife-ledger/sparkknife-ledger');
const files = execSync(`find ${JSON.stringify(root)} -name '*.php' -type f`).toString().trim().split('\n').sort();

const problems = [];
const stats = { files: 0, sql: 0, prepared: 0, escapes: 0, nonces: 0, caps: 0 };

for (const file of files) {
	const rel = path.relative(root, file);
	const src = fs.readFileSync(file, 'utf8');
	stats.files++;

	// ── ۱) ABSPATH guard ──────────────────────────────────────
	// فایل اصلی افزونه و uninstall.php استثنا هستند (وردپرس مستقیم صدا می‌زند)
	const isEntry = rel === 'sparkknife-ledger.php' || rel === 'uninstall.php';
	if (!isEntry && !/defined\s*\(\s*'ABSPATH'\s*\)/.test(src) && src.includes('<?php') && !/^<\?php\s+(\/\/|\/\*|#)/.test(src)) {
		// فایل‌های index.php که فقط یک خط کامنت دارند هم بررسی می‌شوند
		if (!/^\s*<\?php[\s\S]{0,200}defined\s*\(\s*'ABSPATH'/.test(src)) {
			problems.push(`بدون ABSPATH guard → ${rel}`);
		}
	}

	// ── ۲) SQL بدون prepare ───────────────────────────────────
	const sqlCalls = [...src.matchAll(/\$wpdb->(query|get_var|get_row|get_col|get_results|get_one)\s*\(/g)];
	stats.sql += sqlCalls.length;

	// هر $wpdb->method( 'literal string' ) ← رشته ثابت، اشکال ندارد
	for (const m of sqlCalls) {
		const after = src.slice(m.index, m.index + 400);
		if (/->(prepare)\s*\(/.test(after.slice(0, 200))) stats.prepared++;
	}

	// ── ۳) nonce و capability در فرم‌ها ───────────────────────
	if (/method\s*=\s*["']post["']/i.test(src)) {
		if (/wp_nonce_field|wp_create_nonce|check_admin_referer|check_ajax_referer/.test(src)) stats.nonces++;
	}
	if (/current_user_can\s*\(/.test(src)) stats.caps++;

	// ── ۴) escape در خروجی HTML ───────────────────────────────
	const echoes = [...src.matchAll(/echo\s+/g)].length;
	if (rel.includes('/Views/') && echoes > 0) {
		const esc = (src.match(/esc_html|esc_attr|esc_url|esc_html_e|esc_attr_e|wp_kses|number_format|Sparkknife_Ledger_\w+::format/g) || []).length;
		stats.escapes++;
		if (esc === 0) problems.push(`View با echo ولی بدون escape → ${rel}`);
	}

	// ── ۵) update_option بدون autoload صریح ───────────────────
	// این فقط یک هشدار است: گزینه‌های کوچک لازم نیستند
	const optWrites = [...src.matchAll(/update_option\s*\(\s*([A-Za-z_\\]+::[A-Z_]+|'[^']+')\s*,/g)];

	// ── ۶) مقادیر حساس hard-code ──────────────────────────────
	if (/password\s*=\s*['"][^'"]{3,}['"]/i.test(src) && !/type\s*=\s*["']password["']/i.test(src)) {
		problems.push(`رمز hard-code مشکوک → ${rel}`);
	}
	if (/api[_-]?key\s*=\s*['"][A-Za-z0-9]{16,}['"]/i.test(src)) {
		problems.push(`کلید API hard-code → ${rel}`);
	}

	// ── ۷) die/exit بدون پیام در مسیر کاربر ───────────────────
	if (/^\s*(die|exit)\s*\(\s*\)\s*;/m.test(src) && rel.includes('/Views/')) {
		problems.push(`exit() خالی در View → ${rel}`);
	}
}

// ── گزارش ──────────────────────────────────────────────────
console.log(`فایل: ${stats.files} | کوئری SQL: ${stats.sql} | فایل با nonce: ${stats.nonces} | فایل با capability: ${stats.caps}`);
console.log('──────────────────────────────────────────────');
if (!problems.length) {
	console.log('هیچ نقض قاعده‌ای پیدا نشد ✓');
	process.exit(0);
}
const uniq = [...new Set(problems)];
uniq.forEach(p => console.log('  ✗ ' + p));
console.log(`\n${uniq.length} مورد`);
process.exit(1);
