( function () {
	'use strict';

	const core = window.PGRStructuredScannerCore;
	if ( ! core ) {
		return;
	}

	const SCAN_IDLE_MS = 150;
	const instanceStates = new WeakMap();

	function parseJson( value, fallback ) {
		try {
			return JSON.parse( value );
		} catch ( error ) {
			return fallback;
		}
	}

	function isVisible( element ) {
		if ( ! element || element.hidden ) {
			return false;
		}

		const style = window.getComputedStyle( element );
		if ( style.display === 'none' || style.visibility === 'hidden' ) {
			return false;
		}

		return element.getClientRects().length > 0;
	}

	function setStatus( state, statusName, message ) {
		state.root.dataset.pgrStatus = statusName;
		state.status.textContent = message;
	}

	function clearIdleTimer( state ) {
		if ( state.idleTimer !== null ) {
			window.clearTimeout( state.idleTimer );
			state.idleTimer = null;
		}
	}

	function scheduleIdleProcessing( state ) {
		clearIdleTimer( state );

		if ( state.capture.value === '' ) {
			return;
		}

		state.idleTimer = window.setTimeout( function () {
			state.idleTimer = null;
			processCapture( state );
		}, SCAN_IDLE_MS );
	}

	function inputElementsByFieldId( form ) {
		const inputs = new Map();

		form.querySelectorAll( 'input[name]' ).forEach( function ( input ) {
			const match = /^input_([1-9]\d*)$/.exec( input.name );
			if ( match ) {
				inputs.set( match[ 1 ], input );
			}
		} );

		return inputs;
	}

	function availableTargets( state, configuredTargets ) {
		const inputs = inputElementsByFieldId( state.form );
		const descriptors = [];
		const elements = new Map();

		if ( ! Array.isArray( configuredTargets ) ) {
			return { descriptors: descriptors, elements: elements };
		}

		configuredTargets.forEach( function ( descriptor ) {
			if ( ! descriptor || typeof descriptor !== 'object' ) {
				return;
			}

			const id = String( descriptor.id || '' );
			if ( ! /^[1-9]\d*$/.test( id ) || ! inputs.has( id ) ) {
				return;
			}

			descriptors.push( {
				id: id,
				type: String( descriptor.type || '' ),
				displayOnly: descriptor.displayOnly === true,
			} );
			elements.set( id, inputs.get( id ) );
		} );

		return { descriptors: descriptors, elements: elements };
	}

	function safeErrorMessage( state, result ) {
		if (
			result &&
			result.code === 'INVALID_SEGMENT_COUNT' &&
			Number.isInteger( result.expected ) &&
			Number.isInteger( result.received )
		) {
			return state.messages.segmentCount
				.replace( '%1$d', String( result.expected ) )
				.replace( '%2$d', String( result.received ) );
		}

		return state.messages.invalid;
	}

	function applyUpdatePlan( plan, elements ) {
		const prepared = [];

		for ( const update of plan.updates ) {
			const input = elements.get( update.targetId );
			if ( ! input ) {
				return false;
			}

			prepared.push( { input: input, value: String( update.value ) } );
		}

		prepared.forEach( function ( item ) {
			item.input.value = item.value;
			item.input.dispatchEvent( new Event( 'input', { bubbles: true } ) );
			item.input.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		} );

		return true;
	}

	function processCapture( state ) {
		clearIdleTimer( state );

		let rawPayload = state.capture.value;
		state.capture.value = '';
		setStatus( state, 'processing', state.messages.processing );

		const parsed = core.parseScan( rawPayload, state.profileId );
		rawPayload = null;

		if ( ! parsed.ok ) {
			setStatus( state, 'invalid', safeErrorMessage( state, parsed ) );
			state.capture.focus();
			return;
		}

		const targetState = availableTargets( state, state.targetDescriptors );
		const plan = core.buildUpdatePlan(
			parsed,
			state.mappings,
			targetState.descriptors,
			state.scannerFieldId
		);

		if ( ! plan.ok || ! applyUpdatePlan( plan, targetState.elements ) ) {
			setStatus( state, 'invalid', safeErrorMessage( state, plan ) );
			state.capture.focus();
			return;
		}

		setStatus( state, 'success', state.messages.success );
		state.capture.focus();
	}

	function maybeAutofocus( state ) {
		const active = document.activeElement;

		if ( ! active || active === document.body ) {
			state.capture.focus();
			return;
		}

		if ( state.form.contains( active ) ) {
			return;
		}

		const visibleScanners = Array.from(
			state.form.querySelectorAll( '[data-pgr-structured-scanner]' )
		).filter( isVisible );

		if ( visibleScanners.length === 1 && visibleScanners[ 0 ] === state.root ) {
			state.capture.focus();
		}
	}

	function initializeScanner( root ) {
		if ( instanceStates.has( root ) ) {
			return;
		}

		const form = root.closest( 'form' );
		const capture = root.querySelector( '[data-pgr-scanner-capture]' );
		const status = root.querySelector( '[data-pgr-scanner-status]' );
		const focusButton = root.querySelector( '[data-pgr-scanner-focus]' );

		if ( ! form || ! capture || ! status || ! focusButton ) {
			return;
		}

		const state = {
			root: root,
			form: form,
			capture: capture,
			status: status,
			focusButton: focusButton,
			idleTimer: null,
			profileId: root.dataset.pgrProfile || '',
			scannerFieldId: root.dataset.pgrScannerId || '',
			mappings: parseJson( root.dataset.pgrMappings || '{}', null ),
			targetDescriptors: parseJson( root.dataset.pgrTargets || '[]', [] ),
			messages: {
				processing: root.dataset.pgrMessageProcessing || '',
				success: root.dataset.pgrMessageSuccess || '',
				invalid: root.dataset.pgrMessageInvalid || '',
				segmentCount: root.dataset.pgrMessageSegmentCount || '',
			},
		};

		instanceStates.set( root, state );

		capture.addEventListener( 'input', function () {
			scheduleIdleProcessing( state );
		} );

		capture.addEventListener( 'keydown', function ( event ) {
			if ( event.key !== 'Enter' && event.key !== 'Tab' ) {
				return;
			}

			event.preventDefault();
			event.stopPropagation();
			processCapture( state );
		} );

		capture.addEventListener( 'paste', function ( event ) {
			event.preventDefault();

			const clipboard = event.clipboardData;
			state.capture.value = clipboard ? clipboard.getData( 'text' ) : '';
			processCapture( state );
		} );

		focusButton.addEventListener( 'click', function () {
			state.capture.focus();
		} );

		maybeAutofocus( state );
	}

	function initializeAll( scope ) {
		const rootScope = scope && scope.querySelectorAll ? scope : document;

		if (
			rootScope.nodeType === Node.ELEMENT_NODE &&
			rootScope.matches( '[data-pgr-structured-scanner]' )
		) {
			initializeScanner( rootScope );
		}

		rootScope.querySelectorAll( '[data-pgr-structured-scanner]' ).forEach(
			initializeScanner
		);
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', function () {
			initializeAll( document );
		} );
	} else {
		initializeAll( document );
	}

	document.addEventListener( 'gform/post_render', function () {
		initializeAll( document );
	} );
} )();
