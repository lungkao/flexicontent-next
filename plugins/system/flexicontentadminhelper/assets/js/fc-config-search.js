/**
 * FLEXIcontent admin config search bar.
 *
 * Goal: a universal, robust filter bar for Joomla admin forms with hundreds
 * of fields. Works across com_config, com_modules edit, com_plugins edit,
 * FLEXIcontent component edit screens, and arbitrary future admin views,
 * because it discovers field clusters from the DOM instead of relying on
 * a specific form id or markup convention.
 *
 * Architecture:
 *
 *   findFieldCluster()  Identifies the ancestor DOM node that contains the
 *                       largest cluster of field groups. The bar is anchored
 *                       above that node. No reliance on form id / name.
 *
 *   buildIndex()        Captures one record per visible field group.
 *                       "Visible" = computed style not none AND no ancestor
 *                       is an inactive `.tab-pane` or `[role="tabpanel"]`
 *                       lacking `.active`. Records carry haystack text from
 *                       label + description + name + placeholder + option
 *                       text. Result is i18n-agnostic — we never assume
 *                       English.
 *
 *   applyFilter()       Hides non-matching groups via inline `display:none`
 *                       and a `data-fc-search-hidden` marker. Matching ones
 *                       are restored. Counter + live region update with
 *                       a separate debounce to avoid AT spam.
 *
 *   MutationObserver    Watches the cluster for DOM and class changes,
 *                       excluding our own mutations via an attributeFilter
 *                       that omits `style` + a re-entrancy guard.
 *
 *   Tab integration     Listens to Bootstrap, joomla-tab, and generic
 *                       custom-event names. Falls back to the class-change
 *                       mutations on `.tab-pane`.
 *
 * Accessibility:
 *
 *   - <nav role="search" aria-label> wrapper
 *   - <input aria-label aria-controls> + <button aria-label aria-controls>
 *   - aria-live="polite" aria-atomic="true" region with announce-debounce
 *     and change-detection key
 *   - Pre-hide focus rescue moves focus to the search input before its host
 *     group is hidden
 *   - `invalid` event listener (capture phase) reveals hidden invalid
 *     fields and switches to their tab before browser surfacing
 *   - Enter key in search does NOT submit the form
 *   - prefers-reduced-motion / focus-visible respected via CSS
 *   - Idempotent: re-init replaces prior bar, disconnects prior observer
 */
(function () {
	'use strict';

	// ---------- Config ----------

	var cfg = (window.FlexicontentAdminHelper && window.FlexicontentAdminHelper.config) || {};
	var S   = cfg.strings || {};

	var MIN_CHARS         = cfg.minChars         || 2;
	var INPUT_DEBOUNCE    = cfg.inputDebounce    || 150;
	var ANNOUNCE_DELAY    = cfg.announceDelay    || 600;
	var MUTATION_DEBOUNCE = cfg.mutationDebounce || 300;
	var DIAG              = !!cfg.diag;

	// Field-group selectors: Joomla legacy, Bootstrap 5, custom hooks.
	var FIELD_SELECTOR = '.control-group, .form-group, [data-fc-field-group]';
	// Tab pane selectors: Bootstrap + ARIA + joomla-tab-element.
	var TAB_PANE_SELECTOR = '.tab-pane, [role="tabpanel"], joomla-tab-element';

	// ---------- Module state ----------

	var rootContainer = null;
	var bar           = null;
	var input         = null;
	var clearBtn      = null;
	var counter       = null;
	var liveRegion    = null;

	var index         = [];
	var lastQuery     = '';
	var lastAnnouncedKey = '';

	var inputTimer    = null;
	var announceTimer = null;
	var mutationTimer = null;

	var mutationObserver = null;
	var observerSuppressed = false;

	// ---------- Utilities ----------

	function debug() {
		if (!DIAG && !window.FLEXICONTENT_ADMIN_HELPER_DEBUG) { return; }
		try { console.debug.apply(console, ['[fc-search]'].concat([].slice.call(arguments))); } catch (_e) {}
	}

	function ready(fn) {
		if (document.readyState === 'complete' || document.readyState === 'interactive') {
			// Defer a tick so Joomla "recall" tab plugin + late inserts settle.
			setTimeout(fn, 50);
		} else {
			document.addEventListener('DOMContentLoaded', function () { setTimeout(fn, 50); }, { once: true });
		}
	}

	function isModalOpen() {
		return !!document.querySelector('.modal.show, .modal.in, dialog[open]');
	}

	function textOf(el) {
		return (el && el.textContent ? el.textContent : '').replace(/\s+/g, ' ').trim().toLowerCase();
	}

	function isElementVisible(el) {
		if (!el || !el.isConnected) { return false; }
		// offsetParent is null for display:none subtrees (cheap & accurate for our use).
		if (el.offsetParent === null && getComputedStyle(el).position !== 'fixed') {
			// But it's null also for `display:none` only — that is exactly what we want to flag.
			// We still need to allow elements hidden purely by our marker.
			if (el.dataset && el.dataset.fcSearchHidden) { return true; /* hidden by us — treat as visible-in-scope */ }
			return false;
		}
		return true;
	}

	function isInsideInactiveTabPane(el) {
		var node = el.parentElement;
		while (node && node !== document.body) {
			if (node.matches && node.matches(TAB_PANE_SELECTOR)) {
				// Active when: has .active class OR `active` attribute OR aria-hidden!=true OR is visible.
				var hasActiveClass = node.classList && node.classList.contains('active');
				var hasActiveAttr  = node.hasAttribute && node.hasAttribute('active');
				var ariaHidden     = node.getAttribute && node.getAttribute('aria-hidden') === 'true';

				if (!hasActiveClass && !hasActiveAttr && (ariaHidden || !isElementVisible(node))) {
					return true;
				}
			}
			node = node.parentElement;
		}
		return false;
	}

	function gatherFieldText(group) {
		var bits = [];

		var labels = group.querySelectorAll('label, .control-label, legend');
		labels.forEach(function (l) { bits.push(textOf(l)); });

		var helps = group.querySelectorAll('.field-description, .form-text, small.help-block, .help-block');
		helps.forEach(function (h) { bits.push(textOf(h)); });

		var named = group.querySelectorAll('[name]');
		named.forEach(function (n) {
			var nm = n.getAttribute('name') || '';
			bits.push(nm.replace(/[\[\]_\-]+/g, ' ').toLowerCase());
			var ph = n.getAttribute && n.getAttribute('placeholder');
			if (ph) { bits.push(ph.toLowerCase()); }
		});

		var options = group.querySelectorAll('option');
		options.forEach(function (o) { bits.push(textOf(o)); });

		// Backstop: scrape the group's own text content too — covers labels rendered as plain text.
		bits.push(textOf(group));

		return bits.filter(Boolean).join(' ');
	}

	// ---------- Cluster discovery ----------

	function findFieldCluster() {
		var groups = document.querySelectorAll(FIELD_SELECTOR);
		if (!groups.length) { return null; }

		// Walk up each group's chain; tally hits per ancestor.
		var counts = new Map();
		var depths = new Map();
		Array.prototype.forEach.call(groups, function (g) {
			var node = g.parentElement, depth = 0;
			while (node && node !== document.body) {
				counts.set(node, (counts.get(node) || 0) + 1);
				if (!depths.has(node)) { depths.set(node, depth); }
				node = node.parentElement;
				depth++;
			}
		});

		// Best = max count, tiebreak: prefer <form>, then deepest (smallest, most specific).
		var best = null, bestCount = 0, bestIsForm = false, bestDepth = -1;
		counts.forEach(function (count, node) {
			if (count < bestCount) { return; }
			var isForm = node.tagName === 'FORM';
			var depth  = depths.get(node) || 0;
			if (count > bestCount
				|| (!bestIsForm && isForm)
				|| (isForm === bestIsForm && depth > bestDepth)) {
				best = node;
				bestCount = count;
				bestIsForm = isForm;
				bestDepth = depth;
			}
		});

		return best;
	}

	// ---------- Bar construction ----------

	function teardownExistingBar() {
		var prior = document.querySelector('[data-fc-search-bar]');
		if (prior && prior.parentNode) { prior.parentNode.removeChild(prior); }
		if (mutationObserver) { try { mutationObserver.disconnect(); } catch (_e) {} mutationObserver = null; }
	}

	function buildBar(anchor) {
		teardownExistingBar();

		var nav = document.createElement('nav');
		nav.className = 'fc-config-search';
		nav.setAttribute('role', 'search');
		nav.setAttribute('aria-label', S.navLabel || 'Filter form settings');
		nav.setAttribute('data-fc-search-bar', '1');

		var wrap = document.createElement('div');
		wrap.className = 'fc-config-search__wrap';

		var inputId = 'fc-config-search-input';
		input = document.createElement('input');
		input.type            = 'search';
		input.id              = inputId;
		input.className       = 'form-control fc-config-search__input';
		input.placeholder     = S.searchPlaceholder || '';
		input.autocomplete    = 'off';
		input.spellcheck      = false;
		input.setAttribute('aria-label', S.searchLabel || 'Search form settings');

		clearBtn = document.createElement('button');
		clearBtn.type = 'button';
		clearBtn.className = 'fc-config-search__clear';
		clearBtn.setAttribute('aria-label', S.clearLabel || 'Clear search');
		clearBtn.setAttribute('aria-controls', inputId);
		clearBtn.hidden = true;
		clearBtn.textContent = '×';

		counter = document.createElement('span');
		counter.className = 'fc-config-search__counter';
		counter.setAttribute('aria-hidden', 'true');

		liveRegion = document.createElement('div');
		liveRegion.className = 'fc-config-search__live visually-hidden';
		liveRegion.setAttribute('aria-live', 'polite');
		liveRegion.setAttribute('aria-atomic', 'true');

		wrap.appendChild(input);
		wrap.appendChild(clearBtn);
		wrap.appendChild(counter);
		nav.appendChild(wrap);
		nav.appendChild(liveRegion);

		// Insertion strategy: prefer just-inside the form (so it stays with form when scrolling).
		// Falls back to immediately before the cluster's wrapper.
		var hostForm = anchor.closest ? anchor.closest('form') : null;
		var insertionParent = hostForm ? hostForm.parentNode : anchor.parentNode;
		var insertionRef    = hostForm || anchor;
		insertionParent.insertBefore(nav, insertionRef);

		bar = nav;
		anchor.setAttribute('data-fc-search-root', '1');
	}

	// ---------- Indexing ----------

	function buildIndex() {
		index = [];
		if (!rootContainer) { return; }

		var groups = rootContainer.querySelectorAll(FIELD_SELECTOR);
		Array.prototype.forEach.call(groups, function (group) {
			if (isInsideInactiveTabPane(group)) { return; }
			// Skip groups hidden by something other than us (e.g. Joomla showon).
			if (!isElementVisible(group)) { return; }
			var hay = gatherFieldText(group);
			if (!hay) { return; }
			index.push({ el: group, hay: hay });
		});

		debug('indexed', index.length, 'groups under', rootContainer);
	}

	// ---------- Filtering ----------

	function suppressObserver(fn) {
		observerSuppressed = true;
		try { fn(); } finally {
			requestAnimationFrame(function () { observerSuppressed = false; });
		}
	}

	function rescueFocusBeforeHide(group) {
		if (!group.contains(document.activeElement)) { return; }
		if (input && typeof input.focus === 'function') {
			input.focus({ preventScroll: true });
		}
	}

	function applyFilter(query, scopeChanged) {
		var q = (query || '').toLowerCase().trim();
		var tokens = q ? q.split(/\s+/) : [];
		var useFilter = tokens.length > 0 && q.length >= MIN_CHARS;

		var totalMatches = 0;

		suppressObserver(function () {
			for (var i = 0; i < index.length; i++) {
				var rec = index[i];
				var matches = true;
				if (useFilter) {
					for (var t = 0; t < tokens.length; t++) {
						if (rec.hay.indexOf(tokens[t]) === -1) { matches = false; break; }
					}
				}
				if (matches) {
					if (rec.el.dataset.fcSearchHidden) {
						rec.el.style.display = '';
						delete rec.el.dataset.fcSearchHidden;
					}
					if (useFilter) { totalMatches++; }
				} else if (!rec.el.dataset.fcSearchHidden) {
					rescueFocusBeforeHide(rec.el);
					rec.el.style.display = 'none';
					rec.el.dataset.fcSearchHidden = '1';
				}
			}
		});

		if (useFilter) {
			counter.textContent = (S.counterTemplate || '%d').replace('%d', totalMatches);
			counter.hidden = false;
			clearBtn.hidden = false;
		} else {
			counter.textContent = '';
			counter.hidden = true;
			clearBtn.hidden = !input.value;
		}

		clearTimeout(announceTimer);
		announceTimer = setTimeout(function () {
			announce(totalMatches, useFilter, !!scopeChanged);
		}, ANNOUNCE_DELAY);
	}

	function announce(count, useFilter, scopeChanged) {
		var key = (useFilter ? '1' : '0') + ':' + count + (scopeChanged ? ':s' : '');
		if (key === lastAnnouncedKey) { return; }
		lastAnnouncedKey = key;

		if (!useFilter) { return; }

		var msg;
		if (count === 0) {
			msg = scopeChanged
				? (S.matchNoneInTab || 'No settings match in this tab.')
				: (S.matchNone || 'No settings match.');
		} else if (count === 1) {
			msg = S.matchOne || '1 setting matches.';
		} else {
			msg = (S.matchMany || '%d settings match.').replace('%d', count);
		}

		liveRegion.textContent = '';
		setTimeout(function () { liveRegion.textContent = msg; }, 50);
	}

	// ---------- Event handlers ----------

	function onInput() {
		clearTimeout(inputTimer);
		var val = input.value;
		inputTimer = setTimeout(function () {
			if (val === lastQuery) { return; }
			lastQuery = val;
			applyFilter(val, false);
		}, INPUT_DEBOUNCE);
	}

	function clearSearch() {
		input.value = '';
		lastQuery = '';
		lastAnnouncedKey = '';
		applyFilter('', false);
		input.focus();
	}

	function onKeydown(e) {
		if (e.key === 'Enter') {
			// Never submit the host form from the filter input.
			e.preventDefault();
			return;
		}
		if (e.key === 'Escape' && input.value) {
			e.preventDefault();
			clearSearch();
			input.blur();
		}
	}

	function handleInvalidEvent(e) {
		var target = e.target;
		if (!target || !target.closest) { return; }
		var group = target.closest(FIELD_SELECTOR);
		if (!group) { return; }

		if (group.dataset.fcSearchHidden) {
			suppressObserver(function () {
				group.style.display = '';
				delete group.dataset.fcSearchHidden;
			});
		}

		var pane = group.closest('.tab-pane, [role="tabpanel"], joomla-tab-element');
		if (pane && !pane.classList.contains('active') && !pane.hasAttribute('active')) {
			var paneId = pane.id;
			if (paneId) {
				var trigger = document.querySelector('[data-bs-target="#' + paneId + '"], [href="#' + paneId + '"], [aria-controls="' + paneId + '"]');
				if (trigger && window.bootstrap && window.bootstrap.Tab) {
					try { new window.bootstrap.Tab(trigger).show(); } catch (_e) {}
				} else if (trigger) {
					trigger.click();
				}
			}
		}

		setTimeout(function () { try { target.focus({ preventScroll: false }); } catch (_e) {} }, 0);
	}

	function revealAll() {
		if (!rootContainer) { return; }
		suppressObserver(function () {
			var hidden = rootContainer.querySelectorAll('[data-fc-search-hidden]');
			Array.prototype.forEach.call(hidden, function (el) {
				el.style.display = '';
				delete el.dataset.fcSearchHidden;
			});
		});
	}

	function onTabShown() {
		var hadFocus = (document.activeElement === input);
		buildIndex();
		applyFilter(lastQuery, true);
		if (hadFocus && input) { input.focus({ preventScroll: true }); }
	}

	function onMutation() {
		if (observerSuppressed) { return; }
		clearTimeout(mutationTimer);
		mutationTimer = setTimeout(function () {
			buildIndex();
			if (lastQuery) { applyFilter(lastQuery, false); }
		}, MUTATION_DEBOUNCE);
	}

	// ---------- Wiring ----------

	function attachMutationObserver() {
		if (!rootContainer || !window.MutationObserver) { return; }
		try {
			mutationObserver = new MutationObserver(onMutation);
			mutationObserver.observe(rootContainer, {
				childList: true,
				subtree: true,
				attributes: true,
				attributeFilter: ['class', 'hidden', 'aria-hidden', 'active']
			});
		} catch (e) {
			debug('MutationObserver attach failed', e);
		}
	}

	function attachTabListeners() {
		var hosts = [rootContainer, document];
		var events = ['shown.bs.tab', 'tab-shown', 'joomla.tab.shown', 'shown'];

		hosts.forEach(function (host) {
			events.forEach(function (evt) {
				host.addEventListener(evt, function (e) {
					// Generic 'shown' must originate from a tab to count.
					if (evt === 'shown' && e && e.target) {
						var t = e.target;
						if (!(t.closest && (t.closest('[role="tab"]') || t.closest('.nav-tabs') || t.closest('joomla-tab')))) {
							return;
						}
					}
					onTabShown();
				}, true);
			});
		});
	}

	// ---------- Bootstrap ----------

	function init() {
		if (isModalOpen()) {
			debug('skipped: modal open');
			return;
		}

		rootContainer = findFieldCluster();
		if (!rootContainer) {
			debug('no field cluster on page — bar not rendered');
			return;
		}

		buildBar(rootContainer);
		buildIndex();

		if (!index.length) {
			debug('cluster found but indexed 0 groups — bar not rendered');
			teardownExistingBar();
			rootContainer.removeAttribute('data-fc-search-root');
			return;
		}

		input.addEventListener('input', onInput);
		input.addEventListener('keydown', onKeydown);
		clearBtn.addEventListener('click', clearSearch);

		var hostForm = rootContainer.closest ? rootContainer.closest('form') : null;
		if (hostForm) {
			hostForm.addEventListener('invalid', handleInvalidEvent, true);
			hostForm.addEventListener('submit', revealAll, true);
		}

		attachTabListeners();
		attachMutationObserver();

		document.addEventListener('flexicontent:formReady', function () {
			buildIndex();
			if (lastQuery) { applyFilter(lastQuery, true); }
		});

		debug('ready, indexed', index.length, 'fields under', rootContainer);
	}

	ready(init);
})();
