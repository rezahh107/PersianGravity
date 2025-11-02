<?php
/**
 * Admin controller for plugin settings.
 *
 * @package PersianGravityFormsRefactor
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Admin UI and Settings API.
 */
class PGR_Admin {

	/**
	 * Option name.
	 *
	 * @var string
	 */
	private $option_name = 'pgr_settings';

	/**
	 * Register admin hooks.
	 *
	 * @since 1.0.0
	 */
	public function hooks() : void {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'wp_ajax_pgr_validate_nid', array( $this, 'ajax_validate_nid' ) );
	}

	/**
	 * Register settings page.
	 *
	 * @since 1.0.0
	 */
	public function register_menu() : void {
		add_options_page(
			esc_html__( 'Persian GF', 'persian-gravityforms-refactor' ),
			esc_html__( 'Persian GF', 'persian-gravityforms-refactor' ),
			'manage_options',
			'pgr-settings',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Settings API registration.
	 *
	 * @since 1.0.0
	 */
	public function register_settings() : void {
		register_setting(
			'pgr_settings',
			$this->option_name,
			array( $this, 'sanitize_settings' )
		);

		add_settings_section(
			'pgr_general',
			esc_html__( 'General', 'persian-gravityforms-refactor' ),
			'__return_false',
			'pgr_settings'
		);

		add_settings_field(
			'enable_nid_validation',
			esc_html__( 'Validate National ID', 'persian-gravityforms-refactor' ),
			array( $this, 'field_checkbox' ),
			'pgr_settings',
			'pgr_general',
			array( 'key' => 'enable_nid_validation' )
		);
	}

	/**
	 * Sanitize options.
	 *
	 * @since 1.0.0
	 *
	 * @param array $input Raw input.
	 * @return array
	 */
	public function sanitize_settings( $input ) : array {
		$input = is_array( $input ) ? $input : array();
		return array(
			'enable_nid_validation' => PGR_Utils::sanitize_bool( $input['enable_nid_validation'] ?? false ),
		);
	}

	/**
	 * Checkbox renderer.
	 *
	 * @since 1.0.0
	 *
	 * @param array $args Args.
	 */
	public function field_checkbox( $args ) : void {
		$key = sanitize_key( $args['key'] ?? '' );
		$opt = get_option( $this->option_name, array() );
		$val = ! empty( $opt[ $key ] );
		echo '<label><input type="checkbox" name="' . esc_attr( $this->option_name ) . '[' . esc_attr( $key ) . ']" value="1" ' . checked( $val, true, false ) . ' /> ' . esc_html__( 'Enable', 'persian-gravityforms-refactor' ) . '</label>';
	}

	/**
	 * Render settings page.
	 *
	 * @since 1.0.0
	 */
	public function render_page() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Persian Gravity Forms', 'persian-gravityforms-refactor' ) . '</h1>';
		echo '<form action="' . esc_url( admin_url( 'options.php' ) ) . '" method="post">';
		settings_fields( 'pgr_settings' );
		do_settings_sections( 'pgr_settings' );
		submit_button();
		echo '</form>';
		echo '</div>';
	}

	/**
	 * AJAX: validate National ID.
	 *
	 * @since 1.0.0
	 */
	public function ajax_validate_nid() : void {
		check_ajax_referer( 'pgr_admin', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Permission denied.', 'persian-gravityforms-refactor' ) ), 403 );
		}
		$nid    = isset( $_POST['nid'] ) ? sanitize_text_field( wp_unslash( $_POST['nid'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$valid  = PGR_Utils::is_valid_iran_national_id( $nid );
		wp_send_json_success( array( 'valid' => $valid ) );
	}
}
