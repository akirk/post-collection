<?php
/**
 * Frontend app 404 view.
 *
 * @package Post_Collection
 */

defined( 'ABSPATH' ) || exit;

$app = \PostCollection\Post_Collection_App::instance();
?>
<!DOCTYPE html>
<html <?php wp_app_language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php wp_app_the_title( __( 'Not Found', 'post-collection' ) ); ?></title>
	<?php wp_app_head(); ?>
</head>
<body <?php body_class( 'wp-app-body post-collection-app' ); ?>>
	<?php wp_app_body_open(); ?>
	<main class="pc-shell">
		<section class="pc-empty">
			<h1><?php esc_html_e( 'Collection item not found', 'post-collection' ); ?></h1>
			<p><?php esc_html_e( 'It may be private, unpublished, or no longer available.', 'post-collection' ); ?></p>
			<?php if ( $app ) : ?>
				<a class="pc-button pc-button-primary" href="<?php echo esc_url( $app->get_home_url() ); ?>"><?php esc_html_e( 'View Collections', 'post-collection' ); ?></a>
			<?php endif; ?>
		</section>
	</main>
	<?php wp_app_body_close(); ?>
</body>
</html>
