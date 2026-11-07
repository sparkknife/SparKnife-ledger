<?php
/**
 * کنترلر پایه ادمین: مسیریابی actionها، بررسی امنیت و رندر Viewها.
 *
 * @author  SparKnife <155084046+sparkknife@users.noreply.github.com>
 *
 * @package SparKnife_Ledger
 */

defined( 'ABSPATH' ) || exit;

/**
 * کنترلر مرکزی درخواست‌های پنل مدیریت.
 */
class Sparkknife_Ledger_Admin_Controller {

	/**
	 * نمونه یکتا.
	 *
	 * @var Sparkknife_Ledger_Admin_Controller|null
	 */
	private static $instance = null;

	/**
	 * دریافت نمونه.
	 *
	 * @return Sparkknife_Ledger_Admin_Controller
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * ثبت هوک‌ها.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'admin_post_sparkknife_ledger_action', array( $this, 'handle_post_action' ) );
		add_action( 'admin_post_sparkknife_ledger_export', array( $this, 'handle_export' ) );
		add_action( 'admin_post_sparkknife_ledger_print', array( $this, 'handle_print' ) );
	}

	/**
	 * پردازش فرم‌های POST.
	 *
	 * @return void
	 */
	public function handle_post_action() {
		if ( ! is_user_logged_in() ) {
			wp_die( esc_html__( 'دسترسی غیرمجاز.', 'sparkknife-ledger' ), '', array( 'response' => 403 ) );
		}

		$action = isset( $_POST['sparkknife_action'] ) ? sanitize_key( wp_unslash( $_POST['sparkknife_action'] ) ) : '';

		if ( '' === $action ) {
			wp_die( esc_html__( 'درخواست نامعتبر است.', 'sparkknife-ledger' ), '', array( 'response' => 400 ) );
		}

		// بررسی Nonce اختصاصی هر action.
		check_admin_referer( 'sparkknife_ledger_' . $action );

		/*
		 * محدودیت تلاش روی اکشن‌های حساس.
		 *
		 * nonce ثابت می‌کند که درخواست از فرم خودمان آمده، ولی نمی‌گوید
		 * چند بار. اگر نشست یا nonce دزدیده شود، بدون این نگهبان
		 * مهاجم می‌تواند بی‌نهایت `data_purge` یا
		 * `capabilities_save` بفرستد.
		 */
		$allowed = Sparkknife_Ledger_Security::check_rate( $action );

		if ( is_wp_error( $allowed ) ) {
			Sparkknife_Ledger_Admin_Loader::add_notice(
				'error',
				$allowed->get_error_message(),
				array(),
				$this->resolve_redirect()
			);

			return;
		}

		$redirect = $this->resolve_redirect();

		switch ( $action ) {
			case 'person_save':
				$this->handle_person_save( $redirect );
				break;

			case 'person_delete':
				$this->handle_person_delete( $redirect );
				break;

			case 'person_restore':
				$this->handle_person_restore( $redirect );
				break;

			case 'person_status':
				$this->handle_person_status( $redirect );
				break;

			case 'transaction_save':
				$this->handle_transaction_save( $redirect );
				break;

			case 'transaction_void':
				$this->handle_transaction_void( $redirect );
				break;

			case 'settings_save':
				$this->handle_settings_save( $redirect );
				break;

			case 'capabilities_save':
				$this->handle_capabilities_save( $redirect );
				break;

			case 'data_purge':
				$this->handle_data_purge( $redirect );
				break;

			case 'license_save':
				$this->handle_license_save( $redirect );
				break;

			case 'license_remove':
				$this->handle_license_remove( $redirect );
				break;

			case 'security_log_clear':
				$this->handle_security_log_clear( $redirect );
				break;

			case 'onboarding_currency':
				$this->handle_onboarding_currency( $redirect );
				break;

			case 'onboarding_person':
				$this->handle_onboarding_person( $redirect );
				break;

			case 'onboarding_finish':
				$this->handle_onboarding_finish( $redirect );
				break;

			default:
				/**
				 * امکان پردازش actionهای سفارشی.
				 *
				 * @param string $action   نام action.
				 * @param string $redirect نشانی بازگشت.
				 */
				do_action( 'sparkknife_ledger_admin_action_' . $action, $action, $redirect );
				break;
		}

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * استخراج مقصد بازگشت امن.
	 *
	 * @return string
	 */
	private function resolve_redirect() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- redirect پس از check_admin_referer خوانده می‌شود.
		$redirect = isset( $_POST['sparkknife_redirect'] ) ? wp_unslash( $_POST['sparkknife_redirect'] ) : '';
		$fallback = admin_url( 'admin.php?page=sparkknife-ledger' );

		if ( empty( $redirect ) ) {
			return $fallback;
		}

		$redirect = esc_url_raw( $redirect );

		// فقط نشانی‌های پنل مدیریت همین سایت مجازند.
		if ( 0 !== strpos( $redirect, admin_url() ) ) {
			return $fallback;
		}

		if ( false === strpos( $redirect, 'page=sparkknife-ledger' ) ) {
			return $fallback;
		}

		return $redirect;
	}

	/*
	|--------------------------------------------------------------------------
	| اشخاص
	|--------------------------------------------------------------------------
	*/

	/**
	 * ذخیره شخص (ایجاد یا ویرایش).
	 *
	 * @param string $redirect مقصد.
	 * @return void
	 */
	private function handle_person_save( $redirect ) {
		Sparkknife_Ledger_Capabilities::require_capability( 'manage_persons' );

		$person_id = isset( $_POST['person_id'] ) ? absint( $_POST['person_id'] ) : 0;

		$input = array(
			'name'        => isset( $_POST['name'] ) ? wp_unslash( $_POST['name'] ) : '',
			'phone'       => isset( $_POST['phone'] ) ? wp_unslash( $_POST['phone'] ) : '',
			'description' => isset( $_POST['description'] ) ? wp_unslash( $_POST['description'] ) : '',
			'status'      => isset( $_POST['status'] ) ? wp_unslash( $_POST['status'] ) : 'active',
		);

		$service = Sparkknife_Ledger_Person_Service::instance();

		if ( $person_id > 0 ) {
			$result = $service->update( $person_id, $input );

			if ( is_wp_error( $result ) ) {
				$this->flash_error( $result );
				$this->redirect_back_with( $redirect, $person_id, $input );

				return;
			}

			Sparkknife_Ledger_Admin_Loader::add_notice( 'success', __( 'اطلاعات شخص با موفقیت ذخیره شد.', 'sparkknife-ledger' ), array(), $redirect );

			/*
			 * `add_notice()` با `$redirect` خودش خارج می‌شود، ولی این
			 * `return` عمدی است: اگر روزی رفتار `add_notice` عوض شود یا
			 * `$redirect` خالی باشد، جریان به `create()` نمی‌رسد و برای
			 * شخص موجود، یک شخص تکراری ساخته نمی‌شود.
			 */
			return;
		}

		$result = $service->create( $input );

		if ( is_wp_error( $result ) ) {
			$this->flash_error( $result );
			$this->redirect_back_with( $redirect, 0, $input );
		}

		Sparkknife_Ledger_Admin_Loader::add_notice(
			'success',
			__( 'شخص جدید با موفقیت ثبت شد.', 'sparkknife-ledger' ),
			array(),
			add_query_arg(
				array(
					'page'   => 'sparkknife-ledger-persons',
					'action' => 'edit',
					'id'     => (int) $result,
				),
				admin_url( 'admin.php' )
			)
		);
	}

	/**
	 * حذف نرم شخص.
	 *
	 * @param string $redirect مقصد.
	 * @return void
	 */
	private function handle_person_delete( $redirect ) {
		Sparkknife_Ledger_Capabilities::require_capability( 'manage_persons' );

		$person_id = isset( $_POST['person_id'] ) ? absint( $_POST['person_id'] ) : 0;
		$result    = Sparkknife_Ledger_Person_Service::instance()->delete( $person_id );

		if ( is_wp_error( $result ) ) {
			$this->flash_error( $result );
			Sparkknife_Ledger_Admin_Loader::add_notice( 'error', $result->get_error_message(), array(), $redirect );

			/*
			 * `add_notice()` با redirect غیرخالی خودش exit می‌کند، ولی
			 * این return عمدی است: بدون آن، اگر روزی رفتار add_notice
			 * عوض شود یا redirect خالی باشد، پیام «موفقیت» هم بعد از
			 * پیام خطا نوشته می‌شد و کاربر با یک شکست، پیام موفقیت
			 * می‌دید.
			 */
			return;
		}

		Sparkknife_Ledger_Admin_Loader::add_notice(
			'success',
			__( 'شخص حذف شد. اطلاعات برای بازیابی در سوابق حفظ شد.', 'sparkknife-ledger' ),
			array(),
			add_query_arg( array( 'page' => 'sparkknife-ledger-persons', 'view' => 'deleted' ), admin_url( 'admin.php' ) )
		);
	}

	/**
	 * بازگردانی شخص.
	 *
	 * @param string $redirect مقصد.
	 * @return void
	 */
	private function handle_person_restore( $redirect ) {
		Sparkknife_Ledger_Capabilities::require_capability( 'manage_persons' );

		$person_id = isset( $_POST['person_id'] ) ? absint( $_POST['person_id'] ) : 0;
		$result    = Sparkknife_Ledger_Person_Service::instance()->restore( $person_id );

		if ( is_wp_error( $result ) ) {
			Sparkknife_Ledger_Admin_Loader::add_notice( 'error', $result->get_error_message(), array(), $redirect );

			// همان دلیل: پیام موفقیت نباید پس از شکست نوشته شود.
			return;
		}

		Sparkknife_Ledger_Admin_Loader::add_notice( 'success', __( 'شخص با موفقیت بازگردانی شد.', 'sparkknife-ledger' ), array(), $redirect );
	}

	/**
	 * تغییر سریع وضعیت شخص.
	 *
	 * @param string $redirect مقصد.
	 * @return void
	 */
	private function handle_person_status( $redirect ) {
		Sparkknife_Ledger_Capabilities::require_capability( 'manage_persons' );

		$person_id = isset( $_POST['person_id'] ) ? absint( $_POST['person_id'] ) : 0;
		$status    = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : '';

		$result = Sparkknife_Ledger_Person_Service::instance()->set_status( $person_id, $status );

		if ( is_wp_error( $result ) ) {
			Sparkknife_Ledger_Admin_Loader::add_notice( 'error', $result->get_error_message(), array(), $redirect );

			// همان دلیل: پیام موفقیت نباید پس از شکست نوشته شود.
			return;
		}

		Sparkknife_Ledger_Admin_Loader::add_notice(
			'success',
			'inactive' === $status ? __( 'شخص غیرفعال شد.', 'sparkknife-ledger' ) : __( 'شخص فعال شد.', 'sparkknife-ledger' ),
			array(),
			$redirect
		);
	}

	/*
	|--------------------------------------------------------------------------
	| تراکنش‌ها
	|--------------------------------------------------------------------------
	*/

	/**
	 * ذخیره تراکنش (ایجاد یا ویرایش).
	 *
	 * @param string $redirect مقصد.
	 * @return void
	 */
	private function handle_transaction_save( $redirect ) {
		Sparkknife_Ledger_Capabilities::require_capability( 'manage_transactions' );

		$transaction_id = isset( $_POST['transaction_id'] ) ? absint( $_POST['transaction_id'] ) : 0;

		$input = array(
			'person_id'   => isset( $_POST['person_id'] ) ? absint( $_POST['person_id'] ) : 0,
			'type'        => isset( $_POST['type'] ) ? sanitize_key( wp_unslash( $_POST['type'] ) ) : '',
			'amount'      => isset( $_POST['amount'] ) ? wp_unslash( $_POST['amount'] ) : '',
			'txn_date'    => isset( $_POST['txn_date'] ) ? wp_unslash( $_POST['txn_date'] ) : '',
			'description' => isset( $_POST['description'] ) ? wp_unslash( $_POST['description'] ) : '',
		);

		$service = Sparkknife_Ledger_Transaction_Service::instance();

		if ( $transaction_id > 0 ) {
			$result = $service->update( $transaction_id, $input );

			if ( is_wp_error( $result ) ) {
				$this->flash_error( $result );
				Sparkknife_Ledger_Admin_Loader::add_notice(
					'error',
					$result->get_error_message(),
					array(),
					add_query_arg(
						array(
							'page'   => 'sparkknife-ledger-transactions',
							'action' => 'edit',
							'id'     => $transaction_id,
						),
						admin_url( 'admin.php' )
					)
				);
			}

			$person_id = absint( $input['person_id'] );

			Sparkknife_Ledger_Admin_Loader::add_notice(
				'success',
				__( 'تراکنش با موفقیت ویرایش شد.', 'sparkknife-ledger' ),
				array(),
				add_query_arg(
					array(
						'page'   => 'sparkknife-ledger-transactions',
						'action' => 'view',
						'id'     => $transaction_id,
						'person' => $person_id,
					),
					admin_url( 'admin.php' )
				)
			);
		}

		$result = $service->create( $input );

		if ( is_wp_error( $result ) ) {
			$this->flash_error( $result );
			Sparkknife_Ledger_Admin_Loader::add_notice(
				'error',
				$result->get_error_message(),
				array(),
				add_query_arg(
					array(
						'page'   => 'sparkknife-ledger-transactions',
						'action' => 'new',
						'person' => absint( $input['person_id'] ),
					),
					admin_url( 'admin.php' )
				)
			);
		}

		Sparkknife_Ledger_Admin_Loader::add_notice(
			'success',
			__( 'تراکنش با موفقیت ثبت شد.', 'sparkknife-ledger' ),
			array(),
			add_query_arg(
				array(
					'page'   => 'sparkknife-ledger-transactions',
					'action' => 'view',
					'id'     => (int) $result,
				),
				admin_url( 'admin.php' )
			)
		);
	}

	/**
	 * ابطال تراکنش.
	 *
	 * @param string $redirect مقصد.
	 * @return void
	 */
	private function handle_transaction_void( $redirect ) {
		Sparkknife_Ledger_Capabilities::require_capability( 'void_transactions' );

		$transaction_id = isset( $_POST['transaction_id'] ) ? absint( $_POST['transaction_id'] ) : 0;
		$reason         = isset( $_POST['void_reason'] ) ? wp_unslash( $_POST['void_reason'] ) : '';

		$result = Sparkknife_Ledger_Transaction_Service::instance()->void( $transaction_id, $reason );

		$return_url = add_query_arg(
			array(
				'page'   => 'sparkknife-ledger-transactions',
				'action' => 'view',
				'id'     => $transaction_id,
			),
			admin_url( 'admin.php' )
		);

		if ( is_wp_error( $result ) ) {
			Sparkknife_Ledger_Admin_Loader::add_notice( 'error', $result->get_error_message(), array(), $return_url );

			// همان دلیل: پیام موفقیت نباید پس از شکست نوشته شود.
			return;
		}

		Sparkknife_Ledger_Admin_Loader::add_notice(
			'success',
			__( 'تراکنش باطل شد و از محاسبات مالی خارج شد.', 'sparkknife-ledger' ),
			array(),
			$return_url
		);
	}

	/*
	|--------------------------------------------------------------------------
	| تنظیمات
	|--------------------------------------------------------------------------
	*/

	/**
	 * ذخیره تنظیمات.
	 *
	 * @param string $redirect مقصد.
	 * @return void
	 */
	private function handle_settings_save( $redirect ) {
		Sparkknife_Ledger_Capabilities::require_capability( 'manage_settings' );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce در handle_post_action بررسی شده است.
		$input = wp_unslash( $_POST );

		/*
		 * `active_tab` مشخص می‌کند فرم کدام تب ارسال شده است؛ بدون آن،
		 * ذخیره یک تب چک‌باکس‌های تب‌های دیگر را خاموش می‌کرد.
		 */
		$input['settings_section'] = isset( $_POST['active_tab'] )
			? sanitize_key( wp_unslash( $_POST['active_tab'] ) )
			: 'all';

		$tab = isset( $_POST['active_tab'] ) ? sanitize_key( wp_unslash( $_POST['active_tab'] ) ) : 'currency';

		$saved = Sparkknife_Ledger_Settings::save( $input );

		if ( is_wp_error( $saved ) ) {
			Sparkknife_Ledger_Admin_Loader::add_notice(
				'error',
				$saved->get_error_message(),
				array(),
				add_query_arg( array( 'page' => 'sparkknife-ledger-settings', 'tab' => $tab ), admin_url( 'admin.php' ) )
			);

			/*
			 * همان دلیل: پیام «تنظیمات با موفقیت ذخیره شد» نباید پس از
			 * یک خطای واقعی نوشته شود. پیش‌تر فقط exit داخل add_notice
			 * جلوی آن را می‌گرفت.
			 */
			return;
		}

		Sparkknife_Ledger_Admin_Loader::add_notice(
			'success',
			__( 'تنظیمات با موفقیت ذخیره شد.', 'sparkknife-ledger' ),
			array(),
			add_query_arg( array( 'page' => 'sparkknife-ledger-settings', 'tab' => $tab ), admin_url( 'admin.php' ) )
		);
	}

	/**
	 * ذخیره ماتریس دسترسی نقش‌ها.
	 *
	 * @param string $redirect مقصد.
	 * @return void
	 */
	private function handle_capabilities_save( $redirect ) {
		Sparkknife_Ledger_Capabilities::require_capability( 'manage_settings' );

		$matrix = array();
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce در handle_post_action بررسی شده است.
		$raw = isset( $_POST['role_caps'] ) && is_array( $_POST['role_caps'] ) ? wp_unslash( $_POST['role_caps'] ) : array();

		foreach ( $raw as $role => $caps ) {
			$role_name           = sanitize_key( $role );
			$matrix[ $role_name ] = array();

			foreach ( (array) $caps as $cap ) {
				$matrix[ $role_name ][] = sanitize_key( $cap );
			}
		}

		Sparkknife_Ledger_Capabilities::save_role_matrix( $matrix );

		Sparkknife_Ledger_Admin_Loader::add_notice(
			'success',
			__( 'سطوح دسترسی نقش‌ها به‌روزرسانی شد.', 'sparkknife-ledger' ),
			array(),
			add_query_arg( array( 'page' => 'sparkknife-ledger-settings', 'tab' => 'capabilities' ), admin_url( 'admin.php' ) )
		);
	}

	/**
	 * حذف کامل داده‌ها با تأیید صریح.
	 *
	 * @param string $redirect مقصد.
	 * @return void
	 */
	private function handle_data_purge( $redirect ) {
		/*
		 * این متد داده‌های مالی را نابود می‌کند؛ هر دو شرط باید صریحاً
		 * با return محافظت شوند و به خروجی add_notice تکیه نکنیم.
		 * add_notice با redirect غیرخالی exit می‌کند، اما اگر روزی آن
		 * رفتار تغییر کند یا $redirect خالی باشد، بدون این returnها
		 * حذف داده برای کاربر بی‌مجوز هم اجرا می‌شد.
		 */
		if ( ! current_user_can( 'manage_options' ) ) {
			Sparkknife_Ledger_Admin_Loader::add_notice(
				'error',
				__( 'فقط مدیر کل سایت می‌تواند داده‌ها را حذف کند.', 'sparkknife-ledger' ),
				array(),
				$redirect
			);

			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce در handle_post_action بررسی شده است.
		$confirm = isset( $_POST['purge_confirm'] ) ? sanitize_text_field( wp_unslash( $_POST['purge_confirm'] ) ) : '';

		if ( 'DELETE-ALL-DATA' !== $confirm ) {
			Sparkknife_Ledger_Admin_Loader::add_notice(
				'error',
				__( 'متن تأیید صحیح نیست. برای حذف کامل داده‌ها عبارت مشخص‌شده را دقیقاً وارد کنید.', 'sparkknife-ledger' ),
				array(),
				$redirect
			);

			return;
		}

		Sparkknife_Ledger_Installer::purge_all_data();

		Sparkknife_Ledger_Admin_Loader::add_notice(
			'warning',
			__( 'تمام داده‌های مالی حذف شدند. برای ایجاد مجدد جداول، افزونه را غیرفعال و فعال کنید.', 'sparkknife-ledger' ),
			array(),
			admin_url( 'admin.php?page=sparkknife-ledger-settings' )
		);
	}

	/*
	|--------------------------------------------------------------------------
	| ویزارد راه‌اندازی
	|--------------------------------------------------------------------------
	*/

	/**
	 * ذخیرهٔ واحد پول و تقویم از ویزارد.
	 *
	 * @param string $redirect مقصد.
	 * @return void
	 */
	private function handle_onboarding_currency( $redirect ) {
		Sparkknife_Ledger_Capabilities::require_capability( 'manage_settings' );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce در handle_post_action بررسی شده است.
		$currency = isset( $_POST['currency_type'] ) ? sanitize_key( wp_unslash( $_POST['currency_type'] ) ) : 'toman';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$calendar = isset( $_POST['calendar'] ) ? sanitize_key( wp_unslash( $_POST['calendar'] ) ) : 'jalali';

		/*
		 * یک فراخوانی، نه دو تا.
		 *
		 * `Settings::save()` همهٔ فیلدها را یک‌جا می‌نویسد؛ اگر دو بار
		 * صدایش بزنیم، فراخوانی دوم فیلدهایی را که در ورودی‌اش نیست از
		 * مقدار فعلی پر می‌کند — که همان مقدار تازه‌نوشته‌شده است، پس
		 * نتیجه درست می‌شود ولی بیهوده دو نوشتن و دو رکورد تاریخچه
		 * تولید می‌کند.
		 */
		$result = Sparkknife_Ledger_Settings::save(
			array(
				'currency_type' => $currency,
				'calendar'      => $calendar,
			)
		);

		if ( is_wp_error( $result ) ) {
			Sparkknife_Ledger_Admin_Loader::add_notice( 'error', $result->get_error_message(), array(), $redirect );

			return;
		}

		Sparkknife_Ledger_Admin_Loader::add_notice(
			'success',
			__( 'تنظیمات پایه ذخیره شد.', 'sparkknife-ledger' ),
			array(),
			$redirect
		);
	}

	/**
	 * افزودن اولین شخص از ویزارد.
	 *
	 * @param string $redirect مقصد.
	 * @return void
	 */
	private function handle_onboarding_person( $redirect ) {
		Sparkknife_Ledger_Capabilities::require_capability( 'manage_persons' );

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce در handle_post_action بررسی شده است.
		$name  = isset( $_POST['name'] ) ? wp_unslash( $_POST['name'] ) : '';
		$phone = isset( $_POST['phone'] ) ? wp_unslash( $_POST['phone'] ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		$result = Sparkknife_Ledger_Person_Service::instance()->create(
			array(
				'name'        => $name,
				'phone'       => $phone,
				'description' => '',
				'status'      => 'active',
			)
		);

		if ( is_wp_error( $result ) ) {
			Sparkknife_Ledger_Admin_Loader::add_notice( 'error', $result->get_error_message(), array(), $redirect );

			return;
		}

		Sparkknife_Ledger_Admin_Loader::add_notice(
			'success',
			__( 'شخص افزوده شد.', 'sparkknife-ledger' ),
			array(),
			$redirect
		);
	}

	/**
	 * پایان ویزارد.
	 *
	 * @param string $redirect مقصد.
	 * @return void
	 */
	private function handle_onboarding_finish( $redirect ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			Sparkknife_Ledger_Admin_Loader::add_notice(
				'error',
				__( 'دسترسی کافی ندارید.', 'sparkknife-ledger' ),
				array(),
				$redirect
			);

			return;
		}

		update_option( Sparkknife_Ledger_Onboarding_Controller::OPTION_DONE, 'yes', false );

		Sparkknife_Ledger_Admin_Loader::add_notice(
			'success',
			__( 'راه‌اندازی کامل شد.', 'sparkknife-ledger' ),
			array(),
			$redirect
		);
	}

	/*
	|--------------------------------------------------------------------------
	| لایسنس و امنیت
	|--------------------------------------------------------------------------
	*/

	/**
	 * ذخیرهٔ کلید لایسنس.
	 *
	 * @param string $redirect مقصد.
	 * @return void
	 */
	private function handle_license_save( $redirect ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			Sparkknife_Ledger_Admin_Loader::add_notice(
				'error',
				__( 'فقط مدیر کل سایت می‌تواند لایسنس را تغییر دهد.', 'sparkknife-ledger' ),
				array(),
				$redirect
			);

			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce در handle_post_action بررسی شده است.
		$key = isset( $_POST['license_key'] ) ? sanitize_textarea_field( wp_unslash( $_POST['license_key'] ) ) : '';

		$result = Sparkknife_Ledger_License::save_key( $key );

		if ( is_wp_error( $result ) ) {
			Sparkknife_Ledger_Security::log( 'license_rejected', 'warning', array( 'reason' => $result->get_error_code() ) );

			Sparkknife_Ledger_Admin_Loader::add_notice( 'error', $result->get_error_message(), array(), $redirect );

			return;
		}

		Sparkknife_Ledger_Security::log( 'license_activated', 'info', array( 'edition' => Sparkknife_Ledger_License::edition() ) );

		Sparkknife_Ledger_Admin_Loader::add_notice(
			'success',
			__( 'لایسنس فعال شد.', 'sparkknife-ledger' ),
			array(),
			$redirect
		);
	}

	/**
	 * حذف لایسنس.
	 *
	 * @param string $redirect مقصد.
	 * @return void
	 */
	private function handle_license_remove( $redirect ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			Sparkknife_Ledger_Admin_Loader::add_notice(
				'error',
				__( 'فقط مدیر کل سایت می‌تواند لایسنس را تغییر دهد.', 'sparkknife-ledger' ),
				array(),
				$redirect
			);

			return;
		}

		Sparkknife_Ledger_License::remove_key();
		Sparkknife_Ledger_Security::log( 'license_removed', 'info' );

		Sparkknife_Ledger_Admin_Loader::add_notice(
			'success',
			__( 'لایسنس از این سایت حذف شد.', 'sparkknife-ledger' ),
			array(),
			$redirect
		);
	}

	/**
	 * پاک کردن گزارش امنیتی.
	 *
	 * @param string $redirect مقصد.
	 * @return void
	 */
	private function handle_security_log_clear( $redirect ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			Sparkknife_Ledger_Admin_Loader::add_notice(
				'error',
				__( 'دسترسی کافی ندارید.', 'sparkknife-ledger' ),
				array(),
				$redirect
			);

			return;
		}

		Sparkknife_Ledger_Security::clear_log();

		Sparkknife_Ledger_Admin_Loader::add_notice(
			'success',
			__( 'گزارش امنیتی پاک شد.', 'sparkknife-ledger' ),
			array(),
			$redirect
		);
	}

	/*
	|--------------------------------------------------------------------------
	| خروجی گزارش
	|--------------------------------------------------------------------------
	*/

	/**
	 * پردازش خروجی CSV گزارش فردی/تجمیعی.
	 *
	 * @return void
	 */
	public function handle_export() {
		if ( ! is_user_logged_in() ) {
			wp_die( esc_html__( 'دسترسی غیرمجاز.', 'sparkknife-ledger' ), '', array( 'response' => 403 ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- بررسی در ادامه انجام می‌شود.
		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, 'sparkknife_ledger_export' ) ) {
			wp_die( esc_html__( 'اعتبار درخواست منقضی شده است.', 'sparkknife-ledger' ), '', array( 'response' => 403 ) );
		}

		Sparkknife_Ledger_Capabilities::require_capability( 'export_reports' );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce بالا بررسی شد.
		$type   = isset( $_GET['report'] ) ? sanitize_key( wp_unslash( $_GET['report'] ) ) : 'person';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$person = isset( $_GET['person'] ) ? absint( $_GET['person'] ) : 0;

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$args = array(
			'range'     => isset( $_GET['range'] ) ? sanitize_key( wp_unslash( $_GET['range'] ) ) : 'all',
			'date_from' => isset( $_GET['date_from'] ) ? wp_unslash( $_GET['date_from'] ) : '',
			'date_to'   => isset( $_GET['date_to'] ) ? wp_unslash( $_GET['date_to'] ) : '',
			'type'      => isset( $_GET['type'] ) ? sanitize_key( wp_unslash( $_GET['type'] ) ) : '',
			'status'    => isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '',
			'search'    => isset( $_GET['search'] ) ? wp_unslash( $_GET['search'] ) : '',
			'orderby'    => isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : 'balance',
			'order'      => isset( $_GET['order'] ) ? sanitize_key( wp_unslash( $_GET['order'] ) ) : 'desc',
			// صادرات باید کل داده را بدهد؛ بدون این، فقط ۲۰ ردیف اول می‌آمد.
			'for_export' => true,
			'page'       => 1,
		);

		$export = Sparkknife_Ledger_Export_Service::instance();

		/*
		 * خروجی کامل داده — همهٔ اشخاص، تراکنش‌ها و تاریخچه.
		 *
		 * برای آرشیو یا انتقال به سیستم دیگر. جدا از گزارش‌هاست چون
		 * گزارش برای خواندن ساخته شده و ستون‌هایش مشتق‌شده‌اند؛ این
		 * خروجی ستون‌های خام جدول را می‌دهد تا قابل وارد کردن باشد.
		 */
		if ( 'full' === $type ) {
			$export->log_export( 'full' );
			$export->send_csv( $export->full_export_rows(), 'sparkknife-ledger-full' );
		}

		if ( 'summary' === $type ) {
			$report = Sparkknife_Ledger_Report_Service::instance()->get_summary_report( $args );
			$export->log_export( 'summary' );
			$export->send_csv( $export->summary_report_rows( $report ), 'sparkknife-ledger-summary' );
		}

		if ( $person <= 0 ) {
			wp_die( esc_html__( 'شناسه شخص برای گزارش فردی مشخص نشده است.', 'sparkknife-ledger' ), '', array( 'response' => 400 ) );
		}

		$report = Sparkknife_Ledger_Report_Service::instance()->get_person_report( $person, $args );

		if ( is_wp_error( $report ) ) {
			wp_die( esc_html( $report->get_error_message() ), '', array( 'response' => 404 ) );
		}

		$export->log_export( 'person', $person );

		$filename = 'sparkknife-ledger-person-' . $person;
		$person_obj = Sparkknife_Ledger_Person_Repository::instance()->find( $person );

		if ( $person_obj ) {
			$filename = sanitize_title( $person_obj->name );
			$filename = $filename ? 'sparkknife-ledger-' . $filename : $filename;
		}

		$export->send_csv( $export->person_report_rows( $report ), $filename );
	}

	/*
	|--------------------------------------------------------------------------
	| چاپ و PDF
	|--------------------------------------------------------------------------
	*/

	/**
	 * نمایش صفحه چاپ گزارش (و امکان ذخیره به PDF از مرورگر).
	 *
	 * @return void
	 */
	public function handle_print() {
		if ( ! is_user_logged_in() ) {
			wp_die( esc_html__( 'دسترسی غیرمجاز.', 'sparkknife-ledger' ), '', array( 'response' => 403 ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- بررسی در ادامه.
		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, 'sparkknife_ledger_print' ) ) {
			wp_die( esc_html__( 'اعتبار درخواست منقضی شده است.', 'sparkknife-ledger' ), '', array( 'response' => 403 ) );
		}

		Sparkknife_Ledger_Capabilities::require_capability( 'export_reports' );

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- nonce بررسی شد.
		$type   = isset( $_GET['report'] ) ? sanitize_key( wp_unslash( $_GET['report'] ) ) : 'person';
		$person = isset( $_GET['person'] ) ? absint( $_GET['person'] ) : 0;

		$args = array(
			'range'     => isset( $_GET['range'] ) ? sanitize_key( wp_unslash( $_GET['range'] ) ) : 'all',
			'date_from' => isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : '',
			'date_to'   => isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : '',
			'type'      => isset( $_GET['type'] ) ? sanitize_key( wp_unslash( $_GET['type'] ) ) : '',
			'status'    => isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '',
			'search'    => isset( $_GET['search'] ) ? sanitize_text_field( wp_unslash( $_GET['search'] ) ) : '',
			'orderby'   => isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : 'balance',
			'order'     => isset( $_GET['order'] ) ? sanitize_key( wp_unslash( $_GET['order'] ) ) : 'desc',
			// چاپ باید همه ردیف‌ها را شامل شود، نه یک صفحه.
			'for_export' => true,
			'page'       => 1,
		);
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$print = Sparkknife_Ledger_Print_Service::instance();

		if ( 'summary' === $type ) {
			$report = Sparkknife_Ledger_Report_Service::instance()->get_summary_report( $args );

			/** This filter is documented below. */
			$html = apply_filters( 'sparkknife_ledger_render_print', $print->summary_report_html( $report ), $report, 'summary' );

			Sparkknife_Ledger_Audit_Service::instance()->log(
				'settings',
				0,
				'export',
				array( 'report' => 'summary', 'format' => 'print' ),
				__( 'چاپ گزارش تجمیعی', 'sparkknife-ledger' )
			);

			$print->render_document( $html );
		}

		if ( $person <= 0 ) {
			wp_die( esc_html__( 'شناسه شخص مشخص نشده است.', 'sparkknife-ledger' ), '', array( 'response' => 400 ) );
		}

		$report = Sparkknife_Ledger_Report_Service::instance()->get_person_report( $person, $args );

		if ( is_wp_error( $report ) ) {
			wp_die( esc_html( $report->get_error_message() ), '', array( 'response' => 404 ) );
		}

		/**
		 * امکان تولید PDF سمت سرور یا جایگزینی خروجی چاپ.
		 *
		 * @param string $html   خروجی چاپ.
		 * @param array  $report داده گزارش.
		 * @param string $type   نوع گزارش (person|summary).
		 */
		$html = apply_filters( 'sparkknife_ledger_render_print', $print->person_report_html( $report ), $report, 'person' );

		Sparkknife_Ledger_Audit_Service::instance()->log(
			'person',
			$person,
			'export',
			array( 'report' => 'person', 'format' => 'print' ),
			__( 'چاپ گزارش فردی', 'sparkknife-ledger' )
		);

		$print->render_document( $html );
	}

	/*
	|--------------------------------------------------------------------------
	| کمکی
	|--------------------------------------------------------------------------
	*/

	/**
	 * ثبت خطا برای نمایش در فرم (Session-like با transient).
	 *
	 * @param WP_Error $error خطا.
	 * @return void
	 */
	private function flash_error( WP_Error $error ) {
		$user_id = get_current_user_id();

		if ( ! $user_id ) {
			return;
		}

		set_transient(
			'sparkknife_ledger_form_errors_' . $user_id,
			array(
				'message' => $error->get_error_message(),
				'details' => $error->get_error_data(),
			),
			60
		);

		/*
		 * عمداً add_notice صدا زده نمی‌شود.
		 *
		 * پیام خطا از ترنزینت form_errors در header.php نمایش داده می‌شود و
		 * فراخوان‌ها پس از این متد خودشان add_notice( ..., $redirect ) را
		 * صدا می‌زنند. صدا زدن هر دو منبع، همان خطا را دو بار به کاربر
		 * نشان می‌داد.
		 */
	}

	/**
	 * تغییر مسیر به فرم با حفظ ورودی‌های واردشده.
	 *
	 * @param string               $redirect  مقصد.
	 * @param int                  $person_id شناسه.
	 * @param array<string, mixed> $input     ورودی.
	 * @return void
	 */
	private function redirect_back_with( $redirect, $person_id, array $input ) {
		$url = add_query_arg(
			array(
				'page'   => 'sparkknife-ledger-persons',
				'action' => $person_id ? 'edit' : 'new',
				'id'     => $person_id,
			),
			admin_url( 'admin.php' )
		);

		Sparkknife_Ledger_Admin_Loader::add_notice( 'error', '', array(), $url );
	}
}
