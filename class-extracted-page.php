<?php

namespace PostCollection;

class ExtractedPage {
	public $title;
	public $content;
	public $url;
	public $author;
	public $post_format = 'standard';
	public $raw_html;

	/**
	 * ExtractedPage constructor.
	 *
	 * @param string $url
	 * @param string $title
	 * @param string $content
	 */
	public function __construct( $url, $title = '', $content = '' ) {
		$this->title = $title;
		$this->content = $content;
		$this->url = $url;
	}
}
