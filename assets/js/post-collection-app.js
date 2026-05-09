( function () {
	function closest( element, selector ) {
		while ( element && element !== document ) {
			if ( element.matches( selector ) ) {
				return element;
			}
			element = element.parentNode;
		}

		return null;
	}

	function closeEditors( exceptRow ) {
		document.querySelectorAll( '.pc-link-row.is-editing' ).forEach( function ( row ) {
			if ( row !== exceptRow ) {
				row.classList.remove( 'is-editing' );
				var cancel = row.querySelector( '.pc-quick-edit-cancel' );
				if ( cancel ) {
					setToggle( cancel, false );
				}
			}
		} );
	}

	function setToggle( toggle, isEditing ) {
		toggle.classList.toggle( 'pc-quick-edit-open', ! isEditing );
		toggle.classList.toggle( 'pc-quick-edit-cancel', isEditing );
		toggle.textContent = isEditing ? toggle.dataset.cancelLabel || 'Cancel edit' : toggle.dataset.editLabel || 'Edit';
		if ( isEditing && toggle.dataset.cancelUrl ) {
			toggle.href = toggle.dataset.cancelUrl;
		} else if ( ! isEditing && toggle.dataset.editUrl ) {
			toggle.href = toggle.dataset.editUrl;
		}
	}

	function replaceLocation( href ) {
		if ( href && window.history && window.history.replaceState ) {
			window.history.replaceState( null, '', href );
		}
	}

	function openEditor( row ) {
		closeEditors( row );
		row.classList.add( 'is-editing' );
		var toggle = row.querySelector( '.pc-quick-edit-open' );
		if ( toggle ) {
			setToggle( toggle, true );
			replaceLocation( toggle.dataset.editUrl || toggle.href );
		}
		var firstInput = row.querySelector( '.pc-quick-edit-form input[type="text"]' );
		if ( firstInput ) {
			firstInput.focus();
			firstInput.select();
		}
	}

	function closeEditor( row ) {
		row.classList.remove( 'is-editing' );
		var toggle = row.querySelector( '.pc-quick-edit-cancel' );
		if ( toggle ) {
			replaceLocation( toggle.dataset.cancelUrl || toggle.href );
			setToggle( toggle, false );
		}
	}

	function updateTags( row, terms ) {
		var tagContainer = row.querySelector( '.pc-link-tags' );
		if ( ! tagContainer ) {
			return;
		}

		tagContainer.textContent = '';
		terms.forEach( function ( term ) {
			var link = document.createElement( 'a' );
			link.href = term.url;
			link.textContent = term.name;
			tagContainer.appendChild( link );
		} );
	}

	function updateRow( row, item ) {
		row.classList.toggle( 'is-private', !! item.is_private );

		var marker = row.querySelector( '.pc-link-marker' );
		if ( marker ) {
			marker.setAttribute( 'aria-hidden', 'true' );
		}

		var title = row.querySelector( '.pc-link-title' );
		if ( title ) {
			title.href = item.source_url;
			title.textContent = item.title;
		}

		var urlLink = row.querySelector( '.pc-link-url' );
		if ( urlLink ) {
			urlLink.href = item.source_url;
			urlLink.textContent = item.display_url;
		}

		var excerpt = row.querySelector( '.pc-link-excerpt' );
		if ( ! excerpt && item.excerpt ) {
			excerpt = document.createElement( 'p' );
			excerpt.className = 'pc-link-excerpt';
			if ( urlLink ) {
				urlLink.after( excerpt );
			}
		}
		if ( excerpt ) {
			excerpt.textContent = item.excerpt || '';
			excerpt.hidden = ! item.excerpt;
		}

		var host = row.querySelector( '.pc-link-host' );
		if ( host ) {
			host.textContent = item.host || '';
		}

		var privateLabel = row.querySelector( '.pc-link-private' );
		if ( item.is_private && ! privateLabel ) {
			privateLabel = document.createElement( 'span' );
			privateLabel.className = 'pc-link-private';
			privateLabel.textContent = 'private';
			var details = row.querySelector( '.pc-link-meta a' );
			if ( details ) {
				details.before( privateLabel );
			}
		} else if ( ! item.is_private && privateLabel ) {
			privateLabel.remove();
		}

		updateTags( row, item.terms || [] );
		closeEditor( row );
	}

	document.addEventListener( 'click', function ( event ) {
		var editLink = closest( event.target, '.pc-quick-edit-open' );
		if ( editLink ) {
			var row = closest( editLink, '.pc-link-row' );
			if ( row ) {
				event.preventDefault();
				openEditor( row );
			}
			return;
		}

		var cancelLink = closest( event.target, '.pc-quick-edit-cancel' );
		if ( cancelLink ) {
			var cancelRow = closest( cancelLink, '.pc-link-row' );
			if ( cancelRow ) {
				event.preventDefault();
				closeEditor( cancelRow );
			}
		}
	} );

	document.addEventListener( 'submit', function ( event ) {
		var form = closest( event.target, '.pc-quick-edit-form' );
		if ( ! form ) {
			return;
		}

		event.preventDefault();
		var row = closest( form, '.pc-link-row' );
		var button = form.querySelector( 'button[type="submit"]' );
		var status = form.querySelector( '.pc-quick-edit-status' );
		if ( ! row ) {
			return;
		}

		if ( button ) {
			button.disabled = true;
		}
		if ( status ) {
			status.textContent = 'Saving...';
		}

		fetch( form.dataset.ajaxAction || form.action, {
			method: 'POST',
			credentials: 'same-origin',
			body: new FormData( form ),
		} )
			.then( function ( response ) {
				return response.json();
			} )
			.then( function ( result ) {
				if ( ! result || ! result.success ) {
					throw new Error( result && result.data && result.data.message ? result.data.message : 'Could not save changes.' );
				}

				updateRow( row, result.data );
				if ( status ) {
					status.textContent = '';
				}
			} )
			.catch( function ( error ) {
				if ( status ) {
					status.textContent = error.message;
				}
			} )
			.finally( function () {
				if ( button ) {
					button.disabled = false;
				}
			} );
	} );
}() );
