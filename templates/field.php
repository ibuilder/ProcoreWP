<?php
/**
 * Renders a single labelled project field.
 *
 * Override by copying this file to `yourtheme/procore-connect/field.php`.
 *
 * @package ProcoreConnect
 *
 * @var array<string, mixed> $data Template data supplied by the Renderer.
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

$procore_connect_label = (string) ( $data['label'] ?? '' );
$procore_connect_value = (string) ( $data['value'] ?? '' );
$procore_connect_field = (string) ( $data['field'] ?? '' );
$procore_connect_class = (string) ( $data['class'] ?? 'procore-connect' );

if ( '' === $procore_connect_value ) {
	return;
}
?>
<div class="<?php echo esc_attr( $procore_connect_class ); ?>" data-field="<?php echo esc_attr( $procore_connect_field ); ?>">
	<?php if ( '' !== $procore_connect_label ) : ?>
		<span class="procore-connect-field__label"><?php echo esc_html( $procore_connect_label ); ?></span>
	<?php endif; ?>
	<span class="procore-connect-field__value"><?php echo esc_html( $procore_connect_value ); ?></span>
</div>
