((root, factory) => {
	if (typeof define === 'function' && define.amd) {
		define([], () => (root.returnExportsGlobal = factory()));
	} else if (typeof exports === 'object') {
		module.exports = factory();
	} else {
		root.atwho = factory();
	}
})(this, () => {
	"use strict";

	const KEY_CODE = {
		ESC: 27,
		TAB: 9,
		ENTER: 13,
		CTRL: 17,
		A: 65,
		P: 80,
		N: 78,
		LEFT: 37,
		UP: 38,
		RIGHT: 39,
		DOWN: 40,
		BACKSPACE: 8,
		SPACE: 32
	};
	/**
	 * Default callback functions used for various operations.
	 */
	const DEFAULT_CALLBACKS = {
		/**
		 * Processes data before saving.
		 *
		 * @function beforeSave
		 * @param {Array} data - The data to be saved.
		 * @returns {Object} Processed data in hash format.
		 */
		beforeSave: (data) => Controller.arrayToDefaultHash(data),

		/**
		 * Matches the given subtext against a regex pattern.
		 *
		 * @function matcher
		 * @param {string} flag - The flag to create a regex pattern.
		 * @param {string} subtext - The text to search within.
		 * @param {boolean} shouldStartWithSpace - Whether the flag should be matched with a leading space.
		 * @param {boolean} acceptSpaceBar - Whether the space bar is accepted in the match.
		 * @returns {string|null} Matched text or null if no match is found.
		 */
		matcher: (flag, subtext, shouldStartWithSpace, acceptSpaceBar) => {
			// Escape any special regex characters
			const escapedFlag = flag.replace(/[\-\[\]\/\{\}\(\)\*\+\?\.\\\^\$\|]/g, "\\$&");

			const startPattern = shouldStartWithSpace ? '(?:^|\\s)' : "";
			const spaceChar = acceptSpaceBar ? '1F' : '20';

			const regex = new RegExp(
				`${startPattern}${escapedFlag}([^\\x00-\\x${spaceChar}\\x80-\\x9F]+)$`,
				'gi'
			);

			const match = regex.exec(subtext);
			return match && match[1] !== ' ' ? match[1] : null;
		},

		/**
		 * Filters an array of data based on a search query.
		 *
		 * @function filter
		 * @param {string} query - The search query.
		 * @param {Array} items - The array of items to be filtered.
		 * @param {string} searchKey - The key used to search within each data object.
		 * @returns {Array} Filtered array of data that matches the query.
		 */
		filter: (query, items, searchKey) => {
			if (!query || !items.length) {
				return items;
			}

			// Do it the old fashioned way to minimize expensive memory alloocations.
			const lowerQuery = query.toLowerCase();
			const filteredItems = new Array(items.length);
			for (let i = 0, len = items.length; i < len; i++) {
				if (items[i][searchKey].toLowerCase().indexOf(lowerQuery) > -1) {
					filteredItems[i] = items[i];
				}
			}

			// Remove holes
			return filteredItems.filter(() => true);
		},

		/**
		 * Function to apply remote filtering (not implemented).
		 *
		 * @function remoteFilter
		 */
		remoteFilter: null,

		/**
		 * Sorts an array of items based on a search query.
		 *
		 * @function sorter
		 * @param {string} query - The search query.
		 * @param {Array} items - The array of items to be sorted.
		 * @param {string} searchKey - The key used to search within each item.
		 * @returns {Array} Sorted array of items based on query relevance.
		 */
		sorter: (query, items, searchKey) => {
			if (!query) {
				return items;
			}

			const lowerQuery = query.toLowerCase();
			const filteredItems = new Array(items.length);

			for (let i = 0, len = items.length; i < len; i++) {
				let item = items[i];
				item.atwho_order = item[searchKey].toLowerCase().indexOf(lowerQuery);
				if (item.atwho_order > -1) {
					filteredItems[i] = item;
				}
			}

			return filteredItems.sort((a, b) => a.atwho_order - b.atwho_order);
		},

		/**
		 * Evaluates a template string by replacing placeholders with map values.
		 *
		 * @function tplEval
		 * @param {string|Function} tpl - The template string or a function returning a template.
		 * @param {Object} map - The key-value pairs to replace in the template.
		 * @returns {string} The evaluated template string with values filled in.
		 */
		tplEval: (tpl, map) => {
			try {
				const template = typeof tpl === 'string' ? tpl : tpl(map);
				return template.replace(/\$\{([^\}]*)\}/g, (_, key) => map[key]);
			} catch (error) {
				return "";
			}
		},

		/**
		 * Highlights a query in a list item string.
		 *
		 * @function highlighter
		 * @param {string} li - The list item HTML string.
		 * @param {string} query - The query to highlight.
		 * @returns {string} The modified list item with highlighted query.
		 */
		highlighter(strReplace, strWith) {
			var esc = strWith.replace(/[-\/\\^$*+?.()|[\]{}]/g, '\\$&');
			var reg = new RegExp(esc, 'ig');
			return strReplace.replace(reg, '<b>$&</b>');
		},

		/**
		 * Processes a value before insertion.
		 *
		 * @function beforeInsert
		 * @param {string} value - The value to be inserted.
		 * @returns {string} The processed value.
		 */
		beforeInsert: (value) => value,

		/**
		 * Adjusts the offset before repositioning an element.
		 *
		 * @function beforeReposition
		 * @param {Object} offset - The current offset.
		 * @returns {Object} The modified offset.
		 */
		beforeReposition: (offset) => offset,

		/**
		 * Callback function after a match failure.
		 *
		 * @function afterMatchFailed
		 */
		afterMatchFailed: () => {}
	};

	const DEFAULT_SETTINGS = {
		/**
		 * Character that triggers the observation (e.g., `@`).
		 * @type {string|undefined}
		 */
		at: void 0,

		/**
		 * Alias name for the `at` trigger.
		 * This will also serve as the `id` attribute for the popup view.
		 * @type {string|undefined}
		 */
		alias: void 0,

		/**
		 * Data source, which can be:
		 * - An array of items.
		 * - A URL to load JSON data remotely.
		 * If it's an array, it will be used directly. If it's
		 * a URL, At.js will fetch and load the data.
		 * @type {Array|URL|null}
		 */
		data: null,

		/**
		 * HTML template to render at the top of the dropdown popup.
		 * This is commonly used for showing instructions or headings.
		 * @type {string}
		 */
		headerTpl: "",

		/**
		 * Template for rendering each item in the dropdown.
		 * You can use `${}` to interpolate values from the data object,
		 * e.g., `${name}` for the item's name.  Alternatively, this can
		 * be a function that accepts a data item and returns HTML.
		 * @type {string|function}
		 */
		displayTpl: "${name}",

		/**
		 * Template for inserting the selected item into the input field.
		 * `${atwho-at}` represents the trigger character (`@` by default)
		 * and `${name}` is the selected item's name.
		 * @type {string}
		 */
		insertTpl: "${atwho-at}${name}",

		/**
		 * Callback functions for customizing data processing (e.g.,
		 * filtering results).  The default set of callbacks
		 * (`DEFAULT_CALLBACKS`) can be overridden as needed.
		 * @type {Object}
		 */
		callbacks: DEFAULT_CALLBACKS,

		/**
		 * The key in the data object that will be searched and matched
		 * against the user's query.  For example, if set to "name",
		 * At.js will match items based on the "name" field.
		 * @type {string}
		 */
		searchKey: "name",

		/**
		 * Maximum number of items to display in the dropdown list.
		 * @type {number}
		 */
		limit: 5,

		/**
		 * Minimum length of the query string after the trigger character
		 * (`at`).  Once the query exceeds this length, matching will stop.
		 * @type {number}
		 */
		minLen: 0,

		/**
		 * Maximum length of the query string after the trigger character
		 * (`at`).  Once the query exceeds this length, matching will stop.
		 * @type {number}
		 */
		maxLen: 20,

		/**
		 * If set to `true`, the `at` trigger must be preceded by a space in the input field.
		 * @type {boolean}
		 */
		startWithSpace: true,

		/**
		 * Time in milliseconds to keep the popup open after losing focus from the input field.
		 * @type {number}
		 */
		displayTimeout: 300,

		/**
		 * If `true`, the first suggestion in the dropdown will be automatically highlighted.
		 * @type {boolean}
		 */
		highlightFirst: true,

		/**
		 * Delay time in milliseconds before triggering At.js while typing.
		 * For example: `delay: 400`.
		 * @type {number|null}
		 */
		delay: null,

		/**
		 * String to append after inserting a matched item.
		 * @type {string|undefined}
		 */
		suffix: undefined,

		/**
		 * If set to `true`, the dropdown will not show unless the user types the suffix.
		 * @type {boolean}
		 */
		lookUpOnClick: true,

		/**
		 * If set to `true`, the dropdown will not show unless the user types the suffix.
		 * @type {boolean}
		 */
		hideWithoutSuffix: false
	};

	class App {
		constructor(inputor) {
			this.currentFlag = null;
			this.controllers = {};
			this.aliasMaps = {};
			this.inputor = typeof inputor !== 'string' ? inputor : document.querySelector(inputor);
			this.setupRootElement();
			this.listen();
		}

		setupRootElement(iframe = null, asRoot = false) {
			if (iframe) {
				this.window = iframe.contentWindow;
				this.document = iframe.contentDocument || this.window.document;
				this.iframe = iframe;
			} else {
				this.document = this.inputor.ownerDocument;
				this.window = this.document.defaultView || this.document.parentWindow;
				try {
					this.iframe = this.window.frameElement;
				} catch (error) {
					console.error("iframe auto-discovery failed. Set target iframe manually.");
				}
			}

			// Create or reuse containing element
			this.el = document.querySelector('.atwho-container');
			if (!this.el) {
				this.el = document.createElement('div');
				this.el.classList.add('atwho-container');
				this.document.body.appendChild(this.el);
			}
		}

		controller(at) {
			let current;
			if (this.aliasMaps[at]) {
				current = this.controllers[this.aliasMaps[at]];
			} else {
				for (let currentFlag in this.controllers) {
					if (currentFlag === at) {
						current = this.controllers[currentFlag];
						break;
					}
				}
			}
			return current || this.controllers[this.currentFlag];
		}

		setContextFor(at) {
			this.currentFlag = at;
			return this;
		}

		reg(flag, setting) {
			let controller = this.controllers[flag] ||
				 (this.inputor.isContentEditable ? new EditableController(this, flag) : new TextareaController(this, flag));

			if (setting.alias) {
				this.aliasMaps[setting.alias] = flag;
			}
			controller.init(setting);
			this.controllers[flag] = controller;

			return this;
		}

		listen() {
			this.inputor.addEventListener('compositionstart', function(e) {
				let controller = this.controller();
				if (controller) controller.view.hide();
				this.isComposing = true;
			}.bind(this));

			this.inputor.addEventListener('compositionend', function(e) {
				this.isComposing = false;
				setTimeout(() => this.dispatch(e));
			}.bind(this));

			this.inputor.addEventListener('keyup', this.onKeyup.bind(this));
			this.inputor.addEventListener('keydown', this.onKeydown.bind(this));
			this.inputor.addEventListener('blur', function(e) {
				let controller = this.controller();
				if (controller) {
					controller.expectedQueryCBId = null;
					controller.view.hide(e, controller.getOpt("displayTimeout"));
				}
			}.bind(this));

			this.inputor.addEventListener('click', this.dispatch.bind(this));
			this.inputor.addEventListener('scroll', function() {
				let lastScrollTop = this.inputor.scrollTop;
				return e => {
					let currentScrollTop = e.target.scrollTop;
					if (lastScrollTop !== currentScrollTop) {
						let controller = this.controller();
						if (controller) controller.view.hide(e);
					}
					lastScrollTop = currentScrollTop;
				};
			}.bind(this));
		}

		shutdown() {
			Object.values(this.controllers).forEach(controller => {
				controller.destroy();
				delete this.controllers[controller.flag];
			});
			this.inputor.removeEventListener('.atwhoInner');
			this.el.remove();
		}

		dispatch(e) {
			if (e === undefined) return;
			return Object.values(this.controllers).map(controller => controller.lookUp(e));
		}

		onKeyup(e) {
			switch (e.keyCode) {
				case KEY_CODE.ESC:
				case KEY_CODE.DOWN:
				case KEY_CODE.UP:
				case KEY_CODE.CTRL:
				case KEY_CODE.ENTER:
					break;
				case KEY_CODE.P:
				case KEY_CODE.N:
					if (!e.ctrlKey) this.dispatch(e);
					break;
				default:
					this.dispatch(e);
			}
		}

		onKeydown(e) {
			let view = this.controller()?.view;
			if (!view?.visible()) return;

			switch (e.keyCode) {
				case KEY_CODE.ESC:
					view.hide(e);
					break;
				case KEY_CODE.UP:
					view.prev();
					break;
				case KEY_CODE.DOWN:
					view.next();
					break;
				case KEY_CODE.P:
					if (!e.ctrlKey) return;
					view.prev();
					break;
				case KEY_CODE.N:
					if (!e.ctrlKey) return;
					view.next();
					break;
				case KEY_CODE.TAB:
				case KEY_CODE.ENTER:
				case KEY_CODE.SPACE:
					if (!view.visible()) {
						return;
					}
					if (!this.controller().getOpt('spaceSelectsMatch') && e.keyCode === KEY_CODE.SPACE) {
						return;
					}
					if (!this.controller().getOpt('tabSelectsMatch') && e.keyCode === KEY_CODE.TAB) {
						return;
					}
					if (view.highlighted()) {
						view.choose(e);
					} else {
						view.hide(e);
					}
					break;
				default:
					return;
			}

			// prevent a few navigation keys from
			// working when the popup is in view
			e.preventDefault();
			e.stopPropagation();
		}
	}

	class Controller {
		uid() {
			return (Math.random().toString(16) + "000000000").substr(2, 8) + (new Date().getTime());
		}

		constructor(app, at) {
			this.app = app;
			this.at = at;
			this.inputor = this.app.inputor;
			this.id = this.inputor.id || this.uid();
			this.expectedQueryCBId = null;
			this.setting = null;
			this.query = null;
			this.pos = 0;
			this.range = null;

			// Create or reuse ground element
			this.el = document.querySelector(`#atwho-ground-${this.id}`);
			if (!this.el) {
				this.el = document.createElement('div');
				this.el.id = `atwho-ground-${this.id}`;
				this.app.el.appendChild(this.el);
			}

			this.model = new Model(this);
			this.view = new View(this);
		}

		init(setting) {
			this.setting = Object.assign({}, this.setting || DEFAULT_SETTINGS, setting);
			this.view.init();
			return this.model.reload(this.setting.data);
		}

		destroy() {
			this.trigger('beforeDestroy');
			this.model.destroy();
			this.view.destroy();
			return this.el.remove();
		}

		callDefault(funcName, ...args) {
			try {
				return DEFAULT_CALLBACKS[funcName].apply(this, args);
			} catch (error) {
				console.error(`Error: ${error}. Maybe At.js doesn't have the function ${funcName}`);
			}
		}

		trigger(name, data = []) {
			data.push(this);
			const alias = this.getOpt('alias');
			const eventName = alias ? `${name}-${alias}.atwho` : `${name}.atwho`;
			const event = new CustomEvent(eventName, { detail: data });
			this.inputor.dispatchEvent(event);
		}

		callbacks(funcName) {
			return this.getOpt('callbacks')[funcName] || DEFAULT_CALLBACKS[funcName];
		}

		getOpt(at, default_value) {
			try {
				return this.setting[at];
			} catch (e) {
				return null;
			}
		}

		insertContentFor(li) {
			const searchKey = this.getOpt("searchKey");
			let data = { 'atwho-at': this.at };
			data[searchKey] = li.dataset.itemData;
			const tpl = this.getOpt('insertTpl');
			return this.callbacks('tplEval').call(this, tpl, data, 'onInsert');
		}

		renderView(data) {
			const searchKey = this.getOpt('searchKey');
			const sortedData = this.callbacks('sorter').call(this, this.query.text, data, searchKey);

			return this.view.render(sortedData);
		}

		static arrayToDefaultHash(items) {
			if (!Array.isArray(items)) return items;

			const results = new Array(items.length);

			for (let i = 0, len = items.length; i < len; i++) {
				if (typeof items[i] === 'string') {
					results[i] = { name: items[i] };
				} else {
					results[i] = items[i];
				}
			}

			return results;
		}

		lookUp(e) {
			if (e && e.type === 'click' && !this.getOpt('lookUpOnClick')) return;
			if (this.getOpt('suspendOnComposing') && this.app.isComposing) return;

			const query = this.catchQuery(e);
			if (!query) {
				this.expectedQueryCBId = null;
				return query;
			}

			this.app.setContextFor(this.at);
			const wait = this.getOpt('delay');
			if (wait) {
				this._delayLookUp(query, wait);
			} else {
				this._lookUp(query);
			}
			return query;
		}

		_delayLookUp(query, wait) {
			const now = Date.now ? Date.now() : new Date().getTime();
			this.previousCallTime = this.previousCallTime || now;
			const remaining = wait - (now - this.previousCallTime);

			if (remaining > 0 && remaining < wait) {
				this.previousCallTime = now;
				this._stopDelayedCall();
				this.delayedCallTimeout = setTimeout(() => {
					this.previousCallTime = 0;
					this.delayedCallTimeout = null;
					this._lookUp(query);
				}, wait);
			} else {
				this._stopDelayedCall();
				if (this.previousCallTime !== now) this.previousCallTime = 0;
				this._lookUp(query);
			}
		}

		_stopDelayedCall() {
			if (this.delayedCallTimeout) {
				clearTimeout(this.delayedCallTimeout);
				this.delayedCallTimeout = null;
			}
		}

		_generateQueryCBId() {
			return {};
		}

		_lookUp(query) {
			const queryCBId = this._generateQueryCBId();
			this.expectedQueryCBId = queryCBId;
			this.model.query(query.text, (data) => {
				if (queryCBId !== this.expectedQueryCBId) return;
				if (data && data.length > 0) {
					this.renderView(Controller.arrayToDefaultHash(data));
				} else {
					this.view.hide();
				}
			});
		}
	}

	class TextareaController extends Controller {
		catchQuery() {
			const content = this.inputor.value;
			const caretPos = this.inputor.selectionStart;
			const subtext = content.slice(0, caretPos);
			const query = this.callbacks('matcher').call(this, this.at, subtext, this.getOpt('startWithSpace'), this.getOpt('acceptSpaceBar'));

			if (typeof query === 'string' && query.length >= this.getOpt('minLen', 0) && query.length <= this.getOpt('maxLen', 20)) {
				const start = caretPos - query.length;
				const end = start + query.length;
				this.pos = start;
				this.query = { text: query, headPos: start, endPos: end };
				this.trigger('matched', [this.at, this.query.text]);
			} else {
				this.query = null;
				this.view.hide();
			}

			return this.query;
		}

		rect() {
			let caretOffset, iframeOffset, scaleBottom;
			caretOffset = caret(this.inputor, 'offset', this.pos - 1, {
					iframe: this.app.iframe
			});
			if (!caretOffset) return;

			if (this.app.iframe && !this.app.iframeAsRoot) {
				iframeOffset = this.app.iframe.getBoundingClientRect();
				caretOffset.left += iframeOffset.left;
				caretOffset.top += iframeOffset.top;
			}

			// If the document is not in selection mode, add scaleBottom for padding
			scaleBottom = this.app.document.selection ? 0 : 2;

			return {
				left: caretOffset.left,
				top: caretOffset.top,
				bottom: caretOffset.top + caretOffset.height + scaleBottom
			};
		}

		insert(content, li) {
			const source = this.inputor.value;
			const startStr = source.slice(0, Math.max(this.query.headPos - this.at.length, 0));
			const suffix = this.getOpt('suffix') || ' ';
			const newContent = startStr + content + suffix + source.slice(this.query.endPos);
			this.inputor.value = newContent;

			const newPos = startStr.length + content.length;
			this.inputor.setSelectionRange(newPos, newPos);
			this.inputor.focus();
			this.inputor.dispatchEvent(new Event('input'));
		}
	}

	class EditableController extends Controller {
		catchQuery(e) {
			const range = this.app.document.getSelection()?.getRangeAt(0);
			if (!range || !range.collapsed) return;

			const clonedRange = range.cloneRange();
			clonedRange.setStart(range.startContainer, 0);
			const matched = this.callbacks("matcher").call(
				this, this.at, clonedRange.toString(), 
				this.getOpt('startWithSpace'), this.getOpt("acceptSpaceBar")
			);

			if (typeof matched === 'string' && matched.length >= this.getOpt('minLen', 0) && matched.length <= this.getOpt('maxLen', 20)) {
				const index = range.startOffset - this.at.length - matched.length;

				if (index >= 0) {
					this.trigger("matched", [this.at, matched]);
					this.query = { text: matched, range, index };
					return this.query;
				}
			}

			this.view.hide();
		}

		rect() {
			let caretOffset = caret(this.inputor, 'offset', this.pos, {
				iframe: this.app.iframe
			});
			if (!caretOffset) return;

			if (this.app.iframe && !this.app.iframeAsRoot) {
				const iframeOffset = this.app.iframe.getBoundingClientRect();
				caretOffset.left += iframeOffset.left - this.app.window.scrollX + window.scrollX;
				caretOffset.top += iframeOffset.top - this.app.window.scrollY + window.scrollY;
			}

			return {
				left: caretOffset.left,
				top: caretOffset.top,
			};
		}

		insert(content, li) {
			const range = this.query.range;
			range.setStart(range.startContainer, this.query.index);
			range.deleteContents();

			// Insert the text node at the caret's current position
			const textNode = this.app.document.createTextNode(content);
			range.insertNode(textNode);

			// Adjust the range to select the new text node's contents
			range.selectNodeContents(textNode);

			// Collapse the range to place the caret at the end of the inserted text
			range.collapse(false);

			// Clear all existing selections
			const selection = this.app.window.getSelection();
			selection.removeAllRanges();

			// Apply the updated range as the new selection
			selection.addRange(range);

			this.inputor.focus();

			return this.inputor.dispatchEvent(new Event('change'));
		}
	}

	class Model {
		constructor(context) {
			this.context = context;
			this.at = context.at;
			this.storage = context.inputor;
		}

		destroy() {
			this.storage[this.at] = null;
		}

		saved() {
			return this.fetch().length > 0;
		}

		query(query, callback) {
			let data = this.fetch();
			const searchKey = this.context.getOpt("searchKey");
			data = this.context.callbacks('filter').call(this.context, query, data, searchKey) || [];

			const remoteFilter = this.context.callbacks('remoteFilter');
			if (data.length || !remoteFilter) {
				callback(data);
			} else {
				remoteFilter.call(this.context, query, callback);
			}
		}

		fetch() {
			return this.storage[this.at] || [];
		}

		save(data) {
			this.storage[this.at] = this.context.callbacks('beforeSave').call(this.context, data || []);
		}

		load(data) {
			if (!this.saved() && data) {
				this._load(data);
			}
		}

		reload(data) {
			this._load(data);
		}

		_load(data) {
			if (typeof data === 'string') {
				fetch(data)
					.then(response => response.json())
					.then(fetchedData => this.save(fetchedData));
			} else {
				this.save(data);
			}
		}
	}

	class View {
		constructor(context) {
			this.context = context;
			this.el = document.createElement('div');
			this.el.classList.add('atwho-view');
			this.elUl = document.createElement('ul');
			this.el.appendChild(this.elUl);
			this.timeoutID = null;
			this.context.el.appendChild(this.el);
			this.bindEvent();
		}

		init() {
			const id = this.context.getOpt("alias") || this.context.at.charCodeAt(0);
			const headerTpl = this.context.getOpt("headerTpl");

			if (headerTpl && this.el.children.length === 1) {
				this.el.insertAdjacentHTML('afterbegin', headerTpl);
			}
			this.el.id = `at-view-${id}`;
		}

		destroy() {
			this.el.remove();
		}

		bindEvent() {
			const menu = this.el.querySelector('ul');
			let lastCoordX = 0;
			let lastCoordY = 0;

			menu.addEventListener('mousemove', e => {
				const targetLi = e.target.closest('li');
				if (targetLi) {
					for (const sibling of targetLi.parentNode.children) {
						sibling.classList.remove('cur');
					}
					targetLi.classList.add('cur');
				}
			});

			menu.addEventListener('click', this.choose.bind(this));
		}

		visible() {
			return this.el.offsetWidth > 0 && this.el.offsetHeight > 0;
		}

		highlighted() {
			return this.el.querySelectorAll(".cur").length > 0;
		}

		choose(e) {
			const li = this.el.querySelector(".cur");
			if (li) {
				const content = this.context.insertContentFor(li);
				this.context._stopDelayedCall();
				const beforeInsertCallback = this.context.callbacks("beforeInsert");
				const insertContent = beforeInsertCallback?.call(this.context, content, li, e);
				this.context.insert(insertContent, li);
				this.context.trigger("inserted", [li, e]);
				this.hide(e);
			}

			if (this.context.getOpt("hideWithoutSuffix")) {
				this.stopShowing = true;
			}
		}

		reposition(rect) {
			const _window = this.context.app.iframeAsRoot ? this.context.app.window : window;
			const elHeight = this.el.offsetHeight;

			// Adjust bottom and left positions based on window dimensions
			if (rect.bottom + elHeight - _window.scrollY > _window.innerHeight) {
				rect.bottom = rect.top - elHeight;
			}
			if (rect.left > _window.innerWidth - this.el.offsetWidth - 5) {
				rect.left = _window.innerWidth - this.el.offsetWidth - 5;
			}

			const beforeReposition = this.context.callbacks("beforeReposition");
			beforeReposition?.call(this.context, rect);

			this.el.style.left = `${rect.left}px`;
			this.el.style.top = `${rect.top}px`;
			this.context.trigger("reposition", [rect]);
		}

		next() {
			let cur = this.el.querySelector(".cur");
			cur?.classList.remove("cur");

			let next = cur?.nextElementSibling || this.el.querySelector("li:first-child");
			next.classList.add("cur");

			const offset = next.offsetTop + next.offsetHeight + (next.nextElementSibling?.offsetHeight || 0);
			this.scrollTop(Math.max(0, offset - this.el.offsetHeight));
		}

		prev() {
			let cur = this.el.querySelector(".cur");
			cur?.classList.remove("cur");

			let prev = cur?.previousElementSibling || this.el.querySelector("li:last-child");
			prev.classList.add("cur");

			const offset = prev.offsetTop + prev.offsetHeight + (prev.nextElementSibling?.offsetHeight || 0);
			this.scrollTop(Math.max(0, offset - this.el.offsetHeight));
		}

		scrollTop(scrollTop) {
			const scrollDuration = this.context.getOpt("scrollDuration");
			if (scrollDuration) {
				// Smooth scroll using vanilla JS
				this.elUl.scrollTo({ top: scrollTop, behavior: "smooth" });
			} else {
				this.elUl.scrollTop = scrollTop;
			}
		}

		show() {
			if (this.stopShowing) {
				this.stopShowing = false;
				return;
			}

			if (!this.visible()) {
				this.el.style.display = "block";
				this.el.scrollTop = 0;
				this.context.trigger("shown");
			}

			const rect = this.context.rect();
			if (rect) {
				this.reposition(rect);
			}
		}

		hide(e, time) {
			if (!this.visible()) return;

			if (isNaN(time)) {
				this.el.style.display = "none";
				this.context.trigger("hidden", [e]);
			} else {
				clearTimeout(this.timeoutID);
				this.timeoutID = setTimeout(() => this.hide(), time);
			}
		}

		render(items) {
			if (!Array.isArray(items) || items.length === 0) {
				this.hide();
				return;
			}

			const ul = this.el.querySelector("ul");
			ul.innerHTML = "";
			const limit = this.context.getOpt("limit");
			const tpl = this.context.getOpt("displayTpl");
			const searchKey = this.context.getOpt("searchKey");
			const highlightFirst = this.context.getOpt("highlightFirst");

			for (let i = 0, n = Math.min(limit, items.length); i < n; i++) {
				let item = items[i];
				item["atwho-at"] = this.context.at;
				const li = this.context.callbacks("tplEval")?.call(this.context, tpl, item, "onDisplay");

				const liElement = document.createElement("li");
				liElement.innerHTML = this.context.callbacks("highlighter")?.call(this.context, li, this.context.query.text);
				liElement.dataset.itemData = item[searchKey];
				ul.appendChild(liElement);

				if (highlightFirst && !i) {
					liElement.className = "cur";
				}
			}

			this.show();
		}
	}

	const methods = {
		load(at, data) {
			var c = this.controller(at);
			if (c) {
				return c.model.load(data);
			}
		},
		isSelecting() {
			var ref = this.controller();
			return !!(ref && ref.view.visible());
		},
		hide() {
			var ref = this.controller();
			return ref && ref.view.hide();
		},
		reposition() {
			var c = this.controller();
			if (c) {
				return c.view.reposition(c.rect());
			}
		},
		setIframe(iframe, asRoot) {
			this.setupRootElement(iframe, asRoot);
			return null;
		},
		run() {
			return this.dispatch();
		},
		destroy() {
			this.shutdown();
			return this.inputor.removeAttribute('data-atwho');
		}
	};

	return (element, method, ...args) => {
		let app = element.atwhoApp
		if (!app) {
			element.atwhoApp = new App(element);
			app = element.atwhoApp;
		}
		if (typeof method === 'object' || !method) {
			app.reg(method.at, method);
		} else if (methods[method] && app) {
			return methods[method].apply(app, args);
		} else {
			console.error("Method " + method + " does not exist on atwho");
		}
	};
});