<?php
namespace PostCollection\SiteConfig;

use PostCollection\ExtractedPage;

class Youtube extends SiteConfig {

	public function is_url_supported( $url ) {
		$host = wp_parse_url(
			strtolower( $url ),
			PHP_URL_HOST
		);
		switch ( $host ) {
			case 'youtu.be':
			case 'www.youtube.com':
			case 'youtube.com':
				return true;
		}

		return false;
	}

	public function download( $url ) {
		$item = new ExtractedPage( $url );
		$item->post_format = 'video';

		$api_url = 'https://www.youtube.com/oembed?url=' . urlencode( $url ) . '&format=json';
		$response = wp_remote_get( $api_url );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$data = json_decode( wp_remote_retrieve_body( $response ) );
		$item->title = $data->title;
		$attr = json_encode(
			array(
				'url'              => $url,
				'type'             => 'video',
				'providerNameSlug' => 'youtube',
				'responsive'       => true,
				'className'        => 'wp-embed-aspect-16-9 wp-has-aspect-ratio',
			)
		);
		$item->content = '<!-- wp:embed ' . $attr . ' -->
<figure class="wp-block-embed is-type-video is-provider-youtube wp-block-embed-youtube wp-embed-aspect-16-9 wp-has-aspect-ratio"><div class="wp-block-embed__wrapper">
' . $url . '
</div></figure>
<!-- /wp:embed -->';
		$item->author = $data->author_name;

		return $item;
	}
}
