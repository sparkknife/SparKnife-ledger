<?php
/**
 * Repository تراکنش‌ها.
 *
 * @package SparKnife_Ledger
 */

defined( 'ABSPATH' ) || exit;

/**
 * دسترسی داده تراکنش‌ها.
 */
class Sparkknife_Ledger_Transaction_Repository {

	/**
	 * نمونه یکتا.
	 *
	 * @var Sparkknife_Ledger_Transaction_Repository|null
	 */
	private static $instance = null;

	/**
	 * دریافت نمونه.
	 *
	 * @return Sparkknife_Ledger_Transaction_Repository
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * ستون‌های مجاز مرتب‌سازی.
	 *
	 * @return array<int, string>
	 */
	public static function sortable_columns() {
		return array( 'id', 'txn_date', 'amount', 'created_at', 'reference', 'type', 'status' );
	}

	/**
	 * یافتن تراکنش.
	 *
	 * @param int $id شناسه.
	 * @return object|null
	 */
	public function find( $id ) {
		global $wpdb;

		$table = Sparkknife_Ledger_DB::transactions_table();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", absint( $id ) ) );

		return $row ? $row : null;
	}

	/**
	 * یافتن تراکنش با اطلاعات شخص.
	 *
	 * @param int $id شناسه.
	 * @return object|null
	 */
	public function find_with_person( $id ) {
		global $wpdb;

		$transactions = Sparkknife_Ledger_DB::transactions_table();
		$persons      = Sparkknife_Ledger_DB::persons_table();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT t.*, p.name AS person_name, p.phone AS person_phone, p.status AS person_status
				FROM {$transactions} t
				LEFT JOIN {$persons} p ON p.id = t.person_id
				WHERE t.id = %d",
				absint( $id )
			)
		);

		return $row ? $row : null;
	}

	/**
	 * یافتن بر اساس شناسه مرجع.
	 *
	 * @param string $reference شناسه.
	 * @return object|null
	 */
	public function find_by_reference( $reference ) {
		global $wpdb;

		$table = Sparkknife_Ledger_DB::transactions_table();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE reference = %s", $reference ) );

		return $row ? $row : null;
	}

	/**
	 * ساخت شرط WHERE مشترک از فیلترها.
	 *
	 * @param array<string, mixed> $args فیلترها.
	 * @return array{0:string,1:array<int,mixed>} [شرط, پارامترها]
	 */
	private function build_where( array $args ) {
		global $wpdb;

		$where  = array( '1=1' );
		$params = array();

		if ( ! empty( $args['person_id'] ) ) {
			$where[]  = 't.person_id = %d';
			$params[] = absint( $args['person_id'] );
		}

		if ( ! empty( $args['type'] ) && in_array( $args['type'], array( 'income', 'expense' ), true ) ) {
			$where[]  = 't.type = %s';
			$params[] = $args['type'];
		}

		$status = isset( $args['status'] ) ? (string) $args['status'] : '';
		if ( 'posted' === $status || 'void' === $status ) {
			$where[]  = 't.status = %s';
			$params[] = $status;
		} elseif ( 'active_only' === $status ) {
			$where[] = "t.status = 'posted'";
		}

		if ( ! empty( $args['date_from'] ) ) {
			$where[]  = 't.txn_date >= %s';
			$params[] = $args['date_from'];
		}

		if ( ! empty( $args['date_to'] ) ) {
			$where[]  = 't.txn_date <= %s';
			$params[] = $args['date_to'];
		}

		if ( ! empty( $args['created_from'] ) ) {
			$where[]  = 't.created_at >= %s';
			$params[] = $args['created_from'] . ' 00:00:00';
		}

		if ( ! empty( $args['created_to'] ) ) {
			$where[]  = 't.created_at <= %s';
			$params[] = $args['created_to'] . ' 23:59:59';
		}

		if ( ! empty( $args['min_amount'] ) ) {
			$where[]  = 't.amount >= %s';
			$params[] = Sparkknife_Ledger_Amount::normalize( $args['min_amount'] );
		}

		if ( ! empty( $args['max_amount'] ) ) {
			$where[]  = 't.amount <= %s';
			$params[] = Sparkknife_Ledger_Amount::normalize( $args['max_amount'] );
		}

		if ( ! empty( $args['user_id'] ) ) {
			$where[]  = 't.created_by = %d';
			$params[] = absint( $args['user_id'] );
		}

		if ( ! empty( $args['search'] ) ) {
			// همه املای ممکن فارسی تا «علي» و «علی» یکدیگر را بیابند.
			$variants = Sparkknife_Ledger_Sanitizer::search_variants( trim( (string) $args['search'] ) );
			$clauses  = array();

			foreach ( $variants as $variant ) {
				$like      = '%' . $wpdb->esc_like( $variant ) . '%';
				$clauses[] = '(t.reference LIKE %s OR t.description LIKE %s OR p.name LIKE %s)';
				$params[]  = $like;
				$params[]  = $like;
				$params[]  = $like;
			}

			$where[] = '(' . implode( ' OR ', $clauses ) . ')';
		}

		return array( implode( ' AND ', $where ), $params );
	}

	/**
	 * فهرست تراکنش‌ها با فیلتر، صفحه‌بندی و اطلاعات شخص.
	 *
	 * @param array<string, mixed> $args پارامترها.
	 * @return array{items: array<int, object>, total: int, pages: int, page: int, per_page: int}
	 */
	public function query( array $args = array() ) {
		global $wpdb;

		$defaults = array(
			'person_id' => 0,
			'type'      => '',
			'status'    => '',
			'date_from' => '',
			'date_to'   => '',
			'search'    => '',
			'page'      => 1,
			'per_page'  => 20,
			'orderby'   => 'txn_date',
			'order'     => 'DESC',
			'join_person' => true,
		);

		$args = wp_parse_args( $args, $defaults );

		$transactions = Sparkknife_Ledger_DB::transactions_table();
		$persons      = Sparkknife_Ledger_DB::persons_table();

		list( $where_sql, $params ) = $this->build_where( $args );

		$join = $args['join_person']
			? "LEFT JOIN {$persons} p ON p.id = t.person_id"
			: "LEFT JOIN {$persons} p ON p.id = t.person_id";

		$count_sql = "SELECT COUNT(*) FROM {$transactions} t {$join} WHERE {$where_sql}";

		if ( ! empty( $params ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$total = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) );
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$total = (int) $wpdb->get_var( $count_sql );
		}

		$per_page = max( 1, min( 500, (int) $args['per_page'] ) );
		$page     = max( 1, (int) $args['page'] );
		$offset   = ( $page - 1 ) * $per_page;

		$orderby = in_array( $args['orderby'], self::sortable_columns(), true ) ? $args['orderby'] : 'txn_date';
		$order   = 'ASC' === strtoupper( (string) $args['order'] ) ? 'ASC' : 'DESC';

		$items_sql = "SELECT t.*, p.name AS person_name, p.status AS person_status
			FROM {$transactions} t {$join}
			WHERE {$where_sql}
			ORDER BY t.{$orderby} {$order}, t.id DESC
			LIMIT %d OFFSET %d";

		$items_params   = $params;
		$items_params[] = $per_page;
		$items_params[] = $offset;

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$items = $wpdb->get_results( $wpdb->prepare( $items_sql, $items_params ) );

		return array(
			'items'    => is_array( $items ) ? $items : array(),
			'total'    => $total,
			'pages'    => (int) ceil( $total / $per_page ),
			'page'     => $page,
			'per_page' => $per_page,
		);
	}

	/**
	 * محاسبه مجموع مبالغ با فیلتر (فقط تراکنش‌های معتبر به‌صورت پیش‌فرض).
	 *
	 * @param array<string, mixed> $args فیلترها.
	 * @return array{income:int, expense:int, count:int}
	 */
	public function sum_by_type( array $args = array() ) {
		global $wpdb;

		$transactions = Sparkknife_Ledger_DB::transactions_table();
		$persons      = Sparkknife_Ledger_DB::persons_table();

		$args['status'] = isset( $args['status'] ) && in_array( $args['status'], array( 'posted', 'void' ), true )
			? $args['status']
			: 'active_only';

		list( $where_sql, $params ) = $this->build_where( $args );

		$sql = "SELECT
				COALESCE(SUM(CASE WHEN t.type = 'income' THEN t.amount ELSE 0 END), 0) AS income,
				COALESCE(SUM(CASE WHEN t.type = 'expense' THEN t.amount ELSE 0 END), 0) AS expense,
				COUNT(t.id) AS total_count
			FROM {$transactions} t
			LEFT JOIN {$persons} p ON p.id = t.person_id
			WHERE {$where_sql}";

		if ( ! empty( $params ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$row = $wpdb->get_row( $wpdb->prepare( $sql, $params ) );
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$row = $wpdb->get_row( $sql );
		}

		return array(
			'income'  => $row ? Sparkknife_Ledger_Amount::normalize( $row->income ) : '0',
			'expense' => $row ? Sparkknife_Ledger_Amount::normalize( $row->expense ) : '0',
			'count'   => $row ? (int) $row->total_count : 0,
		);
	}

	/**
	 * درج تراکنش.
	 *
	 * @param array<string, mixed> $data داده معتبر.
	 * @return int|WP_Error
	 */
	public function insert( array $data ) {
		global $wpdb;

		$now = Sparkknife_Ledger_Date::now();

		$row = array(
			'reference'   => $data['reference'],
			'person_id'   => (int) $data['person_id'],
			'type'        => $data['type'],
			'amount'      => Sparkknife_Ledger_Amount::normalize( $data['amount'] ),
			'currency'    => Sparkknife_Ledger_Money::currency_code(),
			'txn_date'    => $data['txn_date'],
			'description' => $data['description'],
			'status'      => 'posted',
			'created_by'  => get_current_user_id(),
			'created_at'  => $now,
			'updated_by'  => get_current_user_id(),
			'updated_at'  => $now,
			'void_reason' => null,
			'voided_by'   => 0,
			'voided_at'   => null,
		);

		// مبلغ رشته است (%s) تا در PHP ۳۲-بیتی به عدد صحیح محدود نشود.
		$formats = array( '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%s', '%d', '%s' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$inserted = $wpdb->insert( Sparkknife_Ledger_DB::transactions_table(), $row, $formats );

		if ( false === $inserted ) {
			return new WP_Error( 'sparkknife_ledger_transaction_insert_failed', __( 'ثبت تراکنش با خطا مواجه شد.', 'sparkknife-ledger' ) );
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * به‌روزرسانی فیلدهای تراکنش.
	 *
	 * @param int                  $id   شناسه.
	 * @param array<string, mixed> $data فیلدها.
	 * @return bool|WP_Error
	 */
	public function update( $id, array $data ) {
		global $wpdb;

		$data['updated_by'] = get_current_user_id();
		$data['updated_at'] = Sparkknife_Ledger_Date::now();

		$formats = array();
		foreach ( $data as $key => $value ) {
			// مبلغ به‌صورت رشته ارسال می‌شود تا در PHP ۳۲-بیتی سرریز نکند.
			$formats[] = in_array( $key, array( 'person_id', 'updated_by', 'voided_by' ), true ) ? '%d' : '%s';
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$updated = $wpdb->update(
			Sparkknife_Ledger_DB::transactions_table(),
			$data,
			array( 'id' => (int) $id ),
			$formats,
			array( '%d' )
		);

		if ( false === $updated ) {
			return new WP_Error( 'sparkknife_ledger_transaction_update_failed', __( 'ویرایش تراکنش با خطا مواجه شد.', 'sparkknife-ledger' ) );
		}

		return true;
	}

	/**
	 * ابطال تراکنش.
	 *
	 * @param int    $id     شناسه.
	 * @param string $reason دلیل.
	 * @return bool|WP_Error
	 */
	public function void( $id, $reason ) {
		global $wpdb;

		$now = Sparkknife_Ledger_Date::now();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$updated = $wpdb->update(
			Sparkknife_Ledger_DB::transactions_table(),
			array(
				'status'      => 'void',
				'void_reason' => $reason,
				'voided_by'   => get_current_user_id(),
				'voided_at'   => $now,
				'updated_by'  => get_current_user_id(),
				'updated_at'  => $now,
			),
			array(
				'id'     => (int) $id,
				'status' => 'posted',
			),
			array( '%s', '%s', '%d', '%s', '%d', '%s' ),
			array( '%d', '%s' )
		);

		if ( false === $updated ) {
			return new WP_Error( 'sparkknife_ledger_void_failed', __( 'ابطال تراکنش با خطا مواجه شد.', 'sparkknife-ledger' ) );
		}

		if ( 0 === (int) $updated ) {
			return new WP_Error( 'sparkknife_ledger_void_noop', __( 'تراکنش یافت نشد یا قبلاً باطل شده است.', 'sparkknife-ledger' ) );
		}

		return true;
	}

	/**
	 * شمارش تراکنش‌های معتبر یک شخص.
	 *
	 * @param int $person_id شناسه.
	 * @return int
	 */
	public function count_active_for_person( $person_id ) {
		return Sparkknife_Ledger_DB::count_rows(
			Sparkknife_Ledger_DB::transactions_table(),
			'person_id = %d AND status = %s',
			array( absint( $person_id ), 'posted' )
		);
	}

	/**
	 * شمارش کل تراکنش‌ها.
	 *
	 * @param string $status وضعیت (posted|void|all).
	 * @return int
	 */
	public function count( $status = 'all' ) {
		if ( 'posted' === $status || 'void' === $status ) {
			return Sparkknife_Ledger_DB::count_rows(
				Sparkknife_Ledger_DB::transactions_table(),
				'status = %s',
				array( $status )
			);
		}

		return Sparkknife_Ledger_DB::count_rows( Sparkknife_Ledger_DB::transactions_table() );
	}

	/**
	 * آخرین تراکنش‌ها.
	 *
	 * @param int $limit تعداد.
	 * @return array<int, object>
	 */
	public function latest( $limit = 5 ) {
		global $wpdb;

		$transactions = Sparkknife_Ledger_DB::transactions_table();
		$persons      = Sparkknife_Ledger_DB::persons_table();
		$limit        = max( 1, min( 50, (int) $limit ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT t.*, p.name AS person_name
				FROM {$transactions} t
				LEFT JOIN {$persons} p ON p.id = t.person_id
				ORDER BY t.created_at DESC, t.id DESC
				LIMIT %d",
				$limit
			)
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * میانگین مبلغ تراکنش‌ها.
	 *
	 * @param string $type   income|expense.
	 * @param string $status posted|all.
	 * @return int
	 */
	public function average_amount( $type, $status = 'posted' ) {
		global $wpdb;

		$table  = Sparkknife_Ledger_DB::transactions_table();
		$where  = 'type = %s';
		$params = array( $type );

		if ( 'posted' === $status ) {
			$where   .= ' AND status = %s';
			$params[] = 'posted';
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$avg = $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(ROUND(AVG(amount)) ,0) FROM {$table} WHERE {$where}", $params ) );

		return Sparkknife_Ledger_Amount::normalize( null === $avg ? '0' : $avg );
	}

	/**
	 * داده نمودار ماهانه (۶ ماه اخیر) بر اساس تاریخ تراکنش.
	 *
	 * @param int $months تعداد ماه.
	 * @return array<int, array<string, mixed>>
	 */
	public function monthly_totals( $months = 6 ) {
		global $wpdb;

		$table  = Sparkknife_Ledger_DB::transactions_table();
		$months = max( 1, min( 24, (int) $months ) );

		$since = gmdate( 'Y-m-d', strtotime( current_time( 'Y-m-d' ) . ' -' . ( $months - 1 ) . ' months' ) );
		$since = gmdate( 'Y-m-01', strtotime( $since ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DATE_FORMAT(txn_date, '%%Y-%%m') AS month_key,
					COALESCE(SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END),0) AS income,
					COALESCE(SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END),0) AS expense
				FROM {$table}
				WHERE status = 'posted' AND txn_date >= %s
				GROUP BY month_key
				ORDER BY month_key ASC",
				$since
			)
		);

		$by_month = array();
		foreach ( (array) $rows as $row ) {
			$by_month[ $row->month_key ] = array(
				'income'  => Sparkknife_Ledger_Amount::normalize( $row->income ),
				'expense' => Sparkknife_Ledger_Amount::normalize( $row->expense ),
			);
		}

		$result = array();
		for ( $i = $months - 1; $i >= 0; $i-- ) {
			$key      = gmdate( 'Y-m', strtotime( current_time( 'Y-m-01' ) . ' -' . $i . ' months' ) );
			$result[] = array(
				'key'     => $key,
				'income'  => isset( $by_month[ $key ] ) ? $by_month[ $key ]['income'] : '0',
				'expense' => isset( $by_month[ $key ] ) ? $by_month[ $key ]['expense'] : '0',
			);
		}

		return $result;
	}
}
