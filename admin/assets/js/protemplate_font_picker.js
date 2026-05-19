/**
 * FLEXIcontent Pro Templates — Searchable Google Fonts picker (ARIA 1.2 combobox)
 *
 * Picks up every `[data-fcpt-font-picker]` element in the admin DOM and
 * wires it as an ARIA 1.2 combobox bound to the catalog passed via
 * `data-fcpt-catalog`. Saves the selected family back to the hidden
 * `[data-fcpt-stack-value]` input as a full CSS font-family stack.
 *
 * Accessibility:
 *   - role=combobox + aria-expanded + aria-controls + aria-autocomplete=list
 *   - aria-activedescendant moves between options without shifting DOM focus
 *   - listbox options carry aria-selected + aria-setsize/aria-posinset
 *   - Filter result count announced via aria-live=polite
 *   - Preview pane announces via aria-live=polite
 *   - Full keyboard: Arrow/Home/End/Enter/Esc/Tab + type-ahead
 *   - prefers-reduced-motion handled by the companion stylesheet
 *
 * @since 6.1.0-beta.8
 */

(function () {
	"use strict";

	if (typeof document === "undefined") return;

	var FALLBACK_BY_CATEGORY = {
		"sans-serif":  "system-ui, sans-serif",
		"serif":       "Georgia, serif",
		"display":     "system-ui, sans-serif",
		"handwriting": "cursive",
		"monospace":   "ui-monospace, SFMono-Regular, monospace"
	};

	function buildStack(family, category) {
		var fallback = FALLBACK_BY_CATEGORY[category] || "system-ui, sans-serif";
		var quoted = /\s/.test(family) ? '"' + family + '"' : family;
		return quoted + ", " + fallback;
	}

	function parseJson(attr) {
		if (!attr) return null;
		try { return JSON.parse(attr); } catch (e) { return null; }
	}

	function escapeHtml(s) {
		return String(s).replace(/[&<>"']/g, function (ch) {
			return {"&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;","'":"&#39;"}[ch];
		});
	}

	function FontPicker(root) {
		this.root = root;
		this.catalog = parseJson(root.getAttribute("data-fcpt-catalog")) || [];
		this.labels  = parseJson(root.getAttribute("data-fcpt-labels"))  || {};
		this.combobox = root.querySelector("[data-fcpt-combobox]");
		this.listbox  = root.querySelector("[data-fcpt-listbox]");
		this.live     = root.querySelector("[data-fcpt-live]");
		this.preview  = root.querySelector("[data-fcpt-preview]");
		this.previewSample = root.querySelector("[data-fcpt-preview-sample]");
		this.previewThai   = root.querySelector("[data-fcpt-preview-thai]");
		this.stackInput = root.querySelector("[data-fcpt-stack-value]");
		this.clearBtn   = root.querySelector("[data-fcpt-clear]");

		this.activeIndex = -1;
		this.filtered = this.catalog.slice();
		this.openState = false;

		this.bind();
		this.applyInitialPreview();
	}

	FontPicker.prototype.bind = function () {
		var self = this;
		this.combobox.addEventListener("input",   function () { self.onInput(); });
		this.combobox.addEventListener("keydown", function (e) { self.onKeydown(e); });
		this.combobox.addEventListener("focus",   function () { self.open(); });
		this.combobox.addEventListener("blur",    function () { setTimeout(function () { self.close(); }, 120); });
		if (this.clearBtn) {
			this.clearBtn.addEventListener("click", function () { self.clear(); });
		}
		this.listbox.addEventListener("mousedown", function (e) {
			var li = e.target.closest && e.target.closest("[data-fcpt-option]");
			if (li) {
				e.preventDefault();
				self.selectByIndex(Number(li.getAttribute("data-fcpt-option")));
			}
		});
	};

	FontPicker.prototype.applyInitialPreview = function () {
		var v = this.stackInput.value || "";
		var first = v.split(",")[0].replace(/^["'\s]+|["'\s]+$/g, "");
		if (first) {
			this.setPreviewFont(first);
		}
	};

	FontPicker.prototype.filter = function (q) {
		q = String(q || "").trim().toLowerCase();
		if (!q) {
			this.filtered = this.catalog.slice();
			return;
		}
		var out = [];
		for (var i = 0; i < this.catalog.length; i++) {
			if (this.catalog[i].name.toLowerCase().indexOf(q) !== -1
				|| this.catalog[i].category.indexOf(q) !== -1) {
				out.push(this.catalog[i]);
			}
		}
		this.filtered = out;
	};

	FontPicker.prototype.render = function () {
		var html = "";
		var total = this.filtered.length;
		for (var i = 0; i < total; i++) {
			var f = this.filtered[i];
			var id = this.combobox.id + "-opt-" + i;
			var selected = (this.activeIndex === i);
			html += '<li id="' + escapeHtml(id) + '"'
				+ ' role="option"'
				+ ' aria-selected="' + (selected ? "true" : "false") + '"'
				+ ' aria-posinset="' + (i + 1) + '"'
				+ ' aria-setsize="' + total + '"'
				+ ' class="fcpt-font-picker-option' + (selected ? ' is-active' : '') + '"'
				+ ' data-fcpt-option="' + i + '"'
				+ ' style="font-family: \'' + escapeHtml(f.name) + '\', '
					+ (FALLBACK_BY_CATEGORY[f.category] || 'system-ui, sans-serif') + '">'
				+ '<span class="fcpt-font-picker-option-name">' + escapeHtml(f.name) + '</span>'
				+ '<span class="fcpt-font-picker-option-meta">'
					+ escapeHtml(f.category)
					+ (f.thai ? ' · TH' : '')
					+ '</span>'
				+ '</li>';
		}
		this.listbox.innerHTML = html;
		if (this.live) {
			if (total === 0) {
				this.live.textContent = this.labels.no_results || "No fonts match";
			} else {
				this.live.textContent = (this.labels.results || "%d fonts match")
					.replace("%d", String(total));
			}
		}
	};

	FontPicker.prototype.open = function () {
		if (this.openState) return;
		this.openState = true;
		this.listbox.hidden = false;
		this.combobox.setAttribute("aria-expanded", "true");
		this.filter(this.combobox.value);
		this.activeIndex = -1;
		this.render();
	};

	FontPicker.prototype.close = function () {
		if (!this.openState) return;
		this.openState = false;
		this.listbox.hidden = true;
		this.combobox.setAttribute("aria-expanded", "false");
		this.combobox.removeAttribute("aria-activedescendant");
	};

	FontPicker.prototype.move = function (delta) {
		var total = this.filtered.length;
		if (total === 0) return;
		var next = this.activeIndex + delta;
		if (next < 0) next = total - 1;
		if (next >= total) next = 0;
		this.activeIndex = next;
		this.render();
		var optEl = this.listbox.querySelector('[data-fcpt-option="' + next + '"]');
		if (optEl) {
			this.combobox.setAttribute("aria-activedescendant", optEl.id);
			if (optEl.scrollIntoView) {
				optEl.scrollIntoView({ block: "nearest" });
			}
		}
	};

	FontPicker.prototype.selectByIndex = function (i) {
		if (i < 0 || i >= this.filtered.length) return;
		var picked = this.filtered[i];
		this.combobox.value = picked.name;
		this.stackInput.value = buildStack(picked.name, picked.category);
		this.setPreviewFont(picked.name);
		this.dispatchChange();
		this.close();
	};

	FontPicker.prototype.clear = function () {
		this.combobox.value = "";
		this.stackInput.value = "";
		this.filter("");
		this.render();
		this.setPreviewFont(null);
		this.dispatchChange();
		this.combobox.focus();
	};

	FontPicker.prototype.setPreviewFont = function (family) {
		if (!this.previewSample) return;
		var stack = family
			? "'" + family + "', system-ui, sans-serif"
			: "system-ui, sans-serif";
		this.previewSample.style.fontFamily = stack;
		if (this.previewThai) {
			this.previewThai.style.fontFamily = stack;
		}
	};

	FontPicker.prototype.dispatchChange = function () {
		if (typeof CustomEvent === "function") {
			this.stackInput.dispatchEvent(new CustomEvent("change", { bubbles: true }));
		}
	};

	FontPicker.prototype.onInput = function () {
		this.open();
		this.activeIndex = -1;
		this.render();
		this.setPreviewFont(this.combobox.value || null);
	};

	FontPicker.prototype.onKeydown = function (e) {
		var k = e.key;
		if (k === "ArrowDown") {
			e.preventDefault();
			if (!this.openState) this.open(); else this.move(1);
		} else if (k === "ArrowUp") {
			e.preventDefault();
			if (!this.openState) this.open(); else this.move(-1);
		} else if (k === "Home" && this.openState) {
			e.preventDefault();
			this.activeIndex = 0;
			this.render();
			var opt = this.listbox.querySelector('[data-fcpt-option="0"]');
			if (opt) this.combobox.setAttribute("aria-activedescendant", opt.id);
		} else if (k === "End" && this.openState) {
			e.preventDefault();
			this.activeIndex = this.filtered.length - 1;
			this.render();
			var optE = this.listbox.querySelector('[data-fcpt-option="' + this.activeIndex + '"]');
			if (optE) this.combobox.setAttribute("aria-activedescendant", optE.id);
		} else if (k === "Enter") {
			if (this.openState && this.activeIndex >= 0) {
				e.preventDefault();
				this.selectByIndex(this.activeIndex);
			}
		} else if (k === "Escape" || k === "Esc") {
			if (this.openState) {
				e.preventDefault();
				this.close();
			}
		}
	};

	function init() {
		var roots = document.querySelectorAll("[data-fcpt-font-picker]");
		for (var i = 0; i < roots.length; i++) {
			if (roots[i].dataset.fcptBound === "1") continue;
			roots[i].dataset.fcptBound = "1";
			new FontPicker(roots[i]);
		}
	}

	if (document.readyState === "loading") {
		document.addEventListener("DOMContentLoaded", init, { once: true });
	} else {
		init();
	}

	if (typeof MutationObserver !== "undefined") {
		var observer = new MutationObserver(function () { init(); });
		observer.observe(document.documentElement, { childList: true, subtree: true });
	}
})();
