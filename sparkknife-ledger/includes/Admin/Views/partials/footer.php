<?php
/**
 * پایان‌بند مشترک صفحات افزونه.
 *
 * @author  SparKnife <155084046+sparkknife@users.noreply.github.com>
 *
 * @package SparKnife_Ledger
 */

defined( 'ABSPATH' ) || exit;
?>
	<footer class="spk-footer">
		<span>
			<?php
			printf(
				/* translators: 1: plugin name, 2: version */
				esc_html__( '%1$s نسخه %2$s — مدیریت حساب اشخاص', 'sparkknife-ledger' ),
				'SparKnife Ledger',
				esc_html( SPARKKNIFE_LEDGER_VERSION )
			);
			?>
		</span>
		<span class="spk-footer__meta">
			<?php echo esc_html( Sparkknife_Ledger_Money::currency_label() ); ?>
			<?php echo esc_html( Sparkknife_Ledger_Date::uses_jalali() ? __( '| تقویم شمسی', 'sparkknife-ledger' ) : __( '| تقویم میلادی', 'sparkknife-ledger' ) ); ?>
		</span>
	</footer>
</div><!-- .spk-wrap -->
