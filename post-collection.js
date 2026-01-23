jQuery( function ( $ ) {
	var $document = $( document );

	wp = wp || {};
	$document.on( 'click', 'a.post-collection-mark-publish', function () {
		var $this = $( this );
		wp.ajax.send( 'post-collection-mark-publish', {
			data: {
				id: $this.data( 'id' ),
			},
			success: function ( response ) {
				$this.text( response.new_text ).removeClass( 'post-collection-mark-publish' ).addClass( 'post-collection-mark-private' );
			}
		} );
		return false;
	} );
	$document.on( 'click', 'a.post-collection-mark-private', function () {
		var $this = $( this );
		wp.ajax.send( 'post-collection-mark-private', {
			data: {
				id: $this.data( 'id' ),
			},
			success: function ( response ) {
				$this.text( response.new_text ).removeClass( 'post-collection-mark-private' ).addClass( 'post-collection-mark-publish' );
			}
		} );
		return false;
	} );
	$document.on( 'click', 'a.post-collection-change-author', function () {
		var $this = $( this );
		wp.ajax.send( 'post-collection-change-author', {
			data: {
				id: $this.data( 'id' ),
				author: $this.data( 'author' ),
				originalauthor: $this.data( 'originalauthor' )
			},
			success: function ( response ) {
				$this.text( response.new_text ).data( 'author', response.old_author );
			}
		} );
		return false;
	} );
	$document.on( 'click', 'a.post-collection-fetch-full-content', function () {
		var $this = $( this );
		var search_indicator = $this.find( 'i' );
		if ( search_indicator.hasClass( 'loading' ) ) {
			return;
		}
		wp.ajax.send( 'post-collection-fetch-full-content', {
			data: {
				id: $this.data( 'id' ),
				author: $this.data( 'author' )
			},
			beforeSend: function () {
				search_indicator.addClass( 'form-icon loading' );
			},
			success: function ( response ) {
				search_indicator.removeClass( 'form-icon loading' ).addClass( 'dashicons dashicons-saved' );
				$this.closest( 'article' ).find( 'h4.card-title a' ).text( response.post_title );
				$this.closest( 'article' ).find( 'div.card-body' ).html( response.post_content );
			},
			error: function ( e ) {
				search_indicator.removeClass( 'form-icon loading' ).addClass( 'dashicons dashicons-warning' ).prop( 'title', e );
			}
		} );
		return false;
	} );

	$document.on( 'click', 'a.post-collection-download-images', function () {
		var $this = $( this );
		var search_indicator = $this.find( 'i' );
		if ( search_indicator.hasClass( 'loading' ) ) {
			return;
		}
		wp.ajax.send( 'post-collection-download-images', {
			data: {
				id: $this.data( 'id' ),
				author: $this.data( 'author' )
			},
			beforeSend: function () {
				search_indicator.addClass( 'form-icon loading' );
			},
			success: function ( response ) {
				search_indicator.removeClass( 'form-icon loading' ).addClass( 'dashicons dashicons-saved' );
				$this.closest( 'article' ).find( 'h4.card-title a' ).text( response.post_title );
				$this.closest( 'article' ).find( 'div.card-body' ).html( response.post_content );
			},
			error: function ( e ) {
				search_indicator.removeClass( 'form-icon loading' ).addClass( 'dashicons dashicons-warning' ).prop( 'title', e );
			}
		} );
		return false;
	} );

	$document.on( 'click', 'a.post-collection-re-extract', function () {
		var $this = $( this );
		var search_indicator = $this.find( 'i' );
		if ( search_indicator.hasClass( 'loading' ) ) {
			return;
		}
		wp.ajax.send( 'post-collection-re-extract', {
			data: {
				id: $this.data( 'id' )
			},
			beforeSend: function () {
				search_indicator.addClass( 'form-icon loading' );
			},
			success: function ( response ) {
				search_indicator.removeClass( 'form-icon loading' ).addClass( 'dashicons dashicons-saved' );
				$this.closest( 'article' ).find( 'h4.card-title a' ).text( response.post_title );
				$this.closest( 'article' ).find( 'div.card-body' ).html( response.post_content );
			},
			error: function ( e ) {
				search_indicator.removeClass( 'form-icon loading' ).addClass( 'dashicons dashicons-warning' ).prop( 'title', e );
			}
		} );
		return false;
	} );

	$document.on( 'click', 'a.post-collection-fetch-url-opener', function () {
		$( '#post-collection-fetch-form' ).toggleClass( 'd-hide' ).find( 'input[type=url]' ).focus();
		return false;
	} );
} );
