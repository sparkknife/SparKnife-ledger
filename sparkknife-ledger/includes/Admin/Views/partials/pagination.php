<?php
/**
 * صفحه‌بندی مشترک.
 *
 * @var int    $page     صفحه جاری.
 * @var int    $pages    تعداد صفحات.
 * @var int    $total    تعداد کل.
 * @var string $base_url نشانی پایه (بدون page).
 * @var string $arg      نام پارامتر صفحه.
 * @var int    $per_page تعداد در صفحه.
 *
 * @author  SparKnife <155084046+sparkknife@users.noreply.github.com>
 *
 * @package SparKnife_Ledger
 */

defined( 'ABSPATH' ) || exit;

$page     = isset( $page ) ? max( 1, (int) $page ) : 1;
$pages    = isset( $pages ) ? max( 1, (int) $pages ) : 1;
$total    = isset( $total ) ? (int) $total : 0;
$base_url = isset( $base_url ) ? $base_url : '';
$arg      = isset( $arg ) ? $arg : 'paged';
$per_page = isset( $per_page ) ? (int) $per_page : 20;

if ( $total <= 0 ) {
	return;
}

$from = ( ( $page - 1 ) * $per_page ) + 1;
$to   = min( $total, $page * $per_page );

$build = function ( $target ) use ( $base_url, $arg ) {
	return add_query_arg( $arg, (int) $target, $base_url ) . '#spk-results';
};
?>
<div class="spk-pagination tablenav">
	<span class="spk-pagination__info displaying-num">
		<?php
		printf(
			/* translators: 1: first item, 2: last item, 3: total */
			esc_html__( 'نمایش %1$s تا %2$s از %3$s مورد', 'sparkknife-ledger' ),
			esc_html( Sparkknife_Ledger_Money::digits( (string) $from ) ),
			esc_html( Sparkknife_Ledger_Money::digits( (string) $to ) ),
			esc_html( Sparkknife_Ledger_Money::digits( (string) $total ) )
		);
		?>
	</span>

	<?php if ( $pages > 1 ) : ?>
		<span class="spk-pagination__links">
			<?php if ( $page > 1 ) : ?>
				<a class="spk-page-btn" href="<?php echo esc_url( $build( 1 ) ); ?>" aria-label="<?php esc_attr_e( 'صفحه اول', 'sparkknife-ledger' ); ?>">&laquo;</a>
				<a class="spk-page-btn" href="<?php echo esc_url( $build( $page - 1 ) ); ?>" aria-label="<?php esc_attr_e( 'صفحه قبل', 'sparkknife-ledger' ); ?>">&rsaquo;</a>
			<?php endif; ?>

			<span class="spk-page-current">
				<?php
				printf(
					/* translators: 1: current page, 2: total pages */
					esc_html__( 'صفحه %1$s از %2$s', 'sparkknife-ledger' ),
					esc_html( Sparkknife_Ledger_Money::digits( (string) $page ) ),
					esc_html( Sparkknife_Ledger_Money::digits( (string) $pages ) )
				);
				?>
			</span>

			<?php if ( $page < $pages ) : ?>
				<a class="spk-page-btn" href="<?php echo esc_url( $build( $page + 1 ) ); ?>" aria-label="<?php esc_attr_e( 'صفحه بعد', 'sparkknife-ledger' ); ?>">&lsaquo;</a>
				<a class="spk-page-btn" href="<?php echo esc_url( $build( $pages ) ); ?>" aria-label="<?php esc_attr_e( 'صفحه آخر', 'sparkknife-ledger' ); ?>">&raquo;</a>
			<?php endif; ?>
		</span>
	<?php endif; ?>
</div>
