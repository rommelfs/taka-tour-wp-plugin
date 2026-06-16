<?php
/**
 * Plugin bootstrap and shortcode registration.
 */

defined( 'ABSPATH' ) || exit;

class Taka_Tour_Plugin {
	private static $instance = null;
	private $renderer;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->renderer = new Taka_Tour_Renderer();
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
		add_shortcode( 'taka_homepage', array( $this->renderer, 'homepage' ) );
		add_shortcode( 'taka_tour_schedule', array( $this->renderer, 'tour_schedule' ) );
		add_shortcode( 'taka_tickets', array( $this->renderer, 'tickets' ) );
		add_shortcode( 'taka_sponsor', array( $this->renderer, 'sponsor' ) );
	}

	public function register_assets() {
		wp_register_style( 'taka-tour', TAKA_TOUR_PLUGIN_URL . 'assets/css/taka-tour.css', array(), TAKA_TOUR_VERSION );
		wp_register_script( 'taka-tour', TAKA_TOUR_PLUGIN_URL . 'assets/js/taka-tour.js', array(), TAKA_TOUR_VERSION, true );
		wp_register_style( 'taka-tour-pretix', 'https://pretix.eu/kleinerwald/2026takakonz/widget/v2.css', array(), TAKA_TOUR_VERSION );
		wp_register_script( 'taka-tour-pretix', 'https://pretix.eu/widget/v2.de.js', array(), TAKA_TOUR_VERSION, true );
	}
}
