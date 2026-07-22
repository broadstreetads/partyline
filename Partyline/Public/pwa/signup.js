/* Partyline — public Partyliner signup form. */
(function () {
	'use strict';
	var CFG = window.PL_SIGNUP || {};
	var $ = function (id) { return document.getElementById(id); };

	function token() {
		var el = document.querySelector('#s-turnstile [name="cf-turnstile-response"]');
		return el ? el.value : '';
	}

	function valid() {
		var name = $('s-name').value.trim();
		var email = $('s-email').value.trim();
		var phone = $('s-phone').value.trim();
		var math = $('s-math').value.trim();
		var emailOk = /^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(email);
		var phoneOk = phone.replace(/\D/g, '').length >= 7;
		var mathOk = /^\d{1,3}$/.test(math);
		var turnstileOk = !CFG.turnstileKey || !!token();
		return !!name && emailOk && phoneOk && mathOk && turnstileOk;
	}

	function refresh() { $('s-submit').disabled = !valid(); }

	// Turnstile success re-checks the gate.
	window.sTurnstileCb = refresh;

	function status(msg, kind) {
		var el = $('s-status');
		el.textContent = msg;
		el.className = 'pl-status' + (kind ? ' is-' + kind : '');
	}

	function submit() {
		var btn = $('s-submit');
		btn.disabled = true;
		var label = btn.textContent;
		btn.textContent = 'Signing up…';
		status('', null);

		var fd = new FormData();
		fd.append('name', $('s-name').value);
		fd.append('email', $('s-email').value);
		fd.append('phone', $('s-phone').value);
		fd.append('address', $('s-address').value);
		fd.append('math_answer', $('s-math').value);
		fd.append('math_token', CFG.mathToken || '');
		fd.append('website', $('s-website') ? $('s-website').value : '');
		if (CFG.turnstileKey) { fd.append('turnstile', token()); }

		fetch(CFG.restBase + 'signup', {
			method: 'POST',
			headers: { 'X-WP-Nonce': CFG.nonce },
			credentials: 'same-origin',
			body: fd
		}).then(function (res) {
			return res.json().then(function (data) {
				if (!res.ok) { throw new Error(data && data.message ? data.message : 'Something went wrong.'); }
				return data;
			});
		}).then(function (data) {
			// Replace the form with a confirmation message.
			var main = document.querySelector('.pl-main');
			if (main) {
				main.innerHTML = '<section class="pl-screen"><div class="pl-hero"><h1>📬 Almost there</h1><p>' +
					(data.message || 'Check your email to confirm your Partyliner account.') + '</p></div></section>';
			}
		}).catch(function (err) {
			status(err.message || 'Something went wrong.', 'error');
			btn.textContent = label;
			refresh();
			if (window.turnstile && CFG.turnstileKey) { try { window.turnstile.reset(); } catch (e) {} }
		});
	}

	function boot() {
		['s-name', 's-email', 's-phone', 's-math'].forEach(function (id) {
			$(id).addEventListener('input', refresh);
		});
		$('s-submit').addEventListener('click', submit);
		refresh();
	}

	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', boot); }
	else { boot(); }
})();
