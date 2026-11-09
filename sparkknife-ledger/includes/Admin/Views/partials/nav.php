<?php
/**
 * ناوبری داخلی صفحات افزونه (تب‌های بالای صفحه).
 *
 * @var string $current صفحه جاری.
 *
 * @author  SparKnife <155084046+sparkknife@users.noreply.github.com>
 *
 * @package SparKnife_Ledger
 */

defined( 'ABSPATH' ) || exit;

$current = isset( $current ) ? (string) $current : '';

$menu_items = array(
	array(
		'slug'  => 'dashboard',
		'label' => __( 'داشبورد', 'sparkknife-ledger' ),
		'url'   => admin_url( 'admin.php?page=sparkknife-ledger' ),
		'cap'   => 'reports',
		'icon'  => 'dashicons-dashboard',
	),
	array(
		'slug'  => 'persons',
		'label' => __( 'اشخاص', 'sparkknife-ledger' ),
		'url'   => admin_url( 'admin.php?page=sparkknife-ledger-persons' ),
		'cap'   => 'manage_persons',
		'icon'  => 'dashicons-groups',
	),
	array(
		'slug'  => 'transactions',
		'label' => __( 'تراکنش‌ها', 'sparkknife-ledger' ),
		'url'   => admin_url( 'admin.php?page=sparkknife-ledger-transactions' ),
		'cap'   => 'manage_transactions',
		'icon'  => 'dashicons-list-view',
	),
	array(
		'slug'  => 'reports',
		'label' => __( 'گزارش فردی', 'sparkknife-ledger' ),
		'url'   => admin_url( 'admin.php?page=sparkknife-ledger-reports' ),
		'cap'   => 'reports',
		'icon'  => 'dashicons-chart-line',
	),
	array(
		'slug'  => 'summary',
		'label' => __( 'گزارش تجمیعی', 'sparkknife-ledger' ),
		'url'   => admin_url( 'admin.php?page=sparkknife-ledger-summary' ),
		'cap'   => 'reports',
		'icon'  => 'dashicons-chart-bar',
	),
	array(
		'slug'  => 'audit',
		'label' => __( 'تاریخچه تغییرات', 'sparkknife-ledger' ),
		'url'   => admin_url( 'admin.php?page=sparkknife-ledger-audit' ),
		'cap'   => 'view_audit',
		'icon'  => 'dashicons-backup',
	),
	array(
		'slug'  => 'settings',
		'label' => __( 'تنظیمات', 'sparkknife-ledger' ),
		'url'   => admin_url( 'admin.php?page=sparkknife-ledger-settings' ),
		'cap'   => 'manage_settings',
		'icon'  => 'dashicons-admin-settings',
	),
);
?>
<nav class="spk-nav" aria-label="<?php esc_attr_e( 'ناوبری افزونه', 'sparkknife-ledger' ); ?>">
	<?php foreach ( $menu_items as $item ) : ?>
		<?php if ( ! Sparkknife_Ledger_Capabilities::current_user_can( $item['cap'] ) ) : ?>
			<?php continue; ?>
		<?php endif; ?>
		<a class="spk-nav__item <?php echo $current === $item['slug'] ? 'is-active' : ''; ?>"
			href="<?php echo esc_url( $item['url'] ); ?>"
			<?php echo $current === $item['slug'] ? 'aria-current="page"' : ''; ?>>
			<span class="dashicons <?php echo esc_attr( $item['icon'] ); ?>" aria-hidden="true"></span>
			<span><?php echo esc_html( $item['label'] ); ?></span>
		</a>
	<?php endforeach; ?>
</nav>
