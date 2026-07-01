<?php
/**
 * Private People and Registration module for TAKA Platform.
 */

defined( 'ABSPATH' ) || exit;

class TAKA_People_Module {
	const ADMIN_PAGE_SLUG = 'taka-platform-people';
	const SAVE_ACTION = 'taka_people_save_person';

	private static $person_repository = null;
	private static $registration_repository = null;

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_post_types' ), 0 );
		add_action( 'admin_menu', array( __CLASS__, 'register_admin_menu' ), 18 );
		add_action( 'admin_init', array( __CLASS__, 'ensure_capabilities' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybe_migrate_existing_ticket_orders' ) );
		add_action( 'admin_post_' . self::SAVE_ACTION, array( __CLASS__, 'handle_save_person' ) );
	}

	public static function register_post_types() {
		register_post_type(
			TAKA_PLATFORM_CPT_PERSON,
			array(
				'labels'              => array(
					'name'          => __( 'People', 'taka-platform' ),
					'singular_name' => __( 'Person', 'taka-platform' ),
				),
				'public'              => false,
				'publicly_queryable'  => false,
				'show_ui'             => false,
				'show_in_rest'        => false,
				'exclude_from_search' => true,
				'capability_type'     => 'post',
				'supports'            => array( 'title' ),
			)
		);

		register_post_type(
			TAKA_PLATFORM_CPT_REGISTRATION,
			array(
				'labels'              => array(
					'name'          => __( 'Registrations', 'taka-platform' ),
					'singular_name' => __( 'Registration', 'taka-platform' ),
				),
				'public'              => false,
				'publicly_queryable'  => false,
				'show_ui'             => false,
				'show_in_rest'        => false,
				'exclude_from_search' => true,
				'capability_type'     => 'post',
				'supports'            => array( 'title' ),
			)
		);
	}

	public static function register_admin_menu() {
		add_submenu_page(
			'taka-platform',
			__( 'People', 'taka-platform' ),
			__( 'People', 'taka-platform' ),
			'view_taka_people',
			self::ADMIN_PAGE_SLUG,
			array( __CLASS__, 'render_admin_page' )
		);
	}

	public static function ensure_capabilities() {
		if ( ! function_exists( 'get_role' ) ) {
			return;
		}
		$admin = get_role( 'administrator' );
		if ( ! $admin ) {
			return;
		}
		foreach ( self::capabilities() as $cap ) {
			$admin->add_cap( $cap );
		}
	}

	public static function capabilities() {
		return array(
			'manage_taka_people',
			'view_taka_people',
			'edit_taka_people',
			'view_taka_registrations',
			'edit_taka_registrations',
		);
	}

	public static function person_repository() {
		if ( null === self::$person_repository ) {
			self::$person_repository = new TAKA_People_Person_Repository();
		}
		return self::$person_repository;
	}

	public static function registration_repository() {
		if ( null === self::$registration_repository ) {
			self::$registration_repository = new TAKA_People_Registration_Repository();
		}
		return self::$registration_repository;
	}

	public static function admin_url( $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => self::ADMIN_PAGE_SLUG ), $args ), admin_url( 'admin.php' ) );
	}

	public static function person_link( $person_id, $label = '' ) {
		$person_id = absint( $person_id );
		if ( ! $person_id || ! current_user_can( 'view_taka_people' ) ) {
			return '';
		}
		$person = self::person_repository()->find_by_id( $person_id );
		$label = '' !== trim( (string) $label ) ? $label : ( $person ? TAKA_People_Person::full_name( $person ) : sprintf( __( 'Person #%d', 'taka-platform' ), $person_id ) );
		return '<a href="' . esc_url( self::admin_url( array( 'person_id' => $person_id ) ) ) . '">' . esc_html( $label ) . '</a>';
	}

	public static function sync_order_people_and_registrations( $order ) {
		if ( ! is_object( $order ) || ! method_exists( $order, 'to_array' ) || ! class_exists( 'TAKA_Ticketing_Module' ) || ! class_exists( 'TAKA_Ticketing_Order' ) ) {
			return $order;
		}

		$data = $order->to_array();
		$buyer = is_array( $data['buyer'] ?? null ) ? $data['buyer'] : array();
		$participant = is_array( $data['participant'] ?? null ) ? $data['participant'] : array();
		$buyer_person = self::person_repository()->create_or_update_from_person_data( TAKA_People_Person::from_buyer( $buyer ) );
		$participant_person = self::person_repository()->create_or_update_from_person_data( TAKA_People_Person::from_participant( $participant ) );

		if ( is_wp_error( $buyer_person ) || is_wp_error( $participant_person ) ) {
			return $order;
		}

		$data['buyer_person_id'] = absint( $buyer_person['id'] ?? 0 );
		$data['participant_person_id'] = absint( $participant_person['id'] ?? 0 );
		$registration_ids = array_values( array_filter( array_map( 'absint', (array) ( $data['registration_ids'] ?? array() ) ) ) );

		if ( ! empty( $data['event_id'] ) && ! empty( $participant_person['id'] ) ) {
			$registration = TAKA_People_Registration::from_order_data( $data, absint( $participant_person['id'] ) );
			$saved_registration = self::registration_repository()->save( $registration );
			if ( ! is_wp_error( $saved_registration ) && ! empty( $saved_registration['id'] ) ) {
				$registration_ids[] = absint( $saved_registration['id'] );
			}
		}

		$data['registration_ids'] = array_values( array_unique( $registration_ids ) );
		return TAKA_Ticketing_Module::order_repository()->save( new TAKA_Ticketing_Order( $data ) );
	}

	public static function maybe_migrate_existing_ticket_orders() {
		if ( ! is_admin() || ! current_user_can( 'manage_taka_people' ) || ! class_exists( 'TAKA_Ticketing_Module' ) ) {
			return;
		}

		$count = 0;
		foreach ( TAKA_Ticketing_Module::order_repository()->query( array( 'per_page' => 50 ) ) as $order ) {
			$data = $order->to_array();
			$needs_registration = ! empty( $data['event_id'] );
			if ( ! empty( $data['buyer_person_id'] ) && ! empty( $data['participant_person_id'] ) && ( ! $needs_registration || ! empty( $data['registration_ids'] ) ) ) {
				continue;
			}
			self::sync_order_people_and_registrations( $order );
			$count++;
			if ( $count >= 20 ) {
				break;
			}
		}
	}

	public static function handle_save_person() {
		if ( ! current_user_can( 'edit_taka_people' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'taka-platform' ) );
		}
		check_admin_referer( self::SAVE_ACTION, '_wpnonce' );
		$person = isset( $_POST['person'] ) && is_array( $_POST['person'] ) ? wp_unslash( $_POST['person'] ) : array();
		$result = self::person_repository()->save( $person );
		$args = array();
		if ( is_wp_error( $result ) ) {
			$args['person_error'] = $result->get_error_message();
			if ( ! empty( $person['id'] ) ) {
				$args['person_id'] = absint( $person['id'] );
			}
		} else {
			$args['person_saved'] = '1';
			$args['person_id'] = absint( $result['id'] ?? 0 );
		}
		wp_safe_redirect( self::admin_url( $args ) );
		exit;
	}

	public static function render_admin_page() {
		if ( ! current_user_can( 'view_taka_people' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'taka-platform' ) );
		}

		$person_id = absint( $_GET['person_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		echo '<div class="wrap taka-people-admin"><h1>' . esc_html__( 'People', 'taka-platform' ) . '</h1>';
		if ( ! empty( $_GET['person_saved'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Person saved.', 'taka-platform' ) . '</p></div>';
		}
		if ( ! empty( $_GET['person_error'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( sanitize_text_field( wp_unslash( $_GET['person_error'] ) ) ) . '</p></div>';
		}

		if ( $person_id ) {
			self::render_person_profile( $person_id );
		} elseif ( ! empty( $_GET['new'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			self::render_person_profile( 0 );
		} else {
			self::render_people_overview();
		}
		echo '</div>';
	}

	private static function render_people_overview() {
		$search = sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$people = self::person_repository()->query( array( 'search' => $search, 'per_page' => 200 ) );
		echo '<p class="description">' . esc_html__( 'People are private community records reused across orders, registrations, organizer work and future CRM features.', 'taka-platform' ) . '</p>';
		echo '<p><a class="button button-primary" href="' . esc_url( self::admin_url( array( 'new' => '1' ) ) ) . '">' . esc_html__( 'Add person', 'taka-platform' ) . '</a></p>';
		?>
		<form class="taka-people-search" method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
			<input type="hidden" name="page" value="<?php echo esc_attr( self::ADMIN_PAGE_SLUG ); ?>">
			<label class="screen-reader-text" for="taka-people-search-input"><?php echo esc_html__( 'Search people', 'taka-platform' ); ?></label>
			<input id="taka-people-search-input" type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php echo esc_attr__( 'Search by name, email, dojo, country, rank or association', 'taka-platform' ); ?>">
			<?php submit_button( __( 'Search', 'taka-platform' ), '', '', false ); ?>
		</form>
		<table class="widefat striped taka-people-table">
			<thead><tr>
				<th><?php echo esc_html__( 'Name', 'taka-platform' ); ?></th>
				<th><?php echo esc_html__( 'Country', 'taka-platform' ); ?></th>
				<th><?php echo esc_html__( 'Dojo', 'taka-platform' ); ?></th>
				<th><?php echo esc_html__( 'Events attended', 'taka-platform' ); ?></th>
				<th><?php echo esc_html__( 'Upcoming', 'taka-platform' ); ?></th>
				<th><?php echo esc_html__( 'Last participation', 'taka-platform' ); ?></th>
				<th><?php echo esc_html__( 'Tags', 'taka-platform' ); ?></th>
			</tr></thead>
			<tbody>
				<?php if ( empty( $people ) ) : ?>
					<tr><td colspan="7"><?php echo esc_html__( 'No people found yet.', 'taka-platform' ); ?></td></tr>
				<?php endif; ?>
				<?php foreach ( $people as $person ) : ?>
					<?php $stats = self::person_registration_stats( absint( $person['id'] ?? 0 ) ); ?>
					<tr>
						<td><strong><?php echo self::person_link( $person['id'], TAKA_People_Person::full_name( $person ) ?: ( $person['email'] ?? '' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></strong><br><span class="description"><?php echo esc_html( $person['email'] ?? '' ); ?></span></td>
						<td><?php echo esc_html( self::country_label( $person['country'] ?? '' ) ); ?></td>
						<td><?php echo esc_html( $person['dojo'] ?? '' ); ?></td>
						<td><?php echo esc_html( (string) $stats['attended'] ); ?></td>
						<td><?php echo esc_html( (string) $stats['upcoming'] ); ?></td>
						<td><?php echo esc_html( $stats['last_participation'] ); ?></td>
						<td><?php echo esc_html( implode( ', ', (array) ( $person['tags'] ?? array() ) ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	private static function render_person_profile( $person_id ) {
		$person = $person_id ? self::person_repository()->find_by_id( $person_id ) : null;
		if ( $person_id && ! $person ) {
			echo '<p>' . esc_html__( 'Person not found.', 'taka-platform' ) . '</p>';
			return;
		}
		$person = $person ? $person : TAKA_People_Person::normalize( array() );
		echo '<p><a href="' . esc_url( self::admin_url() ) . '">&larr; ' . esc_html__( 'Back to people', 'taka-platform' ) . '</a></p>';
		echo '<h2>' . esc_html( TAKA_People_Person::full_name( $person ) ?: __( 'New person', 'taka-platform' ) ) . '</h2>';
		self::render_person_form( $person );
		if ( ! empty( $person['id'] ) ) {
			self::render_person_history( $person );
		}
	}

	private static function render_person_form( $person ) {
		if ( ! current_user_can( 'edit_taka_people' ) ) {
			self::render_person_readonly( $person );
			return;
		}
		$countries = array( '' => __( 'Select country', 'taka-platform' ) ) + TAKA_Platform_Data::option_list_choices( 'country', TAKA_Platform_Data::platform_fallback_language() );
		?>
		<form class="taka-people-profile-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="<?php echo esc_attr( self::SAVE_ACTION ); ?>">
			<input type="hidden" name="person[id]" value="<?php echo esc_attr( (string) absint( $person['id'] ?? 0 ) ); ?>">
			<?php wp_nonce_field( self::SAVE_ACTION ); ?>
			<div class="taka-people-profile-grid">
				<section><h3><?php echo esc_html__( 'Basic', 'taka-platform' ); ?></h3>
					<?php self::input( 'first_name', __( 'First name', 'taka-platform' ), $person ); ?>
					<?php self::input( 'last_name', __( 'Last name', 'taka-platform' ), $person ); ?>
					<?php self::input( 'email', __( 'Email', 'taka-platform' ), $person, 'email' ); ?>
					<?php self::input( 'phone', __( 'Phone', 'taka-platform' ), $person ); ?>
					<?php self::select( 'country', __( 'Country', 'taka-platform' ), $countries, $person['country'] ?? '' ); ?>
				</section>
				<section><h3><?php echo esc_html__( 'Seminar', 'taka-platform' ); ?></h3>
					<?php self::input( 'dojo', __( 'Dojo / Club', 'taka-platform' ), $person ); ?>
					<?php self::input( 'association', __( 'Association', 'taka-platform' ), $person ); ?>
					<?php self::input( 'style', __( 'Style', 'taka-platform' ), $person ); ?>
					<?php self::input( 'rank', __( 'Rank / Belt', 'taka-platform' ), $person ); ?>
				</section>
				<section><h3><?php echo esc_html__( 'Preferences', 'taka-platform' ); ?></h3>
					<?php self::select( 'dietary_preference', __( 'Dietary preference', 'taka-platform' ), self::dietary_labels(), $person['dietary_preference'] ?? 'none' ); ?>
					<?php self::textarea( 'allergies', __( 'Allergies', 'taka-platform' ), $person ); ?>
				</section>
				<section><h3><?php echo esc_html__( 'Administration', 'taka-platform' ); ?></h3>
					<?php self::textarea( 'notes', __( 'Notes', 'taka-platform' ), $person ); ?>
					<label><span><?php echo esc_html__( 'Tags', 'taka-platform' ); ?></span><input class="regular-text" type="text" name="person[tags]" value="<?php echo esc_attr( implode( ', ', (array) ( $person['tags'] ?? array() ) ) ); ?>" placeholder="<?php echo esc_attr__( 'VIP, Instructor, Volunteer', 'taka-platform' ); ?>"></label>
					<label><input type="checkbox" name="person[gdpr_consent]" value="1" <?php checked( '1', (string) ( $person['gdpr_consent'] ?? '0' ) ); ?>> <?php echo esc_html__( 'GDPR consent recorded', 'taka-platform' ); ?></label>
					<label><input type="checkbox" name="person[newsletter_consent]" value="1" <?php checked( '1', (string) ( $person['newsletter_consent'] ?? '0' ) ); ?>> <?php echo esc_html__( 'Newsletter consent recorded', 'taka-platform' ); ?></label>
				</section>
				<section><h3><?php echo esc_html__( 'System', 'taka-platform' ); ?></h3>
					<p><strong><?php echo esc_html__( 'Created', 'taka-platform' ); ?>:</strong> <?php echo esc_html( $person['created_at'] ?? '' ); ?></p>
					<p><strong><?php echo esc_html__( 'Updated', 'taka-platform' ); ?>:</strong> <?php echo esc_html( $person['updated_at'] ?? '' ); ?></p>
				</section>
			</div>
			<?php submit_button( __( 'Save person', 'taka-platform' ) ); ?>
		</form>
		<?php
	}

	private static function render_person_history( $person ) {
		$person_id = absint( $person['id'] ?? 0 );
		$registrations = self::registration_repository()->query( array( 'person_id' => $person_id, 'per_page' => -1 ) );
		$orders = self::orders_for_person( $person_id );
		?>
		<div class="taka-people-history">
			<section><?php self::render_registration_table( __( 'Upcoming registrations', 'taka-platform' ), self::filter_registrations_by_time( $registrations, 'upcoming' ) ); ?></section>
			<section><?php self::render_registration_table( __( 'Events attended', 'taka-platform' ), self::filter_registrations_by_time( $registrations, 'past' ) ); ?></section>
			<section><?php self::render_previous_tours( $registrations ); ?></section>
			<section><?php self::render_products_purchased( $orders ); ?></section>
			<section><?php self::render_vouchers_used( $orders ); ?></section>
			<section><h3><?php echo esc_html__( 'Organizer activities', 'taka-platform' ); ?></h3><p class="description"><?php echo esc_html( self::activity_note( $person, 'Organizer' ) ); ?></p></section>
			<section><h3><?php echo esc_html__( 'Volunteer activities', 'taka-platform' ); ?></h3><p class="description"><?php echo esc_html( self::activity_note( $person, 'Volunteer' ) ); ?></p></section>
		</div>
		<?php
	}

	private static function render_registration_table( $title, $registrations ) {
		echo '<h3>' . esc_html( $title ) . '</h3>';
		if ( empty( $registrations ) ) {
			echo '<p class="description">' . esc_html__( 'No registrations recorded.', 'taka-platform' ) . '</p>';
			return;
		}
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Date', 'taka-platform' ) . '</th><th>' . esc_html__( 'Event', 'taka-platform' ) . '</th><th>' . esc_html__( 'Ticket', 'taka-platform' ) . '</th><th>' . esc_html__( 'Payment', 'taka-platform' ) . '</th><th>' . esc_html__( 'Check-in', 'taka-platform' ) . '</th><th>' . esc_html__( 'Order', 'taka-platform' ) . '</th></tr></thead><tbody>';
		foreach ( $registrations as $registration ) {
			$order_link = class_exists( 'TAKA_Ticketing_Module' ) && ! empty( $registration['order_id'] ) ? '<a href="' . esc_url( TAKA_Ticketing_Module::admin_url( array( 'order_id' => absint( $registration['order_id'] ) ) ) ) . '">' . esc_html( $registration['order_number'] ) . '</a>' : esc_html( $registration['order_number'] );
			$event_link = ! empty( $registration['event_id'] ) && get_edit_post_link( absint( $registration['event_id'] ), '' ) ? '<a href="' . esc_url( get_edit_post_link( absint( $registration['event_id'] ), '' ) ) . '">' . esc_html( $registration['event_title'] ) . '</a>' : esc_html( $registration['event_title'] );
			echo '<tr><td>' . esc_html( self::registration_event_date( $registration ) ) . '</td><td>' . $event_link . '</td><td>' . esc_html( $registration['ticket_type_name'] ) . '</td><td>' . esc_html( $registration['payment_status'] ) . '</td><td>' . esc_html( $registration['checkin_status'] ) . '</td><td>' . $order_link . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo '</tbody></table>';
	}

	private static function render_previous_tours( $registrations ) {
		$tours = array();
		foreach ( $registrations as $registration ) {
			$tour_id = ! empty( $registration['event_id'] ) ? (string) get_post_meta( absint( $registration['event_id'] ), '_taka_tour_id', true ) : '';
			if ( '' !== trim( $tour_id ) ) {
				$tours[] = $tour_id;
			}
		}
		$tours = array_values( array_unique( $tours ) );
		echo '<h3>' . esc_html__( 'Previous tours', 'taka-platform' ) . '</h3>';
		echo empty( $tours ) ? '<p class="description">' . esc_html__( 'No tour history recorded.', 'taka-platform' ) . '</p>' : '<p>' . esc_html( implode( ', ', $tours ) ) . '</p>';
	}

	private static function render_products_purchased( $orders ) {
		$items = array();
		foreach ( $orders as $order ) {
			$data = $order->to_array();
			foreach ( (array) ( $data['line_items'] ?? array() ) as $item ) {
				if ( 'product' === (string) ( $item['item_type'] ?? '' ) ) {
					$items[] = trim( (string) ( $item['quantity'] ?? 1 ) . ' x ' . (string) ( $item['title'] ?? '' ) );
				}
			}
		}
		echo '<h3>' . esc_html__( 'Products purchased', 'taka-platform' ) . '</h3>';
		echo empty( $items ) ? '<p class="description">' . esc_html__( 'No products recorded.', 'taka-platform' ) . '</p>' : '<ul><li>' . implode( '</li><li>', array_map( 'esc_html', $items ) ) . '</li></ul>';
	}

	private static function render_vouchers_used( $orders ) {
		$codes = array();
		foreach ( $orders as $order ) {
			$code = trim( (string) $order->get( 'applied_voucher_code', '' ) );
			if ( '' !== $code ) {
				$codes[] = $code;
			}
		}
		echo '<h3>' . esc_html__( 'Vouchers used', 'taka-platform' ) . '</h3>';
		echo empty( $codes ) ? '<p class="description">' . esc_html__( 'No vouchers recorded.', 'taka-platform' ) . '</p>' : '<p>' . esc_html( implode( ', ', array_unique( $codes ) ) ) . '</p>';
	}

	private static function filter_registrations_by_time( $registrations, $bucket ) {
		$today = current_time( 'Y-m-d' );
		return array_values(
			array_filter(
				(array) $registrations,
				static function ( $registration ) use ( $bucket, $today ) {
					if ( 'cancelled' === (string) ( $registration['registration_status'] ?? '' ) ) {
						return false;
					}
					$date = self::registration_event_date( $registration );
					return 'upcoming' === $bucket ? ( '' !== $date && $date >= $today ) : ( '' === $date || $date < $today );
				}
			)
		);
	}

	private static function person_registration_stats( $person_id ) {
		$registrations = self::registration_repository()->query( array( 'person_id' => $person_id, 'per_page' => -1 ) );
		$past = self::filter_registrations_by_time( $registrations, 'past' );
		$upcoming = self::filter_registrations_by_time( $registrations, 'upcoming' );
		$dates = array();
		foreach ( $past as $registration ) {
			$date = self::registration_event_date( $registration );
			if ( '' !== $date ) {
				$dates[] = $date;
			}
		}
		rsort( $dates );
		return array(
			'attended'           => count( $past ),
			'upcoming'           => count( $upcoming ),
			'last_participation' => $dates[0] ?? '',
		);
	}

	private static function registration_event_date( $registration ) {
		$event_id = absint( $registration['event_id'] ?? 0 );
		if ( ! $event_id ) {
			return '';
		}
		return sanitize_text_field( get_post_meta( $event_id, '_taka_date_start', true ) );
	}

	private static function orders_for_person( $person_id ) {
		if ( ! class_exists( 'TAKA_Ticketing_Module' ) ) {
			return array();
		}
		$orders = array();
		foreach ( TAKA_Ticketing_Module::order_repository()->query( array( 'per_page' => -1 ) ) as $order ) {
			if ( absint( $order->get( 'buyer_person_id' ) ) === absint( $person_id ) || absint( $order->get( 'participant_person_id' ) ) === absint( $person_id ) ) {
				$orders[] = $order;
			}
		}
		return $orders;
	}

	private static function activity_note( $person, $tag ) {
		$tags = array_map( 'strtolower', (array) ( $person['tags'] ?? array() ) );
		return in_array( strtolower( $tag ), $tags, true ) ? sprintf( __( '%s tag is assigned. Structured activity history can be added by future modules.', 'taka-platform' ), $tag ) : __( 'No structured activity recorded yet.', 'taka-platform' );
	}

	private static function input( $field, $label, $person, $type = 'text' ) {
		echo '<label><span>' . esc_html( $label ) . '</span><input class="regular-text" type="' . esc_attr( $type ) . '" name="' . esc_attr( 'person[' . $field . ']' ) . '" value="' . esc_attr( $person[ $field ] ?? '' ) . '"></label>';
	}

	private static function textarea( $field, $label, $person ) {
		echo '<label><span>' . esc_html( $label ) . '</span><textarea name="' . esc_attr( 'person[' . $field . ']' ) . '" rows="3">' . esc_textarea( $person[ $field ] ?? '' ) . '</textarea></label>';
	}

	private static function select( $field, $label, $choices, $current ) {
		echo '<label><span>' . esc_html( $label ) . '</span><select name="' . esc_attr( 'person[' . $field . ']' ) . '">';
		foreach ( (array) $choices as $value => $choice_label ) {
			echo '<option value="' . esc_attr( (string) $value ) . '" ' . selected( (string) $current, (string) $value, false ) . '>' . esc_html( (string) $choice_label ) . '</option>';
		}
		echo '</select></label>';
	}

	private static function dietary_labels() {
		return array(
			'none'       => __( 'No dietary preference', 'taka-platform' ),
			'vegetarian' => __( 'Vegetarian', 'taka-platform' ),
			'vegan'      => __( 'Vegan', 'taka-platform' ),
			'other'      => __( 'Other / note', 'taka-platform' ),
		);
	}

	private static function country_label( $country ) {
		$country = sanitize_text_field( $country );
		return '' !== $country ? TAKA_Platform_Data::country_label( $country, TAKA_Platform_Data::platform_fallback_language() ) : '';
	}

	private static function render_person_readonly( $person ) {
		echo '<div class="taka-people-profile-grid"><section>';
		foreach ( $person as $key => $value ) {
			if ( is_array( $value ) ) {
				$value = implode( ', ', $value );
			}
			if ( '' === trim( (string) $value ) ) {
				continue;
			}
			echo '<p><strong>' . esc_html( ucwords( str_replace( '_', ' ', $key ) ) ) . ':</strong> ' . esc_html( $value ) . '</p>';
		}
		echo '</section></div>';
	}
}
