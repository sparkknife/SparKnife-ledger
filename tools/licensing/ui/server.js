#!/usr/bin/env node
/**
 * سرور محلی برای رابط گرافیکی مدیریت لایسنس.
 *
 * چرا Node و نه PHP: کاربر PHP ندارد. Node همراه VS Code و با Git
 * برای ویندوز می‌آید و برای اجرای این سرور کافی است.
 *
 * چرا سرور محلی و نه فایل HTML ساده: مرورگر اجازهٔ اجرای openssl و
 * نوشتن فایل روی دیسک را از یک فایل HTML نمی‌دهد. یک سرور کوچک روی
 * ۱۲۷٫۰٫۰٫۱ این کار را می‌کند و فقط از همین دستگاه در دسترس است.
 *
 * @author  SparKnife <155084046+sparkknife@users.noreply.github.com>
 */

'use strict';

const http = require('http');
const fs = require('fs');
const path = require('path');
const os = require('os');
const crypto = require('crypto');
const { execFileSync, spawn } = require('child_process');

const UI_DIR = __dirname;
const ROOT = path.resolve(UI_DIR, '..', '..', '..');
const LIC_DIR = path.resolve(ROOT, 'tools', 'licensing');
const PLUGIN_DIR = path.resolve(ROOT, 'sparkknife-ledger');
const LICENSE_PHP = path.resolve(PLUGIN_DIR, 'includes', 'Core', 'class-sparkknife-ledger-license.php');
const PRIV = path.resolve(LIC_DIR, 'private_key.pem');
const PUB = path.resolve(LIC_DIR, 'public_key.pem');
const RELEASE_JSON = path.resolve(ROOT, 'release.json');

const PORT = Number(process.env.SPK_PORT || 4173);

/* ─── کمکی‌ها ──────────────────────────────────────────────────────── */

const json = (res, data, code) => {
	res.writeHead(code || 200, {
		'Content-Type': 'application/json; charset=utf-8',
		'Cache-Control': 'no-store',
	});
	res.end(JSON.stringify(data));
};

const readBody = (req) => new Promise((resolve) => {
	let body = '';
	req.on('data', (chunk) => {
		body += chunk;
		if (body.length > 1e7) { req.destroy(); }
	});
	req.on('end', () => {
		const out = {};
		// multipart/form-data سبک — فقط برای فیلدهای متنی کافی است.
		const boundary = (req.headers['content-type'] || '').split('boundary=')[1];
		if (boundary) {
			body.split('--' + boundary).forEach((part) => {
				const m = part.match(/name="([^"]+)"\r\n\r\n([\s\S]*?)\r\n$/);
				if (m) { out[m[1]] = m[2]; }
			});
		} else {
			body.split('&').forEach((pair) => {
				const [k, v] = pair.split('=');
				if (k) { out[decodeURIComponent(k)] = decodeURIComponent(v || ''); }
			});
		}
		resolve(out);
	});
});

const openssl = (args, input) => execFileSync('openssl', args, {
	input: input,
	maxBuffer: 1 << 22,
	encoding: 'utf8',
});

/* ─── خواندن PUBLIC_KEY دقیقاً مثل PHP ─────────────────────────────── */

const phpPublicKey = () => {
	const src = fs.readFileSync(LICENSE_PHP, 'utf8');
	const m = src.match(/const\s+PUBLIC_KEY\s*=\s*('(?:[^'\\]|\\.)*'|"(?:[^"\\]|\\.)*")\s*;/s);
	if (!m) { return null; }

	const lit = m[1];

	if (lit[0] === "'") {
		// تک‌کوتیشن: PHP فقط \\ و \' را تفسیر می‌کند. \n متن خام است.
		return lit.slice(1, -1).replace(/\\\\/g, '\u0000').replace(/\\'/g, "'").replace(/\u0000/g, '\\');
	}

	return lit.slice(1, -1).replace(/\\(.)/g, (all, c) => (
		{ n: '\n', r: '\r', t: '\t', '\\': '\\', '"': '"', $: '$' }[c] || all
	));
};

/* ─── بررسی هم‌ترازی ───────────────────────────────────────────────── */

const fingerprintOf = (pemPath) => {
	try {
		const der = execFileSync('openssl', ['pkey', '-pubin', '-in', pemPath, '-outform', 'DER'], { maxBuffer: 1 << 22 });
		return crypto.createHash('sha256').update(der).digest('hex');
	} catch (e) {
		return null;
	}
};

const keysAligned = () => {
	if (!fs.existsSync(PUB)) { return false; }

	const code = phpPublicKey();
	if (!code) { return false; }

	const tmp = path.join(os.tmpdir(), 'spk-code-' + process.pid + '.pem');
	try {
		fs.writeFileSync(tmp, code.replace(/\\n/g, '\n').trim());
		const a = fingerprintOf(tmp);
		const b = fingerprintOf(PUB);
		return Boolean(a && b && a === b);
	} catch (e) {
		return false;
	} finally {
		try { fs.unlinkSync(tmp); } catch (e) { /* بی‌اهمیت */ }
	}
};

/* ─── resolveCommand: کدام دستور shell را صدا بزنیم ────────────────── */

const runCli = (args) => {
	try {
		const out = execFileSync('bash', ['tools/licensing/sparkknife.sh'].concat(args), {
			cwd: ROOT,
			encoding: 'utf8',
			maxBuffer: 1 << 24,
			env: Object.assign({}, process.env, { SPK_NO_COLOR: '1' }),
		});
		return { ok: true, output: out };
	} catch (e) {
		return { ok: false, output: (e.stdout || '') + (e.stderr || '') };
	}
};

/* ─── API ──────────────────────────────────────────────────────────── */

const api = {

	status: () => {
		let version = '';
		try {
			const src = fs.readFileSync(path.join(PLUGIN_DIR, 'sparkknife-ledger.php'), 'utf8');
			version = (src.match(/SPARKKNIFE_LEDGER_VERSION',\s*'([^']+)'/) || [])[1] || '';
		} catch (e) { /* بی‌اهمیت */ }

		return {
			ok: true,
			private_key: fs.existsSync(PRIV),
			public_key: fs.existsSync(PUB),
			key_in_code: keysAligned(),
			fingerprint: fs.existsSync(PRIV) ? (() => {
				try {
					const der = execFileSync('openssl', ['pkey', '-in', PRIV, '-pubout', '-outform', 'DER'], { maxBuffer: 1 << 22 });
					return crypto.createHash('sha256').update(der).digest('hex');
				} catch (e) { return ''; }
			})() : '',
			version: version,
		};
	},

	setup: (fields) => {
		const force = fields.force === '1';
		const r = runCli(['setup'].concat(force ? ['--force'] : []));

		return {
			ok: r.ok,
			message: r.ok
				? 'کلید ساخته شد و در کد گذاشته شد.'
				: 'ساخت کلید ناموفق بود. خروجی: ' + r.output.slice(-400),
		};
	},

	issue: (fields) => {
		const domain = String(fields.domain || '').trim().replace(/^https?:\/\//, '').replace(/\/.*$/, '');
		const edition = String(fields.edition || 'pro');
		const forever = fields.forever === '1';

		if (!domain) {
			return { ok: false, message: 'دامنه لازم است.' };
		}

		const args = ['key', '--domain=' + domain, '--edition=' + edition, '--quiet'];

		if (!forever && fields.expiry) {
			if (!/^\d{4}-\d{2}-\d{2}$/.test(fields.expiry)) {
				return { ok: false, message: 'تاریخ باید YYYY-MM-DD باشد.' };
			}
			args.push('--expiry=' + fields.expiry);
		}

		const r = runCli(args);

		if (!r.ok) {
			const tail = r.output.trim().split('\n').filter(Boolean).pop() || 'خطای نامشخص';
			return { ok: false, message: tail.replace(/^✗\s*/, '') };
		}

		const key = r.output.trim().split('\n').filter((l) => l.indexOf('SPK1.') === 0).pop();

		if (!key) {
			return { ok: false, message: 'کلید ساخته شد ولی خوانده نشد.' };
		}

		// payload را از خود کلید می‌خوانیم تا خروجی با واقعیت بخواند.
		const parts = key.split('.');
		let payload = {};

		try {
			const raw = Buffer.from(parts[1].replace(/-/g, '+').replace(/_/g, '/'), 'base64').toString('utf8');
			const f = raw.split('|');
			payload = { domain: f[0], expiry: f[1], edition: f[2], serial: f[3] };
		} catch (e) { /* بی‌اهمیت */ }

		// اعتبارسنجی مستقل با openssl، نه باور به اسکریپت.
		let valid = false;
		try {
			const sig = Buffer.from(parts[2].replace(/-/g, '+').replace(/_/g, '/'), 'base64');
			const tmpDir = fs.mkdtempSync(path.join(os.tmpdir(), 'spk-'));
			const sigFile = path.join(tmpDir, 's.bin');
			const dataFile = path.join(tmpDir, 'p.txt');
			fs.writeFileSync(sigFile, sig);
			fs.writeFileSync(dataFile, raw_of(key));
			openssl(['dgst', '-sha256', '-verify', PUB, '-signature', sigFile, dataFile]);
			valid = true;
			fs.rmSync(tmpDir, { recursive: true, force: true });
		} catch (e) {
			valid = false;
		}

		return {
			ok: true,
			key: key,
			domain: payload.domain || domain,
			edition: payload.edition || edition,
			expiry: payload.expiry || '',
			serial: payload.serial || '',
			valid: valid,
			message: 'کلید صادر شد.',
		};
	},

	verify: (fields) => {
		const key = String(fields.key || '').trim();

		if (!key) { return { ok: false, valid: false, message: 'کلید را بچسبانید.' }; }

		const parts = key.split('.');

		if (parts.length !== 3) {
			return { ok: true, valid: false, message: 'کلید باید سه بخش داشته باشد.' };
		}

		if (parts[0] !== 'SPK1') {
			return { ok: true, valid: false, message: 'پیشوند کلید باید SPK1 باشد.' };
		}

		let raw;
		try {
			raw = Buffer.from(parts[1].replace(/-/g, '+').replace(/_/g, '/'), 'base64').toString('utf8');
		} catch (e) {
			return { ok: true, valid: false, message: 'محتوای کلید خوانا نیست.' };
		}

		const f = raw.split('|');
		const payload = { domain: f[0], expiry: f[1], edition: f[2], serial: f[3] };

		let sigOk = false;
		try {
			const sig = Buffer.from(parts[2].replace(/-/g, '+').replace(/_/g, '/'), 'base64');
			const tmpDir = fs.mkdtempSync(path.join(os.tmpdir(), 'spk-'));
			const sigFile = path.join(tmpDir, 's.bin');
			const dataFile = path.join(tmpDir, 'p.txt');
			fs.writeFileSync(sigFile, sig);
			fs.writeFileSync(dataFile, raw);
			openssl(['dgst', '-sha256', '-verify', PUB, '-signature', sigFile, dataFile]);
			sigOk = true;
			fs.rmSync(tmpDir, { recursive: true, force: true });
		} catch (e) {
			sigOk = false;
		}

		if (!sigOk) {
			return { ok: true, valid: false, payload: payload, message: 'امضا نامعتبر است — کلید جعل شده یا دستکاری شده.' };
		}

		if (payload.expiry) {
			const today = new Date().toISOString().slice(0, 10);
			if (payload.expiry < today) {
				return { ok: true, valid: false, payload: payload, message: 'امضا معتبر است ولی کلید منقضی شده (' + payload.expiry + ').' };
			}
		}

		return { ok: true, valid: true, payload: payload, message: 'امضا معتبر است و کلید هنوز منقضی نشده.' };
	},

	release: (fields) => {
		const bump = ['patch', 'minor', 'major'].indexOf(fields.bump) >= 0 ? fields.bump : 'patch';
		const changelog = String(fields.changelog || '').trim();

		const r = runCli(['release', '--' + bump, changelog]);

		if (!r.ok) {
			return { ok: false, message: 'ساخت نسخه ناموفق بود: ' + r.output.slice(-300) };
		}

		let data = {};
		try {
			data = JSON.parse(fs.readFileSync(RELEASE_JSON, 'utf8'));
		} catch (e) { /* بی‌اهمیت */ }

		const zipPath = path.resolve(ROOT, 'build', 'sparkknife-ledger-' + data.version + '.zip');
		let size = '';
		let files = 0;

		try {
			const st = fs.statSync(zipPath);
			size = (st.size / 1024).toFixed(0) + 'K';
		} catch (e) { /* بی‌اهمیت */ }

		try {
			const list = execFileSync('unzip', ['-l', zipPath], { encoding: 'utf8', maxBuffer: 1 << 24 });
			const m = list.trim().split('\n').pop().match(/(\d+)\s+files?/);
			files = m ? Number(m[1]) : 0;
		} catch (e) { /* بی‌اهمیت */ }

		return {
			ok: true,
			version: data.version,
			zip: zipPath,
			size: size,
			files: files,
			message: 'نسخهٔ ' + data.version + ' ساخته شد.',
			next: [
				'git add release.json sparkknife-ledger/ && git commit -m "انتشار ' + data.version + '"',
				'git push',
				'GitHub → Releases → Draft a new release → tag v' + data.version,
				'زیپ build/sparkknife-ledger-' + data.version + '.zip را ضمیمه کنید',
			],
		};
	},
};

/**
 * محتوای اصلی payload از کلید — برای بررسی امضا.
 */
function raw_of(key) {
	const parts = key.split('.');
	return Buffer.from(parts[1].replace(/-/g, '+').replace(/_/g, '/'), 'base64').toString('utf8');
}

/* ─── سرور ─────────────────────────────────────────────────────────── */

const MIME = {
	'.html': 'text/html; charset=utf-8',
	'.js': 'text/javascript; charset=utf-8',
	'.css': 'text/css; charset=utf-8',
	'.svg': 'image/svg+xml',
	'.png': 'image/png',
	'.ico': 'image/x-icon',
};

const server = http.createServer(async (req, res) => {
	const url = new URL(req.url, 'http://' + (req.headers.host || 'localhost'));

	// فقط از همین دستگاه. سرور روی 127.0.0.1 گوش می‌دهد، ولی این
	// بررسی جلوی دسترسی از طریق پروکسی را هم می‌گیرد.
	const remote = req.socket.remoteAddress || '';
	if (!/^(127\.|::1|::ffff:127\.)/.test(remote)) {
		res.writeHead(403);
		res.end('Forbidden');
		return;
	}

	if (url.pathname.indexOf('/api/') === 0) {
		const name = url.pathname.slice(5);

		if (!api[name]) {
			json(res, { ok: false, message: 'دستور ناشناخته' }, 404);
			return;
		}

		if (req.method === 'POST') {
			const fields = await readBody(req);
			try {
				json(res, await api[name](fields));
			} catch (e) {
				json(res, { ok: false, message: 'خطای داخلی: ' + e.message }, 500);
			}
			return;
		}

		try {
			json(res, await api[name]({}));
		} catch (e) {
			json(res, { ok: false, message: 'خطای داخلی: ' + e.message }, 500);
		}
		return;
	}

	// فایل‌های ساکن
	let file = url.pathname === '/' ? '/index.html' : url.pathname;
	file = path.join(UI_DIR, path.normalize(file).replace(/^(\.\.[/\\])+/, ''));

	if (!file.startsWith(UI_DIR) || !fs.existsSync(file) || fs.statSync(file).isDirectory()) {
		res.writeHead(404, { 'Content-Type': 'text/plain; charset=utf-8' });
		res.end('پیدا نشد');
		return;
	}

	res.writeHead(200, {
		'Content-Type': MIME[path.extname(file)] || 'application/octet-stream',
		'Cache-Control': 'no-store',
	});
	fs.createReadStream(file).pipe(res);
});

/* ─── باز کردن مرورگر ──────────────────────────────────────────────── */

const openBrowser = (url) => {
	const cmd = process.platform === 'win32' ? 'cmd'
		: process.platform === 'darwin' ? 'open' : 'xdg-open';
	const args = process.platform === 'win32' ? ['/c', 'start', '', url] : [url];

	try {
		spawn(cmd, args, { detached: true, stdio: 'ignore' }).unref();
	} catch (e) {
		/* اگر باز نشد، نشانی را چاپ می‌کنیم. */
	}
};

server.listen(PORT, '127.0.0.1', () => {
	const url = 'http://127.0.0.1:' + PORT;

	console.log('');
	console.log('  ══════════════════════════════════════════════════');
	console.log('  SparKnife Ledger — مدیریت لایسنس');
	console.log('  ══════════════════════════════════════════════════');
	console.log('');
	console.log('  نشانی:  ' + url);
	console.log('  پوشه:   ' + ROOT);
	console.log('');
	console.log('  برای بستن: Ctrl+C');
	console.log('');

	if (process.env.SPK_NO_OPEN !== '1') {
		openBrowser(url);
	}
});
