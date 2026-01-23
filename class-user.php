<?php
/**
 * Post Collection User
 *
 * This wraps \WP_User and provides methods needed by the Post Collection plugin.
 *
 * @package Post_Collection
 */

namespace PostCollection;

/**
 * User class for the Post Collection plugin.
 *
 * @since 3.0
 *
 * @package Post_Collection
 * @author Alex Kirk
 */
class User extends \WP_User {
	/**
	 * Sanitize a username for use as a user_login.
	 *
	 * @param string $username The username to sanitize.
	 * @return string The sanitized username.
	 */
	public static function sanitize_username( $username ) {
		$username = strtolower( remove_accents( $username ) );
		$username = preg_replace( '/[^a-z0-9.-]+/', '-', $username );
		$username = trim( $username, '-' );
		$username = sanitize_user( $username, true );

		return $username;
	}

	/**
	 * Check if a user is a Friends plugin user.
	 * Without the Friends plugin, this always returns false.
	 *
	 * @param \WP_User $user The user to check.
	 * @return bool Whether the user is a Friends plugin user.
	 */
	public static function is_friends_plugin_user( \WP_User $user ) {
		return $user->has_cap( 'friend' ) || $user->has_cap( 'pending_friend_request' ) || $user->has_cap( 'friend_request' ) || $user->has_cap( 'subscription' );
	}

	/**
	 * Get a user by ID, potentially returning a Post Collection user.
	 *
	 * @param int $user_id The user ID.
	 * @return User|false The user or false.
	 */
	public static function get_user_by_id( $user_id ) {
		$user = get_user_by( 'ID', $user_id );
		if ( $user ) {
			return new self( $user );
		}

		return false;
	}

	/**
	 * Get the author of a post.
	 *
	 * @param \WP_Post $post The post.
	 * @return User The post author.
	 */
	public static function get_post_author( \WP_Post $post ) {
		return new self( $post->post_author );
	}

	/**
	 * Get the local friends page URL for this user.
	 *
	 * @param int|null $post_id Optional post ID to link to.
	 * @return string The URL.
	 */
	public function get_local_friends_page_url( $post_id = null ) {
		if ( ! class_exists( 'Friends\Friends' ) ) {
			if ( $post_id && ! is_wp_error( $post_id ) ) {
				return get_permalink( $post_id );
			}
			return home_url();
		}

		$path = '/';
		if ( $post_id && ! is_wp_error( $post_id ) ) {
			$path = '/' . $post_id . '/';
		}

		$user_login = $this->get_user_login_for_url( $this->user_login );
		if ( ! $user_login || is_wp_error( $user_login ) ) {
			return home_url( '/friends/' . $path );
		}

		return home_url( '/friends/' . $user_login . $path );
	}

	/**
	 * Get the user login formatted for use in URLs.
	 *
	 * @param string $user_login The user login.
	 * @return string The URL-safe user login.
	 */
	protected function get_user_login_for_url( $user_login ) {
		return sanitize_title( $user_login );
	}
}
