<?php defined( 'ABSPATH' ) || exit; ?>
<div class="taka-map" aria-label="<?php echo esc_attr__( 'Stilisierte Europakarte mit Seminarpins', 'taka-tour' ); ?>">
	<svg viewBox="0 0 640 520" role="img" aria-hidden="true" focusable="false">
		<path d="M159 235 190 170l78-34 84 34 105-24 70 50-35 87 25 82-72 67-111-22-92 46-96-52 35-84-58-52Z" />
		<path d="M378 82 460 103l40 70-87 18-63-43Z" />
		<path d="M252 255 330 240l65 48-28 75-91 18-57-55Z" />
	</svg>
	<?php foreach ( $seminars as $seminar ) : ?>
		<span class="taka-map-pin" style="left: <?php echo esc_attr( $seminar['map_x'] ); ?>%; top: <?php echo esc_attr( $seminar['map_y'] ); ?>%;" tabindex="0"><span><?php echo esc_html( $seminar['title'] ); ?></span></span>
	<?php endforeach; ?>
</div>
