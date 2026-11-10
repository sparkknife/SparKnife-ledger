<?php
/**
 * سرویس مدیریت تراکنش‌ها.
 *
 * @author  SparKnife <155084046+sparkknife@users.noreply.github.com>
 *
 * @package SparKnife_Ledger
 */

defined( 'ABSPATH' ) || exit;

/**
 * ثبت، ویرایش و ابطال تراکنش‌ها با ثبت تاریخچه و عملیات اتمیک.
 */
class Sparkknife_Ledger_Transaction_Service {

	/**
	 * نمونه یکتا.
	 *
	 * @var Sparkknife_Ledger_Transaction_Service|null
	 */
	private static $instance = null;

	/**
	 * دریافت نمونه.
	 *
	 * @return Sparkknife_Ledger_Transaction_Service
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * وضعیت‌های تراکنش.
	 *
	 * @return array<string, string>
	 */
	public static function statuses() {
		return array(
			'posted' => __( 'ثبت‌شده', 'sparkknife-ledger' ),
			'void'   => __( 'باطل‌شده', 'sparkknife-ledger' ),
		);
	}

	/**
	 * برچسب وضعیت.
	 *
	 * @param string $status وضعیت.
	 * @return string
	 */
	public static function status_label( $status ) {
		$statuses = self::statuses();
		return isset( $statuses[ $status ] ) ? $statuses[ $status ] : __( 'نامشخص', 'sparkknife-ledger' );
	}

	/**
	 * تولید شناسه یکتای تراکنش.
	 *
	 * قالب: PREFIX-YYYYMMDD-XXXX (پیشوند قابل تنظیم + تاریخ + شماره ترتیبی روز).
	 *
	 * @param string $date تاریخ میلادی Y-m-d.
	 * @return string
	 */
	public function generate_reference( $date = '' ) {
		global $wpdb;

		$prefix = Sparkknife_Ledger_Settings::get( 'reference_prefix', 'TXN' );

		/*
		 * پیشوند می‌تواند فارسی باشد.
		 *
		 * پیش‌تر تنها `[A-Za-z0-9]` نگه داشته می‌شد، پس پیشوندی مثل
		 * «پیشوند-فارسی» یا «۱۲۳۴» به رشته خالی تبدیل می‌شد و **بی‌صدا**
		 * به `TXN` می‌افتاد — کاربر پیشوند را در تنظیمات می‌دید، ولی
		 * شماره تراکنش‌ها چیز دیگری بود. این افزونه فارسی است؛ پیشوند
		 * فارسی باید کار کند.
		 *
		 * ارقام فارسی/عربی به لاتین تبدیل می‌شوند تا شماره‌ها در هر
		 * جستجو و صادراتی یکسان دیده شوند، و خط تیره/زیرخط نگه داشته
		 * می‌شود (جداکننده طبیعی پیشوند است).
		 */
		$prefix = Sparkknife_Ledger_Money::to_latin_digits( (string) $prefix );
		$prefix = preg_replace( '/[^\p{L}\p{N}_-]/u', '', $prefix );

		if ( '' === $prefix ) {
			$prefix = 'TXN';
		}

		// ستون `reference` بیست‌وپنج نویسه جا می‌دهد؛ بیش از این، بریده می‌شود.
		$prefix = Sparkknife_Ledger_Sanitizer::limit( $prefix, 11 );

		$date = $date ? $date : Sparkknife_Ledger_Date::today();
		$stem = $prefix . '-' . gmdate( 'Ymd', strtotime( $date ) ) . '-';

		$table = Sparkknife_Ledger_DB::transactions_table();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$last = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT reference FROM {$table} WHERE reference LIKE %s ESCAPE '" . Sparkknife_Ledger_Sanitizer::LIKE_ESCAPE . "' ORDER BY id DESC LIMIT 1",
				Sparkknife_Ledger_Sanitizer::like_prefix( $stem )
			)
		);

		$next = 1;

		if ( is_string( $last ) && '' !== $last ) {
			$suffix = substr( $last, strlen( $stem ) );
			$next   = (int) $suffix + 1;
		}

		// تضمین یکتایی حتی در صورت درج همزمان.
		$attempts = 0;
		do {
			$reference = $stem . str_pad( (string) $next, 4, '0', STR_PAD_LEFT );
			$exists    = $this->reference_exists( $reference );
			$next++;
			$attempts++;
		} while ( $exists && $attempts < 1000 );

		if ( $exists ) {
			// افت‌پشتی نهایی: بخش تصادفی.
			$reference = $stem . strtoupper( substr( wp_generate_password( 8, false, false ), 0, 6 ) );
		}

		/**
		 * فیلتر شناسه تراکنش.
		 *
		 * @param string $reference شناسه تولیدشده.
		 */
		return apply_filters( 'sparkknife_ledger_generate_reference', $reference );
	}

	/**
	 * آیا شناسه تراکنش قبلاً استفاده شده است؟
	 *
	 * @param string $reference شناسه.
	 * @return bool
	 */
	private function reference_exists( $reference ) {
		return null !== Sparkknife_Ledger_Transaction_Repository::instance()->find_by_reference( $reference );
	}

	/**
	 * ثبت تراکنش جدید.
	 *
	 * @param array<string, mixed> $input ورودی خام.
	 * @return int|WP_Error شناسه تراکنش.
	 */
	public function create( array $input ) {
		$validated = Sparkknife_Ledger_Validator::validate_transaction( $input );

		if ( is_wp_error( $validated ) ) {
			return $validated;
		}

		$validated['reference'] = $this->generate_reference( $validated['txn_date'] );

		$id = Sparkknife_Ledger_DB::transaction(
			function () use ( $validated ) {
				return Sparkknife_Ledger_Transaction_Repository::instance()->insert( $validated );
			}
		);

		if ( is_wp_error( $id ) ) {
			return $id;
		}

		Sparkknife_Ledger_Audit_Service::instance()->log(
			'transaction',
			$id,
			'create',
			array(
				'reference'   => array( 'from' => null, 'to' => $validated['reference'] ),
				'person_id'   => array( 'from' => null, 'to' => $validated['person_id'] ),
				'type'        => array( 'from' => null, 'to' => $validated['type'] ),
				/*
				 * مبلغ در تاریخچه به شکل انسانی ذخیره می‌شود، نه مقیاس‌شده.
				 * `present_for_display()` هم همین انتظار را دارد و با
				 * `format( $value, false )` نمایشش می‌دهد. اگر یک‌جا
				 * مقیاس‌شده و جای دیگر انسانی ذخیره شود، یک مسیر ۱۰٬۰۰۰
				 * برابر خطا می‌دهد و مسیر دیگر درست است — و تفاوتشان فقط
				 * وقتی دیده می‌شود که کاربر همان ردیف را باز کند.
				 */
				'amount'      => array( 'from' => null, 'to' => Sparkknife_Ledger_Money::to_human( $validated['amount'] ) ),
				'txn_date'    => array( 'from' => null, 'to' => $validated['txn_date'] ),
				'description' => array( 'from' => null, 'to' => Sparkknife_Ledger_Sanitizer::limit( $validated['description'], 200 ) ),
			),
			sprintf(
				/* translators: 1: reference, 2: amount, 3: type */
				__( 'ثبت تراکنش %1$s به مبلغ %2$s (%3$s)', 'sparkknife-ledger' ),
				$validated['reference'],
				Sparkknife_Ledger_Money::format( $validated['amount'] ),
				Sparkknife_Ledger_Money::transaction_type_label( $validated['type'] )
			)
		);

		do_action( 'sparkknife_ledger_transaction_created', $id, $validated );

		return $id;
	}

	/**
	 * ویرایش تراکنش.
	 *
	 * تراکنش باطل‌شده قابل ویرایش نیست (برای حفظ یکپارچگی سوابق).
	 *
	 * @param int                  $id    شناسه.
	 * @param array<string, mixed> $input ورودی خام.
	 * @return true|WP_Error
	 */
	public function update( $id, array $input ) {
		$id = absint( $id );

		$transaction = Sparkknife_Ledger_Transaction_Repository::instance()->find( $id );

		if ( ! $transaction ) {
			return new WP_Error( 'sparkknife_ledger_transaction_not_found', __( 'تراکنش موردنظر یافت نشد.', 'sparkknife-ledger' ) );
		}

		if ( 'posted' !== $transaction->status ) {
			return new WP_Error(
				'sparkknife_ledger_transaction_is_void',
				__( 'تراکنش باطل‌شده قابل ویرایش نیست. برای اصلاح، تراکنش جدید ثبت کنید.', 'sparkknife-ledger' )
			);
		}

		$validated = Sparkknife_Ledger_Validator::validate_transaction( $input, $id );

		if ( is_wp_error( $validated ) ) {
			return $validated;
		}

		$before = array(
			'person_id'   => (int) $transaction->person_id,
			'type'        => $transaction->type,
			'amount'      => Sparkknife_Ledger_Amount::normalize( $transaction->amount ),
			'txn_date'    => $transaction->txn_date,
			'description' => (string) $transaction->description,
		);

		$result = Sparkknife_Ledger_DB::transaction(
			function () use ( $id, $validated ) {
				return Sparkknife_Ledger_Transaction_Repository::instance()->update(
					$id,
					array(
						'person_id'   => $validated['person_id'],
						'type'        => $validated['type'],
						'amount'      => $validated['amount'],
						'txn_date'    => $validated['txn_date'],
						'description' => $validated['description'],
					)
				);
			}
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$changes = Sparkknife_Ledger_Audit_Service::instance()->diff( $before, $validated, array( 'person_id', 'type', 'amount', 'txn_date', 'description' ) );

		if ( ! empty( $changes ) ) {
			Sparkknife_Ledger_Audit_Service::instance()->log(
				'transaction',
				$id,
				'update',
				$changes,
				sprintf(
					/* translators: %s: transaction reference */
					__( 'ویرایش تراکنش %s', 'sparkknife-ledger' ),
					$transaction->reference
				)
			);
		}

		do_action( 'sparkknife_ledger_transaction_updated', $id, $validated, $before );

		return true;
	}

	/**
	 * ابطال تراکنش (بدون حذف فیزیکی).
	 *
	 * @param int    $id     شناسه.
	 * @param string $reason دلیل ابطال.
	 * @return true|WP_Error
	 */
	public function void( $id, $reason ) {
		$id = absint( $id );

		$transaction = Sparkknife_Ledger_Transaction_Repository::instance()->find( $id );

		if ( ! $transaction ) {
			return new WP_Error( 'sparkknife_ledger_transaction_not_found', __( 'تراکنش موردنظر یافت نشد.', 'sparkknife-ledger' ) );
		}

		if ( 'posted' !== $transaction->status ) {
			return new WP_Error( 'sparkknife_ledger_already_void', __( 'این تراکنش قبلاً باطل شده است.', 'sparkknife-ledger' ) );
		}

		$reason = Sparkknife_Ledger_Validator::validate_void_reason( $reason );

		if ( is_wp_error( $reason ) ) {
			return $reason;
		}

		$result = Sparkknife_Ledger_DB::transaction(
			function () use ( $id, $reason ) {
				return Sparkknife_Ledger_Transaction_Repository::instance()->void( $id, $reason );
			}
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		/*
		 * فقط تغییرهای واقعی ثبت می‌شوند.
		 *
		 * پیش‌تر `amount` هم در این آرایه بود، با همان مقدار در هر دو
		 * طرف: `from` و `to` هر دو مبلغ تراکنش. صفحهٔ تاریخچه آن را
		 * «مبلغ: ۱۲۵٬۰۰۰ → ۱۲۵٬۰۰۰» نشان می‌داد — ردیفی که هیچ تغییری
		 * توصیف نمی‌کند. ابطال تراکنش مبلغ را دست نمی‌زند؛ تراکنش فقط
		 * از وضعیت `posted` به `void` می‌رود و مبلغش برای حسابرسی
		 * دست‌نخورده می‌ماند. مبلغ همان‌جا در خلاصهٔ ردیف دیده می‌شود.
		 */
		Sparkknife_Ledger_Audit_Service::instance()->log(
			'transaction',
			$id,
			'void',
			array(
				'status'      => array( 'from' => 'posted', 'to' => 'void' ),
				'void_reason' => array( 'from' => null, 'to' => $reason ),
			),
			sprintf(
				/* translators: 1: reference, 2: reason */
				__( 'ابطال تراکنش %1$s — دلیل: %2$s', 'sparkknife-ledger' ),
				$transaction->reference,
				$reason
			)
		);

		do_action( 'sparkknife_ledger_transaction_voided', $id, $transaction, $reason );

		return true;
	}

	/**
	 * بازگردانی تراکنش باطل‌شده به وضعیت ثبت‌شده.
	 *
	 * این قابلیت پیش‌فرض غیرفعال است و تنها با فیلتر
	 * sparkknife_ledger_allow_void_restore فعال می‌شود.
	 *
	 * @param int $id شناسه.
	 * @return true|WP_Error
	 */
	public function restore_void( $id ) {
		if ( ! apply_filters( 'sparkknife_ledger_allow_void_restore', false ) ) {
			return new WP_Error(
				'sparkknife_ledger_restore_disabled',
				__( 'بازگردانی تراکنش باطل‌شده در تنظیمات فعال نیست.', 'sparkknife-ledger' )
			);
		}

		$id          = absint( $id );
		$transaction = Sparkknife_Ledger_Transaction_Repository::instance()->find( $id );

		if ( ! $transaction ) {
			return new WP_Error( 'sparkknife_ledger_transaction_not_found', __( 'تراکنش موردنظر یافت نشد.', 'sparkknife-ledger' ) );
		}

		if ( 'void' !== $transaction->status ) {
			return new WP_Error( 'sparkknife_ledger_not_void', __( 'این تراکنش باطل نشده است.', 'sparkknife-ledger' ) );
		}

		$result = Sparkknife_Ledger_DB::transaction(
			function () use ( $id ) {
				return Sparkknife_Ledger_Transaction_Repository::instance()->update(
					$id,
					array(
						'status'      => 'posted',
						'void_reason' => null,
						'voided_by'   => 0,
						'voided_at'   => null,
					)
				);
			}
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		Sparkknife_Ledger_Audit_Service::instance()->log(
			'transaction',
			$id,
			'restore',
			array( 'status' => array( 'from' => 'void', 'to' => 'posted' ) ),
			sprintf(
				/* translators: %s: reference */
				__( 'بازگردانی تراکنش باطل‌شده %s', 'sparkknife-ledger' ),
				$transaction->reference
			)
		);

		return true;
	}
}
