<?php
/**
 * Internal translation layer for TAKA Tour content.
 */

defined( 'ABSPATH' ) || exit;

class Taka_Tour_Translator {
	private static $auto_translator = null;

	public static function languages() {
		return array( 'de', 'en', 'nl', 'fr', 'lb', 'fi' );
	}

	public static function language_labels() {
		return array(
			'de' => 'DE',
			'en' => 'EN',
			'nl' => 'NL',
			'fr' => 'FR',
			'lb' => 'LB',
			'fi' => 'FI',
		);
	}

	public static function current_language() {
		if ( isset( $_GET['taka_lang'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$lang = sanitize_key( wp_unslash( $_GET['taka_lang'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( in_array( $lang, self::languages(), true ) ) {
				return $lang;
			}
		}

		$accepted = isset( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) ) : '';
		if ( '' !== $accepted ) {
			foreach ( explode( ',', $accepted ) as $part ) {
				$lang = strtolower( substr( trim( $part ), 0, 2 ) );
				if ( in_array( $lang, self::languages(), true ) ) {
					return $lang;
				}
			}
		}

		return 'de';
	}

	public static function translate( $key, $fallback, $lang = null ) {
		$lang = $lang ?: self::current_language();
		if ( 'de' === $lang ) {
			return $fallback;
		}

		$manual = Taka_Tour_Data::manual_translations();
		if ( isset( $manual[ $lang ][ $key ] ) && '' !== $manual[ $lang ][ $key ] ) {
			return $manual[ $lang ][ $key ];
		}

		if ( 'lb' === $lang ) {
			return $fallback;
		}

		if ( null === self::$auto_translator ) {
			self::$auto_translator = new Taka_Tour_Auto_Translator();
		}

		return self::$auto_translator->translate_text( $fallback, $lang );
	}

	public static function languages_for_country( $country ) {
		$map = array(
			'Finland'     => array( 'fi', 'en', 'de' ),
			'Germany'     => array( 'de', 'en' ),
			'Netherlands' => array( 'nl', 'en', 'de' ),
			'Belgium'     => array( 'fr', 'nl', 'de', 'en' ),
			'Luxembourg'  => array( 'fr', 'de', 'lb', 'en' ),
		);

		return $map[ $country ] ?? array( 'en' );
	}
}
