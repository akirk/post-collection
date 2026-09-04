<?php

use PHPUnit\Framework\TestCase;
use PostCollection\Article_Notes;
use PostCollection\Post_Collection;
use PostCollection\Post_Collection_App;

require_once __DIR__ . '/../class-post-collection.php';
require_once __DIR__ . '/../class-post-collection-app.php';
require_once __DIR__ . '/../includes/class-article-notes.php';

class Post_Collection_E_Reader_Test_Plugin extends Post_Collection {
	private $notes;

	public function __construct() {
		$this->notes = new Article_Notes( $this );
	}

	public function get_article_notes() {
		return $this->notes;
	}
}

/**
 * The extension points an e-reader plugin hooks into, and the post lists it
 * builds an ePub from.
 */
class Test_App_E_Reader_Hooks extends TestCase {
	private function get_app() {
		$reflection = new ReflectionClass( Post_Collection_App::class );
		$app        = $reflection->newInstanceWithoutConstructor();

		$property = $reflection->getProperty( 'post_collection' );
		$property->setAccessible( true );
		$property->setValue( $app, new Post_Collection_E_Reader_Test_Plugin() );

		return $app;
	}

	protected function setUp(): void {
		$GLOBALS['wp_test_current_user_caps'] = array();
		$GLOBALS['wp_test_actions']           = array();
		$GLOBALS['wp_test_filters']           = array();
		$GLOBALS['wp_test_posts']             = array();
		$GLOBALS['wp_test_post_meta']         = array();
		$GLOBALS['wp_test_terms']             = array();
		$GLOBALS['wp_test_registered_terms']  = array();
		$GLOBALS['wp_test_term_meta']         = array();
	}

	private function create_collection( $name, $published = true ) {
		$created = wp_insert_term( $name, Post_Collection::COLLECTION_TAXONOMY );
		$term    = get_term( $created['term_id'], Post_Collection::COLLECTION_TAXONOMY );
		if ( $published ) {
			update_term_meta( $term->term_id, 'friends_publish_post_collection', true );
		}

		return $term;
	}

	private function create_article( $title, $collection, $status = 'publish' ) {
		$post_id = wp_insert_post(
			array(
				'post_type'   => Post_Collection::CPT,
				'post_title'  => $title,
				'post_status' => $status,
			)
		);

		wp_set_object_terms( $post_id, $collection->term_id, Post_Collection::COLLECTION_TAXONOMY );

		return get_post( $post_id );
	}

	private function mark_read( $app, $post, $status ) {
		$app->get_post_collection()->get_article_notes()->save_note( $post->ID, $status );
	}

	public function test_unread_posts_leave_out_what_has_been_read() {
		$GLOBALS['wp_test_current_user_caps'] = array( 'edit_posts' => true );

		$app        = $this->get_app();
		$collection = $this->create_collection( 'Reading List' );

		$untouched = $this->create_article( 'Untouched', $collection );
		$unread    = $this->create_article( 'Marked unread', $collection );
		$read      = $this->create_article( 'Read', $collection );
		$skipped   = $this->create_article( 'Skipped', $collection );

		$this->mark_read( $app, $unread, Article_Notes::STATUS_UNREAD );
		$this->mark_read( $app, $read, Article_Notes::STATUS_READ );
		$this->mark_read( $app, $skipped, Article_Notes::STATUS_SKIPPED );

		$ids = wp_list_pluck( $app->get_unread_posts( $collection ), 'ID' );

		$this->assertContains( $untouched->ID, $ids );
		$this->assertContains( $unread->ID, $ids );
		$this->assertNotContains( $read->ID, $ids );
		$this->assertNotContains( $skipped->ID, $ids );
	}

	public function test_query_app_posts_is_restricted_to_one_collection() {
		$GLOBALS['wp_test_current_user_caps'] = array( 'edit_posts' => true );

		$app     = $this->get_app();
		$reading = $this->create_collection( 'Reading List' );
		$links   = $this->create_collection( 'Bookmarks' );

		$in_reading = $this->create_article( 'In the reading list', $reading );
		$in_links   = $this->create_article( 'A bookmark', $links );

		$ids = wp_list_pluck( $app->query_app_posts( $reading ), 'ID' );

		$this->assertSame( array( $in_reading->ID ), $ids );

		$all = wp_list_pluck( $app->query_app_posts(), 'ID' );

		$this->assertContains( $in_reading->ID, $all );
		$this->assertContains( $in_links->ID, $all );
	}

	public function test_a_visitor_only_sees_published_posts_in_published_collections() {
		$app       = $this->get_app();
		$published = $this->create_collection( 'Published' );
		$hidden    = $this->create_collection( 'Hidden', false );

		$public  = $this->create_article( 'Public', $published );
		$private = $this->create_article( 'Private', $published, 'private' );
		$this->create_article( 'Out of sight', $hidden );

		$ids = wp_list_pluck( $app->query_app_posts(), 'ID' );

		$this->assertSame( array( $public->ID ), $ids );

		// An integration that has verified a download password of its own asks
		// for everything, the way the e-reader download URL does.
		$ids = wp_list_pluck( $app->query_app_posts( null, array( 'post_collection_include_private' => true ) ), 'ID' );

		$this->assertContains( $private->ID, $ids );
		$this->assertCount( 3, $ids );
	}

	public function test_nothing_is_rendered_without_an_integration() {
		$app        = $this->get_app();
		$collection = $this->create_collection( 'Reading List' );
		$post       = $this->create_article( 'An article', $collection );

		$this->assertFalse( $app->has_item_actions() );
		$this->assertFalse( $app->has_selection_actions() );

		ob_start();
		$app->render_item_actions( $post, 'links' );
		$app->render_item_select( $post, 'links' );
		$app->render_selection_bar( 'collection', $collection );
		$output = ob_get_clean();

		$this->assertSame( '', $output );
	}

	public function test_an_integration_gets_its_actions_and_the_selection_rendered() {
		$app        = $this->get_app();
		$collection = $this->create_collection( 'Reading List' );
		$post       = $this->create_article( 'An article', $collection );

		add_action(
			'post_collection_app_item_actions',
			function ( $item, $context ) {
				echo '<button>' . esc_html( $context . ':' . $item->ID ) . '</button>';
			},
			10,
			2
		);
		add_action(
			'post_collection_app_selection_actions',
			function ( $context, $term ) {
				echo '<button>' . esc_html( $context . ':' . $term->name ) . '</button>';
			},
			10,
			2
		);

		$this->assertTrue( $app->has_item_actions() );
		$this->assertTrue( $app->has_selection_actions() );

		ob_start();
		$app->render_item_actions( $post, 'links' );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'links:' . $post->ID, $output );

		ob_start();
		$app->render_item_select( $post, 'links' );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'data-pc-select-item="links"', $output );
		$this->assertStringContainsString( 'value="' . $post->ID . '"', $output );

		ob_start();
		$app->render_selection_bar( 'collection', $collection );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'data-pc-selection-bar', $output );
		$this->assertStringContainsString( 'collection:Reading List', $output );
	}

	public function test_the_app_request_hook_carries_the_collection() {
		$app        = $this->get_app();
		$collection = $this->create_collection( 'Reading List' );

		$seen = array();
		add_action(
			'post_collection_app_request',
			function ( $requested_app, $requested_collection ) use ( &$seen ) {
				$seen[] = $requested_collection;
			},
			10,
			2
		);

		$_SERVER['REQUEST_URI']              = '/post-collection/' . $collection->slug . '/';
		$GLOBALS['wp_test_query_vars']       = array( 'wp_app_request' => $collection->slug );

		$app->fire_app_request();

		$this->assertCount( 1, $seen );
		$this->assertInstanceOf( WP_Term::class, $seen[0] );
		$this->assertSame( $collection->term_id, $seen[0]->term_id );

		// A URL outside the app leaves everyone alone.
		$_SERVER['REQUEST_URI'] = '/blog/';
		$app->fire_app_request();

		$this->assertCount( 1, $seen );
	}
}
