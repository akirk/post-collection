<?php

namespace {
	require_once __DIR__ . '/../vendor/autoload.php';

	if ( ! class_exists( 'WP_HTML_Tag_Processor' ) ) {
		require_once __DIR__ . '/stubs/class-wp-html-tag-processor.php';
	}

	require_once __DIR__ . '/../class-extracted-page.php';

	function __( $text, $domain = 'default' ) {
		return $text;
	}

	function _x( $text, $context, $domain = 'default' ) {
		return $text;
	}

	function _e( $text, $domain = 'default' ) {
		echo $text;
	}

	function esc_html__( $text, $domain = 'default' ) {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}

	function esc_html_e( $text, $domain = 'default' ) {
		echo esc_html__( $text, $domain );
	}

	function esc_attr__( $text, $domain = 'default' ) {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}

	function esc_html( $text ) {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}

	function esc_attr( $text ) {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}

	function esc_url( $url ) {
		return htmlspecialchars( $url, ENT_QUOTES, 'UTF-8' );
	}

	function wp_kses_post( $data ) {
		return $data;
	}

	function force_balance_tags( $text ) {
		return $text;
	}

	function wp_parse_url( $url, $component = -1 ) {
		return parse_url( $url, $component );
	}

	function add_action( $tag, $function_to_add, $priority = 10, $accepted_args = 1 ) {
		return true;
	}

	function add_filter( $tag, $function_to_add, $priority = 10, $accepted_args = 1 ) {
		return true;
	}

	function did_action( $hook_name ) {
		return false;
	}
}
