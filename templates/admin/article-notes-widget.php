<?php
/**
 * Article Notes Dashboard Widget Template
 *
 * @package Post_Collection
 */

$pending_articles = isset( $args['pending_articles'] ) ? $args['pending_articles'] : array();
$has_more_pending = isset( $args['has_more_pending'] ) ? $args['has_more_pending'] : false;
$unread_articles = isset( $args['unread_articles'] ) ? $args['unread_articles'] : array();
$has_more_unread = isset( $args['has_more_unread'] ) ? $args['has_more_unread'] : false;
$reviewed_articles = isset( $args['reviewed_articles'] ) ? $args['reviewed_articles'] : array();
$nonce = isset( $args['nonce'] ) ? $args['nonce'] : '';
$statuses = \PostCollection\Article_Notes::get_statuses();
?>

<div class="ereader-article-notes-widget" data-nonce="<?php echo esc_attr( $nonce ); ?>">
	<?php if ( empty( $pending_articles ) && empty( $unread_articles ) && empty( $reviewed_articles ) ) : ?>
		<p class="ereader-no-articles">
			<?php esc_html_e( 'No articles have been sent to your e-reader yet.', 'send-to-e-reader' ); ?>
		</p>
	<?php else : ?>
		<div class="ereader-widget-tabs">
			<button type="button" class="ereader-tab active" data-tab="pending">
				<?php esc_html_e( 'Pending', 'send-to-e-reader' ); ?>
			</button>
			<button type="button" class="ereader-tab" data-tab="unread">
				<?php esc_html_e( 'Unread', 'send-to-e-reader' ); ?>
			</button>
			<button type="button" class="ereader-tab" data-tab="reviewed">
				<?php esc_html_e( 'Reviewed', 'send-to-e-reader' ); ?>
			</button>
		</div>

		<div class="ereader-tab-content active" data-tab="pending">
			<?php if ( empty( $pending_articles ) ) : ?>
				<p class="ereader-no-articles">
					<?php esc_html_e( 'No new articles to review.', 'send-to-e-reader' ); ?>
				</p>
			<?php else : ?>
				<p class="ereader-tab-hint">
				<?php esc_html_e( 'Mark your reading status. Articles move to Unread or Reviewed tab.', 'send-to-e-reader' ); ?>
			</p>
			<ul class="ereader-article-list ereader-pending-list">
					<?php foreach ( $pending_articles as $article ) : ?>
						<li class="ereader-article-item" data-article-id="<?php echo esc_attr( $article['id'] ); ?>">
							<div class="ereader-article-header">
								<a href="<?php echo esc_url( $article['permalink'] ); ?>" class="ereader-article-title" target="_blank">
									<?php echo esc_html( $article['title'] ); ?>
								</a>
								<span class="ereader-article-meta">
									<?php echo esc_html( $article['author'] ); ?>
									<?php if ( $article['sent_date'] ) : ?>
										&bull; <?php echo esc_html( $article['sent_date'] ); ?>
									<?php endif; ?>
								</span>
							</div>

							<div class="ereader-article-controls">
								<div class="ereader-status-buttons">
									<?php foreach ( $statuses as $status_key => $status_label ) : ?>
										<button type="button"
											class="ereader-status-btn <?php echo $article['status'] === $status_key ? 'active' : ''; ?>"
											data-status="<?php echo esc_attr( $status_key ); ?>"
											title="<?php echo esc_attr( $status_label ); ?>">
											<?php echo esc_html( $status_label ); ?>
										</button>
									<?php endforeach; ?>
								</div>

								<div class="ereader-rating" data-rating="<?php echo esc_attr( $article['rating'] ); ?>">
									<?php for ( $i = 1; $i <= 5; $i++ ) : ?>
										<button type="button"
											class="ereader-star <?php echo $i <= $article['rating'] ? 'active' : ''; ?>"
											data-rating="<?php echo esc_attr( $i ); ?>"
											title="<?php echo esc_attr( sprintf( __( '%d stars', 'send-to-e-reader' ), $i ) ); ?>">
											<?php echo $i <= $article['rating'] ? '&#9733;' : '&#9734;'; ?>
										</button>
									<?php endfor; ?>
								</div>
							</div>

							<div class="ereader-notes-wrapper">
								<textarea
									class="ereader-notes"
									placeholder="<?php esc_attr_e( 'Add your notes...', 'send-to-e-reader' ); ?>"
									rows="2"><?php echo esc_textarea( $article['notes'] ); ?></textarea>
							</div>

							<div class="ereader-save-status"></div>
						</li>
					<?php endforeach; ?>
				</ul>
				<?php if ( $has_more_pending ) : ?>
					<div class="ereader-load-more-section">
						<button type="button" class="button ereader-load-more-btn" data-type="pending" data-offset="<?php echo count( $pending_articles ); ?>">
							<?php esc_html_e( 'Load more', 'send-to-e-reader' ); ?>
						</button>
					</div>
				<?php endif; ?>
			<?php endif; ?>
		</div>

		<div class="ereader-tab-content" data-tab="unread">
			<?php if ( empty( $unread_articles ) ) : ?>
				<p class="ereader-no-articles">
					<?php esc_html_e( 'No unread articles.', 'send-to-e-reader' ); ?>
				</p>
			<?php else : ?>
				<p class="ereader-tab-hint">
				<?php esc_html_e( 'Articles marked as "Not read yet". Mark as Read or Skipped to move to Reviewed.', 'send-to-e-reader' ); ?>
			</p>
			<ul class="ereader-article-list ereader-unread-list">
					<?php foreach ( $unread_articles as $article ) : ?>
						<li class="ereader-article-item" data-article-id="<?php echo esc_attr( $article['id'] ); ?>">
							<div class="ereader-article-header">
								<a href="<?php echo esc_url( $article['permalink'] ); ?>" class="ereader-article-title" target="_blank">
									<?php echo esc_html( $article['title'] ); ?>
								</a>
								<span class="ereader-article-meta">
									<?php echo esc_html( $article['author'] ); ?>
									<?php if ( $article['sent_date'] ) : ?>
										&bull; <?php echo esc_html( $article['sent_date'] ); ?>
									<?php endif; ?>
								</span>
							</div>

							<div class="ereader-article-controls">
								<div class="ereader-status-buttons">
									<?php foreach ( $statuses as $status_key => $status_label ) : ?>
										<button type="button"
											class="ereader-status-btn <?php echo $article['status'] === $status_key ? 'active' : ''; ?>"
											data-status="<?php echo esc_attr( $status_key ); ?>"
											title="<?php echo esc_attr( $status_label ); ?>">
											<?php echo esc_html( $status_label ); ?>
										</button>
									<?php endforeach; ?>
								</div>

								<div class="ereader-rating" data-rating="<?php echo esc_attr( $article['rating'] ); ?>">
									<?php for ( $i = 1; $i <= 5; $i++ ) : ?>
										<button type="button"
											class="ereader-star <?php echo $i <= $article['rating'] ? 'active' : ''; ?>"
											data-rating="<?php echo esc_attr( $i ); ?>"
											title="<?php echo esc_attr( sprintf( __( '%d stars', 'send-to-e-reader' ), $i ) ); ?>">
											<?php echo $i <= $article['rating'] ? '&#9733;' : '&#9734;'; ?>
										</button>
									<?php endfor; ?>
								</div>
							</div>

							<div class="ereader-notes-wrapper">
								<textarea
									class="ereader-notes"
									placeholder="<?php esc_attr_e( 'Add your notes...', 'send-to-e-reader' ); ?>"
									rows="2"><?php echo esc_textarea( $article['notes'] ); ?></textarea>
							</div>

							<div class="ereader-save-status"></div>
						</li>
					<?php endforeach; ?>
				</ul>
				<?php if ( $has_more_unread ) : ?>
					<div class="ereader-load-more-section">
						<button type="button" class="button ereader-load-more-btn" data-type="unread" data-offset="<?php echo count( $unread_articles ); ?>">
							<?php esc_html_e( 'Load more', 'send-to-e-reader' ); ?>
						</button>
					</div>
				<?php endif; ?>
			<?php endif; ?>
		</div>

		<div class="ereader-tab-content" data-tab="reviewed">
			<?php if ( empty( $reviewed_articles ) ) : ?>
				<p class="ereader-no-articles">
					<?php esc_html_e( 'No reviewed articles yet.', 'send-to-e-reader' ); ?>
				</p>
			<?php else : ?>
				<p class="ereader-tab-hint">
				<?php esc_html_e( 'Select articles to create a post, or Archive to hide from this list.', 'send-to-e-reader' ); ?>
			</p>
			<ul class="ereader-article-list ereader-reviewed-list">
					<?php foreach ( $reviewed_articles as $article ) : ?>
						<li class="ereader-article-item" data-article-id="<?php echo esc_attr( $article['id'] ); ?>">
							<div class="ereader-article-header">
								<label class="ereader-select-article">
									<input type="checkbox" name="selected_articles[]" value="<?php echo esc_attr( $article['id'] ); ?>">
									<a href="<?php echo esc_url( $article['permalink'] ); ?>" class="ereader-article-title" target="_blank">
										<?php echo esc_html( $article['title'] ); ?>
									</a>
								</label>
								<span class="ereader-article-meta">
									<?php echo esc_html( $article['author'] ); ?>
									<?php if ( $article['rating'] > 0 ) : ?>
										&bull; <?php echo str_repeat( '&#9733;', $article['rating'] ); ?>
									<?php endif; ?>
								</span>
							</div>

							<div class="ereader-article-controls">
								<div class="ereader-status-buttons">
									<?php foreach ( $statuses as $status_key => $status_label ) : ?>
										<button type="button"
											class="ereader-status-btn <?php echo $article['status'] === $status_key ? 'active' : ''; ?>"
											data-status="<?php echo esc_attr( $status_key ); ?>"
											title="<?php echo esc_attr( $status_label ); ?>">
											<?php echo esc_html( $status_label ); ?>
										</button>
									<?php endforeach; ?>
								</div>

								<div class="ereader-rating" data-rating="<?php echo esc_attr( $article['rating'] ); ?>">
									<?php for ( $i = 1; $i <= 5; $i++ ) : ?>
										<button type="button"
											class="ereader-star <?php echo $i <= $article['rating'] ? 'active' : ''; ?>"
											data-rating="<?php echo esc_attr( $i ); ?>"
											title="<?php echo esc_attr( sprintf( __( '%d stars', 'send-to-e-reader' ), $i ) ); ?>">
											<?php echo $i <= $article['rating'] ? '&#9733;' : '&#9734;'; ?>
										</button>
									<?php endfor; ?>
								</div>

								<button type="button" class="ereader-archive-btn" title="<?php esc_attr_e( 'Archive - hide from this list', 'send-to-e-reader' ); ?>">
									<?php esc_html_e( 'Archive', 'send-to-e-reader' ); ?>
								</button>
							</div>

							<?php if ( ! empty( $article['notes'] ) ) : ?>
								<div class="ereader-notes-preview">
									<?php echo esc_html( wp_trim_words( $article['notes'], 20 ) ); ?>
								</div>
							<?php endif; ?>

							<div class="ereader-notes-wrapper" style="display: none;">
								<textarea
									class="ereader-notes"
									placeholder="<?php esc_attr_e( 'Add your notes...', 'send-to-e-reader' ); ?>"
									rows="2"><?php echo esc_textarea( $article['notes'] ); ?></textarea>
							</div>

							<div class="ereader-save-status"></div>
						</li>
					<?php endforeach; ?>
				</ul>

				<div class="ereader-create-post-section">
					<input type="text"
						id="ereader-post-title"
						class="ereader-post-title-input"
						placeholder="<?php esc_attr_e( 'Post title (optional)', 'send-to-e-reader' ); ?>">
					<button type="button" class="button button-primary ereader-create-post-btn">
						<?php esc_html_e( 'Create Post from Selected', 'send-to-e-reader' ); ?>
					</button>
				</div>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</div>
