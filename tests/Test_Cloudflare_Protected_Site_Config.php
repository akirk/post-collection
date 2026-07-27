<?php

use PHPUnit\Framework\TestCase;
use PostCollection\SiteConfig\Cloudflare_Protected;

require_once __DIR__ . '/../site-configs/class-site-config.php';
require_once __DIR__ . '/../site-configs/class-jina.php';
require_once __DIR__ . '/../site-configs/class-cloudflare-protected.php';

class Test_Cloudflare_Protected_Site_Config extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['wp_test_filters']        = array();
		$GLOBALS['wp_test_http_responses'] = array();
	}

	public function test_supports_http_urls() {
		$config = new Cloudflare_Protected();

		$this->assertTrue( $config->is_url_supported( 'https://openai.com/index/introducing-openai-presence' ) );
		$this->assertTrue( $config->is_url_supported( 'http://example.com/article' ) );
		$this->assertFalse( $config->is_url_supported( 'mailto:test@example.com' ) );
	}

	public function test_detects_cloudflare_challenge_body_and_headers() {
		$config = new Cloudflare_Protected();

		$this->assertTrue( $config->is_challenge_response( '<script src="/cdn-cgi/challenge-platform/h/g/orchestrate/chl_page/v1"></script>' ) );
		$this->assertTrue(
			$config->is_challenge_response(
				'<html></html>',
				array(
					'headers' => array(
						'cf-mitigated' => 'challenge',
					),
				)
			)
		);
		$this->assertFalse( $config->is_challenge_response( '<article><p>Readable article.</p></article>' ) );
	}

	public function test_download_uses_jina_reader_markdown() {
		$url        = 'https://example.com/protected-article';
		$reader_url = 'https://r.jina.ai/http://' . $url;

		$GLOBALS['wp_test_http_responses'][ $reader_url ] = array(
			'response' => array(
				'code' => 200,
			),
			'body'     => "Title: Protected Article\n\nURL Source: $url\n\nMarkdown Content:\nFirst paragraph.\n\n## Available today\n\n![Image 1: UI screenshot](https://example.com/image.png?w=3840)\n\n* One item.\n* Another item.\n\nFinal [link](https://example.com/news).",
		);

		$item = ( new Cloudflare_Protected() )->download( $url );

		$this->assertFalse( is_wp_error( $item ) );
		$this->assertSame( 'Protected Article', $item->title );
		$this->assertStringContainsString( '<p>First paragraph.</p>', $item->content );
		$this->assertStringContainsString( '<h2>Available today</h2>', $item->content );
		$this->assertStringContainsString( '<img src="https://example.com/image.png?w=3840" alt="Image 1: UI screenshot">', $item->content );
		$this->assertStringContainsString( '<ul><li>One item.</li><li>Another item.</li></ul>', $item->content );
		$this->assertStringContainsString( '<a href="https://example.com/news">link</a>', $item->content );
	}
}
