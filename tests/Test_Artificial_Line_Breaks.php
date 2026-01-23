<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../class-post-collection.php';

class Test_Artificial_Line_Breaks extends TestCase {

	private $post_collection;

	public function setUp(): void {
		$this->post_collection = new PostCollection\Post_Collection();
	}

	public function test_removes_artificial_line_breaks_from_goatcounter_content() {
		$input = '<!-- wp:paragraph -->
<p>Last year I was working on a product idea and wanted to add some basic analytics<br>to measure how many people are visiting the site. I\'ve also been wanting to add<br>basic analytics to my personal homepage/programming weblog to measure if anyone<br>is reading anything I write (and if so, what?)</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Analytics are useful to measure things like <em>"what type of content is popular,<br>and should I write more of?"</em>, <em>"does it even make sense to distribute a<br>newsletter?"</em>, <em>"how does the redesigned signup button affect signup rates?"</em>,<br><em>"is anyone even using this page I\'m maintaining"?</em></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>I tried a number of existing solutions, and found them are either very complex<br>and designed for advanced users, or far too simplistic. In addition almost all<br>hosted solutions are priced for business users (≥$10/month), making it too<br>expensive for personal/hobby use.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>What seems to be lacking is a "middle ground" that offers useful statistics to<br>answer business questions, without becoming a specialized marketing tool<br>requiring in-depth training to use effectively. Furthermore, some tools have<br>privacy issues (especially Google Analytics). I saw there was space for a new<br>service and ended up putting my original idea in the freezer and writing<br>GoatCounter.<sup><a href="gc">1</a></sup></p>
<!-- /wp:paragraph -->';

		$result = $this->post_collection->remove_artificial_line_breaks( $input );

		$this->assertStringNotContainsString( 'analytics<br>', $result, 'Should remove <br> in middle of sentences' );

		$this->assertStringContainsString( 'analytics to measure', $result, 'Should have space instead of <br>' );
		$this->assertStringContainsString( 'basic analytics to my personal', $result, 'Should remove line breaks' );

		$paragraph_count = substr_count( $result, '<p>' );
		$this->assertEquals( 4, $paragraph_count, 'Should maintain the same number of paragraphs' );
	}

	public function test_preserves_intentional_line_breaks() {
		$input = '<p>First line.<br>Second line after period.<br>Third line.</p>';

		$result = $this->post_collection->remove_artificial_line_breaks( $input );

		$this->assertStringContainsString( 'line.<br>Second', $result, 'Should preserve <br> after sentence endings' );
	}

	public function test_does_nothing_when_no_br_tags() {
		$input = '<p>This is a paragraph without any line breaks at all.</p>';

		$result = $this->post_collection->remove_artificial_line_breaks( $input );

		$this->assertEquals( $input, $result, 'Should not modify content without <br> tags' );
	}

	public function test_preserves_single_br_tag() {
		$input = '<p>First part<br>Second part</p>';

		$result = $this->post_collection->remove_artificial_line_breaks( $input );

		$this->assertEquals( $input, $result, 'Should not modify paragraphs with only one <br> tag' );
	}

	public function test_handles_list_items_with_artificial_breaks() {
		$input = '<!-- wp:list -->
<ul class="wp-block-list"><!-- wp:list-item -->
<li>Give useful data while respecting people\'s privacy. For the most part, it<br>should just "count events" rather than "get as much data as technically<br>possible" (which, for the most part, is not even that useful or valuable for<br>analytics anyway).</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>There should always be an option to add GoatCounter to your site <em>without</em><br>requiring a GDPR consent notice.</li>
<!-- /wp:list-item --></ul>
<!-- /wp:list -->';

		$result = $this->post_collection->remove_artificial_line_breaks( $input );

		$this->assertStringNotContainsString( 'it<br>should', $result, 'Should remove <br> in list items' );
		$this->assertStringContainsString( 'it should', $result, 'Should have space instead of <br>' );
	}

	public function test_preserves_varied_length_lines() {
		$input = '<p>Short line<br>This is a much longer line that breaks the pattern<br>Another short<br>Medium length line here</p>';

		$result = $this->post_collection->remove_artificial_line_breaks( $input );

		$this->assertStringContainsString( '<br>', $result, 'Should preserve <br> when line lengths vary significantly' );
	}

	public function test_handles_multiple_paragraphs() {
		$input = '<p>First paragraph with<br>artificial breaks<br>here in text.</p><p>Second paragraph also<br>has artificial line<br>breaks in text.</p>';

		$result = $this->post_collection->remove_artificial_line_breaks( $input );

		$this->assertStringNotContainsString( 'with<br>artificial', $result, 'Should remove artificial breaks from multiple paragraphs' );
		$this->assertStringContainsString( 'with artificial breaks here', $result, 'Should have spaces instead' );
	}
}
