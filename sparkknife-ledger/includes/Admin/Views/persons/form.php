<?php
/**
 * فرم ایجاد/ویرایش شخص.
 *
 * @var object|null $person  شخص (null برای ایجاد).
 * @var array|null  $summary خلاصه مالی.
 * @var array       $history تاریخچه.
 * @var string      $current صفحه جاری.
 *
 * @package SparKnife_Ledger
 */

defined( 'ABSPATH' ) || exit;

$is_edit = ! empty( $person );
$title   = $is_edit ? __( 'ویرایش شخص', 'sparkknife-ledger' ) : __( 'افزودن شخص جدید', 'sparkknife-ledger' );

$values = array(
	'name'        => $is_edit ? $person->name : '',
	'phone'       => $is_edit ? $person->phone : '',
	'description' => $is_edit ? $person->description : '',
	'status'      => $is_edit ? $person->status : 'active',
);

$header_actions = array(
	array(
		'label' => __( 'بازگشت به فهرست', 'sparkknife-ledger' ),
		'url'   => Sparkknife_Ledger_Renderer::url( 'persons' ),
		'icon'  => 'dashicons-arrow-right-alt',
	),
);

if ( $is_edit ) {
	$header_actions[] = array(
		'label' => __( 'مشاهده حساب', 'sparkknife-ledger' ),
		'url'   => Sparkknife_Ledger_Renderer::url( 'persons', array( 'action' => 'view', 'id' => (int) $person->id ) ),
		'icon'  => 'dashicons-visibility',
	);
}

require __DIR__ . '/../partials/header.php';
require __DIR__ . '/../partials/nav.php';

$current_url = $is_edit
	? Sparkknife_Ledger_Renderer::url( 'persons', array( 'action' => 'edit', 'id' => (int) $person->id ) )
	: Sparkknife_Ledger_Renderer::url( 'persons', array( 'action' => 'new' ) );
?>

<div class="spk-grid spk-grid--sidebar">
	<section class="spk-card">
		<div class="spk-card__header">
			<h2><?php echo esc_html( $title ); ?></h2>
			<?php if ( $is_edit ) : ?>
				<span class="spk-muted">
					<?php
					printf(
						/* translators: %s: creation date */
						esc_html__( 'تاریخ ایجاد: %s', 'sparkknife-ledger' ),
						esc_html( Sparkknife_Ledger_Date::format( $person->created_at, true ) )
					);
					?>
				</span>
			<?php endif; ?>
		</div>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="spk-form" novalidate>
			<input type="hidden" name="action" value="sparkknife_ledger_action" />
			<input type="hidden" name="sparkknife_action" value="person_save" />
			<input type="hidden" name="sparkknife_redirect" value="<?php echo esc_attr( $current_url ); ?>" />
			<input type="hidden" name="person_id" value="<?php echo esc_attr( (string) ( $is_edit ? (int) $person->id : 0 ) ); ?>" />
			<?php wp_nonce_field( 'sparkknife_ledger_person_save' ); ?>

			<div class="spk-form__row">
				<?php
				Sparkknife_Ledger_Renderer::view(
					'partials/form-field',
					array(
						'name'     => 'name',
						'label'    => __( 'نام شخص', 'sparkknife-ledger' ),
						'value'    => $values['name'],
						'required' => true,
						'hint'     => __( 'نام کامل یا عنوان حساب (مثلاً «آقای احمدی» یا «شرکت پخش نمونه»).', 'sparkknife-ledger' ),
						'extra'    => 'maxlength="190" autocomplete="name"',
					)
				);
				?>
			</div>

			<div class="spk-form__row spk-form__row--2">
				<?php
				Sparkknife_Ledger_Renderer::view(
					'partials/form-field',
					array(
						'name'    => 'phone',
						'label'   => __( 'شماره تماس (اختیاری)', 'sparkknife-ledger' ),
						'type'    => 'tel',
						'value'   => $values['phone'],
						'hint'    => __( 'ارقام فارسی نیز پذیرفته می‌شوند.', 'sparkknife-ledger' ),
						'extra'   => 'maxlength="40" dir="ltr" autocomplete="tel" inputmode="tel"',
					)
				);

				Sparkknife_Ledger_Renderer::view(
					'partials/form-field',
					array(
						'name'    => 'status',
						'label'   => __( 'وضعیت', 'sparkknife-ledger' ),
						'type'    => 'select',
						'value'   => $values['status'],
						'options' => Sparkknife_Ledger_Person_Service::statuses(),
						'hint'    => __( 'برای اشخاص غیرفعال امکان ثبت تراکنش جدید وجود ندارد، اما مانده و سوابق حفظ می‌شود.', 'sparkknife-ledger' ),
					)
				);
				?>
			</div>

			<div class="spk-form__row">
				<?php
				Sparkknife_Ledger_Renderer::view(
					'partials/form-field',
					array(
						'name'  => 'description',
						'label' => __( 'توضیحات', 'sparkknife-ledger' ),
						'type'  => 'textarea',
						'value' => $values['description'],
						'hint'  => __( 'اطلاعات تکمیلی مانند آدرس، کد اقتصادی یا یادداشت داخلی.', 'sparkknife-ledger' ),
						'rows'  => 4,
						'extra' => 'maxlength="2000"',
					)
				);
				?>
			</div>

			<div class="spk-form__actions">
				<button type="submit" class="spk-btn spk-btn--primary">
					<span class="dashicons dashicons-yes" aria-hidden="true"></span>
					<?php echo esc_html( $is_edit ? __( 'ذخیره تغییرات', 'sparkknife-ledger' ) : __( 'ثبت شخص', 'sparkknife-ledger' ) ); ?>
				</button>
				<a class="spk-btn spk-btn--ghost" href="<?php echo esc_url( Sparkknife_Ledger_Renderer::url( 'persons' ) ); ?>">
					<?php esc_html_e( 'انصراف', 'sparkknife-ledger' ); ?>
				</a>
			</div>
		</form>
	</section>

	<aside class="spk-sidebar">
		<?php if ( $is_edit && $summary ) : ?>
			<section class="spk-card">
				<h2 class="spk-card__title"><?php esc_html_e( 'خلاصه حساب', 'sparkknife-ledger' ); ?></h2>
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
						<dt><?php esc_html_e( 'مانده', 'sparkknife-ledger' ); ?></dt>
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
			</section>

			<section class="spk-card">
				<h2 class="spk-card__title"><?php esc_html_e( 'عملیات', 'sparkknife-ledger' ); ?></h2>
				<div class="spk-stack">
					<a class="spk-btn spk-btn--primary spk-btn--block" href="<?php echo esc_url( Sparkknife_Ledger_Renderer::url( 'transactions', array( 'action' => 'new', 'person' => (int) $person->id ) ) ); ?>">
						<span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
						<?php esc_html_e( 'ثبت تراکنش جدید', 'sparkknife-ledger' ); ?>
					</a>

					<?php if ( 'active' === $person->status ) : ?>
						<?php
						Sparkknife_Ledger_Renderer::view(
							'partials/action-button',
							array(
								'action'   => 'person_status',
								'label'    => __( 'غیرفعال کردن شخص', 'sparkknife-ledger' ),
								'icon'     => 'dashicons-hidden',
								'fields'   => array(
									'person_id' => (int) $person->id,
									'status'    => 'inactive',
								),
								'class'    => 'spk-btn spk-btn--ghost spk-btn--block',
								'confirm'  => __( 'با غیرفعال شدن، ثبت تراکنش جدید برای این شخص ممکن نیست. ادامه می‌دهید؟', 'sparkknife-ledger' ),
								'redirect' => $current_url,
							)
						);
						?>
					<?php else : ?>
						<?php
						Sparkknife_Ledger_Renderer::view(
							'partials/action-button',
							array(
								'action'   => 'person_status',
								'label'    => __( 'فعال کردن شخص', 'sparkknife-ledger' ),
								'icon'     => 'dashicons-visibility',
								'fields'   => array(
									'person_id' => (int) $person->id,
									'status'    => 'active',
								),
								'class'    => 'spk-btn spk-btn--ghost spk-btn--block',
								'redirect' => $current_url,
							)
						);
						?>
					<?php endif; ?>

					<?php
					Sparkknife_Ledger_Renderer::view(
						'partials/action-button',
						array(
							'action'   => 'person_delete',
							'label'    => __( 'حذف شخص', 'sparkknife-ledger' ),
							'icon'     => 'dashicons-trash',
							'fields'   => array( 'person_id' => (int) $person->id ),
							'class'    => 'spk-btn spk-btn--danger-ghost spk-btn--block',
							'confirm'  => __( 'این شخص حذف شود؟ اطلاعات در سوابق باقی می‌ماند و قابل بازگردانی است. اشخاص دارای تراکنش قابل حذف نیستند.', 'sparkknife-ledger' ),
							'redirect' => $current_url,
						)
					);
					?>
				</div>
				<p class="spk-hint-box">
					<span class="dashicons dashicons-shield" aria-hidden="true"></span>
					<?php esc_html_e( 'حذف شخص نرم است: رکورد حذف‌شده در محاسبات نمی‌آید ولی سوابق مالی حفظ می‌شود. برای اشخاص دارای تراکنش، از گزینه غیرفعال‌سازی استفاده کنید.', 'sparkknife-ledger' ); ?>
				</p>
			</section>
		<?php else : ?>
			<section class="spk-card">
				<h2 class="spk-card__title"><?php esc_html_e( 'راهنما', 'sparkknife-ledger' ); ?></h2>
				<ul class="spk-help-list">
					<li><?php esc_html_e( 'نام شخص الزامی است و باید یکتا باشد (نام + شماره تماس).', 'sparkknife-ledger' ); ?></li>
					<li><?php esc_html_e( 'پس از ثبت شخص، می‌توانید تراکنش‌های ورودی و خروجی ثبت کنید.', 'sparkknife-ledger' ); ?></li>
					<li><?php esc_html_e( 'مانده حساب به‌صورت خودکار و لحظه‌ای محاسبه می‌شود.', 'sparkknife-ledger' ); ?></li>
					<li><?php esc_html_e( 'همه تغییرات در تاریخچه ثبت می‌شود.', 'sparkknife-ledger' ); ?></li>
				</ul>
			</section>
		<?php endif; ?>

		<?php if ( ! empty( $history ) ) : ?>
			<section class="spk-card">
				<h2 class="spk-card__title"><?php esc_html_e( 'آخرین تغییرات', 'sparkknife-ledger' ); ?></h2>
				<ul class="spk-timeline">
					<?php foreach ( $history as $entry ) : ?>
						<?php $action_labels = Sparkknife_Ledger_Audit_Service::instance()->actions(); ?>
						<li>
							<span class="spk-timeline__dot" aria-hidden="true"></span>
							<div>
								<strong><?php echo esc_html( isset( $action_labels[ $entry->action ] ) ? $action_labels[ $entry->action ] : $entry->action ); ?></strong>
								<span class="spk-timeline__time"><?php echo esc_html( Sparkknife_Ledger_Date::format( $entry->created_at, true ) ); ?></span>
								<p><?php echo esc_html( (string) $entry->summary ); ?></p>
							</div>
						</li>
					<?php endforeach; ?>
				</ul>
			</section>
		<?php endif; ?>
	</aside>
</div>

<?php require __DIR__ . '/../partials/footer.php'; ?>
