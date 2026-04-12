<?php
/**
 * Article Notes Frontend Template
 *
 * Displayed below a single post on the Friends frontend.
 *
 * @package Post_Collection
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables extracted from $args.
$article  = isset( $args['article'] ) ? $args['article'] : array();
$statuses = isset( $args['statuses'] ) ? $args['statuses'] : array();
$nonce    = isset( $args['nonce'] ) ? $args['nonce'] : '';

if ( empty( $article ) ) {
	return;
}
?>
<div class="post-collection-frontend-notes post-collection-article-item" data-article-id="<?php echo esc_attr( $article['id'] ); ?>" data-nonce="<?php echo esc_attr( $nonce ); ?>">
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
					<?php /* translators: %d: number of stars for rating */ ?>
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
			rows="3"><?php echo esc_textarea( $article['notes'] ); ?></textarea>
		<div class="post-collection-notes-actions">
			<button type="button" class="button button-small post-collection-save-notes-btn">
				<?php esc_html_e( 'Save', 'post-collection' ); ?>
			</button>
			<span class="post-collection-save-status"></span>
		</div>
	</div>
</div>
