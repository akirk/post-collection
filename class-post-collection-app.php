<?php
/**
 * Frontend app for post collections.
 *
 * @package Post_Collection
 */

namespace PostCollection;

defined( 'ABSPATH' ) || exit;

use WpApp\WpApp;

/**
 * WpApp-powered frontend for browsing collected posts.
 */
class Post_Collection_App {
	const PATH = 'post-collection';

	/**
	 * Singleton instance.
	 *
	 * @var Post_Collection_App|null
	 */
	private static $instance = null;

	/**
	 * Post Collection plugin instance.
	 *
	 * @var Post_Collection
	 */
	private $post_collection;

	/**
	 * WpApp instance.
	 *
	 * @var WpApp
	 */
	private $app;

	/**
	 * Initialize the frontend app.
	 *
	 * @param Post_Collection $post_collection The plugin instance.
	 * @return Post_Collection_App
	 */
	public static function boot( Post_Collection $post_collection ) {
		if ( null === self::$instance ) {
			self::$instance = new self( $post_collection );
			self::$instance->init();
		}

		return self::$instance;
	}

	/**
	 * Get the singleton instance.
	 *
	 * @return Post_Collection_App|null
	 */
	public static function instance() {
		return self::$instance;
	}

	/**
	 * Constructor.
	 *
	 * @param Post_Collection $post_collection The plugin instance.
	 */
	private function __construct( Post_Collection $post_collection ) {
		$this->post_collection = $post_collection;
		$this->app = new WpApp(
			POST_COLLECTION_PLUGIN_DIR . 'templates/app',
			self::PATH,
			array(
				'app_name'                     => __( 'Post Collection', 'post-collection' ),
				'launcher'                     => __( 'Post Collection', 'post-collection' ),
				// Same tile as the WordPress/blueprints catalog entry.
				'app_icon'                     => 'dashicons-book',
				'app_icon_background'          => 'linear-gradient(135deg, #614385, #516395)',
				'app_icon_color'               => '#fff',
				'app_icon_shadow'              => true,
				'show_wp_logo'                 => false,
				'show_site_name'               => true,
				'show_dark_mode_toggle'        => true,
				'show_masterbar_for_anonymous' => true,
				// Owned content: REST reads are gated with the app's capability and
				// OpenStation keeps these menus out of its dock.
				'post_types'                   => array( Post_Collection::CPT, Article_Notes::POST_TYPE ),
				'taxonomies'                   => array( Post_Collection::COLLECTION_TAXONOMY ),
			)
		);
	}

	/**
	 * Set up routes and assets.
	 */
	private function init() {
		add_action( 'wp_loaded', array( $this, 'handle_quick_edit' ) );
		add_action( 'wp_loaded', array( $this, 'handle_collection_settings' ) );
		add_action( 'wp_loaded', array( $this, 'handle_delete_collection' ) );
		add_action( 'wp_loaded', array( $this, 'handle_create_collection' ) );
		add_action( 'wp_ajax_post_collection_quick_edit', array( $this, 'wp_ajax_quick_edit' ) );
		add_action( 'wp_ajax_post_collection_parse_import', array( $this, 'wp_ajax_parse_import' ) );
		add_action( 'wp_ajax_post_collection_import_item', array( $this, 'wp_ajax_import_item' ) );
		add_action( 'wp_ajax_post_collection_toggle_read_status', array( $this, 'wp_ajax_toggle_read_status' ) );
		add_filter( 'private_title_format', array( $this, 'filter_private_title_format' ), 10, 2 );
		add_action( 'template_redirect', array( $this, 'fire_app_request' ), 1 );
		add_action( 'wp_app_before_render', array( $this, 'add_collection_menu_items' ) );

		$this->app->route( '' );
		$this->app->route( 'new', 'new.php' );
		$this->app->route( 'review', 'review.php' );
		$this->app->route( 'export', 'export.php' );
		$this->app->route( '{collection}/settings', 'settings.php' );
		$this->app->route( '{collection}/import', 'import.php' );
		$this->app->route( '{collection}/export', 'export.php' );
		$this->app->route( '{collection}/review', 'review.php' );
		$this->app->route( '{collection}', 'collection.php' );
		$this->app->route( '{collection}/{post_id}', 'post.php' );

		$this->app->add_menu_item(
			'collections',
			__( 'Collections', 'post-collection' ),
			$this->get_home_url()
		);

		if ( current_user_can( $this->post_collection->get_required_role() ) ) {
			$this->app->add_menu_item(
				'review',
				__( 'Review Articles', 'post-collection' ),
				$this->get_review_url()
			);
			$this->app->add_menu_item(
				'new-collection',
				__( 'New Collection', 'post-collection' ),
				$this->get_new_collection_url()
			);
			$this->app->add_menu_item(
				'export-all',
				__( 'Export All Collections', 'post-collection' ),
				$this->get_export_url()
			);
		}

		if ( function_exists( 'wp_app_enqueue_style' ) ) {
			$style_path  = POST_COLLECTION_PLUGIN_DIR . 'assets/css/post-collection-app.css';
			$script_path = POST_COLLECTION_PLUGIN_DIR . 'assets/js/post-collection-app.js';

			// This runs at plugin load, before a route is known, so an omitted
			// scope would resolve to '' and put the assets on every app's pages.
			wp_app_enqueue_style(
				'post-collection-app',
				plugins_url( 'assets/css/post-collection-app.css', POST_COLLECTION_PLUGIN_FILE ),
				array(),
				file_exists( $style_path ) ? filemtime( $style_path ) : POST_COLLECTION_VERSION,
				self::PATH
			);
			wp_app_enqueue_script(
				'post-collection-app',
				plugins_url( 'assets/js/post-collection-app.js', POST_COLLECTION_PLUGIN_FILE ),
				array(),
				file_exists( $script_path ) ? filemtime( $script_path ) : POST_COLLECTION_VERSION,
				true,
				self::PATH
			);
		}

		$this->app->init();

		// With Friends active the plugin is constructed on plugins_loaded, which
		// is too early for integrations that load alongside it and for asking
		// what the current user may do, so the announcement waits for init.
		if ( did_action( 'init' ) ) {
			$this->fire_app_loaded();
		} else {
			add_action( 'init', array( $this, 'fire_app_loaded' ), 20 );
		}
	}

	/**
	 * Announce that the app is ready for integrations to hook into.
	 */
	public function fire_app_loaded() {
		/**
		 * Fires once the frontend app has registered its routes and assets.
		 *
		 * Integrations use this to hook into the app: it runs late enough for
		 * the current user to be known and early enough to enqueue assets for
		 * the app path.
		 *
		 * @param Post_Collection_App $app The app instance.
		 */
		do_action( 'post_collection_app_loaded', $this );
	}

	/**
	 * Let integrations answer an app request themselves.
	 *
	 * This runs before the app decides whether the visitor may see the page, so
	 * that a listener can serve a request that carries its own credentials — an
	 * e-reader fetching an ePub over a password URL, say. A listener that
	 * produces a response is expected to exit; returning lets the app carry on.
	 */
	public function fire_app_request() {
		if ( ! $this->is_app_request() ) {
			return;
		}

		/**
		 * Fires on an app request, before the app renders or redirects.
		 *
		 * @param Post_Collection_App $app        The app instance.
		 * @param \WP_Term|null       $collection The collection the URL points at, if any.
		 */
		do_action( 'post_collection_app_request', $this, $this->get_requested_collection() );
	}

	/**
	 * Get the collection the current app URL points at.
	 *
	 * The route is only matched once the app renders, so before that the
	 * collection is read out of the request path the rewrite rule captured.
	 *
	 * @return \WP_Term|null The collection, or null on a page that spans all of them.
	 */
	public function get_requested_collection() {
		$collection_slug = wp_app_get_route_var( 'collection' );

		if ( ! $collection_slug ) {
			$request = get_query_var( 'wp_app_request' );
			if ( is_string( $request ) && '' !== trim( $request, '/' ) ) {
				$segments = explode( '/', trim( $request, '/' ) );
				if ( ! in_array( $segments[0], array( 'new', 'review', 'export' ), true ) ) {
					$collection_slug = $segments[0];
				}
			}
		}

		return $collection_slug ? $this->get_collection_by_username( $collection_slug ) : null;
	}

	/**
	 * Add Import and Export entries to the app menu when a collection is in context.
	 */
	public function add_collection_menu_items() {
		if ( ! $this->can_manage_collections() ) {
			return;
		}

		$collection_slug = wp_app_get_route_var( 'collection' );
		if ( ! $collection_slug ) {
			return;
		}

		$collection = $this->get_collection_by_username( $collection_slug );
		if ( ! $collection ) {
			return;
		}

		$this->app->add_menu_item(
			'import',
			// translators: %s is the name of a post collection.
			sprintf( __( 'Import to %s', 'post-collection' ), $collection->name ),
			$this->get_collection_import_url( $collection )
		);
		$this->app->add_menu_item(
			'export',
			// translators: %s is the name of a post collection.
			sprintf( __( 'Export %s', 'post-collection' ), $collection->name ),
			$this->get_collection_export_url( $collection )
		);
	}

	/**
	 * Get the backing plugin instance.
	 *
	 * @return Post_Collection
	 */
	public function get_post_collection() {
		return $this->post_collection;
	}

	/**
	 * Build a bookmarklet href for a collection.
	 *
	 * @param \WP_Term $collection The collection term.
	 * @return string Bookmarklet href.
	 */
	public function get_collection_bookmarklet_href( $collection ) {
		return $this->post_collection->get_bookmarklet_href( 'collection', $collection->term_id );
	}

	/**
	 * Build a URLForwarder URL template for a collection.
	 *
	 * @param \WP_Term $collection The collection term.
	 * @return string URLForwarder template URL.
	 */
	public function get_collection_urlforwarder_url( $collection ) {
		return $this->post_collection->get_urlforwarder_url( 'collection', $collection->term_id );
	}

	/**
	 * Get the app home URL.
	 *
	 * @return string
	 */
	public function get_home_url() {
		return home_url( '/' . self::PATH . '/' );
	}

	/**
	 * Get the app URL for creating a collection.
	 *
	 * @return string
	 */
	public function get_new_collection_url() {
		return home_url( '/' . self::PATH . '/new/' );
	}

	/**
	 * Get the review queue URL.
	 *
	 * @return string
	 */
	public function get_review_url() {
		return home_url( '/' . self::PATH . '/review/' );
	}

	/**
	 * Get the review queue URL for a collection.
	 *
	 * @param \WP_Term $collection The collection term.
	 * @return string
	 */
	public function get_collection_review_url( $collection ) {
		return trailingslashit( $this->get_collection_url( $collection ) . 'review' );
	}

	/**
	 * Get the URL for a collection or a collected post.
	 *
	 * @param \WP_Term $collection The collection term.
	 * @param int|null      $post_id Optional post ID.
	 * @return string
	 */
	public function get_collection_url( $collection, $post_id = null ) {
		$path = '/' . self::PATH . '/' . rawurlencode( $collection->slug ) . '/';

		if ( $post_id ) {
			$path .= absint( $post_id ) . '/';
		}

		return home_url( $path );
	}

	/**
	 * Get the settings URL for a collection.
	 *
	 * @param \WP_Term $collection The collection term.
	 * @return string
	 */
	public function get_collection_settings_url( $collection ) {
		return trailingslashit( $this->get_collection_url( $collection ) . 'settings' );
	}

	/**
	 * Get the import URL for a collection.
	 *
	 * @param \WP_Term $collection The collection term.
	 * @return string
	 */
	public function get_collection_import_url( $collection ) {
		return trailingslashit( $this->get_collection_url( $collection ) . 'import' );
	}

	/**
	 * Get the URL of the export page for all collections.
	 *
	 * @return string
	 */
	public function get_export_url() {
		return trailingslashit( $this->get_home_url() . 'export' );
	}

	/**
	 * Get the export URL for a collection.
	 *
	 * @param \WP_Term $collection The collection term.
	 * @return string
	 */
	public function get_collection_export_url( $collection ) {
		return trailingslashit( $this->get_collection_url( $collection ) . 'export' );
	}

	/**
	 * Get collection terms.
	 *
	 * @return array
	 */
	public function get_collections() {
		$terms = get_terms(
			array(
				'taxonomy'   => Post_Collection::COLLECTION_TAXONOMY,
				'hide_empty' => false,
			)
		);

		return is_wp_error( $terms ) ? array() : $terms;
	}

	/**
	 * Find a collection by URL slug.
	 *
	 * @param string $collection_slug The route collection slug.
	 * @return \WP_Term|null
	 */
	public function get_collection_by_username( $collection_slug ) {
		$collection_slug = sanitize_title( rawurldecode( $collection_slug ) );

		$term = get_term_by( 'slug', $collection_slug, Post_Collection::COLLECTION_TAXONOMY );
		if ( $term && ! is_wp_error( $term ) ) {
			return $term;
		}

		return null;
	}

	/**
	 * Whether the current visitor can manage all collections.
	 *
	 * @return bool
	 */
	public function can_manage_collections() {
		return current_user_can( $this->post_collection->get_required_role() );
	}

	/**
	 * Whether the current visitor can view a collection.
	 *
	 * @param \WP_Term $collection The collection term.
	 * @return bool
	 */
	public function can_view_collection( $collection ) {
		if ( $this->can_manage_collections() ) {
			return true;
		}

		return (bool) get_term_meta( $collection->term_id, 'friends_publish_post_collection', true );
	}

	/**
	 * Whether a collection should be tucked away on the app home page.
	 *
	 * @param \WP_Term $collection The collection term.
	 * @return bool
	 */
	public function is_collection_hidden_from_home( $collection ) {
		return (bool) get_term_meta( $collection->term_id, 'post_collection_hide_from_home', true );
	}

	/**
	 * Whether private posts should be included for this visitor.
	 *
	 * @param \WP_Term $collection The collection term.
	 * @return bool
	 */
	public function can_view_private_posts( $collection ) {
		return $this->can_manage_collections();
	}

	/**
	 * Remove WordPress' private title prefix inside the frontend app.
	 *
	 * @param string   $format The title format.
	 * @param \WP_Post $post   The post object.
	 * @return string
	 */
	public function filter_private_title_format( $format, $post = null ) {
		if ( $this->is_app_request() ) {
			return '%s';
		}

		return $format;
	}

	/**
	 * Whether the current request is handled by this frontend app.
	 *
	 * @return bool
	 */
	public function is_app_request() {
		if ( wp_doing_ajax() ) {
			$action = isset( $_REQUEST['action'] ) && is_string( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : '';
			return 'post_collection_quick_edit' === $action;
		}

		if ( empty( $_SERVER['REQUEST_URI'] ) ) {
			return false;
		}

		$path = wp_parse_url( esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ), PHP_URL_PATH );
		if ( ! $path ) {
			return false;
		}

		return (bool) preg_match( '#/(?:index\.php/)?' . preg_quote( self::PATH, '#' ) . '(?:/|$)#', $path );
	}

	/**
	 * Get the frontend mode for a collection.
	 *
	 * @param \WP_Term $collection The collection term.
	 * @return string
	 */
	public function get_collection_mode( $collection ) {
		$mode = get_term_meta( $collection->term_id, 'post_collection_frontend_mode', true );
		if ( 'bookmarks' === $mode ) {
			return 'bookmarks';
		}

		return 'posts';
	}

	/**
	 * Get the default frontend view for a collection.
	 *
	 * @param \WP_Term $collection The collection term.
	 * @return string
	 */
	public function get_collection_default_view( $collection ) {
		$view = get_term_meta( $collection->term_id, 'post_collection_frontend_view', true );
		if ( in_array( $view, array( 'board', 'links', 'reader' ), true ) ) {
			return $view;
		}

		return 'reader';
	}

	/**
	 * Get the active frontend view for a collection.
	 *
	 * @param \WP_Term $collection The collection term.
	 * @return string
	 */
	public function get_collection_view( $collection ) {
		if ( isset( $_GET['pc-view'] ) ) {
			$view = sanitize_key( wp_unslash( $_GET['pc-view'] ) );
			if ( in_array( $view, array( 'board', 'links', 'reader' ), true ) ) {
				return $view;
			}
		}

		return $this->get_collection_default_view( $collection );
	}

	/**
	 * Handle collection frontend settings submissions.
	 */
	public function handle_collection_settings() {
		if ( wp_doing_ajax() ) {
			return;
		}

		if ( ! isset( $_POST['post_collection_action'] ) || 'collection-settings' !== $_POST['post_collection_action'] ) {
			return;
		}

		if ( ! $this->can_manage_collections() ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to edit this collection.', 'post-collection' ), '', array( 'response' => 403 ) );
		}

		$term_id = isset( $_POST['collection_term_id'] ) ? absint( wp_unslash( $_POST['collection_term_id'] ) ) : 0;
		if ( ! $term_id || ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'post-collection-settings-' . $term_id ) ) {
			wp_die( esc_html__( 'The collection settings request could not be verified.', 'post-collection' ), '', array( 'response' => 403 ) );
		}

		$collection = get_term( $term_id, Post_Collection::COLLECTION_TAXONOMY );
		if ( ! $collection || is_wp_error( $collection ) ) {
			wp_die( esc_html__( 'Invalid post collection.', 'post-collection' ), '', array( 'response' => 404 ) );
		}

		$frontend_mode = isset( $_POST['frontend_mode'] ) ? sanitize_key( wp_unslash( $_POST['frontend_mode'] ) ) : 'posts';
		if ( in_array( $frontend_mode, array( 'bookmarks', 'posts' ), true ) ) {
			update_term_meta( $collection->term_id, 'post_collection_frontend_mode', $frontend_mode );
		}

		$frontend_view = isset( $_POST['frontend_view'] ) ? sanitize_key( wp_unslash( $_POST['frontend_view'] ) ) : 'reader';
		if ( in_array( $frontend_view, array( 'board', 'links', 'reader' ), true ) ) {
			update_term_meta( $collection->term_id, 'post_collection_frontend_view', $frontend_view );
		}

		if ( ! empty( $_POST['hide_from_home'] ) ) {
			update_term_meta( $collection->term_id, 'post_collection_hide_from_home', true );
		} else {
			delete_term_meta( $collection->term_id, 'post_collection_hide_from_home' );
		}

		wp_safe_redirect( add_query_arg( 'pc-settings-updated', '1', $this->get_collection_settings_url( $collection ) ) );
		exit;
	}

	/**
	 * Handle frontend collection deletion submissions.
	 */
	public function handle_delete_collection() {
		if ( wp_doing_ajax() ) {
			return;
		}

		if ( ! isset( $_POST['post_collection_action'] ) || 'delete-collection' !== $_POST['post_collection_action'] ) {
			return;
		}

		$result = $this->delete_collection_from_request( $_POST );
		if ( is_wp_error( $result ) ) {
			$status_code = $result->get_error_data( 'status' );
			wp_die( esc_html( $result->get_error_message() ), '', array( 'response' => absint( $status_code ? $status_code : 400 ) ) );
		}

		wp_safe_redirect( add_query_arg( 'pc-collection-deleted', '1', $this->get_home_url() ) );
		exit;
	}

	/**
	 * Delete a post collection term from request data.
	 *
	 * @param array $data Request data.
	 * @return \WP_Term|\WP_Error Deleted collection or error.
	 */
	public function delete_collection_from_request( array $data ) {
		if ( ! $this->can_manage_collections() ) {
			return new \WP_Error( 'forbidden', __( 'Sorry, you are not allowed to delete collections.', 'post-collection' ), array( 'status' => 403 ) );
		}

		$term_id = isset( $data['collection_term_id'] ) ? absint( wp_unslash( $data['collection_term_id'] ) ) : 0;
		if ( ! $term_id || ! isset( $data['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $data['_wpnonce'] ) ), 'post-collection-delete-' . $term_id ) ) {
			return new \WP_Error( 'invalid_nonce', __( 'The delete collection request could not be verified.', 'post-collection' ), array( 'status' => 403 ) );
		}

		$collection = get_term( $term_id, Post_Collection::COLLECTION_TAXONOMY );
		if ( ! $collection || is_wp_error( $collection ) ) {
			return new \WP_Error( 'invalid_collection', __( 'Invalid post collection.', 'post-collection' ), array( 'status' => 404 ) );
		}

		$reassign_term_id = isset( $data['reassign_collection_term_id'] ) ? absint( wp_unslash( $data['reassign_collection_term_id'] ) ) : 0;
		if ( $reassign_term_id ) {
			if ( $reassign_term_id === (int) $collection->term_id ) {
				return new \WP_Error( 'invalid_reassign_collection', __( 'Choose a different collection to receive the saved posts.', 'post-collection' ), array( 'status' => 400 ) );
			}

			$reassign_collection = get_term( $reassign_term_id, Post_Collection::COLLECTION_TAXONOMY );
			if ( ! $reassign_collection || is_wp_error( $reassign_collection ) ) {
				return new \WP_Error( 'invalid_reassign_collection', __( 'Invalid reassignment collection.', 'post-collection' ), array( 'status' => 404 ) );
			}

			$reassigned = $this->reassign_collection_posts( $collection, $reassign_collection );
			if ( is_wp_error( $reassigned ) ) {
				return $reassigned;
			}
		}

		$deleted = wp_delete_term( $collection->term_id, Post_Collection::COLLECTION_TAXONOMY );
		if ( is_wp_error( $deleted ) ) {
			return $deleted;
		}

		if ( ! $deleted ) {
			return new \WP_Error( 'delete_failed', __( 'The collection could not be deleted.', 'post-collection' ), array( 'status' => 500 ) );
		}

		return $collection;
	}

	/**
	 * Reassign all posts in one collection to another collection.
	 *
	 * @param \WP_Term $from_collection Source collection.
	 * @param \WP_Term $to_collection Destination collection.
	 * @return int|\WP_Error Number of reassigned posts or error.
	 */
	private function reassign_collection_posts( \WP_Term $from_collection, \WP_Term $to_collection ) {
		$post_ids = get_posts(
			array(
				'post_type'      => Post_Collection::CPT,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'tax_query'      => array(
					array(
						'taxonomy' => Post_Collection::COLLECTION_TAXONOMY,
						'field'    => 'term_id',
						'terms'    => array( (int) $from_collection->term_id ),
					),
				),
			)
		);

		if ( is_wp_error( $post_ids ) ) {
			return $post_ids;
		}

		foreach ( $post_ids as $post_id ) {
			$updated = wp_set_object_terms( (int) $post_id, array( (int) $to_collection->term_id ), Post_Collection::COLLECTION_TAXONOMY, false );
			if ( is_wp_error( $updated ) ) {
				return $updated;
			}
		}

		return count( $post_ids );
	}

	/**
	 * Export formats offered for a collection.
	 *
	 * @return array Format key => label, extension and MIME type.
	 */
	public function get_export_formats() {
		return array(
			'html' => array(
				'label'       => __( 'Bookmarks HTML', 'post-collection' ),
				'description' => __( 'Netscape bookmarks file. Import it into a browser, Pinboard, Raindrop or most reading list apps.', 'post-collection' ),
				'extension'   => 'html',
				'mime'        => 'text/html',
			),
			'opml' => array(
				'label'       => __( 'OPML', 'post-collection' ),
				'description' => __( 'Outline file with one link per article, for feed readers and outliners.', 'post-collection' ),
				'extension'   => 'opml',
				'mime'        => 'text/x-opml',
			),
		);
	}

	/**
	 * Collect the items of a collection for export.
	 *
	 * @param \WP_Term $collection The collection term.
	 * @return array Items with url, title, tags, created, modified, description and unread keys.
	 */
	public function get_collection_export_items( $collection ) {
		$query = $this->query_collection_posts(
			$collection,
			array(
				'posts_per_page'                => -1,
				'paged'                         => 1,
				'orderby'                       => 'date',
				'order'                         => 'ASC',
				'post_collection_apply_filters' => false,
			)
		);

		$notes = $this->post_collection->get_article_notes();
		$items = array();
		foreach ( $query->posts as $post ) {
			$url = $this->get_source_url( $post );
			if ( ! $url ) {
				continue;
			}

			$note = $notes ? $notes->get_note( $post->ID ) : null;

			$items[] = array(
				'url'         => $url,
				'title'       => html_entity_decode( get_the_title( $post ), ENT_QUOTES, get_bloginfo( 'charset' ) ),
				'tags'        => wp_list_pluck( $this->get_post_terms( $post ), 'name' ),
				'created'     => (int) get_post_time( 'U', true, $post ),
				'modified'    => (int) get_post_modified_time( 'U', true, $post ),
				'description' => $note && ! empty( $note['notes'] ) ? $note['notes'] : '',
				'unread'      => ! $note || Article_Notes::STATUS_UNREAD === $note['status'],
			);
		}

		return $items;
	}

	/**
	 * Build an export file for one or more collections.
	 *
	 * @param array  $collections Collection terms.
	 * @param string $format      Format key from get_export_formats().
	 * @return array|\WP_Error Array with content, filename and mime keys.
	 */
	public function export_collections( array $collections, $format ) {
		$formats = $this->get_export_formats();
		if ( ! isset( $formats[ $format ] ) ) {
			return new \WP_Error( 'unsupported_export_format', __( 'Unsupported export format.', 'post-collection' ), array( 'status' => 400 ) );
		}

		$groups = array();
		foreach ( $collections as $collection ) {
			$groups[] = array(
				'title' => $collection->name,
				'items' => $this->get_collection_export_items( $collection ),
			);
		}

		if ( 1 === count( $collections ) ) {
			$collection = reset( $collections );
			$title      = $collection->name;
			$slug       = sanitize_title( $collection->slug ? $collection->slug : $collection->name );
		} else {
			$title = get_bloginfo( 'name' );
			$slug  = 'post-collections';
		}

		if ( 'opml' === $format ) {
			$content = $this->post_collection->build_opml_export( $groups, $title, get_bloginfo( 'name' ) );
		} else {
			$content = $this->post_collection->build_bookmarks_html_export( $groups );
		}

		return array(
			'content'  => $content,
			'filename' => ( $slug ? $slug : 'collection' ) . '-' . gmdate( 'Y-m-d' ) . '.' . $formats[ $format ]['extension'],
			'mime'     => $formats[ $format ]['mime'],
		);
	}

	/**
	 * Read an uploaded import file.
	 *
	 * @param array $file Uploaded file data.
	 * @return array|\WP_Error Import source or error.
	 */
	private function get_import_upload_source( array $file ) {
		if ( UPLOAD_ERR_OK !== (int) $file['error'] ) {
			return new \WP_Error( 'upload_error', __( 'The import file could not be uploaded.', 'post-collection' ), array( 'status' => 400 ) );
		}

		$filename  = isset( $file['name'] ) ? sanitize_file_name( wp_unslash( $file['name'] ) ) : '';
		$extension = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
		if ( ! in_array( $extension, array( 'csv', 'html', 'htm', 'xml', 'rss', 'atom', 'opml', 'txt' ), true ) ) {
			return new \WP_Error( 'unsupported_import_file', __( 'Please upload a CSV, bookmarks HTML, RSS, Atom, OPML, XML, or text file.', 'post-collection' ), array( 'status' => 400 ) );
		}

		$max_size = (int) apply_filters( 'post_collection_import_upload_size_limit', 5 * 1024 * 1024 );
		$size     = isset( $file['size'] ) ? (int) $file['size'] : 0;
		if ( $max_size > 0 && $size > $max_size ) {
			return new \WP_Error( 'import_file_too_large', __( 'The import file is too large.', 'post-collection' ), array( 'status' => 413 ) );
		}

		$tmp_name = isset( $file['tmp_name'] ) ? $file['tmp_name'] : '';
		if ( ! $tmp_name || ! is_uploaded_file( $tmp_name ) ) {
			return new \WP_Error( 'invalid_import_upload', __( 'The import file upload is invalid.', 'post-collection' ), array( 'status' => 400 ) );
		}

		$content = file_get_contents( $tmp_name );
		if ( false === $content ) {
			return new \WP_Error( 'import_file_unreadable', __( 'The import file could not be read.', 'post-collection' ), array( 'status' => 400 ) );
		}

		return array(
			'content'  => $content,
			'filename' => $filename,
		);
	}

	/**
	 * Parse an import source for the client-side importer.
	 */
	public function wp_ajax_parse_import() {
		$collection = $this->get_import_collection_from_ajax_request();
		if ( is_wp_error( $collection ) ) {
			wp_send_json_error(
				array(
					'message' => $collection->get_error_message(),
				),
				$collection->get_error_data( 'status' ) ? $collection->get_error_data( 'status' ) : 400
			);
		}

		$sources    = array();
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- A pasted export file, parsed structurally below; every extracted URL, title and tag is sanitized by normalize_import_item().
		$raw_import = isset( $_POST['import_urls'] ) ? wp_unslash( $_POST['import_urls'] ) : '';
		if ( trim( (string) $raw_import ) ) {
			$sources[] = array(
				'content'  => $raw_import,
				'filename' => 'pasted.txt',
			);
		}

		if ( isset( $_FILES['import_file'] ) && isset( $_FILES['import_file']['error'] ) && UPLOAD_ERR_NO_FILE !== (int) $_FILES['import_file']['error'] ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- get_import_upload_source() validates the upload and sanitizes the file name.
			$file_source = $this->get_import_upload_source( $_FILES['import_file'] );
			if ( is_wp_error( $file_source ) ) {
				wp_send_json_error(
					array(
						'message' => $file_source->get_error_message(),
					),
					$file_source->get_error_data( 'status' ) ? $file_source->get_error_data( 'status' ) : 400
				);
			}
			$sources[] = $file_source;
		}

		$items = $this->post_collection->parse_import_sources( $sources );

		wp_send_json_success(
			array(
				'items' => $items,
				'total' => count( $items ),
			)
		);
	}

	/**
	 * Import one item for the client-side importer.
	 */
	public function wp_ajax_import_item() {
		$collection = $this->get_import_collection_from_ajax_request();
		if ( is_wp_error( $collection ) ) {
			wp_send_json_error(
				array(
					'message' => $collection->get_error_message(),
				),
				$collection->get_error_data( 'status' ) ? $collection->get_error_data( 'status' ) : 400
			);
		}

		$item = array(
			'url'   => isset( $_POST['url'] ) ? esc_url_raw( wp_unslash( $_POST['url'] ) ) : '',
			'title' => isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '',
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Either a comma separated string or an array; both are sanitized by sanitize_import_tags() via normalize_import_item().
			'tags'  => isset( $_POST['tags'] ) ? wp_unslash( $_POST['tags'] ) : array(),
		);

		if ( is_string( $item['tags'] ) ) {
			$item['tags'] = array_filter( array_map( 'trim', explode( ',', $item['tags'] ) ) );
		}

		$result = $this->post_collection->import_item_to_collection( $collection, $item );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				array(
					'message' => $result->get_error_message(),
					'url'     => isset( $item['url'] ) ? $item['url'] : '',
				),
				$result->get_error_data( 'status' ) ? $result->get_error_data( 'status' ) : 400
			);
		}

		wp_send_json_success( $result );
	}

	/**
	 * Validate and resolve the collection for import AJAX requests.
	 *
	 * @return \WP_Term|\WP_Error
	 */
	private function get_import_collection_from_ajax_request() {
		if ( ! $this->can_manage_collections() ) {
			return new \WP_Error( 'forbidden', __( 'Sorry, you are not allowed to import into this collection.', 'post-collection' ), array( 'status' => 403 ) );
		}

		$term_id = isset( $_POST['collection_term_id'] ) ? absint( wp_unslash( $_POST['collection_term_id'] ) ) : 0;
		if ( ! $term_id || ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'post-collection-import-' . $term_id ) ) {
			return new \WP_Error( 'invalid_nonce', __( 'The collection import request could not be verified.', 'post-collection' ), array( 'status' => 403 ) );
		}

		$collection = get_term( $term_id, Post_Collection::COLLECTION_TAXONOMY );
		if ( ! $collection || is_wp_error( $collection ) ) {
			return new \WP_Error( 'invalid_collection', __( 'Invalid post collection.', 'post-collection' ), array( 'status' => 404 ) );
		}

		return $collection;
	}

	/**
	 * Handle frontend collection creation submissions.
	 */
	public function handle_create_collection() {
		if ( wp_doing_ajax() ) {
			return;
		}

		if ( ! isset( $_POST['post_collection_action'] ) || 'create-collection' !== $_POST['post_collection_action'] ) {
			return;
		}

		$result = $this->create_collection_from_request( $_POST );
		if ( is_wp_error( $result ) ) {
			$status_code = $result->get_error_data( 'status' );
			wp_die( wp_kses_post( $result->get_error_message() ), '', array( 'response' => absint( $status_code ? $status_code : 400 ) ) );
		}

		wp_safe_redirect( $this->get_collection_url( $result ) );
		exit;
	}

	/**
	 * Create a post collection term from request data.
	 *
	 * @param array $data Request data.
	 * @return \WP_Term|\WP_Error
	 */
	public function create_collection_from_request( array $data ) {
		if ( ! $this->can_manage_collections() ) {
			return new \WP_Error( 'forbidden', __( 'Sorry, you are not allowed to create collections.', 'post-collection' ), array( 'status' => 403 ) );
		}

		if ( ! isset( $data['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $data['_wpnonce'] ) ), 'post-collection-create' ) ) {
			return new \WP_Error( 'invalid_nonce', __( 'The create collection request could not be verified.', 'post-collection' ), array( 'status' => 403 ) );
		}

		$display_name = isset( $data['display_name'] ) ? sanitize_text_field( wp_unslash( $data['display_name'] ) ) : '';
		$user_login   = isset( $data['user_login'] ) ? sanitize_user( wp_unslash( $data['user_login'] ) ) : '';
		if ( ! $user_login && $display_name ) {
			$user_login = User::sanitize_username( $display_name );
		}

		if ( ! $user_login ) {
			return new \WP_Error( 'invalid_collection_slug', __( 'Please enter a valid collection slug.', 'post-collection' ), array( 'status' => 400 ) );
		}

		if ( term_exists( $user_login, Post_Collection::COLLECTION_TAXONOMY ) ) {
			return new \WP_Error( 'existing_collection_slug', __( 'That collection slug already exists. Please choose another one.', 'post-collection' ), array( 'status' => 400 ) );
		}

		if ( ! $display_name ) {
			return new \WP_Error( 'invalid_display_name', __( 'Please enter a display name.', 'post-collection' ), array( 'status' => 400 ) );
		}

		$created = wp_insert_term(
			$display_name,
			Post_Collection::COLLECTION_TAXONOMY,
			array(
				'slug' => $user_login,
			)
		);

		if ( is_wp_error( $created ) ) {
			return $created;
		}

		$collection = get_term( $created['term_id'], Post_Collection::COLLECTION_TAXONOMY );
		if ( ! $collection || is_wp_error( $collection ) ) {
			return new \WP_Error( 'collection_not_found', __( 'The collection was created but could not be loaded.', 'post-collection' ), array( 'status' => 500 ) );
		}

		$frontend_mode = isset( $data['frontend_mode'] ) ? sanitize_key( wp_unslash( $data['frontend_mode'] ) ) : 'posts';
		if ( in_array( $frontend_mode, array( 'bookmarks', 'posts' ), true ) ) {
			update_term_meta( $collection->term_id, 'post_collection_frontend_mode', $frontend_mode );
		}

		$frontend_view = isset( $data['frontend_view'] ) ? sanitize_key( wp_unslash( $data['frontend_view'] ) ) : 'reader';
		if ( in_array( $frontend_view, array( 'board', 'links', 'reader' ), true ) ) {
			update_term_meta( $collection->term_id, 'post_collection_frontend_view', $frontend_view );
		}

		if ( ! empty( $data['hide_from_home'] ) ) {
			update_term_meta( $collection->term_id, 'post_collection_hide_from_home', true );
		}

		update_term_meta( $collection->term_id, 'friends_publish_post_collection', true );

		return $collection;
	}

	/**
	 * Whether quick edit mode is active for the current request.
	 *
	 * @return bool
	 */
	public function is_quick_edit_mode() {
		return $this->can_manage_collections() && $this->get_quick_edit_post_id() > 0;
	}

	/**
	 * Get the post currently targeted by quick edit.
	 *
	 * @return int
	 */
	public function get_quick_edit_post_id() {
		if ( ! isset( $_GET['pc-edit'] ) ) {
			return 0;
		}

		return absint( wp_unslash( $_GET['pc-edit'] ) );
	}

	/**
	 * Handle quick edit submissions from the links view.
	 */
	public function handle_quick_edit() {
		if ( wp_doing_ajax() ) {
			return;
		}

		if ( ! isset( $_POST['post_collection_action'] ) || 'quick-edit' !== $_POST['post_collection_action'] ) {
			return;
		}

		$post_id = isset( $_POST['post_id'] ) ? absint( wp_unslash( $_POST['post_id'] ) ) : 0;
		$result = $this->update_quick_edit_post( $_POST );
		if ( is_wp_error( $result ) ) {
			$status_code = $result->get_error_data( 'status' );
			wp_die( esc_html( $result->get_error_message() ), '', array( 'response' => absint( $status_code ? $status_code : 400 ) ) );
		}

		$redirect = wp_get_referer();
		if ( ! $redirect ) {
			$post = get_post( $post_id );
			$collection = $post ? $this->get_post_collection_term( $post ) : null;
			$redirect = $collection ? $this->get_collection_url( $collection ) : $this->get_home_url();
		}

		wp_safe_redirect( add_query_arg( array( 'pc-view' => 'links', 'pc-edit' => $post_id ), remove_query_arg( array( '_wpnonce' ), $redirect ) ) . '#pc-link-' . $post_id );
		exit;
	}

	/**
	 * Handle quick edit AJAX submissions from the links view.
	 */
	public function wp_ajax_quick_edit() {
		$result = $this->update_quick_edit_post( $_POST );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				array(
					'message' => $result->get_error_message(),
				),
				$result->get_error_data( 'status' ) ? $result->get_error_data( 'status' ) : 400
			);
		}

		wp_send_json_success( $result );
	}

	/**
	 * Toggle a collected post between unread and read.
	 */
	public function wp_ajax_toggle_read_status() {
		if ( ! $this->can_manage_collections() ) {
			wp_send_json_error(
				array(
					'message' => __( 'Sorry, you are not allowed to edit this collection.', 'post-collection' ),
				),
				403
			);
		}

		$post_id = isset( $_POST['post_id'] ) ? absint( wp_unslash( $_POST['post_id'] ) ) : 0;
		if ( ! $post_id || ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'post-collection-toggle-read-status-' . $post_id ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'The reading status request could not be verified.', 'post-collection' ),
				),
				403
			);
		}

		$post = get_post( $post_id );
		if ( ! $post || Post_Collection::CPT !== $post->post_type ) {
			wp_send_json_error(
				array(
					'message' => __( 'That post does not exist.', 'post-collection' ),
				),
				404
			);
		}

		$current_status = $this->get_article_note_status( $post );
		$next_status    = Article_Notes::STATUS_READ === $current_status ? Article_Notes::STATUS_UNREAD : Article_Notes::STATUS_READ;
		$note_id        = $this->post_collection->get_article_notes()->save_note( $post_id, $next_status );
		if ( ! $note_id ) {
			wp_send_json_error(
				array(
					'message' => __( 'The reading status could not be saved.', 'post-collection' ),
				),
				500
			);
		}

		wp_send_json_success(
			array(
				'id'          => $post_id,
				'read_status' => $next_status,
				'read_label'  => $this->get_article_note_status_label( $next_status ),
			)
		);
	}

	/**
	 * Update a post from quick edit request data.
	 *
	 * @param array $data Request data.
	 * @return array|\WP_Error
	 */
	private function update_quick_edit_post( array $data ) {
		if ( ! $this->can_manage_collections() ) {
			return new \WP_Error( 'forbidden', __( 'Sorry, you are not allowed to edit this collection.', 'post-collection' ), array( 'status' => 403 ) );
		}

		$post_id = isset( $data['post_id'] ) ? absint( wp_unslash( $data['post_id'] ) ) : 0;
		if ( ! $post_id || ! isset( $data['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $data['_wpnonce'] ) ), 'post-collection-quick-edit-' . $post_id ) ) {
			return new \WP_Error( 'invalid_nonce', __( 'The quick edit request could not be verified.', 'post-collection' ), array( 'status' => 403 ) );
		}

		$post = get_post( $post_id );
		if ( ! $post || Post_Collection::CPT !== $post->post_type ) {
			return new \WP_Error( 'not_found', __( 'That post does not exist.', 'post-collection' ), array( 'status' => 404 ) );
		}

		$title          = isset( $data['post_title'] ) ? sanitize_text_field( wp_unslash( $data['post_title'] ) ) : $post->post_title;
		$url            = isset( $data['source_url'] ) ? esc_url_raw( wp_unslash( $data['source_url'] ) ) : $post->guid;
		$description    = isset( $data['post_excerpt'] ) ? sanitize_textarea_field( wp_unslash( $data['post_excerpt'] ) ) : $post->post_excerpt;
		$status         = isset( $data['post_status'] ) && 'publish' === wp_unslash( $data['post_status'] ) ? 'publish' : 'private';
		$article_status = isset( $data['article_status'] ) ? sanitize_key( wp_unslash( $data['article_status'] ) ) : '';

		$update = array(
			'ID'           => $post_id,
			'post_title'   => $title ? $title : $post->post_title,
			'post_excerpt' => $description,
			'post_status'  => $status,
		);

		if ( $this->post_collection->check_url( $url ) ) {
			$update['guid'] = $url;
		}

		$updated = wp_update_post( $update, true );
		if ( is_wp_error( $updated ) ) {
			return $updated;
		}

		if ( $article_status && in_array( $article_status, array_keys( $this->get_article_statuses() ), true ) ) {
			$note_id = $this->post_collection->get_article_notes()->save_note( $post_id, $article_status );
			if ( ! $note_id ) {
				return new \WP_Error( 'note_save_failed', __( 'The reading status could not be saved.', 'post-collection' ), array( 'status' => 500 ) );
			}
		}

		$taxonomy = $this->post_collection->get_tag_taxonomy();
		if ( taxonomy_exists( $taxonomy ) ) {
			$tags = isset( $data['post_tags'] ) ? sanitize_text_field( wp_unslash( $data['post_tags'] ) ) : '';
			$tags = array_filter( array_map( 'trim', explode( ',', $tags ) ) );
			$terms_updated = wp_set_object_terms( $post_id, $tags, $taxonomy, false );
			if ( is_wp_error( $terms_updated ) ) {
				return $terms_updated;
			}
		}

		$post = get_post( $post_id );
		$collection = $this->get_post_collection_term( $post );
		$terms = $this->get_post_terms( $post );
		$base_url = $collection ? $this->get_collection_url( $collection ) : $this->get_home_url();
		$term_data = array();
		foreach ( $terms as $term ) {
			$term_data[] = array(
				'name' => $term->name,
				'slug' => $term->slug,
				'url'  => add_query_arg(
					array(
						'pc-tag'  => $term->slug,
						'pc-view' => 'links',
					),
					$base_url
				),
			);
		}

		$source_url   = $this->get_source_url( $post );
		$read_status = $this->get_article_note_status( $post );

		return array(
			'id'          => $post->ID,
			'title'       => get_the_title( $post ),
			'source_url'  => $source_url,
			'display_url' => preg_replace( '#^https?://#', '', $source_url ),
			'excerpt'     => $this->get_post_excerpt( $post, 24 ),
			'embed_html'  => $this->get_post_embed_html( $post, 'links' ),
			'host'        => $this->get_source_host( $post ),
			'word_count'  => $this->get_post_word_count( $post ),
			'read_time'   => $this->get_post_read_time_label( $post ),
			'is_private'  => 'private' === $post->post_status,
			'status'      => $post->post_status,
			'read_status' => $read_status,
			'read_label'  => $this->get_article_note_status_label( $read_status ),
			'terms'       => $term_data,
		);
	}

	/**
	 * Get available reading statuses.
	 *
	 * @return array
	 */
	public function get_article_statuses() {
		return Article_Notes::get_statuses();
	}

	/**
	 * Get the current reading status for a collected post.
	 *
	 * @param \WP_Post $post The post.
	 * @return string
	 */
	public function get_article_note_status( \WP_Post $post ) {
		$note = $this->post_collection->get_article_notes()->get_note( $post->ID );
		if ( $note && ! empty( $note['status'] ) && in_array( $note['status'], array_keys( $this->get_article_statuses() ), true ) ) {
			return $note['status'];
		}

		return Article_Notes::STATUS_UNREAD;
	}

	/**
	 * Get a label for a reading status.
	 *
	 * @param string $status Reading status.
	 * @return string
	 */
	public function get_article_note_status_label( $status ) {
		$statuses = $this->get_article_statuses();
		return isset( $statuses[ $status ] ) ? $statuses[ $status ] : $statuses[ Article_Notes::STATUS_UNREAD ];
	}

	/**
	 * Render an interactive reading status label.
	 *
	 * @param \WP_Post $post   The post.
	 * @param string   $status Optional reading status.
	 */
	public function render_article_note_status_toggle( \WP_Post $post, $status = '' ) {
		if ( ! $this->can_manage_collections() ) {
			return;
		}

		$status = $status ? $status : $this->get_article_note_status( $post );
		$label  = $this->get_article_note_status_label( $status );

		// translators: %s is the title of a collected article.
		$toggle_label = sprintf( __( 'Toggle reading status for %s', 'post-collection' ), get_the_title( $post ) );
		?>
		<button
			type="button"
			class="pc-read-status pc-read-status-toggle pc-read-status-<?php echo esc_attr( $status ); ?>"
			data-ajax-action="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"
			data-post-id="<?php echo esc_attr( $post->ID ); ?>"
			data-nonce="<?php echo esc_attr( wp_create_nonce( 'post-collection-toggle-read-status-' . $post->ID ) ); ?>"
			data-read-status="<?php echo esc_attr( $status ); ?>"
			aria-label="<?php echo esc_attr( $toggle_label ); ?>"
		><?php echo esc_html( $label ); ?></button>
		<?php
	}

	/**
	 * Get available frontend views.
	 *
	 * @return array
	 */
	public function get_available_views() {
		return array(
			'board'  => __( 'Board', 'post-collection' ),
			'links'  => __( 'Links', 'post-collection' ),
			'reader' => __( 'Reader', 'post-collection' ),
		);
	}

	/**
	 * Query posts for a collection.
	 *
	 * @param \WP_Term $collection The collection term.
	 * @param array         $args Optional query args.
	 * @return \WP_Query
	 */
	public function query_collection_posts( $collection, array $args = array() ) {
		$apply_filters = ! isset( $args['post_collection_apply_filters'] ) || $args['post_collection_apply_filters'];
		unset( $args['post_collection_apply_filters'] );

		$defaults = array(
			'post_type'           => Post_Collection::CPT,
			'post_status'         => $this->can_view_private_posts( $collection ) ? array( 'publish', 'private' ) : array( 'publish' ),
			'posts_per_page'      => 24,
			'paged'               => isset( $_GET['pc-page'] ) ? max( 1, absint( wp_unslash( $_GET['pc-page'] ) ) ) : 1,
			'ignore_sticky_posts' => true,
			'tax_query'           => array(
				array(
					'taxonomy' => Post_Collection::COLLECTION_TAXONOMY,
					'field'    => 'term_id',
					'terms'    => $collection->term_id,
				),
			),
		);

		$query_args = wp_parse_args( $args, $defaults );

		if ( $apply_filters && isset( $_GET['pc-search'] ) && '' !== trim( sanitize_text_field( wp_unslash( $_GET['pc-search'] ) ) ) ) {
			$query_args['s'] = sanitize_text_field( wp_unslash( $_GET['pc-search'] ) );
		}

		if ( $apply_filters && isset( $_GET['pc-tag'] ) && taxonomy_exists( $this->post_collection->get_tag_taxonomy() ) ) {
			$query_args['tax_query'][] = array(
				'taxonomy' => $this->post_collection->get_tag_taxonomy(),
				'field'    => 'slug',
				'terms'    => sanitize_title( wp_unslash( $_GET['pc-tag'] ) ),
			);
		}

		return new \WP_Query( $query_args );
	}

	/**
	 * Query collected posts independently of the page currently being rendered.
	 *
	 * Integrations use this to assemble a list of posts for an export.
	 *
	 * @param \WP_Term|null $collection Optional collection to restrict the result to.
	 * @param array         $args       Query args. Two extra keys are understood:
	 *                                  post_collection_include_private includes private
	 *                                  posts and posts in unpublished collections,
	 *                                  post_collection_unread_only drops articles that
	 *                                  have been read, skipped or archived.
	 * @return \WP_Post[] The matching posts, newest first.
	 */
	public function query_app_posts( $collection = null, array $args = array() ) {
		$include_private = ! empty( $args['post_collection_include_private'] ) || $this->can_manage_collections();
		$unread_only     = ! empty( $args['post_collection_unread_only'] );
		unset( $args['post_collection_include_private'], $args['post_collection_unread_only'] );

		$query_args = wp_parse_args(
			$args,
			array(
				'post_type'           => Post_Collection::CPT,
				'post_status'         => $include_private ? array( 'publish', 'private' ) : array( 'publish' ),
				'posts_per_page'      => -1,
				'ignore_sticky_posts' => true,
				'orderby'             => 'date',
				'order'               => 'DESC',
			)
		);

		$terms = array();
		if ( $collection instanceof \WP_Term ) {
			if ( ! $include_private && ! $this->can_view_collection( $collection ) ) {
				return array();
			}

			$terms[] = $collection->term_id;
		} elseif ( ! $include_private ) {
			foreach ( $this->get_collections() as $term ) {
				if ( $this->can_view_collection( $term ) ) {
					$terms[] = $term->term_id;
				}
			}

			if ( empty( $terms ) ) {
				return array();
			}
		}

		if ( ! empty( $terms ) ) {
			$query_args['tax_query']   = isset( $query_args['tax_query'] ) ? (array) $query_args['tax_query'] : array();
			$query_args['tax_query'][] = array(
				'taxonomy' => Post_Collection::COLLECTION_TAXONOMY,
				'field'    => 'term_id',
				'terms'    => $terms,
			);
		}

		if ( $unread_only ) {
			$read_ids = $this->post_collection->get_article_notes()->get_read_article_ids();
			if ( ! empty( $read_ids ) ) {
				$query_args['post__not_in'] = array_merge(
					isset( $query_args['post__not_in'] ) ? (array) $query_args['post__not_in'] : array(),
					$read_ids
				);
			}
		}

		$query = new \WP_Query( $query_args );

		return $query->get_posts();
	}

	/**
	 * Get the collected posts that have not been read yet.
	 *
	 * Unread means an article that carries no note, or a note that is still on
	 * the unread status: the same set the review queue works through.
	 *
	 * @param \WP_Term|null $collection Optional collection to restrict the result to.
	 * @param array         $args       Additional query args for query_app_posts().
	 * @return \WP_Post[] The unread posts, newest first.
	 */
	public function get_unread_posts( $collection = null, array $args = array() ) {
		$args['post_collection_unread_only'] = true;

		return $this->query_app_posts( $collection, $args );
	}

	/**
	 * Whether an integration offers actions for individual items.
	 *
	 * @return bool
	 */
	public function has_item_actions() {
		return (bool) has_action( 'post_collection_app_item_actions' );
	}

	/**
	 * Whether an integration offers actions for a selection of items.
	 *
	 * @return bool
	 */
	public function has_selection_actions() {
		return (bool) has_action( 'post_collection_app_selection_actions' );
	}

	/**
	 * Render the actions an integration offers for a single item.
	 *
	 * @param \WP_Post $post    The item.
	 * @param string   $context Where the item is being rendered: board, links,
	 *                          reader, review or detail.
	 */
	public function render_item_actions( \WP_Post $post, $context = '' ) {
		if ( ! $this->has_item_actions() ) {
			return;
		}

		echo '<span class="pc-item-actions">';
		/**
		 * Fires in the action area of a single item in the app.
		 *
		 * @param \WP_Post            $post    The item.
		 * @param string              $context Where the item is being rendered.
		 * @param Post_Collection_App $app     The app instance.
		 */
		do_action( 'post_collection_app_item_actions', $post, $context, $this );
		echo '</span>';
	}

	/**
	 * Render the checkbox that puts an item into the current selection.
	 *
	 * @param \WP_Post $post    The item.
	 * @param string   $context Where the item is being rendered.
	 */
	public function render_item_select( \WP_Post $post, $context = '' ) {
		if ( ! $this->has_selection_actions() ) {
			return;
		}

		?>
		<label class="pc-select">
			<input
				type="checkbox"
				class="pc-select-item"
				value="<?php echo esc_attr( $post->ID ); ?>"
				data-pc-select-item="<?php echo esc_attr( $context ); ?>"
				data-post-title="<?php echo esc_attr( get_the_title( $post ) ); ?>"
				aria-label="
				<?php
					// translators: %s is the title of a collected article.
					echo esc_attr( sprintf( __( 'Select %s', 'post-collection' ), get_the_title( $post ) ) );
				?>
				"
			>
		</label>
		<?php
	}

	/**
	 * Render the bar that appears once items have been selected.
	 *
	 * @param string        $context    Which list the selection is made in.
	 * @param \WP_Term|null $collection The collection in context, if any.
	 */
	public function render_selection_bar( $context = '', $collection = null ) {
		if ( ! $this->has_selection_actions() ) {
			return;
		}

		?>
		<div class="pc-selection-bar" data-pc-selection-bar hidden>
			<span class="pc-selection-count" data-pc-selection-count aria-live="polite"></span>
			<div class="pc-selection-actions">
				<?php
				/**
				 * Fires in the selection bar, once per app list that offers a selection.
				 *
				 * @param string              $context    Which list the selection is made in.
				 * @param \WP_Term|null       $collection The collection in context, if any.
				 * @param Post_Collection_App $app        The app instance.
				 */
				do_action( 'post_collection_app_selection_actions', $context, $collection, $this );
				?>
			</div>
			<button type="button" class="pc-selection-link" data-pc-selection-all><?php esc_html_e( 'Select all', 'post-collection' ); ?></button>
			<button type="button" class="pc-selection-link" data-pc-selection-clear><?php esc_html_e( 'Clear', 'post-collection' ); ?></button>
		</div>
		<?php
	}

	/**
	 * Get a visible post from a collection.
	 *
	 * @param int           $post_id The post ID.
	 * @param \WP_Term      $collection The collection term.
	 * @return \WP_Post|null
	 */
	public function get_visible_post( $post_id, $collection ) {
		$post = get_post( $post_id );

		if ( ! $post || Post_Collection::CPT !== $post->post_type || ! has_term( $collection->term_id, Post_Collection::COLLECTION_TAXONOMY, $post ) ) {
			return null;
		}

		if ( 'publish' !== $post->post_status && ! $this->can_view_private_posts( $collection ) ) {
			return null;
		}

		return $post;
	}

	/**
	 * Count visible posts in a collection.
	 *
	 * @param \WP_Term $collection The collection term.
	 * @return int
	 */
	public function count_collection_posts( $collection ) {
		$query = $this->query_collection_posts(
			$collection,
			array(
				'posts_per_page'                 => 1,
				'fields'                         => 'ids',
				'post_collection_apply_filters' => false,
			)
		);

		return intval( $query->found_posts );
	}

	/**
	 * Get the collection assigned to a post.
	 *
	 * @param \WP_Post $post The post.
	 * @return \WP_Term|null
	 */
	public function get_post_collection_term( \WP_Post $post ) {
		$terms = get_the_terms( $post, Post_Collection::COLLECTION_TAXONOMY );
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return null;
		}

		return reset( $terms );
	}

	/**
	 * Get the source URL for a collected post.
	 *
	 * @param \WP_Post $post The post.
	 * @return string
	 */
	public function get_source_url( \WP_Post $post ) {
		if ( $this->post_collection->check_url( $post->guid ) ) {
			return $post->guid;
		}

		return get_permalink( $post );
	}

	/**
	 * Get a compact source host.
	 *
	 * @param \WP_Post $post The post.
	 * @return string
	 */
	public function get_source_host( \WP_Post $post ) {
		$host = wp_parse_url( $this->get_source_url( $post ), PHP_URL_HOST );
		if ( ! $host ) {
			return '';
		}

		return preg_replace( '#^www\.#', '', strtolower( $host ) );
	}

	/**
	 * Get embeddable media HTML for a post preview.
	 *
	 * @param \WP_Post $post    The post.
	 * @param string   $context Display context for CSS classes.
	 * @return string
	 */
	public function get_post_embed_html( \WP_Post $post, $context = 'collection' ) {
		$video_id = $this->get_post_youtube_video_id( $post );
		if ( ! $video_id ) {
			return '';
		}

		return $this->render_youtube_embed( $video_id, get_the_title( $post ), $context );
	}

	/**
	 * Get embeddable media HTML from the explicit post description.
	 *
	 * @param \WP_Post $post    The post.
	 * @param string   $context Display context for CSS classes.
	 * @return string
	 */
	public function get_post_description_embed_html( \WP_Post $post, $context = 'collection' ) {
		$video_id = $this->get_youtube_video_id_from_text( $post->post_excerpt );
		if ( ! $video_id ) {
			return '';
		}

		return $this->render_youtube_embed( $video_id, get_the_title( $post ), $context );
	}

	/**
	 * Get a YouTube video ID from a post's standalone media URL.
	 *
	 * @param \WP_Post $post The post.
	 * @return string
	 */
	private function get_post_youtube_video_id( \WP_Post $post ) {
		$video_id = $this->get_youtube_video_id_from_text( $post->post_excerpt );
		if ( $video_id ) {
			return $video_id;
		}

		return $this->get_youtube_video_id_from_text( $post->post_content );
	}

	/**
	 * Get a YouTube video ID from text that only contains a YouTube URL.
	 *
	 * @param string $text Text to inspect.
	 * @return string
	 */
	private function get_youtube_video_id_from_text( $text ) {
		$url = $this->get_standalone_url_from_text( $text );
		if ( ! $url ) {
			return '';
		}

		return $this->get_youtube_video_id_from_url( $url );
	}

	/**
	 * Get a URL from text when it is the only meaningful content.
	 *
	 * @param string $text Text to inspect.
	 * @return string
	 */
	private function get_standalone_url_from_text( $text ) {
		$text = preg_replace( '#<!--.*?-->#s', '', (string) $text );
		$text = trim( html_entity_decode( wp_strip_all_tags( strip_shortcodes( $text ) ), ENT_QUOTES, 'UTF-8' ) );

		if ( '' === $text || preg_match( '#\s#', $text ) || ! preg_match( '#^https?://#i', $text ) ) {
			return '';
		}

		return $text;
	}

	/**
	 * Extract a YouTube video ID from a supported URL.
	 *
	 * @param string $url The URL.
	 * @return string
	 */
	private function get_youtube_video_id_from_url( $url ) {
		$url   = html_entity_decode( trim( $url ), ENT_QUOTES, 'UTF-8' );
		$parts = wp_parse_url( $url );

		if ( ! is_array( $parts ) || empty( $parts['host'] ) ) {
			return '';
		}

		$host = preg_replace( '#^www\.#', '', strtolower( $parts['host'] ) );
		$path = isset( $parts['path'] ) ? $parts['path'] : '';
		$id   = '';

		if ( 'youtu.be' === $host ) {
			$path_parts = explode( '/', trim( $path, '/' ) );
			$id         = reset( $path_parts );
		} elseif ( in_array( $host, array( 'youtube.com', 'm.youtube.com', 'music.youtube.com', 'youtube-nocookie.com' ), true ) ) {
			if ( '/watch' === $path && ! empty( $parts['query'] ) ) {
				$query = array();
				parse_str( $parts['query'], $query );
				$id = isset( $query['v'] ) && is_string( $query['v'] ) ? $query['v'] : '';
			} elseif ( preg_match( '#^/(?:embed|shorts|live)/([^/?#]+)#', $path, $matches ) ) {
				$id = $matches[1];
			}
		}

		$id = is_string( $id ) ? trim( $id ) : '';

		return preg_match( '#^[A-Za-z0-9_-]{11}$#', $id ) ? $id : '';
	}

	/**
	 * Render a safe YouTube iframe.
	 *
	 * @param string $video_id YouTube video ID.
	 * @param string $title    Video title.
	 * @param string $context  Display context for CSS classes.
	 * @return string
	 */
	private function render_youtube_embed( $video_id, $title, $context ) {
		$context   = sanitize_html_class( $context );
		$title     = $title ? $title : __( 'YouTube video', 'post-collection' );
		$embed_url = add_query_arg( 'rel', '0', 'https://www.youtube-nocookie.com/embed/' . rawurlencode( $video_id ) );

		// translators: %s is the title of a YouTube video.
		$iframe_title = sprintf( __( 'YouTube video: %s', 'post-collection' ), $title );

		return sprintf(
			'<div class="pc-youtube-embed pc-youtube-embed-%1$s">' .
				'<iframe src="%2$s" title="%3$s" loading="lazy" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>' .
			'</div>',
			esc_attr( $context ),
			esc_url( $embed_url ),
			esc_attr( $iframe_title )
		);
	}

	/**
	 * Get the best image URL for a post card.
	 *
	 * @param \WP_Post $post The post.
	 * @return string
	 */
	public function get_post_image_url( \WP_Post $post ) {
		$thumbnail = get_the_post_thumbnail_url( $post, 'large' );
		if ( $thumbnail ) {
			return $thumbnail;
		}

		if ( class_exists( '\WP_HTML_Tag_Processor' ) ) {
			$processor = new \WP_HTML_Tag_Processor( $post->post_content );
			while ( $processor->next_tag( 'img' ) ) {
				$src = $processor->get_attribute( 'src' );
				if ( $src ) {
					return esc_url_raw( $src );
				}
			}
		}

		return '';
	}

	/**
	 * Get a trimmed excerpt for a card.
	 *
	 * @param \WP_Post $post   The post.
	 * @param int      $length Word length.
	 * @return string
	 */
	public function get_post_excerpt( \WP_Post $post, $length = 28 ) {
		if ( $this->get_post_youtube_video_id( $post ) ) {
			return '';
		}

		$text = $post->post_excerpt ? $post->post_excerpt : $post->post_content;
		$text = wp_strip_all_tags( strip_shortcodes( $text ) );

		return wp_trim_words( $text, $length );
	}

	/**
	 * Get the word or character count for a post.
	 *
	 * Mirrors the WordPress/Friends reading-time locale handling.
	 *
	 * @param \WP_Post $post The post.
	 * @return int
	 */
	public function get_post_word_count( \WP_Post $post ) {
		return count( $this->get_words_from_text( $post->post_content ) );
	}

	/**
	 * Get a localized word-count label for a post.
	 *
	 * @param \WP_Post $post The post.
	 * @return string
	 */
	public function get_post_word_count_label( \WP_Post $post ) {
		$word_count = $this->get_post_word_count( $post );

		return sprintf(
			/* translators: %s is a number of words. */
			_n( '%s word', '%s words', $word_count, 'post-collection' ),
			number_format_i18n( $word_count )
		);
	}

	/**
	 * Get a localized estimated read-time label for a post.
	 *
	 * @param \WP_Post $post The post.
	 * @return string
	 */
	public function get_post_read_time_label( \WP_Post $post ) {
		$read_time = $this->calculate_read_time( $post->post_content );

		if ( $read_time >= MINUTE_IN_SECONDS ) {
			$minutes = (int) ceil( $read_time / MINUTE_IN_SECONDS );

			return sprintf(
				/* translators: %s is a number of minutes. */
				_n( '%s min read', '%s mins read', $minutes, 'post-collection' ),
				number_format_i18n( $minutes )
			);
		}

		if ( $read_time > 20 ) {
			return _x( '< 1 min read', 'reading time', 'post-collection' );
		}

		return _x( 'quick read', 'reading time', 'post-collection' );
	}

	/**
	 * Calculate estimated read time in seconds.
	 *
	 * Based on the Friends plugin formula.
	 *
	 * @param string $original_text Original post content.
	 * @return float
	 */
	private function calculate_read_time( $original_text ) {
		$words_per_minute = $this->uses_character_word_count() ? 500 : 200;
		$additional_time  = 0;
		$figures          = substr_count( strtolower( $original_text ), '<figure' );

		for ( $i = 0; $i < $figures; $i++ ) {
			if ( $i < 10 ) {
				$additional_time += 12 - $i;
			} else {
				$additional_time += 3;
			}
		}

		return count( $this->get_words_from_text( $original_text ) ) / $words_per_minute * 60 + $additional_time;
	}

	/**
	 * Get words or characters from post content.
	 *
	 * @param string $original_text Original post content.
	 * @return array
	 */
	private function get_words_from_text( $original_text ) {
		$text = wp_strip_all_tags( $original_text );

		if ( $this->uses_character_word_count() && preg_match( '/^utf\-?8$/i', get_option( 'blog_charset' ) ) ) {
			$text = trim( preg_replace( "/[\n\r\t ]+/", ' ', $text ), ' ' );
			preg_match_all( '/./u', $text, $words_array );

			$words_array = array_shift( $words_array );

			return is_array( $words_array ) ? $words_array : array();
		}

		$words_array = preg_split( "/[\n\r\t ]+/", $text, -1, PREG_SPLIT_NO_EMPTY );

		return is_array( $words_array ) ? $words_array : array();
	}

	/**
	 * Whether the current locale counts characters instead of words.
	 *
	 * @return bool
	 */
	private function uses_character_word_count() {
		/*
		 * translators: If your word count is based on single characters (e.g. East Asian characters),
		 * enter 'characters_excluding_spaces' or 'characters_including_spaces'. Otherwise, enter 'words'.
		 * Do not translate into your own language.
		 */
		return 0 === strpos( _x( 'words', 'Word count type. Do not translate!', 'post-collection' ), 'characters' );
	}

	/**
	 * Get terms attached to a post.
	 *
	 * @param \WP_Post $post The post.
	 * @return array
	 */
	public function get_post_terms( \WP_Post $post ) {
		$taxonomy = $this->post_collection->get_tag_taxonomy();
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return array();
		}

		$terms = get_the_terms( $post, $taxonomy );
		if ( is_wp_error( $terms ) || ! $terms ) {
			return array();
		}

		return $terms;
	}

	/**
	 * Get popular terms for the visible collection posts.
	 *
	 * @param \WP_Term $collection The collection term.
	 * @return array
	 */
	public function get_collection_terms( $collection ) {
		$taxonomy = $this->post_collection->get_tag_taxonomy();
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return array();
		}

		$query = $this->query_collection_posts(
			$collection,
			array(
				'posts_per_page'                 => -1,
				'fields'                         => 'ids',
				'post_collection_apply_filters' => false,
			)
		);

		if ( empty( $query->posts ) ) {
			return array();
		}

		global $wpdb;

		$post_ids = array_map( 'absint', $query->posts );
		$placeholders = implode( ',', array_fill( 0, count( $post_ids ), '%d' ) );
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $placeholders only ever holds a comma separated list of %d placeholders; the values themselves go through prepare().
		$sql = $wpdb->prepare(
			"SELECT t.term_id, COUNT(*) AS collection_count
			FROM {$wpdb->terms} t
			INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
			INNER JOIN {$wpdb->term_relationships} tr ON tt.term_taxonomy_id = tr.term_taxonomy_id
			WHERE tt.taxonomy = %s
				AND tr.object_id IN ($placeholders)
			GROUP BY t.term_id
			ORDER BY collection_count DESC, t.name ASC
			LIMIT 100",
			array_merge( array( $taxonomy ), $post_ids )
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql is the return value of $wpdb->prepare() directly above.
		$rows = $wpdb->get_results( $sql );

		if ( ! $rows ) {
			return array();
		}

		$terms = array();
		foreach ( $rows as $row ) {
			$term = get_term( (int) $row->term_id, $taxonomy );
			if ( ! $term || is_wp_error( $term ) ) {
				continue;
			}
			if ( $this->is_noisy_tag_term( $term ) ) {
				continue;
			}
			$term->count = (int) $row->collection_count;
			$terms[] = $term;
			if ( count( $terms ) >= 18 ) {
				break;
			}
		}

		return $terms;
	}

	/**
	 * Determine whether a tag term is likely generated noise.
	 *
	 * @param \WP_Term $term The term.
	 * @return bool Whether to hide it from app tag navigation.
	 */
	private function is_noisy_tag_term( \WP_Term $term ) {
		$name = trim( (string) $term->name );
		$slug = trim( (string) $term->slug );

		if ( '' === $name || is_numeric( $name ) || is_numeric( $slug ) ) {
			return true;
		}

		return (bool) preg_match( '/^(?:[a-f0-9]{3}|[a-f0-9]{6})$/i', $name )
			|| (bool) preg_match( '/^(?:[a-f0-9]{3}|[a-f0-9]{6})$/i', $slug );
	}

	/**
	 * Get an external save URL for the collection.
	 *
	 * @param \WP_Term $collection The collection term.
	 * @return string
	 */
	public function get_save_url( $collection ) {
		return add_query_arg(
			array(
				'collection' => $collection->term_id,
			),
			home_url( '/' )
		);
	}
}
