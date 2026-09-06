( function ( root, factory ) {
	'use strict';

	const api = factory();

	if ( typeof module === 'object' && module.exports ) {
		module.exports = api;
	}

	if ( root ) {
		root.PGRStructuredScannerCore = api;
	}
} )( typeof globalThis !== 'undefined' ? globalThis : this, function () {
	'use strict';

	const SAYAD_V01_OUTPUTS = Object.freeze( [
		'qr_version',
		'owner_type',
		'owner_identifier',
		'iban',
		'bank_branch',
		'cheque_serial',
		'sayad_id',
	] );

	const SUPPORTED_TARGET_TYPES = Object.freeze( [ 'text', 'hidden' ] );

	function failure( code, details ) {
		return Object.assign( { ok: false, code: code }, details || {} );
	}

	function normalizeDigits( value ) {
		return String( value )
			.replace( /[\u06F0-\u06F9]/g, function ( digit ) {
				return String( digit.charCodeAt( 0 ) - 0x06F0 );
			} )
			.replace( /[\u0660-\u0669]/g, function ( digit ) {
				return String( digit.charCodeAt( 0 ) - 0x0660 );
			} );
	}

	function parseSayadV01( payload ) {
		if ( typeof payload !== 'string' ) {
			return failure( 'INVALID_SEGMENT_COUNT', { expected: 7, received: 0 } );
		}

		const normalizedPayload = payload.replace( /\r\n?/g, '\n' ).trim();

		if ( normalizedPayload === '' ) {
			return failure( 'INVALID_SEGMENT_COUNT', { expected: 7, received: 0 } );
		}

		const segments = normalizedPayload.split( '\n' ).map( function ( segment ) {
			return normalizeDigits( segment.trim() );
		} );

		if ( segments.length !== SAYAD_V01_OUTPUTS.length ) {
			return failure(
				'INVALID_SEGMENT_COUNT',
				{ expected: SAYAD_V01_OUTPUTS.length, received: segments.length }
			);
		}

		const emptyIndex = segments.findIndex( function ( segment ) {
			return segment === '';
		} );

		if ( emptyIndex !== -1 ) {
			return failure( 'EMPTY_SEGMENT', { segment: emptyIndex + 1 } );
		}

		const data = {};
		SAYAD_V01_OUTPUTS.forEach( function ( output, index ) {
			data[ output ] = segments[ index ];
		} );

		return {
			ok: true,
			profileId: 'sayad_v01',
			outputs: SAYAD_V01_OUTPUTS.slice(),
			data: data,
		};
	}

	const PROFILE_REGISTRY = Object.freeze( {
		sayad_v01: Object.freeze( {
			id: 'sayad_v01',
			outputs: SAYAD_V01_OUTPUTS,
			parse: parseSayadV01,
		} ),
	} );

	function parseScan( payload, profileId ) {
		const profile = PROFILE_REGISTRY[ profileId ];

		if ( ! profile ) {
			return failure( 'PROFILE_NOT_FOUND' );
		}

		return profile.parse( payload );
	}

	function decideCaptureAction( payload, profileId, trigger ) {
		const parsed = parseScan( payload, profileId );
		const explicitFinalization = trigger === 'tab' || trigger === 'paste';

		return {
			action: parsed.ok || explicitFinalization ? 'finalize' : 'continue',
			parsed: parsed,
		};
	}

	function normalizeTargetId( value ) {
		if ( value === null || typeof value === 'undefined' || value === '' ) {
			return '';
		}

		if ( typeof value !== 'string' && typeof value !== 'number' ) {
			return null;
		}

		const candidate = String( value ).trim();
		if ( ! /^[1-9]\d*$/.test( candidate ) ) {
			return null;
		}

		return candidate;
	}

	function normalizeMappings( profile, mappings ) {
		if (
			! mappings ||
			typeof mappings !== 'object' ||
			Array.isArray( mappings )
		) {
			return failure( 'INVALID_MAPPING' );
		}

		const allowedKeys = new Set( profile.outputs );
		const keys = Object.keys( mappings );

		for ( const key of keys ) {
			if ( ! allowedKeys.has( key ) ) {
				return failure( 'INVALID_MAPPING' );
			}
		}

		const normalized = {};
		for ( const output of profile.outputs ) {
			const targetId = normalizeTargetId( mappings[ output ] );

			if ( targetId === null ) {
				return failure( 'INVALID_MAPPING' );
			}

			if ( targetId !== '' ) {
				normalized[ output ] = targetId;
			}
		}

		return { ok: true, mappings: normalized };
	}

	function indexTargetDescriptors( targetDescriptors ) {
		if ( ! Array.isArray( targetDescriptors ) ) {
			return new Map();
		}

		const index = new Map();

		targetDescriptors.forEach( function ( descriptor ) {
			if ( ! descriptor || typeof descriptor !== 'object' ) {
				return;
			}

			const targetId = normalizeTargetId( descriptor.id );
			if ( ! targetId ) {
				return;
			}

			index.set( targetId, {
				id: targetId,
				type: String( descriptor.type || '' ),
				displayOnly: descriptor.displayOnly === true,
			} );
		} );

		return index;
	}

	function validateMappings( profileId, mappings, targetDescriptors, scannerFieldId ) {
		const profile = PROFILE_REGISTRY[ profileId ];

		if ( ! profile ) {
			return failure( 'PROFILE_NOT_FOUND' );
		}

		const normalizedResult = normalizeMappings( profile, mappings );
		if ( ! normalizedResult.ok ) {
			return normalizedResult;
		}

		const scannerId = normalizeTargetId( scannerFieldId );
		const targets = indexTargetDescriptors( targetDescriptors );
		const usedTargets = new Set();

		for ( const output of profile.outputs ) {
			const targetId = normalizedResult.mappings[ output ];

			if ( ! targetId ) {
				continue;
			}

			if ( scannerId && targetId === scannerId ) {
				return failure( 'SELF_TARGET' );
			}

			if ( usedTargets.has( targetId ) ) {
				return failure( 'DUPLICATE_TARGET' );
			}
			usedTargets.add( targetId );

			const descriptor = targets.get( targetId );
			if ( ! descriptor ) {
				return failure( 'TARGET_NOT_FOUND' );
			}

			if (
				descriptor.displayOnly ||
				! SUPPORTED_TARGET_TYPES.includes( descriptor.type )
			) {
				return failure( 'UNSUPPORTED_TARGET' );
			}
		}

		return {
			ok: true,
			profileId: profileId,
			outputs: profile.outputs.slice(),
			mappings: normalizedResult.mappings,
		};
	}

	function parsedResultMatchesProfile( parsed, profile ) {
		if (
			! parsed ||
			parsed.ok !== true ||
			parsed.profileId !== profile.id ||
			! Array.isArray( parsed.outputs ) ||
			! parsed.data ||
			typeof parsed.data !== 'object'
		) {
			return false;
		}

		if ( parsed.outputs.length !== profile.outputs.length ) {
			return false;
		}

		for ( let index = 0; index < profile.outputs.length; index++ ) {
			const output = profile.outputs[ index ];

			if (
				parsed.outputs[ index ] !== output ||
				typeof parsed.data[ output ] !== 'string' ||
				parsed.data[ output ] === ''
			) {
				return false;
			}
		}

		return true;
	}

	function buildUpdatePlan( parsed, mappings, targetDescriptors, scannerFieldId ) {
		if ( ! parsed || parsed.ok !== true ) {
			return failure(
				parsed && typeof parsed.code === 'string' ? parsed.code : 'INVALID_MAPPING'
			);
		}

		const profile = PROFILE_REGISTRY[ parsed.profileId ];
		if ( ! profile ) {
			return failure( 'PROFILE_NOT_FOUND' );
		}

		if ( ! parsedResultMatchesProfile( parsed, profile ) ) {
			return failure( 'INVALID_MAPPING' );
		}

		const mappingResult = validateMappings(
			parsed.profileId,
			mappings,
			targetDescriptors,
			scannerFieldId
		);

		if ( ! mappingResult.ok ) {
			return mappingResult;
		}

		const updates = [];
		for ( const output of profile.outputs ) {
			const targetId = mappingResult.mappings[ output ];
			if ( ! targetId ) {
				continue;
			}

			updates.push( {
				output: output,
				targetId: targetId,
				value: parsed.data[ output ],
			} );
		}

		return {
			ok: true,
			profileId: parsed.profileId,
			updates: updates,
		};
	}

	return Object.freeze( {
		PROFILE_REGISTRY: PROFILE_REGISTRY,
		SAYAD_V01_OUTPUTS: SAYAD_V01_OUTPUTS,
		SUPPORTED_TARGET_TYPES: SUPPORTED_TARGET_TYPES,
		normalizeDigits: normalizeDigits,
		parseScan: parseScan,
		decideCaptureAction: decideCaptureAction,
		validateMappings: validateMappings,
		buildUpdatePlan: buildUpdatePlan,
	} );
} );
