<?php
/**
 * صفحه خطای داخلی افزونه.
 *
 * @var string $title   عنوان.
 * @var string $message پیام.
 * @var string $current صفحه جاری.
 *
 * @author  SparKnife <155084046+sparkknife@users.noreply.github.com>
 *
 * @package SparKnife_Ledger
 */

defined( 'ABSPATH' ) || exit;

require __DIR__ . '/header.php';
require __DIR__ . '/nav.php';
?>
<div class="spk-card">
	<div class="spk-empty">
		<span class="dashicons dashicons-warning" aria-hidden="true"></span>
		<h3><?php echo esc_html( isset( $title ) ? $title : __( 'خطا', 'sparkknife-ledger' ) ); ?></h3>
		<p><?php echo esc_html( isset( $message ) ? $message : '' ); ?></p>
		<a class="spk-btn spk-btn--primary" href="<?php echo esc_url( Sparkknife_Ledger_Renderer::url( 'persons' ) ); ?>">
			<?php esc_html_e( 'بازگشت به فهرست اشخاص', 'sparkknife-ledger' ); ?>
		</a>
	</div>
</div>
<?php require __DIR__ . '/footer.php'; ?>
