<?php
/**
 * Plugin name: Post Collection
 * Plugin URI: https://github.com/akirk/post-collection
 * Version: 2.0.0
 * Author: Alex Kirk
 * Author URI: https://alex.kirk.at/
 *
 * Description: Collect posts from around the web.
 *
 * License: GPL2
 * Text Domain: post-collection
 * Domain Path: /languages/
 *
 * @package Post_Collection
 */

namespace PostCollection;

/**
 * This file contains the main plugin functionality.
 */

defined( 'ABSPATH' ) || exit;
define( 'POST_COLLECTION_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'POST_COLLECTION_PLUGIN_FILE', plugin_dir_path( __FILE__ ) . '/' . basename( __FILE__ ) );
define( 'POST_COLLECTION_VERSION', '2.0.0' );

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/class-user.php';
require_once __DIR__ . '/class-user-query.php';
require_once __DIR__ . '/class-post-collection.php';
require_once __DIR__ . '/class-extracted-page.php';
require_once __DIR__ . '/site-configs/class-site-config.php';
require_once __DIR__ . '/site-configs/class-youtube.php';
require_once __DIR__ . '/includes/class-article-notes.php';

add_filter( 'post_collection_active', '__return_true' );

function load_post_collection( $friends = null ) {
	if ( doing_action( 'init' ) && did_action( 'friends_loaded' ) ) {
		return;
	}

	if ( ! $friends instanceof \Friends\Friends ) {
		$friends = null;
	}

	$post_collection = new Post_Collection( $friends );
	$post_collection->register_site_config( new SiteConfig\Youtube() );
}

add_action( 'friends_loaded', __NAMESPACE__ . '\load_post_collection' );
add_action( 'init', __NAMESPACE__ . '\load_post_collection' );

register_activation_hook( __FILE__, array( __NAMESPACE__ . '\Post_Collection', 'activate_plugin' ) );
add_action( 'activate_blog', array( __NAMESPACE__ . '\Post_Collection', 'activate_plugin' ) );
add_action( 'wp_initialize_site', array( __NAMESPACE__ . '\Post_Collection', 'activate_for_blog' ) );
