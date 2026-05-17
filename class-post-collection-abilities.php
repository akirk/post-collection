<?php
/**
 * WordPress Ability API integration for Post Collection.
 *
 * @package Post_Collection
 */

namespace PostCollection;

defined( 'ABSPATH' ) || exit;

/**
 * Registers app-specific abilities for AI Assistant and other Ability API clients.
 */
class Post_Collection_Abilities {
	const CATEGORY = 'post-collection';

	/**
	 * Main plugin instance.
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

		if ( function_exists( 'did_action' ) && did_action( 'wp_abilities_api_categories_init' ) ) {
			$this->register_ability_category();
		} else {
			add_action( 'wp_abilities_api_categories_init', array( $this, 'register_ability_category' ) );
		}

		if ( function_exists( 'did_action' ) && did_action( 'wp_abilities_api_init' ) ) {
			$this->register_abilities();
		} else {
			add_action( 'wp_abilities_api_init', array( $this, 'register_abilities' ) );
		}

		add_filter( 'ai_assistant_ability_domains', array( $this, 'ai_assistant_ability_domains' ) );
		add_filter( 'ai_assistant_ability_instructions', array( $this, 'ai_assistant_ability_instructions' ), 10, 4 );
	}

	/**
	 * Register domain terms so AI Assistant discovers these abilities for relevant requests.
	 *
	 * @param array $domains Existing ability domains.
	 * @return array Updated ability domains.
	 */
	public function ai_assistant_ability_domains( $domains ) {
		if ( ! is_array( $domains ) ) {
			$domains = array();
		}

		$domains[ self::CATEGORY ] = 'post collection, collected posts, collect posts, save URL, save article, read later, bookmarks, article notes, reading notes, reading status, reviewed articles';

		return $domains;
	}

	/**
	 * Provide result-specific instructions after ability execution.
	 *
	 * @param string $instructions Current instructions.
	 * @param string $ability_id   Ability ID.
	 * @param array  $args         Ability arguments.
	 * @param mixed  $result       Ability result.
	 * @return string Instructions for AI Assistant.
	 */
	public function ai_assistant_ability_instructions( $instructions, $ability_id, $args, $result ) {
		switch ( $ability_id ) {
			case 'post-collection/list-articles':
				return __( 'When presenting collected articles, include the article id, title, collection, reading status, rating, source_url, and view_url. Use post-collection/get-article when the user needs full content.', 'post-collection' );

			case 'post-collection/save-url':
				return __( 'Present the saved article title and include view_url. Mention whether the article was newly created or already existed in the target collection.', 'post-collection' );

			case 'post-collection/update-note':
				return __( 'Confirm the updated reading status, rating, and notes. Include the article title and view_url.', 'post-collection' );
		}

		return $instructions;
	}

	/**
	 * Register the Post Collection ability category.
	 */
	public function register_ability_category() {
		if ( ! function_exists( 'wp_register_ability_category' ) ) {
			return;
		}

		if ( function_exists( 'wp_get_ability_category' ) && wp_get_ability_category( self::CATEGORY ) ) {
			return;
		}

		wp_register_ability_category(
			self::CATEGORY,
			array(
				'label'       => __( 'Post Collection', 'post-collection' ),
				'description' => __( 'Read and manage post collections, collected articles, and article notes.', 'post-collection' ),
			)
		);
	}

	/**
	 * Register Post Collection abilities.
	 */
	public function register_abilities() {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		$this->register_ability(
			'post-collection/list-collections',
			array(
				'label'               => __( 'List Post Collections', 'post-collection' ),
				'description'         => __( 'Lists post collection users with IDs, names, settings, post counts, and URLs.', 'post-collection' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'include_inactive' => array(
							'type'        => 'boolean',
							'description' => __( 'Whether to include collections hidden from the dropdown.', 'post-collection' ),
							'default'     => false,
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'collections' => array(
							'type'  => 'array',
							'items' => self::collection_schema(),
						),
						'total'       => array(
							'type' => 'integer',
						),
					),
				),
				'execute_callback'    => array( $this, 'ability_list_collections' ),
				'permission_callback' => array( $this, 'can_manage_post_collections' ),
				'meta'                => self::ability_meta(
					true,
					false,
					true,
					__( 'Use this first when the user refers to a collection by name or asks what collections exist. Use collection id values with post-collection/save-url, post-collection/list-articles, or post-collection/update-collection.', 'post-collection' )
				),
			)
		);

		$this->register_ability(
			'post-collection/create-collection',
			array(
				'label'               => __( 'Create Post Collection', 'post-collection' ),
				'description'         => __( 'Creates a new post collection user and returns its settings and URLs.', 'post-collection' ),
				'category'            => self::CATEGORY,
				'input_schema'        => self::collection_write_input_schema( true ),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'collection' => self::collection_schema(),
					),
				),
				'execute_callback'    => array( $this, 'ability_create_collection' ),
				'permission_callback' => array( $this, 'can_manage_post_collections' ),
				'meta'                => self::ability_meta(
					false,
					false,
					false,
					__( 'Use this when the user asks to add a new post collection. If user_login is omitted, it will be generated from display_name.', 'post-collection' )
				),
			)
		);

		$this->register_ability(
			'post-collection/update-collection',
			array(
				'label'               => __( 'Update Post Collection', 'post-collection' ),
				'description'         => __( 'Updates a post collection name, description, dropdown mode, or feed publishing setting.', 'post-collection' ),
				'category'            => self::CATEGORY,
				'input_schema'        => self::collection_write_input_schema( false ),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'collection' => self::collection_schema(),
					),
				),
				'execute_callback'    => array( $this, 'ability_update_collection' ),
				'permission_callback' => array( $this, 'can_manage_post_collections' ),
				'meta'                => self::ability_meta(
					false,
					false,
					true,
					__( 'Use post-collection/list-collections first if you need a collection id. dropdown_mode accepts move, copy, or inactive.', 'post-collection' )
				),
			)
		);

		$this->register_ability(
			'post-collection/save-url',
			array(
				'label'               => __( 'Save URL to Post Collection', 'post-collection' ),
				'description'         => __( 'Downloads or extracts a URL and saves it as a collected article in the target post collection. Existing articles with the same URL and collection are reused.', 'post-collection' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'                 => 'object',
					'required'             => array( 'collection_id', 'url' ),
					'properties'           => array(
						'collection_id' => array(
							'type'        => 'integer',
							'description' => __( 'Collection user ID from post-collection/list-collections.', 'post-collection' ),
						),
						'url'           => array(
							'type'        => 'string',
							'description' => __( 'The article URL to collect.', 'post-collection' ),
						),
						'title'         => array(
							'type'        => 'string',
							'description' => __( 'Optional title override for the collected article.', 'post-collection' ),
						),
						'html'          => array(
							'type'        => 'string',
							'description' => __( 'Optional raw page HTML. When omitted, Post Collection downloads the URL.', 'post-collection' ),
						),
						'tags'          => array(
							'type'        => 'array',
							'description' => __( 'Optional tag names to assign to the collected article.', 'post-collection' ),
							'items'       => array(
								'type' => 'string',
							),
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'article' => self::article_schema(),
						'created' => array(
							'type'        => 'boolean',
							'description' => __( 'Whether a new collected article was created.', 'post-collection' ),
						),
					),
				),
				'execute_callback'    => array( $this, 'ability_save_url' ),
				'permission_callback' => array( $this, 'can_manage_post_collections' ),
				'meta'                => self::ability_meta(
					false,
					false,
					true,
					__( 'Use post-collection/list-collections first when the target collection is ambiguous. This ability is idempotent by URL and collection.', 'post-collection' )
				),
			)
		);

		$this->register_ability(
			'post-collection/list-articles',
			array(
				'label'               => __( 'List Collected Articles', 'post-collection' ),
				'description'         => __( 'Lists collected articles with IDs, titles, source URLs, collection details, local URLs, and note summaries.', 'post-collection' ),
				'category'            => self::CATEGORY,
				'input_schema'        => self::article_list_input_schema(),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'articles' => array(
							'type'  => 'array',
							'items' => self::article_schema(),
						),
						'total'    => array(
							'type' => 'integer',
						),
						'limit'    => array(
							'type' => 'integer',
						),
						'offset'   => array(
							'type' => 'integer',
						),
					),
				),
				'execute_callback'    => array( $this, 'ability_list_articles' ),
				'permission_callback' => array( $this, 'can_manage_post_collections' ),
				'meta'                => self::ability_meta(
					true,
					false,
					true,
					__( 'Use this for article lookup and filtering. Use post-collection/get-article for full article content.', 'post-collection' )
				),
			)
		);

		$this->register_ability(
			'post-collection/get-article',
			array(
				'label'               => __( 'Get Collected Article', 'post-collection' ),
				'description'         => __( 'Returns one collected article by ID, including source URL, local URL, full content, and note data.', 'post-collection' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'                 => 'object',
					'required'             => array( 'article_id' ),
					'properties'           => array(
						'article_id' => array(
							'type'        => 'integer',
							'description' => __( 'Collected article post ID from post-collection/list-articles or post-collection/save-url.', 'post-collection' ),
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'article' => self::article_schema( true ),
					),
				),
				'execute_callback'    => array( $this, 'ability_get_article' ),
				'permission_callback' => array( $this, 'can_manage_post_collections' ),
				'meta'                => self::ability_meta(
					true,
					false,
					true,
					__( 'Use this when the user asks for full article content or complete note details. Prefer source_url when citing the original source.', 'post-collection' )
				),
			)
		);

		$this->register_ability(
			'post-collection/update-article-visibility',
			array(
				'label'               => __( 'Update Collected Article Visibility', 'post-collection' ),
				'description'         => __( 'Sets a collected article to private or publish, controlling whether it appears in the public feed.', 'post-collection' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'                 => 'object',
					'required'             => array( 'article_id', 'post_status' ),
					'properties'           => array(
						'article_id'  => array(
							'type'        => 'integer',
							'description' => __( 'Collected article post ID.', 'post-collection' ),
						),
						'post_status' => array(
							'type'        => 'string',
							'enum'        => array( 'private', 'publish' ),
							'description' => __( 'private hides the article from the feed; publish shows it in the feed.', 'post-collection' ),
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'article' => self::article_schema(),
					),
				),
				'execute_callback'    => array( $this, 'ability_update_article_visibility' ),
				'permission_callback' => array( $this, 'can_manage_post_collections' ),
				'meta'                => self::ability_meta(
					false,
					false,
					true,
					__( 'Use publish to show a collected article in the feed and private to hide it.', 'post-collection' )
				),
			)
		);

		$this->register_ability(
			'post-collection/update-note',
			array(
				'label'               => __( 'Update Article Note', 'post-collection' ),
				'description'         => __( 'Creates or updates the reading status, rating, and notes for a collected article.', 'post-collection' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'                 => 'object',
					'required'             => array( 'article_id' ),
					'properties'           => array(
						'article_id' => array(
							'type'        => 'integer',
							'description' => __( 'Collected article post ID.', 'post-collection' ),
						),
						'status'     => array(
							'type'        => 'string',
							'enum'        => Article_Notes::get_all_status_values(),
							'description' => __( 'Reading status: unread, read, skipped, or archived.', 'post-collection' ),
						),
						'rating'     => array(
							'type'        => 'integer',
							'minimum'     => 0,
							'maximum'     => 5,
							'description' => __( 'Star rating from 0 to 5.', 'post-collection' ),
						),
						'notes'      => array(
							'type'        => 'string',
							'description' => __( 'Freeform reading notes. HTML allowed by wp_kses_post is preserved.', 'post-collection' ),
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'article' => self::article_schema(),
						'note'    => self::note_schema(),
					),
				),
				'execute_callback'    => array( $this, 'ability_update_note' ),
				'permission_callback' => array( $this, 'can_manage_post_collections' ),
				'meta'                => self::ability_meta(
					false,
					false,
					true,
					__( 'Use this when the user asks to mark an article read, unread, skipped, archived, rate it, or add/update notes.', 'post-collection' )
				),
			)
		);
	}

	/**
	 * Check whether the current user can manage post collections.
	 *
	 * @param mixed $input Ability input.
	 * @return bool Whether the user can manage post collections.
	 */
	public function can_manage_post_collections( $input = null ) {
		return current_user_can( $this->plugin->get_required_role() );
	}

	/**
	 * List post collections.
	 *
	 * @param array $input Ability input.
	 * @return array Ability result.
	 */
	public function ability_list_collections( $input ) {
		$input = is_array( $input ) ? $input : array();
		$include_inactive = ! empty( $input['include_inactive'] );
		$collections = array();

		foreach ( $this->plugin->get_post_collection_users()->get_results() as $user ) {
			if ( ! $include_inactive && get_user_option( 'friends_post_collection_inactive', $user->ID ) ) {
				continue;
			}

			$collections[] = $this->prepare_collection_data( $user );
		}

		return array(
			'collections' => $collections,
			'total'       => count( $collections ),
		);
	}

	/**
	 * Create a post collection.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error Ability result or error.
	 */
	public function ability_create_collection( $input ) {
		$input = is_array( $input ) ? $input : array();

		$display_name = $this->sanitize_label( $input['display_name'] ?? '' );
		if ( '' === $display_name ) {
			return new \WP_Error( 'missing-display-name', __( 'A display name is required.', 'post-collection' ) );
		}

		$user_login = '';
		if ( isset( $input['user_login'] ) ) {
			$user_login = sanitize_user( $input['user_login'] );
		}
		if ( '' === $user_login ) {
			$user_login = User::sanitize_username( $display_name );
		}

		if ( '' === $user_login ) {
			return new \WP_Error( 'invalid-user-login', __( 'A valid username is required.', 'post-collection' ) );
		}

		if ( username_exists( $user_login ) ) {
			return new \WP_Error( 'user-login-exists', __( 'That username is already registered.', 'post-collection' ) );
		}

		$userdata = array(
			'user_login'   => $user_login,
			'display_name' => $display_name,
			'user_pass'    => wp_generate_password( 256 ),
			'role'         => 'post_collection',
		);

		if ( isset( $input['description'] ) ) {
			$userdata['description'] = sanitize_textarea_field( $input['description'] );
		}

		$user_id = wp_insert_user( $userdata );
		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}

		$user = User::get_user_by_id( $user_id );
		if ( ! $user ) {
			return new \WP_Error( 'collection-not-found', __( 'The collection was created but could not be loaded.', 'post-collection' ) );
		}

		$this->apply_collection_settings( $user, $input );

		return array(
			'collection' => $this->prepare_collection_data( $user ),
		);
	}

	/**
	 * Update a post collection.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error Ability result or error.
	 */
	public function ability_update_collection( $input ) {
		$input = is_array( $input ) ? $input : array();
		$user = $this->get_collection_user( $input['collection_id'] ?? 0 );
		if ( is_wp_error( $user ) ) {
			return $user;
		}

		$userdata = array(
			'ID' => $user->ID,
		);

		if ( array_key_exists( 'display_name', $input ) ) {
			$display_name = $this->sanitize_label( $input['display_name'] );
			if ( '' === $display_name ) {
				return new \WP_Error( 'invalid-display-name', __( 'A valid display name is required.', 'post-collection' ) );
			}
			$userdata['display_name'] = $display_name;
		}

		if ( array_key_exists( 'description', $input ) ) {
			$userdata['description'] = sanitize_textarea_field( $input['description'] );
		}

		if ( count( $userdata ) > 1 ) {
			$updated = wp_update_user( $userdata );
			if ( is_wp_error( $updated ) ) {
				return $updated;
			}
		}

		$this->apply_collection_settings( $user, $input );
		$user = User::get_user_by_id( $user->ID );

		return array(
			'collection' => $this->prepare_collection_data( $user ),
		);
	}

	/**
	 * Save a URL to a collection.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error Ability result or error.
	 */
	public function ability_save_url( $input ) {
		$input = is_array( $input ) ? $input : array();
		$user = $this->get_collection_user( $input['collection_id'] ?? 0 );
		if ( is_wp_error( $user ) ) {
			return $user;
		}

		if ( get_user_option( 'friends_post_collection_inactive', $user->ID ) ) {
			return new \WP_Error( 'inactive-collection', __( 'This post collection is inactive.', 'post-collection' ) );
		}

		$url = isset( $input['url'] ) ? esc_url_raw( trim( (string) $input['url'] ) ) : '';
		if ( ! $this->plugin->check_url( $url ) ) {
			return new \WP_Error( 'invalid-url', __( 'You entered an invalid URL.', 'post-collection' ) );
		}

		$args = array();
		if ( isset( $input['title'] ) ) {
			$args['title'] = $this->sanitize_label( $input['title'] );
		}
		if ( isset( $input['tags'] ) && is_array( $input['tags'] ) ) {
			$args['tags'] = $this->sanitize_tags( $input['tags'] );
		}

		$html = null;
		if ( isset( $input['html'] ) && is_string( $input['html'] ) ) {
			$html = (string) $input['html'];
		}

		$existing_post_id = $this->plugin->url_to_postid( $url, $user->ID );
		$post_id = $this->plugin->save_url_for_collection( $url, $user, $html, $args );
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return new \WP_Error( 'article-not-found', __( 'The article was saved but could not be loaded.', 'post-collection' ) );
		}

		return array(
			'article' => $this->prepare_article_data( $post ),
			'created' => ! $existing_post_id,
		);
	}

	/**
	 * List collected articles.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error Ability result or error.
	 */
	public function ability_list_articles( $input ) {
		$input = is_array( $input ) ? $input : array();
		$limit = $this->clamp_int( $input['limit'] ?? 20, 1, 50 );
		$offset = max( 0, (int) ( $input['offset'] ?? 0 ) );
		$post_status = $input['post_status'] ?? 'any';
		$note_status = $input['note_status'] ?? 'all';

		$args = array(
			'post_type'      => Post_Collection::CPT,
			'post_status'    => 'any' === $post_status ? array( 'private', 'publish' ) : $post_status,
			'posts_per_page' => $limit,
			'offset'         => $offset,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		if ( ! empty( $input['collection_id'] ) ) {
			$user = $this->get_collection_user( $input['collection_id'] );
			if ( is_wp_error( $user ) ) {
				return $user;
			}
			$args['author'] = $user->ID;
		}

		if ( ! empty( $input['search'] ) ) {
			$args['s'] = sanitize_text_field( $input['search'] );
		}

		if ( 'none' === $note_status ) {
			$args['meta_query'] = array(
				array(
					'key'     => Article_Notes::NOTE_ID_META,
					'compare' => 'NOT EXISTS',
				),
			);
		} elseif ( 'all' !== $note_status ) {
			$article_ids = $this->get_article_ids_by_note_status( $note_status );
			if ( empty( $article_ids ) ) {
				return array(
					'articles' => array(),
					'total'    => 0,
					'limit'    => $limit,
					'offset'   => $offset,
				);
			}
			$args['post__in'] = $article_ids;
		}

		$query = new \WP_Query( $args );
		$articles = array();
		foreach ( $query->posts as $post ) {
			$articles[] = $this->prepare_article_data( $post );
		}

		return array(
			'articles' => $articles,
			'total'    => (int) $query->found_posts,
			'limit'    => $limit,
			'offset'   => $offset,
		);
	}

	/**
	 * Get a collected article.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error Ability result or error.
	 */
	public function ability_get_article( $input ) {
		$input = is_array( $input ) ? $input : array();
		$post = $this->get_collected_article( $input['article_id'] ?? 0 );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		return array(
			'article' => $this->prepare_article_data( $post, true ),
		);
	}

	/**
	 * Update collected article visibility.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error Ability result or error.
	 */
	public function ability_update_article_visibility( $input ) {
		$input = is_array( $input ) ? $input : array();
		$post = $this->get_collected_article( $input['article_id'] ?? 0 );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$post_status = $input['post_status'] ?? '';
		if ( ! in_array( $post_status, array( 'private', 'publish' ), true ) ) {
			return new \WP_Error( 'invalid-post-status', __( 'A valid post status is required.', 'post-collection' ) );
		}

		$updated = wp_update_post(
			array(
				'ID'          => $post->ID,
				'post_status' => $post_status,
			),
			true
		);
		if ( is_wp_error( $updated ) ) {
			return $updated;
		}

		$post = get_post( $post->ID );
		if ( ! $post ) {
			return new \WP_Error( 'article-not-found', __( 'The article was updated but could not be loaded.', 'post-collection' ) );
		}

		return array(
			'article' => $this->prepare_article_data( $post ),
		);
	}

	/**
	 * Update an article note.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error Ability result or error.
	 */
	public function ability_update_note( $input ) {
		$input = is_array( $input ) ? $input : array();
		$post = $this->get_collected_article( $input['article_id'] ?? 0 );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		if ( ! array_key_exists( 'status', $input ) && ! array_key_exists( 'rating', $input ) && ! array_key_exists( 'notes', $input ) ) {
			return new \WP_Error( 'missing-note-update', __( 'Provide status, rating, or notes to update.', 'post-collection' ) );
		}

		$status = null;
		if ( array_key_exists( 'status', $input ) ) {
			$status = sanitize_text_field( $input['status'] );
			if ( ! in_array( $status, Article_Notes::get_all_status_values(), true ) ) {
				return new \WP_Error( 'invalid-note-status', __( 'A valid note status is required.', 'post-collection' ) );
			}
		}

		$rating = null;
		if ( array_key_exists( 'rating', $input ) ) {
			$rating = $this->clamp_int( $input['rating'], 0, 5 );
		}

		$notes = null;
		if ( array_key_exists( 'notes', $input ) ) {
			$notes = wp_kses_post( $input['notes'] );
		}

		$note_id = $this->plugin->get_article_notes()->save_note( $post->ID, $status, $rating, $notes );
		if ( ! $note_id ) {
			return new \WP_Error( 'note-not-saved', __( 'Failed to save note.', 'post-collection' ) );
		}

		$post = get_post( $post->ID );
		if ( ! $post ) {
			return new \WP_Error( 'article-not-found', __( 'The note was updated but the article could not be loaded.', 'post-collection' ) );
		}

		$note = $this->prepare_note_data( $post->ID );

		return array(
			'article' => $this->prepare_article_data( $post ),
			'note'    => $note,
		);
	}

	/**
	 * Register a single ability if it is not already registered.
	 *
	 * @param string $ability_id Ability ID.
	 * @param array  $args       Ability arguments.
	 */
	private function register_ability( $ability_id, $args ) {
		if ( function_exists( 'wp_get_ability' ) && wp_get_ability( $ability_id ) ) {
			return;
		}

		wp_register_ability( $ability_id, $args );
	}

	/**
	 * Get a post collection user.
	 *
	 * @param int $collection_id Collection user ID.
	 * @return User|\WP_Error Collection user or error.
	 */
	private function get_collection_user( $collection_id ) {
		$collection_id = absint( $collection_id );
		if ( ! $collection_id ) {
			return new \WP_Error( 'missing-collection-id', __( 'A collection ID is required.', 'post-collection' ) );
		}

		$user = User::get_user_by_id( $collection_id );
		if ( ! $user || ! $user->has_cap( 'post_collection' ) ) {
			return new \WP_Error( 'invalid-collection', __( 'Invalid post collection.', 'post-collection' ) );
		}

		return $user;
	}

	/**
	 * Get a collected article post.
	 *
	 * @param int $article_id Article post ID.
	 * @return \WP_Post|\WP_Error Article post or error.
	 */
	private function get_collected_article( $article_id ) {
		$article_id = absint( $article_id );
		if ( ! $article_id ) {
			return new \WP_Error( 'missing-article-id', __( 'An article ID is required.', 'post-collection' ) );
		}

		$post = get_post( $article_id );
		if ( ! $post || Post_Collection::CPT !== $post->post_type ) {
			return new \WP_Error( 'invalid-article', __( 'Invalid collected article.', 'post-collection' ) );
		}

		return $post;
	}

	/**
	 * Prepare collection data for ability output.
	 *
	 * @param User $user Collection user.
	 * @return array Collection data.
	 */
	private function prepare_collection_data( User $user ) {
		$inactive = (bool) get_user_option( 'friends_post_collection_inactive', $user->ID );
		$copy_mode = (bool) get_user_option( 'friends_post_collection_copy_mode', $user->ID );

		if ( $inactive ) {
			$dropdown_mode = 'inactive';
		} elseif ( $copy_mode ) {
			$dropdown_mode = 'copy';
		} else {
			$dropdown_mode = 'move';
		}

		return array(
			'id'            => (int) $user->ID,
			'user_login'    => (string) $user->user_login,
			'display_name'  => (string) $user->display_name,
			'description'   => (string) $user->description,
			'active'        => ! $inactive,
			'dropdown_mode' => $dropdown_mode,
			'copy_mode'     => $copy_mode,
			'publish_feed'  => (bool) get_user_option( 'friends_publish_post_collection', $user->ID ),
			'posts_count'   => (int) count_user_posts( $user->ID, Post_Collection::CPT ),
			'url'           => (string) $user->get_local_friends_page_url(),
			'edit_url'      => (string) get_edit_user_link( $user->ID ),
		);
	}

	/**
	 * Prepare collected article data for ability output.
	 *
	 * @param \WP_Post $post            Article post.
	 * @param bool     $include_content Whether to include full content.
	 * @return array Article data.
	 */
	private function prepare_article_data( \WP_Post $post, $include_content = false ) {
		$user = User::get_post_author( $post );
		$source_url = get_post_meta( $post->ID, 'url', true );
		if ( ! $source_url ) {
			$source_url = $post->guid;
		}

		$author = get_post_meta( $post->ID, 'author', true );
		if ( ! $author ) {
			$author = $this->plugin->get_post_author_name( $post );
		}

		$data = array(
			'id'            => (int) $post->ID,
			'title'         => html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' ),
			'collection_id' => (int) $post->post_author,
			'collection'    => (string) $user->display_name,
			'post_status'   => (string) $post->post_status,
			'source_url'    => (string) $source_url,
			'view_url'      => (string) $user->get_local_friends_page_url( $post->ID ),
			'edit_url'      => (string) get_edit_post_link( $post->ID, 'raw' ),
			'author'        => (string) $author,
			'excerpt'       => (string) get_the_excerpt( $post ),
			'date'          => (string) $post->post_date,
			'modified'      => (string) $post->post_modified,
			'note'          => $this->prepare_note_data( $post->ID ),
		);

		if ( $include_content ) {
			$data['content'] = wp_kses_post( $post->post_content );
		}

		return $data;
	}

	/**
	 * Prepare article note data for ability output.
	 *
	 * @param int $article_id Article post ID.
	 * @return array Note data.
	 */
	private function prepare_note_data( $article_id ) {
		$note = $this->plugin->get_article_notes()->get_note( $article_id );
		if ( ! $note ) {
			return array(
				'note_id' => 0,
				'status'  => Article_Notes::STATUS_UNREAD,
				'rating'  => 0,
				'notes'   => '',
				'updated' => '',
			);
		}

		return array(
			'note_id' => (int) $note['id'],
			'status'  => (string) $note['status'],
			'rating'  => (int) $note['rating'],
			'notes'   => (string) $note['notes'],
			'updated' => (string) $note['updated'],
		);
	}

	/**
	 * Apply collection options from ability input.
	 *
	 * @param User  $user  Collection user.
	 * @param array $input Ability input.
	 */
	private function apply_collection_settings( User $user, $input ) {
		if ( array_key_exists( 'publish_feed', $input ) ) {
			if ( $input['publish_feed'] ) {
				update_user_option( $user->ID, 'friends_publish_post_collection', true );
			} else {
				delete_user_option( $user->ID, 'friends_publish_post_collection' );
			}
		}

		if ( ! array_key_exists( 'dropdown_mode', $input ) ) {
			return;
		}

		switch ( $input['dropdown_mode'] ) {
			case 'inactive':
				update_user_option( $user->ID, 'friends_post_collection_inactive', true );
				break;

			case 'copy':
				delete_user_option( $user->ID, 'friends_post_collection_inactive' );
				update_user_option( $user->ID, 'friends_post_collection_copy_mode', true );
				break;

			case 'move':
				delete_user_option( $user->ID, 'friends_post_collection_inactive' );
				delete_user_option( $user->ID, 'friends_post_collection_copy_mode' );
				break;
		}
	}

	/**
	 * Get article IDs by note status.
	 *
	 * @param string $status Note status.
	 * @return array Article IDs.
	 */
	private function get_article_ids_by_note_status( $status ) {
		if ( ! in_array( $status, Article_Notes::get_all_status_values(), true ) ) {
			return array();
		}

		$note_ids = get_posts(
			array(
				'post_type'      => Article_Notes::POST_TYPE,
				'posts_per_page' => -1,
				'post_status'    => 'publish',
				'fields'         => 'ids',
				'meta_query'     => array(
					array(
						'key'   => Article_Notes::STATUS_META,
						'value' => $status,
					),
				),
			)
		);

		$article_ids = array();
		foreach ( $note_ids as $note_id ) {
			$parent_id = wp_get_post_parent_id( $note_id );
			if ( $parent_id ) {
				$article_ids[] = (int) $parent_id;
			}
		}

		return array_values( array_unique( $article_ids ) );
	}

	/**
	 * Sanitize a human label.
	 *
	 * @param string $value Input value.
	 * @return string Sanitized label.
	 */
	private function sanitize_label( $value ) {
		return wp_strip_all_tags( trim( sanitize_text_field( (string) $value ) ) );
	}

	/**
	 * Sanitize tag names.
	 *
	 * @param array $tags Raw tags.
	 * @return array Sanitized tags.
	 */
	private function sanitize_tags( $tags ) {
		$sanitized = array();
		foreach ( $tags as $tag ) {
			$tag = $this->sanitize_label( $tag );
			if ( '' !== $tag ) {
				$sanitized[] = $tag;
			}
		}

		return array_values( array_unique( $sanitized ) );
	}

	/**
	 * Clamp an integer to a range.
	 *
	 * @param mixed $value Input value.
	 * @param int   $min   Minimum value.
	 * @param int   $max   Maximum value.
	 * @return int Clamped value.
	 */
	private function clamp_int( $value, $min, $max ) {
		return max( $min, min( $max, (int) $value ) );
	}

	/**
	 * Ability metadata helper.
	 *
	 * @param bool   $readonly     Whether the ability is readonly.
	 * @param bool   $destructive  Whether the ability is destructive.
	 * @param bool   $idempotent   Whether the ability is idempotent.
	 * @param string $instructions Instructions for AI tools.
	 * @return array Ability meta.
	 */
	private static function ability_meta( $readonly, $destructive, $idempotent, $instructions ) {
		return array(
			'annotations'  => array(
				'instructions' => $instructions,
				'readonly'     => $readonly,
				'destructive'  => $destructive,
				'idempotent'   => $idempotent,
			),
			'show_in_rest' => true,
		);
	}

	/**
	 * Schema for writing collection settings.
	 *
	 * @param bool $creating Whether this schema is for creation.
	 * @return array Input schema.
	 */
	private static function collection_write_input_schema( $creating ) {
		$properties = array(
			'display_name'  => array(
				'type'        => 'string',
				'description' => __( 'Human-readable collection name.', 'post-collection' ),
			),
			'user_login'    => array(
				'type'        => 'string',
				'description' => __( 'Optional username for a new collection. Used only when creating.', 'post-collection' ),
			),
			'description'   => array(
				'type'        => 'string',
				'description' => __( 'Collection description.', 'post-collection' ),
			),
			'dropdown_mode' => array(
				'type'        => 'string',
				'enum'        => array( 'move', 'copy', 'inactive' ),
				'description' => __( 'move shows Move to, copy shows Copy to, inactive hides the collection from save menus.', 'post-collection' ),
			),
			'publish_feed'  => array(
				'type'        => 'boolean',
				'description' => __( 'Whether to publish this collection as a feed.', 'post-collection' ),
			),
		);

		if ( ! $creating ) {
			$properties = array_merge(
				array(
					'collection_id' => array(
						'type'        => 'integer',
						'description' => __( 'Collection user ID from post-collection/list-collections.', 'post-collection' ),
					),
				),
				$properties
			);
		}

		return array(
			'type'                 => 'object',
			'required'             => $creating ? array( 'display_name' ) : array( 'collection_id' ),
			'properties'           => $properties,
			'additionalProperties' => false,
		);
	}

	/**
	 * Input schema for listing articles.
	 *
	 * @return array Input schema.
	 */
	private static function article_list_input_schema() {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'collection_id' => array(
					'type'        => 'integer',
					'description' => __( 'Optional collection user ID to filter by.', 'post-collection' ),
				),
				'search'        => array(
					'type'        => 'string',
					'description' => __( 'Optional search term for article title or content.', 'post-collection' ),
				),
				'post_status'   => array(
					'type'        => 'string',
					'enum'        => array( 'any', 'private', 'publish' ),
					'description' => __( 'Article visibility filter. Defaults to any.', 'post-collection' ),
					'default'     => 'any',
				),
				'note_status'   => array(
					'type'        => 'string',
					'enum'        => array( 'all', 'none', 'unread', 'read', 'skipped', 'archived' ),
					'description' => __( 'Article note status filter. none means articles without a note.', 'post-collection' ),
					'default'     => 'all',
				),
				'limit'         => array(
					'type'        => 'integer',
					'minimum'     => 1,
					'maximum'     => 50,
					'description' => __( 'Maximum number of articles to return. Defaults to 20.', 'post-collection' ),
					'default'     => 20,
				),
				'offset'        => array(
					'type'        => 'integer',
					'minimum'     => 0,
					'description' => __( 'Number of matching articles to skip.', 'post-collection' ),
					'default'     => 0,
				),
			),
			'additionalProperties' => false,
		);
	}

	/**
	 * Collection output schema.
	 *
	 * @return array Output schema.
	 */
	private static function collection_schema() {
		return array(
			'type'       => 'object',
			'properties' => array(
				'id'            => array( 'type' => 'integer' ),
				'user_login'    => array( 'type' => 'string' ),
				'display_name'  => array( 'type' => 'string' ),
				'description'   => array( 'type' => 'string' ),
				'active'        => array( 'type' => 'boolean' ),
				'dropdown_mode' => array( 'type' => 'string' ),
				'copy_mode'     => array( 'type' => 'boolean' ),
				'publish_feed'  => array( 'type' => 'boolean' ),
				'posts_count'   => array( 'type' => 'integer' ),
				'url'           => array( 'type' => 'string' ),
				'edit_url'      => array( 'type' => 'string' ),
			),
		);
	}

	/**
	 * Article output schema.
	 *
	 * @param bool $include_content Whether full content may be present.
	 * @return array Output schema.
	 */
	private static function article_schema( $include_content = false ) {
		$properties = array(
			'id'            => array( 'type' => 'integer' ),
			'title'         => array( 'type' => 'string' ),
			'collection_id' => array( 'type' => 'integer' ),
			'collection'    => array( 'type' => 'string' ),
			'post_status'   => array( 'type' => 'string' ),
			'source_url'    => array( 'type' => 'string' ),
			'view_url'      => array( 'type' => 'string' ),
			'edit_url'      => array( 'type' => 'string' ),
			'author'        => array( 'type' => 'string' ),
			'excerpt'       => array( 'type' => 'string' ),
			'date'          => array( 'type' => 'string' ),
			'modified'      => array( 'type' => 'string' ),
			'note'          => self::note_schema(),
		);

		if ( $include_content ) {
			$properties['content'] = array( 'type' => 'string' );
		}

		return array(
			'type'       => 'object',
			'properties' => $properties,
		);
	}

	/**
	 * Note output schema.
	 *
	 * @return array Output schema.
	 */
	private static function note_schema() {
		return array(
			'type'       => 'object',
			'properties' => array(
				'note_id' => array( 'type' => 'integer' ),
				'status'  => array( 'type' => 'string' ),
				'rating'  => array( 'type' => 'integer' ),
				'notes'   => array( 'type' => 'string' ),
				'updated' => array( 'type' => 'string' ),
			),
		);
	}
}
