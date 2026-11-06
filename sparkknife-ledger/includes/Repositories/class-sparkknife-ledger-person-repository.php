<?php
/**
 * Repository اشخاص.
 *
 * @package SparKnife_Ledger
 */

defined( 'ABSPATH' ) || exit;

/**
 * دسترسی داده اشخاص.
 */
class Sparkknife_Ledger_Person_Repository {

	/**
	 * نمونه یکتا.
	 *
	 * @var Sparkknife_Ledger_Person_Repository|null
	 */
	private static $instance = null;

	/**
	 * دریافت نمونه.
	 *
	 * @return Sparkknife_Ledger_Person_Repository
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * ستون‌های مجاز برای مرتب‌سازی.
	 *
	 * @return array<int, string>
	 */
	public static function sortable_columns() {
		return array( 'name', 'created_at', 'updated_at', 'status', 'id' );
	}

	/**
	 * دریافت یک شخص (شامل حذف‌شده‌ها در صورت درخواست).
	 *
	 * @param int  $id             شناسه.
	 * @param bool $include_deleted شامل حذف‌شده‌ها.
	 * @return object|null
	 */
	public function find( $id, $include_deleted = false ) {
		global $wpdb;

		$table = Sparkknife_Ledger_DB::persons_table();
		$where = 'id = %d';

		if ( ! $include_deleted ) {
			$where .= ' AND deleted_at IS NULL';
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE {$where}", $id ) );

		return $row ? $row : null;
	}

	/**
	 * فهرست اشخاص با فیلتر، جستجو، مرتب‌سازی و صفحه‌بندی.
	 *
	 * @param array<string, mixed> $args پارامترها.
	 * @return array{items: array<int, object>, total: int}
	 */
	public function query( array $args = array() ) {
		global $wpdb;

		$defaults = array(
			'search'   => '',
			'status'   => '',
			'page'     => 1,
			'per_page' => 20,
			'orderby'  => 'created_at',
			'order'    => 'DESC',
			'include_deleted' => false,
			'only_deleted'    => false,
		);

		$args = wp_parse_args( $args, $defaults );

		$table  = Sparkknife_Ledger_DB::persons_table();
		$where  = array( '1=1' );
		$params = array();

		if ( ! empty( $args['only_deleted'] ) ) {
			$where[] = 'deleted_at IS NOT NULL';
		} elseif ( empty( $args['include_deleted'] ) ) {
			$where[] = 'deleted_at IS NULL';
		}

		if ( 'active' === $args['status'] || 'inactive' === $args['status'] ) {
			$where[]  = 'status = %s';
			$params[] = $args['status'];
		}

		$search = (string) $args['search'];

		/*
		 * `trim()` استاندارد PHP فقط فاصله و تب لاتین را می‌شناسد. نیم‌فاصله
		 * (`U+200C`) و فاصلهٔ بدون شکست (`U+00A0`) در فارسی پرکاربردند و
		 * `trim()` آن‌ها را نگه می‌دارد. یکسان‌سازی ممیزی، آن‌ها را حذف
		 * می‌کند و عبارت خالی می‌ماند؛ پس پیش از هر کاری همان
		 * یکسان‌سازی را برای سنجش «خالی بودن» به کار می‌بریم.
		 */
		$search_is_blank = '' === trim( Sparkknife_Ledger_Sanitizer::normalize_persian( $search ) );

		if ( ! $search_is_blank ) {
			/*
			 * جستجو با همه املای ممکن فارسی انجام می‌شود تا «علي» و «علی»
			 * یکدیگر را پیدا کنند؛ LIKE در MySQL بایتی مقایسه می‌کند.
			 */
			$variants = Sparkknife_Ledger_Sanitizer::search_variants( $search );
			$clauses  = array();

			$esc = Sparkknife_Ledger_Sanitizer::LIKE_ESCAPE;

			foreach ( $variants as $variant ) {
				// `like_pattern()` جای `esc_like()` که در SQLite بی‌اثر است.
				$like      = Sparkknife_Ledger_Sanitizer::like_pattern( $variant );
				$clauses[] = "(name LIKE %s ESCAPE '{$esc}' OR phone LIKE %s ESCAPE '{$esc}' OR description LIKE %s ESCAPE '{$esc}')";
				$params[]  = $like;
				$params[]  = $like;
				$params[]  = $like;
			}

			/*
			 * اگر یکسان‌سازی، عبارت را کاملاً خالی کند (مثلاً ورودی فقط
			 * نیم‌فاصله باشد) هیچ شرطی ساخته نمی‌شود. ساختن `()` کوئری
			 * را می‌شکند و `LIKE '%%'` همهٔ رکوردها را برمی‌گرداند.
			 */
			if ( ! empty( $clauses ) ) {
				$where[] = '(' . implode( ' OR ', $clauses ) . ')';
			}
		}

		$orderby = in_array( $args['orderby'], self::sortable_columns(), true ) ? $args['orderby'] : 'created_at';
		$order   = 'ASC' === strtoupper( (string) $args['order'] ) ? 'ASC' : 'DESC';

		/*
		 * مرتب‌سازی بر اساس «نام» روی ستون `sort_key` انجام می‌شود، نه
		 * `name`: ترتیب بایتی UTF-8 ترتیب الفبای فارسی نمی‌دهد و پنج
		 * حرف (`پ چ ک گ ی`) را به انتهای فهرست می‌پراند (باگ ۵۹).
		 * سایر ستون‌ها ترتیب بایتی درستی دارند.
		 */
		$sort_column = 'name' === $orderby ? 'sort_key' : $orderby;

		$where_sql = implode( ' AND ', $where );

		$count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";

		if ( ! empty( $params ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$total = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) );
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$total = (int) $wpdb->get_var( $count_sql );
		}

		$per_page = max( 1, min( 500, (int) $args['per_page'] ) );
		$page     = max( 1, (int) $args['page'] );

		/*
		 * سقف صفحه پیش از ضرب.
		 *
		 * پیش‌تر `( $page - 1 ) * $per_page` بدون سقف حساب می‌شد. با
		 * `page` بزرگ‌تر از حدود ۴٫۶×۱۰^۱۶ حاصل‌ضرب از بازهٔ `int`
		 * بیرون می‌زد و PHP بی‌صدا آن را به `float` تبدیل می‌کرد. بعد
		 * `%d` در `$wpdb->prepare()` آن float را به `0` کاست می‌داد و
		 * **صفحهٔ اول** به‌جای صفحهٔ درخواستی برمی‌گشت — بدون هیچ خطا.
		 */
		$max_page = (int) ceil( $total / $per_page );
		$max_page = max( 1, min( $max_page, 1000000 ) );

		if ( $page > $max_page ) {
			$page = $max_page;
		}

		$offset = ( $page - 1 ) * $per_page;

		// نام ستون از whitelist بالا آمده و جهت مرتب‌سازی ثابت است.
		$items_sql = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY {$sort_column} {$order}, id DESC LIMIT %d OFFSET %d";

		$items_params   = $params;
		$items_params[] = $per_page;
		$items_params[] = $offset;

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$items = $wpdb->get_results( $wpdb->prepare( $items_sql, $items_params ) );

		return array(
			'items' => is_array( $items ) ? $items : array(),
			'total' => $total,
			'pages' => (int) ceil( $total / $per_page ),
			'page'  => $page,
			'per_page' => $per_page,
		);
	}

	/**
	 * فهرست ساده اشخاص فعال (برای انتخاب‌گرها).
	 *
	 * @param bool $active_only فقط فعال‌ها.
	 * @return array<int, object>
	 */
	public function get_all( $active_only = false ) {
		global $wpdb;

		$table = Sparkknife_Ledger_DB::persons_table();
		$where = 'deleted_at IS NULL';

		if ( $active_only ) {
			$where .= " AND status = 'active'";
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results( "SELECT id, name, phone, status FROM {$table} WHERE {$where} ORDER BY sort_key ASC" );

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * اشخاص غیرحذف‌شده به‌علاوهٔ اشخاص حذف‌شده‌ای که تراکنش دارند.
	 *
	 * گزارش جمعی ردیف‌هایش را از این فهرست می‌سازد. شخص حذف‌شده‌ای که
	 * تراکنش دارد نباید از گزارش مالی ناپدید شود؛ وگرنه جمع کل عددی را
	 * نشان می‌دهد که هیچ ردیفی توضیحش نمی‌دهد. ستون `deleted_at` هم
	 * برگردانده می‌شود تا ویو بتواند ردیف را با برچسب «حذف‌شده» نشان دهد.
	 *
	 * @return array<int, object>
	 */
	public function get_all_with_transactions() {
		global $wpdb;

		$persons      = Sparkknife_Ledger_DB::persons_table();
		$transactions = Sparkknife_Ledger_DB::transactions_table();

		/*
		 * `EXISTS` به‌جای `JOIN` تا هر شخص فقط یک بار بیاید، حتی اگر
		 * صدها تراکنش داشته باشد.
		 */
		$sql = "SELECT p.id, p.name, p.phone, p.status, p.deleted_at
			FROM {$persons} p
			WHERE p.deleted_at IS NULL
			   OR EXISTS ( SELECT 1 FROM {$transactions} t WHERE t.person_id = p.id )
			ORDER BY p.sort_key ASC";

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results( $sql );

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * درج شخص جدید.
	 *
	 * @param array<string, mixed> $data داده معتبر.
	 * @return int|WP_Error شناسه یا خطا.
	 */
	public function insert( array $data ) {
		global $wpdb;

		$now = Sparkknife_Ledger_Date::now();

		$row = array(
			'name'        => $data['name'],
			'phone'       => $data['phone'],
			'description' => $data['description'],
			'status'      => $data['status'],
			'created_by'  => get_current_user_id(),
			'sort_key'    => Sparkknife_Ledger_Sanitizer::persian_sort_key( (string) $data['name'] ),
			'created_at'  => $now,
			'updated_at'  => $now,
			'deleted_at'  => null,
		);

		$formats = array( '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$inserted = $wpdb->insert( Sparkknife_Ledger_DB::persons_table(), $row, $formats );

		if ( false === $inserted ) {
			return new WP_Error(
				'sparkknife_ledger_person_insert_failed',
				__( 'ثبت شخص با خطا مواجه شد.', 'sparkknife-ledger' )
			);
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * به‌روزرسانی شخص.
	 *
	 * @param int                  $id   شناسه.
	 * @param array<string, mixed> $data داده معتبر.
	 * @return bool|WP_Error
	 */
	public function update( $id, array $data ) {
		global $wpdb;

		$row = array(
			'name'        => $data['name'],
			'phone'       => $data['phone'],
			'description' => $data['description'],
			'status'      => $data['status'],
			/*
			 * تغییر نام باید کلید مرتب‌سازی را هم به‌روز کند، وگرنه
			 * شخص پس از ویرایش نامش در جای قدیمی فهرست می‌ماند.
			 */
			'sort_key'    => Sparkknife_Ledger_Sanitizer::persian_sort_key( (string) $data['name'] ),
			'updated_at'  => Sparkknife_Ledger_Date::now(),
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$updated = $wpdb->update(
			Sparkknife_Ledger_DB::persons_table(),
			$row,
			array( 'id' => (int) $id ),
			array( '%s', '%s', '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);

		if ( false === $updated ) {
			return new WP_Error(
				'sparkknife_ledger_person_update_failed',
				__( 'ویرایش شخص با خطا مواجه شد.', 'sparkknife-ledger' )
			);
		}

		return true;
	}

	/**
	 * حذف نرم شخص.
	 *
	 * @param int $id شناسه.
	 * @return bool|WP_Error
	 */
	public function soft_delete( $id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$updated = $wpdb->update(
			Sparkknife_Ledger_DB::persons_table(),
			array(
				'deleted_at' => Sparkknife_Ledger_Date::now(),
				'status'     => 'inactive',
				'updated_at' => Sparkknife_Ledger_Date::now(),
			),
			array( 'id' => (int) $id ),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);

		if ( false === $updated ) {
			return new WP_Error(
				'sparkknife_ledger_person_delete_failed',
				__( 'حذف شخص با خطا مواجه شد.', 'sparkknife-ledger' )
			);
		}

		return true;
	}

	/**
	 * بازگردانی شخص حذف‌شده.
	 *
	 * @param int $id شناسه.
	 * @return bool|WP_Error
	 */
	public function restore( $id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$updated = $wpdb->update(
			Sparkknife_Ledger_DB::persons_table(),
			array(
				'deleted_at' => null,
				'status'     => 'active',
				'updated_at' => Sparkknife_Ledger_Date::now(),
			),
			array( 'id' => (int) $id ),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);

		return false === $updated
			? new WP_Error( 'sparkknife_ledger_person_restore_failed', __( 'بازگردانی شخص با خطا مواجه شد.', 'sparkknife-ledger' ) )
			: true;
	}

	/**
	 * شمارش اشخاص بر اساس وضعیت.
	 *
	 * @param string $status active|inactive|deleted|all.
	 * @return int
	 */
	public function count_by_status( $status = 'all' ) {
		$where = 'deleted_at IS NULL';

		if ( 'active' === $status ) {
			$where .= " AND status = 'active'";
		} elseif ( 'inactive' === $status ) {
			$where .= " AND status = 'inactive'";
		} elseif ( 'deleted' === $status ) {
			$where = 'deleted_at IS NOT NULL';
		}

		return Sparkknife_Ledger_DB::count_rows( Sparkknife_Ledger_DB::persons_table(), $where );
	}

	/**
	 * آیا شخص دارای تراکنش است؟
	 *
	 * @param int $person_id شناسه.
	 * @return int تعداد تراکنش‌ها.
	 */
	public function count_transactions( $person_id ) {
		return Sparkknife_Ledger_DB::count_rows(
			Sparkknife_Ledger_DB::transactions_table(),
			'person_id = %d',
			array( absint( $person_id ) )
		);
	}

	/**
	 * مجموع مبالغ به تفکیک نوع برای یک شخص (شامل تراکنش‌های باطل‌نشده).
	 *
	 * @param int    $person_id شناسه.
	 * @param string $type      income|expense.
	 * @param array  $date_args شرط بازه: array( 'from' => 'Y-m-d', 'to' => 'Y-m-d' ) یا خالی.
	 * @return string مجموع مقیاس‌شده.
	 */
	public function sum_amounts( $person_id, $type, $date_args = array() ) {
		global $wpdb;

		$table = Sparkknife_Ledger_DB::transactions_table();

		$where  = 'person_id = %d AND type = %s AND status = %s';
		$params = array( absint( $person_id ), $type, 'posted' );

		if ( ! empty( $date_args['from'] ) ) {
			$where   .= ' AND txn_date >= %s';
			$params[] = $date_args['from'];
		}

		if ( ! empty( $date_args['to'] ) ) {
			$where   .= ' AND txn_date <= %s';
			$params[] = $date_args['to'];
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$sum = $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(amount),0) FROM {$table} WHERE {$where}", $params ) );

		return Sparkknife_Ledger_Amount::normalize( null === $sum ? '0' : $sum );
	}
}
