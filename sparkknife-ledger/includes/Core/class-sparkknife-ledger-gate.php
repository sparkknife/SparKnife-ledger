<?php
/**
 * محدودیت‌های نسخهٔ رایگان.
 *
 * این کلاس تنها جایی است که سیاست «چه چیزی رایگان است و چه چیزی نه»
 * تعریف می‌شود. هر جای دیگری از کد که بخواهد بداند آیا کاری مجاز است،
 * از همین‌جا می‌پرسد.
 *
 * چرا یک کلاس جدا و نه `if` پراکنده در سرویس‌ها:
 *
 *   ۱) تغییر سیاست یک فایل است، نه جستجوی کل کد.
 *   ۲) آزمون‌پذیر است. می‌توان همهٔ محدودیت‌ها را در یک جا آزمود.
 *   ۳) صفحهٔ «نسخهٔ رایگان» می‌تواند دقیقاً همان اعدادی را نشان دهد که
 *      اعمال می‌شوند، نه اعدادی که در جای دیگری سخت‌کد شده‌اند.
 *
 * @author  SparKnife <155084046+sparkknife@users.noreply.github.com>
 *
 * @package SparKnife_Ledger
 */

defined( 'ABSPATH' ) || exit;

/**
 * سیاست نسخهٔ رایگان.
 */
class Sparkknife_Ledger_Gate {

	/**
	 * حداکثر اشخاص در نسخهٔ رایگان.
	 *
	 * عدد عمداً ۵۰ است: برای دفتر یک کسب‌وکار کوچک کافی است تا ابزار
	 * واقعاً استفاده شود، ولی برای کسی که چند سال دفتر دارد زود می‌رسد.
	 * اگر ۱۰ می‌گذاشتیم، کاربر همان هفتهٔ اول به سقف می‌خورد و افزونه
	 * را پاک می‌کرد بدون اینکه به ارزشش پی ببرد.
	 *
	 * @var int
	 */
	const FREE_PERSONS = 50;

	/**
	 * حداکثر تراکنش در نسخهٔ رایگان.
	 *
	 * ۵۰۰ تراکنش برای استفادهٔ واقعی کم نیست — یک سال دفتر روزانه
	 * تقریباً همین است. محدودیت تراکنش عمداً سخاوتمندانه است چون
	 * تراکنش همان جایی است که ارزش ابزار دیده می‌شود.
	 *
	 * @var int
	 */
	const FREE_TRANSACTIONS = 500;

	/**
	 * آیا این قابلیت در نسخهٔ فعلی باز است؟
	 *
	 * قابلیت‌هایی که فقط در pro بازند:
	 *   - excel   : خروجی اکسل با قالب
	 *   - multi   : گزارش چند-شخصی
	 *   - remind  : یادآوری خودکار بدهی
	 *   - roles   : دسترسی چند-کاربری
	 *
	 * @param string $feature نام قابلیت.
	 * @return bool
	 */
	public static function is_open( $feature ) {
		$open = Sparkknife_Ledger_License::is_active();

		/**
		 * فیلتر باز بودن قابلیت — برای آزمون و برای کاربرانی که
		 * می‌خواهند همه‌چیز را باز کنند.
		 *
		 * @param bool   $open    وضعیت پیش‌فرض.
		 * @param string $feature نام قابلیت.
		 */
		return (bool) apply_filters( 'sparkknife_ledger_feature_open', $open, $feature );
	}

	/**
	 * آیا اجازهٔ افزودن شخص تازه هست؟
	 *
	 * @param int $current تعداد فعلی اشخاص.
	 * @return true|WP_Error
	 */
	public static function can_add_person( $current ) {
		if ( self::is_open( 'persons' ) ) {
			return true;
		}

		if ( (int) $current >= self::FREE_PERSONS ) {
			return new WP_Error(
				'sparkknife_ledger_limit_persons',
				sprintf(
					/* translators: %s: limit (با ارقام فارسی) */
					__( 'نسخهٔ رایگان تا %s شخص. برای ثبت اشخاص بیشتر، لایسنس تهیه کنید.', 'sparkknife-ledger' ),
					Sparkknife_Ledger_Money::digits( (string) self::FREE_PERSONS )
				),
				array( 'limit' => self::FREE_PERSONS, 'current' => (int) $current )
			);
		}

		return true;
	}

	/**
	 * آیا اجازهٔ ثبت تراکنش تازه هست؟
	 *
	 * @param int $current تعداد فعلی تراکنش‌ها.
	 * @return true|WP_Error
	 */
	public static function can_add_transaction( $current ) {
		if ( self::is_open( 'transactions' ) ) {
			return true;
		}

		if ( (int) $current >= self::FREE_TRANSACTIONS ) {
			return new WP_Error(
				'sparkknife_ledger_limit_transactions',
				sprintf(
					/* translators: %s: limit (با ارقام فارسی) */
					__( 'نسخهٔ رایگان تا %s تراکنش. برای ثبت تراکنش بیشتر، لایسنس تهیه کنید.', 'sparkknife-ledger' ),
					Sparkknife_Ledger_Money::digits( (string) self::FREE_TRANSACTIONS )
				),
				array( 'limit' => self::FREE_TRANSACTIONS, 'current' => (int) $current )
			);
		}

		return true;
	}

	/**
	 * وضعیت مصرف برای نمایش در صفحهٔ سلامت.
	 *
	 * @return array<string, mixed>
	 */
	public static function usage() {
		$persons      = Sparkknife_Ledger_Person_Repository::instance()->count_by_status( 'all' );
		$transactions = Sparkknife_Ledger_Transaction_Repository::instance()->count( 'all' );

		return array(
			'licensed'          => Sparkknife_Ledger_License::is_active(),
			'edition'           => Sparkknife_Ledger_License::edition(),
			'persons'           => array(
				'used'  => (int) $persons,
				'limit' => self::FREE_PERSONS,
				'full'  => ! Sparkknife_Ledger_License::is_active() && (int) $persons >= self::FREE_PERSONS,
				'pct'   => min( 100, (int) round( $persons / max( 1, self::FREE_PERSONS ) * 100 ) ),
			),
			'transactions'      => array(
				'used'  => (int) $transactions,
				'limit' => self::FREE_TRANSACTIONS,
				'full'  => ! Sparkknife_Ledger_License::is_active() && (int) $transactions >= self::FREE_TRANSACTIONS,
				'pct'   => min( 100, (int) round( $transactions / max( 1, self::FREE_TRANSACTIONS ) * 100 ) ),
			),
			'features'          => array(
				'excel'  => self::is_open( 'excel' ),
				'multi'  => self::is_open( 'multi' ),
				'remind' => self::is_open( 'remind' ),
			),
		);
	}
}
