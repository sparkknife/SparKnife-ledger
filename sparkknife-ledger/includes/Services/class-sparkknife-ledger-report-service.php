<?php
/**
 * سرویس گزارش‌گیری فردی و تجمیعی.
 *
 * @package SparKnife_Ledger
 */

defined( 'ABSPATH' ) || exit;

/**
 * تولید داده گزارش‌ها.
 */
class Sparkknife_Ledger_Report_Service {

	/**
	 * سقف ردیف در هر صفحه.
	 *
	 * صفحه‌های عادی حداکثر ۲۰۰ ردیف نشان می‌دهند تا بار سرور کنترل شود،
	 * ولی چاپ و صادرات CSV باید همه داده را بدهند. پیش‌تر کنترلر چاپ
	 * `per_page` را ۵۰۰ می‌فرستاد و بی‌صدا به ۲۰۰ کوتاه می‌شد؛ صادرات هم
	 * اصلاً نمی‌فرستاد و روی پیش‌فرض ۲۰ می‌ماند. نتیجه این بود که از ۲۵۰
	 * شخص، فقط ۲۰۰ در چاپ و ۲۰ در CSV می‌آمد، در حالی که `total` عدد
	 * کامل را نشان می‌داد و کاربر فکر می‌کرد فایلش کامل است.
	 */
	const MAX_PAGE_SIZE = 200;

	/**
	 * سقف ردیف برای صادرات و چاپ.
	 *
	 * عملاً «همه چیز» است؛ فقط برای جلوگیری از تمام شدن حافظه در دفتری
	 * با بیش از صدهزار شخص گذاشته شده. هر ردیف حدود ۲۰۰ بایت می‌گیرد،
	 * پس ۱۰۰٬۰۰۰ ردیف حدود ۲۰ مگابایت می‌شود که در سقف ۲۵۶ مگابایتی
	 * وردپرس جا می‌شود.
	 */
	const MAX_EXPORT_PAGE_SIZE = 100000;

	/**
	 * محاسبه اندازه صفحه با رعایت سقف.
	 *
	 * @param array<string, mixed> $args آرگومان‌ها.
	 * @return int
	 */
	private static function resolve_page_size( array $args ) {
		if ( isset( $args['per_page'] ) ) {
			$requested = absint( $args['per_page'] );

			return max( 1, min( self::MAX_PAGE_SIZE, $requested ) );
		}

		// صادرات و چاپ: همه ردیف‌ها تا سقف بالا.
		if ( ! empty( $args['for_export'] ) ) {
			return self::MAX_EXPORT_PAGE_SIZE;
		}

		return (int) Sparkknife_Ledger_Settings::get( 'per_page', 20 );
	}


	/**
	 * نمونه یکتا.
	 *
	 * @var Sparkknife_Ledger_Report_Service|null
	 */
	private static $instance = null;

	/**
	 * دریافت نمونه.
	 *
	 * @return Sparkknife_Ledger_Report_Service
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * گزارش فردی یک شخص.
	 *
	 * @param int                  $person_id شناسه شخص.
	 * @param array<string, mixed> $args      فیلترها (range, date_from, date_to, type, status, page, per_page, search).
	 * @return array<string, mixed>|WP_Error
	 */
	public function get_person_report( $person_id, array $args = array() ) {
		$person_id = absint( $person_id );
		$person    = Sparkknife_Ledger_Person_Repository::instance()->find( $person_id, true );

		if ( ! $person ) {
			return new WP_Error( 'sparkknife_ledger_person_not_found', __( 'شخص موردنظر یافت نشد.', 'sparkknife-ledger' ) );
		}

		$range = Sparkknife_Ledger_Ledger_Service::resolve_range( $args );

		$date_args = array(
			'from' => $range['from'],
			'to'   => $range['to'],
		);

		$type   = isset( $args['type'] ) && in_array( $args['type'], array( 'income', 'expense' ), true ) ? $args['type'] : '';
		$status = isset( $args['status'] ) && in_array( $args['status'], array( 'posted', 'void' ), true ) ? $args['status'] : '';

		$per_page = self::resolve_page_size( $args );
		$page     = isset( $args['page'] ) ? max( 1, absint( $args['page'] ) ) : 1;

		$query_args = array(
			'person_id' => $person_id,
			'type'      => $type,
			'status'    => $status,
			'date_from' => $range['from'],
			'date_to'   => $range['to'],
			'search'    => isset( $args['search'] ) ? (string) $args['search'] : '',
			'page'      => $page,
			'per_page'  => $per_page,
			'orderby'   => isset( $args['orderby'] ) ? $args['orderby'] : 'txn_date',
			'order'     => isset( $args['order'] ) ? $args['order'] : 'DESC',
		);

		$transactions = Sparkknife_Ledger_Transaction_Repository::instance()->query( $query_args );

		// خلاصه محاسباتی مستقل از فیلتر نوع/وضعیت (مطابق تعریف مانده).
		$summary = Sparkknife_Ledger_Ledger_Service::instance()->get_person_summary( $person_id, $date_args );

		/*
		 * جمع تراکنش‌های همان ردیف‌هایی که پرس‌وجوی بالا برگرداند.
		 *
		 * نکته مهم: اگر کاربر وضعیتی انتخاب نکرده باشد، `query()` هیچ
		 * فیلتری روی وضعیت نمی‌گذارد و ردیف‌های باطل‌شده را هم نشان
		 * می‌دهد. پیش‌تر اینجا `'' === $status ? 'active_only' : $status`
		 * بود، یعنی جمع فقط تراکنش‌های معتبر را می‌گرفت. نتیجه: برای یک
		 * فیلتر یکسان، جدول ۵ ردیف نشان می‌داد ولی جمع پایین صفحه می‌گفت
		 * ۴؛ و هر مصرف‌کننده‌ای (REST، افزونه دیگر، صادرات) دو عدد
		 * ناسازگار می‌گرفت.
		 */
		$filtered_sums = Sparkknife_Ledger_Transaction_Repository::instance()->sum_by_type(
			array(
				'person_id' => $person_id,
				'type'      => $type,
				'status'    => $status,
				'date_from' => $range['from'],
				'date_to'   => $range['to'],
				'search'    => isset( $args['search'] ) ? (string) $args['search'] : '',
			)
		);

		return array(
			'person'       => $person,
			'range'        => $range,
			'filters'      => array(
				'type'   => $type,
				'status' => $status,
				'search' => isset( $args['search'] ) ? (string) $args['search'] : '',
			),
			'transactions' => $transactions,
			'summary'      => $summary,
			'filtered'     => array(
				'income'            => $filtered_sums['income'],
				'expense'           => $filtered_sums['expense'],
				'count'             => $filtered_sums['count'],
				'income_formatted'  => Sparkknife_Ledger_Money::format( $filtered_sums['income'] ),
				'expense_formatted' => Sparkknife_Ledger_Money::format( $filtered_sums['expense'] ),
				'balance_formatted' => Sparkknife_Ledger_Money::format( Sparkknife_Ledger_Amount::sub( $filtered_sums['income'], $filtered_sums['expense'] ) ),
				'balance'           => Sparkknife_Ledger_Amount::sub( $filtered_sums['income'], $filtered_sums['expense'] ),
			),
		);
	}

	/**
	 * گزارش تجمیعی همه اشخاص.
	 *
	 * @param array<string, mixed> $args فیلترها (range, date_from, date_to, person_id, type, status, page, per_page, search, orderby, order).
	 * @return array<string, mixed>
	 */
	public function get_summary_report( array $args = array() ) {
		$range = Sparkknife_Ledger_Ledger_Service::resolve_range( $args );

		$date_args = array(
			'from' => $range['from'],
			'to'   => $range['to'],
		);

		$person_filter = isset( $args['person_id'] ) ? absint( $args['person_id'] ) : 0;

		$type   = isset( $args['type'] ) && in_array( $args['type'], array( 'income', 'expense' ), true ) ? $args['type'] : '';
		$status = isset( $args['status'] ) && in_array( $args['status'], array( 'posted', 'void' ), true ) ? $args['status'] : '';

		// فهرست اشخاص (بر اساس فیلتر).
		if ( $person_filter ) {
			$person = Sparkknife_Ledger_Person_Repository::instance()->find( $person_filter );
			$persons = $person ? array( $person ) : array();
		} else {
			/*
			 * اشخاص غیرحذف‌شده به‌علاوهٔ آن‌هایی که **تراکنش دارند**.
			 *
			 * شخص حذف‌شده معمولاً تراکنش ندارد (سرویس حذف نمی‌گذارد)، ولی
			 * اگر داده دستی تغییر کند یا حذف نرم از راه دیگری انجام شود،
			 * تراکنش‌هایش در جدول می‌مانند. با فهرست کردن فقط اشخاص
			 * غیرحذف‌شده، آن مبالغ از گزارش جمعی ناپدید می‌شدند — یعنی
			 * گزارش مالی دروغ می‌گفت که هیچ‌چیز آن‌ها را نشان نمی‌داد.
			 *
			 * نگه داشتنشان به‌صورت ردیف (با برچسب «حذف‌شده») هم داده را
			 * حفظ می‌کند و هم جمع ردیف‌ها را با جمع کل یکی نگه می‌دارد.
			 */
			$persons = Sparkknife_Ledger_Person_Repository::instance()->get_all_with_transactions();
		}

		$ids      = array_map( 'intval', wp_list_pluck( $persons, 'id' ) );
		$balances = Sparkknife_Ledger_Ledger_Service::instance()->get_balances_bulk( $ids, $date_args );

		$rows = array();

		foreach ( $persons as $person ) {
			$person_id = (int) $person->id;
			$summary   = isset( $balances[ $person_id ] ) ? $balances[ $person_id ] : array(
				'income'         => '0',
				'expense'        => '0',
				'balance'        => '0',
				'count'          => 0,
				'account_status' => 'settled',
			);

			$rows[] = array(
				'id'               => $person_id,
				'name'             => $person->name,
				'phone'            => $person->phone,
				'status'           => $person->status,
				'deleted_at'       => isset( $person->deleted_at ) ? $person->deleted_at : null,
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

		/*
		 * جستجو روی نام و شماره تلفن.
		 *
		 * برای هر دو طرف مقایسه از `normalize_persian()` استفاده می‌شود تا
		 * «علي» و «علی» یکی شمرده شوند؛ وگرنه کسی که نامش را با «ي» عربی
		 * تایپ کرده بود در این گزارش پیدا نمی‌شد، در حالی که همان جستجو در
		 * فهرست اشخاص نتیجه می‌داد.
		 *
		 * `mb_stripos` اگر mbstring نصب نباشد باعث خطای مرگبار می‌شود؛
		 * `stripos` جایگزین امن آن است (نام‌ها پس از یکسان‌سازی فارسی،
		 * تفاوت بزرگی/کوچکی حروف لاتین را فقط در بخش انگلیسی نام دارند).
		 */
		$search = isset( $args['search'] ) ? trim( (string) $args['search'] ) : '';
		if ( '' !== $search ) {
			$needle = Sparkknife_Ledger_Sanitizer::normalize_persian( $search );

			$rows = array_values(
				array_filter(
					$rows,
					function ( $row ) use ( $needle ) {
						$name  = Sparkknife_Ledger_Sanitizer::normalize_persian( $row['name'] );
						$phone = Sparkknife_Ledger_Sanitizer::normalize_persian( (string) $row['phone'] );

						return false !== stripos( $name, $needle ) || false !== strpos( $phone, $needle );
					}
				)
			);
		}

		// فقط اشخاص دارای تراکنش.
		if ( ! empty( $args['with_transactions_only'] ) ) {
			$rows = array_values(
				array_filter(
					$rows,
					function ( $row ) {
						return $row['count'] > 0;
					}
				)
			);
		}

		// مرتب‌سازی.
		$orderby = isset( $args['orderby'] ) ? sanitize_key( (string) $args['orderby'] ) : 'balance';
		$order   = isset( $args['order'] ) && 'asc' === strtolower( (string) $args['order'] ) ? 'asc' : 'desc';

		$sortable = array( 'name', 'income', 'expense', 'balance', 'count' );

		if ( in_array( $orderby, $sortable, true ) ) {
			usort(
				$rows,
				function ( $a, $b ) use ( $orderby, $order ) {
					if ( 'name' === $orderby ) {
						/*
						 * strcmp ترتیب بایتی UTF-8 می‌دهد که برای فارسی
						 * نادرست است («پ» بعد از «د» می‌آید). کلید
						 * مرتب‌سازی الفبایی فارسی استفاده می‌شود.
						 */
						$result = strcmp(
							Sparkknife_Ledger_Sanitizer::persian_sort_key( $a['name'] ),
							Sparkknife_Ledger_Sanitizer::persian_sort_key( $b['name'] )
						);
					} elseif ( 'count' === $orderby ) {
						$result = (int) $a['count'] <=> (int) $b['count'];
					} else {
						$result = Sparkknife_Ledger_Amount::cmp( $a[ $orderby ], $b[ $orderby ] );
					}

					$result = 'asc' === $order ? $result : -$result;

					/*
					 * کلید دوم برای شکستن تساوی.
					 *
					 * usort در PHP پیش از ۸.۰ پایدار نیست؛ بدون این کلید،
					 * ردیف‌هایی با مانده یکسان می‌توانستند بین صفحه‌ها
					 * جابه‌جا شوند یا در دو صفحه تکرار شوند.
					 */
					if ( 0 === $result ) {
						$result = (int) $a['id'] <=> (int) $b['id'];
					}

					return $result;
				}
			);
		}

		// صفحه‌بندی.
		$per_page = self::resolve_page_size( $args );
		$page     = isset( $args['page'] ) ? max( 1, absint( $args['page'] ) ) : 1;
		$total    = count( $rows );

		/*
		 * سقف صفحه پیش از ضرب.
		 *
		 * پیش‌تر `( $page - 1 ) * $per_page` بدون سقف حساب می‌شد. با
		 * `page` بزرگ‌تر از حدود ۴٫۶×۱۰^۱۶ حاصل‌ضرب از بازهٔ `int`
		 * بیرون می‌زد، PHP بی‌صدا آن را به `float` تبدیل می‌کرد و
		 * `array_slice()` با آفست غیرصحیح، **کل ردیف‌ها** را از ابتدا
		 * برمی‌گرداند. یعنی صفحهٔ ۹۲۲۳۳۷۲۰۳۶۸۵۴۷۷۵۸۰۷ همان صفحهٔ اول
		 * می‌شد، بدون هیچ خطا.
		 */
		$max_page = (int) ceil( $total / $per_page );
		$max_page = max( 1, min( $max_page, 1000000 ) );

		if ( $page > $max_page ) {
			$page = $max_page;
		}

		$paged_rows = array_slice( $rows, ( $page - 1 ) * $per_page, $per_page );

		/*
		 * جمع کل در بازه.
		 *
		 * این عدد عمداً مستقل از فیلتر جستجو است تا کاربر جمع «همه» را هم
		 * ببیند. اما نباید مستقل از **مجموعهٔ اشخاص نمایش‌داده‌شده** باشد:
		 * `$persons` از `get_all()` می‌آید که فقط اشخاص حذف‌نشده را برمی‌گرداند،
		 * در حالی که `sum_by_type()` روی کل جدول تراکنش‌ها جمع می‌زند.
		 *
		 * نتیجهٔ آن تناقض، یک جمع کل نادرست نبود؛ یک **ردیف گم‌شده** بود:
		 * اگر شخصی تراکنش داشت و بعد (مثلاً از phpMyAdmin یا با فیلتر
		 * دستی) حذف نرم می‌شد، مبلغش در «مجموع درآمد» می‌آمد ولی هیچ
		 * ردیفی آن را توضیح نمی‌داد — اختلافی که در هیچ ستونی دیده
		 * نمی‌شد و کاربر آن را باگ حسابداری می‌پنداشت.
		 *
		 * حالا وقتی هیچ فیلتر شخصی نیست، شناسه‌های همان مجموعه‌ای که
		 * ردیف‌ها را ساخته به جمع هم داده می‌شود تا هر دو یک مجموعه را
		 * ببینند.
		 */
		$totals_args = array(
			'type'      => $type,
			'status'    => '' === $status ? 'active_only' : $status,
			'date_from' => $range['from'],
			'date_to'   => $range['to'],
			'person_id' => $person_filter,
			'search'    => '',
		);

		if ( ! $person_filter ) {
			$totals_args['person_ids'] = array_map( 'intval', wp_list_pluck( $persons, 'id' ) );
		}

		$totals = Sparkknife_Ledger_Transaction_Repository::instance()->sum_by_type( $totals_args );

		$income  = $totals['income'];
		$expense = $totals['expense'];
		$balance = Sparkknife_Ledger_Amount::sub( $income, $expense );

		$comparison = array(
			'positive' => 0,
			'negative' => 0,
			'settled'  => 0,
		);

		foreach ( $rows as $row ) {
			$comparison[ $row['account_status'] ] = isset( $comparison[ $row['account_status'] ] ) ? $comparison[ $row['account_status'] ] + 1 : 1;
		}

		/*
		 * جمع ردیف‌های فیلترشده.
		 *
		 * `totals` عمداً مستقل از جستجو و از کل بازه حساب می‌شود و برچسبش
		 * هم «مجموع ... تمام اشخاص» است. اما همان عدد در `<tfoot>` جدول
		 * هم استفاده می‌شد، یعنی زیر ستون‌هایی که فقط ردیف‌های فیلترشده
		 * را نشان می‌دادند. با جستجویی که یک ردیف هزار تومانی داشت،
		 * «جمع کل» زیر جدول ۱۰٬۰۰۰ نشان می‌داد.
		 *
		 * `filtered_totals` جمع همان ردیف‌هایی است که واقعاً روی صفحه‌اند.
		 */
		$filtered_income  = '0';
		$filtered_expense = '0';

		foreach ( $rows as $row ) {
			$filtered_income  = Sparkknife_Ledger_Amount::add( $filtered_income, $row['income'] );
			$filtered_expense = Sparkknife_Ledger_Amount::add( $filtered_expense, $row['expense'] );
		}

		$filtered_balance = Sparkknife_Ledger_Amount::sub( $filtered_income, $filtered_expense );

		return array(
			'range'      => $range,
			'filters'    => array(
				'person_id' => $person_filter,
				'type'      => $type,
				'status'    => $status,
				'search'    => $search,
				'orderby'   => $orderby,
				'order'     => $order,
			),
			'rows'       => $paged_rows,
			'all_rows'   => $rows,
			'pagination' => array(
				'total'    => $total,
				'pages'    => (int) ceil( $total / $per_page ),
				'page'     => $page,
				'per_page' => $per_page,
			),
			'totals'     => array(
				'income'            => $income,
				'expense'           => $expense,
				'balance'           => $balance,
				'count'             => $totals['count'],
				'income_formatted'  => Sparkknife_Ledger_Money::format( $income ),
				'expense_formatted' => Sparkknife_Ledger_Money::format( $expense ),
				'balance_formatted' => Sparkknife_Ledger_Money::format( $balance ),
			),
			'filtered_totals' => array(
				'income'            => $filtered_income,
				'expense'           => $filtered_expense,
				'balance'           => $filtered_balance,
				'count'             => count( $rows ),
				'income_formatted'  => Sparkknife_Ledger_Money::format( $filtered_income ),
				'expense_formatted' => Sparkknife_Ledger_Money::format( $filtered_expense ),
				'balance_formatted' => Sparkknife_Ledger_Money::format( $filtered_balance ),
			),
			'comparison' => $comparison,
			'persons'    => Sparkknife_Ledger_Person_Repository::instance()->get_all(),
		);
	}

	/**
	 * نمودار ماهانه برای داشبورد (با بازه دلخواه).
	 *
	 * @param int $months تعداد ماه.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_monthly_chart( $months = 6 ) {
		$data = Sparkknife_Ledger_Transaction_Repository::instance()->monthly_totals( $months );

		foreach ( $data as $index => $row ) {
			/*
			 * برچسب را مخزن ساخته است و از قبل با تقویم فعال هم‌خوان است.
			 * پیش‌تر اینجا `Date::format( $row['key'] . '-01' )` صدا زده
			 * می‌شد که کلید ماه میلادی را به شمسی تبدیل می‌کرد و برچسب
			 * وسط ماه می‌افتاد («۱۴۰۵/۰۸/۱۰» به‌جای «آبان ۱۴۰۵»).
			 */
			$data[ $index ]['income_formatted']  = Sparkknife_Ledger_Money::format( $row['income'] );
			$data[ $index ]['expense_formatted'] = Sparkknife_Ledger_Money::format( $row['expense'] );
			$data[ $index ]['balance']           = Sparkknife_Ledger_Amount::sub( $row['income'], $row['expense'] );
			$data[ $index ]['balance_formatted'] = Sparkknife_Ledger_Money::format( $data[ $index ]['balance'] );
		}

		return $data;
	}
}
