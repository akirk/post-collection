<?php

use PHPUnit\Framework\TestCase;
use PostCollection\Post_Collection;

require_once __DIR__ . '/../class-post-collection.php';

class Post_Collection_Extract_Content_Test_Plugin extends Post_Collection {
	public function __construct() {}
}

class Test_Extract_Content extends TestCase {
	public function test_webflow_rich_text_blocks_are_used_before_script_noise() {
		$plugin = new Post_Collection_Extract_Content_Test_Plugin();
		$html   = '<!doctype html><html><head><title>Getting started with loops | Claude by Anthropic</title></head><body>'
			. '<script>const bubble = ensureBubble(); document.body.appendChild(bubble); const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);'
			. str_repeat( ' tooltip script content', 120 )
			. '</script>'
			. '<main><div class="u-rich-text-blog u-margin-trim w-richtext"><p>There is a lot of talk right now about designing loops instead of prompting your coding agent. This introduction should stay with the article.</p></div>'
			. '<div class="u-rich-text-blog u-margin-trim w-richtext"><h2>Turn-based loops</h2><p>Every prompt starts a manual loop with Claude gathering context, taking action, checking work, and responding with a useful result.</p></div></main>'
			. '</body></html>';

		$item = $plugin->extract_content( $html, 'https://claude.com/blog/getting-started-with-loops' );

		$this->assertFalse( is_wp_error( $item ) );
		$this->assertSame( 'Getting started with loops | Claude by Anthropic', $item->title );
		$this->assertStringContainsString( 'There is a lot of talk right now', wp_strip_all_tags( $item->content ) );
		$this->assertStringContainsString( 'Turn-based loops', wp_strip_all_tags( $item->content ) );
		$this->assertStringNotContainsString( 'tooltip script content', wp_strip_all_tags( $item->content ) );
	}

	public function test_download_falls_back_to_jina_for_cloudflare_challenge() {
		$plugin     = new Post_Collection_Extract_Content_Test_Plugin();
		$url        = 'https://example.com/protected-article';
		$reader_url = 'https://r.jina.ai/http://' . $url;

		$GLOBALS['wp_test_http_responses'][ $url ] = array(
			'headers'  => array(
				'cf-mitigated' => 'challenge',
			),
			'response' => array(
				'code' => 403,
			),
			'body'     => '<html><script src="/cdn-cgi/challenge-platform/h/g/orchestrate/chl_page/v1"></script></html>',
		);
		$GLOBALS['wp_test_http_responses'][ $reader_url ] = array(
			'response' => array(
				'code' => 200,
			),
			'body'     => "Title: Protected Article\n\nMarkdown Content:\nFull protected content.",
		);

		$item = $plugin->download( $url );

		$this->assertFalse( is_wp_error( $item ) );
		$this->assertSame( 'Protected Article', $item->title );
		$this->assertStringContainsString( 'Full protected content.', wp_strip_all_tags( $item->content ) );
	}

	public function test_download_does_not_fallback_to_jina_for_non_cloudflare_http_errors() {
		$plugin     = new Post_Collection_Extract_Content_Test_Plugin();
		$url        = 'https://example.com/not-found';
		$reader_url = 'https://r.jina.ai/http://' . $url;

		$GLOBALS['wp_test_http_responses'][ $url ] = array(
			'response' => array(
				'code' => 404,
			),
			'body'     => 'Not found.',
		);
		$GLOBALS['wp_test_http_responses'][ $reader_url ] = array(
			'response' => array(
				'code' => 200,
			),
			'body'     => "Title: Reader Article\n\nMarkdown Content:\nReader fallback content.",
		);

		$item = $plugin->download( $url );

		$this->assertTrue( is_wp_error( $item ) );
		$this->assertSame( 'could-not-download', $item->get_error_code() );
	}

	public function test_extract_content_reads_article_metadata_from_head_and_visible_author() {
		$plugin = new Post_Collection_Extract_Content_Test_Plugin();
		$html   = '<!doctype html><html><head>'
			. '<title>Article Metadata</title>'
			. '<meta property="article:published_time" content="2026-06-29T16:44:13+00:00">'
			. '</head><body><article>'
			. '<a class="entry-author" href="https://example.com/author/example-author/">Example Author</a>'
			. '<div class="entry-content"><p>This article has enough text for Readability to accept it as the main content. It also carries metadata outside the content body.</p>'
			. '<p>The visible author fallback should be scoped to the article rather than unrelated navigation elsewhere on the page.</p></div>'
			. '</article></body></html>';

		$item = $plugin->extract_content( $html, 'https://example.com/article-metadata' );

		$this->assertFalse( is_wp_error( $item ) );
		$this->assertSame( 'Example Author', $item->author );
		$this->assertSame( '2026-06-29T16:44:13+00:00', $item->published_time );
	}

	public function test_extract_content_reads_article_metadata_from_json_ld() {
		$plugin = new Post_Collection_Extract_Content_Test_Plugin();
		$html   = '<!doctype html><html><head>'
			. '<title>JSON-LD Metadata</title>'
			. '<script type="application/ld+json">{"@context":"https://schema.org","@type":"NewsArticle","datePublished":"2026-07-01T08:30:00+02:00","author":{"@type":"Person","name":"Ada Lovelace"}}</script>'
			. '</head><body><article><p>This article contains enough text for the extractor to find the body and should also expose JSON-LD metadata.</p>'
			. '<p>The metadata parser should handle object-shaped authors in common structured-data blocks.</p></article></body></html>';

		$item = $plugin->extract_content( $html, 'https://example.com/json-ld-metadata' );

		$this->assertFalse( is_wp_error( $item ) );
		$this->assertSame( 'Ada Lovelace', $item->author );
		$this->assertSame( '2026-07-01T06:30:00+00:00', $item->published_time );
	}
}
