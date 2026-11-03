<?php
/**
 * جزئیات تراکنش، ابطال و تاریخچه تغییرات.
 *
 * @var object $transaction تراکنش.
 * @var array  $summary     خلاصه حساب شخص.
 * @var array  $history     تاریخچه.
 * @var string $current     صفحه جاری.
 *
 * @package SparKnife_Ledger
 */

defined( 'ABSPATH' ) || exit;

$is_void = 'void' === $transaction->status;

$header_actions = array(
	array(
		'label' => __( 'بازگشت به تراکنش‌ها', 'sparkknife-ledger' ),
		'url'   => Sparkknife_Ledger_Renderer::url( 'transactions' ),
		'icon'  => 'dashicons-arrow-right-alt',
	),
);

if ( ! $is_void && Sparkknife_Ledger_Capabilities::current_user_can( 'manage_transactions' ) ) {
	$header_actions[] = array(
		'label' => __( 'ویرایش تراکنش', 'sparkknife-ledger' ),
		'url'   => Sparkknife_Ledger_Renderer::url( 'transactions', array( 'action' => 'edit', 'id' => (int) $transaction->id ) ),
		'icon'  => 'dashicons-edit',
	);
}

$title       = __( 'جزئیات تراکنش', 'sparkknife-ledger' );
$description = __( 'اطلاعات کامل تراکنش، وضعیت محاسباتی و سابقه تغییرات', 'sparkknife-ledger' );

require __DIR__ . '/../partials/header.php';
require __DIR__ . '/../partials/nav.php';

$creator = $transaction->created_by ? get_userdata( (int) $transaction->created_by ) : null;
$updater = $transaction->updated_by ? get_userdata( (int) $transaction->updated_by ) : null;
$voider = $transaction->voided_by ? get_userdata( (int) $transaction->voided_by ) : null;
$detail_url = Sparkknife_Ledger_Renderer::url( 'transactions', array( 'action' => 'view', 'id' => (int) $transaction->id ) );
?>

<?php if ( $is_void ) : ?>
	<div class="spk-alert spk-alert--error">
		<span class="dashicons dashicons-dismiss" aria-hidden="true"></span>
		<div>
			<strong><?php esc_html_e( 'این تراکنش باطل شده است و در محاسبات مالی لحاظ نمی‌شود.', 'sparkknife-ledger' ); ?></strong>
			<p>
				<?php
				printf(
					/* translators: 1: date, 2: user */
					esc_html__( 'تاریخ ابطال: %1$s · کاربر ابطال‌کننده: %2$s', 'sparkknife-ledger' ),
					esc_html( Sparkknife_Ledger_Date::format( $transaction->voided_at, true ) ),
					esc_html( $voider ? $voider->display_name : __( 'نامشخص', 'sparkknife-ledger' ) )
				);
				?>
			</p>
			<?php if ( $transaction->void_reason ) : ?>
				<p>
					<?php
					printf(
						/* translators: %s: reason */
						esc_html__( 'دلیل ابطال: %s', 'sparkknife-ledger' ),
						esc_html( $transaction->void_reason )
					);
					?>
				</p>
			<?php endif; ?>
		</div>
	</div>
<?php endif; ?>

<div class="spk-txn-hero spk-txn-hero--<?php echo esc_attr( $transaction->type ); ?> <?php echo $is_void ? 'is-void' : ''; ?>">
	<div class="spk-txn-hero__type">
		<span class="dashicons <?php echo 'income' === $transaction->type ? 'dashicons-arrow-down-alt' : 'dashicons-arrow-up-alt'; ?>" aria-hidden="true"></span>
		<span><?php echo esc_html( Sparkknife_Ledger_Money::transaction_type_label( $transaction->type ) ); ?></span>
	</div>
	<div class="spk-txn-hero__amount">
		<?php echo esc_html( Sparkknife_Ledger_Money::format( $transaction->amount ) ); ?>
	</div>
	<div class="spk-txn-hero__meta">
		<span><code><?php echo esc_html( $transaction->reference ); ?></code></span>
		<span><?php echo esc_html( Sparkknife_Ledger_Date::format( $transaction->txn_date ) ); ?></span>
		<?php
		Sparkknife_Ledger_Renderer::view(
			'partials/status-badge',
			array(
				'type'   => 'transaction',
				'status' => $transaction->status,
			)
		);
		?>
	</div>
</div>

<div class="spk-grid spk-grid--sidebar">
	<div class="spk-stack spk-stack--lg">
		<section class="spk-card">
			<h2 class="spk-card__title"><?php esc_html_e( 'اطلاعات تراکنش', 'sparkknife-ledger' ); ?></h2>
			<dl class="spk-dl spk-dl--2">
				<div>
					<dt><?php esc_html_e( 'شناسه تراکنش', 'sparkknife-ledger' ); ?></dt>
					<dd><code><?php echo esc_html( $transaction->reference ); ?></code></dd>
				</div>
				<div>
					<dt><?php esc_html_e( 'شخص مرتبط', 'sparkknife-ledger' ); ?></dt>
					<dd>
						<a href="<?php echo esc_url( Sparkknife_Ledger_Renderer::url( 'persons', array( 'action' => 'view', 'id' => (int) $transaction->person_id ) ) ); ?>">
							<?php echo esc_html( (string) $transaction->person_name ); ?>
						</a>
						<?php if ( $transaction->person_phone ) : ?>
							<span class="spk-muted" dir="ltr"><?php echo esc_html( $transaction->person_phone ); ?></span>
						<?php endif; ?>
					</dd>
				</div>
				<div>
					<dt><?php esc_html_e( 'نوع تراکنش', 'sparkknife-ledger' ); ?></dt>
					<dd><?php echo esc_html( Sparkknife_Ledger_Money::transaction_type_label( $transaction->type ) ); ?></dd>
				</div>
				<div>
					<dt><?php esc_html_e( 'مبلغ', 'sparkknife-ledger' ); ?></dt>
					<dd class="<?php echo 'income' === $transaction->type ? 'spk-money-positive' : 'spk-money-negative'; ?>">
						<?php echo esc_html( Sparkknife_Ledger_Money::format( $transaction->amount ) ); ?>
					</dd>
				</div>
				<div>
					<dt><?php esc_html_e( 'تاریخ تراکنش', 'sparkknife-ledger' ); ?></dt>
					<dd><?php echo esc_html( Sparkknife_Ledger_Date::format( $transaction->txn_date ) ); ?></dd>
				</div>
				<div>
					<dt><?php esc_html_e( 'واحد پول ثبت', 'sparkknife-ledger' ); ?></dt>
					<dd><?php echo esc_html( $transaction->currency ? $transaction->currency : Sparkknife_Ledger_Money::currency_code() ); ?></dd>
				</div>
				<div>
					<dt><?php esc_html_e( 'تاریخ ثبت در سیستم', 'sparkknife-ledger' ); ?></dt>
					<dd><?php echo esc_html( Sparkknife_Ledger_Date::format( $transaction->created_at, true ) ); ?></dd>
				</div>
				<div>
					<dt><?php esc_html_e( 'کاربر ثبت‌کننده', 'sparkknife-ledger' ); ?></dt>
					<dd><?php echo esc_html( $creator ? $creator->display_name : __( 'نامشخص', 'sparkknife-ledger' ) ); ?></dd>
				</div>
				<div>
					<dt><?php esc_html_e( 'آخرین ویرایش', 'sparkknife-ledger' ); ?></dt>
					<dd>
						<?php echo esc_html( Sparkknife_Ledger_Date::format( $transaction->updated_at, true ) ); ?>
						<?php if ( $updater && (int) $transaction->updated_by !== (int) $transaction->created_by ) : ?>
							<span class="spk-muted">· <?php echo esc_html( $updater->display_name ); ?></span>
						<?php endif; ?>
					</dd>
				</div>
				<div class="spk-dl__full">
					<dt><?php esc_html_e( 'توضیحات', 'sparkknife-ledger' ); ?></dt>
					<dd><?php echo $transaction->description ? nl2br( esc_html( $transaction->description ) ) : '<span class="spk-muted">—</span>'; ?></dd>
				</div>
			</dl>
		</section>

		<?php if ( $is_void ) : ?>
			<section class="spk-card">
				<h2 class="spk-card__title"><?php esc_html_e( 'اطلاعات ابطال', 'sparkknife-ledger' ); ?></h2>
				<dl class="spk-dl">
					<div>
						<dt><?php esc_html_e( 'دلیل ابطال', 'sparkknife-ledger' ); ?></dt>
						<dd><?php echo $transaction->void_reason ? nl2br( esc_html( $transaction->void_reason ) ) : '<span class="spk-muted">—</span>'; ?></dd>
					</div>
					<div>
						<dt><?php esc_html_e( 'تاریخ ابطال', 'sparkknife-ledger' ); ?></dt>
						<dd><?php echo esc_html( Sparkknife_Ledger_Date::format( $transaction->voided_at, true ) ); ?></dd>
					</div>
					<div>
						<dt><?php esc_html_e( 'کاربر ابطال‌کننده', 'sparkknife-ledger' ); ?></dt>
						<dd><?php echo esc_html( $voider ? $voider->display_name : __( 'نامشخص', 'sparkknife-ledger' ) ); ?></dd>
					</div>
				</dl>
			</section>
		<?php endif; ?>

		<section class="spk-card">
			<h2 class="spk-card__title"><?php esc_html_e( 'تاریخچه تغییرات این تراکنش', 'sparkknife-ledger' ); ?></h2>
			<?php if ( empty( $history ) ) : ?>
				<p class="spk-muted"><?php esc_html_e( 'تغییری ثبت نشده است.', 'sparkknife-ledger' ); ?></p>
			<?php else : ?>
				<?php
				$action_labels = Sparkknife_Ledger_Audit_Service::instance()->actions();
				?>
				<ul class="spk-timeline">
					<?php foreach ( $history as $entry ) : ?>
						<?php
						$changes = Sparkknife_Ledger_Audit_Service::instance()->decode_changes( $entry );
						$user    = $entry->user_id ? get_userdata( (int) $entry->user_id ) : null;
						?>
						<li>
							<span class="spk-timeline__dot" aria-hidden="true"></span>
							<div>
								<strong><?php echo esc_html( isset( $action_labels[ $entry->action ] ) ? $action_labels[ $entry->action ] : $entry->action ); ?></strong>
								<span class="spk-timeline__time">
									<?php echo esc_html( Sparkknife_Ledger_Date::format( $entry->created_at, true ) ); ?>
									<?php if ( $user ) : ?>
										· <?php echo esc_html( $user->display_name ); ?>
									<?php endif; ?>
								</span>
								<?php if ( ! empty( $changes ) ) : ?>
									<ul class="spk-changes">
										<?php foreach ( $changes as $field => $change ) : ?>
											<?php
											if ( ! is_array( $change ) || ! array_key_exists( 'from', $change ) ) {
												continue;
											}
											$audit = Sparkknife_Ledger_Audit_Service::instance();
											?>
											<li>
												<span class="spk-changes__field"><?php echo esc_html( $audit->field_label( $field ) ); ?></span>
												<span class="spk-changes__from"><?php echo esc_html( $audit->present_for_display( $field, $change['from'] ) ); ?></span>
												<span aria-hidden="true">←</span>
												<span class="spk-changes__to"><?php echo esc_html( $audit->present_for_display( $field, isset( $change['to'] ) ? $change['to'] : '' ) ); ?></span>
											</li>
										<?php endforeach; ?>
									</ul>
								<?php endif; ?>
								<?php if ( $entry->summary ) : ?>
									<p class="spk-timeline__summary"><?php echo esc_html( (string) $entry->summary ); ?></p>
								<?php endif; ?>
							</div>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</section>
	</div>

	<aside class="spk-sidebar">
		<?php if ( ! $is_void && Sparkknife_Ledger_Capabilities::current_user_can( 'void_transactions' ) ) : ?>
			<section class="spk-card spk-card--danger">
				<h2 class="spk-card__title"><?php esc_html_e( 'ابطال تراکنش', 'sparkknife-ledger' ); ?></h2>
				<p class="spk-muted">
					<?php esc_html_e( 'ابطال باعث حذف تراکنش از محاسبات می‌شود اما اطلاعات و سوابق برای حسابرسی حفظ می‌گردد. این عملیات قابل بازگشت نیست.', 'sparkknife-ledger' ); ?>
				</p>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="spk-form"
					data-confirm="<?php esc_attr_e( 'آیا از ابطال این تراکنش اطمینان دارید؟ این عملیات قابل بازگشت نیست.', 'sparkknife-ledger' ); ?>">
					<input type="hidden" name="action" value="sparkknife_ledger_action" />
					<input type="hidden" name="sparkknife_action" value="transaction_void" />
					<input type="hidden" name="sparkknife_redirect" value="<?php echo esc_attr( $detail_url ); ?>" />
					<input type="hidden" name="transaction_id" value="<?php echo esc_attr( (string) $transaction->id ); ?>" />
					<?php wp_nonce_field( 'sparkknife_ledger_transaction_void' ); ?>

					<?php
					Sparkknife_Ledger_Renderer::view(
						'partials/form-field',
						array(
							'name'     => 'void_reason',
							'label'    => __( 'دلیل ابطال', 'sparkknife-ledger' ),
							'type'     => 'textarea',
							'value'    => '',
							'required' => (bool) Sparkknife_Ledger_Settings::get( 'require_void_reason', 1 ),
							'rows'     => 3,
							'hint'     => __( 'دلیل در تاریخچه ثبت می‌شود و برای همه کاربران دارای دسترسی قابل مشاهده است.', 'sparkknife-ledger' ),
							'extra'    => 'maxlength="500"',
						)
					);
					?>

					<button type="submit" class="spk-btn spk-btn--danger spk-btn--block">
						<span class="dashicons dashicons-dismiss" aria-hidden="true"></span>
						<?php esc_html_e( 'ابطال قطعی تراکنش', 'sparkknife-ledger' ); ?>
					</button>
				</form>
			</section>
		<?php endif; ?>

		<section class="spk-card">
			<h2 class="spk-card__title"><?php esc_html_e( 'مانده حساب شخص', 'sparkknife-ledger' ); ?></h2>
			<dl class="spk-dl">
				<div>
					<dt><?php esc_html_e( 'مجموع ورودی', 'sparkknife-ledger' ); ?></dt>
					<dd class="spk-money-positive"><?php echo esc_html( $summary['income_formatted'] ); ?></dd>
				</div>
				<div>
					<dt><?php esc_html_e( 'مجموع خروجی', 'sparkknife-ledger' ); ?></dt>
					<dd class="spk-money-negative"><?php echo esc_html( $summary['expense_formatted'] ); ?></dd>
				</div>
				<div>
					<dt><?php esc_html_e( 'مانده فعلی', 'sparkknife-ledger' ); ?></dt>
					<dd class="spk-strong <?php echo ! Sparkknife_Ledger_Money::is_negative( $summary['balance'] ) ? 'spk-money-positive' : 'spk-money-negative'; ?>">
						<?php echo esc_html( $summary['balance_formatted'] ); ?>
					</dd>
				</div>
				<div>
					<dt><?php esc_html_e( 'وضعیت حساب', 'sparkknife-ledger' ); ?></dt>
					<dd>
						<?php
						Sparkknife_Ledger_Renderer::view(
							'partials/status-badge',
							array(
								'type'   => 'account',
								'status' => $summary['account_status'],
							)
						);
						?>
					</dd>
				</div>
			</dl>
			<a class="spk-card__link" href="<?php echo esc_url( Sparkknife_Ledger_Renderer::url( 'persons', array( 'action' => 'view', 'id' => (int) $transaction->person_id ) ) ); ?>">
				<?php esc_html_e( 'مشاهده صفحه شخص', 'sparkknife-ledger' ); ?>
			</a>
		</section>
	</aside>
</div>

<?php require __DIR__ . '/../partials/footer.php'; ?>
