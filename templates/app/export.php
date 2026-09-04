<?php
/**
 * Frontend app collection export.
 *
 * Without a format parameter this renders the export chooser; with one it
 * streams the download.
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
$collection      = null;
if ( $collection_slug ) {
	$collection = $app->get_collection_by_username( $collection_slug );
	if ( ! $collection || ! $app->can_view_collection( $collection ) ) {
		status_header( 404 );
		include __DIR__ . '/404.php';
		return;
	}
	$collections = array( $collection );
	$back_url    = $app->get_collection_url( $collection );
} else {
	$collections = $app->get_collections();
	$back_url    = $app->get_home_url();
}
$export_url = $collection ? $app->get_collection_export_url( $collection ) : $app->get_export_url();

$format = isset( $_GET['format'] ) ? sanitize_key( wp_unslash( $_GET['format'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
if ( $format ) {
	$export = $app->export_collections( $collections, $format );
	if ( is_wp_error( $export ) ) {
		wp_die( esc_html( $export->get_error_message() ), '', array( 'response' => 400 ) );
	}

	nocache_headers();
	header( 'Content-Type: ' . $export['mime'] . '; charset=' . get_bloginfo( 'charset' ) );
	header( 'Content-Disposition: attachment; filename="' . $export['filename'] . '"' );
	header( 'Content-Length: ' . strlen( $export['content'] ) );
	echo $export['content']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped while building the export.
	exit;
}

$collection_posts_count = 0;
foreach ( $collections as $export_collection ) {
	$collection_posts_count += $app->count_collection_posts( $export_collection );
}
?>
<!DOCTYPE html>
<html <?php wp_app_language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php wp_app_the_title( __( 'Export', 'post-collection' ) ); ?></title>
	<?php wp_app_head(); ?>
</head>
<body <?php body_class( 'wp-app-body post-collection-app pc-export-page' ); ?>>
	<?php wp_app_body_open(); ?>
	<header class="pc-shell pc-detail-header">
		<?php if ( $collection ) : ?>
			<div class="pc-breadcrumb"><a href="<?php echo esc_url( $back_url ); ?>"><?php echo esc_html( $collection->name ); ?></a></div>
		<?php else : ?>
			<div class="pc-breadcrumb"><a href="<?php echo esc_url( $back_url ); ?>"><?php esc_html_e( 'Collections', 'post-collection' ); ?></a></div>
		<?php endif; ?>
		<p class="pc-kicker"><?php esc_html_e( 'Download', 'post-collection' ); ?></p>
		<h1><?php $collection ? esc_html_e( 'Export', 'post-collection' ) : esc_html_e( 'Export All Collections', 'post-collection' ); ?></h1>
	</header>

	<main class="pc-shell pc-import-layout">
		<section class="pc-import-main pc-export-formats">
			<p>
				<?php
				if ( $collection ) {
					echo esc_html(
						sprintf(
							// translators: %s is a number of articles.
							_n( 'Download the %s link in this collection, with its tags and notes.', 'Download all %s links in this collection, with their tags and notes.', $collection_posts_count, 'post-collection' ),
							number_format_i18n( $collection_posts_count )
						)
					);
				} else {
					echo esc_html(
						sprintf(
							// translators: %1$s is a number of articles, %2$s a number of collections.
							__( 'Download all %1$s links from your %2$s collections, with their tags and notes. Each collection becomes its own folder.', 'post-collection' ),
							number_format_i18n( $collection_posts_count ),
							number_format_i18n( count( $collections ) )
						)
					);
				}
				?>
			</p>
			<ul class="pc-export-format-list">
				<?php foreach ( $app->get_export_formats() as $format_key => $export_format ) : ?>
					<li>
						<a class="pc-button pc-button-primary" href="<?php echo esc_url( add_query_arg( 'format', $format_key, $export_url ) ); ?>"><?php echo esc_html( $export_format['label'] ); ?></a>
						<span><?php echo esc_html( $export_format['description'] ); ?></span>
					</li>
				<?php endforeach; ?>
			</ul>
			<div class="pc-form-actions">
				<a class="pc-button" href="<?php echo esc_url( $back_url ); ?>"><?php esc_html_e( 'Back', 'post-collection' ); ?></a>
			</div>
		</section>

		<aside class="pc-import-aside">
			<h2><?php esc_html_e( 'Full backup', 'post-collection' ); ?></h2>
			<?php if ( current_user_can( 'export' ) ) : ?>
				<p>
					<?php
					echo wp_kses(
						sprintf(
							// translators: %s is a link to the WordPress export tool.
							__( 'These files carry links only. For a complete backup including the extracted article content and images, use the %s and choose Collected Posts.', 'post-collection' ),
							'<a href="' . esc_url( admin_url( 'export.php' ) ) . '">' . esc_html__( 'WordPress export tool', 'post-collection' ) . '</a>'
						),
						array( 'a' => array( 'href' => array() ) )
					);
					?>
				</p>
			<?php else : ?>
				<p><?php esc_html_e( 'These files carry links only. A site administrator can produce a complete backup including the article content with the WordPress export tool.', 'post-collection' ); ?></p>
			<?php endif; ?>
		</aside>
	</main>
	<?php wp_app_body_close(); ?>
</body>
</html>
