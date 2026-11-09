#!/usr/bin/env bash
#
# ساخت جفت کلید لایسنس SparKnife Ledger — بدون نیاز به PHP.
#
# چرا این نسخه: Git Bash روی ویندوز خودش openssl دارد. اگر PHP نصب
# نکرده‌اید، همین اسکریپت کل کار را انجام می‌دهد.
#
# استفاده (از ریشهٔ پوشهٔ پروژه، در ترمینال VS Code):
#
#   bash tools/licensing/make-keys.sh
#
# اگر کلید از قبل هست و می‌خواهید بازنویسی کنید:
#
#   bash tools/licensing/make-keys.sh --force
#
# @author  SparKnife <155084046+sparkknife@users.noreply.github.com>

set -u

FORCE=0
if [ "${1:-}" = "--force" ] || [ "${1:-}" = "-f" ]; then
	FORCE=1
fi

_SELF="${BASH_SOURCE[0]}"
[ -f "$_SELF" ] || _SELF="$0"
DIR="$(cd "$(dirname "$_SELF")" && pwd)"
ROOT="$(cd "$DIR/../.." && pwd)"

PRIV="$DIR/private_key.pem"
PUB="$DIR/public_key.pem"
LICENSE_FILE="$ROOT/sparkknife-ledger/includes/Core/class-sparkknife-ledger-license.php"

LINE="══════════════════════════════════════════════════"

echo ""
echo "ساخت جفت کلید SparKnife Ledger"
echo "$LINE"

# ─── ۱) پیش‌نیاز: openssl ─────────────────────────────────────────────

if ! command -v openssl >/dev/null 2>&1; then
	echo ""
	echo "✗ openssl پیدا نشد."
	echo ""
	echo "  در Git Bash روی ویندوز باید باشد. بررسی کنید:"
	echo "    openssl version"
	echo ""
	echo "  اگر نبود، Git for Windows را دوباره نصب کنید:"
	echo "    https://git-scm.com/download/win"
	echo ""
	exit 1
fi

echo "✓ openssl هست"

# ─── ۲) پیش‌نیاز: مسیر پروژه ──────────────────────────────────────────

if [ ! -f "$LICENSE_FILE" ]; then
	echo ""
	echo "✗ فایل افزونه پیدا نشد:"
	echo "    $LICENSE_FILE"
	echo ""
	echo "  یعنی یکی از این دو اتفاق افتاده:"
	echo ""
	echo "    ۱) اسکریپت را از پوشهٔ اشتباه اجرا کرده‌اید"
	echo "    ۲) زیپ ناقص است"
	echo ""
	echo "  این دستور را بزنید:"
	echo ""
	echo "    ls"
	echo ""
	echo "  باید این‌ها را ببینید:"
	echo "    docs  sparkknife-ledger  tools"
	echo ""
	echo "  اگر فقط README.md دیدید، زیپ اشتباه است. از این آدرس بگیرید:"
	echo "    https://github.com/sparkknife/SparKnife-ledger"
	echo ""
	exit 1
fi

echo "✓ مسیر پروژه درست است"

# ─── ۳) محافظت از کلید موجود ──────────────────────────────────────────

if [ -f "$PRIV" ] && [ "$FORCE" -eq 0 ]; then
	echo ""
	echo "⚠️  کلید خصوصی از قبل وجود دارد:"
	echo "    $PRIV"
	echo ""
	echo "  اگر بازنویسی شود، همهٔ لایسنس‌هایی که صادر کرده‌اید باطل می‌شوند."
	echo ""
	echo "  اگر مطمئنید، با این دستور اجرا کنید:"
	echo "    bash tools/licensing/make-keys.sh --force"
	echo ""
	exit 1
fi

# ─── ۴) ساخت کلید ─────────────────────────────────────────────────────

echo ""
printf "→ ساخت کلید ۳۰۷۲ بیتی … "

if ! openssl genpkey -algorithm RSA -pkeyopt rsa_keygen_bits:3072 -out "$PRIV" 2>/dev/null; then
	echo "✗"
	echo ""
	echo "  خطا در ساخت کلید."
	echo "  بررسی کنید: openssl version"
	echo "  باید ۱.۱ یا بالاتر باشد."
	echo ""
	exit 1
fi

if ! openssl rsa -in "$PRIV" -pubout -out "$PUB" 2>/dev/null; then
	echo "✗"
	echo "  خطا در ساخت کلید عمومی."
	exit 1
fi

if [ ! -s "$PRIV" ] || [ ! -s "$PUB" ]; then
	echo "✗"
	echo "  فایل کلید خالی است — نصب openssl ناقص است."
	exit 1
fi

chmod 600 "$PRIV" 2>/dev/null || true

echo "✓"
echo "✓ کلید خصوصی  → tools/licensing/private_key.pem"
echo "✓ کلید عمومی  → tools/licensing/public_key.pem"

# ─── ۵) اثر انگشت ─────────────────────────────────────────────────────

FP=$(openssl dgst -sha256 -r "$PUB" | cut -d' ' -f1)

echo ""
echo "اثر انگشت (SHA-256):"
echo "  $FP"

# ─── ۶) جایگزینی PUBLIC_KEY ───────────────────────────────────────────

echo ""
echo "$LINE"
printf "→ جایگزینی PUBLIC_KEY در کد … "

TMP_FILE="$LICENSE_FILE.spk-tmp"

# awk بلوک قدیمی را با کلید تازه عوض می‌کند:
# از خط «const PUBLIC_KEY = » شروع، تا خط «-----END PUBLIC KEY-----»
# رد می‌شود، و جای آن یک خط تازه با کلید کامل می‌نشیند.
awk -v pubfile="$PUB" '
BEGIN {
	pem = ""
	while ((getline line < pubfile) > 0) {
		if (pem != "") pem = pem "\n"
		pem = pem line
	}
	close(pubfile)
	skipping = 0
	done = 0
}
{
	if (!done && $0 ~ /const PUBLIC_KEY = /) {
		print "\tconst PUBLIC_KEY = \047" pem "\047;"
		skipping = 1
		next
	}
	if (skipping) {
		if ($0 ~ /-----END PUBLIC KEY-----/) {
			skipping = 0
			done = 1
		}
		next
	}
	print
}
' "$LICENSE_FILE" > "$TMP_FILE"

# ─── ۷) بررسی نتیجه پیش از جایگزینی ───────────────────────────────────

if [ ! -s "$TMP_FILE" ]; then
	echo "✗ فایل نتیجه خالی است — لغو شد."
	rm -f "$TMP_FILE"
	exit 1
fi

if ! grep -q -- "-----BEGIN PUBLIC KEY-----" "$TMP_FILE" || \
   ! grep -q -- "-----END PUBLIC KEY-----" "$TMP_FILE"; then
	echo "✗ کلید در فایل نتیجه کامل نیست — لغو شد."
	rm -f "$TMP_FILE"
	exit 1
fi

KEY_LINE=$(sed -n '2p' "$PUB")

if ! grep -qF "$KEY_LINE" "$TMP_FILE"; then
	echo "✗ کلید تازه در فایل نتیجه نیست — لغو شد."
	rm -f "$TMP_FILE"
	exit 1
fi

mv "$TMP_FILE" "$LICENSE_FILE"

echo "✓"

# ─── ۸) تأیید نهایی ───────────────────────────────────────────────────

PROBE="$DIR/.probe"
SIGFILE="$DIR/.sig"

echo -n "sparkknife-self-test" > "$PROBE"

if ! openssl dgst -sha256 -sign "$PRIV" -out "$SIGFILE" "$PROBE" 2>/dev/null; then
	echo "✗ امضا زده نشد."
	rm -f "$PROBE" "$SIGFILE"
	exit 1
fi

if openssl dgst -sha256 -verify "$PUB" -signature "$SIGFILE" "$PROBE" >/dev/null 2>&1; then
	echo "✓ تأیید: کلیدهای عمومی و خصوصی جفت‌اند"
else
	echo "✗ کلیدها جفت نیستند."
	rm -f "$PROBE" "$SIGFILE"
	exit 1
fi

rm -f "$PROBE" "$SIGFILE"

if grep -qF "$KEY_LINE" "$LICENSE_FILE"; then
	echo "✓ تأیید: کلید در کد همان کلید روی دیسک است"
else
	echo "✗ کلید در کد نیست."
	exit 1
fi

# ─── ۹) پشتیبان ───────────────────────────────────────────────────────

echo ""
echo "$LINE"
echo ""
echo "⚠️  همین حالا پشتیبان بگیرید"
echo ""
echo "   کلید خصوصی شما:"
echo ""
cat "$PRIV"
echo ""
echo "   از -----BEGIN PRIVATE KEY----- تا -----END PRIVATE KEY-----"
echo "   را کامل کپی کنید و در دو جا بگذارید:"
echo "     ۱) گاوصندوق رمز (1Password / Bitwarden)"
echo "     ۲) یک USB یا پرینت در جای امن"
echo ""
echo "   اگر این کلید گم شود، همهٔ لایسنس‌های صادرشده باطل می‌شوند."
echo ""
echo "$LINE"
echo "✓ آماده. گام بعد: آزمون لایسنس"
echo ""
