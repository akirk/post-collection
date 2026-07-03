/**
 * Article Notes frontend interactions.
 *
 * @package Post_Collection
 */

(function($) {
	'use strict';

	var ArticleNotes = {
		saveTimers: {},

		init: function() {
			this.bindEvents();
		},

		bindEvents: function() {
			var self = this;

			$(document).on('click', '.post-collection-status-btn', function(e) {
				e.preventDefault();

				var $btn = $(this);
				var $item = $btn.closest('.post-collection-article-item');
				var articleId = $item.data('article-id');
				var status = $btn.data('status');

				self.saveNote(articleId, { status: status }, $item);

				$item.find('.post-collection-status-btn').removeClass('active');
				$btn.addClass('active');
			});

			$(document).on('click', '.post-collection-star', function(e) {
				e.preventDefault();

				var $star = $(this);
				var $item = $star.closest('.post-collection-article-item');
				var $ratingContainer = $star.closest('.post-collection-rating');
				var articleId = $item.data('article-id');
				var rating = $star.data('rating');

				self.saveNote(articleId, { rating: rating }, $item);
				self.updateStars($ratingContainer, rating);
			});

			$(document).on('input', '.post-collection-notes', function() {
				var $textarea = $(this);
				var $item = $textarea.closest('.post-collection-article-item');
				var articleId = $item.data('article-id');

				if (self.saveTimers[articleId]) {
					clearTimeout(self.saveTimers[articleId]);
				}

				self.saveTimers[articleId] = setTimeout(function() {
					self.saveNote(articleId, { notes: $textarea.val() }, $item);
				}, 1000);
			});

			$(document).on('blur', '.post-collection-notes', function() {
				var $textarea = $(this);
				var $item = $textarea.closest('.post-collection-article-item');
				var articleId = $item.data('article-id');

				if (self.saveTimers[articleId]) {
					clearTimeout(self.saveTimers[articleId]);
					delete self.saveTimers[articleId];
				}

				self.saveNote(articleId, { notes: $textarea.val() }, $item);
			});

			$(document).on('click', '.post-collection-save-notes-btn', function(e) {
				e.preventDefault();

				var $item = $(this).closest('.post-collection-article-item');
				var $textarea = $item.find('.post-collection-notes');
				var articleId = $item.data('article-id');

				if (self.saveTimers[articleId]) {
					clearTimeout(self.saveTimers[articleId]);
					delete self.saveTimers[articleId];
				}

				self.saveNote(articleId, { notes: $textarea.val() }, $item);
			});
		},

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

		saveNote: function(articleId, data, $item) {
			var $status = $item.find('.post-collection-save-status');

			$status.text(postCollectionArticleNotes.i18n.saving).addClass('saving');

			$.post(postCollectionArticleNotes.ajaxurl, $.extend({
				action: 'post_collection_save_note',
				_ajax_nonce: postCollectionArticleNotes.nonce,
				article_id: articleId
			}, data))
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
		}
	};

	$(document).ready(function() {
		ArticleNotes.init();
	});
})(jQuery);
