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
?>
<!DOCTYPE html>
<html <?php echo wp_app_language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php echo wp_app_title( sprintf( __( '%s Settings', 'post-collection' ), $collection->name ) ); ?></title>
	<?php wp_app_head(); ?>
</head>
<body <?php body_class( 'wp-app-body post-collection-app' ); ?>>
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
					<option value="auto"<?php selected( ! $configured_mode ); ?>><?php esc_html_e( 'Auto', 'post-collection' ); ?></option>
					<option value="bookmarks"<?php selected( 'bookmarks', $configured_mode ); ?>><?php esc_html_e( 'Bookmarks', 'post-collection' ); ?></option>
					<option value="posts"<?php selected( 'posts', $configured_mode ); ?>><?php esc_html_e( 'Posts', 'post-collection' ); ?></option>
				</select>
			</label>
			<label>
				<span><?php esc_html_e( 'Default layout', 'post-collection' ); ?></span>
				<select name="frontend_view">
					<option value="auto"<?php selected( ! $configured_view ); ?>><?php esc_html_e( 'Auto', 'post-collection' ); ?></option>
					<option value="board"<?php selected( 'board', $configured_view ); ?>><?php esc_html_e( 'Board', 'post-collection' ); ?></option>
					<option value="links"<?php selected( 'links', $configured_view ); ?>><?php esc_html_e( 'Compact links', 'post-collection' ); ?></option>
					<option value="reader"<?php selected( 'reader', $configured_view ); ?>><?php esc_html_e( 'Reader list', 'post-collection' ); ?></option>
				</select>
			</label>
			<div class="pc-form-actions">
				<a class="pc-button" href="<?php echo esc_url( $app->get_collection_url( $collection ) ); ?>"><?php esc_html_e( 'Back to collection', 'post-collection' ); ?></a>
				<button type="submit"><?php esc_html_e( 'Save settings', 'post-collection' ); ?></button>
			</div>
		</form>
	</main>
	<?php wp_app_body_close(); ?>
</body>
</html>
