<?php
namespace PostCollection\SiteConfig;

defined( 'ABSPATH' ) || exit;

class Archive_Is extends SiteConfig {

	public function is_url_supported( $url ) {
		return $this->is_archive_url( $url );
	}

	public function download( $url ) {
		return new \WP_Error( 'archive-is-download-not-supported', __( 'Archive.is URLs are handled by the default downloader.', 'post-collection' ) );
	}

	public function get_source_url( $url, $content = null ) {
		if ( ! $this->is_archive_url( $url ) || '' === (string) $content ) {
			return '';
		}

		$candidates = array_merge(
			$this->get_link_candidates( $content ),
			$this->get_search_input_candidates( $content ),
			$this->get_archive_path_candidates( $content )
		);

		foreach ( $candidates as $candidate ) {
			$source_url = $this->normalize_source_url_candidate( $candidate );
			if ( $source_url ) {
				return $source_url;
			}
		}

		return '';
	}

	private function get_link_candidates( $content ) {
		if ( ! preg_match_all( '#<link\b[^>]*\b(?:rel=["\'](?:canonical|bookmark)["\'][^>]*\bhref=["\']([^"\']+)|href=["\']([^"\']+)["\'][^>]*\brel=["\'](?:canonical|bookmark)["\'])#i', $content, $matches, PREG_SET_ORDER ) ) {
			return array();
		}

		return array_map(
			function ( $match ) {
				return ! empty( $match[1] ) ? $match[1] : $match[2];
			},
			$matches
		);
	}

	private function get_search_input_candidates( $content ) {
		if ( ! preg_match_all( '#<input\b[^>]*\b(?:name=["\']q["\'][^>]*\bvalue=["\']([^"\']+)|value=["\']([^"\']+)["\'][^>]*\bname=["\']q["\'])#i', $content, $matches, PREG_SET_ORDER ) ) {
			return array();
		}

		return array_map(
			function ( $match ) {
				return ! empty( $match[1] ) ? $match[1] : $match[2];
			},
			$matches
		);
	}

	private function get_archive_path_candidates( $content ) {
		if ( ! preg_match_all( '#https?://archive\.[^/"\']+/(?:[0-9]{4}\.[0-9]{2}\.[0-9]{2}-[0-9]{6}|o/[^/"\']+)/((?:https?:)?//[^"\'<>\s]+)#i', $content, $matches ) ) {
			return array();
		}

		return $matches[1];
	}

	private function normalize_source_url_candidate( $candidate ) {
		$candidate = html_entity_decode( trim( (string) $candidate ), ENT_QUOTES, 'UTF-8' );
		if ( 0 === strpos( $candidate, '//' ) ) {
			$candidate = 'https:' . $candidate;
		}

		if ( preg_match( '#^https?://archive\.[^/]+/(?:[0-9]{4}\.[0-9]{2}\.[0-9]{2}-[0-9]{6}|o/[^/]+)/((?:https?:)?//.+)$#i', $candidate, $match ) ) {
			$candidate = 0 === strpos( $match[1], '//' ) ? 'https:' . $match[1] : $match[1];
		}

		if ( ! $this->is_valid_source_url( $candidate ) ) {
			return '';
		}

		return esc_url_raw( $candidate );
	}

	private function is_valid_source_url( $url ) {
		$scheme = strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) );
		$host   = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );

		return in_array( $scheme, array( 'http', 'https' ), true ) && '' !== $host && ! $this->is_archive_url( $url );
	}

	private function is_archive_url( $url ) {
		$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		if ( 0 === strpos( $host, 'www.' ) ) {
			$host = substr( $host, 4 );
		}

		return in_array( $host, array( 'archive.is', 'archive.today', 'archive.ph', 'archive.vn', 'archive.fo', 'archive.li', 'archive.md' ), true );
	}
}
