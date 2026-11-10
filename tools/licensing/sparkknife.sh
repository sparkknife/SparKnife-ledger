#!/usr/bin/env bash
#
# SparKnife Ledger — ابزار یکجا برای کل کار لایسنس.
#
# این فایل تنها چیزی است که باید اجرا کنید. سه کار را پوشش می‌دهد:
#
#   ۱) راه‌اندازی اولیه: ساخت جفت کلید و جای‌گذاری کلید عمومی در کد
#   ۲) صدور لایسنس برای یک دامنه (و بررسی خودکار آن)
#   ۳) ساخت زیپ آمادهٔ نصب
#
# بدون PHP. فقط Git Bash و openssl که همراه خودش می‌آید.
#
# ── سه دستور، نه بیشتر ────────────────────────────────────────────────
#
#   bash tools/licensing/sparkknife.sh setup
#       یک بار در عمر پروژه. کلید می‌سازد و در کد می‌گذارد.
#
#   bash tools/licensing/sparkknife.sh key --domain=example.com
#       برای هر مشتری. کلید لایسنس می‌دهد.
#
#   bash tools/licensing/sparkknife.sh pack
#       زیپ آمادهٔ نصب در پوشهٔ build/
#
# ── چیزهایی که خودش تصمیم می‌گیرد ─────────────────────────────────────
#
#   • اگر کلید خصوصی هست، دوباره نمی‌سازد (مگر --force)
#   • اگر کلید عمومی در کد با کلید روی دیسک نمی‌خواند، خودش هم‌تراز می‌کند
#   • دامنهٔ سایت فعلی را از آرگومان، یا از گیت، یا با پرسش می‌گیرد
#   • تاریخ انقضا را پیش‌فرض یک سال بعد می‌گذارد
#   • بعد از صدور، کلید را با کلید عمومی واقعیِ داخل کد بررسی می‌کند
#   • در pack، وجود private_key.pem در زیپ را بررسی و رد می‌کند
#
# @author  SparKnife <155084046+sparkknife@users.noreply.github.com>

set -u

_SELF="${BASH_SOURCE[0]}"
[ -f "$_SELF" ] || _SELF="$0"
DIR="$(cd "$(dirname "$_SELF")" && pwd)"
ROOT="$(cd "$DIR/../.." && pwd)"

PRIV="$DIR/private_key.pem"
PUB="$DIR/public_key.pem"
LICENSE_PHP="$ROOT/sparkknife-ledger/includes/Core/class-sparkknife-ledger-license.php"
PLUGIN_DIR="$ROOT/sparkknife-ledger"
BUILD_DIR="$ROOT/build"

LINE="══════════════════════════════════════════════════"
THIN="──────────────────────────────────────────────────"

# پوشهٔ موقت مشترک. `trap` در لحظهٔ خروج ارزیابی می‌شود، نه هنگام
# تعریف؛ اگر متغیر داخل تابع `local` باشد، در آن لحظه وجود ندارد و
# پاک‌سازی با خطای «unbound variable» شکست می‌خورد. یک مسیر جهانی
# نگه می‌داریم و هر تابع فقط محتوایش را عوض می‌کند.
SPK_TMP=""
cleanup() {
	if [ -n "$SPK_TMP" ] && [ -d "$SPK_TMP" ]; then
		rm -rf "$SPK_TMP"
	fi
}
trap cleanup EXIT

# رنگ‌ها فقط وقتی ترمینال است؛ در خروجی گیت تمیز می‌ماند.
if [ -t 1 ]; then
	OK=$'\033[32m'; BAD=$'\033[31m'; WARN=$'\033[33m'; BOLD=$'\033[1m'; OFF=$'\033[0m'
else
	OK=''; BAD=''; WARN=''; BOLD=''; OFF=''
fi

ok()   { printf '  %s✓%s %s\n' "$OK" "$OFF" "$1"; }
bad()  { printf '  %s✗%s %s\n' "$BAD" "$OFF" "$1"; }
warn() { printf '  %s!%s %s\n' "$WARN" "$OFF" "$1"; }
note() { printf '    %s\n' "$1"; }

die() {
	printf '\n%s✗ %s%s\n\n' "$BAD" "$1" "$OFF" >&2
	exit 1
}

# ─── پیش‌نیازها ───────────────────────────────────────────────────────

need_openssl() {
	if ! command -v openssl >/dev/null 2>&1; then
		printf '\n%s✗ openssl پیدا نشد.%s\n\n' "$BAD" "$OFF" >&2
		echo "  در Git Bash روی ویندوز باید همراه Git نصب شده باشد."
		echo "  بررسی کنید:  openssl version"
		echo ""
		echo "  اگر نبود، Git for Windows را دوباره نصب کنید:"
		echo "    https://git-scm.com/download/win"
		echo ""
		exit 1
	fi
}

# ─── خواندن مقدار واقعی PUBLIC_KEY از فایل PHP ──────────────────────
#
# این کار را با پایتون می‌کنیم، نه با grep/awk. دلیلش یک تلهٔ واقعی است:
#
# در PHP رشتهٔ تک‌کوتیشن تفسیر نمی‌شود، پس
#     const PUBLIC_KEY = '-----BEGIN\nMIIB...';
# مقدارش همان کاراکترهای backslash و n است، نه newline. PEM با این
# متن خام معتبر نیست و openssl_pkey_get_public فقط «ناموفق» می‌دهد،
# در حالی که از بیرون به‌نظر می‌رسد کلید درست سر جایش است.
#
# یک بار همین اتفاق افتاد: ابزار «✓ کلید در کد همان کلید روی دیسک است»
# گفت ولی سایت واقعی «کلید عمومی افزونه بارگذاری نشد» می‌داد. دلیل:
# بررسی با فایل روی دیسک انجام می‌شد، نه با مقداری که PHP واقعاً
# می‌خواند.
#
# حالا مقدار را دقیقاً مثل PHP بازسازی می‌کنیم (تک‌کوتیشن = خام،
# دو‌کوتیشن = escape) و همان را به openssl می‌دهیم.

php_public_key() {
	python3 - "$LICENSE_PHP" <<'PYEOF'
import io, re, sys

path = sys.argv[1]
src = io.open(path, encoding='utf-8').read()

m = re.search(r"const\s+PUBLIC_KEY\s*=\s*('(?:[^'\\]|\\.)*'|\"(?:[^\"\\]|\\.)*\")\s*;", src, re.S)
if not m:
    sys.exit(2)

literal = m.group(1)

if literal[0] == "'":
    # تک‌کوتیشن: PHP فقط \\ و \' را تفسیر می‌کند. \n متن خام می‌ماند.
    body = literal[1:-1]
    body = body.replace("\\\\", "\x00B\x00").replace("\\'", "'").replace("\x00B\x00", "\\")
else:
    # دو‌کوتیشن: \n و \r و \t و \\ و \" تفسیر می‌شوند.
    body = literal[1:-1]
    out = []
    i = 0
    while i < len(body):
        c = body[i]
        if c == '\\' and i + 1 < len(body):
            n = body[i + 1]
            mapping = {'n': '\n', 'r': '\r', 't': '\t', '\\': '\\', '"': '"', '$': '$'}
            if n in mapping:
                out.append(mapping[n])
                i += 2
                continue
        out.append(c)
        i += 1
    body = ''.join(out)

sys.stdout.write(body)
PYEOF
}

# آیا مقدار داخل PHP، وقتی PHP می‌خواندش، یک PEM معتبر است؟
php_key_valid() {
	local tmp pem
	tmp="$(mktemp -d)"
	pem="$tmp/k.pem"

	# پاک‌سازی فقط برای همین بررسی؛ وظیفه‌اش این نیست که مقدار را درست
	# کند. اگر PEM معتبر نبود، باید شکست بخورد تا setup آن را بنویسد.
	SPK_TMP="$tmp"

	php_public_key > "$pem" 2>/dev/null || return 1
	[ -s "$pem" ] || return 1

	openssl pkey -pubin -in "$pem" -noout >/dev/null 2>&1 || return 1

	# باید همان کلید روی دیسک باشد، نه فقط یک PEM معتبر.
	if [ -f "$PUB" ]; then
		local a b
		a="$(openssl pkey -pubin -in "$pem" -outform DER 2>/dev/null | openssl dgst -sha256 -r 2>/dev/null | cut -d' ' -f1)"
		b="$(openssl pkey -pubin -in "$PUB" -outform DER 2>/dev/null | openssl dgst -sha256 -r 2>/dev/null | cut -d' ' -f1)"
		[ -n "$a" ] && [ "$a" = "$b" ] || return 1
	fi

	return 0
}

# ─── نوشتن کلید دیسک در فایل PHP ─────────────────────────────────────

sync_public_key() {
	[ -f "$PUB" ] || return 1
	[ -f "$LICENSE_PHP" ] || return 1

	if php_key_valid; then
		return 0
	fi

	local tmp_dir pem_escaped
	tmp_dir="$(mktemp -d)"
	SPK_TMP="$tmp_dir"

	python3 - "$PUB" "$tmp_dir/key.txt" <<'PYEOF' || return 1
import io, sys
raw = io.open(sys.argv[1], encoding='utf-8').read().replace('\r', '')
lines = [ln.rstrip() for ln in raw.split('\n') if ln.strip()]
io.open(sys.argv[2], 'w', encoding='utf-8').write('\\n'.join(lines))
PYEOF

	pem_escaped="$(cat "$tmp_dir/key.txt")"
	[ -n "$pem_escaped" ] || return 1

	python3 - "$LICENSE_PHP" "$pem_escaped" <<'PYEOF' || return 1
import io, re, sys

path, key = sys.argv[1], sys.argv[2]
src = io.open(path, encoding='utf-8').read()

# دو‌کوتیشن الزامی است: PHP در دو‌کوتیشن \n را به newline واقعی تبدیل
# می‌کند. با تک‌کوتیشن، متن خام backslash-n نوشته می‌شد و PEM نامعتبر
# می‌ماند — همان باگی که یک بار رخ داد.
new, n = re.subn(
    r'(const\s+PUBLIC_KEY\s*=\s*)(?:\'(?:[^\'\\]|\\.)*\'|"(?:[^"\\]|\\.)*")(\s*;)',
    lambda m: m.group(1) + '"' + key + '"' + m.group(2),
    src,
    count=1,
    flags=re.S,
)

if n != 1:
    sys.exit(1)

io.open(path, 'w', encoding='utf-8').write(new)
PYEOF

	# بررسی نهایی با همان روشی که PHP می‌خواند.
	php_key_valid
}

# ─── تاریخ پیش‌فرض: یک سال بعد ───────────────────────────────────────

default_expiry() {
	if date -u -d "+1 year" +%Y-%m-%d >/dev/null 2>&1; then
		date -u -d "+1 year" +%Y-%m-%d
	else
		# BSD/macOS
		date -u -v+1y +%Y-%m-%d 2>/dev/null || date -u +%Y-%m-%d
	fi
}

# ─── ۱) setup ─────────────────────────────────────────────────────────

cmd_setup() {
	local force=0
	for a in "$@"; do
		case "$a" in
			--force|-f) force=1 ;;
		esac
	done

	echo ""
	echo "${BOLD}راه‌اندازی لایسنس SparKnife Ledger${OFF}"
	echo "$LINE"
	echo ""

	need_openssl

	# ── کلید خصوصی ──
	if [ -f "$PRIV" ] && [ "$force" -eq 0 ]; then
		ok "کلید خصوصی از قبل هست — دست نمی‌زنم"
		note "برای ساخت کلید تازه: setup --force"
		note "هشدار: با کلید تازه، همهٔ لایسنس‌های قبلی باطل می‌شوند."
	else
		if [ -f "$PRIV" ]; then
			local backup="$PRIV.bak.$(date -u +%Y%m%d%H%M%S)"
			mv "$PRIV" "$backup"
			warn "کلید قبلی به این نشانی منتقل شد:"
			note "$backup"
		fi

		openssl genpkey -algorithm RSA -pkeyopt rsa_keygen_bits:3072 -out "$PRIV" 2>/dev/null \
			|| die "ساخت کلید خصوصی ناموفق بود."

		chmod 600 "$PRIV" 2>/dev/null || true

		ok "کلید خصوصی ساخته شد"
		note "$PRIV"
	fi

	# ── کلید عمومی ──
	openssl pkey -in "$PRIV" -pubout -out "$PUB" 2>/dev/null \
		|| die "استخراج کلید عمومی از کلید خصوصی ناموفق بود."

	ok "کلید عمومی ساخته شد"
	note "$PUB"

	# ── جفت بودن ──
	local probe
	probe="$(mktemp)"
	printf 'sparkknife-pair-probe' > "$probe"
	openssl dgst -sha256 -sign "$PRIV" -out "$probe.sig" "$probe" 2>/dev/null \
		|| die "امضای آزمون ناموفق بود."

	if openssl dgst -sha256 -verify "$PUB" -signature "$probe.sig" "$probe" >/dev/null 2>&1; then
		ok "تأیید: کلید عمومی و خصوصی جفت‌اند"
	else
		rm -f "$probe" "$probe.sig"
		die "کلیدهای ساخته‌شده جفت نیستند. کلید خصوصی را پاک کنید و دوباره تلاش کنید."
	fi

	rm -f "$probe" "$probe.sig"

	# ── نوشتن در کد ──
	[ -f "$LICENSE_PHP" ] || die "فایل لایسنس در کد پیدا نشد: $LICENSE_PHP"

	if sync_public_key; then
		ok "تأیید: کلید در کد همان کلید روی دیسک است"
	else
		bad "نوشتن کلید در فایل PHP ناموفق بود"
		note "دستی انجام دهید: محتوای tools/licensing/public_key.pem را در"
		note "const PUBLIC_KEY داخل class-sparkknife-ledger-license.php بگذارید."
		exit 1
	fi

	# ── اثر انگشت ──
	local fp
	fp="$(openssl pkey -in "$PRIV" -pubout -outform DER 2>/dev/null | openssl dgst -sha256 -r | cut -d' ' -f1)"
	echo ""
	echo "$THIN"
	echo "${BOLD}اثر انگشت کلید${OFF}"
	note "$fp"
	echo ""
	printf '  %s⚠️  کلید خصوصی را گم نکنید.%s\n' "$WARN" "$OFF"
	printf '     با آن، هر لایسنس جعلی قابل ساخت است.\n'
	printf '     اگر گم شود، همهٔ لایسنس‌های صادرشده دیگر قابل تجدید نیستند.\n'
	printf '     جای امن: مدیر رمز، پشتیبان رمزگذاری‌شده. نه در گیت، نه در چت.\n'
	echo ""

	echo "$LINE"
	printf '%sآماده است.%s گام بعدی:\n\n' "$OK$BOLD" "$OFF"
	echo "  bash tools/licensing/sparkknife.sh key --domain=example.com"
	echo ""
}

# ─── ۲) key ───────────────────────────────────────────────────────────

cmd_key() {
	local domain='' edition='pro' expiry='' serial='' verify='' quiet=0

	for a in "$@"; do
		case "$a" in
			--domain=*)  domain="${a#--domain=}" ;;
			--edition=*) edition="${a#--edition=}" ;;
			--expiry=*)  expiry="${a#--expiry=}" ;;
			--serial=*)  serial="${a#--serial=}" ;;
			--verify=*)  verify="${a#--verify=}" ;;
			--forever)   expiry='' ;;
			--quiet|-q)  quiet=1 ;;
		esac
	done

	if [ -n "$verify" ]; then
		verify_key "$verify"
		return $?
	fi

	[ -f "$PRIV" ] || die "کلید خصوصی نیست. اول این را اجرا کنید:  bash tools/licensing/sparkknife.sh setup"

	# ── دامنه ──
	if [ -z "$domain" ]; then
		echo ""
		echo "${BOLD}دامنهٔ سایت مشتری${OFF}"
		echo "$THIN"
		echo "  مثال‌ها:"
		echo "    example.com          یک سایت"
		echo "    *.example.com        همهٔ زیردامنه‌های مشتری"
		echo "    *                    هر دامنه‌ای (فروش دائمی)"
		echo ""
		printf '  دامنه: '
		read -r domain || die "ورودی خوانده نشد."

		domain="$(printf '%s' "$domain" | tr -d ' \t\r' | sed -e 's|^https\?://||' -e 's|/.*$||' -e 's|^www\.||')"
	fi

	[ -n "$domain" ] || die "دامنه لازم است:  --domain=example.com"

	# ── انقضا ──
	if [ -z "$expiry" ]; then
		expiry="$(default_expiry)"
	fi

	if ! printf '%s' "$expiry" | grep -Eq '^[0-9]{4}-[0-9]{2}-[0-9]{2}$'; then
		die "تاریخ انقضا باید YYYY-MM-DD باشد، نه: $expiry"
	fi

	if ! printf '%s' "$edition" | grep -Eq '^(pro|agency)$'; then
		die "نسخه باید pro یا agency باشد، نه: $edition"
	fi

	# ── سریال ──
	if [ -z "$serial" ]; then
		if command -v openssl >/dev/null 2>&1; then
			serial="$(openssl rand -hex 4 | tr '[:lower:]' '[:upper:]')"
		else
			serial="$(date -u +%H%M%S)"
		fi
	fi

	# ── ساخت payload و امضا ──
	local tmp
	tmp="$(mktemp -d)"
	SPK_TMP="$tmp"

	printf '%s|%s|%s|%s' "$domain" "$expiry" "$edition" "$serial" > "$tmp/payload.txt"

	openssl dgst -sha256 -sign "$PRIV" -out "$tmp/sig.bin" "$tmp/payload.txt" 2>/dev/null \
		|| die "امضا ناموفق بود. کلید خصوصی سالم است؟"

	local p64 s64
	p64="$(base64 < "$tmp/payload.txt" | tr -d '\n' | tr '+/' '-_' | tr -d '=')"
	s64="$(base64 < "$tmp/sig.bin" | tr -d '\n' | tr '+/' '-_' | tr -d '=')"

	local key="SPK1.$p64.$s64"

	# ── بررسی با کلید عمومیِ داخل کد، نه فایل ──
	local pub_for_check=''
	if [ -f "$LICENSE_PHP" ] && sync_public_key 2>/dev/null; then
		pub_for_check="$PUB"
	fi

	if [ -n "$pub_for_check" ]; then
		if openssl dgst -sha256 -verify "$pub_for_check" -signature "$tmp/sig.bin" "$tmp/payload.txt" >/dev/null 2>&1; then
			verified=1
		else
			verified=0
		fi
	else
		verified=2
	fi

	# ── خروجی ──
	if [ "$quiet" -eq 1 ]; then
		printf '%s\n' "$key"
	else
		echo ""
		echo "${BOLD}لایسنس صادر شد${OFF}"
		echo "$LINE"
		echo ""
		printf '  %sدامنه%s   %s\n' "$THIN" "$OFF" "$domain"
		printf '  %sنسخه%s    %s\n'  "$THIN" "$OFF" "$edition"
		printf '  %sانقضا%s    %s\n'  "$THIN" "$OFF" "$expiry"
		printf '  %sسریال%s    %s\n'  "$THIN" "$OFF" "$serial"
		echo ""
		echo "$THIN"
		echo "${BOLD}کلید — این را به مشتری بدهید${OFF}"
		echo ""
		printf '%s\n' "$key"
		echo ""

		if [ "$verified" -eq 1 ]; then
			printf '  %s✓%s بررسی خودکار: معتبر است — با کلید عمومیِ داخل کد هم خواند\n' "$OK" "$OFF"
		elif [ "$verified" -eq 0 ]; then
			printf '  %s✗%s بررسی خودکار ناموفق — کلید عمومی در کد با کلید خصوصی جفت نیست\n' "$BAD" "$OFF"
			printf '     اجرا کنید:  bash tools/licensing/sparkknife.sh setup --force\n'
		else
			printf '  %s!%s بررسی خودکار انجام نشد (کلید عمومی در دسترس نبود)\n' "$WARN" "$OFF"
		fi

		echo ""
		echo "${BOLD}مشتری کجا وارد کند${OFF}"
		echo "  پیشخوان وردپرس → SparKnife Ledger → سلامت و امنیت → کادر «کلید لایسنس»"
		echo "  → «فعال‌سازی لایسنس»"
		echo ""

		printf '%sنکته:%s اگر مشتری سایتش را با https://www. باز می‌کند،\n' "$BOLD" "$OFF"
		printf '  دامنهٔ www به‌طور خودکار با نسخهٔ بدون www یکی حساب می‌شود.\n'
		echo ""
	fi

	[ "$verified" -eq 0 ] && return 1
	return 0
}

# ─── بررسی یک کلید موجود ─────────────────────────────────────────────

verify_key() {
	local key="$1"
	local tmp
	tmp="$(mktemp -d)"
	SPK_TMP="$tmp"

	local part count
	count="$(printf '%s' "$key" | awk -F. '{print NF}')"

	if [ "$count" -ne 3 ]; then
		bad "کلید باید سه بخش داشته باشد (SPK1.payload.signature)، این $count بخش دارد."
		return 1
	fi

	local prefix p64 s64
	prefix="$(printf '%s' "$key" | cut -d. -f1)"
	p64="$(printf '%s' "$key" | cut -d. -f2)"
	s64="$(printf '%s' "$key" | cut -d. -f3)"

	if [ "$prefix" != 'SPK1' ]; then
		bad "پیشوند کلید باید SPK1 باشد، نه: $prefix"
		return 1
	fi

	b64url_decode "$p64" > "$tmp/payload.txt" || { bad "محتوای کلید خوانا نیست."; return 1; }
	b64url_decode "$s64" > "$tmp/sig.bin"      || { bad "امضا خوانا نیست."; return 1; }

	local pub=''
	if [ -f "$PUB" ]; then
		pub="$PUB"
	fi

	[ -n "$pub" ] || { bad "کلید عمومی پیدا نشد."; return 1; }

	if ! openssl dgst -sha256 -verify "$pub" -signature "$tmp/sig.bin" "$tmp/payload.txt" >/dev/null 2>&1; then
		bad "امضا نامعتبر است — کلید جعل شده یا با کلید دیگری امضا شده."
		return 1
	fi

	ok "امضا معتبر است"

	local payload domain expiry edition serial
	payload="$(cat "$tmp/payload.txt")"

	domain="$(printf '%s' "$payload"  | cut -d'|' -f1)"
	expiry="$(printf '%s' "$payload"  | cut -d'|' -f2)"
	edition="$(printf '%s' "$payload" | cut -d'|' -f3)"
	serial="$(printf '%s' "$payload"  | cut -d'|' -f4)"

	echo ""
	printf '  دامنه  : %s\n' "$domain"
	printf '  نسخه   : %s\n' "$edition"
	printf '  انقضا  : %s\n' "$( [ -n "$expiry" ] && echo "$expiry" || echo 'بدون انقضا' )"
	printf '  سریال  : %s\n' "$serial"
	echo ""

	if [ -n "$expiry" ]; then
		local today
		today="$(date -u +%Y-%m-%d)"

		if [ "$expiry" \< "$today" ]; then
			bad "منقضی شده است (امروز: $today)"
			return 1
		fi

		ok "هنوز معتبر است (امروز: $today)"
	fi

	return 0
}

b64url_decode() {
	local s="$1"
	s="$(printf '%s' "$s" | tr -- '-_' '+/')"

	local mod=$(( ${#s} % 4 ))
	if [ "$mod" -ne 0 ]; then
		local pad=$(( 4 - mod ))
		local i=0
		while [ "$i" -lt "$pad" ]; do
			s="$s="
			i=$(( i + 1 ))
		done
	fi

	printf '%s' "$s" | base64 -d 2>/dev/null
}

# ─── ۳) pack ──────────────────────────────────────────────────────────

cmd_pack() {
	echo ""
	echo "${BOLD}ساخت بستهٔ نصب${OFF}"
	echo "$LINE"
	echo ""

	[ -d "$PLUGIN_DIR" ] || die "پوشهٔ افزونه پیدا نشد: $PLUGIN_DIR"

	if ! command -v zip >/dev/null 2>&1; then
		die "دستور zip پیدا نشد. در Git Bash باید باشد. بررسی: zip --version"
	fi

	# ── هیچ کلید خصوصی نباید در بسته باشد ──
	local leak
	leak="$(find "$PLUGIN_DIR" -name 'private_key*' -o -name '*.pem' 2>/dev/null)"

	if [ -n "$leak" ]; then
		bad "فایل کلید داخل پوشهٔ افزونه پیدا شد — بسته ساخته نشد:"
		printf '%s\n' "$leak" | while IFS= read -r f; do note "$f"; done
		die "این فایل‌ها باید بیرون از sparkknife-ledger/ باشند."
	fi

	ok "هیچ فایل کلیدی داخل بسته نیست"

	# ── نسخه ──
	local version
	version="$(grep -E "define\( 'SPARKKNIFE_LEDGER_VERSION'" "$PLUGIN_DIR/sparkknife-ledger.php" 2>/dev/null | grep -oE "[0-9]+\.[0-9]+\.[0-9]+" | head -1)"
	[ -n "$version" ] || version='0.0.0'

	# ── ساخت ──
	mkdir -p "$BUILD_DIR"

	local out="$BUILD_DIR/sparkknife-ledger-$version.zip"
	rm -f "$out"

	local stage
	stage="$(mktemp -d)"
	SPK_TMP="$stage"

	cp -r "$PLUGIN_DIR" "$stage/sparkknife-ledger"

	# فایل‌های توسعه‌ای داخل بسته نروند.
	find "$stage/sparkknife-ledger" -name '.git*' -o -name '*.map' -o -name 'node_modules' 2>/dev/null | while IFS= read -r f; do
		rm -rf "$f"
	done

	( cd "$stage" && zip -qr "$out" sparkknife-ledger ) || die "ساخت زیپ ناموفق بود."

	ok "زیپ ساخته شد"
	note "$out"

	# ── بررسی بسته ──
	local count size
	count="$(unzip -l "$out" 2>/dev/null | tail -1 | awk '{print $2}')"
	size="$(du -h "$out" 2>/dev/null | cut -f1)"

	if unzip -l "$out" 2>/dev/null | grep -q 'private_key'; then
		die "زیپ ساخته شد ولی کلید خصوصی داخلش است — پاکش کنید و دوباره بسازید."
	fi

	ok "تأیید: کلید خصوصی در زیپ نیست"

	if unzip -l "$out" 2>/dev/null | grep -qE 'sparkknife-ledger/sparkknife-ledger\.php'; then
		ok "تأیید: نام پوشهٔ داخل زیپ «sparkknife-ledger» است"
	else
		bad "ساختار پوشهٔ داخل زیپ درست نیست"
		die "وردپرس انتظار sparkknife-ledger/sparkknife-ledger.php را دارد."
	fi

	echo ""
	echo "$THIN"
	printf '  نسخه: %s\n' "$version"
	printf '  فایل: %s\n' "$count"
	printf '  حجم:  %s\n' "$size"
	echo ""

	echo "${BOLD}نصب در وردپرس${OFF}"
	echo "  پیشخوان → افزونه‌ها → افزودن → بارگذاری افزونه → انتخاب زیپ → نصب"
	echo ""

	[ -f "$PRIV" ] && printf '  %sیادآوری:%s زیپ بدون کلید خصوصی ساخته شد. جای کلید خصوصی امن است.\n' "$BOLD" "$OFF"
	echo ""
}

# ─── ۴) status ────────────────────────────────────────────────────────

cmd_status() {
	echo ""
	echo "${BOLD}وضعیت لایسنس${OFF}"
	echo "$LINE"
	echo ""

	need_openssl

	if [ -f "$PRIV" ]; then
		ok "کلید خصوصی هست"
		local fp
		fp="$(openssl pkey -in "$PRIV" -pubout -outform DER 2>/dev/null | openssl dgst -sha256 -r | cut -d' ' -f1)"
		note "اثر انگشت: $fp"
	else
		bad "کلید خصوصی نیست"
		note "اجرا کنید: bash tools/licensing/sparkknife.sh setup"
	fi

	if [ -f "$PUB" ]; then
		ok "کلید عمومی روی دیسک هست"
	else
		bad "کلید عمومی روی دیسک نیست"
	fi

	if [ -f "$LICENSE_PHP" ]; then
		if sync_public_key 2>/dev/null; then
			ok "کلید در کد با کلید دیسک هم‌تراز است"
		else
			warn "کلید در کد با کلید دیسک هم‌تراز نیست"
			note "اجرا کنید: bash tools/licensing/sparkknife.sh setup"
		fi
	else
		bad "فایل لایسنس در کد پیدا نشد"
	fi

	local v
	v="$(grep -E "define\( 'SPARKKNIFE_LEDGER_VERSION'" "$PLUGIN_DIR/sparkknife-ledger.php" 2>/dev/null | grep -oE "[0-9]+\.[0-9]+\.[0-9]+" | head -1)"
	echo ""
	printf '  نسخهٔ افزونه: %s\n' "${v:-?}"
	echo ""
}

# ─── راهنما ───────────────────────────────────────────────────────────

usage() {
	cat <<'EOF'

SparKnife Ledger — ابزار لایسنس و بسته‌بندی

  bash tools/licensing/sparkknife.sh setup [--force]
      یک بار. کلید می‌سازد و در کد می‌گذارد.

  bash tools/licensing/sparkknife.sh key --domain=example.com [--edition=pro]
      برای هر مشتری. اگر --domain ندهید، می‌پرسد.
      --edition=agency برای کل شبکه. --expiry=YYYY-MM-DD برای تاریخ دلخواه.
      --forever برای بدون انقضا. --quiet برای گرفتن فقط خود کلید.

  bash tools/licensing/sparkknife.sh key --verify=SPK1.xxx.yyy
      یک کلید موجود را بررسی می‌کند.

  bash tools/licensing/sparkknife.sh pack
      زیپ آمادهٔ نصب در build/ می‌سازد و بررسی می‌کند کلید خصوصی داخلش نباشد.

  bash tools/licensing/sparkknife.sh status
      وضعیت کلیدها و هم‌ترازی‌شان با کد.

EOF
}

# ─── مسیریابی ─────────────────────────────────────────────────────────

CMD="${1:-}"
[ $# -gt 0 ] && shift

case "$CMD" in
	setup)  cmd_setup "$@" ;;
	key)    cmd_key "$@" ;;
	pack)   cmd_pack "$@" ;;
	status) cmd_status "$@" ;;
	''|help|-h|--help) usage ;;
	*)
		printf '\n%s✗ دستور ناشناخته: %s%s\n' "$BAD" "$CMD" "$OFF" >&2
		usage
		exit 1
		;;
esac
