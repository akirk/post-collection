<?php

use PHPUnit\Framework\TestCase;
use PostCollection\Post_Collection;
use PostCollection\Post_Collection_App;

require_once __DIR__ . '/../class-post-collection.php';
require_once __DIR__ . '/../class-post-collection-app.php';

class Post_Collection_Delete_Test_Plugin extends Post_Collection {
	public function __construct() {}
}

class Test_Delete_Collection extends TestCase {
	private function get_app() {
		$reflection = new ReflectionClass( Post_Collection_App::class );
		$app        = $reflection->newInstanceWithoutConstructor();

		$property = $reflection->getProperty( 'post_collection' );
		$property->setAccessible( true );
		$property->setValue( $app, new Post_Collection_Delete_Test_Plugin() );

		return $app;
	}

	protected function setUp(): void {
		$GLOBALS['wp_test_current_user_caps'] = array();
		$GLOBALS['wp_test_posts']             = array();
		$GLOBALS['wp_test_terms']             = array();
		$GLOBALS['wp_test_registered_terms']  = array();
		$GLOBALS['wp_test_term_meta']         = array();
	}

	public static function rejected_delete_requests(): array {
		return array(
			'without collection management capability' => array( false, true, 'forbidden' ),
			'with an invalid nonce' => array( true, false, 'invalid_nonce' ),
		);
	}

	/** @dataProvider rejected_delete_requests */
	public function test_delete_refusals_preserve_collection_and_articles( bool $can_manage, bool $valid_nonce, string $expected_error ) {
		$GLOBALS['wp_test_current_user_caps']['edit_private_posts'] = $can_manage;
		$created = wp_insert_term(
			'Research',
			Post_Collection::COLLECTION_TAXONOMY,
			array( 'slug' => 'research' )
		);
		$term_id = (int) $created['term_id'];
		$post_id = wp_insert_post(
			array(
				'post_title' => 'Saved Article',
				'post_content' => 'Text to keep.',
				'post_type' => Post_Collection::CPT,
			)
		);
		wp_set_object_terms( $post_id, array( $term_id ), Post_Collection::COLLECTION_TAXONOMY );
		update_term_meta( $term_id, 'post_collection_frontend_mode', 'bookmarks' );

		$result = $this->get_app()->delete_collection_from_request(
			array(
				'collection_term_id' => $term_id,
				'_wpnonce' => $valid_nonce ? wp_create_nonce( 'post-collection-delete-' . $term_id ) : 'bad-nonce',
			)
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( $expected_error, $result->get_error_code() );
		$collection = get_term( $term_id, Post_Collection::COLLECTION_TAXONOMY );
		$this->assertInstanceOf( WP_Term::class, $collection );
		$this->assertSame( 'Research', $collection->name );
		$this->assertSame( 'bookmarks', get_term_meta( $term_id, 'post_collection_frontend_mode', true ) );
		$this->assertTrue( has_term( $term_id, Post_Collection::COLLECTION_TAXONOMY, $post_id ) );
		$this->assertInstanceOf( WP_Post::class, get_post( $post_id ) );
		$this->assertSame( 'Text to keep.', get_post( $post_id )->post_content );
	}

	public function test_delete_collection_removes_term_without_deleting_posts() {
		$GLOBALS['wp_test_current_user_caps']['edit_private_posts'] = true;
		$created = wp_insert_term(
			'Research',
			Post_Collection::COLLECTION_TAXONOMY,
			array(
				'slug' => 'research',
			)
		);
		$term_id = (int) $created['term_id'];

		$post_id = wp_insert_post(
			array(
				'post_title' => 'Saved Article',
				'post_type'  => Post_Collection::CPT,
			)
		);
		wp_set_object_terms( $post_id, array( $term_id ), Post_Collection::COLLECTION_TAXONOMY );
		update_term_meta( $term_id, 'post_collection_frontend_mode', 'bookmarks' );

		$app = $this->get_app();

		$result = $app->delete_collection_from_request(
			array(
				'collection_term_id' => $term_id,
				'_wpnonce'          => wp_create_nonce( 'post-collection-delete-' . $term_id ),
			)
		);

		$this->assertInstanceOf( WP_Term::class, $result );
		$this->assertSame( 'Research', $result->name );
		$this->assertNull( get_term( $term_id, Post_Collection::COLLECTION_TAXONOMY ) );
		$this->assertFalse( get_the_terms( $post_id, Post_Collection::COLLECTION_TAXONOMY ) );
		$this->assertInstanceOf( WP_Post::class, get_post( $post_id ) );
		$this->assertSame( array(), get_term_meta( $term_id ) );
	}

	public function test_delete_collection_reassigns_posts_before_deleting_term() {
		$GLOBALS['wp_test_current_user_caps']['edit_private_posts'] = true;
		$source = wp_insert_term(
			'Research',
			Post_Collection::COLLECTION_TAXONOMY,
			array(
				'slug' => 'research',
			)
		);
		$target = wp_insert_term(
			'Archive',
			Post_Collection::COLLECTION_TAXONOMY,
			array(
				'slug' => 'archive',
			)
		);
		$source_id = (int) $source['term_id'];
		$target_id = (int) $target['term_id'];

		$post_id = wp_insert_post(
			array(
				'post_title'  => 'Saved Article',
				'post_type'   => Post_Collection::CPT,
				'post_status' => 'private',
			)
		);
		wp_set_object_terms( $post_id, array( $source_id ), Post_Collection::COLLECTION_TAXONOMY );

		$app = $this->get_app();

		$result = $app->delete_collection_from_request(
			array(
				'collection_term_id'          => $source_id,
				'reassign_collection_term_id' => $target_id,
				'_wpnonce'                   => wp_create_nonce( 'post-collection-delete-' . $source_id ),
			)
		);

		$this->assertInstanceOf( WP_Term::class, $result );
		$this->assertNull( get_term( $source_id, Post_Collection::COLLECTION_TAXONOMY ) );
		$this->assertTrue( has_term( $target_id, Post_Collection::COLLECTION_TAXONOMY, $post_id ) );
		$this->assertInstanceOf( WP_Post::class, get_post( $post_id ) );
	}

	public function test_delete_collection_rejects_reassigning_to_self() {
		$GLOBALS['wp_test_current_user_caps']['edit_private_posts'] = true;
		$created = wp_insert_term(
			'Research',
			Post_Collection::COLLECTION_TAXONOMY,
			array(
				'slug' => 'research',
			)
		);
		$term_id = (int) $created['term_id'];

		$app = $this->get_app();

		$result = $app->delete_collection_from_request(
			array(
				'collection_term_id'          => $term_id,
				'reassign_collection_term_id' => $term_id,
				'_wpnonce'                   => wp_create_nonce( 'post-collection-delete-' . $term_id ),
			)
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'invalid_reassign_collection', $result->get_error_code() );
		$this->assertInstanceOf( WP_Term::class, get_term( $term_id, Post_Collection::COLLECTION_TAXONOMY ) );
	}
}
