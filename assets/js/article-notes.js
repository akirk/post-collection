/**
 * Article Notes Widget JavaScript
 *
 * @package Post_Collection
 */

(function($) {
	'use strict';

	var ArticleNotes = {
		/**
		 * Debounce timer for notes saving.
		 */
		saveTimers: {},

		/**
		 * Initialize the widget.
		 */
		init: function() {
			this.bindEvents();
		},

		/**
		 * Bind event handlers.
		 */
		bindEvents: function() {
			var self = this;

			// Tab switching.
			$(document).on('click', '.post-collection-tab', function(e) {
				e.preventDefault();
				self.switchTab($(this).data('tab'));
			});

			// Status button clicks — radio-style, stay in place.
			$(document).on('click', '.post-collection-status-btn', function(e) {
				e.preventDefault();
				var $btn = $(this);
				var $item = $btn.closest('.post-collection-article-item');
				var articleId = $item.data('article-id');
				var status = $btn.data('status');

				self.saveNote(articleId, { status: status }, $item);

				// Update UI — toggle like radio buttons.
				$item.find('.post-collection-status-btn').removeClass('active');
				$btn.addClass('active');

				// Track the new status on the element for tab-switch sorting.
				$item.data('status', status);
			});

			// Star rating clicks.
			$(document).on('click', '.post-collection-star', function(e) {
				e.preventDefault();
				var $star = $(this);
				var $item = $star.closest('.post-collection-article-item');
				var $ratingContainer = $star.closest('.post-collection-rating');
				var articleId = $item.data('article-id');
				var rating = $star.data('rating');

				self.saveNote(articleId, { rating: rating }, $item);

				// Update UI.
				self.updateStars($ratingContainer, rating);
			});

			// Notes textarea - auto-save with debounce.
			$(document).on('input', '.post-collection-notes', function() {
				var $textarea = $(this);
				var $item = $textarea.closest('.post-collection-article-item');
				var articleId = $item.data('article-id');

				// Clear existing timer for this article.
				if (self.saveTimers[articleId]) {
					clearTimeout(self.saveTimers[articleId]);
				}

				// Set new debounced save.
				self.saveTimers[articleId] = setTimeout(function() {
					self.saveNote(articleId, { notes: $textarea.val() }, $item);
				}, 1000);
			});

			// Save on blur.
			$(document).on('blur', '.post-collection-notes', function() {
				var $textarea = $(this);
				var $item = $textarea.closest('.post-collection-article-item');
				var articleId = $item.data('article-id');

				// Clear pending timer and save immediately.
				if (self.saveTimers[articleId]) {
					clearTimeout(self.saveTimers[articleId]);
					delete self.saveTimers[articleId];
				}

				self.saveNote(articleId, { notes: $textarea.val() }, $item);
			});

			// Explicit save button for notes.
			$(document).on('click', '.post-collection-save-notes-btn', function(e) {
				e.preventDefault();
				var $btn = $(this);
				var $item = $btn.closest('.post-collection-article-item');
				var $textarea = $item.find('.post-collection-notes');
				var articleId = $item.data('article-id');

				// Clear any pending debounce timer.
				if (self.saveTimers[articleId]) {
					clearTimeout(self.saveTimers[articleId]);
					delete self.saveTimers[articleId];
				}

				self.saveNote(articleId, { notes: $textarea.val() }, $item);
			});

			// Create post from selected.
			$(document).on('click', '.post-collection-create-post-btn', function(e) {
				e.preventDefault();
				self.createPostFromSelected();
			});

			// Select all toggle for reviewed list.
			$(document).on('change', '.post-collection-select-all', function() {
				var checked = $(this).prop('checked');
				$('.post-collection-reviewed-list input[type="checkbox"]').prop('checked', checked);
			});

			// Load more articles.
			$(document).on('click', '.post-collection-load-more-btn', function(e) {
				e.preventDefault();
				self.loadMorePending($(this));
			});

			// Archive button clicks.
			$(document).on('click', '.post-collection-archive-btn', function(e) {
				e.preventDefault();
				var $btn = $(this);
				var $item = $btn.closest('.post-collection-article-item');
				var articleId = $item.data('article-id');

				self.saveNote(articleId, { status: 'archived' }, $item);
				self.animateItemRemoval($item);
			});

			// Show another random remembered article.
			$(document).on('click', '.post-collection-remember-another', function(e) {
				e.preventDefault();
				self.loadRandomRemembered();
			});
		},

		/**
		 * Load a random remembered article via AJAX.
		 */
		loadRandomRemembered: function() {
			var $container = $('.post-collection-remember');

			$.get(postCollectionArticleNotes.ajaxurl, {
				action: 'post_collection_random_remembered',
				_ajax_nonce: postCollectionArticleNotes.nonce
			})
				.done(function(response) {
					if (response.success && response.data) {
						var article = response.data;
						$container.find('.post-collection-remember-title')
							.attr('href', article.permalink)
							.text(article.title);
						$container.find('.post-collection-remember-meta')
							.text(article.author);
						$container.find('.post-collection-remember-notes')
							.html(article.notes);

						// Update the article preview if present.
						var $details = $container.find('.post-collection-article-preview');
						if (article.content) {
							if ($details.length) {
								$details.find('.post-collection-article-preview-content').html(article.content);
								$details.removeAttr('open');
							} else {
								var detailsHtml = '<details class="post-collection-article-preview">';
								detailsHtml += '<summary>' + (postCollectionArticleNotes.i18n.showArticle || 'Show article') + '</summary>';
								detailsHtml += '<div class="post-collection-article-preview-content">' + article.content + '</div>';
								detailsHtml += '</details>';
								$container.find('.post-collection-remember-notes').after(detailsHtml);
							}
						} else if ($details.length) {
							$details.remove();
						}
					}
				});
		},

		/**
		 * Switch between tabs.
		 *
		 * @param {string} tab Tab name.
		 */
		switchTab: function(tab) {
			// Before showing the new tab, move articles to their correct lists.
			this.relocateArticles();

			$('.post-collection-tab').removeClass('active');
			$('.post-collection-tab[data-tab="' + tab + '"]').addClass('active');

			$('.post-collection-tab-content').removeClass('active');
			$('.post-collection-tab-content[data-tab="' + tab + '"]').addClass('active');
		},

		/**
		 * Move articles between pending and reviewed lists based on their current status.
		 */
		relocateArticles: function() {
			var self = this;

			// Move read/skipped items from pending to reviewed.
			$('.post-collection-pending-list .post-collection-article-item').each(function() {
				var $item = $(this);
				var status = self.getItemStatus($item);
				if (status === 'read' || status === 'skipped') {
					self.ensureReviewedList();
					$item.addClass('post-collection-reviewed').detach();
					$('.post-collection-reviewed-list').prepend($item);
				}
			});

			// Move unread items from reviewed back to pending.
			$('.post-collection-reviewed-list .post-collection-article-item').each(function() {
				var $item = $(this);
				var status = self.getItemStatus($item);
				if (status === 'unread') {
					$item.removeClass('post-collection-reviewed').detach();
					$('.post-collection-pending-list').prepend($item);
				}
			});
		},

		/**
		 * Get the current status of an article item from its active button.
		 *
		 * @param {jQuery} $item The article item.
		 * @return {string} Status value, or empty string if none selected.
		 */
		getItemStatus: function($item) {
			var $active = $item.find('.post-collection-status-btn.active');
			return $active.length ? $active.data('status') : '';
		},

		/**
		 * Ensure the reviewed list element exists, creating it if needed.
		 */
		ensureReviewedList: function() {
			if ($('.post-collection-reviewed-list').length) {
				return;
			}
			var $reviewedTab = $('.post-collection-tab-content[data-tab="reviewed"]');
			$reviewedTab.find('.post-collection-no-articles').remove();
			$reviewedTab.prepend('<ul class="post-collection-article-list post-collection-reviewed-list"></ul>');
		},

		/**
		 * Update star display.
		 *
		 * @param {jQuery} $container Rating container.
		 * @param {number} rating Current rating.
		 */
		updateStars: function($container, rating) {
			$container.data('rating', rating);
			$container.find('.post-collection-star').each(function(index) {
				var $star = $(this);
				var starRating = index + 1;
				if (starRating <= rating) {
					$star.addClass('active').html('&#9733;');
				} else {
					$star.removeClass('active').html('&#9734;');
				}
			});
		},

		/**
		 * Animate item removal from list.
		 *
		 * @param {jQuery} $item The article item to remove.
		 */
		animateItemRemoval: function($item) {
			$item.addClass('post-collection-moving');
			setTimeout(function() {
				$item.addClass('post-collection-removed');
				setTimeout(function() {
					$item.remove();
				}, 300);
			}, 500);
		},

		/**
		 * Save note via AJAX.
		 *
		 * @param {number} articleId Article post ID.
		 * @param {object} data Data to save.
		 * @param {jQuery} $item Article item element.
		 */
		saveNote: function(articleId, data, $item) {
			var $status = $item.find('.post-collection-save-status');

			$status.text(postCollectionArticleNotes.i18n.saving).addClass('saving');

			var postData = $.extend({
				action: 'post_collection_save_note',
				_ajax_nonce: postCollectionArticleNotes.nonce,
				article_id: articleId
			}, data);

			$.post(postCollectionArticleNotes.ajaxurl, postData)
				.done(function(response) {
					if (response.success) {
						$status.text(postCollectionArticleNotes.i18n.saved)
							.removeClass('saving error')
							.addClass('saved');

						setTimeout(function() {
							$status.text('').removeClass('saved');
						}, 2000);
					} else {
						$status.text(postCollectionArticleNotes.i18n.error)
							.removeClass('saving saved')
							.addClass('error');
					}
				})
				.fail(function() {
					$status.text(postCollectionArticleNotes.i18n.error)
						.removeClass('saving saved')
						.addClass('error');
				});
		},

		/**
		 * Load more articles.
		 *
		 * @param {jQuery} $btn The load more button.
		 */
		loadMorePending: function($btn) {
			var self = this;
			var offset = $btn.data('offset');
			var type = $btn.data('type') || 'pending';
			var originalText = $btn.text();

			$btn.prop('disabled', true).text(postCollectionArticleNotes.i18n.loading || 'Loading...');

			$.post(postCollectionArticleNotes.ajaxurl, {
				action: 'post_collection_load_more_pending',
				_ajax_nonce: postCollectionArticleNotes.nonce,
				offset: offset,
				type: type
			})
				.done(function(response) {
					if (response.success && response.data.articles) {
						var $list = $('.post-collection-pending-list');

						// Append new articles.
						response.data.articles.forEach(function(article) {
							$list.append(self.renderArticleItem(article));
						});

						// Update button offset or hide if no more.
						if (response.data.has_more) {
							$btn.data('offset', response.data.offset);
							$btn.prop('disabled', false).text(originalText);
						} else {
							$btn.closest('.post-collection-load-more-section').remove();
						}
					} else {
						$btn.prop('disabled', false).text(originalText);
					}
				})
				.fail(function() {
					$btn.prop('disabled', false).text(originalText);
				});
		},

		/**
		 * Render an article item HTML.
		 *
		 * @param {object} article Article data.
		 * @return {string} HTML string.
		 */
		renderArticleItem: function(article) {
			var statuses = postCollectionArticleNotes.statuses || {
				'unread': 'Not read yet',
				'read': 'Read',
				'skipped': 'Skipped'
			};

			var hasNote = article.note_id > 0;
			var isReviewed = hasNote && (article.status === 'read' || article.status === 'skipped');
			var itemClass = 'post-collection-article-item' + (isReviewed ? ' post-collection-reviewed' : '');

			var html = '<li class="' + itemClass + '" data-article-id="' + article.id + '">';
			html += '<div class="post-collection-article-header">';
			html += '<label class="post-collection-select-article">';
			html += '<input type="checkbox" name="selected_articles[]" value="' + article.id + '">';
			html += '<a href="' + article.permalink + '" class="post-collection-article-title" target="_blank">' + this.escapeHtml(article.title) + '</a>';
			html += '</label>';
			html += '<span class="post-collection-article-meta">' + this.escapeHtml(article.author);
			if (article.sent_date) {
				html += ' &bull; ' + this.escapeHtml(article.sent_date);
			}
			html += '</span></div>';

			html += '<div class="post-collection-article-controls">';
			html += '<div class="post-collection-status-buttons">';
			for (var key in statuses) {
				var activeClass = (hasNote && article.status === key) ? ' active' : '';
				html += '<button type="button" class="post-collection-status-btn' + activeClass + '" data-status="' + key + '" title="' + statuses[key] + '">' + statuses[key] + '</button>';
			}
			html += '</div>';

			html += '<div class="post-collection-rating" data-rating="' + article.rating + '">';
			for (var i = 1; i <= 5; i++) {
				var starActive = i <= article.rating ? ' active' : '';
				var starChar = i <= article.rating ? '&#9733;' : '&#9734;';
				html += '<button type="button" class="post-collection-star' + starActive + '" data-rating="' + i + '" title="' + i + ' stars">' + starChar + '</button>';
			}
			html += '</div>';

			html += '<button type="button" class="post-collection-archive-btn" title="Archive">Archive</button>';
			html += '</div>';

			html += '<div class="post-collection-notes-wrapper">';
			html += '<textarea class="post-collection-notes" placeholder="Add your notes..." rows="2">' + this.escapeHtml(article.notes || '') + '</textarea>';
			html += '<div class="post-collection-notes-actions">';
			html += '<button type="button" class="button button-small post-collection-save-notes-btn">Save</button>';
			html += '<span class="post-collection-save-status"></span>';
			html += '</div></div>';

			html += '</li>';

			return html;
		},

		/**
		 * Escape HTML entities.
		 *
		 * @param {string} str String to escape.
		 * @return {string} Escaped string.
		 */
		escapeHtml: function(str) {
			if (!str) return '';
			var div = document.createElement('div');
			div.textContent = str;
			return div.innerHTML;
		},

		/**
		 * Create a post from selected reviewed articles.
		 */
		createPostFromSelected: function() {
			var selectedIds = [];
			$('.post-collection-reviewed-list input[type="checkbox"]:checked').each(function() {
				selectedIds.push($(this).val());
			});

			if (selectedIds.length === 0) {
				alert('Please select at least one article.');
				return;
			}

			var postTitle = $('#post-collection-post-title').val();
			var $btn = $('.post-collection-create-post-btn');

			$btn.prop('disabled', true).text('Creating...');

			$.post(postCollectionArticleNotes.ajaxurl, {
				action: 'post_collection_create_post_from_notes',
				_ajax_nonce: postCollectionArticleNotes.nonce,
				article_ids: selectedIds,
				post_title: postTitle
			})
				.done(function(response) {
					if (response.success && response.data.edit_url) {
						window.location.href = response.data.edit_url;
					} else {
						alert(response.data || 'Error creating post');
						$btn.prop('disabled', false).text('Create Post from Selected');
					}
				})
				.fail(function() {
					alert('Error creating post');
					$btn.prop('disabled', false).text('Create Post from Selected');
				});
		}
	};

	// Initialize when DOM is ready.
	$(document).ready(function() {
		ArticleNotes.init();
	});

})(jQuery);
