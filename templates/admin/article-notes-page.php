<?php
/**
 * Article Notes Admin Page Template
 *
 * @package Post_Collection
 */

defined( 'ABSPATH' ) || exit;

$notes = isset( $args['notes'] ) ? $args['notes'] : array();
$total = isset( $args['total'] ) ? $args['total'] : 0;
$paged = isset( $args['paged'] ) ? $args['paged'] : 1;
$per_page = isset( $args['per_page'] ) ? $args['per_page'] : 20;
$status_filter = isset( $args['status_filter'] ) ? $args['status_filter'] : 'all';
$statuses = isset( $args['statuses'] ) ? $args['statuses'] : array();
$nonce = isset( $args['nonce'] ) ? $args['nonce'] : '';

$total_pages = ceil( $total / $per_page );
$base_url = admin_url( 'edit.php?post_type=post_collection&page=post-collection-article-notes' );
?>

<div class="wrap post-collection-notes-admin">
	<h1><?php esc_html_e( 'Collected Posts Notes', 'post-collection' ); ?></h1>

	<div class="post-collection-notes-filters">
		<ul class="subsubsub">
			<li>
				<a href="<?php echo esc_url( $base_url ); ?>" class="<?php echo 'all' === $status_filter ? 'current' : ''; ?>">
					<?php esc_html_e( 'All', 'post-collection' ); ?>
				</a> |
			</li>
			<?php foreach ( $statuses as $status_key => $status_label ) : ?>
				<li>
					<a href="<?php echo esc_url( add_query_arg( 'status', $status_key, $base_url ) ); ?>" class="<?php echo $status_filter === $status_key ? 'current' : ''; ?>">
						<?php echo esc_html( $status_label ); ?>
					</a>
					<?php echo $status_key !== array_key_last( $statuses ) ? '|' : ''; ?>
				</li>
			<?php endforeach; ?>
			<li>
				<a href="<?php echo esc_url( add_query_arg( 'status', 'archived', $base_url ) ); ?>" class="<?php echo 'archived' === $status_filter ? 'current' : ''; ?>">
					<?php esc_html_e( 'Archived', 'post-collection' ); ?>
				</a>
			</li>
		</ul>
	</div>

	<?php if ( empty( $notes ) ) : ?>
		<p class="post-collection-no-notes">
			<?php esc_html_e( 'No article notes found.', 'post-collection' ); ?>
		</p>
	<?php else : ?>
		<table class="wp-list-table widefat fixed striped post-collection-notes-table">
			<thead>
				<tr>
					<th class="column-title"><?php esc_html_e( 'Article', 'post-collection' ); ?></th>
					<th class="column-status"><?php esc_html_e( 'Status', 'post-collection' ); ?></th>
					<th class="column-rating"><?php esc_html_e( 'Rating', 'post-collection' ); ?></th>
					<th class="column-notes"><?php esc_html_e( 'Notes', 'post-collection' ); ?></th>
					<th class="column-date"><?php esc_html_e( 'Updated', 'post-collection' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $notes as $note ) : ?>
					<tr class="post-collection-article-item" data-article-id="<?php echo esc_attr( $note['id'] ); ?>">
						<td class="column-title">
							<strong>
								<a href="<?php echo esc_url( $note['permalink'] ); ?>" target="_blank">
									<?php echo esc_html( $note['title'] ); ?>
								</a>
							</strong>
							<div class="post-collection-article-author">
								<?php echo esc_html( $note['author'] ); ?>
							</div>
						</td>
						<td class="column-status">
							<div class="post-collection-status-buttons">
								<?php foreach ( $statuses as $status_key => $status_label ) : ?>
									<button type="button"
										class="post-collection-status-btn <?php echo $note['status'] === $status_key ? 'active' : ''; ?>"
										data-status="<?php echo esc_attr( $status_key ); ?>"
										title="<?php echo esc_attr( $status_label ); ?>">
										<?php echo esc_html( $status_label ); ?>
									</button>
								<?php endforeach; ?>
							</div>
							<?php if ( 'archived' !== $note['status'] ) : ?>
								<button type="button" class="post-collection-archive-btn" title="<?php esc_attr_e( 'Archive', 'post-collection' ); ?>">
									<?php esc_html_e( 'Archive', 'post-collection' ); ?>
								</button>
							<?php endif; ?>
						</td>
						<td class="column-rating">
							<div class="post-collection-rating" data-rating="<?php echo esc_attr( $note['rating'] ); ?>">
								<?php for ( $i = 1; $i <= 5; $i++ ) : ?>
									<button type="button"
										class="post-collection-star <?php echo $i <= $note['rating'] ? 'active' : ''; ?>"
										data-rating="<?php echo esc_attr( $i ); ?>"
										title="<?php echo esc_attr( sprintf( __( '%d stars', 'post-collection' ), $i ) ); ?>">
										<?php echo $i <= $note['rating'] ? '&#9733;' : '&#9734;'; ?>
									</button>
								<?php endfor; ?>
							</div>
						</td>
						<td class="column-notes">
							<div class="post-collection-notes-wrapper">
								<textarea
									class="post-collection-notes"
									placeholder="<?php esc_attr_e( 'Add your notes...', 'post-collection' ); ?>"
									rows="2"><?php echo esc_textarea( $note['notes'] ); ?></textarea>
							</div>
							<div class="post-collection-save-status"></div>
						</td>
						<td class="column-date">
							<?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $note['updated'] ) ) ); ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<?php if ( $total_pages > 1 ) : ?>
			<div class="tablenav bottom">
				<div class="tablenav-pages">
					<span class="displaying-num">
						<?php
						printf(
							/* translators: %s: Number of items */
							esc_html( _n( '%s item', '%s items', $total, 'post-collection' ) ),
							esc_html( number_format_i18n( $total ) )
						);
						?>
					</span>
					<span class="pagination-links">
						<?php if ( $paged > 1 ) : ?>
							<a class="prev-page button" href="<?php echo esc_url( add_query_arg( 'paged', $paged - 1, add_query_arg( 'status', $status_filter, $base_url ) ) ); ?>">
								<span aria-hidden="true">&lsaquo;</span>
							</a>
						<?php else : ?>
							<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&lsaquo;</span>
						<?php endif; ?>

						<span class="paging-input">
							<?php echo esc_html( $paged ); ?>
							<?php esc_html_e( 'of', 'post-collection' ); ?>
							<span class="total-pages"><?php echo esc_html( $total_pages ); ?></span>
						</span>

						<?php if ( $paged < $total_pages ) : ?>
							<a class="next-page button" href="<?php echo esc_url( add_query_arg( 'paged', $paged + 1, add_query_arg( 'status', $status_filter, $base_url ) ) ); ?>">
								<span aria-hidden="true">&rsaquo;</span>
							</a>
						<?php else : ?>
							<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&rsaquo;</span>
						<?php endif; ?>
					</span>
				</div>
			</div>
		<?php endif; ?>
	<?php endif; ?>
</div>
