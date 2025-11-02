<?php
/**
 * Persian date helpers for Gravity Forms.
 *
 * @package PersianGravityFormsRefactor
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Localize GF datepicker and normalize submitted digits.
 */
class PGR_Persian_Date {

	/**
	 * Register hooks.
	 *
	 * @since 1.0.0
	 */
	public function hooks() : void {
		add_filter( 'gform_pre_submission', array( $this, 'normalize_submission' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend' ) );
	}

	/**
	 * Normalize date inputs (digits) on submission.
	 *
	 * @since 1.0.0
	 *
	 * @param array $form Gravity Forms form.
	 */
	public function normalize_submission( $form ) : void {
		foreach ( $_POST as $key => $val ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			if ( is_string( $val ) && preg_match( '/date|birth|tarikh|\bdate\b/i', $key ) ) {
				$_POST[ $key ] = PGR_Utils::normalize_digits( $val ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			}
		}
	}

	/**
	 * Enqueue frontend localization script.
	 *
	 * @since 1.0.0
	 */
	public function enqueue_frontend() : void {
		wp_register_script(
			'pgr-frontend',
			plugins_url( 'assets/js/pgr-frontend.js', dirname( __FILE__ ) ),
			array( 'jquery' ),
			'1.0.0',
			true
		);
		wp_enqueue_script( 'pgr-frontend' );
	}
}
