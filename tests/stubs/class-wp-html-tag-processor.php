<?php

class WP_HTML_Tag_Processor {
	private $html;
	private $tokens;
	private $current_index = -1;
	private $current_token;

	public function __construct( $html ) {
		$this->html = $html;
		$this->tokenize();
	}

	private function tokenize() {
		$this->tokens = array();
		$offset = 0;
		$length = strlen( $this->html );

		while ( $offset < $length ) {
			if ( $this->html[ $offset ] === '<' ) {
				$end = strpos( $this->html, '>', $offset );
				if ( false === $end ) {
					break;
				}

				$tag_string = substr( $this->html, $offset, $end - $offset + 1 );
				$is_closer = substr( $tag_string, 1, 1 ) === '/';

				if ( $is_closer ) {
					$tag_name = strtoupper( trim( substr( $tag_string, 2, -1 ) ) );
					$this->tokens[] = array(
						'type'      => '#tag',
						'tag_name'  => $tag_name,
						'is_closer' => true,
						'raw'       => $tag_string,
					);
				} else {
					preg_match( '/<([a-zA-Z0-9]+)/', $tag_string, $matches );
					$tag_name = isset( $matches[1] ) ? strtoupper( $matches[1] ) : '';

					$attributes = array();
					preg_match_all( '/([a-zA-Z0-9_-]+)=(["\'])([^\2]*?)\2/', $tag_string, $attr_matches, PREG_SET_ORDER );
					foreach ( $attr_matches as $attr ) {
						$attributes[ $attr[1] ] = $attr[3];
					}

					$this->tokens[] = array(
						'type'       => '#tag',
						'tag_name'   => $tag_name,
						'is_closer'  => false,
						'attributes' => $attributes,
						'raw'        => $tag_string,
					);
				}

				$offset = $end + 1;
			} else {
				$next_tag = strpos( $this->html, '<', $offset );
				if ( false === $next_tag ) {
					$next_tag = $length;
				}

				$text = substr( $this->html, $offset, $next_tag - $offset );
				if ( $text !== '' ) {
					$this->tokens[] = array(
						'type' => '#text',
						'text' => $text,
					);
				}

				$offset = $next_tag;
			}
		}
	}

	public function next_token() {
		$this->current_index++;
		if ( $this->current_index >= count( $this->tokens ) ) {
			return false;
		}

		$this->current_token = $this->tokens[ $this->current_index ];
		return true;
	}

	public function next_tag( $query = null ) {
		while ( $this->next_token() ) {
			if ( $this->current_token['type'] === '#tag' && ! $this->current_token['is_closer'] ) {
				if ( null === $query ) {
					return true;
				}

				if ( is_array( $query ) && isset( $query['tag_name'] ) ) {
					if ( strtoupper( $query['tag_name'] ) === $this->current_token['tag_name'] ) {
						return true;
					}
				} elseif ( is_string( $query ) ) {
					if ( strtoupper( $query ) === $this->current_token['tag_name'] ) {
						return true;
					}
				}
			}
		}

		return false;
	}

	public function get_token_type() {
		return $this->current_token['type'] ?? null;
	}

	public function get_tag() {
		return $this->current_token['tag_name'] ?? null;
	}

	public function is_tag_closer() {
		return $this->current_token['is_closer'] ?? false;
	}

	public function get_modifiable_text() {
		return $this->current_token['text'] ?? '';
	}

	public function get_attribute( $name ) {
		return $this->current_token['attributes'][ $name ] ?? null;
	}

	public function get_attribute_names_with_prefix( $prefix ) {
		if ( ! isset( $this->current_token['attributes'] ) ) {
			return array();
		}

		return array_keys( $this->current_token['attributes'] );
	}

	public function set_attribute( $name, $value ) {
		$raw = $this->current_token['raw'];
		$updated = preg_replace( '/\b' . preg_quote( $name, '/' ) . '=["\'][^"\']*["\']/', $name . '="' . htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' ) . '"', $raw );
		$this->html = str_replace( $raw, $updated, $this->html );
		$this->current_token['raw'] = $updated;
		$this->current_token['attributes'][ $name ] = $value;
	}

	public function get_updated_html() {
		return $this->html;
	}

	public function get_token_name() {
		return 'token_' . $this->current_index;
	}
}
