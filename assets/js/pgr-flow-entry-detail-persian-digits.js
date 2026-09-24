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

	const ROOT_SELECTOR = '#gravityflow-status-box-container > #submitcomment > #minor-publishing.gravityflow-status-box';
	const FIELD_SELECTOR = '.gravityflow-status-box-field';
	const EXCLUDED_ANCESTORS = 'script, style, textarea, select, option, template, noscript, [hidden], [aria-hidden="true"]';
	const PERSIAN_DIGITS = '۰۱۲۳۴۵۶۷۸۹';

	/**
	 * Shape ASCII digit glyphs without parsing or changing numeric semantics.
	 *
	 * @param {string} value Human-visible text.
	 * @return {string} Text with Persian digit glyphs.
	 */
	function shapeDigits( value ) {
		return value.replace( /[0-9]/g, function ( digit ) {
			return PERSIAN_DIGITS.charAt( Number( digit ) );
		} );
	}

	/**
	 * Shape only eligible text nodes below one workflow-info field.
	 *
	 * @param {Document} doc Browser document.
	 * @param {Element} field Exact Gravity Flow status-box field.
	 * @return {number} Number of changed text nodes.
	 */
	function shapeFieldTextNodes( doc, field ) {
		const showText = doc.defaultView && doc.defaultView.NodeFilter
			? doc.defaultView.NodeFilter.SHOW_TEXT
			: 4;
		const walker = doc.createTreeWalker( field, showText );
		let changed = 0;
		let node = walker.nextNode();

		while ( node ) {
			const parent = node.parentElement;
			const excluded = parent && typeof parent.closest === 'function'
				? parent.closest( EXCLUDED_ANCESTORS )
				: null;

			if ( ! excluded && typeof node.nodeValue === 'string' ) {
				const shaped = shapeDigits( node.nodeValue );
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
	 * Apply presentation shaping only when the exact qualified host container
	 * exists. DOM attributes and control values are never read or written.
	 *
	 * @param {Document} doc Browser document.
	 * @return {number} Number of changed text nodes.
	 */
	function run( doc ) {
		if ( ! doc || typeof doc.querySelector !== 'function' ) {
			return 0;
		}

		const root = doc.querySelector( ROOT_SELECTOR );
		if ( ! root ) {
			return 0;
		}

		const fields = root.querySelectorAll( FIELD_SELECTOR );
		let changed = 0;

		fields.forEach( function ( field ) {
			changed += shapeFieldTextNodes( doc, field );
		} );

		return changed;
	}

	return {
		shapeDigits,
		run,
	};
} ) );
