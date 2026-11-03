<?php
/**
 * فرم فیلتر مشترک گزارش‌ها/تراکنش‌ها.
 *
 * @var string $action     نشانی فرم.
 * @var array  $ranges     بازه‌های آماده.
 * @var string $range      بازه فعال.
 * @var string $date_from  تاریخ شروع.
 * @var string $date_to    تاریخ پایان.
 * @var string $type       نوع تراکنش.
 * @var string $status     وضعیت.
 * @var string $search     جستجو.
 * @var array  $persons    فهرست اشخاص برای انتخاب.
 * @var int    $person_id  شخص انتخاب‌شده.
 * @var bool   $show_person نمایش انتخاب شخص.
 * @var bool   $show_type  نمایش فیلتر نوع.
 * @var bool   $show_status نمایش فیلتر وضعیت.
 * @var array  $hidden     فیلدهای مخفی.
 *
 * @package SparKnife_Ledger
 */

defined( 'ABSPATH' ) || exit;

$action      = isset( $action ) ? $action : '';
$ranges      = isset( $ranges ) ? $ranges : Sparkknife_Ledger_Ledger_Service::preset_ranges();
$range       = isset( $range ) ? $range : 'all';
$date_from   = isset( $date_from ) ? $date_from : '';
$date_to     = isset( $date_to ) ? $date_to : '';
$type        = isset( $type ) ? $type : '';
$status      = isset( $status ) ? $status : '';
$search      = isset( $search ) ? $search : '';
$persons     = isset( $persons ) ? $persons : array();
$person_id   = isset( $person_id ) ? (int) $person_id : 0;
$show_person = isset( $show_person ) ? (bool) $show_person : false;
$show_type   = isset( $show_type ) ? (bool) $show_type : true;
$show_status = isset( $show_status ) ? (bool) $show_status : true;
$hidden      = isset( $hidden ) && is_array( $hidden ) ? $hidden : array();

$has_filters = ( 'all' !== $range ) || '' !== $date_from || '' !== $date_to || '' !== $type || '' !== $status || '' !== $search || $person_id > 0;
?>
<form method="get" action="<?php echo esc_url( $action ); ?>" class="spk-filter" id="spk-filters">
	<?php foreach ( $hidden as $hidden_name => $hidden_value ) : ?>
		<input type="hidden" name="<?php echo esc_attr( $hidden_name ); ?>" value="<?php echo esc_attr( $hidden_value ); ?>" />
	<?php endforeach; ?>

	<div class="spk-filter__row">
		<div class="spk-field spk-field--compact">
			<label class="spk-field__label" for="spk-range"><?php esc_html_e( 'بازه زمانی', 'sparkknife-ledger' ); ?></label>
			<select name="range" id="spk-range" class="spk-input">
				<?php foreach ( $ranges as $range_key => $range_data ) : ?>
					<option value="<?php echo esc_attr( $range_key ); ?>" <?php selected( $range, $range_key ); ?>>
						<?php echo esc_html( $range_data['label'] ); ?>
					</option>
				<?php endforeach; ?>
				<option value="custom" <?php selected( $range, 'custom' ); ?>><?php esc_html_e( 'بازه دلخواه', 'sparkknife-ledger' ); ?></option>
			</select>
		</div>

		<div class="spk-field spk-field--compact spk-date-field" data-custom-range>
			<label class="spk-field__label" for="spk-date-from"><?php esc_html_e( 'از تاریخ', 'sparkknife-ledger' ); ?></label>
			<input type="text" class="spk-input" id="spk-date-from" name="date_from" value="<?php echo esc_attr( $date_from ); ?>"
				placeholder="<?php echo esc_attr( Sparkknife_Ledger_Date::today_for_input() ); ?>" inputmode="numeric" autocomplete="off" />
		</div>

		<div class="spk-field spk-field--compact spk-date-field" data-custom-range>
			<label class="spk-field__label" for="spk-date-to"><?php esc_html_e( 'تا تاریخ', 'sparkknife-ledger' ); ?></label>
			<input type="text" class="spk-input" id="spk-date-to" name="date_to" value="<?php echo esc_attr( $date_to ); ?>"
				placeholder="<?php echo esc_attr( Sparkknife_Ledger_Date::today_for_input() ); ?>" inputmode="numeric" autocomplete="off" />
		</div>

		<?php if ( $show_person ) : ?>
			<div class="spk-field spk-field--compact">
				<label class="spk-field__label" for="spk-person-filter"><?php esc_html_e( 'شخص', 'sparkknife-ledger' ); ?></label>
				<select name="person_id" id="spk-person-filter" class="spk-input">
					<option value="0"><?php esc_html_e( 'همه اشخاص', 'sparkknife-ledger' ); ?></option>
					<?php foreach ( $persons as $person_item ) : ?>
						<option value="<?php echo esc_attr( (string) $person_item->id ); ?>" <?php selected( $person_id, (int) $person_item->id ); ?>>
							<?php echo esc_html( $person_item->name ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>
		<?php endif; ?>

		<?php if ( $show_type ) : ?>
			<div class="spk-field spk-field--compact">
				<label class="spk-field__label" for="spk-type-filter"><?php esc_html_e( 'نوع تراکنش', 'sparkknife-ledger' ); ?></label>
				<select name="type" id="spk-type-filter" class="spk-input">
					<option value=""><?php esc_html_e( 'همه انواع', 'sparkknife-ledger' ); ?></option>
					<option value="income" <?php selected( $type, 'income' ); ?>><?php esc_html_e( 'ورودی', 'sparkknife-ledger' ); ?></option>
					<option value="expense" <?php selected( $type, 'expense' ); ?>><?php esc_html_e( 'خروجی', 'sparkknife-ledger' ); ?></option>
				</select>
			</div>
		<?php endif; ?>

		<?php if ( $show_status ) : ?>
			<div class="spk-field spk-field--compact">
				<label class="spk-field__label" for="spk-status-filter"><?php esc_html_e( 'وضعیت', 'sparkknife-ledger' ); ?></label>
				<select name="status" id="spk-status-filter" class="spk-input">
					<option value=""><?php esc_html_e( 'همه وضعیت‌ها', 'sparkknife-ledger' ); ?></option>
					<option value="posted" <?php selected( $status, 'posted' ); ?>><?php esc_html_e( 'ثبت‌شده', 'sparkknife-ledger' ); ?></option>
					<option value="void" <?php selected( $status, 'void' ); ?>><?php esc_html_e( 'باطل‌شده', 'sparkknife-ledger' ); ?></option>
				</select>
			</div>
		<?php endif; ?>

		<div class="spk-field spk-field--compact spk-field--grow">
			<label class="spk-field__label" for="spk-search"><?php esc_html_e( 'جستجو', 'sparkknife-ledger' ); ?></label>
			<input type="search" class="spk-input" id="spk-search" name="search" value="<?php echo esc_attr( $search ); ?>"
				placeholder="<?php esc_attr_e( 'نام، شناسه یا توضیحات…', 'sparkknife-ledger' ); ?>" />
		</div>

		<div class="spk-filter__buttons">
			<button type="submit" class="spk-btn spk-btn--primary">
				<span class="dashicons dashicons-filter" aria-hidden="true"></span>
				<?php esc_html_e( 'اعمال فیلتر', 'sparkknife-ledger' ); ?>
			</button>
			<?php if ( $has_filters ) : ?>
				<a class="spk-btn spk-btn--ghost" href="<?php echo esc_url( remove_query_arg( array( 'range', 'date_from', 'date_to', 'type', 'status', 'search', 'person_id', 'paged' ) ) ); ?>">
					<?php esc_html_e( 'حذف فیلترها', 'sparkknife-ledger' ); ?>
				</a>
			<?php endif; ?>
		</div>
	</div>
</form>
