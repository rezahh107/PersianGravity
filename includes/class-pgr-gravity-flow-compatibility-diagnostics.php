<?php
/**
 * Bounded informational diagnostics for Gravity Flow compatibility observations.
 *
 * Diagnostics are maintenance evidence only. Production adapters own their
 * runtime safety decisions and must never use persisted diagnostics as
 * activation authority.
 *
 * @package PersianGravityForms
 */

defined( 'ABSPATH' ) || exit;

final class PGR_Gravity_Flow_Compatibility_Diagnostics {

	const OPTION         = 'pgr_gravityflow_compatibility_latest';
	const SCHEMA_VERSION = 1;

	const STATE_AVAILABLE     = 'AVAILABLE';
	const STATE_DEGRADED      = 'DEGRADED';
	const STATE_UNAVAILABLE   = 'UNAVAILABLE';
	const STATE_NOT_EVALUATED = 'NOT_EVALUATED';

	const REASON_CONTRACT_SATISFIED     = 'PGR-GFLOW-CONTRACT-SATISFIED';
	const REASON_HOST_UNQUALIFIED       = 'PGR-GFLOW-HOST-UNQUALIFIED';
	const REASON_SEAM_UNAVAILABLE       = 'PGR-GFLOW-SEAM-UNAVAILABLE';
	const REASON_CONTEXT_UNAVAILABLE    = 'PGR-GFLOW-CONTEXT-UNAVAILABLE';
	const REASON_SOURCE_INVALID         = 'PGR-GFLOW-SOURCE-INVALID';
	const REASON_NOT_APPLICABLE         = 'PGR-GFLOW-NOT-APPLICABLE';
	const REASON_CONVERSION_UNAVAILABLE = 'PGR-GFLOW-CONVERSION-UNAVAILABLE';
	const REASON_NOT_EVALUATED          = 'PGR-GFLOW-NOT-EVALUATED';

	private const MAX_SUMMARY_BYTES      = 160;
	private const MAX_HOST_VERSION_BYTES = 32;
	private const MAX_SNAPSHOT_BYTES     = 8192;
	private const SNAPSHOT_TTL_SECONDS   = 604800;
	private const FUTURE_CLOCK_SKEW      = 300;

	/** @var array<string,array<string,mixed>> */
	private static $request_observations = array();

	/** @var bool */
	private static $shutdown_registered = false;

	/**
	 * Canonical diagnostic capability allowlist.
	 *
	 * IDs reuse the established G-008 Gravity Flow surface identities. This is
	 * not a second source-semantics registry and carries no activation policy.
	 *
	 * @return array<string,string>
	 */
	public static function capabilities() {
		return array(
			'gravityflow.inbox.date-created'        => 'Inbox — Date created',
			'gravityflow.inbox.last-updated'        => 'Inbox — Last updated',
			'gravityflow.inbox.due-date'            => 'Inbox — Due date',
			'gravityflow.status.date-created'       => 'Status — Date created',
			'gravityflow.status.workflow-timestamp' => 'Status — Workflow timestamp',
			'gravityflow.status.due-date'           => 'Status — Due date',
			'gravityflow.entry-detail.submitted'    => 'Entry Detail — Submitted',
			'gravityflow.entry-detail.last-updated' => 'Entry Detail — Last updated',
			'gravityflow.entry-detail.due-date'     => 'Entry Detail — Due date',
			'gravityflow.entry-detail.expiration'   => 'Entry Detail — Expiration',
			'gravityflow.entry-detail.schedule'     => 'Entry Detail — Scheduled',
			'gravityflow.timeline-history'          => 'Timeline / History',
			'gravityflow.print'                     => 'Print — inherited Timeline presentation',
		);
	}

	/** @return array<int,string> */
	public static function states() {
		return array(
			self::STATE_AVAILABLE,
			self::STATE_DEGRADED,
			self::STATE_UNAVAILABLE,
			self::STATE_NOT_EVALUATED,
		);
	}

	/**
	 * Stable machine reasons mapped to source-owned human explanations.
	 *
	 * The explanation is deliberately derived from this catalog rather than
	 * accepted as free-form caller data, preventing PII/request payload capture.
	 *
	 * @return array<string,string>
	 */
	public static function reasons() {
		return array(
			self::REASON_CONTRACT_SATISFIED     => 'Required compatibility contract was satisfied in the observed context.',
			self::REASON_HOST_UNQUALIFIED       => 'Observed Gravity Flow host identity or version is not yet qualified for this capability.',
			self::REASON_SEAM_UNAVAILABLE       => 'A required host presentation seam or source contract was unavailable.',
			self::REASON_CONTEXT_UNAVAILABLE    => 'Required request-local render or caller context was unavailable.',
			self::REASON_SOURCE_INVALID         => 'Required host source data was missing, malformed, ambiguous, or outside its admitted domain.',
			self::REASON_NOT_APPLICABLE         => 'The capability was not applicable in the observed module, locale, or render context.',
			self::REASON_CONVERSION_UNAVAILABLE => 'The bounded Persian presentation conversion could not safely produce output.',
			self::REASON_NOT_EVALUATED          => 'No current trustworthy compatibility observation is available.',
		);
	}

	/**
	 * Record one bounded request-local compatibility observation.
	 *
	 * More severe observations take precedence inside one request:
	 * UNAVAILABLE > DEGRADED > AVAILABLE > NOT_EVALUATED. Equal-state later
	 * observations replace earlier observations deterministically.
	 *
	 * @param string $capability_id Established capability ID.
	 * @param string $state         Bounded compatibility state.
	 * @param string $reason_id     Stable reason ID.
	 * @return void
	 * @throws InvalidArgumentException Invalid diagnostic input.
	 */
	public static function record( $capability_id, $state, $reason_id ) {
		self::assert_capability( $capability_id );
		self::assert_state( $state );
		self::assert_reason( $reason_id );
		self::assert_state_reason_pair( $state, $reason_id );

		$observation = array(
			'capability_id' => $capability_id,
			'state'         => $state,
			'reason_id'     => $reason_id,
			'summary'       => self::summary_for_reason( $reason_id ),
			'observed_at'   => time(),
			'host_version'  => self::current_host_version(),
		);

		$existing = self::$request_observations[ $capability_id ] ?? null;
		if (
			is_array( $existing ) &&
			self::state_rank( $state ) < self::state_rank( $existing['state'] ?? self::STATE_NOT_EVALUATED )
		) {
			return;
		}

		self::$request_observations[ $capability_id ] = $observation;
		self::register_shutdown_persistence();
	}

	/** @return array<string,array<string,mixed>> */
	public static function request_snapshot() {
		return self::$request_observations;
	}

	/**
	 * Build reporting-only state for every known capability.
	 *
	 * Request-local evidence wins over persisted evidence. Missing, malformed,
	 * stale, or host-version-mismatched persisted evidence becomes
	 * NOT_EVALUATED. This method is never an activation API.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function status_snapshot() {
		$persisted = self::read_persisted_records();
		$snapshot  = array();

		foreach ( self::capabilities() as $capability_id => $label ) {
			unset( $label );
			if ( isset( self::$request_observations[ $capability_id ] ) ) {
				$snapshot[ $capability_id ] = self::$request_observations[ $capability_id ];
				continue;
			}
			if ( isset( $persisted[ $capability_id ] ) ) {
				$snapshot[ $capability_id ] = $persisted[ $capability_id ];
				continue;
			}

			$snapshot[ $capability_id ] = self::not_evaluated_record( $capability_id );
		}

		return $snapshot;
	}

	/**
	 * Persist at most one latest observation per known capability.
	 *
	 * This is an informational cross-request snapshot only. The option is
	 * explicitly non-autoloaded and never read by runtime adapters for
	 * compatibility decisions.
	 *
	 * @return bool Whether storage changed successfully.
	 */
	public static function persist_request_snapshot() {
		if ( array() === self::$request_observations ) {
			return false;
		}

		$records = self::read_persisted_records();
		foreach ( self::$request_observations as $capability_id => $observation ) {
			$records[ $capability_id ] = $observation;
		}

		$payload = array(
			'schema_version' => self::SCHEMA_VERSION,
			'records'        => $records,
		);

		$encoded = wp_json_encode( $payload );
		if ( ! is_string( $encoded ) || strlen( $encoded ) > self::MAX_SNAPSHOT_BYTES ) {
			return false;
		}

		if ( false === get_option( self::OPTION, false ) ) {
			return (bool) add_option( self::OPTION, $payload, '', false );
		}

		return (bool) update_option( self::OPTION, $payload, false );
	}

	/** @return array<string,array<string,mixed>> */
	private static function read_persisted_records() {
		$payload = get_option( self::OPTION, null );
		if ( ! is_array( $payload ) || array_keys( $payload ) !== array( 'schema_version', 'records' ) ) {
			return array();
		}
		if ( self::SCHEMA_VERSION !== $payload['schema_version'] || ! is_array( $payload['records'] ) ) {
			return array();
		}

		$encoded = wp_json_encode( $payload );
		if ( ! is_string( $encoded ) || strlen( $encoded ) > self::MAX_SNAPSHOT_BYTES ) {
			return array();
		}

		$now          = time();
		$host_version = self::current_host_version();
		$records      = array();
		foreach ( $payload['records'] as $capability_id => $record ) {
			$validated = self::validate_persisted_record( $capability_id, $record, $now, $host_version );
			if ( null !== $validated ) {
				$records[ $capability_id ] = $validated;
			}
		}

		return $records;
	}

	/**
	 * @param mixed       $capability_id Candidate capability ID.
	 * @param mixed       $record        Persisted record.
	 * @param int         $now           Current Unix time.
	 * @param string|null $host_version  Current host version.
	 * @return array<string,mixed>|null
	 */
	private static function validate_persisted_record( $capability_id, $record, $now, $host_version ) {
		if ( ! is_string( $capability_id ) || ! isset( self::capabilities()[ $capability_id ] ) || ! is_array( $record ) ) {
			return null;
		}

		$expected_keys = array( 'capability_id', 'state', 'reason_id', 'summary', 'observed_at', 'host_version' );
		if ( array_keys( $record ) !== $expected_keys || $record['capability_id'] !== $capability_id ) {
			return null;
		}
		if ( ! is_string( $record['state'] ) || ! in_array( $record['state'], self::states(), true ) ) {
			return null;
		}
		if ( ! is_string( $record['reason_id'] ) || ! isset( self::reasons()[ $record['reason_id'] ] ) ) {
			return null;
		}
		try {
			self::assert_state_reason_pair( $record['state'], $record['reason_id'] );
		} catch ( InvalidArgumentException $exception ) {
			unset( $exception );
			return null;
		}
		if ( self::summary_for_reason( $record['reason_id'] ) !== $record['summary'] ) {
			return null;
		}
		if ( ! is_int( $record['observed_at'] ) ) {
			return null;
		}
		if (
			$record['observed_at'] < ( $now - self::SNAPSHOT_TTL_SECONDS ) ||
			$record['observed_at'] > ( $now + self::FUTURE_CLOCK_SKEW )
		) {
			return null;
		}
		if ( ! self::valid_host_version( $record['host_version'] ) || $record['host_version'] !== $host_version ) {
			return null;
		}

		return $record;
	}

	/** @param mixed $capability_id Candidate capability ID. */
	private static function assert_capability( $capability_id ) {
		if ( ! is_string( $capability_id ) || ! isset( self::capabilities()[ $capability_id ] ) ) {
			throw new InvalidArgumentException( 'Unknown Gravity Flow diagnostics capability ID.' );
		}
	}

	/** @param mixed $state Candidate state. */
	private static function assert_state( $state ) {
		if ( ! is_string( $state ) || ! in_array( $state, self::states(), true ) ) {
			throw new InvalidArgumentException( 'Unknown Gravity Flow diagnostics state.' );
		}
	}

	/** @param mixed $reason_id Candidate reason ID. */
	private static function assert_reason( $reason_id ) {
		if ( ! is_string( $reason_id ) || ! isset( self::reasons()[ $reason_id ] ) ) {
			throw new InvalidArgumentException( 'Unknown Gravity Flow diagnostics reason ID.' );
		}
	}

	/**
	 * Prevent contradictory or unclassified state/reason combinations.
	 *
	 * @param string $state     State.
	 * @param string $reason_id Reason ID.
	 */
	private static function assert_state_reason_pair( $state, $reason_id ) {
		if ( self::STATE_AVAILABLE === $state && self::REASON_CONTRACT_SATISFIED !== $reason_id ) {
			throw new InvalidArgumentException( 'AVAILABLE requires the contract-satisfied reason.' );
		}
		if ( self::STATE_NOT_EVALUATED === $state && self::REASON_NOT_EVALUATED !== $reason_id ) {
			throw new InvalidArgumentException( 'NOT_EVALUATED requires the not-evaluated reason.' );
		}
		if (
			in_array( $state, array( self::STATE_DEGRADED, self::STATE_UNAVAILABLE ), true ) &&
			in_array( $reason_id, array( self::REASON_CONTRACT_SATISFIED, self::REASON_NOT_EVALUATED ), true )
		) {
			throw new InvalidArgumentException( 'A non-available evaluated state requires a classified failure reason.' );
		}
	}

	/** @param string $reason_id Known reason ID. */
	private static function summary_for_reason( $reason_id ) {
		$summary = self::reasons()[ $reason_id ] ?? '';
		if ( '' === $summary || strlen( $summary ) > self::MAX_SUMMARY_BYTES ) {
			throw new LogicException( 'Gravity Flow diagnostics reason summary is invalid.' );
		}
		return $summary;
	}

	/** @param string $capability_id Known capability ID. */
	private static function not_evaluated_record( $capability_id ) {
		return array(
			'capability_id' => $capability_id,
			'state'         => self::STATE_NOT_EVALUATED,
			'reason_id'     => self::REASON_NOT_EVALUATED,
			'summary'       => self::summary_for_reason( self::REASON_NOT_EVALUATED ),
			'observed_at'   => null,
			'host_version'  => null,
		);
	}

	/** @param mixed $version Candidate host version. */
	private static function valid_host_version( $version ) {
		if ( null === $version ) {
			return true;
		}
		return (
			is_string( $version ) &&
			strlen( $version ) <= self::MAX_HOST_VERSION_BYTES &&
			1 === preg_match( '/^[A-Za-z0-9][A-Za-z0-9._+\-]*$/', $version )
		);
	}

	/** @return string|null */
	private static function current_host_version() {
		if ( ! defined( 'GRAVITY_FLOW_VERSION' ) ) {
			return null;
		}

		$version = trim( (string) constant( 'GRAVITY_FLOW_VERSION' ) );
		return self::valid_host_version( $version ) ? $version : null;
	}

	/** @param string $state State. */
	private static function state_rank( $state ) {
		$ranks = array(
			self::STATE_NOT_EVALUATED => 0,
			self::STATE_AVAILABLE     => 1,
			self::STATE_DEGRADED      => 2,
			self::STATE_UNAVAILABLE   => 3,
		);
		return $ranks[ $state ] ?? -1;
	}

	/** Register one end-of-request persistence callback after the first observation. */
	private static function register_shutdown_persistence() {
		if ( self::$shutdown_registered ) {
			return;
		}
		add_action( 'shutdown', array( self::class, 'persist_request_snapshot' ), PHP_INT_MAX );
		self::$shutdown_registered = true;
	}
}
