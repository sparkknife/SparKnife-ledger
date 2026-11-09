# SparKnife Ledger

افزونه دفتر حساب اشخاص برای وردپرس — ثبت تراکنش‌های ورودی و خروجی، محاسبه خودکار مانده، گزارش فردی و تجمیعی، تاریخچه تغییرات، تقویم شمسی و رابط راست‌به‌چپ.

[![WordPress](https://img.shields.io/badge/WordPress-5.8%2B-21759b)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-777bb4)](https://www.php.net/)
[![License](https://img.shields.io/badge/License-GPLv2%2B-blue)](https://www.gnu.org/licenses/gpl-2.0.html)
[![Tests](https://img.shields.io/badge/tests-990%20assertions-brightgreen)](#تستها)

---

## فهرست

- [نگاه کلی](#نگاه-کلی)
- [نصب](#نصب)
- [شروع سریع](#شروع-سریع)
- [امکانات](#امکانات)
- [امنیت](#امنیت)
- [لایسنس](#لایسنس)
- [ساختار داده](#ساختار-داده)
- [توسعه](#توسعه)
- [تست‌ها](#تستها)
- [بسته‌بندی](#بستهبندی)
- [مجوز](#مجوز)

---

## نگاه کلی

SparKnife Ledger یک دفتر حساب واقعی است، نه یک فروشگاه. هر شخص مانده دارد، هر تراکنش شماره سند یکتا دارد، تراکنش‌ها هرگز حذف فیزیکی نمی‌شوند (فقط ابطال می‌شوند) و هر تغییر در تاریخچه ثبت می‌گردد.

دو تصمیم طراحی که بقیه چیزها را شکل داده‌اند:

1. **مبالغ به‌صورت عدد صحیح مقیاس‌شده ذخیره می‌شوند** — مبلغ × ۱۰⁴ در `BIGINT`. هیچ `float`، هیچ خطای گرد کردن.
2. **سوابق حسابداری دستکاری‌ناپذیرند** — حذف همیشه نرم است و ابطال جای حذف را می‌گیرد.

---

## نصب

### از پنل وردپرس

1. فایل ZIP را از مسیر **افزونه‌ها ← افزودن ← بارگذاری افزونه** انتخاب و نصب کنید.
2. افزونه را فعال کنید.
3. منوی **دفتر حساب** در پنل ظاهر می‌شود.

### به‌صورت دستی

```bash
cd wp-content/plugins
unzip sparkknife-ledger.zip
# سپس از پنل وردپرس فعال کنید
```

### پیش‌نیازها

| مورد | حداقل |
| --- | --- |
| WordPress | 5.8 |
| PHP | 7.4 (سازگار تا 8.x) |
| دیتابیس | MySQL 5.7+ / MariaDB 10.3+ یا درایور SQLite رسمی وردپرس |

نصب هیچ وابستگی خارجی (Composer، npm، افزونه کمکی) ندارد.

---

## شروع سریع

پس از فعال‌سازی، اگر سایت هنوز داده‌ای ندارد، وردپرس شما را به ویزارد
راه‌اندازی می‌برد: واحد پول، تقویم و اولین شخص را در چهار مرحله می‌پرسد.
می‌توانید ردش کنید و بعداً از تنظیمات انجام دهید.

مسیر دستی:

1. **دفتر حساب ← اشخاص** → یک شخص بسازید (نام الزامی است).
2. **دفتر حساب ← تراکنش‌ها** → اولین دریافت یا پرداخت را ثبت کنید.
3. **دفتر حساب ← داشبورد** → جمع ورودی، جمع خروجی و مانده کل را ببینید.
4. **دفتر حساب ← تنظیمات** → واحد پول، تقویم و تعداد آیتم در هر صفحه را تنظیم کنید.

اشتباه در ثبت؟ تراکنش را **ویرایش** کنید؛ اگر نباید در حساب باشد، **ابطال** کنید (تاریخچه حفظ می‌شود).

---

## امکانات

### اشخاص

- افزودن، ویرایش، غیرفعال‌سازی
- **حذف نرم** با امکان بازگردانی — ردیف در دیتابیس می‌ماند، `deleted_at` پر می‌شود
- جلوگیری از نام تکراری (فقط میان اشخاص فعال)
- جلوگیری از حذف شخصی که تراکنش دارد (قابل تغییر در تنظیمات)
- مانده و وضعیت حساب (بدهکار / بستانکار / تسویه) در فهرست

### تراکنش‌ها

- نوع `income` / `expense`، وضعیت `posted` / `void`
- شماره سند یکتا با پیشوند قابل تنظیم (پیش‌فرض `TXN`)
- تاریخ شمسی یا میلادی، با اعتبارسنجی کبیسه
- **ابطال** با دلیل الزامی (قابل خاموش کردن) — مبلغ از محاسبات خارج می‌شود
- ویرایش تراکنش باطل ممنوع است

### گزارش‌ها

| گزارش | فیلترها |
| --- | --- |
| فردی | شخص، بازه تاریخ، نوع، وضعیت، جستجو |
| تجمیعی | بازه تاریخ، نوع، وضعیت، مرتب‌سازی |

بازه‌های آماده (امروز، این هفته، این ماه، امسال) از هوک `sparkknife_ledger_preset_ranges` قابل گسترش است.

خروجی: **CSV** (سازگار با Excel، با BOM و ارقام فارسی) و **نمایش چاپ/PDF**.

**خروجی کامل داده** (تنظیمات ← خروجی کامل داده): همهٔ اشخاص،
تراکنش‌ها و تاریخچه در یک فایل CSV برای آرشیو یا انتقال به سیستم
دیگر. ستون‌های خام جدول می‌آیند، نه ستون‌های مشتق‌شده — مبلغ به‌صورت
عدد صحیح مقیاس‌شده تا بازگرداندنش بی‌خطا باشد. تراکنش‌های باطل هم
می‌آیند تا تاریخچه کامل بماند. جای پشتیبان دیتابیس را نمی‌گیرد.

### تاریخچه تغییرات

هر عملیات با `object_type`, `object_id`, `action`, `changes` (JSON قبل/بعد) و `hash` ثبت می‌شود. `hash` زنجیره‌ای است (هشِ رکورد قبلی + رکورد جاری) تا حذف یا دستکاری یک ردیف، زنجیره را بشکند.

### ویزارد راه‌اندازی

پس از نصب، اگر سایت داده‌ای ندارد، ویزارد چهارمرحله‌ای باز می‌شود:
خوش‌آمد → واحد پول و تقویم → اولین شخص → پایان. از منو مخفی است و
کامل قابل رد کردن.

فقط برای کاربر تازه‌نصب نمایش داده می‌شود (مدیر کل، بدون داده، و
گزینهٔ «انجام‌شده» ثبت نشده). کاربری که داده دارد هرگز آن را نمی‌بیند.

### تقویم و ارقام

- تبدیل دوطرفه شمسی ↔ میلادی با اعتبارسنجی کامل
- پذیرش ورودی: `۱۲۳`, `۱۲۳٬۴۵۶`, `1,250,000`
- نمایش با ارقام فارسی، جداکننده هزارگان قابل تنظیم

---

## امنیت

امنیت این افزونه در سه لایه است، نه یک چک‌باکس.

**۱) کنترل دسترسی.** هر صفحه با همان capability‌ای نگهبانی می‌شود که
در ثبت منو اعلام شده — دو منبع حقیقت نداریم. هر فرم POST یک nonce
اختصاصی دارد (`sparkknife_ledger_<action>`)، پس nonce یک فرم در فرم
دیگر کار نمی‌کند.

**۲) محدودیت تلاش.** nonce ثابت می‌کند درخواست از فرم خودمان آمده،
ولی نمی‌گوید چند بار. روی عملیات حساس سقف گذاشته شده:

| عملیات | سقف | پنجره |
| --- | --- | --- |
| پاک‌سازی داده | ۳ | ۱۵ دقیقه |
| تغییر دسترسی‌ها | ۱۰ | ۱۵ دقیقه |
| ذخیرهٔ تنظیمات | ۳۰ | ۱۰ دقیقه |
| ابطال تراکنش | ۶۰ | ۱۰ دقیقه |

کلید محدودیت `user_id + IP` است: با IP تنها یک مهاجم بدون نشست
می‌تواند مدیر را قفل کند؛ با کاربر تنها مهاجم با چند حساب دور می‌زند.

**۳) گزارش و پایش.** صفحهٔ **سلامت و امنیت** هفت بررسی سطح سایت
(HTTPS، نسخهٔ PHP، OpenSSL، دسترسی پوشهٔ آپلود، نمایش خطای دیتابیس،
لایسنس، پیشوند جدول) و ۱۰۰ رویداد حساس آخر را نشان می‌دهد.

نکته‌ای که خودش می‌تواند تله شود: گزارش امنیتی نباید منبع نشت شود.
هر کلیدی در زمینهٔ رویداد که نامش شامل password/token/secret/key/
nonce/cookie باشد با `[حذف‌شده]` جایگزین می‌شود.

تمام خروجی‌ها از `esc_html`/`esc_attr`/`esc_url` عبور می‌کنند، کوئری‌ها
از `$wpdb->prepare()` و نام جدول‌ها از فهرست داخلی می‌آیند.

---

## لایسنس

نسخهٔ تجاری با امضای دیجیتال **آفلاین** بررسی می‌شود — بدون هیچ
تماس شبکه‌ای.

```
SPK1.<payload به base64url>.<امضای RSA-SHA256 به base64url>
```

payload امضاشده: `domain|expiry|edition|serial`

افزونه کلید عمومی را در کد دارد و امضا را با `openssl_verify` بررسی
می‌کند. چون محتوا امضاشده است، تغییر دامنه یا تاریخ، امضا را باطل
می‌کند.

**چرا آفلاین و نه سرور:** برای نرم‌افزار مالی، وابستگی به در دسترس
بودن سرور فروشنده ریسک غیرقابل‌قبولی است. اگر سرور بخوابد،
حسابداری مشتری هم می‌خوابد.

جزئیات:

- مقایسهٔ دامنه `www` را نادیده می‌گیرد و `*.example.com` را می‌پذیرد
- بررسی امضا ۶ ساعت کش می‌شود تا در هر بارگذاری صفحه انجام نشود
- کلید نامعتبر **ذخیره نمی‌شود**، پس لایسنس قبلی دست نمی‌خورد
- بدون میزبان (CLI/PHPUnit) بررسی دامنه رد نمی‌شود
- بدون امضا هم افزونه کار می‌کند؛ فقط نسخهٔ `free` است

**محدودیتی که صادقانه باید گفت:** هر سیستم لایسنسی که کدش در اختیار
خریدار باشد قابل دور زدن است. این پیاده‌سازی جلوی استفادهٔ عادی و
توزیع یک کلید بین چند سایت را می‌گیرد، نه کسی که کد را تغییر می‌دهد.
ادعای خلاف این، فروش نادرست است.

مدیریت: **دفتر حساب ← سلامت و امنیت** ← بخش لایسنس.

---

## ساختار داده

### جداول

| جدول | نقش |
| --- | --- |
| `{prefix}sparkknife_ledger_persons` | اشخاص، با حذف نرم |
| `{prefix}sparkknife_ledger_transactions` | تراکنش‌ها، با اطلاعات ابطال |
| `{prefix}sparkknife_ledger_audit_log` | تاریخچه تغییرات با زنجیره هش |

### ستون مبلغ

```
amount BIGINT NOT NULL DEFAULT 0     -- مبلغ × 10^4
```

`AMOUNT_SCALE = 4` یعنی `1,250,000 تومان` به‌صورت `12500000000` ذخیره می‌شود. خواندن و نوشتن همیشه از `Sparkknife_Ledger_Amount` و `Sparkknife_Ledger_Money` عبور می‌کند، هرگز با `intval` یا عملگرهای اعشاری.

> **هشدار:** روی PHP 32-بیتی، `(int)`, `%d`, `max()`, `abs()` و `<=>` روی مبالغ سرریز می‌کنند. همیشه از رشته و `Sparkknife_Ledger_Amount` استفاده کنید.

### گزینه‌ها

| کلید | نوع |
| --- | --- |
| `sparkknife_ledger_db_version` | نسخه اسکیما |
| `sparkknife_ledger_version` | نسخه افزونه |
| `sparkknife_ledger_settings` | آرایه ۱۶ کلیدی تنظیمات |
| `sparkknife_ledger_delete_data_on_uninstall` | `yes` / `no` |

همه با `autoload = off`.

---

## توسعه

### ساختار دایرکتوری

```
sparkknife-ledger/
├── sparkknife-ledger.php          ← بوت‌استرپ، ثابت‌ها، هوک فعال‌سازی
├── uninstall.php                  ← سیاست حذف داده
├── includes/
│   ├── class-sparkknife-ledger-plugin.php
│   ├── class-sparkknife-ledger-admin-loader.php
│   ├── helpers.php                ← توابع عمومی sparkknife_ledger_*
│   ├── Core/                      ← Amount, Money, Date, DB, Installer, Settings, …
│   ├── Repositories/               ← دسترسی خام به جداول
│   ├── Services/                   ← منطق کسب‌وکار
│   └── Admin/
│       ├── Controllers/
│       └── Views/
├── assets/{css,js}
├── languages/
└── readme.txt
```

| سند | محتوا |
| --- | --- |
| [`docs/INSTALLATION.md`](docs/INSTALLATION.md) | نصب، تنظیمات، استفاده روزمره، رفع اشکال |
| [`docs/STAGING.md`](docs/STAGING.md) | نصب آزمایشی و پشتیبان‌گیری (پیش از نصب روی سایت واقعی) |
| [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md) | لایه‌ها، جریان داده، تصمیم‌های طراحی |
| [`docs/BUYER-GUIDE.md`](docs/BUYER-GUIDE.md) | مسیر خریدار: نصب، فعال‌سازی لایسنس، انتقال، پشتیبان |
| [`docs/PRODUCT-PAGE.md`](docs/PRODUCT-PAGE.md) | متن آمادهٔ صفحهٔ فروش و پرسش‌های متداول |
| [`docs/AUTHORSHIP.md`](docs/AUTHORSHIP.md) | نویسنده، مالکیت، لایسنس دوگانه |
| [`docs/RELEASE-SIGNING.md`](docs/RELEASE-SIGNING.md) | امضای دیجیتال بستهٔ انتشار با RSA |
| [`docs/YOUR-STEPS.md`](docs/YOUR-STEPS.md) | کارهایی که پیش از فروش باید خودتان انجام دهید |
| [`docs/AUDIT.md`](docs/AUDIT.md) | گزارش ممیزی پیش از انتشار — ۸۹ باگ مستند |
| [`docs/WHAT-I-DID.md`](docs/WHAT-I-DID.md) | چه کاری در پاس آخر انجام شد و چطور تأییدش کنید |
| [`docs/FROM-ZIP-TO-LIVE.md`](docs/FROM-ZIP-TO-LIVE.md) | راهنمای خط‌به‌خط از زیپ تا نصب زنده (دستوربه‌دستور) |

### قواعد کد

- **Autoloader بدون Composer** — slug فایل از نام کلاس ساخته می‌شود:
  `Sparkknife_Ledger_Person_Service` → `includes/Services/class-sparkknife-ledger-person-service.php`
  یعنی نام کلاس و نام فایل باید همیشه با هم تغییر کنند.
- **هر فایل PHP با `defined( 'ABSPATH' ) || exit;` شروع می‌شود.**
- **Text Domain افزونه `sparkknife-ledger` است** (دو k — مثل برند SparKnife). پیشوند توابع/کلاس‌ها/آپشن‌ها هم `sparkknife_ledger_` است.
- **نام متغیر view args در `render.php` همان `$view_data` است** — تغییرش همه viewها را می‌شکند.

### هوک‌ها

**اکشن‌ها**

```
sparkknife_ledger_loaded
sparkknife_ledger_activated
sparkknife_ledger_deactivated
sparkknife_ledger_person_created
sparkknife_ledger_person_updated
sparkknife_ledger_person_deleted
sparkknife_ledger_transaction_created
sparkknife_ledger_transaction_updated
sparkknife_ledger_transaction_voided
sparkknife_ledger_settings_saved
sparkknife_ledger_audit_logged
sparkknife_ledger_data_purged
sparkknife_ledger_uninstalled
sparkknife_ledger_admin_action_{action}
sparkknife_ledger_migrate
```

**فیلترها**

```
sparkknife_ledger_default_settings
sparkknife_ledger_format_amount
sparkknife_ledger_max_amount
sparkknife_ledger_generate_reference
sparkknife_ledger_preset_ranges
sparkknife_ledger_export_formats
sparkknife_ledger_render_print
sparkknife_ledger_user_can
sparkknife_ledger_allow_void_restore
sparkknife_ledger_uninstall_delete_data
sparkknife_ledger_view_paths
```

### توابع عمومی

```php
sparkknife_ledger()                  // دسترسی به نمونه Plugin
sparkknife_ledger_get_option( $key, $default )
sparkknife_ledger_settings()         // آرایه کامل تنظیمات
sparkknife_ledger_format_amount( $scaled )
sparkknife_ledger_format_date( $datetime )
sparkknife_ledger_get_person( $id )
sparkknife_ledger_get_balance( $person_id )
sparkknife_ledger_currency_symbol()
sparkknife_ledger_can( $capability )
sparkknife_ledger_admin_url( $page = '' )
```

### نمونه: افزودن فرمت خروجی

```php
add_filter( 'sparkknife_ledger_export_formats', function ( $formats ) {
	$formats['xlsx'] = array(
		'label'    => __( 'Excel (xlsx)', 'my-plugin' ),
		'callback' => 'my_plugin_render_xlsx',
	);
	return $formats;
} );
```

---

## تست‌ها

مجموعه تست CLI است و به PHPUnit نیاز ندارد:

```bash
php tests/run-tests.php /path/to/wp-load.php
```

### ابزارهای بررسی

پنج بررسی که بدون نیاز به وردپرس و PHP اجرا می‌شوند:

```bash
./tools/check-all.sh
```

یا هر کدام جدا:

```bash
node tools/lint.mjs                # تجزیهٔ نحوی همهٔ فایل‌های PHP
node tools/static-audit.mjs        # انسجام کلاس‌ها و ارجاع‌های شکسته
node tools/static-audit-rules.mjs  # قواعد امنیتی (nonce، escape، ABSPATH)
node tools/php-compat.mjs          # سازگاری با PHP 7.4
node tools/check-authorship.mjs    # یکپارچگی امضای نویسنده (۱۲ بررسی)
```

```text
lint                   77 فایل | 0 خطا
انسجام کلاس‌ها         هیچ ارجاع شکسته‌ای پیدا نشد ✓
قواعد امنیتی           هیچ نقض قاعده‌ای پیدا نشد ✓
سازگاری PHP            سازگار با PHP 7.4 ✓
امضای نویسنده          همهٔ بررسی‌های نویسندگی موفق ✓
```

و دو ابزار برای انتشار:

```bash
./tools/sign-release.sh sparkknife-ledger.zip    # چکسام + امضای RSA
./tools/verify-release.sh sparkknife-ledger.zip  # بررسی امضا
```

`lint.mjs` به `php-parser` نیاز دارد (`npm install php-parser`).
`check-authorship.mjs` هیچ وابستگی ندارد. راهنمای کامل امضا:
[`docs/RELEASE-SIGNING.md`](docs/RELEASE-SIGNING.md).

### آزمون واقعی بدون نصب PHP

اگر PHP روی ماشین ندارید، PHP-WASM کافی است:

```bash
# ⚠️ این دو گروه را در یک نصب با هم ندهید — npm یکی را حذف می‌کند.
npm install @php-wasm/node @php-wasm/universal   # برای آزمون زمان اجرا
# یا
npm install php-parser                            # برای lint و static-audit

# ساخت سایت وردپرس آزمایشی (SQLite) — یک دستور
node tools/setup-test-site.mjs /tmp/wpsite

# اجرای هر اسکریپت PHP روی آن
node tools/php-wasm.mjs /tmp/myscript.php
```

`setup-test-site.mjs` وردپرس را از مخزن رسمی می‌گیرد، درایور SQLite را
کنارش می‌گذارد، و افزونه را نصب و فعال می‌کند. کاربر: `admin` /
`password123`.

> نکتهٔ فنی که وقت زیادی گرفت: `loadNodeRuntime()` یک **شمارهٔ runtime**
> برمی‌گرداند، نه شیء PHP. باید با `new PHP(id)` از
> `@php-wasm/universal` بسته‌بندی شود.

پوشش: **۹۹۰ assertion در ۱۰ سناریو** — ۳۳۳ در سناریوهای ۰۱–۰۹ و
۶۵۵ در سناریوی رگرسیون.

| # | سناریو |
| --- | --- |
| ۱ | نصب و ساختار دیتابیس |
| ۲ | پول، مبلغ و تاریخ |
| ۳ | مدیریت اشخاص |
| ۴ | تراکنش‌ها |
| ۵ | ابطال تراکنش و تاریخچه تغییرات |
| ۶ | گزارش‌ها و خروجی |
| ۷ | امنیت (nonce، capability، XSS) |
| ۸ | رندر رابط کاربری |
| ۹ | حذف افزونه و نگه‌داری داده‌ها |
| ۱۰ | رگرسیون باگ‌های ممیزی |

`09-uninstall.php` باید آخرین سناریو بماند، چون در پایان جداول را از نو می‌سازد تا سایت تست قابل استفاده مجدد باشد.

---

## بسته‌بندی

```bash
cd /path/to/repo
zip -r sparkknife-ledger.zip sparkknife-ledger \
  -x '*.DS_Store' -x '__MACOSX/*' -x '*/node_modules/*'
```

فایل ZIP باید فقط پوشه `sparkknife-ledger/` را در ریشه داشته باشد — نه `docs/`، نه `tests/`.

---

## مجوز

**لایسنس دوگانه.** هر کدام را که برای کاربری‌تان مناسب‌تر است انتخاب کنید:

| | لایسنس | برای چه کسی |
| --- | --- | --- |
| (الف) | **GPL-2.0-or-later** | استفادهٔ آزاد و متن‌باز؛ با تعهد بازنشر |
| (ب) | **لایسنس تجاری SparKnife** | استفادهٔ تجاری بدون تعهد بازنشر GPL |

متن کامل هر دو در [`sparkknife-ledger/LICENSE`](sparkknife-ledger/LICENSE) است.
اگر نمی‌خواهید کد شما بازنشر شود، لایسنس تجاری را انتخاب کنید — GPL اجازه
می‌دهد هر دریافت‌کنندهٔ نسخهٔ GPL آن را با همان شرایط بازتوزیع کند.

---

## نویسنده

**SparKnife** — طراحی، پیاده‌سازی و نگهداری.

- وب: <https://github.com/sparkknife/SparKnife-ledger>
- ایمیل: <155084046+sparkknife@users.noreply.github.com>

کد این افزونه توسط SparKnife نوشته و بازبینی شده است. هر تصمیم طراحی در
[`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md) توضیح دارد و هر باگ رفع‌شده در
[`docs/AUDIT.md`](docs/AUDIT.md) با ریشه و آزمون رگرسیون ثبت شده است.

© ۱۴۰۵ / 2026 SparKnife. همهٔ حقوق محفوظ است.

---

## تاریخچهٔ نویسندگی

همهٔ کامیت‌های این شاخه به نام **sparkknife** ثبت شده‌اند. تاریخچه یک بار
بازنویسی شد تا نام ابزار از نویسنده‌ها پاک شود؛ محتوای هیچ کامیتی عوض نشد
(درخت هر کامیت بایت‌به‌بایت همان است، فقط فیلد نویسنده/کامیت‌کننده).
