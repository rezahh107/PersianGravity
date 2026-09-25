<?php
/**
 * Presentation-only Persian time-digit shaping for exact Gravity Flow Timeline headers.
 *
 * @package PersianGravityForms
 */

defined( 'ABSPATH' ) || exit;

final class PGR_Gravity_Flow_Timeline_Persian_Time_Digits_Presentation_Adapter {

	/** Host-owned runtime version authority. */
	private const HOST_VERSION_CONSTANT = 'GRAVITY_FLOW_VERSION';

	/** Host-owned plugin identity authority. */
	private const HOST_BASENAME_CONSTANT = 'GRAVITY_FLOW_PLUGIN_BASENAME';

	/** Browser adapter handle. */
	private const SCRIPT_HANDLE = 'pgr-gravity-flow-timeline-persian-time-digits';

	/** @var bool Whether the bounded browser adapter was already enqueued. */
	private $enqueued = false;

	/**
	 * Register only the source-authenticated Timeline presentation signal.
	 *
	 * The signal is emitted by the exact Timeline Jalali adapter only after its
	 * exact version/source/caller/context contract has successfully converted
	 * one authentic Timeline header date.
	 *
	 * @return void
	 */
	public function hooks() {
		add_action(
			'pgr_gravity_flow_timeline_header_presented',
			array( $this, 'enqueue_time_digit_shaper' ),
			PHP_INT_MAX,
			0
		);
	}

	/**
	 * Enqueue the header-only browser shaper for admitted Persian presentation.
	 *
	 * @return void
	 */
	public function enqueue_time_digit_shaper() {
		if (
			$this->enqueued ||
			! function_exists( 'determine_locale' ) ||
			'fa_IR' !== determine_locale() ||
			! $this->is_exact_supported_host() ||
			! defined( 'PGR_URL' ) ||
			! defined( 'PGR_VERSION' )
		) {
			return;
		}

		wp_enqueue_script(
			self::SCRIPT_HANDLE,
			PGR_URL . 'assets/js/pgr-flow-timeline-persian-time-digits.js',
			array(),
			PGR_VERSION,
			true
		);

		$this->enqueued = true;
	}

	/**
	 * Keep the browser shaper bounded to the exact admitted Gravity Flow target.
	 *
	 * Source/caller-chain authority is inherited from the Timeline Jalali
	 * adapter that exclusively emits the owned presentation signal.
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
}
