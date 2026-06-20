<?php
/**
 * Frontend app post detail view.
 *
 * @package Post_Collection
 */

defined( 'ABSPATH' ) || exit;

$app = \PostCollection\Post_Collection_App::instance();
if ( ! $app ) {
	status_header( 500 );
	return;
}

$username = wp_app_get_route_var( 'collection' );
$post_id  = absint( wp_app_get_route_var( 'post_id' ) );
$collection = $app->get_collection_by_username( $username );
$post       = $collection ? $app->get_visible_post( $post_id, $collection ) : null;

if ( ! $collection || ! $post || ! $app->can_view_collection( $collection ) ) {
	status_header( 404 );
	include __DIR__ . '/404.php';
	return;
}

$mode       = $app->get_collection_mode( $collection );
$source_url = $app->get_source_url( $post );
$host       = $app->get_source_host( $post );
$embed_html = $app->get_post_description_embed_html( $post, 'detail' );
$terms      = $app->get_post_terms( $post );
?>
<!DOCTYPE html>
<html <?php echo wp_app_language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php echo wp_app_title( get_the_title( $post ) ); ?></title>
	<?php wp_app_head(); ?>
</head>
<body <?php body_class( 'wp-app-body post-collection-app pc-mode-' . $mode ); ?>>
	<?php wp_app_body_open(); ?>
	<header class="pc-shell pc-detail-header">
		<div class="pc-breadcrumb">
			<a href="<?php echo esc_url( $app->get_home_url() ); ?>"><?php esc_html_e( 'Collections', 'post-collection' ); ?></a>
			<span>/</span>
			<a href="<?php echo esc_url( $app->get_collection_url( $collection ) ); ?>"><?php echo esc_html( $collection->name ); ?></a>
		</div>
		<p class="pc-source"><?php echo esc_html( $host ); ?></p>
		<h1><?php echo esc_html( get_the_title( $post ) ); ?></h1>
		<div class="pc-detail-meta">
			<time datetime="<?php echo esc_attr( get_the_date( DATE_W3C, $post ) ); ?>"><?php echo esc_html( get_the_date( '', $post ) ); ?></time>
			<?php if ( 'private' === $post->post_status && $app->can_manage_collections() ) : ?>
				<span><?php esc_html_e( 'Private', 'post-collection' ); ?></span>
			<?php endif; ?>
			<?php if ( $app->can_manage_collections() ) : ?>
				<a href="<?php echo esc_url( get_edit_post_link( $post->ID ) ); ?>"><?php esc_html_e( 'Edit', 'post-collection' ); ?></a>
			<?php endif; ?>
		</div>
		<div class="pc-detail-actions">
			<a class="pc-button pc-button-primary" href="<?php echo esc_url( $source_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open Original', 'post-collection' ); ?></a>
			<a class="pc-button" href="<?php echo esc_url( $app->get_collection_url( $collection ) ); ?>"><?php esc_html_e( 'Back to Collection', 'post-collection' ); ?></a>
		</div>
		<?php if ( ! empty( $terms ) ) : ?>
			<nav class="pc-tag-strip pc-detail-tags" aria-label="<?php esc_attr_e( 'Tags', 'post-collection' ); ?>">
				<?php foreach ( $terms as $term ) : ?>
					<a href="<?php echo esc_url( add_query_arg( 'pc-tag', $term->slug, $app->get_collection_url( $collection ) ) ); ?>"><?php echo esc_html( $term->name ); ?></a>
				<?php endforeach; ?>
			</nav>
		<?php endif; ?>
	</header>

	<main class="pc-shell pc-detail-layout">
		<article class="pc-detail-content">
			<?php if ( $embed_html ) : ?>
				<?php echo $embed_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php endif; ?>
			<?php if ( '' !== trim( $post->post_content ) ) : ?>
				<?php echo apply_filters( 'the_content', $post->post_content ); ?>
			<?php endif; ?>
		</article>
	</main>
	<?php wp_app_body_close(); ?>
</body>
</html>
