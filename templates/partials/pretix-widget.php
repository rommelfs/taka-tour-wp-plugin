<?php
/**
 * Pretix widget partial.
 */

defined( 'ABSPATH' ) || exit;

$event_url = ! empty( $event ) ? $event : ( $url ?? '' );
?>
<div class="taka-pretix-widget">
	<p class="taka-widget-label"><?php echo esc_html( $label ); ?></p>
	<pretix-widget event="<?php echo esc_url( $event_url ); ?>"></pretix-widget>
	<noscript><a href="<?php echo esc_url( $event_url ); ?>" rel="noopener"><?php echo esc_html__( 'Tickets bei Pretix öffnen', 'taka-tour' ); ?></a></noscript>
</div>
