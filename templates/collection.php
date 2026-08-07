<?php
/**
 * Renders a collection of Procore records as a responsive table.
 *
 * Override by copying this file to `yourtheme/procorewp/collection.php`.
 *
 * @package ProcoreWP
 *
 * @var array<string, mixed> $data Template data supplied by the Renderer.
 */

declare( strict_types = 1 );

use ProcoreWP\Support\Format;

defined( 'ABSPATH' ) || exit;

$procorewp_rows       = (array) ( $data['rows'] ?? array() );
$procorewp_columns    = (array) ( $data['columns'] ?? array() );
$procorewp_title      = (string) ( $data['title'] ?? '' );
$procorewp_class      = (string) ( $data['class'] ?? 'procorewp' );
$procorewp_show_email = ! empty( $data['show_email'] );

if ( empty( $procorewp_columns ) ) {
	return;
}
?>
<div class="<?php echo esc_attr( $procorewp_class ); ?>">
	<?php if ( '' !== $procorewp_title ) : ?>
		<h3 class="procorewp-title"><?php echo esc_html( $procorewp_title ); ?></h3>
	<?php endif; ?>

	<div class="procorewp-table-wrap">
		<table class="procorewp-table">
			<thead>
				<tr>
					<?php foreach ( $procorewp_columns as $procorewp_column ) : ?>
						<th scope="col"><?php echo esc_html( (string) ( $procorewp_column['label'] ?? '' ) ); ?></th>
					<?php endforeach; ?>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $procorewp_rows as $procorewp_row ) : ?>
					<tr>
						<?php foreach ( $procorewp_columns as $procorewp_column ) : ?>
							<td data-label="<?php echo esc_attr( (string) ( $procorewp_column['label'] ?? '' ) ); ?>">
								<?php
								/*
								 * Format::cell() escapes every value it returns; the
								 * markup it emits is limited to a status span or a link.
								 */
								echo wp_kses_post( Format::cell( $procorewp_row, $procorewp_column, $procorewp_show_email ) );
								?>
							</td>
						<?php endforeach; ?>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</div>
