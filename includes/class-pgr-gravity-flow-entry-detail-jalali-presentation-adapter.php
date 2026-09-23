<?php
/**
 * Bounded Jalali presentation for exact Gravity Flow Entry Detail workflow-info dates.
 *
 * @package PersianGravityForms
 */

defined( 'ABSPATH' ) || exit;

final class PGR_Gravity_Flow_Entry_Detail_Jalali_Presentation_Adapter {

	/** Unique literal emitted only by the exact Entry Detail date-format seam. */
	private const MARKER_LITERAL = 'PGRJALALIENTRYDETAIL:';

	/** Host-owned runtime version authority. */
	private const HOST_VERSION_CONSTANT = 'GRAVITY_FLOW_VERSION';

	/** Host-owned plugin identity authority. */
	private const HOST_BASENAME_CONSTANT = 'GRAVITY_FLOW_PLUGIN_BASENAME';

	/**
	 * Register only the composed Entry Detail presentation seams.
	 *
	 * @return void
	 */
	public function hooks() {
		add_filter( 'gravityflow_date_format_entry_detail', array( $this, 'filter_entry_detail_date_format' ), PHP_INT_MAX, 1 );
		add_filter( 'date_i18n', array( $this, 'filter_marked_date' ), PHP_INT_MAX, 4 );
	}

	/**
	 * Prefix Gravity Flow's shared workflow-info date format with a unique,
	 * escaped literal marker. Non-empty host/plugin overrides are preserved.
	 *
	 * @param mixed $format Native date format.
	 * @return mixed
	 */
	public function filter_entry_detail_date_format( $format ) {
		if (
			'' !== $format ||
			! $this->is_exact_supported_host() ||
			! class_exists( 'GFCommon', false ) ||
			! class_exists( 'PGR_Jalali_Presentation', false )
		) {
			return $format;
		}

		$native_format = GFCommon::get_default_date_format();
		if ( ! is_string( $native_format ) || '' === $native_format ) {
			return $format;
		}

		return $this->marked_format( $native_format );
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
		unset( $gmt );

		if (
			! $this->is_exact_supported_host() ||
			! class_exists( 'GFCommon', false ) ||
			! class_exists( 'PGR_Jalali_Presentation', false ) ||
			! is_string( $format )
		) {
			return $date;
		}

		$native_format = GFCommon::get_default_date_format();
		if (
			! is_string( $native_format ) ||
			'' === $native_format ||
			$format !== $this->marked_format( $native_format )
		) {
			return $date;
		}

		$fallback = $this->strip_marker( $date );

		if ( is_int( $timestamp ) ) {
			$local_timestamp = $timestamp;
		} elseif ( is_string( $timestamp ) && 1 === preg_match( '/^-?[0-9]+$/', $timestamp ) ) {
			$local_timestamp = (int) $timestamp;
		} else {
			return $fallback;
		}

		$local_civil = gmdate( 'Y-m-d H:i:s', $local_timestamp );
		if ( ! is_string( $local_civil ) || 19 !== strlen( $local_civil ) ) {
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
			return $fallback;
		}

		return null === $formatted ? $fallback : $formatted;
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

	/**
	 * Admit only the exact host product/version already owned by the product
	 * registry. Missing or drifted host identity always keeps native output.
	 *
	 * @return bool
	 */
	private function is_exact_supported_host() {
		if (
			! defined( self::HOST_VERSION_CONSTANT ) ||
			! defined( self::HOST_BASENAME_CONSTANT ) ||
			! defined( 'PGR_PATH' )
		) {
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
		$target   = '';
		foreach ( $products as $product ) {
			if ( ( $product['product'] ?? '' ) !== $product_slug ) {
				continue;
			}
			$target = isset( $product['target_version'] ) ? (string) $product['target_version'] : '';
			break;
		}

		return '' !== $target && (string) constant( self::HOST_VERSION_CONSTANT ) === $target;
	}
}
