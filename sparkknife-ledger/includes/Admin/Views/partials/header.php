<?php
/**
 * سربرگ مشترک صفحات افزونه.
 *
 * @var string $title          عنوان صفحه.
 * @var string $description    توضیح کوتاه.
 * @var array  $header_actions دکمه‌های سمت چپ (label, url, class).
 * @var array  $actions        نام قدیمی همان متغیر؛ برای سازگاری عقب‌رو.
 *
 * @author  SparKnife <155084046+sparkknife@users.noreply.github.com>
 *
 * @package SparKnife_Ledger
 */

defined( 'ABSPATH' ) || exit;

$title       = isset( $title ) ? $title : '';
$description = isset( $description ) ? $description : '';
$current_tab = isset( $current_tab ) ? $current_tab : '';

/*
 * صفحه‌ها دکمه‌های سربرگ را در `$header_actions` می‌سازند، اما این قالب
 * تا نسخهٔ ۱.۱.۰ فقط `$actions` را می‌خواند. نتیجه: هر ۹ صفحه‌ای که دکمهٔ
 * سربرگ داشت (افزودن شخص، خروجی CSV، چاپ، افزودن تراکنش…) بی‌صدا دکمه‌اش
 * را از دست می‌داد — بدون هیچ خطایی، چون `isset()` روی متغیر تعریف‌نشده
 * فقط `false` می‌دهد.
 *
 * حالا هر دو نام پذیرفته می‌شوند و `$header_actions` اولویت دارد.
 */
if ( isset( $header_actions ) && is_array( $header_actions ) ) {
	$actions = $header_actions;
} else {
	$actions = isset( $actions ) && is_array( $actions ) ? $actions : array();
}
?>
<div class="spk-wrap" dir="rtl">
	<header class="spk-header">
		<div class="spk-header__main">
			<div class="spk-header__brand" aria-hidden="true">
				<span class="spk-logo">S</span>
			</div>
			<div class="spk-header__text">
				<h1 class="spk-title"><?php echo esc_html( $title ); ?></h1>
				<?php if ( '' !== $description ) : ?>
					<p class="spk-subtitle"><?php echo esc_html( $description ); ?></p>
				<?php endif; ?>
			</div>
		</div>
		<?php if ( ! empty( $actions ) ) : ?>
			<div class="spk-header__actions">
				<?php foreach ( $actions as $action ) : ?>
					<a class="spk-btn <?php echo esc_attr( isset( $action['class'] ) ? $action['class'] : 'spk-btn--ghost' ); ?>"
						href="<?php echo esc_url( $action['url'] ); ?>"
						<?php echo ! empty( $action['target'] ) ? 'target="_blank" rel="noopener noreferrer"' : ''; ?>>
						<?php if ( ! empty( $action['icon'] ) ) : ?>
							<span class="dashicons <?php echo esc_attr( $action['icon'] ); ?>" aria-hidden="true"></span>
						<?php endif; ?>
						<?php echo esc_html( $action['label'] ); ?>
					</a>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</header>
	<?php
	// پیام‌های فرم (خطاهای اعتبارسنجی) اگر وجود داشته باشند نمایش داده می‌شوند.
	$form_errors = get_transient( 'sparkknife_ledger_form_errors_' . get_current_user_id() );
	if ( $form_errors && is_array( $form_errors ) && ! empty( $form_errors['message'] ) ) :
		delete_transient( 'sparkknife_ledger_form_errors_' . get_current_user_id() );
		?>
		<div class="spk-alert spk-alert--error" role="alert">
			<span class="dashicons dashicons-warning" aria-hidden="true"></span>
			<span><?php echo esc_html( $form_errors['message'] ); ?></span>
		</div>
	<?php endif; ?>
