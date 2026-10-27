<?php
/**
 * Disposable WU008-only explicit Jalali Merge Tag qualification (not production).
 * Never installed or loaded by PersianGravity runtime.
 */
defined( 'ABSPATH' ) || exit;

$artifact_dir = getenv( 'WU008_ARTIFACT_DIR' );
if ( ! is_string( $artifact_dir ) || ! is_dir( $artifact_dir ) ||
	! class_exists( 'GFAPI' ) || ! class_exists( 'GF_Field_Date' ) ||
	! class_exists( 'GFForms' ) || '3.1.1.1' !== GFForms::$version ) {
	throw new RuntimeException( 'G011 prototype requires exact licensed WU008 GF 3.1.1.1.' );
}
require_once dirname( __DIR__, 2 ) . '/includes/class-pgr-gregorian-jalali-converter.php';
require_once dirname( __DIR__, 2 ) . '/includes/class-pgr-jalali-presentation.php';

$evidence = array(
	'contract'       => 'WU008_G011_EXPLICIT_TAG_PROTOTYPE_V1',
	'head'           => getenv( 'WU008_PGR_SHA' ),
	'gf_version'     => GFForms::$version,
	'gf_sha256'      => getenv( 'WU008_GF_SHA256' ),
	'level'          => 'LICENSED_GFAPI_PHP_ONLY',
	'product_wiring' => false,
	'syntax' => 'pgr_jdate_field_2 (no colon-number: GF 3.1.1.1 treats colon-number as a native field tag)',
	'methods'        => array(),
);
foreach ( array( array( 'GFCommon', 'replace_variables' ), array( 'GFCommon', 'replace_variables_prepopulate' ),
	array( 'GFFormDisplay', 'handle_confirmation' ), array( 'GFAPI', 'send_notification' ) ) as $member ) {
	$reflection = new ReflectionMethod( $member[0], $member[1] );
	$source = $reflection->getFileName();
	$evidence['host_source'][ $member[0] . '::' . $member[1] ] = array(
		'file'       => basename( (string) $source ),
		'start_line' => $reflection->getStartLine(),
		'end_line'   => $reflection->getEndLine(),
		'sha256'     => hash_file( 'sha256', $source ),
	);
}
if ( 'Asia/Tehran' !== wp_timezone_string() ) {
	// This is test fixture configuration, never a product setting.
	update_option( 'timezone_string', 'Asia/Tehran' );
}
$form_id = GFAPI::add_form( array(
	'title'  => 'WU008 disposable explicit Jalali tags',
	'fields' => array(
		array( 'id' => 1, 'type' => 'text', 'label' => 'Name' ),
		array( 'id' => 2, 'type' => 'date', 'label' => 'Visit', 'dateType' => 'datepicker', 'dateFormat' => 'mdy' ),
		array( 'id' => 3, 'type' => 'text', 'label' => 'Not a date' ),
	),
) );
if ( ! is_int( $form_id ) || $form_id <= 0 ) {
	throw new RuntimeException( 'G011 prototype add_form failed.' );
}
$form = GFAPI::get_form( $form_id );
$body = '<p>WU008_G011_MARKER | Created={pgr_jdate_created} | VisitJ={pgr_jdate_field_2} | '
	. 'Name={Name:1} | VisitNative={Visit:2} | WrongType={pgr_jdate_field_3} | '
	. 'Unknown={pgr_jdate_field_99} | Untouched=plain-text.</p>';
$form['confirmations'] = array( 'g011-test' => array(
	'id' => 'g011-test', 'name' => 'G011 selected Text', 'type' => 'message',
	'isDefault' => true, 'isActive' => true, 'disableAutoformat' => true, 'message' => $body,
) );
$notification = array(
	'id' => 'g011-notify', 'name' => 'G011 disposable Notification', 'event' => 'form_submission',
	'to' => 'probe@example.com', 'toType' => 'email',
	'from' => 'probe@example.com', 'fromName' => 'WU008',
	'subject' => 'G011 disposable', 'message' => $body,
	'isActive' => true, 'disableAutoformat' => true,
);
$form['notifications'] = array( 'g011-notify' => $notification );
if ( true !== GFAPI::update_form( $form ) ) {
	throw new RuntimeException( 'G011 prototype update_form failed.' );
}
$form = GFAPI::get_form( $form_id );
$entry_id = GFAPI::add_entry( array(
	'form_id' => $form_id, 'status' => 'active', 'date_created' => '2026-03-20 21:30:00',
	'1' => 'Ada', '2' => '03/20/2026', '3' => '03/20/2026',
) );
$entry = GFAPI::get_entry( $entry_id );
if ( ! is_array( $entry ) || '2026-03-20 21:30:00' !== $entry['date_created'] ||
	'03/20/2026' !== $entry['2'] ) {
	throw new RuntimeException( 'G011 fixture not persisted as requested.' );
}
$evidence['persisted'] = array(
	'created_utc' => $entry['date_created'], 'visit_raw' => $entry['2'],
	'wrong_type_raw' => $entry['3'], 'timezone' => wp_timezone_string(),
);
$utc = new DateTimeImmutable( $entry['date_created'], new DateTimeZone( 'UTC' ) );
$expected_created = PGR_Jalali_Presentation::format_datetime( $utc, wp_timezone() );
$expected_visit = PGR_Jalali_Presentation::format_date( 2026, 3, 20 );
$evidence['expected'] = array( 'created' => $expected_created, 'visit' => $expected_visit );
if ( '۱۴۰۵/۰۱/۰۱، ۰۱:۰۰' !== $expected_created || '۱۴۰۴/۱۲/۲۹' !== $expected_visit ) {
	throw new RuntimeException( 'G008 expected timezone or Jalali boundary mismatch.' );
}
$stored_before = array(
	'entry' => GFAPI::get_entry( $entry_id ),
	'form' => wp_json_encode( GFAPI::get_form( $form_id ) ),
);
$handler_calls = 0;
$handler = static function ( $text, $current_form, $current_entry ) use ( &$handler_calls ) {
	if ( ! is_string( $text ) || ! str_contains( $text, '{pgr_jdate' ) ) {
		return $text;
	}
	++$handler_calls;
	if ( ! is_array( $current_form ) || ! is_array( $current_entry ) ) {
		return $text;
	}
	return preg_replace_callback(
		'/\{pgr_jdate_created\}|\{pgr_jdate_field_([0-9]+)\}/',
		static function ( $hit ) use ( $current_form, $current_entry ) {
			if ( '{pgr_jdate_created}' === $hit[0] ) {
				$raw = $current_entry['date_created'] ?? null;
				if ( ! is_string( $raw ) ) {
					return $hit[0];
				}
				$parsed = DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', $raw, new DateTimeZone( 'UTC' ) );
				return $parsed ? ( PGR_Jalali_Presentation::format_datetime( $parsed, wp_timezone() ) ?? $hit[0] ) : $hit[0];
			}
			$id = (int) $hit[1];
			foreach ( (array) ( $current_form['fields'] ?? array() ) as $field ) {
				if ( ! $field instanceof GF_Field_Date || (int) $field->id !== $id ) {
					continue;
				}
				$value = $current_entry[ (string) $id ] ?? null;
				$parts = is_string( $value ) ? GFCommon::parse_date( $value, $field->dateFormat ) : false;
				if ( ! is_array( $parts ) || ! isset( $parts['year'], $parts['month'], $parts['day'] ) ) {
					return $hit[0];
				}
				return PGR_Jalali_Presentation::format_date(
					(int) $parts['year'], (int) $parts['month'], (int) $parts['day']
				) ?? $hit[0];
			}
			return $hit[0];
		},
		$text
	);
};
$exercise = static function ( string $hook, bool $enabled ) use ( $form, $entry, $notification, $body, $handler, &$handler_calls ): array {
	$handler_calls = 0;
	$confirmation_native = 0;
	$notification_native = 0;
	$active_context = 'confirmation';
	$spy = static function ( $text ) use ( &$confirmation_native, &$notification_native, &$active_context ) {
		if ( is_string( $text ) && str_contains( $text, 'WU008_G011_MARKER' ) ) {
			if ( 'confirmation' === $active_context ) {
				++$confirmation_native;
			} else {
				++$notification_native;
			}
		}
		return $text;
	};
	$captured = array();
	$abort = static function ( $email ) use ( &$captured ) {
		$captured[] = array( 'message' => $email['message'] ?? null, 'subject' => $email['subject'] ?? null );
		$email['abort_email'] = true;
		return $email;
	};
	add_filter( 'gform_pre_replace_merge_tags', $spy, 998, 1 );
	add_filter( 'gform_pre_send_email', $abort, 1, 1 );
	// Defense in depth: no external mail even if GF changes its abort behavior.
	$block_wp_mail = static function () { return true; };
	add_filter( 'pre_wp_mail', $block_wp_mail, 1, 1 );
	// Pinned GF 3.1.1.1 queues notifications by default; force synchronous
	// processing for this disposable single-request observation only.
	$sync_notifications = static function () { return false; };
	add_filter( 'gform_is_asynchronous_notifications_enabled', $sync_notifications, 999, 1 );
	if ( $enabled ) {
		add_filter( $hook, $handler, 10, 3 );
	}
	try {
		$copy = $form;
		$html = GFFormDisplay::handle_confirmation( $copy, $entry, false );
		$active_context = 'notification';
		GFAPI::send_notification( $notification, $form, $entry );
	} finally {
		if ( $enabled ) {
			remove_filter( $hook, $handler, 10 );
		}
		remove_filter( 'gform_pre_replace_merge_tags', $spy, 998 );
		remove_filter( 'gform_pre_send_email', $abort, 1 );
		remove_filter( 'pre_wp_mail', $block_wp_mail, 1 );
		remove_filter( 'gform_is_asynchronous_notifications_enabled', $sync_notifications, 999 );
	}
	return array(
		'confirmation' => $html,
		'notification' => $captured[0]['message'] ?? null,
		'notification_attempts_aborted' => count( $captured ),
		'confirmation_native_callbacks' => $confirmation_native,
		'notification_native_callbacks' => $notification_native,
		'handler_callbacks' => $handler_calls,
	);
};
$methods = array( 'gform_replace_merge_tags', 'gform_pre_replace_merge_tags' );
foreach ( $methods as $hook ) {
	$disabled = $exercise( $hook, false );
	$enabled = $exercise( $hook, true );
	$ok = is_string( $enabled['confirmation'] ) && is_string( $enabled['notification'] ) &&
		1 === $enabled['notification_attempts_aborted'] && $enabled['handler_callbacks'] >= 2;
	foreach ( array( 'confirmation', 'notification' ) as $context ) {
		$html = $enabled[ $context ] ?? '';
		$ok = $ok && is_string( $html ) &&
			str_contains( $html, 'Created=' . $expected_created ) &&
			str_contains( $html, 'VisitJ=' . $expected_visit ) &&
			str_contains( $html, 'Name=Ada' ) &&
			str_contains( $html, 'VisitNative=03/20/2026' ) &&
			str_contains( $html, 'WrongType={pgr_jdate_field_3}' ) &&
			str_contains( $html, 'Unknown={pgr_jdate_field_99}' ) &&
			str_contains( $html, 'Untouched=plain-text.' );
	}
	$ok = $ok && 1 === $enabled['confirmation_native_callbacks'] &&
		1 === $disabled['confirmation_native_callbacks'] &&
		$enabled['notification_native_callbacks'] === $disabled['notification_native_callbacks'] &&
		1 === $enabled['notification_native_callbacks'];
	if ( ! $ok ) {
		// Synthetic fixture content only; neither licensed source nor private Entry data.
		$evidence['diagnostic'][ $hook ] = array(
			'confirmation' => substr( (string) $enabled['confirmation'], 0, 1200 ),
			'notification' => substr( (string) $enabled['notification'], 0, 1200 ),
		);
	}
	$evidence['methods'][ $hook ] = array(
		'status'       => $ok ? 'PASS' : 'FAIL',
		'confirmation' => array(
			'expected_tags_present' => str_contains( (string) $enabled['confirmation'], 'Created=' . $expected_created ),
			'disabled_custom_tag_retained' => str_contains( (string) $disabled['confirmation'], '{pgr_jdate_created}' ),
			'callback_baseline' => $disabled['confirmation_native_callbacks'],
			'callback_enabled' => $enabled['confirmation_native_callbacks'],
			'enabled_sha256' => hash( 'sha256', (string) $enabled['confirmation'] ),
		),
		'notification' => array(
			'expected_tags_present' => str_contains( (string) $enabled['notification'], 'Created=' . $expected_created ),
			'disabled_custom_tag_retained' => str_contains( (string) $disabled['notification'], '{pgr_jdate_created}' ),
			'callback_baseline' => $disabled['notification_native_callbacks'],
			'callback_enabled' => $enabled['notification_native_callbacks'],
			'email_attempts_aborted' => $enabled['notification_attempts_aborted'],
			'enabled_sha256' => hash( 'sha256', (string) $enabled['notification'] ),
		),
		'handler_callbacks' => $enabled['handler_callbacks'],
	);
	if ( $ok ) {
		$evidence['selected_method'] = $hook;
		break; // Do not run the alternative when the primary satisfies the contract.
	}
}
$evidence['stored_data_unchanged'] =
	GFAPI::get_entry( $entry_id ) === $stored_before['entry'] &&
	wp_json_encode( GFAPI::get_form( $form_id ) ) === $stored_before['form'];
$evidence['result'] = isset( $evidence['selected_method'] ) && $evidence['stored_data_unchanged'] ? 'PASS' : 'FAIL';
$file = $artifact_dir . '/g011-explicit-merge-tag-prototype.json';
if ( false === file_put_contents( $file, wp_json_encode( $evidence, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ) ) {
	throw new RuntimeException( 'Cannot write G011 prototype artifact.' );
}
if ( 'PASS' !== $evidence['result'] ) {
	echo "G011 EVIDENCE: " . wp_json_encode( array( 'methods' => $evidence['methods'], 'diagnostic' => $evidence['diagnostic'] ?? array() ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . "\n";
	throw new RuntimeException( 'G011 explicit merge-tag candidate failed; inspect bounded artifact.' );
}
echo 'G011 explicit tags: ' . $evidence['selected_method'] . " PASS, Confirmation + Notification, one native pass\n";
