#!/usr/bin/env bash
#
# صدور کلید لایسنس SparKnife Ledger — بدون نیاز به PHP.
#
# چرا این نسخه: issue.php به PHP نیاز دارد. اگر روی سیستم شما PHP
# نصب نیست (فقط Git Bash دارید)، این اسکریپت همان کار را با openssl
# انجام می‌دهد.
#
# استفاده:
#   bash tools/licensing/issue.sh --domain=example.com --edition=pro
#   bash tools/licensing/issue.sh --domain=example.com --edition=pro --expiry=2027-12-31
#   bash tools/licensing/issue.sh --verify=SPK1.xxx.yyy
#
# @author  SparKnife <155084046+sparkknife@users.noreply.github.com>

set -u

_SELF="${BASH_SOURCE[0]}"
[ -f "$_SELF" ] || _SELF="$0"
DIR="$(cd "$(dirname "$_SELF")" && pwd)"

PRIV="${SPK_PRIVATE_KEY:-$DIR/private_key.pem}"
PUB="${SPK_PUBLIC_KEY:-$DIR/public_key.pem}"

DOMAIN=""
EDITION="pro"
EXPIRY=""
SERIAL=""
VERIFY=""

# ─── خواندن آرگومان‌ها ────────────────────────────────────────────────

for arg in "$@"; do
	case "$arg" in
		--domain=*)  DOMAIN="${arg#--domain=}" ;;
		--edition=*) EDITION="${arg#--edition=}" ;;
		--expiry=*)  EXPIRY="${arg#--expiry=}" ;;
		--serial=*)  SERIAL="${arg#--serial=}" ;;
		--verify=*)  VERIFY="${arg#--verify=}" ;;
		--help|-h)
			echo ""
			echo "صدور کلید لایسنس SparKnife Ledger"
			echo ""
			echo "  --domain=NAME     دامنه (الزامی) — مثال: example.com یا *.example.com"
			echo "  --edition=NAME    pro یا agency (پیش‌فرض: pro)"
			echo "  --expiry=DATE     تاریخ انقضا YYYY-MM-DD (پیش‌فرض: بدون انقضا)"
			echo "  --serial=TEXT     سریال دلخواه (پیش‌فرض: تصادفی)"
			echo "  --verify=KEY      بررسی یک کلید موجود"
			echo ""
			echo "مثال:"
			echo "  bash tools/licensing/issue.sh --domain=example.com --edition=pro --expiry=2027-12-31"
			echo ""
			exit 0
			;;
	esac
done

# ─── پیش‌نیازها ────────────────────────────────────────────────────────

if ! command -v openssl >/dev/null 2>&1; then
	echo "✗ openssl پیدا نشد." >&2
	echo "  در Git Bash باید باشد. بررسی کنید: openssl version" >&2
	exit 1
fi

if ! command -v base64 >/dev/null 2>&1; then
	echo "✗ base64 پیدا نشد." >&2
	echo "  در Git Bash باید باشد." >&2
	exit 1
fi

# ─── decode/encode پایه ───────────────────────────────────────────────

# base64url بدون padding
b64url() {
	openssl base64 -A | tr '+/' '-_' | tr -d '='
}

b64url_decode() {
	local s="$1"
	# بازگرداندن padding
	case $(( ${#s} % 4 )) in
		2) s="${s}==" ;;
		3) s="${s}=" ;;
	esac
	printf '%s' "$s" | tr -- '-_' '+/' | openssl base64 -d -A 2>/dev/null
}

# ─── حالت verify ───────────────────────────────────────────────────────

if [ -n "$VERIFY" ]; then
	echo ""
	echo "بررسی کلید لایسنس"
	echo "══════════════════════════════════════════════════"

	if [ ! -f "$PUB" ]; then
		echo "✗ کلید عمومی پیدا نشد: $PUB" >&2
		exit 1
	fi

	PREFIX="${VERIFY%%.*}"
	REST="${VERIFY#*.}"
	B64PAYLOAD="${REST%%.*}"
	B64SIG="${REST#*.}"

	if [ "$PREFIX" != "SPK1" ]; then
		echo "✗ پیشوند کلید درست نیست (باید SPK1 باشد)."
		exit 1
	fi

	PAYLOAD=$(b64url_decode "$B64PAYLOAD")

	if [ -z "$PAYLOAD" ]; then
		echo "✗ محتوای کلید خوانا نیست — احتمالاً کپی ناقص شده."
		exit 1
	fi

	# امضا را روی دیسک بنویس
	TMPDIR_SIG="$(mktemp -d 2>/dev/null || echo "$DIR/.tmp")"
	[ -d "$TMPDIR_SIG" ] || mkdir -p "$TMPDIR_SIG"

	printf '%s' "$B64SIG" | {
		case $(( ${#B64SIG} % 4 )) in
			2) printf '%s==' "$B64SIG" ;;
			3) printf '%s=' "$B64SIG" ;;
			*) printf '%s' "$B64SIG" ;;
		esac
	} | tr -- '-_' '+/' | openssl base64 -d -A > "$TMPDIR_SIG/sig.bin" 2>/dev/null

	printf '%s' "$PAYLOAD" > "$TMPDIR_SIG/payload.txt"

	if openssl dgst -sha256 -verify "$PUB" -signature "$TMPDIR_SIG/sig.bin" "$TMPDIR_SIG/payload.txt" >/dev/null 2>&1; then
		echo "✓ امضا معتبر است"
		echo ""

		# تفکیک فیلدها
		IFS='|' read -r F_DOMAIN F_EXPIRY F_EDITION F_SERIAL <<< "$PAYLOAD"

		echo "  دامنه  : $F_DOMAIN"
		echo "  نسخه   : $F_EDITION"
		echo "  انقضا  : ${F_EXPIRY:-بدون انقضا}"
		echo "  سریال  : $F_SERIAL"
		echo ""

		if [ -n "$F_EXPIRY" ]; then
			TODAY=$(date +%Y-%m-%d)
			if [[ "$F_EXPIRY" < "$TODAY" ]]; then
				echo "  ⚠️  منقضی شده (امروز: $TODAY)"
			else
				echo "  ✓ هنوز معتبر است (امروز: $TODAY)"
			fi
		fi

		rm -rf "$TMPDIR_SIG"
		exit 0
	else
		echo "✗ امضا نامعتبر است"
		echo "  کلید یا دست‌کاری شده، یا با کلید خصوصی دیگری امضا شده."
		rm -rf "$TMPDIR_SIG"
		exit 1
	fi
fi

# ─── حالت صدور ─────────────────────────────────────────────────────────

if [ -z "$DOMAIN" ]; then
	echo "✗ دامنه لازم است: --domain=example.com  (یا --domain=*)" >&2
	echo "" >&2
	echo "  مثال:" >&2
	echo "    bash tools/licensing/issue.sh --domain=example.com --edition=pro" >&2
	echo "" >&2
	exit 1
fi

case "$EDITION" in
	pro|agency) ;;
	*)
		echo "✗ نسخه باید pro یا agency باشد." >&2
		exit 1
		;;
esac

if [ -n "$EXPIRY" ] && ! [[ "$EXPIRY" =~ ^[0-9]{4}-[0-9]{2}-[0-9]{2}$ ]]; then
	echo "✗ تاریخ انقضا باید YYYY-MM-DD باشد." >&2
	exit 1
fi

if [ ! -f "$PRIV" ]; then
	echo "✗ کلید خصوصی پیدا نشد:" >&2
	echo "    $PRIV" >&2
	echo "" >&2
	echo "  اول این را بزنید:" >&2
	echo "    bash tools/licensing/make-keys.sh" >&2
	echo "" >&2
	exit 1
fi

if [ -z "$SERIAL" ]; then
	SERIAL=$(openssl rand -hex 4 | tr 'a-f' 'A-F')
fi

PAYLOAD="$DOMAIN|$EXPIRY|$EDITION|$SERIAL"

# امضا
TMPDIR_SIGN="$(mktemp -d 2>/dev/null || echo "$DIR/.tmp")"
[ -d "$TMPDIR_SIGN" ] || mkdir -p "$TMPDIR_SIGN"

printf '%s' "$PAYLOAD" > "$TMPDIR_SIGN/payload.txt"

if ! openssl dgst -sha256 -sign "$PRIV" -out "$TMPDIR_SIGN/sig.bin" "$TMPDIR_SIGN/payload.txt" 2>/dev/null; then
	echo "✗ امضا کردن ناموفق بود." >&2
	rm -rf "$TMPDIR_SIGN"
	exit 1
fi

B64P=$(printf '%s' "$PAYLOAD" | b64url)
B64S=$(b64url < "$TMPDIR_SIGN/sig.bin")

KEY="SPK1.$B64P.$B64S"

echo ""
echo "کلید لایسنس صادر شد:"
echo ""
echo "$KEY"
echo ""
echo "  دامنه  : $DOMAIN"
echo "  نسخه   : $EDITION"
echo "  انقضا  : ${EXPIRY:-بدون انقضا}"
echo "  سریال  : $SERIAL"
echo ""

# بررسی خودکار
if [ -f "$PUB" ] && openssl dgst -sha256 -verify "$PUB" -signature "$TMPDIR_SIGN/sig.bin" "$TMPDIR_SIGN/payload.txt" >/dev/null 2>&1; then
	echo "بررسی خودکار: معتبر ✓"
	rm -rf "$TMPDIR_SIGN"
	exit 0
fi

echo "✗ بررسی خودکار شکست خورد! کلید را استفاده نکنید." >&2
rm -rf "$TMPDIR_SIGN"
exit 1
