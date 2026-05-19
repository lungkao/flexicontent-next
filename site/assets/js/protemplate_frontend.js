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

	function init() {
		var lists = document.querySelectorAll(LIST_SELECTOR);
		for (var i = 0; i < lists.length; i++) {
			wireList(lists[i]);
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
