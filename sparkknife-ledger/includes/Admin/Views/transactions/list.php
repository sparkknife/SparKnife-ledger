<?php
/**
 * فهرست تراکنش‌ها.
 *
 * @var array  $query    نتیجه کوئری.
 * @var array  $totals   مجموع‌ها.
 * @var array  $filters  فیلترها.
 * @var array  $range    بازه.
 * @var array  $persons  اشخاص.
 * @var string $base_url نشانی پایه.
 * @var string $current  صفحه جاری.
 *
 * @author  SparKnife <155084046+sparkknife@users.noreply.github.com>
 *
 * @package SparKnife_Ledger
 */

defined( 'ABSPATH' ) || exit;

$header_actions = array(
	array(
		'label' => __( 'ثبت تراکنش جدید', 'sparkknife-ledger' ),
		'url'   => Sparkknife_Ledger_Renderer::url( 'transactions', array( 'action' => 'new' ) ),
		'class' => 'spk-btn--primary',
		'icon'  => 'dashicons-plus-alt2',
	),
	array(
		'label' => __( 'گزارش تجمیعی', 'sparkknife-ledger' ),
		'url'   => Sparkknife_Ledger_Renderer::url( 'summary' ),
		'icon'  => 'dashicons-chart-bar',
	),
);

require __DIR__ . '/../partials/header.php';
require __DIR__ . '/../partials/nav.php';

$selected_person = $filters['person_id'] ? Sparkknife_Ledger_Person_Repository::instance()->find( $filters['person_id'], true ) : null;
?>

<?php
/*
 * خطای بازه تاریخ باید دیده شود.
 *
 * بدون این پیام، تاریخ نامعتبر در فیلتر بی‌صدا نادیده گرفته می‌شد و
 * کاربر فهرستی می‌دید که فیلترش اعمال نشده بود — و آن را نتیجه فیلتر
 * خودش می‌پنداشت.
 */
?>
<?php if ( ! empty( $range['error'] ) && is_wp_error( $range['error'] ) ) : ?>
	<div class="spk-alert spk-alert--warning">
		<span class="dashicons dashicons-warning" aria-hidden="true"></span>
		<span><?php echo esc_html( $range['error']->get_error_message() ); ?></span>
	</div>
<?php endif; ?>

<div class="spk-stats spk-stats--3">
	<article class="spk-stat spk-stat--income">
		<div class="spk-stat__icon"><span class="dashicons dashicons-download" aria-hidden="true"></span></div>
		<div class="spk-stat__body">
			<span class="spk-stat__label"><?php esc_html_e( 'مجموع ورودی (فیلتر فعلی)', 'sparkknife-ledger' ); ?></span>
			<span class="spk-stat__value spk-money-positive"><?php echo esc_html( Sparkknife_Ledger_Money::format( $totals['income'] ) ); ?></span>
		</div>
	</article>

	<article class="spk-stat spk-stat--expense">
		<div class="spk-stat__icon"><span class="dashicons dashicons-upload" aria-hidden="true"></span></div>
		<div class="spk-stat__body">
			<span class="spk-stat__label"><?php esc_html_e( 'مجموع خروجی (فیلتر فعلی)', 'sparkknife-ledger' ); ?></span>
			<span class="spk-stat__value spk-money-negative"><?php echo esc_html( Sparkknife_Ledger_Money::format( $totals['expense'] ) ); ?></span>
		</div>
	</article>

	<article class="spk-stat spk-stat--txn">
		<div class="spk-stat__icon"><span class="dashicons dashicons-list-view" aria-hidden="true"></span></div>
		<div class="spk-stat__body">
			<span class="spk-stat__label"><?php esc_html_e( 'تعداد ردیف', 'sparkknife-ledger' ); ?></span>
			<span class="spk-stat__value"><?php echo esc_html( Sparkknife_Ledger_Money::digits( (string) $totals['count'] ) ); ?></span>
			<span class="spk-stat__meta"><?php echo esc_html( $range['label'] ); ?></span>
		</div>
	</article>
</div>

<?php if ( $selected_person ) : ?>
	<div class="spk-alert spk-alert--info">
		<span class="dashicons dashicons-filter" aria-hidden="true"></span>
		<span>
			<?php
			printf(
				/* translators: %s: person name */
				esc_html__( 'فیلتر بر اساس شخص: %s', 'sparkknife-ledger' ),
				esc_html( $selected_person->name )
			);
			?>
		</span>
		<a href="<?php echo esc_url( Sparkknife_Ledger_Renderer::url( 'transactions' ) ); ?>"><?php esc_html_e( 'حذف فیلتر شخص', 'sparkknife-ledger' ); ?></a>
	</div>
<?php endif; ?>

<?php
Sparkknife_Ledger_Renderer::view(
	'partials/filter-form',
	array(
		'action'      => admin_url( 'admin.php' ),
		'hidden'      => array( 'page' => 'sparkknife-ledger-transactions' ),
		'ranges'      => Sparkknife_Ledger_Ledger_Service::preset_ranges(),
		'range'       => $filters['range'],
		'date_from'   => $filters['date_from'],
		'date_to'     => $filters['date_to'],
		'type'        => $filters['type'],
		'status'      => $filters['status'],
		'search'      => $filters['search'],
		'persons'     => $persons,
		'person_id'   => $filters['person_id'],
		'show_person' => true,
	)
);
?>

<section class="spk-card" id="spk-results">
	<div class="spk-card__header">
		<h2>
			<?php
			printf(
				/* translators: %s: total rows */
				esc_html__( 'تراکنش‌ها (%s ردیف)', 'sparkknife-ledger' ),
				esc_html( Sparkknife_Ledger_Money::digits( (string) $query['total'] ) )
			);
			?>
		</h2>
		<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="spk-inline-form spk-sort-form">
			<input type="hidden" name="page" value="sparkknife-ledger-transactions" />
			<?php if ( $filters['person_id'] ) : ?>
				<input type="hidden" name="person_id" value="<?php echo esc_attr( (string) $filters['person_id'] ); ?>" />
			<?php endif; ?>
			<?php if ( '' !== $filters['type'] ) : ?>
				<input type="hidden" name="type" value="<?php echo esc_attr( $filters['type'] ); ?>" />
			<?php endif; ?>
			<?php if ( '' !== $filters['status'] ) : ?>
				<input type="hidden" name="status" value="<?php echo esc_attr( $filters['status'] ); ?>" />
			<?php endif; ?>
			<?php if ( '' !== $filters['search'] ) : ?>
				<input type="hidden" name="search" value="<?php echo esc_attr( $filters['search'] ); ?>" />
			<?php endif; ?>
			<?php if ( 'all' !== $filters['range'] ) : ?>
				<input type="hidden" name="range" value="<?php echo esc_attr( $filters['range'] ); ?>" />
			<?php endif; ?>
			<label class="screen-reader-text" for="spk-orderby"><?php esc_html_e( 'مرتب‌سازی بر اساس', 'sparkknife-ledger' ); ?></label>
			<select name="orderby" id="spk-orderby" class="spk-input spk-input--sm">
				<option value="txn_date" <?php selected( $filters['orderby'], 'txn_date' ); ?>><?php esc_html_e( 'تاریخ تراکنش', 'sparkknife-ledger' ); ?></option>
				<option value="created_at" <?php selected( $filters['orderby'], 'created_at' ); ?>><?php esc_html_e( 'تاریخ ثبت', 'sparkknife-ledger' ); ?></option>
				<option value="amount" <?php selected( $filters['orderby'], 'amount' ); ?>><?php esc_html_e( 'مبلغ', 'sparkknife-ledger' ); ?></option>
				<option value="id" <?php selected( $filters['orderby'], 'id' ); ?>><?php esc_html_e( 'شناسه', 'sparkknife-ledger' ); ?></option>
			</select>
			<label class="screen-reader-text" for="spk-order"><?php esc_html_e( 'جهت', 'sparkknife-ledger' ); ?></label>
			<select name="order" id="spk-order" class="spk-input spk-input--sm">
				<option value="desc" <?php selected( strtolower( $filters['order'] ), 'desc' ); ?>><?php esc_html_e( 'نزولی', 'sparkknife-ledger' ); ?></option>
				<option value="asc" <?php selected( strtolower( $filters['order'] ), 'asc' ); ?>><?php esc_html_e( 'صعودی', 'sparkknife-ledger' ); ?></option>
			</select>
			<button type="submit" class="spk-btn spk-btn--ghost spk-btn--sm"><?php esc_html_e( 'اعمال', 'sparkknife-ledger' ); ?></button>
		</form>
	</div>

	<?php
	Sparkknife_Ledger_Renderer::view(
		'partials/transactions-table',
		array(
			'transactions' => $query,
			'empty_title'  => __( 'تراکنشی با این فیلترها یافت نشد', 'sparkknife-ledger' ),
			'empty_text'   => __( 'فیلترها را تغییر دهید یا تراکنش جدیدی ثبت کنید.', 'sparkknife-ledger' ),
			'empty_url'    => Sparkknife_Ledger_Renderer::url( 'transactions', array( 'action' => 'new' ) ),
			'show_person'  => true,
		)
	);
	?>

	<?php if ( ! empty( $query['items'] ) ) : ?>
		<?php
		Sparkknife_Ledger_Renderer::view(
			'partials/pagination',
			array(
				'page'     => $query['page'],
				'pages'    => $query['pages'],
				'total'    => $query['total'],
				'per_page' => $query['per_page'],
				'base_url' => $base_url,
				'arg'      => 'paged',
			)
		);
		?>
	<?php endif; ?>
</section>

<?php require __DIR__ . '/../partials/footer.php'; ?>
