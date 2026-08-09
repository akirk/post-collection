<?php
/**
 * Base contract for URL-specific download and source URL handling.
 *
 * Site configs let Post Collection override the default article extraction
 * path for services that need special handling.
 *
 * @package Post_Collection
 */
namespace PostCollection\SiteConfig;

defined( 'ABSPATH' ) || exit;

abstract class SiteConfig {
	abstract public function is_url_supported( $url );
	abstract public function download( $url );

	public function get_source_url( $url, $content = null ) {
		return '';
	}
}
