<?php
/**
 * Dynamic hero tour route map with accessible location fallback list.
 */

defined( 'ABSPATH' ) || exit;

$stops = is_array( $stations ?? null ) ? array_values( $stations ) : TAKA_Platform_Data::hero_route_map_stations( null, $events ?? null );
if ( empty( $stops ) ) { return; }

foreach ( $stops as $position => &$stop ) {
	$stop['marker_x'] = is_numeric( $stop['marker_x'] ?? null ) ? (float) $stop['marker_x'] : (float) ( $stop['x'] ?? 0 );
	$stop['marker_y'] = is_numeric( $stop['marker_y'] ?? null ) ? (float) $stop['marker_y'] : (float) ( $stop['y'] ?? 0 );
	$stop['label_x'] = is_numeric( $stop['label_x'] ?? null ) ? (float) $stop['label_x'] : $stop['marker_x'];
	$stop['label_y'] = is_numeric( $stop['label_y'] ?? null ) ? (float) $stop['label_y'] : $stop['marker_y'];
	$anchor = sanitize_key( $stop['label_anchor'] ?? '' );
	$stop['label_anchor'] = in_array( $anchor, array( 'left', 'right', 'center' ), true ) ? $anchor : 'left';
	$stop['label_width'] = trim( (string) ( $stop['label_width'] ?? '' ) );
	if ( '' === $stop['label_width'] ) { $stop['label_width'] = '11rem'; }
	$stop['label_mobile_x'] = is_numeric( $stop['label_mobile_x'] ?? null ) ? (float) $stop['label_mobile_x'] : $stop['label_x'];
	$stop['label_mobile_y'] = is_numeric( $stop['label_mobile_y'] ?? null ) ? (float) $stop['label_mobile_y'] : $stop['label_y'];
	$mobile_anchor = sanitize_key( $stop['label_mobile_anchor'] ?? '' );
	$stop['label_mobile_anchor'] = in_array( $mobile_anchor, array( 'left', 'right', 'center' ), true ) ? $mobile_anchor : $stop['label_anchor'];
	$stop['label_mobile_width'] = trim( (string) ( $stop['label_mobile_width'] ?? '' ) );
	if ( '' === $stop['label_mobile_width'] ) { $stop['label_mobile_width'] = '36%'; }
	foreach ( array( 'leader_x1', 'leader_y1', 'leader_x2', 'leader_y2' ) as $key ) {
		$stop[ $key ] = is_numeric( $stop[ $key ] ?? null ) ? (float) $stop[ $key ] : ( 'leader_x1' === $key || 'leader_x2' === $key ? $stop['marker_x'] : $stop['marker_y'] );
		$mobile_key = str_replace( 'leader_', 'leader_mobile_', $key );
		$stop[ $mobile_key ] = is_numeric( $stop[ $mobile_key ] ?? null ) ? (float) $stop[ $mobile_key ] : $stop[ $key ];
	}
}
unset( $stop );

$line_points = count( $stops ) > 1 ? implode( ' ', array_map( static fn( $stop ) => round( $stop['marker_x'], 2 ) . ',' . round( $stop['marker_y'], 2 ), $stops ) ) : '';
?>
<div class="taka-hero-route-map" aria-label="<?php echo esc_attr( taka_tour_translate( 'hero.event_locations', 'Event locations' ) ); ?>">
	<?php if ( ! empty( $stops ) ) : ?>
		<div class="taka-hero-route-map__canvas" aria-label="<?php echo esc_attr( taka_tour_translate( 'hero.map_view', 'Map view' ) ); ?>">
			<svg class="taka-hero-route-map__svg" viewBox="0 0 100 100" aria-hidden="true" focusable="false" role="presentation">
				<path class="taka-hero-route-map__silhouette" d="M64 9 C75 12 82 23 79 34 C88 42 85 56 74 59 C70 70 58 75 48 70 C39 78 25 73 24 61 C13 55 14 39 25 34 C28 23 40 19 48 24 C51 14 57 9 64 9 Z" />
				<path class="taka-hero-route-map__silhouette taka-hero-route-map__silhouette--south" d="M50 67 C60 66 69 72 70 82 C63 88 49 88 42 80 C39 74 43 69 50 67 Z" />
				<?php if ( '' !== $line_points ) : ?>
					<polyline class="taka-hero-route-map__line" points="<?php echo esc_attr( $line_points ); ?>" />
				<?php endif; ?>
				<?php foreach ( $stops as $stop ) : ?>
					<?php if ( ! empty( $stop['leader_line'] ) ) : ?>
						<line class="taka-hero-route-map__leader taka-hero-route-map__leader--desktop" x1="<?php echo esc_attr( (string) round( $stop['leader_x1'], 2 ) ); ?>" y1="<?php echo esc_attr( (string) round( $stop['leader_y1'], 2 ) ); ?>" x2="<?php echo esc_attr( (string) round( $stop['leader_x2'], 2 ) ); ?>" y2="<?php echo esc_attr( (string) round( $stop['leader_y2'], 2 ) ); ?>" />
					<?php endif; ?>
					<?php if ( ! empty( $stop['leader_line_mobile'] ) ) : ?>
						<line class="taka-hero-route-map__leader taka-hero-route-map__leader--mobile" x1="<?php echo esc_attr( (string) round( $stop['leader_mobile_x1'], 2 ) ); ?>" y1="<?php echo esc_attr( (string) round( $stop['leader_mobile_y1'], 2 ) ); ?>" x2="<?php echo esc_attr( (string) round( $stop['leader_mobile_x2'], 2 ) ); ?>" y2="<?php echo esc_attr( (string) round( $stop['leader_mobile_y2'], 2 ) ); ?>" />
					<?php endif; ?>
				<?php endforeach; ?>
			</svg>
			<?php foreach ( $stops as $stop ) : ?>
				<?php
				$event = $stop['event'];
				$tab_key = TAKA_Platform_Data::event_panel_key( $event );
				$share_url = TAKA_Platform_Data::event_share_url( $event, taka_tour_current_language() ) ?: '#tickets';
				$country = trim( (string) ( $event['country_label'] ?? ( $event['country'] ?? '' ) ) );
				$flag = trim( (string) ( $event['hero_flag'] ?? '' ) );
				$aria_label = sprintf( taka_tour_translate( 'hero.show_tickets_for', 'Show tickets for %s' ), trim( $stop['label'] . ( '' !== $country ? ', ' . $country : '' ) ) );
				$label_class = 'taka-hero-route-map__label taka-hero-route-map__label--anchor-' . $stop['label_anchor'] . ' taka-hero-route-map__label--mobile-anchor-' . $stop['label_mobile_anchor'];
				?>
				<a class="taka-hero-route-map__marker" href="<?php echo esc_url( $share_url ); ?>" data-taka-ticket-tab="<?php echo esc_attr( $tab_key ); ?>" style="left:<?php echo esc_attr( (string) $stop['marker_x'] ); ?>%;top:<?php echo esc_attr( (string) $stop['marker_y'] ); ?>%;" aria-label="<?php echo esc_attr( $aria_label ); ?>">
					<span class="taka-hero-route-map__pin" aria-hidden="true"></span>
				</a>
				<a class="<?php echo esc_attr( $label_class ); ?>" href="<?php echo esc_url( $share_url ); ?>" data-taka-ticket-tab="<?php echo esc_attr( $tab_key ); ?>" style="left:<?php echo esc_attr( (string) $stop['label_x'] ); ?>%;top:<?php echo esc_attr( (string) $stop['label_y'] ); ?>%;--taka-route-label-width:<?php echo esc_attr( $stop['label_width'] ); ?>;--taka-route-label-mobile-x:<?php echo esc_attr( (string) $stop['label_mobile_x'] ); ?>%;--taka-route-label-mobile-y:<?php echo esc_attr( (string) $stop['label_mobile_y'] ); ?>%;--taka-route-label-mobile-width:<?php echo esc_attr( $stop['label_mobile_width'] ); ?>;" aria-label="<?php echo esc_attr( $aria_label ); ?>">
					<?php if ( '' !== $flag ) : ?><span class="taka-hero-location-flag" aria-hidden="true"><?php echo esc_html( $flag ); ?></span><?php endif; ?><span class="taka-hero-route-map__label-text"><?php echo esc_html( $stop['label'] ); ?></span>
				</a>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</div>
