# معماری افزونه SparKnife Ledger

> سند معماری پیاده‌سازی‌شده — هرچه در این سند آمده در نسخه ۱.۰.۰ موجود است.

## ۱. اهداف معماری

| هدف | راهکار |
|---|---|
| جداسازی مسئولیت‌ها | لایه‌بندی Bootstrap → Repository → Service → Admin/View |
| جلوگیری از تداخل | پیشوند اختصاصی `sparkknife_ledger_` برای توابع، ثابت‌ها و آپشن‌ها؛ پیشوند کلاس `Sparkknife_Ledger_` |
| صحت مالی | مبلغ به‌صورت عدد صحیح مقیاس‌شده (بدون float)، محاسبه مانده از منبع واحد، ابطال به‌جای حذف |
| امنیت | Capability + Nonce + prepare + Sanitize + Escape + Audit |
| قابلیت توسعه | Autoloader، Serviceهای مستقل، Hookهای توسعه، لایه خروجی قابل تعویض |

## ۲. لایه‌ها

```
sparkknife-ledger.php          ← Bootstrap: هدر افزونه، ثابت‌ها، Autoloader، هوک‌های نصب/فعال‌سازی
└── includes/
    ├── Core                  ← زیرساخت مشترک (نصب، دیتابیس، پول، تاریخ، دسترسی، تنظیمات، اعتبارسنجی)
    ├── Repositories          ← تنها لایه‌ای که SQL می‌نویسد (کلیه کوئری‌ها با $wpdb->prepare)
    ├── Services              ← منطق دامنه: اعتبارسنجی کسب‌وکار، تراکنش اتمیک، ابطال، گزارش، خروجی
    ├── Admin                 ← کنترلرهای پنل مدیریت + Viewها (فقط نمایش و Escape)
    └── helpers.php           ← توابع عمومی با پیشوند sparkknife_ledger_ برای توسعه‌دهندگان
```

### قواعد جریان داده

1. **درخواست** → `Sparkknife_Ledger_Admin` مسیر را تشخیص می‌دهد → بررسی Capability → بررسی Nonce
2. **ورودی خام** → `Sparkknife_Ledger_Sanitizer` (پاک‌سازی) → `Sparkknife_Ledger_Validator` (اعتبارسنجی)
3. **Service** → اعمال قواعد دامنه → در صورت نیاز `DB::transaction()` → Repository
4. **Repository** → SQL آماده‌سازی‌شده → `$wpdb`
5. **View** → فقط داده‌های آماده + Escape کامل خروجی

Viewها هیچ‌گاه SQL نمی‌نویسند و کلاس‌های Model دسترسی مستقیم به دیتابیس ندارند.

## ۳. اجزای اصلی (Core)

| کلاس | مسئولیت |
|---|---|
| `Sparkknife_Ledger_Autoloader` | نگاشت نام کلاس به مسیر فایل (بدون نیاز به Composer) |
| `Sparkknife_Ledger_Plugin` | Singleton نگه‌دارنده سرویس‌ها و راه‌انداز هوک‌ها |
| `Sparkknife_Ledger_Installer` | ساخت/به‌روزرسانی جداول با `dbDelta`، نسخه‌بندی اسکیما، مقادیر پیش‌فرض |
| `Sparkknife_Ledger_DB` | نام جداول، اجرای تراکنش اتمیک، بررسی سلامت اسکیما |
| `Sparkknife_Ledger_Money` | تنظیمات واحد پول، تبدیل ورودی به عدد صحیح مقیاس‌شده، قالب‌بندی نمایش |
| `Sparkknife_Ledger_Date` | تبدیل شمسی/میلادی، اعتبارسنجی، قالب‌بندی |
| `Sparkknife_Ledger_Settings` | خواندن/نوشتن تنظیمات با پیش‌فرض‌های امن و ثبت Audit |
| `Sparkknife_Ledger_Capabilities` | تعریف و تخصیص Capabilityها، ماتریس نقش‌ها |
| `Sparkknife_Ledger_Sanitizer` | پاک‌سازی متمرکز ورودی‌ها (متن، عدد فارسی، تاریخ، شناسه) |
| `Sparkknife_Ledger_Validator` | اعتبارسنجی فیلدها و بازگرداندن خطاهای قابل نمایش |

## ۴. دامنه (Services)

| سرویس | مسئولیت |
|---|---|
| `Sparkknife_Ledger_Person_Service` | ایجاد/ویرایش/غیرفعال‌سازی/حذف نرم شخص، قواعد یکتایی و وابستگی |
| `Sparkknife_Ledger_Transaction_Service` | ثبت/ویرایش/ابطال تراکنش، تولید شناسه، ثبت Audit، اتمیک بودن |
| `Sparkknife_Ledger_Ledger_Service` | منبع واحد محاسبات: جمع ورودی/خروجی/مانده/وضعیت حساب |
| `Sparkknife_Ledger_Report_Service` | گزارش فردی و تجمیعی، فیلتر بازه/شخص/نوع، مرتب‌سازی و صفحه‌بندی |
| `Sparkknife_Ledger_Audit_Service` | نوشتن و خواندن تاریخچه تغییرات |
| `Sparkknife_Ledger_Export_Service` | خروجی CSV (سازگار با Excel)، نمایش چاپ/PDF، تولید محتوا برای افزونه‌های PDF |

## ۵. سطوح دسترسی (Capabilities)

| Capability | توضیح | پیش‌فرض |
|---|---|---|
| `sparkknife_ledger_manage_persons` | مدیریت اشخاص | administrator |
| `sparkknife_ledger_manage_transactions` | ثبت/ویرایش تراکنش | administrator |
| `sparkknife_ledger_void_transactions` | ابطال تراکنش | administrator |
| `sparkknife_ledger_reports` | مشاهده داشبورد و گزارش‌ها | administrator |
| `sparkknife_ledger_export_reports` | خروجی گزارش | administrator |
| `sparkknife_ledger_view_audit` | مشاهده تاریخچه تغییرات | administrator |
| `sparkknife_ledger_manage_settings` | تنظیمات افزونه | administrator (فقط `manage_options`) |

تخصیص به سایر نقش‌ها از صفحه «تنظیمات → دسترسی‌ها» به‌صورت اختیاری انجام می‌شود.
ابزارهای حساس (ابطال، خروجی، تنظیمات) Capability مستقل دارند تا تفکیک وظایف ممکن باشد.

## ۶. امنیت (چک‌لیست پیاده‌سازی)

- تمام فرم‌های تغییردهنده: `POST` + `wp_nonce_field` + `check_admin_referer`
- تمام لینک‌های تغییردهنده (ابطال/حذف): Nonce اختصاصی در URL
- بررسی Capability در ابتدای هر Handler و هر صفحه
- SQL فقط با `$wpdb->prepare()` و Whitelist برای `ORDER BY`/جهت مرتب‌سازی
- پاک‌سازی: `sanitize_text_field`، `sanitize_textarea_field`، `absint`، `sanitize_key`
- مبلغ: تبدیل به عدد صحیح مقیاس‌شده، رد مقادیر منفی/صفر/بزرگ‌تر از سقف
- خروجی: `esc_html`، `esc_attr`، `esc_url`، `wp_kses_post`؛ تاریخچه با `esc_html` و `wpautop` کنترل‌شده
- خروجی CSV: حذف کاراکترهای فرمول (`= + - @`) برای جلوگیری از CSV Injection
- جلوگیری از دسترسی مستقیم فایل: `defined( 'ABSPATH' ) || exit;` + `index.php` در همه پوشه‌ها
- بدون ثبت اطلاعات حساس در لاگ؛ ثبت رویدادهای حساس در Audit Log (کاربر، IP، User-Agent)

## ۷. تجربه کاربری

- تمام رابط فارسی و `dir="rtl"` با CSS اختصاصی (بدون وابستگی به فریم‌ورک خارجی)
- اعداد فارسی قابل تنظیم، جداکننده هزارگان، تاریخ شمسی قابل تنظیم
- دکمه‌های عملیات حساس با تأیید دو مرحله‌ای (فرم ابطال با دلیل الزامی)
- جدول‌ها: جستجو، فیلتر، مرتب‌سازی، صفحه‌بندی، حالت خالی راهنما
- واکنش‌گرا: کارت‌ها و جدول‌ها در موبایل به‌صورت کارت/اسکرول افقی
- دسترس‌پذیری: برچسب `label`، حالت فوکوس واضح، `aria-live` برای پیام‌ها

## ۸. تصمیم‌های فنی و دلیل آن‌ها

| تصمیم | دلیل |
|---|---|
| مبلغ به‌صورت `BIGINT` در واحد مقیاس‌شده (×۱۰۰۰۰) | حذف کامل خطای float؛ جمع دقیق و ایندکس‌پذیر؛ مستقل از تغییر بعدی اعشار واحد پول |
| محاسبه مانده به‌صورت پویا (بدون جدول مانده) | عدم امکان ناهمگامی داده؛ ابطال، ویرایش و حذف نرم به‌صورت خودکار منعکس می‌شود |
| ابطال یک‌طرفه (بدون بازگردانی) | حفظ یکپارچگی سوابق مالی؛ ثبت تراکنش اصلاحی به‌جای تغییر تاریخچه |
| ذخیره تاریخ میلادی در DB و نمایش شمسی | مرتب‌سازی و مقایسه درست در SQL؛ نمایش مطابق انتظار کاربر |
| تنظیمات در `wp_options` | استاندارد وردپرس، Autoload، سازگاری با بکاپ‌گیری؛ تغییرات در Audit Log ثبت می‌شود |
| Viewهای PHP ساده (بدون React) | بدون وابستگی Build، سازگاری با هر هاست، بارگذاری سریع در پنل |
| PDF از طریق نمای چاپ مرورگر | تولید PDF فارسی/RTL بدون فونت و shaping خارجی؛ هوک `sparkknife_ledger_render_print` برای جایگزینی کامل خروجی چاپ |
| نقشه راه خروجی Excel | CSV با BOM (سازگار با Excel) + قابلیت افزودن XLSX از طریق هوک |

## ۹. نقشه راه افزودن قابلیت‌های آینده

- **XLSX واقعی**: پیاده‌سازی `Sparkknife_Ledger_Export_Xlsx` و اتصال به هوک `sparkknife_ledger_export_formats`
- **PDF بومی**: اتصال mPDF/TCPDF با فونت فارسی از طریق هوک `sparkknife_ledger_render_print`
- **API**: افزودن `includes/Api/class-sparkknife-ledger-rest-controller.php` با زیرساخت Capability موجود
- **چند کاربری پیشرفته**: جدول مالکیت شخص برای تفکیک داده بین کاربران
- **مانده افتتاحیه**: افزودن ستون `opening_balance` با محاسبه در `Ledger_Service` (بدون تغییر جداول تراکنش)
