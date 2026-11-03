<?php
/**
 * Repository تاریخچه تغییرات.
 *
 * @package SparKnife_Ledger
 */

defined( 'ABSPATH' ) || exit;

/**
 * دسترسی داده Audit Log.
 */
class Sparkknife_Ledger_Audit_Repository {

	/**
	 * نمونه یکتا.
	 *
	 * @var Sparkknife_Ledger_Audit_Repository|null
	 */
	private static $instance = null;

	/**
	 * دریافت نمونه.
	 *
	 * @return Sparkknife_Ledger_Audit_Repository
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * درج ردیف تاریخچه.
	 *
	 * @param array<string, mixed> $data داده.
	 * @return int|false
	 */
	public function insert( array $data ) {
		global $wpdb;

		$row = array(
			'object_type' => substr( (string) $data['object_type'], 0, 30 ),
			'object_id'   => (int) $data['object_id'],
			'action'      => substr( (string) $data['action'], 0, 40 ),
			'user_id'     => (int) $data['user_id'],
			'changes'     => isset( $data['changes'] ) ? $data['changes'] : null,
			'summary'     => isset( $data['summary'] ) ? $data['summary'] : '',
			'ip_address'  => isset( $data['ip_address'] ) ? substr( (string) $data['ip_address'], 0, 90 ) : '',
			'user_agent'  => isset( $data['user_agent'] ) ? substr( (string) $data['user_agent'], 0, 255 ) : '',
			'hash'        => isset( $data['hash'] ) ? $data['hash'] : null,
			'created_at'  => Sparkknife_Ledger_Date::now(),
		);

		$formats = array( '%s', '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s' );

		/*
		 * ستون `user_login` بعدها اضافه شد. اگر هنوز ساخته نشده باشد،
		 * فرستادنش درج را با خطای SQL می‌شکند و **همهٔ** تاریخچه از آن
		 * به بعد از دست می‌رود؛ پس فقط وقتی ستون وجود دارد فرستاده
		 * می‌شود.
		 */
		if ( Sparkknife_Ledger_DB::audit_schema_has_user_login() ) {
			$row['user_login'] = isset( $data['user_login'] ) ? substr( (string) $data['user_login'], 0, 60 ) : '';
			$formats[]         = '%s';
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$inserted = $wpdb->insert(
			Sparkknife_Ledger_DB::audit_table(),
			$row,
			$formats
		);

		return false === $inserted ? false : (int) $wpdb->insert_id;
	}

	/**
	 * کوئری تاریخچه با فیلتر و صفحه‌بندی.
	 *
	 * @param array<string, mixed> $args پارامترها.
	 * @return array{items: array<int, object>, total: int, pages: int, page: int, per_page: int}
	 */
	public function query( array $args = array() ) {
		global $wpdb;

		$defaults = array(
			'object_type' => '',
			'object_id'   => 0,
			'action'      => '',
			'user_id'     => 0,
			'search'      => '',
			'date_from'   => '',
			'date_to'     => '',
			'page'        => 1,
			'per_page'    => 30,
		);

		$args = wp_parse_args( $args, $defaults );

		$table  = Sparkknife_Ledger_DB::audit_table();
		$where  = array( '1=1' );
		$params = array();

		if ( ! empty( $args['object_type'] ) ) {
			$where[]  = 'object_type = %s';
			$params[] = $args['object_type'];
		}

		if ( ! empty( $args['object_id'] ) ) {
			$where[]  = 'object_id = %d';
			$params[] = absint( $args['object_id'] );
		}

		if ( ! empty( $args['action'] ) ) {
			$where[]  = 'action = %s';
			$params[] = $args['action'];
		}

		if ( ! empty( $args['user_id'] ) ) {
			$where[]  = 'user_id = %d';
			$params[] = absint( $args['user_id'] );
		}

		if ( ! empty( $args['search'] ) ) {
			/*
			 * جستجوی متنی در خلاصه و نام کاربر.
			 *
			 * این پارامتر پیش‌تر در `wp_parse_args()` نبود و هر مقداری که
			 * می‌رسید **بی‌صدا نادیده گرفته می‌شد** — یعنی هر کسی از
			 * `query()` جستجو می‌خواست، همهٔ رکوردها را می‌گرفت و فکر
			 * می‌کرد جستجو کار کرده. با هزاران رکورد ممیزی، پیدا کردن
			 * یک تغییر عملاً ناممکن بود.
			 */
			$like   = '%' . $wpdb->esc_like( (string) $args['search'] ) . '%';
			$fields = array( 'summary', 'changes', 'ip_address' );

			/*
			 * ستون `user_login` در نسخه‌های پیشین افزونه وجود نداشت و
			 * `dbDelta` ممکن است هنوز آن را نساخته باشد (مثلاً زمانی که
			 * به‌روزرسانی خودکار اجرا نشده). ارجاع به ستون ناموجود، کوئری
			 * را با خطای SQL می‌شکند و کل صفحه ممیزی خالی می‌شود؛ پس
			 * وجودش یک بار بررسی و نتیجه کش می‌شود.
			 */
			if ( Sparkknife_Ledger_DB::audit_schema_has_user_login() ) {
				$fields[] = 'user_login';
			}

			$where[] = '(' . implode( ' LIKE %s OR ', $fields ) . ' LIKE %s)';

			foreach ( $fields as $unused ) {
				$params[] = $like;
			}
		}

		if ( ! empty( $args['date_from'] ) ) {
			$where[]  = 'created_at >= %s';
			$params[] = $args['date_from'] . ' 00:00:00';
		}

		if ( ! empty( $args['date_to'] ) ) {
			$where[]  = 'created_at <= %s';
			$params[] = $args['date_to'] . ' 23:59:59';
		}

		$where_sql = implode( ' AND ', $where );

		$count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";

		if ( ! empty( $params ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$total = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) );
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$total = (int) $wpdb->get_var( $count_sql );
		}

		$per_page = max( 1, min( 200, (int) $args['per_page'] ) );
		$page     = max( 1, (int) $args['page'] );
		$offset   = ( $page - 1 ) * $per_page;

		$items_sql = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d";

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
	 * آخرین هش ثبت‌شده (برای زنجیره‌سازی).
	 *
	 * @return string
	 */
	public function last_hash() {
		global $wpdb;

		$table = Sparkknife_Ledger_DB::audit_table();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$hash = $wpdb->get_var( "SELECT hash FROM {$table} ORDER BY id DESC LIMIT 1" );

		return is_string( $hash ) ? $hash : '';
	}

	/**
	 * یافتن ردیف تاریخچه.
	 *
	 * @param int $id شناسه.
	 * @return object|null
	 */
	public function find( $id ) {
		global $wpdb;

		$table = Sparkknife_Ledger_DB::audit_table();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", absint( $id ) ) );

		return $row ? $row : null;
	}
}
