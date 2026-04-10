/**
 * SEOMelon Manual Schema & FAQ Editor
 *
 * Handles tab switching, dynamic FAQ add/remove, and client-side JSON-LD
 * validation for the manual schema metabox on post/page/product edit screens.
 */

/* global jQuery */

(function ($) {
	'use strict';

	$(document).ready(function () {
		var $wrap = $('.seomelon-manual-wrap');
		if ($wrap.length === 0) {
			return;
		}

		// ── Tab switching ─────────────────────────────────────────
		$wrap.find('.seomelon-manual-tab').on('click', function (e) {
			e.preventDefault();
			var target = $(this).data('tab');
			$wrap.find('.seomelon-manual-tab').removeClass('active');
			$(this).addClass('active');
			$wrap.find('.seomelon-manual-panel').hide();
			$wrap.find('[data-panel="' + target + '"]').show();
		});

		// ── FAQ: add new question ─────────────────────────────────
		$('#seomelon-faq-add').on('click', function (e) {
			e.preventDefault();
			var $list = $('#seomelon-faq-list');
			var nextIndex = $list.find('.seomelon-faq-item-edit').length;
			var tmpl = $('#seomelon-faq-template').html() || '';
			var html = tmpl
				.replace(/{{INDEX}}/g, nextIndex)
				.replace(/{{NUM}}/g, nextIndex + 1);
			$list.append(html);
			$list.find('.seomelon-faq-item-edit').last().find('.seomelon-faq-question').focus();
		});

		// ── FAQ: remove question ──────────────────────────────────
		$(document).on('click', '.seomelon-faq-remove', function (e) {
			e.preventDefault();
			var $item = $(this).closest('.seomelon-faq-item-edit');
			$item.slideUp(150, function () {
				$item.remove();
				// Reindex remaining items so POST array is contiguous.
				$('#seomelon-faq-list .seomelon-faq-item-edit').each(function (i) {
					$(this).attr('data-index', i);
					$(this).find('.seomelon-faq-item-label').text('Question ' + (i + 1));
					$(this).find('input.seomelon-faq-question').attr('name', 'seomelon_faqs[' + i + '][question]');
					$(this).find('textarea.seomelon-faq-answer').attr('name', 'seomelon_faqs[' + i + '][answer]');
				});
			});
		});

		// ── JSON-LD validation ────────────────────────────────────
		$('#seomelon-schema-validate').on('click', function (e) {
			e.preventDefault();
			var $result = $('#seomelon-schema-validate-result');
			var raw = $('#seomelon-manual-schema-json').val().trim();

			$result.removeClass('success error').text('');

			if (raw === '') {
				$result.addClass('error').text('✗ Empty — paste or compose JSON-LD first');
				return;
			}

			try {
				var parsed = JSON.parse(raw);
				if (typeof parsed !== 'object' || parsed === null) {
					throw new Error('Not a JSON object');
				}
				if (!parsed['@context']) {
					$result.addClass('error').text('✗ Missing @context (should be "https://schema.org")');
					return;
				}
				if (!parsed['@type']) {
					$result.addClass('error').text('✗ Missing @type (e.g. Product, Article, Recipe)');
					return;
				}
				$result.addClass('success').text('✓ Valid JSON-LD (' + parsed['@type'] + ')');
			} catch (err) {
				$result.addClass('error').text('✗ Invalid JSON: ' + err.message);
			}
		});
	});
})(jQuery);
