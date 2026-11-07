<?php
/**
 * فیلد فرم استاندارد.
 *
 * @var string $name        نام.
 * @var string $label       برچسب.
 * @var string $type        نوع (text, tel, textarea, select, number, date, checkbox).
 * @var mixed  $value       مقدار.
 * @var string $hint        راهنما.
 * @var bool   $required    الزامی.
 * @var string $error       پیام خطا.
 * @var array  $options     گزینه‌ها برای select.
 * @var string $placeholder راهنمای درون فیلد.
 * @var string $class       کلاس اضافه.
 * @var string $id          شناسه (پیش‌فرض name).
 * @var string $extra       ویژگی‌های اضافی امن.
 *
 * @author  SparKnife <155084046+sparkknife@users.noreply.github.com>
 *
 * @package SparKnife_Ledger
 */

defined( 'ABSPATH' ) || exit;

$name        = isset( $name ) ? $name : '';
$label       = isset( $label ) ? $label : '';
$type        = isset( $type ) ? $type : 'text';
$value       = isset( $value ) ? $value : '';
$hint        = isset( $hint ) ? $hint : '';
$required    = ! empty( $required );
$error       = isset( $error ) ? $error : '';
$options     = isset( $options ) && is_array( $options ) ? $options : array();
$placeholder = isset( $placeholder ) ? $placeholder : '';
$class       = isset( $class ) ? $class : '';
$id          = isset( $id ) ? $id : 'spk-field-' . $name;
$extra       = isset( $extra ) ? $extra : '';
$rows        = isset( $rows ) ? (int) $rows : 4;
?>
<div class="spk-field <?php echo $error ? 'has-error' : ''; ?> <?php echo esc_attr( $class ); ?>">
	<?php if ( 'checkbox' !== $type ) : ?>
		<label class="spk-field__label" for="<?php echo esc_attr( $id ); ?>">
			<?php echo esc_html( $label ); ?>
			<?php if ( $required ) : ?>
				<span class="spk-required" aria-hidden="true">*</span>
			<?php endif; ?>
		</label>
	<?php endif; ?>

	<?php if ( 'textarea' === $type ) : ?>
		<textarea
			class="spk-input"
			id="<?php echo esc_attr( $id ); ?>"
			name="<?php echo esc_attr( $name ); ?>"
			rows="<?php echo esc_attr( (string) $rows ); ?>"
			placeholder="<?php echo esc_attr( $placeholder ); ?>"
			<?php echo $required ? 'required' : ''; ?>
			<?php echo $extra; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ویژگی‌ها توسط فراخوان کنترل می‌شود. ?>
		><?php echo esc_textarea( (string) $value ); ?></textarea>
	<?php elseif ( 'select' === $type ) : ?>
		<select
			class="spk-input"
			id="<?php echo esc_attr( $id ); ?>"
			name="<?php echo esc_attr( $name ); ?>"
			<?php echo $required ? 'required' : ''; ?>
			<?php echo $extra; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		>
			<?php foreach ( $options as $option_value => $option_label ) : ?>
				<option value="<?php echo esc_attr( (string) $option_value ); ?>" <?php selected( (string) $value, (string) $option_value ); ?>>
					<?php echo esc_html( $option_label ); ?>
				</option>
			<?php endforeach; ?>
		</select>
	<?php elseif ( 'checkbox' === $type ) : ?>
		<label class="spk-checkbox" for="<?php echo esc_attr( $id ); ?>">
			<input
				type="checkbox"
				id="<?php echo esc_attr( $id ); ?>"
				name="<?php echo esc_attr( $name ); ?>"
				value="<?php echo esc_attr( '' !== (string) $value && '0' !== (string) $value ? (string) $value : '1' ); ?>"
				<?php checked( ! empty( $value ) && '0' !== (string) $value ); ?>
				<?php echo $extra; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			/>
			<span><?php echo esc_html( $label ); ?></span>
		</label>
	<?php else : ?>
		<input
			class="spk-input"
			type="<?php echo esc_attr( $type ); ?>"
			id="<?php echo esc_attr( $id ); ?>"
			name="<?php echo esc_attr( $name ); ?>"
			value="<?php echo esc_attr( (string) $value ); ?>"
			placeholder="<?php echo esc_attr( $placeholder ); ?>"
			<?php echo $required ? 'required' : ''; ?>
			<?php echo $extra; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		/>
	<?php endif; ?>

	<?php if ( '' !== $hint ) : ?>
		<p class="spk-field__hint"><?php echo esc_html( $hint ); ?></p>
	<?php endif; ?>

	<?php if ( $error ) : ?>
		<p class="spk-field__error" role="alert"><?php echo esc_html( $error ); ?></p>
	<?php endif; ?>
</div>
