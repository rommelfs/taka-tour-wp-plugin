<?php
/**
 * WordPress-backed repository for private People records.
 */

defined( 'ABSPATH' ) || exit;

class TAKA_People_Person_Repository {
	const PERSON_META = '_taka_person';
	const EMAIL_META = '_taka_person_email';
	const NAME_COUNTRY_META = '_taka_person_name_country';
	const SEARCH_META = '_taka_person_search';

	public function find_by_id( $person_id ) {
		$post = get_post( absint( $person_id ) );
		if ( ! $post || TAKA_PLATFORM_CPT_PERSON !== $post->post_type ) {
			return null;
		}
		return $this->person_from_post( $post );
	}

	public function find_matching( $person ) {
		$person = TAKA_People_Person::normalize( $person );
		if ( '' !== $person['email'] ) {
			$match = $this->find_by_email( $person['email'] );
			if ( $match ) {
				return $match;
			}
		}

		$key = TAKA_People_Person::name_country_key( $person );
		return '' !== $key ? $this->find_by_name_country_key( $key ) : null;
	}

	public function create_or_update_from_person_data( $person ) {
		$person = TAKA_People_Person::normalize( $person );
		$existing = $this->find_matching( $person );
		if ( $existing ) {
			$person = $this->merge_person_data( $existing, $person );
		}
		return $this->save( $person );
	}

	public function save( $person ) {
		$data = TAKA_People_Person::normalize( $person );
		if ( '' === TAKA_People_Person::full_name( $data ) && '' === $data['email'] ) {
			return new WP_Error( 'taka_person_missing_identity', __( 'A person needs at least a name or email address.', 'taka-platform' ) );
		}

		$now = current_time( 'mysql' );
		if ( '' === $data['created_at'] ) {
			$data['created_at'] = $now;
		}
		$data['updated_at'] = $now;

		$title = TAKA_People_Person::full_name( $data );
		if ( '' === $title ) {
			$title = $data['email'];
		}

		$post_data = array(
			'post_type'   => TAKA_PLATFORM_CPT_PERSON,
			'post_status' => 'private',
			'post_title'  => sanitize_text_field( $title ),
		);

		if ( ! empty( $data['id'] ) ) {
			$post_data['ID'] = absint( $data['id'] );
			$result = wp_update_post( $post_data, true );
			$person_id = absint( $data['id'] );
		} else {
			$result = wp_insert_post( $post_data, true );
			$person_id = is_wp_error( $result ) ? 0 : absint( $result );
		}

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$data['id'] = $person_id;
		update_post_meta( $person_id, self::PERSON_META, $data );
		update_post_meta( $person_id, self::EMAIL_META, strtolower( $data['email'] ) );
		update_post_meta( $person_id, self::NAME_COUNTRY_META, TAKA_People_Person::name_country_key( $data ) );
		update_post_meta( $person_id, self::SEARCH_META, $this->search_blob( $data ) );
		return $data;
	}

	public function query( $args = array() ) {
		$query = array(
			'post_type'        => TAKA_PLATFORM_CPT_PERSON,
			'post_status'      => 'private',
			'posts_per_page'   => ( isset( $args['per_page'] ) && -1 === (int) $args['per_page'] ) ? -1 : absint( $args['per_page'] ?? 100 ),
			'orderby'          => 'title',
			'order'            => 'ASC',
			'suppress_filters' => true,
		);

		$people = array_values( array_filter( array_map( array( $this, 'person_from_post' ), get_posts( $query ) ) ) );
		$search = strtolower( trim( sanitize_text_field( $args['search'] ?? '' ) ) );
		if ( '' === $search ) {
			return $people;
		}

		return array_values(
			array_filter(
				$people,
				function ( $person ) use ( $search ) {
					return false !== strpos( strtolower( $this->search_blob( $person ) ), $search );
				}
			)
		);
	}

	private function find_by_email( $email ) {
		$email = strtolower( sanitize_email( $email ) );
		if ( '' === $email ) {
			return null;
		}
		return $this->find_one_by_meta( self::EMAIL_META, $email );
	}

	private function find_by_name_country_key( $key ) {
		return '' !== $key ? $this->find_one_by_meta( self::NAME_COUNTRY_META, sanitize_key( $key ) ) : null;
	}

	private function find_one_by_meta( $meta_key, $meta_value ) {
		$posts = get_posts(
			array(
				'post_type'        => TAKA_PLATFORM_CPT_PERSON,
				'post_status'      => 'private',
				'posts_per_page'   => 1,
				'meta_key'         => $meta_key,
				'meta_value'       => $meta_value,
				'suppress_filters' => true,
			)
		);
		return empty( $posts ) ? null : $this->person_from_post( $posts[0] );
	}

	private function person_from_post( $post ) {
		if ( ! $post instanceof WP_Post ) {
			return null;
		}
		$data = get_post_meta( $post->ID, self::PERSON_META, true );
		$data = is_array( $data ) ? $data : array();
		$data['id'] = absint( $post->ID );
		return TAKA_People_Person::normalize( $data );
	}

	private function merge_person_data( $existing, $incoming ) {
		$merged = TAKA_People_Person::normalize( $existing );
		$incoming = TAKA_People_Person::normalize( $incoming );
		$merged['id'] = absint( $existing['id'] ?? 0 );
		$merged['created_at'] = (string) ( $existing['created_at'] ?? '' );

		foreach ( array( 'first_name', 'last_name', 'email', 'phone', 'country', 'dojo', 'association', 'style', 'rank', 'dietary_preference', 'allergies' ) as $field ) {
			$value = $incoming[ $field ] ?? '';
			if ( '' !== trim( (string) $value ) && 'none' !== (string) $value ) {
				$merged[ $field ] = $value;
			}
		}

		$merged['tags'] = TAKA_People_Person::normalize_tags( array_merge( (array) ( $merged['tags'] ?? array() ), (array) ( $incoming['tags'] ?? array() ) ) );
		if ( '1' === (string) ( $incoming['gdpr_consent'] ?? '0' ) ) {
			$merged['gdpr_consent'] = '1';
		}
		if ( '1' === (string) ( $incoming['newsletter_consent'] ?? '0' ) ) {
			$merged['newsletter_consent'] = '1';
		}

		return $merged;
	}

	private function search_blob( $person ) {
		$person = TAKA_People_Person::normalize( $person );
		return implode(
			' ',
			array_filter(
				array(
					TAKA_People_Person::full_name( $person ),
					$person['email'],
					$person['phone'],
					$person['country'],
					class_exists( 'TAKA_Platform_Data' ) ? TAKA_Platform_Data::country_label( $person['country'], TAKA_Platform_Data::platform_fallback_language() ) : '',
					$person['dojo'],
					$person['association'],
					$person['style'],
					$person['rank'],
					implode( ' ', (array) $person['tags'] ),
				)
			)
		);
	}
}
