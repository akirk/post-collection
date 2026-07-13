<?php
/**
 * Frontend app collection import.
 *
 * @package Post_Collection
 */

defined( 'ABSPATH' ) || exit;

$app = \PostCollection\Post_Collection_App::instance();
if ( ! $app ) {
	status_header( 500 );
	return;
}

if ( ! $app->can_manage_collections() ) {
	status_header( 403 );
	include __DIR__ . '/403.php';
	return;
}

$collection_slug = wp_app_get_route_var( 'collection' );
$collection      = $app->get_collection_by_username( $collection_slug );
if ( ! $collection || ! $app->can_view_collection( $collection ) ) {
	status_header( 404 );
	include __DIR__ . '/404.php';
	return;
}

?>
<!DOCTYPE html>
<html <?php echo wp_app_language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php echo wp_app_title( __( 'Import URLs', 'post-collection' ) ); ?></title>
	<?php wp_app_head(); ?>
</head>
<body <?php body_class( 'wp-app-body post-collection-app pc-import-page' ); ?>>
	<?php wp_app_body_open(); ?>
	<header class="pc-shell pc-detail-header">
		<div class="pc-breadcrumb"><a href="<?php echo esc_url( $app->get_collection_url( $collection ) ); ?>"><?php echo esc_html( $collection->name ); ?></a></div>
		<p class="pc-kicker"><?php esc_html_e( 'Bulk import', 'post-collection' ); ?></p>
		<h1><?php esc_html_e( 'Import URLs', 'post-collection' ); ?></h1>
	</header>

	<main class="pc-shell pc-import-layout">
		<section class="pc-import-main">
			<form class="pc-import-form" method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" data-parse-action="post_collection_parse_import" data-import-action="post_collection_import_item">
				<input type="hidden" name="collection_term_id" value="<?php echo esc_attr( $collection->term_id ); ?>">
				<?php wp_nonce_field( 'post-collection-import-' . $collection->term_id ); ?>
				<label class="pc-import-file-field" for="pc-import-file">
					<span><?php esc_html_e( 'Import file', 'post-collection' ); ?></span>
					<span class="pc-import-file-control">
						<span class="pc-import-file-button"><?php esc_html_e( 'Choose file', 'post-collection' ); ?></span>
						<span class="pc-import-file-name" data-import-file-name><?php esc_html_e( 'No file selected', 'post-collection' ); ?></span>
						<input id="pc-import-file" type="file" name="import_file" accept=".csv,.html,.htm,.xml,.rss,.atom,.txt,text/csv,text/html,application/rss+xml,application/atom+xml,application/xml,text/xml,text/plain">
					</span>
				</label>
				<label for="pc-import-urls">
					<span><?php esc_html_e( 'Paste URLs or export contents', 'post-collection' ); ?></span>
					<textarea id="pc-import-urls" name="import_urls" rows="14" placeholder="https://example.com/article-one&#10;https://example.com/article-two"></textarea>
				</label>
				<div class="pc-form-actions">
					<a class="pc-button" href="<?php echo esc_url( $app->get_collection_url( $collection ) ); ?>"><?php esc_html_e( 'Back to Collection', 'post-collection' ); ?></a>
					<button type="submit"><?php esc_html_e( 'Import URLs', 'post-collection' ); ?></button>
				</div>
			</form>

			<section class="pc-import-progress" data-import-progress hidden>
				<div class="pc-import-progress-header">
					<div>
						<h2><?php esc_html_e( 'Import progress', 'post-collection' ); ?></h2>
						<p data-import-status><?php esc_html_e( 'Waiting to start.', 'post-collection' ); ?></p>
					</div>
					<div class="pc-import-progress-counts" aria-live="polite">
						<strong data-import-completed>0</strong>
						<span>/</span>
						<span data-import-total>0</span>
					</div>
				</div>
				<progress class="pc-import-progress-bar" data-import-progress-bar value="0" max="1"></progress>
				<div class="pc-import-summary" data-import-summary></div>
				<ul class="pc-import-log" data-import-log></ul>
			</section>
		</section>

		<aside class="pc-import-aside">
			<h2><?php esc_html_e( 'Accepted formats', 'post-collection' ); ?></h2>
			<ul>
				<li><?php esc_html_e( 'One URL per line from another reading list.', 'post-collection' ); ?></li>
				<li><?php esc_html_e( 'Pocket CSV exports and other URL CSV files.', 'post-collection' ); ?></li>
				<li><?php esc_html_e( 'Browser bookmarks.html exports.', 'post-collection' ); ?></li>
				<li><?php esc_html_e( 'RSS or Atom feed files.', 'post-collection' ); ?></li>
			</ul>
			<p><?php esc_html_e( 'Each URL is downloaded and article content is extracted as it imports, so large files can take a while.', 'post-collection' ); ?></p>
		</aside>
	</main>
	<?php wp_app_body_close(); ?>
</body>
</html>
