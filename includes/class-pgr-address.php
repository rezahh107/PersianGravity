<?php
/**
 * Iranian address type support for Gravity Forms.
 *
 * @package PersianGravityForms
 */

defined( 'ABSPATH' ) || exit;

final class PGR_Address {

	/**
	 * Register supported Gravity Forms hooks.
	 *
	 * @return void
	 */
	public function hooks() {
		add_filter( 'gform_address_types', array( $this, 'add_iran_address_type' ), 10, 2 );
		add_filter( 'gform_predefined_choices', array( $this, 'add_iran_provinces_choices' ) );
	}

	/**
	 * Add Iran as a country-specific Gravity Forms address type.
	 *
	 * @param array $address_types Existing address types.
	 * @param int   $form_id       Current form ID.
	 * @return array
	 */
	public function add_iran_address_type( $address_types, $form_id ) {
		unset( $form_id );

		$address_types['iran'] = array(
			'label'       => esc_html__( 'Iran', 'persian-gravityforms' ),
			'country'     => 'Iran',
			'zip_label'   => esc_html__( 'Postal Code', 'persian-gravityforms' ),
			'state_label' => esc_html__( 'Province', 'persian-gravityforms' ),
			'states'      => array_merge( array( '' ), self::provinces() ),
		);

		return $address_types;
	}

	/**
	 * Add Iranian provinces to Gravity Forms predefined choices.
	 *
	 * @param array $choices Existing predefined choices.
	 * @return array
	 */
	public function add_iran_provinces_choices( $choices ) {
		$choices[ esc_html__( 'Iranian Provinces', 'persian-gravityforms' ) ] = self::provinces();
		return $choices;
	}

	/**
	 * Return the current 31 provinces of Iran.
	 *
	 * @return string[]
	 */
	public static function provinces() {
		return array(
			'آذربایجان شرقی',
			'آذربایجان غربی',
			'اردبیل',
			'اصفهان',
			'البرز',
			'ایلام',
			'بوشهر',
			'تهران',
			'چهارمحال و بختیاری',
			'خراسان جنوبی',
			'خراسان رضوی',
			'خراسان شمالی',
			'خوزستان',
			'زنجان',
			'سمنان',
			'سیستان و بلوچستان',
			'فارس',
			'قزوین',
			'قم',
			'کردستان',
			'کرمان',
			'کرمانشاه',
			'کهگیلویه و بویراحمد',
			'گلستان',
			'گیلان',
			'لرستان',
			'مازندران',
			'مرکزی',
			'هرمزگان',
			'همدان',
			'یزد',
		);
	}
}
