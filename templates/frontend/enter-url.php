<?php defined( 'ABSPATH' ) || exit; ?>
<a class="chip post-collection-fetch-url-opener" href=""><?php esc_html_e( 'Collect Post', 'post-collection' ); ?></a>

<form action="<?php echo esc_url( home_url() ); ?>" id="post-collection-fetch-form" class="d-hide mt-2">
	<input type="hidden" name="user" value="<?php echo esc_attr( $args['friend_user']->ID ); ?>">
	<div class="card">
		<div class="form-group card-body">
			<label class="form-label" for="collect-url"><?php esc_html_e( 'Enter the URL you want to archive', 'post-collection' ); ?></label>
			<input class="form-input" type="url" name="collect-post" placeholder="<?php esc_attr_e( 'URL', 'post-collection' ); ?>">
		</div>
		<div class="card-footer"><button class="btn btn-primary" href="">Collect URL</button></div>
	</div>
</form>
