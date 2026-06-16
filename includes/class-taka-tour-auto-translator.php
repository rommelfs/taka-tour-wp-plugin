<?php
/**
 * DeepL-ready lazy auto translation service.
 */

defined( 'ABSPATH' ) || exit;

class Taka_Tour_Auto_Translator {
	private const TARGET_CODES = array(
		'en' => 'EN',
		'nl' => 'NL',
		'fr' => 'FR',
		'fi' => 'FI',
	);

	public function is_supported( $target_lang ) {
		return isset( self::TARGET_CODES[ $target_lang ] );
	}

	public function translate_text( $text, $target_lang, $source_lang = 'DE' ) {
		if ( 'de' === $target_lang || 'lb' === $target_lang || ! $this->is_supported( $target_lang ) ) {
			return $text;
		}

		$cache_key = $this->get_cache_key( $text, $target_lang );
		$cached    = $this->get_cached_translation( $cache_key );
		if ( '' !== $cached ) {
			return $cached;
		}

		$api_key = apply_filters( 'taka_tour_deepl_api_key', get_option( 'taka_tour_deepl_api_key', '' ) );
		if ( '' === $api_key || ! function_exists( 'wp_remote_post' ) ) {
			return $text;
		}

		$response = wp_remote_post(
			'https://api-free.deepl.com/v2/translate',
			array(
				'timeout' => 8,
				'headers' => array( 'Authorization' => 'DeepL-Auth-Key ' . $api_key ),
				'body'    => array(
					'text'        => $text,
					'source_lang' => $source_lang,
					'target_lang' => self::TARGET_CODES[ $target_lang ],
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $text;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $body['translations'][0]['text'] ) || ! is_string( $body['translations'][0]['text'] ) ) {
			return $text;
		}

		$translated = sanitize_text_field( $body['translations'][0]['text'] );
		$this->set_cached_translation( $cache_key, $translated );
		return $translated;
	}

	public function get_cache_key( $text, $target_lang ) {
		return 'taka_tour_translation_' . sanitize_key( $target_lang ) . '_' . md5( $text );
	}

	public function get_cached_translation( $key ) {
		$value = get_option( $key, '' );
		return is_string( $value ) ? $value : '';
	}

	public function set_cached_translation( $key, $value ) {
		update_option( $key, $value, false );
	}
}
