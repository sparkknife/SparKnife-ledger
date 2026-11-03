<?php
/**
 * کنترلر تاریخچه تغییرات.
 *
 * @package SparKnife_Ledger
 */

defined( 'ABSPATH' ) || exit;

/**
 * نمایش Audit Log.
 */
class Sparkknife_Ledger_Audit_Controller {

	/**
	 * رندر صفحه تاریخچه.
	 *
	 * @return void
	 */
	public static function render() {
		Sparkknife_Ledger_Capabilities::require_capability( 'view_audit' );

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- فیلتر نمایش.
		$args = array(
			'object_type' => isset( $_GET['object_type'] ) ? sanitize_key( wp_unslash( $_GET['object_type'] ) ) : '',
			'object_id'   => isset( $_GET['object_id'] ) ? absint( $_GET['object_id'] ) : 0,
			'action'      => isset( $_GET['audit_action'] ) ? sanitize_key( wp_unslash( $_GET['audit_action'] ) ) : '',
			'user_id'     => isset( $_GET['user_id'] ) ? absint( $_GET['user_id'] ) : 0,
			'range'       => isset( $_GET['range'] ) ? sanitize_key( wp_unslash( $_GET['range'] ) ) : 'all',
			'date_from'   => isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : '',
			'date_to'     => isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : '',
			'page'        => isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1,
			'per_page'    => 30,
		);
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$range = Sparkknife_Ledger_Ledger_Service::resolve_range(
			array(
				'range'     => $args['range'],
				'date_from' => $args['date_from'],
				'date_to'   => $args['date_to'],
			)
		);

		$query_args = $args;
		$query_args['date_from'] = $range['from'];
		$query_args['date_to']   = $range['to'];

		$result = Sparkknife_Ledger_Audit_Repository::instance()->query( $query_args );

		$base_url = Sparkknife_Ledger_Renderer::url(
			'audit',
			array(
				'object_type'   => $args['object_type'],
				'object_id'     => $args['object_id'] ? $args['object_id'] : '',
				'audit_action'  => $args['action'],
				'user_id'       => $args['user_id'] ? $args['user_id'] : '',
				'range'         => $args['range'],
				'date_from'     => $args['date_from'],
				'date_to'       => $args['date_to'],
			)
		);

		Sparkknife_Ledger_Renderer::view(
			'audit/index',
			array(
				'result'   => $result,
				'args'     => $args,
				'range'    => $range,
				'base_url' => $base_url,
				'current'  => 'audit',
				'users'    => get_users( array( 'fields' => array( 'ID', 'display_name' ) ) ),
			)
		);
	}
}
