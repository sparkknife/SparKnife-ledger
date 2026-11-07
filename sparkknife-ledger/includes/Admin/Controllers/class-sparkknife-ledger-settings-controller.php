<?php
/**
 * کنترلر تنظیمات افزونه.
 *
 * @author  SparKnife <155084046+sparkknife@users.noreply.github.com>
 *
 * @package SparKnife_Ledger
 */

defined( 'ABSPATH' ) || exit;

/**
 * صفحه تنظیمات با تب‌های واحد پول، نمایش، عمومی و دسترسی‌ها.
 */
class Sparkknife_Ledger_Settings_Controller {

	/**
	 * تب‌های مجاز.
	 *
	 * @return array<string, string>
	 */
	private static function tabs() {
		$tabs = array(
			'currency' => __( 'واحد پول', 'sparkknife-ledger' ),
			'display'  => __( 'نمایش و تاریخ', 'sparkknife-ledger' ),
			'general'  => __( 'عمومی', 'sparkknife-ledger' ),
			'data'     => __( 'داده‌ها و حذف', 'sparkknife-ledger' ),
		);

		/*
		 * تب «دسترسی نقش‌ها» فقط برای مدیر کل.
		 *
		 * این تب اجازهٔ دادنِ هر capability به هر نقش را می‌دهد — یعنی
		 * مسیر ارتقای دسترسی است. اگر با `manage_settings` دیده شود،
		 * حسابداری که فقط باید واحد پول را عوض کند می‌تواند نقش خودش
		 * را به مدیر کامل ارتقا دهد. باگ ۸۶.
		 */
		if ( current_user_can( 'manage_options' ) ) {
			$tabs['capabilities'] = __( 'دسترسی نقش‌ها', 'sparkknife-ledger' );
		}

		return $tabs;
	}

	/**
	 * رندر صفحه.
	 *
	 * @return void
	 */
	public static function render() {
		Sparkknife_Ledger_Capabilities::require_capability( 'manage_settings' );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- انتخاب تب.
		$tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'currency';
		$tabs = self::tabs();

		/*
		 * انتخاب تب ناموجود به تب پیش‌فرض برمی‌گردد.
		 *
		 * این خط هم تب نامعتبر را می‌گیرد و هم — مهم‌تر — تلاش برای
		 * باز کردن دستی `?tab=capabilities` را وقتی کاربر
		 * `manage_options` ندارد: آن تب در فهرست نیست، پس شرط
		 * برقرار می‌شود و کاربر به واحد پول می‌رود.
		 */
		if ( ! isset( $tabs[ $tab ] ) ) {
			$tab = 'currency';
		}

		$settings = Sparkknife_Ledger_Settings::all();

		Sparkknife_Ledger_Renderer::view(
			'settings/index',
			array(
				'tab'      => $tab,
				'tabs'     => $tabs,
				'settings' => $settings,
				'health'   => Sparkknife_Ledger_Installer::get_health_report(),
				'matrix'   => Sparkknife_Ledger_Capabilities::get_role_matrix(),
				'roles'    => Sparkknife_Ledger_Capabilities::assignable_roles(),
				'caps'     => Sparkknife_Ledger_Capabilities::all(),
				'current'  => 'settings',
			)
		);
	}
}
