<?php

use PHPUnit\Framework\TestCase;
use PostCollection\ExtractedPage;
use PostCollection\Post_Collection;
use PostCollection\SiteConfig\Archive_Is;

require_once __DIR__ . '/../class-post-collection.php';

if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
	function wp_ai_client_prompt( $prompt ) {
		$GLOBALS['post_collection_test_ai_prompt'] = $prompt;
		if ( empty( $GLOBALS['post_collection_test_ai_response'] ) ) {
			return new \WP_Error( 'ai-unavailable', 'AI unavailable.' );
		}

		return new Post_Collection_Browser_Extension_Test_AI_Builder( $GLOBALS['post_collection_test_ai_response'] );
	}
}

class Post_Collection_Browser_Extension_Test_AI_Builder {
	private $response;

	public function __construct( $response ) {
		$this->response = $response;
	}

	public function using_system_instruction( $instruction ) {
		return $this;
	}

	public function using_max_tokens( $max_tokens ) {
		return $this;
	}

	public function generate_text() {
		return $this->response;
	}
}

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
		$GLOBALS['post_collection_test_ai_response'] = null;
		$GLOBALS['post_collection_test_ai_prompt']   = null;

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
		$this->assertNull( $GLOBALS['post_collection_test_ai_prompt'] );
		$this->assertSame( 'Generate tags', $result['followup_action']['name'] );
		$this->assertSame( 'post_collection_generate_tags', $result['followup_action']['fields']['action'] );
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

	public function test_generate_tags_action_reports_suggested_tags_without_saving_them() {
		$collection = $this->create_collection_term( 4, 'ai', 'AI Articles' );
		$this->create_tag_term( 400, '8217', '8217' );
		$this->create_tag_term( 401, 'Technology', 'technology' );
		$this->create_tag_term( 402, 'Immobilien', 'immobilien' );
		$post       = new \WP_Post(
			(object) array(
				'ID'           => 44,
				'post_author'  => 99,
				'post_title'   => 'Erbbaurecht: Sieht aus wie ein Immo-Schnäppchen. Ist es aber (oft) nicht',
				'post_content' => '<p>Beim Erbbaurecht kaufen Familien ein Haus, aber nicht das Grundstück. Der Erbbauzins und kurze Laufzeiten können Immobilienkäufer finanziell stark belasten.</p>',
				'post_status'  => 'private',
				'post_type'    => Post_Collection::CPT,
				'guid'         => 'https://www.zeit.de/geld/2026-07/erbbaurecht-eigentum-haus-grundstueck-immobilienmarkt-kaeufer-gxe',
			)
		);

		$GLOBALS['wp_test_posts'][ $post->ID ] = $post;
		$GLOBALS['wp_test_terms'][ $post->ID ][ Post_Collection::COLLECTION_TAXONOMY ] = array( $collection->term_id );
		$GLOBALS['post_collection_test_ai_response'] = '{"tags":["Germany","history","Technology","Immobilien","Erbbaurecht"]}';

		$result = $this->plugin->friends_browser_extension_action_generate_tags(
			null,
			$this->create_request(
				$collection->term_id,
				array(
					'url'   => 'https://www.zeit.de/geld/2026-07/erbbaurecht-eigentum-haus-grundstueck-immobilienmarkt-kaeufer-gxe',
					'title' => 'Erbbaurecht: Sieht aus wie ein Immo-Schnäppchen. Ist es aber (oft) nicht',
				)
			),
			new \WP_User( 99 ),
			array()
		);

		$this->assertFalse( is_wp_error( $result ) );
		$this->assertSame( 'Erbbaurecht: Sieht aus wie ein Immo-Schnäppchen. Ist es aber (oft) nicht', $result['title'] );
		$this->assertSame( array( 'Immobilien', 'Erbbaurecht' ), $result['tags'] );
		$this->assertSame( 'Immobilien, Erbbaurecht', $result['values']['tags'] );
		$this->assertSame( 'Update Tags', $result['submit_label'] );
		$this->assertSame( (string) $post->ID, $result['fields']['post_id'] );
		$this->assertSame( '1', $result['fields']['apply_generated_tags'] );
		$this->assertArrayNotHasKey( $this->plugin->get_tag_taxonomy(), $GLOBALS['wp_test_terms'][ $post->ID ] );
		$this->assertStringContainsString( 'Existing tag vocabulary: Immobilien', $GLOBALS['post_collection_test_ai_prompt'] );
		$this->assertStringNotContainsString( '8217', $GLOBALS['post_collection_test_ai_prompt'] );
		$this->assertStringContainsString( 'Tag language: determine the article language from the title and article text', $GLOBALS['post_collection_test_ai_prompt'] );
		$this->assertStringNotContainsString( 'good tags include Immobilien', $GLOBALS['post_collection_test_ai_prompt'] );
	}

	public function test_generate_tags_prompt_uses_article_language_by_default() {
		$collection = $this->create_collection_term( 8, 'essays', 'Essays' );
		$post       = new \WP_Post(
			(object) array(
				'ID'           => 81,
				'post_author'  => 99,
				'post_title'   => 'Do Not Write With an LLM',
				'post_content' => '<p>You should write with your own mind. Writing is how people discover what they think about work, tools, and responsibility.</p>',
				'post_status'  => 'private',
				'post_type'    => Post_Collection::CPT,
				'guid'         => 'https://harperp2.wordpress.com/2026/02/27/do-not-write-with-an-llm/',
			)
		);

		$GLOBALS['wp_test_posts'][ $post->ID ] = $post;
		$GLOBALS['wp_test_terms'][ $post->ID ][ Post_Collection::COLLECTION_TAXONOMY ] = array( $collection->term_id );
		$GLOBALS['post_collection_test_ai_response'] = '{"tags":["writing","ai","education"]}';

		$result = $this->plugin->friends_browser_extension_action_generate_tags(
			null,
			$this->create_request(
				$collection->term_id,
				array(
					'url'   => 'https://harperp2.wordpress.com/2026/02/27/do-not-write-with-an-llm/',
					'title' => 'Do Not Write With an LLM',
				)
			),
			new \WP_User( 99 ),
			array()
		);

		$this->assertFalse( is_wp_error( $result ) );
		$this->assertSame( array( 'writing', 'ai', 'education' ), $result['tags'] );
		$this->assertStringContainsString( 'Tag language: determine the article language from the title and article text', $GLOBALS['post_collection_test_ai_prompt'] );
		$this->assertStringNotContainsString( 'Erbbaurecht:', $GLOBALS['post_collection_test_ai_prompt'] );
	}

	public function test_generate_tags_prompt_uses_collection_language_and_style_settings() {
		$collection = $this->create_collection_term( 9, 'prompt-settings', 'Prompt Settings' );
		update_term_meta( $collection->term_id, Post_Collection::AI_TAG_LANGUAGE_META, 'german' );
		update_term_meta( $collection->term_id, Post_Collection::AI_TAG_STYLE_META, 'snake_case' );
		$post       = new \WP_Post(
			(object) array(
				'ID'           => 91,
				'post_author'  => 99,
				'post_title'   => 'Do Not Write With an LLM',
				'post_content' => '<p>You should write with your own mind. Writing is how people discover what they think about work and responsibility.</p>',
				'post_status'  => 'private',
				'post_type'    => Post_Collection::CPT,
				'guid'         => 'https://harperp2.wordpress.com/2026/02/27/do-not-write-with-an-llm/',
			)
		);

		$GLOBALS['wp_test_posts'][ $post->ID ] = $post;
		$GLOBALS['wp_test_terms'][ $post->ID ][ Post_Collection::COLLECTION_TAXONOMY ] = array( $collection->term_id );
		$GLOBALS['post_collection_test_ai_response'] = '{"tags":["schreibwerkzeuge","kuenstliche_intelligenz"]}';

		$result = $this->plugin->friends_browser_extension_action_generate_tags(
			null,
			$this->create_request(
				$collection->term_id,
				array(
					'url'   => 'https://harperp2.wordpress.com/2026/02/27/do-not-write-with-an-llm/',
					'title' => 'Do Not Write With an LLM',
				)
			),
			new \WP_User( 99 ),
			array()
		);

		$this->assertFalse( is_wp_error( $result ) );
		$this->assertSame( array( 'schreibwerkzeuge', 'kuenstliche_intelligenz' ), $result['tags'] );
		$this->assertStringContainsString( 'Tag language: write every tag in German', $GLOBALS['post_collection_test_ai_prompt'] );
		$this->assertStringContainsString( 'Tag style: snake_case.', $GLOBALS['post_collection_test_ai_prompt'] );
	}

	public function test_generate_tags_action_applies_reviewed_tags_on_second_submit() {
		$collection = $this->create_collection_term( 6, 'ai-updates', 'AI Updates' );
		$post       = new \WP_Post(
			(object) array(
				'ID'           => 61,
				'post_author'  => 99,
				'post_title'   => 'Old Title',
				'post_content' => '<p>Already saved words here.</p>',
				'post_status'  => 'private',
				'post_type'    => Post_Collection::CPT,
				'guid'         => 'https://source.example/generated-update',
			)
		);

		$GLOBALS['wp_test_posts'][ $post->ID ] = $post;
		$GLOBALS['wp_test_terms'][ $post->ID ][ Post_Collection::COLLECTION_TAXONOMY ] = array( $collection->term_id );

		$result = $this->plugin->friends_browser_extension_action_generate_tags(
			null,
			$this->create_request(
				$collection->term_id,
				array(
					'post_id'              => $post->ID,
					'apply_generated_tags' => '1',
					'title'                => 'Reviewed Title',
					'tags'                 => 'first tag, second tag',
				)
			),
			new \WP_User( 99 ),
			array()
		);

		$this->assertFalse( is_wp_error( $result ) );
		$this->assertSame( 'Reviewed Title', $GLOBALS['wp_test_posts'][ $post->ID ]->post_title );
		$this->assertSame( array( 'first tag', 'second tag' ), $GLOBALS['wp_test_terms'][ $post->ID ][ $this->plugin->get_tag_taxonomy() ] );
		$this->assertSame( 'Reviewed Title', $result['values']['title'] );
		$this->assertSame( 'first tag, second tag', $result['values']['tags'] );
		$this->assertSame( 'Update', $result['submit_label'] );
	}

	public function test_save_response_reports_submitted_title_and_tags_without_generating_them() {
		$collection = $this->create_collection_term( 7, 'manual-tags', 'Manual Tags' );
		$this->plugin->download_item = new ExtractedPage(
			'https://source.example/ai-post',
			'Original Page Title',
			'<article><p>Local models classify saved reading material into useful topics.</p></article>'
		);
		$GLOBALS['post_collection_test_ai_response'] = '{"tags":["local ai","reading tools","classification"]}';

		$result = $this->plugin->friends_browser_extension_action_save(
			null,
			$this->create_request(
				$collection->term_id,
				array(
					'url'   => 'https://source.example/ai-post',
					'title' => 'Browser Title',
					'tags'  => 'manual tag',
				)
			),
			new \WP_User( 99 ),
			array()
		);

		$this->assertFalse( is_wp_error( $result ) );
		$this->assertSame( 'Browser Title', $result['title'] );
		$this->assertSame( array( 'manual tag' ), $result['tags'] );
		$this->assertSame( 'Browser Title', $result['values']['title'] );
		$this->assertSame( 'manual tag', $result['values']['tags'] );
		$this->assertSame( 'Update', $result['submit_label'] );
		$this->assertSame( (string) $result['post_id'], $result['fields']['post_id'] );
		$this->assertNull( $GLOBALS['post_collection_test_ai_prompt'] );
	}

	public function test_saved_post_can_be_updated_from_browser_extension_response_state() {
		$collection = $this->create_collection_term( 5, 'updates', 'Updates' );
		$post       = new \WP_Post(
			(object) array(
				'ID'           => 51,
				'post_author'  => 99,
				'post_title'   => 'Old Title',
				'post_content' => '<p>Already saved words here.</p>',
				'post_status'  => 'private',
				'post_type'    => Post_Collection::CPT,
				'guid'         => 'https://source.example/update',
			)
		);

		$GLOBALS['wp_test_posts'][ $post->ID ] = $post;
		$GLOBALS['wp_test_terms'][ $post->ID ][ Post_Collection::COLLECTION_TAXONOMY ] = array( $collection->term_id );

		$result = $this->plugin->friends_browser_extension_action_save(
			null,
			$this->create_request(
				$collection->term_id,
				array(
					'post_id' => $post->ID,
					'title'   => 'Updated Title',
					'tags'    => 'first tag, second tag',
				)
			),
			new \WP_User( 99 ),
			array()
		);

		$this->assertFalse( is_wp_error( $result ) );
		$this->assertSame( 'Updated Title', $GLOBALS['wp_test_posts'][ $post->ID ]->post_title );
		$this->assertSame( array( 'first tag', 'second tag' ), $GLOBALS['wp_test_terms'][ $post->ID ][ $this->plugin->get_tag_taxonomy() ] );
		$this->assertSame( 'Updated Title', $result['values']['title'] );
		$this->assertSame( 'first tag, second tag', $result['values']['tags'] );
		$this->assertSame( 'Update', $result['submit_label'] );
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

	private function create_tag_term( $id, $name, $slug ) {
		$term = new \WP_Term(
			(object) array(
				'term_id'  => $id,
				'name'     => $name,
				'slug'     => $slug,
				'taxonomy' => $this->plugin->get_tag_taxonomy(),
			)
		);

		$GLOBALS['wp_test_registered_terms'][ $this->plugin->get_tag_taxonomy() ][ $id ] = $term;

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
