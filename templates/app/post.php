<?php
/**
 * Frontend app post detail view.
 *
 * @package Post_Collection
 */

defined( 'ABSPATH' ) || exit;

$app = \PostCollection\Post_Collection_App::instance();
if ( ! $app ) {
	status_header( 500 );
	return;
}

$username = wp_app_get_route_var( 'collection' );
$post_id  = absint( wp_app_get_route_var( 'post_id' ) );
$collection = $app->get_collection_by_username( $username );
$post       = $collection ? $app->get_visible_post( $post_id, $collection ) : null;

if ( ! $collection || ! $post || ! $app->can_view_collection( $collection ) ) {
	status_header( 404 );
	include __DIR__ . '/404.php';
	return;
}

$mode             = $app->get_collection_mode( $collection );
$source_url       = $app->get_source_url( $post );
$host             = $app->get_source_host( $post );
$embed_html       = $app->get_post_description_embed_html( $post, 'detail' );
$terms            = $app->get_post_terms( $post );
$word_count_label = $app->get_post_word_count_label( $post );
$read_time_label  = $app->get_post_read_time_label( $post );
$can_edit_notes   = current_user_can( 'edit_posts' );
$note             = $can_edit_notes ? $app->get_post_collection()->get_article_notes()->get_note( $post->ID ) : null;
$statuses         = $app->get_article_statuses();
$read_status      = $note && ! empty( $note['status'] ) ? $note['status'] : $app->get_article_note_status( $post );
$rating           = $note ? (int) $note['rating'] : 0;
$notes            = $note ? $note['notes'] : '';
$notes_nonce      = $can_edit_notes ? wp_create_nonce( 'post-collection-article-notes' ) : '';
$back_label       = 'bookmarks' === $mode ? __( 'Back to Bookmarks', 'post-collection' ) : __( 'Back to Collection', 'post-collection' );
?>
<!DOCTYPE html>
<html <?php wp_app_language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php wp_app_the_title( get_the_title( $post ) ); ?></title>
	<?php wp_app_head(); ?>
</head>
<body <?php body_class( 'wp-app-body post-collection-app pc-post-detail-page pc-mode-' . $mode ); ?>>
	<?php wp_app_body_open(); ?>
	<header class="pc-shell pc-detail-header">
		<div class="pc-breadcrumb">
			<a href="<?php echo esc_url( $app->get_home_url() ); ?>"><?php esc_html_e( 'Collections', 'post-collection' ); ?></a>
			<span>/</span>
			<a href="<?php echo esc_url( $app->get_collection_url( $collection ) ); ?>"><?php echo esc_html( $collection->name ); ?></a>
		</div>
		<p class="pc-source"><?php echo esc_html( $host ); ?></p>
		<h1><?php echo esc_html( get_the_title( $post ) ); ?></h1>
		<div class="pc-detail-meta">
			<time datetime="<?php echo esc_attr( get_the_date( DATE_W3C, $post ) ); ?>"><?php echo esc_html( get_the_date( '', $post ) ); ?></time>
			<span class="pc-reading-meta"><?php echo esc_html( $word_count_label ); ?></span>
			<span class="pc-reading-meta"><?php echo esc_html( $read_time_label ); ?></span>
			<?php $app->render_article_note_status_toggle( $post, $read_status ); ?>
			<?php if ( 'private' === $post->post_status && $app->can_manage_collections() ) : ?>
				<span><?php esc_html_e( 'Private', 'post-collection' ); ?></span>
			<?php endif; ?>
			<?php if ( $app->can_manage_collections() ) : ?>
				<a href="<?php echo esc_url( get_edit_post_link( $post->ID ) ); ?>"><?php esc_html_e( 'Edit', 'post-collection' ); ?></a>
			<?php endif; ?>
		</div>
		<div class="pc-detail-actions">
			<a class="pc-button pc-button-primary" href="<?php echo esc_url( $source_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open Original', 'post-collection' ); ?></a>
			<a class="pc-button" href="<?php echo esc_url( $app->get_collection_url( $collection ) ); ?>"><?php echo esc_html( $back_label ); ?></a>
			<?php $app->render_item_actions( $post, 'detail' ); ?>
		</div>
		<?php if ( ! empty( $terms ) ) : ?>
			<nav class="pc-tag-strip pc-detail-tags" aria-label="<?php esc_attr_e( 'Tags', 'post-collection' ); ?>">
				<?php foreach ( $terms as $term ) : ?>
					<a href="<?php echo esc_url( add_query_arg( 'pc-tag', $term->slug, $app->get_collection_url( $collection ) ) ); ?>"><?php echo esc_html( $term->name ); ?></a>
				<?php endforeach; ?>
			</nav>
		<?php endif; ?>
	</header>

	<main class="pc-shell pc-detail-layout">
		<article id="collected-post-content" class="pc-detail-content" aria-labelledby="collected-post-content-heading" data-ai-assistant-important>
			<h2 id="collected-post-content-heading" class="screen-reader-text"><?php esc_html_e( 'Collected post content', 'post-collection' ); ?></h2>
			<?php if ( $embed_html ) : ?>
				<?php echo $embed_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php endif; ?>
			<?php if ( '' !== trim( $post->post_content ) ) : ?>
				<?php echo apply_filters( 'the_content', $post->post_content ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- the_content is a WordPress core filter and returns rendered post HTML. ?>
			<?php endif; ?>
		</article>
		<?php if ( $can_edit_notes ) : ?>
			<section class="pc-article-notes" data-article-id="<?php echo esc_attr( $post->ID ); ?>" data-nonce="<?php echo esc_attr( $notes_nonce ); ?>" data-ajax-action="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>">
				<div class="pc-article-notes-controls">
					<div class="pc-note-statuses" aria-label="<?php esc_attr_e( 'Reading status', 'post-collection' ); ?>">
						<?php foreach ( $statuses as $status_key => $status_label ) : ?>
							<button type="button" class="pc-note-status<?php echo $read_status === $status_key ? ' is-active' : ''; ?>" data-status="<?php echo esc_attr( $status_key ); ?>">
								<?php echo esc_html( $status_label ); ?>
							</button>
						<?php endforeach; ?>
					</div>
					<div class="pc-note-rating" aria-label="<?php esc_attr_e( 'Rating', 'post-collection' ); ?>" data-rating="<?php echo esc_attr( $rating ); ?>">
						<?php for ( $i = 1; $i <= 5; $i++ ) : ?>
							<?php
							// translators: %d is a rating expressed as a number of stars, from 1 to 5.
							$star_label = sprintf( __( '%d stars', 'post-collection' ), $i );
							?>
							<button type="button" class="pc-note-star<?php echo $i <= $rating ? ' is-active' : ''; ?>" data-rating="<?php echo esc_attr( $i ); ?>" aria-label="<?php echo esc_attr( $star_label ); ?>">
								<?php echo $i <= $rating ? '&#9733;' : '&#9734;'; ?>
							</button>
						<?php endfor; ?>
					</div>
				</div>
				<label class="screen-reader-text" for="pc-article-notes-<?php echo esc_attr( $post->ID ); ?>"><?php esc_html_e( 'Article notes', 'post-collection' ); ?></label>
				<textarea id="pc-article-notes-<?php echo esc_attr( $post->ID ); ?>" class="pc-note-text" rows="5" placeholder="<?php esc_attr_e( 'Add your notes...', 'post-collection' ); ?>"><?php echo esc_textarea( $notes ); ?></textarea>
				<div class="pc-article-notes-actions">
					<button type="button" class="pc-note-save"><?php esc_html_e( 'Save', 'post-collection' ); ?></button>
					<span class="pc-note-save-status" aria-live="polite"></span>
				</div>
			</section>
		<?php endif; ?>
		<nav class="pc-detail-footer-actions" aria-label="<?php esc_attr_e( 'Post navigation', 'post-collection' ); ?>">
			<a class="pc-button" href="<?php echo esc_url( $app->get_collection_url( $collection ) ); ?>"><?php echo esc_html( $back_label ); ?></a>
		</nav>
	</main>
	<?php wp_app_body_close(); ?>
</body>
</html>
