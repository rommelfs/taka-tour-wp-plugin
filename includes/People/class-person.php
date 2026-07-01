<?php
/**
 * Central person normalization for the private TAKA People module.
 *
 * People are durable community records. Orders and registrations should
 * reference people instead of keeping participant data only inside orders.
 */

defined( 'ABSPATH' ) || exit;

class TAKA_People_Person {
	public static function dietary_choices() {
		return array( 'none', 'vegetarian', 'vegan', 'other' );
	}

	public static function normalize( $data ) {
		$data = is_array( $data ) ? $data : array();
		$dietary = sanitize_key( $data['dietary_preference'] ?? 'none' );
		if ( ! in_array( $dietary, self::dietary_choices(), true ) ) {
			$dietary = 'none';
		}

		return array(
			'id'                 => absint( $data['id'] ?? 0 ),
			'first_name'         => sanitize_text_field( $data['first_name'] ?? '' ),
			'last_name'          => sanitize_text_field( $data['last_name'] ?? '' ),
			'email'              => sanitize_email( $data['email'] ?? '' ),
			'phone'              => sanitize_text_field( $data['phone'] ?? '' ),
			'country'            => self::normalize_country( $data['country'] ?? '' ),
			'dojo'               => sanitize_text_field( $data['dojo'] ?? '' ),
			'association'        => sanitize_text_field( $data['association'] ?? '' ),
			'style'              => sanitize_text_field( $data['style'] ?? '' ),
			'rank'               => sanitize_text_field( $data['rank'] ?? '' ),
			'dietary_preference' => $dietary,
			'allergies'          => sanitize_textarea_field( $data['allergies'] ?? '' ),
			'notes'              => sanitize_textarea_field( $data['notes'] ?? '' ),
			'tags'               => self::normalize_tags( $data['tags'] ?? array() ),
			'relationships'      => self::normalize_relationships( $data['relationships'] ?? array() ),
			'birth_date'         => self::normalize_date( $data['birth_date'] ?? '' ),
			'gdpr_consent'       => ! empty( $data['gdpr_consent'] ) ? '1' : '0',
			'newsletter_consent' => ! empty( $data['newsletter_consent'] ) ? '1' : '0',
			'created_at'         => sanitize_text_field( $data['created_at'] ?? '' ),
			'updated_at'         => sanitize_text_field( $data['updated_at'] ?? '' ),
		);
	}

	public static function from_buyer( $buyer ) {
		$buyer = is_array( $buyer ) ? $buyer : array();
		return self::normalize(
			array(
				'first_name' => $buyer['first_name'] ?? '',
				'last_name'  => $buyer['last_name'] ?? '',
				'email'      => $buyer['email'] ?? '',
				'phone'      => $buyer['phone'] ?? '',
				'country'    => $buyer['country'] ?? '',
			)
		);
	}

	public static function from_participant( $participant ) {
		$participant = is_array( $participant ) ? $participant : array();
		return self::normalize(
			array(
				'first_name'         => $participant['first_name'] ?? '',
				'last_name'          => $participant['last_name'] ?? '',
				'email'              => $participant['email'] ?? '',
				'country'            => $participant['country'] ?? '',
				'dojo'               => $participant['dojo'] ?? '',
				'association'        => $participant['association'] ?? '',
				'style'              => $participant['style'] ?? '',
				'rank'               => $participant['rank'] ?? '',
				'dietary_preference' => $participant['dietary_preference'] ?? 'none',
				'allergies'          => $participant['allergies'] ?? '',
				'notes'              => $participant['notes'] ?? '',
			)
		);
	}

	public static function full_name( $person ) {
		$person = is_array( $person ) ? $person : array();
		return trim( (string) ( $person['first_name'] ?? '' ) . ' ' . (string) ( $person['last_name'] ?? '' ) );
	}

	public static function name_country_key( $person ) {
		$person = self::normalize( $person );
		$name = strtolower( remove_accents( self::full_name( $person ) ) );
		$name = preg_replace( '/\s+/', ' ', trim( $name ) );
		if ( '' === $name || '' === (string) $person['country'] ) {
			return '';
		}
		return sanitize_key( $name . '|' . $person['country'] );
	}

	public static function normalize_tags( $tags ) {
		if ( ! is_array( $tags ) ) {
			$tags = preg_split( '/\s*,\s*/', (string) $tags );
		}
		$tags = array_map( 'sanitize_text_field', (array) $tags );
		$tags = array_filter( array_map( 'trim', $tags ) );
		return array_values( array_unique( $tags ) );
	}

	public static function normalize_relationships( $relationships ) {
		$out = array();
		foreach ( (array) $relationships as $relationship ) {
			if ( ! is_array( $relationship ) ) {
				continue;
			}
			$type = sanitize_key( $relationship['type'] ?? '' );
			$label = sanitize_text_field( $relationship['label'] ?? '' );
			$notes = sanitize_text_field( $relationship['notes'] ?? '' );
			if ( '' === $type && '' === $label && '' === $notes ) {
				continue;
			}
			$out[] = array(
				'type'              => '' !== $type ? $type : 'other',
				'label'             => $label,
				'related_person_id' => absint( $relationship['related_person_id'] ?? 0 ),
				'related_object_id' => absint( $relationship['related_object_id'] ?? 0 ),
				'notes'             => $notes,
			);
		}
		return $out;
	}

	private static function normalize_date( $date ) {
		$date = sanitize_text_field( $date );
		return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ? $date : '';
	}

	private static function normalize_country( $country ) {
		$country = sanitize_text_field( $country );
		return class_exists( 'TAKA_Platform_Data' ) ? TAKA_Platform_Data::normalize_event_option_value( 'country', $country ) : $country;
	}
}
