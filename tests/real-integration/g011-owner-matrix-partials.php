<?php
/**
 * Licensed GF 3.1.1.1, G-011 Owner Matrix B: targeted supplementary
 * observations for previously PARTIAL rows. Test-only, disposable data.
 *
 * An observation DOES NOT close a row. Each row retains its own uncovered
 * acceptance obligations. SEAM_ONLY results are never persisted-host proof.
 */
defined( 'ABSPATH' ) || exit;

( static function (): void {
    $fail = static function ( string $message ): void {
        throw new RuntimeException( 'G011 supplementary Owner matrix: ' . $message );
    };
    $dir = getenv( 'WU008_ARTIFACT_DIR' );
    if (
        ! is_string( $dir ) || ! is_dir( $dir ) ||
        ! class_exists( 'GFForms' ) || '3.1.1.1' !== GFForms::$version ||
        '542f56ae0747f3661d1474996527298027db3fb8ed3e6469a6391aaabf61069b' !== getenv( 'WU008_GF_SHA256' ) ||
        20 !== has_filter( 'gform_confirmation', array( 'PGR_GF_Confirmation_Jalali_Adapter', 'filter_confirmation' ) )
    ) {
        $fail( 'Licensed package or real production gform_confirmation Adapter not qualified.' );
    }
    $saved_option = get_option( 'pgr_modules' );
    if ( ! is_array( $saved_option ) || ! isset( $saved_option['states'] ) ) {
        $fail( 'Presentation option unavailable.' );
    }
    $rows = array( 5, 6, 7, 8, 9, 10, 11, 18, 21, 27, 28, 29, 30, 31 );
    $record = array(
        'contract' => 'G011-OWNER-MATRIX-B-FOCUSED-PARTIAL-QUALIFICATION-V1',
        'head' => getenv( 'WU008_PGR_SHA' ),
        'gf_version' => GFForms::$version,
        'gf_sha256' => getenv( 'WU008_GF_SHA256' ),
        'environment' => 'EXACT_HOST_GFAPI_PHP',
        'not_browser' => true,
        'row_closure' => 'NOT_ASSESSED',
        'rows' => array(),
    );
    foreach ( $rows as $row ) {
        $record['rows'][ (string) $row ] = array(
            'status' => 'PARTIAL',
            'cases' => array(),
            'full_acceptance_not_inferred' => true,
        );
    }
    $render = static function ( array $form, array $entry, bool $enabled ) use ( $fail ): array {
        $states = get_option( 'pgr_modules' );
        if ( ! is_array( $states ) || ! isset( $states['states'] ) ) {
            $fail( 'Lost presentation option before render.' );
        }
        $states['states']['jalali_presentation'] = $enabled;
        update_option( 'pgr_modules', $states );
        $callbacks = 0;
        $observer = static function ( $text ) use ( &$callbacks ) {
            ++$callbacks;
            return $text;
        };
        add_filter( 'gform_pre_replace_merge_tags', $observer, 997, 1 );
        try {
            $copy = $form;
            $result = GFFormDisplay::handle_confirmation( $copy, $entry, false );
        } finally {
            remove_filter( 'gform_pre_replace_merge_tags', $observer, 997 );
        }
        if ( ! is_string( $result ) ) {
            $fail( 'Expected Text Confirmation in host test.' );
        }
        if ( 1 !== $callbacks ) {
            $fail( 'Shared Merge Tag callback replay/loss observed.' );
        }
        return array( 'html' => $result, 'callback_count' => $callbacks );
    };
    $fixture = static function (
        string $title,
        string $message,
        array $extra = array(),
        array $fields = array()
    ) use ( $fail ): array {
        if ( ! $fields ) {
            $fields = array( array( 'id' => 1, 'type' => 'text', 'label' => 'Name' ) );
        }
        $id = GFAPI::add_form( array( 'title' => $title, 'fields' => $fields ) );
        if ( ! is_int( $id ) || $id <= 0 ) {
            $fail( 'Could not add Form: ' . $title );
        }
        $form = GFAPI::get_form( $id );
        $form['confirmations'] = array(
            'matrix' => array(
                'id' => 'matrix', 'name' => 'Supplement',
                'type' => 'message', 'isDefault' => true, 'isActive' => true,
                'disableAutoformat' => true, 'message' => $message,
            ),
        );
        if ( true !== GFAPI::update_form( $form ) ) {
            $fail( 'Could not persist selected confirmation: ' . $title );
        }
        $form = GFAPI::get_form( $id );
        if ( ! is_array( $form ) ) {
            $fail( 'Could not re-read Form: ' . $title );
        }
        $found = false;
        foreach ( (array) ( $form['confirmations'] ?? array() ) as $confirmation ) {
            if (
                ! empty( $confirmation['isDefault'] ) &&
                ( $confirmation['message'] ?? null ) === $message
            ) {
                $found = true;
            }
        }
        if ( ! $found ) {
            $fail( 'Default Text confirmation not re-read: ' . $title );
        }
        $entry_values = array_merge(
            array(
                'form_id' => $id, 'status' => 'active',
                'date_created' => '2026-03-20 20:30:00',
                '1' => 'synthetic',
            ),
            $extra
        );
        $entry_id = GFAPI::add_entry( $entry_values );
        if ( ! is_int( $entry_id ) || $entry_id <= 0 ) {
            $fail( 'Could not persist synthetic Entry: ' . $title );
        }
        $entry = GFAPI::get_entry( $entry_id );
        if ( ! is_array( $entry ) ) {
            $fail( 'Could not re-read Entry: ' . $title );
        }
        return array( 'form' => $form, 'entry' => $entry, 'entry_id' => $entry_id );
    };
    $observe = static function (
        int $row,
        string $case,
        string $message,
        array $extra = array(),
        array $fields = array(),
        bool $must_stay_native = false
    ) use ( &$record, $fixture, $render, $fail ): array {
        $f = $fixture( 'G011 supplemental ' . $row . ' ' . $case, $message, $extra, $fields );
        $native = $render( $f['form'], $f['entry'], false );
        $enabled = $render( $f['form'], $f['entry'], true );
        $identical = $native['html'] === $enabled['html'];
        if ( $must_stay_native && ! $identical ) {
            $fail( 'Native-only case was changed at row ' . $row . ', case ' . $case );
        }
        $after = GFAPI::get_entry( $f['entry_id'] );
        if ( ! is_array( $after ) || $after !== $f['entry'] ) {
            $fail( 'GFAPI Entry mutated while rendering row ' . $row . ', case ' . $case );
        }
        $record['rows'][ (string) $row ]['cases'][ $case ] = array(
            'execution' => 'LICENSED_GFAPI_PHP',
            'status' => $must_stay_native ? 'VERIFIED_SUBCASE_NATIVE' : 'OBSERVED_HOST',
            'form_id' => (int) $f['form']['id'],
            'entry_id' => $f['entry_id'],
            'native_sha256' => hash( 'sha256', $native['html'] ),
            'enabled_sha256' => hash( 'sha256', $enabled['html'] ),
            'native_byte_exact' => $identical,
            'shared_callbacks_native' => $native['callback_count'],
            'shared_callbacks_enabled' => $enabled['callback_count'],
            'entry_unchanged' => true,
        );
        return array( 'fixture' => $f, 'native' => $native, 'enabled' => $enabled );
    };

    try {
        // Time composition, local-day boundary observations (5).
        $observe( 5, 'system_time_local_midnight',
            '<div>Clock: {date_created:time:format:Y/m/d}</div>',
            array( 'date_created' => '2026-03-20 20:30:00' ) );
        $observe( 5, 'system_time_before_local_midnight',
            '<div>Clock: {date_created:time:format:Y/m/d}</div>',
            array( 'date_created' => '2026-03-20 20:29:00' ) );

        // Updated system date GFAPI acceptance/defaulting (6).
        $updated = $observe( 6, 'date_updated_explicit',
            '<div>Updated: {date_updated:format:Y/m/d}</div>',
            array( 'date_updated' => '2026-03-22 20:30:00' ) );
        $record['rows']['6']['cases']['date_updated_explicit']['persisted'] =
            $updated['fixture']['entry']['date_updated'] ?? null;
        $missing_updated = $observe( 6, 'date_updated_missing',
            '<div>Updated: {date_updated:format:Y/m/d}</div>' );
        $record['rows']['6']['cases']['date_updated_missing']['persisted'] =
            $missing_updated['fixture']['entry']['date_updated'] ?? null;
        $record['rows']['6']['cases']['date_updated_missing']['status'] =
            'HOST_DEFAULTED_NOT_INVALID_INPUT_PROOF';

        // Positive/empty/missing/invalid payment_date pairs (7, 8).
        $paid = $observe( 7, 'payment_positive',
            'Paid={payment_date:format:Y/m/d}',
            array( 'payment_date' => '2026-03-22 20:30:00' ) );
        $empty = $observe( 7, 'payment_empty',
            'Paid={payment_date:format:Y/m/d}',
            array( 'payment_date' => '' ), array(), true );
        $record['rows']['7']['cases']['payment_positive']['persisted'] =
            $paid['fixture']['entry']['payment_date'] ?? null;
        $record['rows']['7']['cases']['payment_empty']['persisted'] =
            $empty['fixture']['entry']['payment_date'] ?? null;

        $absent = $observe( 8, 'payment_missing',
            'Payment={payment_date:format:Y/m/d}', array(), array(), true );
        $bad = $observe( 8, 'payment_malformed',
            'Payment={payment_date:format:Y/m/d}', array( 'payment_date' => 'bad-utc' ) );
        $record['rows']['8']['cases']['payment_missing']['persisted'] =
            $absent['fixture']['entry']['payment_date'] ?? null;
        $record['rows']['8']['cases']['payment_malformed']['persisted'] =
            $bad['fixture']['entry']['payment_date'] ?? null;
        $record['rows']['8']['cases']['payment_malformed']['status'] =
            'GFAPI_NORMALIZATION_REQUIRES_BOUNDARY_REVIEW';

        // today cannot be clock-locked in this real-host test (9).
        $observe( 9, 'today_current_clock_observation',
            'Today={today:format:Y/m/d} time={today:time:format:Y/m/d}' );
        $record['rows']['9']['controlled_day_rollover'] = 'NOT_EXECUTED';

        // Calendar and time grammar observations without Cartesian claims (10).
        $observe( 10, 'weekday_date_time',
            'weekday={date_created:format:l} | short={date_created:format:D} | civil={date_created:format:Y-m-d H\:i}' );
        $observe( 10, 'mixed_calendar_tokens',
            'calendar={date_created:format:Y y m n d j F}' );

        // Deliberately native-only unsupported/system-date grammars (11,18).
        $observe( 11, 'unsupported_week',
            'Unsupported={date_created:format:W}', array(), array(), true );
        $observe( 11, 'unsupported_modifier',
            'Unsupported={date_created:not-a-modifier}', array(), array(), true );
        $observe( 11, 'time_only_native',
            'TimeOnly={date_created:format:H:i}', array(), array(), true );
        foreach ( array( '2026-03-20 20:30:00', '2025-01-03 20:30:00' ) as $i => $date ) {
            $observe( 18, 'human_diff_' . $i,
                'Human={date_created:human} Diff={date_created:diff}',
                array( 'date_created' => $date ), array(), true );
        }

        // Mixed visible, hidden, script/code, attribute and filename (27).
        $tag = '{date_created:format:Y/m/d}';
        $html = '<div>Human=' . $tag .
            ' <a href="/?d=' . $tag . '" data-day="' . $tag . '">Label</a>' .
            ' <code>' . $tag . '</code> <span hidden>' . $tag . '</span>' .
            ' <script>var stamp="' . $tag . '";</script>' .
            ' path=/archive/' . $tag . ' filename=report-' . $tag . '.pdf</div>';
        $observe( 27, 'compound_human_machine_html', $html );

        // Ambiguous/repeated tags and adversarial upstream changes (28).
        $observe( 28, 'same_tag_multiple_occurrences',
            '<p>First=' . $tag . ' Second=' . $tag . ' /file/' . $tag . '</p>' );
        $f28 = $fixture( 'G011 supplemental 28 prefix', '<p>Anchor=' . $tag . '</p>' );
        $prefix = static function ( $output ) { return is_string( $output ) ? '<b>upstream</b>' . $output : $output; };
        add_filter( 'gform_confirmation', $prefix, 10, 1 );
        try {
            $a28 = $render( $f28['form'], $f28['entry'], false );
            $b28 = $render( $f28['form'], $f28['entry'], true );
        } finally {
            remove_filter( 'gform_confirmation', $prefix, 10 );
        }
        $record['rows']['28']['cases']['upstream_prefix'] = array(
            'execution' => 'LICENSED_GFAPI_PHP',
            'status' => 'OBSERVED_HOST',
            'native_sha256' => hash( 'sha256', $a28['html'] ),
            'enabled_sha256' => hash( 'sha256', $b28['html'] ),
            'native_byte_exact' => $a28['html'] === $b28['html'],
            'upstream_filter_priority' => 10,
            'entry_id' => $f28['entry_id'],
        );

        // A second, non-target Form with literal date-looking text (29).
        $observe( 29, 'unrelated_literal_form',
            '<p>Unrelated: 2026/03/21 and order 2026-03-21</p>',
            array(), array(), true );

        // Page/Redirect URL and encoding must remain GF-owned (30).
        foreach ( array( 'page', 'redirect' ) as $type ) {
            $f = $fixture( 'G011 supplemental 30 ' . $type, 'placeholder' );
            $form = $f['form'];
            $destination = array(
                'id' => 'transport', 'name' => 'Transport', 'type' => $type,
                'isDefault' => true, 'isActive' => true, 'pageId' => 1,
                'url' => home_url( '/?existing=x%2By' ),
                'queryString' =>
                    'day={date_created:format:Y/m/d}&day={date_created:raw}&literal=x%2By&reserved=a%26b',
            );
            $form['confirmations'] = array( 'transport' => $destination );
            if ( true !== GFAPI::update_form( $form ) ) {
                $fail( 'Could not persist Page/Redirect Confirmation.' );
            }
            $form = GFAPI::get_form( (int) $form['id'] );
            $reference = GFFormDisplay::get_confirmation_url( $destination, $form, $f['entry'] );
            $old = get_option( 'pgr_modules' );
            $old['states']['jalali_presentation'] = false;
            update_option( 'pgr_modules', $old );
            $copy = $form;
            $native = GFFormDisplay::handle_confirmation( $copy, $f['entry'], false );
            $old['states']['jalali_presentation'] = true;
            update_option( 'pgr_modules', $old );
            $copy = $form;
            $enabled = GFFormDisplay::handle_confirmation( $copy, $f['entry'], false );
            if (
                ! is_array( $native ) || ! is_array( $enabled ) ||
                $native !== $enabled ||
                ( $enabled['redirect'] ?? null ) !== $reference
            ) {
                $fail( 'Page/Redirect transport URL changed: ' . $type );
            }
            $record['rows']['30']['cases'][ $type . '_query_encoding' ] = array(
                'execution' => 'LICENSED_GFAPI_PHP',
                'status' => 'VERIFIED_SUBCASE_NATIVE',
                'native_url_sha256' => hash( 'sha256', (string) $reference ),
                'enabled_url_sha256' => hash( 'sha256', (string) $enabled['redirect'] ),
                'url_byte_exact' => true, 'http_browser' => 'NOT_EXECUTED',
                'entry_id' => $f['entry_id'],
            );
        }

        // Same-priority and earlier/later read-only hooks (31).
        $f31 = $fixture( 'G011 supplemental 31 hooks', 'Created=' . $tag );
        $order = array();
        $hooks = array();
        foreach ( array( 10, 20, 30 ) as $priority ) {
            $hooks[ $priority ] = static function ( $output ) use ( $priority, &$order ) {
                $order[] = $priority;
                return $output;
            };
            add_filter( 'gform_confirmation', $hooks[ $priority ], $priority, 1 );
        }
        try {
            $n31 = $render( $f31['form'], $f31['entry'], false );
            $native_order = $order;
            $order = array();
            $e31 = $render( $f31['form'], $f31['entry'], true );
            $enabled_order = $order;
        } finally {
            foreach ( $hooks as $priority => $hook ) {
                remove_filter( 'gform_confirmation', $hook, $priority );
            }
        }
        if ( $native_order !== array( 10, 20, 30 ) || $enabled_order !== array( 10, 20, 30 ) ) {
            $fail( 'Same-priority pre/post hook ordering or callback counts changed.' );
        }
        $record['rows']['31']['cases']['read_only_hooks_10_20_30'] = array(
            'execution' => 'LICENSED_GFAPI_PHP', 'status' => 'VERIFIED_SUBCASE_ORDER',
            'priority_order_native' => $native_order,
            'priority_order_enabled' => $enabled_order,
            'output_differs_for_eligible_date' => $n31['html'] !== $e31['html'],
            'non_idempotent_tie' => 'NOT_EXECUTED',
        );

        // Three unavailable-through-GFAPI invalid inputs: only a direct
        // private present_system_date() seam can see these exact raw values.
        // Not a real persisted GFAPI/Confirmation verdict (21 + 6).
        $method = new ReflectionMethod( 'PGR_GF_Confirmation_Jalali_Adapter', 'present_system_date' );
        foreach ( array(
            'date_updated_missing' => array( 'date_updated', array() ),
            'payment_malformed' => array( 'payment_date', array( 'payment_date' => 'bad-utc' ) ),
            'payment_calendar_invalid' => array( 'payment_date', array( 'payment_date' => '2026-02-30 12:00:00' ) ),
        ) as $case => $args ) {
            $result = $method->invoke( null, $args[0], 'format:Y/m/d', $args[1] );
            if ( null !== $result ) {
                $fail( 'Invalid raw date seam unexpectedly converted: ' . $case );
            }
            $record['rows']['21']['cases'][ $case ] = array(
                'execution' => 'SEAM_ONLY',
                'status' => 'SEAM_ONLY_VERIFIED',
                'converted' => false,
                'gfapi_persisted_invalid_input' => 'NOT_PROVEN',
            );
        }
        $record['rows']['21']['persisted_host_partial_evidence'] =
            'g011-owner-matrix-b-rows-20-21.json in same exact-Head WU008 artifact';

    } finally {
        update_option( 'pgr_modules', $saved_option );
    }
    $record['remaining_uncertainty'] = array(
        'controlled_clock_today_rollover',
        'full timezone/weekday/format combinations',
        'native-versus-enabled under host-rejected invalid UTC before GFAPI normalization',
        'complex adversarial HTML/anchor permutations',
        'real HTTP Page/Redirect encoding for each transport',
        'non-idempotent same-priority third-party callbacks',
        'browser coverage for untested source/transport combinations',
    );
    $record['row_closure'] = 'NOT_CLOSED';
    $file = $dir . '/g011-owner-matrix-partial-qualification.json';
    if (
        false === file_put_contents(
            $file,
            wp_json_encode( $record, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES )
        )
    ) {
        $fail( 'Unable to emit scoped supplementary JSON.' );
    }
    echo "G011 supplementary Owner rows: bounded licensed-host observations complete; no automatic row closure.\n";
} )();
