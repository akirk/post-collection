<?php
namespace PostCollection\SiteConfig;

defined( 'ABSPATH' ) || exit;

class Cloudflare_Protected extends Jina {
	public function is_url_supported( $url ) {
		$scheme = wp_parse_url( $url, PHP_URL_SCHEME );
		return in_array( strtolower( (string) $scheme ), array( 'http', 'https' ), true );
	}

	public function is_challenge_response( $html, $response = null ) {
		if ( false !== stripos( $html, '/cdn-cgi/challenge-platform/' )
			|| false !== stripos( $html, 'Enable JavaScript and cookies to continue' )
			|| false !== stripos( $html, 'cf-mitigated' )
		) {
			return true;
		}

		if ( is_array( $response ) ) {
			$cf_mitigated = wp_remote_retrieve_header( $response, 'cf-mitigated' );
			if ( 'challenge' === strtolower( (string) $cf_mitigated ) ) {
				return true;
			}
		}

		return false;
	}

	protected function get_reader_url_filter_name() {
		return 'post_collection_cloudflare_jina_reader_url';
	}
}
