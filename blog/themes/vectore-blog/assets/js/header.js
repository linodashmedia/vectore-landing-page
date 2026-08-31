/**
 * Floating header: docking, hide-on-scroll, and the mobile drawer.
 *
 * This script ONLY toggles classes. With JavaScript off, neither class is ever
 * set and the header is simply a sticky pill with its gap, which is a correct
 * design in its own right. Nothing anywhere may depend on these classes
 * existing.
 */
(function () {
	'use strict';

	var header = document.querySelector('.v-header');
	if (!header) { return; }

	var nav    = header.querySelector('.v-header__nav');
	var burger = header.querySelector('.v-header__burger');

	/* ---- mobile drawer ------------------------------------------------- */
	if (burger && nav) {
		burger.addEventListener('click', function () {
			var open = nav.classList.toggle('is-open');
			burger.setAttribute('aria-expanded', open ? 'true' : 'false');
		});

		// Escape closes it and returns focus to the control that opened it.
		document.addEventListener('keydown', function (e) {
			if (e.key === 'Escape' && nav.classList.contains('is-open')) {
				nav.classList.remove('is-open');
				burger.setAttribute('aria-expanded', 'false');
				burger.focus();
			}
		});
	}

	/* ---- docking + hide on scroll -------------------------------------- */
	var DOCK_AT   = 24;    // px past which the gap closes
	var HIDE_AT   = 240;   // px past which downward scroll may hide the bar
	var UP_NUDGE  = 6;     // px of upward scroll that brings it back

	var lastY   = window.pageYOffset;
	var ticking = false;
	// Set by an in-page anchor click: a TOC jump must never land the reader on
	// a headerless page, so hiding is suppressed briefly after one.
	var holdUntil = 0;

	function update() {
		ticking = false;
		var y = window.pageYOffset;

		header.classList.toggle('is-docked', y > DOCK_AT);

		var held = Date.now() < holdUntil
			|| (nav && nav.classList.contains('is-open'))
			|| header.contains(document.activeElement);

		if (held) {
			header.classList.remove('is-hidden');
		} else if (y > HIDE_AT && y > lastY) {
			header.classList.add('is-hidden');
		} else if (y < lastY - UP_NUDGE || y <= HIDE_AT) {
			header.classList.remove('is-hidden');
		}

		lastY = y;
	}

	window.addEventListener('scroll', function () {
		if (!ticking) { ticking = true; window.requestAnimationFrame(update); }
	}, { passive: true });

	document.addEventListener('click', function (e) {
		var link = e.target.closest && e.target.closest('a[href^="#"]');
		if (link) { holdUntil = Date.now() + 900; }
	});

	update();
}());
