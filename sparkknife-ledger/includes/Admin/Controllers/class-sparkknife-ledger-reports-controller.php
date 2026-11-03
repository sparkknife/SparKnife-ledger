<?php
/**
 * کنترلر گزارش‌ها (فردی و تجمیعی).
 *
 * @package SparKnife_Ledger
 */

defined( 'ABSPATH' ) || exit;

/**
 * گزارش‌گیری.
 */
class Sparkknife_Ledger_Reports_Controller {

	/**
	 * گزارش فردی.
	 *
	 * @return void
	 */
	public static function render_person() {
		Sparkknife_Ledger_Capabilities::require_capability( 'reports' );

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- فیلتر نمایش.
		$person_id = isset( $_GET['person_id'] ) ? absint( $_GET['person_id'] ) : 0;
		$args      = array(
			'range'     => isset( $_GET['range'] ) ? sanitize_key( wp_unslash( $_GET['range'] ) ) : 'all',
			'date_from' => isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : '',
			'date_to'   => isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : '',
			'type'      => isset( $_GET['type'] ) ? sanitize_key( wp_unslash( $_GET['type'] ) ) : '',
			'status'    => isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '',
			'search'    => isset( $_GET['search'] ) ? sanitize_text_field( wp_unslash( $_GET['search'] ) ) : '',
			'page'      => isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1,
			'per_page'  => (int) Sparkknife_Ledger_Settings::get( 'per_page', 20 ),
			'orderby'   => isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : 'txn_date',
			'order'     => isset( $_GET['order'] ) ? sanitize_key( wp_unslash( $_GET['order'] ) ) : 'desc',
		);
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$persons = Sparkknife_Ledger_Person_Repository::instance()->get_all();

		$report = null;

		if ( ! $person_id && ! empty( $persons ) ) {
			// پیش‌فرض: اولین شخص فهرست.
			$person_id = (int) $persons[0]->id;
		}

		if ( $person_id ) {
			$report = Sparkknife_Ledger_Report_Service::instance()->get_person_report( $person_id, $args );
		}

		$base_url = Sparkknife_Ledger_Renderer::url(
			'reports',
			array(
				'person_id' => $person_id ? $person_id : '',
				'range'     => $args['range'],
				'date_from' => $args['date_from'],
				'date_to'   => $args['date_to'],
				'type'      => $args['type'],
				'status'    => $args['status'],
				'search'    => $args['search'],
			)
		);

		Sparkknife_Ledger_Renderer::view(
			'reports/person',
			array(
				'persons'  => $persons,
				'person_id' => $person_id,
				'report'   => $report,
				'args'     => $args,
				'base_url' => $base_url,
				'current'  => 'reports',
			)
		);
	}

	/**
	 * گزارش تجمیعی.
	 *
	 * @return void
	 */
	public static function render_summary() {
		Sparkknife_Ledger_Capabilities::require_capability( 'reports' );

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- فیلتر نمایش.
		$args = array(
			'range'     => isset( $_GET['range'] ) ? sanitize_key( wp_unslash( $_GET['range'] ) ) : 'all',
			'date_from' => isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : '',
			'date_to'   => isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : '',
			'person_id' => isset( $_GET['person_id'] ) ? absint( $_GET['person_id'] ) : 0,
			'type'      => isset( $_GET['type'] ) ? sanitize_key( wp_unslash( $_GET['type'] ) ) : '',
			'search'    => isset( $_GET['search'] ) ? sanitize_text_field( wp_unslash( $_GET['search'] ) ) : '',
			'orderby'   => isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : 'balance',
			'order'     => isset( $_GET['order'] ) ? sanitize_key( wp_unslash( $_GET['order'] ) ) : 'desc',
			'page'      => isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1,
			'per_page'  => (int) Sparkknife_Ledger_Settings::get( 'per_page', 20 ),
		);
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$report = Sparkknife_Ledger_Report_Service::instance()->get_summary_report( $args );

		$base_url = Sparkknife_Ledger_Renderer::url(
			'summary',
			array(
				'range'     => $args['range'],
				'date_from' => $args['date_from'],
				'date_to'   => $args['date_to'],
				'person_id' => $args['person_id'] ? $args['person_id'] : '',
				'type'      => $args['type'],
				'search'    => $args['search'],
				'orderby'   => $args['orderby'],
				'order'     => $args['order'],
			)
		);

		Sparkknife_Ledger_Renderer::view(
			'reports/summary',
			array(
				'report'   => $report,
				'args'     => $args,
				'base_url' => $base_url,
				'current'  => 'summary',
			)
		);
	}
}
