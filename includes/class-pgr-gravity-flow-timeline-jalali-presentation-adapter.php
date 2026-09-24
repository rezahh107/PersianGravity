<?php
/**
 * Bounded Jalali presentation for exact Gravity Flow Timeline dates.
 *
 * @package PersianGravityForms
 */

defined( 'ABSPATH' ) || exit;

final class PGR_Gravity_Flow_Timeline_Jalali_Presentation_Adapter {

	/** Host-owned runtime version authority. */
	private const FLOW_VERSION_CONSTANT = 'GRAVITY_FLOW_VERSION';

	/** Host-owned plugin identity authority. */
	private const FLOW_BASENAME_CONSTANT = 'GRAVITY_FLOW_PLUGIN_BASENAME';

	/** Exact qualified host contract. */
	private const FLOW_VERSION  = '3.1.0';
	private const GF_VERSION    = '3.1.1.1';
	private const FLOW_BASENAME = 'gravityflow/gravityflow.php';

	/** Exact qualified source fingerprints for the production Timeline contract. */
	private const SOURCE_FINGERPRINTS = array(
		'flow_entry_detail' => 'a7634c5604184502457bcb22cdf1ade892e84c888cc60996aea8a810ced7680a',
		'flow_common'       => 'a8844f4b6ac37eed1a2cc1e904418f6ed8c6b4480e982c5a809e895a6f9e0cc8',
		'flow_print'        => 'df969bf8a37ed4f5619e0e8b2a753dd1133fa0c7551b158d740248c67fcb95c6',
		'gf_common'         => 'ac4ed495ee02a119a4fd08c77279f0db0905e6f20f59c472d5e6bffc801ca355',
	);

	/** Exact qualified date-format profiles. */
	private const SUPPORTED_FORMATS = array( 'F j, Y', 'Y-m-d' );

	/** Exact first-seam caller chain, nearest frame first. */
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

	/** Exact second-seam caller chain, nearest frame first. */
	private const SECOND_CHAIN = array(
		'date_i18n',
		'GFCommon::format_date',
		'Gravity_Flow_Common::format_date',
		'Gravity_Flow_Entry_Detail::get_note_header',
		'Gravity_Flow_Entry_Detail::get_note_body',
		'Gravity_Flow_Entry_Detail::notes_grid',
		'Gravity_Flow_Entry_Detail::timeline',
	);

	/** @var array<string,array<string,mixed>> One-shot contexts keyed by exact marked format. */
	private $contexts = array();

	/** @var array<string,string> Marked format => literal marker retained for leak-safe fallback. */
	private $marker_literals = array();

	/** @var int Request-local marker serial. */
	private $marker_serial = 0;

	/** @var bool Whether the second hook has been installed for this request-local adapter. */
	private $date_hook_registered = false;

	/** @var bool|null Cached exact host/source contract result. */
	private $host_contract_valid = null;

	/**
	 * Register only the qualified first Timeline presentation seam.
	 *
	 * date_i18n is installed lazily only after an authentic Timeline row arms an
	 * owned one-shot marker.
	 *
	 * @return void
	 */
	public function hooks() {
		add_filter( 'option_date_format', array( $this, 'filter_date_format' ), PHP_INT_MAX, 2 );
	}

	/**
	 * Arm a unique one-shot format marker only for the exact Timeline call path.
	 *
	 * @param mixed $format Native WordPress date format option value.
	 * @param mixed $option Option name.
	 * @return mixed
	 */
	public function filter_date_format( $format, $option = null ) {
		if ( 'date_format' !== $option || ! is_string( $format ) || ! in_array( $format, self::SUPPORTED_FORMATS, true ) ) {
			return $format;
		}

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace -- Exact host caller-chain proof is the qualified production seam.
		$trace = debug_backtrace( 0, 60 );
		$this->prune_stale_contexts( $trace );
		$path = $this->nearest_contiguous_chain( $trace, self::FIRST_CHAIN );
		if ( null === $path || ! $this->is_exact_supported_host() || ! class_exists( 'PGR_Jalali_Presentation', false ) ) {
			return $format;
		}

		$note  = isset( $path[5]['args'][0] ) ? $path[5]['args'][0] : null;
		$notes = isset( $path[6]['args'][0] ) ? $path[6]['args'][0] : null;
		$entry = isset( $path[7]['args'][0] ) ? $path[7]['args'][0] : null;
		$form  = isset( $path[7]['args'][1] ) ? $path[7]['args'][1] : null;
		if ( ! is_object( $note ) || ! is_array( $notes ) || ! is_array( $entry ) || ! in_array( $note, $notes, true ) ) {
			return $format;
		}

		$raw = isset( $note->date_created ) && is_string( $note->date_created ) ? $note->date_created : null;
		if ( null === $raw || ! $this->first_path_arguments_match( $path, $raw ) ) {
			return $format;
		}

		$event_kind = $this->event_kind( $note, $entry, $raw );
		if ( null === $event_kind ) {
			return $format;
		}

		$timestamp = $this->expected_localized_timestamp( $raw );
		if ( null === $timestamp ) {
			return $format;
		}

		$entry_key   = $this->entry_key( $entry );
		$notes_order = $this->note_identity_order( $notes );
		if ( null === $entry_key || null === $notes_order ) {
			return $format;
		}

		$marked = $this->create_marked_format( $format );
		if ( null === $marked ) {
			return $format;
		}

		$this->contexts[ $marked['format'] ] = array(
			'note'          => $note,
			'note_snapshot' => get_object_vars( $note ),
			'notes'         => $notes,
			'notes_order'   => $notes_order,
			'entry'         => $entry,
			'entry_key'     => $entry_key,
			'form'          => $form,
			'raw'           => $raw,
			'timestamp'     => $timestamp,
			'native_format' => $format,
			'event_kind'    => $event_kind,
		);
		$this->marker_literals[ $marked['format'] ] = $marked['literal'];

		if ( ! $this->date_hook_registered ) {
			add_filter( 'date_i18n', array( $this, 'filter_date_i18n' ), PHP_INT_MAX, 4 );
			$this->date_hook_registered = true;
		}

		return $marked['format'];
	}

	/**
	 * Convert only the date carried by an authentic active Timeline marker.
	 *
	 * A matching owned token is consumed before later validation. Any later
	 * mismatch therefore fails closed and cannot replay or leak into another row.
	 *
	 * @param mixed $date      Native date_i18n output.
	 * @param mixed $format    Native date_i18n format.
	 * @param mixed $timestamp Localized timestamp-plus-offset value.
	 * @param mixed $gmt       Native date_i18n GMT flag.
	 * @return mixed
	 */
	public function filter_date_i18n( $date, $format, $timestamp, $gmt ) {
		$fallback = $this->strip_owned_markers( $date );
		if ( ! is_string( $format ) || empty( $this->contexts ) ) {
			return $fallback;
		}

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace -- Exact host caller-chain proof is the qualified production seam.
		$trace = debug_backtrace( 0, 60 );
		$this->prune_stale_contexts( $trace );
		$path = $this->nearest_contiguous_chain( $trace, self::SECOND_CHAIN );
		if ( null === $path || ! isset( $this->contexts[ $format ] ) ) {
			return $fallback;
		}

		$context = $this->contexts[ $format ];
		unset( $this->contexts[ $format ] );

		$note  = isset( $path[4]['args'][0] ) ? $path[4]['args'][0] : null;
		$notes = isset( $path[5]['args'][0] ) ? $path[5]['args'][0] : null;
		$entry = isset( $path[6]['args'][0] ) ? $path[6]['args'][0] : null;
		$form  = isset( $path[6]['args'][1] ) ? $path[6]['args'][1] : null;

		if (
			true !== $gmt ||
			! is_int( $timestamp ) ||
			$timestamp !== $context['timestamp'] ||
			! $this->is_exact_supported_host() ||
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
			return $fallback;
		}

		return null === $formatted ? $fallback : $formatted;
	}

	/**
	 * Find only the nearest occurrence of a chain head and require every next
	 * frame to be contiguous. A nested/re-entrant head may not borrow an outer
	 * caller further down the trace.
	 *
	 * @param array<int,array<string,mixed>> $trace Debug backtrace.
	 * @param array<int,string>              $expected Expected signatures.
	 * @return array<int,array<string,mixed>>|null
	 */
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

	/**
	 * Build one comparable frame signature.
	 *
	 * @param array<string,mixed> $frame Backtrace frame.
	 * @return string
	 */
	private function frame_signature( $frame ) {
		$class    = isset( $frame['class'] ) ? (string) $frame['class'] : '';
		$type     = isset( $frame['type'] ) ? (string) $frame['type'] : '';
		$function = isset( $frame['function'] ) ? (string) $frame['function'] : '';
		return $class . $type . $function;
	}

	/**
	 * Drop armed contexts whose exact owning Timeline row is no longer on-stack.
	 * Marker literals remain request-local so any stale native marker can still
	 * be stripped safely if it appears later.
	 *
	 * @param array<int,array<string,mixed>> $trace Debug backtrace.
	 * @return void
	 */
	private function prune_stale_contexts( $trace ) {
		foreach ( $this->contexts as $format => $context ) {
			$live = false;
			foreach ( $trace as $frame ) {
				if (
					'Gravity_Flow_Entry_Detail::get_note_body' === $this->frame_signature( $frame ) &&
					isset( $frame['args'][0] ) &&
					$frame['args'][0] === $context['note']
				) {
					$live = true;
					break;
				}
			}
			if ( ! $live ) {
				unset( $this->contexts[ $format ] );
			}
		}
	}

	/**
	 * Validate the exact first-seam host arguments discovered in qualification.
	 *
	 * @param array<int,array<string,mixed>> $path Qualified chain frames.
	 * @param string                         $raw  Authoritative raw timestamp.
	 * @return bool
	 */
	private function first_path_arguments_match( $path, $raw ) {
		return isset( $path[2]['args'], $path[3]['args'] ) &&
			array( $raw, false, '', true ) === $path[2]['args'] &&
			array( $raw, '', false, true ) === $path[3]['args'];
	}

	/**
	 * Validate the exact second-seam host arguments discovered in qualification.
	 *
	 * @param array<int,array<string,mixed>> $path    Qualified chain frames.
	 * @param array<string,mixed>            $context Owned row context.
	 * @param string                         $format  Exact marked format.
	 * @return bool
	 */
	private function second_path_arguments_match( $path, $context, $format ) {
		if ( ! isset( $path[1]['args'], $path[2]['args'] ) ) {
			return false;
		}

		$gf_args = $path[1]['args'];
		$raw     = $context['raw'];
		return in_array(
			$gf_args,
			array(
				array( $raw, false, '', true ),
				array( $raw, false, $format, true ),
			),
			true
		) && array( $raw, '', false, true ) === $path[2]['args'];
	}

	/**
	 * Classify and validate the exact authoritative Timeline event type.
	 *
	 * @param object       $note  Timeline note/event object.
	 * @param array<mixed> $entry Current Entry snapshot.
	 * @param string       $raw   Raw note date_created.
	 * @return string|null
	 */
	private function event_kind( $note, $entry, $raw ) {
		$id = $this->nonnegative_decimal_id( isset( $note->id ) ? $note->id : null );
		if ( null === $id ) {
			return null;
		}

		if ( '0' === $id ) {
			return isset( $entry['date_created'] ) && is_string( $entry['date_created'] ) && $raw === $entry['date_created'] ? 'initial' : null;
		}

		return isset( $note->note_type ) && 'gravityflow' === (string) $note->note_type ? 'stored' : null;
	}

	/**
	 * Strictly derive the host's expected localized timestamp-plus-offset value.
	 *
	 * @param string $raw UTC Y-m-d H:i:s source.
	 * @return int|null
	 */
	private function expected_localized_timestamp( $raw ) {
		try {
			$utc    = new DateTimeZone( 'UTC' );
			$source = DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', $raw, $utc );
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

	/**
	 * Create an unpredictable escaped literal marker for one Timeline row.
	 *
	 * @param string $native_format Qualified native format.
	 * @return array{format:string,literal:string}|null
	 */
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

	/**
	 * Remove every marker literal this request-local adapter owns.
	 *
	 * @param mixed $date Native formatted value.
	 * @return mixed
	 */
	private function strip_owned_markers( $date ) {
		if ( ! is_string( $date ) || empty( $this->marker_literals ) ) {
			return $date;
		}
		return str_replace( array_values( $this->marker_literals ), '', $date );
	}

	/**
	 * Snapshot exact note object identities and order independently of timestamps.
	 *
	 * @param array<mixed> $notes Timeline notes array.
	 * @return array<int,int>|null
	 */
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

	/**
	 * Bind Entry identity without loose numeric coercion.
	 *
	 * @param array<mixed> $entry Entry snapshot.
	 * @return string|null
	 */
	private function entry_key( $entry ) {
		if ( ! isset( $entry['id'], $entry['form_id'] ) ) {
			return null;
		}
		$id      = $this->positive_decimal_id( $entry['id'] );
		$form_id = $this->positive_decimal_id( $entry['form_id'] );
		return null === $id || null === $form_id ? null : $form_id . ':' . $id;
	}

	/**
	 * Normalize only canonical positive decimal IDs.
	 *
	 * @param mixed $value Candidate ID.
	 * @return string|null
	 */
	private function positive_decimal_id( $value ) {
		$normalized = $this->nonnegative_decimal_id( $value );
		return null !== $normalized && '0' !== $normalized ? $normalized : null;
	}

	/**
	 * Normalize only canonical non-negative decimal IDs.
	 *
	 * @param mixed $value Candidate ID.
	 * @return string|null
	 */
	private function nonnegative_decimal_id( $value ) {
		if ( is_int( $value ) ) {
			return $value >= 0 ? (string) $value : null;
		}
		if ( ! is_string( $value ) || 1 !== preg_match( '/^(?:0|[1-9][0-9]*)$/', $value ) ) {
			return null;
		}
		return $value;
	}

	/**
	 * Admit only the exact qualified Flow/GF source contract. The result is
	 * cached on this request-local adapter so source files are never re-hashed
	 * for each Timeline date.
	 *
	 * @return bool
	 */
	private function is_exact_supported_host() {
		if ( null !== $this->host_contract_valid ) {
			return $this->host_contract_valid;
		}

		$this->host_contract_valid = false;
		if (
			! defined( self::FLOW_VERSION_CONSTANT ) ||
			! defined( self::FLOW_BASENAME_CONSTANT ) ||
			! defined( 'WP_PLUGIN_DIR' ) ||
			! class_exists( 'GFForms', false ) ||
			self::FLOW_VERSION !== (string) constant( self::FLOW_VERSION_CONSTANT ) ||
			self::FLOW_BASENAME !== str_replace( '\\', '/', (string) constant( self::FLOW_BASENAME_CONSTANT ) ) ||
			self::GF_VERSION !== (string) GFForms::$version
		) {
			return false;
		}

		$flow_root = dirname( WP_PLUGIN_DIR . '/' . self::FLOW_BASENAME );
		try {
			$gf_reflection = new ReflectionClass( 'GFForms' );
			$gf_main       = $gf_reflection->getFileName();
		} catch ( ReflectionException $exception ) {
			unset( $exception );
			return false;
		}
		if ( ! is_string( $gf_main ) || '' === $gf_main ) {
			return false;
		}

		$paths = array(
			'flow_entry_detail' => $flow_root . '/includes/pages/class-entry-detail.php',
			'flow_common'       => $flow_root . '/includes/class-common.php',
			'flow_print'        => $flow_root . '/includes/pages/class-print-entries.php',
			'gf_common'         => dirname( $gf_main ) . '/common.php',
		);
		$actual = array();
		foreach ( $paths as $key => $path ) {
			if ( ! is_readable( $path ) ) {
				return false;
			}
			$hash = hash_file( 'sha256', $path );
			if ( ! is_string( $hash ) ) {
				return false;
			}
			$actual[ $key ] = $hash;
		}

		$this->host_contract_valid = $this->fingerprints_match( $actual );
		return $this->host_contract_valid;
	}

	/**
	 * Compare calculated source hashes to the exact qualified fingerprint set.
	 *
	 * @param array<string,string> $actual Calculated source hashes.
	 * @return bool
	 */
	private function fingerprints_match( $actual ) {
		return self::SOURCE_FINGERPRINTS === $actual;
	}
}
