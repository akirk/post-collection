<?php
/**
 * Article Notes Dashboard Widget Template
 *
 * @package Post_Collection
 */

defined( 'ABSPATH' ) || exit;

$pending_articles   = isset( $args['pending_articles'] ) ? $args['pending_articles'] : array();
$has_more_pending   = isset( $args['has_more_pending'] ) ? $args['has_more_pending'] : false;
$reviewed_articles  = isset( $args['reviewed_articles'] ) ? $args['reviewed_articles'] : array();
$random_remembered  = isset( $args['random_remembered'] ) ? $args['random_remembered'] : null;
$nonce              = isset( $args['nonce'] ) ? $args['nonce'] : '';
$statuses           = \PostCollection\Article_Notes::get_statuses();
?>

<div class="post-collection-article-notes-widget" data-nonce="<?php echo esc_attr( $nonce ); ?>">
	<?php if ( $random_remembered ) : ?>
		<div class="post-collection-remember">
			<div class="post-collection-remember-header">
				<span class="post-collection-remember-label"><?php esc_html_e( 'From your notes:', 'post-collection' ); ?></span>
				<a href="#" class="post-collection-remember-another"><?php esc_html_e( 'Show another', 'post-collection' ); ?> &rarr;</a>
			</div>
			<a href="<?php echo esc_url( $random_remembered['permalink'] ); ?>" class="post-collection-remember-title" target="_blank">
				<?php echo esc_html( $random_remembered['title'] ); ?>
			</a>
			<span class="post-collection-remember-meta"><?php echo esc_html( $random_remembered['author'] ); ?></span>
			<blockquote class="post-collection-remember-notes">
				<?php echo wp_kses_post( $random_remembered['notes'] ); ?>
			</blockquote>
			<?php if ( ! empty( $random_remembered['content'] ) ) : ?>
				<details class="post-collection-article-preview">
					<summary><?php esc_html_e( 'Show article', 'post-collection' ); ?></summary>
					<div class="post-collection-article-preview-content">
						<?php echo $random_remembered['content']; ?>
					</div>
				</details>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<?php if ( empty( $pending_articles ) && empty( $reviewed_articles ) ) : ?>
		<p class="post-collection-no-articles">
			<?php esc_html_e( 'No articles in your collection yet. Collect posts from the web to start adding notes and reviews.', 'post-collection' ); ?>
		</p>
	<?php else : ?>
		<div class="post-collection-widget-tabs">
			<button type="button" class="post-collection-tab active" data-tab="pending">
				<?php esc_html_e( 'Pending', 'post-collection' ); ?>
			</button>
			<button type="button" class="post-collection-tab" data-tab="reviewed">
				<?php esc_html_e( 'Reviewed', 'post-collection' ); ?>
			</button>
		</div>

		<div class="post-collection-tab-content active" data-tab="pending">
			<?php if ( empty( $pending_articles ) ) : ?>
				<p class="post-collection-no-articles">
					<?php esc_html_e( 'No articles to review.', 'post-collection' ); ?>
				</p>
			<?php else : ?>
				<p class="post-collection-tab-hint">
					<?php esc_html_e( 'Mark your reading status and add notes. Articles marked as Read or Skipped will move to the Reviewed tab on next load.', 'post-collection' ); ?>
				</p>
				<ul class="post-collection-article-list post-collection-pending-list">
					<?php foreach ( $pending_articles as $article ) : ?>
						<?php
						$reviewed_class = ( $article['note_id'] && in_array( $article['status'], array( 'read', 'skipped' ), true ) ) ? ' post-collection-reviewed' : '';
						?>
						<li class="post-collection-article-item<?php echo esc_attr( $reviewed_class ); ?>" data-article-id="<?php echo esc_attr( $article['id'] ); ?>">
							<?php require __DIR__ . '/article-notes-item.php'; ?>
						</li>
					<?php endforeach; ?>
				</ul>
				<?php if ( $has_more_pending ) : ?>
					<div class="post-collection-load-more-section">
						<button type="button" class="button post-collection-load-more-btn" data-type="pending" data-offset="<?php echo count( $pending_articles ); ?>">
							<?php esc_html_e( 'Load more', 'post-collection' ); ?>
						</button>
					</div>
				<?php endif; ?>
			<?php endif; ?>
		</div>

		<div class="post-collection-tab-content" data-tab="reviewed">
			<?php if ( empty( $reviewed_articles ) ) : ?>
				<p class="post-collection-no-articles">
					<?php esc_html_e( 'No reviewed articles yet.', 'post-collection' ); ?>
				</p>
			<?php else : ?>
				<p class="post-collection-tab-hint">
					<?php esc_html_e( 'Select articles to create a post, or Archive to hide from this list.', 'post-collection' ); ?>
				</p>
				<ul class="post-collection-article-list post-collection-reviewed-list">
					<?php foreach ( $reviewed_articles as $article ) : ?>
						<li class="post-collection-article-item post-collection-reviewed" data-article-id="<?php echo esc_attr( $article['id'] ); ?>">
							<?php require __DIR__ . '/article-notes-item.php'; ?>
						</li>
					<?php endforeach; ?>
				</ul>

				<div class="post-collection-create-post-section">
					<input type="text"
						id="post-collection-post-title"
						class="post-collection-post-title-input"
						placeholder="<?php esc_attr_e( 'Post title (optional)', 'post-collection' ); ?>">
					<button type="button" class="button button-primary post-collection-create-post-btn">
						<?php esc_html_e( 'Create Post from Selected', 'post-collection' ); ?>
					</button>
				</div>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</div>
