<?php
/**
 * Bounded informational diagnostics for Gravity Flow compatibility observations.
 *
 * Runtime adapters own safety decisions. This class is reporting evidence only.
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

	private const MAX_HOST_VERSION_BYTES = 32;
	private const MAX_SNAPSHOT_BYTES     = 8192;
	private const SNAPSHOT_TTL_SECONDS   = 604800;
	private const FUTURE_CLOCK_SKEW      = 300;

	private const CAPABILITIES = array(
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

	private const STATES = array(
		self::STATE_AVAILABLE,
		self::STATE_DEGRADED,
		self::STATE_UNAVAILABLE,
		self::STATE_NOT_EVALUATED,
	);

	private const REASONS = array(
		self::REASON_CONTRACT_SATISFIED     => 'Required compatibility contract was satisfied in the observed context.',
		self::REASON_HOST_UNQUALIFIED       => 'Observed Gravity Flow host identity or version is not yet qualified for this capability.',
		self::REASON_SEAM_UNAVAILABLE       => 'A required host presentation seam or source contract was unavailable.',
		self::REASON_CONTEXT_UNAVAILABLE    => 'Required request-local render or caller context was unavailable.',
		self::REASON_SOURCE_INVALID         => 'Required host source data was missing, malformed, ambiguous, or outside its admitted domain.',
		self::REASON_NOT_APPLICABLE         => 'The capability was not applicable in the observed module, locale, or render context.',
		self::REASON_CONVERSION_UNAVAILABLE => 'The bounded Persian presentation conversion could not safely produce output.',
		self::REASON_NOT_EVALUATED          => 'No current trustworthy compatibility observation is available.',
	);

	private const RANKS = array(
		self::STATE_NOT_EVALUATED => 0,
		self::STATE_AVAILABLE     => 1,
		self::STATE_DEGRADED      => 2,
		self::STATE_UNAVAILABLE   => 3,
	);

	/** @var array<string,array<string,mixed>> */
	private static $request_observations = array();

	/** @var bool */
	private static $shutdown_registered = false;

	/** @return array<string,string> */
	public static function capabilities() {
		return self::CAPABILITIES;
	}

	/** @return array<int,string> */
	public static function states() {
		return self::STATES;
	}

	/** @return array<string,string> */
	public static function reasons() {
		return self::REASONS;
	}

	/**
	 * Record one request-local observation.
	 *
	 * UNAVAILABLE > DEGRADED > AVAILABLE > NOT_EVALUATED. Equal-state later
	 * observations replace earlier observations. Messages are catalog-owned;
	 * callers cannot attach free-form context or payload data.
	 *
	 * @throws InvalidArgumentException Invalid diagnostic input.
	 */
	public static function record( $capability_id, $state, $reason_id ) {
		self::assert_input( $capability_id, $state, $reason_id );

		$observation = array(
			'capability_id' => $capability_id,
			'state'         => $state,
			'reason_id'     => $reason_id,
			'summary'       => self::REASONS[ $reason_id ],
			'observed_at'   => time(),
			'host_version'  => self::current_host_version(),
		);
		$existing    = self::$request_observations[ $capability_id ] ?? null;

		if ( is_array( $existing ) && self::RANKS[ $state ] < self::RANKS[ $existing['state'] ] ) {
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
	 * Return reporting state only; never use this API for runtime activation.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function status_snapshot() {
		$persisted = self::read_persisted_records();
		$snapshot  = array();

		foreach ( self::CAPABILITIES as $capability_id => $label ) {
			unset( $label );
			$snapshot[ $capability_id ] = self::$request_observations[ $capability_id ]
				?? $persisted[ $capability_id ]
				?? self::not_evaluated_record( $capability_id );
		}

		return $snapshot;
	}

	/**
	 * Persist one latest reporting record per capability in one non-autoloaded option.
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
		if (
			! is_array( $payload ) ||
			array_keys( $payload ) !== array( 'schema_version', 'records' ) ||
			self::SCHEMA_VERSION !== $payload['schema_version'] ||
			! is_array( $payload['records'] )
		) {
			return array();
		}

		$encoded = wp_json_encode( $payload );
		if ( ! is_string( $encoded ) || strlen( $encoded ) > self::MAX_SNAPSHOT_BYTES ) {
			return array();
		}

		$records      = array();
		$now          = time();
		$host_version = self::current_host_version();
		foreach ( $payload['records'] as $capability_id => $record ) {
			if ( self::persisted_record_is_trustworthy( $capability_id, $record, $now, $host_version ) ) {
				$records[ $capability_id ] = $record;
			}
		}
		return $records;
	}

	private static function persisted_record_is_trustworthy( $capability_id, $record, $now, $host_version ) {
		if ( ! is_string( $capability_id ) || ! isset( self::CAPABILITIES[ $capability_id ] ) || ! is_array( $record ) ) {
			return false;
		}
		if ( array_keys( $record ) !== array( 'capability_id', 'state', 'reason_id', 'summary', 'observed_at', 'host_version' ) ) {
			return false;
		}
		if (
			$record['capability_id'] !== $capability_id ||
			! is_string( $record['state'] ) ||
			! in_array( $record['state'], self::STATES, true ) ||
			! is_string( $record['reason_id'] ) ||
			! isset( self::REASONS[ $record['reason_id'] ] ) ||
			! self::state_reason_pair_is_valid( $record['state'], $record['reason_id'] ) ||
			self::REASONS[ $record['reason_id'] ] !== $record['summary'] ||
			! is_int( $record['observed_at'] )
		) {
			return false;
		}
		if (
			$record['observed_at'] < ( $now - self::SNAPSHOT_TTL_SECONDS ) ||
			$record['observed_at'] > ( $now + self::FUTURE_CLOCK_SKEW ) ||
			! self::valid_host_version( $record['host_version'] ) ||
			$record['host_version'] !== $host_version
		) {
			return false;
		}
		return true;
	}

	private static function assert_input( $capability_id, $state, $reason_id ) {
		if ( ! is_string( $capability_id ) || ! isset( self::CAPABILITIES[ $capability_id ] ) ) {
			throw new InvalidArgumentException( 'Unknown Gravity Flow diagnostics capability ID.' );
		}
		if ( ! is_string( $state ) || ! in_array( $state, self::STATES, true ) ) {
			throw new InvalidArgumentException( 'Unknown Gravity Flow diagnostics state.' );
		}
		if ( ! is_string( $reason_id ) || ! isset( self::REASONS[ $reason_id ] ) ) {
			throw new InvalidArgumentException( 'Unknown Gravity Flow diagnostics reason ID.' );
		}
		if ( ! self::state_reason_pair_is_valid( $state, $reason_id ) ) {
			throw new InvalidArgumentException( 'Gravity Flow diagnostics state/reason pair is invalid.' );
		}
	}

	private static function state_reason_pair_is_valid( $state, $reason_id ) {
		if ( self::STATE_AVAILABLE === $state ) {
			return self::REASON_CONTRACT_SATISFIED === $reason_id;
		}
		if ( self::STATE_NOT_EVALUATED === $state ) {
			return self::REASON_NOT_EVALUATED === $reason_id;
		}
		return ! in_array( $reason_id, array( self::REASON_CONTRACT_SATISFIED, self::REASON_NOT_EVALUATED ), true );
	}

	private static function not_evaluated_record( $capability_id ) {
		return array(
			'capability_id' => $capability_id,
			'state'         => self::STATE_NOT_EVALUATED,
			'reason_id'     => self::REASON_NOT_EVALUATED,
			'summary'       => self::REASONS[ self::REASON_NOT_EVALUATED ],
			'observed_at'   => null,
			'host_version'  => null,
		);
	}

	private static function current_host_version() {
		if ( ! defined( 'GRAVITY_FLOW_VERSION' ) ) {
			return null;
		}
		$version = trim( (string) constant( 'GRAVITY_FLOW_VERSION' ) );
		return self::valid_host_version( $version ) ? $version : null;
	}

	private static function valid_host_version( $version ) {
		return null === $version || (
			is_string( $version ) &&
			strlen( $version ) <= self::MAX_HOST_VERSION_BYTES &&
			1 === preg_match( '/^[A-Za-z0-9][A-Za-z0-9._+\-]*$/', $version )
		);
	}

	private static function register_shutdown_persistence() {
		if ( self::$shutdown_registered ) {
			return;
		}
		add_action( 'shutdown', array( self::class, 'persist_request_snapshot' ), PHP_INT_MAX );
		self::$shutdown_registered = true;
	}
}
