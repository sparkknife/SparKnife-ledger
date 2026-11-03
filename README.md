# SparKnife Ledger

افزونه دفتر حساب اشخاص برای وردپرس — ثبت تراکنش‌های ورودی و خروجی، محاسبه خودکار مانده، گزارش فردی و تجمیعی، تاریخچه تغییرات، تقویم شمسی و رابط راست‌به‌چپ.

[![WordPress](https://img.shields.io/badge/WordPress-5.8%2B-21759b)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-777bb4)](https://www.php.net/)
[![License](https://img.shields.io/badge/License-GPLv2%2B-blue)](https://www.gnu.org/licenses/gpl-2.0.html)
[![Tests](https://img.shields.io/badge/tests-681%20passing-brightgreen)](#تستها)

---

## فهرست

- [نگاه کلی](#نگاه-کلی)
- [نصب](#نصب)
- [شروع سریع](#شروع-سریع)
- [امکانات](#امکانات)
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

پس از فعال‌سازی:

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

### تاریخچه تغییرات

هر عملیات با `object_type`, `object_id`, `action`, `changes` (JSON قبل/بعد) و `hash` ثبت می‌شود. `hash` زنجیره‌ای است (هشِ رکورد قبلی + رکورد جاری) تا حذف یا دستکاری یک ردیف، زنجیره را بشکند.

### تقویم و ارقام

- تبدیل دوطرفه شمسی ↔ میلادی با اعتبارسنجی کامل
- پذیرش ورودی: `۱۲۳`, `۱۲۳٬۴۵۶`, `1,250,000`
- نمایش با ارقام فارسی، جداکننده هزارگان قابل تنظیم

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
| [`docs/AUDIT.md`](docs/AUDIT.md) | گزارش ممیزی پیش از انتشار |

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

پوشش: **۶۸۱ assertion در ۱۰ سناریو**

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

GPL-2.0-or-later. فایل [`LICENSE`](https://www.gnu.org/licenses/gpl-2.0.html) را ببینید.
