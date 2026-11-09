<?php
/**
 * صفحه تنظیمات.
 *
 * @var string $tab      تب فعال.
 * @var array  $tabs     تب‌ها.
 * @var array  $settings تنظیمات.
 * @var array  $health   وضعیت جداول.
 * @var array  $matrix   ماتریس دسترسی.
 * @var array  $roles    نقش‌ها.
 * @var array  $caps     Capabilityها.
 * @var string $current  صفحه جاری.
 *
 * @author  SparKnife <155084046+sparkknife@users.noreply.github.com>
 *
 * @package SparKnife_Ledger
 */

defined( 'ABSPATH' ) || exit;

require __DIR__ . '/../partials/header.php';
require __DIR__ . '/../partials/nav.php';

$settings_page_url = Sparkknife_Ledger_Renderer::url( 'settings', array( 'tab' => $tab ) );
$currency_presets  = Sparkknife_Ledger_Money::currency_presets();
?>

<div class="spk-tabs spk-tabs--page">
	<?php foreach ( $tabs as $tab_key => $tab_label ) : ?>
		<a class="spk-tab <?php echo $tab === $tab_key ? 'is-active' : ''; ?>"
			href="<?php echo esc_url( Sparkknife_Ledger_Renderer::url( 'settings', array( 'tab' => $tab_key ) ) ); ?>">
			<?php echo esc_html( $tab_label ); ?>
		</a>
	<?php endforeach; ?>
</div>

<?php if ( in_array( $tab, array( 'currency', 'display', 'general' ), true ) ) : ?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="spk-form spk-settings-form">
		<input type="hidden" name="action" value="sparkknife_ledger_action" />
		<input type="hidden" name="sparkknife_action" value="settings_save" />
		<input type="hidden" name="sparkknife_redirect" value="<?php echo esc_attr( $settings_page_url ); ?>" />
		<input type="hidden" name="active_tab" value="<?php echo esc_attr( $tab ); ?>" />
		<?php wp_nonce_field( 'sparkknife_ledger_settings_save' ); ?>

		<?php if ( 'currency' === $tab ) : ?>
			<section class="spk-card">
				<div class="spk-card__header">
					<h2><?php esc_html_e( 'واحد پول', 'sparkknife-ledger' ); ?></h2>
					<span class="spk-muted"><?php esc_html_e( 'واحد پول در تمام صفحات، گزارش‌ها و خروجی‌ها استفاده می‌شود.', 'sparkknife-ledger' ); ?></span>
				</div>

				<fieldset class="spk-fieldset">
					<legend class="spk-fieldset__legend"><?php esc_html_e( 'انتخاب واحد پول', 'sparkknife-ledger' ); ?></legend>
					<div class="spk-radio-cards">
						<?php foreach ( $currency_presets as $key => $preset ) : ?>
							<label class="spk-radio-card <?php echo $settings['currency_type'] === $key ? 'is-selected' : ''; ?>">
								<input type="radio" name="currency_type" value="<?php echo esc_attr( $key ); ?>"
									<?php checked( $settings['currency_type'], $key ); ?> data-currency-toggle />
								<span class="spk-radio-card__title"><?php echo esc_html( $preset['label'] ); ?></span>
								<span class="spk-radio-card__meta">
									<?php echo esc_html( 'custom' === $key ? __( 'تعریف نام و نماد اختصاصی', 'sparkknife-ledger' ) : $preset['code'] ); ?>
								</span>
							</label>
						<?php endforeach; ?>
					</div>
				</fieldset>

				<div class="spk-form__row spk-form__row--3" data-custom-currency>
					<?php
					Sparkknife_Ledger_Renderer::view(
						'partials/form-field',
						array(
							'name'  => 'custom_currency_label',
							'label' => __( 'نام واحد پول دلخواه', 'sparkknife-ledger' ),
							'value' => $settings['custom_currency_label'],
							'hint'  => __( 'مثال: دینار، درهم، سکه', 'sparkknife-ledger' ),
							'extra' => 'maxlength="60"',
						)
					);

					Sparkknife_Ledger_Renderer::view(
						'partials/form-field',
						array(
							'name'  => 'custom_currency_symbol',
							'label' => __( 'نماد نمایش', 'sparkknife-ledger' ),
							'value' => $settings['custom_currency_symbol'],
							'hint'  => __( 'نماد کوتاه کنار مبلغ (اختیاری).', 'sparkknife-ledger' ),
							'extra' => 'maxlength="10"',
						)
					);

					Sparkknife_Ledger_Renderer::view(
						'partials/form-field',
						array(
							'name'  => 'custom_currency_code',
							'label' => __( 'کد واحد پول', 'sparkknife-ledger' ),
							'value' => $settings['custom_currency_code'],
							'hint'  => __( 'برای خروجی‌ها؛ نمونه: IRT', 'sparkknife-ledger' ),
							'extra' => 'maxlength="10" dir="ltr"',
						)
					);
					?>
				</div>

				<div class="spk-form__row spk-form__row--2">
					<?php
					Sparkknife_Ledger_Renderer::view(
						'partials/form-field',
						array(
							'name'    => 'currency_decimals',
							'label'   => __( 'تعداد ارقام اعشار در نمایش', 'sparkknife-ledger' ),
							'type'    => 'select',
							'value'   => $settings['currency_decimals'],
							'options' => array( 0 => __( 'بدون اعشار (۰)', 'sparkknife-ledger' ), 1 => '۱', 2 => '۲', 3 => '۳', 4 => '۴' ),
							'hint'    => __( 'ذخیره‌سازی داخلی همیشه دقیق است؛ این گزینه فقط نمایش را تغییر می‌دهد.', 'sparkknife-ledger' ),
						)
					);

					Sparkknife_Ledger_Renderer::view(
						'partials/form-field',
						array(
							'name'    => 'currency_position',
							'label'   => __( 'جایگاه واحد پول', 'sparkknife-ledger' ),
							'type'    => 'select',
							'value'   => $settings['currency_position'],
							'options' => array(
								'suffix' => __( 'بعد از مبلغ (۱٬۲۰۰ تومان)', 'sparkknife-ledger' ),
								'prefix' => __( 'قبل از مبلغ (تومان ۱٬۲۰۰)', 'sparkknife-ledger' ),
							),
						)
					);
					?>
				</div>

				<div class="spk-preview-box">
					<span class="spk-preview-box__label"><?php esc_html_e( 'پیش‌نمایش نمایش مبلغ', 'sparkknife-ledger' ); ?></span>
					<span class="spk-preview-box__value"><?php echo esc_html( Sparkknife_Ledger_Money::format( 125000000 ) ); ?></span>
					<span class="spk-preview-box__value spk-money-negative"><?php echo esc_html( Sparkknife_Ledger_Money::format( -2500000 ) ); ?></span>
				</div>
			</section>
		<?php elseif ( 'display' === $tab ) : ?>
			<section class="spk-card">
				<h2 class="spk-card__title"><?php esc_html_e( 'نمایش اعداد و تاریخ', 'sparkknife-ledger' ); ?></h2>

				<div class="spk-form__row spk-form__row--2">
					<?php
					Sparkknife_Ledger_Renderer::view(
						'partials/form-field',
						array(
							'name'    => 'calendar',
							'label'   => __( 'تقویم نمایش', 'sparkknife-ledger' ),
							'type'    => 'select',
							'value'   => $settings['calendar'],
							'options' => array(
								'jalali'    => __( 'شمسی (جلالی)', 'sparkknife-ledger' ),
								'gregorian' => __( 'میلادی', 'sparkknife-ledger' ),
							),
							'hint'    => __( 'تاریخ‌ها همیشه به‌صورت میلادی در دیتابیس ذخیره می‌شوند؛ این گزینه فقط نحوه نمایش و ورود را تغییر می‌دهد.', 'sparkknife-ledger' ),
						)
					);

					Sparkknife_Ledger_Renderer::view(
						'partials/form-field',
						array(
							'name'    => 'thousands_separator',
							'label'   => __( 'جداکننده هزارگان', 'sparkknife-ledger' ),
							'type'    => 'select',
							'value'   => $settings['thousands_separator'],
							'options' => array(
								',' => __( 'کاما (1,000)', 'sparkknife-ledger' ),
								'.' => __( 'نقطه (1.000)', 'sparkknife-ledger' ),
								'٬' => __( 'ممیز فارسی (۱٬۰۰۰)', 'sparkknife-ledger' ),
								' ' => __( 'فاصله (1 000)', 'sparkknife-ledger' ),
							),
						)
					);
					?>
				</div>

				<?php
				Sparkknife_Ledger_Renderer::view(
					'partials/form-field',
					array(
						'name'   => 'use_persian_digits',
						'label'  => __( 'نمایش ارقام به‌صورت فارسی (۱۲۳)', 'sparkknife-ledger' ),
						'type'   => 'checkbox',
						'value'  => $settings['use_persian_digits'],
						'hint'   => __( 'ورودی‌های فارسی در فرم‌ها همیشه پذیرفته می‌شوند، مستقل از این تنظیم.', 'sparkknife-ledger' ),
					)
				);
				?>
			</section>
		<?php else : ?>
			<section class="spk-card">
				<h2 class="spk-card__title"><?php esc_html_e( 'تنظیمات عمومی', 'sparkknife-ledger' ); ?></h2>

				<div class="spk-form__row spk-form__row--2">
					<?php
					Sparkknife_Ledger_Renderer::view(
						'partials/form-field',
						array(
							'name'  => 'reference_prefix',
							'label' => __( 'پیشوند شناسه تراکنش', 'sparkknife-ledger' ),
							'value' => $settings['reference_prefix'],
							'hint'  => __( 'شناسه‌ها به شکل PREFIX-YYYYMMDD-0001 ساخته می‌شوند.', 'sparkknife-ledger' ),
							'extra' => 'maxlength="10" dir="ltr"',
						)
					);

					Sparkknife_Ledger_Renderer::view(
						'partials/form-field',
						array(
							'name'  => 'per_page',
							'label' => __( 'تعداد ردیف در هر صفحه', 'sparkknife-ledger' ),
							'type'  => 'number',
							'value' => $settings['per_page'],
							'hint'  => __( 'بین ۵ تا ۲۰۰', 'sparkknife-ledger' ),
							'extra' => 'min="5" max="200" step="5"',
						)
					);
					?>
				</div>

				<div class="spk-form__row spk-form__row--2">
					<?php
					Sparkknife_Ledger_Renderer::view(
						'partials/form-field',
						array(
							'name'    => 'default_transaction_type',
							'label'   => __( 'نوع پیش‌فرض تراکنش', 'sparkknife-ledger' ),
							'type'    => 'select',
							'value'   => $settings['default_transaction_type'],
							'options' => array(
								'income'  => __( 'ورودی', 'sparkknife-ledger' ),
								'expense' => __( 'خروجی', 'sparkknife-ledger' ),
							),
						)
					);
					?>
					<div></div>
				</div>

				<div class="spk-checkbox-list">
					<?php
					Sparkknife_Ledger_Renderer::view(
						'partials/form-field',
						array(
							'name'  => 'require_void_reason',
							'label' => __( 'ثبت دلیل ابطال الزامی باشد', 'sparkknife-ledger' ),
							'type'  => 'checkbox',
							'value' => $settings['require_void_reason'],
						)
					);

					Sparkknife_Ledger_Renderer::view(
						'partials/form-field',
						array(
							'name'  => 'allow_person_delete',
							'label' => __( 'امکان حذف نرم اشخاص بدون سابقه تراکنش', 'sparkknife-ledger' ),
							'type'  => 'checkbox',
							'value' => $settings['allow_person_delete'],
							'hint'  => __( 'اشخاص دارای تراکنش در هر حالت قابل حذف نیستند.', 'sparkknife-ledger' ),
						)
					);

					Sparkknife_Ledger_Renderer::view(
						'partials/form-field',
						array(
							'name'  => 'show_welcome_panel',
							'label' => __( 'نمایش پنل خوش‌آمدگویی در داشبورد خالی', 'sparkknife-ledger' ),
							'type'  => 'checkbox',
							'value' => $settings['show_welcome_panel'],
						)
					);
					?>
				</div>
			</section>
		<?php endif; ?>

		<div class="spk-form__actions spk-form__actions--sticky">
			<button type="submit" class="spk-btn spk-btn--primary">
				<span class="dashicons dashicons-yes" aria-hidden="true"></span>
				<?php esc_html_e( 'ذخیره تنظیمات', 'sparkknife-ledger' ); ?>
			</button>
			<span class="spk-muted"><?php esc_html_e( 'تغییرات تنظیمات در تاریخچه ثبت می‌شود.', 'sparkknife-ledger' ); ?></span>
		</div>
	</form>

<?php elseif ( 'capabilities' === $tab ) : ?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="spk-form">
		<input type="hidden" name="action" value="sparkknife_ledger_action" />
		<input type="hidden" name="sparkknife_action" value="capabilities_save" />
		<input type="hidden" name="sparkknife_redirect" value="<?php echo esc_attr( $settings_page_url ); ?>" />
		<?php wp_nonce_field( 'sparkknife_ledger_capabilities_save' ); ?>

		<section class="spk-card">
			<div class="spk-card__header">
				<h2><?php esc_html_e( 'سطوح دسترسی نقش‌ها', 'sparkknife-ledger' ); ?></h2>
				<span class="spk-muted"><?php esc_html_e( 'نقش مدیر همیشه تمام دسترسی‌ها را دارد.', 'sparkknife-ledger' ); ?></span>
			</div>

			<div class="spk-table-wrap">
				<table class="spk-table spk-table--matrix">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'دسترسی', 'sparkknife-ledger' ); ?></th>
							<th scope="col"><?php esc_html_e( 'مدیر (ثابت)', 'sparkknife-ledger' ); ?></th>
							<?php foreach ( $roles as $role_key => $role_label ) : ?>
								<th scope="col"><?php echo esc_html( $role_label ); ?></th>
							<?php endforeach; ?>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $caps as $cap_key => $cap_label ) : ?>
							<tr>
								<th scope="row">
									<?php echo esc_html( $cap_label ); ?>
									<code class="spk-cap-code"><?php echo esc_html( 'sparkknife_ledger_' . $cap_key ); ?></code>
								</th>
								<td class="spk-center">
									<span class="spk-badge is-ok"><?php esc_html_e( 'دارد', 'sparkknife-ledger' ); ?></span>
								</td>
								<?php foreach ( $roles as $role_key => $role_label ) : ?>
									<td class="spk-center">
										<label class="spk-checkbox spk-checkbox--center">
											<input type="checkbox"
												name="role_caps[<?php echo esc_attr( $role_key ); ?>][]"
												value="<?php echo esc_attr( $cap_key ); ?>"
												<?php checked( in_array( $cap_key, isset( $matrix[ $role_key ] ) ? $matrix[ $role_key ] : array(), true ) ); ?> />
											<span class="screen-reader-text">
												<?php
												printf(
													/* translators: 1: capability, 2: role */
													esc_html__( 'اعطای %1$s به %2$s', 'sparkknife-ledger' ),
													esc_html( $cap_label ),
													esc_html( $role_label )
												);
												?>
											</span>
										</label>
									</td>
								<?php endforeach; ?>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>

			<div class="spk-form__actions">
				<button type="submit" class="spk-btn spk-btn--primary">
					<span class="dashicons dashicons-shield" aria-hidden="true"></span>
					<?php esc_html_e( 'ذخیره دسترسی‌ها', 'sparkknife-ledger' ); ?>
				</button>
			</div>
		</section>
	</form>

<?php else : ?>
	<section class="spk-card">
		<h2 class="spk-card__title"><?php esc_html_e( 'وضعیت جداول دیتابیس', 'sparkknife-ledger' ); ?></h2>
		<div class="spk-table-wrap">
			<table class="spk-table spk-table--compact">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'جدول', 'sparkknife-ledger' ); ?></th>
						<th scope="col"><?php esc_html_e( 'نام', 'sparkknife-ledger' ); ?></th>
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
								<?php
								/*
								 * سه حالت، نه دو.
								 *
								 * پیش‌تر هر وضعیت غیر از `ok` «یافت نشد» نمایش
								 * داده می‌شد. جدولی که وجود دارد ولی ستون‌هایش
								 * ناقص است (ساخت نصفه) «یافت نشد» نبود و این
								 * پیام مدیر را به مسیر اشتباه می‌فرستاد.
								 */
								$spk_status = isset( $table['status'] ) ? (string) $table['status'] : 'missing';

								if ( 'ok' === $spk_status ) {
									$spk_badge_class = 'is-ok';
									$spk_badge_label = __( 'آماده', 'sparkknife-ledger' );
								} elseif ( 'broken' === $spk_status ) {
									$spk_badge_class = 'is-bad';
									$spk_badge_label = __( 'ناقص', 'sparkknife-ledger' );
								} else {
									$spk_badge_class = 'is-bad';
									$spk_badge_label = __( 'یافت نشد', 'sparkknife-ledger' );
								}
								?>
								<span class="spk-badge <?php echo esc_attr( $spk_badge_class ); ?>">
									<?php echo esc_html( $spk_badge_label ); ?>
								</span>
								<?php if ( 'broken' === $spk_status && ! empty( $table['missing_columns'] ) ) : ?>
									<span class="spk-muted spk-small">
										<?php
										printf(
											/* translators: %s: comma separated column names */
											esc_html__( 'ستون‌های غایب: %s', 'sparkknife-ledger' ),
											esc_html( implode( '، ', array_map( 'strval', (array) $table['missing_columns'] ) ) )
										);
										?>
									</span>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( Sparkknife_Ledger_Money::digits( $table['rows'] ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<p class="spk-muted">
			<?php
			printf(
				/* translators: %s: db version */
				esc_html__( 'نسخه اسکیمای نصب‌شده: %s', 'sparkknife-ledger' ),
				esc_html( (string) get_option( 'sparkknife_ledger_db_version', __( 'نامشخص', 'sparkknife-ledger' ) ) )
			);
			?>
		</p>
	</section>

	<section class="spk-card" id="spk-data-export">
		<h2 class="spk-card__title"><?php esc_html_e( 'خروجی کامل داده', 'sparkknife-ledger' ); ?></h2>
		<p>
			<?php esc_html_e( 'همهٔ اشخاص، تراکنش‌ها و تاریخچهٔ تغییرات را در یک فایل CSV بگیرید. این فایل برای آرشیو یا انتقال به سیستم دیگر ساخته شده است.', 'sparkknife-ledger' ); ?>
		</p>
		<p class="spk-muted">
			<?php esc_html_e( 'تراکنش‌های باطل هم در فایل می‌آیند تا تاریخچه کامل بماند. ستون مبلغ به‌صورت عدد صحیح ضرب‌در-۱۰٬۰۰۰ نوشته می‌شود؛ همین باعث می‌شود بازگرداندنش به سیستم بی‌خطا باشد.', 'sparkknife-ledger' ); ?>
		</p>
		<p class="spk-muted">
			<strong><?php esc_html_e( 'این خروجی جای پشتیبان دیتابیس را نمی‌گیرد.', 'sparkknife-ledger' ); ?></strong>
			<?php esc_html_e( 'تنظیمات، دسترسی‌ها و حساب‌های کاربری در آن نیست. پیش از حذف کامل داده‌ها، از هاست خود پشتیبان دیتابیس بگیرید.', 'sparkknife-ledger' ); ?>
		</p>

		<a
			class="spk-btn spk-btn--primary"
			href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=sparkknife_ledger_export&report=full' ), 'sparkknife_ledger_export' ) ); ?>"
		>
			<span class="dashicons dashicons-download" aria-hidden="true"></span>
			<?php esc_html_e( 'گرفتن خروجی کامل', 'sparkknife-ledger' ); ?>
		</a>
	</section>

	<section class="spk-card">
		<h2 class="spk-card__title"><?php esc_html_e( 'سیاست نگهداری داده‌ها', 'sparkknife-ledger' ); ?></h2>
		<p>
			<?php esc_html_e( 'داده‌های مالی این افزونه در زمان غیرفعال‌سازی یا حذف افزونه به‌صورت خودکار پاک نمی‌شوند. برای حذف کامل، باید به‌صورت صریح و با تأیید دستی در این صفحه اقدام کنید.', 'sparkknife-ledger' ); ?>
		</p>
		<p class="spk-muted">
			<?php esc_html_e( 'اطلاعات تراکنش‌ها به‌صورت نرم حذف می‌شوند (ابطال) و برای حسابرسی باقی می‌مانند.', 'sparkknife-ledger' ); ?>
		</p>

		<?php if ( current_user_can( 'manage_options' ) ) : ?>
			<div class="spk-danger-zone">
				<h3><?php esc_html_e( 'حذف کامل داده‌های مالی', 'sparkknife-ledger' ); ?></h3>
				<p><?php esc_html_e( 'این عملیات جداول اشخاص، تراکنش‌ها و تاریخچه را حذف می‌کند و غیرقابل بازگشت است. پیش از انجام، از دیتابیس پشتیبان تهیه کنید.', 'sparkknife-ledger' ); ?></p>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="spk-form"
					data-confirm="<?php esc_attr_e( 'هشدار نهایی: تمام داده‌های مالی حذف می‌شوند. مطمئن هستید؟', 'sparkknife-ledger' ); ?>">
					<input type="hidden" name="action" value="sparkknife_ledger_action" />
					<input type="hidden" name="sparkknife_action" value="data_purge" />
					<input type="hidden" name="sparkknife_redirect" value="<?php echo esc_attr( $settings_page_url ); ?>" />
					<?php wp_nonce_field( 'sparkknife_ledger_data_purge' ); ?>

					<label class="spk-field__label" for="spk-purge-confirm">
						<?php
						printf(
							/* translators: %s: confirmation phrase */
							esc_html__( 'برای تأیید، عبارت %s را وارد کنید:', 'sparkknife-ledger' ),
							'DELETE-ALL-DATA'
						);
						?>
					</label>
					<input type="text" class="spk-input" id="spk-purge-confirm" name="purge_confirm" dir="ltr" autocomplete="off" required />
					<button type="submit" class="spk-btn spk-btn--danger spk-btn--block">
						<span class="dashicons dashicons-trash" aria-hidden="true"></span>
						<?php esc_html_e( 'حذف کامل داده‌ها', 'sparkknife-ledger' ); ?>
					</button>
				</form>
			</div>
		<?php endif; ?>
	</section>
<?php endif; ?>

<?php require __DIR__ . '/../partials/footer.php'; ?>
