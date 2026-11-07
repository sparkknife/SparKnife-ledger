<?php
/**
 * دکمه عملیات POST با Nonce و تأیید اختیاری.
 *
 * @var string $action  نام action.
 * @var string $label   متن دکمه.
 * @var string $icon    آیکن dashicons.
 * @var array  $fields  فیلدهای مخفی.
 * @var string $confirm متن تأیید (اختیاری).
 * @var string $class   کلاس دکمه.
 * @var string $redirect مقصد بازگشت (پیش‌فرض: صفحه جاری).
 *
 * @author  SparKnife <155084046+sparkknife@users.noreply.github.com>
 *
 * @package SparKnife_Ledger
 */

defined( 'ABSPATH' ) || exit;

$action   = isset( $action ) ? $action : '';
$label    = isset( $label ) ? $label : '';
$icon     = isset( $icon ) ? $icon : '';
$fields   = isset( $fields ) && is_array( $fields ) ? $fields : array();
$confirm  = isset( $confirm ) ? $confirm : '';
$class    = isset( $class ) ? $class : 'spk-btn spk-btn--ghost spk-btn--sm';
$redirect = isset( $redirect ) ? $redirect : '';

if ( '' === $redirect ) {
	$redirect = add_query_arg( array() );
}
?>
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="spk-inline-form"
	<?php echo '' !== $confirm ? 'data-confirm="' . esc_attr( $confirm ) . '"' : ''; ?>>
	<input type="hidden" name="action" value="sparkknife_ledger_action" />
	<input type="hidden" name="sparkknife_action" value="<?php echo esc_attr( $action ); ?>" />
	<input type="hidden" name="sparkknife_redirect" value="<?php echo esc_attr( $redirect ); ?>" />
	<?php wp_nonce_field( 'sparkknife_ledger_' . $action ); ?>
	<?php foreach ( $fields as $field_name => $field_value ) : ?>
		<input type="hidden" name="<?php echo esc_attr( $field_name ); ?>" value="<?php echo esc_attr( (string) $field_value ); ?>" />
	<?php endforeach; ?>
	<button type="submit" class="<?php echo esc_attr( $class ); ?>">
		<?php if ( '' !== $icon ) : ?>
			<span class="dashicons <?php echo esc_attr( $icon ); ?>" aria-hidden="true"></span>
		<?php endif; ?>
		<?php echo esc_html( $label ); ?>
	</button>
</form>
