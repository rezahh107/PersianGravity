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
	 * The exact GP Advanced Select runtime creates a `gfield_select` Tom Select
	 * wrapper with its `change_listener` plugin and sets `rtl` on that wrapper.
	 * The bundled stylesheet targets `.ts-control.rtl` instead and keeps the
	 * single-select caret on the LTR side. Scope the compatibility rule to the
	 * authentic wrapper state and reserve the caret gap on RTL inline-end.
	 *
	 * @return string
	 */
	public static function css() {
		return '.ts-wrapper.gfield_select.plugin-change_listener.rtl .ts-control,.ts-wrapper.gfield_select.plugin-change_listener.rtl .ts-control>input{direction:rtl;text-align:right;}'
			. '.ts-wrapper.gfield_select.plugin-change_listener.rtl:not(.form-control):not(.form-select).single .ts-control{background-position:left .75rem center;padding-inline-start:var(--ts-pr-min)!important;padding-inline-end:max(var(--ts-pr-min),var(--ts-pr-caret))!important;}';
	}
}
