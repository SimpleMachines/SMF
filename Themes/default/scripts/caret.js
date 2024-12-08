((root, factory) => {
	if (typeof define === 'function' && define.amd) {
		define([], () => (root.returnExportsGlobal = factory()));
	} else if (typeof exports === 'object') {
		module.exports = factory();
	} else {
		root.caret = factory();
	}
})(this, () => {
	"use strict";

	class Caret {
		constructor(opt) {
			if (opt.iframe) {
				this.window = opt.iframe.contentWindow;
				this.document = opt.iframe.contentDocument || this.window.document;
				this.iframe = opt.iframe;
			} else {
				this.document = opt.inputor.ownerDocument;
				this.window = this.document.defaultView || this.document.parentWindow;
				try {
					this.iframe = this.window.frameElement;
				} catch (error) {
					console.error("iframe auto-discovery failed. Set target iframe manually.");
				}
			}

			this.inputor = opt.inputor;
		}
	}

	class EditableCaret extends Caret {
		setPos(pos) {
			const sel = this.window.getSelection();
			if (sel) {
				let offset = 0, found = false;

				const findPosition = (pos, parent) => {
					for (const node of parent.childNodes) {
						if (found) break;

						if (node.nodeType === 3) {
							if (offset + node.length >= pos) {
								found = true;
								const range = this.document.createRange();
								range.setStart(node, pos - offset);
								sel.removeAllRanges();
								sel.addRange(range);
								break;
							} else {
								offset += node.length;
							}
						} else {
							findPosition(pos, node);
						}
					}
				};

				findPosition(pos, this.inputor);
			}
			return this.inputor;
		}

		getPosition() {
			const offset = this.getOffset();
			const inputorOffset = this.inputor.getBoundingClientRect();
			return {
				left: offset.left - inputorOffset.left,
				top: offset.top - inputorOffset.top
			};
		}

		getPos() {
			const range = this.range();
			if (range) {
				const clonedRange = range.cloneRange();
				clonedRange.selectNodeContents(this.inputor);
				clonedRange.setEnd(range.endContainer, range.endOffset);
				return clonedRange.toString().length;
			}
		}

		getOffset() {
			const range = this.range();
			if (range) {
				let offset;
				if (range.endOffset - 1 > 0 && range.endContainer !== this.inputor) {
					const clonedRange = range.cloneRange();
					clonedRange.setStart(range.endContainer, range.endOffset - 1);
					clonedRange.setEnd(range.endContainer, range.endOffset);
					const rect = clonedRange.getBoundingClientRect();
					offset = {
						height: rect.height,
						left: rect.left + rect.width,
						top: rect.top
					};
				}

				// Get offset from beginning of line
				if (!offset || offset.height === 0) {
					const clonedRange = range.cloneRange();
					const shadowCaret = this.document.createTextNode("\u200D");
					clonedRange.insertNode(shadowCaret);
					const rect = clonedRange.getBoundingClientRect();
					offset = {
						height: rect.height,
						left: rect.left,
						top: rect.top
					};
					shadowCaret.remove();
				}

				return {
					left: offset.left + this.window.scrollX,
					top: offset.top + this.window.scrollY,
					height: offset.height
				};
			}
		}

		range() {
			const sel = this.window.getSelection();
			return sel && sel.rangeCount > 0 ? sel.getRangeAt(0) : null;
		}
	}

	class InputCaret extends Caret {
		getPos() {
			return this.inputor.selectionStart;
		}

		setPos(pos) {
			this.inputor.setSelectionRange(pos, pos);
			return this.inputor;
		}

		getOffset(pos) {
			const offset = this.inputor.getBoundingClientRect();
			const position = this.getPosition(pos);

			return {
				left: offset.left + this.window.scrollX + position.left - this.inputor.scrollLeft,
				top: offset.top + this.window.scrollY + position.top - this.inputor.scrollTop,
				height: position.height
			};
		}

		getPosition(pos = this.getPos()) {
			const format = value => value
				.replace(/[<>`"&]/g, '?')
				.replace(/\r?\n/g, "<br/>")
				.replace(/\s/g, '&nbsp;');

			const html = `
				<span style="position: relative; display: inline;">${format(this.inputor.value.slice(0, pos))}</span>
				<span id="caret-position-marker" style="position: relative; display: inline;">\u200D</span>
				<span style="position: relative; display: inline;">${format(this.inputor.value.slice(pos))}</span>
			`;

			return this.createMirror(html).rect();
		}

		createMirror(html) {
			const attributes = [
				'borderBottomWidth', 'borderLeftWidth', 'borderRightWidth', 'borderTopStyle',
				'borderRightStyle', 'borderBottomStyle', 'borderLeftStyle', 'borderTopWidth',
				'boxSizing', 'fontFamily', 'fontSize', 'fontWeight', 'height', 'letterSpacing',
				'lineHeight', 'marginBottom', 'marginLeft', 'marginRight', 'marginTop',
				'outlineWidth', 'overflow', 'overflowX', 'overflowY', 'paddingBottom',
				'paddingLeft', 'paddingRight', 'paddingTop', 'textAlign', 'textOverflow',
				'textTransform', 'wordBreak', 'wordWrap',
			];

			const css = {
				position: 'absolute',
				left: '-9999px',
				top: '0',
				zIndex: '-2000',
			};

			if (this.inputor.tagName === 'TEXTAREA') {
				attributes.push('width');
			}

			attributes.forEach(attr => {
				css[attr] = getComputedStyle(this.inputor)[attr];
			});

			const mirror = this.document.createElement('div');
			Object.assign(mirror.style, css);
			mirror.innerHTML = html;
			this.document.body.append(mirror);

			return {
				rect: () => {
					const marker = mirror.ownerDocument.getElementById('caret-position-marker');
					const boundingRect = {
						left: marker.offsetLeft,
						top: marker.offsetTop,
						height: marker.offsetHeight
					};
					mirror.remove();

					return boundingRect;
				}
			};
		}
	}

	const methods = {
		pos(pos) {
			return pos !== undefined ? this.setPos(pos) : this.getPos();
		},
		position(pos) {
			return this.getPosition(pos);
		},
		offset(pos) {
			return this.getOffset(pos);
		}
	};

	return (inputor, method, value, opt = {}) => {
		if (typeof value == 'object') {
			obj = value;
		}
		opt.inputor = inputor;
		const isContentEditable = inputor => !!(inputor.contentEditable && inputor.contentEditable === 'true');
		const caret = isContentEditable(inputor) ? new EditableCaret(opt) : new InputCaret(opt);
		if (methods[method]) {
			return methods[method].apply(caret, [value]);
		} else {
			throw new Error(`Method ${method} does not exist`);
		}
	};
});