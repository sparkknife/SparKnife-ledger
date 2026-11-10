<?php
/**
 * کنترلر سلامت سیستم و امنیت.
 *
 * @author  SparKnife <155084046+sparkknife@users.noreply.github.com>
 *
 * @package SparKnife_Ledger
 */

defined( 'ABSPATH' ) || exit;

/**
 * صفحهٔ سلامت سیستم، امنیت و لایسنس.
 */
class Sparkknife_Ledger_Health_Controller {

	/**
	 * رندر صفحه.
	 *
	 * @return void
	 */
	public static function render() {
		/*
		 * همان مجوزی که در ثبت منو استفاده شده: `manage_options`.
		 *
		 * پیش‌تر این‌جا `manage_settings` بود. WordPress مجوز زیرمنو را
		 * از همان پارامتری می‌خواند که در `add_submenu_page` داده شده،
		 * ولی نکتهٔ خطرناک این است که دو منبع حقیقت داشتیم: منو
		 * `manage_options` و رندر `manage_settings`. هر حسابداری که
		 * مجوز «تنظیمات» افزونه را داشت می‌توانست نشانی صفحه را دستی
		 * بزند و نسخهٔ PHP، وضعیت HTTPS، مسیر پشتیبان و جزئیات
		 * لایسنس را ببیند — اطلاعاتی که به کارش نمی‌آید و برای نقشه‌برداری
		 * از سایت به کار مهاجم می‌آید.
		 *
		 * این صفحه اطلاعات سطح سایت را نشان می‌دهد، پس فقط مدیر کل.
		 */
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(
				esc_html__( 'شما اجازه دسترسی به این بخش را ندارید.', 'sparkknife-ledger' ),
				esc_html__( 'دسترسی غیرمجاز', 'sparkknife-ledger' ),
				array( 'response' => 403 )
			);
		}

		Sparkknife_Ledger_Renderer::view(
			'health/index',
			array(
				'checks'   => Sparkknife_Ledger_Security::health_checks(),
				'log'      => Sparkknife_Ledger_Security::get_log( 25 ),
				'license'  => Sparkknife_Ledger_License::state( true ),
				'license_key' => Sparkknife_Ledger_License::key(),
				'usage'    => Sparkknife_Ledger_Gate::usage(),
				'current'  => 'health',
			)
		);
	}
}
