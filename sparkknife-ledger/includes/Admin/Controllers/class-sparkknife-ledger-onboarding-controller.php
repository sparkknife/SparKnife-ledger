<?php
/**
 * کنترلر ویزارد راه‌اندازی اولیه.
 *
 * @author  SparKnife <155084046+sparkknife@users.noreply.github.com>
 *
 * @package SparKnife_Ledger
 */

defined( 'ABSPATH' ) || exit;

/**
 * ویزارد چهارمرحله‌ای راه‌اندازی.
 *
 * چرا ویزارد: کاربر تازه‌ای که افزونه را نصب می‌کند با یک پنل خالی و
 * هفت منو روبه‌رو می‌شود. ویزارد او را در سه تصمیم واقعی راهنمایی
 * می‌کند — واحد پول، تقویم، و اولین شخص — و بعد رهایش می‌کند.
 *
 * **قابل رد کردن است.** اگر کاربر ببندد، دیگر نشان داده نمی‌شود. یک
 * ویزارد که نتوان رد کرد، آزار است نه راهنما.
 */
class Sparkknife_Ledger_Onboarding_Controller {

	/**
	 * گزینه‌ای که می‌گوید ویزارد تکمیل یا رد شده است.
	 */
	const OPTION_DONE = 'sparkknife_ledger_onboarding_done';

	/**
	 * اسلاگ صفحه.
	 */
	const PAGE = 'sparkknife-ledger-setup';

	/**
	 * آیا ویزارد باید نشان داده شود؟
	 *
	 * @return bool
	 */
	public static function needs_setup() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		if ( get_option( self::OPTION_DONE, 'no' ) === 'yes' ) {
			return false;
		}

		// اگر داده وجود دارد، کاربر قبلاً شروع کرده — مزاحم نشو.
		$persons = Sparkknife_Ledger_Person_Repository::instance()->query( array( 'per_page' => 1 ) );

		return empty( $persons['total'] );
	}

	/**
	 * هدایت یک‌باره به ویزارد پس از فعال‌سازی.
	 *
	 * @return void
	 */
	public static function maybe_redirect() {
		if ( ! self::needs_setup() ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['page'] ) ) {
			return; // کاربر خودش جایی رفته؛ مزاحم نشو.
		}

		if ( wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE ) );
		exit;
	}

	/**
	 * رندر ویزارد.
	 *
	 * @return void
	 */
	public static function render() {
		/*
		 * هم‌خوان با مجوز ثبت منو (`manage_options`).
		 *
		 * ویزارد تنظیمات پایه و اولین شخص را می‌سازد؛ کاربری که فقط
		 * مجوز «اشخاص» دارد نباید بتواند واحد پول و تقویم کل سایت را
		 * از این‌جا عوض کند.
		 */
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(
				esc_html__( 'شما اجازه دسترسی به این بخش را ندارید.', 'sparkknife-ledger' ),
				esc_html__( 'دسترسی غیرمجاز', 'sparkknife-ledger' ),
				array( 'response' => 403 )
			);
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- فقط نمایش مرحله.
		$step = isset( $_GET['step'] ) ? max( 1, min( 4, absint( $_GET['step'] ) ) ) : 1;

		Sparkknife_Ledger_Renderer::view(
			'onboarding/index',
			array(
				'step'     => $step,
				'settings' => Sparkknife_Ledger_Settings::all(),
				'current'  => 'setup',
			)
		);
	}
}
