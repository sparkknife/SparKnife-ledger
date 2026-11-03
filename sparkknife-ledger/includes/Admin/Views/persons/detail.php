<?php
/**
 * صفحه اختصاصی شخص: همه اطلاعات مالی.
 *
 * @var object $person  شخص.
 * @var array  $report  گزارش فردی.
 * @var array  $args    فیلترها.
 * @var array  $history تاریخچه.
 * @var string $current صفحه جاری.
 * @var string $base_url نشانی پایه.
 *
 * @package SparKnife_Ledger
 */

defined( 'ABSPATH' ) || exit;

$summary = $report['summary'];
$person  = $report['person'];

$header_actions = array(
	array(
		'label' => __( 'ویرایش شخص', 'sparkknife-ledger' ),
		'url'   => Sparkknife_Ledger_Renderer::url( 'persons', array( 'action' => 'edit', 'id' => (int) $person->id ) ),
		'icon'  => 'dashicons-edit',
	),
	array(
		'label' => __( 'ثبت تراکنش', 'sparkknife-ledger' ),
		'url'   => Sparkknife_Ledger_Renderer::url( 'transactions', array( 'action' => 'new', 'person' => (int) $person->id ) ),
		'class' => 'spk-btn--primary',
		'icon'  => 'dashicons-plus-alt2',
	),
	array(
		'label' => __( 'گزارش کامل', 'sparkknife-ledger' ),
		'url'   => Sparkknife_Ledger_Renderer::url( 'reports', array( 'person_id' => (int) $person->id ) ),
		'icon'  => 'dashicons-chart-line',
	),
);

$title       = $person->name;
$description = __( 'صفحه اختصاصی حساب شخص — خلاصه مالی، تراکنش‌ها و سوابق تغییرات', 'sparkknife-ledger' );

require __DIR__ . '/../partials/header.php';
require __DIR__ . '/../partials/nav.php';

$is_deleted = ! empty( $person->deleted_at );

$export_url = wp_nonce_url(
	add_query_arg(
		array(
			'action'    => 'sparkknife_ledger_export',
			'report'    => 'person',
			'person'    => (int) $person->id,
			'range'     => $args['range'],
			'date_from' => $args['date_from'],
			'date_to'   => $args['date_to'],
			'type'      => $args['type'],
			'status'    => $args['status'],
		),
		admin_url( 'admin-post.php' )
	),
	'sparkknife_ledger_export'
);
?>

<?php if ( $is_deleted ) : ?>
	<div class="spk-alert spk-alert--warning">
		<span class="dashicons dashicons-warning" aria-hidden="true"></span>
		<span><?php esc_html_e( 'این شخص حذف (نرم) شده است. سوابق برای حسابرسی نمایش داده می‌شود و می‌توانید او را بازگردانی کنید.', 'sparkknife-ledger' ); ?></span>
		<?php
		Sparkknife_Ledger_Renderer::view(
			'partials/action-button',
			array(
				'action' => 'person_restore',
				'label'  => __( 'بازگردانی شخص', 'sparkknife-ledger' ),
				'fields' => array( 'person_id' => (int) $person->id ),
				'icon'   => 'dashicons-undo',
			)
		);
		?>
	</div>
<?php endif; ?>

<section class="spk-profile">
	<div class="spk-profile__avatar" aria-hidden="true">
		<?php echo esc_html( function_exists( 'mb_substr' ) ? mb_substr( $person->name, 0, 1 ) : substr( $person->name, 0, 1 ) ); ?>
	</div>
	<div class="spk-profile__info">
		<h2><?php echo esc_html( $person->name ); ?></h2>
		<div class="spk-profile__meta">
			<?php if ( $person->phone ) : ?>
				<a dir="ltr" href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', (string) $person->phone ) ); ?>" class="spk-chip">
					<span class="dashicons dashicons-phone" aria-hidden="true"></span>
					<?php echo esc_html( $person->phone ); ?>
				</a>
			<?php endif; ?>
			<span class="spk-chip">
				<span class="dashicons dashicons-calendar" aria-hidden="true"></span>
				<?php
				printf(
					/* translators: %s: date */
					esc_html__( 'عضویت از %s', 'sparkknife-ledger' ),
					esc_html( Sparkknife_Ledger_Date::format( $person->created_at ) )
				);
				?>
			</span>
			<?php
			Sparkknife_Ledger_Renderer::view(
				'partials/status-badge',
				array(
					'type'   => 'person',
					'status' => $is_deleted ? 'inactive' : $person->status,
				)
			);
			?>
		</div>
		<?php if ( $person->description ) : ?>
			<p class="spk-profile__desc"><?php echo esc_html( $person->description ); ?></p>
		<?php endif; ?>
	</div>
</section>

<div class="spk-stats spk-stats--4">
	<article class="spk-stat spk-stat--income">
		<div class="spk-stat__icon"><span class="dashicons dashicons-download" aria-hidden="true"></span></div>
		<div class="spk-stat__body">
			<span class="spk-stat__label"><?php esc_html_e( 'مجموع ورودی‌ها', 'sparkknife-ledger' ); ?></span>
			<span class="spk-stat__value spk-money-positive"><?php echo esc_html( $summary['income_formatted'] ); ?></span>
			<span class="spk-stat__meta"><?php esc_html_e( 'تراکنش‌های معتبر', 'sparkknife-ledger' ); ?></span>
		</div>
	</article>

	<article class="spk-stat spk-stat--expense">
		<div class="spk-stat__icon"><span class="dashicons dashicons-upload" aria-hidden="true"></span></div>
		<div class="spk-stat__body">
			<span class="spk-stat__label"><?php esc_html_e( 'مجموع خروجی‌ها', 'sparkknife-ledger' ); ?></span>
			<span class="spk-stat__value spk-money-negative"><?php echo esc_html( $summary['expense_formatted'] ); ?></span>
			<span class="spk-stat__meta"><?php esc_html_e( 'تراکنش‌های معتبر', 'sparkknife-ledger' ); ?></span>
		</div>
	</article>

	<article class="spk-stat spk-stat--balance <?php echo Sparkknife_Ledger_Money::is_negative( $summary['balance'] ) ? 'is-negative' : 'is-positive'; ?>">
		<div class="spk-stat__icon"><span class="dashicons dashicons-chart-pie" aria-hidden="true"></span></div>
		<div class="spk-stat__body">
			<span class="spk-stat__label"><?php esc_html_e( 'مانده نهایی', 'sparkknife-ledger' ); ?></span>
			<span class="spk-stat__value <?php echo ! Sparkknife_Ledger_Money::is_negative( $summary['balance'] ) ? 'spk-money-positive' : 'spk-money-negative'; ?>">
				<?php echo esc_html( $summary['balance_formatted'] ); ?>
			</span>
			<span class="spk-stat__meta"><?php echo esc_html( Sparkknife_Ledger_Ledger_Service::status_label( $summary['account_status'] ) ); ?></span>
		</div>
	</article>

	<article class="spk-stat spk-stat--txn">
		<div class="spk-stat__icon"><span class="dashicons dashicons-list-view" aria-hidden="true"></span></div>
		<div class="spk-stat__body">
			<span class="spk-stat__label"><?php esc_html_e( 'تعداد تراکنش‌ها', 'sparkknife-ledger' ); ?></span>
			<span class="spk-stat__value"><?php echo esc_html( Sparkknife_Ledger_Money::to_persian_digits( (string) $summary['count'] ) ); ?></span>
			<span class="spk-stat__meta">
				<?php
				printf(
					/* translators: %s: void count */
					esc_html__( '%s تراکنش باطل‌شده', 'sparkknife-ledger' ),
					esc_html( Sparkknife_Ledger_Money::to_persian_digits( (string) $summary['void_count'] ) )
				);
				?>
			</span>
		</div>
	</article>
</div>

<section class="spk-card">
	<div class="spk-card__header">
		<h2><?php esc_html_e( 'تراکنش‌های حساب', 'sparkknife-ledger' ); ?></h2>
		<div class="spk-card__actions">
			<?php if ( Sparkknife_Ledger_Capabilities::current_user_can( 'export_reports' ) ) : ?>
				<a class="spk-btn spk-btn--ghost spk-btn--sm" href="<?php echo esc_url( $export_url ); ?>">
					<span class="dashicons dashicons-download" aria-hidden="true"></span>
					<?php esc_html_e( 'خروجی CSV', 'sparkknife-ledger' ); ?>
				</a>
			<?php endif; ?>
			<a class="spk-btn spk-btn--ghost spk-btn--sm" href="<?php echo esc_url( Sparkknife_Ledger_Renderer::url( 'reports', array( 'person_id' => (int) $person->id ) ) ); ?>">
				<span class="dashicons dashicons-awards" aria-hidden="true"></span>
				<?php esc_html_e( 'گزارش تفصیلی', 'sparkknife-ledger' ); ?>
			</a>
		</div>
	</div>

	<?php
	Sparkknife_Ledger_Renderer::view(
		'partials/transactions-table',
		array(
			'transactions' => $report['transactions'],
			'empty_title'  => __( 'این شخص هنوز تراکنشی ندارد', 'sparkknife-ledger' ),
			'empty_text'   => __( 'با ثبت اولین تراکنش، مانده حساب محاسبه می‌شود.', 'sparkknife-ledger' ),
			'empty_url'    => Sparkknife_Ledger_Renderer::url( 'transactions', array( 'action' => 'new', 'person' => (int) $person->id ) ),
			'show_person'  => false,
		)
	);
	?>
</section>

<div class="spk-grid spk-grid--2">
	<section class="spk-card">
		<h2 class="spk-card__title"><?php esc_html_e( 'سابقه تغییرات شخص', 'sparkknife-ledger' ); ?></h2>
		<?php if ( empty( $history ) ) : ?>
			<p class="spk-muted"><?php esc_html_e( 'تغییری برای این شخص ثبت نشده است.', 'sparkknife-ledger' ); ?></p>
		<?php else : ?>
			<ul class="spk-timeline">
				<?php
				$action_labels = Sparkknife_Ledger_Audit_Service::instance()->actions();
				foreach ( $history as $entry ) :
					$changes    = Sparkknife_Ledger_Audit_Service::instance()->decode_changes( $entry );
					$user       = $entry->user_id ? get_userdata( (int) $entry->user_id ) : null;
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
										if ( ! is_array( $change ) || ! isset( $change['from'] ) ) {
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
							<?php elseif ( $entry->summary ) : ?>
								<p><?php echo esc_html( (string) $entry->summary ); ?></p>
							<?php endif; ?>
						</div>
					</li>
				<?php endforeach; ?>
			</ul>
			<a class="spk-card__link" href="<?php echo esc_url( Sparkknife_Ledger_Renderer::url( 'audit', array( 'object_type' => 'person', 'object_id' => (int) $person->id ) ) ); ?>">
				<?php esc_html_e( 'مشاهده کل تاریخچه این شخص', 'sparkknife-ledger' ); ?>
			</a>
		<?php endif; ?>
	</section>

	<section class="spk-card">
		<h2 class="spk-card__title"><?php esc_html_e( 'اطلاعات ثبت', 'sparkknife-ledger' ); ?></h2>
		<dl class="spk-dl">
			<div>
				<dt><?php esc_html_e( 'شناسه داخلی', 'sparkknife-ledger' ); ?></dt>
				<dd><code>#<?php echo esc_html( (string) $person->id ); ?></code></dd>
			</div>
			<div>
				<dt><?php esc_html_e( 'ثبت‌کننده', 'sparkknife-ledger' ); ?></dt>
				<dd>
					<?php
					$creator = $person->created_by ? get_userdata( (int) $person->created_by ) : null;
					echo esc_html( $creator ? $creator->display_name : __( 'نامشخص', 'sparkknife-ledger' ) );
					?>
				</dd>
			</div>
			<div>
				<dt><?php esc_html_e( 'تاریخ ایجاد', 'sparkknife-ledger' ); ?></dt>
				<dd><?php echo esc_html( Sparkknife_Ledger_Date::format( $person->created_at, true ) ); ?></dd>
			</div>
			<div>
				<dt><?php esc_html_e( 'آخرین ویرایش', 'sparkknife-ledger' ); ?></dt>
				<dd><?php echo esc_html( Sparkknife_Ledger_Date::format( $person->updated_at, true ) ); ?></dd>
			</div>
			<?php if ( $is_deleted ) : ?>
				<div>
					<dt><?php esc_html_e( 'تاریخ حذف', 'sparkknife-ledger' ); ?></dt>
					<dd><?php echo esc_html( Sparkknife_Ledger_Date::format( $person->deleted_at, true ) ); ?></dd>
				</div>
			<?php endif; ?>
		</dl>

		<h3 class="spk-card__subtitle"><?php esc_html_e( 'عملیات حساس', 'sparkknife-ledger' ); ?></h3>
		<div class="spk-stack">
			<?php
			Sparkknife_Ledger_Renderer::view(
				'partials/action-button',
				array(
					'action'   => 'person_delete',
					'label'    => __( 'حذف شخص', 'sparkknife-ledger' ),
					'icon'     => 'dashicons-trash',
					'fields'   => array( 'person_id' => (int) $person->id ),
					'class'    => 'spk-btn spk-btn--danger-ghost spk-btn--block',
					'confirm'  => __( 'این شخص حذف شود؟ سوابق حفظ می‌شود.', 'sparkknife-ledger' ),
					'redirect' => $base_url,
				)
			);
			?>
		</div>
	</section>
</div>

<?php require __DIR__ . '/../partials/footer.php'; ?>
