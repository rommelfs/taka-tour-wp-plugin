<?php
/**
 * Helper functions for TAKA Tour Website Builder.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Render a template with scoped variables.
 *
 * @param string $template Template file relative to templates directory.
 * @param array  $args     Template variables.
 * @return string
 */
function taka_tour_render_template( $template, $args = array() ) {
	$template_path = TAKA_TOUR_PLUGIN_DIR . 'templates/' . ltrim( $template, '/' );

	if ( ! file_exists( $template_path ) ) {
		return '';
	}

	ob_start();
	extract( $args, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
	include $template_path;
	return ob_get_clean();
}

/**
 * Return allowed HTML for rich template text.
 *
 * @return array
 */
function taka_tour_allowed_html() {
	return array(
		'a'      => array(
			'href'   => array(),
			'target' => array(),
			'rel'    => array(),
			'class'  => array(),
		),
		'br'     => array(),
		'em'     => array(),
		'strong' => array(),
		'span'          => array( 'class' => array() ),
		'pretix-widget' => array( 'event' => array() ),
	);

}
