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
		add_action( 'wp_ajax_post_collection_load_more_pending', array( $this, 'ajax_load_more_pending' ) );
		add_action( 'before_delete_post', array( $this, 'maybe_delete_note' ) );
		add_action( 'friends_post_after_footer', array( $this, 'render_frontend_notes' ) );
	}

	/**
	 * Render the notes UI on the Friends frontend single post view.
	 */
	public function render_frontend_notes() {
		if ( ! is_single() || ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		$post = get_post();
		if ( ! $post || Post_Collection::CPT !== $post->post_type ) {
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
	 * Apply optional filters to article queries.
	 *
	 * @param array $query_args Query arguments.
	 * @param array $args       Optional filter arguments.
	 * @return array Filtered query arguments.
	 */
	private function apply_article_query_args( array $query_args, array $args = array() ) {
		if ( ! empty( $args['collection_id'] ) ) {
			$query_args['tax_query'] = isset( $query_args['tax_query'] ) ? (array) $query_args['tax_query'] : array();
			$query_args['tax_query'][] = array(
				'taxonomy' => Post_Collection::COLLECTION_TAXONOMY,
				'field'    => 'term_id',
				'terms'    => absint( $args['collection_id'] ),
			);
		}

		return $query_args;
	}

	/**
	 * Get articles that have been downloaded but not yet reviewed.
	 *
	 * @param int $limit  Maximum number of articles to return.
	 * @param int $offset Number of articles to skip.
	 * @param array $args Optional filter arguments.
	 * @return array Array of post objects with note data.
	 */
	public function get_pending_articles( $limit = 20, $offset = 0, array $args = array() ) {
		$meta_query = apply_filters( 'post_collection_article_queued_meta_query', array() );
		$meta_query[] = array(
			'key'     => self::NOTE_ID_META,
			'compare' => 'NOT EXISTS',
		);

		if ( count( $meta_query ) > 1 ) {
			array_unshift( $meta_query, array( 'relation' => 'AND' ) );
		}

		$query_args = array(
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
			$query_args['orderby'] = 'meta_value_num';
			$query_args['meta_key'] = $queued_meta_key;
		}

		$query_args = $this->apply_article_query_args( $query_args, $args );
		$posts = get_posts( $query_args );

		return array_map( array( $this, 'prepare_article_data' ), $posts );
	}

	/**
	 * Get articles that are pending review: either no note yet, or note with unread status.
	 *
	 * @param int $limit  Maximum number of articles to return.
	 * @param int $offset Number of articles to skip.
	 * @param array $args Optional filter arguments.
	 * @return array Combined array of pending and unread articles.
	 */
	public function get_pending_and_unread_articles( $limit = 20, $offset = 0, array $args = array() ) {
		$pending = $this->get_pending_articles( $limit, $offset, $args );
		$unread = $this->get_unread_articles( $limit, $offset, $args );

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
	 * @param array $args Optional filter arguments.
	 * @return array Array of post objects with note data.
	 */
	public function get_unread_articles( $limit = 20, $offset = 0, array $args = array() ) {
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

		$query_args = array(
			'post_type'      => $this->get_article_post_types(),
			'posts_per_page' => $limit,
			'offset'         => $offset,
			'post_status'    => 'any',
			'post__in'       => $article_ids,
			'orderby'        => 'post__in',
		);

		$query_args = $this->apply_article_query_args( $query_args, $args );
		$posts = get_posts( $query_args );

		return array_map( array( $this, 'prepare_article_data' ), $posts );
	}

	/**
	 * Get articles that have been reviewed (read or skipped).
	 *
	 * @param int $limit Maximum number of articles to return.
	 * @param int $offset Number of articles to skip.
	 * @param array $args Optional filter arguments.
	 * @return array Array of post objects with note data.
	 */
	public function get_reviewed_articles( $limit = 20, $offset = 0, array $args = array() ) {
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

		$query_args = array(
			'post_type'      => $this->get_article_post_types(),
			'posts_per_page' => $limit,
			'offset'         => $offset,
			'post_status'    => 'any',
			'post__in'       => $article_ids,
			'orderby'        => 'post__in',
		);

		$query_args = $this->apply_article_query_args( $query_args, $args );
		$posts = get_posts( $query_args );

		return array_map( array( $this, 'prepare_article_data' ), $posts );
	}

	/**
	 * Get the unified review queue, including pending, unread, read, and skipped articles.
	 *
	 * @param int $limit  Maximum number of articles to return.
	 * @param int $offset Number of articles to skip.
	 * @param array $args Optional filter arguments.
	 * @return array Array of article data.
	 */
	public function get_review_queue_articles( $limit = 20, $offset = 0, array $args = array() ) {
		$query_limit = max( $limit + $offset, $limit );
		$articles    = array_merge(
			$this->get_pending_and_unread_articles( $query_limit, 0, $args ),
			$this->get_reviewed_articles( $query_limit, 0, $args )
		);

		$seen   = array();
		$result = array();
		foreach ( $articles as $article ) {
			if ( isset( $seen[ $article['id'] ] ) ) {
				continue;
			}

			$seen[ $article['id'] ] = true;
			$result[] = $article;
		}

		usort(
			$result,
			static function ( $a, $b ) {
				$a_order = ! empty( $a['sent_timestamp'] ) ? (int) $a['sent_timestamp'] : (int) $a['id'];
				$b_order = ! empty( $b['sent_timestamp'] ) ? (int) $b['sent_timestamp'] : (int) $b['id'];

				if ( $a_order === $b_order ) {
					return (int) $b['id'] - (int) $a['id'];
				}

				return $b_order - $a_order;
			}
		);

		return array_slice( $result, $offset, $limit );
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
		$sent_timestamp = 0;
		$sent_date = '';
		$sent_datetime = '';
		$sent_label = '';
		if ( ! empty( $queued_meta_key ) ) {
			$sent_timestamp = (int) get_post_meta( $post->ID, $queued_meta_key, true );
		}

		/**
		 * Filter the timestamp for when an article entered the review queue.
		 *
		 * Plugins can use this to supply a more specific source timestamp than
		 * the generic queued/orderby meta key.
		 *
		 * @param int      $sent_timestamp Unix timestamp.
		 * @param \WP_Post $post           Article post.
		 * @param string   $queued_meta_key Meta key used for queued articles.
		 */
		$sent_timestamp = (int) apply_filters( 'post_collection_article_sent_timestamp', $sent_timestamp, $post, $queued_meta_key );
		if ( $sent_timestamp ) {
			$date_format   = get_option( 'date_format' );
			$time_format   = get_option( 'time_format' );
			$sent_date     = date_i18n( $date_format, $sent_timestamp );
			$sent_datetime = date_i18n( 'c', $sent_timestamp );
			$sent_label    = sprintf(
				/* translators: %s is a formatted date and time. */
				__( 'Sent to e-reader %s', 'post-collection' ),
				date_i18n( $date_format . ' ' . $time_format, $sent_timestamp )
			);

			/**
			 * Filter the human-readable sent-to-reader label.
			 *
			 * @param string   $sent_label     Human-readable label.
			 * @param int      $sent_timestamp Unix timestamp.
			 * @param \WP_Post $post           Article post.
			 */
			$sent_label = apply_filters( 'post_collection_article_sent_label', $sent_label, $sent_timestamp, $post );
			if ( ! is_string( $sent_label ) ) {
				$sent_label = '';
			}
		}

		// Strip images from content for the preview.
		$content = $post->post_content;
		$content = preg_replace( '/<img[^>]*>/i', '', $content );
		$content = preg_replace( '/<figure[^>]*>.*?<\/figure>/is', '', $content );
		$excerpt = get_the_excerpt( $post );
		$summary_word_count = str_word_count( wp_strip_all_tags( $excerpt ) );

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
			'sent_datetime' => $sent_datetime,
			'sent_label'  => $sent_label,
			'sent_timestamp' => $sent_timestamp,
			'excerpt'     => $excerpt,
			'summary_word_count' => $summary_word_count,
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
		$args = array();
		if ( ! empty( $_POST['collection_id'] ) ) {
			$args['collection_id'] = absint( wp_unslash( $_POST['collection_id'] ) );
		}

		if ( 'queue' === $type ) {
			$articles = $this->get_review_queue_articles( $limit + 1, $offset, $args );
		} elseif ( 'reviewed' === $type ) {
			$articles = $this->get_reviewed_articles( $limit + 1, $offset, $args );
		} elseif ( 'unread' === $type ) {
			$articles = $this->get_unread_articles( $limit + 1, $offset, $args );
		} elseif ( 'all' === $type ) {
			$articles = $this->get_pending_and_unread_articles( $limit + 1, $offset, $args );
		} else {
			$articles = $this->get_pending_articles( $limit + 1, $offset, $args );
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
