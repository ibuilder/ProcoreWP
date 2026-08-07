<?php
/**
 * Renders a project image.
 *
 * Override by copying this file to `yourtheme/procore-connect/image.php`.
 *
 * @package ProcoreConnect
 *
 * @var array<string, mixed> $data Template data supplied by the Renderer.
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

$procore_connect_url    = (string) ( $data['url'] ?? '' );
$procore_connect_alt    = (string) ( $data['alt'] ?? '' );
$procore_connect_width  = (int) ( $data['width'] ?? 0 );
$procore_connect_height = (int) ( $data['height'] ?? 0 );
$procore_connect_lazy   = ! empty( $data['lazy'] );
$procore_connect_class  = (string) ( $data['class'] ?? 'procore-connect' );

if ( '' === $procore_connect_url ) {
	return;
}
?>
<figure class="<?php echo esc_attr( $procore_connect_class ); ?>">
	<img
		src="<?php echo esc_url( $procore_connect_url ); ?>"
		alt="<?php echo esc_attr( $procore_connect_alt ); ?>"
		<?php if ( $procore_connect_width > 0 ) : ?>
			width="<?php echo esc_attr( (string) $procore_connect_width ); ?>"
		<?php endif; ?>
		<?php if ( $procore_connect_height > 0 ) : ?>
			height="<?php echo esc_attr( (string) $procore_connect_height ); ?>"
		<?php endif; ?>
		<?php if ( $procore_connect_lazy ) : ?>
			loading="lazy" decoding="async"
		<?php endif; ?>
		referrerpolicy="no-referrer"
	/>
</figure>
