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
			$only_status = isset( $changes['status'] ) && 1 === count( $changes );
			$action      = $only_status ? 'status_change' : 'update';

			/*
			 * خلاصه باید با action بخواند.
			 *
			 * پیش‌تر `$action` در حالت تغییرِ فقط-وضعیت `status_change`
			 * می‌شد، ولی متن خلاصه همیشه «ویرایش اطلاعات شخص» می‌ماند.
			 * نتیجه در تاریخچه:
			 *
			 *     [غیرفعال‌سازی]  ویرایش اطلاعات شخص «آرش محمدی»
			 *
			 * کاربر با دیدن این ردیف فکر می‌کند نام یا تلفن هم عوض شده
			 * و برای یافتن تغییر به‌دنبال جزئیاتی می‌گردد که وجود ندارد.
			 * حالا خلاصه می‌گوید «تغییر وضعیت شخص».
			 */
			Sparkknife_Ledger_Audit_Service::instance()->log(
				'person',
				$id,
				$action,
				$changes,
				$only_status
					? sprintf(
						/* translators: 1: person name, 2: new status label */
						__( 'تغییر وضعیت شخص «%1$s» به %2$s', 'sparkknife-ledger' ),
						$validated['name'],
						'active' === $validated['status']
							? __( 'فعال', 'sparkknife-ledger' )
							: __( 'غیرفعال', 'sparkknife-ledger' )
					)
					: sprintf(
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

		/*
		 * مقایسه باید با همان قاعده‌ای باشد که جستجو استفاده می‌کند.
		 *
		 * پیش‌تر نام با `strcasecmp` خام مقایسه می‌شد، پس «علي رضایی» و
		 * «علی رضایی» دو شخص جدا شمرده می‌شدند — در حالی که جستجو آن دو
		 * را یکی می‌داند. کاربر می‌توانست برای یک نفر دو پرونده بسازد و
		 * بعد در گزارش‌ها دنبال «مانده واقعی» بگردد.
		 *
		 * شماره هم با `\D` پاک می‌شد که ارقام فارسی و عربی را **حذف**
		 * می‌کند، نه تبدیل؛ پس «۰۹۱۲۱۱۱۰۰۰۱» به رشته خالی می‌رسید.
		 */
		$needle_name  = $this->duplicate_name_key( $name );
		$needle_phone = $this->duplicate_phone_key( $phone );

		foreach ( $result['items'] as $item ) {
			if ( (int) $item->id === (int) $exclude ) {
				continue;
			}

			if ( $this->duplicate_name_key( $item->name ) !== $needle_name ) {
				continue;
			}

			$item_phone = $this->duplicate_phone_key( $item->phone );

			/*
			 * اگر هیچ‌کدام شماره ندارند، نام یکسان کافی است.
			 * اگر یکی شماره دارد و دیگری نه، دو شخص متفاوت‌اند.
			 */
			if ( '' === $item_phone && '' === $needle_phone ) {
				return true;
			}

			if ( '' !== $item_phone && $item_phone === $needle_phone ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * کلید مقایسه نام برای تشخیص تکراری.
	 *
	 * همان یکسان‌سازی فارسی که در جستجو به کار می‌رود، به‌علاوه حذف
	 * فاصله‌های تکراری و نویسه‌های بی‌عرض.
	 *
	 * @param string $name نام.
	 * @return string
	 */
	private function duplicate_name_key( $name ) {
		$name = Sparkknife_Ledger_Sanitizer::normalize_persian( (string) $name );
		$name = preg_replace( '/\s+/u', ' ', $name );

		return mb_strtolower( trim( (string) $name ), 'UTF-8' );
	}

	/**
	 * کلید مقایسه شماره تماس برای تشخیص تکراری.
	 *
	 * ارقام فارسی و عربی نخست به لاتین تبدیل می‌شوند و سپس هر نویسه
	 * غیررقمی حذف می‌گردد. خروجی برای شماره‌های بدون رقم رشته خالی است
	 * و فراخوان باید آن را «شماره ندارد» تفسیر کند.
	 *
	 * @param string $phone شماره.
	 * @return string
	 */
	private function duplicate_phone_key( $phone ) {
		$phone = Sparkknife_Ledger_Money::to_latin_digits( (string) $phone );

		return (string) preg_replace( '/\D/', '', $phone );
	}
}
