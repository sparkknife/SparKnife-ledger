/**
 * SparKnife Ledger – اسکریپت پنل مدیریت
 *
 * قابلیت‌ها:
 *   ۱) تأیید عملیات حساس (فرم‌های data-confirm)
 *   ۲) پیش‌نمایش لحظه‌ای و قالب‌بندی مبلغ
 *   ۳) نمایش فیلدهای بازه دلخواه
 *   ۴) اعتبارسنجی سمت کاربر فرم تراکنش
 *   ۵) پیش‌نمایش و پاک‌سازی ورودی تاریخ
 *
 * بدون وابستگی به فریم‌ورک؛ سازگار با jQuery موجود در وردپرس.
 */
( function () {
	'use strict';

	var config = window.sparkknifeLedger || {};
	var strings = config.strings || {};

	/**
	 * تبدیل ارقام فارسی/عربی به لاتین.
	 *
	 * @param {string} value مقدار.
	 * @return {string} مقدار لاتین.
	 */
	function toLatinDigits( value ) {
		return String( value || '' )
			.replace( /[\u06F0-\u06F9]/g, function ( ch ) {
				return String.fromCharCode( ch.charCodeAt( 0 ) - 0x06F0 + 48 );
			} )
			.replace( /[\u0660-\u0669]/g, function ( ch ) {
				return String.fromCharCode( ch.charCodeAt( 0 ) - 0x0660 + 48 );
			} );
	}

	/**
	 * تبدیل ارقام لاتین به فارسی.
	 *
	 * @param {string} value مقدار.
	 * @return {string} مقدار فارسی.
	 */
	function toPersianDigits( value ) {
		return String( value || '' ).replace( /[0-9]/g, function ( ch ) {
			return String.fromCharCode( ch.charCodeAt( 0 ) - 48 + 0x06F0 );
		} );
	}

	/**
	 * جداکننده هزارگان عددی.
	 *
	 * @param {string} whole بخش صحیح.
	 * @param {string} separator جداکننده.
	 * @return {string} با جداکننده.
	 */
	function groupDigits( whole, separator ) {
		return whole.replace( /\B(?=(\d{3})+(?!\d))/g, separator );
	}

	/**
	 * قالب‌بندی مبلغ برای نمایش.
	 *
	 * @param {string} raw        مبلغ خام.
	 * @param {boolean} withCurrency افزودن واحد.
	 * @return {string} مقدار قالب‌بندی‌شده.
	 */
	function formatAmount( raw, withCurrency ) {
		var separator = config.separator || ',';
		var currency = config.currency || '';
		var normalized = toLatinDigits( raw ).replace( /[^\d.\-]/g, '' );

		if ( '' === normalized || '-' === normalized || '.' === normalized ) {
			return '—';
		}

		var negative = '-' === normalized.charAt( 0 );
		normalized = normalized.replace( /-/g, '' );

		var parts = normalized.split( '.' );
		var whole = parts[ 0 ] || '0';
		var fraction = parts[ 1 ] || '';

		/*
		 * اعشار را با همان قاعدهٔ سرور **قطع** می‌کنیم، نه گرد.
		 *
		 * پیش‌تر `config.decimals` به جاوااسکریپت فرستاده می‌شد ولی هیچ‌جا
		 * خوانده نمی‌شد و هر تعداد رقم اعشاری که کاربر تایپ می‌کرد (تا ۴)
		 * در پیش‌نمایش می‌آمد. سرور همان مبلغ را با `currency_decimals`
		 * قطع می‌کند (`Amount::scaled_to_decimal_string()` از `substr`
		 * استفاده می‌کند و گرد نمی‌کند)، پس نسخهٔ اول این رفع که «نیم به
		 * بالا» گرد می‌کرد، خودش یک ناسازگاری تازه می‌ساخت: پیش‌نمایش
		 * «۱٬۰۰۱» و ذخیره‌شدهٔ واقعی «۱٬۰۰۰».
		 *
		 * برای مبلغ مالی هم قطع درست‌تر است: هیچ‌وقت مبلغی بیش از آنچه
		 * واقعاً ثبت شده نشان داده نمی‌شود.
		 */
		var decimals = parseInt( config.decimals, 10 );
		decimals     = isNaN( decimals ) || decimals < 0 ? 0 : Math.min( 4, decimals );

		fraction = ( fraction + '0000' ).substring( 0, decimals );
		fraction = fraction.replace( /0+$/, '' );

		var output = ( negative ? '-' : '' ) + groupDigits( whole, separator );

		if ( fraction ) {
			output += '.' + fraction;
		}

		if ( withCurrency && currency ) {
			output += ' ' + currency;
		}

		return config.persianDigits ? toPersianDigits( output ) : output;
	}

	/* ۱) تأیید عملیات حساس ----------------------------------------- */
	document.addEventListener( 'submit', function ( event ) {
		var form = event.target;

		if ( ! form || ! form.hasAttribute ) {
			return;
		}

		var message = form.getAttribute( 'data-confirm' );

		if ( message ) {
			if ( ! window.confirm( message ) ) {
				event.preventDefault();
				event.stopPropagation();
				return false;
			}

			// جلوگیری از ارسال دوباره.
			var buttons = form.querySelectorAll( 'button[type="submit"], input[type="submit"]' );
			Array.prototype.forEach.call( buttons, function ( button ) {
				button.disabled = true;
			} );
		}

		// اعتبارسنجی فرم تراکنش.
		if ( form.id === 'spk-transaction-form' ) {
			return validateTransactionForm( form, event );
		}
	}, true );

	/**
	 * اعتبارسنجی فرم تراکنش در سمت کاربر.
	 *
	 * @param {HTMLFormElement} form فرم.
	 * @param {Event}           event رویداد.
	 * @return {boolean} نتیجه.
	 */
	function validateTransactionForm( form, event ) {
		var amountInput = form.querySelector( '[data-amount-input]' );
		var dateInput = form.querySelector( '[data-date-input]' );
		var valid = true;

		if ( amountInput ) {
			var rawAmount = toLatinDigits( amountInput.value );
			var amount    = rawAmount.replace( /[^\d.]/g, '' );

			/*
			 * علامت منفی پیش از حذف نویسه‌ها بررسی می‌شود؛ وگرنه «-5» به «5»
			 * تبدیل می‌شد و از اعتبارسنجی سمت کاربر رد می‌شد ولی سرور ردش
			 * می‌کرد و کاربر پیام خطای متناقض می‌گرفت.
			 */
			var has_minus = /-/.test( rawAmount );

			if ( has_minus || '' === amount || parseFloat( amount ) <= 0 ) {
				markError( amountInput, strings.invalidAmount || 'مبلغ معتبر نیست.' );
				valid = false;
			} else {
				clearError( amountInput );
			}
		}

		if ( dateInput && '' === dateInput.value.trim() ) {
			markError( dateInput, strings.required || 'تاریخ الزامی است.' );
			valid = false;
		} else if ( dateInput ) {
			clearError( dateInput );
		}

		if ( ! valid ) {
			event.preventDefault();
			event.stopPropagation();
		}

		return valid;
	}

	/**
	 * نمایش خطای فیلد.
	 *
	 * @param {HTMLElement} input   فیلد.
	 * @param {string}      message پیام.
	 * @return {void}
	 */
	function markError( input, message ) {
		var wrapper = input.closest( '.spk-field' );

		if ( ! wrapper ) {
			return;
		}

		wrapper.classList.add( 'has-error' );

		var error = wrapper.querySelector( '.spk-field__error' );

		if ( ! error ) {
			error = document.createElement( 'p' );
			error.className = 'spk-field__error';
			error.setAttribute( 'role', 'alert' );
			wrapper.appendChild( error );
		}

		error.textContent = message;
		input.setAttribute( 'aria-invalid', 'true' );
	}

	/**
	 * پاک‌کردن خطای فیلد.
	 *
	 * @param {HTMLElement} input فیلد.
	 * @return {void}
	 */
	function clearError( input ) {
		var wrapper = input.closest( '.spk-field' );

		if ( ! wrapper ) {
			return;
		}

		wrapper.classList.remove( 'has-error' );
		input.removeAttribute( 'aria-invalid' );

		var error = wrapper.querySelector( '.spk-field__error' );

		if ( error ) {
			error.remove();
		}
	}

	/* ۲) پیش‌نمایش مبلغ -------------------------------------------- */
	function initAmountPreview() {
		var input = document.querySelector( '[data-amount-input]' );
		var preview = document.querySelector( '[data-amount-preview]' );

		if ( ! input || ! preview ) {
			return;
		}

		var update = function () {
			preview.textContent = formatAmount( input.value, true );
		};

		input.addEventListener( 'input', function () {
			update();
			clearError( input );
		} );

		input.addEventListener( 'blur', function () {
			var raw = toLatinDigits( input.value ).replace( /[^\d.]/g, '' );

			if ( '' === raw ) {
				return;
			}

			var parts = raw.split( '.' );
			var whole = parts[ 0 ].replace( /^0+(?=\d)/, '' );
			var formatted = groupDigits( whole, config.separator || ',' );

			if ( parts[ 1 ] ) {
				formatted += '.' + parts[ 1 ].substring( 0, 4 );
			}

			if ( ! config.persianDigits ) {
				input.value = formatted;
			} else {
				// در حالت ارقام فارسی، مقدار فیلد لاتین می‌ماند تا ارسال درست انجام شود.
				input.value = formatted.replace( /,/g, '' );
			}

			update();
		} );

		update();
	}

	/* ۳) بازه دلخواه ------------------------------------------------ */
	function initRangeToggle() {
		var select = document.getElementById( 'spk-range' );
		var wrap = document.querySelector( '.spk-wrap' );

		if ( ! select || ! wrap ) {
			return;
		}

		var toggle = function () {
			wrap.classList.toggle( 'has-custom-range', 'custom' === select.value );
		};

		select.addEventListener( 'change', toggle );
		toggle();
	}

	/* ۵) پیش‌نمایش تاریخ ------------------------------------------- */
	function initDateInput() {
		var input = document.querySelector( '[data-date-input]' );

		if ( ! input ) {
			return;
		}

		input.addEventListener( 'input', function () {
			var value = toLatinDigits( input.value ).replace( /[^\d\/\-]/g, '' );

			if ( value !== input.value ) {
				input.value = value;
			}

			clearError( input );
		} );
	}

	/* راه‌اندازی --------------------------------------------------- */
	function init() {
		initAmountPreview();
		initRangeToggle();
		initDateInput();
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
