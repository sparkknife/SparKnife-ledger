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
		return array(
			'currency'     => __( 'واحد پول', 'sparkknife-ledger' ),
			'display'      => __( 'نمایش و تاریخ', 'sparkknife-ledger' ),
			'general'      => __( 'عمومی', 'sparkknife-ledger' ),
			'capabilities' => __( 'دسترسی نقش‌ها', 'sparkknife-ledger' ),
			'data'         => __( 'داده‌ها و حذف', 'sparkknife-ledger' ),
		);
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
