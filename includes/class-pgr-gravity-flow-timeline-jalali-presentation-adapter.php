<?php
/**
 * Bounded Jalali presentation for Gravity Flow Timeline dates.
 *
 * Runtime admission is behavioral: canonical host identity plus the authentic
 * request-local Timeline caller/data contract. Exact package/source identity is
 * qualification evidence in CI, not a production activation oracle.
 *
 * @package PersianGravityForms
 */

defined( 'ABSPATH' ) || exit;

final class PGR_Gravity_Flow_Timeline_Jalali_Presentation_Adapter {

	/** Host-owned runtime version observation. Version equality is not eligibility. */
	private const HOST_VERSION_CONSTANT = 'GRAVITY_FLOW_VERSION';

	/** Host-owned plugin identity authority. */
	private const HOST_BASENAME_CONSTANT = 'GRAVITY_FLOW_PLUGIN_BASENAME';

	/** Canonical Gravity Flow plugin main-file identity. */
	private const HOST_PLUGIN_BASENAME = 'gravityflow/gravityflow.php';

	private const CAP_TIMELINE = 'gravityflow.timeline-history';
	private const CAP_PRINT    = 'gravityflow.print';

	private const SUPPORTED_FORMATS = array( 'F j, Y', 'Y-m-d' );

	private const FIRST_CHAIN = array(
		'get_option',
		'GFCommon::get_default_date_format',
		'GFCommon::format_date',
		'Gravity_Flow_Common::format_date',
		'Gravity_Flow_Entry_Detail::get_note_header',
		'Gravity_Flow_Entry_Detail::get_note_body',
		'Gravity_Flow_Entry_Detail::notes_grid',
		'Gravity_Flow_Entry_Detail::timeline',
	);

	private const TIME_FORMAT_CHAIN = array(
		'get_option',
		'GFCommon::get_default_time_format',
		'GFCommon::format_date',
		'Gravity_Flow_Common::format_date',
		'Gravity_Flow_Entry_Detail::get_note_header',
		'Gravity_Flow_Entry_Detail::get_note_body',
		'Gravity_Flow_Entry_Detail::notes_grid',
		'Gravity_Flow_Entry_Detail::timeline',
	);

	private const SECOND_CHAIN = array(
		'date_i18n',
		'GFCommon::format_date',
		'Gravity_Flow_Common::format_date',
		'Gravity_Flow_Entry_Detail::get_note_header',
		'Gravity_Flow_Entry_Detail::get_note_body',
		'Gravity_Flow_Entry_Detail::notes_grid',
		'Gravity_Flow_Entry_Detail::timeline',
	);

	/** @var array<string,array<string,mixed>> */
	private $contexts = array();

	/** @var array<int,array<string,mixed>> */
	private $time_contexts = array();

	/** @var array<string,string> */
	private $marker_literals = array();

	/** @var int */
	private $marker_serial = 0;

	/** @var bool */
	private $date_hook_registered = false;

	/** @var bool|null Cached canonical host-identity observation. */
	private $host_contract_valid = null;

	/** @var string|null Product slug derived only from the canonical host basename. */
	private $flow_product_slug = null;

	/** @return void */
	public function hooks() {
		if (
			! function_exists( 'determine_locale' ) ||
			'fa_IR' !== determine_locale() ||
			! class_exists( 'PGR_Jalali_Presentation', false )
		) {
			return;
		}

		add_filter( 'option_date_format', array( $this, 'filter_date_format' ), PHP_INT_MAX, 2 );
		add_filter( 'option_time_format', array( $this, 'filter_time_format' ), PHP_INT_MAX, 2 );
	}

	/** @return mixed */
	public function filter_date_format( $format, $option = null ) {
		if (
			'date_format' !== $option ||
			! is_string( $format ) ||
			! function_exists( 'determine_locale' ) ||
			'fa_IR' !== determine_locale() ||
			! class_exists( 'PGR_Jalali_Presentation', false )
		) {
			return $format;
		}

		$trace = $this->capture_trace();
		$this->prune_stale_contexts( $trace );
		if ( ! $this->is_timeline_candidate_trace( $trace ) ) {
			return $format;
		}

		$print = $this->is_print_inheritance_trace( $trace );
		$path  = $this->nearest_contiguous_chain( $trace, self::FIRST_CHAIN );
		if ( null === $path || ! in_array( $format, self::SUPPORTED_FORMATS, true ) ) {
			$this->record_failure( 'STATE_DEGRADED', 'REASON_SEAM_UNAVAILABLE', $print );
			return $format;
		}
		if ( ! $this->has_qualified_host_identity() ) {
			$this->record_failure( 'STATE_UNAVAILABLE', 'REASON_HOST_UNQUALIFIED', $print );
			return $format;
		}

		$note  = $path[5]['args'][0] ?? null;
		$notes = $path[6]['args'][0] ?? null;
		$entry = $path[7]['args'][0] ?? null;
		$form  = $path[7]['args'][1] ?? null;
		if ( ! is_object( $note ) || ! is_array( $notes ) || ! is_array( $entry ) || ! in_array( $note, $notes, true ) ) {
			$this->record_failure( 'STATE_DEGRADED', 'REASON_CONTEXT_UNAVAILABLE', $print );
			return $format;
		}

		$raw = isset( $note->date_created ) && is_string( $note->date_created ) ? $note->date_created : null;
		if ( null === $raw || ! $this->first_path_arguments_match( $path, $raw ) ) {
			$this->record_failure( 'STATE_DEGRADED', 'REASON_SOURCE_INVALID', $print );
			return $format;
		}

		$event_kind = $this->event_kind( $note, $entry, $raw );
		$timestamp  = $this->expected_localized_timestamp( $raw );
		$entry_key  = $this->entry_key( $entry );
		$note_order = $this->note_identity_order( $notes );
		if ( null === $event_kind || null === $timestamp || null === $entry_key || null === $note_order ) {
			$this->record_failure( 'STATE_DEGRADED', 'REASON_SOURCE_INVALID', $print );
			return $format;
		}

		$marked = $this->create_marked_format( $format );
		if ( null === $marked ) {
			$this->record_failure( 'STATE_DEGRADED', 'REASON_CONTEXT_UNAVAILABLE', $print );
			return $format;
		}

		$this->contexts[ $marked['format'] ]        = array(
			'note'            => $note,
			'note_snapshot'   => get_object_vars( $note ),
			'notes'           => $notes,
			'notes_order'     => $note_order,
			'entry'           => $entry,
			'entry_key'       => $entry_key,
			'form'            => $form,
			'raw'             => $raw,
			'timestamp'       => $timestamp,
			'native_format'   => $format,
			'event_kind'      => $event_kind,
			'print_inherited' => $print,
		);
		$this->marker_literals[ $marked['format'] ] = $marked['literal'];

		if ( ! $this->date_hook_registered ) {
			add_filter( 'date_i18n', array( $this, 'filter_date_i18n' ), PHP_INT_MAX, 4 );
			$this->date_hook_registered = true;
		}

		return $marked['format'];
	}

	/** @return mixed */
	public function filter_time_format( $format, $option = null ) {
		if (
			'time_format' !== $option ||
			! is_string( $format ) ||
			'' === $format ||
			! function_exists( 'determine_locale' ) ||
			'fa_IR' !== determine_locale()
		) {
			return $format;
		}

		$trace = $this->capture_trace();
		$this->prune_stale_contexts( $trace );
		if ( ! $this->is_timeline_candidate_trace( $trace ) ) {
			return $format;
		}

		$print = $this->is_print_inheritance_trace( $trace );
		$path  = $this->nearest_contiguous_chain( $trace, self::TIME_FORMAT_CHAIN );
		if ( null === $path ) {
			$this->record_failure( 'STATE_DEGRADED', 'REASON_SEAM_UNAVAILABLE', $print );
			return $format;
		}
		if ( ! $this->has_qualified_host_identity() ) {
			$this->record_failure( 'STATE_UNAVAILABLE', 'REASON_HOST_UNQUALIFIED', $print );
			return $format;
		}

		$owned = $this->active_calendar_context_for_time_path( $path );
		if ( null === $owned || (bool) ( $owned['context']['print_inherited'] ?? false ) !== $print ) {
			$this->record_failure( 'STATE_DEGRADED', 'REASON_CONTEXT_UNAVAILABLE', $print );
			return $format;
		}

		$note    = $owned['context']['note'];
		$context = $owned['context'];
		$this->time_contexts[ spl_object_id( $note ) ] = array(
			'note'              => $note,
			'note_snapshot'     => $context['note_snapshot'],
			'notes'             => $context['notes'],
			'notes_order'       => $context['notes_order'],
			'entry'             => $context['entry'],
			'entry_key'         => $context['entry_key'],
			'form'              => $context['form'],
			'raw'               => $context['raw'],
			'timestamp'         => $context['timestamp'],
			'time_format'       => $format,
			'date_format'       => $owned['format'],
			'event_kind'        => $context['event_kind'],
			'print_inherited'   => (bool) ( $context['print_inherited'] ?? false ),
			'calendar_admitted' => false,
		);

		return $format;
	}

	/** @return mixed */
	public function filter_date_i18n( $date, $format, $timestamp, $gmt ) {
		$fallback = $this->strip_owned_markers( $date );
		if ( ! is_string( $format ) || ! function_exists( 'determine_locale' ) || 'fa_IR' !== determine_locale() ) {
			return $fallback;
		}

		$trace = $this->capture_trace();
		$this->prune_stale_contexts( $trace );
		$path = $this->nearest_contiguous_chain( $trace, self::SECOND_CHAIN );
		if ( null === $path ) {
			if ( isset( $this->contexts[ $format ] ) ) {
				$context = $this->contexts[ $format ];
				unset( $this->contexts[ $format ] );
				$this->clear_time_context_for_context( $context );
				$this->record_failure( 'STATE_DEGRADED', 'REASON_SEAM_UNAVAILABLE', (bool) ( $context['print_inherited'] ?? false ) );
			}
			return $fallback;
		}

		if ( ! isset( $this->contexts[ $format ] ) ) {
			return $this->shape_timeline_time_digits( $fallback, $format, $timestamp, $gmt, $path, $trace );
		}

		$context = $this->contexts[ $format ];
		unset( $this->contexts[ $format ] );
		$print    = $this->is_print_inheritance_trace( $trace );
		$expected = (bool) ( $context['print_inherited'] ?? false );
		$time_key = is_object( $context['note'] ?? null ) ? spl_object_id( $context['note'] ) : null;
		$note     = $path[4]['args'][0] ?? null;
		$notes    = $path[5]['args'][0] ?? null;
		$entry    = $path[6]['args'][0] ?? null;
		$form     = $path[6]['args'][1] ?? null;

		if ( ! $this->has_qualified_host_identity() ) {
			$this->clear_time_context_for_context( $context );
			$this->record_failure( 'STATE_UNAVAILABLE', 'REASON_HOST_UNQUALIFIED', $expected || $print );
			return $fallback;
		}
		if ( $print !== $expected ) {
			$this->clear_time_context_for_context( $context );
			$this->record_failure( 'STATE_DEGRADED', 'REASON_CONTEXT_UNAVAILABLE', $expected || $print );
			return $fallback;
		}

		if (
			true !== $gmt ||
			! is_int( $timestamp ) ||
			$timestamp !== $context['timestamp'] ||
			! class_exists( 'PGR_Jalali_Presentation', false ) ||
			! is_object( $note ) ||
			$note !== $context['note'] ||
			get_object_vars( $note ) !== $context['note_snapshot'] ||
			! is_array( $notes ) ||
			$notes !== $context['notes'] ||
			$this->note_identity_order( $notes ) !== $context['notes_order'] ||
			! is_array( $entry ) ||
			$entry !== $context['entry'] ||
			$this->entry_key( $entry ) !== $context['entry_key'] ||
			$form !== $context['form'] ||
			! $this->second_path_arguments_match( $path, $context, $format ) ||
			$this->event_kind( $note, $entry, $context['raw'] ) !== $context['event_kind'] ||
			! in_array( $context['native_format'], self::SUPPORTED_FORMATS, true )
		) {
			if ( null !== $time_key ) {
				unset( $this->time_contexts[ $time_key ] );
			}
			$this->record_failure( 'STATE_DEGRADED', 'REASON_SOURCE_INVALID', $expected );
			return $fallback;
		}

		try {
			$formatted = PGR_Jalali_Presentation::format_date(
				(int) gmdate( 'Y', $timestamp ),
				(int) gmdate( 'n', $timestamp ),
				(int) gmdate( 'j', $timestamp )
			);
		} catch ( Throwable $exception ) {
			unset( $exception );
			$formatted = null;
		}

		if ( null === $formatted ) {
			if ( null !== $time_key ) {
				unset( $this->time_contexts[ $time_key ] );
			}
			$this->record_failure( 'STATE_DEGRADED', 'REASON_CONVERSION_UNAVAILABLE', $expected );
			return $fallback;
		}

		if ( null !== $time_key && isset( $this->time_contexts[ $time_key ] ) ) {
			$this->time_contexts[ $time_key ]['calendar_admitted'] = true;
		}
		$this->record_available( $expected );
		return $formatted;
	}

	/** @return mixed */
	private function shape_timeline_time_digits( $date, $format, $timestamp, $gmt, $path, $trace = array() ) {
		if ( ! is_string( $date ) || ! function_exists( 'determine_locale' ) || 'fa_IR' !== determine_locale() ) {
			return $date;
		}

		$note = $path[4]['args'][0] ?? null;
		if ( ! is_object( $note ) ) {
			return $date;
		}

		$key = spl_object_id( $note );
		if ( ! isset( $this->time_contexts[ $key ] ) ) {
			return $date;
		}

		$context = $this->time_contexts[ $key ];
		if ( $format !== $context['time_format'] || true !== $context['calendar_admitted'] ) {
			unset( $this->time_contexts[ $key ] );
			$this->record_failure( 'STATE_DEGRADED', 'REASON_CONTEXT_UNAVAILABLE', (bool) ( $context['print_inherited'] ?? false ) );
			return $date;
		}
		unset( $this->time_contexts[ $key ] );

		$print = array() === $trace ? (bool) ( $context['print_inherited'] ?? false ) : $this->is_print_inheritance_trace( $trace );
		if ( (bool) ( $context['print_inherited'] ?? false ) !== $print ) {
			$this->record_failure( 'STATE_DEGRADED', 'REASON_CONTEXT_UNAVAILABLE', $print || (bool) ( $context['print_inherited'] ?? false ) );
			return $date;
		}

		$notes = $path[5]['args'][0] ?? null;
		$entry = $path[6]['args'][0] ?? null;
		$form  = $path[6]['args'][1] ?? null;
		if (
			! $this->has_qualified_host_identity() ||
			true !== $gmt ||
			! is_int( $timestamp ) ||
			$timestamp !== $context['timestamp'] ||
			$note !== $context['note'] ||
			get_object_vars( $note ) !== $context['note_snapshot'] ||
			! is_array( $notes ) ||
			$notes !== $context['notes'] ||
			$this->note_identity_order( $notes ) !== $context['notes_order'] ||
			! is_array( $entry ) ||
			$entry !== $context['entry'] ||
			$this->entry_key( $entry ) !== $context['entry_key'] ||
			$form !== $context['form'] ||
			! $this->time_path_arguments_match( $path, $context ) ||
			$this->event_kind( $note, $entry, $context['raw'] ) !== $context['event_kind']
		) {
			$this->record_failure( 'STATE_DEGRADED', 'REASON_SOURCE_INVALID', $print );
			return $date;
		}

		return $this->shape_ascii_digits( $date );
	}

	private function shape_ascii_digits( $value ) {
		return strtr(
			$value,
			array(
				'0' => '۰',
				'1' => '۱',
				'2' => '۲',
				'3' => '۳',
				'4' => '۴',
				'5' => '۵',
				'6' => '۶',
				'7' => '۷',
				'8' => '۸',
				'9' => '۹',
			)
		);
	}

	private function capture_trace() {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace -- Qualified caller-chain proof.
		return debug_backtrace( 0, 60 );
	}

	private function nearest_contiguous_chain( $trace, $expected ) {
		foreach ( $trace as $index => $frame ) {
			if ( $this->frame_signature( $frame ) !== $expected[0] ) {
				continue;
			}
			foreach ( $expected as $offset => $signature ) {
				if ( ! isset( $trace[ $index + $offset ] ) || $this->frame_signature( $trace[ $index + $offset ] ) !== $signature ) {
					return null;
				}
			}
			return array_slice( $trace, $index, count( $expected ) );
		}
		return null;
	}

	private function frame_signature( $frame ) {
		$class    = isset( $frame['class'] ) ? (string) $frame['class'] : '';
		$type     = isset( $frame['type'] ) ? (string) $frame['type'] : '';
		$function = isset( $frame['function'] ) ? (string) $frame['function'] : '';
		return $class . $type . $function;
	}

	private function is_timeline_candidate_trace( $trace ) {
		foreach ( $trace as $frame ) {
			if ( 'Gravity_Flow_Entry_Detail::timeline' === $this->frame_signature( $frame ) ) {
				return true;
			}
		}
		return false;
	}

	private function is_print_inheritance_trace( $trace ) {
		$timeline_index = null;
		foreach ( $trace as $index => $frame ) {
			if ( 'Gravity_Flow_Entry_Detail::timeline' === $this->frame_signature( $frame ) ) {
				$timeline_index = $index;
				break;
			}
		}
		if ( null === $timeline_index ) {
			return false;
		}
		$count = count( $trace );
		for ( $index = $timeline_index + 1; $index < $count; ++$index ) {
			if ( 'Gravity_Flow_Print_Entries::render' === $this->frame_signature( $trace[ $index ] ) ) {
				return true;
			}
		}
		return false;
	}

	private function prune_stale_contexts( $trace ) {
		foreach ( $this->contexts as $format => $context ) {
			if ( ! $this->note_is_live_on_stack( $trace, $context['note'] ?? null ) ) {
				unset( $this->contexts[ $format ] );
			}
		}
		foreach ( $this->time_contexts as $key => $context ) {
			if ( ! $this->note_is_live_on_stack( $trace, $context['note'] ?? null ) ) {
				unset( $this->time_contexts[ $key ] );
			}
		}
	}

	private function note_is_live_on_stack( $trace, $note ) {
		if ( ! is_object( $note ) ) {
			return false;
		}
		foreach ( $trace as $frame ) {
			if (
				'Gravity_Flow_Entry_Detail::get_note_body' === $this->frame_signature( $frame ) &&
				isset( $frame['args'][0] ) &&
				$frame['args'][0] === $note
			) {
				return true;
			}
		}
		return false;
	}

	private function first_path_arguments_match( $path, $raw ) {
		return isset( $path[2]['args'], $path[3]['args'] ) &&
			array( $raw, false, '', true ) === $path[2]['args'] &&
			array( $raw, '', false, true ) === $path[3]['args'];
	}

	private function active_calendar_context_for_time_path( $path ) {
		$note  = $path[5]['args'][0] ?? null;
		$notes = $path[6]['args'][0] ?? null;
		$entry = $path[7]['args'][0] ?? null;
		$form  = $path[7]['args'][1] ?? null;
		if ( ! is_object( $note ) || ! is_array( $notes ) || ! is_array( $entry ) || ! in_array( $note, $notes, true ) ) {
			return null;
		}

		$matches = array();
		foreach ( $this->contexts as $format => $context ) {
			if (
				( $context['note'] ?? null ) !== $note ||
				get_object_vars( $note ) !== ( $context['note_snapshot'] ?? null ) ||
				( $context['notes'] ?? null ) !== $notes ||
				$this->note_identity_order( $notes ) !== ( $context['notes_order'] ?? null ) ||
				( $context['entry'] ?? null ) !== $entry ||
				$this->entry_key( $entry ) !== ( $context['entry_key'] ?? null ) ||
				( $context['form'] ?? null ) !== $form
			) {
				continue;
			}
			$matches[] = array(
				'format'  => $format,
				'context' => $context,
			);
		}
		return 1 === count( $matches ) ? $matches[0] : null;
	}

	private function time_path_arguments_match( $path, $context ) {
		if ( ! isset( $path[1]['args'], $path[2]['args'], $context['date_format'] ) ) {
			return false;
		}
		$raw = $context['raw'];
		return array( $raw, false, $context['date_format'], true ) === $path[1]['args'] &&
			array( $raw, '', false, true ) === $path[2]['args'];
	}

	private function second_path_arguments_match( $path, $context, $format ) {
		if ( ! isset( $path[1]['args'], $path[2]['args'] ) ) {
			return false;
		}
		$raw = $context['raw'];
		return in_array(
			$path[1]['args'],
			array(
				array( $raw, false, '', true ),
				array( $raw, false, $format, true ),
			),
			true
		) && array( $raw, '', false, true ) === $path[2]['args'];
	}

	private function event_kind( $note, $entry, $raw ) {
		$id = $this->nonnegative_decimal_id( $note->id ?? null );
		if ( null === $id ) {
			return null;
		}
		if ( '0' === $id ) {
			return isset( $entry['date_created'] ) && is_string( $entry['date_created'] ) && $raw === $entry['date_created'] ? 'initial' : null;
		}
		if ( null === $this->flow_product_slug || ! isset( $note->note_type ) ) {
			return null;
		}
		return $this->flow_product_slug === (string) $note->note_type ? 'stored' : null;
	}

	private function expected_localized_timestamp( $raw ) {
		try {
			$source = DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', $raw, new DateTimeZone( 'UTC' ) );
			$errors = DateTimeImmutable::getLastErrors();
			if (
				false === $source ||
				( is_array( $errors ) && ( $errors['warning_count'] > 0 || $errors['error_count'] > 0 ) ) ||
				$source->format( 'Y-m-d H:i:s' ) !== $raw
			) {
				return null;
			}
			$civil = $source->setTimezone( wp_timezone() );
		} catch ( Throwable $exception ) {
			unset( $exception );
			return null;
		}

		$timestamp = gmmktime(
			(int) $civil->format( 'H' ),
			(int) $civil->format( 'i' ),
			(int) $civil->format( 's' ),
			(int) $civil->format( 'n' ),
			(int) $civil->format( 'j' ),
			(int) $civil->format( 'Y' )
		);
		return is_int( $timestamp ) ? $timestamp : null;
	}

	private function create_marked_format( $native_format ) {
		try {
			$literal = 'PGRTIMELINE' . bin2hex( random_bytes( 16 ) ) . (string) ++$this->marker_serial . 'X';
		} catch ( Throwable $exception ) {
			unset( $exception );
			return null;
		}
		$escaped = '';
		foreach ( str_split( $literal ) as $character ) {
			$escaped .= '\\' . $character;
		}
		return array(
			'format'  => $escaped . $native_format,
			'literal' => $literal,
		);
	}

	private function strip_owned_markers( $date ) {
		if ( ! is_string( $date ) || empty( $this->marker_literals ) ) {
			return $date;
		}
		return str_replace( array_values( $this->marker_literals ), '', $date );
	}

	private function note_identity_order( $notes ) {
		$result = array();
		foreach ( $notes as $note ) {
			if ( ! is_object( $note ) ) {
				return null;
			}
			$result[] = spl_object_id( $note );
		}
		return $result;
	}

	private function entry_key( $entry ) {
		if ( ! isset( $entry['id'], $entry['form_id'] ) ) {
			return null;
		}
		$id      = $this->positive_decimal_id( $entry['id'] );
		$form_id = $this->positive_decimal_id( $entry['form_id'] );
		return null === $id || null === $form_id ? null : $form_id . ':' . $id;
	}

	private function positive_decimal_id( $value ) {
		$normalized = $this->nonnegative_decimal_id( $value );
		return null !== $normalized && '0' !== $normalized ? $normalized : null;
	}

	private function nonnegative_decimal_id( $value ) {
		if ( is_int( $value ) ) {
			return $value >= 0 ? (string) $value : null;
		}
		if ( ! is_string( $value ) || 1 !== preg_match( '/^(?:0|[1-9][0-9]*)$/', $value ) ) {
			return null;
		}
		return $value;
	}

	private function has_qualified_host_identity() {
		if ( null !== $this->host_contract_valid ) {
			return $this->host_contract_valid;
		}

		$this->host_contract_valid = false;
		$this->flow_product_slug   = null;
		if (
			! defined( self::HOST_VERSION_CONSTANT ) ||
			! defined( self::HOST_BASENAME_CONSTANT ) ||
			! class_exists( 'GFForms', false )
		) {
			return false;
		}

		$flow_version = trim( (string) constant( self::HOST_VERSION_CONSTANT ) );
		$gf_version   = trim( (string) GFForms::$version );
		$basename     = str_replace( '\\', '/', (string) constant( self::HOST_BASENAME_CONSTANT ) );
		$product_slug = $this->resolve_product_slug( $flow_version, $gf_version, $basename );
		if ( null === $product_slug ) {
			return false;
		}

		$this->flow_product_slug   = $product_slug;
		$this->host_contract_valid = true;
		return true;
	}

	private function resolve_product_slug( $flow_version, $gf_version, $basename ) {
		$basename = str_replace( '\\', '/', $basename );
		if (
			! $this->valid_version_observation( $flow_version ) ||
			! $this->valid_version_observation( $gf_version ) ||
			self::HOST_PLUGIN_BASENAME !== $basename
		) {
			return null;
		}
		return dirname( self::HOST_PLUGIN_BASENAME );
	}

	private function valid_version_observation( $version ) {
		return is_string( $version ) && '' !== $version && strlen( $version ) <= 32 &&
			1 === preg_match( '/^[A-Za-z0-9][A-Za-z0-9._+\-]*$/', $version );
	}

	private function clear_time_context_for_context( $context ) {
		$note = $context['note'] ?? null;
		if ( is_object( $note ) ) {
			unset( $this->time_contexts[ spl_object_id( $note ) ] );
		}
	}

	private function record_available( $print_inherited ) {
		$this->record_diagnostic( self::CAP_TIMELINE, 'STATE_AVAILABLE', 'REASON_CONTRACT_SATISFIED' );
		if ( $print_inherited ) {
			$this->record_diagnostic( self::CAP_PRINT, 'STATE_AVAILABLE', 'REASON_CONTRACT_SATISFIED' );
		}
	}

	private function record_failure( $state, $reason, $print_inherited ) {
		$this->record_diagnostic( self::CAP_TIMELINE, $state, $reason );
		if ( $print_inherited ) {
			$this->record_diagnostic( self::CAP_PRINT, $state, $reason );
		}
	}

	private function record_diagnostic( $capability, $state, $reason ) {
		if ( ! class_exists( 'PGR_Gravity_Flow_Compatibility_Diagnostics', false ) ) {
			return;
		}
		$diagnostics = 'PGR_Gravity_Flow_Compatibility_Diagnostics';
		$state_name  = $diagnostics . '::' . $state;
		$reason_name = $diagnostics . '::' . $reason;
		if ( ! defined( $state_name ) || ! defined( $reason_name ) ) {
			return;
		}
		PGR_Gravity_Flow_Compatibility_Diagnostics::record(
			$capability,
			constant( $state_name ),
			constant( $reason_name )
		);
	}
}
