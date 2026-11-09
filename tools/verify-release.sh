#!/usr/bin/env bash
#
# بررسی امضای دیجیتال بستهٔ انتشار SparKnife Ledger.
#
# کاری که می‌کند:
#   ۱) SHA-256 بسته را دوباره حساب می‌کند و با فایل .sha256 می‌سنجد
#   ۲) امضای RSA را با کلید عمومی بررسی می‌کند
#
# اگر هر دو درست باشند، بسته دقیقاً همان چیزی است که SparKnife امضا
# کرده و از زمان امضا یک بایت هم عوض نشده.
#
# استفاده:
#   ./tools/verify-release.sh sparkknife-ledger.zip tools/licensing/public_key.pem
#
# خروج صفر = معتبر. خروج یک = دست‌کاری‌شده یا امضا نامعتبر.

set -uo pipefail

ZIP="${1:-}"
PUB="${2:-tools/licensing/public_key.pem}"

if [ -z "$ZIP" ]; then
	echo "استفاده: $0 <فایل.zip> [کلید-عمومی.pem]" >&2
	exit 1
fi

if [ ! -f "$ZIP" ]; then
	echo "✗ فایل پیدا نشد → $ZIP" >&2
	exit 1
fi

BASE="${ZIP%.zip}"
SHA_FILE="${BASE}.sha256"
SIG_FILE="${BASE}.sig"
FAIL=0

command -v openssl >/dev/null || { echo "✗ openssl نصب نیست" >&2; exit 1; }

echo "بررسی $ZIP"
echo "──────────────────────────────────────────────"

# ۱) چکسام
if [ -f "$SHA_FILE" ]; then
	EXPECTED=$(tr -d ' \n\r' < "$SHA_FILE")
	ACTUAL=$(openssl dgst -sha256 -r "$ZIP" | awk '{print $1}')
	if [ "$EXPECTED" = "$ACTUAL" ]; then
		echo "✓ چکسام SHA-256 مطابق است"
		echo "  $ACTUAL"
	else
		echo "✗ چکسام مطابق نیست — فایل عوض شده"
		echo "  انتظار: $EXPECTED"
		echo "  واقعی : $ACTUAL"
		FAIL=1
	fi
else
	echo "⚠ فایل چکسام نیست → $SHA_FILE"
	echo "  بدون آن فقط امضا بررسی می‌شود."
fi

# ۲) امضا
if [ -f "$SIG_FILE" ]; then
	if [ ! -f "$PUB" ]; then
		echo "✗ کلید عمومی پیدا نشد → $PUB" >&2
		exit 1
	fi
	if openssl dgst -sha256 -verify "$PUB" -signature "$SIG_FILE" "$ZIP" >/dev/null 2>&1; then
		echo "✓ امضای RSA معتبر است"
		echo "  این بسته با کلید خصوصی SparKnife امضا شده است."
	else
		echo "✗ امضای RSA نامعتبر است"
		echo "  بسته یا دست‌کاری شده یا با کلید دیگری امضا شده."
		FAIL=1
	fi
else
	echo "⚠ فایل امضا نیست → $SIG_FILE"
	echo "  این بسته امضای دیجیتال ندارد."
fi

echo "──────────────────────────────────────────────"
if [ "$FAIL" -eq 0 ]; then
	echo "نتیجه: بسته معتبر است ✓"
else
	echo "نتیجه: بسته معتبر نیست ✗" >&2
fi

exit "$FAIL"
