<?php
/**
 * گزارش فردی.
 *
 * @var array       $persons   فهرست اشخاص.
 * @var int         $person_id شخص انتخاب‌شده.
 * @var array|null  $report    گزارش.
 * @var array       $args      فیلترها.
 * @var string      $base_url  نشانی پایه.
 * @var string      $current   صفحه جاری.
 *
 * @package SparKnife_Ledger
 */

defined( 'ABSPATH' ) || exit;

$header_actions = array();

if ( is_array( $report ) && Sparkknife_Ledger_Capabilities::current_user_can( 'export_reports' ) ) {
	$export_base = array(
		'action'    => 'sparkknife_ledger_export',
		'report'    => 'person',
		'person'    => $person_id,
		'range'     => $args['range'],
		'date_from' => $args['date_from'],
		'date_to'   => $args['date_to'],
		'type'      => $args['type'],
		'status'    => $args['status'],
	);

	$header_actions[] = array(
		'label' => __( 'خروجی CSV / Excel', 'sparkknife-ledger' ),
		'url'   => wp_nonce_url( add_query_arg( $export_base, admin_url( 'admin-post.php' ) ), 'sparkknife_ledger_export' ),
		'icon'  => 'dashicons-download',
	);

	$print_url = wp_nonce_url(
		add_query_arg(
			array_merge(
				$export_base,
				array(
					'report'  => 'person-print',
					'print'   => '1',
				)
			),
			admin_url( 'admin-post.php' )
		),
		'sparkknife_ledger_print'
	);

	$header_actions[] = array(
		'label'  => __( 'چاپ / PDF', 'sparkknife-ledger' ),
		'url'    => $print_url,
		'icon'   => 'dashicons-printer',
		'target' => '_blank',
	);
}

require __DIR__ . '/../partials/header.php';
require __DIR__ . '/../partials/nav.php';
?>

<?php if ( empty( $persons ) ) : ?>
	<div class="spk-card">
		<?php
		Sparkknife_Ledger_Renderer::view(
			'partials/empty-state',
			array(
				'icon'        => 'dashicons-chart-line',
				'title'       => __( 'برای تهیه گزارش، ابتدا شخص ثبت کنید', 'sparkknife-ledger' ),
				'message'     => __( 'گزارش فردی بر اساس تراکنش‌های هر شخص ساخته می‌شود.', 'sparkknife-ledger' ),
				'action_url'  => Sparkknife_Ledger_Renderer::url( 'persons', array( 'action' => 'new' ) ),
				'action_text' => __( 'افزودن شخص', 'sparkknife-ledger' ),
			)
		);
		?>
	</div>
<?php elseif ( is_wp_error( $report ) ) : ?>
	<div class="spk-card">
		<?php
		Sparkknife_Ledger_Renderer::view(
			'partials/error-page',
			array(
				'title'   => __( 'خطا در تهیه گزارش', 'sparkknife-ledger' ),
				'message' => $report->get_error_message(),
				'current' => 'reports',
			)
		);
		?>
	</div>
<?php elseif ( is_array( $report ) ) : ?>
	<?php
	$person  = $report['person'];
	$summary = $report['summary'];
	$range   = $report['range'];
	?>

	<section class="spk-report-head">
		<div>
			<h2 class="spk-report-head__title">
				<?php echo esc_html( $person->name ); ?>
				<?php if ( ! empty( $person->deleted_at ) ) : ?>
					<span class="spk-badge is-void"><?php esc_html_e( 'حذف‌شده', 'sparkknife-ledger' ); ?></span>
				<?php endif; ?>
			</h2>
			<p class="spk-muted">
				<?php
				printf(
					/* translators: 1: from date, 2: to date */
					esc_html__( 'بازه گزارش: %1$s تا %2$s', 'sparkknife-ledger' ),
					esc_html( $range['from'] ? Sparkknife_Ledger_Date::format( $range['from'] ) : '—' ),
					esc_html( $range['to'] ? Sparkknife_Ledger_Date::format( $range['to'] ) : '—' )
				);
				?>
			</p>
		</div>
		<div class="spk-report-head__actions">
			<a class="spk-btn spk-btn--ghost spk-btn--sm" href="<?php echo esc_url( Sparkknife_Ledger_Renderer::url( 'persons', array( 'action' => 'view', 'id' => (int) $person->id ) ) ); ?>">
				<span class="dashicons dashicons-admin-users" aria-hidden="true"></span>
				<?php esc_html_e( 'صفحه شخص', 'sparkknife-ledger' ); ?>
			</a>
		</div>
	</section>

	<div class="spk-stats spk-stats--4">
		<article class="spk-stat spk-stat--income">
			<div class="spk-stat__icon"><span class="dashicons dashicons-download" aria-hidden="true"></span></div>
			<div class="spk-stat__body">
				<span class="spk-stat__label"><?php esc_html_e( 'مجموع ورودی‌ها', 'sparkknife-ledger' ); ?></span>
				<span class="spk-stat__value spk-money-positive"><?php echo esc_html( $summary['income_formatted'] ); ?></span>
			</div>
		</article>
		<article class="spk-stat spk-stat--expense">
			<div class="spk-stat__icon"><span class="dashicons dashicons-upload" aria-hidden="true"></span></div>
			<div class="spk-stat__body">
				<span class="spk-stat__label"><?php esc_html_e( 'مجموع خروجی‌ها', 'sparkknife-ledger' ); ?></span>
				<span class="spk-stat__value spk-money-negative"><?php echo esc_html( $summary['expense_formatted'] ); ?></span>
			</div>
		</article>
		<article class="spk-stat spk-stat--balance">
			<div class="spk-stat__icon"><span class="dashicons dashicons-chart-pie" aria-hidden="true"></span></div>
			<div class="spk-stat__body">
				<span class="spk-stat__label"><?php esc_html_e( 'مانده حساب', 'sparkknife-ledger' ); ?></span>
				<span class="spk-stat__value <?php echo ! Sparkknife_Ledger_Money::is_negative( $summary['balance'] ) ? 'spk-money-positive' : 'spk-money-negative'; ?>">
					<?php echo esc_html( $summary['balance_formatted'] ); ?>
				</span>
			</div>
		</article>
		<article class="spk-stat spk-stat--txn">
			<div class="spk-stat__icon"><span class="dashicons dashicons-list-view" aria-hidden="true"></span></div>
			<div class="spk-stat__body">
				<span class="spk-stat__label"><?php esc_html_e( 'وضعیت مالی', 'sparkknife-ledger' ); ?></span>
				<span class="spk-stat__value">
					<?php
					Sparkknife_Ledger_Renderer::view(
						'partials/status-badge',
						array(
							'type'   => 'account',
							'status' => $summary['account_status'],
						)
					);
					?>
				</span>
				<span class="spk-stat__meta">
					<?php
					printf(
						/* translators: 1: transactions, 2: void */
						esc_html__( '%1$s تراکنش معتبر · %2$s باطل‌شده', 'sparkknife-ledger' ),
						esc_html( Sparkknife_Ledger_Money::digits( (string) $summary['count'] ) ),
						esc_html( Sparkknife_Ledger_Money::digits( (string) $summary['void_count'] ) )
					);
					?>
				</span>
			</div>
		</article>
	</div>

	<?php
	$hidden_form_args = array();

	if ( $person_id ) {
		$hidden_form_args['person_id'] = $person_id;
	}

	Sparkknife_Ledger_Renderer::view(
		'partials/filter-form',
		array(
			'action'      => admin_url( 'admin.php' ),
			'hidden'      => array_merge( array( 'page' => 'sparkknife-ledger-reports' ), $hidden_form_args ),
			'range'       => $args['range'],
			'date_from'   => $args['date_from'],
			'date_to'     => $args['date_to'],
			'type'        => $args['type'],
			'status'      => $args['status'],
			'search'      => $args['search'],
			'persons'     => $persons,
			'person_id'   => $person_id,
			'show_person' => false,
		)
	);
	?>

	<?php if ( ! empty( $report['range']['error'] ) && is_wp_error( $report['range']['error'] ) ) : ?>
	<div class="spk-alert spk-alert--warning">
		<span class="dashicons dashicons-warning" aria-hidden="true"></span>
		<span><?php echo esc_html( $report['range']['error']->get_error_message() ); ?></span>
	</div>
<?php endif; ?>

<?php
/*
 * هشدار «این مبالغ کل بازه‌اند، نه نتیجهٔ فیلتر».
 *
 * شرط پیش‌تر فقط `type` را می‌سنجید، در حالی که متن هشدار خودش می‌گوید
 * «نوع/جستجو». نتیجه: با جستجوی بی‌نتیجه جدول خالی می‌شد ولی کارت‌های
 * بالای آن همچنان مبلغ کل بازه را نشان می‌دادند — عددی که هیچ ردیفی
 * توضیحش نمی‌داد و هیچ توضیحی هم نداشت.
 *
 * حالا وجود فیلتر جستجو هم شرط را فعال می‌کند. `filtered.count > 0`
 * لازم است چون وقتی هیچ ردیفی نیست، عدد صفر همه‌چیز را می‌گوید و هشدار
 * تکراری است.
 */
$has_narrowing_filter = '' !== (string) $args['type']
	|| '' !== (string) $report['filters']['search'];

/*
 * دو حالت جدا:
 *
 * - فیلتر نتیجه داشت: بگوییم مبالغ بالا کل بازه است، نه فیلتر.
 * - فیلتر هیچ نتیجه‌ای نداشت: جدول خالی است و مبالغ بالا **فقط** کل
 *   بازه‌اند؛ این حالت گیج‌کننده‌تر است و باید هم توضیح داده شود.
 */
$filter_matched_something = (int) $report['filtered']['count'] > 0;
?>
<?php if ( $has_narrowing_filter && $filter_matched_something ) : ?>
		<div class="spk-alert spk-alert--info">
			<span class="dashicons dashicons-info" aria-hidden="true"></span>
			<span>
				<?php
				printf(
					/* translators: %s: count */
					esc_html__( '%s تراکنش با فیلتر نوع/جستجوی فعلی مطابقت دارد؛ مبالغ ستون‌های بالا مطابق تعریف مانده، همه تراکنش‌های معتبر بازه را شامل می‌شود.', 'sparkknife-ledger' ),
					esc_html( Sparkknife_Ledger_Money::digits( (string) $report['filtered']['count'] ) )
				);
				?>
			</span>
		</div>
	<?php endif; ?>

	<?php if ( $has_narrowing_filter && ! $filter_matched_something ) : ?>
		<div class="spk-alert spk-alert--warning">
			<span class="dashicons dashicons-warning" aria-hidden="true"></span>
			<span>
				<?php
				esc_html_e( 'هیچ تراکنشی با فیلتر فعلی مطابقت ندارد. مبالغ ستون‌های بالا مربوط به کل بازه است، نه نتیجهٔ این فیلتر.', 'sparkknife-ledger' );
				?>
			</span>
		</div>
	<?php endif; ?>

	<section class="spk-card" id="spk-results">
		<div class="spk-card__header">
			<h2><?php esc_html_e( 'تراکنش‌های بازه', 'sparkknife-ledger' ); ?></h2>
			<span class="spk-muted"><?php echo esc_html( $range['label'] ); ?></span>
		</div>

		<?php
		Sparkknife_Ledger_Renderer::view(
			'partials/transactions-table',
			array(
				'transactions' => $report['transactions'],
				'empty_title'  => __( 'تراکنشی در این بازه یافت نشد', 'sparkknife-ledger' ),
				'empty_text'   => __( 'بازه زمانی یا فیلترها را تغییر دهید.', 'sparkknife-ledger' ),
				'empty_url'    => Sparkknife_Ledger_Renderer::url( 'transactions', array( 'action' => 'new', 'person' => (int) $person->id ) ),
				'show_person'  => false,
			)
		);
		?>

		<?php if ( ! empty( $report['transactions']['items'] ) ) : ?>
			<?php
			Sparkknife_Ledger_Renderer::view(
				'partials/pagination',
				array(
					'page'     => $report['transactions']['page'],
					'pages'    => $report['transactions']['pages'],
					'total'    => $report['transactions']['total'],
					'per_page' => $report['transactions']['per_page'],
					'base_url' => $base_url,
					'arg'      => 'paged',
				)
			);
			?>
		<?php endif; ?>
	</section>

	<section class="spk-card">
		<h2 class="spk-card__title"><?php esc_html_e( 'جمع‌بندی محاسباتی', 'sparkknife-ledger' ); ?></h2>
		<div class="spk-formula">
			<span class="spk-formula__part spk-money-positive"><?php echo esc_html( $summary['income_formatted'] ); ?></span>
			<span class="spk-formula__op">−</span>
			<span class="spk-formula__part spk-money-negative"><?php echo esc_html( $summary['expense_formatted'] ); ?></span>
			<span class="spk-formula__op">=</span>
			<span class="spk-formula__part spk-strong <?php echo ! Sparkknife_Ledger_Money::is_negative( $summary['balance'] ) ? 'spk-money-positive' : 'spk-money-negative'; ?>">
				<?php echo esc_html( $summary['balance_formatted'] ); ?>
			</span>
		</div>
		<p class="spk-muted">
			<?php esc_html_e( 'مانده = مجموع تراکنش‌های ورودی معتبر − مجموع تراکنش‌های خروجی معتبر. تراکنش‌های باطل‌شده در محاسبه لحاظ نمی‌شوند.', 'sparkknife-ledger' ); ?>
		</p>
	</section>
<?php endif; ?>

<?php require __DIR__ . '/../partials/footer.php'; ?>
