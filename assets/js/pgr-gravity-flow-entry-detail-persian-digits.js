(function ( factory ) {
	'use strict';

	const api = factory();

	if ( typeof module === 'object' && module.exports ) {
		module.exports = api;
		return;
	}

	api.run( document );
})( function () {
	'use strict';

	const ROOT_SELECTOR = '#gravityflow-status-box-container .gravityflow-status-box';
	const PERSIAN_DIGITS = '۰۱۲۳۴۵۶۷۸۹';
	const EXCLUDED_ANCESTORS = 'script,style,noscript,template,input,textarea,select,option,[hidden],[aria-hidden="true"],[contenteditable="true"]';

	function shapeDigits( value ) {
		if ( typeof value !== 'string' ) {
			return value;
		}

		return value.replace( /[0-9]/g, function ( digit ) {
			return PERSIAN_DIGITS.charAt( digit.charCodeAt( 0 ) - 48 );
		} );
	}

	function isVisibleTextNode( node ) {
		const parent = node && node.parentElement;
		if ( ! parent || parent.closest( EXCLUDED_ANCESTORS ) ) {
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

	function shapeWorkflowInfo( doc ) {
		if ( ! doc || typeof doc.querySelector !== 'function' || typeof doc.createTreeWalker !== 'function' ) {
			return 0;
		}

		const root = doc.querySelector( ROOT_SELECTOR );
		const nodeFilter = doc.defaultView && doc.defaultView.NodeFilter;
		if ( ! root || ! nodeFilter ) {
			return 0;
		}

		const walker = doc.createTreeWalker( root, nodeFilter.SHOW_TEXT );
		let changed = 0;
		let node = walker.nextNode();

		while ( node ) {
			const before = node.nodeValue;
			if ( typeof before === 'string' && /[0-9]/.test( before ) && isVisibleTextNode( node ) ) {
				const after = shapeDigits( before );
				if ( after !== before ) {
					node.nodeValue = after;
					changed += 1;
				}
			}
			node = walker.nextNode();
		}

		return changed;
	}

	return {
		ROOT_SELECTOR,
		shapeDigits,
		shapeWorkflowInfo,
		run: shapeWorkflowInfo,
	};
} );
