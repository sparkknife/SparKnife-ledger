<?php
/**
 * سرویس نمایش چاپ و PDF گزارش‌ها.
 *
 * چاپ از طریق پنجره چاپ مرورگر انجام می‌شود؛ این روش برای متون فارسی و
 * RTL بدون نیاز به فونت یا کتابخانه PDF سمت سرور دقیق و قابل اعتماد است.
 * برای تولید PDF سرورساید، هوک sparkknife_ledger_render_pdf در دسترس است.
 *
 * @package SparKnife_Ledger
 */

defined( 'ABSPATH' ) || exit;

/**
 * رندر صفحه چاپ.
 */
class Sparkknife_Ledger_Print_Service {

	/**
	 * نمونه یکتا.
	 *
	 * @var Sparkknife_Ledger_Print_Service|null
	 */
	private static $instance = null;

	/**
	 * دریافت نمونه.
	 *
	 * @return Sparkknife_Ledger_Print_Service
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * چاپ کامل صفحه (فقط برای requests مجاز).
	 *
	 * @param string $html عنوان و بدنه HTML آماده.
	 * @return void
	 */
	public function render_document( $html ) {
		if ( ! headers_sent() ) {
			nocache_headers();
			header( 'Content-Type: text/html; charset=UTF-8' );
			header( 'X-Content-Type-Options: nosniff' );
		}

		$title = sprintf(
			/* translators: 1: plugin name, 2: date */
			__( 'گزارش %1$s — %2$s', 'sparkknife-ledger' ),
			'SparKnife Ledger',
			Sparkknife_Ledger_Date::format( Sparkknife_Ledger_Date::now(), true )
		);

		$css_url = SPARKKNIFE_LEDGER_URL . 'assets/css/print.css?ver=' . SPARKKNIFE_LEDGER_VERSION;

		?>
<!DOCTYPE html>
<html <?php language_attributes(); ?> dir="rtl">
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<meta name="robots" content="noindex, nofollow" />
	<title><?php echo esc_html( $title ); ?></title>
	<link rel="stylesheet" href="<?php echo esc_url( $css_url ); ?>" />
</head>
<body class="spk-print">
	<div class="spk-print__toolbar no-print">
		<button type="button" class="spk-print__button" onclick="window.print();">
			<?php esc_html_e( 'چاپ / ذخیره به PDF', 'sparkknife-ledger' ); ?>
		</button>
		<span class="spk-print__hint"><?php esc_html_e( 'برای ذخیره به‌صورت PDF، در پنجره چاپ گزینه «Save as PDF» را انتخاب کنید.', 'sparkknife-ledger' ); ?></span>
	</div>
	<main class="spk-print__page">
		<?php echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML در لایه سرویس با escape ساخته شده است. ?>
	</main>
	<script>
		window.addEventListener( 'load', function () {
			if ( ! window.location.search.includes( 'noauto' ) ) {
				window.setTimeout( function () { window.print(); }, 350 );
			}
		} );
	</script>
</body>
</html>
		<?php
		exit;
	}

	/**
	 * ساخت HTML گزارش فردی برای چاپ.
	 *
	 * @param array<string, mixed> $report نتیجه گزارش.
	 * @return string
	 */
	public function person_report_html( array $report ) {
		$person  = $report['person'];
		$summary = $report['summary'];
		$range   = $report['range'];

		ob_start();
		?>
		<header class="spk-print__header">
			<div>
				<h1><?php esc_html_e( 'گزارش فردی حساب', 'sparkknife-ledger' ); ?></h1>
				<p class="spk-print__subtitle"><?php echo esc_html( $person->name ); ?></p>
			</div>
			<div class="spk-print__meta">
				<span><?php echo esc_html( Sparkknife_Ledger_Money::currency_label() ); ?></span>
				<span><?php echo esc_html( Sparkknife_Ledger_Print_Service::instance()->range_text( $range ) ); ?></span>
			</div>
		</header>

		<section class="spk-print__summary">
			<div><span><?php esc_html_e( 'مجموع ورودی‌ها', 'sparkknife-ledger' ); ?></span><strong><?php echo esc_html( $summary['income_formatted'] ); ?></strong></div>
			<div><span><?php esc_html_e( 'مجموع خروجی‌ها', 'sparkknife-ledger' ); ?></span><strong><?php echo esc_html( $summary['expense_formatted'] ); ?></strong></div>
			<div><span><?php esc_html_e( 'مانده نهایی', 'sparkknife-ledger' ); ?></span><strong><?php echo esc_html( $summary['balance_formatted'] ); ?></strong></div>
			<div><span><?php esc_html_e( 'وضعیت حساب', 'sparkknife-ledger' ); ?></span><strong><?php echo esc_html( Sparkknife_Ledger_Ledger_Service::status_label( $summary['account_status'] ) ); ?></strong></div>
		</section>

		<table class="spk-print__table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'شناسه', 'sparkknife-ledger' ); ?></th>
					<th><?php esc_html_e( 'تاریخ', 'sparkknife-ledger' ); ?></th>
					<th><?php esc_html_e( 'نوع', 'sparkknife-ledger' ); ?></th>
					<th><?php esc_html_e( 'مبلغ', 'sparkknife-ledger' ); ?></th>
					<th><?php esc_html_e( 'وضعیت', 'sparkknife-ledger' ); ?></th>
					<th><?php esc_html_e( 'توضیحات', 'sparkknife-ledger' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $report['transactions']['items'] ) ) : ?>
					<tr><td colspan="6"><?php esc_html_e( 'تراکنشی در این بازه وجود ندارد.', 'sparkknife-ledger' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $report['transactions']['items'] as $transaction ) : ?>
						<tr class="<?php echo 'void' === $transaction->status ? 'is-void' : ''; ?>">
							<td><span dir="ltr"><?php echo esc_html( $transaction->reference ); ?></span></td>
							<td><?php echo esc_html( Sparkknife_Ledger_Date::format( $transaction->txn_date ) ); ?></td>
							<td><?php echo esc_html( Sparkknife_Ledger_Money::transaction_type_label( $transaction->type ) ); ?></td>
							<td><?php echo esc_html( Sparkknife_Ledger_Money::format( $transaction->amount ) ); ?></td>
							<td><?php echo esc_html( Sparkknife_Ledger_Transaction_Service::status_label( $transaction->status ) ); ?></td>
							<td><?php echo esc_html( (string) $transaction->description ); ?></td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>

		<?php echo $this->footer_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escape در سازنده انجام شده. ?>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * ساخت HTML گزارش تجمیعی برای چاپ.
	 *
	 * @param array<string, mixed> $report نتیجه گزارش.
	 * @return string
	 */
	public function summary_report_html( array $report ) {
		$totals = $report['totals'];
		$range  = $report['range'];

		ob_start();
		?>
		<header class="spk-print__header">
			<div>
				<h1><?php esc_html_e( 'گزارش تجمیعی حساب اشخاص', 'sparkknife-ledger' ); ?></h1>
				<p class="spk-print__subtitle"><?php echo esc_html( Sparkknife_Ledger_Print_Service::instance()->range_text( $range ) ); ?></p>
			</div>
			<div class="spk-print__meta">
				<span><?php echo esc_html( Sparkknife_Ledger_Money::currency_label() ); ?></span>
			</div>
		</header>

		<section class="spk-print__summary">
			<div><span><?php esc_html_e( 'مجموع ورودی‌ها', 'sparkknife-ledger' ); ?></span><strong><?php echo esc_html( $totals['income_formatted'] ); ?></strong></div>
			<div><span><?php esc_html_e( 'مجموع خروجی‌ها', 'sparkknife-ledger' ); ?></span><strong><?php echo esc_html( $totals['expense_formatted'] ); ?></strong></div>
			<div><span><?php esc_html_e( 'مانده کل', 'sparkknife-ledger' ); ?></span><strong><?php echo esc_html( $totals['balance_formatted'] ); ?></strong></div>
			<div><span><?php esc_html_e( 'تعداد تراکنش', 'sparkknife-ledger' ); ?></span><strong><?php echo esc_html( Sparkknife_Ledger_Money::to_persian_digits( (string) $totals['count'] ) ); ?></strong></div>
		</section>

		<table class="spk-print__table">
			<thead>
				<tr>
					<th>#</th>
					<th><?php esc_html_e( 'نام شخص', 'sparkknife-ledger' ); ?></th>
					<th><?php esc_html_e( 'شماره تماس', 'sparkknife-ledger' ); ?></th>
					<th><?php esc_html_e( 'مجموع ورودی', 'sparkknife-ledger' ); ?></th>
					<th><?php esc_html_e( 'مجموع خروجی', 'sparkknife-ledger' ); ?></th>
					<th><?php esc_html_e( 'مانده', 'sparkknife-ledger' ); ?></th>
					<th><?php esc_html_e( 'وضعیت حساب', 'sparkknife-ledger' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $report['all_rows'] ) ) : ?>
					<tr><td colspan="7"><?php esc_html_e( 'داده‌ای برای نمایش وجود ندارد.', 'sparkknife-ledger' ); ?></td></tr>
				<?php else : ?>
					<?php $index = 1; ?>
					<?php foreach ( $report['all_rows'] as $row ) : ?>
						<tr>
							<td><?php echo esc_html( Sparkknife_Ledger_Money::to_persian_digits( (string) $index ) ); ?></td>
							<td><?php echo esc_html( $row['name'] ); ?></td>
							<td dir="ltr"><?php echo esc_html( (string) $row['phone'] ); ?></td>
							<td><?php echo esc_html( Sparkknife_Ledger_Money::format( $row['income'] ) ); ?></td>
							<td><?php echo esc_html( Sparkknife_Ledger_Money::format( $row['expense'] ) ); ?></td>
							<td><?php echo esc_html( Sparkknife_Ledger_Money::format( $row['balance'] ) ); ?></td>
							<td><?php echo esc_html( Sparkknife_Ledger_Ledger_Service::status_label( $row['account_status'] ) ); ?></td>
						</tr>
						<?php $index++; ?>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
			<tfoot>
				<tr>
					<th colspan="3"><?php esc_html_e( 'جمع کل', 'sparkknife-ledger' ); ?></th>
					<th><?php echo esc_html( $totals['income_formatted'] ); ?></th>
					<th><?php echo esc_html( $totals['expense_formatted'] ); ?></th>
					<th><?php echo esc_html( $totals['balance_formatted'] ); ?></th>
					<th></th>
				</tr>
			</tfoot>
		</table>

		<?php echo $this->footer_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * پاصفحه چاپ.
	 *
	 * @return string
	 */
	private function footer_html() {
		$user = wp_get_current_user();

		return sprintf(
			'<footer class="spk-print__footer"><span>%s</span><span>%s</span></footer>',
			esc_html(
				sprintf(
					/* translators: 1: user name, 2: date */
					__( 'تهیه‌کننده: %1$s — تاریخ: %2$s', 'sparkknife-ledger' ),
					$user->display_name,
					Sparkknife_Ledger_Date::format( Sparkknife_Ledger_Date::now(), true )
				)
			),
			esc_html( Sparkknife_Ledger_Money::currency_label() . ' — SparKnife Ledger' )
		);
	}

	/**
	 * متن خوانای بازه.
	 *
	 * @param array<string, mixed> $range بازه.
	 * @return string
	 */
	public function range_text( array $range ) {
		if ( empty( $range['from'] ) && empty( $range['to'] ) ) {
			return __( 'همه زمان‌ها', 'sparkknife-ledger' );
		}

		return sprintf(
			/* translators: 1: from, 2: to */
			__( 'از %1$s تا %2$s', 'sparkknife-ledger' ),
			! empty( $range['from'] ) ? Sparkknife_Ledger_Date::format( $range['from'] ) : '—',
			! empty( $range['to'] ) ? Sparkknife_Ledger_Date::format( $range['to'] ) : '—'
		);
	}
}
