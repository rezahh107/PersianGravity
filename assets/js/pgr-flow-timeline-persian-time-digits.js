( function ( factory ) {
	'use strict';

	const adapter = factory();

	if ( typeof module === 'object' && module.exports ) {
		module.exports = adapter;
		return;
	}

	adapter.run( document );
}( function () {
	'use strict';

	const HEADER_SELECTOR = [
		'.gravityflow-timeline > .inside > .gravityflow-note > .gravityflow-note-body-wrap > .gravityflow-note-body > .gravityflow-note-header > .gravityflow-note-meta',
		'#view-container > .gravityflow-note > .gravityflow-note-body-wrap > .gravityflow-note-body > .gravityflow-note-header > .gravityflow-note-meta',
	].join( ', ' );
	const EXCLUDED_ANCESTORS = 'script, style, textarea, select, option, template, noscript, [hidden], [aria-hidden="true"], [contenteditable="true"]';
	const TIME_TOKEN = /(?:^|[^0-9])((?:[01]?[0-9]|2[0-3]):[0-5][0-9])(?=$|[^0-9])/g;
	const PERSIAN_DIGITS = '۰۱۲۳۴۵۶۷۸۹';

	/**
	 * Shape ASCII digit glyphs in one already-qualified time token.
	 *
	 * @param {string} value ASCII time token.
	 * @return {string} Token with Persian digit glyphs.
	 */
	function shapeAsciiDigits( value ) {
		return value.replace( /[0-9]/g, function ( digit ) {
			return PERSIAN_DIGITS.charAt( Number( digit ) );
		} );
	}

	/**
	 * Shape only bounded ASCII clock tokens; arbitrary numbers remain native.
	 *
	 * @param {string} value Human-visible Timeline header text.
	 * @return {string} Header text with Persian time glyphs only.
	 */
	function shapeTimeTokens( value ) {
		if ( typeof value !== 'string' ) {
			return value;
		}

		return value.replace( TIME_TOKEN, function ( match, timeToken ) {
			return match.replace( timeToken, shapeAsciiDigits( timeToken ) );
		} );
	}

	/**
	 * Confirm a header text node is human-visible rather than hidden/editable.
	 *
	 * @param {Text} node Candidate text node.
	 * @return {boolean} Whether the node is eligible for glyph shaping.
	 */
	function isVisibleTextNode( node ) {
		const parent = node && node.parentElement;
		if ( ! parent || typeof parent.closest !== 'function' || parent.closest( EXCLUDED_ANCESTORS ) ) {
			return false;
		}

		const view = parent.ownerDocument && parent.ownerDocument.defaultView;
		if ( view && typeof view.getComputedStyle === 'function' ) {
			const style = view.getComputedStyle( parent );
			if ( style.display === 'none' || style.visibility === 'hidden' || style.visibility === 'collapse' ) {
				return false;
			}
		}

		return typeof parent.getClientRects !== 'function' || parent.getClientRects().length > 0;
	}

	/**
	 * Shape only visible text nodes within one exact Timeline meta element.
	 *
	 * @param {Document} doc Browser document.
	 * @param {Element} header Exact Gravity Flow Timeline meta element.
	 * @return {number} Number of changed text nodes.
	 */
	function shapeHeaderTextNodes( doc, header ) {
		const showText = doc.defaultView && doc.defaultView.NodeFilter
			? doc.defaultView.NodeFilter.SHOW_TEXT
			: 4;
		const walker = doc.createTreeWalker( header, showText );
		let changed = 0;
		let node = walker.nextNode();

		while ( node ) {
			if ( typeof node.nodeValue === 'string' && /[0-9]/.test( node.nodeValue ) && isVisibleTextNode( node ) ) {
				const shaped = shapeTimeTokens( node.nodeValue );
				if ( shaped !== node.nodeValue ) {
					node.nodeValue = shaped;
					changed += 1;
				}
			}

			node = walker.nextNode();
		}

		return changed;
	}

	/**
	 * Apply shaping only to exact Entry Detail/Print Timeline header meta nodes.
	 *
	 * Timeline bodies, attributes, links and controls are never traversed.
	 *
	 * @param {Document} doc Browser document.
	 * @return {number} Number of changed text nodes.
	 */
	function run( doc ) {
		if ( ! doc || typeof doc.querySelectorAll !== 'function' ) {
			return 0;
		}

		const headers = doc.querySelectorAll( HEADER_SELECTOR );
		let changed = 0;

		headers.forEach( function ( header ) {
			changed += shapeHeaderTextNodes( doc, header );
		} );

		return changed;
	}

	return {
		HEADER_SELECTOR,
		shapeTimeTokens,
		run,
	};
} ) );
