<?php
/**
 * Renders a collection of Procore records as a responsive table.
 *
 * Override by copying this file to `yourtheme/procore-connect/collection.php`.
 *
 * @package ProcoreConnect
 *
 * @var array<string, mixed> $data Template data supplied by the Renderer.
 */

declare( strict_types = 1 );

use ProcoreConnect\Support\Format;

defined( 'ABSPATH' ) || exit;

$procore_connect_rows       = (array) ( $data['rows'] ?? array() );
$procore_connect_columns    = (array) ( $data['columns'] ?? array() );
$procore_connect_title      = (string) ( $data['title'] ?? '' );
$procore_connect_class      = (string) ( $data['class'] ?? 'procore-connect' );
$procore_connect_show_email = ! empty( $data['show_email'] );

if ( empty( $procore_connect_columns ) ) {
	return;
}
?>
<div class="<?php echo esc_attr( $procore_connect_class ); ?>">
	<?php if ( '' !== $procore_connect_title ) : ?>
		<h3 class="procore-connect-title"><?php echo esc_html( $procore_connect_title ); ?></h3>
	<?php endif; ?>

	<div class="procore-connect-table-wrap">
		<table class="procore-connect-table">
			<thead>
				<tr>
					<?php foreach ( $procore_connect_columns as $procore_connect_column ) : ?>
						<th scope="col"><?php echo esc_html( (string) ( $procore_connect_column['label'] ?? '' ) ); ?></th>
					<?php endforeach; ?>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $procore_connect_rows as $procore_connect_row ) : ?>
					<tr>
						<?php foreach ( $procore_connect_columns as $procore_connect_column ) : ?>
							<td data-label="<?php echo esc_attr( (string) ( $procore_connect_column['label'] ?? '' ) ); ?>">
								<?php
								/*
								 * Format::cell() escapes every value it returns; the
								 * markup it emits is limited to a status span or a link.
								 */
								echo wp_kses_post( Format::cell( $procore_connect_row, $procore_connect_column, $procore_connect_show_email ) );
								?>
							</td>
						<?php endforeach; ?>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</div>
