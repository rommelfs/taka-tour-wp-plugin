<?php
/**
 * Adjacent label layout for the hero tour route map.
 *
 * @package TAKA_Platform
 */

defined( 'ABSPATH' ) || exit;

class TAKA_Platform_Tour_Map_Label_Layout {
	/**
	 * Compute compact marker-adjacent label geometry for map stations.
	 *
	 * @param array $stations Ordered station data with marker_x, marker_y and label.
	 * @param array $options  Optional layout tuning values.
	 * @return array
	 */
	public static function compute( $stations, $options = array() ) {
		$stations = array_values( is_array( $stations ) ? $stations : array() );
		if ( empty( $stations ) ) {
			return array();
		}

		$desktop = self::compute_mode( $stations, self::mode_options( 'desktop', $options ) );
		$mobile  = self::compute_mode( $stations, self::mode_options( 'mobile', $options ) );

		foreach ( $stations as $index => $station ) {
			$desktop_layout = $desktop[ $index ] ?? self::fallback_layout( $station, self::mode_options( 'desktop', $options ) );
			$mobile_layout  = $mobile[ $index ] ?? self::fallback_layout( $station, self::mode_options( 'mobile', $options ) );
			$label = (string) ( $desktop_layout['label'] ?? ( $station['display_label'] ?? ( $station['label'] ?? '' ) ) );

			$stations[ $index ]['label']               = $label;
			$stations[ $index ]['display_label']       = $label;
			$stations[ $index ]['label_x']             = $desktop_layout['x'];
			$stations[ $index ]['label_y']             = $desktop_layout['y'];
			$stations[ $index ]['label_anchor']        = $desktop_layout['anchor'];
			$stations[ $index ]['label_width']         = self::format_percent( $desktop_layout['width'] );
			$stations[ $index ]['label_height']        = $desktop_layout['height'];
			$stations[ $index ]['label_mobile_x']      = $mobile_layout['x'];
			$stations[ $index ]['label_mobile_y']      = $mobile_layout['y'];
			$stations[ $index ]['label_mobile_anchor'] = $mobile_layout['anchor'];
			$stations[ $index ]['label_mobile_width']  = self::format_percent( $mobile_layout['width'] );
			$stations[ $index ]['label_mobile_height'] = $mobile_layout['height'];
			$stations[ $index ]['label_placement']     = $desktop_layout['placement'];
			$stations[ $index ]['label_layout_source'] = $desktop_layout['source'];
			$stations[ $index ]['leader_line']         = false;
			$stations[ $index ]['leader_line_mobile']  = false;
		}

		return $stations;
	}

	private static function mode_options( $mode, $options ) {
		$defaults = array(
			'desktop' => array(
				'mode'                 => 'desktop',
				'map_width'            => 720.0,
				'map_height'           => 474.0,
				'safe_padding_x'       => 10.0,
				'safe_padding_y'       => 10.0,
				'label_height'         => 26.0,
				'label_padding_x'      => 12.0,
				'label_font_average'   => 7.1,
				'flag_width'           => 16.0,
				'marker_radius'        => 10.0,
				'label_gap'            => 8.0,
				'collision_margin'     => 4.0,
				'offset_steps'         => array( 1.0, 1.4, 1.9, 2.5 ),
			),
			'mobile'  => array(
				'mode'                 => 'mobile',
				'map_width'            => 360.0,
				'map_height'           => 496.0,
				'safe_padding_x'       => 6.0,
				'safe_padding_y'       => 8.0,
				'label_height'         => 22.0,
				'label_padding_x'      => 9.0,
				'label_font_average'   => 6.0,
				'flag_width'           => 13.0,
				'marker_radius'        => 8.0,
				'label_gap'            => 7.0,
				'collision_margin'     => 3.0,
				'offset_steps'         => array( 1.0, 1.35, 1.8, 2.35, 3.0 ),
			),
		);

		$mode_options = $defaults[ $mode ] ?? $defaults['desktop'];
		if ( isset( $options[ $mode ] ) && is_array( $options[ $mode ] ) ) {
			$mode_options = array_merge( $mode_options, $options[ $mode ] );
		}

		return $mode_options;
	}

	private static function compute_mode( $stations, $options ) {
		$placed = array();
		$layouts = array();
		$markers = self::marker_rects( $stations, $options );
		$cluster_state = array();

		foreach ( $stations as $index => $station ) {
			$layout = self::place_station( $station, $index, $stations, $markers, $placed, $cluster_state, $options, false );

			if ( null === $layout ) {
				$layout = self::place_station( $station, $index, $stations, $markers, $placed, $cluster_state, $options, true );
			}

			if ( null === $layout ) {
				$layout = self::nearest_fallback( $station, $index, $placed, $options );
			}

			$layouts[ $index ] = $layout;
			$placed[] = $layout['rect'];
			self::remember_cluster_choice( $station, $index, $stations, $cluster_state, $layout['placement'], $options );
		}

		ksort( $layouts );
		return $layouts;
	}

	private static function place_station( $station, $index, $stations, $markers, $placed, $cluster_state, $options, $use_short_label ) {
		$label = self::label_for_station( $station, $use_short_label );
		if ( '' === $label ) {
			return null;
		}

		$size = self::label_size( $label, $station, $options );
		foreach ( (array) $options['offset_steps'] as $offset_scale ) {
			foreach ( self::candidate_order( $station, $index, $stations, $cluster_state, $options ) as $placement ) {
				$candidate = self::candidate_rect( $station, $placement, $size, (float) $offset_scale, $options );
				if ( ! self::candidate_is_valid( $candidate, $index, $placed, $markers, $options ) ) {
					continue;
				}
				return array(
					'x'         => $candidate['x'],
					'y'         => $candidate['y'],
					'anchor'    => $candidate['anchor'],
					'width'     => $size['width_pct'],
					'height'    => $size['height_pct'],
					'placement' => $placement,
					'label'     => $label,
					'rect'      => $candidate['rect'],
					'source'    => $use_short_label ? 'adjacent_short_label' : 'adjacent_candidates',
				);
			}
		}

		return null;
	}

	private static function candidate_order( $station, $index, $stations, $cluster_state, $options ) {
		$x = self::coordinate( $station['marker_x'] ?? ( $station['x'] ?? 50 ), 50 );
		$base = $x < 28
			? array( 'right', 'bottom-right', 'top-right', 'below', 'above', 'left', 'bottom-left', 'top-left' )
			: ( $x > 72
				? array( 'left', 'bottom-left', 'top-left', 'below', 'above', 'right', 'bottom-right', 'top-right' )
				: array( 'right', 'left', 'top-right', 'top-left', 'bottom-right', 'bottom-left', 'above', 'below' ) );

		$cluster_key = self::cluster_key( $station, $index, $stations, $options );
		if ( '' !== $cluster_key && ! empty( $cluster_state[ $cluster_key ] ) ) {
			$used = $cluster_state[ $cluster_key ];
			$alternates = array( 'top-right', 'bottom-left', 'bottom-right', 'top-left', 'above', 'below', 'right', 'left' );
			if ( in_array( end( $used ), array( 'top-right', 'top-left', 'above' ), true ) ) {
				$alternates = array( 'bottom-left', 'bottom-right', 'below', 'right', 'left', 'top-right', 'top-left', 'above' );
			}
			$base = array_values( array_unique( array_merge( $alternates, $base ) ) );
		}

		return $base;
	}

	private static function candidate_rect( $station, $placement, $size, $offset_scale, $options ) {
		$marker_x = self::coordinate( $station['marker_x'] ?? ( $station['x'] ?? 50 ), 50 );
		$marker_y = self::coordinate( $station['marker_y'] ?? ( $station['y'] ?? 50 ), 50 );
		$gap_x = self::px_to_x_percent( ( (float) $options['marker_radius'] + (float) $options['label_gap'] ) * $offset_scale, $options );
		$gap_y = self::px_to_y_percent( ( (float) $options['marker_radius'] + (float) $options['label_gap'] ) * $offset_scale, $options );
		$diag_x = self::px_to_x_percent( ( (float) $options['marker_radius'] + (float) $options['label_gap'] * .8 ) * $offset_scale, $options );
		$diag_y = self::px_to_y_percent( ( (float) $options['marker_radius'] + (float) $options['label_gap'] * .8 ) * $offset_scale, $options );
		$width = $size['width_pct'];
		$height = $size['height_pct'];
		$anchor = 'left';
		$x = $marker_x + $gap_x;
		$y = $marker_y;

		switch ( $placement ) {
			case 'left':
				$anchor = 'right';
				$x = $marker_x - $gap_x;
				break;
			case 'top-right':
				$x = $marker_x + $diag_x;
				$y = $marker_y - $diag_y - $height / 2;
				break;
			case 'top-left':
				$anchor = 'right';
				$x = $marker_x - $diag_x;
				$y = $marker_y - $diag_y - $height / 2;
				break;
			case 'bottom-right':
				$x = $marker_x + $diag_x;
				$y = $marker_y + $diag_y + $height / 2;
				break;
			case 'bottom-left':
				$anchor = 'right';
				$x = $marker_x - $diag_x;
				$y = $marker_y + $diag_y + $height / 2;
				break;
			case 'above':
				$anchor = 'center';
				$x = $marker_x;
				$y = $marker_y - $gap_y - $height / 2;
				break;
			case 'below':
				$anchor = 'center';
				$x = $marker_x;
				$y = $marker_y + $gap_y + $height / 2;
				break;
		}

		$rect = self::rect_from_anchor( $x, $y, $width, $height, $anchor );
		return array( 'x' => $x, 'y' => $y, 'anchor' => $anchor, 'rect' => $rect );
	}

	private static function candidate_is_valid( $candidate, $station_index, $placed, $markers, $options ) {
		$rect = $candidate['rect'];
		$safe = array(
			'left'   => self::px_to_x_percent( (float) $options['safe_padding_x'], $options ),
			'right'  => 100 - self::px_to_x_percent( (float) $options['safe_padding_x'], $options ),
			'top'    => self::px_to_y_percent( (float) $options['safe_padding_y'], $options ),
			'bottom' => 100 - self::px_to_y_percent( (float) $options['safe_padding_y'], $options ),
		);

		if ( $rect['left'] < $safe['left'] || $rect['right'] > $safe['right'] || $rect['top'] < $safe['top'] || $rect['bottom'] > $safe['bottom'] ) {
			return false;
		}

		$margin = self::collision_margin_rect( $rect, $options );
		foreach ( $placed as $placed_rect ) {
			if ( self::rects_intersect( $margin, $placed_rect ) ) {
				return false;
			}
		}

		foreach ( $markers as $marker_index => $marker_rect ) {
			if ( (int) $marker_index === (int) $station_index ) {
				continue;
			}
			if ( self::rects_intersect( $margin, $marker_rect ) ) {
				return false;
			}
		}

		return true;
	}

	private static function nearest_fallback( $station, $index, $placed, $options ) {
		$label = self::label_for_station( $station, true );
		$size = self::label_size( $label, $station, $options );
		$best = null;
		foreach ( (array) $options['offset_steps'] as $offset_scale ) {
			foreach ( array( 'right', 'left', 'bottom-right', 'top-right', 'bottom-left', 'top-left', 'below', 'above' ) as $placement ) {
				$candidate = self::candidate_rect( $station, $placement, $size, (float) $offset_scale, $options );
				$score = self::fallback_score( $candidate['rect'], $placed, $options );
				if ( null === $best || $score < $best['score'] ) {
					$best = array( 'candidate' => $candidate, 'score' => $score, 'placement' => $placement );
				}
			}
		}

		$candidate = $best['candidate'];
		$rect = self::clamp_rect_to_bounds( $candidate['rect'], $options );
		$point = self::anchor_from_rect( $rect, $candidate['anchor'] );

		return array(
			'x'         => $point['x'],
			'y'         => $point['y'],
			'anchor'    => $candidate['anchor'],
			'width'     => $size['width_pct'],
			'height'    => $size['height_pct'],
			'placement' => $best['placement'],
			'label'     => $label,
			'rect'      => $rect,
			'source'    => 'adjacent_fallback',
		);
	}

	private static function fallback_score( $rect, $placed, $options ) {
		$score = 0.0;
		$safe_x = self::px_to_x_percent( (float) $options['safe_padding_x'], $options );
		$safe_y = self::px_to_y_percent( (float) $options['safe_padding_y'], $options );
		$score += max( 0, $safe_x - $rect['left'] ) * 100;
		$score += max( 0, $rect['right'] - ( 100 - $safe_x ) ) * 100;
		$score += max( 0, $safe_y - $rect['top'] ) * 100;
		$score += max( 0, $rect['bottom'] - ( 100 - $safe_y ) ) * 100;
		foreach ( $placed as $placed_rect ) {
			if ( self::rects_intersect( $rect, $placed_rect ) ) {
				$score += 1000 + self::intersection_area( $rect, $placed_rect );
			}
		}
		return $score;
	}

	private static function label_for_station( $station, $use_short_label ) {
		$label = trim( (string) ( $station['display_label'] ?? ( $station['label'] ?? '' ) ) );
		if ( ! $use_short_label ) {
			return $label;
		}

		$short = trim( (string) ( $station['short_label'] ?? '' ) );
		if ( '' !== $short && self::text_length( $short ) < self::text_length( $label ) ) {
			return $short;
		}

		if ( preg_match( '/^(.+?)(?:\\s+[\\-–—:]\\s+|\\s+\\()/u', $label, $matches ) ) {
			return trim( $matches[1] );
		}

		if ( self::text_length( $label ) > 18 && preg_match( '/^([^\\s]+)\\s+/u', $label, $matches ) ) {
			return trim( $matches[1] );
		}

		return $label;
	}

	private static function label_size( $label, $station, $options ) {
		$flag = trim( (string) ( $station['flag'] ?? ( is_array( $station['event'] ?? null ) ? ( $station['event']['hero_flag'] ?? '' ) : '' ) ) );
		$width_px = self::text_length( $label ) * (float) $options['label_font_average'] + (float) $options['label_padding_x'] * 2 + 8.0;
		if ( '' !== $flag ) {
			$width_px += (float) $options['flag_width'];
		}
		$width_px = max( $width_px, 34.0 );

		return array(
			'width_pct'  => self::px_to_x_percent( $width_px, $options ),
			'height_pct' => self::px_to_y_percent( (float) $options['label_height'], $options ),
		);
	}

	private static function marker_rects( $stations, $options ) {
		$rects = array();
		$radius_x = self::px_to_x_percent( (float) $options['marker_radius'], $options );
		$radius_y = self::px_to_y_percent( (float) $options['marker_radius'], $options );
		foreach ( $stations as $index => $station ) {
			$x = self::coordinate( $station['marker_x'] ?? ( $station['x'] ?? 50 ), 50 );
			$y = self::coordinate( $station['marker_y'] ?? ( $station['y'] ?? 50 ), 50 );
			$rects[ $index ] = array(
				'left'   => $x - $radius_x,
				'right'  => $x + $radius_x,
				'top'    => $y - $radius_y,
				'bottom' => $y + $radius_y,
			);
		}
		return $rects;
	}

	private static function cluster_key( $station, $index, $stations, $options ) {
		$x = self::coordinate( $station['marker_x'] ?? ( $station['x'] ?? 50 ), 50 );
		$y = self::coordinate( $station['marker_y'] ?? ( $station['y'] ?? 50 ), 50 );
		$threshold_x = self::px_to_x_percent( 72.0, $options );
		$threshold_y = self::px_to_y_percent( 58.0, $options );

		foreach ( $stations as $other_index => $other ) {
			if ( (int) $other_index >= (int) $index ) {
				continue;
			}
			$other_x = self::coordinate( $other['marker_x'] ?? ( $other['x'] ?? 50 ), 50 );
			$other_y = self::coordinate( $other['marker_y'] ?? ( $other['y'] ?? 50 ), 50 );
			if ( abs( $other_x - $x ) <= $threshold_x && abs( $other_y - $y ) <= $threshold_y ) {
				return round( ( $x + $other_x ) / 12 ) . ':' . round( ( $y + $other_y ) / 12 );
			}
		}

		return '';
	}

	private static function remember_cluster_choice( $station, $index, $stations, &$cluster_state, $placement, $options ) {
		$key = self::cluster_key( $station, $index, $stations, $options );
		if ( '' === $key ) {
			return;
		}
		if ( empty( $cluster_state[ $key ] ) ) {
			$cluster_state[ $key ] = array();
		}
		$cluster_state[ $key ][] = $placement;
	}

	private static function fallback_layout( $station, $options ) {
		$label = self::label_for_station( $station, false );
		$size = self::label_size( $label, $station, $options );
		$candidate = self::candidate_rect( $station, 'right', $size, 1.0, $options );
		return array(
			'x'         => $candidate['x'],
			'y'         => $candidate['y'],
			'anchor'    => $candidate['anchor'],
			'width'     => $size['width_pct'],
			'height'    => $size['height_pct'],
			'placement' => 'right',
			'label'     => $label,
			'rect'      => $candidate['rect'],
			'source'    => 'adjacent_default',
		);
	}

	private static function rect_from_anchor( $x, $y, $width, $height, $anchor ) {
		if ( 'right' === $anchor ) {
			$left = $x - $width;
			$right = $x;
		} elseif ( 'center' === $anchor ) {
			$left = $x - $width / 2;
			$right = $x + $width / 2;
		} else {
			$left = $x;
			$right = $x + $width;
		}

		return array(
			'left'   => $left,
			'right'  => $right,
			'top'    => $y - $height / 2,
			'bottom' => $y + $height / 2,
		);
	}

	private static function anchor_from_rect( $rect, $anchor ) {
		if ( 'right' === $anchor ) {
			$x = $rect['right'];
		} elseif ( 'center' === $anchor ) {
			$x = ( $rect['left'] + $rect['right'] ) / 2;
		} else {
			$x = $rect['left'];
		}
		return array( 'x' => $x, 'y' => ( $rect['top'] + $rect['bottom'] ) / 2 );
	}

	private static function clamp_rect_to_bounds( $rect, $options ) {
		$safe_x = self::px_to_x_percent( (float) $options['safe_padding_x'], $options );
		$safe_y = self::px_to_y_percent( (float) $options['safe_padding_y'], $options );
		$width = $rect['right'] - $rect['left'];
		$height = $rect['bottom'] - $rect['top'];
		$left = self::clamp( $rect['left'], $safe_x, 100 - $safe_x - $width );
		$top = self::clamp( $rect['top'], $safe_y, 100 - $safe_y - $height );
		return array( 'left' => $left, 'right' => $left + $width, 'top' => $top, 'bottom' => $top + $height );
	}

	private static function collision_margin_rect( $rect, $options ) {
		$margin_x = self::px_to_x_percent( (float) $options['collision_margin'], $options );
		$margin_y = self::px_to_y_percent( (float) $options['collision_margin'], $options );
		return array(
			'left'   => $rect['left'] - $margin_x,
			'right'  => $rect['right'] + $margin_x,
			'top'    => $rect['top'] - $margin_y,
			'bottom' => $rect['bottom'] + $margin_y,
		);
	}

	private static function rects_intersect( $a, $b ) {
		return $a['left'] < $b['right'] && $a['right'] > $b['left'] && $a['top'] < $b['bottom'] && $a['bottom'] > $b['top'];
	}

	private static function intersection_area( $a, $b ) {
		$width = max( 0, min( $a['right'], $b['right'] ) - max( $a['left'], $b['left'] ) );
		$height = max( 0, min( $a['bottom'], $b['bottom'] ) - max( $a['top'], $b['top'] ) );
		return $width * $height;
	}

	private static function text_length( $value ) {
		$value = trim( wp_strip_all_tags( (string) $value ) );
		if ( function_exists( 'mb_strlen' ) ) {
			return (int) mb_strlen( $value, 'UTF-8' );
		}
		return strlen( $value );
	}

	private static function coordinate( $value, $fallback ) {
		return self::clamp( is_numeric( $value ) ? (float) $value : (float) $fallback, 0.0, 100.0 );
	}

	private static function px_to_x_percent( $px, $options ) {
		return (float) $px / max( 1.0, (float) $options['map_width'] ) * 100;
	}

	private static function px_to_y_percent( $px, $options ) {
		return (float) $px / max( 1.0, (float) $options['map_height'] ) * 100;
	}

	private static function format_percent( $value ) {
		return rtrim( rtrim( number_format( (float) $value, 2, '.', '' ), '0' ), '.' ) . '%';
	}

	private static function clamp( $value, $min, $max ) {
		return max( $min, min( $max, (float) $value ) );
	}
}
