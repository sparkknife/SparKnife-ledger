<?php
/**
 * تاریخچه تغییرات.
 *
 * @var array  $result   نتیجه کوئری.
 * @var array  $args     فیلترها.
 * @var array  $range    بازه.
 * @var string $base_url نشانی پایه.
 * @var string $current  صفحه جاری.
 * @var array  $users    کاربران.
 *
 * @package SparKnife_Ledger
 */

defined( 'ABSPATH' ) || exit;

$header_actions = array(
	array(
		'label' => __( 'بازگشت به داشبورد', 'sparkknife-ledger' ),
		'url'   => Sparkknife_Ledger_Renderer::url( 'dashboard' ),
		'icon'  => 'dashicons-dashboard',
	),
);

require __DIR__ . '/../partials/header.php';
require __DIR__ . '/../partials/nav.php';

$audit         = Sparkknife_Ledger_Audit_Service::instance();
$actions       = $audit->actions();
$object_labels = array(
	'person'      => __( 'شخص', 'sparkknife-ledger' ),
	'transaction' => __( 'تراکنش', 'sparkknife-ledger' ),
	'settings'    => __( 'تنظیمات', 'sparkknife-ledger' ),
);
?>

<div class="spk-alert spk-alert--info">
	<span class="dashicons dashicons-shield-alt" aria-hidden="true"></span>
	<span><?php esc_html_e( 'همه تغییرات مهم (ایجاد، ویرایش مبلغ/نوع/شخص/وضعیت، ابطال، تغییر تنظیمات و دسترسی‌ها) با کاربر، زمان، IP و مقادیر قبل/بعد ثبت می‌شوند تا قابل پیگیری باشند.', 'sparkknife-ledger' ); ?></span>
</div>

<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="spk-filter">
	<input type="hidden" name="page" value="sparkknife-ledger-audit" />
	<div class="spk-filter__row">
		<div class="spk-field spk-field--compact">
			<label class="spk-field__label" for="spk-audit-type"><?php esc_html_e( 'نوع موجودیت', 'sparkknife-ledger' ); ?></label>
			<select name="object_type" id="spk-audit-type" class="spk-input">
				<option value=""><?php esc_html_e( 'همه', 'sparkknife-ledger' ); ?></option>
				<?php foreach ( $object_labels as $key => $label ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $args['object_type'], $key ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>

		<div class="spk-field spk-field--compact">
			<label class="spk-field__label" for="spk-audit-action"><?php esc_html_e( 'عملیات', 'sparkknife-ledger' ); ?></label>
			<select name="audit_action" id="spk-audit-action" class="spk-input">
				<option value=""><?php esc_html_e( 'همه عملیات', 'sparkknife-ledger' ); ?></option>
				<?php foreach ( $actions as $key => $label ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $args['action'], $key ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>

		<div class="spk-field spk-field--compact">
			<label class="spk-field__label" for="spk-audit-user"><?php esc_html_e( 'کاربر', 'sparkknife-ledger' ); ?></label>
			<select name="user_id" id="spk-audit-user" class="spk-input">
				<option value="0"><?php esc_html_e( 'همه کاربران', 'sparkknife-ledger' ); ?></option>
				<?php foreach ( $users as $user ) : ?>
					<option value="<?php echo esc_attr( (string) $user->ID ); ?>" <?php selected( $args['user_id'], (int) $user->ID ); ?>>
						<?php echo esc_html( $user->display_name ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</div>

		<div class="spk-field spk-field--compact">
			<label class="spk-field__label" for="spk-audit-range"><?php esc_html_e( 'بازه زمانی', 'sparkknife-ledger' ); ?></label>
			<select name="range" id="spk-audit-range" class="spk-input">
				<?php foreach ( Sparkknife_Ledger_Ledger_Service::preset_ranges() as $key => $data ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $args['range'], $key ); ?>><?php echo esc_html( $data['label'] ); ?></option>
				<?php endforeach; ?>
				<option value="custom" <?php selected( $args['range'], 'custom' ); ?>><?php esc_html_e( 'بازه دلخواه', 'sparkknife-ledger' ); ?></option>
			</select>
		</div>

		<div class="spk-field spk-field--compact">
			<label class="spk-field__label" for="spk-audit-object"><?php esc_html_e( 'شناسه موجودیت', 'sparkknife-ledger' ); ?></label>
			<input type="number" class="spk-input" id="spk-audit-object" name="object_id" min="0"
				value="<?php echo esc_attr( $args['object_id'] ? (string) $args['object_id'] : '' ); ?>" placeholder="0" />
		</div>

		<div class="spk-filter__buttons">
			<button type="submit" class="spk-btn spk-btn--primary">
				<span class="dashicons dashicons-filter" aria-hidden="true"></span>
				<?php esc_html_e( 'اعمال فیلتر', 'sparkknife-ledger' ); ?>
			</button>
			<a class="spk-btn spk-btn--ghost" href="<?php echo esc_url( Sparkknife_Ledger_Renderer::url( 'audit' ) ); ?>"><?php esc_html_e( 'حذف فیلترها', 'sparkknife-ledger' ); ?></a>
		</div>
	</div>
</form>

<section class="spk-card" id="spk-results">
	<div class="spk-card__header">
		<h2>
			<?php
			printf(
				/* translators: %s: count */
				esc_html__( 'رویدادهای ثبت‌شده (%s)', 'sparkknife-ledger' ),
				esc_html( Sparkknife_Ledger_Money::to_persian_digits( (string) $result['total'] ) )
			);
			?>
		</h2>
		<span class="spk-muted"><?php echo esc_html( $range['label'] ); ?></span>
	</div>

	<?php if ( empty( $result['items'] ) ) : ?>
		<?php
		Sparkknife_Ledger_Renderer::view(
			'partials/empty-state',
			array(
				'icon'    => 'dashicons-backup',
				'title'   => __( 'رویدادی برای نمایش نیست', 'sparkknife-ledger' ),
				'message' => __( 'با تغییر اشخاص، تراکنش‌ها یا تنظیمات، رویدادها در این بخش ثبت می‌شوند.', 'sparkknife-ledger' ),
			)
		);
		?>
	<?php else : ?>
		<div class="spk-table-wrap">
			<table class="spk-table spk-table--compact">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'زمان', 'sparkknife-ledger' ); ?></th>
						<th scope="col"><?php esc_html_e( 'کاربر', 'sparkknife-ledger' ); ?></th>
						<th scope="col"><?php esc_html_e( 'موجودیت', 'sparkknife-ledger' ); ?></th>
						<th scope="col"><?php esc_html_e( 'عملیات', 'sparkknife-ledger' ); ?></th>
						<th scope="col"><?php esc_html_e( 'تغییرات', 'sparkknife-ledger' ); ?></th>
						<th scope="col"><?php esc_html_e( 'شرح', 'sparkknife-ledger' ); ?></th>
						<th scope="col"><?php esc_html_e( 'IP', 'sparkknife-ledger' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $result['items'] as $entry ) : ?>
						<?php
						$user    = $entry->user_id ? get_userdata( (int) $entry->user_id ) : null;
						$changes = $audit->decode_changes( $entry );
						?>
						<tr>
							<td data-label="<?php esc_attr_e( 'زمان', 'sparkknife-ledger' ); ?>" class="spk-nowrap">
								<?php echo esc_html( Sparkknife_Ledger_Date::format( $entry->created_at, true ) ); ?>
							</td>
							<td data-label="<?php esc_attr_e( 'کاربر', 'sparkknife-ledger' ); ?>">
								<?php echo esc_html( $user ? $user->display_name : __( 'سیستم', 'sparkknife-ledger' ) ); ?>
							</td>
							<td data-label="<?php esc_attr_e( 'موجودیت', 'sparkknife-ledger' ); ?>">
								<?php echo esc_html( isset( $object_labels[ $entry->object_type ] ) ? $object_labels[ $entry->object_type ] : $entry->object_type ); ?>
								<?php if ( (int) $entry->object_id > 0 ) : ?>
									<code>#<?php echo esc_html( (string) $entry->object_id ); ?></code>
								<?php endif; ?>
							</td>
							<td data-label="<?php esc_attr_e( 'عملیات', 'sparkknife-ledger' ); ?>">
								<span class="spk-badge <?php echo in_array( $entry->action, array( 'void', 'delete', 'purge_data' ), true ) ? 'is-bad' : 'is-neutral'; ?>">
									<?php echo esc_html( isset( $actions[ $entry->action ] ) ? $actions[ $entry->action ] : $entry->action ); ?>
								</span>
							</td>
							<td data-label="<?php esc_attr_e( 'تغییرات', 'sparkknife-ledger' ); ?>">
								<?php if ( empty( $changes ) ) : ?>
									<span class="spk-muted">—</span>
								<?php else : ?>
									<ul class="spk-changes spk-changes--inline">
										<?php foreach ( $changes as $field => $change ) : ?>
											<?php
											if ( ! is_array( $change ) || ! array_key_exists( 'from', $change ) ) {
												continue;
											}
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
							</td>
							<td data-label="<?php esc_attr_e( 'شرح', 'sparkknife-ledger' ); ?>">
								<?php echo esc_html( (string) $entry->summary ); ?>
							</td>
							<td data-label="IP" class="spk-nowrap">
								<code dir="ltr"><?php echo esc_html( (string) $entry->ip_address ); ?></code>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>

		<?php
		Sparkknife_Ledger_Renderer::view(
			'partials/pagination',
			array(
				'page'     => $result['page'],
				'pages'    => $result['pages'],
				'total'    => $result['total'],
				'per_page' => $result['per_page'],
				'base_url' => $base_url,
				'arg'      => 'paged',
			)
		);
		?>
	<?php endif; ?>
</section>

<?php require __DIR__ . '/../partials/footer.php'; ?>
