<?php
/**
 * Accessible ticket information modal.
 */

defined( 'ABSPATH' ) || exit;

$drawer_id = (string) ( $drawer_id ?? '' );
$title     = (string) ( $title ?? '' );
$rows      = is_array( $rows ?? null ) ? $rows : array();
$image     = (string) ( $image ?? '' );
$type      = sanitize_html_class( (string) ( $type ?? 'default' ) );

if ( '' === $drawer_id || empty( $rows ) ) {
	return;
}
?>
<div class="taka-info-modal taka-info-modal--<?php echo esc_attr( $type ); ?>" id="<?php echo esc_attr( $drawer_id ); ?>" role="dialog" aria-modal="true" aria-labelledby="<?php echo esc_attr( $drawer_id ); ?>-title" hidden>
	<div class="taka-info-modal__overlay" data-taka-info-modal-close></div>
	<section class="taka-info-modal__panel" tabindex="-1">
		<header class="taka-info-modal__header">
			<h3 class="taka-info-modal__title" id="<?php echo esc_attr( $drawer_id ); ?>-title"><?php echo esc_html( $title ); ?></h3>
			<button class="taka-info-modal__close" type="button" data-taka-info-modal-close aria-label="<?php echo esc_attr__( 'Close', 'taka-platform' ); ?>">×</button>
		</header>
		<div class="taka-info-modal__body">
			<?php if ( '' !== $image ) : ?>
				<div class="taka-info-modal__media"><img class="taka-info-modal__image" src="<?php echo esc_url( $image ); ?>" alt=""></div>
			<?php endif; ?>
			<dl class="taka-info-modal__list">
				<?php foreach ( $rows as $row ) : ?>
					<?php
					$label = (string) ( $row['label'] ?? '' );
					$value = (string) ( $row['value'] ?? '' );
					$url   = (string) ( $row['url'] ?? '' );
					if ( '' === trim( $label ) || '' === trim( $value ) ) {
						continue;
					}
					?>
					<div class="taka-info-modal__row">
						<dt><?php echo esc_html( $label ); ?></dt>
						<dd>
							<?php if ( '' !== $url ) : ?>
								<a href="<?php echo esc_url( $url ); ?>" rel="noopener noreferrer"><?php echo esc_html( $value ); ?></a>
							<?php else : ?>
								<?php echo esc_html( $value ); ?>
							<?php endif; ?>
						</dd>
					</div>
				<?php endforeach; ?>
			</dl>
		</div>
	</section>
</div>
