<?php defined( 'ABSPATH' ) || exit; ?>
<article class="taka-seminar-card" id="seminar-<?php echo esc_attr( sanitize_title( $seminar['title'] ) ); ?>">
	<div class="taka-card-meta"><span><?php echo esc_html( $seminar['flag'] ); ?></span><span><?php echo esc_html( $seminar['country'] ); ?></span><span><?php echo esc_html( $seminar['date'] ); ?></span></div>
	<h3><?php echo esc_html( $seminar['title'] ); ?></h3>
	<p class="taka-subtitle"><?php echo esc_html( $seminar['subtitle'] ); ?></p>
	<p><?php echo esc_html( $seminar['description'] ); ?></p>
	<dl class="taka-details"><div><dt><?php echo esc_html__( 'Format', 'taka-tour' ); ?></dt><dd><?php echo esc_html( $seminar['type'] ); ?></dd></div><div><dt><?php echo esc_html__( 'Gastgeber', 'taka-tour' ); ?></dt><dd><?php echo esc_html( $seminar['hosts'] ); ?></dd></div></dl>
	<?php if ( ! empty( $seminar['pretix_url'] ) ) : ?>
		<div class="taka-card-actions">
			<a class="taka-button" href="<?php echo esc_url( $seminar['pretix_url'] ); ?>"><?php echo esc_html__( 'Tickets kaufen', 'taka-tour' ); ?></a>
			<button class="taka-button taka-button-secondary" type="button" data-taka-toggle aria-expanded="false"><?php echo esc_html__( 'Tickets anzeigen', 'taka-tour' ); ?></button>
		</div>
		<div class="taka-pretix-panel" hidden>
			<?php echo taka_tour_render_template( 'partials/pretix-widget.php', array( 'url' => $seminar['pretix_url'], 'label' => $seminar['title'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php if ( ! empty( $seminar['kids_pretix_url'] ) ) : ?>
				<?php echo taka_tour_render_template( 'partials/pretix-widget.php', array( 'url' => $seminar['kids_pretix_url'], 'label' => __( 'Kindertraining', 'taka-tour' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php endif; ?>
		</div>
	<?php else : ?>
		<p class="taka-ticket-status"><?php echo esc_html( $seminar['ticket_status'] ); ?></p>
	<?php endif; ?>
</article>
