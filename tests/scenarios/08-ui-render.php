<?php
/**
 * سناریو ۸: رندر واقعی صفحات پنل (بدون خطای PHP).
 *
 * @package SparKnife_Ledger
 */

Sparkknife_Ledger_Test_Reporter::group( '۸) رندر صفحات پنل' );

// آماده‌سازی محیط ادمین.
if ( ! defined( 'WP_ADMIN' ) ) {
	define( 'WP_ADMIN', true );
}

require_once ABSPATH . 'wp-admin/includes/admin.php';
require_once ABSPATH . 'wp-admin/includes/screen.php';
require_once ABSPATH . 'wp-admin/includes/template.php';

set_current_screen( 'toplevel_page_sparkknife-ledger' );

/**
 * رندر یک view و بازگرداندن خروجی، با تشخیص خطا.
 *
 * @param string $view  نام view.
 * @param array  $args  داده.
 * @param string $label برچسب.
 * @return string
 */
function spk_render_view( $view, $args, $label ) {
	$errors = array();

	set_error_handler(
		function ( $errno, $errstr, $errfile, $errline ) use ( &$errors ) {
			$errors[] = $errstr . ' @ ' . basename( $errfile ) . ':' . $errline;
			return true;
		}
	);

	try {
		$html = Sparkknife_Ledger_Renderer::capture( $view, $args );
	} catch ( Throwable $e ) {
		$errors[] = $e->getMessage() . ' @ ' . basename( $e->getFile() ) . ':' . $e->getLine();
		$html     = '';
	}

	restore_error_handler();

	spk_assert( empty( $errors ), 'رندر ' . $label . ' بدون خطا', implode( ' | ', array_slice( $errors, 0, 3 ) ) );
	spk_assert( strlen( $html ) > 200, 'خروجی ' . $label . ' تولید شد', strlen( $html ) );

	return $html;
}

$persons     = $GLOBALS['spk_test_persons'];
$ledger      = Sparkknife_Ledger_Ledger_Service::instance();
$reports     = Sparkknife_Ledger_Report_Service::instance();
$settings    = Sparkknife_Ledger_Settings::all();
$person_row  = Sparkknife_Ledger_Person_Repository::instance()->find( $persons['ali'] );
$txn_row     = Sparkknife_Ledger_Transaction_Repository::instance()->find_with_person( $GLOBALS['spk_test_txns']['expense'] );

// داشبورد.
$dashboard_html = spk_render_view(
	'dashboard/index',
	array(
		'stats'        => $ledger->get_overall_stats(),
		'latest'       => Sparkknife_Ledger_Transaction_Repository::instance()->latest( 8 ),
		'positive'     => $ledger->get_persons_by_account_status( 'positive', 5 ),
		'negative'     => $ledger->get_persons_by_account_status( 'negative', 5 ),
		'chart'        => $reports->get_monthly_chart( 6 ),
		'chart_months' => 6,
		'top_debtors'  => array(),
		'health'       => Sparkknife_Ledger_Installer::get_health_report(),
		'can_add'      => true,
		'can_persons'  => true,
		'current'      => 'dashboard',
	),
	'داشبورد'
);

spk_assert( false !== strpos( $dashboard_html, 'مجموع ورودی‌ها' ), 'کارت مجموع ورودی در داشبورد' );
spk_assert( false !== strpos( $dashboard_html, 'مجموع مانده حساب‌ها' ), 'کارت مانده حساب‌ها در داشبورد' );
spk_assert( false !== strpos( $dashboard_html, 'آخرین تراکنش‌ها' ), 'بخش آخرین تراکنش‌ها' );
spk_assert( false !== strpos( $dashboard_html, 'spk-chart' ), 'نمودار داشبورد' );

// فهرست اشخاص.
$persons_list = Sparkknife_Ledger_Person_Repository::instance()->query( array( 'per_page' => 20, 'page' => 1 ) );
$persons_html = spk_render_view(
	'persons/list',
	array(
		'result'   => $persons_list,
		'balances' => $ledger->get_balances_bulk( array_map( 'intval', wp_list_pluck( $persons_list['items'], 'id' ) ) ),
		'args'     => array( 'search' => '', 'status' => '', 'orderby' => 'created_at', 'order' => 'desc', 'only_deleted' => false, 'page' => 1, 'per_page' => 20 ),
		'view'     => 'all',
		'stats'    => array( 'total' => 4, 'active' => 3, 'inactive' => 1, 'deleted' => 0 ),
		'base_url' => admin_url( 'admin.php?page=sparkknife-ledger-persons' ),
		'current'  => 'persons',
	),
	'فهرست اشخاص'
);

spk_assert( false !== strpos( $persons_html, 'علی رضایی' ), 'نام شخص در فهرست' );
spk_assert( false !== strpos( $persons_html, 'spk-table' ), 'جدول اشخاص رندر شد' );

// فرم شخص.
$form_html = spk_render_view(
	'persons/form',
	array(
		'person'  => $person_row,
		'summary' => $ledger->get_person_summary( $persons['ali'] ),
		'history' => Sparkknife_Ledger_Audit_Service::instance()->get_history( 'person', $persons['ali'], 5 ),
		'current' => 'persons',
	),
	'فرم ویرایش شخص'
);

spk_assert( false !== strpos( $form_html, 'wpnonce' ) || false !== strpos( $form_html, '_wpnonce' ), 'فیلد nonce در فرم شخص' );
spk_assert( false !== strpos( $form_html, 'name="name"' ), 'فیلد نام در فرم' );
spk_assert( false !== strpos( $form_html, 'value="علی رضایی"' ), 'مقدار فعلی نام در فرم' );

// صفحه اختصاصی شخص.
$detail_html = spk_render_view(
	'persons/detail',
	array(
		'person'   => $person_row,
		'report'   => $reports->get_person_report( $persons['ali'] ),
		'args'     => array( 'range' => 'all', 'date_from' => '', 'date_to' => '', 'type' => '', 'status' => '', 'search' => '', 'page' => 1, 'per_page' => 20, 'orderby' => 'txn_date', 'order' => 'desc' ),
		'history'  => Sparkknife_Ledger_Audit_Service::instance()->get_history( 'person', $persons['ali'], 10 ),
		'current'  => 'persons',
		'base_url' => admin_url( 'admin.php?page=sparkknife-ledger-persons&action=view&id=' . $persons['ali'] ),
	),
	'صفحه اختصاصی شخص'
);

spk_assert( false !== strpos( $detail_html, 'مانده نهایی' ), 'کارت مانده نهایی در صفحه شخص' );
spk_assert( false !== strpos( $detail_html, 'تراکنش‌های حساب' ), 'جدول تراکنش‌های شخص' );

// فهرست تراکنش‌ها.
$txn_query = Sparkknife_Ledger_Transaction_Repository::instance()->query( array( 'per_page' => 20, 'page' => 1 ) );
$txn_html  = spk_render_view(
	'transactions/list',
	array(
		'query'    => $txn_query,
		'totals'   => Sparkknife_Ledger_Transaction_Repository::instance()->sum_by_type(),
		'filters'  => array( 'person_id' => 0, 'type' => '', 'status' => '', 'search' => '', 'range' => 'all', 'date_from' => '', 'date_to' => '', 'orderby' => 'txn_date', 'order' => 'desc', 'paged' => 1 ),
		'range'    => array( 'from' => '', 'to' => '', 'label' => 'همه زمان‌ها', 'preset' => 'all' ),
		'persons'  => Sparkknife_Ledger_Person_Repository::instance()->get_all(),
		'base_url' => admin_url( 'admin.php?page=sparkknife-ledger-transactions' ),
		'current'  => 'transactions',
	),
	'فهرست تراکنش‌ها'
);

spk_assert( false !== strpos( $txn_html, 'TXN-' ), 'شناسه تراکنش در فهرست' );
spk_assert( false !== strpos( $txn_html, 'spk-badge' ), 'نشان وضعیت در فهرست' );

// فرم تراکنش جدید.
$new_txn_html = spk_render_view(
	'transactions/form',
	array(
		'transaction'        => null,
		'persons'            => Sparkknife_Ledger_Person_Repository::instance()->get_all( true ),
		'preselected_person' => $persons['ali'],
		'current'            => 'transactions',
	),
	'فرم تراکنش جدید'
);

spk_assert( false !== strpos( $new_txn_html, 'name="amount"' ), 'فیلد مبلغ در فرم تراکنش' );
spk_assert( false !== strpos( $new_txn_html, 'data-amount-input' ), 'قابلیت پیش‌نمایش مبلغ' );
spk_assert( false !== strpos( $new_txn_html, 'value="income"' ), 'انتخاب پیش‌فرض نوع ورودی' );

// جزئیات تراکنش.
$txn_detail_html = spk_render_view(
	'transactions/detail',
	array(
		'transaction' => $txn_row,
		'summary'     => $ledger->get_person_summary( (int) $txn_row->person_id ),
		'history'     => Sparkknife_Ledger_Audit_Service::instance()->get_history( 'transaction', (int) $txn_row->id ),
		'current'     => 'transactions',
	),
	'جزئیات تراکنش'
);

spk_assert( false !== strpos( $txn_detail_html, 'ابطال' ), 'فرم ابطال در جزئیات تراکنش' );
spk_assert( false !== strpos( $txn_detail_html, 'void_reason' ), 'فیلد دلیل ابطال' );

// گزارش فردی.
$report_html = spk_render_view(
	'reports/person',
	array(
		'persons'   => Sparkknife_Ledger_Person_Repository::instance()->get_all(),
		'person_id' => $persons['ali'],
		'report'    => $reports->get_person_report( $persons['ali'] ),
		'args'      => array( 'range' => 'all', 'date_from' => '', 'date_to' => '', 'type' => '', 'status' => '', 'search' => '', 'page' => 1, 'per_page' => 20, 'orderby' => 'txn_date', 'order' => 'desc' ),
		'base_url'  => admin_url( 'admin.php?page=sparkknife-ledger-reports&person_id=' . $persons['ali'] ),
		'current'   => 'reports',
	),
	'گزارش فردی'
);

spk_assert( false !== strpos( $report_html, 'جمع‌بندی محاسباتی' ), 'بخش جمع‌بندی در گزارش فردی' );
spk_assert( false !== strpos( $report_html, 'spk-formula' ), 'نمایش فرمول محاسبه' );

// گزارش تجمیعی.
$summary_html = spk_render_view(
	'reports/summary',
	array(
		'report'   => $reports->get_summary_report(),
		'args'     => array( 'range' => 'all', 'date_from' => '', 'date_to' => '', 'person_id' => 0, 'type' => '', 'status' => '', 'search' => '', 'orderby' => 'balance', 'order' => 'desc', 'page' => 1, 'per_page' => 20 ),
		'base_url' => admin_url( 'admin.php?page=sparkknife-ledger-summary' ),
		'current'  => 'summary',
	),
	'گزارش تجمیعی'
);

spk_assert( false !== strpos( $summary_html, 'گزارش تفکیکی اشخاص' ), 'جدول تفکیکی در گزارش تجمیعی' );
spk_assert( false !== strpos( $summary_html, 'مقایسه وضعیت مالی' ), 'بخش مقایسه وضعیت' );

// تاریخچه تغییرات.
$audit_html = spk_render_view(
	'audit/index',
	array(
		'result'   => Sparkknife_Ledger_Audit_Repository::instance()->query( array( 'per_page' => 10 ) ),
		'args'     => array( 'object_type' => '', 'object_id' => 0, 'action' => '', 'user_id' => 0, 'range' => 'all', 'date_from' => '', 'date_to' => '', 'page' => 1, 'per_page' => 30 ),
		'range'    => array( 'from' => '', 'to' => '', 'label' => 'همه زمان‌ها' ),
		'base_url' => admin_url( 'admin.php?page=sparkknife-ledger-audit' ),
		'current'  => 'audit',
		'users'    => get_users( array( 'fields' => array( 'ID', 'display_name' ) ) ),
	),
	'تاریخچه تغییرات'
);

spk_assert( false !== strpos( $audit_html, 'رویدادهای ثبت‌شده' ), 'عنوان تاریخچه' );

// تنظیمات.
foreach ( array( 'currency', 'display', 'general', 'capabilities', 'data' ) as $tab ) {
	$tab_html = spk_render_view(
		'settings/index',
		array(
			'tab'      => $tab,
			'tabs'     => array( 'currency' => 'واحد پول', 'display' => 'نمایش', 'general' => 'عمومی', 'capabilities' => 'دسترسی', 'data' => 'داده‌ها' ),
			'settings' => $settings,
			'health'   => Sparkknife_Ledger_Installer::get_health_report(),
			'matrix'   => Sparkknife_Ledger_Capabilities::get_role_matrix(),
			'roles'    => Sparkknife_Ledger_Capabilities::assignable_roles(),
			'caps'     => Sparkknife_Ledger_Capabilities::all(),
			'current'  => 'settings',
		),
		'تنظیمات — تب ' . $tab
	);

	spk_assert( false === strpos( $tab_html, 'Fatal error' ), 'بدون خطای مرگبار در تب ' . $tab );
}

// RTL در خروجی.
spk_assert( false !== strpos( $dashboard_html, 'dir="rtl"' ), 'صفحه با direction راست‌به‌چپ رندر می‌شود' );
spk_assert( false !== strpos( $dashboard_html, 'spk-wrap' ), 'کلاس ظرف اصلی' );

// escape خروجی: نام شخص دارای کاراکتر HTML نباید اجرا شود.
$evil_id = Sparkknife_Ledger_Person_Service::instance()->create( array( 'name' => 'شرکت <script>alert("xss")</script> تست' ) );
$evil    = Sparkknife_Ledger_Person_Repository::instance()->find( (int) $evil_id );

if ( $evil ) {
	$evil_list = Sparkknife_Ledger_Person_Repository::instance()->query( array( 'search' => 'شرکت', 'per_page' => 5 ) );

	$evil_html = Sparkknife_Ledger_Renderer::capture(
		'persons/list',
		array(
			'result'   => $evil_list,
			'balances' => array(),
			'args'     => array( 'search' => 'شرکت', 'status' => '', 'orderby' => 'created_at', 'order' => 'desc', 'only_deleted' => false, 'page' => 1, 'per_page' => 20 ),
			'view'     => 'all',
			'stats'    => array( 'total' => 1, 'active' => 1, 'inactive' => 0, 'deleted' => 0 ),
			'base_url' => admin_url( 'admin.php?page=sparkknife-ledger-persons' ),
			'current'  => 'persons',
		)
	);

	spk_assert( false === strpos( $evil_html, '<script>alert' ), 'اسکریپت مخرب در خروجی اجرا نمی‌شود' );
	spk_assert( false === stripos( $evil_html, 'alert("xss")' ), 'کد مخرب به خروجی نشت نکرده است' );
	spk_assert( false !== strpos( $evil_html, 'شرکت' ), 'بخش سالم نام حفظ شده است' );

	Sparkknife_Ledger_Person_Service::instance()->delete( (int) $evil_id );
}
