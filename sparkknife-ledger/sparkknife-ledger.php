<?php
/**
 * Plugin Name:       SparKnife Ledger
 * Plugin URI:        https://github.com/sparkknife/SparKnife-ledger
 * Description:       سیستم حرفه‌ای مدیریت حساب اشخاص، ثبت تراکنش‌های ورودی و خروجی، محاسبه خودکار مانده و گزارش‌گیری فردی و تجمیعی.
 * Version:           1.1.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            SparKnife
 * Author URI:        https://github.com/sparkknife
 * License:           GPL-2.0-or-later OR commercial — see LICENSE
 * License URI:       https://github.com/sparkknife/SparKnife-ledger/blob/main/sparkknife-ledger/LICENSE
 * Text Domain:       sparkknife-ledger
 * Domain Path:       /languages
 *
 * SparKnife Ledger — دفتر حساب اشخاص برای وردپرس.
 *
 * SparKnife Ledger محصولی از SparKnife است: طراحی، مالکیت و
 * مسئولیت نگهداری آن با SparKnife است. هر تصمیم طراحی در
 * docs/ARCHITECTURE.md توضیح دارد و هر باگ رفع‌شده در docs/AUDIT.md
 * با ریشه و آزمون رگرسیون ثبت شده است.
 *
 * کد عمداً ساده و خواناست: بدون Composer، بدون فریم‌ورک
 * جاوااسکریپت، بدون وابستگی بیرونی. تنها چیزی که لازم است خود
 * وردپرس و PHP است. هیچ کتابخانهٔ شخص ثالثی در بسته نیست.
 *
 * @author     SparKnife <155084046+sparkknife@users.noreply.github.com>
 * @copyright  2026 SparKnife. All rights reserved.
 * @license    GPL-2.0-or-later OR commercial — see LICENSE
 * @link       https://github.com/sparkknife/SparKnife-ledger
 *
 * @package SparKnife_Ledger
 */

defined( 'ABSPATH' ) || exit;

/*
|--------------------------------------------------------------------------
| ثابت‌های افزونه
|--------------------------------------------------------------------------
*/

define( 'SPARKKNIFE_LEDGER_VERSION', '1.1.0' );
define( 'SPARKKNIFE_LEDGER_FILE', __FILE__ );
define( 'SPARKKNIFE_LEDGER_DIR', plugin_dir_path( __FILE__ ) );
define( 'SPARKKNIFE_LEDGER_URL', plugin_dir_url( __FILE__ ) );
define( 'SPARKKNIFE_LEDGER_BASENAME', plugin_basename( __FILE__ ) );

/** نسخه اسکیما؛ فقط هنگام تغییر ساختار جداول افزایش می‌یابد. */
define( 'SPARKKNIFE_LEDGER_DB_VERSION', '1.1.0' );

/** پیشوند جداول اختصاصی (بدون $wpdb->prefix که در زمان اجرا اضافه می‌شود). */
define( 'SPARKKNIFE_LEDGER_TABLE_PREFIX', 'sparkknife_ledger_' );

/** مقیاس ذخیره مبلغ: مبلغ × 10^4 ذخیره می‌شود تا محاسبات اعشاری دقیق باقی بمانند. */
define( 'SPARKKNIFE_LEDGER_AMOUNT_SCALE', 4 );

/*
|--------------------------------------------------------------------------
| بارگذاری
|--------------------------------------------------------------------------
*/

require_once SPARKKNIFE_LEDGER_DIR . 'includes/Core/class-sparkknife-ledger-autoloader.php';
Sparkknife_Ledger_Autoloader::register();

require_once SPARKKNIFE_LEDGER_DIR . 'includes/helpers.php';

// لایه رندر Viewها (بارگذاری قطعی؛ در غیر این صورت صفحات پنل خطای مرگبار می‌دهند).
require_once SPARKKNIFE_LEDGER_DIR . 'includes/Admin/Views/partials/render.php';

/*
|--------------------------------------------------------------------------
| هوک‌های چرخه حیات
|--------------------------------------------------------------------------
*/

register_activation_hook( __FILE__, array( 'Sparkknife_Ledger_Installer', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Sparkknife_Ledger_Installer', 'deactivate' ) );

/*
 * فعال‌سازی شبکه‌ای: register_activation_hook در حالت شبکه فقط سایت جاری را
 * آماده می‌کند. این هوک پیش از آن اجرا می‌شود و تنها وقتی $network_wide
 * روشن است کار می‌کند تا در تک‌سایتی دوبار نصب انجام نشود.
 */
add_action( 'activate_' . SPARKKNIFE_LEDGER_BASENAME, array( 'Sparkknife_Ledger_Installer', 'activate_network' ) );

// اجرای کم‌هزینه بررسی به‌روزرسانی اسکیما و مهاجرت تنظیمات در هر بار بارگذاری.
add_action( 'plugins_loaded', array( 'Sparkknife_Ledger_Installer', 'maybe_upgrade' ), 5 );

/*
|--------------------------------------------------------------------------
| راه‌اندازی افزونه
|--------------------------------------------------------------------------
*/

/**
 * دسترسی سراسری به نمونه افزونه.
 *
 * @return Sparkknife_Ledger_Plugin
 */
function sparkknife_ledger() {
	return Sparkknife_Ledger_Plugin::instance();
}

sparkknife_ledger();
