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
}
