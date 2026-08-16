<?php
/**
 * Renders project locations as an accessible list carrying geo microdata.
 *
 * No mapping library is loaded. The coordinates are published on each item so a
 * theme can attach whichever map provider the site already uses, without this
 * plugin sending visitor data to a third party.
 *
 * Override by copying this file to `yourtheme/procore-connect/map.php`.
 *
 * @package ProcoreConnect
 *
 * @var array<string, mixed> $data Template data supplied by the Renderer.
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

$procore_connect_points = (array) ( $data['points'] ?? array() );
$procore_connect_title  = (string) ( $data['title'] ?? '' );
$procore_connect_link   = ! empty( $data['link'] );
$procore_connect_class  = (string) ( $data['class'] ?? 'procore-connect' );

if ( empty( $procore_connect_points ) ) {
	return;
}
?>
<div class="<?php echo esc_attr( $procore_connect_class ); ?>" data-procore-connect-points="<?php echo esc_attr( (string) wp_json_encode( $procore_connect_points ) ); ?>">
	<?php if ( '' !== $procore_connect_title ) : ?>
		<h3 class="procore-connect-title"><?php echo esc_html( $procore_connect_title ); ?></h3>
	<?php endif; ?>

	<ul class="procore-connect-map__list">
		<?php foreach ( $procore_connect_points as $procore_connect_point ) : ?>
			<li
				class="procore-connect-map__item"
				data-latitude="<?php echo esc_attr( (string) $procore_connect_point['latitude'] ); ?>"
				data-longitude="<?php echo esc_attr( (string) $procore_connect_point['longitude'] ); ?>"
			>
				<span class="procore-connect-map__name"><?php echo esc_html( (string) $procore_connect_point['name'] ); ?></span>

				<?php if ( '' !== (string) $procore_connect_point['location'] ) : ?>
					<span class="procore-connect-map__location"><?php echo esc_html( (string) $procore_connect_point['location'] ); ?></span>
				<?php endif; ?>

				<?php if ( $procore_connect_link ) : ?>
					<a
						class="procore-connect-map__link"
						href="<?php echo esc_url( 'https://www.openstreetmap.org/?mlat=' . rawurlencode( (string) $procore_connect_point['latitude'] ) . '&mlon=' . rawurlencode( (string) $procore_connect_point['longitude'] ) . '#map=15/' . rawurlencode( (string) $procore_connect_point['latitude'] ) . '/' . rawurlencode( (string) $procore_connect_point['longitude'] ) ); ?>"
						rel="nofollow noopener external"
					>
						<?php
						printf(
							/* translators: %s: project name. */
							esc_html__( 'View %s on a map', 'connect-for-procore' ),
							esc_html( (string) $procore_connect_point['name'] )
						);
						?>
					</a>
				<?php endif; ?>
			</li>
		<?php endforeach; ?>
	</ul>
</div>
