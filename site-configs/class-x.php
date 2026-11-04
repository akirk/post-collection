<?php
/**
 * Extract rendered X posts supplied by the browser extension.
 *
 * @package Post_Collection
 */
namespace PostCollection\SiteConfig;

defined( 'ABSPATH' ) || exit;

use PostCollection\ExtractedPage;

class X extends SiteConfig {
	public function is_url_supported( $url ) {
		return in_array( strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) ), array( 'x.com', 'www.x.com', 'twitter.com', 'www.twitter.com' ), true )
			&& preg_match( '~/[^/]+/status/[0-9]+~', (string) wp_parse_url( $url, PHP_URL_PATH ) );
	}

	public function download( $url, $content = null ) {
		if ( ! is_string( $content ) || ! $this->is_url_supported( $url ) ) {
			return new \WP_Error( 'x-rendered-post-unavailable', 'No rendered X post available.' );
		}

		$previous_errors = libxml_use_internal_errors( true );
		$dom = new \DOMDocument();
		$loaded = $dom->loadHTML( '<?xml encoding="UTF-8">' . $content, LIBXML_NOWARNING | LIBXML_NOERROR );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous_errors );
		if ( ! $loaded ) {
			return new \WP_Error( 'x-rendered-post-unavailable', 'Could not parse the rendered X post.' );
		}

		$xpath = new \DOMXPath( $dom );
		$path = rtrim( wp_parse_url( $url, PHP_URL_PATH ), '/' );
		foreach ( $xpath->query( '//article[@data-testid="tweet"]' ) as $article ) {
			foreach ( $xpath->query( './/a[time]', $article ) as $link ) {
				if ( rtrim( (string) wp_parse_url( $link->getAttribute( 'href' ), PHP_URL_PATH ), '/' ) !== $path ) {
					continue;
				}

				$texts = $xpath->query( './/*[@data-testid="tweetText"]', $article );
				if ( ! $texts->length ) {
					continue;
				}
				$item = new ExtractedPage( $url );
				$page_title = $xpath->query( '//title' )->item( 0 );
				$item->original_title = $page_title ? wp_strip_all_tags( trim( sanitize_text_field( $page_title->textContent ) ) ) : '';
				$name = $xpath->query( './/*[@data-testid="User-Name"]//a', $article )->item( 0 );
				$item->author = $name ? trim( $name->textContent ) : '';
				$item->title = $item->author ? $item->author . ' on X' : 'Post on X';
				$item->published_time = $xpath->query( './time', $link )->item( 0 )->getAttribute( 'datetime' );
				$item->raw_html = $content;
				$item->content = '';
				foreach ( $texts as $index => $text ) {
					// Keep inline formatting, but discard X's layout and clamping styles.
					foreach ( $xpath->query( './/a[starts-with(@href, "/")]', $text ) as $anchor ) {
						$anchor->setAttribute( 'href', 'https://x.com' . $anchor->getAttribute( 'href' ) );
					}
					$html = strip_tags( $dom->saveHTML( $text ), '<a><br><img><strong><em><code>' );
					$paragraphs = preg_split( '/\n\s*\n/', trim( $html ) );
					$html = '<p>' . implode( '</p><p>', array_map( 'nl2br', $paragraphs ) ) . '</p>';
					$item->content .= $index ? '<blockquote>' . $html . '</blockquote>' : $html;
				}
				foreach ( $xpath->query( './/*[@data-testid="tweetPhoto"]//img', $article ) as $image ) {
					$item->content .= '<figure>' . $dom->saveHTML( $image ) . '</figure>';
				}
				return $item;
			}
		}

		return new \WP_Error( 'x-rendered-post-unavailable', 'The requested X post was not found in the rendered page.' );
	}
}
