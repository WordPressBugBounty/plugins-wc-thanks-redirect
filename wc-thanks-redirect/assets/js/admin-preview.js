/* Javascript for WCTR Admin Order Preview */

jQuery(function ($) {

	var $search  = $('#wctr-preview-search');
	var $results = $('#wctr-preview-results');
	var $preview = $('#wctr-order-preview');
	var $summary = $('#wctr-order-preview-summary');
	var $link    = $('#wctr-order-preview-link');
	var $nudge   = $('#wctr-order-preview-nudge');

	var searchTimer   = null;
	var searchRequest = null;
	var previewRequest = null;

	if (!$search.length || typeof wctr_config === 'undefined') {
		return;
	}

	function escapeHtml(value) {
		return $('<div>').text(value == null ? '' : value).html();
	}

	function closeResults() {
		$results.attr('hidden', true).empty();
	}

	function hidePreview() {
		if (previewRequest) {
			previewRequest.abort();
		}

		$preview.attr('hidden', true);
		$summary.empty();
		$link.attr('hidden', true).attr('href', '#');
		$nudge.attr('hidden', true).empty();
	}

	$search.on('input', function () {
		var term = $.trim($search.val());

		hidePreview();

		if (searchTimer) {
			clearTimeout(searchTimer);
		}

		if (term.length < 2) {
			closeResults();
			return;
		}

		searchTimer = setTimeout(function () {
			if (searchRequest) {
				searchRequest.abort();
			}

			$results.attr('hidden', false).html(
				'<li class="wctr-preview-results__item wctr-preview-results__item--muted">' + escapeHtml(wctr_config.labels.preview_searching) + '</li>'
			);

			searchRequest = $.ajax({
				url: wctr_config.restApiBase + 'preview/orders',
				method: 'GET',
				dataType: 'json',
				data: { search: term },
				beforeSend: function (xhr) {
					xhr.setRequestHeader('X-WP-Nonce', wctr_config.nonce);
				}
			}).done(function (orders) {
				$results.empty();

				if (!orders || !orders.length) {
					$results.append(
						'<li class="wctr-preview-results__item wctr-preview-results__item--muted">' + escapeHtml(wctr_config.labels.preview_no_results) + '</li>'
					);
					return;
				}

				$.each(orders, function (index, order) {
					var $item = $('<li>', {
						'class': 'wctr-preview-results__item',
						'text': order.text,
						'data-id': order.id,
						'data-text': order.text,
						'tabindex': 0,
						'role': 'option'
					});
					$results.append($item);
				});
			});
		}, 300);
	});

	$results.on('click keydown', '.wctr-preview-results__item[data-id]', function (event) {
		if (event.type === 'keydown' && event.key !== 'Enter' && event.key !== ' ') {
			return;
		}

		event.preventDefault();

		var id   = $(this).data('id');
		var text = $(this).data('text');

		$search.val(text);
		closeResults();
		loadPreview(id);
	});

	$(document).on('click', function (event) {
		if (!$(event.target).closest('#wctr-preview-search, #wctr-preview-results').length) {
			closeResults();
		}
	});

	function renderPreview(data) {
		var modeNoticeKey = data.mode.customer_sees_this ? 'preview_live_notice' : 'preview_sandbox_notice';
		var modeClass      = data.mode.customer_sees_this ? 'wctr-badge-live' : 'wctr-badge-sandbox';
		var redirectText   = data.redirect.url ? data.redirect.label + ': ' + data.redirect.url : data.redirect.label;

		var html = '<p class="' + modeClass + ' rounded d-inline-block px-2 py-1 mb-2 small">' + escapeHtml(wctr_config.labels[modeNoticeKey]) + '</p>';
		html += '<p class="wctr-order-preview__redirect mb-0"><strong>' + escapeHtml(wctr_config.labels.preview_redirect) + ':</strong> ' + escapeHtml(redirectText) + '</p>';

		$summary.html(html);
		$link.attr('href', data.preview_url).removeAttr('hidden');

		if (wctr_config.showUpsell && wctr_config.labels.preview_nudge) {
			$nudge.html(wctr_config.labels.preview_nudge).removeAttr('hidden');
		}
	}

	function loadPreview(orderId) {
		$preview.removeAttr('hidden');
		$summary.html('<p class="wctr-text-muted small mb-0">' + escapeHtml(wctr_config.labels.preview_loading) + '</p>');
		$link.attr('hidden', true);

		previewRequest = $.ajax({
			url: wctr_config.restApiBase + 'preview/orders/' + encodeURIComponent(orderId),
			method: 'GET',
			dataType: 'json',
			beforeSend: function (xhr) {
				xhr.setRequestHeader('X-WP-Nonce', wctr_config.nonce);
			}
		}).done(function (data) {
			renderPreview(data);
		}).fail(function (jqXHR, textStatus) {
			if ('abort' === textStatus) {
				return;
			}
			$summary.html('<p class="text-danger small mb-0">' + escapeHtml(wctr_config.labels.preview_error) + '</p>');
		});
	}
});
