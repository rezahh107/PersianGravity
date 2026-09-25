<?php
/**
 * Bounded Jalali presentation for admitted GravityView system dates.
 *
 * @package PersianGravityForms
 */

defined( 'ABSPATH' ) || exit;

final class PGR_GravityView_Jalali_Presentation_Adapter {

	/** Exact qualified GravityView plugin identity. */
	private const HOST_PLUGIN_BASENAME = 'gravityview/gravityview.php';

	/** Exact admitted GravityView field identities. */
	private const DATE_CREATED = 'date_created';
	private const DATE_UPDATED = 'date_updated';

	/** @var bool|null */
	private $host_contract_valid = null;

	/**
	 * Register only the two exact qualified field-output seams.
	 *
	 * @return void
	 */
	public function hooks() {
		add_filter( 'gravityview/template/field/date_created/output', array( $this, 'filter_date_created_output' ), 20, 2 );
		add_filter( 'gravityview/template/field/date_updated/output', array( $this, 'filter_date_updated_output' ), 20, 2 );
	}

	/**
	 * Present GravityView's authoritative Entry date_created property as Jalali.
	 *
	 * @param mixed $output  Native GravityView display output.
	 * @param mixed $context GravityView Template_Context.
	 * @return mixed
	 */
	public function filter_date_created_output( $output, $context ) {
		return $this->filter_system_date_output( $output, $context, self::DATE_CREATED );
	}

	/**
	 * Present GravityView's authoritative Entry date_updated property as Jalali.
	 *
	 * @param mixed $output  Native GravityView display output.
	 * @param mixed $context GravityView Template_Context.
	 * @return mixed
	 */
	public function filter_date_updated_output( $output, $context ) {
		return $this->filter_system_date_output( $output, $context, self::DATE_UPDATED );
	}

	/**
	 * Convert only the exact admitted frontend field identity.
	 *
	 * Native GravityView output is always the fallback. The localized display
	 * string is never parsed; the authoritative raw Entry property is used.
	 *
	 * @param mixed  $output   Native GravityView display output.
	 * @param mixed  $context  GravityView Template_Context.
	 * @param string $field_id Exact field identity bound to the current hook.
	 * @return mixed
	 */
	private function filter_system_date_output( $output, $context, $field_id ) {
		if (
			! $this->is_eligible_frontend_context( $context, $field_id ) ||
			! $this->is_exact_supported_host() ||
			! class_exists( 'PGR_Jalali_Presentation', false )
		) {
			return $output;
		}

		$entry = $context->entry->as_entry();
		if ( ! is_array( $entry ) || ! isset( $entry['id'] ) || empty( $entry['id'] ) ) {
			return $output;
		}

		$raw = $entry[ $field_id ] ?? null;
		if ( ! is_string( $raw ) || '' === $raw ) {
			return $output;
		}

		$source = $this->parse_utc_datetime( $raw );
		if ( null === $source ) {
			return $output;
		}

		try {
			$presented = PGR_Jalali_Presentation::format_datetime( $source );
		} catch ( Throwable $exception ) {
			unset( $exception );
			return $output;
		}

		return null === $presented ? $output : $presented;
	}

	/**
	 * Require the exact qualified frontend/context/field boundary.
	 *
	 * @param mixed  $context  GravityView Template_Context.
	 * @param string $field_id Exact field identity bound to the current hook.
	 * @return bool
	 */
	private function is_eligible_frontend_context( $context, $field_id ) {
		if (
			! class_exists( 'PGR_Module_Registry', false ) ||
			! PGR_Module_Registry::is_enabled( 'jalali_presentation' ) ||
			! function_exists( 'determine_locale' ) ||
			'fa_IR' !== determine_locale() ||
			! function_exists( 'is_admin' ) ||
			is_admin() ||
			! is_object( $context ) ||
			! isset( $context->field, $context->view, $context->entry ) ||
			! is_object( $context->field ) ||
			! is_object( $context->view ) ||
			! is_object( $context->entry ) ||
			! method_exists( $context->entry, 'as_entry' )
		) {
			return false;
		}

		$field_type = isset( $context->field->type ) && is_scalar( $context->field->type )
			? (string) $context->field->type
			: '';
		$field_id_value = isset( $context->field->ID ) && is_scalar( $context->field->ID )
			? (string) $context->field->ID
			: '';

		return $field_id === $field_type && $field_id === $field_id_value;
	}

	/**
	 * Strictly parse Gravity Forms' authoritative UTC Y-m-d H:i:s value.
	 *
	 * @param string $raw Raw Entry property.
	 * @return DateTimeImmutable|null
	 */
	private function parse_utc_datetime( $raw ) {
		try {
			$source = DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', $raw, new DateTimeZone( 'UTC' ) );
		} catch ( Throwable $exception ) {
			unset( $exception );
			return null;
		}

		$errors = DateTimeImmutable::getLastErrors();
		if (
			false === $source ||
			( is_array( $errors ) && ( $errors['warning_count'] > 0 || $errors['error_count'] > 0 ) ) ||
			$source->format( 'Y-m-d H:i:s' ) !== $raw
		) {
			return null;
		}

		return $source;
	}

	/**
	 * Admit only the exact qualified GravityView product/version identity.
	 *
	 * Version authority remains the existing product registry. The exact plugin
	 * basename binds that manifest entry to the active host package. Header I/O
	 * is performed at most once per request-local adapter instance.
	 *
	 * @return bool
	 */
	private function is_exact_supported_host() {
		if ( null !== $this->host_contract_valid ) {
			return $this->host_contract_valid;
		}

		$this->host_contract_valid = false;
		if (
			! defined( 'PGR_PATH' ) ||
			! defined( 'WP_PLUGIN_DIR' ) ||
			! function_exists( 'get_file_data' )
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

		$product_slug = dirname( self::HOST_PLUGIN_BASENAME );
		$target       = '';
		foreach ( $products as $product ) {
			if ( ! is_array( $product ) || (string) ( $product['product'] ?? '' ) !== $product_slug ) {
				continue;
			}
			$target = isset( $product['target_version'] ) ? (string) $product['target_version'] : '';
			break;
		}
		if ( '' === $target ) {
			return false;
		}

		$plugin_file = WP_PLUGIN_DIR . '/' . self::HOST_PLUGIN_BASENAME;
		if ( ! is_readable( $plugin_file ) ) {
			return false;
		}

		$data = get_file_data(
			$plugin_file,
			array( 'Version' => 'Version' ),
			'plugin'
		);
		$version = is_array( $data ) && isset( $data['Version'] ) ? (string) $data['Version'] : '';

		$this->host_contract_valid = '' !== $version && $version === $target;
		return $this->host_contract_valid;
	}
}
