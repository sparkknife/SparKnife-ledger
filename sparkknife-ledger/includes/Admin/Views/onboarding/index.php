<?php
/**
 * ویزارد راه‌اندازی اولیه.
 *
 * @var int   $step     مرحلهٔ جاری (۱ تا ۴).
 * @var array $settings تنظیمات فعلی.
 * @var string $current اسلاگ صفحه.
 *
 * @author  SparKnife <155084046+sparkknife@users.noreply.github.com>
 *
 * @package SparKnife_Ledger
 */

defined( 'ABSPATH' ) || exit;

$spk_steps = array(
	1 => __( 'خوش آمدید', 'sparkknife-ledger' ),
	2 => __( 'واحد پول و تقویم', 'sparkknife-ledger' ),
	3 => __( 'اولین شخص', 'sparkknife-ledger' ),
	4 => __( 'پایان', 'sparkknife-ledger' ),
);

$spk_title = __( 'راه‌اندازی SparKnife Ledger', 'sparkknife-ledger' );

require __DIR__ . '/../partials/header.php';
?>

<div class="spk-onboarding" dir="rtl">
	<ol class="spk-steps" aria-label="<?php esc_attr_e( 'مراحل راه‌اندازی', 'sparkknife-ledger' ); ?>">
		<?php foreach ( $spk_steps as $spk_num => $spk_label ) : ?>
			<li class="spk-step <?php echo $spk_num === $step ? 'is-current' : ( $spk_num < $step ? 'is-done' : '' ); ?>">
				<span class="spk-step__num"><?php echo esc_html( (string) $spk_num ); ?></span>
				<span class="spk-step__label"><?php echo esc_html( $spk_label ); ?></span>
			</li>
		<?php endforeach; ?>
	</ol>

	<?php if ( 1 === $step ) : ?>
		<section class="spk-card">
			<h2 class="spk-card__title"><?php esc_html_e( 'به دفتر حساب خوش آمدید', 'sparkknife-ledger' ); ?></h2>

			<p>
				<?php esc_html_e( 'این ویزارد سه تصمیم کوتاه از شما می‌گیرد و بعد شما را به پنل اصلی می‌برد. کمتر از یک دقیقه طول می‌کشد.', 'sparkknife-ledger' ); ?>
			</p>

			<ul class="spk-list">
				<li><?php esc_html_e( 'واحد پول و تقویم را انتخاب می‌کنید.', 'sparkknife-ledger' ); ?></li>
				<li><?php esc_html_e( 'اولین شخص را می‌سازید (اختیاری).', 'sparkknife-ledger' ); ?></li>
				<li><?php esc_html_e( 'وارد پنل می‌شوید و اولین تراکنش را ثبت می‌کنید.', 'sparkknife-ledger' ); ?></li>
			</ul>

			<p class="spk-actions">
				<a class="button button-primary" href="<?php echo esc_url( add_query_arg( array( 'page' => 'sparkknife-ledger-setup', 'step' => 2 ), admin_url( 'admin.php' ) ) ); ?>">
					<?php esc_html_e( 'شروع', 'sparkknife-ledger' ); ?>
				</a>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="spk-inline-form">
					<input type="hidden" name="action" value="sparkknife_ledger_action" />
					<input type="hidden" name="sparkknife_action" value="onboarding_finish" />
					<input type="hidden" name="sparkknife_redirect" value="<?php echo esc_url( admin_url( 'admin.php?page=sparkknife-ledger' ) ); ?>" />
					<?php wp_nonce_field( 'sparkknife_ledger_onboarding_finish' ); ?>
					<button type="submit" class="button button-link">
						<?php esc_html_e( 'رد کردن و رفتن به پنل', 'sparkknife-ledger' ); ?>
					</button>
				</form>
			</p>
		</section>

	<?php elseif ( 2 === $step ) : ?>
		<section class="spk-card">
			<h2 class="spk-card__title"><?php esc_html_e( 'واحد پول و تقویم', 'sparkknife-ledger' ); ?></h2>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="spk-form">
				<input type="hidden" name="action" value="sparkknife_ledger_action" />
				<input type="hidden" name="sparkknife_action" value="onboarding_currency" />
				<input type="hidden" name="sparkknife_redirect" value="<?php echo esc_url( add_query_arg( array( 'page' => 'sparkknife-ledger-setup', 'step' => 3 ), admin_url( 'admin.php' ) ) ); ?>" />
				<?php wp_nonce_field( 'sparkknife_ledger_onboarding_currency' ); ?>

				<fieldset class="spk-fieldset">
					<legend class="spk-field__label"><?php esc_html_e( 'واحد پول', 'sparkknife-ledger' ); ?></legend>

					<?php
					$spk_currencies = array(
						'toman' => __( 'تومان', 'sparkknife-ledger' ),
						'rial'  => __( 'ریال', 'sparkknife-ledger' ),
						'custom' => __( 'واحد دلخواه', 'sparkknife-ledger' ),
					);

					foreach ( $spk_currencies as $spk_key => $spk_label ) :
						?>
						<label class="spk-radio">
							<input
								type="radio"
								name="currency_type"
								value="<?php echo esc_attr( $spk_key ); ?>"
								<?php checked( $settings['currency_type'], $spk_key ); ?>
							/>
							<span><?php echo esc_html( $spk_label ); ?></span>
						</label>
					<?php endforeach; ?>
				</fieldset>

				<fieldset class="spk-fieldset">
					<legend class="spk-field__label"><?php esc_html_e( 'تقویم', 'sparkknife-ledger' ); ?></legend>

					<?php
					$spk_calendars = array(
						'jalali'   => __( 'شمسی (جلالی)', 'sparkknife-ledger' ),
						'gregorian' => __( 'میلادی', 'sparkknife-ledger' ),
					);

					foreach ( $spk_calendars as $spk_key => $spk_label ) :
						?>
						<label class="spk-radio">
							<input
								type="radio"
								name="calendar"
								value="<?php echo esc_attr( $spk_key ); ?>"
								<?php checked( $settings['calendar'], $spk_key ); ?>
							/>
							<span><?php echo esc_html( $spk_label ); ?></span>
						</label>
					<?php endforeach; ?>
				</fieldset>

				<p class="spk-actions">
					<button type="submit" class="button button-primary">
						<?php esc_html_e( 'ذخیره و ادامه', 'sparkknife-ledger' ); ?>
					</button>
					<a class="button button-link" href="<?php echo esc_url( add_query_arg( array( 'page' => 'sparkknife-ledger-setup', 'step' => 1 ), admin_url( 'admin.php' ) ) ); ?>">
						<?php esc_html_e( 'بازگشت', 'sparkknife-ledger' ); ?>
					</a>
				</p>
			</form>
		</section>

	<?php elseif ( 3 === $step ) : ?>
		<section class="spk-card">
			<h2 class="spk-card__title"><?php esc_html_e( 'اولین شخص', 'sparkknife-ledger' ); ?></h2>

			<p class="spk-hint">
				<?php esc_html_e( 'یکی از مشتریان یا همکارانتان را اضافه کنید. می‌توانید این مرحله را رد کنید و بعداً از بخش «اشخاص» انجام دهید.', 'sparkknife-ledger' ); ?>
			</p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="spk-form">
				<input type="hidden" name="action" value="sparkknife_ledger_action" />
				<input type="hidden" name="sparkknife_action" value="onboarding_person" />
				<input type="hidden" name="sparkknife_redirect" value="<?php echo esc_url( add_query_arg( array( 'page' => 'sparkknife-ledger-setup', 'step' => 4 ), admin_url( 'admin.php' ) ) ); ?>" />
				<?php wp_nonce_field( 'sparkknife_ledger_onboarding_person' ); ?>

				<p class="spk-field">
					<label for="spk-onb-name" class="spk-field__label"><?php esc_html_e( 'نام شخص', 'sparkknife-ledger' ); ?></label>
					<input type="text" id="spk-onb-name" name="name" class="regular-text" required maxlength="190" />
				</p>

				<p class="spk-field">
					<label for="spk-onb-phone" class="spk-field__label"><?php esc_html_e( 'شماره تماس (اختیاری)', 'sparkknife-ledger' ); ?></label>
					<input type="text" id="spk-onb-phone" name="phone" class="regular-text" dir="ltr" maxlength="30" />
				</p>

				<p class="spk-actions">
					<button type="submit" class="button button-primary">
						<?php esc_html_e( 'افزودن و ادامه', 'sparkknife-ledger' ); ?>
					</button>
					<a class="button button-link" href="<?php echo esc_url( add_query_arg( array( 'page' => 'sparkknife-ledger-setup', 'step' => 4 ), admin_url( 'admin.php' ) ) ); ?>">
						<?php esc_html_e( 'رد کردن', 'sparkknife-ledger' ); ?>
					</a>
				</p>
			</form>
		</section>

	<?php else : ?>
		<section class="spk-card">
			<h2 class="spk-card__title"><?php esc_html_e( 'آماده است', 'sparkknife-ledger' ); ?></h2>

			<p>
				<?php esc_html_e( 'افزونه آمادهٔ استفاده است. از پنل اصلی می‌توانید شخص اضافه کنید، تراکنش ثبت کنید و گزارش بگیرید.', 'sparkknife-ledger' ); ?>
			</p>

			<ul class="spk-list">
				<li><?php esc_html_e( 'تراکنش‌ها هرگز فیزیکی حذف نمی‌شوند؛ فقط باطل می‌شوند.', 'sparkknife-ledger' ); ?></li>
				<li><?php esc_html_e( 'هر تغییر در تاریخچه ثبت می‌شود.', 'sparkknife-ledger' ); ?></li>
				<li><?php esc_html_e( 'قبل از هر عملیات حساس، پشتیبان بگیرید.', 'sparkknife-ledger' ); ?></li>
			</ul>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="spk-form">
				<input type="hidden" name="action" value="sparkknife_ledger_action" />
				<input type="hidden" name="sparkknife_action" value="onboarding_finish" />
				<input type="hidden" name="sparkknife_redirect" value="<?php echo esc_url( admin_url( 'admin.php?page=sparkknife-ledger' ) ); ?>" />
				<?php wp_nonce_field( 'sparkknife_ledger_onboarding_finish' ); ?>

				<button type="submit" class="button button-primary button-hero">
					<?php esc_html_e( 'رفتن به پنل', 'sparkknife-ledger' ); ?>
				</button>
			</form>
		</section>
	<?php endif; ?>
</div>

<?php require __DIR__ . '/../partials/footer.php'; ?>
