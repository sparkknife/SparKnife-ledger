#!/usr/bin/env bash
#
# اجرای همهٔ بررسی‌های استاتیک با یک دستور.
#
# چه می‌کند:
#   ۱) lint          — تجزیهٔ نحوی همهٔ فایل‌های PHP
#   ۲) static-audit  — انسجام کلاس‌ها، ارجاع‌های شکسته، ثابت‌ها
#   ۳) rules         — ABSPATH guard، SQL، nonce، escape
#   ۴) php-compat    — سازگاری با PHP 7.4
#   ۵) authorship    — امضای نویسنده و نبود کلید خصوصی
#
# پیش‌نیاز: node و بستهٔ php-parser (`npm install php-parser`)
#
# استفاده:
#   ./tools/check-all.sh
#
# خروج صفر = همه سبز. یک = چیزی سر جایش نیست.
#
# این بررسی‌ها به PHP یا وردپرس نیاز ندارند. آزمون یکپارچه
# (tests/run-tests.php) جداگانه و روی نصب واقعی وردپرس اجرا می‌شود.

set -uo pipefail

cd "$(dirname "$0")/.." || exit 2

FAIL=0
WIDTH=22

run() {
	local name="$1"
	local script="$2"
	printf "%-${WIDTH}s " "$name"
	local out
	if out=$(node "$script" 2>&1); then
		echo "$out" | tail -1
	else
		echo "$out" | tail -3
		FAIL=1
	fi
}

echo "بررسی‌های استاتیک SparKnife Ledger"
echo "══════════════════════════════════════════════════"

run "lint" tools/lint.mjs
run "انسجام کلاس‌ها" tools/static-audit.mjs
run "قواعد امنیتی" tools/static-audit-rules.mjs
run "سازگاری PHP" tools/php-compat.mjs
run "امضای نویسنده" tools/check-authorship.mjs

echo "══════════════════════════════════════════════════"

if [ "$FAIL" -eq 0 ]; then
	echo "همهٔ بررسی‌ها موفق ✓"
	exit 0
fi

echo "بررسی ناموفق ✗" >&2
exit 1
