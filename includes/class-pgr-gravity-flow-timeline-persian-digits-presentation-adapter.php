<?php
/**
 * Presentation-only Persian time digit shaping for exact Gravity Flow Timeline headers.
 *
 * @package PersianGravityForms
 */

defined( 'ABSPATH' ) || exit;

final class PGR_Gravity_Flow_Timeline_Persian_Digits_Presentation_Adapter {

	/** Host-owned runtime version authority. */
	private const FLOW_VERSION_CONSTANT = 'GRAVITY_FLOW_VERSION';

	/** Host-owned plugin identity authority. */
	private const FLOW_BASENAME_CONSTANT = 'GRAVITY_FLOW_PLUGIN_BASENAME';

	/** Exact qualified host versions. */
	private const FLOW_VERSION = '3.1.0';
	private const GF_VERSION   = '3.1.1.1';

	/** Exact qualified source fingerprints shared with Timeline calendar admission. */
	private const SOURCE_FINGERPRINTS = array(
		'flow_entry_detail' => 'a7634c5604184502457bcb22cdf1ade892e84c888cc60996aea8a810ced7680a',
		'flow_common'       => 'a8844f4b6ac37eed1a2cc1e904418f6ed8c6b4480e982c5a809e895a6f9e0cc8',
		'flow_print'        => 'df969bf8a37ed4f5619e0e8b2a753dd1133fa0c7551b158d740248c67fcb95c6',
		'gf_common'         => 'ac4ed495ee02a119a4fd08c77279f0db0905e6f20f59c472d5e6bffc801ca355',
	);

	/** Exact time-format lookup caller chain, nearest frame first. */
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

	/** Exact time-render caller chain, nearest frame first. */
	private const DATE_I18N_CHAIN = array(
		'date_i18n',
		'GFCommon::format_date',
		'Gravity_Flow_Common::format_date',
		'Gravity_Flow_Entry_Detail::get_note_header',
		'Gravity_Flow_Entry_Detail::get_note_body',
		'Gravity_Flow_Entry_Detail::notes_grid',
		'Gravity_Flow_Entry_Detail::timeline',
	);

	/** @var array<int,array<string,mixed>> Pending row-local time contexts keyed by note object ID. */
	private $contexts = array();

	/** @var bool|null Cached exact host/source contract result. */
	private $host_contract_valid = null;

	/**
	 * Register only the exact Persian Timeline time presentation seam.
	 *
	 * @return void
	 */
	public function hooks() {
		if (
			! function_exists( 'determine_locale' ) ||
			'fa_IR' !== determine_locale() ||
			! $this->is_exact_supported_host()
		) {
			return;
		}

		add_filter( 'option_time_format', array( $this, 'capture_time_format' ), PHP_INT_MAX, 2 );
		add_filter( 'date_i18n', array( $this, 'shape_timeline_time_digits' ), PHP_INT_MAX, 4 );
	}

	/**
	 * Capture the exact row-local host time format without changing it.
	 *
	 * @param mixed $format Native WordPress time-format option value.
	 * @param mixed $option Option name.
	 * @return mixed
	 */
	public function capture_time_format( $format, $option = null ) {
		if (
			'time_format' !== $option ||
			! is_string( $format ) ||
			'' === $format ||
			! function_exists( 'determine_locale' ) ||
			'fa_IR' !== determine_locale() ||
			! $this->is_exact_supported_host()
		) {
			return $format;
		}

		$trace = $this->capture_trace();
		$this->prune_stale_contexts( $trace );
		$path = $this->nearest_contiguous_chain( $trace, self::TIME_FORMAT_CHAIN );
		if ( null === $path ) {
			return $format;
		}

		$note  = $path[5]['args'][0] ?? null;
		$notes = $path[6]['args'][0] ?? null;
		$entry = $path[7]['args'][0] ?? null;
		$form  = $path[7]['args'][1] ?? null;
		$raw   = $path[4]['args'][1] ?? null;
		if (
			! is_object( $note ) ||
			! is_array( $notes ) ||
			! is_array( $entry ) ||
			! is_string( $raw ) ||
			! isset( $note->date_created ) ||
			(string) $note->date_created !== $raw ||
			! in_array( $note, $notes, true ) ||
			! $this->path_arguments_match( $path, $raw )
		) {
			return $format;
		}

		$timestamp = $this->expected_localized_timestamp( $raw );
		if ( null === $timestamp ) {
			return $format;
		}

		$this->contexts[ spl_object_id( $note ) ] = array(
			'note'          => $note,
			'note_snapshot' => get_object_vars( $note ),
			'notes'         => $notes,
			'notes_order'   => $this->note_identity_order( $notes ),
			'entry'         => $entry,
			'entry_key'     => $this->entry_key( $entry ),
			'form'          => $form,
			'raw'           => $raw,
			'timestamp'     => $timestamp,
			'time_format'   => $format,
		);

		return $format;
	}

	/**
	 * Shape only the exact host-owned Timeline time string.
	 *
	 * Calendar conversion remains owned by the separate Timeline Jalali adapter.
	 * This method performs glyph substitution only; it never parses or recalculates
	 * a time value.
	 *
	 * @param mixed $date      Native date_i18n output.
	 * @param mixed $format    Native date_i18n format.
	 * @param mixed $timestamp Localized timestamp-plus-offset value.
	 * @param mixed $gmt       Native date_i18n GMT flag.
	 * @return mixed
	 */
	public function shape_timeline_time_digits( $date, $format, $timestamp, $gmt ) {
		if (
			! is_string( $date ) ||
			! is_string( $format ) ||
			! function_exists( 'determine_locale' ) ||
			'fa_IR' !== determine_locale() ||
			! $this->is_exact_supported_host() ||
			empty( $this->contexts )
		) {
			return $date;
		}

		$trace = $this->capture_trace();
		$this->prune_stale_contexts( $trace );
		$path = $this->nearest_contiguous_chain( $trace, self::DATE_I18N_CHAIN );
		if ( null === $path ) {
			return $date;
		}

		$note = $path[4]['args'][0] ?? null;
		if ( ! is_object( $note ) ) {
			return $date;
		}

		$key = spl_object_id( $note );
		if ( ! isset( $this->contexts[ $key ] ) ) {
			return $date;
		}

		$context = $this->contexts[ $key ];
		if ( $format !== $context['time_format'] ) {
			return $date;
		}

		unset( $this->contexts[ $key ] );

		$notes = $path[5]['args'][0] ?? null;
		$entry = $path[6]['args'][0] ?? null;
		$form  = $path[6]['args'][1] ?? null;
		if (
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
			! $this->date_i18n_path_arguments_match( $path, $context['raw'] )
		) {
			return $date;
		}

		return $this->shape_ascii_digits( $date );
	}

	/**
	 * Shape ASCII glyphs only; already-Persian digits and all non-digits survive unchanged.
	 *
	 * @param string $value Human-visible time string.
	 * @return string
	 */
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

	/**
	 * Capture the bounded request stack used to prove the exact host path.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function capture_trace() {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace -- Qualified caller-chain proof.
		return debug_backtrace( 0, 60 );
	}

	/**
	 * Require the nearest matching head to own the complete contiguous chain.
	 *
	 * @param array<int,array<string,mixed>> $trace    Debug backtrace.
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
	 * Build one comparable backtrace signature.
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
	 * Drop row contexts once their owning get_note_body frame is no longer active.
	 *
	 * @param array<int,array<string,mixed>> $trace Debug backtrace.
	 * @return void
	 */
	private function prune_stale_contexts( $trace ) {
		foreach ( $this->contexts as $key => $context ) {
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
				unset( $this->contexts[ $key ] );
			}
		}
	}

	/**
	 * Validate first-seam host arguments.
	 *
	 * @param array<int,array<string,mixed>> $path Qualified time-format chain.
	 * @param string                         $raw  Raw note date_created.
	 * @return bool
	 */
	private function path_arguments_match( $path, $raw ) {
		return isset( $path[2]['args'], $path[3]['args'] ) &&
			array( $raw, false, '', true ) === $path[2]['args'] &&
			array( $raw, '', false, true ) === $path[3]['args'];
	}

	/**
	 * Validate second-seam host arguments.
	 *
	 * @param array<int,array<string,mixed>> $path Qualified date_i18n chain.
	 * @param string                         $raw  Raw note date_created.
	 * @return bool
	 */
	private function date_i18n_path_arguments_match( $path, $raw ) {
		return isset( $path[1]['args'], $path[2]['args'] ) &&
			array( $raw, false, '', true ) === $path[1]['args'] &&
			array( $raw, '', false, true ) === $path[2]['args'];
	}

	/**
	 * Derive the same localized timestamp-plus-offset consumed by GF date_i18n.
	 *
	 * @param string $raw UTC Y-m-d H:i:s.
	 * @return int|null
	 */
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
		if ( is_int( $value ) ) {
			return $value > 0 ? (string) $value : null;
		}

		if ( ! is_string( $value ) || 1 !== preg_match( '/^[1-9][0-9]*$/', $value ) ) {
			return null;
		}

		return $value;
	}

	/**
	 * Admit only the exact qualified Flow/GF source contract.
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
			! defined( 'PGR_PATH' ) ||
			! defined( 'WP_PLUGIN_DIR' ) ||
			! class_exists( 'GFForms', false )
		) {
			return false;
		}

		$registry_path = PGR_PATH . 'includes/localization/products.php';
		if ( ! is_readable( $registry_path ) ) {
			return false;
		}

		$products = require $registry_path;
		if ( ! is_array( $products ) ) {
			return false;
		}

		$flow_version  = (string) constant( self::FLOW_VERSION_CONSTANT );
		$gf_version    = (string) GFForms::$version;
		$flow_basename = str_replace( '\\', '/', (string) constant( self::FLOW_BASENAME_CONSTANT ) );
		if ( self::FLOW_VERSION !== $flow_version || self::GF_VERSION !== $gf_version ) {
			return false;
		}

		$product_slug = dirname( $flow_basename );
		if ( '' === $product_slug || '.' === $product_slug || '/' === $product_slug || false !== strpos( $flow_basename, '..' ) ) {
			return false;
		}

		$manifest_match = false;
		foreach ( $products as $product ) {
			if (
				is_array( $product ) &&
				(string) ( $product['product'] ?? '' ) === $product_slug &&
				self::FLOW_VERSION === (string) ( $product['target_version'] ?? '' )
			) {
				$manifest_match = true;
				break;
			}
		}
		if ( ! $manifest_match ) {
			return false;
		}

		$flow_root = dirname( WP_PLUGIN_DIR . '/' . $flow_basename );
		try {
			$gf_main = ( new ReflectionClass( 'GFForms' ) )->getFileName();
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

		$this->host_contract_valid = self::SOURCE_FINGERPRINTS === $actual;
		return $this->host_contract_valid;
	}
}
