<?php
/**
 * This template contains the post collection editor.
 *
 * @version 1.0
 * @package Friends_Post_Collection
 */

?><form method="post">
	<?php wp_nonce_field( 'edit-post-collection-' . $args['user']->ID ); ?>
	<table class="form-table">
		<tbody>
			<tr>
				<th><label for="display_name"><?php esc_html_e( 'Display Name', 'friends' ); ?></label></th>
				<td><input type="text" name="display_name" id="display_name" value="<?php echo esc_attr( $args['user']->display_name ); ?>" class="regular-text" /></td>
			</tr>
			<tr>
				<th><label for="description"><?php esc_html_e( 'Description', 'friends' ); ?></label></th>
				<td><textarea name="description" id="description" rows="5" cols="30"><?php echo esc_html( $args['user']->description ); ?></textarea></td>
			</tr>
			<tr>
				<th><label for="dropdown"><?php esc_html_e( 'Display in Dropdown', 'friends' ); ?></label></th>
				<td><select name="dropdown">
					<option value="inactive"<?php selected( $args['inactive'] ); ?>><?php esc_html_e( 'Hide', 'friends' ); ?></option>
					<option value="move"<?php selected( ! $args['inactive'] && ! $args['copy_mode'] ); ?>>
						<?php
							echo esc_html(
								sprintf(
									// translators: %s is the name of a post collection.
									_x( 'Move to %s', 'post-collection', 'friends' ),
									$args['user']->display_name
								)
							);
							?>
					</option>
					<option value="copy"<?php selected( ! $args['inactive'] && $args['copy_mode'] ); ?>>
						<?php
							echo esc_html(
								sprintf(
									// translators: %s is the name of a post collection.
									_x( 'Copy to %s', 'post-collection', 'friends' ),
									$args['user']->display_name
								)
							);
							?>
					</option>
				</select>
			</tr>
			<tr>
				<th><label><?php /* phpcs:ignore WordPress.WP.I18n.MissingArgDomain */ esc_html_e( 'Posts' ); ?></label></th>
				<td>
					<fieldset>
					<a href="<?php echo esc_url( $args['user']->get_local_friends_page_url() ); ?>">
						<?php
						// translators: %d is the number of posts.
						echo esc_html( sprintf( _n( 'View %d post', 'View %d posts', $args['posts']->found_posts, 'friends' ), $args['posts']->found_posts ) );
						?>
					</a>
					</fieldset>
				</td>
			</tr>
			<tr>
				<th><label><?php esc_html_e( 'Syndicate Posts', 'friends' ); ?></label></th>
				<td>
					<fieldset>
						<input type="checkbox" name="publish_post_collection" value="1" <?php checked( get_user_option( 'friends_publish_post_collection', $args['user']->ID ) ); ?> />
						<?php
						$feed = trailingslashit( $args['user']->get_local_friends_page_url() . 'feed' );
						echo wp_kses(
							sprintf(
							// translators: %s is a URL.
								__( 'Publish this Post Collection at %s', 'friends' ),
								'<a href="' . esc_url( $feed ) . '">' . esc_url( $feed ) . '</a>'
							),
							array( 'a' => array( 'href' => array() ) )
						);
						?>
					</fieldset>
				</td>
			</tr>
			<tr>
				<th><label><?php esc_html_e( 'Bookmarklet', 'friends' ); ?></label></th>
				<td>
					<a href="javascript:<?php echo rawurlencode( trim( str_replace( "window.document.getElementById( 'post-collection-script' ).getAttribute( 'data-post-url' )", "'" . esc_url( home_url() ) . "/?user=" . $args['user']->ID . "'", $args['bookmarklet_js'] ), ';' ) ); ?>" style="display: inline-block; padding: .5em; border: 1px solid #999; border-radius: 4px; background-color: #ddd;text-decoration: none; margin-right: 3em">
						<?php
						echo esc_html(
							sprintf(
							// translators: %s is the name of a Post Collection user.
								__( 'Save to %s', 'friends' ),
								$args['user']->display_name
							)
						);
						?>
					</a>
					<p class="description">
						<?php esc_html_e( 'Save articles from the web to this Post Collection using this bookmarklet. Drag it to your browser bar.', 'friends' ); ?></a></p>
				</td>
			</tr>			<tr>
				<th><label><?php esc_html_e( 'Other tools', 'friends' ); ?></label></th>
				<td>
					<input type="text" id="friends-search-placeholder-url" value="<?php echo esc_attr( $args['post_collection_url'] . '&amp;url=%s' ); ?>" style="width: 30em" />
					<button type="button" class="button" onclick="copyToClipboard()"><?php esc_html_e( 'Copy to clipboard', 'friends' ); ?></button>
					<script>
						function copyToClipboard() {
							var input = document.getElementById("friends-search-placeholder-url");
							input.select();

							try {
								var success = document.execCommand("copy");
							} catch (err) {
							}
						}
					</script>
					<p class="description">
						<?php
						echo wp_kses(
							sprintf(
								// translators: %1$s is a URL, %2$s is a URL.
								__( 'In other tools such as <a href="%1$s">Alfred</a> or <a href="%2$s">URL Forwarder</a> you\'ll need a URL like this.', 'friends' ),
								'https://www.alfredapp.com/',
								'https://play.google.com/store/apps/details?id=net.daverix.urlforward'
							),
							array(
								'a' => array(
									'href' => array(),
								),
							)
						);
						?>
						</a></p>
				</td>
			</tr>
			<?php do_action( 'users_edit_post_collection_table_end', $args['user'] ); ?>
		</tbody>
	</table>
	<?php do_action( 'users_edit_post_collection_after_form', $args['user'] ); ?>
	<p class="submit">
		<input type="submit" id="submit" class="button button-primary" value="<?php /* phpcs:ignore WordPress.WP.I18n.MissingArgDomain */ esc_html_e( 'Save Changes' ); ?>">
	</p>

	<p class="description">
		<a href="<?php echo esc_url( self_admin_url( 'admin.php?page=post-collection' ) ); ?>"><?php esc_html_e( 'Back to the Post Collection overview', 'friends' ); ?></a></p>

</form>


