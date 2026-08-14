<?php

namespace {
	if ( ! defined( 'ABSPATH' ) ) {
		define( 'ABSPATH', '/tmp/' );
	}

	if ( ! defined( 'OBJECT' ) ) {
		define( 'OBJECT', 'OBJECT' );
	}

	require_once __DIR__ . '/../vendor/autoload.php';

	if ( ! class_exists( 'WP_HTML_Tag_Processor' ) ) {
		require_once __DIR__ . '/stubs/class-wp-html-tag-processor.php';
	}

	$GLOBALS['wp_test_actions']            = array();
	$GLOBALS['wp_test_filters']            = array();
	$GLOBALS['wp_test_did_actions']        = array();
	$GLOBALS['wp_test_doing_actions']      = array();
	$GLOBALS['wp_test_ability_categories'] = array();
	$GLOBALS['wp_test_abilities']          = array();
	$GLOBALS['wp_test_current_user_caps']  = array();
	$GLOBALS['wp_test_current_user_id']    = 0;
	$GLOBALS['wp_test_posts']              = array();
	$GLOBALS['wp_test_post_meta']          = array();
	$GLOBALS['wp_test_terms']              = array();
	$GLOBALS['wp_test_term_meta']          = array();
	$GLOBALS['wp_test_registered_terms']   = array();
	$GLOBALS['wp_test_users']              = array();
	$GLOBALS['wp_test_user_options']       = array();

	function __( $text, $domain = 'default' ) {
		return $text;
	}

	function _x( $text, $context, $domain = 'default' ) {
		return $text;
	}

	function _e( $text, $domain = 'default' ) {
		echo $text;
	}

	function esc_html__( $text, $domain = 'default' ) {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}

	function esc_html_e( $text, $domain = 'default' ) {
		echo esc_html__( $text, $domain );
	}

	function esc_attr__( $text, $domain = 'default' ) {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}

	function esc_html( $text ) {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}

	function esc_attr( $text ) {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}

	function esc_url( $url ) {
		return htmlspecialchars( $url, ENT_QUOTES, 'UTF-8' );
	}

	function esc_url_raw( $url, $protocols = null ) {
		return (string) $url;
	}

	function wp_kses_post( $data ) {
		return $data;
	}

	function wp_kses( $data, $allowed_html, $allowed_protocols = array() ) {
		return $data;
	}

	function wp_unslash( $value ) {
		return is_array( $value ) ? array_map( 'wp_unslash', $value ) : stripslashes( (string) $value );
	}

	function force_balance_tags( $text ) {
		return $text;
	}

	function wp_parse_url( $url, $component = -1 ) {
		return parse_url( $url, $component );
	}

	function add_action( $tag, $function_to_add, $priority = 10, $accepted_args = 1 ) {
		$GLOBALS['wp_test_actions'][ $tag ][] = compact( 'function_to_add', 'priority', 'accepted_args' );
		return true;
	}

	function add_filter( $tag, $function_to_add, $priority = 10, $accepted_args = 1 ) {
		$GLOBALS['wp_test_filters'][ $tag ][] = compact( 'function_to_add', 'priority', 'accepted_args' );
		return true;
	}

	function apply_filters( $tag, $value, ...$args ) {
		if ( empty( $GLOBALS['wp_test_filters'][ $tag ] ) ) {
			return $value;
		}

		foreach ( $GLOBALS['wp_test_filters'][ $tag ] as $filter ) {
			$value = call_user_func_array(
				$filter['function_to_add'],
				array_slice( array_merge( array( $value ), $args ), 0, $filter['accepted_args'] )
			);
		}

		return $value;
	}

	function did_action( $hook_name ) {
		return (int) ( $GLOBALS['wp_test_did_actions'][ $hook_name ] ?? 0 );
	}

	function doing_action( $hook_name = null ) {
		if ( null === $hook_name ) {
			return ! empty( $GLOBALS['wp_test_doing_actions'] );
		}
		return ! empty( $GLOBALS['wp_test_doing_actions'][ $hook_name ] );
	}

	function current_user_can( $capability, ...$args ) {
		return ! empty( $GLOBALS['wp_test_current_user_caps'][ $capability ] );
	}

	function get_current_user_id() {
		return (int) $GLOBALS['wp_test_current_user_id'];
	}

	function wp_get_current_user() {
		return new WP_User( get_current_user_id() );
	}

	function wp_set_current_user( $id ) {
		$GLOBALS['wp_test_current_user_id'] = (int) $id;
		return wp_get_current_user();
	}

	function is_user_logged_in() {
		return get_current_user_id() > 0;
	}

	function rest_sanitize_boolean( $value ) {
		if ( is_string( $value ) ) {
			$value = strtolower( $value );
			if ( in_array( $value, array( 'false', '0' ), true ) ) {
				return false;
			}
		}
		return (bool) $value;
	}

	function wp_register_ability_category( $slug, $args = array() ) {
		$GLOBALS['wp_test_ability_categories'][ $slug ] = $args;
		return true;
	}

	function wp_has_ability_category( $slug ) {
		return array_key_exists( $slug, $GLOBALS['wp_test_ability_categories'] );
	}

	function wp_register_ability( $name, $args = array() ) {
		$GLOBALS['wp_test_abilities'][ $name ] = $args;
		return true;
	}

	function wp_has_ability( $name ) {
		return array_key_exists( $name, $GLOBALS['wp_test_abilities'] );
	}

	function wp_get_ability( $name ) {
		return $GLOBALS['wp_test_abilities'][ $name ] ?? null;
	}

	function wp_remote_get( $url, $args = array() ) {
		if ( isset( $GLOBALS['wp_test_http_responses'][ $url ] ) ) {
			return $GLOBALS['wp_test_http_responses'][ $url ];
		}

		return new WP_Error( 'http_request_failed', 'No test response registered.' );
	}

	function wp_safe_remote_get( $url, $args = array() ) {
		return wp_remote_get( $url, $args );
	}

	function wp_remote_retrieve_response_code( $response ) {
		return $response['response']['code'] ?? 0;
	}

	function wp_remote_retrieve_body( $response ) {
		return $response['body'] ?? '';
	}

	function wp_remote_retrieve_header( $response, $header ) {
		$headers = $response['headers'] ?? array();
		foreach ( $headers as $key => $value ) {
			if ( strtolower( (string) $key ) === strtolower( (string) $header ) ) {
				return $value;
			}
		}

		return '';
	}

	function is_wp_error( $thing ) {
		return $thing instanceof WP_Error;
	}

	class WP_Error {
		public $errors = array();
		public $error_data = array();

		public function __construct( $code = '', $message = '', $data = '' ) {
			if ( $code ) {
				$this->errors[ $code ][] = $message;
				if ( '' !== $data ) {
					$this->error_data[ $code ] = $data;
				}
			}
		}

		public function get_error_code() {
			return array_key_first( $this->errors );
		}

		public function get_error_message( $code = '' ) {
			if ( '' === $code ) {
				$code = $this->get_error_code();
			}
			return $this->errors[ $code ][0] ?? '';
		}

		public function get_error_data( $code = '' ) {
			if ( '' === $code ) {
				$code = $this->get_error_code();
			}
			return $this->error_data[ $code ] ?? null;
		}
	}

	class WP_User {
		public $ID = 0;
		public $user_login = '';
		public $display_name = '';
		public $description = '';
		public $caps = array();
		public $roles = array();

		public function __construct( $id = 0 ) {
			if ( $id instanceof self ) {
				foreach ( get_object_vars( $id ) as $key => $value ) {
					$this->$key = $value;
				}
				return;
			}

			if ( is_object( $id ) ) {
				foreach ( get_object_vars( $id ) as $key => $value ) {
					$this->$key = $value;
				}
				return;
			}

			$id = (int) $id;
			if ( $id && isset( $GLOBALS['wp_test_users'][ $id ] ) ) {
				foreach ( get_object_vars( $GLOBALS['wp_test_users'][ $id ] ) as $key => $value ) {
					$this->$key = $value;
				}
				return;
			}

			$this->ID = $id;
		}

		public function exists() {
			return $this->ID > 0;
		}

		public function has_cap( $cap ) {
			return ! empty( $this->caps[ $cap ] ) || in_array( $cap, $this->roles, true );
		}
	}

	class WP_User_Query {
		private $query_results = array();

		public function __construct( $args = array() ) {
			$users = array_values( $GLOBALS['wp_test_users'] );
			if ( ! empty( $args['role'] ) ) {
				$role = $args['role'];
				$users = array_filter(
					$users,
					function ( $user ) use ( $role ) {
						return in_array( $role, (array) $user->roles, true ) || ! empty( $user->caps[ $role ] );
					}
				);
			}
			$this->query_results = array_values( $users );
		}

		public function query() {}

		public function get_results() {
			return $this->query_results;
		}

		public function get_total() {
			return count( $this->query_results );
		}
	}

	class WP_Post {
		public $ID = 0;
		public $post_author = 1;
		public $post_title = '';
		public $post_content = '';
		public $post_excerpt = '';
		public $post_status = 'publish';
		public $post_type = 'post';
		public $post_date = '2026-01-01 00:00:00';
		public $post_modified = '2026-01-01 00:00:00';
		public $post_parent = 0;
		public $guid = '';

		public function __construct( $post = null ) {
			if ( is_object( $post ) ) {
				foreach ( get_object_vars( $post ) as $key => $value ) {
					$this->$key = $value;
				}
			}
		}
	}

	class WP_Term {
		public $term_id = 0;
		public $name = '';
		public $slug = '';
		public $taxonomy = '';

		public function __construct( $term = null ) {
			if ( is_object( $term ) ) {
				foreach ( get_object_vars( $term ) as $key => $value ) {
					$this->$key = $value;
				}
			}
		}
	}

	class WP_Query {
		public $posts = array();
		public $found_posts = 0;
		public $query_vars = array();

		public function __construct( $query = '' ) {
			$this->query_vars  = is_array( $query ) ? $query : array();
			$filtered          = post_collection_test_filter_posts( array_values( $GLOBALS['wp_test_posts'] ), $this->query_vars, false );
			$this->found_posts = count( $filtered );
			$this->posts       = post_collection_test_filter_posts( $filtered, $this->query_vars, true, false );
		}

		public function get_posts() {
			return $this->posts;
		}
	}

	class WP_REST_Request {
		private $params = array();

		public function __construct( $method = '', $route = '' ) {}

		public function set_body_params( $params ) {
			$this->params = (array) $params;
		}

		public function get_param( $param ) {
			return $this->params[ $param ] ?? null;
		}
	}

	function post_collection_test_filter_posts( $posts, $args, $paginate = true, $filter = true ) {
		if ( $filter ) {
			if ( ! empty( $args['post_type'] ) ) {
				$post_types = (array) $args['post_type'];
				$posts = array_filter(
					$posts,
					function ( $post ) use ( $post_types ) {
						return in_array( $post->post_type, $post_types, true );
					}
				);
			}

			if ( ! empty( $args['post_status'] ) && 'any' !== $args['post_status'] ) {
				$statuses = (array) $args['post_status'];
				$posts = array_filter(
					$posts,
					function ( $post ) use ( $statuses ) {
						return in_array( $post->post_status, $statuses, true );
					}
				);
			}

			if ( ! empty( $args['author'] ) ) {
				$author = (int) $args['author'];
				$posts = array_filter(
					$posts,
					function ( $post ) use ( $author ) {
						return (int) $post->post_author === $author;
					}
				);
			}

			if ( ! empty( $args['post__in'] ) ) {
				$post_ids = array_map( 'intval', (array) $args['post__in'] );
				$posts = array_filter(
					$posts,
					function ( $post ) use ( $post_ids ) {
						return in_array( (int) $post->ID, $post_ids, true );
					}
				);
			}

			if ( ! empty( $args['s'] ) ) {
				$search = strtolower( (string) $args['s'] );
				$posts = array_filter(
					$posts,
					function ( $post ) use ( $search ) {
						return false !== strpos( strtolower( $post->post_title . ' ' . $post->post_content ), $search );
					}
				);
			}

			if ( ! empty( $args['meta_query'] ) ) {
				$meta_query = $args['meta_query'];
				$posts = array_filter(
					$posts,
					function ( $post ) use ( $meta_query ) {
						return post_collection_test_post_matches_meta_query( $post, $meta_query );
					}
				);
			}

			if ( ! empty( $args['tax_query'] ) ) {
				$tax_query = $args['tax_query'];
				$posts = array_filter(
					$posts,
					function ( $post ) use ( $tax_query ) {
						return post_collection_test_post_matches_tax_query( $post, $tax_query );
					}
				);
			}
		}

		$posts = array_values( $posts );

		if ( ! $paginate ) {
			return $posts;
		}

		$offset = isset( $args['offset'] ) ? max( 0, (int) $args['offset'] ) : 0;
		$limit  = isset( $args['posts_per_page'] ) ? (int) $args['posts_per_page'] : -1;

		if ( -1 === $limit ) {
			return array_slice( $posts, $offset );
		}

		return array_slice( $posts, $offset, $limit );
	}

	function post_collection_test_post_matches_meta_query( $post, $meta_query ) {
		$relation = 'AND';
		if ( isset( $meta_query['relation'] ) ) {
			$relation = strtoupper( $meta_query['relation'] );
		}

		$clauses = array();
		foreach ( $meta_query as $key => $clause ) {
			if ( ! is_array( $clause ) || ! isset( $clause['key'] ) ) {
				continue;
			}
			if ( isset( $clause['relation'] ) && ! isset( $clause['key'] ) ) {
				$relation = strtoupper( $clause['relation'] );
				continue;
			}
			$clauses[] = $clause;
		}

		if ( empty( $clauses ) ) {
			return true;
		}

		$matches = array_map(
			function ( $clause ) use ( $post ) {
				$key     = $clause['key'];
				$compare = strtoupper( $clause['compare'] ?? '=' );
				$value   = $clause['value'] ?? null;
				$stored  = get_post_meta( $post->ID, $key, true );
				$has     = '' !== $stored && null !== $stored;

				if ( 'NOT EXISTS' === $compare ) {
					return ! $has;
				}
				if ( 'EXISTS' === $compare ) {
					return $has;
				}
				if ( 'IN' === $compare ) {
					return in_array( (string) $stored, array_map( 'strval', (array) $value ), true );
				}
				return (string) $stored === (string) $value;
			},
			$clauses
		);

		return 'OR' === $relation ? in_array( true, $matches, true ) : ! in_array( false, $matches, true );
	}

	function post_collection_test_post_matches_tax_query( $post, $tax_query ) {
		$relation = 'AND';
		if ( isset( $tax_query['relation'] ) ) {
			$relation = strtoupper( $tax_query['relation'] );
		}

		$clauses = array();
		foreach ( $tax_query as $clause ) {
			if ( ! is_array( $clause ) || empty( $clause['taxonomy'] ) ) {
				continue;
			}
			$clauses[] = $clause;
		}

		if ( empty( $clauses ) ) {
			return true;
		}

		$matches = array_map(
			function ( $clause ) use ( $post ) {
				$taxonomy = $clause['taxonomy'];
				$field    = $clause['field'] ?? 'term_id';
				$terms    = array_map( 'strval', (array) ( $clause['terms'] ?? array() ) );
				$operator = strtoupper( $clause['operator'] ?? 'IN' );
				$assigned = $GLOBALS['wp_test_terms'][ $post->ID ][ $taxonomy ] ?? array();

				if ( 'term_id' !== $field ) {
					$assigned = array_map(
						function ( $term_id ) use ( $taxonomy, $field ) {
							$term = get_term( $term_id, $taxonomy );
							return $term && isset( $term->$field ) ? $term->$field : $term_id;
						},
						$assigned
					);
				}

				$has_term = (bool) array_intersect( array_map( 'strval', $assigned ), $terms );
				return 'NOT IN' === $operator ? ! $has_term : $has_term;
			},
			$clauses
		);

		return 'OR' === $relation ? in_array( true, $matches, true ) : ! in_array( false, $matches, true );
	}

	function get_posts( $args = array() ) {
		$posts = post_collection_test_filter_posts( array_values( $GLOBALS['wp_test_posts'] ), $args );
		if ( isset( $args['fields'] ) && 'ids' === $args['fields'] ) {
			return array_map(
				function ( $post ) {
					return (int) $post->ID;
				},
				$posts
			);
		}
		return $posts;
	}

	function get_post( $post = null ) {
		if ( $post instanceof WP_Post ) {
			return $post;
		}
		if ( null === $post ) {
			return $GLOBALS['post'] ?? null;
		}
		$post_id = is_object( $post ) ? (int) $post->ID : (int) $post;
		return $GLOBALS['wp_test_posts'][ $post_id ] ?? null;
	}

	function get_the_ID() {
		$post = get_post();
		return $post ? (int) $post->ID : 0;
	}

	function get_post_status( $post = null ) {
		$post = get_post( $post );
		return $post ? $post->post_status : false;
	}

	function wp_get_post_parent_id( $post_id ) {
		$post = get_post( $post_id );
		return $post ? (int) $post->post_parent : 0;
	}

	function wp_insert_post( $postarr, $wp_error = false, $fire_after_hooks = true ) {
		$post_id = isset( $postarr['ID'] ) ? (int) $postarr['ID'] : ( count( $GLOBALS['wp_test_posts'] ) + 1 );
		$post = new WP_Post(
			(object) array_merge(
				array(
					'ID' => $post_id,
				),
				$postarr
			)
		);
		$GLOBALS['wp_test_posts'][ $post_id ] = $post;
		return $post_id;
	}

	function wp_update_post( $postarr, $wp_error = false ) {
		$post_id = (int) ( $postarr['ID'] ?? 0 );
		if ( ! $post_id || empty( $GLOBALS['wp_test_posts'][ $post_id ] ) ) {
			return $wp_error ? new WP_Error( 'invalid-post', 'Invalid post.' ) : 0;
		}
		foreach ( $postarr as $key => $value ) {
			$GLOBALS['wp_test_posts'][ $post_id ]->$key = $value;
		}
		return $post_id;
	}

	function wp_untrash_post( $post_id ) {
		return true;
	}

	function get_post_meta( $post_id, $key = '', $single = false ) {
		if ( '' === $key ) {
			return $GLOBALS['wp_test_post_meta'][ $post_id ] ?? array();
		}
		if ( ! array_key_exists( $post_id, $GLOBALS['wp_test_post_meta'] ) || ! array_key_exists( $key, $GLOBALS['wp_test_post_meta'][ $post_id ] ) ) {
			return $single ? '' : array();
		}
		$value = $GLOBALS['wp_test_post_meta'][ $post_id ][ $key ];
		return $single ? $value : (array) $value;
	}

	function update_post_meta( $post_id, $meta_key, $meta_value, $prev_value = '' ) {
		$GLOBALS['wp_test_post_meta'][ $post_id ][ $meta_key ] = $meta_value;
		return true;
	}

	function delete_post_meta( $post_id, $meta_key, $meta_value = '' ) {
		unset( $GLOBALS['wp_test_post_meta'][ $post_id ][ $meta_key ] );
		return true;
	}

	function get_terms( $args = array() ) {
		$taxonomy = $args['taxonomy'] ?? '';
		$terms = array_values( $GLOBALS['wp_test_registered_terms'][ $taxonomy ] ?? array() );

		if ( isset( $args['orderby'] ) && 'name' === $args['orderby'] ) {
			usort(
				$terms,
				function ( $a, $b ) use ( $args ) {
					$result = strcasecmp( $a->name, $b->name );
					return isset( $args['order'] ) && 'DESC' === strtoupper( $args['order'] ) ? -$result : $result;
				}
			);
		}

		return $terms;
	}

	function get_term_by( $field, $value, $taxonomy = '', $output = OBJECT, $filter = 'raw' ) {
		foreach ( $GLOBALS['wp_test_registered_terms'][ $taxonomy ] ?? array() as $term ) {
			if ( isset( $term->$field ) && (string) $term->$field === (string) $value ) {
				return $term;
			}
		}

		return false;
	}

	function get_term( $term_id, $taxonomy = '' ) {
		$term_id = (int) $term_id;
		if ( $taxonomy ) {
			return $GLOBALS['wp_test_registered_terms'][ $taxonomy ][ $term_id ] ?? null;
		}

		foreach ( $GLOBALS['wp_test_registered_terms'] as $terms ) {
			if ( isset( $terms[ $term_id ] ) ) {
				return $terms[ $term_id ];
			}
		}

		return null;
	}

	function term_exists( $term, $taxonomy = '', $parent_term = null ) {
		foreach ( $GLOBALS['wp_test_registered_terms'][ $taxonomy ] ?? array() as $registered_term ) {
			if ( (string) $registered_term->slug === (string) $term || (string) $registered_term->name === (string) $term ) {
				return array(
					'term_id'          => (string) $registered_term->term_id,
					'term_taxonomy_id' => (string) $registered_term->term_id,
				);
			}
		}

		return null;
	}

	function get_term_meta( $term_id, $key = '', $single = false ) {
		if ( '' === $key ) {
			return $GLOBALS['wp_test_term_meta'][ $term_id ] ?? array();
		}
		if ( ! array_key_exists( $term_id, $GLOBALS['wp_test_term_meta'] ) || ! array_key_exists( $key, $GLOBALS['wp_test_term_meta'][ $term_id ] ) ) {
			return $single ? '' : array();
		}
		$value = $GLOBALS['wp_test_term_meta'][ $term_id ][ $key ];
		return $single ? $value : (array) $value;
	}

	function update_term_meta( $term_id, $meta_key, $meta_value, $prev_value = '' ) {
		$GLOBALS['wp_test_term_meta'][ $term_id ][ $meta_key ] = $meta_value;
		return true;
	}

	function delete_term_meta( $term_id, $meta_key, $meta_value = '' ) {
		unset( $GLOBALS['wp_test_term_meta'][ $term_id ][ $meta_key ] );
		return true;
	}

	function wp_insert_term( $term, $taxonomy, $args = array() ) {
		$term_id = count( $GLOBALS['wp_test_registered_terms'][ $taxonomy ] ?? array() ) + 1;
		$created = new WP_Term(
			(object) array(
				'term_id'  => $term_id,
				'name'     => $term,
				'slug'     => $args['slug'] ?? sanitize_title( $term ),
				'taxonomy' => $taxonomy,
			)
		);
		$GLOBALS['wp_test_registered_terms'][ $taxonomy ][ $term_id ] = $created;
		return array(
			'term_id'          => $term_id,
			'term_taxonomy_id' => $term_id,
		);
	}

	function wp_delete_term( $term, $taxonomy, $args = array() ) {
		$term_id = (int) $term;
		if ( empty( $GLOBALS['wp_test_registered_terms'][ $taxonomy ][ $term_id ] ) ) {
			return false;
		}

		unset( $GLOBALS['wp_test_registered_terms'][ $taxonomy ][ $term_id ] );
		unset( $GLOBALS['wp_test_term_meta'][ $term_id ] );
		foreach ( $GLOBALS['wp_test_terms'] as $object_id => $taxonomies ) {
			if ( empty( $taxonomies[ $taxonomy ] ) ) {
				continue;
			}
			$GLOBALS['wp_test_terms'][ $object_id ][ $taxonomy ] = array_values(
				array_filter(
					$taxonomies[ $taxonomy ],
					function ( $assigned_term_id ) use ( $term_id ) {
						return (int) $assigned_term_id !== $term_id;
					}
				)
			);
		}

		return true;
	}

	function wp_set_post_terms( $post_id, $terms, $taxonomy, $append = false ) {
		return wp_set_object_terms( $post_id, $terms, $taxonomy, $append );
	}

	function wp_set_object_terms( $object_id, $terms, $taxonomy, $append = false ) {
		if ( $append ) {
			$GLOBALS['wp_test_terms'][ $object_id ][ $taxonomy ] = array_values(
				array_unique(
					array_merge( $GLOBALS['wp_test_terms'][ $object_id ][ $taxonomy ] ?? array(), (array) $terms )
				)
			);
			return $GLOBALS['wp_test_terms'][ $object_id ][ $taxonomy ];
		}
		$GLOBALS['wp_test_terms'][ $object_id ][ $taxonomy ] = (array) $terms;
		return $terms;
	}

	function get_the_terms( $post, $taxonomy ) {
		$post = get_post( $post );
		if ( ! $post ) {
			return false;
		}

		$term_ids = $GLOBALS['wp_test_terms'][ $post->ID ][ $taxonomy ] ?? array();
		$terms = array();
		foreach ( $term_ids as $term_id ) {
			$term = get_term( $term_id, $taxonomy );
			if ( $term ) {
				$terms[] = $term;
			}
		}

		return $terms ?: false;
	}

	function has_term( $term = '', $taxonomy = '', $post = null ) {
		$post = get_post( $post );
		if ( ! $post ) {
			return false;
		}

		$terms = $GLOBALS['wp_test_terms'][ $post->ID ][ $taxonomy ] ?? array();
		return in_array( (int) $term, array_map( 'intval', $terms ), true );
	}

	function wp_get_post_revisions( $post_id = 0, $args = null ) {
		return array();
	}

	function get_the_title( $post = 0 ) {
		$post = get_post( $post );
		return $post ? $post->post_title : '';
	}

	function get_the_excerpt( $post = null ) {
		$post = get_post( $post );
		return $post ? $post->post_excerpt : '';
	}

	function get_permalink( $post = 0 ) {
		$post_id = is_object( $post ) ? $post->ID : (int) $post;
		return 'https://example.com/?p=' . $post_id;
	}

	function get_edit_post_link( $id = 0, $context = 'display' ) {
		return 'https://example.com/wp-admin/post.php?post=' . (int) $id . '&action=edit';
	}

	function get_edit_user_link( $user_id = null ) {
		return 'https://example.com/wp-admin/user-edit.php?user_id=' . (int) $user_id;
	}

	function self_admin_url( $path = '', $scheme = 'admin' ) {
		return 'https://example.com/wp-admin/' . ltrim( $path, '/' );
	}

	function get_user_by( $field, $value ) {
		foreach ( $GLOBALS['wp_test_users'] as $user ) {
			if ( 'ID' === $field && (int) $user->ID === (int) $value ) {
				return new WP_User( $user );
			}
			if ( 'login' === $field && $user->user_login === $value ) {
				return new WP_User( $user );
			}
		}
		return false;
	}

	function username_exists( $user_login ) {
		$user = get_user_by( 'login', $user_login );
		return $user ? $user->ID : false;
	}

	function wp_insert_user( $userdata ) {
		$user_id = isset( $userdata['ID'] ) ? (int) $userdata['ID'] : ( count( $GLOBALS['wp_test_users'] ) + 1 );
		$user = new WP_User(
			(object) array(
				'ID'           => $user_id,
				'user_login'   => $userdata['user_login'] ?? '',
				'display_name' => $userdata['display_name'] ?? '',
				'description'  => $userdata['description'] ?? '',
				'roles'        => isset( $userdata['role'] ) ? array( $userdata['role'] ) : array(),
				'caps'         => isset( $userdata['role'] ) ? array( $userdata['role'] => true ) : array(),
			)
		);
		$GLOBALS['wp_test_users'][ $user_id ] = $user;
		return $user_id;
	}

	function wp_update_user( $userdata ) {
		$user_id = (int) ( $userdata['ID'] ?? 0 );
		if ( ! $user_id || empty( $GLOBALS['wp_test_users'][ $user_id ] ) ) {
			return new WP_Error( 'invalid-user', 'Invalid user.' );
		}
		foreach ( $userdata as $key => $value ) {
			if ( 'ID' !== $key ) {
				$GLOBALS['wp_test_users'][ $user_id ]->$key = $value;
			}
		}
		return $user_id;
	}

	function get_user_option( $option, $user_id = 0 ) {
		return $GLOBALS['wp_test_user_options'][ $user_id ][ $option ] ?? false;
	}

	function update_user_option( $user_id, $option, $value, $global = false ) {
		$GLOBALS['wp_test_user_options'][ $user_id ][ $option ] = $value;
		return true;
	}

	function delete_user_option( $user_id, $option, $global = false ) {
		unset( $GLOBALS['wp_test_user_options'][ $user_id ][ $option ] );
		return true;
	}

	function count_user_posts( $user_id, $post_type = 'post' ) {
		return count(
			array_filter(
				$GLOBALS['wp_test_posts'],
				function ( $post ) use ( $user_id, $post_type ) {
					return (int) $post->post_author === (int) $user_id && $post->post_type === $post_type;
				}
			)
		);
	}

	function home_url( $path = '', $scheme = null ) {
		return 'https://example.com' . $path;
	}

	function add_query_arg( ...$args ) {
		if ( is_array( $args[0] ) ) {
			$params = $args[0];
			$url    = $args[1] ?? '';
		} else {
			$params = array( $args[0] => $args[1] ?? '' );
			$url    = $args[2] ?? '';
		}

		$separator = false === strpos( $url, '?' ) ? '?' : '&';
		return $url . $separator . http_build_query( $params );
	}

	function trailingslashit( $string ) {
		return rtrim( $string, '/' ) . '/';
	}

	function wp_verify_nonce( $nonce, $action = -1 ) {
		return hash_equals( 'nonce-' . $action, (string) $nonce ) ? 1 : false;
	}

	function wp_create_nonce( $action = -1 ) {
		return 'nonce-' . $action;
	}

	function wp_nonce_field( $action = -1, $name = '_wpnonce', $referer = true, $display = true ) {
		$field = '<input type="hidden" name="' . esc_attr( $name ) . '" value="' . esc_attr( wp_create_nonce( $action ) ) . '" />';
		if ( $display ) {
			echo $field;
		}
		return $field;
	}

	function sanitize_text_field( $str ) {
		return trim( strip_tags( (string) $str ) );
	}

	function sanitize_textarea_field( $str ) {
		return trim( strip_tags( (string) $str ) );
	}

	function sanitize_user( $username, $strict = false ) {
		$username = strtolower( (string) $username );
		$username = preg_replace( $strict ? '/[^a-z0-9._-]/' : '/[^a-z0-9._@-]/', '', $username );
		return trim( $username );
	}

	function sanitize_title( $title, $fallback_title = '', $context = 'save' ) {
		$title = strtolower( remove_accents( (string) $title ) );
		$title = preg_replace( '/[^a-z0-9-]+/', '-', $title );
		return trim( $title, '-' );
	}

	function sanitize_key( $key ) {
		return preg_replace( '/[^a-z0-9_-]/', '', strtolower( (string) $key ) );
	}

	function remove_accents( $text ) {
		return $text;
	}

	function wp_strip_all_tags( $text, $remove_breaks = false ) {
		return strip_tags( (string) $text );
	}

	function wp_generate_password( $length = 12, $special_chars = true, $extra_special_chars = false ) {
		return str_repeat( 'x', $length );
	}

	function absint( $maybeint ) {
		return abs( (int) $maybeint );
	}

	function date_i18n( $format, $timestamp = false, $gmt = false ) {
		return date( $format, $timestamp ?: time() );
	}

	require_once __DIR__ . '/../class-extracted-page.php';
	require_once __DIR__ . '/../site-configs/class-site-config.php';
	require_once __DIR__ . '/../site-configs/class-jina.php';
	require_once __DIR__ . '/../site-configs/class-cloudflare-protected.php';
	require_once __DIR__ . '/../site-configs/class-archive-is.php';
	require_once __DIR__ . '/../class-user.php';
	require_once __DIR__ . '/../class-user-query.php';
	require_once __DIR__ . '/../includes/class-article-notes.php';
}
