#!/usr/bin/env bash
#
# امضای دیجیتال بستهٔ انتشار SparKnife Ledger.
#
# چه می‌کند:
#   ۱) از فایل ZIP یک چکسام SHA-256 می‌گیرد
#   ۲) چکسام را با کلید خصوصی RSA شما امضا می‌کند
#   ۳) هر دو را کنار ZIP می‌گذارد:  .sha256  و  .sig
#
# چرا RSA و نه GPG:
#   کلید عمومی RSA از قبل داخل افزونه embed شده است
#   (tools/licensing/public_key.pem). یعنی خریدار همان کلید عمومی‌ای را
#   دارد که برای بررسی لایسنس استفاده می‌شود و می‌تواند امضای بسته را هم
#   با همان بررسی کند. یک اعتماد، دو کاربرد.
#
# استفاده:
#   ./tools/sign-release.sh sparkknife-ledger.zip
#
# پیش‌نیاز: openssl و کلید خصوصی در tools/licensing/private_key.pem
#
# ⚠️ کلید خصوصی هرگز از دستگاه شما بیرون نمی‌رود. این اسکریپت فقط
#    چکسام را امضا می‌کند، نه خود بسته را.

set -euo pipefail

ZIP="${1:-}"
KEY="${SPK_PRIVATE_KEY:-tools/licensing/private_key.pem}"

if [ -z "$ZIP" ]; then
	echo "استفاده: $0 <فایل.zip>" >&2
	exit 1
fi

if [ ! -f "$ZIP" ]; then
	echo "خطا: فایل پیدا نشد → $ZIP" >&2
	exit 1
fi

if [ ! -f "$KEY" ]; then
	cat >&2 <<-'EOF'
		خطا: کلید خصوصی پیدا نشد.

		اگر کلید را گم کرده‌اید، ساخت جفت کلید تازه را در
		tools/licensing/README.md ببینید — ولی بدانید که با کلید تازه،
		همهٔ لایسنس‌های صادرشدهٔ قبلی باطل می‌شوند.
	EOF
	exit 1
fi

command -v openssl >/dev/null || { echo "خطا: openssl نصب نیست" >&2; exit 1; }

BASE="${ZIP%.zip}"
SHA_FILE="${BASE}.sha256"
SIG_FILE="${BASE}.sig"

echo "→ چکسام SHA-256 …"
# فقط نام فایل پایه در فایل چکسام، تا با `sha256sum -c` هر جایی کار کند.
( cd "$(dirname "$ZIP")" && openssl dgst -sha256 -r "$(basename "$ZIP")" | awk '{print $1}' > "$(basename "$SHA_FILE")" )

echo "→ امضای RSA …"
openssl dgst -sha256 \
	-sign "$KEY" \
	-out "$SIG_FILE" \
	"$ZIP"

HASH=$(cat "$SHA_FILE")
echo
echo "✓ امضا ساخته شد"
echo
echo "  بسته    : $ZIP"
echo "  چکسام  : $SHA_FILE"
echo "  امضا    : $SIG_FILE"
echo "  SHA-256 : $HASH"
echo
echo "این سه فایل را با هم منتشر کنید. خریدار با این دستور بررسی می‌کند:"
echo "  ./tools/verify-release.sh $ZIP tools/licensing/public_key.pem"
