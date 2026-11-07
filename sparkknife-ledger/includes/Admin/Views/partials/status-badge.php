<?php
/**
 * نشان وضعیت تراکنش یا شخص.
 *
 * @var string $type   transaction|person|account.
 * @var string $status وضعیت.
 *
 * @author  SparKnife <155084046+sparkknife@users.noreply.github.com>
 *
 * @package SparKnife_Ledger
 */

defined( 'ABSPATH' ) || exit;

$type   = isset( $type ) ? $type : 'transaction';
$status = isset( $status ) ? (string) $status : '';

$map = array(
	'transaction' => array(
		'posted' => array( 'label' => __( 'ثبت‌شده', 'sparkknife-ledger' ), 'class' => 'is-ok' ),
		'void'   => array( 'label' => __( 'باطل‌شده', 'sparkknife-ledger' ), 'class' => 'is-void' ),
	),
	'person'      => array(
		'active'   => array( 'label' => __( 'فعال', 'sparkknife-ledger' ), 'class' => 'is-ok' ),
		'inactive' => array( 'label' => __( 'غیرفعال', 'sparkknife-ledger' ), 'class' => 'is-muted' ),
	),
	'account'     => array(
		'positive' => array( 'label' => __( 'مانده مثبت', 'sparkknife-ledger' ), 'class' => 'is-ok' ),
		'negative' => array( 'label' => __( 'مانده منفی', 'sparkknife-ledger' ), 'class' => 'is-bad' ),
		'settled'  => array( 'label' => __( 'تسویه‌شده', 'sparkknife-ledger' ), 'class' => 'is-neutral' ),
	),
);

$item = isset( $map[ $type ][ $status ] ) ? $map[ $type ][ $status ] : array(
	'label' => __( 'نامشخص', 'sparkknife-ledger' ),
	'class' => 'is-muted',
);
?>
<span class="spk-badge <?php echo esc_attr( $item['class'] ); ?>"><?php echo esc_html( $item['label'] ); ?></span>
