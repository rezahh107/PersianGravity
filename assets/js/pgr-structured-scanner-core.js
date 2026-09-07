( function ( root, factory ) {
	'use strict';

	const api = factory();

	if ( root ) {
		root.PGRStructuredScannerCore = api;
		api.configureProfiles( root.PGRScannerProfiles );
	}

	if ( typeof module === 'object' && module.exports ) {
		module.exports = api;
	}
} )( typeof globalThis !== 'undefined' ? globalThis : this, function () {
	'use strict';

	const SUPPORTED_TARGET_TYPES = Object.freeze( [ 'text', 'hidden' ] );
	const PROFILE_ID_PATTERN = /^[a-z][a-z0-9_]*$/;
	let profileRegistry = Object.create( null );

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

	function normalizeRuntimeProfile( definition ) {
		if ( ! definition || typeof definition !== 'object' || Array.isArray( definition ) ) {
			return null;
		}

		const id = typeof definition.id === 'string' ? definition.id : '';
		const parser = definition.parser;
		const outputs = definition.outputs;

		if (
			! PROFILE_ID_PATTERN.test( id ) ||
			! parser ||
			typeof parser !== 'object' ||
			Array.isArray( parser ) ||
			parser.type !== 'segments_v1' ||
			parser.separator !== 'newline' ||
			typeof parser.trim !== 'boolean' ||
			typeof parser.normalize_digits !== 'boolean' ||
			! Array.isArray( outputs ) ||
			outputs.length === 0
		) {
			return null;
		}

		const normalizedOutputs = [];
		const seenKeys = new Set();

		for ( const output of outputs ) {
			if (
				! output ||
				typeof output !== 'object' ||
				Array.isArray( output ) ||
				typeof output.key !== 'string' ||
				! PROFILE_ID_PATTERN.test( output.key ) ||
				seenKeys.has( output.key ) ||
				typeof output.required !== 'boolean'
			) {
				return null;
			}

			seenKeys.add( output.key );
			normalizedOutputs.push( Object.freeze( {
				key: output.key,
				required: output.required,
			} ) );
		}

		return Object.freeze( {
			id: id,
			parser: Object.freeze( {
				type: 'segments_v1',
				separator: 'newline',
				trim: parser.trim,
				normalize_digits: parser.normalize_digits,
			} ),
			outputs: Object.freeze( normalizedOutputs ),
		} );
	}

	function configureProfiles( definitions ) {
		const next = Object.create( null );
		const rejected = [];

		if ( ! Array.isArray( definitions ) ) {
			profileRegistry = next;
			return { ok: false, count: 0, rejected: [ 'INVALID_PROFILE_REGISTRY' ] };
		}

		definitions.forEach( function ( definition, index ) {
			const normalized = normalizeRuntimeProfile( definition );
			if ( ! normalized ) {
				rejected.push( index );
				return;
			}
			if ( Object.prototype.hasOwnProperty.call( next, normalized.id ) ) {
				rejected.push( index );
				return;
			}
			next[ normalized.id ] = normalized;
		} );

		profileRegistry = next;
		return { ok: true, count: Object.keys( next ).length, rejected: rejected };
	}

	function getProfile( profileId ) {
		if ( typeof profileId !== 'string' ) {
			return null;
		}
		return profileRegistry[ profileId ] || null;
	}

	function outputKeys( profile ) {
		return profile.outputs.map( function ( output ) {
			return output.key;
		} );
	}

	function parseSegmentsV1( payload, profile ) {
		const expected = profile.outputs.length;
		if ( typeof payload !== 'string' ) {
			return failure( 'INVALID_SEGMENT_COUNT', { expected: expected, received: 0 } );
		}

		const normalizedPayload = payload.replace( /\r\n?/g, '\n' );
		let segments = normalizedPayload === '' ? [] : normalizedPayload.split( '\n' );

		if ( profile.parser.trim ) {
			segments = segments.map( function ( segment ) {
				return segment.trim();
			} );

			while ( segments.length > expected && segments[ 0 ] === '' ) {
				segments.shift();
			}
			while ( segments.length > expected && segments[ segments.length - 1 ] === '' ) {
				segments.pop();
			}
		}

		if ( segments.length !== expected ) {
			return failure( 'INVALID_SEGMENT_COUNT', {
				expected: expected,
				received: segments.length,
			} );
		}

		const data = {};
		for ( let index = 0; index < profile.outputs.length; index++ ) {
			const output = profile.outputs[ index ];
			let value = segments[ index ];

			if ( profile.parser.normalize_digits ) {
				value = normalizeDigits( value );
			}

			if ( output.required && value === '' ) {
				return failure( 'EMPTY_SEGMENT', { segment: index + 1 } );
			}
			data[ output.key ] = value;
		}

		return {
			ok: true,
			profileId: profile.id,
			outputs: outputKeys( profile ),
			data: data,
		};
	}

	function parseScan( payload, profileId ) {
		const profile = getProfile( profileId );
		if ( ! profile ) {
			return failure( 'PROFILE_NOT_FOUND' );
		}

		return parseSegmentsV1( payload, profile );
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
		if ( ! mappings || typeof mappings !== 'object' || Array.isArray( mappings ) ) {
			return failure( 'INVALID_MAPPING' );
		}

		const keys = outputKeys( profile );
		const allowedKeys = new Set( keys );
		for ( const key of Object.keys( mappings ) ) {
			if ( ! allowedKeys.has( key ) ) {
				return failure( 'INVALID_MAPPING' );
			}
		}

		const normalized = {};
		for ( const output of keys ) {
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
		const profile = getProfile( profileId );
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
		const keys = outputKeys( profile );

		for ( const output of keys ) {
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
			if ( descriptor.displayOnly || ! SUPPORTED_TARGET_TYPES.includes( descriptor.type ) ) {
				return failure( 'UNSUPPORTED_TARGET' );
			}
		}

		return {
			ok: true,
			profileId: profileId,
			outputs: keys,
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
				parsed.outputs[ index ] !== output.key ||
				typeof parsed.data[ output.key ] !== 'string' ||
				( output.required && parsed.data[ output.key ] === '' )
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

		const profile = getProfile( parsed.profileId );
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
		for ( const output of outputKeys( profile ) ) {
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
		SUPPORTED_TARGET_TYPES: SUPPORTED_TARGET_TYPES,
		normalizeDigits: normalizeDigits,
		configureProfiles: configureProfiles,
		parseScan: parseScan,
		decideCaptureAction: decideCaptureAction,
		validateMappings: validateMappings,
		buildUpdatePlan: buildUpdatePlan,
	} );
} );
