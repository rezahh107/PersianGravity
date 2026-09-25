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

	/** @var bool Whether an authentic qualified Timeline header was presented. */
	private $qualified_timeline_presented = false;

	/** @var bool Whether the bounded Entry Detail browser adapter was enqueued. */
	private $enqueued = false;

	/** @var bool Whether the standalone Print page script tag was emitted. */
	private $print_script_emitted = false;

	/**
	 * Register only bounded post-render presentation hooks.
	 *
	 * The internal signal is emitted by the exact Timeline Jalali adapter only
	 * after its exact version/source/caller/context contract successfully
	 * converts one authentic Timeline header date.
	 *
	 * @return void
	 */
	public function hooks() {
		add_action(
			'pgr_gravity_flow_timeline_header_presented',
			array( $this, 'mark_qualified_timeline_presentation' ),
			PHP_INT_MAX,
			0
		);
		add_action(
			'gravityflow_entry_detail_content_after',
			array( $this, 'enqueue_entry_detail_shaper' ),
			PHP_INT_MAX,
			2
		);
		add_action(
			'gravityflow_print_entry_footer',
			array( $this, 'print_standalone_shaper' ),
			PHP_INT_MAX,
			2
		);
	}

	/**
	 * Mark one authentic Timeline render as eligible for glyph shaping.
	 *
	 * @return void
	 */
	public function mark_qualified_timeline_presentation() {
		$this->qualified_timeline_presented = true;
	}

	/**
	 * Enqueue the header-only shaper after Entry Detail Timeline rendering.
	 *
	 * @param mixed $form  Host form.
	 * @param mixed $entry Host entry.
	 * @return void
	 */
	public function enqueue_entry_detail_shaper( $form, $entry ) {
		unset( $form, $entry );

		if ( ! $this->consume_qualified_presentation() || $this->enqueued || ! $this->can_shape() ) {
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
	 * Print the same owned shaper on Gravity Flow's standalone Print page.
	 *
	 * Exact Flow 3.1.0 does not call WordPress footer script printers on this
	 * document. Its entry-footer action runs after the optional Timeline render,
	 * so an authenticated Timeline signal can safely emit one owned script tag.
	 * The browser adapter defers execution until DOMContentLoaded, covering later
	 * entries in the same bulk-print document without broad DOM interception.
	 *
	 * @param mixed $form  Host form.
	 * @param mixed $entry Host entry.
	 * @return void
	 */
	public function print_standalone_shaper( $form, $entry ) {
		unset( $form, $entry );

		if ( ! $this->consume_qualified_presentation() || $this->print_script_emitted || ! $this->can_shape() ) {
			return;
		}

		wp_enqueue_script(
			self::SCRIPT_HANDLE,
			PGR_URL . 'assets/js/pgr-flow-timeline-persian-time-digits.js',
			array(),
			PGR_VERSION,
			false
		);
		wp_print_scripts( self::SCRIPT_HANDLE );

		$this->print_script_emitted = true;
	}

	/**
	 * Consume the row-render signal so unrelated later host actions cannot reuse
	 * stale Timeline presentation authority.
	 *
	 * @return bool
	 */
	private function consume_qualified_presentation() {
		$qualified                          = $this->qualified_timeline_presented;
		$this->qualified_timeline_presented = false;
		return $qualified;
	}

	/**
	 * Apply common locale/runtime gates without touching host date/time state.
	 *
	 * @return bool
	 */
	private function can_shape() {
		return function_exists( 'determine_locale' ) &&
			'fa_IR' === determine_locale() &&
			$this->is_exact_supported_host() &&
			defined( 'PGR_URL' ) &&
			defined( 'PGR_VERSION' );
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
