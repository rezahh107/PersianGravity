<?php
/**
 * G-011 test-only MU fixture for the real HTTP submission lifecycle.
 * Installed only in disposable WU008 WordPress; NEVER shipped by the plugin.
 */
defined( 'ABSPATH' ) || exit;
function pgr_g011_preprocess_probe_active( $form_id = null ) {
	$cfg = get_option( 'pgr_g011_preprocess_probe_active', array() );
	return is_array( $cfg ) && ! empty( $cfg['form_id'] ) &&
		( null === $form_id || (int) $cfg['form_id'] === (int) $form_id ) ? $cfg : false;
}
function pgr_g011_preprocess_probe_record( $kind, $data ) {
	$events = get_option( 'pgr_g011_preprocess_probe_events', array() );
	if ( ! is_array( $events ) ) {
		$events = array();
	}
	if ( count( $events ) < 50 ) {
		$events[] = array( 'kind' => $kind, 'data' => $data );
		update_option( 'pgr_g011_preprocess_probe_events', $events, false );
	}
}
add_filter( 'gform_pre_process', static function ( $form ) {
	$cfg = pgr_g011_preprocess_probe_active( $form['id'] ?? null );
	if ( ! $cfg ) {
		return $form;
	}
	$needle = 'Created={date_created:format:Y/m/d}';
	$before = $form['confirmations'] ?? array();
	$changed = 0;
	foreach ( $form['confirmations'] as $id => $confirmation ) {
		if ( 'message' !== ( $confirmation['type'] ?? null ) || ! is_string( $confirmation['message'] ?? null ) ) {
			continue;
		}
		$modified = str_replace( $needle, 'Created=' . $cfg['marker'], $confirmation['message'], $count );
		if ( $count ) {
			$form['confirmations'][ $id ]['message'] = $modified;
			$changed += $count;
		}
	}
	pgr_g011_preprocess_probe_record( 'pre_process', array(
		'changed_occurrences' => $changed,
		'changed_confirmations' => $before !== ( $form['confirmations'] ?? null ),
		'confirmation_count' => count( $form['confirmations'] ?? array() ),
	) );
	return $form;
}, 10, 1 );

$record_merge = static function ( $text, $form ) {
	$cfg = pgr_g011_preprocess_probe_active( $form['id'] ?? null );
	if ( $cfg && is_string( $text ) && ( false !== strpos( $text, $cfg['marker'] ) || false !== strpos( $text, 'Created=' ) ) ) {
		pgr_g011_preprocess_probe_record( 'third_party_' . current_filter(), array(
			'priority' => current_filter() === 'gform_pre_replace_merge_tags' ? $GLOBALS['pgr_g011_probe_priority'] ?? null : null,
			'marker_visible' => false !== strpos( $text, $cfg['marker'] ),
			'native_tag_visible' => false !== strpos( $text, 'Created={date_created:format:Y/m/d}' ),
			'selected_type' => $form['confirmation']['type'] ?? null,
		) );
	}
	return $text;
};
add_filter( 'gform_pre_replace_merge_tags', static function ( $text, $form ) use ( $record_merge ) {
	$GLOBALS['pgr_g011_probe_priority'] = 1;
	return $record_merge( $text, $form );
}, 1, 2 );
add_filter( 'gform_pre_replace_merge_tags', static function ( $text, $form ) use ( $record_merge ) {
	$GLOBALS['pgr_g011_probe_priority'] = 999;
	return $record_merge( $text, $form );
}, 999, 2 );
add_filter( 'gform_confirmation', static function ( $confirmation, $form ) {
	$cfg = pgr_g011_preprocess_probe_active( $form['id'] ?? null );
	if ( $cfg && is_string( $confirmation ) ) {
		pgr_g011_preprocess_probe_record( 'confirmation', array(
			'marker_visible_before_restore' => false !== strpos( $confirmation, $cfg['marker'] ),
			'selected_type' => $form['confirmation']['type'] ?? null,
		) );
		// Deliberately keep the displayed response usable in this disposable trial.
		return str_replace( $cfg['marker'], '۱۴۰۵/۰۱/۰۱', $confirmation );
	}
	return $confirmation;
}, 5, 2 );
