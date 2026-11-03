<?php
/**
 * سربرگ مشترک صفحات افزونه.
 *
 * @var string $title       عنوان صفحه.
 * @var string $description توضیح کوتاه.
 * @var array  $actions     دکمه‌های سمت چپ (label, url, class).
 *
 * @package SparKnife_Ledger
 */

defined( 'ABSPATH' ) || exit;

$title       = isset( $title ) ? $title : '';
$description = isset( $description ) ? $description : '';
$actions     = isset( $actions ) && is_array( $actions ) ? $actions : array();
$current_tab = isset( $current_tab ) ? $current_tab : '';
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
