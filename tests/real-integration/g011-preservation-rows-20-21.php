<?php
/**
 * G-011 Owner Matrix B rows 20/21: real licensed-host preservation controls.
 * Test-only. Forms, Entries and values are synthetic and disposable.
 * Runtime evidence is scoped to the exact WP/GF/PGR installation.
 */
defined( 'ABSPATH' ) || exit;

( static function (): void {
    $fail = static function ( string $reason ): void {
        throw new RuntimeException( 'G-011 rows 20/21: ' . $reason );
    };
    $artifact_dir = getenv( 'WU008_ARTIFACT_DIR' );
    if (
        ! is_string( $artifact_dir ) || ! is_dir( $artifact_dir ) ||
        ! class_exists( 'GFForms' ) || '3.1.1.1' !== GFForms::$version ||
        '542f56ae0747f3661d1474996527298027db3fb8ed3e6469a6391aaabf61069b' !== getenv( 'WU008_GF_SHA256' ) ||
        ! class_exists( 'PGR_GF_Confirmation_Jalali_Adapter' ) ||
        20 !== has_filter( 'gform_confirmation', array( 'PGR_GF_Confirmation_Jalali_Adapter', 'filter_confirmation' ) )
    ) {
        $fail( 'Exact licensed host or production Adapter unavailable.' );
    }

    $evidence = array(
        'contract' => 'G011-MATRIX-B-ROWS-20-21-EXACT-HOST-V1',
        'head' => getenv( 'WU008_PGR_SHA' ),
        'host_version' => GFForms::$version,
        'host_sha256' => getenv( 'WU008_GF_SHA256' ),
        'execution_level' => 'GFAPI persisted form/entry + authentic PHP Text Confirmation',
        'browser_qualification' => 'NOT_EXECUTED',
        'row_20' => array(),
        'row_21' => array(),
    );

    // Toggle only the existing presentation option. Probe the shared callback
    // count separately in native/enabled modes; never render tags a second time.
    $original_option = get_option( 'pgr_modules' );
    if ( ! is_array( $original_option ) || ! isset( $original_option['states'] ) ) {
        $fail( 'Module option missing.' );
    }
    $render = static function ( array $form, array $entry, bool $enabled ) use ( $fail ): array {
        $option = get_option( 'pgr_modules' );
        if ( ! is_array( $option ) || ! isset( $option['states'] ) ) {
            $fail( 'Module option disappeared during rendering.' );
        }
        $option['states']['jalali_presentation'] = $enabled;
        update_option( 'pgr_modules', $option );
        $calls = 0;
        $spy = static function ( $source ) use ( &$calls ) {
            ++$calls;
            return $source;
        };
        add_filter( 'gform_pre_replace_merge_tags', $spy, 998, 1 );
        try {
            $copy = $form;
            $html = GFFormDisplay::handle_confirmation( $copy, $entry, false );
        } finally {
            remove_filter( 'gform_pre_replace_merge_tags', $spy, 998 );
        }
        if ( ! is_string( $html ) ) {
            $fail( 'Selected Text Confirmation did not return HTML.' );
        }
        return array( 'html' => $html, 'callbacks' => $calls );
    };
    $create_form = static function ( string $title, array $fields, string $message ) use ( $fail ): array {
        $form_id = GFAPI::add_form( array( 'title' => $title, 'fields' => $fields ) );
        if ( ! is_int( $form_id ) || $form_id <= 0 ) {
            $fail( 'GFAPI could not create form: ' . $title );
        }
        $form = GFAPI::get_form( $form_id );
        if ( ! is_array( $form ) ) {
            $fail( 'GFAPI could not re-read form: ' . $title );
        }
        $form['confirmations'] = array(
            'row-qual' => array(
                'id' => 'row-qual', 'name' => 'Qualification',
                'type' => 'message', 'isActive' => true, 'isDefault' => true,
                'disableAutoformat' => true, 'message' => $message,
            ),
        );
        if ( true !== GFAPI::update_form( $form ) ) {
            $fail( 'GFAPI could not persist confirmation: ' . $title );
        }
        $form = GFAPI::get_form( $form_id );
        if ( ! is_array( $form ) ) {
            $fail( 'Updated form could not be recovered: ' . $title );
        }
        $found = false;
        foreach ( (array) ( $form['confirmations'] ?? array() ) as $confirmation ) {
            if (
                'message' === ( $confirmation['type'] ?? '' ) &&
                ! empty( $confirmation['isDefault'] ) &&
                ( $confirmation['message'] ?? null ) === $message
            ) {
                $found = true;
            }
        }
        if ( ! $found ) {
            $fail( 'Persisted confirmation content/selection mismatch: ' . $title );
        }
        return $form;
    };
    $add_entry = static function ( array $values ) use ( $fail ): array {
        $id = GFAPI::add_entry( $values );
        if ( is_wp_error( $id ) || ! is_int( $id ) || $id <= 0 ) {
            $fail( 'Unable to persist independent synthetic Entry.' );
        }
        $stored = GFAPI::get_entry( $id );
        if ( ! is_array( $stored ) ) {
            $fail( 'Unable to re-read independent synthetic Entry.' );
        }
        return $stored;
    };

    try {
        // ROW 20: Each of these three fields must have its true GF-registered
        // type on the recovered form. The custom Jalali field is NOT Gregorian.
        $mixed = $create_form(
            'G011 Owner Row20 Mixed Ownership',
            array(
                array( 'id' => 1, 'type' => 'pgr_jalali_date', 'label' => 'Already Jalali' ),
                array( 'id' => 2, 'type' => 'text', 'label' => 'Date-like Text' ),
                array( 'id' => 3, 'type' => 'date', 'label' => 'Visit', 'dateType' => 'datepicker', 'dateFormat' => 'mdy' ),
            ),
            '<p>ROW20_J: {Already Jalali:1}; END_J</p><p>ROW20_T: {Date-like Text:2}; END_T</p><p>ROW20_G: {Visit:3}; END_G</p>'
        );
        $types = array();
        foreach ( $mixed['fields'] as $field ) {
            $types[ (int) $field->id ] = $field;
        }
        if (
            ! isset( $types[1], $types[2], $types[3] ) ||
            'pgr_jalali_date' !== $types[1]->type ||
            $types[1] instanceof GF_Field_Date ||
            'text' !== $types[2]->type ||
            $types[2] instanceof GF_Field_Date ||
            ! $types[3] instanceof GF_Field_Date ||
            'mdy' !== $types[3]->dateFormat
        ) {
            $fail( 'Row20 recovered field types or Gregorian date format differ from the qualified fixture.' );
        }
        $mixed_entry = $add_entry( array(
            'form_id' => (int) $mixed['id'], 'status' => 'active',
            'date_created' => '2026-03-20 20:30:00',
            '1' => '1405-01-01', '2' => '2026-03-21', '3' => '03/21/2026',
        ) );
        foreach ( array( '1' => '1405-01-01', '2' => '2026-03-21', '3' => '03/21/2026' ) as $id => $raw ) {
            if ( ( $mixed_entry[ $id ] ?? null ) !== $raw ) {
                $fail( 'Row20 GFAPI did not retain source Entry value for field ' . $id );
            }
        }
        $native = $render( $mixed, $mixed_entry, false );
        $enabled = $render( $mixed, $mixed_entry, true );
        $left = 'ROW20_G: ';
        $right = '; END_G';
        $anchor = strpos( $native['html'], $left );
        $start = false === $anchor ? false : $anchor + strlen( $left );
        $end = false === $start ? false : strpos( $native['html'], $right, $start );
        $native_date = ( false === $start || false === $end ) ? null : substr( $native['html'], $start, $end - $start );
        $expected_html = '03/21/2026' === $native_date ?
            substr_replace( $native['html'], esc_html( '۰۱/۰۱/۱۴۰۵' ), $start, strlen( $native_date ) ) : null;
        $row20_good = is_string( $expected_html ) &&
            $enabled['html'] === $expected_html &&
            $enabled['html'] !== $native['html'] &&
            1 === substr_count( $native['html'], $left ) &&
            1 === substr_count( $native['html'], $right ) &&
            1 === $native['callbacks'] && 1 === $enabled['callbacks'];
        $stored_after = GFAPI::get_entry( (int) $mixed_entry['id'] );
        $row20_good = $row20_good && is_array( $stored_after ) &&
            ( $stored_after['1'] ?? null ) === $mixed_entry['1'] &&
            ( $stored_after['2'] ?? null ) === $mixed_entry['2'] &&
            ( $stored_after['3'] ?? null ) === $mixed_entry['3'];
        $evidence['row_20'] = array(
            'status' => $row20_good ? 'VERIFIED_HOST' : 'FAILED',
            'form_id' => (int) $mixed['id'], 'entry_id' => (int) $mixed_entry['id'],
            'field_types' => array( 'pgr_jalali_date', 'text', 'date' ),
            'native_gregorian_span' => $native_date,
            'native_sha256' => hash( 'sha256', $native['html'] ),
            'enabled_sha256' => hash( 'sha256', $enabled['html'] ),
            'expected_single_span_sha256' => is_string( $expected_html ) ? hash( 'sha256', $expected_html ) : null,
            'unowned_spans_byte_exact' => $enabled['html'] === $expected_html,
            'callbacks' => array( 'native' => $native['callbacks'], 'enabled' => $enabled['callbacks'] ),
            'entry_unchanged' => is_array( $stored_after ) && $stored_after['1'] === $mixed_entry['1'] &&
                $stored_after['2'] === $mixed_entry['2'] && $stored_after['3'] === $mixed_entry['3'],
        );
        if ( ! $row20_good ) {
            $fail( 'Row20 did not preserve Jalali/Text native bytes while converting only the Gregorian Date field.' );
        }

        // Lock negative bounds to the production presentation support policy.
        // 1800 dates are VALID; 1799-12-31 and 2124-03-20 are not.
        if (
            '1800-01-01' !== PGR_Jalali_Presentation::VALIDATED_PRODUCT_MIN ||
            '2124-03-19' !== PGR_Jalali_Presentation::VALIDATED_PRODUCT_MAX ||
            PGR_Jalali_Presentation::is_in_validated_product_range( 1799, 12, 31 ) ||
            PGR_Jalali_Presentation::is_in_validated_product_range( 2124, 3, 20 )
        ) {
            $fail( 'Row21 test boundary drifted from the authoritative supported date range.' );
        }

        // ROW 21: GFAPI persistence may reject/normalize impossible values.
        // Never label those inputs VERIFIED; only byte-exact cases that reach
        // the authentic selected Confirmation with their intended value count.
        $cases = array(
            array( 'id' => 'missing_date_updated', 'target' => 'date_updated', 'tag' => '{date_updated:format:Y/m/d}', 'mode' => 'missing' ),
            array( 'id' => 'missing_payment_date', 'target' => 'payment_date', 'tag' => '{payment_date:format:Y/m/d}', 'mode' => 'missing' ),
            array( 'id' => 'malformed_payment_date', 'target' => 'payment_date', 'tag' => '{payment_date:format:Y/m/d}', 'value' => 'bad-utc' ),
            array( 'id' => 'calendar_invalid_payment_date', 'target' => 'payment_date', 'tag' => '{payment_date:format:Y/m/d}', 'value' => '2026-02-30 12:00:00' ),
            array( 'id' => 'out_of_range_future_payment', 'target' => 'payment_date', 'tag' => '{payment_date:format:Y/m/d}', 'value' => '2124-03-20 00:00:00' ),
            array( 'id' => 'out_of_range_past_payment', 'target' => 'payment_date', 'tag' => '{payment_date:format:Y/m/d}', 'value' => '1799-12-31 00:00:00' ),
            array( 'id' => 'calendar_invalid_gf_date', 'target' => '2', 'tag' => '{Visit:2}', 'value' => '02/30/2026' ),
            array( 'id' => 'out_of_range_future_gf_date', 'target' => '2', 'tag' => '{Visit:2}', 'value' => '03/20/2124' ),
            array( 'id' => 'out_of_range_past_gf_date', 'target' => '2', 'tag' => '{Visit:2}', 'value' => '12/31/1799' ),
        );
        $verified = 0;
        $unproven = 0;
        foreach ( $cases as $case ) {
            $case_form = $create_form(
                'G011 Owner Row21 ' . $case['id'],
                array(
                    array( 'id' => 1, 'type' => 'text', 'label' => 'Name' ),
                    array( 'id' => 2, 'type' => 'date', 'label' => 'Visit', 'dateType' => 'datepicker', 'dateFormat' => 'mdy' ),
                ),
                '<p>ROW21_' . $case['id'] . ': ' . $case['tag'] . '; END_ROW21</p>'
            );
            $requested = array(
                'form_id' => (int) $case_form['id'],
                'status' => 'active', 'date_created' => '2026-03-20 20:30:00',
                '1' => 'synthetic', '2' => '03/21/2026',
            );
            if ( 'missing' !== ( $case['mode'] ?? '' ) ) {
                $requested[ $case['target'] ] = $case['value'];
            } elseif ( '2' === $case['target'] ) {
                unset( $requested['2'] );
            }
            $entry_id = GFAPI::add_entry( $requested );
            if ( is_wp_error( $entry_id ) || ! is_int( $entry_id ) || $entry_id <= 0 ) {
                $evidence['row_21'][ $case['id'] ] = array(
                    'status' => 'NOT_PROVEN', 'persistence' => 'GFAPI_REJECTED',
                    'target' => $case['target'], 'supplied' => $case['value'] ?? null,
                );
                ++$unproven;
                continue;
            }
            $stored = GFAPI::get_entry( $entry_id );
            if ( ! is_array( $stored ) ) {
                $fail( 'Row21 could not re-read a GFAPI-accepted Entry.' );
            }
            $observed = $stored[ $case['target'] ] ?? null;
            $qualified = 'missing' === ( $case['mode'] ?? '' ) ?
                ( ! isset( $stored[ $case['target'] ] ) || '' === $observed ) :
                ( $observed === $case['value'] );
            // Observe even normalized values, but never upgrade an
            // unpreserved input to VERIFIED merely because output is native.
            $nat = $render( $case_form, $stored, false );
            $on = $render( $case_form, $stored, true );
            $byte_exact = $nat['html'] === $on['html'];
            $unchanged = GFAPI::get_entry( $entry_id );
            $storage_safe = is_array( $unchanged ) &&
                ( $unchanged[ $case['target'] ] ?? null ) === $observed;
            if ( $qualified && ( ! $byte_exact || ! $storage_safe || 1 !== $nat['callbacks'] || 1 !== $on['callbacks'] ) ) {
                $fail( 'Row21 admissible invalid input mutated native bytes/state/callbacks: ' . $case['id'] );
            }
            $status = $qualified && $byte_exact && $storage_safe ? 'VERIFIED_HOST' : 'NOT_PROVEN';
            if ( 'VERIFIED_HOST' === $status ) {
                ++$verified;
            } else {
                ++$unproven;
            }
            $evidence['row_21'][ $case['id'] ] = array(
                'status' => $status,
                'persistence' => $qualified ? 'GFAPI_PRESERVED_INTENDED_INPUT' : 'GFAPI_NORMALIZED_OR_DEFAULTED',
                'form_id' => (int) $case_form['id'], 'entry_id' => (int) $entry_id,
                'target' => $case['target'], 'supplied' => $case['value'] ?? null,
                'persisted' => $observed,
                'native_sha256' => hash( 'sha256', $nat['html'] ),
                'enabled_sha256' => hash( 'sha256', $on['html'] ),
                'native_byte_exact' => $byte_exact,
                'callbacks' => array( 'native' => $nat['callbacks'], 'enabled' => $on['callbacks'] ),
                'entry_unchanged' => $storage_safe,
            );
        }
        $evidence['row_21_summary'] = array(
            'total' => count( $cases ),
            'verified_host' => $verified,
            'not_proven' => $unproven,
            'closure' => 0 === $unproven ? 'HOST_VERIFIED' : 'NOT_CLOSED',
        );
    } finally {
        update_option( 'pgr_modules', $original_option );
        // Synthetic forms/Entries are confined to the disposable WU008 DB.
    }

    $output = $artifact_dir . '/g011-owner-matrix-b-rows-20-21.json';
    if ( false === file_put_contents( $output, wp_json_encode( $evidence, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ) ) {
        $fail( 'Unable to persist bounded evidence JSON.' );
    }
    echo 'G011 rows 20/21: licensed-host evidence captured; review per-case NOT_PROVEN before closure.' . "\n";
} )();
