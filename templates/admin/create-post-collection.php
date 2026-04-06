<?php
/**
 * This template contains the post collection creation screen.
 *
 * @version 1.0
 * @package Friends_Post_Collection
 */

defined( 'ABSPATH' ) || exit;

?><form method="post">
	<?php wp_nonce_field( 'create-post-collection' ); ?>
	<table class="form-table">
		<tbody>
			<tr>
				<th scope="row"><label for="display_name"><?php esc_html_e( 'Display Name', 'post-collection' ); ?></label></th>
				<td><input type="text" name="display_name" id="display_name" value="<?php echo esc_attr( $args['display_name'] ); ?>" required class="regular-text" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="user_login"><?php esc_html_e( 'Username', 'post-collection' ); ?></label></th>
				<td>
					<input type="text" id="user_login" name="user_login" value="<?php echo esc_attr( $args['user_login'] ); ?>" placeholder="" class="regular-text" />
				</td>
			</tr>
			<?php do_action( 'users_create_post_collection_table_end', $args['user_login'], $args['display_name'] ); ?>
		</tbody>
	</table>
	<?php do_action( 'users_create_post_collection_after_form', $args['user_login'], $args['display_name'] ); ?>
	<p class="submit">
		<input type="submit" id="submit" class="button button-primary" value="<?php esc_html_e( 'Create Post Collection', 'post-collection' ); ?>">
	</p>
</form>
