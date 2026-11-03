<?php
/**
 * داشبورد مدیریت.
 *
 * @var array $stats        آمار کلی.
 * @var array $latest       آخرین تراکنش‌ها.
 * @var array $positive     اشخاص با مانده مثبت.
 * @var array $negative     اشخاص با مانده منفی.
 * @var array $chart        داده نمودار.
 * @var int   $chart_months تعداد ماه.
 * @var array $health       وضعیت جداول.
 * @var bool  $can_add      دسترسی ثبت تراکنش.
 * @var bool  $can_persons  دسترسی مدیریت اشخاص.
 * @var string $current     صفحه جاری.
 *
 * @package SparKnife_Ledger
 */

defined( 'ABSPATH' ) || exit;

require __DIR__ . '/../partials/header.php';
require __DIR__ . '/../partials/nav.php';
?>

<?php if ( 0 === (int) $stats['persons_total'] && 0 === (int) $stats['transactions_all'] ) : ?>
	<section class="spk-card spk-welcome">
		<div class="spk-welcome__icon" aria-hidden="true"><span class="dashicons dashicons-chart-area"></span></div>
		<div>
			<h2><?php esc_html_e( 'به SparKnife Ledger خوش آمدید', 'sparkknife-ledger' ); ?></h2>
			<p><?php esc_html_e( 'برای شروع، ابتدا یک شخص ایجاد کنید و سپس تراکنش‌های ورودی و خروجی او را ثبت کنید. مانده حساب‌ها به‌صورت خودکار محاسبه می‌شود.', 'sparkknife-ledger' ); ?></p>
			<div class="spk-welcome__steps">
				<span><b>۱</b> <?php esc_html_e( 'افزودن شخص', 'sparkknife-ledger' ); ?></span>
				<span><b>۲</b> <?php esc_html_e( 'ثبت تراکنش', 'sparkknife-ledger' ); ?></span>
				<span><b>۳</b> <?php esc_html_e( 'مشاهده گزارش', 'sparkknife-ledger' ); ?></span>
			</div>
			<?php if ( $can_persons ) : ?>
				<a class="spk-btn spk-btn--primary" href="<?php echo esc_url( Sparkknife_Ledger_Renderer::url( 'persons', array( 'action' => 'new' ) ) ); ?>">
					<span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
					<?php esc_html_e( 'افزودن اولین شخص', 'sparkknife-ledger' ); ?>
				</a>
			<?php endif; ?>
		</div>
	</section>
<?php endif; ?>

<div class="spk-stats">
	<article class="spk-stat spk-stat--persons">
		<div class="spk-stat__icon"><span class="dashicons dashicons-groups" aria-hidden="true"></span></div>
		<div class="spk-stat__body">
			<span class="spk-stat__label"><?php esc_html_e( 'تعداد کل اشخاص', 'sparkknife-ledger' ); ?></span>
			<span class="spk-stat__value"><?php echo esc_html( Sparkknife_Ledger_Money::to_persian_digits( (string) $stats['persons_total'] ) ); ?></span>
			<span class="spk-stat__meta">
				<?php
				printf(
					/* translators: 1: active count, 2: inactive count */
					esc_html__( '%1$s فعال · %2$s غیرفعال', 'sparkknife-ledger' ),
					esc_html( Sparkknife_Ledger_Money::to_persian_digits( (string) $stats['persons_active'] ) ),
					esc_html( Sparkknife_Ledger_Money::to_persian_digits( (string) $stats['persons_inactive'] ) )
				);
				?>
			</span>
		</div>
	</article>

	<article class="spk-stat spk-stat--income">
		<div class="spk-stat__icon"><span class="dashicons dashicons-arrow-down-alt" aria-hidden="true"></span></div>
		<div class="spk-stat__body">
			<span class="spk-stat__label"><?php esc_html_e( 'مجموع ورودی‌ها', 'sparkknife-ledger' ); ?></span>
			<span class="spk-stat__value spk-money-positive"><?php echo esc_html( $stats['income_formatted'] ); ?></span>
			<span class="spk-stat__meta">
				<?php
				printf(
					/* translators: %s: average amount */
					esc_html__( 'میانگین هر تراکنش: %s', 'sparkknife-ledger' ),
					esc_html( Sparkknife_Ledger_Money::format( $stats['average_income'] ) )
				);
				?>
			</span>
		</div>
	</article>

	<article class="spk-stat spk-stat--expense">
		<div class="spk-stat__icon"><span class="dashicons dashicons-arrow-up-alt" aria-hidden="true"></span></div>
		<div class="spk-stat__body">
			<span class="spk-stat__label"><?php esc_html_e( 'مجموع خروجی‌ها', 'sparkknife-ledger' ); ?></span>
			<span class="spk-stat__value spk-money-negative"><?php echo esc_html( $stats['expense_formatted'] ); ?></span>
			<span class="spk-stat__meta">
				<?php
				printf(
					/* translators: %s: average amount */
					esc_html__( 'میانگین هر تراکنش: %s', 'sparkknife-ledger' ),
					esc_html( Sparkknife_Ledger_Money::format( $stats['average_expense'] ) )
				);
				?>
			</span>
		</div>
	</article>

	<article class="spk-stat spk-stat--balance <?php echo ! Sparkknife_Ledger_Money::is_negative( $stats['balance'] ) ? 'is-positive' : 'is-negative'; ?>">
		<div class="spk-stat__icon"><span class="dashicons dashicons-chart-pie" aria-hidden="true"></span></div>
		<div class="spk-stat__body">
			<span class="spk-stat__label"><?php esc_html_e( 'مجموع مانده حساب‌ها', 'sparkknife-ledger' ); ?></span>
			<span class="spk-stat__value <?php echo ! Sparkknife_Ledger_Money::is_negative( $stats['balance'] ) ? 'spk-money-positive' : 'spk-money-negative'; ?>">
				<?php echo esc_html( $stats['balance_formatted'] ); ?>
			</span>
			<span class="spk-stat__meta">
				<?php echo esc_html( Sparkknife_Ledger_Ledger_Service::status_label( $stats['account_status'] ) ); ?>
			</span>
		</div>
	</article>

	<article class="spk-stat spk-stat--txn">
		<div class="spk-stat__icon"><span class="dashicons dashicons-list-view" aria-hidden="true"></span></div>
		<div class="spk-stat__body">
			<span class="spk-stat__label"><?php esc_html_e( 'تعداد تراکنش‌ها', 'sparkknife-ledger' ); ?></span>
			<span class="spk-stat__value"><?php echo esc_html( Sparkknife_Ledger_Money::to_persian_digits( (string) $stats['transactions'] ) ); ?></span>
			<span class="spk-stat__meta">
				<?php
				printf(
					/* translators: 1: void count, 2: total transactions */
					esc_html__( '%1$s باطل‌شده از %2$s کل رکورد', 'sparkknife-ledger' ),
					esc_html( Sparkknife_Ledger_Money::to_persian_digits( (string) $stats['void_count'] ) ),
					esc_html( Sparkknife_Ledger_Money::to_persian_digits( (string) $stats['transactions_all'] ) )
				);
				?>
			</span>
		</div>
	</article>
</div>

<div class="spk-grid spk-grid--2">
	<section class="spk-card">
		<div class="spk-card__header">
			<h2><?php esc_html_e( 'روند ماهانه', 'sparkknife-ledger' ); ?></h2>
			<div class="spk-card__actions">
				<?php foreach ( array( 3, 6, 12 ) as $months_option ) : ?>
					<a class="spk-chip <?php echo (int) $chart_months === $months_option ? 'is-active' : ''; ?>"
						href="<?php echo esc_url( add_query_arg( 'months', $months_option ) ); ?>">
						<?php
						printf(
							/* translators: %s: number of months */
							esc_html__( '%s ماه', 'sparkknife-ledger' ),
							esc_html( Sparkknife_Ledger_Money::to_persian_digits( (string) $months_option ) )
						);
						?>
					</a>
				<?php endforeach; ?>
			</div>
		</div>

		<?php
		$max_value = '1';
		foreach ( $chart as $month ) {
			$max_value = Sparkknife_Ledger_Money::max_of( array( $max_value, $month['income'], $month['expense'] ) );
		}
		?>
		<div class="spk-chart" role="img" aria-label="<?php esc_attr_e( 'نمودار ورودی و خروجی ماهانه', 'sparkknife-ledger' ); ?>">
			<?php foreach ( $chart as $month ) : ?>
				<?php
				$income_height  = Sparkknife_Ledger_Money::percent_of( $month['income'], $max_value );
				$expense_height = Sparkknife_Ledger_Money::percent_of( $month['expense'], $max_value );
				?>
				<div class="spk-chart__group">
					<div class="spk-chart__bars">
						<div class="spk-chart__bar spk-chart__bar--income" style="height: <?php echo esc_attr( (string) max( 2, $income_height ) ); ?>%"
							title="<?php echo esc_attr( sprintf( '%s: %s', __( 'ورودی', 'sparkknife-ledger' ), $month['income_formatted'] ) ); ?>"></div>
						<div class="spk-chart__bar spk-chart__bar--expense" style="height: <?php echo esc_attr( (string) max( 2, $expense_height ) ); ?>%"
							title="<?php echo esc_attr( sprintf( '%s: %s', __( 'خروجی', 'sparkknife-ledger' ), $month['expense_formatted'] ) ); ?>"></div>
					</div>
					<span class="spk-chart__label"><?php echo esc_html( $month['label'] ); ?></span>
				</div>
			<?php endforeach; ?>
		</div>
		<div class="spk-legend">
			<span class="spk-legend__item spk-legend__item--income"><?php esc_html_e( 'ورودی', 'sparkknife-ledger' ); ?></span>
			<span class="spk-legend__item spk-legend__item--expense"><?php esc_html_e( 'خروجی', 'sparkknife-ledger' ); ?></span>
		</div>
	</section>

	<section class="spk-card">
		<div class="spk-card__header">
			<h2><?php esc_html_e( 'وضعیت مالی اشخاص', 'sparkknife-ledger' ); ?></h2>
			<a class="spk-card__link" href="<?php echo esc_url( Sparkknife_Ledger_Renderer::url( 'summary' ) ); ?>">
				<?php esc_html_e( 'گزارش کامل', 'sparkknife-ledger' ); ?>
			</a>
		</div>

		<div class="spk-balance-columns">
			<div class="spk-balance-col">
				<h3 class="spk-balance-col__title spk-money-positive">
					<span class="dashicons dashicons-plus-alt" aria-hidden="true"></span>
					<?php esc_html_e( 'مانده مثبت', 'sparkknife-ledger' ); ?>
				</h3>
				<?php if ( empty( $positive ) ) : ?>
					<p class="spk-muted"><?php esc_html_e( 'شخصی با مانده مثبت وجود ندارد.', 'sparkknife-ledger' ); ?></p>
				<?php else : ?>
					<ul class="spk-list">
						<?php foreach ( $positive as $item ) : ?>
							<li>
								<a href="<?php echo esc_url( Sparkknife_Ledger_Renderer::url( 'reports', array( 'person_id' => $item['id'] ) ) ); ?>">
									<?php echo esc_html( $item['name'] ); ?>
								</a>
								<span class="spk-list__amount spk-money-positive"><?php echo esc_html( $item['balance_formatted'] ); ?></span>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>

			<div class="spk-balance-col">
				<h3 class="spk-balance-col__title spk-money-negative">
					<span class="dashicons dashicons-dismiss" aria-hidden="true"></span>
					<?php esc_html_e( 'مانده منفی', 'sparkknife-ledger' ); ?>
				</h3>
				<?php if ( empty( $negative ) ) : ?>
					<p class="spk-muted"><?php esc_html_e( 'شخصی با مانده منفی وجود ندارد.', 'sparkknife-ledger' ); ?></p>
				<?php else : ?>
					<ul class="spk-list">
						<?php foreach ( $negative as $item ) : ?>
							<li>
								<a href="<?php echo esc_url( Sparkknife_Ledger_Renderer::url( 'reports', array( 'person_id' => $item['id'] ) ) ); ?>">
									<?php echo esc_html( $item['name'] ); ?>
								</a>
								<span class="spk-list__amount spk-money-negative"><?php echo esc_html( $item['balance_formatted'] ); ?></span>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>
		</div>
	</section>
</div>

<section class="spk-card">
	<div class="spk-card__header">
		<h2><?php esc_html_e( 'آخرین تراکنش‌ها', 'sparkknife-ledger' ); ?></h2>
		<div class="spk-card__actions">
			<?php if ( $can_add ) : ?>
				<a class="spk-btn spk-btn--primary spk-btn--sm" href="<?php echo esc_url( Sparkknife_Ledger_Renderer::url( 'transactions', array( 'action' => 'new' ) ) ); ?>">
					<span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
					<?php esc_html_e( 'ثبت تراکنش', 'sparkknife-ledger' ); ?>
				</a>
			<?php endif; ?>
			<a class="spk-card__link" href="<?php echo esc_url( Sparkknife_Ledger_Renderer::url( 'transactions' ) ); ?>"><?php esc_html_e( 'همه تراکنش‌ها', 'sparkknife-ledger' ); ?></a>
		</div>
	</div>

	<?php if ( empty( $latest ) ) : ?>
		<?php
		Sparkknife_Ledger_Renderer::view(
			'partials/empty-state',
			array(
				'icon'        => 'dashicons-list-view',
				'title'       => __( 'هنوز تراکنشی ثبت نشده است', 'sparkknife-ledger' ),
				'message'     => __( 'با ثبت اولین تراکنش، آمار و نمودارها فعال می‌شوند.', 'sparkknife-ledger' ),
				'action_url'  => $can_add ? Sparkknife_Ledger_Renderer::url( 'transactions', array( 'action' => 'new' ) ) : '',
				'action_text' => __( 'ثبت تراکنش جدید', 'sparkknife-ledger' ),
			)
		);
		?>
	<?php else : ?>
		<div class="spk-table-wrap">
			<table class="spk-table">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'شناسه', 'sparkknife-ledger' ); ?></th>
						<th scope="col"><?php esc_html_e( 'شخص', 'sparkknife-ledger' ); ?></th>
						<th scope="col"><?php esc_html_e( 'نوع', 'sparkknife-ledger' ); ?></th>
						<th scope="col"><?php esc_html_e( 'مبلغ', 'sparkknife-ledger' ); ?></th>
						<th scope="col"><?php esc_html_e( 'تاریخ', 'sparkknife-ledger' ); ?></th>
						<th scope="col"><?php esc_html_e( 'وضعیت', 'sparkknife-ledger' ); ?></th>
						<th scope="col"><span class="screen-reader-text"><?php esc_html_e( 'عملیات', 'sparkknife-ledger' ); ?></span></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $latest as $transaction ) : ?>
						<tr class="<?php echo 'void' === $transaction->status ? 'is-void' : ''; ?>">
							<td data-label="<?php esc_attr_e( 'شناسه', 'sparkknife-ledger' ); ?>"><code><?php echo esc_html( $transaction->reference ); ?></code></td>
							<td data-label="<?php esc_attr_e( 'شخص', 'sparkknife-ledger' ); ?>"><?php echo esc_html( (string) $transaction->person_name ); ?></td>
							<td data-label="<?php esc_attr_e( 'نوع', 'sparkknife-ledger' ); ?>">
								<span class="spk-type spk-type--<?php echo esc_attr( $transaction->type ); ?>">
									<?php echo esc_html( Sparkknife_Ledger_Money::transaction_type_label( $transaction->type ) ); ?>
								</span>
							</td>
							<td data-label="<?php esc_attr_e( 'مبلغ', 'sparkknife-ledger' ); ?>">
								<span class="<?php echo 'income' === $transaction->type ? 'spk-money-positive' : 'spk-money-negative'; ?>">
									<?php echo esc_html( Sparkknife_Ledger_Money::format( $transaction->amount ) ); ?>
								</span>
							</td>
							<td data-label="<?php esc_attr_e( 'تاریخ', 'sparkknife-ledger' ); ?>"><?php echo esc_html( Sparkknife_Ledger_Date::format( $transaction->txn_date ) ); ?></td>
							<td data-label="<?php esc_attr_e( 'وضعیت', 'sparkknife-ledger' ); ?>">
								<?php
								Sparkknife_Ledger_Renderer::view(
									'partials/status-badge',
									array(
										'type'   => 'transaction',
										'status' => $transaction->status,
									)
								);
								?>
							</td>
							<td>
								<a class="spk-btn spk-btn--icon" href="<?php echo esc_url( Sparkknife_Ledger_Renderer::url( 'transactions', array( 'action' => 'view', 'id' => (int) $transaction->id ) ) ); ?>"
									aria-label="<?php esc_attr_e( 'مشاهده جزئیات', 'sparkknife-ledger' ); ?>">
									<span class="dashicons dashicons-visibility" aria-hidden="true"></span>
								</a>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php endif; ?>
</section>

<details class="spk-card spk-details">
	<summary><?php esc_html_e( 'وضعیت سلامت سیستم', 'sparkknife-ledger' ); ?></summary>
	<div class="spk-table-wrap">
		<table class="spk-table spk-table--compact">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'جدول', 'sparkknife-ledger' ); ?></th>
					<th scope="col"><?php esc_html_e( 'نام در دیتابیس', 'sparkknife-ledger' ); ?></th>
					<th scope="col"><?php esc_html_e( 'وضعیت', 'sparkknife-ledger' ); ?></th>
					<th scope="col"><?php esc_html_e( 'تعداد ردیف', 'sparkknife-ledger' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $health as $table ) : ?>
					<tr>
						<td><?php echo esc_html( $table['label'] ); ?></td>
						<td><code><?php echo esc_html( $table['table'] ); ?></code></td>
						<td>
							<?php if ( 'ok' === $table['status'] ) : ?>
								<span class="spk-badge is-ok"><?php esc_html_e( 'آماده', 'sparkknife-ledger' ); ?></span>
							<?php else : ?>
								<span class="spk-badge is-bad"><?php esc_html_e( 'یافت نشد', 'sparkknife-ledger' ); ?></span>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( Sparkknife_Ledger_Money::to_persian_digits( $table['rows'] ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</details>

<?php require __DIR__ . '/../partials/footer.php'; ?>
