<?php
/**
 * سرویس مدیریت اشخاص.
 *
 * @package SparKnife_Ledger
 */

defined( 'ABSPATH' ) || exit;

/**
 * منطق دامنه اشخاص.
 */
class Sparkknife_Ledger_Person_Service {

	/**
	 * نمونه یکتا.
	 *
	 * @var Sparkknife_Ledger_Person_Service|null
	 */
	private static $instance = null;

	/**
	 * دریافت نمونه.
	 *
	 * @return Sparkknife_Ledger_Person_Service
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * فهرست وضعیت‌های مجاز.
	 *
	 * @return array<string, string>
	 */
	public static function statuses() {
		return array(
			'active'   => __( 'فعال', 'sparkknife-ledger' ),
			'inactive' => __( 'غیرفعال', 'sparkknife-ledger' ),
		);
	}

	/**
	 * ایجاد شخص جدید.
	 *
	 * @param array<string, mixed> $input ورودی خام.
	 * @return int|WP_Error شناسه شخص.
	 */
	public function create( array $input ) {
		$validated = Sparkknife_Ledger_Validator::validate_person( $input );

		if ( is_wp_error( $validated ) ) {
			return $validated;
		}

		$existing = $this->find_duplicate( $validated['name'], $validated['phone'] );

		if ( $existing ) {
			return new WP_Error(
				'sparkknife_ledger_person_duplicate',
				__( 'شخص دیگری با همین نام و شماره تماس ثبت شده است.', 'sparkknife-ledger' )
			);
		}

		$id = Sparkknife_Ledger_DB::transaction(
			function () use ( $validated ) {
				return Sparkknife_Ledger_Person_Repository::instance()->insert( $validated );
			}
		);

		if ( is_wp_error( $id ) ) {
			return $id;
		}

		Sparkknife_Ledger_Audit_Service::instance()->log(
			'person',
			$id,
			'create',
			array(
				'name'   => array( 'from' => null, 'to' => $validated['name'] ),
				'phone'  => array( 'from' => null, 'to' => $validated['phone'] ),
				'status' => array( 'from' => null, 'to' => $validated['status'] ),
			),
			sprintf(
				/* translators: %s: person name */
				__( 'ایجاد شخص «%s»', 'sparkknife-ledger' ),
				$validated['name']
			)
		);

		do_action( 'sparkknife_ledger_person_created', $id, $validated );

		return $id;
	}

	/**
	 * ویرایش شخص.
	 *
	 * @param int                  $id    شناسه.
	 * @param array<string, mixed> $input ورودی خام.
	 * @return true|WP_Error
	 */
	public function update( $id, array $input ) {
		$id     = absint( $id );
		$person = Sparkknife_Ledger_Person_Repository::instance()->find( $id );

		if ( ! $person ) {
			return new WP_Error( 'sparkknife_ledger_person_not_found', __( 'شخص موردنظر یافت نشد.', 'sparkknife-ledger' ) );
		}

		$validated = Sparkknife_Ledger_Validator::validate_person( $input, $id );

		if ( is_wp_error( $validated ) ) {
			return $validated;
		}

		$existing = $this->find_duplicate( $validated['name'], $validated['phone'], $id );

		if ( $existing ) {
			return new WP_Error(
				'sparkknife_ledger_person_duplicate',
				__( 'شخص دیگری با همین نام و شماره تماس ثبت شده است.', 'sparkknife-ledger' )
			);
		}

		$before = array(
			'name'        => $person->name,
			'phone'       => $person->phone,
			'description' => $person->description,
			'status'      => $person->status,
		);

		$result = Sparkknife_Ledger_DB::transaction(
			function () use ( $id, $validated ) {
				return Sparkknife_Ledger_Person_Repository::instance()->update( $id, $validated );
			}
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$changes = Sparkknife_Ledger_Audit_Service::instance()->diff( $before, $validated, array( 'name', 'phone', 'description', 'status' ) );

		if ( ! empty( $changes ) ) {
			$action = isset( $changes['status'] ) && 1 === count( $changes ) ? 'status_change' : 'update';

			Sparkknife_Ledger_Audit_Service::instance()->log(
				'person',
				$id,
				$action,
				$changes,
				sprintf(
					/* translators: %s: person name */
					__( 'ویرایش اطلاعات شخص «%s»', 'sparkknife-ledger' ),
					$validated['name']
				)
			);
		}

		do_action( 'sparkknife_ledger_person_updated', $id, $validated );

		return true;
	}

	/**
	 * تغییر وضعیت فعال/غیرفعال.
	 *
	 * @param int    $id     شناسه.
	 * @param string $status وضعیت جدید.
	 * @return true|WP_Error
	 */
	public function set_status( $id, $status ) {
		$id = absint( $id );

		if ( ! in_array( $status, array( 'active', 'inactive' ), true ) ) {
			return new WP_Error( 'sparkknife_ledger_invalid_status', __( 'وضعیت انتخاب‌شده معتبر نیست.', 'sparkknife-ledger' ) );
		}

		$person = Sparkknife_Ledger_Person_Repository::instance()->find( $id );

		if ( ! $person ) {
			return new WP_Error( 'sparkknife_ledger_person_not_found', __( 'شخص موردنظر یافت نشد.', 'sparkknife-ledger' ) );
		}

		return $this->update(
			$id,
			array(
				'name'        => $person->name,
				'phone'       => $person->phone,
				'description' => $person->description,
				'status'      => $status,
			)
		);
	}

	/**
	 * حذف نرم شخص.
	 *
	 * حذف تنها زمانی مجاز است که شخص تراکنش نداشته باشد؛ در غیر این صورت
	 * باید غیرفعال شود تا سوابق مالی حفظ شوند. این تصمیم در سند معماری
	 * مستند شده است.
	 *
	 * @param int $id شناسه.
	 * @return true|WP_Error
	 */
	public function delete( $id ) {
		$id     = absint( $id );
		$person = Sparkknife_Ledger_Person_Repository::instance()->find( $id );

		if ( ! $person ) {
			return new WP_Error( 'sparkknife_ledger_person_not_found', __( 'شخص موردنظر یافت نشد.', 'sparkknife-ledger' ) );
		}

		if ( ! Sparkknife_Ledger_Settings::get( 'allow_person_delete', 1 ) ) {
			return new WP_Error(
				'sparkknife_ledger_delete_disabled',
				__( 'حذف اشخاص در تنظیمات غیرفعال شده است؛ از گزینه غیرفعال‌سازی استفاده کنید.', 'sparkknife-ledger' )
			);
		}

		$transaction_count = Sparkknife_Ledger_Person_Repository::instance()->count_transactions( $id );

		if ( $transaction_count > 0 ) {
			return new WP_Error(
				'sparkknife_ledger_person_has_transactions',
				__( 'این شخص دارای سابقه تراکنش است و حذف نمی‌شود؛ برای حفظ سوابق مالی، او را غیرفعال کنید.', 'sparkknife-ledger' )
			);
		}

		$result = Sparkknife_Ledger_DB::transaction(
			function () use ( $id ) {
				return Sparkknife_Ledger_Person_Repository::instance()->soft_delete( $id );
			}
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		Sparkknife_Ledger_Audit_Service::instance()->log(
			'person',
			$id,
			'delete',
			array(
				'name'   => array( 'from' => $person->name, 'to' => null ),
				'status' => array( 'from' => $person->status, 'to' => 'inactive' ),
			),
			sprintf(
				/* translators: %s: person name */
				__( 'حذف (نرم) شخص «%s»', 'sparkknife-ledger' ),
				$person->name
			)
		);

		do_action( 'sparkknife_ledger_person_deleted', $id );

		return true;
	}

	/**
	 * بازگردانی شخص حذف‌شده.
	 *
	 * @param int $id شناسه.
	 * @return true|WP_Error
	 */
	public function restore( $id ) {
		$id     = absint( $id );
		$person = Sparkknife_Ledger_Person_Repository::instance()->find( $id, true );

		if ( ! $person ) {
			return new WP_Error( 'sparkknife_ledger_person_not_found', __( 'شخص موردنظر یافت نشد.', 'sparkknife-ledger' ) );
		}

		if ( empty( $person->deleted_at ) ) {
			return new WP_Error( 'sparkknife_ledger_person_not_deleted', __( 'این شخص حذف نشده است.', 'sparkknife-ledger' ) );
		}

		$result = Sparkknife_Ledger_DB::transaction(
			function () use ( $id ) {
				return Sparkknife_Ledger_Person_Repository::instance()->restore( $id );
			}
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		Sparkknife_Ledger_Audit_Service::instance()->log(
			'person',
			$id,
			'restore',
			array( 'status' => array( 'from' => 'deleted', 'to' => 'active' ) ),
			sprintf(
				/* translators: %s: person name */
				__( 'بازگردانی شخص «%s»', 'sparkknife-ledger' ),
				$person->name
			)
		);

		return true;
	}

	/**
	 * یافتن شخص تکراری.
	 *
	 * @param string $name     نام.
	 * @param string $phone    تلفن.
	 * @param int    $exclude  شناسه استثنا.
	 * @return bool
	 */
	public function find_duplicate( $name, $phone, $exclude = 0 ) {
		$result = Sparkknife_Ledger_Person_Repository::instance()->query(
			array(
				'search'   => $name,
				'per_page' => 200,
				'page'     => 1,
			)
		);

		foreach ( $result['items'] as $item ) {
			if ( (int) $item->id === (int) $exclude ) {
				continue;
			}

			if ( 0 !== strcasecmp( trim( $item->name ), trim( $name ) ) ) {
				continue;
			}

			// اگر هر دو شماره خالی باشند، نام یکسان کافی نیست مگر تلفن هم یکسان باشد.
			if ( '' === trim( (string) $item->phone ) && '' === trim( (string) $phone ) ) {
				return true;
			}

			if ( preg_replace( '/\D/', '', (string) $item->phone ) === preg_replace( '/\D/', '', (string) $phone ) ) {
				return true;
			}
		}

		return false;
	}
}
