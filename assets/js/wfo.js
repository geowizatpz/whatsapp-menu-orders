/**
 * WhatsApp Menu & Orders — front-end app.
 *
 * No build step, no dependencies, one IIFE. Cart is scoped per widget instance:
 * localStorage['wfo_cart_v1:<instance>']. Every amount is an integer in minor units.
 *
 * @package WFO
 */
(function () {
	'use strict';

	var VERSION = '0.1.0';
	var CART_PREFIX = 'wfo_cart_v1:';
	var DRAFT_PREFIX = 'wfo_draft_v1:';
	var CODE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // base32-ish, no 0/O/1/I
	var MAX_QTY = 99;
	var MAX_ITEM_NOTE = 180;
	var MAX_MESSAGE_URL = 1980; // keep the whole wa.me URL under 2000 chars

	/* ---------------------------------------------------------------------
	 * Small DOM / value helpers
	 * ------------------------------------------------------------------ */

	function toArray(list) {
		var out = [];
		var i;
		if (!list) {
			return out;
		}
		for (i = 0; i < list.length; i++) {
			out.push(list[i]);
		}
		return out;
	}

	function each(list, fn) {
		var i;
		if (!list) {
			return;
		}
		for (i = 0; i < list.length; i++) {
			fn(list[i], i);
		}
	}

	function qs(selector, context) {
		try {
			return (context || document).querySelector(selector);
		} catch (e) {
			return null;
		}
	}

	function qsa(selector, context) {
		try {
			return toArray((context || document).querySelectorAll(selector));
		} catch (e) {
			return [];
		}
	}

	function closest(el, selector) {
		while (el && el.nodeType === 1) {
			if (el.matches && el.matches(selector)) {
				return el;
			}
			el = el.parentElement;
		}
		return null;
	}

	function on(el, type, fn, options) {
		if (el && el.addEventListener) {
			el.addEventListener(type, fn, options || false);
		}
	}

	/* "lat,lng" -> { lat, lng } or null. */
	function parseLatLng(value) {
		var m = /^\s*(-?\d{1,2}(?:\.\d+)?)\s*,\s*(-?\d{1,3}(?:\.\d+)?)\s*$/.exec(String(value || ''));
		var lat;
		var lng;

		if (!m) {
			return null;
		}
		lat = parseFloat(m[1]);
		lng = parseFloat(m[2]);

		if (lat < -90 || lat > 90 || lng < -180 || lng > 180) {
			return null;
		}
		return { lat: lat, lng: lng };
	}

	function cleanLatLng(value) {
		var pos = parseLatLng(value);
		return pos ? pos.lat.toFixed(6) + ',' + pos.lng.toFixed(6) : '';
	}

	/* Load the Maps JavaScript API once for every menu on the page. */
	var mapsCallbacks = [];
	var mapsStatus = '';

	function loadGoogleMaps(key, done) {
		var script;

		if (window.google && window.google.maps && window.google.maps.Map) {
			done(true);
			return;
		}
		if (mapsStatus === 'failed') {
			done(false);
			return;
		}

		mapsCallbacks.push(done);

		if (mapsStatus === 'loading') {
			return;
		}
		mapsStatus = 'loading';

		function finish(ok) {
			var list = mapsCallbacks.slice();
			mapsCallbacks = [];
			mapsStatus = ok ? 'ready' : 'failed';
			each(list, function (cb) {
				cb(ok);
			});
		}

		window.wfoMapsReady = function () {
			finish(true);
		};

		/* Google calls this when the key is rejected (wrong key, API off, domain not allowed). */
		window.gm_authFailure = function () {
			mapsStatus = 'failed';
			each(qsa('[data-wfo-map]'), function (el) {
				el.setAttribute('hidden', '');
			});
		};

		script = document.createElement('script');
		script.src = 'https://maps.googleapis.com/maps/api/js?key=' + encodeURIComponent(key) +
			'&callback=wfoMapsReady&loading=async&language=' +
			encodeURIComponent(String(document.documentElement.lang || 'es').slice(0, 2));
		script.async = true;
		script.onerror = function () {
			finish(false);
		};
		document.head.appendChild(script);
	}

	function setText(el, value) {
		if (el) {
			el.textContent = value === null || value === undefined ? '' : String(value);
		}
	}

	function setHidden(el, hidden) {
		if (el) {
			if (hidden) {
				el.setAttribute('hidden', '');
			} else {
				el.removeAttribute('hidden');
			}
		}
	}

	function addClass(el, name) {
		if (el && el.classList) {
			el.classList.add(name);
		}
	}

	function removeClass(el, name) {
		if (el && el.classList) {
			el.classList.remove(name);
		}
	}

	function toggleClass(el, name, state) {
		if (!el || !el.classList) {
			return;
		}
		if (state) {
			el.classList.add(name);
		} else {
			el.classList.remove(name);
		}
	}

	function create(tag, className) {
		var el = document.createElement(tag);
		if (className) {
			el.className = className;
		}
		return el;
	}

	function intValue(value, fallback) {
		var number = parseInt(value, 10);
		return isNaN(number) ? fallback : number;
	}

	function clamp(value, min, max) {
		return Math.max(min, Math.min(max, value));
	}

	function fill(template, value) {
		if (typeof template !== 'string' || '' === template) {
			return String(value);
		}
		return template.indexOf('%s') !== -1 ? template.replace('%s', value) : template + ' ' + value;
	}

	function fillNumber(template, value) {
		if (typeof template !== 'string' || '' === template) {
			return String(value);
		}
		if (template.indexOf('%d') !== -1) {
			return template.replace('%d', value);
		}
		return template + ' ' + value;
	}

	function normalizeText(value) {
		var text = String(value || '').toLowerCase().replace(/\s+/g, ' ').trim();
		if (text.normalize) {
			text = text.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
		}
		return text;
	}

	function prefersReducedMotion() {
		return !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
	}

	function readStorage(key) {
		try {
			return window.localStorage.getItem(key);
		} catch (e) {
			return null;
		}
	}

	function writeStorage(key, value) {
		try {
			window.localStorage.setItem(key, value);
		} catch (e) {
			/* Private mode / quota: the app keeps working from memory. */
		}
	}

	function removeStorage(key) {
		try {
			window.localStorage.removeItem(key);
		} catch (e) {
			/* ignore */
		}
	}

	function parseJson(raw) {
		if (!raw) {
			return null;
		}
		try {
			return JSON.parse(raw);
		} catch (e) {
			return null;
		}
	}

	function scrollToPosition(top) {
		var behavior = prefersReducedMotion() ? 'auto' : 'smooth';
		if ('scrollBehavior' in document.documentElement.style) {
			window.scrollTo({ top: top, behavior: behavior });
		} else {
			window.scrollTo(0, top);
		}
	}

	/* Module-level scroll lock shared by every widget on the page. */
	var locks = {};

	function lockScroll(key) {
		locks[key] = true;
		addClass(document.body, 'wfo-scroll-lock');
	}

	function unlockScroll(key) {
		var name;
		delete locks[key];
		for (name in locks) {
			if (Object.prototype.hasOwnProperty.call(locks, name)) {
				return;
			}
		}
		removeClass(document.body, 'wfo-scroll-lock');
	}

	/* Money formatting that matches WFO\Money::format() exactly. */
	function createMoney(currency) {
		var c = currency || {};
		var decimals = intValue(c.decimals, 2);
		decimals = clamp(decimals, 0, 2);
		var symbol = typeof c.symbol === 'string' ? c.symbol : '';
		var position = c.position === 'right' ? 'right' : 'left';
		var thousands = c.thousands === null || c.thousands === undefined ? ',' : String(c.thousands);
		var decimal = c.decimal === null || c.decimal === undefined ? '.' : String(c.decimal);
		var formatter = null;

		if (typeof Intl !== 'undefined' && Intl.NumberFormat) {
			try {
				formatter = new Intl.NumberFormat('en-US', {
					minimumFractionDigits: decimals,
					maximumFractionDigits: decimals,
					useGrouping: true
				});
			} catch (e) {
				formatter = null;
			}
		}

		function number(minor) {
			var value = intValue(minor, 0) / 100; // stored in cents; decimals is display only
			var out = '';
			var parts;
			var i;
			var part;
			var fixed;
			var groups;

			if (formatter && formatter.formatToParts) {
				try {
					parts = formatter.formatToParts(value);
					for (i = 0; i < parts.length; i++) {
						part = parts[i];
						if (part.type === 'group') {
							out += thousands;
						} else if (part.type === 'decimal') {
							out += decimal;
						} else {
							out += part.value;
						}
					}
					return out;
				} catch (e) {
					/* fall through to the manual formatter */
				}
			}

			fixed = Math.abs(value).toFixed(decimals);
			groups = fixed.split('.');
			groups[0] = groups[0].replace(/\B(?=(\d{3})+(?!\d))/g, thousands);
			out = (value < 0 ? '-' : '') + groups[0];
			if (decimals > 0) {
				out += decimal + (groups[1] || '');
			}
			return out;
		}

		return {
			money: function (minor) {
				var formatted = number(minor);
				return position === 'right' ? formatted + symbol : symbol + formatted;
			},
			amount: function (minor) {
				return number(minor);
			}
		};
	}

	function randomInt(max) {
		if (window.crypto && window.crypto.getRandomValues) {
			var buffer = new Uint32Array(1);
			window.crypto.getRandomValues(buffer);
			return buffer[0] % max;
		}
		return Math.floor(Math.random() * max);
	}

	function generateCode(prefix) {
		var clean = String(prefix || 'WFO').toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 8) || 'WFO';
		var out = '';
		var i;
		for (i = 0; i < 5; i++) {
			out += CODE_ALPHABET.charAt(randomInt(CODE_ALPHABET.length));
		}
		return clean + '-' + out;
	}

	function clipText(value, max) {
		if (value.length <= max) {
			return value;
		}
		var out = value.slice(0, Math.max(0, max - 1));
		if (/[\uD800-\uDBFF]$/.test(out)) {
			out = out.slice(0, -1);
		}
		return out + '\u2026';
	}

	function shortenNote(value, amount) {
		var target = value.length - amount;
		if (target <= 1) {
			return value.length > 1 ? '\u2026' : '';
		}
		var out = value.slice(0, target - 1).replace(/\s+$/, '');
		if (/[\uD800-\uDBFF]$/.test(out)) {
			out = out.slice(0, -1);
		}
		return out.replace(/\u2026$/, '') + '\u2026';
	}

	/* Line whose tokens are all empty is dropped, as the contract requires. */
	function applyTemplate(template, values) {
		var lines = String(template || '').split(/\r?\n/);
		var kept = [];
		var i;
		var j;
		var line;
		var tokens;
		var key;
		var nonEmpty;
		var unknown;

		for (i = 0; i < lines.length; i++) {
			line = lines[i];
			tokens = line.match(/\{[a-z]+\}/g) || [];
			nonEmpty = false;
			unknown = false;

			for (j = 0; j < tokens.length; j++) {
				key = tokens[j].slice(1, -1);
				if (Object.prototype.hasOwnProperty.call(values, key)) {
					if (String(values[key]) !== '') {
						nonEmpty = true;
					}
				} else {
					unknown = true;
				}
			}

			if (tokens.length && !nonEmpty && !unknown) {
				continue;
			}

			line = line.replace(/\{([a-z]+)\}/g, function (match, name) {
				return Object.prototype.hasOwnProperty.call(values, name) ? values[name] : match;
			});

			kept.push(line.replace(/[ \t]+$/, ''));
		}

		return kept.join('\n').replace(/\n{3,}/g, '\n\n').replace(/^\s+|\s+$/g, '');
	}

	/* ---------------------------------------------------------------------
	 * App
	 * ------------------------------------------------------------------ */

	function App(root, data) {
		this.root = root;
		this.data = data;
		this.settings = data.settings || {};
		this.strings = data.strings || {};
		this.currency = data.currency || {};
		this.instance = data.instance || 'wfo-1';
		this.cartKey = CART_PREFIX + this.instance;
		this.draftKey = DRAFT_PREFIX + this.instance;
		this.money = createMoney(this.currency);
		this.items = {};
		this.optionIndex = {};
		this.searchText = {};
		this.cart = [];
		this.dialogState = null;
		this.editIndex = -1;
		this.dialogTrigger = null;
		this.drawerTrigger = null;
		this.drawerOpen = false;
		this.sending = false;
		this.locked = {};
		this.lastMessage = '';
		this.lastWaUrl = '';
		this.activeCat = '';
		this.draftTimer = null;
		this.copyTimer = null;
		this.backdropTimer = null;
		this.scrollTick = false;
		this.reducedMotion = prefersReducedMotion();
		/* The REST endpoint's anti-bot window expects a form that is 2s–6h old. */
		this.startedAt = Math.floor(Date.now() / 1000);

		this.safeInstance = String(this.instance).replace(/[^a-zA-Z0-9_-]/g, '-');

		this.el = {
			search: qs('[data-wfo-search]', root),
			searchClear: qs('[data-wfo-search-clear]', root),
			searchStatus: qs('[data-wfo-search-status]', root),
			noResults: qs('[data-wfo-no-results]', root),
			catbar: qs('[data-wfo-catbar]', root),
			catTrack: qs('.wfo-catbar__track', root),
			catLinks: qsa('.wfo-catbar__link', root),
			sections: qsa('.wfo-cat', root),
			cards: qsa('.wfo-card', root),
			cartBtn: qs('[data-wfo-open-cart]', root),
			cartCount: qs('.wfo-cartbtn__count', root),
			cartTotal: qs('.wfo-cartbtn__total', root),
			cartStatus: qs('[data-wfo-cart-status]', root),
			dialog: qs('.wfo-dialog', root),
			dialogMedia: qs('[data-wfo-dialog-media]', root),
			dialogTitle: qs('[data-wfo-dialog-title]', root),
			dialogDesc: qs('[data-wfo-dialog-desc]', root),
			dialogGroups: qs('[data-wfo-dialog-groups]', root),
			dialogNote: qs('[data-wfo-dialog-note]', root),
			dialogTotal: qs('[data-wfo-dialog-total]', root),
			dialogAdd: qs('[data-wfo-dialog-add]', root),
			dialogAddLabel: qs('[data-wfo-dialog-add-label]', root),
			dialogHint: qs('[data-wfo-dialog-hint]', root),
			dialogClose: qs('[data-wfo-dialog-close]', root),
			qtyInput: qs('[data-wfo-qty-input]', root),
			qtyMinus: qs('[data-wfo-qty-minus]', root),
			qtyPlus: qs('[data-wfo-qty-plus]', root),
			drawer: qs('[data-wfo-drawer]', root),
			drawerBackdrop: qs('[data-wfo-drawer-backdrop]', root),
			drawerClosers: qsa('[data-wfo-drawer-close]', root),
			drawerCount: qs('[data-wfo-drawer-count]', root),
			lines: qs('[data-wfo-lines]', root),
			summary: qs('[data-wfo-summary]', root),
			subtotal: qs('[data-wfo-subtotal]', root),
			deliveryRow: qs('[data-wfo-delivery-row]', root),
			delivery: qs('[data-wfo-delivery]', root),
			freeHint: qs('[data-wfo-free-hint]', root),
			total: qs('[data-wfo-total]', root),
			minNote: qs('[data-wfo-min-note]', root),
			form: qs('[data-wfo-form]', root),
			send: qs('[data-wfo-send]', root),
			sendLabel: qs('[data-wfo-send-label]', root),
			sendHint: qs('[data-wfo-send-hint]', root),
			sendError: qs('[data-wfo-form-error]', root),
			noWhatsapp: qs('[data-wfo-no-whatsapp]', root),
			confirm: qs('[data-wfo-confirm]', root),
			confirmTitle: qs('[data-wfo-confirm-title]', root),
			confirmCode: qs('[data-wfo-code]', root),
			confirmTotal: qs('[data-wfo-confirm-total]', root),
			confirmPayment: qs('[data-wfo-confirm-payment]', root),
			confirmStatus: qs('[data-wfo-confirm-status]', root),
			waLink: qs('[data-wfo-wa-link]', root),
			copy: qs('[data-wfo-copy]', root),
			restart: qs('[data-wfo-restart]', root)
		};

		this.fields = {
			name: qs('[name="customer[name]"]', root),
			phone: qs('[name="customer[phone]"]', root),
			address: qs('[name="customer[address]"]', root),
			time: qs('[name="customer[time]"]', root),
			note: qs('[name="customer[note]"]', root),
			type: qsa('input[name="type"]', root),
			payment: qsa('input[name="payment"]', root),
			addressWrap: qs('[data-wfo-address]', root),
			deliveryNote: qs('[data-wfo-delivery-note]', root),
			location: qs('[data-wfo-location]', root),
			map: qs('[data-wfo-map]', root),
			mapCanvas: qs('[data-wfo-map-canvas]', root),
			mapLocate: qs('[data-wfo-map-locate]', root),
			mapClear: qs('[data-wfo-map-clear]', root),
			mapStatus: qs('[data-wfo-map-status]', root)
		};

		this.mapState = { loading: false, ready: false, failed: false, map: null, marker: null };

		this.boundDrawerKey = this.onDrawerKey.bind(this);
		this.boundConfirmKey = this.onConfirmKey.bind(this);
		this.boundScroll = this.onScroll.bind(this);
		this.boundResize = this.onResize.bind(this);

		this.buildIndex();
		this.bind();
		this.loadCart();
		this.restoreDraft();
		this.syncFormState();
		this.measureCatbar();
		this.renderCart();
		this.applySearch('');
		this.initSpy();
	}

	App.prototype.t = function (key, fallback) {
		var value = this.strings[key];
		return typeof value === 'string' && value !== '' ? value : fallback;
	};

	App.prototype.countLabel = function (count) {
		var noun = count === 1 ? this.t('item', 'item') : this.t('items', 'items');
		return count + ' ' + noun;
	};

	App.prototype.buildIndex = function () {
		var self = this;
		var items = this.data.items || [];

		each(items, function (item) {
			var index = {};
			self.items[item.id] = item;
			each(item.groups || [], function (group) {
				each(group.options || [], function (option) {
					index[group.id + ':' + option.id] = { group: group, option: option };
				});
			});
			self.optionIndex[item.id] = index;
			self.searchText[item.id] = normalizeText((item.name || '') + ' ' + (item.description || ''));
		});
	};

	App.prototype.bind = function () {
		var self = this;
		var el = this.el;

		on(this.root, 'click', function (event) {
			var open = closest(event.target, '[data-wfo-open]');
			var openCart;

			if (open) {
				event.preventDefault();
				self.openDialog(intValue(open.getAttribute('data-wfo-open'), 0), -1);
				return;
			}

			openCart = closest(event.target, '[data-wfo-open-cart]');
			if (openCart) {
				event.preventDefault();
				self.openDrawer(openCart);
			}
		});

		each(el.drawerClosers, function (button) {
			on(button, 'click', function () {
				self.closeDrawer(true);
			});
		});

		on(el.drawerBackdrop, 'click', function () {
			self.closeDrawer(true);
		});

		on(el.dialogClose, 'click', function () {
			self.closeDialog();
		});

		on(el.dialog, 'click', function (event) {
			if (event.target === el.dialog) {
				self.closeDialog();
			}
		});

		on(el.dialog, 'cancel', function () {
			/* Native Esc close: cleanup happens in the close handler. */
			self.editIndex = -1;
		});

		on(el.dialog, 'close', function () {
			self.finishDialogClose();
		});

		on(el.dialogGroups, 'change', function (event) {
			if (closest(event.target, 'input[data-wfo-opt]')) {
				self.onOptionChange();
			}
		});

		on(el.dialogNote, 'input', function () {
			if (self.dialogState) {
				self.dialogState.note = String(el.dialogNote.value || '').slice(0, MAX_ITEM_NOTE);
			}
		});

		on(el.qtyMinus, 'click', function () {
			self.changeQty(-1);
		});

		on(el.qtyPlus, 'click', function () {
			self.changeQty(1);
		});

		on(el.qtyInput, 'input', function () {
			var raw = String(el.qtyInput.value || '').replace(/\D+/g, '');
			if ('' === raw) {
				return;
			}
			if (self.dialogState) {
				self.dialogState.qty = clamp(intValue(raw, 1), 1, MAX_QTY);
			}
			self.updateDialogTotal();
		});

		on(el.qtyInput, 'change', function () {
			if (self.dialogState) {
				el.qtyInput.value = String(self.dialogState.qty);
			}
		});

		on(el.dialogAdd, 'click', function () {
			self.saveLine();
		});

		on(el.lines, 'click', function (event) {
			var plus = closest(event.target, '[data-wfo-line-plus]');
			var minus;
			var edit;
			var remove;
			var index;

			if (plus) {
				index = intValue(plus.getAttribute('data-wfo-line-plus'), -1);
				self.changeLineQty(index, 1);
				return;
			}

			minus = closest(event.target, '[data-wfo-line-minus]');
			if (minus) {
				index = intValue(minus.getAttribute('data-wfo-line-minus'), -1);
				self.changeLineQty(index, -1);
				return;
			}

			edit = closest(event.target, '[data-wfo-line-edit]');
			if (edit) {
				index = intValue(edit.getAttribute('data-wfo-line-edit'), -1);
				self.editLine(index);
				return;
			}

			remove = closest(event.target, '[data-wfo-line-remove]');
			if (remove) {
				index = intValue(remove.getAttribute('data-wfo-line-remove'), -1);
				self.removeLine(index);
			}
		});

		on(el.form, 'submit', function (event) {
			event.preventDefault();
			self.sendOrder();
		});

		on(el.form, 'input', function (event) {
			self.onFormChange(event.target);
		});

		on(el.form, 'change', function (event) {
			self.onFormChange(event.target);
		});

		on(el.search, 'input', function () {
			self.applySearch(el.search.value);
		});

		on(el.searchClear, 'click', function () {
			if (el.search) {
				el.search.value = '';
				el.search.focus();
			}
			self.applySearch('');
		});

		on(el.catbar, 'click', function (event) {
			var link = closest(event.target, '[data-wfo-cat]');
			var slug;
			var section;

			if (!link || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
				return;
			}

			slug = link.getAttribute('data-wfo-cat');
			section = self.sectionBySlug(slug);

			if (!section) {
				return;
			}

			event.preventDefault();
			self.setActiveCat(slug, false);
			self.scrollToSection(section);
		});

		on(el.waLink, 'click', function (event) {
			if (!self.lastWaUrl && el.waLink.getAttribute('href') === '#') {
				event.preventDefault();
			}
		});

		on(el.copy, 'click', function () {
			self.copyOrder();
		});

		on(el.restart, 'click', function () {
			self.restart();
		});

		on(window, 'scroll', this.boundScroll, { passive: true });
		on(window, 'resize', this.boundResize, { passive: true });
	};

	/* ---------------------------------------------------------------------
	 * Payload, cart storage and validation
	 * ------------------------------------------------------------------ */

	function readPayload(root) {
		var node = qs('script.wfo-data', root);
		var data;

		if (!node) {
			return null;
		}

		data = parseJson(node.textContent || node.innerHTML);

		if (!data || typeof data !== 'object' || !data.items || !data.items.length) {
			return null;
		}

		return data;
	}

	App.prototype.loadCart = function () {
		var stored = parseJson(readStorage(this.cartKey));
		this.cart = this.sanitizeCart(stored);
	};

	App.prototype.sanitizeCart = function (list) {
		var self = this;
		var out = [];
		var i;

		if (!list || Object.prototype.toString.call(list) !== '[object Array]') {
			return out;
		}

		for (i = 0; i < list.length; i++) {
			var entry = list[i];
			var id;
			var item;
			var qty;
			var opts;
			var note;

			if (!entry || typeof entry !== 'object') {
				continue;
			}

			id = intValue(entry.id, 0);
			item = self.items[id];

			if (!item || item.available === false) {
				continue;
			}

			qty = clamp(intValue(entry.qty, 1), 1, MAX_QTY);
			opts = self.sanitizeOpts(item, entry.opts);

			if (opts === null) {
				continue;
			}

			note = typeof entry.note === 'string' ? entry.note.replace(/\s+/g, ' ').trim().slice(0, MAX_ITEM_NOTE) : '';
			out.push({ id: id, qty: qty, opts: opts, note: note });
		}

		return out;
	};

	App.prototype.sanitizeOpts = function (item, raw) {
		var index = this.optionIndex[item.id] || {};
		var wanted = {};
		var tokens = [];
		var groups = item.groups || [];
		var i;
		var g;
		var group;
		var picks;
		var option;
		var token;

		if (raw && typeof raw.length === 'number') {
			for (i = 0; i < raw.length; i++) {
				token = String(raw[i]);
				if (index[token] && !index[token].option.soldout) {
					wanted[token] = true;
				}
			}
		}

		for (g = 0; g < groups.length; g++) {
			group = groups[g];
			picks = [];

			for (i = 0; i < (group.options || []).length; i++) {
				option = group.options[i];
				token = group.id + ':' + option.id;
				if (wanted[token]) {
					picks.push(token);
				}
			}

			if (group.type === 'radio') {
				picks = picks.slice(0, 1);
				if (group.required && !picks.length) {
					return null;
				}
			} else {
				var max = intValue(group.max, 0);
				var min = intValue(group.min, 0);
				if (max > 0) {
					picks = picks.slice(0, max);
				}
				if (min > 0 && picks.length < min) {
					return null;
				}
			}

			tokens = tokens.concat(picks);
		}

		return tokens;
	};

	App.prototype.saveCart = function () {
		writeStorage(this.cartKey, JSON.stringify(this.cart));
	};

	/* ---------------------------------------------------------------------
	 * Draft persistence
	 * ------------------------------------------------------------------ */

	App.prototype.restoreDraft = function () {
		var draft = parseJson(readStorage(this.draftKey));
		var self = this;
		var input;

		if (!draft || typeof draft !== 'object') {
			return;
		}

		if (self.fields.name && typeof draft.name === 'string') {
			self.fields.name.value = draft.name.slice(0, 60);
		}
		if (self.fields.phone && typeof draft.phone === 'string') {
			self.fields.phone.value = draft.phone.slice(0, 24);
		}
		if (self.fields.address && typeof draft.address === 'string') {
			self.fields.address.value = draft.address.slice(0, 200);
		}
		if (self.fields.location && typeof draft.location === 'string') {
			self.fields.location.value = cleanLatLng(draft.location);
		}
		if (draft.type) {
			input = qs('input[name="type"][value="' + String(draft.type).replace(/[^a-z]/g, '') + '"]', self.root);
			if (input && !input.disabled) {
				input.checked = true;
			}
		}
		if (draft.payment) {
			input = qs('input[name="payment"][value="' + String(draft.payment).replace(/[^a-z]/g, '') + '"]', self.root);
			if (input && !input.disabled) {
				input.checked = true;
			}
		}
	};

	App.prototype.persistDraft = function () {
		var values = this.formValues();
		writeStorage(this.draftKey, JSON.stringify({
			name: values.name,
			phone: values.phone,
			type: values.type,
			address: values.address,
			location: values.location,
			payment: values.payment
		}));
	};

	App.prototype.persistDraftSoon = function () {
		var self = this;
		window.clearTimeout(this.draftTimer);
		this.draftTimer = window.setTimeout(function () {
			self.persistDraft();
		}, 180);
	};

	App.prototype.clearDraft = function () {
		window.clearTimeout(this.draftTimer);
		removeStorage(this.draftKey);
	};

	/* ---------------------------------------------------------------------
	 * Values
	 * ------------------------------------------------------------------ */

	App.prototype.item = function (id) {
		return this.items[id] || null;
	};

	App.prototype.optionRef = function (itemId, token) {
		return (this.optionIndex[itemId] || {})[token] || null;
	};

	App.prototype.lineUnit = function (line) {
		var item = this.item(line.id);
		var self = this;
		var total;

		if (!item) {
			return 0;
		}

		total = intValue(item.price, 0);

		each(line.opts || [], function (token) {
			var ref = self.optionRef(line.id, token);
			if (ref) {
				total += intValue(ref.option.price, 0);
			}
		});

		return total;
	};

	App.prototype.lineTotal = function (line) {
		return this.lineUnit(line) * intValue(line.qty, 1);
	};

	App.prototype.subtotal = function () {
		var self = this;
		var total = 0;
		each(this.cart, function (line) {
			total += self.lineTotal(line);
		});
		return total;
	};

	App.prototype.cartCount = function () {
		var count = 0;
		each(this.cart, function (line) {
			count += intValue(line.qty, 0);
		});
		return count;
	};

	App.prototype.currentType = function () {
		var i;
		for (i = 0; i < this.fields.type.length; i++) {
			if (this.fields.type[i].checked) {
				return this.fields.type[i].value;
			}
		}
		return '';
	};

	App.prototype.currentPayment = function () {
		var i;
		for (i = 0; i < this.fields.payment.length; i++) {
			if (this.fields.payment[i].checked) {
				return this.fields.payment[i].value;
			}
		}
		return '';
	};

	App.prototype.enabledTypes = function () {
		return toArray(this.fields.type);
	};

	App.prototype.formValues = function () {
		return {
			name: this.fields.name ? String(this.fields.name.value || '').trim() : '',
			phone: this.fields.phone ? String(this.fields.phone.value || '').trim() : '',
			type: this.currentType(),
			address: this.fields.address ? String(this.fields.address.value || '').trim() : '',
			location: this.fields.location ? cleanLatLng(this.fields.location.value) : '',
			time: this.fields.time ? String(this.fields.time.value || 'asap') : 'asap',
			payment: this.currentPayment(),
			note: this.fields.note ? String(this.fields.note.value || '').trim() : ''
		};
	};

	App.prototype.timeLabel = function () {
		var select = this.fields.time;
		if (!select || select.selectedIndex < 0 || !select.options[select.selectedIndex]) {
			return '';
		}
		return String(select.options[select.selectedIndex].text || '');
	};

	App.prototype.computeTotals = function () {
		var subtotal = this.subtotal();
		var type = this.currentType();
		var threshold = intValue(this.settings.freeDeliveryOver, 0);
		var baseFee = intValue(this.settings.deliveryFee, 0);
		var fee = 0;

		if (type === 'delivery') {
			fee = threshold === 0 || subtotal < threshold ? baseFee : 0;
		}

		return {
			subtotal: subtotal,
			fee: fee,
			total: subtotal + fee,
			type: type,
			threshold: threshold
		};
	};

	App.prototype.deliveryLabel = function (totals) {
		if (totals.fee > 0) {
			return this.money.money(totals.fee);
		}

		// No flat fee configured: show the custom label (for example "by zone").
		if (intValue(this.settings.deliveryFee, 0) === 0 && this.settings.deliveryFeeLabel) {
			return String(this.settings.deliveryFeeLabel);
		}

		return this.t('free', 'Free');
	};

	/* ---------------------------------------------------------------------
	 * Cart drawer rendering
	 * ------------------------------------------------------------------ */

	App.prototype.renderCart = function () {
		this.renderLines();
		this.updateSummary();
		this.updateCartButton();
	};

	App.prototype.renderLines = function () {
		var self = this;
		var list = this.el.lines;
		var count = this.cartCount();

		if (!list) {
			return;
		}

		list.textContent = '';

		each(this.cart, function (line, index) {
			var item = self.item(line.id);
			if (!item) {
				return;
			}

			var row = create('li', 'wfo-line');
			var head = create('div', 'wfo-line__head');
			var title = create('h3', 'wfo-line__title');
			var price = create('span', 'wfo-line__price');
			var labels = [];
			var actions;
			var stepper;
			var minus;
			var value;
			var plus;
			var tools;
			var edit;
			var remove;

			row.setAttribute('data-wfo-line', String(index));
			title.textContent = item.name;
			price.textContent = self.money.money(self.lineTotal(line));

			head.appendChild(title);
			head.appendChild(price);
			row.appendChild(head);

			each(line.opts || [], function (token) {
				var ref = self.optionRef(line.id, token);
				if (ref) {
					labels.push(ref.option.label);
				}
			});

			if (labels.length) {
				var opts = create('p', 'wfo-line__opts');
				opts.textContent = labels.join(' · ');
				row.appendChild(opts);
			}

			if (line.note) {
				var note = create('p', 'wfo-line__note');
				note.textContent = '\u201C' + line.note + '\u201D';
				row.appendChild(note);
			}

			actions = create('div', 'wfo-line__actions');
			stepper = create('div', 'wfo-stepper wfo-qty');

			minus = create('button', 'wfo-stepper__btn');
			minus.type = 'button';
			minus.setAttribute('data-wfo-line-minus', String(index));
			minus.setAttribute('aria-label', self.t('quantity', 'Quantity') + ': ' + item.name + ' −');
			minus.textContent = '\u2212';
			minus.disabled = intValue(line.qty, 1) <= 1;

			value = create('span', 'wfo-stepper__value');
			value.textContent = String(line.qty);
			value.setAttribute('aria-live', 'off');

			plus = create('button', 'wfo-stepper__btn');
			plus.type = 'button';
			plus.setAttribute('data-wfo-line-plus', String(index));
			plus.setAttribute('aria-label', self.t('quantity', 'Quantity') + ': ' + item.name + ' +');
			plus.textContent = '+';
			plus.disabled = intValue(line.qty, 1) >= MAX_QTY;

			stepper.appendChild(minus);
			stepper.appendChild(value);
			stepper.appendChild(plus);

			tools = create('div', 'wfo-line__tools');

			edit = create('button', 'wfo-line__tool');
			edit.type = 'button';
			edit.setAttribute('data-wfo-line-edit', String(index));
			edit.setAttribute('aria-label', self.t('edit', 'Edit') + ': ' + item.name);
			edit.textContent = self.t('edit', 'Edit');

			remove = create('button', 'wfo-line__tool wfo-line__tool--remove');
			remove.type = 'button';
			remove.setAttribute('data-wfo-line-remove', String(index));
			remove.setAttribute('aria-label', self.t('remove', 'Remove') + ': ' + item.name);
			remove.textContent = self.t('remove', 'Remove');

			tools.appendChild(edit);
			tools.appendChild(remove);
			actions.appendChild(stepper);
			actions.appendChild(tools);
			row.appendChild(actions);
			list.appendChild(row);
		});

		if (this.el.drawerCount) {
			this.el.drawerCount.textContent = count ? this.countLabel(count) : '';
		}

		toggleClass(this.el.drawer, 'is-empty', count === 0);
	};

	App.prototype.updateSummary = function () {
		var totals = this.computeTotals();
		var self = this;
		var scope = this.settings.minOrderScope === 'total' ? 'total' : 'subtotal';
		var minOrder = intValue(this.settings.minOrder, 0);
		var basis = scope === 'total' ? totals.total : totals.subtotal;
		var remaining = minOrder - basis;
		var hint;
		var $deliveryRow = this.el.deliveryRow;

		setText(this.el.subtotal, this.money.money(totals.subtotal));
		setText(this.el.total, this.money.money(totals.total));

		if ($deliveryRow) {
			if (totals.type === 'delivery') {
				$deliveryRow.removeAttribute('hidden');
				setText(this.el.delivery, this.deliveryLabel(totals));
			} else {
				$deliveryRow.setAttribute('hidden', '');
			}
		}

		if (this.el.freeHint) {
			if (totals.type === 'delivery' && totals.threshold > 0) {
				if (totals.subtotal >= totals.threshold) {
					hint = this.t('freeDelivery', 'Free delivery');
					addClass(this.el.freeHint, 'is-reached');
				} else {
					hint = fill(this.t('freeDeliveryOver', 'Free delivery over %s'), this.money.money(totals.threshold));
					hint += ' · ' + fill(this.t('remaining', '%s to go'), this.money.money(totals.threshold - totals.subtotal));
					removeClass(this.el.freeHint, 'is-reached');
				}
				this.el.freeHint.textContent = hint;
				this.el.freeHint.removeAttribute('hidden');
			} else {
				this.el.freeHint.setAttribute('hidden', '');
			}
		}

		if (this.el.minNote) {
			if (this.cart.length && minOrder > 0 && remaining > 0) {
				this.el.minNote.textContent = fill(this.t('minOrderNotice', 'Add %s more to reach the minimum order.'), this.money.money(remaining));
				this.el.minNote.removeAttribute('hidden');
			} else {
				this.el.minNote.setAttribute('hidden', '');
			}
		}

		this.updateSendState(totals);
	};

	App.prototype.updateCartButton = function () {
		var count = this.cartCount();
		var subtotal = this.subtotal();
		var confirmHidden = !this.el.confirm || this.el.confirm.hasAttribute('hidden');
		// The order button stays reachable even with an empty cart.
		var visible = confirmHidden;

		if (this.el.cartBtn) {
			if (visible) {
				this.el.cartBtn.removeAttribute('hidden');
			} else {
				this.el.cartBtn.setAttribute('hidden', '');
			}
			this.el.cartBtn.setAttribute('aria-expanded', this.drawerOpen ? 'true' : 'false');
		}

		setText(this.el.cartCount, String(count));
		setText(this.el.cartTotal, count ? this.money.money(subtotal) : '');

		if (this.el.cartCount) {
			this.el.cartCount.hidden = !count;
		}

		if (this.el.cartTotal) {
			this.el.cartTotal.hidden = !count;
		}
		setText(this.el.cartStatus, count ? this.countLabel(count) + ' · ' + this.money.money(subtotal) : '');
	};

	App.prototype.updateSendState = function (totals) {
		var reason = this.blockReason(totals);
		var sending = this.sending;

		if (!this.el.send) {
			return;
		}

		this.el.send.disabled = !!reason || sending;
		setText(this.el.sendHint, sending ? this.t('sending', 'Sending…') : (reason || this.t('sendingHint', 'WhatsApp opens with your order ready to send.')));
		toggleClass(this.el.sendHint, 'is-warn', !!reason && !sending);

		if (this.el.noWhatsapp) {
			setHidden(this.el.noWhatsapp, !!this.settings.whatsapp && !!this.whatsappNumber());
		}
	};

	App.prototype.whatsappNumber = function () {
		return String(this.settings.whatsappNumber || '').replace(/\D+/g, '');
	};

	App.prototype.blockReason = function (totals) {
		totals = totals || this.computeTotals();

		var minOrder = intValue(this.settings.minOrder, 0);
		var scope = this.settings.minOrderScope === 'total' ? 'total' : 'subtotal';
		var basis = scope === 'total' ? totals.total : totals.subtotal;
		var remaining = minOrder - basis;

		if (!this.cart.length) {
			return this.t('emptyCart', 'Your order is empty.');
		}
		if (!this.data.open) {
			return this.t('businessClosed', 'We are closed right now');
		}
		if (!this.settings.whatsapp || !this.whatsappNumber()) {
			return this.t('whatsappMissing', 'WhatsApp number is not configured yet.');
		}
		if (!this.enabledTypes().length) {
			return this.t('notAvailable', 'Ordering is not available right now.');
		}
		if (!this.fields.payment.length) {
			return this.t('notAvailable', 'Ordering is not available right now.');
		}
		if (minOrder > 0 && remaining > 0) {
			return fill(this.t('minOrderNotice', 'Add %s more to reach the minimum order.'), this.money.money(remaining));
		}
		return '';
	};

	/* ---------------------------------------------------------------------
	 * Drawer open / close
	 * ------------------------------------------------------------------ */

	App.prototype.openDrawer = function (trigger) {
		if (!this.el.drawer || (this.el.dialog && this.el.dialog.open)) {
			return;
		}

		var self = this;
		var target = this.el.drawerClosers[0];

		this.drawerTrigger = trigger || document.activeElement;
		this.renderCart();
		addClass(this.el.drawer, 'is-open');
		this.el.drawer.setAttribute('aria-hidden', 'false');
		this.drawerOpen = true;

		/* Load the map only once someone actually opens the cart (each map load is billed by Google). */
		if (this.currentType() === 'delivery' && this.fields.map) {
			this.ensureMap();
		}

		if (this.el.drawerBackdrop) {
			window.clearTimeout(this.backdropTimer);
			this.el.drawerBackdrop.removeAttribute('hidden');
		}

		if (this.el.cartBtn) {
			this.el.cartBtn.setAttribute('aria-expanded', 'true');
		}

		lockScroll(this.instance + ':drawer');
		document.addEventListener('keydown', this.boundDrawerKey, true);

		if (!target) {
			target = this.el.drawer;
			this.el.drawer.setAttribute('tabindex', '-1');
		}

		window.setTimeout(function () {
			if (self.drawerOpen && target) {
				target.focus();
			}
		}, 30);
	};

	App.prototype.closeDrawer = function (restoreFocus) {
		if (!this.el.drawer || !this.drawerOpen) {
			return;
		}

		var self = this;
		var trigger = this.drawerTrigger;

		this.drawerOpen = false;
		removeClass(this.el.drawer, 'is-open');
		this.el.drawer.setAttribute('aria-hidden', 'true');
		document.removeEventListener('keydown', this.boundDrawerKey, true);
		unlockScroll(this.instance + ':drawer');

		if (this.el.cartBtn) {
			this.el.cartBtn.setAttribute('aria-expanded', 'false');
		}

		if (this.el.drawerBackdrop) {
			window.clearTimeout(this.backdropTimer);
			this.backdropTimer = window.setTimeout(function () {
				if (!self.drawerOpen && self.el.drawerBackdrop) {
					self.el.drawerBackdrop.setAttribute('hidden', '');
				}
			}, 280);
		}

		if (restoreFocus !== false) {
			if (trigger && document.contains(trigger) && trigger.focus) {
				trigger.focus();
			} else if (this.el.cartBtn && !this.el.cartBtn.hidden) {
				this.el.cartBtn.focus();
			}
		}
	};

	App.prototype.onDrawerKey = function (event) {
		if (!this.drawerOpen) {
			return;
		}
		if (this.el.dialog && this.el.dialog.open) {
			return;
		}

		if (event.key === 'Escape' || event.keyCode === 27) {
			event.preventDefault();
			this.closeDrawer(true);
			return;
		}

		if (event.key === 'Tab' || event.keyCode === 9) {
			trapFocus(this.el.drawer, event);
		}
	};

	App.prototype.onConfirmKey = function (event) {
		if (!this.el.confirm || this.el.confirm.hasAttribute('hidden')) {
			return;
		}
		if (event.key === 'Tab' || event.keyCode === 9) {
			trapFocus(this.el.confirm, event);
		}
	};

	function trapFocus(container, event) {
		var nodes = qsa('a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])', container);
		var visible = [];
		var i;
		var first;
		var last;
		var index = -1;

		for (i = 0; i < nodes.length; i++) {
			if (nodes[i].getClientRects().length) {
				visible.push(nodes[i]);
			}
		}

		if (!visible.length) {
			return;
		}

		first = visible[0];
		last = visible[visible.length - 1];

		for (i = 0; i < visible.length; i++) {
			if (visible[i] === document.activeElement) {
				index = i;
				break;
			}
		}

		/* index -1 covers focus parked on a tabindex="-1" heading (confirmation). */
		if (event.shiftKey) {
			if (index <= 0) {
				event.preventDefault();
				last.focus();
			}
		} else if (index === -1 || index === visible.length - 1) {
			event.preventDefault();
			first.focus();
		}
	}

	/* ---------------------------------------------------------------------
	 * Item dialog
	 * ------------------------------------------------------------------ */

	App.prototype.openDialog = function (itemId, editIndex) {
		var item = this.item(itemId);
		var line;
		var opts;

		if (!item || item.available === false || !this.el.dialog) {
			return;
		}

		line = editIndex >= 0 ? this.cart[editIndex] : null;
		opts = line ? (line.opts || []).slice() : this.defaultOpts(item);

		this.editIndex = line ? editIndex : -1;
		this.dialogState = {
			id: itemId,
			qty: line ? intValue(line.qty, 1) : 1,
			opts: opts,
			note: line ? String(line.note || '') : ''
		};
		this.dialogTrigger = document.activeElement;

		this.paintDialog(item, !!line);
		lockScroll(this.instance + ':dialog');

		if (typeof this.el.dialog.showModal === 'function') {
			if (!this.el.dialog.open) {
				this.el.dialog.showModal();
			}
		} else {
			this.el.dialog.setAttribute('open', '');
			this.el.dialog.setAttribute('aria-modal', 'true');
		}
	};

	App.prototype.paintDialog = function (item, editing) {
		var self = this;

		setText(this.el.dialogTitle, item.name);
		setText(this.el.dialogDesc, item.description || '');
		setHidden(this.el.dialogDesc, !item.description);

		if (this.el.dialogMedia) {
			this.el.dialogMedia.textContent = '';
			if (item.image && item.image.url) {
				var img = create('img');
				img.src = item.image.url;
				if (item.image.srcset) {
					img.setAttribute('srcset', item.image.srcset);
					img.setAttribute('sizes', '(min-width: 760px) 660px, 100vw');
				}
				img.alt = item.image.alt || item.name;
				img.width = intValue(item.image.width, 800) || 800;
				img.height = intValue(item.image.height, 450) || 450;
				img.loading = 'eager';
				img.decoding = 'async';
				this.el.dialogMedia.appendChild(img);
				this.el.dialogMedia.removeAttribute('hidden');
				removeClass(this.el.dialogMedia, 'wfo-dialog__media--empty');
			} else {
				this.el.dialogMedia.setAttribute('hidden', '');
				addClass(this.el.dialogMedia, 'wfo-dialog__media--empty');
			}
		}

		if (this.el.dialogGroups) {
			this.el.dialogGroups.textContent = '';
			each(item.groups || [], function (group) {
				self.el.dialogGroups.appendChild(self.buildGroup(item, group));
			});
		}

		if (this.el.dialogNote) {
			this.el.dialogNote.value = this.dialogState.note || '';
		}

		if (this.el.qtyInput) {
			this.el.qtyInput.value = String(this.dialogState.qty);
		}

		if (this.el.dialogAddLabel) {
			this.el.dialogAddLabel.textContent = editing
				? this.t('updateOrder', 'Update order')
				: this.t('addToOrder', 'Add to order');
		}

		this.updateCheckboxLimits();
		this.updateDialogTotal();
	};

	App.prototype.buildGroup = function (item, group) {
		var self = this;
		var fieldset = create('fieldset', 'wfo-optgroup wfo-group');
		var legend = create('legend', 'wfo-optgroup__head');
		var label = create('span', 'wfo-optgroup__label');
		var meta = create('span', 'wfo-optgroup__meta');
		var options = create('div', 'wfo-optgroup__options');

		fieldset.setAttribute('data-wfo-group', group.id);
		label.textContent = group.label;
		meta.textContent = this.groupMeta(group);
		legend.appendChild(label);
		legend.appendChild(meta);

		each(group.options || [], function (option) {
			var token = group.id + ':' + option.id;
			var wrapper = create('label', 'wfo-option');
			var input = document.createElement('input');
			var text = create('span', 'wfo-option__label');
			var price;

			input.type = group.type === 'radio' ? 'radio' : 'checkbox';
			input.name = 'wfo-opt-' + self.safeInstance + '-' + item.id + '-' + group.id;
			input.value = token;
			input.setAttribute('data-wfo-opt', token);
			input.setAttribute('data-wfo-group', group.id);
			input.checked = self.dialogState.opts.indexOf(token) !== -1;
			input.id = 'wfo-opt-' + self.safeInstance + '-' + item.id + '-' + group.id + '-' + option.id;

			if (option.soldout) {
				input.disabled = true;
				input.setAttribute('data-wfo-soldout', '1');
				addClass(wrapper, 'is-soldout');
			}

			if (input.checked) {
				addClass(wrapper, 'is-selected');
			}

			text.textContent = option.label;
			wrapper.appendChild(input);
			wrapper.appendChild(text);

			if (intValue(option.price, 0) !== 0) {
				price = create('span', 'wfo-option__price');
				price.textContent = (option.price > 0 ? '+ ' : '\u2212 ') + self.money.money(Math.abs(option.price));
				wrapper.appendChild(price);
			}

			if (option.soldout) {
				var sold = create('span', 'wfo-option__soldout');
				sold.textContent = self.t('soldOut', 'Sold out');
				wrapper.appendChild(sold);
			}

			options.appendChild(wrapper);
		});

		fieldset.appendChild(legend);
		fieldset.appendChild(options);

		return fieldset;
	};

	App.prototype.groupMeta = function (group) {
		var bits = [];
		var max = intValue(group.max, 0);
		var min = intValue(group.min, 0);

		bits.push(group.required ? this.t('required', 'Required') : this.t('optional', 'Optional'));

		if (group.type === 'checkbox') {
			if (max > 1 && min > 0) {
				bits.push(min + '\u2013' + max);
			} else if (max > 1) {
				bits.push(fillNumber(this.t('upTo', 'up to %d'), max));
			} else if (min > 0) {
				bits.push(this.t('choose', 'Choose') + ' ' + min);
			}
		}

		return bits.join(' · ');
	};

	App.prototype.defaultOpts = function (item) {
		var self = this;
		var tokens = [];

		each(item.groups || [], function (group) {
			var defaults = [];
			var available = [];

			each(group.options || [], function (option) {
				if (option.soldout) {
					return;
				}
				available.push(option);
				if (option.default) {
					defaults.push(option);
				}
			});

			if (group.type === 'radio') {
				var pick = defaults.length ? defaults[0] : (group.required ? available[0] : null);
				if (pick) {
					tokens.push(group.id + ':' + pick.id);
				}
			} else {
				var max = intValue(group.max, 0);
				if (max > 0) {
					defaults = defaults.slice(0, max);
				}
				each(defaults, function (option) {
					tokens.push(group.id + ':' + option.id);
				});
			}
		});

		return tokens;
	};

	App.prototype.onOptionChange = function () {
		var item = this.dialogState ? this.item(this.dialogState.id) : null;
		var groups = this.el.dialogGroups ? qsa('.wfo-option input', this.el.dialogGroups) : [];

		if (!item) {
			return;
		}

		each(groups, function (input) {
			var wrapper = closest(input, '.wfo-option');
			toggleClass(wrapper, 'is-selected', input.checked);
		});

		this.dialogState.opts = this.collectOpts(item);
		this.updateCheckboxLimits();
		this.updateDialogTotal();
	};

	App.prototype.collectOpts = function (item) {
		var checked = {};
		var tokens = [];

		each(this.el.dialogGroups ? qsa('input[data-wfo-opt]:checked', this.el.dialogGroups) : [], function (input) {
			checked[input.getAttribute('data-wfo-opt')] = true;
		});

		each(item.groups || [], function (group) {
			each(group.options || [], function (option) {
				var token = group.id + ':' + option.id;
				if (checked[token]) {
					tokens.push(token);
				}
			});
		});

		return tokens;
	};

	App.prototype.updateCheckboxLimits = function () {
		var item = this.dialogState ? this.item(this.dialogState.id) : null;
		var self = this;

		if (!item) {
			return;
		}

		each(item.groups || [], function (group) {
			var max;
			var count = 0;
			var inputs;

			if (group.type !== 'checkbox') {
				return;
			}

			max = intValue(group.max, 0);
			if (max <= 0) {
				return;
			}

			inputs = self.el.dialogGroups ? qsa('input[data-wfo-opt][data-wfo-group="' + group.id + '"]', self.el.dialogGroups) : [];

			each(inputs, function (input) {
				if (input.checked) {
					count++;
				}
			});

			each(inputs, function (input) {
				if (input.getAttribute('data-wfo-soldout') === '1') {
					return;
				}
				input.disabled = !input.checked && count >= max;
			});
		});
	};

	App.prototype.dialogProblem = function (item, qty) {
		var self = this;
		var checked = {};
		var problem = '';

		if (!item) {
			return '';
		}

		each(this.el.dialogGroups ? qsa('input[data-wfo-opt]:checked', this.el.dialogGroups) : [], function (input) {
			checked[input.getAttribute('data-wfo-opt')] = true;
		});

		each(item.groups || [], function (group) {
			var count = 0;
			each(group.options || [], function (option) {
				if (checked[group.id + ':' + option.id]) {
					count++;
				}
			});

			if (problem) {
				return;
			}

			if (group.type === 'radio' && group.required && count < 1) {
				problem = (self.t('choose', 'Choose') + ' · ' + group.label);
			} else if (group.type !== 'radio') {
				var min = intValue(group.min, 0);
				var max = intValue(group.max, 0);
				if (min > 0 && count < min) {
					problem = self.t('choose', 'Choose') + ' ' + min + ' · ' + group.label;
				} else if (max > 0 && count > max) {
					problem = fillNumber(self.t('upTo', 'up to %d'), max) + ' · ' + group.label;
				}
			}
		});

		if (!problem && qty < 1) {
			problem = this.t('quantity', 'Quantity');
		}

		return problem;
	};

	App.prototype.updateDialogTotal = function () {
		var state = this.dialogState;
		var item = state ? this.item(state.id) : null;
		var qty;
		var total;
		var problem;

		if (!item) {
			return;
		}

		qty = this.el.qtyInput ? clamp(intValue(this.el.qtyInput.value, state.qty), 1, MAX_QTY) : state.qty;
		state.qty = qty;
		total = (intValue(item.price, 0) + this.optionDeltas(item, this.collectOpts(item))) * qty;
		problem = this.dialogProblem(item, qty);

		setText(this.el.dialogTotal, this.money.money(total));

		if (this.el.dialogAdd) {
			this.el.dialogAdd.disabled = !!problem;
		}

		if (this.el.dialogHint) {
			this.el.dialogHint.textContent = problem || '';
			toggleClass(this.el.dialogHint, 'is-warn', !!problem);
		}

		if (this.el.qtyMinus) {
			this.el.qtyMinus.disabled = qty <= 1;
		}
		if (this.el.qtyPlus) {
			this.el.qtyPlus.disabled = qty >= MAX_QTY;
		}
	};

	App.prototype.optionDeltas = function (item, tokens) {
		var self = this;
		var total = 0;
		each(tokens, function (token) {
			var ref = self.optionRef(item.id, token);
			if (ref) {
				total += intValue(ref.option.price, 0);
			}
		});
		return total;
	};

	App.prototype.changeQty = function (delta) {
		if (!this.dialogState) {
			return;
		}
		this.dialogState.qty = clamp(this.dialogState.qty + delta, 1, MAX_QTY);
		if (this.el.qtyInput) {
			this.el.qtyInput.value = String(this.dialogState.qty);
		}
		this.updateDialogTotal();
	};

	App.prototype.saveLine = function () {
		var state = this.dialogState;
		var item = state ? this.item(state.id) : null;
		var qty;
		var opts;
		var note;
		var line;
		var existing;

		if (!item) {
			return;
		}

		qty = clamp(intValue(this.el.qtyInput ? this.el.qtyInput.value : state.qty, state.qty), 1, MAX_QTY);
		opts = this.collectOpts(item);

		if (this.dialogProblem(item, qty)) {
			this.updateDialogTotal();
			return;
		}

		note = this.el.dialogNote ? String(this.el.dialogNote.value || '').replace(/\s+/g, ' ').trim().slice(0, MAX_ITEM_NOTE) : '';
		line = { id: item.id, qty: qty, opts: opts, note: note };

		if (this.editIndex >= 0 && this.cart[this.editIndex]) {
			this.cart[this.editIndex] = line;
		} else {
			existing = this.findLine(line);
			if (existing >= 0) {
				this.cart[existing].qty = clamp(this.cart[existing].qty + qty, 1, MAX_QTY);
			} else {
				this.cart.push(line);
			}
		}

		this.saveCart();
		this.renderCart();
		this.editIndex = -1;
		this.closeDialog();
	};

	App.prototype.findLine = function (line) {
		var i;
		var entry;
		var a = line.opts.slice().sort().join('|');

		for (i = 0; i < this.cart.length; i++) {
			entry = this.cart[i];
			if (entry.id === line.id && (entry.note || '') === (line.note || '') && entry.opts.slice().sort().join('|') === a) {
				return i;
			}
		}

		return -1;
	};

	App.prototype.closeDialog = function () {
		if (!this.el.dialog) {
			return;
		}

		if (this.el.dialog.open && typeof this.el.dialog.close === 'function') {
			this.el.dialog.close();
		} else {
			this.el.dialog.removeAttribute('open');
			this.finishDialogClose();
		}
	};

	App.prototype.finishDialogClose = function () {
		var trigger = this.dialogTrigger;

		unlockScroll(this.instance + ':dialog');
		this.dialogState = null;
		this.editIndex = -1;

		if (trigger && document.contains(trigger) && trigger.focus) {
			trigger.focus();
		} else if (this.drawerOpen && this.el.drawerClosers[0]) {
			this.el.drawerClosers[0].focus();
		} else if (this.el.cartBtn && !this.el.cartBtn.hidden) {
			this.el.cartBtn.focus();
		}
	};

	/* ---------------------------------------------------------------------
	 * Line editing
	 * ------------------------------------------------------------------ */

	App.prototype.changeLineQty = function (index, delta) {
		if (index < 0 || !this.cart[index]) {
			return;
		}
		this.cart[index].qty = clamp(intValue(this.cart[index].qty, 1) + delta, 1, MAX_QTY);
		this.saveCart();
		this.renderCart();
	};

	App.prototype.removeLine = function (index) {
		if (index < 0 || !this.cart[index]) {
			return;
		}
		this.cart.splice(index, 1);
		this.saveCart();
		this.renderCart();

		if (!this.cart.length) {
			var focusTarget = this.el.drawerClosers[0];
			if (focusTarget) {
				focusTarget.focus();
			}
		}
	};

	App.prototype.editLine = function (index) {
		if (index < 0 || !this.cart[index]) {
			return;
		}
		this.openDialog(this.cart[index].id, index);
	};

	/* ---------------------------------------------------------------------
	 * Form behaviour
	 * ------------------------------------------------------------------ */

	App.prototype.onFormChange = function (target) {
		if (!target || !target.name) {
			return;
		}

		var isType = target.name === 'type';
		var isPayment = target.name === 'payment';
		var field = '';

		if (isType) {
			this.syncFormState();
		} else if (isPayment) {
			this.syncFormState();
		}

		if (target.name.indexOf('customer[') === 0) {
			field = target.name.slice(9, -1);
		} else if (isType) {
			field = 'type';
		} else if (isPayment) {
			field = 'payment';
		}

		if (field) {
			this.clearError(field);
		}

		this.persistDraftSoon();
		this.updateSummary();
	};

	App.prototype.syncFormState = function () {
		var type = this.currentType();
		var delivery = type === 'delivery';

		each(qsa('.wfo-segment__item', this.root), function (item) {
			var input = qs('input', item);
			toggleClass(item, 'is-checked', !!(input && input.checked));
		});

		each(qsa('.wfo-pay__card', this.root), function (item) {
			var input = qs('input', item);
			toggleClass(item, 'is-checked', !!(input && input.checked));
		});

		if (this.fields.addressWrap) {
			if (delivery) {
				this.fields.addressWrap.removeAttribute('hidden');
			} else {
				this.fields.addressWrap.setAttribute('hidden', '');
			}
		}

		if (this.fields.address) {
			this.fields.address.setAttribute('aria-required', delivery ? 'true' : 'false');
		}

		if (this.fields.deliveryNote) {
			if (delivery) {
				this.fields.deliveryNote.removeAttribute('hidden');
			} else {
				this.fields.deliveryNote.setAttribute('hidden', '');
			}
		}

		if (delivery && this.fields.map && this.drawerOpen) {
			this.ensureMap();
		}
	};

	/* ---------------------------------------------------------------------
	 * Delivery pin on Google Maps (only when a Maps key is configured)
	 * ------------------------------------------------------------------- */

	App.prototype.mapsLink = function (latlng) {
		return latlng ? 'https://www.google.com/maps?q=' + latlng : '';
	};

	/* Address text plus the pin link, as stored and sent. */
	App.prototype.addressWithPin = function (values) {
		var lines = [];

		if (values.address) {
			lines.push(values.address);
		}
		if (values.location) {
			lines.push(this.t('location', 'Location') + ': ' + this.mapsLink(values.location));
		}

		return lines.join('\n');
	};

	App.prototype.ensureMap = function () {
		var self = this;
		var state = this.mapState;
		var key = this.settings.mapsKey;

		if (state.ready || state.loading || state.failed || !key || !this.fields.mapCanvas) {
			return;
		}

		state.loading = true;

		loadGoogleMaps(key, function (ok) {
			state.loading = false;

			if (!ok) {
				state.failed = true;
				return;
			}

			try {
				self.buildMap();
			} catch (e) {
				state.failed = true;
				if (self.fields.map) {
					self.fields.map.setAttribute('hidden', '');
				}
			}
		});
	};

	App.prototype.buildMap = function () {
		var self = this;
		var state = this.mapState;
		var gm = window.google.maps;
		var center = parseLatLng(this.settings.mapCenter) || { lat: 18.980899, lng: -99.088958 };
		var saved = parseLatLng(this.fields.location ? this.fields.location.value : '');

		this.fields.map.removeAttribute('hidden');

		state.map = new gm.Map(this.fields.mapCanvas, {
			center: saved || center,
			zoom: saved ? 17 : 15,
			gestureHandling: 'cooperative',
			mapTypeControl: false,
			streetViewControl: false,
			fullscreenControl: false,
			clickableIcons: false
		});
		state.ready = true;

		state.map.addListener('click', function (event) {
			self.setPin({ lat: event.latLng.lat(), lng: event.latLng.lng() }, false);
		});

		if (saved) {
			this.setPin(saved, false);
		}

		on(this.fields.mapLocate, 'click', function () {
			self.locateMe();
		});

		on(this.fields.mapClear, 'click', function () {
			self.clearPin();
		});
	};

	App.prototype.setPin = function (pos, pan) {
		var self = this;
		var state = this.mapState;
		var gm = window.google && window.google.maps;

		if (!gm || !state.map) {
			return;
		}

		if (!state.marker) {
			state.marker = new gm.Marker({ map: state.map, position: pos, draggable: true });
			state.marker.addListener('dragend', function () {
				var p = state.marker.getPosition();
				self.storePin({ lat: p.lat(), lng: p.lng() });
			});
		} else {
			state.marker.setPosition(pos);
			state.marker.setMap(state.map);
		}

		if (pan) {
			state.map.panTo(pos);
			state.map.setZoom(17);
		}

		this.storePin(pos);
	};

	App.prototype.storePin = function (pos) {
		if (this.fields.location) {
			this.fields.location.value = pos.lat.toFixed(6) + ',' + pos.lng.toFixed(6);
		}
		if (this.fields.mapClear) {
			this.fields.mapClear.removeAttribute('hidden');
		}
		this.setMapStatus(this.t('mapPinSet', 'Pin saved. We will send it with your order.'), false);
		this.clearError('address');
		this.persistDraftSoon();
	};

	App.prototype.clearPin = function () {
		if (this.mapState.marker) {
			this.mapState.marker.setMap(null);
		}
		if (this.fields.location) {
			this.fields.location.value = '';
		}
		if (this.fields.mapClear) {
			this.fields.mapClear.setAttribute('hidden', '');
		}
		this.setMapStatus('', false);
		this.persistDraftSoon();
	};

	App.prototype.locateMe = function () {
		var self = this;

		if (!navigator.geolocation) {
			this.setMapStatus(this.t('mapLocateError', 'We could not get your location. Place the pin by hand.'), true);
			return;
		}

		this.setMapStatus(this.t('mapLocating', 'Finding you…'), false);

		navigator.geolocation.getCurrentPosition(function (position) {
			self.setPin({ lat: position.coords.latitude, lng: position.coords.longitude }, true);
		}, function () {
			self.setMapStatus(self.t('mapLocateError', 'We could not get your location. Place the pin by hand.'), true);
		}, { enableHighAccuracy: true, timeout: 12000, maximumAge: 60000 });
	};

	App.prototype.setMapStatus = function (text, isError) {
		if (!this.fields.mapStatus) {
			return;
		}
		this.fields.mapStatus.textContent = text;
		toggleClass(this.fields.mapStatus, 'is-error', !!isError);
	};

	App.prototype.validate = function () {
		var values = this.formValues();
		var errors = {};
		var order = [];
		var enabledTypes = this.enabledTypes();
		var enabledPayments = this.fields.payment.length;
		var digits = values.phone.replace(/\D+/g, '');
		var first = '';

		if (!values.name) {
			errors.name = this.t('requiredField', 'Please fill this in.');
			order.push('name');
		}

		if (!values.phone) {
			errors.phone = this.t('requiredField', 'Please fill this in.');
			order.push('phone');
		} else if (digits.length < 6 || digits.length > 20) {
			errors.phone = this.t('invalidPhone', 'Enter a valid phone number.');
			order.push('phone');
		}

		if (!enabledTypes.length) {
			errors.type = this.t('notAvailable', 'Ordering is not available right now.');
			order.push('type');
		} else if (!values.type) {
			errors.type = this.t('chooseType', 'Choose pickup or delivery.');
			order.push('type');
		}

		if (values.type === 'delivery' && !values.address && !values.location) {
			errors.address = this.mapState.ready
				? this.t('fillAddressOrPin', 'Write your address or place the pin on the map.')
				: this.t('fillAddress', 'We need an address for delivery.');
			order.push('address');
		}

		if (!enabledPayments) {
			errors.payment = this.t('notAvailable', 'Ordering is not available right now.');
			order.push('payment');
		} else if (!values.payment) {
			errors.payment = this.t('choosePayment', 'Choose how you would like to pay.');
			order.push('payment');
		}

		first = order.length ? order[0] : '';

		return { valid: !order.length, errors: errors, first: first };
	};

	App.prototype.clearErrors = function () {
		this.showErrors({});
		if (this.el.sendError) {
			this.el.sendError.textContent = '';
			this.el.sendError.setAttribute('hidden', '');
		}
	};

	App.prototype.clearError = function (field) {
		var wrapper = qs('[data-wfo-field="' + field + '"]', this.root);
		var error = qs('[data-wfo-error="' + field + '"]', this.root);
		var inputs;

		if (!wrapper || !error) {
			return;
		}

		setText(error, '');
		removeClass(wrapper, 'has-error');
		inputs = qsa('input, select, textarea', wrapper);
		each(inputs, function (input) {
			input.removeAttribute('aria-invalid');
		});
	};

	App.prototype.showErrors = function (errors) {
		var self = this;
		var fields = ['name', 'phone', 'type', 'address', 'payment'];

		each(fields, function (field) {
			var wrapper = qs('[data-wfo-field="' + field + '"]', self.root);
			var error = qs('[data-wfo-error="' + field + '"]', self.root);
			var has = Object.prototype.hasOwnProperty.call(errors, field) && !!errors[field];
			var inputs;

			if (!wrapper) {
				return;
			}

			toggleClass(wrapper, 'has-error', has);

			if (error) {
				setText(error, has ? errors[field] : '');
			}

			inputs = qsa('input, select, textarea', wrapper);
			each(inputs, function (input) {
				if (has) {
					input.setAttribute('aria-invalid', 'true');
				} else {
					input.removeAttribute('aria-invalid');
				}
			});
		});
	};

	App.prototype.focusField = function (field) {
		var wrapper = qs('[data-wfo-field="' + field + '"]', this.root);
		var target = wrapper ? qs('input, select, textarea', wrapper) : null;

		if (target && target.focus) {
			target.focus();
			if (target.scrollIntoView) {
				try {
					target.scrollIntoView({ block: 'center', behavior: prefersReducedMotion() ? 'auto' : 'smooth' });
				} catch (e) {
					/* older browsers */
				}
			}
		}
	};

	App.prototype.resetForm = function () {
		var self = this;

		if (this.fields.name) {
			this.fields.name.value = '';
		}
		if (this.fields.phone) {
			this.fields.phone.value = '';
		}
		if (this.fields.address) {
			this.fields.address.value = '';
		}
		this.clearPin();
		if (this.fields.note) {
			this.fields.note.value = '';
		}
		if (this.fields.time) {
			this.fields.time.selectedIndex = 0;
		}

		each(this.fields.type, function (input, index) {
			input.checked = index === 0;
		});

		each(this.fields.payment, function (input) {
			input.checked = self.fields.payment.length === 1;
		});

		if (this.el.search) {
			this.el.search.value = '';
		}

		this.syncFormState();
		this.clearErrors();
		this.applySearch('');
	};

	/* ---------------------------------------------------------------------
	 * Search
	 * ------------------------------------------------------------------ */

	App.prototype.applySearch = function (query) {
		var self = this;
		var q = normalizeText(query);
		var visible = 0;
		var anySection = false;

		each(this.el.cards, function (card) {
			var id = intValue(card.getAttribute('data-wfo-item'), 0);
			var haystack = self.searchText[id] || '';
			var match = !q || haystack.indexOf(q) !== -1;

			if (match) {
				card.removeAttribute('hidden');
				visible++;
			} else {
				card.setAttribute('hidden', '');
			}
		});

		each(this.el.sections, function (section) {
			var has = qs('.wfo-card:not([hidden])', section);
			if (has) {
				section.removeAttribute('hidden');
				anySection = true;
			} else {
				section.setAttribute('hidden', '');
			}
		});

		each(this.el.catLinks, function (link) {
			var section = self.sectionBySlug(link.getAttribute('data-wfo-cat'));
			if (section && section.hidden) {
				link.setAttribute('hidden', '');
			} else {
				link.removeAttribute('hidden');
			}
		});

		if (this.el.noResults) {
			setHidden(this.el.noResults, !q || visible > 0 || !anySection && !this.el.sections.length);
		}
		if (this.el.searchClear) {
			setHidden(this.el.searchClear, !q);
		}
		if (this.el.searchStatus) {
			this.el.searchStatus.textContent = q ? visible + ' ' + this.t('items', 'items') : '';
		}

		if (!q) {
			this.spy();
		}
	};

	/* ---------------------------------------------------------------------
	 * Category bar, sticky offset and scroll spy
	 * ------------------------------------------------------------------ */

	App.prototype.sectionBySlug = function (slug) {
		var i;
		for (i = 0; i < this.el.sections.length; i++) {
			if (this.el.sections[i].getAttribute('data-wfo-section') === slug) {
				return this.el.sections[i];
			}
		}
		return null;
	};

	App.prototype.catOffset = function () {
		if (!this.el.catbar) {
			return 16;
		}
		var style = window.getComputedStyle ? window.getComputedStyle(this.el.catbar) : null;
		var top = style ? parseFloat(style.top) || 0 : 0;
		return this.el.catbar.offsetHeight + top + 16;
	};

	App.prototype.measureCatbar = function () {
		if (!this.el.catbar) {
			return;
		}
		var style = window.getComputedStyle ? window.getComputedStyle(this.el.catbar) : null;
		var top = style ? parseFloat(style.top) || 0 : 0;
		this.root.style.setProperty('--wfo-catbar-h', (this.el.catbar.offsetHeight + top) + 'px');
	};

	App.prototype.scrollToSection = function (section) {
		var top = section.getBoundingClientRect().top + (window.pageYOffset || document.documentElement.scrollTop || 0) - this.catOffset();
		scrollToPosition(Math.max(0, top));
	};

	App.prototype.setActiveCat = function (slug, scrollLink) {
		var self = this;
		var activeLink = null;

		if (slug === this.activeCat) {
			return;
		}

		this.activeCat = slug;

		each(this.el.catLinks, function (link) {
			var isActive = link.getAttribute('data-wfo-cat') === slug;
			toggleClass(link, 'is-active', isActive);
			if (isActive) {
				link.setAttribute('aria-current', 'true');
				activeLink = link;
			} else {
				link.removeAttribute('aria-current');
			}
		});

		if (scrollLink && activeLink && this.el.catTrack && this.el.catTrack.scrollWidth > this.el.catTrack.clientWidth) {
			var left = activeLink.offsetLeft - ((this.el.catTrack.clientWidth - activeLink.offsetWidth) / 2);
			if ('scrollBehavior' in document.documentElement.style) {
				this.el.catTrack.scrollTo({ left: Math.max(0, left), behavior: this.reducedMotion ? 'auto' : 'smooth' });
			} else {
				this.el.catTrack.scrollLeft = Math.max(0, left);
			}
		}
	};

	App.prototype.spy = function () {
		var self = this;
		var offset = this.catOffset();
		var active = null;

		if (!this.el.sections.length) {
			return;
		}

		each(this.el.sections, function (section) {
			if (section.hidden) {
				return;
			}
			if (section.getBoundingClientRect().top - offset <= 2) {
				active = section;
			}
		});

		if (!active) {
			for (var i = 0; i < this.el.sections.length; i++) {
				if (!this.el.sections[i].hidden) {
					active = this.el.sections[i];
					break;
				}
			}
		}

		if (active) {
			this.setActiveCat(active.getAttribute('data-wfo-section'), true);
		}
	};

	App.prototype.initSpy = function () {
		this.spy();
	};

	App.prototype.onScroll = function () {
		var self = this;

		if (this.scrollTick) {
			return;
		}

		this.scrollTick = true;

		if (window.requestAnimationFrame) {
			window.requestAnimationFrame(function () {
				self.scrollTick = false;
				self.spy();
			});
		} else {
			window.setTimeout(function () {
				self.scrollTick = false;
				self.spy();
			}, 80);
		}
	};

	App.prototype.onResize = function () {
		this.measureCatbar();
		this.spy();
	};

	/* ---------------------------------------------------------------------
	 * Sending
	 * ------------------------------------------------------------------ */

	App.prototype.sendOrder = function () {
		var self = this;
		var totals;
		var values;
		var validation;
		var block;
		var code;
		var built;
		var itemsPayload;
		var snapshot;

		if (this.sending) {
			return;
		}

		if (!this.cart.length) {
			return;
		}

		totals = this.computeTotals();
		block = this.blockReason(totals);

		if (block) {
			if (this.el.sendHint) {
				this.el.sendHint.textContent = block;
				addClass(this.el.sendHint, 'is-warn');
			}
			return;
		}

		validation = this.validate();

		if (!validation.valid) {
			this.showErrors(validation.errors);
			this.focusField(validation.first);
			return;
		}

		this.clearErrors();
		values = this.formValues();
		code = generateCode(this.settings.orderPrefix);
		built = this.buildMessage(code, values, totals);

		itemsPayload = [];
		each(this.cart, function (line) {
			itemsPayload.push({
				id: line.id,
				qty: line.qty,
				opts: (line.opts || []).slice(),
				note: line.note || ''
			});
		});

		snapshot = {
			code: code,
			total: totals.total,
			subtotal: totals.subtotal,
			fee: totals.fee,
			payment: values.payment,
			message: built.message,
			url: built.url,
			customer: values,
			items: itemsPayload
		};

		this.sending = true;
		this.lastMessage = built.message;
		this.lastWaUrl = built.url;

		/* Must happen synchronously inside the click/submit gesture. */
		try {
			window.open(built.url, '_blank', 'noopener,noreferrer');
		} catch (e) {
			/* Popup blockers still leave the confirmation link usable. */
		}

		this.showConfirmation(snapshot);

		this.cart = [];
		this.saveCart();
		this.clearDraft();
		this.renderCart();
		this.setSendingState(false);

		this.postOrder(snapshot);
	};

	App.prototype.setSendingState = function (sending) {
		this.sending = sending;
		if (this.el.sendLabel) {
			setText(this.el.sendLabel, sending ? this.t('sending', 'Sending…') : this.t('sendOnWhatsApp', 'Send order on WhatsApp'));
		}
		this.updateSendState();
	};

	App.prototype.buildMessage = function (code, values, totals) {
		var self = this;
		var notes = {
			order: values.note || '',
			items: []
		};
		var lines = this.cart.map(function (line, index) {
			notes.items[index] = line.note || '';
			return {
				line: line,
				item: self.item(line.id),
				unit: self.lineUnit(line)
			};
		});
		var template = typeof this.settings.messageTemplate === 'string' ? this.settings.messageTemplate : '';
		var timeLabel = values.time === 'asap' ? this.t('asap', 'As soon as possible') : this.timeLabel();
		var message;
		var url;
		var guard = 0;
		var longest;
		var longestLength;
		var overflow;
		var cut;

		if (!template.trim()) {
			template = '*New order* {code}' + '\n' + '{items}' + '\n' + '*Total: {total}*';
		}

		function compose() {
			var itemText = lines.map(function (entry, index) {
				var item = entry.item;
				var text;

				if (!item) {
					return '';
				}

				/* Contract item line: `2× Chicken Burger — 18.00` (amount without symbol). */
				text = entry.line.qty + '\u00D7 ' + item.name + ' \u2014 ' + self.money.amount(entry.unit * entry.line.qty);

				each(entry.line.opts || [], function (token) {
					var ref = self.optionRef(entry.line.id, token);
					if (ref && intValue(ref.option.price, 0) !== 0) {
						text += '\n   + ' + ref.option.label + ' (' + (ref.option.price < 0 ? '-' : '') + self.money.amount(Math.abs(ref.option.price)) + ')';
					}
				});

				if (notes.items[index]) {
					text += '\n   \u00B7 ' + notes.items[index];
				}

				return text;
			}).join('\n');

			var templateValues = {
				code: code,
				restaurant: self.settings.restaurantName || '',
				items: itemText,
				subtotal: self.money.money(totals.subtotal),
				delivery: totals.type === 'delivery' ? self.deliveryLabel(totals) : '',
				total: self.money.money(totals.total),
				payment: values.payment === 'bank' ? self.t('bank', 'Bank transfer') : (values.payment === 'cash' ? self.t('cash', 'Cash') : ''),
				type: values.type === 'delivery' ? self.t('delivery_', 'Delivery') : (values.type === 'pickup' ? self.t('pickup', 'Pickup') : ''),
				name: values.name,
				phone: values.phone,
				address: values.type === 'delivery' ? [
					values.address ? self.t('address', 'Address') + ': ' + values.address : '',
					values.location ? self.t('location', 'Location') + ': ' + self.mapsLink(values.location) : ''
				].filter(Boolean).join('\n') : '',
				time: timeLabel ? self.t('when', 'Time') + ': ' + timeLabel : '',
				note: values.note ? self.t('note', 'Note') + ': ' + values.note : ''
			};

			return applyTemplate(template, templateValues);
		}

		message = compose();
		url = this.waUrl(message);

		while (url.length >= MAX_MESSAGE_URL && guard < 80) {
			guard++;

			longest = null;
			longestLength = 0;

			if (notes.order.length > 2) {
				longest = { type: 'order' };
				longestLength = notes.order.length;
			}

			each(notes.items, function (note, index) {
				if (note.length > 2 && note.length > longestLength) {
					longest = { type: 'item', index: index };
					longestLength = note.length;
				}
			});

			if (!longest) {
				break;
			}

			overflow = url.length - (MAX_MESSAGE_URL - 20);
			cut = Math.max(8, Math.ceil(overflow / 4));

			if (longest.type === 'order') {
				notes.order = shortenNote(notes.order, cut);
			} else {
				notes.items[longest.index] = shortenNote(notes.items[longest.index], cut);
			}

			message = compose();
			url = this.waUrl(message);
		}

		/* Pathological orders (huge cart): trim the final message as a last resort. */
		while (url.length >= MAX_MESSAGE_URL && message.length > 40) {
			message = clipText(message, Math.max(40, message.length - Math.max(40, Math.ceil(url.length - MAX_MESSAGE_URL + 20))));
			url = this.waUrl(message);
		}

		return { message: message, url: url };
	};

	App.prototype.waUrl = function (message) {
		return 'https://wa.me/' + this.whatsappNumber() + '?text=' + encodeURIComponent(message);
	};

	App.prototype.postOrder = function (snapshot) {
		var self = this;
		var lang = String((document.documentElement.getAttribute('lang') || navigator.language || 'en')).slice(0, 2).toLowerCase();

		/*
		 * The server rejects stamps older than 24h (anti-bot time trap). A menu
		 * left open overnight would otherwise lose the order record, so refresh
		 * the stamp just before sending when it has gone stale.
		 */
		var nowTs = Math.floor(Date.now() / 1000);

		if (!this.startedAt || (nowTs - this.startedAt) > (20 * 3600)) {
			this.startedAt = nowTs - 60;
		}
		var payload = {
			code: snapshot.code,
			message: snapshot.message,
			lang: lang,
			items: snapshot.items,
			customer: {
				name: snapshot.customer.name,
				phone: snapshot.customer.phone,
				type: snapshot.customer.type,
				address: snapshot.customer.type === 'delivery' ? this.addressWithPin(snapshot.customer) : snapshot.customer.address,
				time: snapshot.customer.time,
				payment: snapshot.customer.payment,
				note: snapshot.customer.note
			},
			hp: '',
			t: this.startedAt
		};

		if (!window.fetch || !this.data.endpoint) {
			this.setConfirmStatus(this.t('errorNetwork', 'We could not reach the server, but your order is ready in WhatsApp.'), true);
			this.setSendingState(false);
			return;
		}

		window.fetch(this.data.endpoint, {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify(payload),
			keepalive: true,
			credentials: 'same-origin'
		}).then(function (response) {
			return response.json().then(function (body) {
				return { ok: response.ok, body: body };
			}, function () {
				return { ok: response.ok, body: null };
			});
		}).then(function (result) {
			var body = result.body;

			self.sending = false;

			if (result.ok && body && body.ok) {
				if (body.code) {
					setText(self.el.confirmCode, String(body.code));
				}
				if (typeof body.total === 'number') {
					setText(self.el.confirmTotal, self.money.money(body.total));
				}
				if (body.wa_url && /^https:\/\/wa\.me\//.test(String(body.wa_url))) {
					self.lastWaUrl = String(body.wa_url);
					if (self.el.waLink) {
						self.el.waLink.setAttribute('href', self.lastWaUrl);
					}
				}
				self.setConfirmStatus('', false);
			} else {
				var message = body && body.message ? String(body.message) : self.t('errorGeneric', 'Something went wrong. Please try again.');
				self.setConfirmStatus(message, true);
			}
		}).catch(function () {
			self.sending = false;
			self.setConfirmStatus(self.t('errorNetwork', 'We could not reach the server, but your order is ready in WhatsApp.'), true);
		});
	};

	App.prototype.showConfirmation = function (snapshot) {
		var self = this;

		this.closeDrawer(false);

		if (!this.el.confirm) {
			return;
		}

		setText(this.el.confirmCode, snapshot.code);
		setText(this.el.confirmTotal, this.money.money(snapshot.total));

		if (this.el.waLink) {
			this.el.waLink.setAttribute('href', snapshot.url || '#');
		}

		if (this.el.confirmPayment) {
			this.el.confirmPayment.textContent = '';

			var title = create('h3', 'wfo-confirm__pay-title');
			title.textContent = this.t('paymentInstructions', 'Payment instructions');
			this.el.confirmPayment.appendChild(title);

			if (snapshot.payment === 'bank') {
				var bankText = create('p', 'wfo-confirm__pay-text');
				bankText.textContent = this.settings.bankDetails
					? this.t('bankTransferTo', 'Send the transfer to this account and share the receipt on WhatsApp.')
					: this.t('bankHint', 'We will send the account details on WhatsApp.');
				this.el.confirmPayment.appendChild(bankText);

				if (this.settings.bankDetails) {
					var bank = create('pre', 'wfo-confirm__bank');
					bank.textContent = String(this.settings.bankDetails);
					this.el.confirmPayment.appendChild(bank);
				}

				if (this.settings.bankNote) {
					var bankNote = create('p', 'wfo-confirm__bank-note');
					bankNote.textContent = String(this.settings.bankNote);
					this.el.confirmPayment.appendChild(bankNote);
				}
			} else {
				var cashText = create('p', 'wfo-confirm__pay-text');
				cashText.textContent = this.t('cashHint', 'Pay on delivery or pickup.');
				this.el.confirmPayment.appendChild(cashText);
			}

			if (this.settings.paymentNote) {
				var payNote = create('p', 'wfo-confirm__payment-note');
				payNote.textContent = String(this.settings.paymentNote);
				this.el.confirmPayment.appendChild(payNote);
			}
		}

		if (this.el.confirmStatus) {
			this.el.confirmStatus.textContent = '';
			removeClass(this.el.confirmStatus, 'is-warn');
		}

		this.el.confirm.removeAttribute('hidden');
		lockScroll(this.instance + ':confirm');
		document.addEventListener('keydown', this.boundConfirmKey, true);
		this.updateCartButton();

		if (this.el.confirm.scrollTop) {
			this.el.confirm.scrollTop = 0;
		}

		window.setTimeout(function () {
			if (self.el.confirmTitle) {
				self.el.confirmTitle.focus();
			}
		}, 30);
	};

	App.prototype.setConfirmStatus = function (message, warn) {
		if (!this.el.confirmStatus) {
			return;
		}
		this.el.confirmStatus.textContent = message || '';
		toggleClass(this.el.confirmStatus, 'is-warn', !!warn);
	};

	App.prototype.copyOrder = function () {
		var self = this;
		var button = this.el.copy;
		var original = button ? button.textContent : '';

		if (!this.lastMessage) {
			return;
		}

		copyText(this.lastMessage).then(function () {
			if (!button) {
				return;
			}
			button.textContent = self.t('copied', 'Copied');
			window.clearTimeout(self.copyTimer);
			self.copyTimer = window.setTimeout(function () {
				button.textContent = original;
			}, 2000);
		}, function () {
			self.setConfirmStatus(self.t('errorGeneric', 'Something went wrong. Please try again.'), true);
		});
	};

	function copyText(text) {
		if (navigator.clipboard && navigator.clipboard.writeText && window.isSecureContext) {
			return navigator.clipboard.writeText(text).catch(function () {
				return legacyCopy(text);
			});
		}
		return legacyCopy(text);
	}

	function legacyCopy(text) {
		return new Promise(function (resolve, reject) {
			var area = document.createElement('textarea');
			area.value = text;
			area.setAttribute('readonly', '');
			area.style.position = 'fixed';
			area.style.top = '0';
			area.style.left = '-9999px';
			document.body.appendChild(area);
			area.select();
			var ok = false;
			try {
				ok = document.execCommand('copy');
			} catch (e) {
				ok = false;
			}
			document.body.removeChild(area);
			if (ok) {
				resolve();
			} else {
				reject(new Error('copy failed'));
			}
		});
	}

	App.prototype.restart = function () {
		var firstCard;

		if (this.el.confirm) {
			this.el.confirm.setAttribute('hidden', '');
		}
		unlockScroll(this.instance + ':confirm');
		document.removeEventListener('keydown', this.boundConfirmKey, true);

		this.cart = [];
		this.saveCart();
		this.clearDraft();
		this.resetForm();
		this.renderCart();

		this.sending = false;
		this.setSendingState(false);
		this.lastMessage = '';
		this.lastWaUrl = '';

		if (this.el.confirmCode) {
			this.el.confirmCode.textContent = '';
		}
		if (this.el.confirmTotal) {
			this.el.confirmTotal.textContent = '';
		}
		if (this.el.confirmPayment) {
			this.el.confirmPayment.textContent = '';
		}
		if (this.el.waLink) {
			this.el.waLink.setAttribute('href', '#');
		}
		this.setConfirmStatus('', false);

		this.activeCat = '';
		this.measureCatbar();
		this.spy();

		scrollToPosition(Math.max(0, this.root.getBoundingClientRect().top + (window.pageYOffset || 0) - 16));

		firstCard = qs('.wfo-card__hit:not([disabled])', this.root);
		if (firstCard) {
			firstCard.focus({ preventScroll: true });
		}
	};

	/* ---------------------------------------------------------------------
	 * Boot
	 * ------------------------------------------------------------------ */

	function init(root) {
		if (typeof root === 'string') {
			each(qsa(root, document), init);
			return;
		}

		if (!root || root.nodeType !== 1) {
			return;
		}

		if (root.dataset.wfoReady === '1') {
			return;
		}

		var data = readPayload(root);

		if (!data) {
			return;
		}

		root.dataset.wfoReady = '1';

		try {
			new App(root, data);
		} catch (error) {
			delete root.dataset.wfoReady;
			if (window.console && window.console.error) {
				window.console.error('[WFO] Initialisation failed', error);
			}
		}
	}

	function initAll(scope) {
		each(qsa('[data-wfo-root]', scope || document), init);
	}

	window.WFO = {
		init: init,
		version: VERSION
	};

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', function () {
			initAll(document);
		});
	} else {
		initAll(document);
	}

	document.addEventListener('wfo:init', function (event) {
		if (event && event.target && event.target.nodeType === 1 && event.target.matches && event.target.matches('[data-wfo-root]')) {
			init(event.target);
		} else {
			initAll(document);
		}
	});

	var elementorHooked = false;

	function registerElementor() {
		if (elementorHooked) {
			return;
		}

		if (!window.elementorFrontend || !window.elementorFrontend.hooks || !window.elementorFrontend.hooks.addAction) {
			return;
		}

		elementorHooked = true;

		window.elementorFrontend.hooks.addAction('frontend/element_ready/wfo_menu.default', function ($scope) {
			var el = $scope;

			if (el && el.jquery) {
				el = el[0];
			}

			if (!el || el.nodeType !== 1) {
				return;
			}

			if (el.matches && el.matches('[data-wfo-root]')) {
				init(el);
				return;
			}

			initAll(el);
		});
	}

	window.addEventListener('elementor/frontend/init', registerElementor);

	/* Widget scripts can load after Elementor has already booted. */
	if (window.elementorFrontend) {
		registerElementor();
	}
})();
