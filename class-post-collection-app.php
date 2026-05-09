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
				'app_name'                      => __( 'Post Collection', 'post-collection' ),
				'my_apps'                       => __( 'Post Collection', 'post-collection' ),
				'show_wp_logo'                  => false,
				'show_site_name'                => true,
				'show_dark_mode_toggle'         => true,
				'show_masterbar_for_anonymous' => true,
			)
		);
	}

	/**
	 * Set up routes and assets.
	 */
	private function init() {
		add_action( 'wp_loaded', array( $this, 'handle_quick_edit' ) );
		add_action( 'wp_ajax_post_collection_quick_edit', array( $this, 'wp_ajax_quick_edit' ) );

		$this->app->route( '' );
		$this->app->route( '{username}', 'collection.php' );
		$this->app->route( '{username}/{post_id}', 'post.php' );

		$this->app->add_menu_item(
			'collections',
			__( 'Collections', 'post-collection' ),
			$this->get_home_url()
		);

		if ( current_user_can( $this->post_collection->get_required_role() ) ) {
			$this->app->add_menu_item(
				'new-collection',
				__( 'New Collection', 'post-collection' ),
				self_admin_url( 'admin.php?page=create-post-collection' )
			);
		}

		if ( function_exists( 'wp_app_enqueue_style' ) ) {
			wp_app_enqueue_style(
				'post-collection-app',
				plugins_url( 'assets/css/post-collection-app.css', POST_COLLECTION_PLUGIN_FILE ),
				array(),
				POST_COLLECTION_VERSION
			);
			wp_app_enqueue_script(
				'post-collection-app',
				plugins_url( 'assets/js/post-collection-app.js', POST_COLLECTION_PLUGIN_FILE ),
				array(),
				POST_COLLECTION_VERSION,
				true
			);
		}

		$this->app->init();
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
	 * Get the app home URL.
	 *
	 * @return string
	 */
	public function get_home_url() {
		return home_url( '/' . self::PATH . '/' );
	}

	/**
	 * Get the URL for a collection or a collected post.
	 *
	 * @param \WP_User|User $user    The collection user.
	 * @param int|null      $post_id Optional post ID.
	 * @return string
	 */
	public function get_collection_url( $user, $post_id = null ) {
		$path = '/' . self::PATH . '/' . rawurlencode( $user->user_login ) . '/';

		if ( $post_id ) {
			$path .= absint( $post_id ) . '/';
		}

		return home_url( $path );
	}

	/**
	 * Get collection users.
	 *
	 * @return array
	 */
	public function get_collections() {
		return $this->post_collection->get_post_collection_users()->get_results();
	}

	/**
	 * Find a collection by URL username.
	 *
	 * @param string $username The route username.
	 * @return User|null
	 */
	public function get_collection_by_username( $username ) {
		$username = sanitize_user( rawurldecode( $username ), true );

		foreach ( $this->get_collections() as $user ) {
			if (
				$user->user_login === $username ||
				sanitize_title( $user->user_login ) === sanitize_title( $username ) ||
				sanitize_title( $user->display_name ) === sanitize_title( $username )
			) {
				return $user;
			}
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
	 * @param User|\WP_User $user The collection user.
	 * @return bool
	 */
	public function can_view_collection( $user ) {
		if ( $this->can_manage_collections() ) {
			return true;
		}

		return (bool) get_user_option( 'friends_publish_post_collection', $user->ID );
	}

	/**
	 * Whether private posts should be included for this visitor.
	 *
	 * @param User|\WP_User $user The collection user.
	 * @return bool
	 */
	public function can_view_private_posts( $user ) {
		return $this->can_manage_collections();
	}

	/**
	 * Get the frontend mode for a collection.
	 *
	 * @param User|\WP_User $user The collection user.
	 * @return string
	 */
	public function get_collection_mode( $user ) {
		$mode = get_user_option( 'post_collection_frontend_mode', $user->ID );
		if ( in_array( $mode, array( 'bookmarks', 'posts' ), true ) ) {
			return $mode;
		}

		if ( $this->is_bookmark_collection( $user ) ) {
			return 'bookmarks';
		}

		return 'posts';
	}

	/**
	 * Get the default frontend view for a collection.
	 *
	 * @param User|\WP_User $user The collection user.
	 * @return string
	 */
	public function get_collection_default_view( $user ) {
		$view = get_user_option( 'post_collection_frontend_view', $user->ID );
		if ( in_array( $view, array( 'board', 'links', 'reader' ), true ) ) {
			return $view;
		}

		if ( 'bookmarks' === $this->get_collection_mode( $user ) ) {
			return 'links';
		}

		return 'reader';
	}

	/**
	 * Get the active frontend view for a collection.
	 *
	 * @param User|\WP_User $user The collection user.
	 * @return string
	 */
	public function get_collection_view( $user ) {
		if ( isset( $_GET['pc-view'] ) ) {
			$view = sanitize_key( wp_unslash( $_GET['pc-view'] ) );
			if ( in_array( $view, array( 'board', 'links', 'reader' ), true ) ) {
				return $view;
			}
		}

		return $this->get_collection_default_view( $user );
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
			wp_die( esc_html( $result->get_error_message() ), '', array( 'response' => $result->get_error_data( 'status' ) ? $result->get_error_data( 'status' ) : 400 ) );
		}

		$redirect = wp_get_referer();
		if ( ! $redirect ) {
			$post = get_post( $post_id );
			$redirect = $post ? $this->get_collection_url( new User( $post->post_author ) ) : $this->get_home_url();
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

		$title       = isset( $data['post_title'] ) ? sanitize_text_field( wp_unslash( $data['post_title'] ) ) : $post->post_title;
		$url         = isset( $data['source_url'] ) ? esc_url_raw( wp_unslash( $data['source_url'] ) ) : $post->guid;
		$description = isset( $data['post_excerpt'] ) ? sanitize_textarea_field( wp_unslash( $data['post_excerpt'] ) ) : $post->post_excerpt;
		$status      = isset( $data['post_status'] ) && 'publish' === wp_unslash( $data['post_status'] ) ? 'publish' : 'private';

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
		$user = new User( $post->post_author );
		$terms = $this->get_post_terms( $post );
		$base_url = $this->get_collection_url( $user );
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

		$source_url = $this->get_source_url( $post );

		return array(
			'id'          => $post->ID,
			'title'       => get_the_title( $post ),
			'source_url'  => $source_url,
			'display_url' => preg_replace( '#^https?://#', '', $source_url ),
			'excerpt'     => $this->get_post_excerpt( $post, 24 ),
			'host'        => $this->get_source_host( $post ),
			'is_private'  => 'private' === $post->post_status,
			'status'      => $post->post_status,
			'terms'       => $term_data,
		);
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
	 * Detect whether a collection should use the bookmark UI.
	 *
	 * @param User|\WP_User $user The collection user.
	 * @return bool
	 */
	public function is_bookmark_collection( $user ) {
		$bookmark_slugs = apply_filters(
			'post_collection_bookmark_collection_slugs',
			array( 'bookmark', 'bookmarks' )
		);
		$collection_slugs = array(
			sanitize_title( $user->user_login ),
			sanitize_title( $user->display_name ),
		);

		foreach ( $collection_slugs as $collection_slug ) {
			foreach ( $bookmark_slugs as $bookmark_slug ) {
				$bookmark_slug = sanitize_title( $bookmark_slug );
				if ( $collection_slug === $bookmark_slug || false !== strpos( $collection_slug, $bookmark_slug ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Query posts for a collection.
	 *
	 * @param User|\WP_User $user The collection user.
	 * @param array         $args Optional query args.
	 * @return \WP_Query
	 */
	public function query_collection_posts( $user, array $args = array() ) {
		$apply_filters = ! isset( $args['post_collection_apply_filters'] ) || $args['post_collection_apply_filters'];
		unset( $args['post_collection_apply_filters'] );

		$defaults = array(
			'post_type'           => Post_Collection::CPT,
			'post_status'         => $this->can_view_private_posts( $user ) ? array( 'publish', 'private' ) : array( 'publish' ),
			'author'              => $user->ID,
			'posts_per_page'      => 24,
			'paged'               => isset( $_GET['pc-page'] ) ? max( 1, absint( wp_unslash( $_GET['pc-page'] ) ) ) : 1,
			'ignore_sticky_posts' => true,
		);

		$query_args = wp_parse_args( $args, $defaults );

		if ( $apply_filters && isset( $_GET['pc-search'] ) && '' !== trim( wp_unslash( $_GET['pc-search'] ) ) ) {
			$query_args['s'] = sanitize_text_field( wp_unslash( $_GET['pc-search'] ) );
		}

		if ( $apply_filters && isset( $_GET['pc-tag'] ) && taxonomy_exists( $this->post_collection->get_tag_taxonomy() ) ) {
			$query_args['tax_query'] = array(
				array(
					'taxonomy' => $this->post_collection->get_tag_taxonomy(),
					'field'    => 'slug',
					'terms'    => sanitize_title( wp_unslash( $_GET['pc-tag'] ) ),
				),
			);
		}

		return new \WP_Query( $query_args );
	}

	/**
	 * Get a visible post from a collection.
	 *
	 * @param int           $post_id The post ID.
	 * @param User|\WP_User $user    The collection user.
	 * @return \WP_Post|null
	 */
	public function get_visible_post( $post_id, $user ) {
		$post = get_post( $post_id );

		if ( ! $post || Post_Collection::CPT !== $post->post_type || intval( $post->post_author ) !== intval( $user->ID ) ) {
			return null;
		}

		if ( 'publish' !== $post->post_status && ! $this->can_view_private_posts( $user ) ) {
			return null;
		}

		return $post;
	}

	/**
	 * Count visible posts in a collection.
	 *
	 * @param User|\WP_User $user The collection user.
	 * @return int
	 */
	public function count_collection_posts( $user ) {
		$query = $this->query_collection_posts(
			$user,
			array(
				'posts_per_page'                 => 1,
				'fields'                         => 'ids',
				'post_collection_apply_filters' => false,
			)
		);

		return intval( $query->found_posts );
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
		$text = $post->post_excerpt ? $post->post_excerpt : $post->post_content;
		$text = wp_strip_all_tags( strip_shortcodes( $text ) );

		return wp_trim_words( $text, $length );
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
	 * @param User|\WP_User $user The collection user.
	 * @return array
	 */
	public function get_collection_terms( $user ) {
		$taxonomy = $this->post_collection->get_tag_taxonomy();
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return array();
		}

		$query = $this->query_collection_posts(
			$user,
			array(
				'posts_per_page'                 => 100,
				'fields'                         => 'ids',
				'post_collection_apply_filters' => false,
			)
		);

		if ( empty( $query->posts ) ) {
			return array();
		}

		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'object_ids' => $query->posts,
				'hide_empty' => true,
				'number'     => 18,
				'orderby'    => 'count',
				'order'      => 'DESC',
			)
		);

		if ( is_wp_error( $terms ) || ! $terms ) {
			return array();
		}

		return $terms;
	}

	/**
	 * Get an external save URL for the collection.
	 *
	 * @param User|\WP_User $user The collection user.
	 * @return string
	 */
	public function get_save_url( $user ) {
		return add_query_arg(
			array(
				'user' => $user->ID,
			),
			home_url( '/' )
		);
	}
}
