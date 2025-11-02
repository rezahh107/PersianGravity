<?php
/**
 * Core orchestrator singleton.
 *
 * @package PersianGravityFormsRefactor
 * @since   1.0.0
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
	 * @since 1.0.0
	 *
	 * @return PGR_Core
	 */
	public static function instance() : PGR_Core {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	private function __construct() {
		add_action( 'plugins_loaded', array( $this, 'on_plugins_loaded' ), 10 );
		add_action( 'init', array( $this, 'on_init' ) );
	}

	/**
	 * Textdomain and dependency checks.
	 *
	 * @since 1.0.0
	 */
	public function on_plugins_loaded() : void {
		load_plugin_textdomain( 'persian-gravityforms-refactor', false, dirname( plugin_basename( PGR_PLUGIN_FILE ) ) . '/languages' );

		if ( ! class_exists( 'GFCommon' ) ) {
			add_action(
				'admin_notices',
				static function () {
					echo '<div class="notice notice-error"><p>' . esc_html__( 'Persian Gravity Forms requires Gravity Forms to be installed and active.', 'persian-gravityforms-refactor' ) . '</p></div>';
				}
			);
			return;
		}

		$this->load_module( 'admin', 'PGR_Admin' );
		$this->load_module( 'national_id', 'PGR_National_ID' );
		$this->load_module( 'persian_date', 'PGR_Persian_Date' );
	}

	/**
	 * Register public hooks.
	 *
	 * @since 1.0.0
	 */
	public function on_init() : void {
		// Placeholder for init-time hooks.
	}

	/**
	 * Lazy-load and register a module.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key   Array key.
	 * @param string $class Class name to instantiate.
	 */
	private function load_module( string $key, string $class ) : void {
		if ( isset( $this->modules[ $key ] ) ) {
			return;
		}
		if ( ! class_exists( $class ) ) {
			// Try to autoload via SPL. The autoloader is already registered.
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
	 * @since 1.0.0
	 *
	 * @param string $key Module key.
	 * @return object|null
	 */
	public function get_module( string $key ) {
		return $this->modules[ $key ] ?? null;
	}
}
