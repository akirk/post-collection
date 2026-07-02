<?php
/**
 * Frontend app collection view.
 *
 * @package Post_Collection
 */

defined( 'ABSPATH' ) || exit;

$app = \PostCollection\Post_Collection_App::instance();
if ( ! $app ) {
	status_header( 500 );
	return;
}

$collection_slug = wp_app_get_route_var( 'collection' );
$collection      = $app->get_collection_by_username( $collection_slug );
if ( ! $collection || ! $app->can_view_collection( $collection ) ) {
	status_header( 404 );
	include __DIR__ . '/404.php';
	return;
}

$mode             = $app->get_collection_mode( $collection );
$view             = $app->get_collection_view( $collection );
$views            = $app->get_available_views();
$article_statuses = $app->get_article_statuses();
$quick_edit_post_id = $app->get_quick_edit_post_id();
$quick_edit = $app->is_quick_edit_mode() && 'links' === $view;
$query      = $app->query_collection_posts( $collection );
$terms      = $app->get_collection_terms( $collection );
$search     = isset( $_GET['pc-search'] ) ? sanitize_text_field( wp_unslash( $_GET['pc-search'] ) ) : '';
$active_tag = isset( $_GET['pc-tag'] ) ? sanitize_title( wp_unslash( $_GET['pc-tag'] ) ) : '';
$page       = isset( $_GET['pc-page'] ) ? max( 1, absint( wp_unslash( $_GET['pc-page'] ) ) ) : 1;
$base_url   = $app->get_collection_url( $collection );

$page_args = array();
if ( $view ) {
	$page_args['pc-view'] = $view;
}
if ( $quick_edit ) {
	$page_args['pc-edit'] = $quick_edit_post_id;
}
if ( '' !== $search ) {
	$page_args['pc-search'] = $search;
}
if ( '' !== $active_tag ) {
	$page_args['pc-tag'] = $active_tag;
}
?>
<!DOCTYPE html>
<html <?php echo wp_app_language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php echo wp_app_title( $collection->name ); ?></title>
	<?php wp_app_head(); ?>
</head>
<body <?php body_class( 'wp-app-body post-collection-app pc-mode-' . $mode . ' pc-view-' . $view . ( $quick_edit ? ' pc-quick-edit' : '' ) ); ?>>
	<?php wp_app_body_open(); ?>
	<header class="pc-shell pc-collection-header">
		<div class="pc-breadcrumb"><a href="<?php echo esc_url( $app->get_home_url() ); ?>"><?php esc_html_e( 'Collections', 'post-collection' ); ?></a></div>
		<div class="pc-collection-title-row">
			<div>
				<p class="pc-kicker"><?php echo esc_html( 'bookmarks' === $mode ? __( 'Bookmark collection', 'post-collection' ) : __( 'Post collection', 'post-collection' ) ); ?></p>
				<h1><?php echo esc_html( $collection->name ); ?></h1>
				<?php if ( $collection->description ) : ?>
					<p class="pc-description"><?php echo esc_html( $collection->description ); ?></p>
				<?php endif; ?>
			</div>
			<div class="pc-collection-stats">
				<strong><?php echo esc_html( number_format_i18n( $query->found_posts ) ); ?></strong>
				<span><?php echo esc_html( 'bookmarks' === $mode ? __( 'visible saved links', 'post-collection' ) : __( 'visible posts', 'post-collection' ) ); ?></span>
				<?php if ( $app->can_manage_collections() ) : ?>
					<a href="<?php echo esc_url( $app->get_collection_settings_url( $collection ) ); ?>"><?php esc_html_e( 'Settings', 'post-collection' ); ?></a>
				<?php endif; ?>
			</div>
		</div>

		<?php if ( $app->can_manage_collections() ) : ?>
			<form class="pc-add-form" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
				<input type="hidden" name="collection" value="<?php echo esc_attr( $collection->term_id ); ?>">
				<input type="url" name="collect-post" placeholder="https://example.com/article" required>
				<button type="submit"><?php esc_html_e( 'Save', 'post-collection' ); ?></button>
			</form>
		<?php endif; ?>

		<form class="pc-filter-bar" method="get" action="<?php echo esc_url( $base_url ); ?>">
			<label class="screen-reader-text" for="pc-search"><?php esc_html_e( 'Search this collection', 'post-collection' ); ?></label>
			<input id="pc-search" type="search" name="pc-search" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search saved items', 'post-collection' ); ?>">
			<input type="hidden" name="pc-view" value="<?php echo esc_attr( $view ); ?>">
			<?php if ( '' !== $active_tag ) : ?>
				<input type="hidden" name="pc-tag" value="<?php echo esc_attr( $active_tag ); ?>">
			<?php endif; ?>
			<button type="submit"><?php esc_html_e( 'Search', 'post-collection' ); ?></button>
			<?php if ( '' !== $search || '' !== $active_tag ) : ?>
				<a class="pc-clear-link" href="<?php echo esc_url( add_query_arg( 'pc-view', $view, $base_url ) ); ?>"><?php esc_html_e( 'Clear', 'post-collection' ); ?></a>
			<?php endif; ?>
		</form>

		<nav class="pc-view-switch" aria-label="<?php esc_attr_e( 'Collection view', 'post-collection' ); ?>">
			<?php foreach ( $views as $view_key => $view_label ) : ?>
				<?php
				$view_url = add_query_arg(
					array_filter(
						array(
							'pc-search' => $search,
							'pc-tag'    => $active_tag,
							'pc-view'   => $view_key,
						)
					),
					$base_url
				);
				?>
				<a class="<?php echo $view === $view_key ? 'is-active' : ''; ?>" href="<?php echo esc_url( $view_url ); ?>"><?php echo esc_html( $view_label ); ?></a>
			<?php endforeach; ?>
		</nav>

		<?php if ( ! empty( $terms ) ) : ?>
			<nav class="pc-tag-strip" aria-label="<?php esc_attr_e( 'Collection tags', 'post-collection' ); ?>">
				<?php foreach ( $terms as $term ) : ?>
					<?php
					$tag_url = add_query_arg(
						array_filter(
							array(
								'pc-search' => $search,
								'pc-tag'    => $term->slug,
								'pc-view'   => $view,
							)
						),
						$base_url
					);
					?>
					<a class="<?php echo $active_tag === $term->slug ? 'is-active' : ''; ?>" href="<?php echo esc_url( $tag_url ); ?>">
						<?php echo esc_html( $term->name ); ?>
						<span><?php echo esc_html( number_format_i18n( $term->count ) ); ?></span>
					</a>
				<?php endforeach; ?>
			</nav>
		<?php endif; ?>

	</header>

	<main class="pc-shell">
		<?php if ( ! $query->have_posts() ) : ?>
			<section class="pc-empty">
				<h2><?php esc_html_e( 'No items found', 'post-collection' ); ?></h2>
				<p><?php esc_html_e( 'Try another search or tag.', 'post-collection' ); ?></p>
			</section>
		<?php elseif ( 'board' === $view ) : ?>
			<section class="pc-bookmark-board" aria-label="<?php esc_attr_e( 'Bookmarks', 'post-collection' ); ?>">
				<?php foreach ( $query->posts as $post ) : ?>
					<?php
					$image_url   = $app->get_post_image_url( $post );
					$embed_html  = $app->get_post_embed_html( $post, 'board' );
					$excerpt     = $app->get_post_excerpt( $post, 22 );
					$source_url  = $app->get_source_url( $post );
					$host        = $app->get_source_host( $post );
					$read_status = $app->get_article_note_status( $post );
					?>
					<article class="pc-bookmark-card">
						<?php if ( $embed_html ) : ?>
							<div class="pc-card-embed"><?php echo $embed_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
						<?php elseif ( $image_url ) : ?>
							<a class="pc-card-image" href="<?php echo esc_url( $source_url ); ?>" target="_blank" rel="noopener noreferrer">
								<img src="<?php echo esc_url( $image_url ); ?>" alt="">
							</a>
						<?php else : ?>
							<a class="pc-card-host" href="<?php echo esc_url( $source_url ); ?>" target="_blank" rel="noopener noreferrer">
								<?php echo esc_html( $host ? substr( $host, 0, 1 ) : '#' ); ?>
							</a>
						<?php endif; ?>
						<div class="pc-card-body">
							<p class="pc-source"><?php echo esc_html( $host ); ?></p>
							<h2><a href="<?php echo esc_url( $source_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( get_the_title( $post ) ); ?></a></h2>
							<?php if ( $excerpt ) : ?>
								<p><?php echo esc_html( $excerpt ); ?></p>
							<?php endif; ?>
							<div class="pc-card-actions">
								<a href="<?php echo esc_url( $app->get_collection_url( $collection, $post->ID ) ); ?>"><?php esc_html_e( 'Details', 'post-collection' ); ?></a>
								<?php $app->render_article_note_status_toggle( $post, $read_status ); ?>
								<?php if ( 'private' === $post->post_status && $app->can_manage_collections() ) : ?>
									<span><?php esc_html_e( 'Private', 'post-collection' ); ?></span>
								<?php endif; ?>
							</div>
						</div>
					</article>
				<?php endforeach; ?>
			</section>
		<?php elseif ( 'links' === $view ) : ?>
			<section class="pc-link-list" aria-label="<?php esc_attr_e( 'Links', 'post-collection' ); ?>">
				<?php foreach ( $query->posts as $post ) : ?>
					<?php
					$source_url  = $app->get_source_url( $post );
					$host        = $app->get_source_host( $post );
					$embed_html  = $app->get_post_embed_html( $post, 'links' );
					$excerpt     = $app->get_post_excerpt( $post, 24 );
					$post_terms  = $app->get_post_terms( $post );
					$tag_names   = wp_list_pluck( $post_terms, 'name' );
					$read_status = $app->get_article_note_status( $post );
					$is_editing  = $quick_edit && intval( $post->ID ) === intval( $quick_edit_post_id );
					$edit_url    = add_query_arg( array( 'pc-view' => 'links', 'pc-edit' => $post->ID ) ) . '#pc-link-' . $post->ID;
					$cancel_url  = remove_query_arg( 'pc-edit' ) . '#pc-link-' . $post->ID;
					?>
					<article id="pc-link-<?php echo esc_attr( $post->ID ); ?>" class="pc-link-row<?php echo 'private' === $post->post_status ? ' is-private' : ''; ?><?php echo $is_editing ? ' is-editing' : ''; ?>">
						<div class="pc-link-main">
							<div class="pc-link-title-line">
								<span class="pc-link-marker" aria-hidden="true"></span>
								<h2><a class="pc-link-title" href="<?php echo esc_url( $source_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( get_the_title( $post ) ); ?></a></h2>
							</div>
							<a class="pc-link-url" href="<?php echo esc_url( $source_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( preg_replace( '#^https?://#', '', $source_url ) ); ?></a>
							<?php if ( $embed_html ) : ?>
								<div class="pc-link-embed"><?php echo $embed_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
							<?php elseif ( $excerpt ) : ?>
								<p class="pc-link-excerpt"><?php echo esc_html( $excerpt ); ?></p>
							<?php endif; ?>
						</div>
						<div class="pc-link-meta">
							<time datetime="<?php echo esc_attr( get_the_date( DATE_W3C, $post ) ); ?>"><?php echo esc_html( get_the_date( '', $post ) ); ?></time>
							<span class="pc-link-host"><?php echo esc_html( $host ); ?></span>
							<?php $app->render_article_note_status_toggle( $post, $read_status ); ?>
							<?php if ( 'private' === $post->post_status && $app->can_manage_collections() ) : ?>
								<span class="pc-link-private"><?php esc_html_e( 'private', 'post-collection' ); ?></span>
							<?php endif; ?>
							<a href="<?php echo esc_url( $app->get_collection_url( $collection, $post->ID ) ); ?>"><?php esc_html_e( 'Details', 'post-collection' ); ?></a>
							<?php if ( $app->can_manage_collections() ) : ?>
								<?php if ( $is_editing ) : ?>
									<a class="pc-quick-edit-cancel" href="<?php echo esc_url( $cancel_url ); ?>" data-edit-url="<?php echo esc_url( $edit_url ); ?>" data-cancel-url="<?php echo esc_url( $cancel_url ); ?>" data-edit-label="<?php esc_attr_e( 'Edit', 'post-collection' ); ?>" data-cancel-label="<?php esc_attr_e( 'Cancel edit', 'post-collection' ); ?>"><?php esc_html_e( 'Cancel edit', 'post-collection' ); ?></a>
								<?php else : ?>
									<a class="pc-quick-edit-open" href="<?php echo esc_url( $edit_url ); ?>" data-edit-url="<?php echo esc_url( $edit_url ); ?>" data-cancel-url="<?php echo esc_url( $cancel_url ); ?>" data-edit-label="<?php esc_attr_e( 'Edit', 'post-collection' ); ?>" data-cancel-label="<?php esc_attr_e( 'Cancel edit', 'post-collection' ); ?>"><?php esc_html_e( 'Edit', 'post-collection' ); ?></a>
								<?php endif; ?>
							<?php endif; ?>
						</div>
						<?php if ( ! empty( $post_terms ) ) : ?>
							<div class="pc-link-tags">
								<?php foreach ( $post_terms as $term ) : ?>
									<a href="<?php echo esc_url( add_query_arg( array_filter( array( 'pc-tag' => $term->slug, 'pc-view' => $view ) ), $base_url ) ); ?>"><?php echo esc_html( $term->name ); ?></a>
								<?php endforeach; ?>
							</div>
						<?php else : ?>
							<div class="pc-link-tags"></div>
						<?php endif; ?>
						<?php if ( $app->can_manage_collections() ) : ?>
							<form class="pc-quick-edit-form" method="post" action="<?php echo esc_url( $edit_url ); ?>" data-ajax-action="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>">
								<input type="hidden" name="action" value="post_collection_quick_edit">
								<input type="hidden" name="post_collection_action" value="quick-edit">
								<input type="hidden" name="post_id" value="<?php echo esc_attr( $post->ID ); ?>">
								<?php wp_nonce_field( 'post-collection-quick-edit-' . $post->ID ); ?>
								<label>
									<span><?php esc_html_e( 'Title', 'post-collection' ); ?></span>
									<input type="text" name="post_title" value="<?php echo esc_attr( get_the_title( $post ) ); ?>">
								</label>
								<label>
									<span><?php esc_html_e( 'URL', 'post-collection' ); ?></span>
									<input type="url" name="source_url" value="<?php echo esc_attr( $source_url ); ?>">
								</label>
								<label>
									<span><?php esc_html_e( 'Description', 'post-collection' ); ?></span>
									<textarea name="post_excerpt" rows="2"><?php echo esc_textarea( $post->post_excerpt ); ?></textarea>
								</label>
								<label>
									<span><?php esc_html_e( 'Tags', 'post-collection' ); ?></span>
									<input type="text" name="post_tags" value="<?php echo esc_attr( implode( ', ', $tag_names ) ); ?>">
								</label>
								<label>
									<span><?php esc_html_e( 'Read status', 'post-collection' ); ?></span>
									<select name="article_status">
										<?php foreach ( $article_statuses as $status_key => $status_label ) : ?>
											<option value="<?php echo esc_attr( $status_key ); ?>"<?php selected( $status_key, $read_status ); ?>><?php echo esc_html( $status_label ); ?></option>
										<?php endforeach; ?>
									</select>
								</label>
								<div class="pc-quick-edit-actions">
									<label class="pc-quick-edit-public">
										<input type="checkbox" name="post_status" value="publish" <?php checked( 'publish', $post->post_status ); ?>>
										<span><?php esc_html_e( 'Public', 'post-collection' ); ?></span>
									</label>
									<button type="submit"><?php esc_html_e( 'Save', 'post-collection' ); ?></button>
									<span class="pc-quick-edit-status" aria-live="polite"></span>
								</div>
							</form>
						<?php endif; ?>
					</article>
				<?php endforeach; ?>
			</section>
		<?php else : ?>
			<section class="pc-post-list" aria-label="<?php esc_attr_e( 'Posts', 'post-collection' ); ?>">
				<?php foreach ( $query->posts as $post ) : ?>
					<?php
					$image_url   = $app->get_post_image_url( $post );
					$embed_html  = $app->get_post_embed_html( $post, 'reader' );
					$excerpt     = $app->get_post_excerpt( $post, 38 );
					$host        = $app->get_source_host( $post );
					$read_status = $app->get_article_note_status( $post );
					?>
					<article class="pc-post-row<?php echo $image_url || $embed_html ? ' has-image' : ' is-text-only'; ?>">
						<?php if ( $embed_html ) : ?>
							<div class="pc-post-embed"><?php echo $embed_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
						<?php elseif ( $image_url ) : ?>
							<a class="pc-post-thumb" href="<?php echo esc_url( $app->get_collection_url( $collection, $post->ID ) ); ?>">
								<img src="<?php echo esc_url( $image_url ); ?>" alt="">
							</a>
						<?php endif; ?>
						<div>
							<p class="pc-source"><?php echo esc_html( $host ); ?></p>
							<h2><a href="<?php echo esc_url( $app->get_collection_url( $collection, $post->ID ) ); ?>"><?php echo esc_html( get_the_title( $post ) ); ?></a></h2>
							<?php if ( $excerpt ) : ?>
								<p><?php echo esc_html( $excerpt ); ?></p>
							<?php endif; ?>
							<div class="pc-row-meta">
								<time datetime="<?php echo esc_attr( get_the_date( DATE_W3C, $post ) ); ?>"><?php echo esc_html( get_the_date( '', $post ) ); ?></time>
								<?php $app->render_article_note_status_toggle( $post, $read_status ); ?>
								<a href="<?php echo esc_url( $app->get_source_url( $post ) ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Original', 'post-collection' ); ?></a>
							</div>
						</div>
					</article>
				<?php endforeach; ?>
			</section>
		<?php endif; ?>

		<?php if ( $query->max_num_pages > 1 ) : ?>
			<nav class="pc-pagination" aria-label="<?php esc_attr_e( 'Pagination', 'post-collection' ); ?>">
				<?php if ( $page > 1 ) : ?>
					<a class="pc-pagination-prev" href="<?php echo esc_url( add_query_arg( array_merge( $page_args, array( 'pc-page' => $page - 1 ) ), $base_url ) ); ?>"><?php esc_html_e( 'Previous', 'post-collection' ); ?></a>
				<?php endif; ?>
				<span>
					<?php
					echo esc_html(
						sprintf(
							// translators: %1$d is the current page, %2$d is the total number of pages.
							__( 'Page %1$d of %2$d', 'post-collection' ),
							$page,
							$query->max_num_pages
						)
					);
					?>
				</span>
				<?php if ( $page < $query->max_num_pages ) : ?>
					<a class="pc-pagination-next" rel="next" href="<?php echo esc_url( add_query_arg( array_merge( $page_args, array( 'pc-page' => $page + 1 ) ), $base_url ) ); ?>"><?php esc_html_e( 'Next', 'post-collection' ); ?></a>
				<?php endif; ?>
			</nav>
		<?php endif; ?>
	</main>
	<?php wp_app_body_close(); ?>
</body>
</html>
