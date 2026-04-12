<?php
/**
 * This template contains the post collection editor.
 *
 * @version 1.0
 * @package Friends_Post_Collection
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables from parent scope.
?>
<div class="card">
	<h2 class="title"><?php esc_html_e( 'Friends Post Collection', 'post-collection' ); ?></h2>
	<h3><?php esc_html_e( 'Bookmarklets', 'post-collection' ); ?></h3>

	<p><?php esc_html_e( "Drag one of these bookmarklets to your bookmarks bar and click it when you're on an article you want to save from the web.", 'post-collection' ); ?></p>
	<p>
		<?php foreach ( $args['post_collections'] as $collection ) : ?>
		<a href="javascript:<?php echo rawurlencode( trim( str_replace( "window.document.getElementById( 'post-collection-script' ).getAttribute( 'data-post-url' )", "'" . esc_url( home_url() ) . "/?user=" . $collection['user_id'] . "'", $args['bookmarklet_js'] ), ';' ) ); ?>" style="display: inline-block; padding: .5em; border: 1px solid #999; border-radius: 4px; background-color: #ddd;text-decoration: none; margin-right: 3em">
			<?php
			echo esc_html(
				sprintf(
				// translators: %s is the name of a Post Collection user.
					__( 'Save to %s', 'post-collection' ),
					$collection['display_name']
				)
			);
			?>
		</a>
		<?php endforeach; ?>
	</p>
</div>
