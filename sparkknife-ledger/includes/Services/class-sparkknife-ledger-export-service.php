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

		if ( preg_match( '/^[=\+\-@\t\r]/', $value ) ) {
			return "'" . $value;
		}

		return $value;
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
		$person = $report['person'];
		$range  = $report['range'];
		$rows   = array();

		$rows[] = array( __( 'گزارش فردی حساب', 'sparkknife-ledger' ) );
		$rows[] = array( __( 'نام شخص', 'sparkknife-ledger' ), $person->name );
		$rows[] = array( __( 'شماره تماس', 'sparkknife-ledger' ), (string) $person->phone );
		$rows[] = array( __( 'وضعیت شخص', 'sparkknife-ledger' ), 'active' === $person->status ? __( 'فعال', 'sparkknife-ledger' ) : __( 'غیرفعال', 'sparkknife-ledger' ) );
		$rows[] = array( __( 'بازه گزارش', 'sparkknife-ledger' ), $this->range_label( $range ) );
		$rows[] = array( __( 'واحد پول', 'sparkknife-ledger' ), Sparkknife_Ledger_Money::currency_label() );
		$rows[] = array( __( 'تاریخ تهیه', 'sparkknife-ledger' ), Sparkknife_Ledger_Date::format( Sparkknife_Ledger_Date::now(), true ) );
		$rows[] = array(
			__( 'تهیه‌کننده', 'sparkknife-ledger' ),
			wp_get_current_user()->display_name,
		);
		$rows[] = array();

		$summary = $report['summary'];

		$rows[] = array( __( 'خلاصه مالی (تراکنش‌های معتبر)', 'sparkknife-ledger' ) );
		$rows[] = array( __( 'مجموع ورودی‌ها', 'sparkknife-ledger' ), Sparkknife_Ledger_Money::format( $summary['income'] ) );
		$rows[] = array( __( 'مجموع خروجی‌ها', 'sparkknife-ledger' ), Sparkknife_Ledger_Money::format( $summary['expense'] ) );
		$rows[] = array( __( 'مانده نهایی', 'sparkknife-ledger' ), Sparkknife_Ledger_Money::format( $summary['balance'] ) );
		$rows[] = array( __( 'وضعیت حساب', 'sparkknife-ledger' ), Sparkknife_Ledger_Ledger_Service::status_label( $summary['account_status'] ) );
		$rows[] = array( __( 'تعداد تراکنش‌های معتبر', 'sparkknife-ledger' ), (string) $summary['count'] );
		$rows[] = array( __( 'تعداد تراکنش‌های باطل‌شده', 'sparkknife-ledger' ), (string) $summary['void_count'] );
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

		foreach ( $report['transactions']['items'] as $transaction ) {
			$user       = $transaction->created_by ? get_userdata( (int) $transaction->created_by ) : null;
			$rows[]     = array(
				$transaction->reference,
				Sparkknife_Ledger_Date::format( $transaction->txn_date ),
				Sparkknife_Ledger_Money::transaction_type_label( $transaction->type ),
				Sparkknife_Ledger_Money::format( $transaction->amount ),
				Sparkknife_Ledger_Transaction_Service::status_label( $transaction->status ),
				(string) $transaction->description,
				$user ? $user->display_name : __( 'نامشخص', 'sparkknife-ledger' ),
				Sparkknife_Ledger_Date::format( $transaction->created_at, true ),
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
		$rows[] = array( __( 'تهیه‌کننده', 'sparkknife-ledger' ), wp_get_current_user()->display_name );
		$rows[] = array();

		$rows[] = array( __( 'مجموع ورودی‌ها', 'sparkknife-ledger' ), Sparkknife_Ledger_Money::format( $report['totals']['income'] ) );
		$rows[] = array( __( 'مجموع خروجی‌ها', 'sparkknife-ledger' ), Sparkknife_Ledger_Money::format( $report['totals']['expense'] ) );
		$rows[] = array( __( 'مانده کل', 'sparkknife-ledger' ), Sparkknife_Ledger_Money::format( $report['totals']['balance'] ) );
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
				Sparkknife_Ledger_Money::format( $row['income'] ),
				Sparkknife_Ledger_Money::format( $row['expense'] ),
				Sparkknife_Ledger_Money::format( $row['balance'] ),
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

		$from = ! empty( $range['from'] ) ? Sparkknife_Ledger_Date::format( $range['from'] ) : '—';
		$to   = ! empty( $range['to'] ) ? Sparkknife_Ledger_Date::format( $range['to'] ) : '—';

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
