function post_content( url ) {
	if ( url.indexOf( '?' ) > 0 ) {
		url += '&';
	} else {
		url += '?';
	}
	url += 'collect-post=' + encodeURIComponent( location.href );
	var form = window.document.createElement( 'form' );
	form.setAttribute( 'id', 'body-copy' );
	form.setAttribute( 'method', 'post' );
	form.setAttribute( 'action', url );
	form.setAttribute( 'accept-charset', 'UTF-8' );

	function input( key, value ) {
		var input = window.document.createElement( 'input' );
		input.setAttribute( 'type', 'hidden' );
		input.setAttribute( 'name', key );
		input.setAttribute( 'value', value );
		return input;
	}

	try {
		var bodyCopy = window.document.cloneNode( true );
		var loader = bodyCopy.getElementById( 'post-collection-loader' );
		if ( loader && loader.parentNode ) {
			loader.parentNode.removeChild( loader );
		}
		var previousCopy = bodyCopy.getElementById( 'body-copy' );
		if ( previousCopy && previousCopy.parentNode ) {
			previousCopy.parentNode.removeChild( previousCopy );
		}

		['script', 'style', 'canvas', 'select', 'textarea'].forEach( function( tagName ) {
			var elems = bodyCopy.getElementsByTagName( tagName );
			for ( var i = elems.length - 1; i >= 0; i-- ) {
				elems[i].parentNode.removeChild( elems[i] );
			}
		} );

		form.appendChild( input( 'body', bodyCopy.documentElement.outerHTML ) );
		form.appendChild( input( 'collect-post', location.href ) );

		window.document.body.appendChild( form );
		form.submit();
		if ( loader && loader.parentNode ) {
			loader.parentNode.removeChild( loader );
		}
		if ( form && form.parentNode ) {
			form.parentNode.removeChild( form );
		}

	} catch ( e ) {
		window.document.getElementById( 'post-collection-loader' ).textContent = e;
		location.href = url + '&error=' + escape( e );
	}
};
if ( confirm( text.do_you_want_to_send_the_article_to_your_blog ) ) {
	var div = window.document.createElement( 'div' );
	div.setAttribute( 'id', 'post-collection-loader' );
	div.style.position = 'fixed';
	div.style.top = 0;
	div.style.right = 0;
	div.style.width = '100%';
	div.style.backgroundColor = '#393';
	div.style.color = '#eee';
	div.style.textAlign = 'center';
	div.style.fontFamily = 'sans-serif';
	div.style.padding = '1em';
	div.style.zIndex = 6999999;
	div.textContent = text.sending_article_to_your_blog;
	window.document.body.appendChild( div );

	post_content( window.document.getElementById( 'post-collection-script' ).getAttribute( 'data-post-url' ) );
}
