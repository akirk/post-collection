<?php

use PHPUnit\Framework\TestCase;
use PostCollection\SiteConfig\X;

require_once __DIR__ . '/../site-configs/class-x.php';

class Test_X_Site_Config extends TestCase {
	public function test_selects_requested_post_and_preserves_paragraphs_quotes_and_media() {
		$config = new X();
		$html = '<html><head><title>Writer on X: &quot;Long post&quot; / X</title></head><body><article data-testid="tweet"><a href="/other/status/1"><time datetime="2026-01-01">Today</time></a><div data-testid="tweetText">Unrelated reply</div></article>'
			. '<article data-testid="tweet"><div data-testid="User-Name"><a href="/writer">Writer</a><a href="/writer">@writer</a></div>'
			. '<img src="https://example.com/avatar.jpg"><button>Share</button>'
			. '<div data-testid="tweetText"><span>First paragraph.</span>' . "\n\n" . '<span>Second paragraph with <a href="https://example.com/original">a link</a> and <a href="/someone">a mention</a>.</span></div>'
			. '<div data-testid="tweetText">Quoted post.</div><div data-testid="tweetPhoto"><img src="https://example.com/photo.jpg" alt="Photo"></div>'
			. '<a href="/writer/status/2"><time datetime="2026-11-03T17:07:28.000Z">Today</time></a><button>123 likes</button></article></body></html>';

		$item = $config->download( 'https://x.com/writer/status/2?s=20', $html );

		$this->assertFalse( is_wp_error( $item ) );
		$this->assertSame( 'Writer on X', $item->title );
		$this->assertSame( 'Writer on X: "Long post" / X', $item->original_title );
		$this->assertSame( 'Writer', $item->author );
		$this->assertSame( '2026-11-03T17:07:28.000Z', $item->published_time );
		$this->assertStringContainsString( '<p>First paragraph.</p><p>Second paragraph', $item->content );
		$this->assertStringContainsString( 'href="https://example.com/original"', $item->content );
		$this->assertStringContainsString( 'href="https://x.com/someone"', $item->content );
		$this->assertStringContainsString( '<blockquote><p>Quoted post.</p></blockquote>', $item->content );
		$this->assertStringContainsString( 'https://example.com/photo.jpg', $item->content );
		foreach ( array( 'Unrelated reply', 'avatar.jpg', 'Share', '123 likes' ) as $noise ) {
			$this->assertStringNotContainsString( $noise, $item->content );
		}
	}

	public function test_missing_rendered_post_allows_default_extraction_to_continue() {
		$config = new X();
		$this->assertTrue( $config->is_url_supported( 'https://twitter.com/writer/status/2' ) );
		$this->assertFalse( $config->is_url_supported( 'https://example.com/writer/status/2' ) );
		$this->assertFalse( $config->is_url_supported( 'https://x.com/writer' ) );
		$this->assertTrue( is_wp_error( $config->download( 'https://x.com/writer/status/2' ) ) );
		$this->assertTrue( is_wp_error( $config->download( 'https://x.com/writer/status/2', '<html><body>Login required</body></html>' ) ) );
	}
}
