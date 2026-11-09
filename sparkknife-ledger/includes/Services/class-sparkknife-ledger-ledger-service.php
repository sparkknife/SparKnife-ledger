<?php
/**
 * سرویس محاسبات مالی.
 *
 * منبع واحد حقیقت برای محاسبه مانده:
 *   مانده = مجموع ورودی‌های معتبر − مجموع خروجی‌های معتبر
 * تراکنش‌های باطل‌شده (status = void) هرگز در محاسبات لحاظ نمی‌شوند.
 *
 * @author  SparKnife <155084046+sparkknife@users.noreply.github.com>
 *
 * @package SparKnife_Ledger
 */

defined( 'ABSPATH' ) || exit;

/**
 * محاسبات مانده و آمار.
 */
class Sparkknife_Ledger_Ledger_Service {

	/**
	 * نمونه یکتا.
	 *
	 * @var Sparkknife_Ledger_Ledger_Service|null
	 */
	private static $instance = null;

	/**
	 * دریافت نمونه.
	 *
	 * @return Sparkknife_Ledger_Ledger_Service
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * وضعیت‌های حساب.
	 *
	 * @return array<string, string>
	 */
	public static function account_statuses() {
		return array(
			'positive' => __( 'مانده مثبت', 'sparkknife-ledger' ),
			'negative' => __( 'مانده منفی', 'sparkknife-ledger' ),
			'settled'  => __( 'حساب تسویه‌شده', 'sparkknife-ledger' ),
		);
	}

	/**
	 * تشخیص وضعیت حساب از مانده.
	 *
	 * @param int $balance مانده مقیاس‌شده.
	 * @return string positive|negative|settled
	 */
	public static function status_from_balance( $balance ) {
		$comparison = Sparkknife_Ledger_Amount::cmp( $balance, '0' );

		if ( $comparison > 0 ) {
			return 'positive';
		}

		if ( $comparison < 0 ) {
			return 'negative';
		}

		return 'settled';
	}

	/**
	 * برچسب وضعیت حساب.
	 *
	 * @param string $status وضعیت.
	 * @return string
	 */
	public static function status_label( $status ) {
		$statuses = self::account_statuses();
		return isset( $statuses[ $status ] ) ? $statuses[ $status ] : __( 'نامشخص', 'sparkknife-ledger' );
	}

	/**
	 * محاسبه مبالغ اشخاص با یک کوئری گروهی (بدون N+1).
	 *
	 * @param array<int, int>      $person_ids فهرست شناسه‌ها.
	 * @param array<string, mixed> $date_args  فیلتر بازه تاریخ (اختیاری).
	 * @return array<int, array<string, mixed>>
	 */
	public function get_balances_bulk( array $person_ids, array $date_args = array() ) {
		global $wpdb;

		$person_ids = array_values( array_unique( array_filter( array_map( 'absint', $person_ids ) ) ) );

		if ( empty( $person_ids ) ) {
			return array();
		}

		$table = Sparkknife_Ledger_DB::transactions_table();

		$placeholders = implode( ',', array_fill( 0, count( $person_ids ), '%d' ) );
		$params       = $person_ids;

		$where = "status = 'posted' AND person_id IN ({$placeholders})";

		if ( ! empty( $date_args['from'] ) ) {
			$where   .= ' AND txn_date >= %s';
			$params[] = $date_args['from'];
		}

		if ( ! empty( $date_args['to'] ) ) {
			$where   .= ' AND txn_date <= %s';
			$params[] = $date_args['to'];
		}

		$sql =
			"SELECT person_id,
				COALESCE(SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END), 0) AS income,
				COALESCE(SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END), 0) AS expense,
				COUNT(*) AS txn_count
			FROM {$table}
			WHERE {$where}
			GROUP BY person_id";

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ) );

		$result = array();

		foreach ( $person_ids as $person_id ) {
			$result[ $person_id ] = array(
				'person_id'      => $person_id,
				'income'         => '0',
				'expense'        => '0',
				'balance'        => '0',
				'count'          => 0,
				'account_status' => 'settled',
			);
		}

		foreach ( (array) $rows as $row ) {
			$person_id = (int) $row->person_id;
			$income    = Sparkknife_Ledger_Amount::normalize( $row->income );
			$expense   = Sparkknife_Ledger_Amount::normalize( $row->expense );
			$balance   = Sparkknife_Ledger_Amount::sub( $income, $expense );

			$result[ $person_id ] = array(
				'person_id'      => $person_id,
				'income'         => $income,
				'expense'        => $expense,
				'balance'        => $balance,
				'count'          => (int) $row->txn_count,
				'account_status' => self::status_from_balance( $balance ),
			);
		}

		return $result;
	}

	/**
	 * خلاصه مالی یک شخص.
	 *
	 * @param int                  $person_id شناسه.
	 * @param array<string, mixed> $date_args فیلتر بازه تاریخ.
	 * @return array<string, mixed>
	 */
	public function get_person_summary( $person_id, array $date_args = array() ) {
		$person_id = absint( $person_id );

		$balances = $this->get_balances_bulk( array( $person_id ), $date_args );

		$summary = isset( $balances[ $person_id ] )
			? $balances[ $person_id ]
			: array(
				'person_id'      => $person_id,
				'income'         => '0',
				'expense'        => '0',
				'balance'        => '0',
				'count'          => 0,
				'account_status' => 'settled',
			);

		$void_count = Sparkknife_Ledger_DB::count_rows(
			Sparkknife_Ledger_DB::transactions_table(),
			"person_id = %d AND status = 'void'",
			array( $person_id )
		);

		$summary['void_count']       = $void_count;
		$summary['account_status_label'] = self::status_label( $summary['account_status'] );
		$summary['income_formatted']  = Sparkknife_Ledger_Money::format( $summary['income'] );
		$summary['expense_formatted'] = Sparkknife_Ledger_Money::format( $summary['expense'] );
		$summary['balance_formatted'] = Sparkknife_Ledger_Money::format( $summary['balance'] );

		return $summary;
	}

	/**
	 * آمار کلی سیستم (داشبورد).
	 *
	 * @param array<string, mixed> $date_args فیلتر بازه تاریخ.
	 * @return array<string, mixed>
	 */
	public function get_overall_stats( array $date_args = array() ) {
		global $wpdb;

		$table   = Sparkknife_Ledger_DB::transactions_table();
		$where   = "status = 'posted'";
		$params  = array();

		if ( ! empty( $date_args['from'] ) ) {
			$where   .= ' AND txn_date >= %s';
			$params[] = $date_args['from'];
		}

		if ( ! empty( $date_args['to'] ) ) {
			$where   .= ' AND txn_date <= %s';
			$params[] = $date_args['to'];
		}

		$sql = "SELECT
				COALESCE(SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END), 0) AS income,
				COALESCE(SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END), 0) AS expense,
				COUNT(*) AS total_count,
				COUNT(DISTINCT person_id) AS persons_with_txn
			FROM {$table} WHERE {$where}";

		if ( ! empty( $params ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$row = $wpdb->get_row( $wpdb->prepare( $sql, $params ) );
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$row = $wpdb->get_row( $sql );
		}

		$income  = $row ? Sparkknife_Ledger_Amount::normalize( $row->income ) : '0';
		$expense = $row ? Sparkknife_Ledger_Amount::normalize( $row->expense ) : '0';
		$balance = Sparkknife_Ledger_Amount::sub( $income, $expense );

		$stats = array(
			'persons_total'     => Sparkknife_Ledger_Person_Repository::instance()->count_by_status( 'all' ),
			'persons_active'    => Sparkknife_Ledger_Person_Repository::instance()->count_by_status( 'active' ),
			'persons_inactive'  => Sparkknife_Ledger_Person_Repository::instance()->count_by_status( 'inactive' ),
			'income'            => $income,
			'expense'           => $expense,
			'balance'           => $balance,
			'transactions'      => $row ? (int) $row->total_count : 0,
			'transactions_all'  => Sparkknife_Ledger_Transaction_Repository::instance()->count(),
			'void_count'        => Sparkknife_Ledger_Transaction_Repository::instance()->count( 'void' ),
			'persons_with_txn'  => $row ? (int) $row->persons_with_txn : 0,
			'average_income'    => Sparkknife_Ledger_Transaction_Repository::instance()->average_amount( 'income' ),
			'average_expense'   => Sparkknife_Ledger_Transaction_Repository::instance()->average_amount( 'expense' ),
		);

		$stats['income_formatted']  = Sparkknife_Ledger_Money::format( $income );
		$stats['expense_formatted'] = Sparkknife_Ledger_Money::format( $expense );
		$stats['balance_formatted'] = Sparkknife_Ledger_Money::format( $stats['balance'] );
		$stats['account_status']    = self::status_from_balance( $stats['balance'] );

		return $stats;
	}

	/**
	 * تقسیم‌بندی اشخاص بر اساس وضعیت مانده.
	 *
	 * @param string $status positive|negative|settled|all.
	 * @param int    $limit  حداکثر تعداد (۰ = بدون محدودیت).
	 * @return array<int, array<string, mixed>>
	 */
	public function get_persons_by_account_status( $status = 'all', $limit = 0 ) {
		$persons = Sparkknife_Ledger_Person_Repository::instance()->get_all();

		$ids      = array_map( 'intval', wp_list_pluck( $persons, 'id' ) );
		$balances = $this->get_balances_bulk( $ids );

		$result = array();

		foreach ( $persons as $person ) {
			$person_id = (int) $person->id;
			$summary   = isset( $balances[ $person_id ] ) ? $balances[ $person_id ] : null;

			if ( ! $summary ) {
				continue;
			}

			if ( 'all' !== $status && $summary['account_status'] !== $status ) {
				continue;
			}

			$result[] = array(
				'id'               => $person_id,
				'name'             => $person->name,
				'phone'            => $person->phone,
				'status'           => $person->status,
				'income'           => $summary['income'],
				'expense'          => $summary['expense'],
				'balance'          => $summary['balance'],
				'count'            => $summary['count'],
				'account_status'   => $summary['account_status'],
				'income_formatted'  => Sparkknife_Ledger_Money::format( $summary['income'] ),
				'expense_formatted' => Sparkknife_Ledger_Money::format( $summary['expense'] ),
				'balance_formatted' => Sparkknife_Ledger_Money::format( $summary['balance'] ),
			);
		}

		usort(
			$result,
			function ( $a, $b ) {
				return Sparkknife_Ledger_Amount::cmp( $b['balance'], $a['balance'] );
			}
		);

		if ( $limit > 0 ) {
			$result = array_slice( $result, 0, (int) $limit );
		}

		return $result;
	}

	/**
	 * بازه‌های زمانی آماده برای فیلتر گزارش.
	 *
	 * @return array<string, array{from:string,to:string,label:string}>
	 */
	public static function preset_ranges() {
		$today = current_time( 'Y-m-d' );

		$ranges = array(
			'all'        => array(
				'from'  => '',
				'to'    => '',
				'label' => __( 'همه زمان‌ها', 'sparkknife-ledger' ),
			),
			'this_month' => array(
				'from'  => self::month_start( 0 ),
				'to'    => self::month_end( 0 ),
				'label' => __( 'این ماه', 'sparkknife-ledger' ),
			),
			'last_month' => array(
				'from'  => self::month_start( -1 ),
				'to'    => self::month_end( -1 ),
				'label' => __( 'ماه گذشته', 'sparkknife-ledger' ),
			),
			'this_year'  => array(
				'from'  => Sparkknife_Ledger_Date::uses_jalali()
					? self::jalali_year_start()
					: gmdate( 'Y-01-01', strtotime( $today ) ),
				'to'    => $today,
				'label' => __( 'سال جاری', 'sparkknife-ledger' ),
			),
			'last_30'    => array(
				'from'  => gmdate( 'Y-m-d', strtotime( $today . ' -30 days' ) ),
				'to'    => $today,
				'label' => __( '۳۰ روز اخیر', 'sparkknife-ledger' ),
			),
			'last_90'    => array(
				'from'  => gmdate( 'Y-m-d', strtotime( $today . ' -90 days' ) ),
				'to'    => $today,
				'label' => __( '۹۰ روز اخیر', 'sparkknife-ledger' ),
			),
		);

		return apply_filters( 'sparkknife_ledger_preset_ranges', $ranges );
	}

	/**
	 * ابتدای ماه جاری (یا ماه‌های قبل) به میلادی.
	 *
	 * «ماه» بر اساس تقویم فعال محاسبه می‌شود.
	 *
	 * پیش‌تر این بازه‌ها همیشه میلادی بودند: در تقویم شمسی، «این ماه»
	 * از ۱ نوامبر تا ۳۰ نوامبر می‌رفت، که در شمسی می‌شود ۱۰ آبان تا ۹ آذر
	 * — نه یک ماه شمسی، بلکه یک بازه کج که هم بخشی از آبان را جا
	 * می‌گذارد و هم بخشی از آذر را بی‌ربط شامل می‌شود. برای کاربری که
	 * با تقویم شمسی کار می‌کند این عدد بی‌معنی است.
	 *
	 * @param int $offset جابه‌جایی ماه (۰ = این ماه، -۱ = ماه قبل).
	 * @return string
	 */
	private static function month_start( $offset = 0 ) {
		$offset = (int) $offset;

		if ( ! Sparkknife_Ledger_Date::uses_jalali() ) {
			$first = gmdate( 'Y-m-01', strtotime( current_time( 'Y-m-01' ) . ' ' . $offset . ' month' ) );

			return $first;
		}

		list( $jy, $jm ) = self::current_jalali_ym();

		$jm += $offset;

		while ( $jm < 1 ) {
			$jm += 12;
			--$jy;
		}

		while ( $jm > 12 ) {
			$jm -= 12;
			++$jy;
		}

		list( $gy, $gm, $gd ) = Sparkknife_Ledger_Date::jalali_to_gregorian( $jy, $jm, 1 );

		return sprintf( '%04d-%02d-%02d', $gy, $gm, $gd );
	}

	/**
	 * انتهای ماه جاری (یا ماه‌های قبل) به میلادی.
	 *
	 * @param int $offset جابه‌جایی ماه.
	 * @return string
	 */
	private static function month_end( $offset = 0 ) {
		$offset = (int) $offset;

		if ( ! Sparkknife_Ledger_Date::uses_jalali() ) {
			$anchor = gmdate( 'Y-m-01', strtotime( current_time( 'Y-m-01' ) . ' ' . $offset . ' month' ) );

			return gmdate( 'Y-m-t', strtotime( $anchor ) );
		}

		list( $jy, $jm ) = self::current_jalali_ym();

		$jm += $offset;

		while ( $jm < 1 ) {
			$jm += 12;
			--$jy;
		}

		while ( $jm > 12 ) {
			$jm -= 12;
			++$jy;
		}

		// طول ماه شمسی: نیمه اول سال ۳۱ روز، نیمه دوم ۳۰ روز، اسفند ۲۹ یا ۳۰.
		if ( $jm <= 6 ) {
			$jd = 31;
		} elseif ( $jm <= 11 ) {
			$jd = 30;
		} else {
			$jd = Sparkknife_Ledger_Date::is_jalali_leap( $jy ) ? 30 : 29;
		}

		list( $gy, $gm, $gd ) = Sparkknife_Ledger_Date::jalali_to_gregorian( $jy, $jm, $jd );

		return sprintf( '%04d-%02d-%02d', $gy, $gm, $gd );
	}

	/**
	 * سال و ماه شمسی امروز.
	 *
	 * @return array{0: int, 1: int}
	 */
	private static function current_jalali_ym() {
		$today = current_time( 'Y-m-d' );

		list( $jy, $jm ) = Sparkknife_Ledger_Date::gregorian_to_jalali(
			(int) substr( $today, 0, 4 ),
			(int) substr( $today, 5, 2 ),
			(int) substr( $today, 8, 2 )
		);

		return array( (int) $jy, (int) $jm );
	}

	/**
	 * ابتدای سال شمسی جاری به میلادی.
	 *
	 * @return string
	 */
	private static function jalali_year_start() {
		$today = current_time( 'Y-m-d' );

		list( $jy ) = Sparkknife_Ledger_Date::gregorian_to_jalali(
			(int) substr( $today, 0, 4 ),
			(int) substr( $today, 5, 2 ),
			(int) substr( $today, 8, 2 )
		);

		list( $gy, $gm, $gd ) = Sparkknife_Ledger_Date::jalali_to_gregorian( $jy, 1, 1 );

		return sprintf( '%04d-%02d-%02d', $gy, $gm, $gd );
	}

	/**
	 * محاسبه بازه از پارامترهای درخواست.
	 *
	 * @param array<string, mixed> $request پارامترها (preset, date_from, date_to).
	 * @return array{from:string,to:string,label:string,preset:string}
	 */
	/**
	 * تبدیل یک تاریخ فیلتر (شمسی یا میلادی) به تاریخ ذخیره‌سازی میلادی.
	 *
	 * قاعده: اگر مقدار با الگوی YYYY-MM-DD یا YYYY/MM/DD و سال بزرگ‌تر از
	 * ۱۵۰۰ وارد شود، میلادی تلقی می‌شود (چون تاریخ شمسی معتبر همیشه
	 * سالی کمتر از ۱۶۰۰ دارد). در غیر این صورت بر اساس تقویم فعال تبدیل
	 * می‌شود تا لینک‌های قدیمی و APIهای موجود همیشه کار کنند.
	 *
	 * @param string $value مقدار خام.
	 * @return string|WP_Error
	 */
	private static function parse_filter_date( $value ) {
		$value = trim( Sparkknife_Ledger_Sanitizer::date( $value ) );

		if ( '' === $value ) {
			return '';
		}

		$normalized = str_replace( '/', '-', $value );

		if ( preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $normalized, $m ) && (int) $m[1] > 1500 ) {
			return Sparkknife_Ledger_Date::validate_gregorian( $normalized );
		}

		return Sparkknife_Ledger_Date::to_storage( $value );
	}

	public static function resolve_range( array $request ) {
		$preset = isset( $request['range'] ) ? sanitize_key( (string) $request['range'] ) : 'all';
		$ranges = self::preset_ranges();

		if ( 'custom' === $preset ) {
			$from = '';
			$to   = '';

			/*
			 * ورودی کاربر ممکن است در تقویم فعال (شمسی یا میلادی) باشد؛
			 * اما اگر مقدار «میلادیِ معتبر» تشخیص داده شود (سال > ۱۵۰۰ و
			 * الگوی YYYY-MM-DD)، همان ذخیره می‌شود. در غیر این صورت با
			 * تنظیمات تقویم تبدیل می‌گردد. تنظیمات فعلی ادمین در
			 * Sparkknife_Ledger_Date::to_storage() رعایت می‌شود.
			 */
			if ( ! empty( $request['date_from'] ) ) {
				$from_date = self::parse_filter_date( $request['date_from'] );

				if ( is_wp_error( $from_date ) ) {
					return array(
						'from'   => '',
						'to'     => '',
						'label'  => __( 'بازه دلخواه', 'sparkknife-ledger' ),
						'preset' => 'custom',
						'error'  => $from_date,
					);
				}

				$from = $from_date;
			}

			if ( ! empty( $request['date_to'] ) ) {
				$to_date = self::parse_filter_date( $request['date_to'] );

				if ( is_wp_error( $to_date ) ) {
					return array(
						'from'   => $from,
						'to'     => '',
						'label'  => __( 'بازه دلخواه', 'sparkknife-ledger' ),
						'preset' => 'custom',
						'error'  => $to_date,
					);
				}

				$to = $to_date;
			}

			if ( '' !== $from && '' !== $to && $from > $to ) {
				// بازه معکوس: جابه‌جایی خودکار به‌جای خطا.
				$swap = $from;
				$from = $to;
				$to   = $swap;
			}

			return array(
				'from'   => $from,
				'to'     => $to,
				'label'  => __( 'بازه دلخواه', 'sparkknife-ledger' ),
				'preset' => 'custom',
			);
		}

		if ( ! isset( $ranges[ $preset ] ) ) {
			$preset = 'all';
		}

		return array(
			'from'   => $ranges[ $preset ]['from'],
			'to'     => $ranges[ $preset ]['to'],
			'label'  => $ranges[ $preset ]['label'],
			'preset' => $preset,
		);
	}
}
