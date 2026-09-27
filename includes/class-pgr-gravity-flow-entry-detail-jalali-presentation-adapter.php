<?php
/**
 * Bounded Jalali presentation for qualified Gravity Flow Entry Detail workflow-info dates.
 *
 * @package PersianGravityForms
 */

defined( 'ABSPATH' ) || exit;

final class PGR_Gravity_Flow_Entry_Detail_Jalali_Presentation_Adapter {

	/** Unique literal emitted only by the exact Entry Detail date-format seam. */
	private const MARKER_LITERAL = 'PGRJALALIENTRYDETAIL:';

	/** Host-owned runtime version observed against repository qualification authority. */
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
	 * Marker ownership is admitted only for the exact repository-qualified Flow
	 * version because this shared seam has no semantic row identity. This keeps a
	 * future host from adding another workflow-info date surface that would be
	 * converted before the post-render four-call reconciliation could detect it.
	 *
	 * @param mixed $format Native date format.
	 * @return mixed
	 */
	public function filter_entry_detail_date_format( $format ) {
		$this->reset_marker_context();

		if ( '' !== $format || ! $this->is_exact_qualified_date_family_host() ) {
			return $format;
		}

		if ( ! class_exists( 'GFCommon', false ) || ! class_exists( 'PGR_Jalali_Presentation', false ) ) {
			return $format;
		}

		$native_format = GFCommon::get_default_date_format();
		if ( ! is_string( $native_format ) || '' === $native_format ) {
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
			return $this->is_owned_marked_format( $format ) ? $this->strip_marker( $date ) : $date;
		}

		$fallback = $this->strip_marker( $date );

		if ( ! $this->is_exact_qualified_date_family_host() ) {
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
	 * Exact Flow 3.1.0 qualification proves that a complete four-row workflow-info
	 * render routes Submitted, Last Updated, Due and Expiration through the shared
	 * format/date_i18n chain before this action. The shared hooks do not identify a
	 * semantic row, so incomplete renders deliberately emit no per-capability
	 * observation instead of guessing which capability was evaluated.
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

		if ( 4 !== count( $results ) ) {
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
		}
	}

	/**
	 * Build the exact escaped marker format consumed by date_i18n.
	 *
	 * @param string $native_format Gravity Forms' native date format.
	 * @return string
	 */
	private function marked_format( $native_format ) {
		return $this->escaped_marker() . $native_format;
	}

	/**
	 * Return the escaped literal prefix used only by this adapter.
	 *
	 * @return string
	 */
	private function escaped_marker() {
		$marker = '';
		foreach ( str_split( self::MARKER_LITERAL ) as $character ) {
			$marker .= '\\' . $character;
		}

		return $marker;
	}

	/**
	 * Determine whether the incoming format belongs to this adapter's marker
	 * family even when its request-local active context is stale or disarmed.
	 *
	 * @param mixed $format Native date_i18n format.
	 * @return bool
	 */
	private function is_owned_marked_format( $format ) {
		return is_string( $format ) && 0 === strpos( $format, $this->escaped_marker() );
	}

	/**
	 * Remove this adapter's marker from native output before any owned fallback.
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
	 * Validate the canonical Gravity Flow product identity before consulting the
	 * repository-owned exact qualification target.
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
	 * Admit marker ownership only for the exact Gravity Flow version already
	 * qualified by the repository localization/product authority. The version is
	 * never duplicated here; version drift fails closed before marker ownership.
	 *
	 * @return bool
	 */
	private function is_exact_qualified_date_family_host() {
		if ( ! $this->has_qualified_host_identity() || ! defined( 'PGR_PATH' ) ) {
			return false;
		}

		$registry_path = PGR_PATH . 'includes/localization/products.php';
		if ( ! is_readable( $registry_path ) ) {
			return false;
		}

		$plugin_basename = str_replace( '\\', '/', (string) constant( self::HOST_BASENAME_CONSTANT ) );
		$product_slug    = dirname( $plugin_basename );
		if ( '' === $product_slug || '.' === $product_slug || '/' === $product_slug ) {
			return false;
		}

		$products = require $registry_path;
		if ( ! is_array( $products ) ) {
			return false;
		}

		$target = '';
		foreach ( $products as $product ) {
			if ( ! is_array( $product ) || ( $product['product'] ?? '' ) !== $product_slug ) {
				continue;
			}

			$target = isset( $product['target_version'] ) ? (string) $product['target_version'] : '';
			break;
		}

		return '' !== $target && (string) constant( self::HOST_VERSION_CONSTANT ) === $target;
	}

	/**
	 * Record one aggregate observation for the four shared-contract date IDs.
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
