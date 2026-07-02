<?php
/**
 * Frontend app collection overview.
 *
 * @package Post_Collection
 */

defined( 'ABSPATH' ) || exit;

$app = \PostCollection\Post_Collection_App::instance();
if ( ! $app ) {
	status_header( 500 );
	return;
}

$collections        = array_filter( $app->get_collections(), array( $app, 'can_view_collection' ) );
$visible_collections = array();
$hidden_collections  = array();

foreach ( $collections as $collection ) {
	if ( $app->is_collection_hidden_from_home( $collection ) ) {
		$hidden_collections[] = $collection;
	} else {
		$visible_collections[] = $collection;
	}
}

$render_collection_card = static function ( $collection ) use ( $app ) {
	$count = $app->count_collection_posts( $collection );
	$mode  = $app->get_collection_mode( $collection );
	$posts = $app->query_collection_posts(
		$collection,
		array(
			'posts_per_page'                => 3,
			'post_collection_apply_filters' => false,
			'no_found_rows'                 => true,
		)
	);
	?>
	<article class="pc-collection-card pc-collection-card-<?php echo esc_attr( $mode ); ?>">
		<a class="pc-collection-card-main" href="<?php echo esc_url( $app->get_collection_url( $collection ) ); ?>">
			<span class="pc-mode-label"><?php echo esc_html( 'bookmarks' === $mode ? __( 'Bookmarks', 'post-collection' ) : __( 'Posts', 'post-collection' ) ); ?></span>
			<h2><?php echo esc_html( $collection->name ); ?></h2>
			<?php if ( $collection->description ) : ?>
				<p><?php echo esc_html( wp_trim_words( $collection->description, 18 ) ); ?></p>
			<?php endif; ?>
			<span class="pc-count">
				<?php
				echo esc_html(
					sprintf(
						'bookmarks' === $mode
							// translators: %d is the number of saved links.
							? _n( '%d saved link', '%d saved links', $count, 'post-collection' )
							// translators: %d is the number of posts.
							: _n( '%d post', '%d posts', $count, 'post-collection' ),
						$count
					)
				);
				?>
			</span>
		</a>
		<?php if ( $posts->have_posts() ) : ?>
			<div class="pc-mini-list-wrap">
				<p class="pc-mini-list-title"><?php echo esc_html( 'bookmarks' === $mode ? __( 'Recent saved links', 'post-collection' ) : __( 'Recent posts', 'post-collection' ) ); ?></p>
				<ul class="pc-mini-list">
					<?php foreach ( $posts->posts as $post ) : ?>
						<li>
							<a href="<?php echo esc_url( $app->get_collection_url( $collection, $post->ID ) ); ?>"><?php echo esc_html( get_the_title( $post ) ); ?></a>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>
	</article>
	<?php
};
?>
<!DOCTYPE html>
<html <?php echo wp_app_language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php echo wp_app_title( __( 'Post Collection', 'post-collection' ) ); ?></title>
	<?php wp_app_head(); ?>
</head>
<body <?php body_class( 'wp-app-body post-collection-app' ); ?>>
	<?php wp_app_body_open(); ?>
	<header class="pc-shell pc-app-header">
		<div>
			<p class="pc-kicker"><?php esc_html_e( 'Saved from the web', 'post-collection' ); ?></p>
			<h1><?php esc_html_e( 'Post Collection', 'post-collection' ); ?></h1>
		</div>
		<?php if ( $app->can_manage_collections() ) : ?>
			<div class="pc-app-header-actions">
				<a class="pc-button" href="<?php echo esc_url( $app->get_review_url() ); ?>"><?php esc_html_e( 'Review Articles', 'post-collection' ); ?></a>
				<a class="pc-button pc-button-primary" href="<?php echo esc_url( $app->get_new_collection_url() ); ?>"><?php esc_html_e( 'New Collection', 'post-collection' ); ?></a>
			</div>
		<?php endif; ?>
	</header>

	<main class="pc-shell">
		<?php if ( empty( $collections ) ) : ?>
			<section class="pc-empty">
				<h2><?php esc_html_e( 'No collections to show', 'post-collection' ); ?></h2>
				<p><?php esc_html_e( 'Published collections will appear here.', 'post-collection' ); ?></p>
			</section>
		<?php else : ?>
			<?php if ( ! empty( $visible_collections ) ) : ?>
				<section class="pc-collection-grid" aria-label="<?php esc_attr_e( 'Collections', 'post-collection' ); ?>">
					<?php foreach ( $visible_collections as $collection ) : ?>
						<?php $render_collection_card( $collection ); ?>
					<?php endforeach; ?>
				</section>
			<?php endif; ?>

			<?php if ( empty( $visible_collections ) && ! empty( $hidden_collections ) ) : ?>
				<p class="pc-muted-note"><?php esc_html_e( 'All collections are hidden from the default view.', 'post-collection' ); ?></p>
			<?php endif; ?>

			<?php if ( ! empty( $hidden_collections ) ) : ?>
				<details class="pc-hidden-collections">
					<summary>
						<?php
						echo esc_html(
							sprintf(
								// translators: %d is the number of hidden collections.
								_n( '%d hidden collection', '%d hidden collections', count( $hidden_collections ), 'post-collection' ),
								count( $hidden_collections )
							)
						);
						?>
					</summary>
					<section class="pc-collection-grid pc-collection-grid-hidden" aria-label="<?php esc_attr_e( 'Hidden collections', 'post-collection' ); ?>">
						<?php foreach ( $hidden_collections as $collection ) : ?>
							<?php $render_collection_card( $collection ); ?>
						<?php endforeach; ?>
					</section>
				</details>
			<?php endif; ?>
		<?php endif; ?>
	</main>
	<?php wp_app_body_close(); ?>
</body>
</html>
