/**
 * FLEXIcontent Pro Templates — Frontend Behavior
 *
 * Augments the Pro Layout teaser-feed CSS with the minimum JS required
 * by WCAG 2.2 — currently just Escape-key dismissal for the bento
 * intro-reveal pattern (1.4.13 Content on Hover or Focus).
 *
 * Contract:
 *   - Looks for elements matching `.fc-cat-pro-list, .fc-mcats-pro-list`
 *   - Only activates when at least one descendant card uses the bento
 *     appearance (`.fcpt-appearance-bento`); otherwise no-op
 *   - When the user presses Escape while focus or hover is inside a
 *     bento card, that card gets `data-fc-dismissed="true"` which the
 *     stylesheet uses to force the intro back to collapsed even while
 *     hover/focus remains inside the card
 *   - The dismissed flag clears when pointer / focus fully leaves the
 *     card so the next hover/focus cycle re-reveals naturally
 *   - Honors prefers-reduced-motion via the existing CSS — JS does not
 *     animate, only flips a data attribute
 *
 * No external dependencies. Modern syntax with feature checks so it
 * degrades to no-op on legacy browsers without throwing.
 *
 * @package FLEXIcontent
 * @since   6.1.0-beta.8
 */

(function () {
	"use strict";

	if (typeof document === "undefined" || !document.querySelectorAll) {
		return;
	}

	var BENTO_SELECTOR = ".fcpt-appearance-bento";
	var CARD_SELECTOR = ".fc-cat-pro-li, .fc-mcats-pro-li";
	var LIST_SELECTOR = ".fc-cat-pro-list, .fc-mcats-pro-list";
	var DISMISSED_ATTR = "data-fc-dismissed";

	/**
	 * Find the bento card that currently contains focus or pointer.
	 * Returns null if none.
	 */
	function findActiveBentoCard(list) {
		var focused = document.activeElement;
		if (focused && list.contains(focused)) {
			var card = focused.closest(CARD_SELECTOR);
			if (card && card.querySelector(BENTO_SELECTOR)) {
				return card;
			}
		}
		var hovered = list.querySelector("[data-fc-hover='true']");
		if (hovered && hovered.querySelector(BENTO_SELECTOR)) {
			return hovered;
		}
		return null;
	}

	function dismiss(card) {
		if (!card) return;
		card.setAttribute(DISMISSED_ATTR, "true");
	}

	function clearDismiss(card) {
		if (!card) return;
		card.removeAttribute(DISMISSED_ATTR);
	}

	/**
	 * Wire a single list. Idempotent via a marker so re-wiring after
	 * AJAX is safe.
	 */
	function wireList(list) {
		if (list.dataset.fcProtemplateBound === "1") return;
		list.dataset.fcProtemplateBound = "1";

		if (!list.querySelector(BENTO_SELECTOR)) return;

		list.addEventListener(
			"mouseover",
			function (ev) {
				var card = ev.target.closest && ev.target.closest(CARD_SELECTOR);
				if (card && list.contains(card)) {
					card.dataset.fcHover = "true";
				}
			},
			false
		);

		list.addEventListener(
			"mouseout",
			function (ev) {
				var card = ev.target.closest && ev.target.closest(CARD_SELECTOR);
				if (!card) return;
				var to = ev.relatedTarget;
				if (to && card.contains(to)) return;
				delete card.dataset.fcHover;
				clearDismiss(card);
			},
			false
		);

		list.addEventListener(
			"focusout",
			function (ev) {
				var card = ev.target.closest && ev.target.closest(CARD_SELECTOR);
				if (!card) return;
				var to = ev.relatedTarget;
				if (to && card.contains(to)) return;
				clearDismiss(card);
			},
			false
		);

		// WCAG 1.4.13 — Escape collapses revealed content while focus /
		// pointer can stay on the card.
		list.addEventListener(
			"keydown",
			function (ev) {
				if (ev.key !== "Escape" && ev.key !== "Esc") return;
				var card = findActiveBentoCard(list);
				if (!card) return;
				dismiss(card);
				ev.stopPropagation();
			},
			false
		);
	}

	/* ── Entry animation (fade-up on scroll) ──────────────────────
	 * Cards are visible by default. We opt-in to the animation by
	 * adding `.fc-pro-entry-ready` on the <ul> — CSS uses that class
	 * to put cards into the pre-animation state (opacity 0). We only
	 * add the class when:
	 *   - JS is loaded (this script runs)
	 *   - User does NOT prefer reduced motion
	 *   - IntersectionObserver is available
	 *
	 * Then IntersectionObserver toggles `.is-visible` per card as it
	 * scrolls into view. Cards already in viewport on init get the
	 * class immediately (no flash).
	 */
	function setupEntryAnimation(list) {
		if (typeof IntersectionObserver === "undefined") return;

		var mq = typeof window !== "undefined" && window.matchMedia
			? window.matchMedia("(prefers-reduced-motion: reduce)")
			: null;
		if (mq && mq.matches) return;

		list.classList.add("fc-pro-entry-ready");
		var items = list.children;
		for (var i = 0; i < items.length; i++) {
			items[i].style.setProperty("--fc-card-index", String(i));
		}

		var io = new IntersectionObserver(
			function (entries) {
				for (var i = 0; i < entries.length; i++) {
					if (entries[i].isIntersecting) {
						entries[i].target.classList.add("is-visible");
						io.unobserve(entries[i].target);
					}
				}
			},
			{ rootMargin: "0px 0px -8% 0px", threshold: 0.05 }
		);

		for (var j = 0; j < items.length; j++) {
			io.observe(items[j]);
		}
	}

	function init() {
		var lists = document.querySelectorAll(LIST_SELECTOR);
		for (var i = 0; i < lists.length; i++) {
			wireList(lists[i]);
			setupEntryAnimation(lists[i]);
		}
	}

	if (document.readyState === "loading") {
		document.addEventListener("DOMContentLoaded", init, { once: true });
	} else {
		init();
	}

	// Re-scan on AJAX category reload / infinite scroll. Cheap, scoped.
	if (typeof MutationObserver !== "undefined") {
		var observer = new MutationObserver(function (mutations) {
			for (var i = 0; i < mutations.length; i++) {
				var added = mutations[i].addedNodes;
				for (var j = 0; j < added.length; j++) {
					var node = added[j];
					if (node.nodeType !== 1) continue;
					if (node.matches && node.matches(LIST_SELECTOR)) {
						wireList(node);
					} else if (node.querySelectorAll) {
						var nested = node.querySelectorAll(LIST_SELECTOR);
						for (var k = 0; k < nested.length; k++) {
							wireList(nested[k]);
						}
					}
				}
			}
		});
		observer.observe(document.documentElement, {
			childList: true,
			subtree: true,
		});
	}
})();
