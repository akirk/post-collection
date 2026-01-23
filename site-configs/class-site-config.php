<?php
namespace PostCollection\SiteConfig;

abstract class SiteConfig {
	abstract public function is_url_supported( $url );
	abstract public function download( $url );
}
