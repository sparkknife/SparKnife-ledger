<?php
/**
 * کنترلر مدیریت اشخاص.
 *
 * @author  SparKnife <155084046+sparkknife@users.noreply.github.com>
 *
 * @package SparKnife_Ledger
 */

defined( 'ABSPATH' ) || exit;

/**
 * فهرست، فرم و جزئیات اشخاص.
 */
class Sparkknife_Ledger_Persons_Controller {

	/**
	 * رندر صفحه.
	 *
	 * @return void
	 */
	public static function render() {
		Sparkknife_Ledger_Capabilities::require_capability( 'manage_persons' );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- خواندن مسیر نمایشی.
		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : 'list';

		switch ( $action ) {
			case 'new':
				self::render_form( 0 );
				break;

			case 'edit':
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended
				self::render_form( isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0 );
				break;

			case 'view':
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended
				self::render_detail( isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0 );
				break;

			default:
				self::render_list();
				break;
		}
	}

	/**
	 * فهرست اشخاص.
	 *
	 * @return void
	 */
	private static function render_list() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- فقط فیلتر نمایش.
		$view = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : 'all';

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$args = array(
			'search'     => isset( $_GET['search'] ) ? sanitize_text_field( wp_unslash( $_GET['search'] ) ) : '',
			'status'     => isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '',
			'page'       => isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1,
			'per_page'   => (int) Sparkknife_Ledger_Settings::get( 'per_page', 20 ),
			'orderby'    => isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : 'created_at',
			'order'      => isset( $_GET['order'] ) ? sanitize_key( wp_unslash( $_GET['order'] ) ) : 'desc',
			'only_deleted' => 'deleted' === $view,
		);

		$result   = Sparkknife_Ledger_Person_Repository::instance()->query( $args );
		$ledger   = Sparkknife_Ledger_Ledger_Service::instance();
		$balances = $ledger->get_balances_bulk( array_map( 'intval', wp_list_pluck( $result['items'], 'id' ) ) );

		$stats = array(
			'total'    => Sparkknife_Ledger_Person_Repository::instance()->count_by_status( 'all' ),
			'active'   => Sparkknife_Ledger_Person_Repository::instance()->count_by_status( 'active' ),
			'inactive' => Sparkknife_Ledger_Person_Repository::instance()->count_by_status( 'inactive' ),
			'deleted'  => Sparkknife_Ledger_Person_Repository::instance()->count_by_status( 'deleted' ),
		);

		$base_url = Sparkknife_Ledger_Renderer::url(
			'persons',
			array(
				'view'   => 'all' !== $view ? $view : '',
				'search' => $args['search'],
				'status' => $args['status'],
				'orderby' => $args['orderby'],
				'order'  => $args['order'],
			)
		);

		Sparkknife_Ledger_Renderer::view(
			'persons/list',
			array(
				'result'   => $result,
				'balances' => $balances,
				'args'     => $args,
				'view'     => $view,
				'stats'    => $stats,
				'base_url' => $base_url,
				'current'  => 'persons',
			)
		);
	}

	/**
	 * فرم ایجاد/ویرایش.
	 *
	 * @param int $person_id شناسه (۰ برای ایجاد).
	 * @return void
	 */
	private static function render_form( $person_id ) {
		$person = null;

		if ( $person_id > 0 ) {
			$person = Sparkknife_Ledger_Person_Repository::instance()->find( $person_id );

			if ( ! $person ) {
				Sparkknife_Ledger_Renderer::view(
					'partials/error-page',
					array(
						'title'   => __( 'شخص یافت نشد', 'sparkknife-ledger' ),
						'message' => __( 'شخص موردنظر وجود ندارد یا حذف شده است.', 'sparkknife-ledger' ),
						'current' => 'persons',
					)
				);
				return;
			}
		}

		$summary = $person ? Sparkknife_Ledger_Ledger_Service::instance()->get_person_summary( $person_id ) : null;

		Sparkknife_Ledger_Renderer::view(
			'persons/form',
			array(
				'person'  => $person,
				'summary' => $summary,
				'current' => 'persons',
				'history' => $person ? Sparkknife_Ledger_Audit_Service::instance()->get_history( 'person', $person_id, 8 ) : array(),
			)
		);
	}

	/**
	 * صفحه اختصاصی شخص (اطلاعات مالی کامل).
	 *
	 * @param int $person_id شناسه.
	 * @return void
	 */
	private static function render_detail( $person_id ) {
		$person = Sparkknife_Ledger_Person_Repository::instance()->find( $person_id, true );

		if ( ! $person ) {
			Sparkknife_Ledger_Renderer::view(
				'partials/error-page',
				array(
					'title'   => __( 'شخص یافت نشد', 'sparkknife-ledger' ),
					'message' => __( 'شخص موردنظر وجود ندارد یا حذف شده است.', 'sparkknife-ledger' ),
					'current' => 'persons',
				)
			);
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- فیلتر نمایش.
		$args = array(
			'range'   => isset( $_GET['range'] ) ? sanitize_key( wp_unslash( $_GET['range'] ) ) : 'all',
			'date_from' => isset( $_GET['date_from'] ) ? wp_unslash( $_GET['date_from'] ) : '',
			'date_to'   => isset( $_GET['date_to'] ) ? wp_unslash( $_GET['date_to'] ) : '',
			'type'    => isset( $_GET['type'] ) ? sanitize_key( wp_unslash( $_GET['type'] ) ) : '',
			'status'  => isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '',
			'search'  => isset( $_GET['search'] ) ? sanitize_text_field( wp_unslash( $_GET['search'] ) ) : '',
			'page'    => isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1,
			'per_page' => (int) Sparkknife_Ledger_Settings::get( 'per_page', 20 ),
		);

		$report = Sparkknife_Ledger_Report_Service::instance()->get_person_report( $person_id, $args );

		if ( is_wp_error( $report ) ) {
			Sparkknife_Ledger_Renderer::view(
				'partials/error-page',
				array(
					'title'   => __( 'خطا', 'sparkknife-ledger' ),
					'message' => $report->get_error_message(),
					'current' => 'persons',
				)
			);
			return;
		}

		$history = Sparkknife_Ledger_Audit_Service::instance()->get_history( 'person', $person_id, 10 );

		Sparkknife_Ledger_Renderer::view(
			'persons/detail',
			array(
				'person'   => $person,
				'report'   => $report,
				'args'     => $args,
				'history'  => $history,
				'current'  => 'persons',
				'base_url' => Sparkknife_Ledger_Renderer::url( 'persons', array( 'action' => 'view', 'id' => $person_id ) ),
			)
		);
	}
}
