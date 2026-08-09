<?php
/**
 * Jina reader fallback for Cloudflare-protected article downloads.
 *
 * This uses r.jina.ai to retrieve a markdown representation of a URL when
 * WordPress cannot fetch the original page because the response is a
 * Cloudflare challenge. It is not used for search, embeddings, or reranking.
 *
 * @package Post_Collection
 */
namespace PostCollection\SiteConfig;

defined( 'ABSPATH' ) || exit;

use PostCollection\ExtractedPage;

abstract class Jina extends SiteConfig {
	public function download( $url ) {
		$item       = new ExtractedPage( $url );
		$reader_url = $this->get_reader_url( $url );

		if ( ! $reader_url ) {
			return new \WP_Error(
				'jina-reader-disabled',
				__( 'Jina reader is disabled.', 'post-collection' ),
				array(
					'status' => 502,
					'url'    => $url,
				)
			);
		}

		$response = wp_remote_get(
			$reader_url,
			array(
				'timeout'     => 30,
				'redirection' => 5,
				'headers'     => array(
					'user-agent' => 'WordPress; Post Collection Jina reader',
					'accept'     => 'text/markdown,text/plain;q=0.9,*/*;q=0.1',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $status ) {
			return new \WP_Error(
				'jina-reader-error',
				__( 'Could not download the article through Jina reader.', 'post-collection' ),
				array(
					'status'      => 502,
					'url'         => $url,
					'reader_url'  => $reader_url,
					'http_status' => $status,
					'http_body'   => wp_remote_retrieve_body( $response ),
				)
			);
		}

		$markdown = trim( wp_remote_retrieve_body( $response ) );
		$title    = $this->extract_title( $markdown );
		$content  = $this->extract_markdown_content( $markdown );

		$item->title    = $title;
		$item->content  = $this->markdown_to_html( $content );
		$item->raw_html = $markdown;

		return $item;
	}

	protected function get_reader_url( $url ) {
		$reader_url = apply_filters(
			$this->get_reader_url_filter_name(),
			'https://r.jina.ai/http://%s',
			$url
		);

		if ( ! $reader_url ) {
			return '';
		}

		return false === strpos( $reader_url, '%s' ) ? $reader_url : sprintf( $reader_url, $url );
	}

	protected function get_reader_url_filter_name() {
		return 'post_collection_jina_reader_url';
	}

	private function extract_title( $markdown ) {
		if ( preg_match( '/^Title:\s*(.+)$/mi', $markdown, $matches ) ) {
			return trim( $matches[1] );
		}

		if ( preg_match( '/^#\s+(.+)$/m', $markdown, $matches ) ) {
			return trim( $matches[1] );
		}

		return '';
	}

	private function extract_markdown_content( $markdown ) {
		$parts = preg_split( '/^Markdown Content:\s*$/mi', $markdown, 2 );
		return trim( 2 === count( $parts ) ? $parts[1] : $markdown );
	}

	private function markdown_to_html( $markdown ) {
		$blocks     = preg_split( "/\n{2,}/", trim( $markdown ) );
		$html       = array();
		$list_items = array();

		foreach ( $blocks as $block ) {
			$block = trim( $block );
			if ( '' === $block ) {
				continue;
			}

			if ( preg_match( '/^\*\s+(.+)$/m', $block ) ) {
				foreach ( preg_split( "/\n+/", $block ) as $line ) {
					if ( preg_match( '/^\*\s+(.+)$/', trim( $line ), $matches ) ) {
						$list_items[] = '<li>' . $this->format_inline_markdown( $matches[1] ) . '</li>';
					}
				}
				continue;
			}

			if ( $list_items ) {
				$html[]     = '<ul>' . implode( '', $list_items ) . '</ul>';
				$list_items = array();
			}

			if ( preg_match( '/^(#{2,6})\s+(.+)$/', $block, $matches ) ) {
				$level  = strlen( $matches[1] );
				$html[] = '<h' . $level . '>' . $this->format_inline_markdown( $matches[2] ) . '</h' . $level . '>';
				continue;
			}

			if ( preg_match( '/^!\[([^\]]*)\]\(([^)]+)\)$/', $block, $matches ) ) {
				$html[] = '<figure><img src="' . esc_url( $matches[2] ) . '" alt="' . esc_attr( $matches[1] ) . '"></figure>';
				continue;
			}

			$html[] = '<p>' . $this->format_inline_markdown( preg_replace( "/\n+/", ' ', $block ) ) . '</p>';
		}

		if ( $list_items ) {
			$html[] = '<ul>' . implode( '', $list_items ) . '</ul>';
		}

		return implode( "\n", $html );
	}

	private function format_inline_markdown( $text ) {
		$text = esc_html( $text );

		return preg_replace_callback(
			'/\[([^\]]+)\]\((https?:\/\/[^)]+)\)/',
			function ( $matches ) {
				return '<a href="' . esc_url( html_entity_decode( $matches[2], ENT_QUOTES, 'UTF-8' ) ) . '">' . esc_html( html_entity_decode( $matches[1], ENT_QUOTES, 'UTF-8' ) ) . '</a>';
			},
			$text
		);
	}
}
