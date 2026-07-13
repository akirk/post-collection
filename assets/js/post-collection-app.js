( function () {
	function closest( element, selector ) {
		while ( element && element !== document ) {
			if ( matches( element, selector ) ) {
				return element;
			}
			element = element.parentNode;
		}

		return null;
	}

	function matches( element, selector ) {
		return !! ( element && element.matches && element.matches( selector ) );
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

	function escapeHtml( value ) {
		var div = document.createElement( 'div' );
		div.textContent = value || '';
		return div.innerHTML;
	}

	function getReviewShell( element ) {
		return closest( element, '.pc-review-shell' );
	}

	function getArticleNotesContext( notes ) {
		var shell = getReviewShell( notes );
		return {
			ajaxAction: notes.dataset.ajaxAction || ( shell ? shell.dataset.ajaxAction : '' ),
			nonce: notes.dataset.nonce || ( shell ? shell.dataset.nonce : '' ),
			statuses: shell && shell.dataset.statuses ? JSON.parse( shell.dataset.statuses ) : null,
		};
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

	function applyReadStatus( readStatus, item ) {
		if ( ! readStatus || ! item.read_status ) {
			return;
		}

		[ 'unread', 'read', 'skipped', 'archived' ].forEach( function ( status ) {
			readStatus.classList.remove( 'pc-read-status-' + status );
		} );
		readStatus.classList.add( 'pc-read-status-' + item.read_status );
		readStatus.dataset.readStatus = item.read_status;
		readStatus.textContent = item.read_label || item.read_status;
	}

	function updateReadStatus( row, item ) {
		if ( ! item.read_status ) {
			return;
		}

		row.querySelectorAll( '.pc-read-status' ).forEach( function ( readStatus ) {
			applyReadStatus( readStatus, item );
		} );

		var select = row.querySelector( '.pc-quick-edit-form select[name="article_status"]' );
		if ( select ) {
			select.value = item.read_status;
		}
	}

	function updateReadStatusControls( postId, item ) {
		document.querySelectorAll( '.pc-read-status-toggle[data-post-id="' + postId + '"]' ).forEach( function ( readStatus ) {
			applyReadStatus( readStatus, item );
		} );
	}

	function updateArticleNotesStatus( postId, status ) {
		var notes = document.querySelector( '.pc-article-notes[data-article-id="' + postId + '"]' );
		if ( ! notes ) {
			return;
		}

		notes.querySelectorAll( '.pc-note-status' ).forEach( function ( button ) {
			button.classList.toggle( 'is-active', button.dataset.status === status );
		} );
	}

	function updateNoteStars( ratingContainer, rating ) {
		ratingContainer.dataset.rating = rating;
		ratingContainer.querySelectorAll( '.pc-note-star' ).forEach( function ( star ) {
			var active = parseInt( star.dataset.rating, 10 ) <= rating;
			star.classList.toggle( 'is-active', active );
			star.innerHTML = active ? '&#9733;' : '&#9734;';
		} );
	}

	function previewNoteStars( ratingContainer, rating ) {
		ratingContainer.querySelectorAll( '.pc-note-star' ).forEach( function ( star ) {
			star.classList.toggle( 'is-previewed', parseInt( star.dataset.rating, 10 ) <= rating );
		} );
	}

	function clearNoteStarPreview( ratingContainer ) {
		ratingContainer.querySelectorAll( '.pc-note-star' ).forEach( function ( star ) {
			star.classList.remove( 'is-previewed' );
		} );
	}

	function setNoteSaveStatus( notes, message, state ) {
		var status = notes.querySelector( '.pc-note-save-status' );
		if ( ! status ) {
			return;
		}

		status.className = 'pc-note-save-status' + ( state ? ' is-' + state : '' );
		status.textContent = message || '';
	}

	function setReviewItemCollapsed( item, collapsed ) {
		if ( ! item || ! item.classList.contains( 'pc-review-item' ) ) {
			return;
		}

		item.classList.toggle( 'is-collapsed', collapsed );
	}

	function updateReviewItemCollapseForStatus( item, status ) {
		setReviewItemCollapsed( item, 'read' === status || 'skipped' === status );
	}

	function getCollapsedStatusLabel( status, label ) {
		return 'read' === status || 'skipped' === status ? label : '';
	}

	function activateTab( tabs, tab ) {
		var tabName = tab.dataset.pcTab || '';
		if ( ! tabName ) {
			return;
		}

		tabs.querySelectorAll( '[data-pc-tab]' ).forEach( function ( button ) {
			var active = button === tab;
			button.classList.toggle( 'is-active', active );
			button.setAttribute( 'aria-selected', active ? 'true' : 'false' );
			button.tabIndex = active ? 0 : -1;
		} );

		tabs.querySelectorAll( '[data-pc-tab-panel]' ).forEach( function ( panel ) {
			var active = panel.dataset.pcTabPanel === tabName;
			panel.classList.toggle( 'is-active', active );
			panel.hidden = ! active;
		} );
	}

	function focusAdjacentTab( tab, direction ) {
		var tabs = closest( tab, '[data-pc-tabs]' );
		var buttons = tabs ? Array.prototype.slice.call( tabs.querySelectorAll( '[data-pc-tab]' ) ) : [];
		var index = buttons.indexOf( tab );
		var nextIndex;
		if ( -1 === index || ! buttons.length ) {
			return;
		}

		if ( 'first' === direction ) {
			nextIndex = 0;
		} else if ( 'last' === direction ) {
			nextIndex = buttons.length - 1;
		} else {
			nextIndex = ( index + direction + buttons.length ) % buttons.length;
		}

		activateTab( tabs, buttons[ nextIndex ] );
		buttons[ nextIndex ].focus();
	}

	function updateReviewItemCollapsedStatus( item, label ) {
		var status = item ? item.querySelector( '.pc-review-collapsed-status' ) : null;
		if ( status ) {
			status.textContent = label || '';
		}
	}

	function saveArticleNote( notes, data ) {
		var context = getArticleNotesContext( notes );
		var postData = new FormData();
		postData.append( 'action', 'post_collection_save_note' );
		postData.append( '_ajax_nonce', context.nonce || '' );
		postData.append( 'article_id', notes.dataset.articleId || '' );

		Object.keys( data ).forEach( function ( key ) {
			postData.append( key, data[ key ] );
		} );

		setNoteSaveStatus( notes, 'Saving...', 'saving' );

		return fetch( context.ajaxAction, {
			method: 'POST',
			credentials: 'same-origin',
			body: postData,
		} )
			.then( function ( response ) {
				return response.json();
			} )
			.then( function ( result ) {
				if ( ! result || ! result.success ) {
					throw new Error( result && result.data ? result.data : 'Could not save note.' );
				}

				setNoteSaveStatus( notes, 'Saved', 'saved' );
				window.setTimeout( function () {
					setNoteSaveStatus( notes, '', '' );
				}, 2000 );

				return result;
			} )
			.catch( function ( error ) {
				setNoteSaveStatus( notes, error.message || 'Error saving', 'error' );
				throw error;
		} );
	}

	function getReviewList( shell, list ) {
		return shell ? shell.querySelector( '.pc-review-list[data-review-list="' + list + '"]' ) : null;
	}

	function getReviewStatuses( shell ) {
		if ( ! shell || ! shell.dataset.statuses ) {
			return {
				unread: 'Not read yet',
				read: 'Read',
				skipped: 'Skipped',
			};
		}

		return JSON.parse( shell.dataset.statuses );
	}

	function renderReviewArticle( article, context, shell ) {
		var statuses = getReviewStatuses( shell );
		var rating = parseInt( article.rating, 10 ) || 0;
		var status = article.status || 'unread';
		var isCollapsed = 'read' === status || 'skipped' === status;
		var html = '<article class="pc-review-item' + ( isCollapsed ? ' is-collapsed' : '' ) + ' pc-article-notes" data-article-id="' + article.id + '">';
		html += '<div class="pc-review-item-main">';
		html += '<div class="pc-review-title-block"><h2><a href="' + escapeHtml( article.permalink ) + '">' + escapeHtml( article.title ) + '</a></h2><p>' + escapeHtml( article.author || '' );
		if ( article.collection && article.collection !== article.author ) {
			html += '<span>' + escapeHtml( article.collection ) + '</span>';
		}
		if ( article.sent_label ) {
			html += '<span>';
			if ( article.sent_datetime ) {
				html += '<time datetime="' + escapeHtml( article.sent_datetime ) + '">' + escapeHtml( article.sent_label ) + '</time>';
			} else {
				html += escapeHtml( article.sent_label );
			}
			html += '</span>';
		}
		html += '<span class="pc-review-collapsed-meta pc-review-collapsed-status">' + escapeHtml( getCollapsedStatusLabel( status, statuses[ status ] || status ) ) + '</span>';
		if ( parseInt( article.summary_word_count, 10 ) > 0 ) {
			var summaryWordCount = parseInt( article.summary_word_count, 10 );
			html += '<span class="pc-review-collapsed-meta">' + summaryWordCount + ' summary ' + ( 1 === summaryWordCount ? 'word' : 'words' ) + '</span>';
		}
		html += '</p></div></div>';
		if ( article.content ) {
			html += '<details class="pc-review-preview"><summary>Show article</summary><div>' + article.content + '</div></details>';
			html += '<button type="button" class="pc-review-collapse">Collapse</button>';
		}
		html += '<div class="pc-article-notes-controls"><div class="pc-note-statuses" aria-label="Reading status">';
		Object.keys( statuses ).forEach( function ( key ) {
			html += '<button type="button" class="pc-note-status' + ( status === key ? ' is-active' : '' ) + '" data-status="' + escapeHtml( key ) + '">' + escapeHtml( statuses[ key ] ) + '</button>';
		} );
		html += '</div><div class="pc-note-rating" aria-label="Rating" data-rating="' + rating + '">';
		for ( var i = 1; i <= 5; i++ ) {
			html += '<button type="button" class="pc-note-star' + ( i <= rating ? ' is-active' : '' ) + '" data-rating="' + i + '" aria-label="' + i + ' stars">' + ( i <= rating ? '&#9733;' : '&#9734;' ) + '</button>';
		}
		html += '</div></div>';
		html += '<label class="screen-reader-text" for="pc-review-notes-' + article.id + '">Article notes</label>';
		html += '<textarea id="pc-review-notes-' + article.id + '" class="pc-note-text" rows="3" placeholder="Add your notes...">' + escapeHtml( article.notes || '' ) + '</textarea>';
		html += '<div class="pc-article-notes-actions"><button type="button" class="pc-note-save">Save</button>';
		html += '<span class="pc-note-save-status" aria-live="polite"></span></div></article>';
		return html;
	}

	function loadMoreReviewArticles( button ) {
		var shell = getReviewShell( button );
		var listName = button.dataset.list || 'pending';
		var list = getReviewList( shell, listName );
		var context = getArticleNotesContext( shell );
		if ( ! shell || ! list ) {
			return;
		}

		var data = new FormData();
		data.append( 'action', 'post_collection_load_more_pending' );
		data.append( '_ajax_nonce', context.nonce || '' );
		data.append( 'offset', button.dataset.offset || '0' );
		data.append( 'type', button.dataset.type || 'all' );
		if ( shell.dataset.collectionId && '0' !== shell.dataset.collectionId ) {
			data.append( 'collection_id', shell.dataset.collectionId );
		}

		var originalLabel = button.textContent;
		button.disabled = true;
		button.textContent = 'Loading...';

		fetch( context.ajaxAction, {
			method: 'POST',
			credentials: 'same-origin',
			body: data,
		} )
			.then( function ( response ) {
				return response.json();
			} )
			.then( function ( result ) {
				if ( ! result || ! result.success ) {
					throw new Error( 'Could not load articles.' );
				}

				result.data.articles.forEach( function ( article ) {
					list.insertAdjacentHTML( 'beforeend', renderReviewArticle( article, listName, shell ) );
				} );

				button.dataset.offset = result.data.offset;
				if ( ! result.data.has_more ) {
					button.remove();
				}
			} )
			.catch( function () {
				button.disabled = false;
				button.textContent = originalLabel;
			} )
			.finally( function () {
				if ( button.parentNode ) {
					button.disabled = false;
					button.textContent = originalLabel;
				}
			} );
	}

	function toggleReadStatus( toggle ) {
		if ( toggle.disabled ) {
			return;
		}

		var data = new FormData();
		data.append( 'action', 'post_collection_toggle_read_status' );
		data.append( 'post_id', toggle.dataset.postId || '' );
		data.append( '_wpnonce', toggle.dataset.nonce || '' );

		toggle.disabled = true;
		toggle.setAttribute( 'aria-busy', 'true' );

		fetch( toggle.dataset.ajaxAction, {
			method: 'POST',
			credentials: 'same-origin',
			body: data,
		} )
			.then( function ( response ) {
				return response.json();
			} )
			.then( function ( result ) {
				if ( ! result || ! result.success ) {
					throw new Error( result && result.data && result.data.message ? result.data.message : 'Could not save reading status.' );
				}

				updateReadStatusControls( result.data.id, result.data );
				updateArticleNotesStatus( result.data.id, result.data.read_status );
				var row = closest( toggle, '.pc-link-row' );
				if ( row ) {
					updateReadStatus( row, result.data );
				}
			} )
			.catch( function ( error ) {
				toggle.title = error.message;
			} )
			.finally( function () {
				toggle.disabled = false;
				toggle.removeAttribute( 'aria-busy' );
			} );
	}

	function setImportStatus( shell, message ) {
		var status = shell ? shell.querySelector( '[data-import-status]' ) : null;
		if ( status ) {
			status.textContent = message || '';
		}
	}

	function updateImportProgress( shell, completed, total ) {
		var completedNode = shell.querySelector( '[data-import-completed]' );
		var totalNode = shell.querySelector( '[data-import-total]' );
		var progress = shell.querySelector( '[data-import-progress-bar]' );

		if ( completedNode ) {
			completedNode.textContent = completed;
		}
		if ( totalNode ) {
			totalNode.textContent = total;
		}
		if ( progress ) {
			progress.max = total || 1;
			progress.value = completed;
		}
	}

	function addImportLogItem( shell, item, state, message ) {
		var log = shell.querySelector( '[data-import-log]' );
		if ( ! log ) {
			return;
		}

		var row = document.createElement( 'li' );
		row.className = 'is-' + state;

		var label = document.createElement( 'strong' );
		label.textContent = item.title || item.url || '';
		row.appendChild( label );

		var detail = document.createElement( 'span' );
		detail.textContent = message || item.url || '';
		row.appendChild( detail );

		log.appendChild( row );
	}

	function setImportSummary( shell, created, existing, failed ) {
		var summary = shell.querySelector( '[data-import-summary]' );
		if ( ! summary ) {
			return;
		}

		summary.textContent = created + ' new, ' + existing + ' already saved, ' + failed + ' failed.';
	}

	function postImportData( form, data ) {
		return fetch( form.action, {
			method: 'POST',
			credentials: 'same-origin',
			body: data,
		} )
			.then( function ( response ) {
				return response.json();
			} )
			.then( function ( result ) {
				if ( ! result || ! result.success ) {
					throw new Error( result && result.data && result.data.message ? result.data.message : 'Import request failed.' );
				}

				return result.data;
			} );
	}

	function appendItemToImportData( data, item ) {
		data.append( 'url', item.url || '' );
		data.append( 'title', item.title || '' );
		( item.tags || [] ).forEach( function ( tag ) {
			data.append( 'tags[]', tag );
		} );
	}

	function runImport( form ) {
		var shell = document.querySelector( '[data-import-progress]' );
		var button = form.querySelector( 'button[type="submit"]' );
		if ( ! shell ) {
			return;
		}

		shell.hidden = false;
		shell.querySelector( '[data-import-log]' ).textContent = '';
		setImportSummary( shell, 0, 0, 0 );
		updateImportProgress( shell, 0, 0 );
		setImportStatus( shell, 'Reading import source...' );

		if ( button ) {
			button.disabled = true;
			button.textContent = 'Importing...';
		}

		var parseData = new FormData( form );
		parseData.append( 'action', form.dataset.parseAction || 'post_collection_parse_import' );

		postImportData( form, parseData )
			.then( function ( data ) {
				var items = data.items || [];
				var total = items.length;
				var completed = 0;
				var created = 0;
				var existing = 0;
				var failed = 0;

				updateImportProgress( shell, completed, total );
				if ( ! total ) {
					setImportStatus( shell, 'No importable URLs found.' );
					return Promise.resolve();
				}

				setImportStatus( shell, 'Importing 1 of ' + total + '...' );

				return items.reduce( function (promise, item, index ) {
					return promise.then( function () {
						var itemData = new FormData();
						itemData.append( 'action', form.dataset.importAction || 'post_collection_import_item' );
						itemData.append( 'collection_term_id', form.querySelector( 'input[name="collection_term_id"]' ).value );
						itemData.append( '_wpnonce', form.querySelector( 'input[name="_wpnonce"]' ).value );
						appendItemToImportData( itemData, item );

						setImportStatus( shell, 'Importing ' + ( index + 1 ) + ' of ' + total + ': ' + ( item.title || item.url ) );

						return postImportData( form, itemData )
							.then( function ( result ) {
								completed++;
								if ( result.created ) {
									created++;
									addImportLogItem( shell, item, 'created', 'Saved and extracted.' );
								} else {
									existing++;
									addImportLogItem( shell, item, 'existing', 'Already saved.' );
								}
								updateImportProgress( shell, completed, total );
								setImportSummary( shell, created, existing, failed );
							} )
							.catch( function ( error ) {
								completed++;
								failed++;
								addImportLogItem( shell, item, 'failed', error.message || 'Failed.' );
								updateImportProgress( shell, completed, total );
								setImportSummary( shell, created, existing, failed );
							} );
					} );
				}, Promise.resolve() ).then( function () {
					setImportStatus( shell, 'Import complete.' );
				} );
			} )
			.catch( function ( error ) {
				setImportStatus( shell, error.message || 'Import failed.' );
			} )
			.finally( function () {
				if ( button ) {
					button.disabled = false;
					button.textContent = 'Import URLs';
				}
			} );
	}

	var noteSaveTimers = {};

	function queueNoteTextSave( textarea ) {
		var notes = closest( textarea, '.pc-article-notes' );
		if ( ! notes ) {
			return;
		}

		var articleId = notes.dataset.articleId || '';
		if ( noteSaveTimers[ articleId ] ) {
			window.clearTimeout( noteSaveTimers[ articleId ] );
		}

		noteSaveTimers[ articleId ] = window.setTimeout( function () {
			delete noteSaveTimers[ articleId ];
			saveArticleNote( notes, { notes: textarea.value } ).catch( function () {} );
		}, 1000 );
	}

	function saveNoteTextNow( notes ) {
		var textarea = notes.querySelector( '.pc-note-text' );
		if ( ! textarea ) {
			return;
		}

		var articleId = notes.dataset.articleId || '';
		if ( noteSaveTimers[ articleId ] ) {
			window.clearTimeout( noteSaveTimers[ articleId ] );
			delete noteSaveTimers[ articleId ];
		}

		saveArticleNote( notes, { notes: textarea.value } ).catch( function () {} );
	}

	function getCollectionList() {
		return document.querySelector( '.pc-bookmark-board, .pc-link-list, .pc-post-list' );
	}

	function getCollectionListSelector( list ) {
		if ( ! list ) {
			return '';
		}
		if ( list.classList.contains( 'pc-bookmark-board' ) ) {
			return '.pc-bookmark-board';
		}
		if ( list.classList.contains( 'pc-link-list' ) ) {
			return '.pc-link-list';
		}
		if ( list.classList.contains( 'pc-post-list' ) ) {
			return '.pc-post-list';
		}

		return '';
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

		var embed = row.querySelector( '.pc-link-embed' );
		if ( item.embed_html ) {
			if ( ! embed ) {
				embed = document.createElement( 'div' );
				embed.className = 'pc-link-embed';
				if ( urlLink ) {
					urlLink.after( embed );
				}
			}
			embed.innerHTML = item.embed_html;
			embed.hidden = false;
		} else if ( embed ) {
			embed.remove();
			embed = null;
		}

		var excerpt = row.querySelector( '.pc-link-excerpt' );
		if ( ! excerpt && item.excerpt ) {
			excerpt = document.createElement( 'p' );
			excerpt.className = 'pc-link-excerpt';
			if ( embed ) {
				embed.after( excerpt );
			} else if ( urlLink ) {
				urlLink.after( excerpt );
			}
		}
		if ( excerpt ) {
			excerpt.textContent = item.excerpt || '';
			excerpt.hidden = ! item.excerpt || !! item.embed_html;
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
		updateReadStatus( row, item );
		closeEditor( row );
	}

	var infiniteObserver = null;
	var infiniteLoading = false;

	function setPaginationLoading( pagination, nextLink, isLoading ) {
		if ( pagination ) {
			pagination.classList.toggle( 'is-loading', isLoading );
			if ( isLoading ) {
				pagination.setAttribute( 'aria-busy', 'true' );
			} else {
				pagination.removeAttribute( 'aria-busy' );
			}
		}
		if ( nextLink ) {
			if ( ! nextLink.dataset.label ) {
				nextLink.dataset.label = nextLink.textContent;
			}
			nextLink.textContent = isLoading ? 'Loading...' : nextLink.dataset.label;
		}
	}

	function setupInfiniteScroll() {
		if ( infiniteObserver ) {
			infiniteObserver.disconnect();
			infiniteObserver = null;
		}

		if ( ! ( 'IntersectionObserver' in window ) || ! window.fetch || ! window.DOMParser ) {
			return;
		}

		var nextLink = document.querySelector( '.pc-pagination-next' );
		if ( ! nextLink ) {
			return;
		}

		infiniteObserver = new IntersectionObserver( function ( entries ) {
			entries.forEach( function ( entry ) {
				if ( entry.isIntersecting ) {
					loadNextPage();
				}
			} );
		}, { rootMargin: '360px 0px' } );
		infiniteObserver.observe( nextLink );
	}

	function loadNextPage() {
		var currentList = getCollectionList();
		var selector = getCollectionListSelector( currentList );
		var pagination = document.querySelector( '.pc-pagination' );
		var nextLink = document.querySelector( '.pc-pagination-next' );

		if ( infiniteLoading || ! currentList || ! selector || ! nextLink ) {
			return;
		}

		infiniteLoading = true;
		if ( infiniteObserver ) {
			infiniteObserver.disconnect();
		}
		setPaginationLoading( pagination, nextLink, true );

		fetch( nextLink.href, {
			credentials: 'same-origin',
		} )
			.then( function ( response ) {
				if ( ! response.ok ) {
					throw new Error( 'Could not load more items.' );
				}

				return response.text();
			} )
			.then( function ( html ) {
				var doc = new DOMParser().parseFromString( html, 'text/html' );
				var nextList = doc.querySelector( selector );
				if ( ! nextList ) {
					throw new Error( 'Could not load more items.' );
				}

				Array.prototype.forEach.call( nextList.children, function ( child ) {
					currentList.appendChild( document.importNode( child, true ) );
				} );

				var nextPagination = doc.querySelector( '.pc-pagination' );
				pagination = document.querySelector( '.pc-pagination' );
				if ( pagination && nextPagination ) {
					pagination.replaceWith( document.importNode( nextPagination, true ) );
				} else if ( pagination ) {
					pagination.remove();
				}

				infiniteLoading = false;
				setupInfiniteScroll();
			} )
			.catch( function () {
				infiniteLoading = false;
				setPaginationLoading( pagination, nextLink, false );
			} );
	}

	document.addEventListener( 'click', function ( event ) {
		var loadMoreReview = closest( event.target, '.pc-review-load-more' );
		if ( loadMoreReview ) {
			event.preventDefault();
			loadMoreReviewArticles( loadMoreReview );
			return;
		}

		var collapseReview = closest( event.target, '.pc-review-collapse' );
		if ( collapseReview ) {
			var collapseItem = closest( collapseReview, '.pc-review-item' );
			if ( collapseItem ) {
				event.preventDefault();
				setReviewItemCollapsed( collapseItem, true );
			}
			return;
		}

		var readStatus = closest( event.target, '.pc-read-status-toggle' );
		if ( readStatus ) {
			event.preventDefault();
			toggleReadStatus( readStatus );
			return;
		}

		var noteStatus = closest( event.target, '.pc-note-status' );
		if ( noteStatus ) {
			var statusNotes = closest( noteStatus, '.pc-article-notes' );
			if ( statusNotes ) {
				event.preventDefault();
				statusNotes.querySelectorAll( '.pc-note-status' ).forEach( function ( button ) {
					button.classList.toggle( 'is-active', button === noteStatus );
				} );
				saveArticleNote( statusNotes, { status: noteStatus.dataset.status || '' } )
					.then( function () {
						var reviewItem = closest( statusNotes, '.pc-review-item' );
						updateReadStatusControls( statusNotes.dataset.articleId, {
							read_status: noteStatus.dataset.status || '',
							read_label: noteStatus.textContent.trim(),
						} );
						updateReviewItemCollapsedStatus( reviewItem, getCollapsedStatusLabel( noteStatus.dataset.status || '', noteStatus.textContent.trim() ) );
						updateReviewItemCollapseForStatus( reviewItem, noteStatus.dataset.status || '' );
					} )
					.catch( function () {} );
			}
			return;
		}

		var noteStar = closest( event.target, '.pc-note-star' );
		if ( noteStar ) {
			var ratingNotes = closest( noteStar, '.pc-article-notes' );
			var ratingContainer = closest( noteStar, '.pc-note-rating' );
			if ( ratingNotes && ratingContainer ) {
				event.preventDefault();
				var rating = parseInt( noteStar.dataset.rating, 10 ) || 0;
				updateNoteStars( ratingContainer, rating );
				saveArticleNote( ratingNotes, { rating: rating } ).catch( function () {} );
			}
			return;
		}

		var saveNote = closest( event.target, '.pc-note-save' );
		if ( saveNote ) {
			var saveNotes = closest( saveNote, '.pc-article-notes' );
			if ( saveNotes ) {
				event.preventDefault();
				saveNoteTextNow( saveNotes );
			}
			return;
		}

		var collapsedReviewItem = closest( event.target, '.pc-review-item.is-collapsed' );
		if ( collapsedReviewItem && ! closest( event.target, 'a, button, input, textarea, select, details, summary' ) ) {
			event.preventDefault();
			setReviewItemCollapsed( collapsedReviewItem, false );
			return;
		}

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

		var tab = closest( event.target, '[data-pc-tab]' );
		if ( tab ) {
			var tabs = closest( tab, '[data-pc-tabs]' );
			if ( tabs ) {
				event.preventDefault();
				activateTab( tabs, tab );
			}
		}
	} );

	document.addEventListener( 'input', function ( event ) {
		if ( matches( event.target, '.pc-note-text' ) ) {
			queueNoteTextSave( event.target );
		}
	} );

	document.addEventListener( 'change', function ( event ) {
		if ( ! matches( event.target, '.pc-import-file-control input[type="file"]' ) ) {
			return;
		}

		var field = closest( event.target, '.pc-import-file-field' );
		var name = field ? field.querySelector( '[data-import-file-name]' ) : null;
		if ( name ) {
			name.textContent = event.target.files && event.target.files.length ? event.target.files[0].name : 'No file selected';
		}
	} );

	document.addEventListener( 'mouseover', function ( event ) {
		var noteStar = closest( event.target, '.pc-note-star' );
		var ratingContainer = noteStar ? closest( noteStar, '.pc-note-rating' ) : null;
		if ( ratingContainer ) {
			previewNoteStars( ratingContainer, parseInt( noteStar.dataset.rating, 10 ) || 0 );
		}
	} );

	document.addEventListener( 'mouseout', function ( event ) {
		var ratingContainer = closest( event.target, '.pc-note-rating' );
		if ( ratingContainer && ! ratingContainer.contains( event.relatedTarget ) ) {
			clearNoteStarPreview( ratingContainer );
		}
	} );

	document.addEventListener( 'focusin', function ( event ) {
		if ( matches( event.target, '.pc-note-star' ) ) {
			var ratingContainer = closest( event.target, '.pc-note-rating' );
			if ( ratingContainer ) {
				previewNoteStars( ratingContainer, parseInt( event.target.dataset.rating, 10 ) || 0 );
			}
		}
	} );

	document.addEventListener( 'focusout', function ( event ) {
		if ( matches( event.target, '.pc-note-star' ) ) {
			var ratingContainer = closest( event.target, '.pc-note-rating' );
			if ( ratingContainer && ! ratingContainer.contains( event.relatedTarget ) ) {
				clearNoteStarPreview( ratingContainer );
			}
		}
	} );

	document.addEventListener( 'keydown', function ( event ) {
		if ( ! matches( event.target, '[data-pc-tab]' ) ) {
			return;
		}

		if ( 'ArrowLeft' === event.key || 'ArrowUp' === event.key ) {
			event.preventDefault();
			focusAdjacentTab( event.target, -1 );
		} else if ( 'ArrowRight' === event.key || 'ArrowDown' === event.key ) {
			event.preventDefault();
			focusAdjacentTab( event.target, 1 );
		} else if ( 'Home' === event.key ) {
			event.preventDefault();
			focusAdjacentTab( event.target, 'first' );
		} else if ( 'End' === event.key ) {
			event.preventDefault();
			focusAdjacentTab( event.target, 'last' );
		}
	} );

	document.addEventListener( 'blur', function ( event ) {
		if ( matches( event.target, '.pc-note-text' ) ) {
			var notes = closest( event.target, '.pc-article-notes' );
			if ( notes ) {
				saveNoteTextNow( notes );
			}
		}
	}, true );

	document.addEventListener( 'submit', function ( event ) {
		var importForm = closest( event.target, '.pc-import-form' );
		if ( importForm ) {
			event.preventDefault();
			runImport( importForm );
			return;
		}

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

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', setupInfiniteScroll );
	} else {
		setupInfiniteScroll();
	}
}() );
