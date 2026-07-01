<?php
/**
 * Private resource management for tour logistics.
 *
 * This module tracks physical resources, their assignments and movement
 * history. It is not a warehouse system; it answers operational questions:
 * what exists, where is it, who is responsible and which event needs it next?
 */

defined( 'ABSPATH' ) || exit;

class TAKA_Resource_Management_Module {
	const ADMIN_PAGE_SLUG        = 'taka-platform-resources';
	const RESOURCE_META          = '_taka_resource';
	const MOVEMENT_META          = '_taka_resource_movement';
	const EVENT_REQUIREMENTS_META = '_taka_event_required_resources';
	const SAVE_RESOURCE_ACTION   = 'taka_resource_save';
	const MOVE_RESOURCE_ACTION   = 'taka_resource_move';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_post_types' ), 0 );
		add_action( 'admin_menu', array( __CLASS__, 'register_admin_menu' ), 24 );
		add_action( 'admin_init', array( __CLASS__, 'ensure_capabilities' ) );
		add_action( 'admin_post_' . self::SAVE_RESOURCE_ACTION, array( __CLASS__, 'handle_save_resource' ) );
		add_action( 'admin_post_' . self::MOVE_RESOURCE_ACTION, array( __CLASS__, 'handle_move_resource' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'register_event_meta_box' ) );
		add_action( 'save_post_' . TAKA_PLATFORM_CPT_EVENT, array( __CLASS__, 'save_event_requirements' ), 10, 2 );
	}

	public static function register_post_types() {
		register_post_type(
			TAKA_PLATFORM_CPT_RESOURCE,
			array(
				'labels'              => array(
					'name'          => __( 'Resources', 'taka-platform' ),
					'singular_name' => __( 'Resource', 'taka-platform' ),
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
			TAKA_PLATFORM_CPT_RESOURCE_MOVEMENT,
			array(
				'labels'              => array(
					'name'          => __( 'Resource Movements', 'taka-platform' ),
					'singular_name' => __( 'Resource Movement', 'taka-platform' ),
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
			__( 'Resources', 'taka-platform' ),
			__( 'Resources', 'taka-platform' ),
			'view_taka_resources',
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
			'view_taka_resources',
			'manage_taka_resources',
		);
	}

	public static function admin_url( $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => self::ADMIN_PAGE_SLUG ), $args ), admin_url( 'admin.php' ) );
	}

	public static function register_event_meta_box() {
		if ( ! current_user_can( 'view_taka_resources' ) ) {
			return;
		}
		add_meta_box(
			'taka_event_required_resources',
			__( 'Required resources', 'taka-platform' ),
			array( __CLASS__, 'render_event_meta_box' ),
			TAKA_PLATFORM_CPT_EVENT,
			'side',
			'default'
		);
	}

	public static function render_event_meta_box( $post ) {
		$requirements = self::event_requirements( $post->ID );
		$resources = self::resources();
		wp_nonce_field( 'taka_event_required_resources', '_taka_event_required_resources_nonce' );
		$rows = max( 4, count( $requirements ) + 1 );
		?>
		<p class="description"><?php esc_html_e( 'Private logistics only. These resources are never shown on public event pages.', 'taka-platform' ); ?></p>
		<div class="taka-event-resource-requirements">
			<?php for ( $i = 0; $i < $rows; $i++ ) : $row = $requirements[ $i ] ?? array(); ?>
				<div class="taka-event-resource-row">
					<select name="taka_event_required_resources[<?php echo esc_attr( $i ); ?>][resource_id]">
						<option value="0"><?php esc_html_e( 'Select resource', 'taka-platform' ); ?></option>
						<?php foreach ( $resources as $resource ) : ?>
							<option value="<?php echo esc_attr( $resource['id'] ); ?>" <?php selected( absint( $row['resource_id'] ?? 0 ), $resource['id'] ); ?>><?php echo esc_html( $resource['name'] ); ?></option>
						<?php endforeach; ?>
					</select>
					<input type="number" min="1" name="taka_event_required_resources[<?php echo esc_attr( $i ); ?>][quantity]" value="<?php echo esc_attr( $row['quantity'] ?? '' ); ?>" placeholder="<?php echo esc_attr__( 'Qty', 'taka-platform' ); ?>">
					<input type="text" name="taka_event_required_resources[<?php echo esc_attr( $i ); ?>][notes]" value="<?php echo esc_attr( $row['notes'] ?? '' ); ?>" placeholder="<?php echo esc_attr__( 'Notes', 'taka-platform' ); ?>">
				</div>
			<?php endfor; ?>
		</div>
		<?php
	}

	public static function save_event_requirements( $post_id, $post ) {
		if ( ! $post || TAKA_PLATFORM_CPT_EVENT !== $post->post_type ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'manage_taka_resources' ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$nonce = sanitize_text_field( wp_unslash( $_POST['_taka_event_required_resources_nonce'] ?? '' ) );
		if ( ! wp_verify_nonce( $nonce, 'taka_event_required_resources' ) ) {
			return;
		}
		$raw = isset( $_POST['taka_event_required_resources'] ) && is_array( $_POST['taka_event_required_resources'] ) ? wp_unslash( $_POST['taka_event_required_resources'] ) : array();
		update_post_meta( $post_id, self::EVENT_REQUIREMENTS_META, self::normalize_requirements( $raw ) );
	}

	public static function handle_save_resource() {
		if ( ! current_user_can( 'manage_taka_resources' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'taka-platform' ) );
		}
		check_admin_referer( self::SAVE_RESOURCE_ACTION, '_wpnonce' );
		$raw = isset( $_POST['resource'] ) && is_array( $_POST['resource'] ) ? wp_unslash( $_POST['resource'] ) : array();
		$result = self::save_resource( self::normalize_resource( $raw ) );
		$args = array();
		if ( is_wp_error( $result ) ) {
			$args['resource_error'] = $result->get_error_message();
		} else {
			$args['resource_saved'] = '1';
		}
		wp_safe_redirect( self::admin_url( $args ) );
		exit;
	}

	public static function handle_move_resource() {
		if ( ! current_user_can( 'manage_taka_resources' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'taka-platform' ) );
		}
		check_admin_referer( self::MOVE_RESOURCE_ACTION, '_wpnonce' );
		$raw = isset( $_POST['movement'] ) && is_array( $_POST['movement'] ) ? wp_unslash( $_POST['movement'] ) : array();
		$result = self::record_movement( self::normalize_movement( $raw ) );
		$args = array();
		if ( is_wp_error( $result ) ) {
			$args['resource_error'] = $result->get_error_message();
		} else {
			$args['movement_saved'] = '1';
		}
		wp_safe_redirect( self::admin_url( $args ) );
		exit;
	}

	public static function render_admin_page() {
		if ( ! current_user_can( 'view_taka_resources' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'taka-platform' ) );
		}

		$resources = self::resources();
		$movements = self::movements( 50 );
		$requirements = self::event_requirement_rows();
		$metrics = self::metrics( $resources, $movements );
		?>
		<div class="wrap taka-resources-admin">
			<h1><?php esc_html_e( 'Resource Management', 'taka-platform' ); ?></h1>
			<p class="description"><?php esc_html_e( 'Track tour logistics: equipment, location, responsibility, assignments and movement history.', 'taka-platform' ); ?></p>
			<?php self::render_notices(); ?>
			<?php self::render_metrics( $metrics ); ?>
			<div class="taka-admin-grid taka-admin-grid--two">
				<section class="taka-admin-panel">
					<h2><?php esc_html_e( 'Add resource', 'taka-platform' ); ?></h2>
					<?php self::render_resource_form(); ?>
				</section>
				<section class="taka-admin-panel">
					<h2><?php esc_html_e( 'Move resource', 'taka-platform' ); ?></h2>
					<?php self::render_movement_form( $resources ); ?>
				</section>
			</div>
			<section class="taka-admin-panel taka-admin-panel--full">
				<h2><?php esc_html_e( 'Resources', 'taka-platform' ); ?></h2>
				<?php self::render_resource_table( $resources ); ?>
			</section>
			<div class="taka-admin-grid taka-admin-grid--two">
				<section class="taka-admin-panel">
					<h2><?php esc_html_e( 'Upcoming event requirements', 'taka-platform' ); ?></h2>
					<?php self::render_requirement_table( $requirements ); ?>
				</section>
				<section class="taka-admin-panel">
					<h2><?php esc_html_e( 'Movement history', 'taka-platform' ); ?></h2>
					<?php self::render_movement_table( $movements ); ?>
				</section>
			</div>
		</div>
		<?php
	}

	private static function render_notices() {
		if ( ! empty( $_GET['resource_error'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-error"><p>' . esc_html( wp_unslash( $_GET['resource_error'] ) ) . '</p></div>'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}
		if ( ! empty( $_GET['resource_saved'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-success"><p>' . esc_html__( 'Resource saved.', 'taka-platform' ) . '</p></div>';
		}
		if ( ! empty( $_GET['movement_saved'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-success"><p>' . esc_html__( 'Movement recorded.', 'taka-platform' ) . '</p></div>';
		}
	}

	private static function render_metrics( $metrics ) {
		?>
		<div class="taka-resource-metrics">
			<?php self::metric_card( __( 'Total resources', 'taka-platform' ), $metrics['total'] ); ?>
			<?php self::metric_card( __( 'Assigned to tours', 'taka-platform' ), $metrics['on_tour'] ); ?>
			<?php self::metric_card( __( 'Reserved', 'taka-platform' ), $metrics['reserved'] ); ?>
			<?php self::metric_card( __( 'Missing', 'taka-platform' ), $metrics['missing'] ); ?>
			<?php self::metric_card( __( 'Overdue returns', 'taka-platform' ), $metrics['overdue'] ); ?>
			<?php self::metric_card( __( 'Broken resources', 'taka-platform' ), $metrics['broken'] ); ?>
		</div>
		<?php
	}

	private static function metric_card( $label, $value ) {
		?>
		<div class="taka-resource-card">
			<span><?php echo esc_html( $label ); ?></span>
			<strong><?php echo esc_html( number_format_i18n( absint( $value ) ) ); ?></strong>
		</div>
		<?php
	}

	private static function render_resource_form() {
		$events = self::events();
		$people = class_exists( 'TAKA_People_Module' ) ? TAKA_People_Module::person_repository()->query( array( 'per_page' => 250 ) ) : array();
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="<?php echo esc_attr( self::SAVE_RESOURCE_ACTION ); ?>">
			<?php wp_nonce_field( self::SAVE_RESOURCE_ACTION, '_wpnonce' ); ?>
			<div class="taka-admin-field-grid">
				<label>
					<span><?php esc_html_e( 'Name', 'taka-platform' ); ?></span>
					<input type="text" name="resource[name]" required>
				</label>
				<label>
					<span><?php esc_html_e( 'Category', 'taka-platform' ); ?></span>
					<select name="resource[category]">
						<?php foreach ( self::categories() as $key => $label ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<label>
					<span><?php esc_html_e( 'Quantity', 'taka-platform' ); ?></span>
					<input type="number" name="resource[quantity]" min="1" value="1">
				</label>
				<label>
					<span><?php esc_html_e( 'Serial number', 'taka-platform' ); ?></span>
					<input type="text" name="resource[serial_number]">
				</label>
				<label>
					<span><?php esc_html_e( 'Current location', 'taka-platform' ); ?></span>
					<input type="text" name="resource[current_location]">
				</label>
				<label>
					<span><?php esc_html_e( 'Responsible person', 'taka-platform' ); ?></span>
					<input type="text" name="resource[responsible_person]">
				</label>
				<label>
					<span><?php esc_html_e( 'Condition', 'taka-platform' ); ?></span>
					<select name="resource[condition]">
						<?php foreach ( self::conditions() as $key => $label ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<label>
					<span><?php esc_html_e( 'Status', 'taka-platform' ); ?></span>
					<select name="resource[status]">
						<?php foreach ( self::statuses() as $key => $label ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<label>
					<span><?php esc_html_e( 'Photo attachment ID', 'taka-platform' ); ?></span>
					<input type="number" name="resource[photo_id]" min="0">
				</label>
				<label>
					<span><?php esc_html_e( 'Tour assignment', 'taka-platform' ); ?></span>
					<input type="text" name="resource[assignment][tour_key]" placeholder="taka-tour">
				</label>
				<label>
					<span><?php esc_html_e( 'Event assignment', 'taka-platform' ); ?></span>
					<select name="resource[assignment][event_id]">
						<option value="0"><?php esc_html_e( 'No event', 'taka-platform' ); ?></option>
						<?php foreach ( $events as $event ) : ?>
							<option value="<?php echo esc_attr( $event->ID ); ?>"><?php echo esc_html( get_the_title( $event ) ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<label>
					<span><?php esc_html_e( 'Planning item ID', 'taka-platform' ); ?></span>
					<input type="text" name="resource[assignment][planning_item_id]">
				</label>
				<label>
					<span><?php esc_html_e( 'Vehicle', 'taka-platform' ); ?></span>
					<input type="text" name="resource[assignment][vehicle]">
				</label>
				<label>
					<span><?php esc_html_e( 'Person assignment', 'taka-platform' ); ?></span>
					<select name="resource[assignment][person_id]">
						<option value="0"><?php esc_html_e( 'No person', 'taka-platform' ); ?></option>
						<?php foreach ( $people as $person ) : ?>
							<option value="<?php echo esc_attr( $person['id'] ); ?>"><?php echo esc_html( TAKA_People_Person::full_name( $person ) ?: $person['email'] ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<label class="taka-admin-field-grid__wide">
					<span><?php esc_html_e( 'Description', 'taka-platform' ); ?></span>
					<textarea name="resource[description]" rows="3"></textarea>
				</label>
				<label class="taka-admin-field-grid__wide">
					<span><?php esc_html_e( 'Notes', 'taka-platform' ); ?></span>
					<textarea name="resource[notes]" rows="3"></textarea>
				</label>
			</div>
			<p class="submit"><button class="button button-primary" type="submit"><?php esc_html_e( 'Save resource', 'taka-platform' ); ?></button></p>
		</form>
		<?php
	}

	private static function render_movement_form( $resources ) {
		if ( empty( $resources ) ) {
			echo '<p>' . esc_html__( 'Create a resource first.', 'taka-platform' ) . '</p>';
			return;
		}
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="<?php echo esc_attr( self::MOVE_RESOURCE_ACTION ); ?>">
			<?php wp_nonce_field( self::MOVE_RESOURCE_ACTION, '_wpnonce' ); ?>
			<div class="taka-admin-field-grid">
				<label>
					<span><?php esc_html_e( 'Resource', 'taka-platform' ); ?></span>
					<select name="movement[resource_id]" required>
						<?php foreach ( $resources as $resource ) : ?>
							<option value="<?php echo esc_attr( $resource['id'] ); ?>"><?php echo esc_html( $resource['name'] ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<label>
					<span><?php esc_html_e( 'New location', 'taka-platform' ); ?></span>
					<input type="text" name="movement[to_location]" required>
				</label>
				<label>
					<span><?php esc_html_e( 'Responsible person', 'taka-platform' ); ?></span>
					<input type="text" name="movement[responsible_person]">
				</label>
				<label>
					<span><?php esc_html_e( 'Moved at', 'taka-platform' ); ?></span>
					<input type="datetime-local" name="movement[moved_at]" value="<?php echo esc_attr( current_time( 'Y-m-d\TH:i' ) ); ?>">
				</label>
				<label>
					<span><?php esc_html_e( 'Expected return', 'taka-platform' ); ?></span>
					<input type="date" name="movement[expected_return]">
				</label>
				<label class="taka-admin-field-grid__wide">
					<span><?php esc_html_e( 'Notes', 'taka-platform' ); ?></span>
					<textarea name="movement[notes]" rows="3"></textarea>
				</label>
			</div>
			<p class="submit"><button class="button button-primary" type="submit"><?php esc_html_e( 'Record movement', 'taka-platform' ); ?></button></p>
		</form>
		<?php
	}

	private static function render_resource_table( $resources ) {
		if ( empty( $resources ) ) {
			echo '<p>' . esc_html__( 'No resources have been created yet.', 'taka-platform' ) . '</p>';
			return;
		}
		?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Resource', 'taka-platform' ); ?></th>
					<th><?php esc_html_e( 'Category', 'taka-platform' ); ?></th>
					<th><?php esc_html_e( 'Qty', 'taka-platform' ); ?></th>
					<th><?php esc_html_e( 'Location', 'taka-platform' ); ?></th>
					<th><?php esc_html_e( 'Responsible', 'taka-platform' ); ?></th>
					<th><?php esc_html_e( 'Condition', 'taka-platform' ); ?></th>
					<th><?php esc_html_e( 'Status', 'taka-platform' ); ?></th>
					<th><?php esc_html_e( 'Assigned to', 'taka-platform' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $resources as $resource ) : ?>
					<tr>
						<td><strong><?php echo esc_html( $resource['name'] ); ?></strong><br><span class="description"><?php echo esc_html( $resource['serial_number'] ); ?></span></td>
						<td><?php echo esc_html( self::categories()[ $resource['category'] ] ?? $resource['category'] ); ?></td>
						<td><?php echo esc_html( $resource['quantity'] ); ?></td>
						<td><?php echo esc_html( $resource['current_location'] ); ?></td>
						<td><?php echo esc_html( $resource['responsible_person'] ); ?></td>
						<td><?php echo esc_html( self::conditions()[ $resource['condition'] ] ?? $resource['condition'] ); ?></td>
						<td><?php echo esc_html( self::statuses()[ $resource['status'] ] ?? $resource['status'] ); ?></td>
						<td><?php echo esc_html( self::assignment_label( $resource['assignment'] ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	private static function render_requirement_table( $rows ) {
		if ( empty( $rows ) ) {
			echo '<p>' . esc_html__( 'No event resource requirements have been defined yet.', 'taka-platform' ) . '</p>';
			return;
		}
		?>
		<table class="widefat striped">
			<thead><tr><th><?php esc_html_e( 'Event', 'taka-platform' ); ?></th><th><?php esc_html_e( 'Resource', 'taka-platform' ); ?></th><th><?php esc_html_e( 'Qty', 'taka-platform' ); ?></th></tr></thead>
			<tbody>
				<?php foreach ( $rows as $row ) : ?>
					<tr>
						<td><?php echo esc_html( $row['event_title'] ); ?></td>
						<td><?php echo esc_html( $row['resource_name'] ); ?><br><span class="description"><?php echo esc_html( $row['notes'] ); ?></span></td>
						<td><?php echo esc_html( $row['quantity'] ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	private static function render_movement_table( $movements ) {
		if ( empty( $movements ) ) {
			echo '<p>' . esc_html__( 'No movement history yet.', 'taka-platform' ) . '</p>';
			return;
		}
		?>
		<table class="widefat striped">
			<thead><tr><th><?php esc_html_e( 'Moved', 'taka-platform' ); ?></th><th><?php esc_html_e( 'Resource', 'taka-platform' ); ?></th><th><?php esc_html_e( 'From / To', 'taka-platform' ); ?></th><th><?php esc_html_e( 'Expected return', 'taka-platform' ); ?></th></tr></thead>
			<tbody>
				<?php foreach ( $movements as $movement ) : ?>
					<tr>
						<td><?php echo esc_html( $movement['moved_at'] ); ?></td>
						<td><?php echo esc_html( $movement['resource_name'] ); ?></td>
						<td><?php echo esc_html( trim( $movement['from_location'] . ' -> ' . $movement['to_location'] ) ); ?><br><span class="description"><?php echo esc_html( $movement['responsible_person'] ); ?></span></td>
						<td><?php echo esc_html( $movement['expected_return'] ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	private static function save_resource( $resource ) {
		if ( '' === $resource['name'] ) {
			return new WP_Error( 'taka_resource_missing_name', __( 'Resource name is required.', 'taka-platform' ) );
		}
		$post_data = array(
			'post_type'   => TAKA_PLATFORM_CPT_RESOURCE,
			'post_status' => 'private',
			'post_title'  => $resource['name'],
		);
		if ( ! empty( $resource['id'] ) ) {
			$post_data['ID'] = absint( $resource['id'] );
			$result = wp_update_post( $post_data, true );
			$post_id = absint( $resource['id'] );
		} else {
			$result = wp_insert_post( $post_data, true );
			$post_id = is_wp_error( $result ) ? 0 : absint( $result );
		}
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$resource['id'] = $post_id;
		$resource['updated_at'] = current_time( 'mysql' );
		if ( empty( $resource['created_at'] ) ) {
			$resource['created_at'] = get_post_time( 'Y-m-d H:i:s', false, $post_id );
		}
		update_post_meta( $post_id, self::RESOURCE_META, $resource );
		return $resource;
	}

	private static function record_movement( $movement ) {
		$resource = self::resource( $movement['resource_id'] );
		if ( ! $resource ) {
			return new WP_Error( 'taka_resource_missing_resource', __( 'Select a valid resource.', 'taka-platform' ) );
		}
		if ( '' === $movement['to_location'] ) {
			return new WP_Error( 'taka_resource_missing_location', __( 'New location is required.', 'taka-platform' ) );
		}

		$movement['resource_name'] = $resource['name'];
		$movement['from_location'] = $resource['current_location'];
		$title = sprintf( '%1$s -> %2$s', $resource['name'], $movement['to_location'] );
		$post_id = wp_insert_post(
			array(
				'post_type'   => TAKA_PLATFORM_CPT_RESOURCE_MOVEMENT,
				'post_status' => 'private',
				'post_title'  => sanitize_text_field( $title ),
			),
			true
		);
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}
		$movement['id'] = absint( $post_id );
		$movement['created_at'] = current_time( 'mysql' );
		update_post_meta( $post_id, self::MOVEMENT_META, $movement );

		$resource['current_location'] = $movement['to_location'];
		if ( '' !== $movement['responsible_person'] ) {
			$resource['responsible_person'] = $movement['responsible_person'];
		}
		if ( 'available' === $resource['status'] ) {
			$resource['status'] = 'assigned';
		}
		self::save_resource( $resource );
		return $movement;
	}

	private static function resources() {
		$posts = get_posts(
			array(
				'post_type'        => TAKA_PLATFORM_CPT_RESOURCE,
				'post_status'      => 'private',
				'posts_per_page'   => -1,
				'orderby'          => 'title',
				'order'            => 'ASC',
				'suppress_filters' => true,
			)
		);
		$out = array();
		foreach ( $posts as $post ) {
			$out[] = self::resource_from_post( $post );
		}
		return array_values( array_filter( $out ) );
	}

	private static function resource( $resource_id ) {
		$post = get_post( absint( $resource_id ) );
		return $post && TAKA_PLATFORM_CPT_RESOURCE === $post->post_type ? self::resource_from_post( $post ) : null;
	}

	private static function resource_from_post( $post ) {
		if ( ! $post instanceof WP_Post ) {
			return null;
		}
		$data = get_post_meta( $post->ID, self::RESOURCE_META, true );
		$data = is_array( $data ) ? $data : array();
		$data['id'] = absint( $post->ID );
		$data['name'] = $data['name'] ?? get_the_title( $post );
		return self::normalize_resource( $data );
	}

	private static function movements( $limit ) {
		$posts = get_posts(
			array(
				'post_type'        => TAKA_PLATFORM_CPT_RESOURCE_MOVEMENT,
				'post_status'      => 'private',
				'posts_per_page'   => absint( $limit ),
				'orderby'          => 'date',
				'order'            => 'DESC',
				'suppress_filters' => true,
			)
		);
		$out = array();
		foreach ( $posts as $post ) {
			$data = get_post_meta( $post->ID, self::MOVEMENT_META, true );
			$data = is_array( $data ) ? $data : array();
			$data['id'] = absint( $post->ID );
			$out[] = self::normalize_movement( $data );
		}
		return $out;
	}

	private static function normalize_resource( $data ) {
		$data = is_array( $data ) ? $data : array();
		$category = sanitize_key( $data['category'] ?? 'other' );
		if ( ! array_key_exists( $category, self::categories() ) ) {
			$category = 'other';
		}
		$condition = sanitize_key( $data['condition'] ?? 'good' );
		if ( ! array_key_exists( $condition, self::conditions() ) ) {
			$condition = 'good';
		}
		$status = sanitize_key( $data['status'] ?? 'available' );
		if ( ! array_key_exists( $status, self::statuses() ) ) {
			$status = 'available';
		}
		return array(
			'id'                 => absint( $data['id'] ?? 0 ),
			'name'               => sanitize_text_field( $data['name'] ?? '' ),
			'category'           => $category,
			'description'        => sanitize_textarea_field( $data['description'] ?? '' ),
			'serial_number'      => sanitize_text_field( $data['serial_number'] ?? '' ),
			'quantity'           => max( 1, absint( $data['quantity'] ?? 1 ) ),
			'current_location'   => sanitize_text_field( $data['current_location'] ?? '' ),
			'responsible_person' => sanitize_text_field( $data['responsible_person'] ?? '' ),
			'condition'          => $condition,
			'status'             => $status,
			'photo_id'           => absint( $data['photo_id'] ?? 0 ),
			'notes'              => sanitize_textarea_field( $data['notes'] ?? '' ),
			'assignment'         => self::normalize_assignment( $data['assignment'] ?? array() ),
			'created_at'         => sanitize_text_field( $data['created_at'] ?? '' ),
			'updated_at'         => sanitize_text_field( $data['updated_at'] ?? '' ),
		);
	}

	private static function normalize_assignment( $data ) {
		$data = is_array( $data ) ? $data : array();
		return array(
			'tour_key'         => sanitize_key( $data['tour_key'] ?? '' ),
			'event_id'         => absint( $data['event_id'] ?? 0 ),
			'planning_item_id' => sanitize_text_field( $data['planning_item_id'] ?? '' ),
			'vehicle'          => sanitize_text_field( $data['vehicle'] ?? '' ),
			'person_id'        => absint( $data['person_id'] ?? 0 ),
		);
	}

	private static function normalize_movement( $data ) {
		$data = is_array( $data ) ? $data : array();
		return array(
			'id'                 => absint( $data['id'] ?? 0 ),
			'resource_id'        => absint( $data['resource_id'] ?? 0 ),
			'resource_name'      => sanitize_text_field( $data['resource_name'] ?? '' ),
			'from_location'      => sanitize_text_field( $data['from_location'] ?? '' ),
			'to_location'        => sanitize_text_field( $data['to_location'] ?? '' ),
			'responsible_person' => sanitize_text_field( $data['responsible_person'] ?? '' ),
			'moved_at'           => sanitize_text_field( $data['moved_at'] ?? current_time( 'mysql' ) ),
			'expected_return'    => self::sanitize_date( $data['expected_return'] ?? '' ),
			'notes'              => sanitize_textarea_field( $data['notes'] ?? '' ),
			'created_at'         => sanitize_text_field( $data['created_at'] ?? '' ),
		);
	}

	private static function normalize_requirements( $rows ) {
		$out = array();
		foreach ( (array) $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$resource_id = absint( $row['resource_id'] ?? 0 );
			if ( ! $resource_id ) {
				continue;
			}
			$out[] = array(
				'resource_id' => $resource_id,
				'quantity'    => max( 1, absint( $row['quantity'] ?? 1 ) ),
				'notes'       => sanitize_text_field( $row['notes'] ?? '' ),
			);
		}
		return $out;
	}

	private static function event_requirements( $event_id ) {
		$data = get_post_meta( absint( $event_id ), self::EVENT_REQUIREMENTS_META, true );
		return self::normalize_requirements( is_array( $data ) ? $data : array() );
	}

	private static function event_requirement_rows() {
		$resources = array();
		foreach ( self::resources() as $resource ) {
			$resources[ absint( $resource['id'] ) ] = $resource['name'];
		}
		$rows = array();
		foreach ( self::events() as $event ) {
			foreach ( self::event_requirements( $event->ID ) as $requirement ) {
				$rows[] = array(
					'event_id'      => absint( $event->ID ),
					'event_title'   => get_the_title( $event ),
					'resource_id'    => absint( $requirement['resource_id'] ),
					'resource_name'  => $resources[ absint( $requirement['resource_id'] ) ] ?? __( 'Unknown resource', 'taka-platform' ),
					'quantity'       => absint( $requirement['quantity'] ),
					'notes'          => sanitize_text_field( $requirement['notes'] ?? '' ),
				);
			}
		}
		return $rows;
	}

	private static function metrics( $resources, $movements ) {
		$today = gmdate( 'Y-m-d' );
		$metrics = array(
			'total'   => count( $resources ),
			'on_tour' => 0,
			'reserved' => 0,
			'missing' => 0,
			'overdue' => 0,
			'broken'  => 0,
		);
		foreach ( $resources as $resource ) {
			if ( '' !== (string) ( $resource['assignment']['tour_key'] ?? '' ) ) {
				$metrics['on_tour']++;
			}
			if ( 'reserved' === $resource['status'] ) {
				$metrics['reserved']++;
			}
			if ( 'missing' === $resource['status'] ) {
				$metrics['missing']++;
			}
			if ( in_array( $resource['condition'], array( 'damaged', 'broken' ), true ) ) {
				$metrics['broken']++;
			}
		}
		foreach ( $movements as $movement ) {
			if ( '' !== $movement['expected_return'] && $movement['expected_return'] < $today ) {
				$metrics['overdue']++;
			}
		}
		return $metrics;
	}

	private static function assignment_label( $assignment ) {
		$assignment = self::normalize_assignment( $assignment );
		$parts = array();
		if ( '' !== $assignment['tour_key'] ) {
			$parts[] = sprintf( __( 'Tour: %s', 'taka-platform' ), $assignment['tour_key'] );
		}
		if ( $assignment['event_id'] ) {
			$parts[] = sprintf( __( 'Event: %s', 'taka-platform' ), get_the_title( $assignment['event_id'] ) );
		}
		if ( '' !== $assignment['planning_item_id'] ) {
			$parts[] = sprintf( __( 'Planning: %s', 'taka-platform' ), $assignment['planning_item_id'] );
		}
		if ( '' !== $assignment['vehicle'] ) {
			$parts[] = sprintf( __( 'Vehicle: %s', 'taka-platform' ), $assignment['vehicle'] );
		}
		if ( $assignment['person_id'] && class_exists( 'TAKA_People_Module' ) ) {
			$person = TAKA_People_Module::person_repository()->find_by_id( $assignment['person_id'] );
			if ( $person ) {
				$parts[] = sprintf( __( 'Person: %s', 'taka-platform' ), TAKA_People_Person::full_name( $person ) ?: $person['email'] );
			}
		}
		return implode( ', ', $parts );
	}

	private static function categories() {
		return array(
			'audio'              => __( 'Audio', 'taka-platform' ),
			'video'              => __( 'Video', 'taka-platform' ),
			'marketing'          => __( 'Marketing', 'taka-platform' ),
			'training_equipment' => __( 'Training equipment', 'taka-platform' ),
			'merchandise'        => __( 'Merchandise', 'taka-platform' ),
			'furniture'          => __( 'Furniture', 'taka-platform' ),
			'vehicles'           => __( 'Vehicles', 'taka-platform' ),
			'other'              => __( 'Other', 'taka-platform' ),
		);
	}

	private static function conditions() {
		return array(
			'excellent' => __( 'Excellent', 'taka-platform' ),
			'good'      => __( 'Good', 'taka-platform' ),
			'worn'      => __( 'Worn', 'taka-platform' ),
			'damaged'   => __( 'Damaged', 'taka-platform' ),
			'broken'    => __( 'Broken', 'taka-platform' ),
		);
	}

	private static function statuses() {
		return array(
			'available'   => __( 'Available', 'taka-platform' ),
			'assigned'    => __( 'Assigned', 'taka-platform' ),
			'reserved'    => __( 'Reserved', 'taka-platform' ),
			'missing'     => __( 'Missing', 'taka-platform' ),
			'maintenance' => __( 'Maintenance', 'taka-platform' ),
			'retired'     => __( 'Retired', 'taka-platform' ),
		);
	}

	private static function events() {
		return get_posts(
			array(
				'post_type'        => TAKA_PLATFORM_CPT_EVENT,
				'post_status'      => array( 'publish', 'draft', 'future', 'private' ),
				'posts_per_page'   => 250,
				'orderby'          => 'title',
				'order'            => 'ASC',
				'suppress_filters' => true,
			)
		);
	}

	private static function sanitize_date( $date ) {
		$date = sanitize_text_field( $date );
		return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ? $date : '';
	}
}
