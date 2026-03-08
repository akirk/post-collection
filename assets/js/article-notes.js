/**
 * Article Notes Widget JavaScript
 *
 * @package Send_To_E_Reader
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
			$(document).on('click', '.ereader-tab', function(e) {
				e.preventDefault();
				self.switchTab($(this).data('tab'));
			});

			// Status button clicks.
			$(document).on('click', '.ereader-status-btn', function(e) {
				e.preventDefault();
				var $btn = $(this);
				var $item = $btn.closest('.ereader-article-item');
				var articleId = $item.data('article-id');
				var status = $btn.data('status');
				var currentTab = $('.ereader-tab.active').data('tab');

				self.saveNote(articleId, { status: status }, $item);

				// Update UI.
				$item.find('.ereader-status-btn').removeClass('active');
				$btn.addClass('active');

				// Check if item should move to another tab.
				var shouldMove = false;
				if (currentTab === 'pending') {
					// Any status in pending moves the item out.
					shouldMove = true;
				} else if (currentTab === 'unread' && (status === 'read' || status === 'skipped' || status === 'archived')) {
					// Read, Skipped, or Archived in unread moves to reviewed.
					shouldMove = true;
				} else if (currentTab === 'reviewed' && status === 'unread') {
					// Changing back to unread moves to unread tab.
					shouldMove = true;
				}

				if (shouldMove) {
					self.animateItemRemoval($item);
				}
			});

			// Star rating clicks.
			$(document).on('click', '.ereader-star', function(e) {
				e.preventDefault();
				var $star = $(this);
				var $item = $star.closest('.ereader-article-item');
				var $ratingContainer = $star.closest('.ereader-rating');
				var articleId = $item.data('article-id');
				var rating = $star.data('rating');

				self.saveNote(articleId, { rating: rating }, $item);

				// Update UI.
				self.updateStars($ratingContainer, rating);
			});

			// Notes textarea - save on blur with debounce.
			$(document).on('input', '.ereader-notes', function() {
				var $textarea = $(this);
				var $item = $textarea.closest('.ereader-article-item');
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

			// Also save on blur.
			$(document).on('blur', '.ereader-notes', function() {
				var $textarea = $(this);
				var $item = $textarea.closest('.ereader-article-item');
				var articleId = $item.data('article-id');

				// Clear pending timer and save immediately.
				if (self.saveTimers[articleId]) {
					clearTimeout(self.saveTimers[articleId]);
					delete self.saveTimers[articleId];
				}

				self.saveNote(articleId, { notes: $textarea.val() }, $item);
			});

			// Toggle notes in reviewed list.
			$(document).on('click', '.ereader-notes-preview', function() {
				var $item = $(this).closest('.ereader-article-item');
				$(this).hide();
				$item.find('.ereader-notes-wrapper').show();
				$item.find('.ereader-notes').focus();
			});

			// Create post from selected.
			$(document).on('click', '.ereader-create-post-btn', function(e) {
				e.preventDefault();
				self.createPostFromSelected();
			});

			// Select all toggle for reviewed list.
			$(document).on('change', '.ereader-select-all', function() {
				var checked = $(this).prop('checked');
				$('.ereader-reviewed-list input[type="checkbox"]').prop('checked', checked);
			});

			// Load more pending articles.
			$(document).on('click', '.ereader-load-more-btn', function(e) {
				e.preventDefault();
				self.loadMorePending($(this));
			});

			// Archive button clicks.
			$(document).on('click', '.ereader-archive-btn', function(e) {
				e.preventDefault();
				var $btn = $(this);
				var $item = $btn.closest('.ereader-article-item');
				var articleId = $item.data('article-id');

				self.saveNote(articleId, { status: 'archived' }, $item);
				self.animateItemRemoval($item);
			});
		},

		/**
		 * Switch between tabs.
		 *
		 * @param {string} tab Tab name.
		 */
		switchTab: function(tab) {
			$('.ereader-tab').removeClass('active');
			$('.ereader-tab[data-tab="' + tab + '"]').addClass('active');

			$('.ereader-tab-content').removeClass('active');
			$('.ereader-tab-content[data-tab="' + tab + '"]').addClass('active');
		},

		/**
		 * Update star display.
		 *
		 * @param {jQuery} $container Rating container.
		 * @param {number} rating Current rating.
		 */
		updateStars: function($container, rating) {
			$container.data('rating', rating);
			$container.find('.ereader-star').each(function(index) {
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
			$item.addClass('ereader-moving');
			setTimeout(function() {
				$item.addClass('ereader-removed');
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
			var $status = $item.find('.ereader-save-status');

			$status.text(ereaderArticleNotes.i18n.saving).addClass('saving');

			var postData = $.extend({
				action: 'ereader_save_note',
				_ajax_nonce: ereaderArticleNotes.nonce,
				article_id: articleId
			}, data);

			$.post(ereaderArticleNotes.ajaxurl, postData)
				.done(function(response) {
					if (response.success) {
						$status.text(ereaderArticleNotes.i18n.saved)
							.removeClass('saving error')
							.addClass('saved');

						setTimeout(function() {
							$status.text('').removeClass('saved');
						}, 2000);
					} else {
						$status.text(ereaderArticleNotes.i18n.error)
							.removeClass('saving saved')
							.addClass('error');
					}
				})
				.fail(function() {
					$status.text(ereaderArticleNotes.i18n.error)
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

			$btn.prop('disabled', true).text(ereaderArticleNotes.i18n.loading || 'Loading...');

			$.post(ereaderArticleNotes.ajaxurl, {
				action: 'ereader_load_more_pending',
				_ajax_nonce: ereaderArticleNotes.nonce,
				offset: offset,
				type: type
			})
				.done(function(response) {
					if (response.success && response.data.articles) {
						var $list = $('.ereader-' + type + '-list');

						// Append new articles.
						response.data.articles.forEach(function(article) {
							$list.append(self.renderArticleItem(article));
						});

						// Update button offset or hide if no more.
						if (response.data.has_more) {
							$btn.data('offset', response.data.offset);
							$btn.prop('disabled', false).text(originalText);
						} else {
							$btn.closest('.ereader-load-more-section').remove();
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
			var statuses = ereaderArticleNotes.statuses || {
				'unread': 'Not read yet',
				'read': 'Read',
				'skipped': 'Skipped'
			};

			var html = '<li class="ereader-article-item" data-article-id="' + article.id + '">';
			html += '<div class="ereader-article-header">';
			html += '<a href="' + article.permalink + '" class="ereader-article-title" target="_blank">' + this.escapeHtml(article.title) + '</a>';
			html += '<span class="ereader-article-meta">' + this.escapeHtml(article.author);
			if (article.sent_date) {
				html += ' &bull; ' + this.escapeHtml(article.sent_date);
			}
			html += '</span></div>';

			html += '<div class="ereader-article-controls">';
			html += '<div class="ereader-status-buttons">';
			for (var key in statuses) {
				var activeClass = article.status === key ? ' active' : '';
				html += '<button type="button" class="ereader-status-btn' + activeClass + '" data-status="' + key + '" title="' + statuses[key] + '">' + statuses[key] + '</button>';
			}
			html += '</div>';

			html += '<div class="ereader-rating" data-rating="' + article.rating + '">';
			for (var i = 1; i <= 5; i++) {
				var starActive = i <= article.rating ? ' active' : '';
				var starChar = i <= article.rating ? '&#9733;' : '&#9734;';
				html += '<button type="button" class="ereader-star' + starActive + '" data-rating="' + i + '" title="' + i + ' stars">' + starChar + '</button>';
			}
			html += '</div></div>';

			html += '<div class="ereader-notes-wrapper">';
			html += '<textarea class="ereader-notes" placeholder="Add your notes..." rows="2">' + this.escapeHtml(article.notes || '') + '</textarea>';
			html += '</div>';

			html += '<div class="ereader-save-status"></div>';
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
			$('.ereader-reviewed-list input[type="checkbox"]:checked').each(function() {
				selectedIds.push($(this).val());
			});

			if (selectedIds.length === 0) {
				alert('Please select at least one article.');
				return;
			}

			var postTitle = $('#ereader-post-title').val();
			var $btn = $('.ereader-create-post-btn');

			$btn.prop('disabled', true).text('Creating...');

			$.post(ereaderArticleNotes.ajaxurl, {
				action: 'ereader_create_post_from_notes',
				_ajax_nonce: ereaderArticleNotes.nonce,
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
