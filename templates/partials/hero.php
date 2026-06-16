<?php
/**
 * Homepage hero partial.
 */

defined( 'ABSPATH' ) || exit;

$images     = Taka_Tour_Data::images();
$hero_image = $images['hero_image'];
?>
<section class="taka-hero" style="--taka-hero-image: url('<?php echo esc_url( $hero_image ); ?>');">
	<div class="taka-hero-content">
		<p class="taka-kicker"><?php echo esc_html__( 'TAKA European Tour 2026', 'taka-tour' ); ?></p>
		<h1><?php echo esc_html__( 'Harmony in Motion', 'taka-tour' ); ?></h1>
		<p><?php echo esc_html__( 'Traditionelles Okinawa Shorin-Ryu Karate-Do, Kobujutsu und Soft Blocking mit Takafumi Nakayama Sensei.', 'taka-tour' ); ?></p>
		<p class="taka-hero-date"><?php echo esc_html__( '26.–27. September 2026 · Konz, Deutschland', 'taka-tour' ); ?></p>
		<div class="taka-card-actions"><a class="taka-button" href="#tour"><?php echo esc_html__( 'Tour ansehen', 'taka-tour' ); ?></a><a class="taka-button taka-button-secondary" href="#seminar-konz-trier"><?php echo esc_html__( 'Tickets', 'taka-tour' ); ?></a></div>
	</div>
</section>
