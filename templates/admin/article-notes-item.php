<?php
/**
 * Single Article Notes Item Template
 *
 * Used in both pending and reviewed lists. Parent CSS class controls visibility
 * of elements like the checkbox (.post-collection-reviewed-list) and archive button.
 *
 * @package Post_Collection
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="post-collection-article-header">
	<label class="post-collection-select-article">
		<input type="checkbox" name="selected_articles[]" value="<?php echo esc_attr( $article['id'] ); ?>">
		<a href="<?php echo esc_url( $article['permalink'] ); ?>" class="post-collection-article-title" target="_blank">
			<?php echo esc_html( $article['title'] ); ?>
		</a>
	</label>
	<span class="post-collection-article-meta">
		<?php echo esc_html( $article['author'] ); ?>
		<?php if ( ! empty( $article['sent_date'] ) ) : ?>
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

	<button type="button" class="post-collection-archive-btn" title="<?php esc_attr_e( 'Archive - hide from this list', 'post-collection' ); ?>">
		<?php esc_html_e( 'Archive', 'post-collection' ); ?>
	</button>
</div>

<div class="post-collection-notes-wrapper">
	<textarea
		class="post-collection-notes"
		placeholder="<?php esc_attr_e( 'Add your notes...', 'post-collection' ); ?>"
		rows="2"><?php echo esc_textarea( $article['notes'] ); ?></textarea>
	<div class="post-collection-notes-actions">
		<button type="button" class="button button-small post-collection-save-notes-btn">
			<?php esc_html_e( 'Save', 'post-collection' ); ?>
		</button>
		<span class="post-collection-save-status"></span>
	</div>
</div>
