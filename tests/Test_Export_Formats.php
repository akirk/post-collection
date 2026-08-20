<?php

use PHPUnit\Framework\TestCase;
use PostCollection\Post_Collection;

require_once __DIR__ . '/../class-post-collection.php';

class Post_Collection_Export_Test_Plugin extends Post_Collection {
	public function __construct() {}
}

class Test_Export_Formats extends TestCase {
	private function get_items() {
		return array(
			array(
				'url'         => 'https://example.com/one?a=1&b=2',
				'title'       => 'One & Two',
				'tags'        => array( 'ai', 'reading' ),
				'created'     => 1700000000,
				'modified'    => 1700000500,
				'description' => "Worth a <b>second</b> look\nlater",
				'unread'      => true,
			),
			array(
				'url'   => 'https://example.com/two',
				'title' => '',
			),
		);
	}

	public function test_bookmarks_html_export_round_trips_through_import() {
		$plugin = new Post_Collection_Export_Test_Plugin();
		$html   = $plugin->build_bookmarks_html_export( array( array( 'title' => 'My "Collection"', 'items' => $this->get_items() ) ) );

		$this->assertStringStartsWith( '<!DOCTYPE NETSCAPE-Bookmark-file-1>', $html );
		$this->assertStringContainsString( '<H3 ADD_DATE=', $html );
		$this->assertStringContainsString( '<DT><A HREF="https://example.com/one?a=1&amp;b=2" ADD_DATE="1700000000" LAST_MODIFIED="1700000500" TAGS="ai,reading" TOREAD="1">One &amp; Two</A>', $html );
		$this->assertStringContainsString( '<DD>Worth a second look', $html );
		$this->assertStringContainsString( '<DT><A HREF="https://example.com/two" ADD_DATE="', $html );
		$this->assertStringNotContainsString( '<b>', $html );

		$this->assertSame(
			array( 'https://example.com/one?a=1&b=2', 'https://example.com/two' ),
			$plugin->parse_import_urls( $html )
		);
	}

	public function test_opml_export_is_valid_and_round_trips_through_import() {
		$plugin = new Post_Collection_Export_Test_Plugin();
		$opml   = $plugin->build_opml_export( array( array( 'title' => 'My "Collection"', 'items' => $this->get_items() ) ), 'My "Collection"', 'Owner' );

		$xml = simplexml_load_string( $opml );
		$this->assertNotFalse( $xml );
		$this->assertSame( '2.0', (string) $xml['version'] );
		$this->assertSame( 'My "Collection"', (string) $xml->head->title );
		$this->assertSame( 'Owner', (string) $xml->head->ownerName );

		$outlines = $xml->body->outline->outline;
		$this->assertCount( 2, $outlines );
		$this->assertSame( 'link', (string) $outlines[0]['type'] );
		$this->assertSame( 'One & Two', (string) $outlines[0]['text'] );
		$this->assertSame( 'https://example.com/one?a=1&b=2', (string) $outlines[0]['url'] );
		$this->assertSame( 'ai,reading', (string) $outlines[0]['category'] );
		$this->assertSame( 'Tue, 14 Nov 2023 22:13:20 +0000', (string) $outlines[0]['created'] );
		$this->assertSame( 'https://example.com/two', (string) $outlines[1]['text'] );

		$this->assertSame(
			array(
				array(
					'url'   => 'https://example.com/one?a=1&b=2',
					'title' => 'One & Two',
					'tags'  => array( 'ai', 'reading' ),
				),
				array(
					'url'   => 'https://example.com/two',
					'title' => 'https://example.com/two',
					'tags'  => array(),
				),
			),
			$plugin->parse_import_items( $opml, 'collection.opml' )
		);
	}

	public function test_parse_import_items_reads_nested_feed_opml() {
		$plugin = new Post_Collection_Export_Test_Plugin();
		$opml   = '<?xml version="1.0"?><opml version="2.0"><head><title>Subscriptions</title></head><body>'
			. '<outline text="Tech"><outline text="A Blog" type="rss" xmlUrl="https://example.com/feed" htmlUrl="https://example.com/" category="/Tech/Blogs,news"/></outline>'
			. '<outline text="Feed only" type="rss" xmlUrl="https://example.org/feed.xml"/>'
			. '<outline text="Empty folder"/>'
			. '</body></opml>';

		$this->assertSame(
			array(
				array(
					'url'   => 'https://example.com/',
					'title' => 'A Blog',
					'tags'  => array( 'Tech/Blogs', 'news' ),
				),
				array(
					'url'   => 'https://example.org/feed.xml',
					'title' => 'Feed only',
					'tags'  => array(),
				),
			),
			$plugin->parse_import_items( $opml, 'subscriptions.opml' )
		);
	}

	public function test_exports_put_each_group_in_its_own_folder() {
		$plugin = new Post_Collection_Export_Test_Plugin();
		$groups = array(
			array( 'title' => 'First', 'items' => array( array( 'url' => 'https://example.com/a' ) ) ),
			array( 'title' => 'Second', 'items' => array( array( 'url' => 'https://example.com/b' ), array( 'url' => 'https://example.com/c' ) ) ),
		);

		$html = $plugin->build_bookmarks_html_export( $groups );
		$this->assertSame( 2, preg_match_all( '/<H3 [^>]*>(First|Second)<\/H3>/', $html ) );
		$this->assertSame( 3, substr_count( $html, '<DT><A HREF=' ) );
		$this->assertSame( array( 'https://example.com/a', 'https://example.com/b', 'https://example.com/c' ), $plugin->parse_import_urls( $html ) );

		$xml = simplexml_load_string( $plugin->build_opml_export( $groups, 'Site' ) );
		$this->assertCount( 2, $xml->body->outline );
		$this->assertSame( 'Second', (string) $xml->body->outline[1]['text'] );
		$this->assertCount( 2, $xml->body->outline[1]->outline );
		$this->assertCount( 3, $plugin->parse_import_items( $xml->asXML(), 'all.opml' ) );
	}
}
