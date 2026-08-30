<?php
/**
 * Iranian currency definitions for Gravity Forms.
 *
 * @package PersianGravityForms
 */

defined( 'ABSPATH' ) || exit;

final class PGR_Currency {

	/**
	 * Register currency hooks.
	 *
	 * @return void
	 */
	public function hooks() {
		add_filter( 'gform_currencies', array( $this, 'add_iranian_currencies' ) );
	}

	/**
	 * Add Iranian Rial and Toman currency configurations.
	 *
	 * @param array $currencies Existing Gravity Forms currencies.
	 * @return array
	 */
	public function add_iranian_currencies( $currencies ) {
		$currencies['IRR'] = array(
			'name'               => esc_html__( 'Iranian Rial', 'persian-gravityforms' ),
			'code'               => 'IRR',
			'symbol_left'        => '',
			'symbol_right'       => 'ریال',
			'symbol_padding'     => ' ',
			'thousand_separator' => ',',
			'decimal_separator'  => '.',
			'decimals'           => 0,
		);

		$currencies['IRT'] = array(
			'name'               => esc_html__( 'Iranian Toman', 'persian-gravityforms' ),
			'code'               => 'IRT',
			'symbol_left'        => '',
			'symbol_right'       => 'تومان',
			'symbol_padding'     => ' ',
			'thousand_separator' => ',',
			'decimal_separator'  => '.',
			'decimals'           => 0,
		);

		return $currencies;
	}
}
