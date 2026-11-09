<?php
/**
 * فرم ایجاد/ویرایش تراکنش.
 *
 * @var object|null $transaction        تراکنش.
 * @var array       $persons            اشخاص فعال.
 * @var int         $preselected_person شخص انتخاب‌شده.
 * @var string      $current            صفحه جاری.
 *
 * @author  SparKnife <155084046+sparkknife@users.noreply.github.com>
 *
 * @package SparKnife_Ledger
 */

defined( 'ABSPATH' ) || exit;

$is_edit = ! empty( $transaction );
$title   = $is_edit
	? sprintf(
		/* translators: %s: reference */
		__( 'ویرایش تراکنش %s', 'sparkknife-ledger' ),
		$transaction->reference
	)
	: __( 'ثبت تراکنش جدید', 'sparkknife-ledger' );

$default_type = $is_edit ? $transaction->type : (string) Sparkknife_Ledger_Settings::get( 'default_transaction_type', 'income' );

$values = array(
	'person_id'   => $is_edit ? (int) $transaction->person_id : (int) $preselected_person,
	'type'        => $default_type,
	'amount'      => $is_edit ? Sparkknife_Ledger_Money::to_human( $transaction->amount, 4 ) : '',
	'txn_date'    => $is_edit ? Sparkknife_Ledger_Date::format_for_input( $transaction->txn_date ) : Sparkknife_Ledger_Date::today_for_input(),
	'description' => $is_edit ? $transaction->description : '',
);

$header_actions = array(
	array(
		'label' => __( 'بازگشت به تراکنش‌ها', 'sparkknife-ledger' ),
		'url'   => Sparkknife_Ledger_Renderer::url( 'transactions' ),
		'icon'  => 'dashicons-arrow-right-alt',
	),
);

if ( $is_edit ) {
	$header_actions[] = array(
		'label' => __( 'مشاهده جزئیات', 'sparkknife-ledger' ),
		'url'   => Sparkknife_Ledger_Renderer::url( 'transactions', array( 'action' => 'view', 'id' => (int) $transaction->id ) ),
		'icon'  => 'dashicons-visibility',
	);
}

require __DIR__ . '/../partials/header.php';
require __DIR__ . '/../partials/nav.php';

$persons_options = array();
foreach ( $persons as $person ) {
	$persons_options[ (int) $person->id ] = $person->name . ( $person->phone ? ' — ' . $person->phone : '' );
}

$form_url = $is_edit
	? Sparkknife_Ledger_Renderer::url( 'transactions', array( 'action' => 'edit', 'id' => (int) $transaction->id ) )
	: Sparkknife_Ledger_Renderer::url( 'transactions', array( 'action' => 'new' ) );
?>

<?php if ( empty( $persons ) ) : ?>
	<div class="spk-alert spk-alert--warning">
		<span class="dashicons dashicons-warning" aria-hidden="true"></span>
		<span><?php esc_html_e( 'برای ثبت تراکنش، حداقل یک شخص فعال لازم است.', 'sparkknife-ledger' ); ?></span>
		<?php if ( Sparkknife_Ledger_Capabilities::current_user_can( 'manage_persons' ) ) : ?>
			<a class="spk-btn spk-btn--sm spk-btn--primary" href="<?php echo esc_url( Sparkknife_Ledger_Renderer::url( 'persons', array( 'action' => 'new' ) ) ); ?>">
				<?php esc_html_e( 'افزودن شخص', 'sparkknife-ledger' ); ?>
			</a>
		<?php endif; ?>
	</div>
<?php endif; ?>

<div class="spk-grid spk-grid--sidebar">
	<section class="spk-card">
		<div class="spk-card__header">
			<h2><?php echo esc_html( $title ); ?></h2>
			<?php if ( $is_edit ) : ?>
				<span class="spk-muted">
					<?php
					printf(
						/* translators: %s: date */
						esc_html__( 'ثبت‌شده در %s', 'sparkknife-ledger' ),
						esc_html( Sparkknife_Ledger_Date::format( $transaction->created_at, true ) )
					);
					?>
				</span>
			<?php endif; ?>
		</div>

		<?php
		/*
		 * بدون `novalidate`.
		 *
		 * پیش‌تر این فرم `novalidate` داشت تا اعتبارسنجی سفارشی
		 * جاوااسکریپت (پیام‌های فارسی) تنها مرجع باشد. ولی فیلدها
		 * `required` هم داشتند و آن صفت هرگز اعمال نمی‌شد — یعنی
		 * اگر JS بارگذاری نمی‌شد (کش، خطای شبکه، افزونهٔ مسدودکننده،
		 * کاربر با JS خاموش)، فرم خالی ارسال می‌شد، صفحه دوباره
		 * بارگذاری می‌شد و کاربر تازه خطا را می‌دید.
		 *
		 * سرور جلوی ذخیرهٔ دادهٔ نامعتبر را می‌گیرد (تأییدشده)، پس
		 * این باگ امنیتی نبود — ولی «صفحه دوباره بارگذاری شد و هیچ
		 * اتفاقی نیفتاد» تجربهٔ بدی است که اعتبارسنجی بومی مرورگر
		 * جلویش را می‌گیرد، حتی بدون جاوااسکریپت.
		 *
		 * جاوااسکریپت همچنان پیام‌های فارسی خودش را نشان می‌دهد؛
		 * فقط دیگر مانع اعتبارسنجی بومی نیست.
		 */
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="spk-form" id="spk-transaction-form">
			<input type="hidden" name="action" value="sparkknife_ledger_action" />
			<input type="hidden" name="sparkknife_action" value="transaction_save" />
			<input type="hidden" name="sparkknife_redirect" value="<?php echo esc_attr( $form_url ); ?>" />
			<input type="hidden" name="transaction_id" value="<?php echo esc_attr( (string) ( $is_edit ? (int) $transaction->id : 0 ) ); ?>" />
			<?php wp_nonce_field( 'sparkknife_ledger_transaction_save' ); ?>

			<fieldset class="spk-fieldset">
				<legend class="spk-fieldset__legend"><?php esc_html_e( 'نوع تراکنش', 'sparkknife-ledger' ); ?></legend>
				<div class="spk-segmented">
					<label class="spk-segmented__option spk-segmented__option--income">
						<input type="radio" name="type" value="income" <?php checked( $values['type'], 'income' ); ?> required />
						<span>
							<span class="dashicons dashicons-arrow-down-alt" aria-hidden="true"></span>
							<?php esc_html_e( 'ورودی (به حساب شخص وارد می‌شود)', 'sparkknife-ledger' ); ?>
						</span>
					</label>
					<label class="spk-segmented__option spk-segmented__option--expense">
						<input type="radio" name="type" value="expense" <?php checked( $values['type'], 'expense' ); ?> required />
						<span>
							<span class="dashicons dashicons-arrow-up-alt" aria-hidden="true"></span>
							<?php esc_html_e( 'خروجی (از حساب شخص خارج می‌شود)', 'sparkknife-ledger' ); ?>
						</span>
					</label>
				</div>
			</fieldset>

			<div class="spk-form__row spk-form__row--2">
				<?php
				Sparkknife_Ledger_Renderer::view(
					'partials/form-field',
					array(
						'name'     => 'person_id',
						'label'    => __( 'شخص', 'sparkknife-ledger' ),
						'type'     => 'select',
						'value'    => $values['person_id'],
						'options'  => $persons_options ? $persons_options : array( 0 => __( 'شخصی وجود ندارد', 'sparkknife-ledger' ) ),
						'required' => true,
					)
				);

				Sparkknife_Ledger_Renderer::view(
					'partials/form-field',
					array(
						'name'        => 'amount',
						'label'       => __( 'مبلغ', 'sparkknife-ledger' ),
						'value'       => $values['amount'],
						'required'    => true,
						'placeholder' => '0',
						'hint'        => sprintf(
							/* translators: %s: currency label */
							__( 'مبلغ به %s؛ جداکننده هزارگان و ممیز اختیاری است.', 'sparkknife-ledger' ),
							Sparkknife_Ledger_Money::currency_label()
						),
						'extra'       => 'maxlength="30" inputmode="decimal" autocomplete="off" dir="ltr" data-amount-input="1"',
						'class'       => 'spk-field--amount',
					)
				);
				?>
			</div>

			<div class="spk-form__row spk-form__row--2">
				<?php
				Sparkknife_Ledger_Renderer::view(
					'partials/form-field',
					array(
						'name'     => 'txn_date',
						'label'    => __( 'تاریخ تراکنش', 'sparkknife-ledger' ),
						'value'    => $values['txn_date'],
						'required' => true,
						'hint'     => Sparkknife_Ledger_Date::uses_jalali()
							? __( 'تاریخ شمسی، نمونه: ۱۴۰۳/۰۵/۱۲', 'sparkknife-ledger' )
							: __( 'تاریخ میلادی، نمونه: 2024/08/02', 'sparkknife-ledger' ),
						'extra'    => 'maxlength="10" inputmode="numeric" autocomplete="off" data-date-input="1"',
					)
				);
				?>
				<div class="spk-field spk-field--preview">
					<span class="spk-field__label"><?php esc_html_e( 'پیش‌نمایش مبلغ', 'sparkknife-ledger' ); ?></span>
					<div class="spk-amount-preview" data-amount-preview aria-live="polite">
						<?php
						/*
						 * parse_amount() در خطا یک WP_Error برمی‌گرداند و WP_Error
						 * در شرط بولی «صدق‌پذیر» است؛ بنابراین الگوی پیشین
						 * `?: 0` هرگز به ۰ نمی‌رسید و format() روی شیء خطا
						 * می‌افتاد و صفحه با خطای مرگبار از کار می‌افتاد.
						 */
						$preview_amount = '' !== $values['amount']
							? Sparkknife_Ledger_Money::parse_amount( $values['amount'] )
							: null;

						echo esc_html( is_string( $preview_amount ) ? Sparkknife_Ledger_Money::format( $preview_amount ) : '—' );
						?>
					</div>
					<p class="spk-field__hint"><?php esc_html_e( 'مبلغ نهایی پس از ذخیره، دقیق و اعشاری ذخیره می‌شود.', 'sparkknife-ledger' ); ?></p>
				</div>
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
						'hint'  => __( 'شرح بابت تراکنش، شماره فاکتور یا یادداشت (اختیاری).', 'sparkknife-ledger' ),
						'rows'  => 3,
						'extra' => 'maxlength="2000"',
					)
				);
				?>
			</div>

			<div class="spk-form__actions">
				<button type="submit" class="spk-btn spk-btn--primary" <?php disabled( empty( $persons ) ); ?>>
					<span class="dashicons dashicons-yes" aria-hidden="true"></span>
					<?php echo esc_html( $is_edit ? __( 'ذخیره تغییرات', 'sparkknife-ledger' ) : __( 'ثبت تراکنش', 'sparkknife-ledger' ) ); ?>
				</button>
				<a class="spk-btn spk-btn--ghost" href="<?php echo esc_url( Sparkknife_Ledger_Renderer::url( 'transactions' ) ); ?>">
					<?php esc_html_e( 'انصراف', 'sparkknife-ledger' ); ?>
				</a>
			</div>
		</form>
	</section>

	<aside class="spk-sidebar">
		<?php if ( $is_edit ) : ?>
			<section class="spk-card">
				<h2 class="spk-card__title"><?php esc_html_e( 'اطلاعات ثبت', 'sparkknife-ledger' ); ?></h2>
				<dl class="spk-dl">
					<div>
						<dt><?php esc_html_e( 'شناسه تراکنش', 'sparkknife-ledger' ); ?></dt>
						<dd><code><?php echo esc_html( $transaction->reference ); ?></code></dd>
					</div>
					<div>
						<dt><?php esc_html_e( 'ثبت‌کننده', 'sparkknife-ledger' ); ?></dt>
						<dd>
							<?php
							$creator = sparkknife_ledger_user_label( $transaction->created_by );
							echo esc_html( $creator );
							?>
						</dd>
					</div>
					<div>
						<dt><?php esc_html_e( 'آخرین ویرایش', 'sparkknife-ledger' ); ?></dt>
						<dd><?php echo esc_html( Sparkknife_Ledger_Date::format( $transaction->updated_at, true ) ); ?></dd>
					</div>
				</dl>
			</section>
		<?php endif; ?>

		<section class="spk-card">
			<h2 class="spk-card__title"><?php esc_html_e( 'قواعد ثبت', 'sparkknife-ledger' ); ?></h2>
			<ul class="spk-help-list">
				<li><?php esc_html_e( 'مبلغ باید بزرگ‌تر از صفر باشد.', 'sparkknife-ledger' ); ?></li>
				<li><?php esc_html_e( 'ثبت تراکنش برای تاریخ آینده مجاز نیست.', 'sparkknife-ledger' ); ?></li>
				<li><?php esc_html_e( 'برای اشخاص غیرفعال امکان ثبت تراکنش جدید وجود ندارد.', 'sparkknife-ledger' ); ?></li>
				<li><?php esc_html_e( 'مانده حساب به‌صورت خودکار و بدون نیاز به محاسبه دستی به‌روزرسانی می‌شود.', 'sparkknife-ledger' ); ?></li>
				<li><?php esc_html_e( 'ابطال تراکنش ثبت‌شده باعث حذف آن از محاسبات می‌شود، اما سابقه حفظ می‌گردد.', 'sparkknife-ledger' ); ?></li>
			</ul>
		</section>
	</aside>
</div>

<?php require __DIR__ . '/../partials/footer.php'; ?>
