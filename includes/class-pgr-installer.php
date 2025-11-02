<?php
/**
 * Handles plugin activation and deactivation routines.
 *
 * @package PersianGravityFormsRefactor
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Activation and deactivation handler for the plugin.
 *
 * @since 1.0.0
 */
class PGR_Installer {
	/**
	 * Executes logic on plugin activation.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function activate() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			wp_die( esc_html__( 'Insufficient permissions to activate Persian Gravity Forms Refactor.', 'persian-gravityforms-refactor' ) );
		}

		if ( ! self::check_dependencies() ) {
			return;
		}

		flush_rewrite_rules();
	}

	/**
	 * Executes logic on plugin deactivation.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function deactivate() {
		flush_rewrite_rules();
	}

	/**
	 * Validates plugin dependencies before activation completes.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	protected static function check_dependencies() {
		if ( ! function_exists( 'pgr_dependencies_met' ) ) {
			return true;
		}

		if ( pgr_dependencies_met() ) {
			return true;
		}

		$messages = pgr_dependency_messages();

		if ( empty( $messages ) ) {
			$messages = array( esc_html__( 'Missing required dependencies.', 'persian-gravityforms-refactor' ) );
		}

		$messages = array_map( 'esc_html', $messages );

		wp_die(
			wp_kses_post( '<p>' . implode( '</p><p>', $messages ) . '</p>' ),
			esc_html__( 'Activation halted', 'persian-gravityforms-refactor' )
		);

		return false;
	}
}
