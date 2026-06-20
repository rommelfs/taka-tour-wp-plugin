<?php
/**
 * Event export provider contract.
 */

defined( 'ABSPATH' ) || exit;

interface TAKA_Platform_Event_Export_Provider_Interface {
	public function key();
	public function label();
	public function content_type();
	public function file_extension();
	public function export( $events );
}
