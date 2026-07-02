<?php
/**
 * Frontend app article review queue.
 *
 * @package Post_Collection
 */

defined( 'ABSPATH' ) || exit;

$app = \PostCollection\Post_Collection_App::instance();
if ( ! $app ) {
	status_header( 500 );
	return;
}

if ( ! current_user_can( 'edit_posts' ) ) {
	status_header( 403 );
	include __DIR__ . '/403.php';
	return;
}

$article_notes = $app->get_post_collection()->get_article_notes();
$statuses      = $app->get_article_statuses();
$nonce         = wp_create_nonce( 'post-collection-article-notes' );
$limit         = 12;

$review_articles = $article_notes->get_review_queue_articles( $limit + 1 );
$has_more_articles = count( $review_articles ) > $limit;
if ( $has_more_articles ) {
	$review_articles = array_slice( $review_articles, 0, $limit );
}

$get_article_url = static function ( $article ) use ( $app ) {
	$post = get_post( $article['id'] );
	if ( $post ) {
		$collection = $app->get_post_collection_term( $post );
		if ( $collection ) {
			return $app->get_collection_url( $collection, $post->ID );
		}
	}

	return $article['permalink'];
};

$render_article = static function ( $article ) use ( $statuses, $get_article_url ) {
	$rating = isset( $article['rating'] ) ? (int) $article['rating'] : 0;
	$status = isset( $article['status'] ) ? $article['status'] : \PostCollection\Article_Notes::STATUS_UNREAD;
	$url    = $get_article_url( $article );
	$is_collapsed = in_array( $status, array( \PostCollection\Article_Notes::STATUS_READ, \PostCollection\Article_Notes::STATUS_SKIPPED ), true );
	?>
	<article class="pc-review-item<?php echo $is_collapsed ? ' is-collapsed' : ''; ?> pc-article-notes" data-article-id="<?php echo esc_attr( $article['id'] ); ?>">
		<div class="pc-review-item-main">
			<div class="pc-review-title-block">
				<h2><a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $article['title'] ); ?></a></h2>
				<p>
					<?php echo esc_html( $article['author'] ); ?>
					<?php if ( ! empty( $article['collection'] ) && $article['collection'] !== $article['author'] ) : ?>
						<span><?php echo esc_html( $article['collection'] ); ?></span>
					<?php endif; ?>
					<?php if ( ! empty( $article['sent_label'] ) ) : ?>
						<span>
							<?php if ( ! empty( $article['sent_datetime'] ) ) : ?>
								<time datetime="<?php echo esc_attr( $article['sent_datetime'] ); ?>"><?php echo esc_html( $article['sent_label'] ); ?></time>
							<?php else : ?>
								<?php echo esc_html( $article['sent_label'] ); ?>
							<?php endif; ?>
						</span>
					<?php endif; ?>
				</p>
			</div>
		</div>

		<?php if ( ! empty( $article['content'] ) ) : ?>
			<details class="pc-review-preview">
				<summary><?php esc_html_e( 'Show article', 'post-collection' ); ?></summary>
				<div><?php echo $article['content']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
			</details>
			<button type="button" class="pc-review-collapse"><?php esc_html_e( 'Collapse', 'post-collection' ); ?></button>
		<?php endif; ?>

		<div class="pc-article-notes-controls">
			<div class="pc-note-statuses" aria-label="<?php esc_attr_e( 'Reading status', 'post-collection' ); ?>">
				<?php foreach ( $statuses as $status_key => $status_label ) : ?>
					<button type="button" class="pc-note-status<?php echo $status === $status_key ? ' is-active' : ''; ?>" data-status="<?php echo esc_attr( $status_key ); ?>">
						<?php echo esc_html( $status_label ); ?>
					</button>
				<?php endforeach; ?>
			</div>
			<div class="pc-note-rating" aria-label="<?php esc_attr_e( 'Rating', 'post-collection' ); ?>" data-rating="<?php echo esc_attr( $rating ); ?>">
				<?php for ( $i = 1; $i <= 5; $i++ ) : ?>
					<button type="button" class="pc-note-star<?php echo $i <= $rating ? ' is-active' : ''; ?>" data-rating="<?php echo esc_attr( $i ); ?>" aria-label="<?php echo esc_attr( sprintf( __( '%d stars', 'post-collection' ), $i ) ); ?>">
						<?php echo $i <= $rating ? '&#9733;' : '&#9734;'; ?>
					</button>
				<?php endfor; ?>
			</div>
		</div>

		<label class="screen-reader-text" for="pc-review-notes-<?php echo esc_attr( $article['id'] ); ?>"><?php esc_html_e( 'Article notes', 'post-collection' ); ?></label>
		<textarea id="pc-review-notes-<?php echo esc_attr( $article['id'] ); ?>" class="pc-note-text" rows="3" placeholder="<?php esc_attr_e( 'Add your notes...', 'post-collection' ); ?>"><?php echo esc_textarea( $article['notes'] ); ?></textarea>

		<div class="pc-article-notes-actions">
			<button type="button" class="pc-note-save"><?php esc_html_e( 'Save', 'post-collection' ); ?></button>
			<span class="pc-note-save-status" aria-live="polite"></span>
		</div>
	</article>
	<?php
};
?>
<!DOCTYPE html>
<html <?php echo wp_app_language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php echo wp_app_title( __( 'Review Articles', 'post-collection' ) ); ?></title>
	<?php wp_app_head(); ?>
</head>
<body <?php body_class( 'wp-app-body post-collection-app pc-review-page' ); ?>>
	<?php wp_app_body_open(); ?>
	<header class="pc-shell pc-detail-header">
		<div class="pc-breadcrumb"><a href="<?php echo esc_url( $app->get_home_url() ); ?>"><?php esc_html_e( 'Collections', 'post-collection' ); ?></a></div>
		<p class="pc-kicker"><?php esc_html_e( 'Article notes', 'post-collection' ); ?></p>
		<h1><?php esc_html_e( 'Review Articles', 'post-collection' ); ?></h1>
	</header>

	<main class="pc-shell pc-review-shell" data-nonce="<?php echo esc_attr( $nonce ); ?>" data-ajax-action="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" data-statuses="<?php echo esc_attr( wp_json_encode( $statuses ) ); ?>">
		<div class="pc-review-list" data-review-list="review">
			<?php foreach ( $review_articles as $article ) : ?>
				<?php $render_article( $article ); ?>
			<?php endforeach; ?>
		</div>
		<?php if ( empty( $review_articles ) ) : ?>
			<p class="pc-review-empty"><?php esc_html_e( 'No articles are waiting for review.', 'post-collection' ); ?></p>
		<?php endif; ?>
		<?php if ( $has_more_articles ) : ?>
			<button type="button" class="pc-review-load-more" data-type="queue" data-list="review" data-offset="<?php echo esc_attr( count( $review_articles ) ); ?>"><?php esc_html_e( 'Load more', 'post-collection' ); ?></button>
		<?php endif; ?>
	</main>
	<?php wp_app_body_close(); ?>
</body>
</html>
