<?php
/**
 * Renders project locations as an accessible list carrying geo microdata.
 *
 * No mapping library is loaded. The coordinates are published on each item so a
 * theme can attach whichever map provider the site already uses, without this
 * plugin sending visitor data to a third party.
 *
 * Override by copying this file to `yourtheme/procorewp/map.php`.
 *
 * @package ProcoreWP
 *
 * @var array<string, mixed> $data Template data supplied by the Renderer.
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

$procorewp_points = (array) ( $data['points'] ?? array() );
$procorewp_title  = (string) ( $data['title'] ?? '' );
$procorewp_link   = ! empty( $data['link'] );
$procorewp_class  = (string) ( $data['class'] ?? 'procorewp' );

if ( empty( $procorewp_points ) ) {
	return;
}
?>
<div class="<?php echo esc_attr( $procorewp_class ); ?>" data-procorewp-points="<?php echo esc_attr( (string) wp_json_encode( $procorewp_points ) ); ?>">
	<?php if ( '' !== $procorewp_title ) : ?>
		<h3 class="procorewp-title"><?php echo esc_html( $procorewp_title ); ?></h3>
	<?php endif; ?>

	<ul class="procorewp-map__list">
		<?php foreach ( $procorewp_points as $procorewp_point ) : ?>
			<li
				class="procorewp-map__item"
				data-latitude="<?php echo esc_attr( (string) $procorewp_point['latitude'] ); ?>"
				data-longitude="<?php echo esc_attr( (string) $procorewp_point['longitude'] ); ?>"
			>
				<span class="procorewp-map__name"><?php echo esc_html( (string) $procorewp_point['name'] ); ?></span>

				<?php if ( '' !== (string) $procorewp_point['location'] ) : ?>
					<span class="procorewp-map__location"><?php echo esc_html( (string) $procorewp_point['location'] ); ?></span>
				<?php endif; ?>

				<?php if ( $procorewp_link ) : ?>
					<a
						class="procorewp-map__link"
						href="<?php echo esc_url( 'https://www.openstreetmap.org/?mlat=' . rawurlencode( (string) $procorewp_point['latitude'] ) . '&mlon=' . rawurlencode( (string) $procorewp_point['longitude'] ) . '#map=15/' . rawurlencode( (string) $procorewp_point['latitude'] ) . '/' . rawurlencode( (string) $procorewp_point['longitude'] ) ); ?>"
						rel="nofollow noopener external"
					>
						<?php
						printf(
							/* translators: %s: project name. */
							esc_html__( 'View %s on a map', 'procorewp' ),
							esc_html( (string) $procorewp_point['name'] )
						);
						?>
					</a>
				<?php endif; ?>
			</li>
		<?php endforeach; ?>
	</ul>
</div>
