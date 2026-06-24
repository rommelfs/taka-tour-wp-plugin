<?php
/**
 * Automatic label layout for the hero tour route map.
 *
 * @package TAKA_Platform
 */

defined( 'ABSPATH' ) || exit;

class TAKA_Platform_Tour_Map_Label_Layout {
	/**
	 * Compute desktop and mobile label geometry for map stations.
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
			$desktop_layout = $desktop[ $index ] ?? array();
			$mobile_layout  = $mobile[ $index ] ?? array();

			$stations[ $index ]['label_x']             = $desktop_layout['x'] ?? (float) ( $station['marker_x'] ?? 50 );
			$stations[ $index ]['label_y']             = $desktop_layout['y'] ?? (float) ( $station['marker_y'] ?? 50 );
			$stations[ $index ]['label_anchor']        = $desktop_layout['anchor'] ?? 'left';
			$stations[ $index ]['label_width']         = self::format_percent( $desktop_layout['width'] ?? 22 );
			$stations[ $index ]['label_height']        = $desktop_layout['height'] ?? null;
			$stations[ $index ]['label_mobile_x']      = $mobile_layout['x'] ?? $stations[ $index ]['label_x'];
			$stations[ $index ]['label_mobile_y']      = $mobile_layout['y'] ?? $stations[ $index ]['label_y'];
			$stations[ $index ]['label_mobile_anchor'] = $mobile_layout['anchor'] ?? $stations[ $index ]['label_anchor'];
			$stations[ $index ]['label_mobile_width']  = self::format_percent( $mobile_layout['width'] ?? 36 );
			$stations[ $index ]['label_mobile_height'] = $mobile_layout['height'] ?? null;
			$stations[ $index ]['label_layout_side']   = $desktop_layout['side'] ?? '';
			$stations[ $index ]['label_layout_source'] = $desktop_layout['source'] ?? 'auto_columns';
			$stations[ $index ]['leader_line']         = ! empty( $desktop_layout['leader_line'] );
			$stations[ $index ]['leader_line_mobile']  = ! empty( $mobile_layout['leader_line'] );

			foreach ( array( 'leader_x1', 'leader_y1', 'leader_x2', 'leader_y2' ) as $key ) {
				if ( isset( $desktop_layout[ $key ] ) ) {
					$stations[ $index ][ $key ] = $desktop_layout[ $key ];
				}
				$mobile_key = str_replace( 'leader_', 'leader_mobile_', $key );
				if ( isset( $mobile_layout[ $key ] ) ) {
					$stations[ $index ][ $mobile_key ] = $mobile_layout[ $key ];
				}
			}
		}

		return $stations;
	}

	private static function mode_options( $mode, $options ) {
		$defaults = array(
			'desktop' => array(
				'mode'                   => 'desktop',
				'safe_x'                 => 4.5,
				'safe_y'                 => 7.5,
				'label_width'            => 22.5,
				'min_label_width'        => 18.5,
				'marker_gap_x'           => 5.0,
				'label_gap_y'            => 2.4,
				'center_band'            => 4.5,
				'marker_clearance_y'     => 2.8,
				'label_height_base'      => 7.2,
				'label_height_line'      => 3.9,
				'chars_per_width_pct'    => 1.02,
				'leader_min_distance'    => 13.5,
				'leader_min_horizontal'  => 7.5,
				'leader_marker_padding'  => 1.4,
				'max_leaders'            => 4,
				'restrict_width_to_route' => true,
			),
			'mobile'  => array(
				'mode'                   => 'mobile',
				'safe_x'                 => 4.0,
				'safe_y'                 => 5.5,
				'label_width'            => 36.0,
				'min_label_width'        => 22.0,
				'marker_gap_x'           => 2.5,
				'label_gap_y'            => 2.0,
				'center_band'            => 5.5,
				'marker_clearance_y'     => 2.6,
				'label_height_base'      => 5.6,
				'label_height_line'      => 2.9,
				'chars_per_width_pct'    => 0.56,
				'leader_min_distance'    => 16.0,
				'leader_min_horizontal'  => 10.0,
				'leader_marker_padding'  => 1.6,
				'max_leaders'            => 2,
				'restrict_width_to_route' => false,
			),
		);

		$mode_options = $defaults[ $mode ] ?? $defaults['desktop'];
		if ( isset( $options[ $mode ] ) && is_array( $options[ $mode ] ) ) {
			$mode_options = array_merge( $mode_options, $options[ $mode ] );
		}

		foreach ( $mode_options as $key => $value ) {
			if ( is_numeric( $value ) ) {
				$mode_options[ $key ] = (float) $value;
			}
		}

		return $mode_options;
	}

	private static function compute_mode( $stations, $options ) {
		$markers = self::marker_bounds( $stations );
		$center_x = ( $markers['min_x'] + $markers['max_x'] ) / 2;
		if ( $markers['max_x'] - $markers['min_x'] < 12 ) {
			$center_x = 50.0;
		}

		$widths = self::column_widths( $markers, $options );
		$items = self::build_items( $stations, $center_x, $widths, $options );
		$items = self::assign_sides( $items, $center_x, $options );

		$by_side = array( 'left' => array(), 'right' => array() );
		foreach ( $items as $item ) {
			$by_side[ $item['side'] ][] = $item;
		}

		$layout = array();
		foreach ( $by_side as $side => $side_items ) {
			foreach ( self::layout_side( $side_items, $stations, $options ) as $item ) {
				$layout[ $item['index'] ] = $item;
			}
		}

		return self::apply_leader_lines( $layout, $stations, $options );
	}

	private static function marker_bounds( $stations ) {
		$xs = array();
		$ys = array();
		foreach ( $stations as $station ) {
			$xs[] = self::coordinate( $station['marker_x'] ?? ( $station['x'] ?? 50 ), 50 );
			$ys[] = self::coordinate( $station['marker_y'] ?? ( $station['y'] ?? 50 ), 50 );
		}

		return array(
			'min_x' => min( $xs ),
			'max_x' => max( $xs ),
			'min_y' => min( $ys ),
			'max_y' => max( $ys ),
		);
	}

	private static function column_widths( $markers, $options ) {
		$desired = (float) $options['label_width'];
		$min     = (float) $options['min_label_width'];
		$safe_x  = (float) $options['safe_x'];

		$left = $desired;
		$right = $desired;
		if ( ! empty( $options['restrict_width_to_route'] ) ) {
			$left_available  = max( $min, $markers['min_x'] - (float) $options['marker_gap_x'] - $safe_x );
			$right_available = max( $min, 100 - $safe_x - $markers['max_x'] - (float) $options['marker_gap_x'] );
			$left = min( $desired, $left_available );
			$right = min( $desired, $right_available );
		}

		return array(
			'left'  => max( $min, min( 44.0, $left ) ),
			'right' => max( $min, min( 44.0, $right ) ),
		);
	}

	private static function build_items( $stations, $center_x, $widths, $options ) {
		$items = array();
		foreach ( $stations as $index => $station ) {
			$marker_x = self::coordinate( $station['marker_x'] ?? ( $station['x'] ?? 50 ), 50 );
			$marker_y = self::coordinate( $station['marker_y'] ?? ( $station['y'] ?? 50 ), 50 );
			$manual_x = self::nullable_coordinate( $station['label_manual_x'] ?? null );
			$manual_y = self::nullable_coordinate( $station['label_manual_y'] ?? null );
			$manual_anchor = self::label_anchor( $station['label_manual_anchor'] ?? '' );
			$manual_width = self::width_percent( $station['label_manual_width'] ?? null );
			$side = '';

			if ( null !== $manual_x && null !== $manual_y ) {
				$side = $manual_x < $center_x ? 'left' : 'right';
			}

			$items[] = array(
				'index'                  => $index,
				'marker_x'               => $marker_x,
				'marker_y'               => $marker_y,
				'label'                  => (string) ( $station['display_label'] ?? ( $station['label'] ?? '' ) ),
				'side'                   => $side,
				'manual_x'               => $manual_x,
				'manual_y'               => $manual_y,
				'manual_anchor'          => $manual_anchor,
				'manual_width'           => $manual_width,
				'leader_line_preference' => array_key_exists( 'leader_line_preference', $station ) ? $station['leader_line_preference'] : null,
				'widths'                 => $widths,
			);
		}

		return $items;
	}

	private static function assign_sides( $items, $center_x, $options ) {
		$hard = array();
		$center = array();
		$counts = array( 'left' => 0, 'right' => 0 );
		$band = (float) $options['center_band'];

		foreach ( $items as $item ) {
			if ( '' !== $item['side'] ) {
				$counts[ $item['side'] ]++;
				$hard[] = $item;
				continue;
			}

			if ( $item['marker_x'] < $center_x - $band ) {
				$item['side'] = 'left';
			} elseif ( $item['marker_x'] > $center_x + $band ) {
				$item['side'] = 'right';
			}

			if ( '' === $item['side'] ) {
				$center[] = $item;
			} else {
				$item['side'] = self::side_with_safe_capacity( $item, $item['side'], $options );
				$counts[ $item['side'] ]++;
				$hard[] = $item;
			}
		}

		usort(
			$center,
			static function ( $a, $b ) {
				return $a['marker_y'] <=> $b['marker_y'];
			}
		);

		foreach ( $center as $item ) {
			if ( $counts['left'] === $counts['right'] ) {
				$left_space = $item['marker_x'] - (float) $options['safe_x'];
				$right_space = 100 - (float) $options['safe_x'] - $item['marker_x'];
				$item['side'] = $right_space >= $left_space ? 'right' : 'left';
			} else {
				$item['side'] = $counts['left'] < $counts['right'] ? 'left' : 'right';
			}
			$item['side'] = self::side_with_safe_capacity( $item, $item['side'], $options );
			$counts[ $item['side'] ]++;
			$hard[] = $item;
		}

		return $hard;
	}

	private static function side_with_safe_capacity( $item, $side, $options ) {
		if ( self::side_capacity( $item, $side, $options ) >= (float) $options['min_label_width'] ) {
			return $side;
		}

		$opposite = 'right' === $side ? 'left' : 'right';
		return self::side_capacity( $item, $opposite, $options ) > self::side_capacity( $item, $side, $options ) ? $opposite : $side;
	}

	private static function side_capacity( $item, $side, $options ) {
		$safe_x = (float) $options['safe_x'];
		$gap = (float) $options['marker_gap_x'];
		if ( 'right' === $side ) {
			return 100 - $safe_x - (float) $item['marker_x'] - $gap;
		}
		return (float) $item['marker_x'] - $safe_x - $gap;
	}

	private static function layout_side( $items, $all_stations, $options ) {
		if ( empty( $items ) ) {
			return array();
		}

		usort(
			$items,
			static function ( $a, $b ) {
				if ( $a['marker_y'] === $b['marker_y'] ) {
					return $a['index'] <=> $b['index'];
				}
				return $a['marker_y'] <=> $b['marker_y'];
			}
		);

		foreach ( $items as &$item ) {
			$side = $item['side'];
			$manual_layout = null !== $item['manual_x'] && null !== $item['manual_y'];
			$item['anchor'] = $manual_layout && '' !== $item['manual_anchor'] ? $item['manual_anchor'] : ( 'right' === $side ? 'right' : 'left' );
			$item['width'] = $item['manual_width'] ?: (float) $item['widths'][ $side ];
			$item['x'] = null !== $item['manual_x'] ? $item['manual_x'] : ( 'right' === $side ? 100 - (float) $options['safe_x'] : (float) $options['safe_x'] );
			$item['height'] = self::estimate_height( $item['label'], $item['width'], $options );
			$item['y'] = null !== $item['manual_y'] ? $item['manual_y'] : self::desired_y( $item, $all_stations, $options );
			$item['source'] = $manual_layout ? 'manual' : 'auto_columns';
		}
		unset( $item );

		return self::fit_vertical_slots( $items, $options, $all_stations );
	}

	private static function desired_y( $item, $all_stations, $options ) {
		$top = (float) $options['safe_y'];
		$bottom = 100 - (float) $options['safe_y'];
		$half = $item['height'] / 2;
		$y = $item['marker_y'];

		if ( self::rect_contains_marker_x( self::item_rect( $item, $y ), $item['marker_x'] ) ) {
			$clearance = $half + (float) $options['marker_clearance_y'];
			$down = min( $bottom - $half, $item['marker_y'] + $clearance );
			$up = max( $top + $half, $item['marker_y'] - $clearance );
			$y = $item['marker_y'] < 50 || ( $bottom - $down ) > ( $up - $top ) ? $down : $up;
		}

		foreach ( $all_stations as $station ) {
			$marker_x = self::coordinate( $station['marker_x'] ?? ( $station['x'] ?? 50 ), 50 );
			$marker_y = self::coordinate( $station['marker_y'] ?? ( $station['y'] ?? 50 ), 50 );
			if ( ! self::rect_contains_marker_x( self::item_rect( $item, $y ), $marker_x ) ) {
				continue;
			}
			if ( abs( $marker_y - $y ) < $half + (float) $options['marker_clearance_y'] ) {
				$y += $marker_y <= $y ? $half + (float) $options['marker_clearance_y'] : -1 * ( $half + (float) $options['marker_clearance_y'] );
			}
		}

		return self::clamp( $y, $top + $half, $bottom - $half );
	}

	private static function fit_vertical_slots( $items, $options, $all_stations ) {
		$count = count( $items );
		$top = (float) $options['safe_y'];
		$bottom = 100 - (float) $options['safe_y'];
		$gap = (float) $options['label_gap_y'];
		$total_height = array_reduce(
			$items,
			static function ( $carry, $item ) {
				return $carry + (float) $item['height'];
			},
			0.0
		);
		if ( $count > 1 && $total_height + $gap * ( $count - 1 ) > $bottom - $top ) {
			$gap = max( 0.75, ( ( $bottom - $top ) - $total_height ) / ( $count - 1 ) );
		}

		for ( $i = 0; $i < $count; $i++ ) {
			$half = $items[ $i ]['height'] / 2;
			$items[ $i ]['y'] = self::clamp( $items[ $i ]['y'], $top + $half, $bottom - $half );
			if ( 0 < $i ) {
				$previous_gap = $items[ $i - 1 ]['height'] / 2 + $gap + $half;
				$items[ $i ]['y'] = max( $items[ $i ]['y'], $items[ $i - 1 ]['y'] + $previous_gap );
			}
		}

		for ( $i = $count - 1; $i >= 0; $i-- ) {
			$half = $items[ $i ]['height'] / 2;
			$items[ $i ]['y'] = min( $items[ $i ]['y'], $bottom - $half );
			if ( $i < $count - 1 ) {
				$next_gap = $items[ $i + 1 ]['height'] / 2 + $gap + $half;
				$items[ $i ]['y'] = min( $items[ $i ]['y'], $items[ $i + 1 ]['y'] - $next_gap );
			}
		}

		for ( $i = 0; $i < $count; $i++ ) {
			$half = $items[ $i ]['height'] / 2;
			$items[ $i ]['y'] = max( $items[ $i ]['y'], $top + $half );
			if ( 0 < $i ) {
				$previous_gap = $items[ $i - 1 ]['height'] / 2 + $gap + $half;
				$items[ $i ]['y'] = max( $items[ $i ]['y'], $items[ $i - 1 ]['y'] + $previous_gap );
			}
		}

		return self::clear_marker_collisions( $items, $options, $gap, $all_stations );
	}

	private static function clear_marker_collisions( $items, $options, $gap, $all_stations ) {
		$count = count( $items );

		for ( $i = 0; $i < $count; $i++ ) {
			$collision = self::colliding_marker_for_item( $items[ $i ], $all_stations );
			if ( null === $collision ) {
				continue;
			}

			$half = $items[ $i ]['height'] / 2;
			$clearance = $half + (float) $options['marker_clearance_y'];
			$candidates = array(
				array( 'y' => $collision['marker_y'] - $clearance, 'direction' => 'up' ),
				array( 'y' => $collision['marker_y'] + $clearance, 'direction' => 'down' ),
			);
			usort(
				$candidates,
				static function ( $a, $b ) use ( $items, $i ) {
					return abs( $a['y'] - $items[ $i ]['y'] ) <=> abs( $b['y'] - $items[ $i ]['y'] );
				}
			);

			foreach ( $candidates as $candidate ) {
				$trial = self::place_collision_candidate( $items, $i, $candidate['y'], $candidate['direction'], $options, $gap );
				if ( null !== $trial ) {
					$items = $trial;
					break;
				}
			}
		}

		return $items;
	}

	private static function place_collision_candidate( $items, $index, $candidate_y, $direction, $options, $gap ) {
		$count = count( $items );
		$top = (float) $options['safe_y'];
		$bottom = 100 - (float) $options['safe_y'];
		$half = $items[ $index ]['height'] / 2;
		$items[ $index ]['y'] = self::clamp( $candidate_y, $top + $half, $bottom - $half );

		if ( 'up' === $direction ) {
			for ( $i = $index - 1; $i >= 0; $i-- ) {
				$current_half = $items[ $i ]['height'] / 2;
				$next_half = $items[ $i + 1 ]['height'] / 2;
				$max_y = $items[ $i + 1 ]['y'] - $next_half - $gap - $current_half;
				$items[ $i ]['y'] = min( $items[ $i ]['y'], $max_y );
				if ( $items[ $i ]['y'] < $top + $current_half ) {
					return null;
				}
			}
		} else {
			for ( $i = $index + 1; $i < $count; $i++ ) {
				$current_half = $items[ $i ]['height'] / 2;
				$previous_half = $items[ $i - 1 ]['height'] / 2;
				$min_y = $items[ $i - 1 ]['y'] + $previous_half + $gap + $current_half;
				$items[ $i ]['y'] = max( $items[ $i ]['y'], $min_y );
				if ( $items[ $i ]['y'] > $bottom - $current_half ) {
					return null;
				}
			}
		}

		return $items;
	}

	private static function colliding_marker_for_item( $item, $stations ) {
		$rect = self::item_rect( $item, $item['y'] );
		foreach ( $stations as $marker ) {
			$marker_x = self::coordinate( $marker['marker_x'] ?? ( $marker['x'] ?? 50 ), 50 );
			$marker_y = self::coordinate( $marker['marker_y'] ?? ( $marker['y'] ?? 50 ), 50 );
			if (
				$marker_x >= $rect['left'] - 1.2
				&& $marker_x <= $rect['right'] + 1.2
				&& $marker_y >= $rect['top'] - 1.2
				&& $marker_y <= $rect['bottom'] + 1.2
			) {
				return array( 'marker_x' => $marker_x, 'marker_y' => $marker_y );
			}
		}
		return null;
	}

	private static function apply_leader_lines( $layout, $stations, $options ) {
		$candidates = array();
		foreach ( $layout as $index => $item ) {
			$rect = self::item_rect( $item, $item['y'] );
			$end_x = 'right' === $item['side'] ? $rect['left'] : $rect['right'];
			$start_x = $item['marker_x'] + ( 'right' === $item['side'] ? (float) $options['leader_marker_padding'] : -1 * (float) $options['leader_marker_padding'] );
			$distance = hypot( $end_x - $start_x, $item['y'] - $item['marker_y'] );
			$manual = null !== $item['leader_line_preference'];
			$wants_line = $manual ? (bool) $item['leader_line_preference'] : ( $distance >= (float) $options['leader_min_distance'] && abs( $end_x - $start_x ) >= (float) $options['leader_min_horizontal'] );

			$layout[ $index ]['leader_line'] = false;
			$layout[ $index ]['leader_x1'] = $start_x;
			$layout[ $index ]['leader_y1'] = $item['marker_y'];
			$layout[ $index ]['leader_x2'] = $end_x;
			$layout[ $index ]['leader_y2'] = $item['y'];

			if ( ! $wants_line ) {
				continue;
			}

			$candidates[] = array(
				'index'    => $index,
				'distance' => $distance,
				'line'     => array( $start_x, $item['marker_y'], $end_x, $item['y'] ),
			);
		}

		usort(
			$candidates,
			static function ( $a, $b ) {
				if ( $a['distance'] === $b['distance'] ) {
					return $a['index'] <=> $b['index'];
				}
				return $b['distance'] <=> $a['distance'];
			}
		);

		$accepted = array();
		$limit = max( 0, (int) $options['max_leaders'] );
		foreach ( $candidates as $candidate ) {
			if ( count( $accepted ) >= $limit ) {
				break;
			}
			if ( self::line_crosses_any( $candidate['line'], $accepted ) ) {
				continue;
			}
			if ( self::line_hits_other_label( $candidate['line'], $candidate['index'], $layout ) ) {
				continue;
			}
			$layout[ $candidate['index'] ]['leader_line'] = true;
			$accepted[] = $candidate['line'];
		}

		ksort( $layout );
		return $layout;
	}

	private static function line_hits_other_label( $line, $line_index, $layout ) {
		foreach ( $layout as $index => $item ) {
			if ( (int) $index === (int) $line_index ) {
				continue;
			}
			if ( self::line_intersects_rect( $line, self::item_rect( $item, $item['y'] ) ) ) {
				return true;
			}
		}
		return false;
	}

	private static function line_crosses_any( $line, $accepted ) {
		foreach ( $accepted as $existing ) {
			if ( self::segments_intersect( $line, $existing ) ) {
				return true;
			}
		}
		return false;
	}

	private static function line_intersects_rect( $line, $rect ) {
		$edges = array(
			array( $rect['left'], $rect['top'], $rect['right'], $rect['top'] ),
			array( $rect['right'], $rect['top'], $rect['right'], $rect['bottom'] ),
			array( $rect['right'], $rect['bottom'], $rect['left'], $rect['bottom'] ),
			array( $rect['left'], $rect['bottom'], $rect['left'], $rect['top'] ),
		);
		foreach ( $edges as $edge ) {
			if ( self::segments_intersect( $line, $edge ) ) {
				return true;
			}
		}
		return false;
	}

	private static function segments_intersect( $a, $b ) {
		$d1 = self::direction( $a[0], $a[1], $a[2], $a[3], $b[0], $b[1] );
		$d2 = self::direction( $a[0], $a[1], $a[2], $a[3], $b[2], $b[3] );
		$d3 = self::direction( $b[0], $b[1], $b[2], $b[3], $a[0], $a[1] );
		$d4 = self::direction( $b[0], $b[1], $b[2], $b[3], $a[2], $a[3] );

		return ( ( $d1 > 0 && $d2 < 0 ) || ( $d1 < 0 && $d2 > 0 ) )
			&& ( ( $d3 > 0 && $d4 < 0 ) || ( $d3 < 0 && $d4 > 0 ) );
	}

	private static function direction( $ax, $ay, $bx, $by, $cx, $cy ) {
		return ( ( $cx - $ax ) * ( $by - $ay ) ) - ( ( $cy - $ay ) * ( $bx - $ax ) );
	}

	private static function item_rect( $item, $y ) {
		$width = (float) $item['width'];
		$height = (float) $item['height'];
		$anchor = $item['anchor'] ?? ( 'right' === $item['side'] ? 'right' : 'left' );
		if ( 'right' === $anchor ) {
			$left = (float) $item['x'] - $width;
			$right = (float) $item['x'];
		} elseif ( 'center' === $anchor ) {
			$left = (float) $item['x'] - $width / 2;
			$right = (float) $item['x'] + $width / 2;
		} else {
			$left = (float) $item['x'];
			$right = (float) $item['x'] + $width;
		}

		return array(
			'left'   => $left,
			'right'  => $right,
			'top'    => (float) $y - $height / 2,
			'bottom' => (float) $y + $height / 2,
		);
	}

	private static function rect_contains_marker_x( $rect, $marker_x ) {
		return $marker_x >= $rect['left'] - 1.2 && $marker_x <= $rect['right'] + 1.2;
	}

	private static function estimate_height( $label, $width, $options ) {
		$length = self::text_length( $label );
		$chars_per_line = max( 8, (int) floor( (float) $width * (float) $options['chars_per_width_pct'] ) );
		$lines = min( 3, max( 1, (int) ceil( max( 1, $length ) / $chars_per_line ) ) );
		return (float) $options['label_height_base'] + ( $lines - 1 ) * (float) $options['label_height_line'];
	}

	private static function text_length( $value ) {
		$value = trim( wp_strip_all_tags( (string) $value ) );
		if ( function_exists( 'mb_strlen' ) ) {
			return (int) mb_strlen( $value, 'UTF-8' );
		}
		return strlen( $value );
	}

	private static function label_anchor( $value ) {
		$value = sanitize_key( (string) $value );
		return in_array( $value, array( 'left', 'right', 'center' ), true ) ? $value : '';
	}

	private static function nullable_coordinate( $value ) {
		if ( null === $value || '' === $value || ! is_numeric( $value ) ) {
			return null;
		}
		return self::coordinate( $value, 50 );
	}

	private static function coordinate( $value, $fallback ) {
		return self::clamp( is_numeric( $value ) ? (float) $value : (float) $fallback, 0.0, 100.0 );
	}

	private static function width_percent( $value ) {
		if ( is_numeric( $value ) ) {
			return self::clamp( (float) $value, 8.0, 70.0 );
		}
		if ( is_string( $value ) && preg_match( '/^(\d+(?:\.\d+)?)%$/', trim( $value ), $matches ) ) {
			return self::clamp( (float) $matches[1], 8.0, 70.0 );
		}
		return null;
	}

	private static function format_percent( $value ) {
		return rtrim( rtrim( number_format( (float) $value, 2, '.', '' ), '0' ), '.' ) . '%';
	}

	private static function clamp( $value, $min, $max ) {
		return max( $min, min( $max, (float) $value ) );
	}
}
