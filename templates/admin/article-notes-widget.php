<?php
/**
 * Article Notes Dashboard Widget Template
 *
 * @package Post_Collection
 */

defined( 'ABSPATH' ) || exit;

$pending_articles = isset( $args['pending_articles'] ) ? $args['pending_articles'] : array();
$has_more_pending = isset( $args['has_more_pending'] ) ? $args['has_more_pending'] : false;
$reviewed_articles = isset( $args['reviewed_articles'] ) ? $args['reviewed_articles'] : array();
$nonce = isset( $args['nonce'] ) ? $args['nonce'] : '';
$statuses = \PostCollection\Article_Notes::get_statuses();
?>

<div class="post-collection-article-notes-widget" data-nonce="<?php echo esc_attr( $nonce ); ?>">
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
						<li class="post-collection-article-item" data-article-id="<?php echo esc_attr( $article['id'] ); ?>">
							<div class="post-collection-article-header">
								<a href="<?php echo esc_url( $article['permalink'] ); ?>" class="post-collection-article-title" target="_blank">
									<?php echo esc_html( $article['title'] ); ?>
								</a>
								<span class="post-collection-article-meta">
									<?php echo esc_html( $article['author'] ); ?>
									<?php if ( $article['sent_date'] ) : ?>
										&bull; <?php echo esc_html( $article['sent_date'] ); ?>
									<?php endif; ?>
								</span>
							</div>

							<div class="post-collection-article-controls">
								<div class="post-collection-status-buttons">
									<?php foreach ( $statuses as $status_key => $status_label ) : ?>
										<button type="button"
											class="post-collection-status-btn <?php echo ( $article['note_id'] && $article['status'] === $status_key ) ? 'active' : ''; ?>"
											data-status="<?php echo esc_attr( $status_key ); ?>"
											title="<?php echo esc_attr( $status_label ); ?>">
											<?php echo esc_html( $status_label ); ?>
										</button>
									<?php endforeach; ?>
								</div>

								<div class="post-collection-rating" data-rating="<?php echo esc_attr( $article['rating'] ); ?>">
									<?php for ( $i = 1; $i <= 5; $i++ ) : ?>
										<button type="button"
											class="post-collection-star <?php echo $i <= $article['rating'] ? 'active' : ''; ?>"
											data-rating="<?php echo esc_attr( $i ); ?>"
											title="<?php echo esc_attr( sprintf( __( '%d stars', 'post-collection' ), $i ) ); ?>">
											<?php echo $i <= $article['rating'] ? '&#9733;' : '&#9734;'; ?>
										</button>
									<?php endfor; ?>
								</div>
							</div>

							<div class="post-collection-notes-wrapper">
								<textarea
									class="post-collection-notes"
									placeholder="<?php esc_attr_e( 'Add your notes...', 'post-collection' ); ?>"
									rows="2"><?php echo esc_textarea( $article['notes'] ); ?></textarea>
								<button type="button" class="button button-small post-collection-save-notes-btn">
									<?php esc_html_e( 'Save', 'post-collection' ); ?>
								</button>
							</div>

							<div class="post-collection-save-status"></div>
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
						<li class="post-collection-article-item" data-article-id="<?php echo esc_attr( $article['id'] ); ?>">
							<div class="post-collection-article-header">
								<label class="post-collection-select-article">
									<input type="checkbox" name="selected_articles[]" value="<?php echo esc_attr( $article['id'] ); ?>">
									<a href="<?php echo esc_url( $article['permalink'] ); ?>" class="post-collection-article-title" target="_blank">
										<?php echo esc_html( $article['title'] ); ?>
									</a>
								</label>
								<span class="post-collection-article-meta">
									<?php echo esc_html( $article['author'] ); ?>
									<?php if ( $article['rating'] > 0 ) : ?>
										&bull; <?php echo str_repeat( '&#9733;', $article['rating'] ); ?>
									<?php endif; ?>
								</span>
							</div>

							<div class="post-collection-article-controls">
								<div class="post-collection-status-buttons">
									<?php foreach ( $statuses as $status_key => $status_label ) : ?>
										<button type="button"
											class="post-collection-status-btn <?php echo $article['status'] === $status_key ? 'active' : ''; ?>"
											data-status="<?php echo esc_attr( $status_key ); ?>"
											title="<?php echo esc_attr( $status_label ); ?>">
											<?php echo esc_html( $status_label ); ?>
										</button>
									<?php endforeach; ?>
								</div>

								<div class="post-collection-rating" data-rating="<?php echo esc_attr( $article['rating'] ); ?>">
									<?php for ( $i = 1; $i <= 5; $i++ ) : ?>
										<button type="button"
											class="post-collection-star <?php echo $i <= $article['rating'] ? 'active' : ''; ?>"
											data-rating="<?php echo esc_attr( $i ); ?>"
											title="<?php echo esc_attr( sprintf( __( '%d stars', 'post-collection' ), $i ) ); ?>">
											<?php echo $i <= $article['rating'] ? '&#9733;' : '&#9734;'; ?>
										</button>
									<?php endfor; ?>
								</div>

								<button type="button" class="post-collection-archive-btn" title="<?php esc_attr_e( 'Archive - hide from this list', 'post-collection' ); ?>">
									<?php esc_html_e( 'Archive', 'post-collection' ); ?>
								</button>
							</div>

							<?php if ( ! empty( $article['notes'] ) ) : ?>
								<div class="post-collection-notes-preview">
									<?php echo esc_html( wp_trim_words( $article['notes'], 20 ) ); ?>
								</div>
							<?php endif; ?>

							<div class="post-collection-notes-wrapper" style="display: none;">
								<textarea
									class="post-collection-notes"
									placeholder="<?php esc_attr_e( 'Add your notes...', 'post-collection' ); ?>"
									rows="2"><?php echo esc_textarea( $article['notes'] ); ?></textarea>
								<button type="button" class="button button-small post-collection-save-notes-btn">
									<?php esc_html_e( 'Save', 'post-collection' ); ?>
								</button>
							</div>

							<div class="post-collection-save-status"></div>
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
