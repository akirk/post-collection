<?php

use PHPUnit\Framework\TestCase;
use PostCollection\ExtractedPage;
use PostCollection\Post_Collection;
use PostCollection\User;

require_once __DIR__ . '/../class-post-collection.php';

class Post_Collection_Browser_Extension_Test_Plugin extends Post_Collection {
	public $download_item;
	public $existing_post_id = null;

	public function __construct() {}

	public function get_required_role() {
		return 'edit_private_posts';
	}

	public function url_to_postid( $url, $user_id ) {
		return $this->existing_post_id;
	}

	public function download( $url, $content = null ) {
		return $this->download_item;
	}
}

class Test_Browser_Extension_Action extends TestCase {
	private $plugin;

	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['wp_test_current_user_caps'] = array(
			'edit_private_posts' => true,
		);
		$GLOBALS['wp_test_posts']             = array();
		$GLOBALS['wp_test_post_meta']         = array();
		$GLOBALS['wp_test_users']             = array();
		$GLOBALS['wp_test_user_options']      = array();

		$this->plugin = new Post_Collection_Browser_Extension_Test_Plugin();
	}

	public function test_save_response_reports_collected_post_word_count() {
		$collection = $this->create_collection_user( 1, 'articles', 'Articles' );
		$this->plugin->download_item = new ExtractedPage(
			'https://source.example/post',
			'Source Title',
			'<article><p>One two three.</p><p>Four &amp; five.</p></article>'
		);

		$result = $this->plugin->friends_browser_extension_action_save(
			null,
			$this->create_request( $collection->ID ),
			new \WP_User( 99 ),
			array()
		);

		$this->assertFalse( is_wp_error( $result ) );
		$this->assertTrue( $result['success'] );
		$this->assertSame( 5, $result['word_count'] );
		$this->assertSame( '5 words', $result['word_count_label'] );
		$this->assertStringContainsString( '5 words', $result['message'] );
	}

	public function test_existing_url_response_reports_existing_post_word_count() {
		$collection = $this->create_collection_user( 2, 'saved', 'Saved Articles' );
		$post       = new \WP_Post(
			(object) array(
				'ID'           => 42,
				'post_author'  => $collection->ID,
				'post_title'   => 'Existing',
				'post_content' => '<p>Already saved words here.</p>',
				'post_status'  => 'private',
				'post_type'    => Post_Collection::CPT,
				'guid'         => 'https://source.example/post',
			)
		);

		$GLOBALS['wp_test_posts'][ $post->ID ] = $post;
		$this->plugin->existing_post_id       = $post->ID;

		$result = $this->plugin->friends_browser_extension_action_save(
			null,
			$this->create_request( $collection->ID ),
			new \WP_User( 99 ),
			array()
		);

		$this->assertFalse( is_wp_error( $result ) );
		$this->assertFalse( $result['created'] );
		$this->assertSame( 4, $result['word_count'] );
		$this->assertSame( '4 words', $result['word_count_label'] );
		$this->assertStringContainsString( 'already in the collection', $result['message'] );
	}

	private function create_collection_user( $id, $login, $display_name ) {
		$user = new User(
			(object) array(
				'ID'           => $id,
				'user_login'   => $login,
				'display_name' => $display_name,
				'description'  => '',
				'roles'        => array( 'post_collection' ),
				'caps'         => array( 'post_collection' => true ),
			)
		);

		$GLOBALS['wp_test_users'][ $id ] = $user;

		return $user;
	}

	private function create_request( $collection_id ) {
		$request = new \WP_REST_Request( 'POST', '/friends/v1/extension/action' );
		$request->set_body_params(
			array(
				'collection_id' => $collection_id,
				'url'           => 'https://source.example/post',
				'html'          => '<html><body>Fallback page HTML.</body></html>',
				'title'         => 'Browser Page Title',
			)
		);

		return $request;
	}
}
