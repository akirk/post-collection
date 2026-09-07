<?php

use PHPUnit\Framework\TestCase;
use PostCollection\Article_Notes;
use PostCollection\Post_Collection;
use PostCollection\Post_Collection_Abilities;
use PostCollection\User;

require_once __DIR__ . '/../class-post-collection.php';
require_once __DIR__ . '/../class-post-collection-abilities.php';

class Post_Collection_Abilities_Test_Plugin extends Post_Collection {
	public $collection_users = array();

	public function __construct() {}

	public function get_required_role() {
		return 'edit_private_posts';
	}

	public function get_post_collection_users() {
		return new Post_Collection_Abilities_Test_User_Query( $this->collection_users );
	}

	public function get_article_notes() {
		return new Article_Notes( $this );
	}

	public function get_post_author_name( \WP_Post $post ) {
		return 'Test Author';
	}
}

class Post_Collection_Abilities_Test_User_Query {
	private $users;

	public function __construct( array $users ) {
		$this->users = $users;
	}

	public function get_results() {
		return $this->users;
	}
}

class Test_Post_Collection_Abilities extends TestCase {
	private $plugin;
	private $abilities;

	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['wp_test_actions']            = array();
		$GLOBALS['wp_test_filters']            = array();
		$GLOBALS['wp_test_did_actions']        = array();
		$GLOBALS['wp_test_doing_actions']      = array();
		$GLOBALS['wp_test_ability_categories'] = array();
		$GLOBALS['wp_test_abilities']          = array();
		$GLOBALS['wp_test_current_user_caps']  = array(
			'edit_private_posts' => true,
		);
		$GLOBALS['wp_test_posts']              = array();
		$GLOBALS['wp_test_post_meta']          = array();
		$GLOBALS['wp_test_users']              = array();
		$GLOBALS['wp_test_user_options']       = array();

		$this->plugin    = new Post_Collection_Abilities_Test_Plugin();
		$this->abilities = new Post_Collection_Abilities( $this->plugin );
	}

	public function test_registers_category_and_abilities() {
		$this->abilities->register_ability_category();
		$this->abilities->register_abilities();

		$this->assertArrayHasKey( 'post-collection', $GLOBALS['wp_test_ability_categories'] );
		$this->assertArrayHasKey( 'post-collection/list-collections', $GLOBALS['wp_test_abilities'] );
		$this->assertArrayHasKey( 'post-collection/create-collection', $GLOBALS['wp_test_abilities'] );
		$this->assertArrayHasKey( 'post-collection/update-collection', $GLOBALS['wp_test_abilities'] );
		$this->assertArrayHasKey( 'post-collection/save-url', $GLOBALS['wp_test_abilities'] );
		$this->assertArrayHasKey( 'post-collection/list-articles', $GLOBALS['wp_test_abilities'] );
		$this->assertArrayHasKey( 'post-collection/get-article', $GLOBALS['wp_test_abilities'] );
		$this->assertArrayHasKey( 'post-collection/update-article-visibility', $GLOBALS['wp_test_abilities'] );
		$this->assertArrayHasKey( 'post-collection/update-article-content', $GLOBALS['wp_test_abilities'] );
		$this->assertArrayHasKey( 'post-collection/update-note', $GLOBALS['wp_test_abilities'] );
		$this->assertTrue( $GLOBALS['wp_test_abilities']['post-collection/list-articles']['meta']['annotations']['readonly'] );
		$this->assertFalse( $GLOBALS['wp_test_abilities']['post-collection/update-note']['meta']['annotations']['destructive'] );
		$this->assertTrue( $GLOBALS['wp_test_abilities']['post-collection/update-note']['meta']['annotations']['idempotent'] );
		foreach ( array( 'get-article', 'update-article-visibility', 'update-article-content', 'update-note' ) as $ability ) {
			$schema = $GLOBALS['wp_test_abilities'][ 'post-collection/' . $ability ]['input_schema'];
			$this->assertContains( 'id', $schema['required'] );
			$this->assertArrayHasKey( 'id', $schema['properties'] );
			$this->assertArrayNotHasKey( 'article_id', $schema['properties'] );
		}
	}

	public function test_registers_ai_assistant_domain_and_instructions() {
		$domains = $this->abilities->ai_assistant_ability_domains( array() );

		$this->assertArrayHasKey( 'post-collection', $domains );
		$this->assertStringContainsString( 'article notes', $domains['post-collection'] );

		$instructions = $this->abilities->ai_assistant_ability_instructions( '', 'post-collection/save-url', array(), array() );
		$this->assertStringContainsString( 'view_url', $instructions );
	}

	public function test_list_collections_treats_string_false_as_false() {
		$active = $this->create_collection_user( 1, 'active', 'Active Collection' );
		$inactive = $this->create_collection_user( 2, 'inactive', 'Inactive Collection' );
		update_user_option( $inactive->ID, 'friends_post_collection_inactive', true );

		$this->plugin->collection_users = array( $active, $inactive );

		$result = $this->abilities->ability_list_collections(
			array(
				'include_inactive' => 'false',
			)
		);

		$this->assertSame( 1, $result['total'] );
		$this->assertSame( $active->ID, $result['collections'][0]['id'] );
	}

	public function test_update_collection_treats_string_false_as_false() {
		$collection = $this->create_collection_user( 3, 'articles', 'Articles' );
		update_user_option( $collection->ID, 'friends_publish_post_collection', true );

		$result = $this->abilities->ability_update_collection(
			array(
				'collection_id' => $collection->ID,
				'publish_feed'  => 'false',
			)
		);

		$this->assertFalse( is_wp_error( $result ) );
		$this->assertFalse( get_user_option( 'friends_publish_post_collection', $collection->ID ) );
		$this->assertFalse( $result['collection']['publish_feed'] );
	}

	public function test_update_article_content_edits_collected_article_fields() {
		$collection = $this->create_collection_user( 5, 'editing', 'Editing' );
		$this->plugin->collection_users = array( $collection );
		$article = $this->create_article( 105, $collection->ID, 'Original Title' );
		update_post_meta( $article->ID, 'url', 'https://example.com/original' );

		$result = $this->abilities->ability_update_article_content(
			array(
				'id'          => $article->ID,
				'title'       => 'Updated Title',
				'source_url'  => 'https://example.com/updated',
				'excerpt'     => 'Updated excerpt',
				'content'     => '<p>Updated body</p>',
				'tags'        => array( 'AI', 'Reading' ),
				'post_status' => 'publish',
			)
		);

		$this->assertFalse( is_wp_error( $result ) );
		$this->assertSame( 'Updated Title', $GLOBALS['wp_test_posts'][ $article->ID ]->post_title );
		$this->assertSame( 'https://example.com/updated', $GLOBALS['wp_test_posts'][ $article->ID ]->guid );
		$this->assertSame( 'https://example.com/updated', get_post_meta( $article->ID, 'url', true ) );
		$this->assertSame( 'Updated excerpt', $GLOBALS['wp_test_posts'][ $article->ID ]->post_excerpt );
		$this->assertSame( '<p>Updated body</p>', $GLOBALS['wp_test_posts'][ $article->ID ]->post_content );
		$this->assertSame( 'publish', $GLOBALS['wp_test_posts'][ $article->ID ]->post_status );
		$this->assertSame( array( 'AI', 'Reading' ), $GLOBALS['wp_test_terms'][ $article->ID ][ $this->plugin->get_tag_taxonomy() ] );
		$this->assertSame( '<p>Updated body</p>', $result['article']['content'] );
		$this->assertSame( 'https://example.com/updated', $result['article']['source_url'] );
	}

	public function test_article_abilities_accept_generic_id_alias() {
		$collection = $this->create_collection_user( 6, 'aliases', 'Aliases' );
		$this->plugin->collection_users = array( $collection );
		$article = $this->create_article( 106, $collection->ID, 'Alias Article' );

		$result = $this->abilities->ability_get_article( array( 'id' => $article->ID ) );
		$this->assertFalse( is_wp_error( $result ) );
		$this->assertSame( $article->ID, $result['article']['id'] );

		$result = $this->abilities->ability_update_article_visibility(
			array(
				'id'          => $article->ID,
				'post_status' => 'publish',
			)
		);
		$this->assertFalse( is_wp_error( $result ) );
		$this->assertSame( 'publish', $result['article']['post_status'] );

		$result = $this->abilities->ability_update_article_content(
			array(
				'id'      => $article->ID,
				'excerpt' => 'Updated through the id alias',
			)
		);
		$this->assertFalse( is_wp_error( $result ) );
		$this->assertSame( 'Updated through the id alias', $result['article']['excerpt'] );
	}

	public function test_list_articles_unread_includes_articles_without_notes() {
		$collection = $this->create_collection_user( 4, 'reading', 'Reading' );
		$this->plugin->collection_users = array( $collection );

		$without_note = $this->create_article( 101, $collection->ID, 'No Note' );
		$unread = $this->create_article( 102, $collection->ID, 'Explicit Unread' );
		$read = $this->create_article( 103, $collection->ID, 'Read' );

		$this->create_note( 201, $unread->ID, Article_Notes::STATUS_UNREAD );
		$this->create_note( 202, $read->ID, Article_Notes::STATUS_READ );

		$result = $this->abilities->ability_list_articles(
			array(
				'note_status' => Article_Notes::STATUS_UNREAD,
				'limit'       => 10,
			)
		);

		$ids = array_map(
			function ( $article ) {
				return $article['id'];
			},
			$result['articles']
		);

		$this->assertContains( $without_note->ID, $ids );
		$this->assertContains( $unread->ID, $ids );
		$this->assertNotContains( $read->ID, $ids );
		$this->assertSame( 2, $result['total'] );
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

	private function create_article( $id, $collection_id, $title ) {
		$post = new \WP_Post(
			(object) array(
				'ID'            => $id,
				'post_author'   => $collection_id,
				'post_title'    => $title,
				'post_content'  => 'Article content for ' . $title,
				'post_excerpt'  => 'Excerpt for ' . $title,
				'post_status'   => 'private',
				'post_type'     => Post_Collection::CPT,
				'post_date'     => '2026-01-01 00:00:00',
				'post_modified' => '2026-01-01 00:00:00',
				'guid'          => 'https://example.com/source/' . $id,
			)
		);

		$GLOBALS['wp_test_posts'][ $id ] = $post;

		return $post;
	}

	private function create_note( $note_id, $article_id, $status ) {
		$note = new \WP_Post(
			(object) array(
				'ID'            => $note_id,
				'post_parent'   => $article_id,
				'post_content'  => 'Note ' . $note_id,
				'post_status'   => 'publish',
				'post_type'     => Article_Notes::POST_TYPE,
				'post_date'     => '2026-01-01 00:00:00',
				'post_modified' => '2026-01-01 00:00:00',
			)
		);

		$GLOBALS['wp_test_posts'][ $note_id ] = $note;
		update_post_meta( $article_id, Article_Notes::NOTE_ID_META, $note_id );
		update_post_meta( $note_id, Article_Notes::STATUS_META, $status );

		return $note;
	}
}
