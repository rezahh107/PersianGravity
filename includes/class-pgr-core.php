<?php
/**
 * Core orchestrator singleton.
 *
 * @package PersianGravityFormsRefactor
 * @since   3.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Core singleton to wire hooks and modules.
 */
class PGR_Core {

	/**
	 * Singleton instance.
	 *
	 * @var PGR_Core|null
	 */
	private static $instance = null;

	/**
	 * Loaded modules.
	 *
	 * @var array<string,object>
	 */
	private $modules = array();

	/**
	 * Get instance.
	 *
	 * @since 3.0.0
	 *
	 * @return PGR_Core
	 */
	public static function instance(): PGR_Core {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 *
	 * @since 3.0.0
	 */
	private function __construct() {
		add_action( 'plugins_loaded', array( $this, 'on_plugins_loaded' ), 10 );
	}

	/**
	 * Initialize after plugins loaded – check dependencies, load modules, register hooks.
	 *
	 * @since 3.0.0
	 */
	public function on_plugins_loaded(): void {
		// Load text domain
		load_plugin_textdomain(
			'persian-gravityforms-refactor',
			false,
			dirname( plugin_basename( PGR_FILE ) ) . '/languages'
		);

		// Check Gravity Forms presence
		if ( ! class_exists( 'GFCommon' ) ) {
			add_action( 'admin_notices', function() {
				echo '<div class="notice notice-error"><p>' .
				     esc_html__( 'Persian Gravity Forms requires Gravity Forms to be installed and active.', 'persian-gravityforms-refactor' ) .
				     '</p></div>';
			} );
			return;
		}

		// Load modules (admin, national_id, persian_date)
		$this->load_module( 'admin', 'PGR_Admin' );
		$this->load_module( 'national_id', 'PGR_National_ID' );
		$this->load_module( 'persian_date', 'PGR_Persian_Date' );

		// Register core hooks
		$this->register_core_hooks();
	}

	/**
	 * Register all core WordPress and Gravity Forms hooks.
	 *
	 * @since 3.0.0
	 */
	private function register_core_hooks(): void {
		// Admin assets
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );

		// Frontend assets (only when needed)
		add_action( 'gform_enqueue_scripts', array( $this, 'frontend_enqueue_scripts' ), 10, 2 );

		// Register custom field (National ID)
		add_action( 'gform_loaded', array( $this, 'register_custom_field' ) );

		// Per-form Persian formatting toggle
		add_filter( 'gform_form_settings', array( $this, 'add_persian_formatting_setting' ), 10, 2 );
		add_filter( 'gform_pre_form_settings_save', array( $this, 'save_persian_formatting_setting' ) );

		// Normalize digits on submission (safe method)
		add_filter( 'gform_save_field_value', array( $this, 'normalize_digits_on_save' ), 10, 4 );
	}

	/**
	 * Enqueue admin scripts only on Gravity Forms edit screens.
	 *
	 * @since 3.0.0
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function admin_enqueue_scripts( string $hook ): void {
		if ( ! class_exists( 'RGForms' ) || ! RGForms::is_gravity_page() ) {
			return;
		}
		$page = rgget( 'page' );
		if ( ! in_array( $page, array( 'gf_edit_forms', 'gf_new_form' ), true ) ) {
			return;
		}

		$script_asset = PGR_URL . 'assets/js/pgr-admin.js';
		wp_enqueue_script(
			'pgr-admin',
			$script_asset,
			array( 'jquery' ),
			$this->get_asset_version( PGR_PATH . 'assets/js/pgr-admin.js' ),
			true
		);

		wp_localize_script( 'pgr-admin', 'PGRDefaults', array(
			'forceEnglish'   => (bool) get_option( 'pgr_default_force_english', false ),
			'liveValidation' => (bool) get_option( 'pgr_enable_nid_validation', false ),
		) );
	}

	/**
	 * Enqueue frontend script only if form has National ID field or Persian formatting enabled.
	 *
	 * @since 3.0.0
	 *
	 * @param array $form     Gravity Forms form array.
	 * @param bool  $is_ajax  Whether form is submitted via AJAX.
	 */
	public function frontend_enqueue_scripts( array $form, bool $is_ajax ): void {
		$has_national_id = ! empty( GFCommon::get_fields_by_type( $form, array( 'ir_national_id', 'pgr_national_id' ) ) );
		$has_persian_formatting = ! empty( $form['pgr_enable'] );

		if ( ! $has_national_id && ! $has_persian_formatting ) {
			return;
		}

		wp_enqueue_script(
			'pgr-frontend',
			PGR_URL . 'assets/js/pgr-frontend.js',
			array(),
			$this->get_asset_version( PGR_PATH . 'assets/js/pgr-frontend.js' ),
			true
		);
	}

	/**
	 * Register the custom Gravity Forms field (National ID).
	 *
	 * @since 3.0.0
	 */
	public function register_custom_field(): void {
		if ( class_exists( 'GF_Fields' ) && class_exists( 'PGR_GF_Field_National_ID' ) ) {
			GF_Fields::register( new PGR_GF_Field_National_ID() );
		}
	}

	/**
	 * Add a checkbox to form settings for enabling Persian digit normalization.
	 *
	 * @since 3.0.0
	 *
	 * @param array $settings Form settings.
	 * @param array $form     Form object.
	 * @return array Modified settings.
	 */
	public function add_persian_formatting_setting( array $settings, array $form ): array {
		$checked = ! empty( $form['pgr_enable'] ) ? 'checked="checked"' : '';
		$row = '<tr>
			<th><label for="pgr_enable">' . esc_html__( 'Persian formatting', 'persian-gravityforms-refactor' ) . '</label></th>
			<td>
				<label>
					<input id="pgr_enable" type="checkbox" name="pgr_enable" value="1" ' . $checked . ' />
					' . esc_html__( 'Normalize Persian/Arabic digits on submit for this form', 'persian-gravityforms-refactor' ) . '
				</label>
				<p class="description">' . esc_html__( 'If enabled, all text inputs will be normalized to English digits on submission.', 'persian-gravityforms-refactor' ) . '</p>
			</td>
		</tr>';

		if ( isset( $settings['Form Basics'] ) ) {
			$settings['Form Basics'] .= $row;
		}
		return $settings;
	}

	/**
	 * Save the Persian formatting setting when form settings are saved.
	 *
	 * @since 3.0.0
	 *
	 * @param array $form Form object being saved.
	 * @return array Modified form object.
	 */
	public function save_persian_formatting_setting( array $form ): array {
		$form['pgr_enable'] = isset( $_POST['pgr_enable'] ) ? 1 : 0;
		return $form;
	}

	/**
	 * Normalize Persian/Arabic digits to English digits before saving field value.
	 *
	 * @since 3.0.0
	 *
	 * @param mixed  $value      Field value.
	 * @param array  $form       Form object.
	 * @param string $field_id   Field ID.
	 * @param array  $entry      Entry object.
	 * @return mixed Normalized value.
	 */
	public function normalize_digits_on_save( $value, array $form, string $field_id, array $entry ) {
		if ( empty( $form['pgr_enable'] ) ) {
			return $value;
		}
		if ( ! class_exists( 'PGR_Utils' ) ) {
			return $value;
		}
		if ( is_string( $value ) ) {
			$value = PGR_Utils::normalize_digits( $value );
		} elseif ( is_array( $value ) ) {
			array_walk_recursive( $value, function( &$item ) {
				if ( is_string( $item ) ) {
					$item = PGR_Utils::normalize_digits( $item );
				}
			} );
		}
		return $value;
	}

	/**
	 * Get asset version based on file modification time (cache busting).
	 *
	 * @since 3.0.0
	 *
	 * @param string $file_path Absolute path to asset file.
	 * @return string Version string.
	 */
	private function get_asset_version( string $file_path ): string {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && file_exists( $file_path ) ) {
			return (string) filemtime( $file_path );
		}
		return PGR_VERSION;
	}

	/**
	 * Lazy-load and register a module.
	 *
	 * @since 3.0.0
	 *
	 * @param string $key   Module key.
	 * @param string $class Class name.
	 */
	private function load_module( string $key, string $class ): void {
		if ( isset( $this->modules[ $key ] ) ) {
			return;
		}
		if ( class_exists( $class ) ) {
			$instance = new $class();
			if ( method_exists( $instance, 'hooks' ) ) {
				$instance->hooks();
			}
			$this->modules[ $key ] = $instance;
		}
	}

	/**
	 * Public accessor to a module by key.
	 *
	 * @since 3.0.0
	 *
	 * @param string $key Module key.
	 * @return object|null
	 */
	public function get_module( string $key ) {
		return $this->modules[ $key ] ?? null;
	}
}
