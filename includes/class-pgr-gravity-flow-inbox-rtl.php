<?php
/**
 * Optional, source-qualified Gravity Flow Inbox AG Grid RTL initialization.
 *
 * Gravity Flow and AG Grid retain ownership of grid construction and behavior.
 *
 * @package PersianGravityForms
 */

defined( 'ABSPATH' ) || exit;

final class PGR_Gravity_Flow_Inbox_RTL {
	/** Only the authentically exercised Gravity Flow host is admitted. */
	private const ADMITTED_VERSION = '3.1.0';
	private const HOST_BASENAME    = 'gravityflow/gravityflow.php';

	/**
	 * The Settings API is the sole opt-in authority; missing/malformed means OFF.
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		$settings = get_option( 'pgr_settings', array() );
		return is_array( $settings ) && isset( $settings['gravity_flow_inbox_rtl'] )
			&& 1 === $settings['gravity_flow_inbox_rtl'];
	}

	/**
	 * Wire only for a confirmed enabled setting and authentic vendor identity.
	 *
	 * @return void
	 */
	public function hooks() {
		if ( ! self::is_enabled() || ! self::is_admitted_host() ) {
			return;
		}

		// Gravity Flow 3.1.0 builds the grids on this filter at priority 10.
		// Run after that producer, before later presentation integrations.
		add_filter( 'gravityflow_js_config_shared', array( $this, 'inbox_grid_config' ), 11, 1 );
	}

	/**
	 * Before AG Grid construction, supply a missing initial direction option.
	 * Existing valid options belong to the component/integration which set them.
	 *
	 * @param mixed $config Gravity Flow's shared localized JS configuration.
	 * @return mixed Unrelated and unsupported configuration is unchanged.
	 */
	public function inbox_grid_config( $config ) {
		if ( ! self::is_enabled() || ! self::is_admitted_host() ||
			! is_array( $config ) || ! isset( $config['grids'] ) || ! is_array( $config['grids'] ) ) {
			return $config;
		}

		// WordPress's active request locale (including admin user locale), not
		// an unconditional site locale or the page's potentially overridden CSS.
		$effective_rtl = is_rtl();

		foreach ( $config['grids'] as $grid_id => &$grid ) {
			if ( ! self::is_producer_inbox_grid( $grid_id, $grid ) ) {
				continue;
			}

			// Respect valid explicit ownership by Gravity Flow or another filter.
			// Do not convert or overwrite even a conflicting bool.
			if ( array_key_exists( 'enableRtl', $grid['grid_options'] ) ) {
				continue;
			}

			$grid['grid_options']['enableRtl'] = $effective_rtl;
		}
		unset( $grid );

		return $config;
	}

	/**
	 * Admit only a Grid that the real Gravity Flow 3.1.0 Inbox producer
	 * could have emitted in this request. The vendor's Task model computes
	 * the full assignee/type/location/hash identity from its own searchArgs.
	 * This covers admin, shortcode and block without guessing an ID prefix.
	 *
	 * @param mixed $grid_id Shared-config Grid identity.
	 * @param mixed $grid Shared-config Grid payload.
	 * @return bool
	 */
	private static function is_producer_inbox_grid( $grid_id, $grid ) {
		if ( ! is_string( $grid_id ) || ! is_array( $grid ) ||
			! isset( $grid['grid_options'] ) || ! is_array( $grid['grid_options'] ) ) {
			return false;
		}

		$options = $grid['grid_options'];
		// Gravity Flow 3.1.0 Inbox_Service_Provider::get_config_for_grids().
		if ( ! isset( $options['searchArgs'], $options['columnDefs'], $options['rowData'], $options['paginationPageSize'] ) ||
			! is_array( $options['searchArgs'] ) || ! is_array( $options['columnDefs'] ) ||
			! count( $options['columnDefs'] ) || ! is_array( $options['rowData'] ) ||
			true !== ( $options['pagination'] ?? null ) ||
			! is_int( $options['paginationPageSize'] ) || $options['paginationPageSize'] < 1 ||
			! array_key_exists( 'current_user_token', $grid ) ) {
			return false;
		}

		if ( ! class_exists( 'Gravity_Flow', false ) ||
			! class_exists( '\\Gravity_Flow\\Gravity_Flow\\Inbox\\Inbox_Service_Provider', false ) ) {
			return false;
		}

		try {
			$flow  = \Gravity_Flow::get_instance();
			$model = $flow->container()->get( \Gravity_Flow\Gravity_Flow\Inbox\Inbox_Service_Provider::TASK_MODEL );
			if ( ! is_object( $model ) || ! is_callable( array( $model, 'get_unique_grid_id_from_args' ) ) ) {
				return false;
			}

			$produced = $model->get_unique_grid_id_from_args( $options['searchArgs'] );
			return is_string( $produced ) && hash_equals( $produced, $grid_id );
		} catch ( \Throwable $error ) {
			// Missing/ambiguous producer state must leave the host Grid untouched.
			return false;
		}
	}

	/**
	 * Fail closed on absent vendor, aliases, or unqualified versions.
	 *
	 * @return bool
	 */
	private static function is_admitted_host() {
		return defined( 'GRAVITY_FLOW_VERSION' ) &&
			self::ADMITTED_VERSION === (string) constant( 'GRAVITY_FLOW_VERSION' ) &&
			defined( 'GRAVITY_FLOW_PLUGIN_BASENAME' ) &&
			self::HOST_BASENAME === str_replace( '\\', '/', (string) constant( 'GRAVITY_FLOW_PLUGIN_BASENAME' ) );
	}
}
