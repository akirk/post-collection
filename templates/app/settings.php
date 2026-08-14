<?php
/**
 * Frontend app collection settings.
 *
 * @package Post_Collection
 */

defined( 'ABSPATH' ) || exit;

$app = \PostCollection\Post_Collection_App::instance();
if ( ! $app ) {
	status_header( 500 );
	return;
}

$collection_slug = wp_app_get_route_var( 'collection' );
$collection      = $app->get_collection_by_username( $collection_slug );
if ( ! $collection || ! $app->can_manage_collections() ) {
	status_header( 404 );
	include __DIR__ . '/404.php';
	return;
}

$configured_mode = get_term_meta( $collection->term_id, 'post_collection_frontend_mode', true );
$configured_view = get_term_meta( $collection->term_id, 'post_collection_frontend_view', true );
$hide_from_home  = get_term_meta( $collection->term_id, 'post_collection_hide_from_home', true );
$collection_posts_count = $app->count_collection_posts( $collection );
$reassign_collections   = array_filter(
	$app->get_collections(),
	static function ( $candidate ) use ( $collection ) {
		return (int) $candidate->term_id !== (int) $collection->term_id;
	}
);
?>
<!DOCTYPE html>
<html <?php echo wp_app_language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php echo wp_app_title( sprintf( __( '%s Settings', 'post-collection' ), $collection->name ) ); ?></title>
	<?php wp_app_head(); ?>
</head>
<body <?php body_class( 'wp-app-body post-collection-app pc-settings-page' ); ?>>
	<?php wp_app_body_open(); ?>
	<header class="pc-shell pc-detail-header">
		<div class="pc-breadcrumb">
			<a href="<?php echo esc_url( $app->get_home_url() ); ?>"><?php esc_html_e( 'Collections', 'post-collection' ); ?></a>
			<span>/</span>
			<a href="<?php echo esc_url( $app->get_collection_url( $collection ) ); ?>"><?php echo esc_html( $collection->name ); ?></a>
		</div>
		<p class="pc-kicker"><?php esc_html_e( 'Collection settings', 'post-collection' ); ?></p>
		<h1><?php echo esc_html( $collection->name ); ?></h1>
	</header>

	<main class="pc-shell">
		<form class="pc-collection-settings pc-settings-page-form" method="post" action="<?php echo esc_url( $app->get_collection_settings_url( $collection ) ); ?>">
			<input type="hidden" name="post_collection_action" value="collection-settings">
			<input type="hidden" name="collection_term_id" value="<?php echo esc_attr( $collection->term_id ); ?>">
			<?php wp_nonce_field( 'post-collection-settings-' . $collection->term_id ); ?>
			<label>
				<span><?php esc_html_e( 'Type', 'post-collection' ); ?></span>
				<select name="frontend_mode">
					<option value="posts"<?php selected( 'posts', $configured_mode ?: 'posts' ); ?>><?php esc_html_e( 'Posts', 'post-collection' ); ?></option>
					<option value="bookmarks"<?php selected( 'bookmarks', $configured_mode ); ?>><?php esc_html_e( 'Bookmarks', 'post-collection' ); ?></option>
				</select>
			</label>
			<label>
				<span><?php esc_html_e( 'Default layout', 'post-collection' ); ?></span>
				<select name="frontend_view">
					<option value="reader"<?php selected( 'reader', $configured_view ?: 'reader' ); ?>><?php esc_html_e( 'Reader list', 'post-collection' ); ?></option>
					<option value="board"<?php selected( 'board', $configured_view ); ?>><?php esc_html_e( 'Board', 'post-collection' ); ?></option>
					<option value="links"<?php selected( 'links', $configured_view ); ?>><?php esc_html_e( 'Compact links', 'post-collection' ); ?></option>
				</select>
			</label>
			<label class="pc-checkbox-field">
				<input type="checkbox" name="hide_from_home" value="1" <?php checked( $hide_from_home ); ?>>
				<span><?php esc_html_e( 'Hide from the default collections view', 'post-collection' ); ?></span>
			</label>
			<div class="pc-form-actions">
				<a class="pc-button" href="<?php echo esc_url( $app->get_collection_url( $collection ) ); ?>"><?php esc_html_e( 'Back to collection', 'post-collection' ); ?></a>
				<button type="submit"><?php esc_html_e( 'Save settings', 'post-collection' ); ?></button>
			</div>
		</form>
		<section class="pc-settings-bookmarklet">
			<h2><?php esc_html_e( 'Save from the web', 'post-collection' ); ?></h2>
			<div class="pc-save-tabs" data-pc-tabs>
				<div class="pc-save-tab-list" role="tablist" aria-label="<?php esc_attr_e( 'Save methods', 'post-collection' ); ?>">
					<button type="button" class="is-active" role="tab" aria-selected="true" aria-controls="pc-settings-bookmarklet-panel-<?php echo esc_attr( $collection->term_id ); ?>" id="pc-settings-bookmarklet-tab-<?php echo esc_attr( $collection->term_id ); ?>" data-pc-tab="bookmarklet"><?php esc_html_e( 'Bookmarklet', 'post-collection' ); ?></button>
					<button type="button" role="tab" aria-selected="false" aria-controls="pc-settings-extension-panel-<?php echo esc_attr( $collection->term_id ); ?>" id="pc-settings-extension-tab-<?php echo esc_attr( $collection->term_id ); ?>" data-pc-tab="extension" tabindex="-1"><?php esc_html_e( 'Browser extension', 'post-collection' ); ?></button>
					<button type="button" role="tab" aria-selected="false" aria-controls="pc-settings-urlforwarder-panel-<?php echo esc_attr( $collection->term_id ); ?>" id="pc-settings-urlforwarder-tab-<?php echo esc_attr( $collection->term_id ); ?>" data-pc-tab="urlforwarder" tabindex="-1"><?php esc_html_e( 'URLForwarder', 'post-collection' ); ?></button>
				</div>
				<div id="pc-settings-bookmarklet-panel-<?php echo esc_attr( $collection->term_id ); ?>" class="pc-save-tab-panel is-active" role="tabpanel" aria-labelledby="pc-settings-bookmarklet-tab-<?php echo esc_attr( $collection->term_id ); ?>" data-pc-tab-panel="bookmarklet">
					<p><?php esc_html_e( "Drag this bookmarklet to your bookmarks bar and click it when you're on an article you want to save from the web.", 'post-collection' ); ?></p>
					<?php
					$app->get_post_collection()->render_bookmarklet_link(
						$app->get_collection_bookmarklet_href( $collection ),
						sprintf(
							// translators: %s is the name of a post collection.
							__( 'Save to %s', 'post-collection' ),
							$collection->name
						)
					);
					?>
				</div>
				<div id="pc-settings-extension-panel-<?php echo esc_attr( $collection->term_id ); ?>" class="pc-save-tab-panel" role="tabpanel" aria-labelledby="pc-settings-extension-tab-<?php echo esc_attr( $collection->term_id ); ?>" data-pc-tab-panel="extension" hidden>
					<p><?php esc_html_e( 'The Friends browser extension can add save actions for your collections and send the current page content for better article extraction.', 'post-collection' ); ?></p>
				</div>
				<div id="pc-settings-urlforwarder-panel-<?php echo esc_attr( $collection->term_id ); ?>" class="pc-save-tab-panel" role="tabpanel" aria-labelledby="pc-settings-urlforwarder-tab-<?php echo esc_attr( $collection->term_id ); ?>" data-pc-tab-panel="urlforwarder" hidden>
					<p>
						<?php esc_html_e( 'Use these values in', 'post-collection' ); ?>
						<a href="<?php echo esc_url( 'https://f-droid.org/packages/net.daverix.urlforward/' ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'URLForwarder for Android', 'post-collection' ); ?></a>.
					</p>
					<table class="pc-urlforwarder-settings">
						<tbody>
							<tr>
								<th scope="row"><?php esc_html_e( 'Filter name', 'post-collection' ); ?></th>
								<td><code><?php echo esc_html( $collection->name ); ?></code></td>
								<td>
									<button type="button" class="pc-button pc-copy-value" data-copy-value="<?php echo esc_attr( $collection->name ); ?>" data-copy-label="<?php esc_attr_e( 'Copy', 'post-collection' ); ?>" data-copied-label="<?php esc_attr_e( 'Copied', 'post-collection' ); ?>">
										<?php esc_html_e( 'Copy', 'post-collection' ); ?>
									</button>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Output URL', 'post-collection' ); ?></th>
								<td><code class="pc-urlforwarder-output-url"><?php echo esc_html( $app->get_collection_urlforwarder_url( $collection ) ); ?></code></td>
								<td>
									<button type="button" class="pc-button pc-copy-value" data-copy-value="<?php echo esc_attr( $app->get_collection_urlforwarder_url( $collection ) ); ?>" data-copy-label="<?php esc_attr_e( 'Copy', 'post-collection' ); ?>" data-copied-label="<?php esc_attr_e( 'Copied', 'post-collection' ); ?>">
										<?php esc_html_e( 'Copy', 'post-collection' ); ?>
									</button>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Replaceable text', 'post-collection' ); ?></th>
								<td><code>@url</code></td>
								<td></td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Replaceable subject', 'post-collection' ); ?></th>
								<td><code>@subject</code></td>
								<td></td>
							</tr>
						</tbody>
					</table>
					<p class="pc-urlforwarder-note"><?php esc_html_e( 'The forwarded URL should be URL encoded.', 'post-collection' ); ?></p>
				</div>
			</div>
		</section>
		<section class="pc-settings-danger">
			<h2><?php esc_html_e( 'Delete collection', 'post-collection' ); ?></h2>
			<p><?php esc_html_e( 'Deleting this collection removes it from the app. Saved posts are not deleted.', 'post-collection' ); ?></p>
			<form method="post" action="<?php echo esc_url( $app->get_collection_settings_url( $collection ) ); ?>" onsubmit="return window.confirm('<?php echo esc_js( __( 'Delete this collection? Saved posts will not be deleted.', 'post-collection' ) ); ?>');">
				<input type="hidden" name="post_collection_action" value="delete-collection">
				<input type="hidden" name="collection_term_id" value="<?php echo esc_attr( $collection->term_id ); ?>">
				<?php wp_nonce_field( 'post-collection-delete-' . $collection->term_id ); ?>
				<?php if ( $collection_posts_count > 0 ) : ?>
					<label>
						<span><?php esc_html_e( 'Saved posts', 'post-collection' ); ?></span>
						<select name="reassign_collection_term_id">
							<option value="0"><?php esc_html_e( 'Leave posts unassigned', 'post-collection' ); ?></option>
							<?php foreach ( $reassign_collections as $reassign_collection ) : ?>
								<option value="<?php echo esc_attr( $reassign_collection->term_id ); ?>"><?php echo esc_html( $reassign_collection->name ); ?></option>
							<?php endforeach; ?>
						</select>
					</label>
					<p class="pc-settings-danger-note">
						<?php
						echo esc_html(
							sprintf(
								// translators: %d is the number of posts in the collection being deleted.
								_n( '%d saved post can be reassigned before deletion.', '%d saved posts can be reassigned before deletion.', $collection_posts_count, 'post-collection' ),
								$collection_posts_count
							)
						);
						?>
					</p>
				<?php endif; ?>
				<button type="submit" class="pc-button pc-button-danger"><?php esc_html_e( 'Delete collection', 'post-collection' ); ?></button>
			</form>
		</section>
	</main>
	<?php wp_app_body_close(); ?>
</body>
</html>
