<?php

use PHPUnit\Framework\TestCase;
use PostCollection\Post_Collection;

require_once __DIR__ . '/../class-post-collection.php';

class Post_Collection_Save_Method_Urls_Test_Plugin extends Post_Collection {
	public function __construct() {}
}

class Test_Save_Method_Urls extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['wp_test_posts']            = array();
		$GLOBALS['wp_test_terms']            = array();
		$GLOBALS['wp_test_registered_terms'] = array();
		$GLOBALS['wp_test_current_user_id']  = 99;
	}

	public function test_urlforwarder_template_uses_post_collection_endpoint_parameters() {
		$plugin = new Post_Collection_Save_Method_Urls_Test_Plugin();

		$this->assertSame(
			'https://example.com/?collection=42&collect-post=@url&title=@subject',
			$plugin->get_urlforwarder_url( 'collection', 42 )
		);
	}

	public function test_extracts_url_from_shared_text_payload() {
		$plugin = new Post_Collection_Save_Method_Urls_Test_Plugin();

		$this->assertSame(
			array(
				'url'   => 'https://www.heise.de/ct/inhalt/2026/16/122/',
				'title' => '„Eine breit streuende Schrotflinte“: Offenheit ist eine Geschäftsstrategie – auch bei KI-Modellen',
			),
			$plugin->parse_shared_url_payload(
				"„Eine breit streuende Schrotflinte“: Offenheit ist eine Geschäftsstrategie – auch bei KI-Modellen\nhttps://www.heise.de/ct/inhalt/2026/16/122/"
			)
		);
	}

	public function test_can_save_collection_url_without_content_for_manual_editing() {
		$plugin     = new Post_Collection_Save_Method_Urls_Test_Plugin();
		$collection = new \WP_Term(
			(object) array(
				'term_id'  => 42,
				'name'     => 'Saved',
				'slug'     => 'saved',
				'taxonomy' => Post_Collection::COLLECTION_TAXONOMY,
			)
		);

		$post_id = $plugin->save_url_to_collection_term_without_content(
			'https://www.heise.de/ct/inhalt/2026/16/122/',
			$collection,
			array( 'title' => 'Manual Article' )
		);

		$this->assertSame( 1, $post_id );
		$this->assertSame( 'Manual Article', $GLOBALS['wp_test_posts'][ $post_id ]->post_title );
		$this->assertSame( '', $GLOBALS['wp_test_posts'][ $post_id ]->post_content );
		$this->assertSame( 'private', $GLOBALS['wp_test_posts'][ $post_id ]->post_status );
		$this->assertSame( 'https://www.heise.de/ct/inhalt/2026/16/122/', $GLOBALS['wp_test_posts'][ $post_id ]->guid );
		$this->assertSame( array( 42 ), $GLOBALS['wp_test_terms'][ $post_id ][ Post_Collection::COLLECTION_TAXONOMY ] );
	}
}
