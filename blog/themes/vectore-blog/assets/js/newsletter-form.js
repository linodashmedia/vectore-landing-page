/**
 * Newsletter signup. Progressive enhancement: the form is a real <form> with a
 * real action, so it still submits without JavaScript. This only intercepts it
 * to keep the reader on the page.
 */
(function () {
	'use strict';

	document.addEventListener('submit', function (e) {
		var form = e.target;
		if (!form.classList || !form.classList.contains('v-nl__form')) { return; }

		e.preventDefault();

		var input  = form.querySelector('.v-nl__input');
		var btn    = form.querySelector('.v-nl__btn');
		var status = form.parentNode.querySelector('.v-nl__status');
		var hp     = form.querySelector('.v-nl__hp input');

		function say(state, msg) {
			if (!status) { return; }
			status.setAttribute('data-state', state);
			status.textContent = msg;
		}

		// A filled honeypot is a bot. Show the success message rather than an
		// error, so the bot has nothing to learn from the response.
		if (hp && hp.value) { say('success', 'Thanks, you are on the list.'); return; }

		var email = (input && input.value || '').trim();
		if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
			say('error', 'Please enter a valid email address.');
			if (input) { input.focus(); }
			return;
		}

		var label = btn ? btn.textContent : '';
		if (btn) { btn.disabled = true; btn.textContent = 'Joining...'; }
		say('', '');

		var body = new FormData();
		body.append('action', 'vectore_newsletter');
		body.append('email', email);
		body.append('nonce', form.dataset.nonce || '');
		body.append('source', form.dataset.source || 'blog');

		fetch(form.dataset.endpoint, { method: 'POST', body: body, credentials: 'same-origin' })
			.then(function (r) { return r.json().then(function (j) { return { ok: r.ok, data: j }; }); })
			.then(function (res) {
				if (res.ok && res.data && res.data.success) {
					form.reset();
					say('success', (res.data.data && res.data.data.message) || 'You are on the list.');
				} else {
					var m = res.data && res.data.data && res.data.data.message;
					say('error', m || 'Something went wrong. Please try again.');
				}
			})
			.catch(function () { say('error', 'Network error. Please try again.'); })
			.finally(function () {
				if (btn) { btn.disabled = false; btn.textContent = label; }
			});
	});
}());
