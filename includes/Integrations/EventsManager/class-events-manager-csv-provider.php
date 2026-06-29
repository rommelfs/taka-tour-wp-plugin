<?php
/**
 * Events Manager compatible CSV export provider.
 */

defined( 'ABSPATH' ) || exit;

class TAKA_Platform_Events_Manager_CSV_Provider extends TAKA_Platform_CSV_Provider {
	public function key() { return 'events_manager_csv'; }
	public function label() { return __( 'Events Manager compatible CSV', 'taka-platform' ); }

	public function export( $events ) {
		$columns = array(
			'event_name',
			'post_title',
			'post_content',
			'event_start_date',
			'event_start_time',
			'event_end_date',
			'event_end_time',
			'location_name',
			'location_address',
			'location_town',
			'location_country',
			'taka_ticket_mode',
			'taka_ticket_url',
			'taka_ticket_door_price',
			'taka_ticket_door_note',
			'taka_organizers',
			'_taka_platform_event_id',
			'external_id',
		);
		return self::csv_from_rows(
			$columns,
			$events,
			static function ( $event ) {
				return array(
					'event_name' => $event['title'] ?? '',
					'post_title' => $event['title'] ?? '',
					'post_content' => $event['description'] ?? '',
					'event_start_date' => $event['start_date'] ?? '',
					'event_start_time' => $event['start_time'] ?? '',
					'event_end_date' => $event['end_date'] ?? '',
					'event_end_time' => $event['end_time'] ?? '',
					'location_name' => $event['venue']['name'] ?? '',
					'location_address' => $event['venue']['address'] ?? '',
					'location_town' => $event['venue']['city'] ?? '',
					'location_country' => $event['venue']['country'] ?? '',
					'taka_ticket_mode' => $event['ticket_mode'] ?? '',
					'taka_ticket_url' => $event['ticket_url'] ?? '',
					'taka_ticket_door_price' => $event['ticket_door_price'] ?? '',
					'taka_ticket_door_note' => $event['ticket_door_note'] ?? '',
					'taka_organizers' => implode( ', ', array_filter( wp_list_pluck( $event['organizer_relationships'] ?? array(), 'name' ) ) ),
					'_taka_platform_event_id' => $event['taka_event_id'] ?? '',
					'external_id' => $event['external_id'] ?? '',
				);
			}
		);
	}
}
