(function () {
	'use strict';

	var state = JSON.parse(JSON.stringify(window.WPAIChatAdmin.settings || {}));
	var promptDefault = window.WPAIChatAdmin.prompt || '';

	function restUrl(path, params) {
		var base = new URL(window.WPAIChatAdmin.restUrl, window.location.origin);
		var queryIndex = path.indexOf('?');
		var cleanPath = queryIndex === -1 ? path : path.slice(0, queryIndex);
		var pathQuery = queryIndex === -1 ? '' : path.slice(queryIndex + 1);

		if (base.searchParams.has('rest_route')) {
			base.searchParams.set('rest_route', base.searchParams.get('rest_route').replace(/\/$/, '') + cleanPath);
		} else {
			base.pathname = base.pathname.replace(/\/$/, '') + cleanPath;
		}

		new URLSearchParams(pathQuery).forEach(function (value, key) {
			base.searchParams.set(key, value);
		});
		Object.keys(params || {}).forEach(function (key) {
			base.searchParams.set(key, params[key]);
		});
		return base.toString();
	}

	function api(path, options) {
		options = options || {};
		options.headers = Object.assign({ 'Content-Type': 'application/json', 'X-WP-Nonce': window.WPAIChatAdmin.nonce }, options.headers || {});
		return fetch(restUrl(path), options).then(function (response) {
			return response.json().then(function (json) {
				if (!response.ok) {
					throw new Error(json.message || 'Request failed');
				}
				return json;
			});
		});
	}

	function setStatus(el, text, type) {
		if (!el) return;
		el.textContent = text || '';
		el.className = 'aichat-inline-status' + (type ? ' is-' + type : '');
	}

	function escapeHtml(text) {
		var div = document.createElement('div');
		div.textContent = text == null ? '' : String(text);
		return div.innerHTML;
	}

	function escapeAttr(text) {
		return escapeHtml(text).replace(/"/g, '&quot;');
	}

	function setByPath(obj, path, value) {
		var parts = path.split('.');
		var last = parts.pop();
		var cursor = obj;
		parts.forEach(function (part) {
			cursor[part] = cursor[part] || {};
			cursor = cursor[part];
		});
		cursor[last] = value;
	}

	function formToSettings(form) {
		var next = JSON.parse(JSON.stringify(state));
		form.querySelectorAll('input, textarea, select').forEach(function (field) {
			if (!field.name) return;
			var value;
			if (field.type === 'checkbox') {
				value = field.checked;
			} else if (field.type === 'radio') {
				if (!field.checked) return;
				value = field.value === '1' ? true : field.value === '0' ? false : field.value;
			} else if (field.multiple) {
				value = Array.prototype.slice.call(field.selectedOptions).map(function (option) { return parseInt(option.value, 10); });
			} else {
				value = field.value;
			}
			setByPath(next, field.name, value);
		});
		return next;
	}

	function applySelectValues(root) {
		root.querySelectorAll('select').forEach(function (select) {
			var value = select.name.split('.').reduce(function (obj, key) { return obj && obj[key]; }, state);
			if (Array.isArray(value)) {
				Array.prototype.forEach.call(select.options, function (option) { option.selected = value.indexOf(parseInt(option.value, 10)) !== -1; });
			} else if (value !== undefined) {
				select.value = value;
			}
		});
	}

	function saveSettings(next, statusEl, successText) {
		return api('/settings', { method: 'POST', body: JSON.stringify(next) }).then(function (json) {
			state = json.settings;
			promptDefault = json.settings.system_prompt_preview || promptDefault;
			setStatus(statusEl, successText, 'success');
			return json;
		}).catch(function (error) {
			setStatus(statusEl, error.message, 'error');
		});
	}

	function initConfig() {
		var form = document.getElementById('aichat-settings-form');
		if (!form) return;
		applySelectValues(form);
		var cache = form.querySelector('[name="cache_enabled"]');
		var ttl = form.querySelector('[data-cache-ttl]');
		var override = document.getElementById('aichat-override-toggle');
		var prompt = document.getElementById('aichat-prompt-preview');
		function sync() {
			ttl.hidden = !cache.checked;
			prompt.readOnly = !override.checked;
			if (!override.checked) prompt.value = promptDefault;
		}
		cache.addEventListener('change', sync);
		override.addEventListener('change', sync);
		var togglePassword = form.querySelector('[data-toggle-password]');
		if (togglePassword) {
			togglePassword.addEventListener('click', function () {
				var input = form.querySelector('[name="openai_api_key"]');
				input.type = input.type === 'password' ? 'text' : 'password';
			});
		}
		document.getElementById('aichat-test-connection').addEventListener('click', function () {
			var status = document.getElementById('aichat-test-status');
			setStatus(status, 'Testing connection...', '');
			api('/test-connection', { method: 'POST', body: JSON.stringify(formToSettings(form)) }).then(function (json) {
				setStatus(status, json.message || 'Connection successful.', 'success');
			}).catch(function (error) {
				setStatus(status, error.message, 'error');
			});
		});
		form.addEventListener('submit', function (event) {
			event.preventDefault();
			var next = formToSettings(form);
			if (!override.checked) next.system_prompt_override = '';
			saveSettings(next, document.getElementById('aichat-save-status'), 'Configuration saved.').then(sync);
		});
		sync();
	}

	function icon() {
		return window.WPAIChatAdmin.chatIcon || '';
	}

	function initKnowledge() {
		var table = document.getElementById('aichat-knowledge-table');
		if (!table) return;
		var page = 1;
		function badge(text, color) {
			return '<span class="aichat-badge aichat-badge-' + color + '">' + text + '</span>';
		}
		function load() {
			table.innerHTML = '<p>Loading...</p>';
			api('/knowledge?page=' + page + '&per_page=20', { method: 'GET', headers: { 'X-WP-Nonce': window.WPAIChatAdmin.nonce } }).then(function (json) {
				if (!json.items.length) {
					table.innerHTML = '<div class="aichat-empty">' + icon() + '<span>No content indexed yet. Run a crawl to get started.</span></div>';
					return;
				}
				var rows = json.items.map(function (item) {
					return '<tr data-id="' + item.id + '"><td>' + badge(escapeHtml(item.type), 'green') + '</td><td>' + escapeHtml(item.title) + '</td><td>' + escapeHtml(item.last_synced) + '</td><td>' + (item.is_stale ? badge('Stale', 'yellow') : badge('Synced', 'green')) + '</td><td class="aichat-actions"><button class="aichat-link-button" data-edit>Edit</button>' + (item.is_stale ? '<button class="aichat-link-button" data-resync>Re-sync</button>' : '') + '<button class="aichat-link-button" data-delete>Delete</button></td></tr><tr class="aichat-edit-row" data-edit-row="' + item.id + '" hidden><td colspan="5"><textarea>' + escapeHtml(item.content) + '</textarea><div class="aichat-actions"><button class="aichat-button aichat-button-primary" data-save>Edit Save</button><button class="aichat-button aichat-button-secondary" data-cancel>Cancel</button></div></td></tr>';
				}).join('');
				table.innerHTML = '<table class="aichat-table"><thead><tr><th>Type</th><th>Title</th><th>Last Synced</th><th>Status</th><th>Actions</th></tr></thead><tbody>' + rows + '</tbody></table><div class="aichat-pagination"><button class="aichat-button aichat-button-secondary" data-prev>Previous</button><span>Page ' + json.page + ' of ' + Math.max(1, json.total_pages) + '</span><button class="aichat-button aichat-button-secondary" data-next>Next</button></div>';
				table.querySelector('[data-prev]').disabled = page <= 1;
				table.querySelector('[data-next]').disabled = page >= json.total_pages;
			});
		}
		table.addEventListener('click', function (event) {
			var btn = event.target.closest('button');
			if (!btn) return;
			var row = event.target.closest('tr');
			var id = row ? row.getAttribute('data-id') : null;
			if (btn.hasAttribute('data-prev')) { page--; load(); }
			if (btn.hasAttribute('data-next')) { page++; load(); }
			if (btn.hasAttribute('data-edit')) { table.querySelector('[data-edit-row="' + id + '"]').hidden = false; }
			if (btn.hasAttribute('data-cancel')) { event.target.closest('.aichat-edit-row').hidden = true; }
			if (btn.hasAttribute('data-save')) {
				var editRow = event.target.closest('.aichat-edit-row');
				api('/knowledge/' + editRow.getAttribute('data-edit-row'), { method: 'PUT', body: JSON.stringify({ content: editRow.querySelector('textarea').value }) }).then(load);
			}
			if (btn.hasAttribute('data-delete') && id) api('/knowledge/' + id, { method: 'DELETE' }).then(load);
			if (btn.hasAttribute('data-resync') && id) api('/knowledge/' + id + '/resync', { method: 'POST' }).then(load);
		});
		document.getElementById('aichat-refresh-knowledge').addEventListener('click', load);
		load();
		initCrawl(load);
	}

	function initCrawl(afterDone) {
		var button = document.getElementById('aichat-start-crawl');
		if (!button) return;
		var progress = document.querySelector('.aichat-progress');
		var bar = progress.querySelector('span');
		var log = document.getElementById('aichat-crawl-log');
		var status = document.getElementById('aichat-crawl-status');
		button.addEventListener('click', function () {
			button.disabled = true;
			progress.hidden = false;
			progress.classList.add('is-indeterminate');
			log.hidden = false;
			log.innerHTML = '';
			setStatus(status, 'Crawling...', '');
			fetch(restUrl('/crawl/start', { _wpnonce: window.WPAIChatAdmin.nonce }), { method: 'POST', headers: { 'X-WP-Nonce': window.WPAIChatAdmin.nonce } }).then(function (response) {
				var reader = response.body.getReader();
				var decoder = new TextDecoder();
				var buffer = '';
				function read() {
					return reader.read().then(function (result) {
						if (result.done) return;
						buffer += decoder.decode(result.value, { stream: true });
						buffer.split('\n\n').slice(0, -1).forEach(function (chunk) {
							var line = chunk.replace(/^data:\s*/, '');
							if (!line) return;
							var item = JSON.parse(line);
							if (item.progress) {
								progress.classList.remove('is-indeterminate');
								bar.style.width = item.progress + '%';
							}
							if (item.title) {
								log.insertAdjacentHTML('beforeend', '<div class="aichat-log-line">' + icon() + '<span>' + escapeHtml(item.type) + ': ' + escapeHtml(item.title) + '</span>' + '<span class="aichat-badge aichat-badge-' + (item.status === 'error' ? 'red' : item.status === 'skipped' ? 'yellow' : 'green') + '">' + escapeHtml(item.status) + '</span></div>');
								log.scrollTop = log.scrollHeight;
							}
							if (item.status === 'complete') {
								setStatus(status, 'Crawl complete. ' + item.count + ' items indexed.', 'success');
								afterDone();
							}
						});
						buffer = buffer.split('\n\n').pop();
						return read();
					});
				}
				return read();
			}).catch(function (error) {
				setStatus(status, error.message, 'error');
			}).finally(function () {
				button.disabled = false;
			});
		});
	}

	function previewSettings(form) {
		return formToSettings(form).widget || {};
	}

	function renderPreview(form) {
		var target = document.getElementById('aichat-widget-preview');
		if (!target) return;
		var w = previewSettings(form);
		var avatar = w.bot_avatar_url ? '<img class="aichat-preview-avatar" src="' + escapeAttr(w.bot_avatar_url) + '" alt="">' : '<span class="aichat-preview-avatar"></span>';
		var bubbleIcon = w.bubble_icon === 'custom' && w.bubble_icon_url ? '<img src="' + escapeAttr(w.bubble_icon_url) + '" alt="">' : window.WPAIChatAdmin.chatIcon;
		target.innerHTML = '<div class="aichat-preview-root" style="--bubble:' + escapeAttr(w.bubble_color) + ';--window-bg:' + escapeAttr(w.window_bg) + ';--header-bg:' + escapeAttr(w.header_bg) + ';--header-text:' + escapeAttr(w.header_text_color) + ';--user-bg:' + escapeAttr(w.user_msg_bg) + ';--user-text:' + escapeAttr(w.user_msg_color) + ';--bot-bg:' + escapeAttr(w.bot_msg_bg) + ';--bot-text:' + escapeAttr(w.bot_msg_color) + ';--radius:' + parseInt(w.border_radius || 0, 10) + 'px;--width:' + parseInt(w.window_width || 360, 10) + 'px;--height:' + parseInt(w.window_height || 520, 10) + 'px;--shadow:' + (w.shadow ? '0 18px 60px rgba(0,0,0,.18)' : 'none') + ';font-size:' + parseInt(w.font_size || 14, 10) + 'px;font-family:' + escapeAttr(w.font_family) + '"><div class="aichat-preview-window"><div class="aichat-preview-header">' + avatar + '<span class="aichat-preview-name">' + escapeHtml(w.bot_name) + '</span><button class="aichat-preview-close">' + window.WPAIChatAdmin.xIcon + '</button></div><div class="aichat-preview-messages"><div class="aichat-preview-msg bot">' + escapeHtml(w.welcome_message) + '</div><div class="aichat-preview-msg user">Can you help me?</div><div class="aichat-preview-msg bot">Yes, I can help with questions about this site.</div></div><div class="aichat-preview-input"><span>' + escapeHtml(w.input_placeholder) + '</span><button class="aichat-preview-send">' + window.WPAIChatAdmin.sendIcon + '</button></div></div><button class="aichat-preview-bubble">' + bubbleIcon + '</button></div>';
	}

	function initMedia(root) {
		root.addEventListener('click', function (event) {
			var btn = event.target.closest('[data-media-target]');
			if (!btn || !window.wp || !window.wp.media) return;
			var frame = window.wp.media({ title: 'Select image', multiple: false, library: { type: 'image' } });
			frame.on('select', function () {
				var file = frame.state().get('selection').first().toJSON();
				var input = root.querySelector('[name="' + btn.getAttribute('data-media-target') + '"]');
				if (input) {
					input.value = file.url;
					input.dispatchEvent(new Event('input', { bubbles: true }));
				}
			});
			frame.open();
		});
	}

	function initDesigner() {
		var form = document.getElementById('aichat-widget-form');
		if (!form) return;
		applySelectValues(form);
		initMedia(form);
		form.addEventListener('input', function () { renderPreview(form); });
		form.addEventListener('change', function () { renderPreview(form); });
		form.addEventListener('submit', function (event) {
			event.preventDefault();
			saveSettings(formToSettings(form), document.getElementById('aichat-widget-status'), 'Widget saved.');
		});
		renderPreview(form);
	}

	function initPlacement() {
		var form = document.getElementById('aichat-placement-form');
		if (!form) return;
		applySelectValues(form);
		function sync() {
			var shortcode = form.querySelector('[name="widget.shortcode_mode"][value="1"]').checked;
			var position = form.querySelector('[name="widget.position"]').value;
			form.querySelector('[data-floating-controls]').hidden = shortcode;
			form.querySelector('[data-custom-position]').hidden = position !== 'custom' || shortcode;
		}
		form.addEventListener('change', sync);
		form.addEventListener('submit', function (event) {
			event.preventDefault();
			saveSettings(formToSettings(form), document.getElementById('aichat-placement-status'), 'Placement saved.');
		});
		document.getElementById('aichat-copy-shortcode').addEventListener('click', function () {
			navigator.clipboard.writeText('[wp_aichat]');
			this.textContent = 'Copied: [wp_aichat]';
		});
		sync();
	}

	document.addEventListener('DOMContentLoaded', function () {
		initConfig();
		initKnowledge();
		initDesigner();
		initPlacement();
	});
}());
