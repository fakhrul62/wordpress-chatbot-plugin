(function () {
	'use strict';

	function decodeConfig(root) {
		try {
			return JSON.parse(atob(root.getAttribute('data-config') || 'e30='));
		} catch (error) {
			return {};
		}
	}

	function textMessage(role, text) {
		var el = document.createElement('div');
		el.className = 'aichat-message ' + role;
		el.textContent = text;
		return el;
	}

	function init(root) {
		var config = decodeConfig(root);
		var bubble = root.querySelector('#wp-aichat-bubble');
		var win = root.querySelector('#wp-aichat-window');
		var close = root.querySelector('.aichat-close');
		var messages = root.querySelector('#wp-aichat-messages');
		var input = root.querySelector('#wp-aichat-input');
		var send = root.querySelector('#wp-aichat-send');
		var history = [];

		function open() {
			root.classList.add('is-open');
			win.setAttribute('aria-hidden', 'false');
			input.focus();
		}
		function closeWindow() {
			root.classList.remove('is-open');
			win.setAttribute('aria-hidden', 'true');
		}
		function scroll() {
			messages.scrollTop = messages.scrollHeight;
		}
		function add(role, text) {
			messages.appendChild(textMessage(role, text));
			if (role === 'user' || role === 'model') {
				history.push({ role: role, text: text });
				history = history.slice(-10);
			}
			scroll();
		}
		function typing() {
			var el = document.createElement('div');
			el.className = 'aichat-message model';
			el.setAttribute('data-typing', '1');
			el.innerHTML = '<span class="aichat-typing"><span></span><span></span><span></span></span>';
			messages.appendChild(el);
			scroll();
			return el;
		}
		function autoresize() {
			input.style.height = 'auto';
			input.style.height = Math.min(input.scrollHeight, 112) + 'px';
		}
		function sendMessage() {
			var message = input.value.trim();
			if (!message || send.disabled) {
				return;
			}
			add('user', message);
			input.value = '';
			autoresize();
			send.disabled = true;
			var indicator = typing();
			fetch(config.rest_url, {
				method: 'POST',
				headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': config.nonce || '' },
				body: JSON.stringify({ message: message, conversation: history.slice(0, -1) })
			}).then(function (response) {
				return response.json().then(function (json) {
					if (!response.ok) {
						throw new Error(json.message || 'Request failed');
					}
					return json;
				});
			}).then(function (json) {
				indicator.remove();
				add('model', json.response || '');
			}).catch(function () {
				indicator.remove();
				messages.appendChild(textMessage('error', 'Something went wrong. Please try again in a moment.'));
				scroll();
			}).finally(function () {
				send.disabled = false;
				input.focus();
			});
		}

		if (config.welcome_message) {
			add('model', config.welcome_message);
		}
		if (root.classList.contains('wp-aichat-inline')) {
			open();
		}
		bubble.addEventListener('click', function () { root.classList.contains('is-open') ? closeWindow() : open(); });
		close.addEventListener('click', closeWindow);
		send.addEventListener('click', sendMessage);
		input.addEventListener('input', autoresize);
		input.addEventListener('keydown', function (event) {
			if (event.key === 'Enter' && !event.shiftKey) {
				event.preventDefault();
				sendMessage();
			}
		});
	}

	document.addEventListener('DOMContentLoaded', function () {
		document.querySelectorAll('#wp-aichat-root').forEach(init);
	});
}());
