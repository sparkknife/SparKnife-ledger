<?php
/**
 * لایه رندر Viewها.
 *
 * Viewها PHP ساده هستند؛ این کلاس امکان بازنویسی آن‌ها توسط قالب یا
 * افزونه دیگر را از طریق مسیر قالب فراهم می‌کند.
 *
 * @author  SparKnife <155084046+sparkknife@users.noreply.github.com>
 *
 * @package SparKnife_Ledger
 */

defined( 'ABSPATH' ) || exit;

/**
 * رندر امن فایل‌های View.
 */
class Sparkknife_Ledger_Renderer {

	/**
	 * رندر یک View.
	 *
	 * @param string               $view مسیر نسبی بدون پسوند (مثلاً persons/list).
	 * @param array<string, mixed> $args داده‌های View.
	 * @return void
	 */
	public static function view( $view, array $view_data = array() ) {
		$file = self::locate( $view );

		if ( ! $file ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				printf(
					'<div class="notice notice-error"><p>%s</p></div>',
					esc_html( sprintf( 'SparKnife Ledger: view "%s" یافت نشد.', $view ) )
				);
			}
			return;
		}

		/*
		 * استخراج متغیرها برای استفاده در View.
		 *
		 * نکته مهم: برخی Viewها کلید `args` هم دریافت می‌کنند (فیلترهای صفحه).
		 * چون پارامتر تابع دیگر `$args` نیست (`$view_data`)، `extract()` با
		 * EXTR_SKIP آن کلید را حذف نمی‌کند و View مقدار درست را می‌بیند.
		 * بدون این تغییر، تمام Viewها به‌جای فیلترها آرایه دیتای خام می‌گرفتند.
		 */
		// phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- داده‌های کنترل‌شده و در محدوده تابع.
		extract( $view_data, EXTR_SKIP );

		include $file;
	}

	/**
	 * رندر و بازگرداندن خروجی به‌صورت رشته.
	 *
	 * @param string               $view مسیر.
	 * @param array<string, mixed> $args داده.
	 * @return string
	 */
	public static function capture( $view, array $view_data = array() ) {
		ob_start();
		self::view( $view, $view_data );

		return (string) ob_get_clean();
	}

	/**
	 * یافتن فایل View با احترام به بازنویسی قالب.
	 *
	 * @param string $view مسیر.
	 * @return string|false
	 */
	public static function locate( $view ) {
		$view = str_replace( array( '..', "\0" ), '', (string) $view );
		$view = ltrim( $view, '/' );

		$candidates = array();

		// امکان بازنویسی در قالب: wp-content/themes/theme/sparkknife-ledger/{view}.php
		$template = locate_template( array( 'sparkknife-ledger/' . $view . '.php' ) );

		if ( $template ) {
			$candidates[] = $template;
		}

		$candidates[] = SPARKKNIFE_LEDGER_DIR . 'includes/Admin/Views/' . $view . '.php';

		/**
		 * فیلتر مسیرهای View.
		 *
		 * @param array<int, string> $candidates مسیرها.
		 * @param string             $view       نام view.
		 */
		$candidates = apply_filters( 'sparkknife_ledger_view_paths', $candidates, $view );

		foreach ( $candidates as $candidate ) {
			if ( is_readable( $candidate ) ) {
				return $candidate;
			}
		}

		return false;
	}

	/**
	 * چاپ مقدار escape شده.
	 *
	 * @param mixed $value مقدار.
	 * @return void
	 */
	public static function e( $value ) {
		echo esc_html( (string) $value );
	}

	/**
	 * ساخت آدرس صفحه افزونه با پارامترها.
	 *
	 * @param string               $page اسلاگ کوتاه صفحه.
	 * @param array<string, mixed> $args پارامترها.
	 * @return string
	 */
	public static function url( $page, array $args = array() ) {
		$slugs = array(
			'dashboard'    => 'sparkknife-ledger',
			'persons'      => 'sparkknife-ledger-persons',
			'transactions' => 'sparkknife-ledger-transactions',
			'reports'      => 'sparkknife-ledger-reports',
			'summary'      => 'sparkknife-ledger-summary',
			'audit'        => 'sparkknife-ledger-audit',
			'settings'     => 'sparkknife-ledger-settings',
		);

		$slug = isset( $slugs[ $page ] ) ? $slugs[ $page ] : $page;

		$args = array_filter(
			$args,
			function ( $value ) {
				return '' !== $value && null !== $value;
			}
		);

		/*
		 * مقادیر باید کدگذاری شوند.
		 *
		 * `add_query_arg()` خودش چیزی را کدگذاری نمی‌کند: مقدار جستجوی
		 * «علی & co» با `&` خام در آدرس می‌نشست و مرورگر آن را به دو
		 * پارامتر می‌شکست — «search=علی » و «co=». حتی فاصله هم خام
		 * می‌ماند. با یک مقدار دستکاری‌شده می‌شد پارامتر جعلی ساخت.
		 */
		$args = array_map(
			function ( $value ) {
				return rawurlencode( (string) $value );
			},
			$args
		);

		$url = add_query_arg( array_merge( array( 'page' => $slug ), $args ), admin_url( 'admin.php' ) );

		return $url;
	}

	/**
	 * فرم مخفی برای actionهای POST.
	 *
	 * @param string               $action   نام action.
	 * @param string               $redirect مقصد بازگشت.
	 * @param array<string, mixed> $fields   فیلدهای مخفی.
	 * @param string               $class    کلاس‌ها.
	 * @return void
	 */
	public static function action_form( $action, $redirect, array $fields = array(), $class = 'spk-inline-form' ) {
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="<?php echo esc_attr( $class ); ?>">
			<input type="hidden" name="action" value="sparkknife_ledger_action" />
			<input type="hidden" name="sparkknife_action" value="<?php echo esc_attr( $action ); ?>" />
			<input type="hidden" name="sparkknife_redirect" value="<?php echo esc_attr( $redirect ); ?>" />
			<?php wp_nonce_field( 'sparkknife_ledger_' . $action ); ?>
			<?php foreach ( $fields as $name => $value ) : ?>
				<input type="hidden" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ); ?>" />
			<?php endforeach; ?>
			<?php
	}
}
