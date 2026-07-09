<?php

use PHPUnit\Framework\TestCase;
use PostCollection\Post_Collection;
use PostCollection\User;

require_once __DIR__ . '/../class-post-collection.php';

class Test_Entry_Dropdown_Menu extends TestCase {
	private $plugin;

	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['wp_test_actions']          = array();
		$GLOBALS['wp_test_filters']          = array();
		$GLOBALS['wp_test_current_user_caps'] = array(
			'edit_private_posts' => true,
		);
		$GLOBALS['wp_test_posts']            = array();
		$GLOBALS['wp_test_post_meta']        = array();
		$GLOBALS['wp_test_terms']            = array();
		$GLOBALS['wp_test_term_meta']        = array();
		$GLOBALS['wp_test_registered_terms'] = array();
		$GLOBALS['wp_test_users']            = array();
		$GLOBALS['wp_test_user_options']     = array();
		unset( $GLOBALS['post'] );

		$this->plugin = new Post_Collection();
	}

	public function test_normal_friend_item_shows_collection_moves_without_collection_management_controls() {
		$post = $this->create_post(
			101,
			'friend_post_cache',
			'Normal Friend Item',
			'publish'
		);
		$GLOBALS['post'] = $post;

		$friend_user = $this->create_user( 501, 'claude', 'Claude', array( 'post_collection' ) );
		$this->create_collection_term( 201, 'bookmarks', 'Bookmarks' );
		$this->create_collection_term( 202, 'saved-posts', 'Saved Posts' );
		update_term_meta( 202, 'friends_post_collection_copy_mode', true );

		$output = $this->render_dropdown( $post, $friend_user );

		$this->assertStringNotContainsString( 'Edit Post Collection', $output );
		$this->assertStringNotContainsString( 'Hide post from the feed', $output );
		$this->assertStringContainsString( 'Move to Bookmarks', $output );
		$this->assertStringContainsString( 'Copy to Saved Posts', $output );
	}

	public function test_collected_post_shows_management_controls_and_skips_current_collection() {
		$post = $this->create_post(
			102,
			Post_Collection::CPT,
			'Collected Item',
			'publish'
		);
		$GLOBALS['post'] = $post;

		$collection_user = $this->create_user( 502, 'collection-author', 'Collection Author', array( 'post_collection' ) );
		$post->post_author = $collection_user->ID;

		$this->create_collection_term( 203, 'bookmarks', 'Bookmarks' );
		$this->create_collection_term( 204, 'saved-posts', 'Saved Posts' );
		wp_set_object_terms( $post->ID, array( 203 ), Post_Collection::COLLECTION_TAXONOMY );

		$output = $this->render_dropdown( $post, $collection_user );

		$this->assertStringContainsString( 'Edit Post Collection', $output );
		$this->assertStringContainsString( 'Hide post from the feed', $output );
		$this->assertStringNotContainsString( 'Move to Bookmarks', $output );
		$this->assertStringContainsString( 'Move to Saved Posts', $output );
	}

	private function render_dropdown( \WP_Post $post, \WP_User $friend_user ) {
		ob_start();
		$this->plugin->entry_dropdown_menu( $post, $friend_user );
		return ob_get_clean();
	}

	private function create_post( $id, $post_type, $title, $status ) {
		$post = new \WP_Post(
			(object) array(
				'ID'           => $id,
				'post_author'  => 0,
				'post_title'   => $title,
				'post_content' => 'Post content for ' . $title,
				'post_status'  => $status,
				'post_type'    => $post_type,
				'guid'         => 'https://example.com/source/' . $id,
			)
		);

		$GLOBALS['wp_test_posts'][ $id ] = $post;

		return $post;
	}

	private function create_user( $id, $login, $display_name, array $roles ) {
		$user = new User(
			(object) array(
				'ID'           => $id,
				'user_login'   => $login,
				'display_name' => $display_name,
				'description'  => '',
				'roles'        => $roles,
				'caps'         => array_fill_keys( $roles, true ),
			)
		);

		$GLOBALS['wp_test_users'][ $id ] = $user;

		return $user;
	}

	private function create_collection_term( $id, $slug, $name ) {
		$term = new \WP_Term(
			(object) array(
				'term_id'  => $id,
				'slug'     => $slug,
				'name'     => $name,
				'taxonomy' => Post_Collection::COLLECTION_TAXONOMY,
			)
		);

		$GLOBALS['wp_test_registered_terms'][ Post_Collection::COLLECTION_TAXONOMY ][ $id ] = $term;

		return $term;
	}
}
