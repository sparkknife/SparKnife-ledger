<?php
/**
 * حالت خالی مشترک.
 *
 * @var string $icon        آیکن dashicons.
 * @var string $title       عنوان.
 * @var string $message     توضیح.
 * @var string $action_url  نشانی دکمه.
 * @var string $action_text متن دکمه.
 *
 * @author  SparKnife <155084046+sparkknife@users.noreply.github.com>
 *
 * @package SparKnife_Ledger
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="spk-empty">
	<span class="dashicons <?php echo esc_attr( isset( $icon ) ? $icon : 'dashicons-info-outline' ); ?>" aria-hidden="true"></span>
	<h3><?php echo esc_html( isset( $title ) ? $title : __( 'موردی برای نمایش نیست', 'sparkknife-ledger' ) ); ?></h3>
	<?php if ( ! empty( $message ) ) : ?>
		<p><?php echo esc_html( $message ); ?></p>
	<?php endif; ?>
	<?php if ( ! empty( $action_url ) && ! empty( $action_text ) ) : ?>
		<a class="spk-btn spk-btn--primary" href="<?php echo esc_url( $action_url ); ?>"><?php echo esc_html( $action_text ); ?></a>
	<?php endif; ?>
</div>
