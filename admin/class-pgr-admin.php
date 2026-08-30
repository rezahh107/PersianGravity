<?php
/**
 * Minimal WordPress settings page for Persian Gravity Forms.
 *
 * @package PersianGravityForms
 */

defined( 'ABSPATH' ) || exit;

final class PGR_Admin {

	const OPTION = 'pgr_settings';

	/**
	 * Register WordPress admin hooks.
	 *
	 * @return void
	 */
	public function hooks() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Add the plugin settings page.
	 *
	 * @return void
	 */
	public function add_menu() {
		add_options_page(
			esc_html__( 'Persian Gravity Forms', 'persian-gravityforms' ),
			esc_html__( 'Persian Gravity Forms', 'persian-gravityforms' ),
			'manage_options',
			'persian-gravityforms',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Register the single plugin settings model.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			'pgr_settings_group',
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => array(
					'default_force_english' => 1,
				),
			)
		);

		add_settings_section(
			'pgr_national_id_defaults',
			esc_html__( 'National ID defaults', 'persian-gravityforms' ),
			'__return_false',
			'persian-gravityforms'
		);

		add_settings_field(
			'default_force_english',
			esc_html__( 'Normalize digits while typing', 'persian-gravityforms' ),
			array( $this, 'render_default_force_english' ),
			'persian-gravityforms',
			'pgr_national_id_defaults'
		);
	}

	/**
	 * Sanitize the complete settings array.
	 *
	 * @param mixed $input Raw settings value.
	 * @return array
	 */
	public function sanitize_settings( $input ) {
		$input = is_array( $input ) ? $input : array();

		return array(
			'default_force_english' => empty( $input['default_force_english'] ) ? 0 : 1,
		);
	}

	/**
	 * Render the National ID client-normalization default.
	 *
	 * @return void
	 */
	public function render_default_force_english() {
		$settings = get_option( self::OPTION, array( 'default_force_english' => 1 ) );
		?>
		<label>
			<input
				type="checkbox"
				name="<?php echo esc_attr( self::OPTION ); ?>[default_force_english]"
				value="1"
				<?php checked( ! empty( $settings['default_force_english'] ) ); ?>
			/>
			<?php esc_html_e( 'Convert Persian/Arabic digits to ASCII in new National ID fields while the user types. Server-side normalization always remains authoritative.', 'persian-gravityforms' ); ?>
		</label>
		<?php
	}

	/**
	 * Render the settings page.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Persian Gravity Forms', 'persian-gravityforms' ); ?></h1>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'pgr_settings_group' );
				do_settings_sections( 'persian-gravityforms' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}
}
