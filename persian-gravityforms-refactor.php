<?php
/**
 * Plugin bootstrap file for Persian Gravity Forms Refactor.
 *
 * @package PersianGravityFormsRefactor
 * @since   1.0.0
 */

/**
 * Plugin Name: Persian Gravity Forms Refactor
 * Plugin URI:  https://wordpress.org/plugins/persian-gravity-forms/
 * Description: Modernized Persian localization layer for Gravity Forms with enhanced security and performance.
 * Version:     1.0.0
 * Author:      Persian Gravity Forms Team
 * Author URI:  https://profiles.wordpress.org/persianscript/
 * Text Domain: persian-gravityforms-refactor
 * Domain Path: /languages
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'PGR_VERSION' ) ) {
	define( 'PGR_VERSION', '1.0.0' );
}

if ( ! defined( 'PGR_FILE' ) ) {
	define( 'PGR_FILE', __FILE__ );
}

if ( ! defined( 'PGR_PATH' ) ) {
	define( 'PGR_PATH', plugin_dir_path( PGR_FILE ) );
}

if ( ! defined( 'PGR_URL' ) ) {
	define( 'PGR_URL', plugin_dir_url( PGR_FILE ) );
}

if ( ! defined( 'PGR_BASENAME' ) ) {
	define( 'PGR_BASENAME', plugin_basename( PGR_FILE ) );
}

if ( ! defined( 'PGR_PLUGIN_DIR' ) ) {
	define( 'PGR_PLUGIN_DIR', PGR_PATH );
}

if ( ! defined( 'PGR_PLUGIN_URL' ) ) {
	define( 'PGR_PLUGIN_URL', PGR_URL );
}

if ( ! defined( 'PGR_MIN_PHP_VERSION' ) ) {
	define( 'PGR_MIN_PHP_VERSION', '7.4' );
}

if ( ! defined( 'PGR_MIN_GF_VERSION' ) ) {
	define( 'PGR_MIN_GF_VERSION', '2.5' );
}

if ( ! defined( 'PGR_ACTION' ) ) {
	define( 'PGR_ACTION', 'pgr_admin_action' );
}

if ( ! defined( 'PGR_NONCE' ) ) {
	define( 'PGR_NONCE', 'pgr_admin_nonce' );
}

/**
 * Available Hooks Reference.
 *
 * Available Actions:
 * - pgr_after_init: Fires after plugin initialization completes.
 * - pgr_before_admin_load: Fires before admin interface logic runs.
 * - pgr_admin_action: Fires after a secured admin request is validated.
 *
 * Available Filters:
 * - pgr_autoload_paths: Filter autoloader search paths.
 * - pgr_dependency_checks: Modify dependency requirement evaluations.
 * - pgr_gravity_forms_plugin_file: Adjust Gravity Forms detection target.
 *
 * @since 1.0.0
 */

require_once PGR_PATH . 'includes/autoload.php';

add_action(
	'plugins_loaded',
	static function () {
		load_plugin_textdomain(
			'persian-gravityforms-refactor',
			false,
			dirname( plugin_basename( PGR_FILE ) ) . '/languages'
		);
	},
	0
);

register_activation_hook( PGR_FILE, array( 'PGR_Installer', 'activate' ) );
register_deactivation_hook( PGR_FILE, array( 'PGR_Installer', 'deactivate' ) );

if ( ! function_exists( 'pgr_dependency_messages' ) ) {
	/**
	 * Stores dependency error messages for admin display.
	 *
	 * @since 1.0.0
	 *
	 * @param array|null $messages Optional. Messages to persist.
	 * @return array
	 */
	function pgr_dependency_messages( $messages = null ) {
		static $store = array();

		if ( null !== $messages ) {
			$store = (array) $messages;
		}

		return $store;
	}
}

if ( ! function_exists( 'pgr_is_gravity_forms_active' ) ) {
	/**
	 * Determines whether Gravity Forms is currently active.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	function pgr_is_gravity_forms_active() {
		if ( class_exists( 'GFForms' ) ) {
			return true;
		}

		if ( ! function_exists( 'is_plugin_active' ) ) {
			$plugin_admin = ABSPATH . 'wp-admin/includes/plugin.php';

			if ( file_exists( $plugin_admin ) ) {
				require_once $plugin_admin;
			}
		}

		/**
		 * Filters the Gravity Forms plugin file used to detect activation.
		 *
		 * @since 1.0.0
		 *
		 * @param string $plugin_file Gravity Forms plugin basename.
		 */
		$gravity_forms_plugin = apply_filters( 'pgr_gravity_forms_plugin_file', 'gravityforms/gravityforms.php' );

		if ( function_exists( 'is_plugin_active' ) && is_plugin_active( $gravity_forms_plugin ) ) {
			return true;
		}

		if ( function_exists( 'is_plugin_active_for_network' ) && is_multisite() && is_plugin_active_for_network( $gravity_forms_plugin ) ) {
			return true;
		}

		return false;
	}
}

if ( ! function_exists( 'pgr_dependencies_met' ) ) {
	/**
	 * Determines whether plugin dependencies are satisfied.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	function pgr_dependencies_met() {
		$messages   = array();
		$gf_active  = pgr_is_gravity_forms_active();
		$gf_version = null;

		if ( $gf_active && class_exists( 'GFCommon' ) && method_exists( 'GFCommon', 'get_version' ) ) {
			$gf_version = (string) GFCommon::get_version();
		}

		if ( $gf_active && null === $gf_version && defined( 'GF_MIN_VERSION' ) ) {
			$gf_version = (string) GF_MIN_VERSION;
		}

		$checks = array(
			'php_version' => array(
				'pass'    => version_compare( PHP_VERSION, PGR_MIN_PHP_VERSION, '>=' ),
				'message' => sprintf(
					/* translators: %s: Minimum required PHP version. */
					esc_html__( 'Persian Gravity Forms Refactor requires PHP %s or newer.', 'persian-gravityforms-refactor' ),
					PGR_MIN_PHP_VERSION
				),
			),
			'gf_active'   => array(
				'pass'    => $gf_active,
				'message' => esc_html__( 'Persian Gravity Forms Refactor requires Gravity Forms to be installed and active.', 'persian-gravityforms-refactor' ),
			),
		);

		if ( $gf_active && null !== $gf_version ) {
			$checks['gf_version'] = array(
				'pass'    => version_compare( $gf_version, PGR_MIN_GF_VERSION, '>=' ),
				'message' => sprintf(
					/* translators: %s: Minimum required Gravity Forms version. */
					esc_html__( 'Persian Gravity Forms Refactor requires Gravity Forms %s or newer.', 'persian-gravityforms-refactor' ),
					PGR_MIN_GF_VERSION
				),
			);
		}

		/**
		 * Filters dependency checks before evaluation.
		 *
		 * @since 1.0.0
		 *
		 * @param array $checks Associative array of dependency checks.
		 */
		$checks = apply_filters( 'pgr_dependency_checks', $checks );

		foreach ( (array) $checks as $check ) {
			if ( ! is_array( $check ) ) {
				continue;
			}

			if ( ! array_key_exists( 'pass', $check ) || ! array_key_exists( 'message', $check ) ) {
				continue;
			}

			if ( true === $check['pass'] ) {
				continue;
			}

			$messages[] = (string) $check['message'];
		}

		if ( empty( $messages ) ) {
			pgr_dependency_messages( array() );

			return true;
		}

		pgr_dependency_messages( $messages );

		if ( ! has_action( 'admin_notices', 'pgr_render_dependency_notice' ) ) {
			add_action( 'admin_notices', 'pgr_render_dependency_notice' );
		}

		if ( ! has_action( 'network_admin_notices', 'pgr_render_dependency_notice' ) ) {
			add_action( 'network_admin_notices', 'pgr_render_dependency_notice' );
		}

		return false;
	}
}

if ( ! function_exists( 'pgr_render_dependency_notice' ) ) {
	/**
	 * Displays admin notices for missing dependencies.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	function pgr_render_dependency_notice() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		$messages = pgr_dependency_messages();

		if ( empty( $messages ) ) {
			return;
		}

		$messages = array_map( 'esc_html', $messages );

		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			implode( '</p><p>', $messages )
		);
	}
}

if ( ! function_exists( 'pgr_handle_admin_requests' ) ) {
	/**
	 * Validates plugin-specific admin POST requests.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	function pgr_handle_admin_requests() {
		if ( ! is_admin() ) {
			return;
		}

		/**
		 * Fires before admin interface logic loads.
		 *
		 * @since 1.0.0
		 */
		do_action( 'pgr_before_admin_load' );

		$request_method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '';

		if ( 'POST' !== strtoupper( $request_method ) ) {
			return;
		}

		if ( empty( $_POST[ PGR_ACTION ] ) ) {
			return;
		}

		$action = sanitize_key( wp_unslash( $_POST[ PGR_ACTION ] ) );

		if ( '' === $action ) {
			return;
		}

		$nonce_value = isset( $_POST[ PGR_NONCE ] ) ? sanitize_text_field( wp_unslash( $_POST[ PGR_NONCE ] ) ) : '';

		if ( ! wp_verify_nonce( $nonce_value, $action ) ) {
			wp_die( esc_html__( 'Security check failed.', 'persian-gravityforms-refactor' ) );
		}

		/**
		 * Fires when a secured admin action has been validated.
		 *
		 * @since 1.0.0
		 *
		 * @param string $action Sanitized action slug.
		 */
		do_action( 'pgr_admin_action', $action );
	}
}

add_action( 'admin_init', 'pgr_handle_admin_requests' );

if ( ! function_exists( 'pgr_sanitize_settings' ) ) {
	/**
	 * Recursively sanitizes plugin settings arrays.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $input Raw input data.
	 * @return mixed
	 */
	function pgr_sanitize_settings( $input ) {
		if ( is_array( $input ) ) {
			return array_map( 'pgr_sanitize_settings', $input );
		}

		return sanitize_text_field( (string) $input );
	}
}

if ( ! function_exists( 'pgr_init' ) ) {
	/**
	 * Initializes the plugin once dependencies are satisfied.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	function pgr_init() {
		if ( ! pgr_dependencies_met() ) {
			return;
		}

		if ( class_exists( 'PGR_Core' ) && is_callable( array( 'PGR_Core', 'get_instance' ) ) ) {
			PGR_Core::get_instance();

			/**
			 * Fires after the plugin has completed initialization.
			 *
			 * @since 1.0.0
			 */
			do_action( 'pgr_after_init' );

			return;
		}

		$legacy_core = PGR_PATH . 'includes/class-core.php';

		if ( file_exists( $legacy_core ) ) {
			require_once $legacy_core;
		}

		/**
		 * Fires after the plugin has completed initialization.
		 *
		 * @since 1.0.0
		 */
		do_action( 'pgr_after_init' );
	}
}

add_action( 'plugins_loaded', 'pgr_init' );
