<?php
/**
 * JSON event export provider.
 */

defined( 'ABSPATH' ) || exit;

class TAKA_Platform_JSON_Provider implements TAKA_Platform_Event_Export_Provider_Interface {
	public function key() { return 'json'; }
	public function label() { return __( 'JSON export', 'taka-platform' ); }
	public function content_type() { return 'application/json; charset=utf-8'; }
	public function file_extension() { return 'json'; }
	public function export( $events ) {
		return wp_json_encode(
			array(
				'platform_version' => TAKA_PLATFORM_VERSION,
				'generated_at' => gmdate( 'c' ),
				'source' => 'taka_platform',
				'events' => array_values( $events ),
			),
			JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
		);
	}
}
