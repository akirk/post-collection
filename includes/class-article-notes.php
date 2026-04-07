<?php
/**
 * Article Notes
 *
 * Manages article notes/reviews as a custom post type.
 *
 * @package Post_Collection
 */

namespace PostCollection;

defined( 'ABSPATH' ) || exit;

/**
 * Class for managing article notes and reviews.
 *
 * @since 1.1.0
 */
class Article_Notes {
	const POST_TYPE = 'post_collection_note';
	const NOTE_ID_META = 'post_collection_note_id';
	const RATING_META = 'post_collection_rating';
	const STATUS_META = 'post_collection_status';

	const STATUS_UNREAD = 'unread';
	const STATUS_READ = 'read';
	const STATUS_SKIPPED = 'skipped';
	const STATUS_ARCHIVED = 'archived';

	/**
	 * Reference to the main plugin instance.
	 *
	 * @var Post_Collection
	 */
	private $plugin;

	/**
	 * Constructor.
	 *
	 * @param Post_Collection $plugin The main plugin instance.
	 */
	public function __construct( Post_Collection $plugin ) {
		$this->plugin = $plugin;
		$this->register_hooks();
	}

	/**
	 * Register WordPress hooks.
	 */
	private function register_hooks() {
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'wp_ajax_post_collection_save_note', array( $this, 'ajax_save_note' ) );
		add_action( 'wp_ajax_post_collection_get_notes', array( $this, 'ajax_get_notes' ) );
		add_action( 'wp_ajax_post_collection_load_more_pending', array( $this, 'ajax_load_more_pending' ) );
		add_action( 'wp_ajax_post_collection_create_post_from_notes', array( $this, 'ajax_create_post_from_notes' ) );
		add_action( 'wp_ajax_post_collection_dismiss_old_articles', array( $this, 'ajax_dismiss_old_articles' ) );
		add_action( 'wp_ajax_post_collection_random_remembered', array( $this, 'ajax_random_remembered' ) );
		add_action( 'wp_ajax_post_collection_save_title', array( $this, 'ajax_save_title' ) );
		add_action( 'before_delete_post', array( $this, 'maybe_delete_note' ) );
		add_action( 'wp_dashboard_setup', array( $this, 'register_dashboard_widget' ) );
		add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
		add_action( 'friends_post_footer_first', array( $this, 'render_frontend_notes' ) );
	}

	/**
	 * Register admin menu page.
	 */
	public function register_admin_menu() {
		add_submenu_page(
			'edit.php?post_type=' . Post_Collection::CPT,
			__( 'Collected Posts Notes', 'post-collection' ),
			__( 'Notes', 'post-collection' ),
			'edit_posts',
			'post-collection-article-notes',
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Render the admin page.
	 */
	public function render_admin_page() {
		$this->enqueue_admin_page_assets();

		// Get current filter.
		$status_filter = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : 'all';
		$paged = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;
		$per_page = 20;

		// Get notes based on filter.
		$notes_data = $this->get_all_notes( $status_filter, $per_page, ( $paged - 1 ) * $per_page );

		Post_Collection::template_loader()->get_template_part(
			'admin/article-notes-page',
			null,
			array(
				'notes'         => $notes_data['notes'],
				'total'         => $notes_data['total'],
				'paged'         => $paged,
				'per_page'      => $per_page,
				'status_filter' => $status_filter,
				'statuses'      => self::get_statuses(),
				'nonce'         => wp_create_nonce( 'post-collection-article-notes' ),
			)
		);
	}

	/**
	 * Render the notes UI on the Friends frontend single post view.
	 */
	public function render_frontend_notes() {
		if ( ! is_single() || ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		$post = get_post();
		if ( ! $post ) {
			return;
		}

		$article = $this->prepare_article_data( $post );
		$statuses = self::get_statuses();
		$nonce = wp_create_nonce( 'post-collection-article-notes' );

		$version = POST_COLLECTION_VERSION;
		wp_enqueue_style(
			'post-collection-article-notes',
			plugins_url( 'assets/css/article-notes.css', dirname( __FILE__ ) ),
			array(),
			$version
		);
		wp_enqueue_script(
			'post-collection-article-notes',
			plugins_url( 'assets/js/article-notes.js', dirname( __FILE__ ) ),
			array( 'jquery' ),
			$version,
			true
		);
		wp_localize_script(
			'post-collection-article-notes',
			'postCollectionArticleNotes',
			array(
				'ajaxurl'  => admin_url( 'admin-ajax.php' ),
				'nonce'    => $nonce,
				'statuses' => $statuses,
				'i18n'     => array(
					'saving'      => __( 'Saving...', 'post-collection' ),
					'saved'       => __( 'Saved', 'post-collection' ),
					'error'       => __( 'Error saving', 'post-collection' ),
					'showArticle' => __( 'Show article', 'post-collection' ),
				),
			)
		);

		Post_Collection::template_loader()->get_template_part(
			'frontend/article-notes',
			null,
			array(
				'article'  => $article,
				'statuses' => $statuses,
				'nonce'    => $nonce,
			)
		);
	}

	/**
	 * Enqueue assets for the admin page.
	 */
	private function enqueue_admin_page_assets() {
		$version = POST_COLLECTION_VERSION;

		wp_enqueue_style(
			'post-collection-article-notes-admin',
			plugins_url( 'assets/css/article-notes-admin.css', dirname( __FILE__ ) ),
			array(),
			$version
		);

		wp_enqueue_script(
			'post-collection-article-notes',
			plugins_url( 'assets/js/article-notes.js', dirname( __FILE__ ) ),
			array( 'jquery' ),
			$version,
			true
		);

		wp_localize_script(
			'post-collection-article-notes',
			'postCollectionArticleNotes',
			array(
				'ajaxurl'  => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'post-collection-article-notes' ),
				'statuses' => self::get_statuses(),
				'i18n'     => array(
					'saving'  => __( 'Saving...', 'post-collection' ),
					'saved'   => __( 'Saved', 'post-collection' ),
					'error'   => __( 'Error saving', 'post-collection' ),
					'loading' => __( 'Loading...', 'post-collection' ),
				),
			)
		);
	}

	/**
	 * Get all notes with optional filtering.
	 *
	 * @param string $status Status filter ('all', 'unread', 'read', 'skipped', 'archived').
	 * @param int    $limit  Number of items per page.
	 * @param int    $offset Offset for pagination.
	 * @return array Array with 'notes' and 'total' keys.
	 */
	public function get_all_notes( $status = 'all', $limit = 20, $offset = 0 ) {
		$meta_query = array();

		if ( 'all' !== $status && in_array( $status, self::get_all_status_values(), true ) ) {
			$meta_query[] = array(
				'key'   => self::STATUS_META,
				'value' => $status,
			);
		}

		$args = array(
			'post_type'      => self::POST_TYPE,
			'posts_per_page' => $limit,
			'offset'         => $offset,
			'post_status'    => 'publish',
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		if ( ! empty( $meta_query ) ) {
			$args['meta_query'] = $meta_query;
		}

		$query = new \WP_Query( $args );
		$notes = array();

		foreach ( $query->posts as $note_post ) {
			$parent_id = $note_post->post_parent;
			$parent = get_post( $parent_id );

			if ( ! $parent ) {
				continue;
			}

			$notes[] = array(
				'id'          => $parent_id,
				'note_id'     => $note_post->ID,
				'title'       => get_the_title( $parent ),
				'permalink'   => get_permalink( $parent ),
				'author'      => $this->plugin->get_post_author_name( $parent ),
				'status'      => get_post_meta( $note_post->ID, self::STATUS_META, true ) ?: self::STATUS_UNREAD,
				'rating'      => (int) get_post_meta( $note_post->ID, self::RATING_META, true ),
				'notes'       => $note_post->post_content,
				'updated'     => $note_post->post_modified,
			);
		}

		// Get total count.
		$count_args = array(
			'post_type'      => self::POST_TYPE,
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			'fields'         => 'ids',
		);

		if ( ! empty( $meta_query ) ) {
			$count_args['meta_query'] = $meta_query;
		}

		$total = count( get_posts( $count_args ) );

		return array(
			'notes' => $notes,
			'total' => $total,
		);
	}

	/**
	 * Register the article notes custom post type.
	 */
	public function register_post_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'          => __( 'Article Notes', 'post-collection' ),
					'singular_name' => __( 'Article Note', 'post-collection' ),
				),
				'public'              => false,
				'show_ui'             => false,
				'show_in_menu'        => false,
				'show_in_rest'        => false,
				'exclude_from_search' => true,
				'publicly_queryable'  => false,
				'supports'            => array( 'editor' ),
				'hierarchical'        => false,
				'can_export'          => true,
			)
		);
	}

	/**
	 * Register the dashboard widget.
	 */
	public function register_dashboard_widget() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		wp_add_dashboard_widget(
			'post_collection_article_notes',
			__( 'Collected Posts Notes', 'post-collection' ),
			array( $this, 'render_dashboard_widget' ),
			array( $this, 'render_dashboard_widget_config' )
		);
	}

	/**
	 * Get widget options for the current user.
	 *
	 * @return array Widget options.
	 */
	private function get_widget_options() {
		$defaults = array(
			'pending_count'      => 1,
			'reviewed_count'     => 5,
			'show_random_note'   => true,
			'show_tabs'          => true,
		);
		$options = get_user_option( 'post_collection_widget_options' );
		if ( ! is_array( $options ) ) {
			$options = array();
		}
		return array_merge( $defaults, $options );
	}

	/**
	 * Render the dashboard widget configuration form.
	 */
	public function render_dashboard_widget_config() {
		$options = $this->get_widget_options();

		if ( isset( $_POST['post_collection_widget_config_nonce'] )
			&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['post_collection_widget_config_nonce'] ) ), 'post_collection_widget_config' )
		) {
			$options['pending_count']    = isset( $_POST['pending_count'] ) ? max( 1, (int) $_POST['pending_count'] ) : 1;
			$options['reviewed_count']   = isset( $_POST['reviewed_count'] ) ? max( 1, (int) $_POST['reviewed_count'] ) : 5;
			$options['show_random_note'] = ! empty( $_POST['show_random_note'] );
			$options['show_tabs']        = ! empty( $_POST['show_tabs'] );
			update_user_option( get_current_user_id(), 'post_collection_widget_options', $options );
		}

		?>
		<?php wp_nonce_field( 'post_collection_widget_config', 'post_collection_widget_config_nonce' ); ?>
		<p>
			<label>
				<input type="checkbox" name="show_random_note" value="1" <?php checked( $options['show_random_note'] ); ?>>
				<?php esc_html_e( 'Show "From your notes" section', 'post-collection' ); ?>
			</label>
		</p>
		<p>
			<label>
				<input type="checkbox" name="show_tabs" value="1" <?php checked( $options['show_tabs'] ); ?>>
				<?php esc_html_e( 'Show Pending / Reviewed tabs', 'post-collection' ); ?>
			</label>
		</p>
		<p>
			<label for="post-collection-pending-count"><?php esc_html_e( 'Pending articles to show:', 'post-collection' ); ?></label>
			<input type="number" id="post-collection-pending-count" name="pending_count" value="<?php echo esc_attr( $options['pending_count'] ); ?>" min="1" max="50" class="small-text">
		</p>
		<p>
			<label for="post-collection-reviewed-count"><?php esc_html_e( 'Reviewed articles to show:', 'post-collection' ); ?></label>
			<input type="number" id="post-collection-reviewed-count" name="reviewed_count" value="<?php echo esc_attr( $options['reviewed_count'] ); ?>" min="1" max="50" class="small-text">
		</p>
		<?php
	}

	/**
	 * Render the dashboard widget.
	 */
	public function render_dashboard_widget() {
		$this->enqueue_widget_assets();
		$options = $this->get_widget_options();

		$pending_limit = (int) $options['pending_count'];
		$review_limit  = (int) $options['reviewed_count'];

		$pending_articles = $this->get_pending_and_unread_articles( $pending_limit + 1 );
		$has_more_pending = count( $pending_articles ) > $pending_limit;
		if ( $has_more_pending ) {
			$pending_articles = array_slice( $pending_articles, 0, $pending_limit );
		}

		Post_Collection::template_loader()->get_template_part(
			'admin/article-notes-widget',
			null,
			array(
				'pending_articles'  => $options['show_tabs'] ? $pending_articles : array(),
				'has_more_pending'  => $options['show_tabs'] && $has_more_pending,
				'reviewed_articles' => $options['show_tabs'] ? $this->get_reviewed_articles( $review_limit ) : array(),
				'show_tabs'         => $options['show_tabs'],
				'random_remembered' => $options['show_random_note'] ? $this->get_random_remembered_article() : null,
				'nonce'             => wp_create_nonce( 'post-collection-article-notes' ),
			)
		);
	}

	/**
	 * Enqueue assets for the widget.
	 */
	private function enqueue_widget_assets() {
		$version = POST_COLLECTION_VERSION;

		wp_enqueue_style(
			'post-collection-article-notes',
			plugins_url( 'assets/css/article-notes.css', dirname( __FILE__ ) ),
			array(),
			$version
		);

		wp_enqueue_script(
			'post-collection-article-notes',
			plugins_url( 'assets/js/article-notes.js', dirname( __FILE__ ) ),
			array( 'jquery' ),
			$version,
			true
		);

		wp_localize_script(
			'post-collection-article-notes',
			'postCollectionArticleNotes',
			array(
				'ajaxurl'  => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'post-collection-article-notes' ),
				'statuses' => self::get_statuses(),
				'i18n'     => array(
					'saving'        => __( 'Saving...', 'post-collection' ),
					'saved'         => __( 'Saved', 'post-collection' ),
					'error'         => __( 'Error saving', 'post-collection' ),
					'loading'       => __( 'Loading...', 'post-collection' ),
					'confirmCreate' => __( 'Create a post from the selected reviews?', 'post-collection' ),
					'showArticle'   => __( 'Show article', 'post-collection' ),
				),
			)
		);
	}

	/**
	 * Get post types to query for articles.
	 *
	 * @return array Array of post type names.
	 */
	private function get_article_post_types() {
		// Get all registered post types to ensure we don't miss any.
		$post_types = get_post_types( array(), 'names' );

		// Exclude our own note post type and some WordPress internals.
		$exclude = array(
			self::POST_TYPE,
			'revision',
			'nav_menu_item',
			'custom_css',
			'customize_changeset',
			'oembed_cache',
			'user_request',
			'wp_block',
			'wp_template',
			'wp_template_part',
			'wp_global_styles',
			'wp_navigation',
			'wp_font_family',
			'wp_font_face',
		);

		return array_values( array_diff( $post_types, $exclude ) );
	}

	/**
	 * Get articles that have been downloaded but not yet reviewed.
	 *
	 * @param int $limit  Maximum number of articles to return.
	 * @param int $offset Number of articles to skip.
	 * @return array Array of post objects with note data.
	 */
	public function get_pending_articles( $limit = 20, $offset = 0 ) {
		$meta_query = apply_filters( 'post_collection_article_queued_meta_query', array() );
		$meta_query[] = array(
			'key'     => self::NOTE_ID_META,
			'compare' => 'NOT EXISTS',
		);

		if ( count( $meta_query ) > 1 ) {
			array_unshift( $meta_query, array( 'relation' => 'AND' ) );
		}

		$args = array(
			'post_type'      => $this->get_article_post_types(),
			'posts_per_page' => $limit,
			'offset'         => $offset,
			'post_status'    => 'any',
			'meta_query'     => $meta_query,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		$queued_meta_key = apply_filters( 'post_collection_article_queued_orderby_meta_key', '' );
		if ( ! empty( $queued_meta_key ) ) {
			$args['orderby'] = 'meta_value_num';
			$args['meta_key'] = $queued_meta_key;
		}

		$posts = get_posts( $args );

		return array_map( array( $this, 'prepare_article_data' ), $posts );
	}

	/**
	 * Get articles that are pending review: either no note yet, or note with unread status.
	 *
	 * @param int $limit  Maximum number of articles to return.
	 * @param int $offset Number of articles to skip.
	 * @return array Combined array of pending and unread articles.
	 */
	public function get_pending_and_unread_articles( $limit = 20, $offset = 0 ) {
		$pending = $this->get_pending_articles( $limit, $offset );
		$unread = $this->get_unread_articles( $limit, $offset );

		$combined = array_merge( $pending, $unread );

		// Deduplicate by article ID.
		$seen = array();
		$result = array();
		foreach ( $combined as $article ) {
			if ( ! isset( $seen[ $article['id'] ] ) ) {
				$seen[ $article['id'] ] = true;
				$result[] = $article;
			}
		}

		// Sort by ID descending (newest first).
		usort(
			$result,
			function ( $a, $b ) {
				return $b['id'] - $a['id'];
			}
		);

		return array_slice( $result, 0, $limit );
	}

	/**
	 * Get articles marked as unread (has note but status is unread).
	 *
	 * @param int $limit  Maximum number of articles to return.
	 * @param int $offset Number of articles to skip.
	 * @return array Array of post objects with note data.
	 */
	public function get_unread_articles( $limit = 20, $offset = 0 ) {
		// Get note IDs with unread status.
		$note_ids = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'posts_per_page' => -1,
				'post_status'    => 'publish',
				'fields'         => 'ids',
				'meta_query'     => array(
					array(
						'key'   => self::STATUS_META,
						'value' => self::STATUS_UNREAD,
					),
				),
			)
		);

		if ( empty( $note_ids ) ) {
			return array();
		}

		// Get the parent article IDs.
		$article_ids = array();
		foreach ( $note_ids as $note_id ) {
			$parent_id = wp_get_post_parent_id( $note_id );
			if ( $parent_id ) {
				$article_ids[] = $parent_id;
			}
		}

		if ( empty( $article_ids ) ) {
			return array();
		}

		$args = array(
			'post_type'      => $this->get_article_post_types(),
			'posts_per_page' => $limit,
			'offset'         => $offset,
			'post_status'    => 'any',
			'post__in'       => $article_ids,
			'orderby'        => 'post__in',
		);

		$posts = get_posts( $args );

		return array_map( array( $this, 'prepare_article_data' ), $posts );
	}

	/**
	 * Get articles that have been reviewed (read or skipped).
	 *
	 * @param int $limit Maximum number of articles to return.
	 * @return array Array of post objects with note data.
	 */
	public function get_reviewed_articles( $limit = 20 ) {
		// Get note IDs with read or skipped status.
		$note_ids = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'posts_per_page' => -1,
				'post_status'    => 'publish',
				'fields'         => 'ids',
				'meta_query'     => array(
					array(
						'key'     => self::STATUS_META,
						'value'   => array( self::STATUS_READ, self::STATUS_SKIPPED ),
						'compare' => 'IN',
					),
				),
			)
		);

		if ( empty( $note_ids ) ) {
			return array();
		}

		// Get the parent article IDs.
		$article_ids = array();
		foreach ( $note_ids as $note_id ) {
			$parent_id = wp_get_post_parent_id( $note_id );
			if ( $parent_id ) {
				$article_ids[] = $parent_id;
			}
		}

		if ( empty( $article_ids ) ) {
			return array();
		}

		$args = array(
			'post_type'      => $this->get_article_post_types(),
			'posts_per_page' => $limit,
			'post_status'    => 'any',
			'post__in'       => $article_ids,
			'orderby'        => 'post__in',
		);

		$posts = get_posts( $args );

		return array_map( array( $this, 'prepare_article_data' ), $posts );
	}

	/**
	 * Get a random reviewed article that has notes content.
	 *
	 * @return array|null Article data or null if none found.
	 */
	public function get_random_remembered_article() {
		$note_ids = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'posts_per_page' => -1,
				'post_status'    => 'publish',
				'fields'         => 'ids',
				'meta_query'     => array(
					array(
						'key'     => self::STATUS_META,
						'value'   => array( self::STATUS_READ, self::STATUS_SKIPPED ),
						'compare' => 'IN',
					),
				),
			)
		);

		if ( empty( $note_ids ) ) {
			return null;
		}

		// Filter to only notes that have actual content.
		$notes_with_content = array();
		foreach ( $note_ids as $note_id ) {
			$note_post = get_post( $note_id );
			if ( $note_post && ! empty( trim( $note_post->post_content ) ) ) {
				$notes_with_content[] = $note_id;
			}
		}

		if ( empty( $notes_with_content ) ) {
			return null;
		}

		$random_note_id = $notes_with_content[ array_rand( $notes_with_content ) ];
		$parent_id = wp_get_post_parent_id( $random_note_id );

		if ( ! $parent_id ) {
			return null;
		}

		$post = get_post( $parent_id );
		if ( ! $post ) {
			return null;
		}

		return $this->prepare_article_data( $post );
	}

	/**
	 * Prepare article data for display.
	 *
	 * @param \WP_Post $post The post object.
	 * @return array Prepared article data.
	 */
	private function prepare_article_data( $post ) {
		$note = $this->get_note( $post->ID );
		$queued_meta_key = apply_filters( 'post_collection_article_queued_orderby_meta_key', '' );
		$sent_date = '';
		if ( ! empty( $queued_meta_key ) ) {
			$timestamp = get_post_meta( $post->ID, $queued_meta_key, true );
			if ( $timestamp ) {
				$sent_date = date_i18n( get_option( 'date_format' ), $timestamp );
			}
		}

		// Strip images from content for the preview.
		$content = $post->post_content;
		$content = preg_replace( '/<img[^>]*>/i', '', $content );
		$content = preg_replace( '/<figure[^>]*>.*?<\/figure>/is', '', $content );

		// Use the per-post author meta if available.
		$author = get_post_meta( $post->ID, 'author', true );
		if ( ! $author ) {
			$author = $this->plugin->get_post_author_name( $post );
		}

		$user = new User( $post->post_author );
		$permalink = $user->get_local_friends_page_url( $post->ID );

		return array(
			'id'          => $post->ID,
			'title'       => html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' ),
			'permalink'   => $permalink,
			'author'      => $author,
			'collection'  => $user->display_name,
			'sent_date'   => $sent_date,
			'excerpt'     => get_the_excerpt( $post ),
			'content'     => wp_kses_post( $content ),
			'note_id'     => $note ? $note['id'] : 0,
			'status'      => $note ? $note['status'] : self::STATUS_UNREAD,
			'rating'      => $note ? $note['rating'] : 0,
			'notes'       => $note ? $note['notes'] : '',
		);
	}

	/**
	 * Get note for an article.
	 *
	 * @param int $article_id The article post ID.
	 * @return array|null Note data or null if not found.
	 */
	public function get_note( $article_id ) {
		$note_id = get_post_meta( $article_id, self::NOTE_ID_META, true );

		if ( ! $note_id ) {
			return null;
		}

		$note_post = get_post( $note_id );

		if ( ! $note_post || self::POST_TYPE !== $note_post->post_type ) {
			// Clean up orphaned reference.
			delete_post_meta( $article_id, self::NOTE_ID_META );
			return null;
		}

		return array(
			'id'      => $note_post->ID,
			'status'  => get_post_meta( $note_post->ID, self::STATUS_META, true ) ?: self::STATUS_UNREAD,
			'rating'  => (int) get_post_meta( $note_post->ID, self::RATING_META, true ),
			'notes'   => $note_post->post_content,
			'updated' => $note_post->post_modified,
		);
	}

	/**
	 * Save or update a note for an article.
	 *
	 * @param int    $article_id The article post ID.
	 * @param string $status     Reading status (unread, read, skipped).
	 * @param int    $rating     Star rating (0-5).
	 * @param string $notes      Notes text.
	 * @return int|false Note post ID on success, false on failure.
	 */
	public function save_note( $article_id, $status = null, $rating = null, $notes = null ) {
		$existing_note_id = get_post_meta( $article_id, self::NOTE_ID_META, true );

		$note_data = array(
			'post_type'   => self::POST_TYPE,
			'post_parent' => $article_id,
			'post_status' => 'publish',
		);

		if ( null !== $notes ) {
			$note_data['post_content'] = wp_kses_post( $notes );
		}

		if ( $existing_note_id ) {
			$note_data['ID'] = $existing_note_id;
			$note_id = wp_update_post( $note_data );
		} else {
			$note_id = wp_insert_post( $note_data );

			if ( $note_id && ! is_wp_error( $note_id ) ) {
				update_post_meta( $article_id, self::NOTE_ID_META, $note_id );
			}
		}

		if ( ! $note_id || is_wp_error( $note_id ) ) {
			return false;
		}

		if ( null !== $status && in_array( $status, self::get_all_status_values(), true ) ) {
			update_post_meta( $note_id, self::STATUS_META, $status );
		}

		if ( null !== $rating ) {
			$rating = max( 0, min( 5, (int) $rating ) );
			update_post_meta( $note_id, self::RATING_META, $rating );
		}

		return $note_id;
	}

	/**
	 * Delete a note.
	 *
	 * @param int $article_id The article post ID.
	 * @return bool True on success, false on failure.
	 */
	public function delete_note( $article_id ) {
		$note_id = get_post_meta( $article_id, self::NOTE_ID_META, true );

		if ( ! $note_id ) {
			return false;
		}

		delete_post_meta( $article_id, self::NOTE_ID_META );
		wp_delete_post( $note_id, true );

		return true;
	}

	/**
	 * Maybe delete note when article is deleted.
	 *
	 * @param int $post_id The post ID being deleted.
	 */
	public function maybe_delete_note( $post_id ) {
		$post = get_post( $post_id );

		if ( ! $post ) {
			return;
		}

		// If an article is being deleted, delete its note too.
		if ( self::POST_TYPE !== $post->post_type ) {
			$this->delete_note( $post_id );
		}
	}

	/**
	 * AJAX handler for saving a note.
	 */
	public function ajax_save_note() {
		check_ajax_referer( 'post-collection-article-notes' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'post-collection' ) );
		}

		$article_id = isset( $_POST['article_id'] ) ? (int) $_POST['article_id'] : 0;

		if ( ! $article_id ) {
			wp_send_json_error( __( 'Invalid article ID.', 'post-collection' ) );
		}

		$status = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : null;
		$rating = isset( $_POST['rating'] ) ? (int) $_POST['rating'] : null;
		$notes = isset( $_POST['notes'] ) ? wp_kses_post( wp_unslash( $_POST['notes'] ) ) : null;

		$note_id = $this->save_note( $article_id, $status, $rating, $notes );

		if ( ! $note_id ) {
			wp_send_json_error( __( 'Failed to save note.', 'post-collection' ) );
		}

		wp_send_json_success(
			array(
				'note_id' => $note_id,
				'message' => __( 'Note saved.', 'post-collection' ),
			)
		);
	}

	/**
	 * AJAX handler for getting notes data.
	 */
	public function ajax_get_notes() {
		check_ajax_referer( 'post-collection-article-notes' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'post-collection' ) );
		}

		$type = isset( $_GET['type'] ) ? sanitize_text_field( wp_unslash( $_GET['type'] ) ) : 'pending';
		$limit = isset( $_GET['limit'] ) ? (int) $_GET['limit'] : 20;

		if ( 'reviewed' === $type ) {
			$articles = $this->get_reviewed_articles( $limit );
		} else {
			$articles = $this->get_pending_articles( $limit );
		}

		wp_send_json_success( $articles );
	}

	/**
	 * AJAX handler for loading more pending articles.
	 */
	public function ajax_load_more_pending() {
		check_ajax_referer( 'post-collection-article-notes' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'post-collection' ) );
		}

		$offset = isset( $_POST['offset'] ) ? (int) $_POST['offset'] : 0;
		$type = isset( $_POST['type'] ) ? sanitize_text_field( wp_unslash( $_POST['type'] ) ) : 'pending';
		$limit = 10;

		if ( 'unread' === $type ) {
			$articles = $this->get_unread_articles( $limit + 1, $offset );
		} else {
			$articles = $this->get_pending_articles( $limit + 1, $offset );
		}

		$has_more = count( $articles ) > $limit;
		if ( $has_more ) {
			$articles = array_slice( $articles, 0, $limit );
		}

		wp_send_json_success(
			array(
				'articles' => $articles,
				'has_more' => $has_more,
				'offset'   => $offset + count( $articles ),
			)
		);
	}

	/**
	 * AJAX handler for creating a post from selected notes.
	 */
	public function ajax_create_post_from_notes() {
		check_ajax_referer( 'post-collection-article-notes' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'post-collection' ) );
		}

		$article_ids = isset( $_POST['article_ids'] ) ? array_map( 'intval', (array) $_POST['article_ids'] ) : array();
		$post_title = isset( $_POST['post_title'] ) ? sanitize_text_field( wp_unslash( $_POST['post_title'] ) ) : '';

		if ( empty( $article_ids ) ) {
			wp_send_json_error( __( 'No articles selected.', 'post-collection' ) );
		}

		if ( empty( $post_title ) ) {
			$post_title = sprintf(
				/* translators: %s is a date */
				__( 'Reading Notes - %s', 'post-collection' ),
				date_i18n( get_option( 'date_format' ) )
			);
		}

		$post_content = $this->generate_post_content( $article_ids );

		$post_id = wp_insert_post(
			array(
				'post_title'   => $post_title,
				'post_content' => $post_content,
				'post_status'  => 'draft',
				'post_type'    => 'post',
			)
		);

		if ( is_wp_error( $post_id ) ) {
			wp_send_json_error( $post_id->get_error_message() );
		}

		wp_send_json_success(
			array(
				'post_id'  => $post_id,
				'edit_url' => get_edit_post_link( $post_id, 'raw' ),
				'message'  => __( 'Post created successfully.', 'post-collection' ),
			)
		);
	}

	/**
	 * Generate post content from article notes.
	 *
	 * @param array $article_ids Array of article IDs.
	 * @return string Generated post content in block editor format.
	 */
	private function generate_post_content( $article_ids ) {
		$blocks = array();

		foreach ( $article_ids as $article_id ) {
			$post = get_post( $article_id );
			if ( ! $post ) {
				continue;
			}

			$note = $this->get_note( $article_id );
			if ( ! $note ) {
				continue;
			}

			$permalink = esc_url( get_permalink( $post ) );
			$title = wp_kses_post( get_the_title( $post ) );
			$author = esc_html( $this->plugin->get_post_author_name( $post ) );

			// Build star rating display.
			$stars = '';
			if ( $note['rating'] > 0 ) {
				$stars = str_repeat( '★', $note['rating'] ) . str_repeat( '☆', 5 - $note['rating'] );
			}

			$group_meta = array(
				'metadata' => array(
					'name' => $title,
				),
				'layout'   => array(
					'type' => 'constrained',
				),
			);

			$content = '<!-- wp:group ' . wp_json_encode( $group_meta ) . ' -->' . PHP_EOL;
			$content .= '<div class="wp-block-group">';

			// Heading with link.
			$content .= '<!-- wp:heading {"level":3} -->' . PHP_EOL;
			$content .= '<h3><a href="' . $permalink . '">' . $title . '</a></h3>' . PHP_EOL;
			$content .= '<!-- /wp:heading -->';

			// Author and rating.
			$meta_line = $author;
			if ( $stars ) {
				$meta_line .= ' — ' . $stars;
			}
			$content .= '<!-- wp:paragraph {"className":"article-meta"} -->' . PHP_EOL;
			$content .= '<p class="article-meta">' . $meta_line . '</p>' . PHP_EOL;
			$content .= '<!-- /wp:paragraph -->';

			// Notes.
			if ( ! empty( $note['notes'] ) ) {
				$content .= '<!-- wp:quote -->' . PHP_EOL;
				$content .= '<blockquote class="wp-block-quote"><p>' . wp_kses_post( $note['notes'] ) . '</p></blockquote>' . PHP_EOL;
				$content .= '<!-- /wp:quote -->';
			}

			$content .= '</div>' . PHP_EOL;
			$content .= '<!-- /wp:group -->';

			$blocks[] = $content;
		}

		return implode( PHP_EOL . PHP_EOL, $blocks );
	}

	/**
	 * AJAX handler for dismissing old articles (marking all pending as skipped).
	 */
	public function ajax_dismiss_old_articles() {
		check_ajax_referer( 'post-collection-article-notes' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'post-collection' ) );
		}

		$meta_query = apply_filters( 'post_collection_article_queued_meta_query', array() );
		$meta_query[] = array(
			'key'     => self::NOTE_ID_META,
			'compare' => 'NOT EXISTS',
		);

		if ( count( $meta_query ) > 1 ) {
			array_unshift( $meta_query, array( 'relation' => 'AND' ) );
		}

		$args = array(
			'post_type'      => $this->get_article_post_types(),
			'posts_per_page' => -1,
			'post_status'    => 'any',
			'fields'         => 'ids',
			'meta_query'     => $meta_query,
		);

		$article_ids = get_posts( $args );
		$count = 0;

		foreach ( $article_ids as $article_id ) {
			$note_id = $this->save_note( $article_id, self::STATUS_SKIPPED, 0, '' );
			if ( $note_id ) {
				$count++;
			}
		}

		wp_send_json_success(
			array(
				'count'   => $count,
				'message' => sprintf(
					/* translators: %d is the number of articles dismissed */
					__( '%d articles marked as skipped.', 'post-collection' ),
					$count
				),
			)
		);
	}

	/**
	 * AJAX handler for getting a random remembered article.
	 */
	public function ajax_random_remembered() {
		check_ajax_referer( 'post-collection-article-notes' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'post-collection' ) );
		}

		$article = $this->get_random_remembered_article();

		if ( ! $article ) {
			wp_send_json_error( __( 'No articles with notes found.', 'post-collection' ) );
		}

		wp_send_json_success( $article );
	}

	/**
	 * AJAX handler for saving an article title.
	 */
	public function ajax_save_title() {
		check_ajax_referer( 'post-collection-article-notes' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'post-collection' ) );
		}

		$article_id = isset( $_POST['article_id'] ) ? (int) $_POST['article_id'] : 0;
		$title = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';

		if ( ! $article_id || ! $title ) {
			wp_send_json_error( __( 'Invalid article ID or title.', 'post-collection' ) );
		}

		$result = wp_update_post(
			array(
				'ID'         => $article_id,
				'post_title' => $title,
			)
		);

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		wp_send_json_success( array( 'title' => $title ) );
	}

	/**
	 * Get all valid reading statuses.
	 *
	 * @return array Associative array of status => label.
	 */
	public static function get_statuses() {
		return array(
			self::STATUS_UNREAD  => __( 'Not read yet', 'post-collection' ),
			self::STATUS_READ    => __( 'Read', 'post-collection' ),
			self::STATUS_SKIPPED => __( 'Skipped', 'post-collection' ),
		);
	}

	/**
	 * Get all valid status values including archived.
	 *
	 * @return array Array of status values.
	 */
	public static function get_all_status_values() {
		return array(
			self::STATUS_UNREAD,
			self::STATUS_READ,
			self::STATUS_SKIPPED,
			self::STATUS_ARCHIVED,
		);
	}
}
