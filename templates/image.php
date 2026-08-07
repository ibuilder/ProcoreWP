<?php
/**
 * Renders a project image.
 *
 * Override by copying this file to `yourtheme/procorewp/image.php`.
 *
 * @package ProcoreWP
 *
 * @var array<string, mixed> $data Template data supplied by the Renderer.
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

$procorewp_url    = (string) ( $data['url'] ?? '' );
$procorewp_alt    = (string) ( $data['alt'] ?? '' );
$procorewp_width  = (int) ( $data['width'] ?? 0 );
$procorewp_height = (int) ( $data['height'] ?? 0 );
$procorewp_lazy   = ! empty( $data['lazy'] );
$procorewp_class  = (string) ( $data['class'] ?? 'procorewp' );

if ( '' === $procorewp_url ) {
	return;
}
?>
<figure class="<?php echo esc_attr( $procorewp_class ); ?>">
	<img
		src="<?php echo esc_url( $procorewp_url ); ?>"
		alt="<?php echo esc_attr( $procorewp_alt ); ?>"
		<?php if ( $procorewp_width > 0 ) : ?>
			width="<?php echo esc_attr( (string) $procorewp_width ); ?>"
		<?php endif; ?>
		<?php if ( $procorewp_height > 0 ) : ?>
			height="<?php echo esc_attr( (string) $procorewp_height ); ?>"
		<?php endif; ?>
		<?php if ( $procorewp_lazy ) : ?>
			loading="lazy" decoding="async"
		<?php endif; ?>
		referrerpolicy="no-referrer"
	/>
</figure>
