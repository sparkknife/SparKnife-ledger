<?php
/**
 * کنترلر داشبورد مدیریت.
 *
 * @author  SparKnife <155084046+sparkknife@users.noreply.github.com>
 *
 * @package SparKnife_Ledger
 */

defined( 'ABSPATH' ) || exit;

/**
 * داشبورد.
 */
class Sparkknife_Ledger_Dashboard_Controller {

	/**
	 * رندر صفحه داشبورد.
	 *
	 * @return void
	 */
	public static function render() {
		Sparkknife_Ledger_Capabilities::require_capability( 'reports' );

		$ledger  = Sparkknife_Ledger_Ledger_Service::instance();
		$reports = Sparkknife_Ledger_Report_Service::instance();

		$stats = $ledger->get_overall_stats();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- فقط خواندن فیلتر، بدون تغییر داده.
		$chart_months = isset( $_GET['months'] ) ? max( 3, min( 12, absint( $_GET['months'] ) ) ) : 6;

		$data = array(
			'stats'         => $stats,
			'latest'        => Sparkknife_Ledger_Transaction_Repository::instance()->latest( 8 ),
			'positive'      => $ledger->get_persons_by_account_status( 'positive', 5 ),
			'negative'      => $ledger->get_persons_by_account_status( 'negative', 5 ),
			'chart'         => $reports->get_monthly_chart( $chart_months ),
			'chart_months'  => $chart_months,
			'top_debtors'   => array_slice( $ledger->get_persons_by_account_status( 'negative' ), 0, 5 ),
			'health'        => Sparkknife_Ledger_Installer::get_health_report(),
			'can_add'       => Sparkknife_Ledger_Capabilities::current_user_can( 'manage_transactions' ),
			'can_persons'   => Sparkknife_Ledger_Capabilities::current_user_can( 'manage_persons' ),
			'current'       => 'dashboard',
		);

		Sparkknife_Ledger_Renderer::view( 'dashboard/index', $data );
	}
}
