<?php
/**
 * گزارش تجمیعی.
 *
 * @var array  $report   گزارش.
 * @var array  $args     فیلترها.
 * @var string $base_url نشانی پایه.
 * @var string $current  صفحه جاری.
 *
 * @package SparKnife_Ledger
 */

defined( 'ABSPATH' ) || exit;

$totals     = $report['totals'];
$range      = $report['range'];
$comparison = $report['comparison'];

$header_actions = array();

if ( Sparkknife_Ledger_Capabilities::current_user_can( 'export_reports' ) ) {
	$export_base = array(
		'action'    => 'sparkknife_ledger_export',
		'report'    => 'summary',
		'range'     => $args['range'],
		'date_from' => $args['date_from'],
		'date_to'   => $args['date_to'],
		'person_id' => $args['person_id'] ? $args['person_id'] : '',
		'type'      => $args['type'],
		'orderby'   => $args['orderby'],
		'order'     => $args['order'],
	);

	$header_actions[] = array(
		'label' => __( 'خروجی CSV / Excel', 'sparkknife-ledger' ),
		'url'   => wp_nonce_url( add_query_arg( $export_base, admin_url( 'admin-post.php' ) ), 'sparkknife_ledger_export' ),
		'icon'  => 'dashicons-download',
	);

	$print_url = wp_nonce_url(
		add_query_arg( array_merge( $export_base, array( 'report' => 'summary-print', 'print' => '1' ) ), admin_url( 'admin-post.php' ) ),
		'sparkknife_ledger_print'
	);

	$header_actions[] = array(
		'label'  => __( 'چاپ / PDF', 'sparkknife-ledger' ),
		'url'    => $print_url,
		'icon'   => 'dashicons-printer',
		'target' => '_blank',
	);
}

require __DIR__ . '/../partials/header.php';
require __DIR__ . '/../partials/nav.php';

$total_persons = count( $report['all_rows'] );
?>

<div class="spk-stats spk-stats--4">
	<article class="spk-stat spk-stat--income">
		<div class="spk-stat__icon"><span class="dashicons dashicons-download" aria-hidden="true"></span></div>
		<div class="spk-stat__body">
			<span class="spk-stat__label"><?php esc_html_e( 'مجموع ورودی تمام اشخاص', 'sparkknife-ledger' ); ?></span>
			<span class="spk-stat__value spk-money-positive"><?php echo esc_html( $totals['income_formatted'] ); ?></span>
			<span class="spk-stat__meta"><?php echo esc_html( $range['label'] ); ?></span>
		</div>
	</article>

	<article class="spk-stat spk-stat--expense">
		<div class="spk-stat__icon"><span class="dashicons dashicons-upload" aria-hidden="true"></span></div>
		<div class="spk-stat__body">
			<span class="spk-stat__label"><?php esc_html_e( 'مجموع خروجی تمام اشخاص', 'sparkknife-ledger' ); ?></span>
			<span class="spk-stat__value spk-money-negative"><?php echo esc_html( $totals['expense_formatted'] ); ?></span>
			<span class="spk-stat__meta">
				<?php
				printf(
					/* translators: %s: count */
					esc_html__( '%s تراکنش', 'sparkknife-ledger' ),
					esc_html( Sparkknife_Ledger_Money::to_persian_digits( (string) $totals['count'] ) )
				);
				?>
			</span>
		</div>
	</article>

	<article class="spk-stat spk-stat--balance <?php echo ! Sparkknife_Ledger_Money::is_negative( $totals['balance'] ) ? 'is-positive' : 'is-negative'; ?>">
		<div class="spk-stat__icon"><span class="dashicons dashicons-chart-pie" aria-hidden="true"></span></div>
		<div class="spk-stat__body">
			<span class="spk-stat__label"><?php esc_html_e( 'مانده کل', 'sparkknife-ledger' ); ?></span>
			<span class="spk-stat__value <?php echo ! Sparkknife_Ledger_Money::is_negative( $totals['balance'] ) ? 'spk-money-positive' : 'spk-money-negative'; ?>">
				<?php echo esc_html( $totals['balance_formatted'] ); ?>
			</span>
			<span class="spk-stat__meta"><?php echo esc_html( Sparkknife_Ledger_Ledger_Service::status_label( Sparkknife_Ledger_Ledger_Service::status_from_balance( $totals['balance'] ) ) ); ?></span>
		</div>
	</article>

	<article class="spk-stat spk-stat--persons">
		<div class="spk-stat__icon"><span class="dashicons dashicons-groups" aria-hidden="true"></span></div>
		<div class="spk-stat__body">
			<span class="spk-stat__label"><?php esc_html_e( 'اشخاص در گزارش', 'sparkknife-ledger' ); ?></span>
			<span class="spk-stat__value"><?php echo esc_html( Sparkknife_Ledger_Money::to_persian_digits( (string) $total_persons ) ); ?></span>
			<span class="spk-stat__meta">
				<?php
				printf(
					/* translators: 1: positive, 2: negative, 3: settled */
					esc_html__( 'مثبت: %1$s · منفی: %2$s · تسویه: %3$s', 'sparkknife-ledger' ),
					esc_html( Sparkknife_Ledger_Money::to_persian_digits( (string) $comparison['positive'] ) ),
					esc_html( Sparkknife_Ledger_Money::to_persian_digits( (string) $comparison['negative'] ) ),
					esc_html( Sparkknife_Ledger_Money::to_persian_digits( (string) $comparison['settled'] ) )
				);
				?>
			</span>
		</div>
	</article>
</div>

<?php
Sparkknife_Ledger_Renderer::view(
	'partials/filter-form',
	array(
		'action'      => admin_url( 'admin.php' ),
		'hidden'      => array( 'page' => 'sparkknife-ledger-summary' ),
		'range'       => $args['range'],
		'date_from'   => $args['date_from'],
		'date_to'     => $args['date_to'],
		'type'        => $args['type'],
		'status'      => '',
		'search'      => $args['search'],
		'persons'     => $report['persons'],
		'person_id'   => $args['person_id'],
		'show_person' => true,
		'show_status' => false,
	)
);
?>

<section class="spk-card" id="spk-results">
	<div class="spk-card__header">
		<h2><?php esc_html_e( 'گزارش تفکیکی اشخاص', 'sparkknife-ledger' ); ?></h2>
		<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="spk-inline-form spk-sort-form">
			<input type="hidden" name="page" value="sparkknife-ledger-summary" />
			<?php if ( $args['person_id'] ) : ?>
				<input type="hidden" name="person_id" value="<?php echo esc_attr( (string) $args['person_id'] ); ?>" />
			<?php endif; ?>
			<?php if ( 'all' !== $args['range'] ) : ?>
				<input type="hidden" name="range" value="<?php echo esc_attr( $args['range'] ); ?>" />
			<?php endif; ?>
			<?php if ( '' !== $args['search'] ) : ?>
				<input type="hidden" name="search" value="<?php echo esc_attr( $args['search'] ); ?>" />
			<?php endif; ?>
			<label class="screen-reader-text" for="spk-summary-orderby"><?php esc_html_e( 'مرتب‌سازی بر اساس', 'sparkknife-ledger' ); ?></label>
			<select name="orderby" id="spk-summary-orderby" class="spk-input spk-input--sm">
				<option value="balance" <?php selected( $args['orderby'], 'balance' ); ?>><?php esc_html_e( 'مانده', 'sparkknife-ledger' ); ?></option>
				<option value="name" <?php selected( $args['orderby'], 'name' ); ?>><?php esc_html_e( 'نام', 'sparkknife-ledger' ); ?></option>
				<option value="income" <?php selected( $args['orderby'], 'income' ); ?>><?php esc_html_e( 'مجموع ورودی', 'sparkknife-ledger' ); ?></option>
				<option value="expense" <?php selected( $args['orderby'], 'expense' ); ?>><?php esc_html_e( 'مجموع خروجی', 'sparkknife-ledger' ); ?></option>
				<option value="count" <?php selected( $args['orderby'], 'count' ); ?>><?php esc_html_e( 'تعداد تراکنش', 'sparkknife-ledger' ); ?></option>
			</select>
			<select name="order" class="spk-input spk-input--sm" aria-label="<?php esc_attr_e( 'جهت مرتب‌سازی', 'sparkknife-ledger' ); ?>">
				<option value="desc" <?php selected( strtolower( $args['order'] ), 'desc' ); ?>><?php esc_html_e( 'نزولی', 'sparkknife-ledger' ); ?></option>
				<option value="asc" <?php selected( strtolower( $args['order'] ), 'asc' ); ?>><?php esc_html_e( 'صعودی', 'sparkknife-ledger' ); ?></option>
			</select>
			<button type="submit" class="spk-btn spk-btn--ghost spk-btn--sm"><?php esc_html_e( 'اعمال', 'sparkknife-ledger' ); ?></button>
		</form>
	</div>

	<?php if ( empty( $report['rows'] ) ) : ?>
		<?php
		Sparkknife_Ledger_Renderer::view(
			'partials/empty-state',
			array(
				'icon'        => 'dashicons-chart-bar',
				'title'       => __( 'داده‌ای برای این گزارش وجود ندارد', 'sparkknife-ledger' ),
				'message'     => __( 'با ثبت اشخاص و تراکنش‌ها، این گزارش تکمیل می‌شود.', 'sparkknife-ledger' ),
				'action_url'  => Sparkknife_Ledger_Renderer::url( 'transactions', array( 'action' => 'new' ) ),
				'action_text' => __( 'ثبت تراکنش', 'sparkknife-ledger' ),
			)
		);
		?>
	<?php else : ?>
		<div class="spk-table-wrap">
			<table class="spk-table spk-table--hover spk-table--numeric">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'شخص', 'sparkknife-ledger' ); ?></th>
						<th scope="col"><?php esc_html_e( 'مجموع ورودی', 'sparkknife-ledger' ); ?></th>
						<th scope="col"><?php esc_html_e( 'مجموع خروجی', 'sparkknife-ledger' ); ?></th>
						<th scope="col"><?php esc_html_e( 'مانده', 'sparkknife-ledger' ); ?></th>
						<th scope="col"><?php esc_html_e( 'وضعیت حساب', 'sparkknife-ledger' ); ?></th>
						<th scope="col"><?php esc_html_e( 'تعداد تراکنش', 'sparkknife-ledger' ); ?></th>
						<th scope="col"><?php esc_html_e( 'مقایسه', 'sparkknife-ledger' ); ?></th>
						<th scope="col"><span class="screen-reader-text"><?php esc_html_e( 'عملیات', 'sparkknife-ledger' ); ?></span></th>
					</tr>
				</thead>
				<tbody>
					<?php
					$max_balance = '1';
					foreach ( $report['rows'] as $row ) {
						$max_balance = Sparkknife_Ledger_Amount::cmp( Sparkknife_Ledger_Amount::abs( $row['balance'] ), $max_balance ) > 0 ? Sparkknife_Ledger_Amount::abs( $row['balance'] ) : $max_balance;
					}
					?>
					<?php foreach ( $report['rows'] as $row ) : ?>
						<?php $bar_width = Sparkknife_Ledger_Money::percent_of( $row['balance'], $max_balance ); ?>
						<tr>
							<td data-label="<?php esc_attr_e( 'شخص', 'sparkknife-ledger' ); ?>">
								<a class="spk-link-strong" href="<?php echo esc_url( Sparkknife_Ledger_Renderer::url( 'reports', array( 'person_id' => $row['id'] ) ) ); ?>">
									<?php echo esc_html( $row['name'] ); ?>
								</a>
								<?php if ( 'inactive' === $row['status'] ) : ?>
									<span class="spk-badge is-muted"><?php esc_html_e( 'غیرفعال', 'sparkknife-ledger' ); ?></span>
								<?php endif; ?>
							</td>
							<td data-label="<?php esc_attr_e( 'مجموع ورودی', 'sparkknife-ledger' ); ?>" class="spk-money-positive">
								<?php echo esc_html( Sparkknife_Ledger_Money::format( $row['income'] ) ); ?>
							</td>
							<td data-label="<?php esc_attr_e( 'مجموع خروجی', 'sparkknife-ledger' ); ?>" class="spk-money-negative">
								<?php echo esc_html( Sparkknife_Ledger_Money::format( $row['expense'] ) ); ?>
							</td>
							<td data-label="<?php esc_attr_e( 'مانده', 'sparkknife-ledger' ); ?>">
								<span class="spk-balance <?php echo Sparkknife_Ledger_Money::is_positive( $row['balance'] ) ? 'is-positive' : ( Sparkknife_Ledger_Money::is_negative( $row['balance'] ) ? 'is-negative' : 'is-zero' ); ?>">
									<?php echo esc_html( Sparkknife_Ledger_Money::format( $row['balance'] ) ); ?>
								</span>
							</td>
							<td data-label="<?php esc_attr_e( 'وضعیت حساب', 'sparkknife-ledger' ); ?>">
								<?php
								Sparkknife_Ledger_Renderer::view(
									'partials/status-badge',
									array(
										'type'   => 'account',
										'status' => $row['account_status'],
									)
								);
								?>
							</td>
							<td data-label="<?php esc_attr_e( 'تعداد تراکنش', 'sparkknife-ledger' ); ?>">
								<?php echo esc_html( Sparkknife_Ledger_Money::to_persian_digits( (string) $row['count'] ) ); ?>
							</td>
							<td data-label="<?php esc_attr_e( 'مقایسه', 'sparkknife-ledger' ); ?>">
								<span class="spk-minibar" aria-hidden="true">
									<span class="spk-minibar__fill <?php echo Sparkknife_Ledger_Money::is_negative( $row['balance'] ) ? 'is-negative' : 'is-positive'; ?>"
										style="width: <?php echo esc_attr( (string) max( 2, $bar_width ) ); ?>%"></span>
								</span>
							</td>
							<td class="spk-table__actions">
								<a class="spk-btn spk-btn--icon" href="<?php echo esc_url( Sparkknife_Ledger_Renderer::url( 'reports', array( 'person_id' => $row['id'] ) ) ); ?>"
									title="<?php esc_attr_e( 'گزارش فردی', 'sparkknife-ledger' ); ?>">
									<span class="dashicons dashicons-chart-line" aria-hidden="true"></span>
									<span class="screen-reader-text"><?php esc_html_e( 'گزارش فردی', 'sparkknife-ledger' ); ?></span>
								</a>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
				<?php
				/*
				 * جمع ستون‌های همین جدول.
				 *
				 * `$totals` جمع کل بازه است (مستقل از جستجو) و در کارت‌های
				 * بالای صفحه با برچسب «مجموع ... تمام اشخاص» نمایش داده
				 * می‌شود. زیر جدول اما باید جمع همان ردیف‌هایی باشد که
				 * کاربر می‌بیند؛ وگرنه با یک ردیف هزار تومانی، «جمع کل»
				 * زیر جدول ۱۰٬۰۰۰ نشان می‌داد.
				 */
				$foot = isset( $report['filtered_totals'] ) ? $report['filtered_totals'] : $totals;
				?>
				<tfoot>
					<tr>
						<th scope="row"><?php esc_html_e( 'جمع کل', 'sparkknife-ledger' ); ?></th>
						<th class="spk-money-positive"><?php echo esc_html( $foot['income_formatted'] ); ?></th>
						<th class="spk-money-negative"><?php echo esc_html( $foot['expense_formatted'] ); ?></th>
						<th class="<?php echo ! Sparkknife_Ledger_Money::is_negative( $foot['balance'] ) ? 'spk-money-positive' : 'spk-money-negative'; ?>">
							<?php echo esc_html( $foot['balance_formatted'] ); ?>
						</th>
						<th colspan="4"></th>
					</tr>
				</tfoot>
			</table>
		</div>

		<?php
		Sparkknife_Ledger_Renderer::view(
			'partials/pagination',
			array(
				'page'     => $report['pagination']['page'],
				'pages'    => $report['pagination']['pages'],
				'total'    => $report['pagination']['total'],
				'per_page' => $report['pagination']['per_page'],
				'base_url' => $base_url,
				'arg'      => 'paged',
			)
		);
		?>
	<?php endif; ?>
</section>

<section class="spk-card">
	<h2 class="spk-card__title"><?php esc_html_e( 'مقایسه وضعیت مالی اشخاص', 'sparkknife-ledger' ); ?></h2>
	<div class="spk-comparison">
		<div class="spk-comparison__item spk-comparison__item--positive">
			<span class="spk-comparison__count"><?php echo esc_html( Sparkknife_Ledger_Money::to_persian_digits( (string) $comparison['positive'] ) ); ?></span>
			<span class="spk-comparison__label"><?php esc_html_e( 'اشخاص دارای مانده مثبت', 'sparkknife-ledger' ); ?></span>
		</div>
		<div class="spk-comparison__item spk-comparison__item--negative">
			<span class="spk-comparison__count"><?php echo esc_html( Sparkknife_Ledger_Money::to_persian_digits( (string) $comparison['negative'] ) ); ?></span>
			<span class="spk-comparison__label"><?php esc_html_e( 'اشخاص دارای مانده منفی', 'sparkknife-ledger' ); ?></span>
		</div>
		<div class="spk-comparison__item spk-comparison__item--settled">
			<span class="spk-comparison__count"><?php echo esc_html( Sparkknife_Ledger_Money::to_persian_digits( (string) $comparison['settled'] ) ); ?></span>
			<span class="spk-comparison__label"><?php esc_html_e( 'حساب‌های تسویه‌شده', 'sparkknife-ledger' ); ?></span>
		</div>
	</div>
</section>

<?php require __DIR__ . '/../partials/footer.php'; ?>
