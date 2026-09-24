<?php
/**
 * Presentation-only Persian digit shaping for exact Gravity Flow Entry Detail workflow info.
 *
 * @package PersianGravityForms
 */

defined( 'ABSPATH' ) || exit;

final class PGR_Gravity_Flow_Entry_Detail_Persian_Digits_Adapter {

	/** Browser asset handle. */
	private const SCRIPT_HANDLE = 'pgr-gravity-flow-entry-detail-persian-digits';

	/** Host-owned runtime version authority. */
	private const HOST_VERSION_CONSTANT = 'GRAVITY_FLOW_VERSION';

	/** Host-owned plugin identity authority. */
	private const HOST_BASENAME_CONSTANT = 'GRAVITY_FLOW_PLUGIN_BASENAME';

	/**
	 * Register only the exact workflow-info completion seam.
	 *
	 * @return void
	 */
	public function hooks() {
		add_action(
			'gravityflow_below_workflow_info_entry_detail',
			array( $this, 'enqueue_presentation_script' ),
			PHP_INT_MAX,
			3
		);
	}

	/**
	 * Enqueue the presentation adapter only after Gravity Flow rendered the
	 * qualified workflow-info family in a Persian/RTL request.
	 *
	 * The browser adapter changes Text nodes only. This callback never reads,
	 * rewrites, or re-emits Entry/workflow values.
	 *
	 * @param mixed $form         Host form.
	 * @param mixed $entry        Host entry.
	 * @param mixed $current_step Host current step.
	 * @return void
	 */
	public function enqueue_presentation_script( $form, $entry, $current_step ) {
		unset( $form, $entry, $current_step );

		if (
			! $this->is_exact_supported_host() ||
			! function_exists( 'determine_locale' ) ||
			'fa_IR' !== determine_locale() ||
			! function_exists( 'is_rtl' ) ||
			! is_rtl() ||
			! defined( 'PGR_URL' ) ||
			! defined( 'PGR_VERSION' )
		) {
			return;
		}

		wp_enqueue_script(
			self::SCRIPT_HANDLE,
			PGR_URL . 'assets/js/pgr-gravity-flow-entry-detail-persian-digits.js',
			array(),
			PGR_VERSION,
			true
		);
	}

	/**
	 * Admit only the exact Gravity Flow product/version already owned by the
	 * product registry. Missing or drifted host identity keeps native output.
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
