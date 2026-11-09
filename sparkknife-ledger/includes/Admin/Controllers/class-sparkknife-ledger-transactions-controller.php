<?php
/**
 * کنترلر مدیریت تراکنش‌ها.
 *
 * @author  SparKnife <155084046+sparkknife@users.noreply.github.com>
 *
 * @package SparKnife_Ledger
 */

defined( 'ABSPATH' ) || exit;

/**
 * فهرست، فرم، جزئیات و ابطال تراکنش.
 */
class Sparkknife_Ledger_Transactions_Controller {

	/**
	 * رندر صفحه.
	 *
	 * @return void
	 */
	public static function render() {
		Sparkknife_Ledger_Capabilities::require_capability( 'manage_transactions' );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- مسیر نمایشی.
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
	 * فهرست تراکنش‌ها.
	 *
	 * @return void
	 */
	private static function render_list() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- فیلتر نمایش.
		$filters = array(
			'person_id' => isset( $_GET['person_id'] ) ? absint( $_GET['person_id'] ) : 0,
			'type'      => isset( $_GET['type'] ) ? sanitize_key( wp_unslash( $_GET['type'] ) ) : '',
			'status'    => isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '',
			'search'    => isset( $_GET['search'] ) ? sanitize_text_field( wp_unslash( $_GET['search'] ) ) : '',
			'range'     => isset( $_GET['range'] ) ? sanitize_key( wp_unslash( $_GET['range'] ) ) : 'all',
			'date_from' => isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : '',
			'date_to'   => isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : '',
			'orderby'   => isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : 'txn_date',
			'order'     => isset( $_GET['order'] ) ? sanitize_key( wp_unslash( $_GET['order'] ) ) : 'desc',
			'paged'     => isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1,
		);
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$range = Sparkknife_Ledger_Ledger_Service::resolve_range(
			array(
				'range'     => $filters['range'],
				'date_from' => $filters['date_from'],
				'date_to'   => $filters['date_to'],
			)
		);

		$query = Sparkknife_Ledger_Transaction_Repository::instance()->query(
			array(
				'person_id' => $filters['person_id'],
				'type'      => $filters['type'],
				'status'    => $filters['status'],
				'search'    => $filters['search'],
				'date_from' => $range['from'],
				'date_to'   => $range['to'],
				'page'      => $filters['paged'],
				'per_page'  => (int) Sparkknife_Ledger_Settings::get( 'per_page', 20 ),
				'orderby'   => $filters['orderby'],
				'order'     => $filters['order'],
			)
		);

		/*
		 * جمع پایین جدول باید با همان ردیف‌هایی بخواند که جدول نشان
		 * می‌دهد. اگر کاربر وضعیتی انتخاب نکرده، `query()` ردیف‌های
		 * باطل‌شده را هم می‌آورد؛ پیش‌تر اینجا `'active_only'` گذاشته
		 * می‌شد و جمع فقط معتبرها را می‌گرفت. نتیجه: جدول ۵ ردیف داشت
		 * ولی جمع می‌گفت ۴ — و کاربر نمی‌دانست کدام درست است.
		 */
		$totals = Sparkknife_Ledger_Transaction_Repository::instance()->sum_by_type(
			array(
				'person_id' => $filters['person_id'],
				'type'      => $filters['type'],
				'status'    => $filters['status'],
				'search'    => $filters['search'],
				'date_from' => $range['from'],
				'date_to'   => $range['to'],
			)
		);

		$base_url = Sparkknife_Ledger_Renderer::url(
			'transactions',
			array(
				'person_id' => $filters['person_id'] ? $filters['person_id'] : '',
				'type'      => $filters['type'],
				'status'    => $filters['status'],
				'search'    => $filters['search'],
				'range'     => $filters['range'],
				'date_from' => $filters['date_from'],
				'date_to'   => $filters['date_to'],
				'orderby'   => $filters['orderby'],
				'order'     => $filters['order'],
			)
		);

		Sparkknife_Ledger_Renderer::view(
			'transactions/list',
			array(
				'query'    => $query,
				'totals'   => $totals,
				'filters'  => $filters,
				'range'    => $range,
				'persons'  => Sparkknife_Ledger_Person_Repository::instance()->get_all(),
				'base_url' => $base_url,
				'current'  => 'transactions',
			)
		);
	}

	/**
	 * فرم ایجاد/ویرایش.
	 *
	 * @param int $transaction_id شناسه.
	 * @return void
	 */
	private static function render_form( $transaction_id ) {
		$transaction = null;

		if ( $transaction_id > 0 ) {
			$transaction = Sparkknife_Ledger_Transaction_Repository::instance()->find_with_person( $transaction_id );

			if ( ! $transaction ) {
				Sparkknife_Ledger_Renderer::view(
					'partials/error-page',
					array(
						'title'   => __( 'تراکنش یافت نشد', 'sparkknife-ledger' ),
						'message' => __( 'تراکنش موردنظر وجود ندارد.', 'sparkknife-ledger' ),
						'current' => 'transactions',
					)
				);
				return;
			}

			if ( 'posted' !== $transaction->status ) {
				Sparkknife_Ledger_Renderer::view(
					'partials/error-page',
					array(
						'title'   => __( 'تراکنش باطل‌شده', 'sparkknife-ledger' ),
						'message' => __( 'تراکنش باطل‌شده قابل ویرایش نیست. برای اصلاح، تراکنش جدید ثبت کنید.', 'sparkknife-ledger' ),
						'current' => 'transactions',
					)
				);
				return;
			}
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- مقدار پیش‌فرض فرم.
		$preselected_person = isset( $_GET['person'] ) ? absint( $_GET['person'] ) : 0;
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$persons = Sparkknife_Ledger_Person_Repository::instance()->get_all( true );

		Sparkknife_Ledger_Renderer::view(
			'transactions/form',
			array(
				'transaction'       => $transaction,
				'persons'           => $persons,
				'preselected_person' => $transaction ? (int) $transaction->person_id : $preselected_person,
				'current'           => 'transactions',
			)
		);
	}

	/**
	 * جزئیات تراکنش.
	 *
	 * @param int $transaction_id شناسه.
	 * @return void
	 */
	private static function render_detail( $transaction_id ) {
		$transaction = Sparkknife_Ledger_Transaction_Repository::instance()->find_with_person( $transaction_id );

		if ( ! $transaction ) {
			Sparkknife_Ledger_Renderer::view(
				'partials/error-page',
				array(
					'title'   => __( 'تراکنش یافت نشد', 'sparkknife-ledger' ),
					'message' => __( 'تراکنش موردنظر وجود ندارد.', 'sparkknife-ledger' ),
					'current' => 'transactions',
				)
			);
			return;
		}

		$summary = Sparkknife_Ledger_Ledger_Service::instance()->get_person_summary( (int) $transaction->person_id );
		$history = Sparkknife_Ledger_Audit_Service::instance()->get_history( 'transaction', $transaction_id, 30 );

		Sparkknife_Ledger_Renderer::view(
			'transactions/detail',
			array(
				'transaction' => $transaction,
				'summary'     => $summary,
				'history'     => $history,
				'current'     => 'transactions',
			)
		);
	}
}
