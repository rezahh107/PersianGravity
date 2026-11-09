<?php
/**
 * Read-only, version-scoped PersianGravity ← GPP Inbox 1 warning facts.
 *
 * No foreign option reads/writes, Grid state mutations, or vendor calls.
 * Browser-initialized ag-rtl/ag-ltr is deliberately NOT inferred here.
 *
 * @package PersianGravityForms
 */

defined( 'ABSPATH' ) || exit;

final class PGR_GPP_Inbox_RTL_Compatibility {
	/**
	 * Treat unknown, partially installed, malformed or throwing GPP as unknown.
	 *
	 * @return array<string,string>
	 */
	private static function gpp_axis_status() {
		$unknown = array(
			'interface' => 'absent',
			'requested' => 'unknown',
			'effective' => 'unknown',
		);

		if ( ! function_exists( 'gpp_gravity_flow_inbox_axis_status' ) ) {
			return $unknown;
		}

		try {
			$candidate = gpp_gravity_flow_inbox_axis_status();
		} catch ( \Throwable $exception ) {
			$unknown['interface'] = 'error';
			return $unknown;
		}

		if ( ! is_array( $candidate ) ||
			1 !== ( $candidate['api_version'] ?? null ) ||
			'supported' !== ( $candidate['capability'] ?? null ) ||
			'gravity_flow.inbox_1' !== ( $candidate['scope'] ?? null ) ||
			'not_observed' !== ( $candidate['grid_mode'] ?? null ) ||
			! in_array( $candidate['requested'] ?? null, array( 'on', 'off', 'unknown' ), true ) ||
			! in_array( $candidate['effective'] ?? null, array( 'on', 'off' ), true ) ||
			( 'unknown' !== $candidate['requested'] && $candidate['requested'] !== $candidate['effective'] ) ||
			( 'unknown' === $candidate['requested'] && 'on' !== $candidate['effective'] ) ) {
			$unknown['interface'] = 'unsupported';
			return $unknown;
		}

		return array(
			'interface' => 'supported',
			'requested' => $candidate['requested'],
			'effective' => $candidate['effective'],
		);
	}

	/**
	 * Classify only actual pinned browser failure evidence.
	 *
	 * determine_locale() identifies the effective Admin/request locale; get_locale()
	 * identifies the site Frontend locale. Right-to-left direction alone is not
	 * evidence of the qualified fa_IR Admin Grid. Neither observes the Grid.
	 *
	 * @return array<string,mixed>
	 */
	public static function facts() {
		$pgr         = pgr_gravity_flow_inbox_rtl_status();
		$gpp         = self::gpp_axis_status();
		$site_locale = function_exists( 'get_locale' ) ? get_locale() : null;
		$problem     = 'none';

		if ( 'admitted' === $pgr['host'] &&
			in_array( $pgr['requested'], array( 'on', 'off' ), true ) &&
			'supported' === $gpp['interface'] &&
			in_array( $gpp['requested'], array( 'on', 'off' ), true ) ) {
			$fa_frontend = 'fa_IR' === $site_locale;
			$fa_admin    = function_exists( 'determine_locale' ) && 'fa_IR' === determine_locale();

			if ( 'on' === $pgr['requested'] && $fa_frontend ) {
				$problem = 'on' === $gpp['effective'] ? 'rtl_with_ltr_axis' : 'rtl_column_order_without_axis';
			} elseif ( 'off' === $pgr['requested'] && 'off' === $gpp['effective'] && $fa_frontend ) {
				$problem = 'ltr_grid_without_axis';
			} elseif ( $fa_admin && 'off' === $pgr['requested'] ) {
				$problem = 'native_admin_mobile_scroll';
			} elseif ( $fa_admin && 'on' === $pgr['requested'] ) {
				$problem = 'native_admin_mobile_touch';
			}
		}

		$compatibility = 'none' === $problem
			? 'unknown' : ( 'rtl_with_ltr_axis' === $problem ? 'incompatible' : 'known_failure' );

		return array(
			'pgr'           => $pgr,
			'gpp'           => $gpp,
			'site_locale'   => is_string( $site_locale ) ? $site_locale : 'unknown',
			'compatibility' => $compatibility,
			'problem'       => $problem,
			'grid_mode'     => 'not_observed',
			// Scope of the observed defect, NOT a runtime plugin-version claim.
			'evidence'      => 'none' === $problem ? 'none_for_this_combination'
				: 'GPP_PR174_RUN_42469384530__GPP_c8cf9fd__PGR_311c2f5d__FLOW_3.1.0__GF_3.1.1.1__WP_6.8.3__CHROMIUM_140.0.7339.16',
		);
	}
}
