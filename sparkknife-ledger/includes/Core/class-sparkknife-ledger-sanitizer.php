<?php
/**
 * پاک‌سازی متمرکز ورودی‌ها.
 *
 * @package SparKnife_Ledger
 */

defined( 'ABSPATH' ) || exit;

/**
 * پاک‌سازی ورودی‌های فرم.
 */
class Sparkknife_Ledger_Sanitizer {

	/**
	 * متن ساده تک‌خطی.
	 *
	 * @param mixed $value ورودی.
	 * @param int   $max_length حداکثر طول.
	 * @return string
	 */
	public static function text( $value, $max_length = 190 ) {
		$value = sanitize_text_field( wp_unslash( (string) $value ) );
		return self::limit( $value, $max_length );
	}

	/**
	 * متن چندخطی.
	 *
	 * @param mixed $value ورودی.
	 * @param int   $max_length حداکثر طول.
	 * @return string
	 */
	public static function textarea( $value, $max_length = 2000 ) {
		$value = sanitize_textarea_field( wp_unslash( (string) $value ) );
		return self::limit( $value, $max_length );
	}

	/**
	 * شناسه عددی مثبت.
	 *
	 * @param mixed $value ورودی.
	 * @return int
	 */
	public static function id( $value ) {
		return absint( is_scalar( $value ) ? $value : 0 );
	}

	/**
	 * کلید (حروف کوچک، خط تیره).
	 *
	 * @param mixed $value ورودی.
	 * @return string
	 */
	public static function key( $value ) {
		return sanitize_key( wp_unslash( (string) $value ) );
	}

	/**
	 * شماره تماس: ارقام لاتین، فاصله، خط تیره و +.
	 *
	 * @param mixed $value ورودی.
	 * @return string
	 */
	public static function phone( $value ) {
		$value = Sparkknife_Ledger_Money::to_latin_digits( (string) $value );
		$value = preg_replace( '/[^0-9+\-\s()]/', '', $value );
		$value = trim( preg_replace( '/\s+/', ' ', (string) $value ) );

		return self::limit( $value, 40 );
	}

	/**
	 * تاریخ ورودی (پاک‌سازی سبک؛ اعتبارسنجی در Validator).
	 *
	 * @param mixed $value ورودی.
	 * @return string
	 */
	public static function date( $value ) {
		$value = Sparkknife_Ledger_Money::to_latin_digits( (string) $value );
		$value = preg_replace( '/[^0-9\/\-]/', '', $value );

		return self::limit( (string) $value, 10 );
	}

	/**
	 * فیلد ورودی مبلغ (پاک‌سازی سبک؛ تبدیل در Money).
	 *
	 * @param mixed $value ورودی.
	 * @return string
	 */
	public static function amount( $value ) {
		$value = Sparkknife_Ledger_Money::to_latin_digits( (string) $value );
		$value = str_replace( array( '٫', '،', '٬' ), array( '.', ',', '' ), $value );
		$value = preg_replace( '/[^0-9.,\s\-]/', '', (string) $value );

		return self::limit( $value, 30 );
	}

	/**
	 * کوتاه کردن ایمن رشته.
	 *
	 * @param string $value رشته.
	 * @param int    $max_length حداکثر طول.
	 * @return string
	 */
	public static function limit( $value, $max_length ) {
		$value      = (string) $value;
		$max_length = absint( $max_length );

		if ( $max_length <= 0 || '' === $value ) {
			return $value;
		}

		if ( function_exists( 'mb_strlen' ) ) {
			return mb_strlen( $value ) > $max_length ? mb_substr( $value, 0, $max_length ) : $value;
		}

		return strlen( $value ) > $max_length ? substr( $value, 0, $max_length ) : $value;
	}

	/**
	 * اعتبارسنجی Nonce و توقف در صورت خطا.
	 *
	 * @param string $action نام action.
	 * @param string $field  نام فیلد nonce.
	 * @return void
	 */
	public static function verify_nonce( $action, $field = '_wpnonce' ) {
		$nonce = isset( $_REQUEST[ $field ] ) ? sanitize_text_field( wp_unslash( $_REQUEST[ $field ] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, $action ) ) {
			wp_die(
				esc_html__( 'اعتبار درخواست منقضی شده است. لطفاً صفحه را دوباره بارگذاری کرده و مجدداً تلاش کنید.', 'sparkknife-ledger' ),
				esc_html__( 'خطای امنیتی', 'sparkknife-ledger' ),
				array( 'response' => 403 )
			);
		}
	}

	/**
	 * خواندن فیلد POST از طریق غیرفعال‌سازی موقت magic quotes (در صورت وجود).
	 *
	 * @param string $key نام فیلد.
	 * @return string
	 */
	public static function raw_post( $key ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- این متد فقط دسترسی به مقدار خام است؛ بررسی nonce در لایه کنترلر انجام می‌شود.
		return isset( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : '';
	}
}
