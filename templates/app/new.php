<?php
/**
 * Frontend app collection creation.
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
?>
<!DOCTYPE html>
<html <?php echo wp_app_language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php echo wp_app_title( __( 'New Collection', 'post-collection' ) ); ?></title>
	<?php wp_app_head(); ?>
</head>
<body <?php body_class( 'wp-app-body post-collection-app pc-new-collection-page' ); ?>>
	<?php wp_app_body_open(); ?>
	<header class="pc-shell pc-detail-header">
		<div class="pc-breadcrumb"><a href="<?php echo esc_url( $app->get_home_url() ); ?>"><?php esc_html_e( 'Collections', 'post-collection' ); ?></a></div>
		<p class="pc-kicker"><?php esc_html_e( 'New collection', 'post-collection' ); ?></p>
		<h1><?php esc_html_e( 'Create Collection', 'post-collection' ); ?></h1>
	</header>

	<main class="pc-shell">
		<form class="pc-create-collection-form" method="post" action="<?php echo esc_url( $app->get_new_collection_url() ); ?>">
			<input type="hidden" name="post_collection_action" value="create-collection">
			<?php wp_nonce_field( 'post-collection-create' ); ?>
			<label>
				<span><?php esc_html_e( 'Display name', 'post-collection' ); ?></span>
				<input type="text" name="display_name" required>
			</label>
			<label>
				<span><?php esc_html_e( 'Slug', 'post-collection' ); ?></span>
				<input type="text" name="user_login">
			</label>
			<label>
				<span><?php esc_html_e( 'Type', 'post-collection' ); ?></span>
				<select name="frontend_mode">
					<option value="posts"><?php esc_html_e( 'Posts', 'post-collection' ); ?></option>
					<option value="bookmarks"><?php esc_html_e( 'Bookmarks', 'post-collection' ); ?></option>
				</select>
			</label>
			<label>
				<span><?php esc_html_e( 'Default layout', 'post-collection' ); ?></span>
				<select name="frontend_view">
					<option value="reader"><?php esc_html_e( 'Reader list', 'post-collection' ); ?></option>
					<option value="board"><?php esc_html_e( 'Board', 'post-collection' ); ?></option>
					<option value="links"><?php esc_html_e( 'Compact links', 'post-collection' ); ?></option>
				</select>
			</label>
			<label class="pc-checkbox-field">
				<input type="checkbox" name="hide_from_home" value="1">
				<span><?php esc_html_e( 'Hide from the default collections view', 'post-collection' ); ?></span>
			</label>
			<div class="pc-form-actions">
				<a class="pc-button" href="<?php echo esc_url( $app->get_home_url() ); ?>"><?php esc_html_e( 'Cancel', 'post-collection' ); ?></a>
				<button type="submit"><?php esc_html_e( 'Create Collection', 'post-collection' ); ?></button>
			</div>
		</form>
	</main>
	<?php wp_app_body_close(); ?>
</body>
</html>
