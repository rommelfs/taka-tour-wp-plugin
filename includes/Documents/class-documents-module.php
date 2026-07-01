<?php
/**
 * Private document library, knowledge base and certificate architecture.
 *
 * Documents and knowledge articles are reusable internal assets. Certificates
 * are scaffolded as templates now; PDF generation and QR verification belong
 * to later phases.
 */

defined( 'ABSPATH' ) || exit;

class TAKA_Documents_Module {
	const ADMIN_PAGE_SLUG      = 'taka-platform-documents';
	const DOCUMENT_META        = '_taka_document';
	const KNOWLEDGE_META       = '_taka_knowledge';
	const CERTIFICATE_META     = '_taka_certificate_template';
	const SAVE_DOCUMENT_ACTION = 'taka_documents_save_document';
	const SAVE_KNOWLEDGE_ACTION = 'taka_documents_save_knowledge';
	const SAVE_CERTIFICATE_ACTION = 'taka_documents_save_certificate';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_post_types' ), 0 );
		add_action( 'admin_menu', array( __CLASS__, 'register_admin_menu' ), 25 );
		add_action( 'admin_init', array( __CLASS__, 'ensure_capabilities' ) );
		add_action( 'admin_post_' . self::SAVE_DOCUMENT_ACTION, array( __CLASS__, 'handle_save_document' ) );
		add_action( 'admin_post_' . self::SAVE_KNOWLEDGE_ACTION, array( __CLASS__, 'handle_save_knowledge' ) );
		add_action( 'admin_post_' . self::SAVE_CERTIFICATE_ACTION, array( __CLASS__, 'handle_save_certificate' ) );
	}

	public static function register_post_types() {
		self::register_private_post_type( TAKA_PLATFORM_CPT_DOCUMENT, __( 'Documents', 'taka-platform' ), __( 'Document', 'taka-platform' ) );
		self::register_private_post_type( TAKA_PLATFORM_CPT_KNOWLEDGE, __( 'Knowledge Articles', 'taka-platform' ), __( 'Knowledge Article', 'taka-platform' ) );
		self::register_private_post_type( TAKA_PLATFORM_CPT_CERTIFICATE_TEMPLATE, __( 'Certificate Templates', 'taka-platform' ), __( 'Certificate Template', 'taka-platform' ) );
	}

	public static function register_admin_menu() {
		add_submenu_page(
			'taka-platform',
			__( 'Documents', 'taka-platform' ),
			__( 'Documents', 'taka-platform' ),
			'view_taka_documents',
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
			'view_taka_documents',
			'manage_taka_documents',
		);
	}

	public static function admin_url( $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => self::ADMIN_PAGE_SLUG ), $args ), admin_url( 'admin.php' ) );
	}

	public static function handle_save_document() {
		if ( ! current_user_can( 'manage_taka_documents' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'taka-platform' ) );
		}
		check_admin_referer( self::SAVE_DOCUMENT_ACTION, '_wpnonce' );
		$raw = isset( $_POST['document'] ) && is_array( $_POST['document'] ) ? wp_unslash( $_POST['document'] ) : array();
		self::redirect_after_save( self::save_item( TAKA_PLATFORM_CPT_DOCUMENT, self::DOCUMENT_META, self::normalize_document( $raw ), __( 'Document', 'taka-platform' ) ), 'documents' );
	}

	public static function handle_save_knowledge() {
		if ( ! current_user_can( 'manage_taka_documents' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'taka-platform' ) );
		}
		check_admin_referer( self::SAVE_KNOWLEDGE_ACTION, '_wpnonce' );
		$raw = isset( $_POST['knowledge'] ) && is_array( $_POST['knowledge'] ) ? wp_unslash( $_POST['knowledge'] ) : array();
		self::redirect_after_save( self::save_item( TAKA_PLATFORM_CPT_KNOWLEDGE, self::KNOWLEDGE_META, self::normalize_knowledge( $raw ), __( 'Knowledge article', 'taka-platform' ) ), 'knowledge' );
	}

	public static function handle_save_certificate() {
		if ( ! current_user_can( 'manage_taka_documents' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'taka-platform' ) );
		}
		check_admin_referer( self::SAVE_CERTIFICATE_ACTION, '_wpnonce' );
		$raw = isset( $_POST['certificate'] ) && is_array( $_POST['certificate'] ) ? wp_unslash( $_POST['certificate'] ) : array();
		self::redirect_after_save( self::save_item( TAKA_PLATFORM_CPT_CERTIFICATE_TEMPLATE, self::CERTIFICATE_META, self::normalize_certificate_template( $raw ), __( 'Certificate template', 'taka-platform' ) ), 'certificates' );
	}

	public static function render_admin_page() {
		if ( ! current_user_can( 'view_taka_documents' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'taka-platform' ) );
		}

		$section = sanitize_key( $_GET['section'] ?? 'documents' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! in_array( $section, array( 'documents', 'knowledge', 'certificates', 'search' ), true ) ) {
			$section = 'documents';
		}
		echo '<div class="wrap taka-documents-admin"><h1>' . esc_html__( 'Documents & Knowledge', 'taka-platform' ) . '</h1>';
		echo '<p class="description">' . esc_html__( 'Reusable private assets attached to tours, events, people and operations.', 'taka-platform' ) . '</p>';
		self::render_notices();
		self::render_nav( $section );
		if ( 'knowledge' === $section ) {
			self::render_knowledge_section();
		} elseif ( 'certificates' === $section ) {
			self::render_certificates_section();
		} elseif ( 'search' === $section ) {
			self::render_search_section();
		} else {
			self::render_documents_section();
		}
		echo '</div>';
	}

	private static function register_private_post_type( $post_type, $name, $singular ) {
		register_post_type(
			$post_type,
			array(
				'labels'              => array( 'name' => $name, 'singular_name' => $singular ),
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

	private static function render_notices() {
		if ( ! empty( $_GET['documents_error'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-error"><p>' . esc_html( wp_unslash( $_GET['documents_error'] ) ) . '</p></div>'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}
		if ( ! empty( $_GET['documents_saved'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-success"><p>' . esc_html__( 'Item saved.', 'taka-platform' ) . '</p></div>';
		}
	}

	private static function render_nav( $current ) {
		echo '<nav class="nav-tab-wrapper taka-documents-tabs">';
		foreach ( array( 'documents' => __( 'Documents', 'taka-platform' ), 'knowledge' => __( 'Knowledge Base', 'taka-platform' ), 'certificates' => __( 'Certificates', 'taka-platform' ), 'search' => __( 'Search', 'taka-platform' ) ) as $section => $label ) {
			echo '<a class="nav-tab ' . ( $current === $section ? 'nav-tab-active' : '' ) . '" href="' . esc_url( self::admin_url( array( 'section' => $section ) ) ) . '">' . esc_html( $label ) . '</a>';
		}
		echo '</nav>';
	}

	private static function render_documents_section() {
		$documents = self::items( TAKA_PLATFORM_CPT_DOCUMENT, self::DOCUMENT_META, 'document' );
		?>
		<div class="taka-admin-grid taka-admin-grid--two">
			<section class="taka-admin-panel">
				<h2><?php echo esc_html__( 'Add document', 'taka-platform' ); ?></h2>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="<?php echo esc_attr( self::SAVE_DOCUMENT_ACTION ); ?>">
					<?php wp_nonce_field( self::SAVE_DOCUMENT_ACTION, '_wpnonce' ); ?>
					<?php self::render_document_fields(); ?>
					<p class="submit"><button class="button button-primary" type="submit"><?php echo esc_html__( 'Save document', 'taka-platform' ); ?></button></p>
				</form>
			</section>
			<section class="taka-admin-panel">
				<h2><?php echo esc_html__( 'Document Library', 'taka-platform' ); ?></h2>
				<?php self::render_item_table( $documents, 'document' ); ?>
			</section>
		</div>
		<?php
	}

	private static function render_knowledge_section() {
		$articles = self::items( TAKA_PLATFORM_CPT_KNOWLEDGE, self::KNOWLEDGE_META, 'knowledge' );
		?>
		<div class="taka-admin-grid taka-admin-grid--two">
			<section class="taka-admin-panel">
				<h2><?php echo esc_html__( 'Add knowledge article', 'taka-platform' ); ?></h2>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="<?php echo esc_attr( self::SAVE_KNOWLEDGE_ACTION ); ?>">
					<?php wp_nonce_field( self::SAVE_KNOWLEDGE_ACTION, '_wpnonce' ); ?>
					<?php self::render_knowledge_fields(); ?>
					<p class="submit"><button class="button button-primary" type="submit"><?php echo esc_html__( 'Save article', 'taka-platform' ); ?></button></p>
				</form>
			</section>
			<section class="taka-admin-panel">
				<h2><?php echo esc_html__( 'Knowledge Base', 'taka-platform' ); ?></h2>
				<?php self::render_item_table( $articles, 'knowledge' ); ?>
			</section>
		</div>
		<?php
	}

	private static function render_certificates_section() {
		$templates = self::items( TAKA_PLATFORM_CPT_CERTIFICATE_TEMPLATE, self::CERTIFICATE_META, 'certificate' );
		?>
		<div class="taka-admin-grid taka-admin-grid--two">
			<section class="taka-admin-panel">
				<h2><?php echo esc_html__( 'Certificate template scaffold', 'taka-platform' ); ?></h2>
				<p class="description"><?php echo esc_html__( 'Prepare certificate metadata now. PDF rendering, QR verification and digital signatures are future phases.', 'taka-platform' ); ?></p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="<?php echo esc_attr( self::SAVE_CERTIFICATE_ACTION ); ?>">
					<?php wp_nonce_field( self::SAVE_CERTIFICATE_ACTION, '_wpnonce' ); ?>
					<?php self::render_certificate_fields(); ?>
					<p class="submit"><button class="button button-primary" type="submit"><?php echo esc_html__( 'Save certificate template', 'taka-platform' ); ?></button></p>
				</form>
			</section>
			<section class="taka-admin-panel">
				<h2><?php echo esc_html__( 'Certificate templates', 'taka-platform' ); ?></h2>
				<?php self::render_item_table( $templates, 'certificate' ); ?>
			</section>
		</div>
		<?php
	}

	private static function render_search_section() {
		$query = sanitize_text_field( wp_unslash( $_GET['q'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$results = self::search_items( $query );
		?>
		<form class="taka-documents-search" method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
			<input type="hidden" name="page" value="<?php echo esc_attr( self::ADMIN_PAGE_SLUG ); ?>">
			<input type="hidden" name="section" value="search">
			<input type="search" name="q" value="<?php echo esc_attr( $query ); ?>" placeholder="<?php echo esc_attr__( 'Search documents, knowledge and tags', 'taka-platform' ); ?>">
			<?php submit_button( __( 'Search', 'taka-platform' ), '', '', false ); ?>
		</form>
		<section class="taka-admin-panel taka-admin-panel--full">
			<h2><?php echo esc_html__( 'Search results', 'taka-platform' ); ?></h2>
			<?php self::render_item_table( $results, 'mixed' ); ?>
		</section>
		<?php
	}

	private static function render_document_fields() {
		self::text_input( 'document[title]', __( 'Title', 'taka-platform' ), true );
		self::textarea_input( 'document[description]', __( 'Description', 'taka-platform' ) );
		self::select_input( 'document[category]', __( 'Category', 'taka-platform' ), self::document_categories() );
		self::text_input( 'document[attachment_id]', __( 'Attachment ID', 'taka-platform' ), false, 'number' );
		self::text_input( 'document[owner]', __( 'Owner', 'taka-platform' ) );
		self::select_input( 'document[visibility]', __( 'Visibility', 'taka-platform' ), self::visibility_options() );
		self::text_input( 'document[tags]', __( 'Tags', 'taka-platform' ) );
		self::render_assignment_fields( 'document' );
	}

	private static function render_knowledge_fields() {
		self::text_input( 'knowledge[title]', __( 'Title', 'taka-platform' ), true );
		self::textarea_input( 'knowledge[description]', __( 'Summary', 'taka-platform' ) );
		self::textarea_input( 'knowledge[content]', __( 'Article content', 'taka-platform' ), 8 );
		self::select_input( 'knowledge[category]', __( 'Category', 'taka-platform' ), self::knowledge_categories() );
		self::text_input( 'knowledge[owner]', __( 'Owner', 'taka-platform' ) );
		self::select_input( 'knowledge[visibility]', __( 'Visibility', 'taka-platform' ), self::visibility_options() );
		self::text_input( 'knowledge[tags]', __( 'Tags', 'taka-platform' ) );
		self::render_assignment_fields( 'knowledge' );
	}

	private static function render_certificate_fields() {
		self::text_input( 'certificate[title]', __( 'Title', 'taka-platform' ), true );
		self::select_input( 'certificate[certificate_type]', __( 'Certificate type', 'taka-platform' ), self::certificate_types() );
		self::textarea_input( 'certificate[description]', __( 'Description', 'taka-platform' ) );
		self::textarea_input( 'certificate[template_notes]', __( 'Template notes / future layout instructions', 'taka-platform' ), 5 );
		self::text_input( 'certificate[owner]', __( 'Owner', 'taka-platform' ) );
		self::select_input( 'certificate[visibility]', __( 'Visibility', 'taka-platform' ), self::visibility_options() );
		self::text_input( 'certificate[tags]', __( 'Tags', 'taka-platform' ) );
		self::render_assignment_fields( 'certificate' );
	}

	private static function render_assignment_fields( $prefix ) {
		echo '<fieldset class="taka-documents-assignments"><legend>' . esc_html__( 'Assignments', 'taka-platform' ) . '</legend>';
		foreach ( self::assignment_fields() as $field => $label ) {
			self::text_input( $prefix . '[assignments][' . $field . ']', $label );
		}
		echo '</fieldset>';
	}

	private static function render_item_table( $items, $type ) {
		if ( empty( $items ) ) {
			echo '<p class="description">' . esc_html__( 'No items found.', 'taka-platform' ) . '</p>';
			return;
		}
		?>
		<table class="widefat striped">
			<thead><tr><th><?php echo esc_html__( 'Title', 'taka-platform' ); ?></th><th><?php echo esc_html__( 'Type', 'taka-platform' ); ?></th><th><?php echo esc_html__( 'Category', 'taka-platform' ); ?></th><th><?php echo esc_html__( 'Visibility', 'taka-platform' ); ?></th><th><?php echo esc_html__( 'Assignments', 'taka-platform' ); ?></th><th><?php echo esc_html__( 'Tags', 'taka-platform' ); ?></th></tr></thead>
			<tbody>
				<?php foreach ( $items as $item ) : ?>
					<tr>
						<td><strong><?php echo esc_html( $item['title'] ); ?></strong><br><span class="description"><?php echo esc_html( $item['description'] ); ?></span></td>
						<td><?php echo esc_html( ucfirst( $item['item_type'] ?? $type ) ); ?></td>
						<td><?php echo esc_html( $item['category_label'] ); ?></td>
						<td><?php echo esc_html( self::visibility_options()[ $item['visibility'] ] ?? $item['visibility'] ); ?></td>
						<td><?php echo esc_html( self::assignment_summary( $item['assignments'] ) ); ?></td>
						<td><?php echo esc_html( implode( ', ', (array) $item['tags'] ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	private static function save_item( $post_type, $meta_key, $data, $fallback_title ) {
		if ( '' === $data['title'] ) {
			return new WP_Error( 'taka_documents_missing_title', __( 'Title is required.', 'taka-platform' ) );
		}
		$post_data = array( 'post_type' => $post_type, 'post_status' => 'private', 'post_title' => $data['title'] ?: $fallback_title );
		if ( ! empty( $data['id'] ) ) {
			$post_data['ID'] = absint( $data['id'] );
			$result = wp_update_post( $post_data, true );
			$post_id = absint( $data['id'] );
		} else {
			$result = wp_insert_post( $post_data, true );
			$post_id = is_wp_error( $result ) ? 0 : absint( $result );
		}
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$data['id'] = $post_id;
		$data['updated_at'] = current_time( 'mysql' );
		if ( empty( $data['created_at'] ) ) {
			$data['created_at'] = get_post_time( 'Y-m-d H:i:s', false, $post_id );
		}
		update_post_meta( $post_id, $meta_key, $data );
		return $data;
	}

	private static function redirect_after_save( $result, $section ) {
		$args = array( 'section' => $section );
		if ( is_wp_error( $result ) ) {
			$args['documents_error'] = $result->get_error_message();
		} else {
			$args['documents_saved'] = '1';
		}
		wp_safe_redirect( self::admin_url( $args ) );
		exit;
	}

	private static function items( $post_type, $meta_key, $type ) {
		$posts = get_posts( array( 'post_type' => $post_type, 'post_status' => 'private', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC', 'suppress_filters' => true ) );
		return array_values( array_filter( array_map( static function ( $post ) use ( $meta_key, $type ) {
			$data = get_post_meta( $post->ID, $meta_key, true );
			$data = is_array( $data ) ? $data : array();
			$data['id'] = absint( $post->ID );
			$data['item_type'] = $type;
			if ( 'document' === $type ) {
				return self::normalize_document( $data );
			}
			if ( 'knowledge' === $type ) {
				return self::normalize_knowledge( $data );
			}
			return self::normalize_certificate_template( $data );
		}, $posts ) ) );
	}

	private static function search_items( $query ) {
		$query = strtolower( trim( (string) $query ) );
		$items = array_merge(
			self::items( TAKA_PLATFORM_CPT_DOCUMENT, self::DOCUMENT_META, 'document' ),
			self::items( TAKA_PLATFORM_CPT_KNOWLEDGE, self::KNOWLEDGE_META, 'knowledge' )
		);
		if ( '' === $query ) {
			return $items;
		}
		return array_values( array_filter( $items, static function ( $item ) use ( $query ) {
			$haystack = strtolower( implode( ' ', array( $item['title'] ?? '', $item['description'] ?? '', $item['content'] ?? '', $item['category'] ?? '', implode( ' ', (array) ( $item['tags'] ?? array() ) ), self::assignment_summary( $item['assignments'] ?? array() ) ) ) );
			return false !== strpos( $haystack, $query );
		} ) );
	}

	private static function normalize_document( $data ) {
		$data = self::normalize_common_item( $data, 'document', self::document_categories() );
		$data['attachment_id'] = absint( $data['attachment_id'] ?? 0 );
		return $data;
	}

	private static function normalize_knowledge( $data ) {
		$data = self::normalize_common_item( $data, 'knowledge', self::knowledge_categories() );
		$data['content'] = sanitize_textarea_field( $data['content'] ?? '' );
		return $data;
	}

	private static function normalize_certificate_template( $data ) {
		$data = self::normalize_common_item( $data, 'certificate', self::certificate_types(), 'attendance' );
		$data['certificate_type'] = sanitize_key( $data['certificate_type'] ?? $data['category'] ?? 'attendance' );
		if ( ! array_key_exists( $data['certificate_type'], self::certificate_types() ) ) {
			$data['certificate_type'] = 'attendance';
		}
		$data['category'] = $data['certificate_type'];
		$data['category_label'] = self::certificate_types()[ $data['certificate_type'] ];
		$data['template_notes'] = sanitize_textarea_field( $data['template_notes'] ?? '' );
		return $data;
	}

	private static function normalize_common_item( $data, $type, $categories, $default_category = '' ) {
		$data = is_array( $data ) ? $data : array();
		$category = sanitize_key( $data['category'] ?? $default_category );
		if ( '' === $category || ! array_key_exists( $category, $categories ) ) {
			$category = array_key_first( $categories );
		}
		$visibility = sanitize_key( $data['visibility'] ?? 'organizer_only' );
		if ( ! array_key_exists( $visibility, self::visibility_options() ) ) {
			$visibility = 'organizer_only';
		}
		return array(
			'id'             => absint( $data['id'] ?? 0 ),
			'item_type'      => $type,
			'title'          => sanitize_text_field( $data['title'] ?? '' ),
			'description'    => sanitize_textarea_field( $data['description'] ?? '' ),
			'category'       => $category,
			'category_label' => $categories[ $category ] ?? $category,
			'owner'          => sanitize_text_field( $data['owner'] ?? '' ),
			'visibility'     => $visibility,
			'tags'           => self::normalize_tags( $data['tags'] ?? array() ),
			'assignments'    => self::normalize_assignments( $data['assignments'] ?? array() ),
			'created_at'     => sanitize_text_field( $data['created_at'] ?? '' ),
			'updated_at'     => sanitize_text_field( $data['updated_at'] ?? '' ),
		);
	}

	private static function normalize_assignments( $assignments ) {
		$assignments = is_array( $assignments ) ? $assignments : array();
		return array(
			'tour_key'         => sanitize_key( $assignments['tour_key'] ?? '' ),
			'event_id'         => absint( $assignments['event_id'] ?? 0 ),
			'person_id'        => absint( $assignments['person_id'] ?? 0 ),
			'organizer_id'     => absint( $assignments['organizer_id'] ?? 0 ),
			'venue_id'         => absint( $assignments['venue_id'] ?? 0 ),
			'planning_item_id' => sanitize_text_field( $assignments['planning_item_id'] ?? '' ),
			'order_id'         => absint( $assignments['order_id'] ?? 0 ),
			'volunteer_role'   => sanitize_text_field( $assignments['volunteer_role'] ?? '' ),
		);
	}

	private static function normalize_tags( $tags ) {
		if ( ! is_array( $tags ) ) {
			$tags = preg_split( '/\s*,\s*/', (string) $tags );
		}
		return array_values( array_unique( array_filter( array_map( 'sanitize_text_field', (array) $tags ) ) ) );
	}

	private static function assignment_summary( $assignments ) {
		$assignments = self::normalize_assignments( $assignments );
		$parts = array();
		if ( '' !== $assignments['tour_key'] ) { $parts[] = sprintf( __( 'Tour: %s', 'taka-platform' ), $assignments['tour_key'] ); }
		if ( $assignments['event_id'] ) { $parts[] = sprintf( __( 'Event: %s', 'taka-platform' ), get_the_title( $assignments['event_id'] ) ); }
		if ( $assignments['person_id'] && class_exists( 'TAKA_People_Module' ) ) {
			$person = TAKA_People_Module::person_repository()->find_by_id( $assignments['person_id'] );
			$parts[] = sprintf( __( 'Person: %s', 'taka-platform' ), $person ? TAKA_People_Person::full_name( $person ) : '#' . $assignments['person_id'] );
		}
		if ( $assignments['organizer_id'] ) { $parts[] = sprintf( __( 'Organizer: %s', 'taka-platform' ), get_the_title( $assignments['organizer_id'] ) ); }
		if ( $assignments['venue_id'] ) { $parts[] = sprintf( __( 'Venue: %s', 'taka-platform' ), get_the_title( $assignments['venue_id'] ) ); }
		if ( '' !== $assignments['planning_item_id'] ) { $parts[] = sprintf( __( 'Planning: %s', 'taka-platform' ), $assignments['planning_item_id'] ); }
		if ( $assignments['order_id'] ) { $parts[] = sprintf( __( 'Order: #%d', 'taka-platform' ), $assignments['order_id'] ); }
		if ( '' !== $assignments['volunteer_role'] ) { $parts[] = sprintf( __( 'Role: %s', 'taka-platform' ), $assignments['volunteer_role'] ); }
		return implode( ', ', $parts );
	}

	private static function text_input( $name, $label, $required = false, $type = 'text' ) {
		echo '<label class="taka-documents-field"><span>' . esc_html( $label ) . '</span><input type="' . esc_attr( $type ) . '" name="' . esc_attr( $name ) . '"' . ( $required ? ' required' : '' ) . '></label>';
	}

	private static function textarea_input( $name, $label, $rows = 3 ) {
		echo '<label class="taka-documents-field"><span>' . esc_html( $label ) . '</span><textarea name="' . esc_attr( $name ) . '" rows="' . esc_attr( absint( $rows ) ) . '"></textarea></label>';
	}

	private static function select_input( $name, $label, $choices ) {
		echo '<label class="taka-documents-field"><span>' . esc_html( $label ) . '</span><select name="' . esc_attr( $name ) . '">';
		foreach ( $choices as $value => $choice_label ) {
			echo '<option value="' . esc_attr( $value ) . '">' . esc_html( $choice_label ) . '</option>';
		}
		echo '</select></label>';
	}

	private static function document_categories() {
		return array(
			'travel_itinerary'    => __( 'Travel itinerary', 'taka-platform' ),
			'hotel_confirmation' => __( 'Hotel confirmation', 'taka-platform' ),
			'flight_ticket'      => __( 'Flight ticket', 'taka-platform' ),
			'visa'               => __( 'Visa', 'taka-platform' ),
			'insurance'          => __( 'Insurance', 'taka-platform' ),
			'contract'           => __( 'Contract', 'taka-platform' ),
			'venue_agreement'    => __( 'Venue agreement', 'taka-platform' ),
			'invoice'            => __( 'Invoice', 'taka-platform' ),
			'photo'              => __( 'Photo', 'taka-platform' ),
			'logo'               => __( 'Logo', 'taka-platform' ),
			'press_material'     => __( 'Press material', 'taka-platform' ),
			'consent_form'       => __( 'Consent form', 'taka-platform' ),
			'liability_waiver'   => __( 'Liability waiver', 'taka-platform' ),
			'medical_declaration' => __( 'Medical declaration', 'taka-platform' ),
			'minor_consent'      => __( 'Minor consent', 'taka-platform' ),
			'other'              => __( 'Other', 'taka-platform' ),
		);
	}

	private static function knowledge_categories() {
		return array(
			'organize_seminar'    => __( 'How to organize a seminar', 'taka-platform' ),
			'event_checklist'     => __( 'Checklist before event', 'taka-platform' ),
			'travel_guidelines'   => __( 'Travel guidelines', 'taka-platform' ),
			'volunteer_handbook'  => __( 'Volunteer handbook', 'taka-platform' ),
			'emergency_procedure' => __( 'Emergency procedures', 'taka-platform' ),
			'other'               => __( 'Other', 'taka-platform' ),
		);
	}

	private static function certificate_types() {
		return array(
			'attendance' => __( 'Attendance certificate', 'taka-platform' ),
			'instructor' => __( 'Instructor certificate', 'taka-platform' ),
			'volunteer'  => __( 'Volunteer certificate', 'taka-platform' ),
			'speaker'    => __( 'Speaker certificate', 'taka-platform' ),
		);
	}

	private static function visibility_options() {
		return array(
			'public'         => __( 'Public', 'taka-platform' ),
			'organizer_only' => __( 'Organizer only', 'taka-platform' ),
			'volunteer'      => __( 'Volunteer', 'taka-platform' ),
			'admin_only'     => __( 'Admin only', 'taka-platform' ),
			'specific_users' => __( 'Specific users', 'taka-platform' ),
		);
	}

	private static function assignment_fields() {
		return array(
			'tour_key'         => __( 'Tour', 'taka-platform' ),
			'event_id'         => __( 'Event ID', 'taka-platform' ),
			'person_id'        => __( 'Person ID', 'taka-platform' ),
			'organizer_id'     => __( 'Organizer ID', 'taka-platform' ),
			'venue_id'         => __( 'Venue ID', 'taka-platform' ),
			'planning_item_id' => __( 'Planning item ID', 'taka-platform' ),
			'order_id'         => __( 'Order ID', 'taka-platform' ),
			'volunteer_role'   => __( 'Volunteer role', 'taka-platform' ),
		);
	}
}
