<?php
/**
 * Renders a single labelled project field.
 *
 * Override by copying this file to `yourtheme/procorewp/field.php`.
 *
 * @package ProcoreWP
 *
 * @var array<string, mixed> $data Template data supplied by the Renderer.
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

$procorewp_label = (string) ( $data['label'] ?? '' );
$procorewp_value = (string) ( $data['value'] ?? '' );
$procorewp_field = (string) ( $data['field'] ?? '' );
$procorewp_class = (string) ( $data['class'] ?? 'procorewp' );

if ( '' === $procorewp_value ) {
	return;
}
?>
<div class="<?php echo esc_attr( $procorewp_class ); ?>" data-field="<?php echo esc_attr( $procorewp_field ); ?>">
	<?php if ( '' !== $procorewp_label ) : ?>
		<span class="procorewp-field__label"><?php echo esc_html( $procorewp_label ); ?></span>
	<?php endif; ?>
	<span class="procorewp-field__value"><?php echo esc_html( $procorewp_value ); ?></span>
</div>
