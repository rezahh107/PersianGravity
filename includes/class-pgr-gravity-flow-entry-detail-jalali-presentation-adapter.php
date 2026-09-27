<?php
/**
 * Bounded Jalali presentation for Gravity Flow Entry Detail workflow-info dates.
 *
 * @package PersianGravityForms
 */

defined( 'ABSPATH' ) || exit;

final class PGR_Gravity_Flow_Entry_Detail_Jalali_Presentation_Adapter {

	/** Unique literal emitted only by the exact Entry Detail date-format seam. */
	private const MARKER_LITERAL = 'PGRJALALIENTRYDETAIL:';

	/** Host-owned runtime version observation. Version equality is not eligibility. */
	private const HOST_VERSION_CONSTANT = 'GRAVITY_FLOW_VERSION';

	/** Host-owned plugin identity authority. */
	private const HOST_BASENAME_CONSTANT = 'GRAVITY_FLOW_PLUGIN_BASENAME';

	/** Canonical Gravity Flow plugin main-file identity. */
	private const HOST_PLUGIN_BASENAME = 'gravityflow/gravityflow.php';

	private const CAPABILITIES = array(
		'gravityflow.entry-detail.submitted',
		'gravityflow.entry-detail.last-updated',
		'gravityflow.entry-detail.due-date',
		'gravityflow.entry-detail.expiration',
	);

	/** @var string|null Exact marker format armed by the qualified Flow hook. */
	private $active_marker_format = null;

	/** @var array<int,string|null> Request-local marked-call outcomes. */
	private $marked_call_results = array();

	/**
	 * Register the composed Entry Detail presentation seams plus a diagnostics-only
	 * post-render observer. The observer never authorizes or changes presentation.
	 *
	 * @return void
	 */
	public function hooks() {
		add_filter( 'gravityflow_date_format_entry_detail', array( $this, 'filter_entry_detail_date_format' ), PHP_INT_MAX, 1 );
		add_filter( 'date_i18n', array( $this, 'filter_marked_date' ), PHP_INT_MAX, 4 );
		add_action(
			'gravityflow_below_workflow_info_entry_detail',
			array( $this, 'finalize_date_family_diagnostics' ),
			PHP_INT_MAX,
			3
		);
	}

	/**
	 * Prefix Gravity Flow's shared workflow-info date format with a unique,
	 * escaped literal marker. Non-empty host/plugin overrides are preserved.
	 *
	 * @param mixed $format Native date format.
	 * @return mixed
	 */
	public function filter_entry_detail_date_format( $format ) {
		$this->reset_marker_context();

		if ( '' !== $format ) {
			$this->record_all_diagnostics( 'STATE_DEGRADED', 'REASON_CONTEXT_UNAVAILABLE' );
			return $format;
		}

		if ( ! $this->has_qualified_host_identity() ) {
			$this->record_all_diagnostics( 'STATE_UNAVAILABLE', 'REASON_HOST_UNQUALIFIED' );
			return $format;
		}

		if ( ! class_exists( 'GFCommon', false ) ) {
			$this->record_all_diagnostics( 'STATE_DEGRADED', 'REASON_SEAM_UNAVAILABLE' );
			return $format;
		}

		if ( ! class_exists( 'PGR_Jalali_Presentation', false ) ) {
			$this->record_all_diagnostics( 'STATE_DEGRADED', 'REASON_CONVERSION_UNAVAILABLE' );
			return $format;
		}

		$native_format = GFCommon::get_default_date_format();
		if ( ! is_string( $native_format ) || '' === $native_format ) {
			$this->record_all_diagnostics( 'STATE_DEGRADED', 'REASON_SEAM_UNAVAILABLE' );
			return $format;
		}

		$this->active_marker_format = $this->marked_format( $native_format );

		return $this->active_marker_format;
	}

	/**
	 * Replace only the date component that carries this adapter's exact marker.
	 *
	 * WordPress date_i18n receives a localized timestamp-plus-offset value for
	 * this path. Its UTC components therefore represent the already-localized
	 * Gregorian civil date. Reading those components with gmdate() avoids a
	 * second timezone conversion. Gravity Flow's surrounding native time output
	 * remains untouched.
	 *
	 * @param mixed $date      Native date_i18n output.
	 * @param mixed $format    Native date_i18n format.
	 * @param mixed $timestamp Localized timestamp-plus-offset value.
	 * @param mixed $gmt       Native date_i18n GMT flag.
	 * @return mixed
	 */
	public function filter_marked_date( $date, $format, $timestamp, $gmt ) {
		if (
			null === $this->active_marker_format ||
			! is_string( $format ) ||
			$format !== $this->active_marker_format
		) {
			return $date;
		}

		$fallback = $this->strip_marker( $date );

		if ( ! $this->has_qualified_host_identity() ) {
			$this->marked_call_results[] = 'REASON_HOST_UNQUALIFIED';
			return $fallback;
		}

		if ( true !== $gmt || ! is_int( $timestamp ) ) {
			$this->marked_call_results[] = 'REASON_SOURCE_INVALID';
			return $fallback;
		}

		if ( ! class_exists( 'PGR_Jalali_Presentation', false ) ) {
			$this->marked_call_results[] = 'REASON_CONVERSION_UNAVAILABLE';
			return $fallback;
		}

		$local_civil = gmdate( 'Y-m-d H:i:s', $timestamp );
		if ( ! is_string( $local_civil ) || 19 !== strlen( $local_civil ) ) {
			$this->marked_call_results[] = 'REASON_SOURCE_INVALID';
			return $fallback;
		}

		try {
			$formatted = PGR_Jalali_Presentation::format_date(
				(int) substr( $local_civil, 0, 4 ),
				(int) substr( $local_civil, 5, 2 ),
				(int) substr( $local_civil, 8, 2 )
			);
		} catch ( Throwable $exception ) {
			unset( $exception );
			$this->marked_call_results[] = 'REASON_CONVERSION_UNAVAILABLE';
			return $fallback;
		}

		if ( null === $formatted ) {
			$this->marked_call_results[] = 'REASON_CONVERSION_UNAVAILABLE';
			return $fallback;
		}

		$this->marked_call_results[] = null;
		return $formatted;
	}

	/**
	 * Finalize per-capability reporting only after the exact host post-render seam.
	 *
	 * Exact Flow 3.1.0 qualification proves a complete workflow-info render routes
	 * Submitted, Last Updated, Due and Expiration through the shared format and
	 * date_i18n chain before this action. A synthetic version-string change does
	 * not change that runtime contract. If the aggregate observation cannot prove
	 * all four capabilities equally, the per-field report is deliberately marked
	 * context-unavailable rather than guessing which field drifted.
	 *
	 * @param mixed $form         Host form.
	 * @param mixed $entry        Host entry.
	 * @param mixed $current_step Host current step.
	 * @return void
	 */
	public function finalize_date_family_diagnostics( $form, $entry, $current_step ) {
		unset( $form, $entry, $current_step );

		if ( null === $this->active_marker_format ) {
			return;
		}

		$results = $this->marked_call_results;
		$this->reset_marker_context();

		if ( ! $this->has_qualified_host_identity() ) {
			$this->record_all_diagnostics( 'STATE_UNAVAILABLE', 'REASON_HOST_UNQUALIFIED' );
			return;
		}

		if ( 4 !== count( $results ) ) {
			$this->record_all_diagnostics( 'STATE_DEGRADED', 'REASON_CONTEXT_UNAVAILABLE' );
			return;
		}

		$failures = array_values(
			array_filter(
				$results,
				static function ( $reason ) {
					return null !== $reason;
				}
			)
		);

		if ( array() === $failures ) {
			$this->record_all_diagnostics( 'STATE_AVAILABLE', 'REASON_CONTRACT_SATISFIED' );
			return;
		}

		$unique_failures = array_values( array_unique( $failures ) );
		if ( 4 === count( $failures ) && 1 === count( $unique_failures ) ) {
			$reason = $unique_failures[0];
			if ( 'REASON_HOST_UNQUALIFIED' === $reason ) {
				$this->record_all_diagnostics( 'STATE_UNAVAILABLE', $reason );
				return;
			}

			$this->record_all_diagnostics( 'STATE_DEGRADED', $reason );
			return;
		}

		$this->record_all_diagnostics( 'STATE_DEGRADED', 'REASON_CONTEXT_UNAVAILABLE' );
	}

	/**
	 * Build the exact escaped marker format consumed by date_i18n.
	 *
	 * @param string $native_format Gravity Forms' native date format.
	 * @return string
	 */
	private function marked_format( $native_format ) {
		$marker = '';
		foreach ( str_split( self::MARKER_LITERAL ) as $character ) {
			$marker .= '\\' . $character;
		}

		return $marker . $native_format;
	}

	/**
	 * Remove this adapter's marker from native output before any fallback.
	 *
	 * @param mixed $date Native date_i18n output.
	 * @return mixed
	 */
	private function strip_marker( $date ) {
		if ( ! is_string( $date ) ) {
			return $date;
		}

		return str_replace( self::MARKER_LITERAL, '', $date );
	}

	/** @return void */
	private function reset_marker_context() {
		$this->active_marker_format = null;
		$this->marked_call_results  = array();
	}

	/**
	 * Validate Gravity Flow product identity without making version equality an
	 * activation oracle. The composed callback/context/data contract supplies
	 * capability compatibility; the version remains bounded provenance only.
	 *
	 * @return bool
	 */
	private function has_qualified_host_identity() {
		if ( ! defined( self::HOST_VERSION_CONSTANT ) || ! defined( self::HOST_BASENAME_CONSTANT ) ) {
			return false;
		}

		$version = trim( (string) constant( self::HOST_VERSION_CONSTANT ) );
		if ( '' === $version || strlen( $version ) > 32 || 1 !== preg_match( '/^[A-Za-z0-9][A-Za-z0-9._+\-]*$/', $version ) ) {
			return false;
		}

		$plugin_basename = str_replace( '\\', '/', (string) constant( self::HOST_BASENAME_CONSTANT ) );
		return self::HOST_PLUGIN_BASENAME === $plugin_basename;
	}

	/**
	 * Record the same observation for all four shared-contract date capabilities.
	 *
	 * @param string $state_constant  Diagnostics state constant name.
	 * @param string $reason_constant Diagnostics reason constant name.
	 * @return void
	 */
	private function record_all_diagnostics( $state_constant, $reason_constant ) {
		if ( ! class_exists( 'PGR_Gravity_Flow_Compatibility_Diagnostics', false ) ) {
			return;
		}

		$state  = constant( 'PGR_Gravity_Flow_Compatibility_Diagnostics::' . $state_constant );
		$reason = constant( 'PGR_Gravity_Flow_Compatibility_Diagnostics::' . $reason_constant );

		foreach ( self::CAPABILITIES as $capability_id ) {
			PGR_Gravity_Flow_Compatibility_Diagnostics::record( $capability_id, $state, $reason );
		}
	}
}
