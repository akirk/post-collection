<?php

use PHPUnit\Framework\TestCase;
use PostCollection\Post_Collection;

require_once __DIR__ . '/../class-post-collection.php';

class Post_Collection_Import_Test_Plugin extends Post_Collection {
	public function __construct() {}
}

class Test_Import_Urls extends TestCase {
	public function test_parse_import_urls_accepts_plain_lists_and_bookmark_exports() {
		$plugin = new Post_Collection_Import_Test_Plugin();
		$raw    = '<DT><A HREF="https://example.com/one?x=1&amp;y=2">One</A>'
			. "\nhttps://example.com/two\n"
			. 'Saved for later: https://example.com/three).'
			. "\nhttps://example.com/two";

		$this->assertSame(
			array(
				'https://example.com/one?x=1&y=2',
				'https://example.com/two',
				'https://example.com/three',
			),
			$plugin->parse_import_urls( $raw )
		);
	}

	public function test_parse_import_urls_ignores_invalid_and_non_http_links() {
		$plugin = new Post_Collection_Import_Test_Plugin();
		$raw    = '<A HREF="javascript:alert(1)">Bad</A>'
			. "\nftp://example.com/file"
			. "\nhttps://example.com/good;";

		$this->assertSame(
			array( 'https://example.com/good' ),
			$plugin->parse_import_urls( $raw )
		);
	}

	public function test_parse_import_items_accepts_pocket_style_csv() {
		$plugin = new Post_Collection_Import_Test_Plugin();
		$csv    = "given_title,given_url,tags,status\n"
			. "\"First Article\",https://example.com/first,\"ai, reading\",unread\n"
			. "\"Second Article\",https://example.com/second,,archive\n";

		$this->assertSame(
			array(
				array(
					'url'   => 'https://example.com/first',
					'title' => 'First Article',
					'tags'  => array( 'ai', 'reading' ),
				),
				array(
					'url'   => 'https://example.com/second',
					'title' => 'Second Article',
					'tags'  => array(),
				),
			),
			$plugin->parse_import_items( $csv, 'pocket.csv' )
		);
	}

	public function test_parse_import_items_accepts_rss_and_atom_files() {
		$plugin = new Post_Collection_Import_Test_Plugin();
		$rss    = '<?xml version="1.0"?><rss><channel><item><title>RSS Item</title><link>https://example.com/rss</link><category>news</category></item></channel></rss>';
		$atom   = '<?xml version="1.0"?><feed><entry><title>Atom Item</title><link href="https://example.com/atom" rel="alternate"/><category term="research"/></entry></feed>';

		$this->assertSame(
			array(
				array(
					'url'   => 'https://example.com/rss',
					'title' => 'RSS Item',
					'tags'  => array( 'news' ),
				),
			),
			$plugin->parse_import_items( $rss, 'feed.rss' )
		);

		$this->assertSame(
			array(
				array(
					'url'   => 'https://example.com/atom',
					'title' => 'Atom Item',
					'tags'  => array( 'research' ),
				),
			),
			$plugin->parse_import_items( $atom, 'feed.atom' )
		);
	}

	public function test_parse_import_sources_deduplicates_across_file_and_paste_sources() {
		$plugin = new Post_Collection_Import_Test_Plugin();
		$sources = array(
			array(
				'content'  => "title,url\nOne,https://example.com/one\n",
				'filename' => 'links.csv',
			),
			array(
				'content'  => "https://example.com/one\nhttps://example.com/two",
				'filename' => 'pasted.txt',
			),
		);

		$this->assertSame(
			array(
				array(
					'url'   => 'https://example.com/one',
					'title' => 'One',
					'tags'  => array(),
				),
				array(
					'url'   => 'https://example.com/two',
					'title' => '',
					'tags'  => array(),
				),
			),
			$plugin->parse_import_sources( $sources )
		);
	}
}
