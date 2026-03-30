/**
 * SEOMelon Admin JavaScript
 *
 * Handles AJAX interactions for syncing, scanning, generating, applying,
 * tab switching, progress polling, registration, and settings management.
 *
 * @package SEOMelon
 */

/* global jQuery, seomelon */

(function ($) {
	'use strict';

	var SEOMelon = {

		/**
		 * Currently active poll timer.
		 */
		pollTimer: null,

		/**
		 * Number of poll attempts made for the current job.
		 */
		pollCount: 0,

		/**
		 * Maximum number of poll attempts before giving up (3s * 100 = 5 min).
		 */
		maxPollAttempts: 100,

		/**
		 * Initialize all event bindings.
		 */
		init: function () {
			// Dashboard bulk actions.
			$('#seomelon-sync-all').on('click', this.syncAll.bind(this));
			$('#seomelon-scan-all').on('click', this.scanAll.bind(this));
			$('#seomelon-generate-all').on('click', this.generateAll.bind(this));

			// Row-level actions (delegated).
			$(document).on('click', '.seomelon-action-generate', this.generateSingle.bind(this));
			$(document).on('click', '.seomelon-action-apply', this.applySingle.bind(this));

			// Settings page.
			$('#seomelon-test-connection').on('click', this.testConnection.bind(this));
			$('#seomelon-save-settings').on('click', this.saveSettings.bind(this));
			$('#seomelon-toggle-key').on('click', this.toggleApiKey.bind(this));
			$('#seomelon-register').on('click', this.registerSite.bind(this));

			// Tab switching.
			$('#seomelon-content-tabs').on('click', '.nav-tab', this.switchContentTab.bind(this));
			$('#seomelon-insight-tabs').on('click', '.nav-tab', this.switchInsightTab.bind(this));

			// Progress modal close.
			$('#seomelon-progress-close').on('click', this.closeProgressModal.bind(this));

			// Clean up polling on page navigation to prevent leaked timers.
			$(window).on('beforeunload', this.stopPoll.bind(this));
		},

		/* ==================================================================
		   Dashboard Actions
		   ================================================================== */

		/**
		 * Sync all content to the SEOMelon API.
		 */
		syncAll: function (e) {
			e.preventDefault();

			if (!confirm(seomelon.i18n.confirm_bulk)) {
				return;
			}

			this.setBulkLoading(true, seomelon.i18n.syncing);

			this.ajax('seomelon_sync', {}, function (response) {
				this.setBulkLoading(false);
				if (response.success) {
					this.showBulkStatus(
						seomelon.i18n.success + ' ' + response.data.synced + ' items synced.',
						'success'
					);
					// Refresh the page to show updated content.
					setTimeout(function () { location.reload(); }, 1500);
				} else {
					this.showBulkStatus(response.data.message, 'error');
				}
			}.bind(this));
		},

		/**
		 * Trigger an SEO scan for all synced content.
		 */
		scanAll: function (e) {
			e.preventDefault();

			this.setBulkLoading(true, seomelon.i18n.scanning);

			this.ajax('seomelon_scan', {}, function (response) {
				this.setBulkLoading(false);
				if (response.success && response.data.tracking_id) {
					this.showProgress(seomelon.i18n.scanning, response.data.tracking_id);
				} else if (response.success) {
					this.showBulkStatus(seomelon.i18n.success, 'success');
					setTimeout(function () { location.reload(); }, 1500);
				} else {
					this.showBulkStatus(response.data.message, 'error');
				}
			}.bind(this));
		},

		/**
		 * Trigger AI content generation for all synced content.
		 */
		generateAll: function (e) {
			e.preventDefault();

			if (!confirm(seomelon.i18n.confirm_bulk)) {
				return;
			}

			this.setBulkLoading(true, seomelon.i18n.generating);

			this.ajax('seomelon_generate', {}, function (response) {
				this.setBulkLoading(false);
				if (response.success && response.data.tracking_id) {
					this.showProgress(seomelon.i18n.generating, response.data.tracking_id);
				} else if (response.success) {
					this.showBulkStatus(seomelon.i18n.success, 'success');
					setTimeout(function () { location.reload(); }, 1500);
				} else {
					this.showBulkStatus(response.data.message, 'error');
				}
			}.bind(this));
		},

		/**
		 * Generate AI content for a single item.
		 */
		generateSingle: function (e) {
			e.preventDefault();
			var $btn = $(e.currentTarget);
			var contentId = $btn.data('content-id');

			$btn.prop('disabled', true).text(seomelon.i18n.generating);

			this.ajax('seomelon_generate', { content_ids: [contentId] }, function (response) {
				$btn.prop('disabled', false).text('Generate');
				if (response.success && response.data.tracking_id) {
					this.showProgress(seomelon.i18n.generating, response.data.tracking_id);
				} else if (response.success) {
					location.reload();
				} else {
					alert(response.data.message);
				}
			}.bind(this));
		},

		/**
		 * Apply suggestions to a single content item.
		 */
		applySingle: function (e) {
			e.preventDefault();
			var $btn = $(e.currentTarget);
			var contentId = $btn.data('content-id');
			var postId = $btn.data('post-id');

			$btn.prop('disabled', true).text(seomelon.i18n.applying);

			this.ajax('seomelon_apply', {
				content_id: contentId,
				post_id: postId
			}, function (response) {
				$btn.prop('disabled', false).text('Apply');
				if (response.success) {
					$btn.closest('tr').find('.seomelon-badge')
						.removeClass('seomelon-badge-grey seomelon-badge-blue')
						.addClass('seomelon-badge-green')
						.text('Applied');
					this.showDetailStatus(response.data.message, 'success');
				} else {
					alert(response.data.message);
				}
			}.bind(this));
		},

		/* ==================================================================
		   Settings Actions
		   ================================================================== */

		/**
		 * Test the API connection.
		 */
		testConnection: function (e) {
			e.preventDefault();

			$('#seomelon-test-spinner').addClass('is-active');
			$('#seomelon-test-result').text(seomelon.i18n.testing).removeClass('success error');

			this.ajax('seomelon_test_connection', {}, function (response) {
				$('#seomelon-test-spinner').removeClass('is-active');
				if (response.success) {
					$('#seomelon-test-result')
						.text(seomelon.i18n.connected + ' — ' + (response.data.plan || 'Free'))
						.addClass('success');
				} else {
					$('#seomelon-test-result')
						.text(response.data.message)
						.addClass('error');
				}
			});
		},

		/**
		 * Register the site with SEOMelon API and get an API key.
		 */
		registerSite: function (e) {
			e.preventDefault();

			var email = $('#seomelon-register-email').val();
			var storeName = $('#seomelon-register-name').val();

			if (!email) {
				$('#seomelon-register-result').text('Email is required.').addClass('error');
				return;
			}

			$('#seomelon-register-spinner').addClass('is-active');
			$('#seomelon-register-result').text(seomelon.i18n.registering).removeClass('success error');

			this.ajax('seomelon_register', {
				email: email,
				store_name: storeName
			}, function (response) {
				$('#seomelon-register-spinner').removeClass('is-active');
				if (response.success && response.data.api_key) {
					// Auto-fill the API key field.
					$('#seomelon-api-key').val(response.data.api_key);
					$('#seomelon-register-result')
						.text(seomelon.i18n.success + ' API key set automatically. Click "Save Settings" to continue.')
						.addClass('success');
				} else {
					$('#seomelon-register-result')
						.text(response.data.message || seomelon.i18n.error)
						.addClass('error');
				}
			});
		},

		/**
		 * Save all settings via AJAX.
		 */
		saveSettings: function (e) {
			e.preventDefault();

			$('#seomelon-save-spinner').addClass('is-active');
			$('#seomelon-save-result').text(seomelon.i18n.saving).removeClass('success error');

			var contentTypes = [];
			$('input[name="content_types[]"]:checked').each(function () {
				contentTypes.push($(this).val());
			});

			var data = {
				api_key: $('#seomelon-api-key').val(),
				api_url: $('#seomelon-api-url').val(),
				content_types: contentTypes,
				tone: $('#seomelon-tone').val(),
				auto_sync: $('#seomelon-auto-sync').val()
			};

			this.ajax('seomelon_save_settings', data, function (response) {
				$('#seomelon-save-spinner').removeClass('is-active');
				if (response.success) {
					$('#seomelon-save-result')
						.text(response.data.message)
						.addClass('success');
				} else {
					$('#seomelon-save-result')
						.text(response.data.message)
						.addClass('error');
				}
			});
		},

		/**
		 * Toggle API key field visibility.
		 */
		toggleApiKey: function (e) {
			e.preventDefault();
			var $input = $('#seomelon-api-key');
			var type = $input.attr('type') === 'password' ? 'text' : 'password';
			$input.attr('type', type);

			$(e.currentTarget).find('.dashicons')
				.toggleClass('dashicons-visibility dashicons-hidden');
		},

		/* ==================================================================
		   Tab Switching
		   ================================================================== */

		/**
		 * Switch content type tabs on the dashboard.
		 */
		switchContentTab: function (e) {
			e.preventDefault();
			var $tab = $(e.currentTarget);
			var type = $tab.data('type');

			// Update active tab.
			$tab.siblings().removeClass('nav-tab-active');
			$tab.addClass('nav-tab-active');

			// Filter table rows.
			var $rows = $('#seomelon-content-body tr');

			if (type === 'all') {
				$rows.show();
			} else {
				$rows.each(function () {
					var rowType = $(this).data('content-type');
					$(this).toggle(rowType === type);
				});
			}
		},

		/**
		 * Switch category tabs on the insights page.
		 */
		switchInsightTab: function (e) {
			e.preventDefault();
			var $tab = $(e.currentTarget);
			var category = $tab.data('category');

			$tab.siblings().removeClass('nav-tab-active');
			$tab.addClass('nav-tab-active');

			var $cards = $('.seomelon-insight-card');

			if (category === 'all') {
				$cards.removeClass('seomelon-hidden');
			} else {
				$cards.each(function () {
					var cardCat = $(this).data('category');
					$(this).toggleClass('seomelon-hidden', cardCat !== category);
				});
			}
		},

		/* ==================================================================
		   Progress Polling
		   ================================================================== */

		/**
		 * Show the progress modal and start polling.
		 */
		showProgress: function (title, trackingId) {
			$('#seomelon-progress-title').text(title);
			$('#seomelon-progress-fill').css('width', '10%');
			$('#seomelon-progress-message').text(seomelon.i18n.generating || 'Processing...');
			$('#seomelon-progress-close').hide();
			$('#seomelon-progress-modal').show();

			this.pollJob(trackingId);
		},

		/**
		 * Poll the job status endpoint at intervals.
		 *
		 * The Laravel backend uses 'complete' (not 'completed') as the
		 * terminal success status.
		 */
		pollJob: function (trackingId) {
			var self = this;
			this.pollCount = 0;

			this.pollTimer = setInterval(function () {
				self.pollCount++;

				if (self.pollCount > self.maxPollAttempts) {
					self.stopPoll();
					$('#seomelon-progress-message').text('Job timed out. Please check the dashboard and try again.');
					$('#seomelon-progress-close').show();
					return;
				}

				self.ajax('seomelon_job_status', { tracking_id: trackingId }, function (response) {
					if (!response.success) {
						self.stopPoll();
						$('#seomelon-progress-message').text(response.data.message);
						$('#seomelon-progress-close').show();
						return;
					}

					var data = response.data;
					var progress = data.progress || 0;
					var status = data.status || 'processing';

					$('#seomelon-progress-fill').css('width', progress + '%');

					if (data.message) {
						$('#seomelon-progress-message').text(data.message);
					}

					// Laravel uses 'complete', Shopify used 'completed' -- accept both.
					if (status === 'complete' || status === 'completed' || status === 'done') {
						self.stopPoll();
						$('#seomelon-progress-fill').css('width', '100%');
						$('#seomelon-progress-message').text(data.message || seomelon.i18n.success);
						$('#seomelon-progress-close').show();
					} else if (status === 'failed' || status === 'error') {
						self.stopPoll();
						$('#seomelon-progress-message').text(data.message || seomelon.i18n.error);
						$('#seomelon-progress-close').show();
					}
				});
			}, 3000);
		},

		/**
		 * Stop polling.
		 */
		stopPoll: function () {
			if (this.pollTimer) {
				clearInterval(this.pollTimer);
				this.pollTimer = null;
			}
		},

		/**
		 * Close the progress modal and refresh the page.
		 */
		closeProgressModal: function (e) {
			e.preventDefault();
			$('#seomelon-progress-modal').hide();
			this.stopPoll();
			location.reload();
		},

		/* ==================================================================
		   Utilities
		   ================================================================== */

		/**
		 * Set bulk action loading state.
		 */
		setBulkLoading: function (loading, message) {
			var $spinner = $('#seomelon-bulk-spinner');
			var $btns = $('.seomelon-bulk-actions .button');

			if (loading) {
				$spinner.addClass('is-active');
				$btns.prop('disabled', true);
				$('#seomelon-bulk-status').text(message || '').removeClass('success error');
			} else {
				$spinner.removeClass('is-active');
				$btns.prop('disabled', false);
			}
		},

		/**
		 * Show a status message in the bulk actions bar.
		 */
		showBulkStatus: function (message, type) {
			$('#seomelon-bulk-status')
				.text(message)
				.removeClass('success error')
				.addClass(type);
		},

		/**
		 * Show a status message on the detail page.
		 */
		showDetailStatus: function (message, type) {
			$('#seomelon-detail-status')
				.text(message)
				.removeClass('success error')
				.addClass(type);
		},

		/**
		 * Perform an AJAX request to the SEOMelon backend.
		 */
		ajax: function (action, data, callback) {
			data = data || {};
			data.action = action;
			data.nonce = seomelon.nonce;

			$.post(seomelon.ajax_url, data, function (response) {
				if (typeof callback === 'function') {
					callback(response);
				}
			}).fail(function () {
				if (typeof callback === 'function') {
					callback({ success: false, data: { message: seomelon.i18n.error } });
				}
			});
		}
	};

	$(document).ready(function () {
		SEOMelon.init();
	});

})(jQuery);
