/**
 * Single post: the table of contents, the reading-position marker, the
 * progress bar and the scroll-to-top button.
 *
 * The TOC is built from the article's own <h2>s at runtime rather than being
 * authored, so a writer never has to maintain one and it can never fall out of
 * step with the post. Headings that already carry an id (the block editor sets
 * one when an anchor is typed) keep it, so existing deep links survive.
 */
(function () {
	'use strict';

	var content = document.querySelector('.v-content');
	if (!content) { return; }

	/* ---- build the TOC -------------------------------------------------- */
	var mounts   = document.querySelectorAll('[data-v-toc]');
	var headings = Array.prototype.slice.call(content.querySelectorAll('h2'))
		.filter(function (h) { return h.textContent.trim().length > 0; });

	var links = [];

	if (mounts.length && headings.length >= 2) {
		var used = Object.create(null);

		headings.forEach(function (h, i) {
			if (!h.id) {
				var base = h.textContent.trim().toLowerCase()
					.replace(/[^\w\s-]/g, '')
					.replace(/\s+/g, '-')
					.replace(/-+/g, '-')
					.slice(0, 60) || ('section-' + (i + 1));
				// Two headings with the same words must not produce the same id,
				// or every link would jump to the first of them.
				var id = base, n = 2;
				while (used[id] || document.getElementById(id)) { id = base + '-' + (n++); }
				used[id] = true;
				h.id = id;
			} else {
				used[h.id] = true;
			}
		});

		mounts.forEach(function (mount) {
			var list = document.createElement('ol');
			list.className = 'v-toc__list';

			headings.forEach(function (h) {
				var li = document.createElement('li');
				var a  = document.createElement('a');
				a.href = '#' + h.id;
				a.textContent = h.textContent.trim();
				li.appendChild(a);
				list.appendChild(li);
				links.push(a);
			});

			mount.appendChild(list);
			mount.hidden = false;
		});
	}

	/* ---- reading position ----------------------------------------------
	 * The active heading is the last one whose top has passed the header's
	 * clearance line. Computed from scroll position rather than from an
	 * IntersectionObserver so that a heading taller than the viewport, or two
	 * headings visible at once, still resolve to exactly one active entry. */
	function clearance() {
		var v = getComputedStyle(document.documentElement).getPropertyValue('--v-header-clear');
		var n = parseFloat(v);
		return isNaN(n) ? 96 : n;
	}

	var progress = null;
	if (document.body.classList.contains('single')) {
		progress = document.createElement('div');
		progress.className = 'v-progress';
		document.body.appendChild(progress);
	}

	var toTop = document.createElement('button');
	toTop.type = 'button';
	toTop.className = 'v-totop';
	toTop.setAttribute('aria-label', 'Back to top');
	toTop.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 19V5M5 12l7-7 7 7"/></svg>';
	toTop.addEventListener('click', function () {
		window.scrollTo({ top: 0, behavior: 'smooth' });
	});
	document.body.appendChild(toTop);

	var ticking = false;

	function update() {
		ticking = false;
		var y    = window.pageYOffset;
		var line = y + clearance() + 12;

		if (links.length) {
			var activeIndex = -1;
			for (var i = 0; i < headings.length; i++) {
				if (headings[i].getBoundingClientRect().top + y <= line) { activeIndex = i; }
			}
			links.forEach(function (a, i) {
				a.classList.toggle('is-active', i === activeIndex);
			});

			// Keep the active entry visible inside a scrolling rail. Guarded on
			// the rail actually being scrollable, so this never hijacks the page.
			if (activeIndex > -1) {
				var a  = links[activeIndex];
				var ul = a.closest('.v-toc');
				if (ul && ul.scrollHeight > ul.clientHeight + 4) {
					var top = a.offsetTop - ul.clientHeight / 2;
					ul.scrollTo({ top: top, behavior: 'auto' });
				}
			}
		}

		if (progress) {
			var box   = content.getBoundingClientRect();
			var start = box.top + y;
			var span  = Math.max(1, content.offsetHeight - window.innerHeight);
			var pct   = Math.min(1, Math.max(0, (y - start) / span));
			progress.style.transform = 'scaleX(' + pct + ')';
		}

		toTop.classList.toggle('is-visible', y > 700);
	}

	window.addEventListener('scroll', function () {
		if (!ticking) { ticking = true; window.requestAnimationFrame(update); }
	}, { passive: true });
	window.addEventListener('resize', update, { passive: true });

	update();
}());
