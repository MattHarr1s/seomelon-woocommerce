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
		 * Guard flag to prevent duplicate AJAX requests.
		 */
		isProcessing: false,

		/**
		 * Initialize all event bindings.
		 */
		init: function () {
			// Dashboard bulk actions.
			$('#seomelon-sync-all').on('click', this.syncAll.bind(this));
			$('#seomelon-scan-all').on('click', this.scanAll.bind(this));
			$('#seomelon-generate-all').on('click', this.generateAll.bind(this));
			$('#seomelon-apply-all').on('click', this.applyAll.bind(this));

			// Row-level actions (delegated).
			$(document).on('click', '.seomelon-action-generate', this.generateSingle.bind(this));
			$(document).on('click', '.seomelon-action-apply', this.applySingle.bind(this));

			// Settings page.
			$('#seomelon-test-connection').on('click', this.testConnection.bind(this));
			$('#seomelon-save-settings').on('click', this.saveSettings.bind(this));
			$('#seomelon-toggle-key').on('click', this.toggleApiKey.bind(this));
			$('#seomelon-register').on('click', this.registerSite.bind(this));
			$('#seomelon-disconnect').on('click', this.disconnectSite.bind(this));

			// Advanced connect toggle.
			$('#seomelon-show-advanced-connect').on('click', function (e) {
				e.preventDefault();
				$('#seomelon-advanced-connect').slideToggle(200);
			});

			// Billing upgrade buttons.
			$(document).on('click', '.seomelon-upgrade-btn', this.upgradePlan.bind(this));

			// Google Search Console.
			$('#seomelon-gsc-connect').on('click', this.gscConnect.bind(this));
			$('#seomelon-gsc-disconnect').on('click', this.gscDisconnect.bind(this));

			// Tab switching.
			$('#seomelon-content-tabs').on('click', '.nav-tab', this.switchContentTab.bind(this));
			$('#seomelon-insight-tabs').on('click', '.nav-tab', this.switchInsightTab.bind(this));

			// Progress modal close.
			$('#seomelon-progress-close').on('click', this.closeProgressModal.bind(this));

			// Pagination.
			this.currentPage = 1;
			this.perPage = 20;
			$('#seomelon-page-prev').on('click', this.prevPage.bind(this));
			$('#seomelon-page-next').on('click', this.nextPage.bind(this));
			this.paginateTable();

			// Clean up polling on page navigation to prevent leaked timers.
			$(window).on('beforeunload', this.stopPoll.bind(this));

			// Live character counts and preview updates for editable suggestion fields.
			this.initEditableFields();

			// Show notice after Stripe checkout redirect.
			this.handleBillingRedirect();
		},

		/* ==================================================================
		   Dashboard Actions
		   ================================================================== */

		/**
		 * Sync all content to the SEOMelon API.
		 */
		syncAll: function (e) {
			e.preventDefault();
			if (this.isProcessing) { return; }

			if (!confirm(seomelon.i18n.confirm_bulk)) {
				return;
			}

			this.isProcessing = true;
			this.setBulkLoading(true, seomelon.i18n.syncing);

			this.ajax('seomelon_sync', {}, function (response) {
				this.isProcessing = false;
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
			if (this.isProcessing) { return; }

			this.isProcessing = true;
			this.setBulkLoading(true, seomelon.i18n.scanning);

			this.ajax('seomelon_scan', {}, function (response) {
				this.isProcessing = false;
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
			if (this.isProcessing) { return; }

			if (!confirm(seomelon.i18n.confirm_bulk)) {
				return;
			}

			this.isProcessing = true;
			this.setBulkLoading(true, seomelon.i18n.generating);

			this.ajax('seomelon_generate', {}, function (response) {
				this.isProcessing = false;
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
		 * Apply suggestions to all generated items sequentially.
		 */
		applyAll: function (e) {
			e.preventDefault();
			if (this.isProcessing) { return; }

			if (!confirm(seomelon.i18n.confirm_bulk)) {
				return;
			}

			var self = this;
			var $rows = $('#seomelon-content-body tr');
			var applyButtons = [];

			// Collect all rows that have "Apply" buttons and generated status.
			$rows.each(function () {
				var $btn = $(this).find('.seomelon-action-apply');
				if ($btn.length) {
					applyButtons.push($btn);
				}
			});

			if (applyButtons.length === 0) {
				this.showBulkStatus('No items to apply.', 'error');
				return;
			}

			self.isProcessing = true;
			self.setBulkLoading(true, seomelon.i18n.applying);
			var applied = 0;
			var total = applyButtons.length;

			function applyNext(index) {
				if (index >= total) {
					self.isProcessing = false;
					self.setBulkLoading(false);
					self.showBulkStatus(seomelon.i18n.success + ' ' + applied + '/' + total + ' items applied.', 'success');
					setTimeout(function () { location.reload(); }, 1500);
					return;
				}

				var $btn = applyButtons[index];
				self.ajax('seomelon_apply', {
					content_id: $btn.data('content-id'),
					post_id: $btn.data('post-id'),
					content_type: $btn.data('content-type') || 'post'
				}, function (response) {
					if (response.success) {
						applied++;
						$btn.closest('tr').find('.seomelon-badge')
							.removeClass('seomelon-badge-grey seomelon-badge-blue')
							.addClass('seomelon-badge-green')
							.text('Applied');
					}
					$('#seomelon-bulk-status').text(seomelon.i18n.applying + ' ' + (index + 1) + '/' + total);
					applyNext(index + 1);
				});
			}

			applyNext(0);
		},

		/**
		 * Generate AI content for a single item.
		 */
		generateSingle: function (e) {
			e.preventDefault();
			var $btn = $(e.currentTarget);
			if ($btn.prop('disabled')) { return; }
			var contentId = $btn.data('content-id');
			var originalText = $btn.text();

			$btn.prop('disabled', true).text(seomelon.i18n.generating);

			this.ajax('seomelon_generate', { content_ids: [contentId] }, function (response) {
				$btn.prop('disabled', false).text(originalText);
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
		 *
		 * On the detail page, collects any user-edited field values and sends
		 * them along with the request so the backend can use those instead of
		 * the original API suggestions.
		 */
		applySingle: function (e) {
			e.preventDefault();
			var $btn = $(e.currentTarget);
			if ($btn.prop('disabled')) { return; }
			var contentId = $btn.data('content-id');
			var postId = $btn.data('post-id');
			var contentType = $btn.data('content-type') || 'post';
			var originalText = $btn.text();

			var data = {
				content_id: contentId,
				post_id: postId,
				content_type: contentType
			};

			// Collect edited values from detail page fields (if present).
			var editFields = {
				meta_title: '#seomelon-edit-meta-title',
				meta_description: '#seomelon-edit-meta-description',
				og_title: '#seomelon-edit-og-title',
				og_description: '#seomelon-edit-og-description',
				aeo_description: '#seomelon-edit-aeo-description'
			};

			$.each(editFields, function (key, selector) {
				var $field = $(selector);
				if ($field.length && $.trim($field.val())) {
					data[key] = $field.val();
				}
			});

			$btn.prop('disabled', true).text(seomelon.i18n.applying);

			this.ajax('seomelon_apply', data, function (response) {
				$btn.prop('disabled', false).text(originalText);
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
			$('#seomelon-register').prop('disabled', true);
			$('#seomelon-register-result').text('Connecting...').removeClass('success error');

			this.ajax('seomelon_register', {
				email: email,
				store_name: storeName
			}, function (response) {
				$('#seomelon-register-spinner').removeClass('is-active');
				if (response.success) {
					$('#seomelon-register-result')
						.text('Connected! Reloading...')
						.addClass('success');
					setTimeout(function () { window.location.reload(); }, 1000);
				} else {
					$('#seomelon-register').prop('disabled', false);
					$('#seomelon-register-result')
						.text((response.data && response.data.message) || seomelon.i18n.error)
						.addClass('error');
				}
			});
		},

		/**
		 * Upgrade to a paid plan via Stripe Checkout.
		 */
		upgradePlan: function (e) {
			e.preventDefault();
			var $btn = $(e.currentTarget);
			var plan = $btn.data('plan');

			$btn.prop('disabled', true).text('Redirecting...');

			this.ajax('seomelon_billing_checkout', { plan: plan }, function (response) {
				if (response.success && response.data.checkout_url) {
					window.location.href = response.data.checkout_url;
				} else if (response.success && response.data.is_beta) {
					// Billing not configured yet — show beta message
					$btn.prop('disabled', false).text('Free during beta');
					alert(response.data.error || 'All features are free during beta!');
				} else {
					$btn.prop('disabled', false).text('Upgrade to ' + plan.charAt(0).toUpperCase() + plan.slice(1));
					alert((response.data && response.data.message) || 'Upgrade failed. Please try again.');
				}
			});
		},

		/**
		 * Show an admin notice after returning from Stripe Checkout.
		 */
		handleBillingRedirect: function () {
			var params = new URLSearchParams(window.location.search);
			var billing = params.get('billing');

			if (!billing) {
				return;
			}

			var $wrap = $('.wrap').first();
			var notice;

			if ('success' === billing) {
				notice = '<div class="notice notice-success is-dismissible"><p><strong>Payment successful!</strong> Your plan has been upgraded. It may take a moment for changes to appear.</p></div>';
			} else if ('cancelled' === billing) {
				notice = '<div class="notice notice-info is-dismissible"><p>Checkout was cancelled. You can upgrade anytime from the plan section below.</p></div>';
			}

			if (notice && $wrap.length) {
				$wrap.find('h1').first().after(notice);
			}

			// Clean up the URL so refreshing doesn't re-show the notice.
			if (window.history.replaceState) {
				params.delete('billing');
				var clean = window.location.pathname + '?' + params.toString();
				window.history.replaceState({}, '', clean);
			}
		},

		/**
		 * Disconnect and clear stored API token so user can reconnect.
		 */
		disconnectSite: function (e) {
			e.preventDefault();

			if (!confirm('Disconnect from SEOMelon? You can reconnect immediately after.')) {
				return;
			}

			this.ajax('seomelon_save_settings', {
				api_key: '',
				api_url: $('#seomelon-api-url').val(),
				content_types: [],
				tone: 'professional',
				auto_sync: 'manual'
			}, function () {
				location.reload();
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

			var targetLocales = [];
			$('input[name="target_locales[]"]:checked').each(function () {
				targetLocales.push($(this).val());
			});

			var data = {
				api_key: $('#seomelon-api-key').val(),
				api_url: $('#seomelon-api-url').val(),
				content_types: contentTypes,
				tone: $('#seomelon-tone').val(),
				auto_sync: $('#seomelon-auto-sync').val(),
				target_locales: targetLocales
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
		   Google Search Console
		   ================================================================== */

		/**
		 * Connect Google Search Console via OAuth.
		 *
		 * Opens the Google authorization URL in a new tab. Once the user
		 * completes authorization, they return to the Settings page.
		 */
		gscConnect: function (e) {
			e.preventDefault();
			var self = this;

			$('#seomelon-gsc-spinner').addClass('is-active');
			$('#seomelon-gsc-result').text('').removeClass('success error');

			this.ajax('seomelon_gsc_connect', {}, function (response) {
				$('#seomelon-gsc-spinner').removeClass('is-active');
				if (response.success && response.data.url) {
					window.open(response.data.url, '_blank', 'width=600,height=700');
					$('#seomelon-gsc-result')
						.text('Complete sign-in in the new window. This page will refresh.')
						.addClass('success');

					// Poll for connection status.
					var pollCount = 0;
					var maxPolls = 60; // 3s * 60 = 3 minutes.
					var pollTimer = setInterval(function () {
						pollCount++;
						if (pollCount > maxPolls) {
							clearInterval(pollTimer);
							$('#seomelon-gsc-result')
								.text('Connection check timed out. Please refresh this page.')
								.addClass('error');
							return;
						}

						self.ajax('seomelon_gsc_status', {}, function (statusResponse) {
							if (statusResponse.success && statusResponse.data.connected) {
								clearInterval(pollTimer);
								location.reload();
							}
						});
					}, 3000);
				} else {
					$('#seomelon-gsc-result')
						.text(response.data.message || seomelon.i18n.error)
						.addClass('error');
				}
			});
		},

		/**
		 * Disconnect Google Search Console.
		 */
		gscDisconnect: function (e) {
			e.preventDefault();

			if (!confirm('Disconnect Google Search Console? You will stop receiving search performance data.')) {
				return;
			}

			this.ajax('seomelon_gsc_disconnect', {}, function (response) {
				if (response.success) {
					location.reload();
				} else {
					alert(response.data.message || seomelon.i18n.error);
				}
			});
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

			// Update active tab.
			$tab.siblings().removeClass('nav-tab-active');
			$tab.addClass('nav-tab-active');

			// Reset to page 1 and re-paginate.
			this.currentPage = 1;
			this.paginateTable();
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
					} else if (status === 'failed' || status === 'error' || status === 'unknown') {
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
		   Pagination
		   ================================================================== */

		paginateTable: function () {
			var $rows = $('#seomelon-content-body tr:visible');
			var total = $rows.length;
			var totalPages = Math.max(1, Math.ceil(total / this.perPage));
			var start = (this.currentPage - 1) * this.perPage;
			var end = start + this.perPage;

			// Show/hide rows based on current page (only visible rows).
			$('#seomelon-content-body tr').each(function (i) {
				if ($(this).is(':visible') || $(this).data('paginated-hidden')) {
					// Only paginate visible rows.
				}
			});

			// Simple approach: hide all, show page slice.
			var visibleIndex = 0;
			$('#seomelon-content-body tr').each(function () {
				var $row = $(this);
				// Respect tab filtering.
				var activeTab = $('#seomelon-content-tabs .nav-tab-active').data('type');
				var rowType = $row.data('content-type');
				var tabVisible = (activeTab === 'all' || !activeTab || rowType === activeTab);

				if (tabVisible) {
					if (visibleIndex >= start && visibleIndex < end) {
						$row.show();
					} else {
						$row.hide();
					}
					visibleIndex++;
				} else {
					$row.hide();
				}
			});

			// Update page display.
			$('#seomelon-page-display').text(this.currentPage + ' / ' + totalPages);
			$('#seomelon-page-prev').prop('disabled', this.currentPage <= 1);
			$('#seomelon-page-next').prop('disabled', this.currentPage >= totalPages);
		},

		prevPage: function (e) {
			e.preventDefault();
			if (this.currentPage > 1) {
				this.currentPage--;
				this.paginateTable();
			}
		},

		nextPage: function (e) {
			e.preventDefault();
			var $rows = $('#seomelon-content-body tr');
			var totalPages = Math.max(1, Math.ceil($rows.length / this.perPage));
			if (this.currentPage < totalPages) {
				this.currentPage++;
				this.paginateTable();
			}
		},

		/* ==================================================================
		   Editable Suggestion Fields
		   ================================================================== */

		/**
		 * Initialize live character counts and preview updates for editable
		 * suggestion fields on the content detail page.
		 */
		initEditableFields: function () {
			var self = this;

			$('.seomelon-edit-field').on('input', function () {
				self.updateCharCount($(this));
			});

			// Update SERP preview when meta title or description changes.
			$('#seomelon-edit-meta-title').on('input', function () {
				var val = $(this).val().substring(0, 60);
				$('#seomelon-serp-title').text(val);
			});

			$('#seomelon-edit-meta-description').on('input', function () {
				var val = $(this).val().substring(0, 160);
				$('#seomelon-serp-description').text(val);
			});

			// Update social preview when OG fields change.
			$('#seomelon-edit-og-title').on('input', function () {
				$('#seomelon-social-title').text($(this).val());
			});

			$('#seomelon-edit-og-description').on('input', function () {
				$('#seomelon-social-desc').text($(this).val());
			});
		},

		/**
		 * Update the character count indicator for an editable field.
		 *
		 * Reads data-min-length and data-max-length from the field to
		 * determine whether the count should show green, yellow, or red.
		 *
		 * @param {jQuery} $field The input or textarea element.
		 */
		updateCharCount: function ($field) {
			var len = $field.val().length;
			var maxLen = parseInt($field.data('max-length'), 10) || 60;
			var minLen = parseInt($field.data('min-length'), 10) || 0;
			var id = $field.attr('id');
			var $counter = $('#seomelon-charcount-' + id.replace('seomelon-edit-', ''));

			if (!$counter.length) {
				return;
			}

			// Update the number.
			$counter.find('.seomelon-charcount-num').text(len);

			// Update the color class.
			$counter.removeClass('seomelon-charcount-ok seomelon-charcount-warn seomelon-charcount-over');

			if (len > maxLen) {
				$counter.addClass('seomelon-charcount-over');
			} else if (len >= minLen && len <= maxLen) {
				$counter.addClass('seomelon-charcount-ok');
			} else {
				$counter.addClass('seomelon-charcount-warn');
			}
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
