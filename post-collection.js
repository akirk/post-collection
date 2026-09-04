jQuery( function ( $ ) {
	var $document = $( document );

	wp = wp || {};
	$document.on( 'click', 'a.post-collection-mark-publish', function () {
		var $this = $( this );
		wp.ajax.send( 'post-collection-mark-publish', {
			data: {
				_ajax_nonce: postCollection.nonce,
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
				_ajax_nonce: postCollection.nonce,
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
		if ( $this.data( 'loading' ) ) {
			return false;
		}
		wp.ajax.send( 'post-collection-change-author', {
			data: {
				_ajax_nonce: postCollection.nonce,
				id: $this.data( 'id' ),
				author: $this.data( 'author' ),
				originalauthor: $this.data( 'originalauthor' )
			},
			beforeSend: function () {
				$this.data( 'loading', true );
			},
			success: function ( response ) {
				$this.text( response.new_text ).data( 'author', response.old_author );
			},
			complete: function () {
				$this.data( 'loading', false );
			}
		} );
		return false;
	} );
	$document.on( 'click', 'a.post-collection-save-to-collection', function () {
		var $this = $( this );
		if ( $this.data( 'loading' ) ) {
			return false;
		}
		wp.ajax.send( 'post-collection-save-to-collection', {
			data: {
				_ajax_nonce: postCollection.nonce,
				id: $this.data( 'id' ),
				collection: $this.data( 'collection' )
			},
			beforeSend: function () {
				$this.data( 'loading', true );
			},
			success: function ( response ) {
				$this.text( response.new_text );
			},
			complete: function () {
				$this.data( 'loading', false );
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
				_ajax_nonce: postCollection.nonce,
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
				_ajax_nonce: postCollection.nonce,
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
				_ajax_nonce: postCollection.nonce,
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
