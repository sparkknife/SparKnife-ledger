<?php
/**
 * اعتبارسنجی قواعد دامنه.
 *
 * @package SparKnife_Ledger
 */

defined( 'ABSPATH' ) || exit;

/**
 * جمع‌کننده خطاهای اعتبارسنجی.
 */
class Sparkknife_Ledger_Validator {

	/**
	 * خطاهای ثبت‌شده به تفکیک فیلد.
	 *
	 * @var array<string, string>
	 */
	private $errors = array();

	/**
	 * داده پاک‌سازی‌شده.
	 *
	 * @var array<string, mixed>
	 */
	private $data = array();

	/**
	 * افزودن خطا.
	 *
	 * @param string $field   نام فیلد.
	 * @param string $message پیام.
	 * @return void
	 */
	public function add_error( $field, $message ) {
		if ( ! isset( $this->errors[ $field ] ) ) {
			$this->errors[ $field ] = $message;
		}
	}

	/**
	 * آیا خطایی وجود دارد؟
	 *
	 * @return bool
	 */
	public function has_errors() {
		return ! empty( $this->errors );
	}

	/**
	 * فهرست خطاها.
	 *
	 * @return array<string, string>
	 */
	public function errors() {
		return $this->errors;
	}

	/**
	 * اولین پیام خطا.
	 *
	 * @return string
	 */
	public function first_error() {
		$values = array_values( $this->errors );
		return isset( $values[0] ) ? $values[0] : '';
	}

	/**
	 * افزودن داده معتبر به خروجی.
	 *
	 * @param string $key   کلید.
	 * @param mixed  $value مقدار.
	 * @return void
	 */
	public function set( $key, $value ) {
		$this->data[ $key ] = $value;
	}

	/**
	 * داده معتبر.
	 *
	 * @return array<string, mixed>
	 */
	public function data() {
		return $this->data;
	}

	/**
	 * مقدار یک فیلد داده.
	 *
	 * @param string $key     کلید.
	 * @param mixed  $default پیش‌فرض.
	 * @return mixed
	 */
	public function get( $key, $default = null ) {
		return array_key_exists( $key, $this->data ) ? $this->data[ $key ] : $default;
	}

	/**
	 * ساخت WP_Error از خطاها.
	 *
	 * @return WP_Error
	 */
	public function to_wp_error() {
		return new WP_Error( 'sparkknife_ledger_validation_failed', $this->first_error(), $this->errors );
	}

	/*
	|--------------------------------------------------------------------------
	| قواعد اعتبارسنجی اشخاص
	|--------------------------------------------------------------------------
	*/

	/**
	 * اعتبارسنجی داده شخص.
	 *
	 * @param array<string, mixed> $input     ورودی پاک‌سازی‌نشده.
	 * @param int                  $person_id شناسه در حالت ویرایش (۰ برای ایجاد).
	 * @return array<string, mixed>|WP_Error
	 */
	public static function validate_person( array $input, $person_id = 0 ) {
		$validator = new self();
		$person_id = absint( $person_id );

		$name = Sparkknife_Ledger_Sanitizer::text( isset( $input['name'] ) ? $input['name'] : '', 190 );

		if ( '' === $name ) {
			$validator->add_error( 'name', __( 'نام شخص الزامی است.', 'sparkknife-ledger' ) );
		} elseif ( function_exists( 'mb_strlen' ) && mb_strlen( $name ) < 2 ) {
			$validator->add_error( 'name', __( 'نام شخص باید حداقل دو نویسه باشد.', 'sparkknife-ledger' ) );
		}

		$phone = Sparkknife_Ledger_Sanitizer::phone( isset( $input['phone'] ) ? $input['phone'] : '' );

		if ( '' !== $phone ) {
			$digits = preg_replace( '/\D/', '', $phone );

			if ( strlen( $digits ) < 4 || strlen( $digits ) > 20 ) {
				$validator->add_error( 'phone', __( 'شماره تماس معتبر نیست (۴ تا ۲۰ رقم).', 'sparkknife-ledger' ) );
			}
		}

		$description = Sparkknife_Ledger_Sanitizer::textarea( isset( $input['description'] ) ? $input['description'] : '', 2000 );

		$status = isset( $input['status'] ) ? Sparkknife_Ledger_Sanitizer::key( $input['status'] ) : 'active';
		if ( ! in_array( $status, array( 'active', 'inactive' ), true ) ) {
			$status = 'active';
		}

		if ( $person_id && 'inactive' === $status ) {
			$active_transactions = Sparkknife_Ledger_Transaction_Repository::instance()->count_active_for_person( $person_id );

			if ( $active_transactions > 0 ) {
				// غیرفعال‌سازی مجاز است اما به کاربر اطلاع داده می‌شود؛ مانده حفظ می‌شود.
				$validator->set( 'notice_inactive_with_balance', 1 );
			}
		}

		$validator->set( 'name', $name );
		$validator->set( 'phone', $phone );
		$validator->set( 'description', $description );
		$validator->set( 'status', $status );

		if ( $validator->has_errors() ) {
			return $validator->to_wp_error();
		}

		return $validator->data();
	}

	/*
	|--------------------------------------------------------------------------
	| قواعد اعتبارسنجی تراکنش‌ها
	|--------------------------------------------------------------------------
	*/

	/**
	 * اعتبارسنجی داده تراکنش.
	 *
	 * @param array<string, mixed> $input          ورودی پاک‌سازی‌نشده.
	 * @param int                  $transaction_id شناسه در حالت ویرایش.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function validate_transaction( array $input, $transaction_id = 0 ) {
		$validator    = new self();
		$transaction_id = absint( $transaction_id );

		$person_id = Sparkknife_Ledger_Sanitizer::id( isset( $input['person_id'] ) ? $input['person_id'] : 0 );

		if ( $person_id <= 0 ) {
			$validator->add_error( 'person_id', __( 'انتخاب شخص الزامی است.', 'sparkknife-ledger' ) );
		} else {
			$person = Sparkknife_Ledger_Person_Repository::instance()->find( $person_id );

			if ( ! $person ) {
				$validator->add_error( 'person_id', __( 'شخص انتخاب‌شده یافت نشد.', 'sparkknife-ledger' ) );
			} elseif ( 'active' !== $person->status && ! $transaction_id ) {
				$validator->add_error( 'person_id', __( 'شخص انتخاب‌شده غیرفعال است؛ ثبت تراکنش جدید برای او مجاز نیست.', 'sparkknife-ledger' ) );
			}
		}

		$type = isset( $input['type'] ) ? Sparkknife_Ledger_Sanitizer::key( $input['type'] ) : '';

		if ( ! in_array( $type, array( 'income', 'expense' ), true ) ) {
			$validator->add_error( 'type', __( 'نوع تراکنش باید ورودی یا خروجی باشد.', 'sparkknife-ledger' ) );
		}

		$amount_raw = isset( $input['amount'] ) ? Sparkknife_Ledger_Sanitizer::amount( $input['amount'] ) : '';
		$amount     = Sparkknife_Ledger_Money::parse_amount( $amount_raw );

		if ( is_wp_error( $amount ) ) {
			$validator->add_error( 'amount', $amount->get_error_message() );
			$amount = '0';
		}

		$date_input = isset( $input['txn_date'] ) ? Sparkknife_Ledger_Sanitizer::date( $input['txn_date'] ) : '';
		$txn_date   = Sparkknife_Ledger_Date::to_storage( $date_input );

		if ( is_wp_error( $txn_date ) ) {
			$validator->add_error( 'txn_date', $txn_date->get_error_message() );
			$txn_date = '';
		} elseif ( '' !== $txn_date && Sparkknife_Ledger_Date::is_future( $txn_date ) ) {
			$validator->add_error( 'txn_date', __( 'تاریخ تراکنش نمی‌تواند در آینده باشد.', 'sparkknife-ledger' ) );
		}

		$description = Sparkknife_Ledger_Sanitizer::textarea( isset( $input['description'] ) ? $input['description'] : '', 2000 );

		$validator->set( 'person_id', $person_id );
		$validator->set( 'type', $type );
		$validator->set( 'amount', (string) $amount );
		$validator->set( 'txn_date', $txn_date );
		$validator->set( 'description', $description );

		if ( $validator->has_errors() ) {
			return $validator->to_wp_error();
		}

		return $validator->data();
	}

	/**
	 * اعتبارسنجی دلیل ابطال.
	 *
	 * @param string $reason دلیل.
	 * @return string|WP_Error
	 */
	public static function validate_void_reason( $reason ) {
		$reason   = Sparkknife_Ledger_Sanitizer::textarea( $reason, 500 );
		$required = (bool) Sparkknife_Ledger_Settings::get( 'require_void_reason', 1 );

		if ( $required && '' === trim( $reason ) ) {
			return new WP_Error(
				'sparkknife_ledger_void_reason_required',
				__( 'ثبت دلیل ابطال الزامی است.', 'sparkknife-ledger' )
			);
		}

		if ( $required && function_exists( 'mb_strlen' ) && mb_strlen( trim( $reason ) ) < 3 ) {
			return new WP_Error(
				'sparkknife_ledger_void_reason_short',
				__( 'دلیل ابطال باید حداقل سه نویسه باشد.', 'sparkknife-ledger' )
			);
		}

		return $reason;
	}
}
