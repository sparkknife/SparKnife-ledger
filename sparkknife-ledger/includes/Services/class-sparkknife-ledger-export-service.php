<?php
/**
 * سرویس خروجی گزارش‌ها (CSV سازگار با Excel، چاپ و PDF).
 *
 * @package SparKnife_Ledger
 */

defined( 'ABSPATH' ) || exit;

/**
 * تولید خروجی‌های گزارش.
 *
 * فرمت‌های افزودنی: با فیلتر sparkknife_ledger_export_formats می‌توان فرمت
 * جدید (مثلاً xlsx) ثبت کرد؛ رندر هر فرمت از طریق هوک
 * sparkknife_ledger_export_render_{format} انجام می‌شود.
 */
class Sparkknife_Ledger_Export_Service {

	/**
	 * خواندن ایمن یک کلید از خلاصه مالی.
	 *
	 * @param array<string, mixed> $summary خلاصه.
	 * @param string               $key     کلید.
	 * @return string|int مقدار یا صفر.
	 */
	private function summary_value( array $summary, $key ) {
		if ( ! isset( $summary[ $key ] ) || is_object( $summary[ $key ] ) ) {
			return ( 'account_status' === $key ) ? 'settled' : '0';
		}

		return $summary[ $key ];
	}

	/**
	 * نمونه یکتا.
	 *
	 * @var Sparkknife_Ledger_Export_Service|null
	 */
	private static $instance = null;

	/**
	 * دریافت نمونه.
	 *
	 * @return Sparkknife_Ledger_Export_Service
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * فرمت‌های خروجی پشتیبانی‌شده.
	 *
	 * @return array<string, array<string, string>>
	 */
	public static function formats() {
		$formats = array(
			'csv'   => array(
				'label'       => __( 'CSV / Excel', 'sparkknife-ledger' ),
				'description' => __( 'فایل متنی سازگار با Excel (با BOM برای نمایش صحیح فارسی)', 'sparkknife-ledger' ),
				'capability'  => 'export_reports',
			),
			'print' => array(
				'label'       => __( 'چاپ', 'sparkknife-ledger' ),
				'description' => __( 'نمای چاپ رسمی با پشتیبانی کامل RTL', 'sparkknife-ledger' ),
				'capability'  => 'export_reports',
			),
			'pdf'   => array(
				'label'       => __( 'PDF', 'sparkknife-ledger' ),
				'description' => __( 'ذخیره گزارش به‌صورت PDF از پنجره چاپ مرورگر', 'sparkknife-ledger' ),
				'capability'  => 'export_reports',
			),
		);

		/**
		 * ثبت فرمت‌های خروجی جدید.
		 *
		 * @param array<string, array<string, string>> $formats فرمت‌ها.
		 */
		return apply_filters( 'sparkknife_ledger_export_formats', $formats );
	}

		/**
	 * مبلغ برای فایل CSV: عدد خام، بدون واحد پول.
	 *
	 * `Money::format()` برای نمایش روی صفحه ساخته شده: ارقام فارسی می‌کند
	 * و برچسب واحد پول می‌چسباند («۱,۰۰۰ تومان»). در Excel این یک رشته
	 * متنی است، نه عدد، پس کاربر نمی‌تواند جمع بزند، فیلتر کند یا نمودار
	 * بکشد — و برای فایل صادراتی حسابداری این یعنی فایل عملاً بی‌مصرف.
	 *
	 * اینجا مقدار با ارقام لاتین نوشته می‌شود تا Excel آن را عدد بشناسد.
	 * واحد پول در ردیف جداگانه سرصفحه می‌آید.
	 *
	 * @param string $scaled مبلغ مقیاس‌شده.
	 * @return string
	 */
	/**
	 * تاریخ برای فایل CSV: قالب ISO با ارقام لاتین.
	 *
	 * `Date::format()` تاریخ شمسی با ارقام فارسی می‌دهد («۱۴۰۳/۰۵/۱۰»).
	 * Excel آن را تاریخ نمی‌شناسد، پس نمی‌شود مرتب کرد یا فیلتر بازه‌ای
	 * زد. همان مشکلی که در ستون مبلغ بود، اینجا هم هست.
	 *
	 * تاریخ ذخیره‌شده (میلادی ISO) برای ماشین از هر چیزی بهتر است: Excel
	 * و هر ابزار دیگری آن را تاریخ می‌شناسد. تاریخ شمسی همچنان در
	 * سرصفحه گزارش با برچسب «بازه گزارش» می‌آید.
	 *
	 * @param string $date      تاریخ Y-m-d یا Y-m-d H:i:s.
	 * @param bool   $with_time نمایش ساعت.
	 * @return string
	 */
	private function csv_date( $date, $with_time = false ) {
		$date = trim( (string) $date );

		if ( '' === $date || 0 === strpos( $date, '0000-00-00' ) ) {
			return '';
		}

		return $with_time && strlen( $date ) >= 19 ? substr( $date, 0, 19 ) : substr( $date, 0, 10 );
	}

	private function csv_amount( $scaled ) {
		return (string) Sparkknife_Ledger_Money::to_human( $scaled );
	}

/**
	 * پاک‌سازی سلول CSV برای جلوگیری از CSV Injection.
	 *
	 * هر مقدار آغازشده با = + - @ یا TAB/RG با یک آپاستروف بی‌خطر می‌شود.
	 *
	 * @param mixed $value مقدار.
	 * @return string
	 */
	public static function sanitize_cell( $value ) {
		$value = (string) $value;

		if ( '' === $value ) {
			return '';
		}

		/*
		 * سلول ممکن است با «پیش‌وند پوشاننده» شروع شود.
		 *
		 * پیش‌تر فقط خودِ `$value` با الگوی `^[=\+\-@\t\r]` سنجیده می‌شد و
		 * این کافی نبود: یک نویسهٔ بی‌عرض (Zero-Width Space، RLM، BOM) یا
		 * یک فاصله در ابتدای سلول، `^` را به شکست می‌کشاند، در حالی که
		 * Excel و LibreOffice آن پیش‌وندها را نادیده می‌گیرند و رشتهٔ
		 * بعدی را **به‌عنوان فرمول اجرا می‌کنند**.
		 *
		 * نمونهٔ عملی: نام شخصی که با `\u{200B}=cmd|calc` ذخیره شده باشد،
		 * یا توضیحی که با `\n=1+1` شروع شود. `Sanitizer::text()` نویسهٔ
		 * بی‌عرض را حذف نمی‌کند (فقط بررسی می‌کند رشتهٔ مرئی خالی نباشد)،
		 * پس این مقدار تا مرحلهٔ CSV زنده می‌ماند.
		 */
		$probe = preg_replace( '/^[\s\x{200B}-\x{200F}\x{FEFF}\x{00A0}\x{2028}\x{2029}]+/u', '', $value );

		if ( null === $probe ) {
			$probe = $value;
		}

		$unsafe = (bool) preg_match( '/^[=\+\-@]/', $probe );

		// حفظ رفتار قبلی: tab یا CR در ابتدای خودِ سلول هم ناامن است.
		if ( ! $unsafe && preg_match( '/^[=\+\-@\t\r]/', $value ) ) {
			$unsafe = true;
		}

		// آپاستروف باید در **نخستین** بایت سلول بنشیند تا Excel آن را ببیند.
		return $unsafe ? "'" . $value : $value;
	}

	/**
	 * ارسال فایل CSV به مرورگر و پایان درخواست.
	 *
	 * @param array<int, array<int, string>> $rows     ردیف‌ها.
	 * @param string                         $filename نام فایل بدون پسوند.
	 * @return void
	 */
	public function send_csv( array $rows, $filename ) {
		$filename = preg_replace( '/[^A-Za-z0-9_\-\x{0600}-\x{06FF}]/u', '-', $filename );
		$filename = trim( (string) $filename, '-' );
		$filename = $filename ? $filename : 'sparkknife-ledger-report';
		$filename = $filename . '-' . gmdate( 'Y-m-d-His' ) . '.csv';

		if ( ! headers_sent() ) {
			nocache_headers();
			header( 'Content-Type: text/csv; charset=UTF-8' );
			header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
			header( 'X-Content-Type-Options: nosniff' );
		}

		$output = fopen( 'php://output', 'w' );

		if ( false === $output ) {
			return;
		}

		// BOM برای نمایش صحیح UTF-8 در Excel.
		fwrite( $output, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- نوشتن مستقیم به خروجی HTTP.

		foreach ( $rows as $row ) {
			$cells = array_map( array( __CLASS__, 'sanitize_cell' ), (array) $row );
			fputcsv( $output, $cells, ',' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fputcsv -- نوشتن مستقیم به خروجی HTTP.
		}

		fclose( $output ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- بستن جریان خروجی.

		exit;
	}

	/**
	 * ساخت ردیف‌های CSV گزارش فردی.
	 *
	 * @param array<string, mixed> $report نتیجه get_person_report.
	 * @return array<int, array<int, string>>
	 */
	public function person_report_rows( array $report ) {
		/*
		 * ورودی ناقص نباید خطای مرگبار بدهد. پیش‌تر اگر کلیدهای
		 * person/range/summary نبودند، این تابع به TypeError می‌رسید.
		 * حالا در آن حالت فقط سرستون‌ها برگردانده می‌شوند.
		 */
		if ( empty( $report['person'] ) || ! is_object( $report['person'] ) ) {
			return array( array( __( 'گزارش فردی حساب', 'sparkknife-ledger' ) ), array( __( 'داده‌ای برای نمایش نیست.', 'sparkknife-ledger' ) ) );
		}

		$person  = $report['person'];
		$range   = isset( $report['range'] ) && is_array( $report['range'] ) ? $report['range'] : array();
		$summary = isset( $report['summary'] ) && is_array( $report['summary'] ) ? $report['summary'] : array();
		$rows    = array();

		$rows[] = array( __( 'گزارش فردی حساب', 'sparkknife-ledger' ) );
		$rows[] = array( __( 'نام شخص', 'sparkknife-ledger' ), $person->name );
		$rows[] = array( __( 'شماره تماس', 'sparkknife-ledger' ), (string) $person->phone );
		$rows[] = array( __( 'وضعیت شخص', 'sparkknife-ledger' ), 'active' === $person->status ? __( 'فعال', 'sparkknife-ledger' ) : __( 'غیرفعال', 'sparkknife-ledger' ) );
		$rows[] = array( __( 'بازه گزارش', 'sparkknife-ledger' ), $this->range_label( $range ) );
		$rows[] = array( __( 'واحد پول', 'sparkknife-ledger' ), Sparkknife_Ledger_Money::currency_label() );
		$rows[] = array( __( 'تاریخ تهیه', 'sparkknife-ledger' ), Sparkknife_Ledger_Date::format( Sparkknife_Ledger_Date::now(), true ) );
		$rows[] = array( __( 'تاریخ تهیه (ISO)', 'sparkknife-ledger' ), $this->csv_date( Sparkknife_Ledger_Date::now(), true ) );
		$rows[] = array(
			__( 'تهیه‌کننده', 'sparkknife-ledger' ),
			wp_get_current_user()->display_name,
		);
		$rows[] = array();

		$rows[] = array( __( 'خلاصه مالی (تراکنش‌های معتبر)', 'sparkknife-ledger' ) );
		$rows[] = array( __( 'مجموع ورودی‌ها', 'sparkknife-ledger' ), $this->csv_amount( $this->summary_value( $summary, 'income' ) ) );
		$rows[] = array( __( 'مجموع خروجی‌ها', 'sparkknife-ledger' ), $this->csv_amount( $this->summary_value( $summary, 'expense' ) ) );
		$rows[] = array( __( 'مانده نهایی', 'sparkknife-ledger' ), $this->csv_amount( $this->summary_value( $summary, 'balance' ) ) );
		$rows[] = array( __( 'وضعیت حساب', 'sparkknife-ledger' ), Sparkknife_Ledger_Ledger_Service::status_label( (string) $this->summary_value( $summary, 'account_status' ) ) );
		$rows[] = array( __( 'تعداد تراکنش‌های معتبر', 'sparkknife-ledger' ), (string) $this->summary_value( $summary, 'count' ) );
		$rows[] = array( __( 'تعداد تراکنش‌های باطل‌شده', 'sparkknife-ledger' ), (string) $this->summary_value( $summary, 'void_count' ) );
		$rows[] = array();

		$rows[] = array(
			__( 'شناسه تراکنش', 'sparkknife-ledger' ),
			__( 'تاریخ', 'sparkknife-ledger' ),
			__( 'نوع', 'sparkknife-ledger' ),
			__( 'مبلغ', 'sparkknife-ledger' ),
			__( 'وضعیت', 'sparkknife-ledger' ),
			__( 'توضیحات', 'sparkknife-ledger' ),
			__( 'ثبت‌کننده', 'sparkknife-ledger' ),
			__( 'تاریخ ثبت', 'sparkknife-ledger' ),
		);

		$items = isset( $report['transactions']['items'] ) && is_array( $report['transactions']['items'] )
			? $report['transactions']['items']
			: array();

		foreach ( $items as $transaction ) {
			$rows[]     = array(
				$transaction->reference,
				$this->csv_date( $transaction->txn_date ),
				Sparkknife_Ledger_Money::transaction_type_label( $transaction->type ),
				$this->csv_amount( $transaction->amount ),
				Sparkknife_Ledger_Transaction_Service::status_label( $transaction->status ),
				(string) $transaction->description,
				sparkknife_ledger_user_label( $transaction->created_by ),
				$this->csv_date( $transaction->created_at, true ),
			);
		}

		return $rows;
	}

	/**
	 * ساخت ردیف‌های CSV گزارش تجمیعی.
	 *
	 * @param array<string, mixed> $report نتیجه get_summary_report.
	 * @return array<int, array<int, string>>
	 */
	public function summary_report_rows( array $report ) {
		$rows = array();

		$rows[] = array( __( 'گزارش تجمیعی حساب اشخاص', 'sparkknife-ledger' ) );
		$rows[] = array( __( 'بازه گزارش', 'sparkknife-ledger' ), $this->range_label( $report['range'] ) );
		$rows[] = array( __( 'واحد پول', 'sparkknife-ledger' ), Sparkknife_Ledger_Money::currency_label() );
		$rows[] = array( __( 'تاریخ تهیه', 'sparkknife-ledger' ), Sparkknife_Ledger_Date::format( Sparkknife_Ledger_Date::now(), true ) );
		$rows[] = array( __( 'تاریخ تهیه (ISO)', 'sparkknife-ledger' ), $this->csv_date( Sparkknife_Ledger_Date::now(), true ) );
		$rows[] = array( __( 'تهیه‌کننده', 'sparkknife-ledger' ), wp_get_current_user()->display_name );
		$rows[] = array();

		/*
		 * اگر فیلتری (جستجو، نوع یا وضعیت) فعال باشد، ردیف‌های فایل با
		 * جمع کل بازه یکی نیستند. هر دو عدد نوشته می‌شوند تا کاربر
		 * فایلی نداشته باشد که جمع بالایش با جمع ستون‌هایش نمی‌خواند.
		 */
		$has_filter = ! empty( $report['filters']['search'] ) || ! empty( $report['filters']['type'] ) || ! empty( $report['filters']['status'] );

		if ( $has_filter && isset( $report['filtered_totals'] ) ) {
			$filtered = $report['filtered_totals'];

			$rows[] = array( __( 'مجموع ورودی‌ها (ردیف‌های این فایل)', 'sparkknife-ledger' ), $this->csv_amount( $filtered['income'] ) );
			$rows[] = array( __( 'مجموع خروجی‌ها (ردیف‌های این فایل)', 'sparkknife-ledger' ), $this->csv_amount( $filtered['expense'] ) );
			$rows[] = array( __( 'مانده (ردیف‌های این فایل)', 'sparkknife-ledger' ), $this->csv_amount( $filtered['balance'] ) );
			$rows[] = array();

			$rows[] = array( __( 'مجموع ورودی‌ها (کل بازه، بدون فیلتر)', 'sparkknife-ledger' ), $this->csv_amount( $report['totals']['income'] ) );
			$rows[] = array( __( 'مجموع خروجی‌ها (کل بازه، بدون فیلتر)', 'sparkknife-ledger' ), $this->csv_amount( $report['totals']['expense'] ) );
			$rows[] = array( __( 'مانده کل (بدون فیلتر)', 'sparkknife-ledger' ), $this->csv_amount( $report['totals']['balance'] ) );
		} else {
			$rows[] = array( __( 'مجموع ورودی‌ها', 'sparkknife-ledger' ), $this->csv_amount( $report['totals']['income'] ) );
			$rows[] = array( __( 'مجموع خروجی‌ها', 'sparkknife-ledger' ), $this->csv_amount( $report['totals']['expense'] ) );
			$rows[] = array( __( 'مانده کل', 'sparkknife-ledger' ), $this->csv_amount( $report['totals']['balance'] ) );
		}

		$rows[] = array();

		$rows[] = array(
			__( 'ردیف', 'sparkknife-ledger' ),
			__( 'نام شخص', 'sparkknife-ledger' ),
			__( 'شماره تماس', 'sparkknife-ledger' ),
			__( 'مجموع ورودی', 'sparkknife-ledger' ),
			__( 'مجموع خروجی', 'sparkknife-ledger' ),
			__( 'مانده', 'sparkknife-ledger' ),
			__( 'وضعیت حساب', 'sparkknife-ledger' ),
			__( 'تعداد تراکنش', 'sparkknife-ledger' ),
		);

		$index = 1;
		foreach ( $report['all_rows'] as $row ) {
			$rows[] = array(
				(string) $index,
				$row['name'],
				(string) $row['phone'],
				$this->csv_amount( $row['income'] ),
				$this->csv_amount( $row['expense'] ),
				$this->csv_amount( $row['balance'] ),
				Sparkknife_Ledger_Ledger_Service::status_label( $row['account_status'] ),
				(string) $row['count'],
			);
			$index++;
		}

		return $rows;
	}

	/**
	 * برچسب خوانای بازه.
	 *
	 * @param array<string, mixed> $range بازه.
	 * @return string
	 */
	public function range_label( array $range ) {
		if ( empty( $range['from'] ) && empty( $range['to'] ) ) {
			return __( 'همه زمان‌ها', 'sparkknife-ledger' );
		}

		$from = ! empty( $range['from'] ) ? $this->csv_date( $range['from'] ) : '';
		$to   = ! empty( $range['to'] ) ? $this->csv_date( $range['to'] ) : '';

		return sprintf(
			/* translators: 1: from date, 2: to date */
			__( 'از %1$s تا %2$s', 'sparkknife-ledger' ),
			$from,
			$to
		);
	}

	/**
	 * ثبت رویداد خروجی در تاریخچه.
	 *
	 * @param string $type   person|summary.
	 * @param int    $object شناسه شخص (در گزارش تجمیعی صفر).
	 * @return void
	 */
	public function log_export( $type, $object = 0 ) {
		Sparkknife_Ledger_Audit_Service::instance()->log(
			'person' === $type ? 'person' : 'settings',
			absint( $object ),
			'export',
			array(
				'report' => $type,
				'format' => 'csv',
			),
			'person' === $type
				? __( 'خروجی گرفته‌شده از گزارش فردی', 'sparkknife-ledger' )
				: __( 'خروجی گرفته‌شده از گزارش تجمیعی', 'sparkknife-ledger' )
		);
	}
}
