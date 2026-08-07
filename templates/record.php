<?php
/**
 * Renders a single Procore record as a labelled detail list.
 *
 * Override by copying this file to `yourtheme/procorewp/record.php`.
 *
 * @package ProcoreWP
 *
 * @var array<string, mixed> $data Template data supplied by the Renderer.
 */

declare( strict_types = 1 );

use ProcoreWP\Support\Format;

defined( 'ABSPATH' ) || exit;

$procorewp_record     = (array) ( $data['record'] ?? array() );
$procorewp_columns    = (array) ( $data['columns'] ?? array() );
$procorewp_title      = (string) ( $data['title'] ?? '' );
$procorewp_class      = (string) ( $data['class'] ?? 'procorewp' );
$procorewp_show_email = ! empty( $data['show_email'] );

if ( empty( $procorewp_record ) ) {
	return;
}
?>
<div class="<?php echo esc_attr( $procorewp_class ); ?>">
	<?php if ( '' !== $procorewp_title ) : ?>
		<h2 class="procorewp-title"><?php echo esc_html( $procorewp_title ); ?></h2>
	<?php endif; ?>

	<dl class="procorewp-details">
		<?php foreach ( $procorewp_columns as $procorewp_column ) : ?>
			<?php $procorewp_value = Format::cell( $procorewp_record, $procorewp_column, $procorewp_show_email ); ?>
			<?php if ( '' === $procorewp_value || '—' === $procorewp_value ) : ?>
				<?php continue; ?>
			<?php endif; ?>
			<div class="procorewp-detail">
				<dt><?php echo esc_html( (string) ( $procorewp_column['label'] ?? '' ) ); ?></dt>
				<dd><?php echo wp_kses_post( $procorewp_value ); ?></dd>
			</div>
		<?php endforeach; ?>
	</dl>
</div>
