<?php
/**
 * Renders a single Procore record as a labelled detail list.
 *
 * Override by copying this file to `yourtheme/procore-connect/record.php`.
 *
 * @package ProcoreConnect
 *
 * @var array<string, mixed> $data Template data supplied by the Renderer.
 */

declare( strict_types = 1 );

use ProcoreConnect\Support\Format;

defined( 'ABSPATH' ) || exit;

$procore_connect_record     = (array) ( $data['record'] ?? array() );
$procore_connect_columns    = (array) ( $data['columns'] ?? array() );
$procore_connect_title      = (string) ( $data['title'] ?? '' );
$procore_connect_class      = (string) ( $data['class'] ?? 'procore-connect' );
$procore_connect_show_email = ! empty( $data['show_email'] );

if ( empty( $procore_connect_record ) ) {
	return;
}
?>
<div class="<?php echo esc_attr( $procore_connect_class ); ?>">
	<?php if ( '' !== $procore_connect_title ) : ?>
		<h2 class="procore-connect-title"><?php echo esc_html( $procore_connect_title ); ?></h2>
	<?php endif; ?>

	<dl class="procore-connect-details">
		<?php foreach ( $procore_connect_columns as $procore_connect_column ) : ?>
			<?php $procore_connect_value = Format::cell( $procore_connect_record, $procore_connect_column, $procore_connect_show_email ); ?>
			<?php if ( '' === $procore_connect_value || '—' === $procore_connect_value ) : ?>
				<?php continue; ?>
			<?php endif; ?>
			<div class="procore-connect-detail">
				<dt><?php echo esc_html( (string) ( $procore_connect_column['label'] ?? '' ) ); ?></dt>
				<dd><?php echo wp_kses_post( $procore_connect_value ); ?></dd>
			</div>
		<?php endforeach; ?>
	</dl>
</div>
