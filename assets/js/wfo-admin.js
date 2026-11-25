/**
 * WhatsApp Menu & Orders — admin helpers.
 *
 * Vanilla JS for the repeatable fields, jQuery only where wp-admin provides it
 * (jquery-ui-sortable for the menu order and wpColorPicker on the design tab).
 */
(function () {
	'use strict';

	var sequence = 1000;
	var booted = false;

	function ready(fn) {
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', fn);
		} else {
			fn();
		}
	}

	function closest(node, selector) {
		while (node && node.nodeType === 1) {
			if (node.matches && node.matches(selector)) {
				return node;
			}
			node = node.parentNode;
		}
		return null;
	}

	function config() {
		return window.WFO_ADMIN && typeof window.WFO_ADMIN === 'object' ? window.WFO_ADMIN : {};
	}

	function message(key, fallback) {
		var strings = config().i18n || {};
		return strings[key] || fallback;
	}

	function nextIndex() {
		sequence += 1;
		return sequence;
	}

	function cloneTemplate(template, replacements) {
		var html = template.innerHTML;
		var keys = Object.keys(replacements);
		var holder;

		for (var i = 0; i < keys.length; i += 1) {
			html = html.split(keys[i]).join(String(replacements[keys[i]]));
		}

		holder = document.createElement('div');
		holder.innerHTML = html.trim();

		return holder.firstElementChild;
	}

	function setInput(scope, selector, value) {
		var input = scope.querySelector(selector);

		if (input) {
			input.value = value === null || typeof value === 'undefined' ? '' : String(value);
		}
	}

	function setChecked(scope, selector, checked) {
		var input = scope.querySelector(selector);

		if (input) {
			input.checked = !!checked;
		}
	}

	function builderFrom(node) {
		return closest(node, '[data-wfo-builder]');
	}

	function groupFrom(node) {
		return closest(node, '[data-wfo-group]');
	}

	function refreshEmpty(builder) {
		var container = builder.querySelector('[data-wfo-groups]');
		var groups;
		var empty;

		if (!container) {
			return;
		}

		groups = container.querySelectorAll('[data-wfo-group]');
		empty = container.querySelector('[data-wfo-empty]');

		if (groups.length > 0) {
			if (empty) {
				empty.parentNode.removeChild(empty);
			}
			return;
		}

		if (!empty) {
			empty = document.createElement('p');
			empty.className = 'wfo-empty';
			empty.setAttribute('data-wfo-empty', '');
			empty.textContent = message('noGroups', 'No option groups yet.');
			container.appendChild(empty);
		}
	}

	function syncType(group) {
		if (!group) {
			return;
		}

		var select = group.querySelector('[data-wfo-type]');
		var min = group.querySelector('[data-wfo-min]');
		var max = group.querySelector('[data-wfo-max]');

		if (!select) {
			return;
		}

		var isRadio = select.value === 'radio';

		if (min) {
			min.disabled = isRadio;
		}

		if (max) {
			max.disabled = isRadio;
		}
	}

	function addGroup(builder, focus) {
		var template = builder ? builder.querySelector('template[data-wfo-tpl-group]') : null;
		var container = builder ? builder.querySelector('[data-wfo-groups]') : null;
		var index = nextIndex();
		var card;

		if (!template || !container) {
			return null;
		}

		card = cloneTemplate(template, { __g__: index });

		if (!card) {
			return null;
		}

		card.setAttribute('data-index', String(index));
		container.appendChild(card);
		refreshEmpty(builder);
		syncType(card);

		if (focus !== false) {
			var label = card.querySelector('input[name$="[label]"]');

			if (label) {
				label.focus();
			}
		}

		return card;
	}

	function addOption(group, option, focus) {
		var builder = group ? closest(group, '[data-wfo-builder]') : null;
		var template = builder ? builder.querySelector('template[data-wfo-tpl-option]') : null;
		var container = group ? group.querySelector('[data-wfo-options]') : null;
		var groupIndex = group ? group.getAttribute('data-index') : '';
		var row;

		if (!template || !container || !groupIndex) {
			return null;
		}

		row = cloneTemplate(template, {
			__g__: groupIndex,
			__o__: nextIndex()
		});

		if (!row) {
			return null;
		}

		container.appendChild(row);

		if (option) {
			setInput(row, 'input[name$="[label]"]', option.label);
			setInput(row, 'input[name$="[price]"]', option.price);
			setChecked(row, 'input[name$="[default]"]', option.default);
			setChecked(row, 'input[name$="[soldout]"]', option.soldout);
		}

		if (focus !== false) {
			var label = row.querySelector('input[name$="[label]"]');

			if (label) {
				label.focus();
			}
		}

		return row;
	}

	function addBadge(scope) {
		var template = scope.querySelector('template[data-wfo-tpl-badge]');
		var container = scope.querySelector('[data-wfo-badges]');
		var row;

		if (!template || !container) {
			return null;
		}

		row = cloneTemplate(template, { __b__: nextIndex() });

		if (!row) {
			return null;
		}

		container.appendChild(row);

		var label = row.querySelector('input[name$="[label]"]');

		if (label) {
			label.focus();
		}

		return row;
	}

	function fillGroup(card, preset) {
		var type = card.querySelector('[data-wfo-type]');
		var options = preset.options || [];

		setInput(card, 'input[name$="[label]"]', preset.label);
		setChecked(card, 'input[name$="[required]"]', preset.required);
		setInput(card, 'input[name$="[min]"]', typeof preset.min === 'number' ? preset.min : 0);
		setInput(card, 'input[name$="[max]"]', typeof preset.max === 'number' ? preset.max : 0);

		if (type) {
			type.value = preset.type === 'radio' ? 'radio' : 'checkbox';
		}

		for (var i = 0; i < options.length; i += 1) {
			addOption(card, options[i], false);
		}

		syncType(card);
	}

	function insertPreset(select) {
		var presets = config().presets || [];
		var index = parseInt(select.value, 10);
		var preset = presets[index];
		var field = closest(select, '.wfo-field--block') || document;
		var builder = field.querySelector ? field.querySelector('[data-wfo-builder]') : null;
		var card;

		select.value = '';

		if (!preset || !builder) {
			return;
		}

		card = addGroup(builder, false);

		if (!card) {
			return;
		}

		fillGroup(card, preset);
		refreshEmpty(builder);

		var label = card.querySelector('input[name$="[label]"]');

		if (label) {
			label.focus();
		}
	}

	function saveOrder(ids) {
		var settings = config();
		var status = document.querySelector('.wfo-reorder-status');
		var $ = window.jQuery;
		var url = settings.ajaxUrl || window.ajaxurl;

		if (!url || !settings.reorderNonce || !ids.length) {
			return;
		}

		if (status) {
			status.textContent = message('reorderSaving', 'Saving menu order…');
		}

		if (!$ || !$.post) {
			return;
		}

		$.post(
			url,
			{
				action: 'wfo_reorder',
				nonce: settings.reorderNonce,
				ids: ids
			},
			function (response) {
				if (status) {
					status.textContent = response && response.success
						? message('reorderSaved', 'Menu order saved.')
						: message('reorderError', 'The menu order could not be saved.');
				}
			},
			'json'
		).fail(function () {
			if (status) {
				status.textContent = message('reorderError', 'The menu order could not be saved.');
			}
		});
	}

	function initSortable() {
		var $ = window.jQuery;

		if (!$ || !$.fn || !$.fn.sortable) {
			return;
		}

		if (!document.body || !document.body.classList.contains('post-type-wfo_item')) {
			return;
		}

		$(function () {
			var $list = $('#the-list');

			if (!$list.length || $list.find('tr').length < 2) {
				return;
			}

			$list.sortable({
				items: 'tr',
				handle: '.wfo-drag',
				axis: 'y',
				cursor: 'move',
				forcePlaceholderSize: true,
				placeholder: 'wfo-sort-placeholder',
				update: function () {
					var ids = [];

					$list.find('tr').each(function (position) {
						var id = parseInt(String(this.id).replace('post-', ''), 10);

						if (!isNaN(id)) {
							ids.push(id);
						}

						$(this).toggleClass('alternate', position % 2 === 1);
						$(this).find('.wfo-order__num').text(position);
					});

					saveOrder(ids);
				}
			});

			$list.addClass('wfo-sortable');
		});
	}

	function initColorPickers() {
		var $ = window.jQuery;

		if (!$ || !$.fn || !$.fn.wpColorPicker) {
			return;
		}

		$('.wfo-color-field').each(function () {
			var $field = $(this);

			if ($field.data('wfo-color-picker')) {
				return;
			}

			$field.wpColorPicker();
			$field.data('wfo-color-picker', true);
		});
	}

	function boot() {
		if (booted) {
			return;
		}

		booted = true;

		initColorPickers();
		initSortable();

		var groups = document.querySelectorAll('[data-wfo-group]');

		for (var i = 0; i < groups.length; i += 1) {
			syncType(groups[i]);
		}
	}

	document.addEventListener('click', function (event) {
		var target = event.target;
		var button;
		var builder;
		var group;
		var row;

		button = closest(target, '[data-wfo-add-group]');

		if (button) {
			event.preventDefault();
			builder = builderFrom(button);

			if (builder) {
				addGroup(builder);
			}
			return;
		}

		button = closest(target, '[data-wfo-remove-group]');

		if (button) {
			event.preventDefault();
			group = groupFrom(button);

			if (group && group.parentNode) {
				builder = builderFrom(group);
				group.parentNode.removeChild(group);

				if (builder) {
					refreshEmpty(builder);
				}
			}
			return;
		}

		button = closest(target, '[data-wfo-add-option]');

		if (button) {
			event.preventDefault();
			group = groupFrom(button);

			if (group) {
				addOption(group);
			}
			return;
		}

		button = closest(target, '[data-wfo-remove-option]');

		if (button) {
			event.preventDefault();
			row = closest(button, '[data-wfo-option]');

			if (row && row.parentNode) {
				row.parentNode.removeChild(row);
			}
			return;
		}

		button = closest(target, '[data-wfo-add-badge]');

		if (button) {
			event.preventDefault();
			addBadge(closest(button, '.wfo-field--block') || document);
			return;
		}

		button = closest(target, '[data-wfo-remove-badge]');

		if (button) {
			event.preventDefault();
			row = closest(button, '[data-wfo-badge]');

			if (row && row.parentNode) {
				row.parentNode.removeChild(row);
			}
		}
	});

	document.addEventListener('change', function (event) {
		var type = closest(event.target, '[data-wfo-type]');

		if (type) {
			syncType(groupFrom(type));
			return;
		}

		var preset = closest(event.target, '[data-wfo-preset]');

		if (preset) {
			insertPreset(preset);
		}
	});

	ready(boot);
}());
