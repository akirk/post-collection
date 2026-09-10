( function () {
	document.addEventListener( 'click', function ( event ) {
		var button = event.target.closest( '[data-post-collection-copy-url]' );

		if ( ! button ) {
			return;
		}

		var input = document.querySelector( button.getAttribute( 'data-post-collection-copy-url' ) );

		if ( ! input ) {
			return;
		}

		input.select();

		try {
			document.execCommand( 'copy' );
		} catch ( error ) {}
	} );
} )();
