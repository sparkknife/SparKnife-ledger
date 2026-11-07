<?php
/**
 * جدول تراکنش‌ها (قابل استفاده مجدد).
 *
 * @var array  $transactions نتیجه کوئری.
 * @var string $empty_title  عنوان حالت خالی.
 * @var string $empty_text   متن حالت خالی.
 * @var string $empty_url    دکمه حالت خالی.
 * @var bool   $show_person  نمایش ستون شخص.
 *
 * @package SparKnife_Ledger
 */

defined( 'ABSPATH' ) || exit;

$transactions = isset( $transactions ) ? $transactions : array();
$empty_title  = isset( $empty_title ) ? $empty_title : __( 'تراکنشی یافت نشد', 'sparkknife-ledger' );
$empty_text   = isset( $empty_text ) ? $empty_text : '';
$empty_url    = isset( $empty_url ) ? $empty_url : '';
$show_person  = isset( $show_person ) ? (bool) $show_person : true;
$items        = isset( $transactions['items'] ) ? $transactions['items'] : array();
?>
<?php if ( empty( $items ) ) : ?>
	<?php
	Sparkknife_Ledger_Renderer::view(
		'partials/empty-state',
		array(
			'icon'        => 'dashicons-list-view',
			'title'       => $empty_title,
			'message'     => $empty_text,
			'action_url'  => $empty_url,
			'action_text' => __( 'ثبت تراکنش جدید', 'sparkknife-ledger' ),
		)
	);
	?>
<?php else : ?>
	<div class="spk-table-wrap">
		<table class="spk-table spk-table--hover">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'شناسه', 'sparkknife-ledger' ); ?></th>
					<?php if ( $show_person ) : ?>
						<th scope="col"><?php esc_html_e( 'شخص', 'sparkknife-ledger' ); ?></th>
					<?php endif; ?>
					<th scope="col"><?php esc_html_e( 'نوع', 'sparkknife-ledger' ); ?></th>
					<th scope="col"><?php esc_html_e( 'مبلغ', 'sparkknife-ledger' ); ?></th>
					<th scope="col"><?php esc_html_e( 'تاریخ تراکنش', 'sparkknife-ledger' ); ?></th>
					<th scope="col"><?php esc_html_e( 'توضیحات', 'sparkknife-ledger' ); ?></th>
					<th scope="col"><?php esc_html_e( 'وضعیت', 'sparkknife-ledger' ); ?></th>
					<th scope="col"><span class="screen-reader-text"><?php esc_html_e( 'عملیات', 'sparkknife-ledger' ); ?></span></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $items as $transaction ) : ?>
					<?php
					$view_url = Sparkknife_Ledger_Renderer::url(
						'transactions',
						array(
							'action' => 'view',
							'id'     => (int) $transaction->id,
						)
					);
					$is_void = 'void' === $transaction->status;
					?>
					<tr class="<?php echo $is_void ? 'is-void' : ''; ?>">
						<td data-label="<?php esc_attr_e( 'شناسه', 'sparkknife-ledger' ); ?>">
							<a href="<?php echo esc_url( $view_url ); ?>"><code><?php echo esc_html( $transaction->reference ); ?></code></a>
						</td>
						<?php if ( $show_person ) : ?>
							<td data-label="<?php esc_attr_e( 'شخص', 'sparkknife-ledger' ); ?>">
								<a href="<?php echo esc_url( Sparkknife_Ledger_Renderer::url( 'persons', array( 'action' => 'view', 'id' => (int) $transaction->person_id ) ) ); ?>">
									<?php echo esc_html( (string) $transaction->person_name ); ?>
								</a>
							</td>
						<?php endif; ?>
						<td data-label="<?php esc_attr_e( 'نوع', 'sparkknife-ledger' ); ?>">
							<span class="spk-type spk-type--<?php echo esc_attr( $transaction->type ); ?>">
								<span class="dashicons <?php echo 'income' === $transaction->type ? 'dashicons-arrow-down-alt' : 'dashicons-arrow-up-alt'; ?>" aria-hidden="true"></span>
								<?php echo esc_html( Sparkknife_Ledger_Money::transaction_type_label( $transaction->type ) ); ?>
							</span>
						</td>
						<td data-label="<?php esc_attr_e( 'مبلغ', 'sparkknife-ledger' ); ?>">
							<span class="spk-amount <?php echo $is_void ? 'is-void' : ( 'income' === $transaction->type ? 'spk-money-positive' : 'spk-money-negative' ); ?>">
								<?php echo esc_html( Sparkknife_Ledger_Money::format( $transaction->amount ) ); ?>
							</span>
						</td>
						<td data-label="<?php esc_attr_e( 'تاریخ تراکنش', 'sparkknife-ledger' ); ?>">
							<?php echo esc_html( Sparkknife_Ledger_Date::format( $transaction->txn_date ) ); ?>
						</td>
						<td data-label="<?php esc_attr_e( 'توضیحات', 'sparkknife-ledger' ); ?>">
							<?php if ( $transaction->description ) : ?>
								<span class="spk-table__desc"><?php echo esc_html( wp_trim_words( (string) $transaction->description, 10, '…' ) ); ?></span>
							<?php else : ?>
								<span class="spk-muted">—</span>
							<?php endif; ?>
						</td>
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
						<td class="spk-table__actions">
							<a class="spk-btn spk-btn--icon" href="<?php echo esc_url( $view_url ); ?>" title="<?php esc_attr_e( 'مشاهده', 'sparkknife-ledger' ); ?>">
								<span class="dashicons dashicons-visibility" aria-hidden="true"></span>
								<span class="screen-reader-text"><?php esc_html_e( 'مشاهده', 'sparkknife-ledger' ); ?></span>
							</a>
							<?php if ( ! $is_void && Sparkknife_Ledger_Capabilities::current_user_can( 'manage_transactions' ) ) : ?>
								<a class="spk-btn spk-btn--icon" href="<?php echo esc_url( Sparkknife_Ledger_Renderer::url( 'transactions', array( 'action' => 'edit', 'id' => (int) $transaction->id ) ) ); ?>" title="<?php esc_attr_e( 'ویرایش', 'sparkknife-ledger' ); ?>">
									<span class="dashicons dashicons-edit" aria-hidden="true"></span>
									<span class="screen-reader-text"><?php esc_html_e( 'ویرایش', 'sparkknife-ledger' ); ?></span>
								</a>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
<?php endif; ?>
