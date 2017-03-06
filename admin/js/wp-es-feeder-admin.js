(function ($) {
	'use strict';
	var settings = {};

	$(window).load(function () {
		init();
	});

	function init() {
		testConnectionClick();
		createIndexClick();
		deleteIndexClick();
		reindexClick();
	}

	function testConnectionClick() {
		$('#es_test_connection').on('click', function (e) {
			if (!checkpoint()) return;
			connectionRequest();
		});
	}

	function createIndexClick() {
		$('#es_create_index').on('click', function (e) {
			if (!checkpoint()) return;
			createIndexRequest();
		});
	}

	function deleteIndexClick() {
		$('#es_delete_index').on('click', function (e) {
			if (!checkpoint()) return;

			var isConfirmed = confirm('Deleting an index removes all of the data stored in that index. Do you want to continue?');
			if (!isConfirmed) {
				return;
			}

			deleteIndexRequest();
		});
	}

	function reindexClick() {
		$('#es_reindex').on('click', function (e) {
			if (!checkpoint()) return;

			deleteIndexRequest()
				.then(function () {
					createIndexRequest();
				}).then(function () {
					processRecords();
				});
		});
	}

	function connectionRequest() {
		return request(settings.server, { method: 'GET' })
			.then(function (data) {
				if (data.error || !data) {
					var errorMessage = 'Connection failed';
					jsonDisplay(
						JSON.stringify($.extend(data, { message: errorMessage }), null, 2)
					);
				}
				else {
					jsonDisplay(JSON.stringify(data, null, 2));
				}
			});
	}

	function createIndexRequest() {
		return request(settings.server + settings.index, { method: 'PUT' })
			.then(function (data) {
				if (data.error) {
					var errorMessage = 'Index creation failed.';
					jsonDisplay(
						JSON.stringify($.extend(data, { message: errorMessage }), null, 2)
					);
				}
				else {
					jsonDisplay(JSON.stringify(data, null, 2));
				}
			});
	}

	function deleteIndexRequest() {
		return request(settings.server + settings.index, { method: 'DELETE' })
			.then(function (data) {
				if (data.error) {
					var errorMessage = 'Index deletion failed';
					jsonDisplay(
						JSON.stringify($.extend(data, { message: errorMessage }), null, 2)
					);
				}
				else {
					jsonDisplay(JSON.stringify(data, null, 2));
				}
			});
	}

	function processRecords() {
		$('.index-spinner').html(renderCounter());

		getPosts().then(function (isProcessing) {
			if (!isProcessing) {
				var timer = setTimeout(function () {
					getCount();
					clearTimeout(timer);
				}, 3000);
			}
		})
			.catch(function (error) {
				$('.index-spinner').empty();
				var errorMessage = 'Error encountered while indexing.';
				jsonDisplay(
					JSON.stringify({ error: true, message: errorMessage }, null, 2)
				);
			});
	}

	function getPosts(page) {
		if (!page) {
			page = 1;
		}
		var origin = document.location.origin;

		return request(origin + '/wp-json/wp/v2/posts?per_page=25&page=' + page)
			.then(function (data) {
				if (!(data instanceof Array)) {
					return true;
				}
				else if (!data.length) {
					return true;
				}

				data.forEach(function(record) {
					indexRecord(record);
				});

				page++;
			})
			.then(function (isEmpty) {
				if (isEmpty) {
					return false;
				}
				return getPosts(page);
			});
	}

	function indexRecord(payload) {
		return request(settings.server + settings.index + '/post', {
			method: 'POST',
			body: JSON.stringify(payload)
		});
	}

	function getCount() {
		request(settings.server + settings.index + '/_count', {
			method: 'GET'
		}).then(function (data) {
			$('.index-spinner')
				.html('<span style="top: 10px; position: absolute;">Indexed ' + data.count + ' records.</span>');
				jsonDisplay(
					JSON.stringify(data, null, 2)
				);
		});
	}

	function checkpoint() {
		getSettings();
		if (!isValidServer()) {
			return false;
		}
		if (!isValidIndex()) {
			return false;
		}
		return true;
	}

	function renderCounter() {
		var html = '<div class="spinner is-active spinner-animation">';
		html += 'Processing... Do not leave this page.';
		html += '</div>';
		return html;
	}

	function request(url, options) {
		return fetch(url, options)
			.then(function (response) {
				return response.json();
			})
			.catch(function (error) {
				return { error: true, message: '' };
			});
	}

	function jsonDisplay(str) {
		if (typeof str !== 'string') {
			throw new Error('jsonDisplay(): argument must be a string');
		}
		$('#es_output').text(str);
	}

	function notice(str) {
		if (typeof str !== 'string') {
			throw new Error('notice(): argument must be a string');
		}
		$('.wp_es_settings').prepend(str);
	}

	function noticeTimer() {
		var timer = setTimeout(function () {
			$('.notice').remove();
			clearTimeout(timer);
		}, 3000);
	}

	function isValidServer() {
		var tmp = settings.server.trim();
		var lastCharacter = tmp.length - 1;
		var validName = new RegExp('(^[a-z0-9\.-\/:]+$)', 'gi');

		if (tmp[lastCharacter] !== '/' || !validName.test(settings.server)) {
			var errorMessage = 'Elasticsearch Server URL is invalid.';
			notice('<div class="notice notice-error"><p>' + errorMessage + '</p></div>');
			noticeTimer();
			return false;
		}

		return true;
	}

	function isValidIndex() {
		var validName = new RegExp('(^[a-z0-9_\.-]+$)', 'gi');

		if (!settings.index || !validName.test(settings.index)) {
			var errorMessage = 'Please supply a valid index name.';
			notice('<div class="notice notice-error"><p>' + errorMessage + '</p></div>');
			noticeTimer();
			return false;
		}

		return true;
	}

	function getSettings() {
		settings = {
			server: $('#es_url').val(),
			index: $('#es_index').val(),
			auth: {
				enabled: $('#es_auth_required').is(':checked'),
				user: $('#es_username').val(),
				password: $('#es_passowrd').val()
			}
		};
		return settings;
	}
})(jQuery);