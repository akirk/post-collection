<?php

use PHPUnit\Framework\TestCase;
use PostCollection\ExtractedPage;
use PostCollection\Post_Collection;
use PostCollection\SiteConfig\Archive_Is;

require_once __DIR__ . '/../class-post-collection.php';

class Post_Collection_Browser_Extension_Test_Plugin extends Post_Collection {
	public $download_item;
	public $download_url;
	public $existing_post_id = null;

	public function __construct() {}

	public function get_required_role() {
		return 'edit_private_posts';
	}

	public function url_to_postid( $url, $user_id ) {
		return $this->existing_post_id;
	}

	public function download( $url, $content = null ) {
		$this->download_url = $url;
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
		$this->plugin->register_site_config( new Archive_Is() );
	}

	public function test_save_response_reports_collected_post_word_count() {
		$collection = $this->create_collection_term( 1, 'articles', 'Articles' );
		$this->plugin->download_item = new ExtractedPage(
			'https://source.example/post',
			'Source Title',
			'<article><p>One two three.</p><p>Four &amp; five.</p></article>'
		);

		$result = $this->plugin->friends_browser_extension_action_save(
			null,
			$this->create_request( $collection->term_id ),
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
		$collection = $this->create_collection_term( 2, 'saved', 'Saved Articles' );
		$post       = new \WP_Post(
			(object) array(
				'ID'           => 42,
				'post_author'  => 99,
				'post_title'   => 'Existing',
				'post_content' => '<p>Already saved words here.</p>',
				'post_status'  => 'private',
				'post_type'    => Post_Collection::CPT,
				'guid'         => 'https://source.example/post',
			)
		);

		$GLOBALS['wp_test_posts'][ $post->ID ] = $post;
		$GLOBALS['wp_test_terms'][ $post->ID ][ Post_Collection::COLLECTION_TAXONOMY ] = array( $collection->term_id );
		$this->plugin->existing_post_id       = $post->ID;

		$result = $this->plugin->friends_browser_extension_action_save(
			null,
			$this->create_request( $collection->term_id ),
			new \WP_User( 99 ),
			array()
		);

		$this->assertFalse( is_wp_error( $result ) );
		$this->assertFalse( $result['created'] );
		$this->assertSame( 4, $result['word_count'] );
		$this->assertSame( '4 words', $result['word_count_label'] );
		$this->assertStringContainsString( 'already in the collection', $result['message'] );
	}

	public function test_archive_is_save_uses_original_url_from_posted_html() {
		$collection   = $this->create_collection_term( 3, 'archive-captures', 'Archive Captures' );
		$original_url = 'https://www.nzz.ch/konsequent_himmelwaerts-ld.898962';
		$archive_html = '<html><head><link rel="canonical" href="https://archive.is/2026.08.08-084003/' . $original_url . '"></head>'
			. '<body><input name="q" value="' . $original_url . '"></body></html>';
		$this->plugin->download_item = new ExtractedPage(
			$original_url,
			'Konsequent himmelwärts',
			'<article><p>Extracted article content.</p></article>'
		);

		$result = $this->plugin->friends_browser_extension_action_save(
			null,
			$this->create_request(
				$collection->term_id,
				array(
					'url'  => 'https://archive.is/MT8fM',
					'html' => $archive_html,
				)
			),
			new \WP_User( 99 ),
			array()
		);

		$this->assertFalse( is_wp_error( $result ) );
		$this->assertTrue( $result['success'] );
		$this->assertSame( $original_url, $this->plugin->download_url );
		$saved_post = reset( $GLOBALS['wp_test_posts'] );
		$this->assertSame( $original_url, $saved_post->guid );
	}

	private function create_collection_term( $id, $slug, $name ) {
		$term = new \WP_Term(
			(object) array(
				'term_id'  => $id,
				'name'     => $name,
				'slug'     => $slug,
				'taxonomy' => Post_Collection::COLLECTION_TAXONOMY,
			)
		);

		$GLOBALS['wp_test_registered_terms'][ Post_Collection::COLLECTION_TAXONOMY ][ $id ] = $term;

		return $term;
	}

	private function create_request( $collection_id, $params = array() ) {
		$request = new \WP_REST_Request( 'POST', '/friends/v1/extension/action' );
		$request->set_body_params(
			array_merge(
				array(
					'collection_id' => $collection_id,
					'url'           => 'https://source.example/post',
					'html'          => '<html><body>Fallback page HTML.</body></html>',
					'title'         => 'Browser Page Title',
				),
				$params
			)
		);

		return $request;
	}
}
