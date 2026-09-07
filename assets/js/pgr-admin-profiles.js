( function () {
	'use strict';

	const strings = window.PGRProfileAdmin || {};

	function text( key, fallback ) {
		return typeof strings[ key ] === 'string' && strings[ key ] !== '' ? strings[ key ] : fallback;
	}

	function createElement( tag, attributes, label ) {
		const element = document.createElement( tag );
		Object.keys( attributes || {} ).forEach( function ( name ) {
			element.setAttribute( name, attributes[ name ] );
		} );
		if ( typeof label === 'string' ) {
			element.textContent = label;
		}
		return element;
	}

	function makeOutputRow() {
		const row = createElement( 'div', { 'class': 'pgr-output-row', 'data-pgr-output-row': '1' } );
		row.appendChild( createElement( 'span', { 'class': 'pgr-output-row__index', 'data-pgr-output-index': '1' }, '1' ) );

		const keyLabel = createElement( 'label' );
		keyLabel.appendChild( createElement( 'span', {}, text( 'outputKey', 'Output key' ) ) );
		keyLabel.appendChild( createElement( 'input', { type: 'text', 'class': 'pgr-ltr', dir: 'ltr', pattern: '[a-z][a-z0-9_]*', required: 'required' } ) );
		row.appendChild( keyLabel );

		const valueLabel = createElement( 'label' );
		valueLabel.appendChild( createElement( 'span', {}, text( 'outputLabel', 'Output label' ) ) );
		valueLabel.appendChild( createElement( 'input', { type: 'text', required: 'required' } ) );
		row.appendChild( valueLabel );

		const requiredLabel = createElement( 'label', { 'class': 'pgr-checkbox pgr-output-row__required' } );
		requiredLabel.appendChild( createElement( 'input', { type: 'checkbox', value: '1' } ) );
		requiredLabel.appendChild( createElement( 'span', {}, text( 'required', 'Required' ) ) );
		row.appendChild( requiredLabel );

		const actions = createElement( 'div', { 'class': 'pgr-output-row__actions' } );
		actions.appendChild( createElement( 'button', { type: 'button', 'class': 'button button-small', 'data-pgr-output-up': '1' }, text( 'moveUp', 'Move up' ) ) );
		actions.appendChild( createElement( 'button', { type: 'button', 'class': 'button button-small', 'data-pgr-output-down': '1' }, text( 'moveDown', 'Move down' ) ) );
		actions.appendChild( createElement( 'button', { type: 'button', 'class': 'button button-small', 'data-pgr-output-remove': '1' }, text( 'remove', 'Remove' ) ) );
		row.appendChild( actions );
		return row;
	}

	function renumber( list ) {
		const rows = Array.from( list.querySelectorAll( ':scope > [data-pgr-output-row]' ) );
		rows.forEach( function ( row, index ) {
			const displayIndex = row.querySelector( '[data-pgr-output-index]' );
			if ( displayIndex ) {
				displayIndex.textContent = String( index + 1 );
			}
			const inputs = row.querySelectorAll( 'input' );
			if ( inputs[ 0 ] ) {
				inputs[ 0 ].name = 'profile[outputs][' + index + '][key]';
			}
			if ( inputs[ 1 ] ) {
				inputs[ 1 ].name = 'profile[outputs][' + index + '][label]';
			}
			if ( inputs[ 2 ] ) {
				inputs[ 2 ].name = 'profile[outputs][' + index + '][required]';
			}
		} );
	}

	function initializeOutputEditor( form ) {
		const list = form.querySelector( '[data-pgr-output-list]' );
		const addButton = form.querySelector( '[data-pgr-add-output]' );
		if ( ! list || ! addButton ) {
			return;
		}

		addButton.addEventListener( 'click', function () {
			const row = makeOutputRow();
			list.appendChild( row );
			renumber( list );
			const firstInput = row.querySelector( 'input' );
			if ( firstInput ) {
				firstInput.focus();
			}
		} );

		list.addEventListener( 'click', function ( event ) {
			const button = event.target.closest( 'button' );
			if ( ! button || ! list.contains( button ) ) {
				return;
			}
			const row = button.closest( '[data-pgr-output-row]' );
			if ( ! row ) {
				return;
			}

			if ( button.hasAttribute( 'data-pgr-output-up' ) && row.previousElementSibling ) {
				list.insertBefore( row, row.previousElementSibling );
			} else if ( button.hasAttribute( 'data-pgr-output-down' ) && row.nextElementSibling ) {
				list.insertBefore( row.nextElementSibling, row );
			} else if ( button.hasAttribute( 'data-pgr-output-remove' ) ) {
				const rows = list.querySelectorAll( ':scope > [data-pgr-output-row]' );
				if ( rows.length > 1 ) {
					row.remove();
				}
			}
			renumber( list );
		} );
		renumber( list );
	}

	function initializeDeleteConfirmation() {
		document.querySelectorAll( 'form' ).forEach( function ( form ) {
			const action = form.querySelector( 'input[name="action"][value="pgr_scanner_profile_delete"]' );
			if ( ! action ) {
				return;
			}
			form.addEventListener( 'submit', function ( event ) {
				if ( ! window.confirm( text( 'deleteConfirm', 'Delete this Scanner profile?' ) ) ) {
					event.preventDefault();
				}
			} );
		} );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		document.querySelectorAll( '.pgr-profile-form' ).forEach( initializeOutputEditor );
		initializeDeleteConfirmation();
	} );
} )();
