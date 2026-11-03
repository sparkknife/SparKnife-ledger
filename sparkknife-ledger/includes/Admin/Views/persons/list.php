<?php
/**
 * فهرست اشخاص.
 *
 * @var array  $result   نتیجه کوئری.
 * @var array  $balances مانده‌ها.
 * @var array  $args     فیلترها.
 * @var string $view     all|deleted.
 * @var array  $stats    آمار.
 * @var string $base_url نشانی پایه.
 * @var string $current  صفحه جاری.
 *
 * @package SparKnife_Ledger
 */

defined( 'ABSPATH' ) || exit;

$title = 'deleted' === $view ? __( 'اشخاص حذف‌شده', 'sparkknife-ledger' ) : __( 'مدیریت اشخاص', 'sparkknife-ledger' );

$header_actions = array();

if ( 'deleted' !== $view ) {
	$header_actions[] = array(
		'label' => __( 'افزودن شخص جدید', 'sparkknife-ledger' ),
		'url'   => Sparkknife_Ledger_Renderer::url( 'persons', array( 'action' => 'new' ) ),
		'class' => 'spk-btn--primary',
		'icon'  => 'dashicons-plus-alt2',
	);

	$header_actions[] = array(
		'label' => __( 'گزارش تجمیعی', 'sparkknife-ledger' ),
		'url'   => Sparkknife_Ledger_Renderer::url( 'summary' ),
		'icon'  => 'dashicons-chart-bar',
	);
}

require __DIR__ . '/../partials/header.php';
require __DIR__ . '/../partials/nav.php';

$export_args = array(
	'page'   => 'sparkknife-ledger-persons',
	'view'   => $args['view'] ?? '',
);
?>

<div class="spk-toolbar">
	<div class="spk-tabs">
		<a class="spk-tab <?php echo 'deleted' !== $view ? 'is-active' : ''; ?>"
			href="<?php echo esc_url( Sparkknife_Ledger_Renderer::url( 'persons' ) ); ?>">
			<?php esc_html_e( 'اشخاص فعال', 'sparkknife-ledger' ); ?>
			<span class="spk-count"><?php echo esc_html( Sparkknife_Ledger_Money::to_persian_digits( (string) ( $stats['active'] + $stats['inactive'] ) ) ); ?></span>
		</a>
		<a class="spk-tab <?php echo 'deleted' === $view ? 'is-active' : ''; ?>"
			href="<?php echo esc_url( Sparkknife_Ledger_Renderer::url( 'persons', array( 'view' => 'deleted' ) ) ); ?>">
			<?php esc_html_e( 'حذف‌شده‌ها', 'sparkknife-ledger' ); ?>
			<span class="spk-count"><?php echo esc_html( Sparkknife_Ledger_Money::to_persian_digits( (string) $stats['deleted'] ) ); ?></span>
		</a>
	</div>
</div>

<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="spk-filter">
	<input type="hidden" name="page" value="sparkknife-ledger-persons" />
	<?php if ( 'deleted' === $view ) : ?>
		<input type="hidden" name="view" value="deleted" />
	<?php endif; ?>

	<div class="spk-filter__row">
		<div class="spk-field spk-field--compact spk-field--grow">
			<label class="spk-field__label" for="spk-person-search"><?php esc_html_e( 'جستجو', 'sparkknife-ledger' ); ?></label>
			<input type="search" class="spk-input" id="spk-person-search" name="search"
				value="<?php echo esc_attr( $args['search'] ); ?>"
				placeholder="<?php esc_attr_e( 'نام، شماره تماس یا توضیحات…', 'sparkknife-ledger' ); ?>" />
		</div>

		<?php if ( 'deleted' !== $view ) : ?>
			<div class="spk-field spk-field--compact">
				<label class="spk-field__label" for="spk-person-status"><?php esc_html_e( 'وضعیت', 'sparkknife-ledger' ); ?></label>
				<select name="status" id="spk-person-status" class="spk-input">
					<option value=""><?php esc_html_e( 'همه وضعیت‌ها', 'sparkknife-ledger' ); ?></option>
					<option value="active" <?php selected( $args['status'], 'active' ); ?>><?php esc_html_e( 'فعال', 'sparkknife-ledger' ); ?></option>
					<option value="inactive" <?php selected( $args['status'], 'inactive' ); ?>><?php esc_html_e( 'غیرفعال', 'sparkknife-ledger' ); ?></option>
				</select>
			</div>
		<?php endif; ?>

		<div class="spk-field spk-field--compact">
			<label class="spk-field__label" for="spk-person-orderby"><?php esc_html_e( 'مرتب‌سازی', 'sparkknife-ledger' ); ?></label>
			<select name="orderby" id="spk-person-orderby" class="spk-input">
				<option value="created_at" <?php selected( $args['orderby'], 'created_at' ); ?>><?php esc_html_e( 'تاریخ ایجاد', 'sparkknife-ledger' ); ?></option>
				<option value="name" <?php selected( $args['orderby'], 'name' ); ?>><?php esc_html_e( 'نام', 'sparkknife-ledger' ); ?></option>
				<option value="updated_at" <?php selected( $args['orderby'], 'updated_at' ); ?>><?php esc_html_e( 'آخرین ویرایش', 'sparkknife-ledger' ); ?></option>
			</select>
		</div>

		<div class="spk-field spk-field--compact">
			<label class="spk-field__label" for="spk-person-order"><?php esc_html_e( 'ترتیب', 'sparkknife-ledger' ); ?></label>
			<select name="order" id="spk-person-order" class="spk-input">
				<option value="desc" <?php selected( strtolower( $args['order'] ), 'desc' ); ?>><?php esc_html_e( 'نزولی', 'sparkknife-ledger' ); ?></option>
				<option value="asc" <?php selected( strtolower( $args['order'] ), 'asc' ); ?>><?php esc_html_e( 'صعودی', 'sparkknife-ledger' ); ?></option>
			</select>
		</div>

		<div class="spk-filter__buttons">
			<button type="submit" class="spk-btn spk-btn--primary">
				<span class="dashicons dashicons-search" aria-hidden="true"></span>
				<?php esc_html_e( 'جستجو', 'sparkknife-ledger' ); ?>
			</button>
			<a class="spk-btn spk-btn--ghost" href="<?php echo esc_url( Sparkknife_Ledger_Renderer::url( 'persons', 'deleted' === $view ? array( 'view' => 'deleted' ) : array() ) ); ?>">
				<?php esc_html_e( 'بازنشانی', 'sparkknife-ledger' ); ?>
			</a>
		</div>
	</div>
</form>

<section class="spk-card" id="spk-results">
	<?php if ( empty( $result['items'] ) ) : ?>
		<?php
		Sparkknife_Ledger_Renderer::view(
			'partials/empty-state',
			array(
				'icon'        => 'dashicons-groups',
				'title'       => 'deleted' === $view ? __( 'شخص حذف‌شده‌ای وجود ندارد', 'sparkknife-ledger' ) : __( 'هنوز شخصی ثبت نشده است', 'sparkknife-ledger' ),
				'message'     => 'deleted' === $view
					? __( 'اشخاص حذف‌شده در این بخش نمایش داده می‌شوند و قابل بازگردانی هستند.', 'sparkknife-ledger' )
					: __( 'برای ثبت تراکنش، ابتدا شخص را اضافه کنید.', 'sparkknife-ledger' ),
				'action_url'  => 'deleted' === $view ? '' : Sparkknife_Ledger_Renderer::url( 'persons', array( 'action' => 'new' ) ),
				'action_text' => __( 'افزودن شخص جدید', 'sparkknife-ledger' ),
			)
		);
		?>
	<?php else : ?>
		<div class="spk-table-wrap">
			<table class="spk-table spk-table--hover">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'نام', 'sparkknife-ledger' ); ?></th>
						<th scope="col"><?php esc_html_e( 'شماره تماس', 'sparkknife-ledger' ); ?></th>
						<th scope="col"><?php esc_html_e( 'مجموع ورودی', 'sparkknife-ledger' ); ?></th>
						<th scope="col"><?php esc_html_e( 'مجموع خروجی', 'sparkknife-ledger' ); ?></th>
						<th scope="col"><?php esc_html_e( 'مانده', 'sparkknife-ledger' ); ?></th>
						<th scope="col"><?php esc_html_e( 'وضعیت', 'sparkknife-ledger' ); ?></th>
						<th scope="col"><?php esc_html_e( 'تاریخ ایجاد', 'sparkknife-ledger' ); ?></th>
						<th scope="col"><span class="screen-reader-text"><?php esc_html_e( 'عملیات', 'sparkknife-ledger' ); ?></span></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $result['items'] as $person ) : ?>
						<?php
						$person_id = (int) $person->id;
						$summary   = isset( $balances[ $person_id ] ) ? $balances[ $person_id ] : array(
							'income'         => 0,
							'expense'        => 0,
							'balance'        => 0,
							'count'          => 0,
							'account_status' => 'settled',
						);
						$is_deleted = ! empty( $person->deleted_at );
						?>
						<tr>
							<td data-label="<?php esc_attr_e( 'نام', 'sparkknife-ledger' ); ?>">
								<a class="spk-link-strong" href="<?php echo esc_url( Sparkknife_Ledger_Renderer::url( 'persons', array( 'action' => 'view', 'id' => $person_id ) ) ); ?>">
									<?php echo esc_html( $person->name ); ?>
								</a>
								<?php if ( $person->description ) : ?>
									<span class="spk-table__hint"><?php echo esc_html( wp_trim_words( (string) $person->description, 8, '…' ) ); ?></span>
								<?php endif; ?>
							</td>
							<td data-label="<?php esc_attr_e( 'شماره تماس', 'sparkknife-ledger' ); ?>">
								<?php if ( $person->phone ) : ?>
									<a dir="ltr" class="spk-phone" href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', (string) $person->phone ) ); ?>">
										<?php echo esc_html( $person->phone ); ?>
									</a>
								<?php else : ?>
									<span class="spk-muted">—</span>
								<?php endif; ?>
							</td>
							<td data-label="<?php esc_attr_e( 'مجموع ورودی', 'sparkknife-ledger' ); ?>" class="spk-money-positive">
								<?php echo esc_html( Sparkknife_Ledger_Money::format( $summary['income'] ) ); ?>
							</td>
							<td data-label="<?php esc_attr_e( 'مجموع خروجی', 'sparkknife-ledger' ); ?>" class="spk-money-negative">
								<?php echo esc_html( Sparkknife_Ledger_Money::format( $summary['expense'] ) ); ?>
							</td>
							<td data-label="<?php esc_attr_e( 'مانده', 'sparkknife-ledger' ); ?>">
								<span class="spk-balance <?php echo Sparkknife_Ledger_Money::is_positive( $summary['balance'] ) ? 'is-positive' : ( Sparkknife_Ledger_Money::is_negative( $summary['balance'] ) ? 'is-negative' : 'is-zero' ); ?>">
									<?php echo esc_html( Sparkknife_Ledger_Money::format( $summary['balance'] ) ); ?>
								</span>
								<span class="spk-table__hint">
									<?php
									printf(
										/* translators: %s: account status label */
										esc_html__( '%s', 'sparkknife-ledger' ),
										esc_html( Sparkknife_Ledger_Ledger_Service::status_label( $summary['account_status'] ) )
									);
									?>
								</span>
							</td>
							<td data-label="<?php esc_attr_e( 'وضعیت', 'sparkknife-ledger' ); ?>">
								<?php if ( $is_deleted ) : ?>
									<span class="spk-badge is-void"><?php esc_html_e( 'حذف‌شده', 'sparkknife-ledger' ); ?></span>
								<?php else : ?>
									<?php
									Sparkknife_Ledger_Renderer::view(
										'partials/status-badge',
										array(
											'type'   => 'person',
											'status' => $person->status,
										)
									);
									?>
								<?php endif; ?>
							</td>
							<td data-label="<?php esc_attr_e( 'تاریخ ایجاد', 'sparkknife-ledger' ); ?>">
								<?php echo esc_html( Sparkknife_Ledger_Date::format( $person->created_at ) ); ?>
							</td>
							<td class="spk-table__actions">
								<?php if ( $is_deleted ) : ?>
									<?php
									$restore_url = admin_url( 'admin-post.php' );
									Sparkknife_Ledger_Renderer::view(
										'partials/action-button',
										array(
											'action'  => 'person_restore',
											'label'   => __( 'بازگردانی', 'sparkknife-ledger' ),
											'icon'    => 'dashicons-undo',
											'fields'  => array( 'person_id' => $person_id ),
											'confirm' => __( 'این شخص بازگردانی شود؟', 'sparkknife-ledger' ),
										)
									);
									?>
								<?php else : ?>
									<a class="spk-btn spk-btn--icon" href="<?php echo esc_url( Sparkknife_Ledger_Renderer::url( 'persons', array( 'action' => 'edit', 'id' => $person_id ) ) ); ?>"
										title="<?php esc_attr_e( 'ویرایش', 'sparkknife-ledger' ); ?>">
										<span class="dashicons dashicons-edit" aria-hidden="true"></span>
										<span class="screen-reader-text"><?php esc_html_e( 'ویرایش', 'sparkknife-ledger' ); ?></span>
									</a>
									<a class="spk-btn spk-btn--icon" href="<?php echo esc_url( Sparkknife_Ledger_Renderer::url( 'reports', array( 'person_id' => $person_id ) ) ); ?>"
										title="<?php esc_attr_e( 'گزارش مالی', 'sparkknife-ledger' ); ?>">
										<span class="dashicons dashicons-chart-line" aria-hidden="true"></span>
										<span class="screen-reader-text"><?php esc_html_e( 'گزارش مالی', 'sparkknife-ledger' ); ?></span>
									</a>
									<a class="spk-btn spk-btn--icon" href="<?php echo esc_url( Sparkknife_Ledger_Renderer::url( 'transactions', array( 'action' => 'new', 'person' => $person_id ) ) ); ?>"
										title="<?php esc_attr_e( 'ثبت تراکنش برای این شخص', 'sparkknife-ledger' ); ?>">
										<span class="dashicons dashicons-plus-alt" aria-hidden="true"></span>
										<span class="screen-reader-text"><?php esc_html_e( 'ثبت تراکنش', 'sparkknife-ledger' ); ?></span>
									</a>
								<?php endif; ?>
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
