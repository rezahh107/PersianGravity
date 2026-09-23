<?php
/**
 * Bounded RTL compatibility for the exact GP Advanced Select integration.
 *
 * @package PersianGravity
 */

defined( 'ABSPATH' ) || exit;

/** Correct source-proven Tom Select RTL caret behavior without modifying vendor files. */
final class PGR_Gravity_Perks_RTL {
	/** Exact admitted GP Advanced Select version. */
	private const TARGET_VERSION = '1.1.21';

	/** Exact upstream style handle in GP Advanced Select 1.1.21. */
	private const STYLE_HANDLE = 'gp-advanced-select-tom-select';

	/** Whether the request-local inline fix has already been attached. */
	private $attached = false;

	/** Register only bounded lifecycle hooks. */
	public function hooks() {
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_attach' ), 100, 0 );
		add_action( 'gform_enqueue_scripts', array( $this, 'maybe_attach' ), 100, 0 );
		add_action( 'admin_enqueue_scripts', array( $this, 'maybe_attach' ), 100, 0 );
	}

	/**
	 * Attach the fix only for fa_IR RTL requests and only after the exact vendor
	 * handle has been registered. Inline CSS then prints only when that handle is
	 * actually enqueued by GP Advanced Select.
	 *
	 * @return void
	 */
	public function maybe_attach() {
		if (
			$this->attached
			|| ! defined( 'GP_ADVANCED_SELECT_VERSION' )
			|| self::TARGET_VERSION !== GP_ADVANCED_SELECT_VERSION
			|| 'fa_IR' !== determine_locale()
			|| ! is_rtl()
			|| ! wp_style_is( self::STYLE_HANDLE, 'registered' )
		) {
			return;
		}

		if ( wp_add_inline_style( self::STYLE_HANDLE, self::css() ) ) {
			$this->attached = true;
		}
	}

	/**
	 * The vendor Tom Select runtime sets `rtl` on `.ts-wrapper`, while the bundled
	 * stylesheet targets `.ts-control.rtl` and pins the single-select caret to the
	 * right. Scope the compatibility rule to that actual wrapper state.
	 *
	 * @return string
	 */
	public static function css() {
		return '.ts-wrapper.rtl .ts-control,.ts-wrapper.rtl .ts-control>input{direction:rtl;text-align:right;}'
			. '.ts-wrapper.rtl:not(.form-control):not(.form-select).single .ts-control{background-position:left .75rem center;padding-left:max(var(--ts-pr-min),calc(var(--ts-pr-clear-button) + var(--ts-pr-caret)))!important;padding-right:var(--ts-pr-min)!important;}';
	}
}
