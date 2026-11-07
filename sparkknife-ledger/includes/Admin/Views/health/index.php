<?php
/**
 * صفحهٔ سلامت سیستم و امنیت.
 *
 * @var array $checks      بررسی‌های سلامت.
 * @var array $log         گزارش امنیتی.
 * @var array $license     وضعیت لایسنس.
 * @var string $license_key کلید فعلی.
 * @var string $current    اسلاگ صفحه.
 *
 * @author  SparKnife <155084046+sparkknife@users.noreply.github.com>
 *
 * @package SparKnife_Ledger
 */

defined( 'ABSPATH' ) || exit;

$spk_ok    = 0;
$spk_warn  = 0;
$spk_fail  = 0;

foreach ( $checks as $spk_check ) {
	if ( 'ok' === $spk_check['status'] ) {
		++$spk_ok;
	} elseif ( 'fail' === $spk_check['status'] ) {
		++$spk_fail;
	} else {
		++$spk_warn;
	}
}

$spk_title = __( 'سلامت، امنیت و لایسنس', 'sparkknife-ledger' );

require __DIR__ . '/../partials/header.php';
?>

<section class="spk-card">
	<h2 class="spk-card__title"><?php esc_html_e( 'وضعیت کلی', 'sparkknife-ledger' ); ?></h2>

	<div class="spk-health-summary">
		<div class="spk-health-pill is-ok">
			<span class="spk-health-pill__num"><?php echo esc_html( (string) $spk_ok ); ?></span>
			<span class="spk-health-pill__label"><?php esc_html_e( 'سالم', 'sparkknife-ledger' ); ?></span>
		</div>
		<div class="spk-health-pill is-warn">
			<span class="spk-health-pill__num"><?php echo esc_html( (string) $spk_warn ); ?></span>
			<span class="spk-health-pill__label"><?php esc_html_e( 'توجه', 'sparkknife-ledger' ); ?></span>
		</div>
		<div class="spk-health-pill is-fail">
			<span class="spk-health-pill__num"><?php echo esc_html( (string) $spk_fail ); ?></span>
			<span class="spk-health-pill__label"><?php esc_html_e( 'مشکل', 'sparkknife-ledger' ); ?></span>
		</div>
	</div>

	<?php if ( 0 === $spk_fail && 0 === $spk_warn ) : ?>
		<p class="spk-health-note is-ok">
			<?php esc_html_e( 'همه بررسی‌ها سالم‌اند. افزونه آمادهٔ استفادهٔ واقعی است.', 'sparkknife-ledger' ); ?>
		</p>
	<?php endif; ?>
</section>

<section class="spk-card">
	<h2 class="spk-card__title"><?php esc_html_e( 'بررسی‌های امنیتی', 'sparkknife-ledger' ); ?></h2>

	<table class="spk-table spk-health-table">
		<thead>
			<tr>
				<th scope="col"><?php esc_html_e( 'بررسی', 'sparkknife-ledger' ); ?></th>
				<th scope="col"><?php esc_html_e( 'وضعیت', 'sparkknife-ledger' ); ?></th>
				<th scope="col"><?php esc_html_e( 'توضیح', 'sparkknife-ledger' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $checks as $spk_check ) : ?>
				<?php
				$spk_status = isset( $spk_check['status'] ) ? $spk_check['status'] : 'info';
				$spk_labels = array(
					'ok'   => __( 'سالم', 'sparkknife-ledger' ),
					'warn' => __( 'توجه', 'sparkknife-ledger' ),
					'fail' => __( 'مشکل', 'sparkknife-ledger' ),
					'info' => __( 'اطلاع', 'sparkknife-ledger' ),
				);
				?>
				<tr>
					<th scope="row"><?php echo esc_html( $spk_check['label'] ); ?></th>
					<td>
						<span class="spk-badge is-<?php echo esc_attr( $spk_status ); ?>">
							<?php echo esc_html( isset( $spk_labels[ $spk_status ] ) ? $spk_labels[ $spk_status ] : $spk_status ); ?>
						</span>
					</td>
					<td><?php echo esc_html( $spk_check['detail'] ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</section>

<?php /* ── لایسنس ─────────────────────────────────────────────── */ ?>
<section class="spk-card">
	<h2 class="spk-card__title"><?php esc_html_e( 'لایسنس', 'sparkknife-ledger' ); ?></h2>

	<?php if ( ! empty( $license['valid'] ) ) : ?>
		<p class="spk-health-note is-ok">
			<?php echo esc_html( $license['message'] ); ?>
		</p>

		<table class="spk-table">
			<tbody>
				<tr>
					<th scope="row"><?php esc_html_e( 'نسخه', 'sparkknife-ledger' ); ?></th>
					<td><code dir="ltr"><?php echo esc_html( $license['edition'] ); ?></code></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'دامنه', 'sparkknife-ledger' ); ?></th>
					<td><code dir="ltr"><?php echo esc_html( $license['domain'] ); ?></code></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'انقضا', 'sparkknife-ledger' ); ?></th>
					<td>
						<?php
						echo esc_html(
							'' !== $license['expiry']
								? $license['expiry']
								: __( 'بدون انقضا', 'sparkknife-ledger' )
						);
						?>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'سریال', 'sparkknife-ledger' ); ?></th>
					<td><code dir="ltr"><?php echo esc_html( $license['serial'] ); ?></code></td>
				</tr>
			</tbody>
		</table>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="spk-form">
			<input type="hidden" name="action" value="sparkknife_ledger_action" />
			<input type="hidden" name="sparkknife_action" value="license_remove" />
			<input type="hidden" name="sparkknife_redirect" value="<?php echo esc_url( admin_url( 'admin.php?page=sparkknife-ledger-health' ) ); ?>" />
			<?php wp_nonce_field( 'sparkknife_ledger_license_remove' ); ?>
			<button type="submit" class="button">
				<?php esc_html_e( 'حذف لایسنس از این سایت', 'sparkknife-ledger' ); ?>
			</button>
		</form>

	<?php else : ?>
		<p class="spk-health-note is-warn">
			<?php echo esc_html( $license['message'] ); ?>
		</p>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="spk-form">
			<input type="hidden" name="action" value="sparkknife_ledger_action" />
			<input type="hidden" name="sparkknife_action" value="license_save" />
			<input type="hidden" name="sparkknife_redirect" value="<?php echo esc_url( admin_url( 'admin.php?page=sparkknife-ledger-health' ) ); ?>" />
			<?php wp_nonce_field( 'sparkknife_ledger_license_save' ); ?>

			<p class="spk-field">
				<label for="spk-license-key" class="spk-field__label">
					<?php esc_html_e( 'کلید لایسنس', 'sparkknife-ledger' ); ?>
				</label>
				<textarea
					id="spk-license-key"
					name="license_key"
					rows="3"
					dir="ltr"
					class="large-text code"
					placeholder="SPK1.xxxxx.yyyyy"
				><?php echo esc_textarea( $license_key ); ?></textarea>
				<span class="spk-field__help">
					<?php esc_html_e( 'کلیدی که هنگام خرید دریافت کرده‌اید را این‌جا بچسبانید. کلید برای دامنهٔ همین سایت صادر شده باشد.', 'sparkknife-ledger' ); ?>
				</span>
			</p>

			<button type="submit" class="button button-primary">
				<?php esc_html_e( 'فعال‌سازی لایسنس', 'sparkknife-ledger' ); ?>
			</button>
		</form>
	<?php endif; ?>
</section>

<?php /* ── گزارش امنیتی ───────────────────────────────────────── */ ?>
<section class="spk-card">
	<h2 class="spk-card__title"><?php esc_html_e( 'رویدادهای امنیتی', 'sparkknife-ledger' ); ?></h2>

	<?php if ( empty( $log ) ) : ?>
		<p class="spk-empty">
			<?php esc_html_e( 'رویداد امنیتی ثبت نشده است. این طبیعی است.', 'sparkknife-ledger' ); ?>
		</p>
	<?php else : ?>
		<table class="spk-table">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'زمان', 'sparkknife-ledger' ); ?></th>
					<th scope="col"><?php esc_html_e( 'رویداد', 'sparkknife-ledger' ); ?></th>
					<th scope="col"><?php esc_html_e( 'سطح', 'sparkknife-ledger' ); ?></th>
					<th scope="col"><?php esc_html_e( 'کاربر', 'sparkknife-ledger' ); ?></th>
					<th scope="col"><?php esc_html_e( 'IP', 'sparkknife-ledger' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $log as $spk_entry ) : ?>
					<tr>
						<td>
							<?php
							echo esc_html(
								Sparkknife_Ledger_Date::format(
									gmdate( 'Y-m-d', (int) $spk_entry['time'] ),
									true
								)
							);
							?>
						</td>
						<td><code dir="ltr"><?php echo esc_html( (string) $spk_entry['event'] ); ?></code></td>
						<td>
							<span class="spk-badge is-<?php echo 'warning' === $spk_entry['level'] ? 'warn' : 'ok'; ?>">
								<?php echo esc_html( (string) $spk_entry['level'] ); ?>
							</span>
						</td>
						<td><?php echo esc_html( sparkknife_ledger_user_label( (int) $spk_entry['user_id'] ) ); ?></td>
						<td><code dir="ltr"><?php echo esc_html( (string) $spk_entry['ip'] ); ?></code></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="spk-form">
			<input type="hidden" name="action" value="sparkknife_ledger_action" />
			<input type="hidden" name="sparkknife_action" value="security_log_clear" />
			<input type="hidden" name="sparkknife_redirect" value="<?php echo esc_url( admin_url( 'admin.php?page=sparkknife-ledger-health' ) ); ?>" />
			<?php wp_nonce_field( 'sparkknife_ledger_security_log_clear' ); ?>
			<button type="submit" class="button">
				<?php esc_html_e( 'پاک کردن گزارش', 'sparkknife-ledger' ); ?>
			</button>
		</form>
	<?php endif; ?>
</section>

<?php require __DIR__ . '/../partials/footer.php'; ?>
