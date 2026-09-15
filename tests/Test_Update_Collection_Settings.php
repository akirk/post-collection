<?php

use PHPUnit\Framework\TestCase;
use PostCollection\Post_Collection;
use PostCollection\Post_Collection_App;

require_once __DIR__ . '/../class-post-collection.php';
require_once __DIR__ . '/../class-post-collection-app.php';

class Post_Collection_Settings_Test_Plugin extends Post_Collection {
	public function __construct() {}
}

class Test_Update_Collection_Settings extends TestCase {
	private function get_app() {
		$reflection = new ReflectionClass( Post_Collection_App::class );
		$app        = $reflection->newInstanceWithoutConstructor();

		$property = $reflection->getProperty( 'post_collection' );
		$property->setAccessible( true );
		$property->setValue( $app, new Post_Collection_Settings_Test_Plugin() );

		return $app;
	}

	protected function setUp(): void {
		$GLOBALS['wp_test_current_user_caps'] = array();
		$GLOBALS['wp_test_registered_terms']  = array();
		$GLOBALS['wp_test_term_meta']         = array();
	}

	public function test_update_collection_settings_renames_collection_without_changing_slug_when_slug_is_unchanged() {
		$GLOBALS['wp_test_current_user_caps']['edit_private_posts'] = true;
		$created = wp_insert_term(
			'Reading List',
			Post_Collection::COLLECTION_TAXONOMY,
			array(
				'slug' => 'reading-list',
			)
		);
		$term_id = (int) $created['term_id'];

		$app    = $this->get_app();
		$result = $app->update_collection_settings_from_request(
			array(
				'collection_term_id' => $term_id,
				'display_name'       => 'Long Reads',
				'user_login'         => 'reading-list',
				'frontend_mode'      => 'bookmarks',
				'frontend_view'      => 'links',
				'hide_from_home'     => '1',
				'_wpnonce'           => wp_create_nonce( 'post-collection-settings-' . $term_id ),
			)
		);

		$this->assertInstanceOf( WP_Term::class, $result );
		$this->assertSame( 'Long Reads', $result->name );
		$this->assertSame( 'reading-list', $result->slug );
		$this->assertSame( 'bookmarks', get_term_meta( $term_id, 'post_collection_frontend_mode', true ) );
		$this->assertSame( 'links', get_term_meta( $term_id, 'post_collection_frontend_view', true ) );
		$this->assertTrue( get_term_meta( $term_id, 'post_collection_hide_from_home', true ) );
	}

	public function test_update_collection_settings_can_change_slug() {
		$GLOBALS['wp_test_current_user_caps']['edit_private_posts'] = true;
		$created = wp_insert_term(
			'Reading List',
			Post_Collection::COLLECTION_TAXONOMY,
			array(
				'slug' => 'reading-list',
			)
		);
		$term_id = (int) $created['term_id'];

		$app    = $this->get_app();
		$result = $app->update_collection_settings_from_request(
			array(
				'collection_term_id' => $term_id,
				'display_name'       => 'Long Reads',
				'user_login'         => 'long-reads',
				'_wpnonce'           => wp_create_nonce( 'post-collection-settings-' . $term_id ),
			)
		);

		$this->assertInstanceOf( WP_Term::class, $result );
		$this->assertSame( 'Long Reads', $result->name );
		$this->assertSame( 'long-reads', $result->slug );
	}

	public function test_update_collection_settings_rejects_duplicate_slug() {
		$GLOBALS['wp_test_current_user_caps']['edit_private_posts'] = true;
		wp_insert_term(
			'Archive',
			Post_Collection::COLLECTION_TAXONOMY,
			array(
				'slug' => 'archive',
			)
		);
		$created = wp_insert_term(
			'Reading List',
			Post_Collection::COLLECTION_TAXONOMY,
			array(
				'slug' => 'reading-list',
			)
		);
		$term_id = (int) $created['term_id'];

		$app    = $this->get_app();
		$result = $app->update_collection_settings_from_request(
			array(
				'collection_term_id' => $term_id,
				'display_name'       => 'Reading List',
				'user_login'         => 'archive',
				'_wpnonce'           => wp_create_nonce( 'post-collection-settings-' . $term_id ),
			)
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'existing_collection_slug', $result->get_error_code() );
		$this->assertSame( 'reading-list', get_term( $term_id, Post_Collection::COLLECTION_TAXONOMY )->slug );
	}

	public function test_update_collection_settings_rejects_empty_display_name() {
		$GLOBALS['wp_test_current_user_caps']['edit_private_posts'] = true;
		$created = wp_insert_term( 'Reading List', Post_Collection::COLLECTION_TAXONOMY );
		$term_id = (int) $created['term_id'];

		$app    = $this->get_app();
		$result = $app->update_collection_settings_from_request(
			array(
				'collection_term_id' => $term_id,
				'display_name'       => ' ',
				'_wpnonce'           => wp_create_nonce( 'post-collection-settings-' . $term_id ),
			)
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'invalid_display_name', $result->get_error_code() );
		$this->assertSame( 'Reading List', get_term( $term_id, Post_Collection::COLLECTION_TAXONOMY )->name );
	}
}
