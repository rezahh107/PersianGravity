<?php
/** Deterministic checks for metadata-only source-admission evidence. */
function pgr_admission_json( $path ) {
    $data = json_decode( file_get_contents( $path ), true, 512, JSON_THROW_ON_ERROR );
    if ( ! is_array( $data ) ) {
        throw new RuntimeException( 'Admission JSON must decode to an array: ' . $path );
    }
    return $data;
}

function pgr_admission_unique_strings( $values, $label ) {
    if ( ! is_array( $values ) || $values !== array_values( $values ) || $values !== array_values( array_unique( $values ) ) ) {
        throw new RuntimeException( 'Invalid unique string list: ' . $label );
    }
    foreach ( $values as $value ) {
        if ( ! is_string( $value ) || '' === $value ) {
            throw new RuntimeException( 'Invalid string value: ' . $label );
        }
    }
}

function pgr_validate_admission( $root ) {
    $dir      = rtrim( $root, '/\\' ) . '/tools/i18n/admission';
    $baseline = pgr_admission_json( $dir . '/baseline.json' );
    $surfaces = pgr_admission_json( $dir . '/surfaces.json' );
    $glossary = pgr_admission_json( $dir . '/glossary.json' );
    $js       = pgr_admission_json( $dir . '/javascript.json' );
    $rtl      = pgr_admission_json( $dir . '/rtl-bidi.json' );

    $expected = array(
        'gravityforms' => array(
            'target_version'                => '3.1.1.1',
            'package_sha256'                => '542f56ae0747f3661d1474996527298027db3fb8ed3e6469a6391aaabf61069b',
            'vendor_pot_sha256'             => 'a4eb120ee9513552004400548c26a568612ace6accdf9fdcd60b8aa4b163bccf',
            'canonical_message_count'       => 4207,
            'context_message_count'         => 22,
            'plural_message_count'          => 16,
            'canonical_keyset_sha256'       => '1b92edc87f2d152cb98a026dde815ab93e3e8303eef2954e95b3a816a85801fb',
            'source_reference_index_sha256' => '599e6e80e4761d3968e3da8917f76fadfeb14b8204cc5d2d5220a97a09624b89',
            'source_reference_count'        => 6510,
            'vendor_pot_unique_keys'        => 4208,
            'vendor_pot_only_stale'         => 1,
            'reference_only_differences'    => 5,
        ),
        'gravityflow' => array(
            'target_version'                => '3.1.0',
            'package_sha256'                => 'ac0573b75831380417a21a455176e25eb746d718bbbd0bb70d6da6f48cba5404',
            'vendor_pot_sha256'             => '09a66357bb86fa4b425c2905a6c3417b057da18a9d423d934aa4c544be306961',
            'canonical_message_count'       => 1098,
            'context_message_count'         => 3,
            'plural_message_count'          => 6,
            'canonical_keyset_sha256'       => 'cea9efe9c99f0e4424de029137ecef044133129684918ba6523f4691e6d58c82',
            'source_reference_index_sha256' => 'b9a9a01620cf74738dfeb9e759f4af747a6a2e59a4b547ff659bfcc5314b5f75',
            'source_reference_count'        => 1576,
            'vendor_pot_unique_keys'        => 1098,
            'vendor_pot_only_stale'         => 0,
            'reference_only_differences'    => 3,
        ),
    );

    if ( 1 !== ( $baseline['admission_revision'] ?? null ) || 'U+001F' !== ( $baseline['identity_separator'] ?? null ) ) {
        throw new RuntimeException( 'Invalid admission revision/identity separator' );
    }
    if ( array_keys( $expected ) !== array_column( $baseline['products'] ?? array(), 'product' ) ) {
        throw new RuntimeException( 'Admission product ordering/uniqueness drift' );
    }

    $admitted = array();
    $indexes  = array();
    foreach ( $baseline['products'] as $record ) {
        $product = $record['product'];
        $want    = $expected[ $product ];
        if ( 'PACKAGE_INSPECTED_METADATA_ONLY' !== ( $record['source_status'] ?? null ) ||
            'METADATA_ONLY_ADMISSION' !== ( $record['admission_mode'] ?? null ) ||
            'OWNER_SUPPLIED_EXACT_PACKAGE' !== ( $record['project_source_authority'] ?? null ) ||
            'NOT_PROVEN' !== ( $record['vendor_authenticity'] ?? null ) ) {
            throw new RuntimeException( 'Invalid admission state: ' . $product );
        }
        foreach ( $want as $field => $value ) {
            if ( $record[ $field ] !== $value ) {
                throw new RuntimeException( 'Admission evidence drift: ' . $product . ':' . $field );
            }
        }
        foreach ( array( 'package_sha256', 'vendor_pot_sha256', 'canonical_keyset_sha256', 'source_reference_index_sha256' ) as $field ) {
            if ( ! preg_match( '/^[a-f0-9]{64}$/D', $record[ $field ] ) ) {
                throw new RuntimeException( 'Invalid admission hash: ' . $product . ':' . $field );
            }
        }
        if ( $record['target_version'] !== $record['observed_source_version'] ) {
            throw new RuntimeException( 'Observed version drift: ' . $product );
        }
        $consistency = $record['source_pot_consistency'] ?? array();
        if ( 'PASS' !== ( $consistency['status'] ?? null ) ||
            $record['canonical_message_count'] !== ( $consistency['source_backed_unique_keys'] ?? null ) ||
            $want['vendor_pot_unique_keys'] !== ( $consistency['vendor_pot_unique_keys'] ?? null ) ||
            0 !== ( $consistency['missing_from_vendor_pot'] ?? null ) ||
            $want['vendor_pot_only_stale'] !== ( $consistency['vendor_pot_only_stale'] ?? null ) ||
            0 !== ( $consistency['context_differences'] ?? null ) ||
            0 !== ( $consistency['plural_differences'] ?? null ) ||
            $want['reference_only_differences'] !== ( $consistency['reference_only_differences'] ?? null ) ||
            'STATIC_SOURCE_CONFIRMED_CUSTOM_TOKENIZER_AND_BYTE_RECONCILIATION' !== ( $consistency['extraction'] ?? null ) ||
            'NOT_EXECUTED_TOOLING_DOWNLOAD_BLOCKED' !== ( $consistency['wp_cli_regeneration'] ?? null ) ) {
            throw new RuntimeException( 'Incomplete source/POT consistency: ' . $product );
        }
        if ( $record['context_message_count'] > $record['canonical_message_count'] ||
            $record['plural_message_count'] > $record['canonical_message_count'] ||
            $record['source_reference_count'] < $record['canonical_message_count'] ) {
            throw new RuntimeException( 'Invalid admission counts: ' . $product );
        }
        $ids    = $record['message_identity_index']['evidence_ids'] ?? null;
        $sorted = $ids;
        if ( ! is_array( $sorted ) ) {
            throw new RuntimeException( 'Missing message evidence index: ' . $product );
        }
        sort( $sorted, SORT_STRING );
        pgr_admission_unique_strings( $ids, $product . ' message evidence' );
        if ( 'AGGREGATE_KEYSET_PLUS_REFERENCED_EVIDENCE' !== ( $record['message_identity_index']['mode'] ?? null ) || $ids !== $sorted ) {
            throw new RuntimeException( 'Non-deterministic message evidence index: ' . $product );
        }
        foreach ( $ids as $id ) {
            if ( ! preg_match( '/^[a-f0-9]{64}$/D', $id ) ) {
                throw new RuntimeException( 'Invalid message identity hash: ' . $product );
            }
        }
        $indexes[ $product ]  = array_fill_keys( $ids, true );
        $admitted[ $product ] = $record;
    }

    if ( 'EXPLICIT_SOURCE_PATH_RULES_ONLY_NO_SEMANTIC_FALLBACK' !== ( $surfaces['classification_method'] ?? null ) ) {
        throw new RuntimeException( 'Hidden/unknown surface classification fallback' );
    }
    $categories = array( 'frontend_runtime', 'workflow_runtime', 'entry_management_runtime', 'admin_builder', 'settings_integrations', 'developer_diagnostics', 'unclassified' );
    $controls   = array( 'native_select', 'enhanced_select', 'custom_dropdown', 'text_input', 'textarea', 'checkbox_radio', 'numeric_identifier', 'date_input', 'table', 'pagination', 'breadcrumb', 'action_menu', 'modal_dialog', 'tabs', 'toolbar', 'custom_widget', 'unknown' );
    $surface_ids = array();
    $surface_counts = array_fill_keys( array_keys( $admitted ), 0 );
    $aggregate_keys = array();
    foreach ( $surfaces['surfaces'] ?? array() as $surface ) {
        $id = $surface['product'] . '::' . $surface['category'] . '::' . $surface['entry_point'];
        if ( ! isset( $admitted[ $surface['product'] ] ) ||
            ! in_array( $surface['category'], $categories, true ) ||
            $id !== $surface['surface_id'] || isset( $surface_ids[ $id ] ) ||
            empty( $surface['entry_point_evidence'] ) || empty( $surface['source_path_rules'] ) ) {
            throw new RuntimeException( 'Invalid surface registry record: ' . $id );
        }
        pgr_admission_unique_strings( $surface['entry_point_evidence'], $id . ' entry-point evidence' );
        pgr_admission_unique_strings( $surface['source_path_rules'], $id . ' source-path rules' );
        pgr_admission_unique_strings( $surface['control_types'], $id . ' control types' );
        foreach ( $surface['control_types'] as $control ) {
            if ( ! in_array( $control, $controls, true ) ) {
                throw new RuntimeException( 'Invalid control type: ' . $control );
            }
        }
        if ( ! is_bool( $surface['rtl_surface'] ?? null ) ) {
            throw new RuntimeException( 'Invalid rtl_surface: ' . $id );
        }
        if ( 'aggregated_unresolved' === $surface['entry_point'] ) {
            $aggregate_key = $surface['product'] . '::' . $surface['category'];
            if ( isset( $aggregate_keys[ $aggregate_key ] ) ) {
                throw new RuntimeException( 'Duplicate aggregated unresolved surface: ' . $aggregate_key );
            }
            $aggregate_keys[ $aggregate_key ] = true;
        }
        $surface_ids[ $id ] = true;
        ++$surface_counts[ $surface['product'] ];
    }
    foreach ( $admitted as $product => $record ) {
        $summary = $surfaces['summary'][ $product ] ?? null;
        if ( ! is_array( $summary ) ||
            $record['canonical_message_count'] !== ( $summary['unique_messages'] ?? null ) ||
            ( $summary['classified_messages'] ?? -1 ) + ( $summary['unclassified_messages'] ?? -1 ) !== $summary['unique_messages'] ||
            ( $summary['multi_surface_messages'] ?? -1 ) < 0 || ( $summary['multi_surface_messages'] ?? -1 ) > $summary['unique_messages'] ||
            $surface_counts[ $product ] !== ( $summary['surfaces'] ?? null ) ) {
            throw new RuntimeException( 'Surface summary drift: ' . $product );
        }
    }
    if ( array_keys( $admitted ) !== array_keys( $surfaces['summary'] ?? array() ) ) {
        throw new RuntimeException( 'Surface summary product scope drift' );
    }

    $js_classifications = array( 'REGISTERED_AND_NATIVE_TRANSLATION_ATTACHED', 'REGISTERED_BUT_TRANSLATION_NOT_ATTACHED', 'ENQUEUED_WITH_PHP_LOCALIZED_DATA', 'STATIC_FILE_ONLY', 'UNKNOWN' );
    $php_js_classes     = array( 'PHP_TRANSLATED_STRINGS_PASSED_TO_JS', 'CONFIG_DATA_PASSED_TO_JS', 'MIXED' );
    if ( array_keys( $admitted ) !== array_keys( $js ) ) {
        throw new RuntimeException( 'JavaScript census product scope/order drift' );
    }
    foreach ( $js as $product => $census ) {
        if ( $admitted[ $product ]['target_version'] !== ( $census['target_version'] ?? null ) ||
            0 !== ( $census['runtime_activated_handles'] ?? null ) ||
            'NOT_PRESENT_IN_TARGET_SOURCE' !== ( $census['script_modules'] ?? null ) ) {
            throw new RuntimeException( 'JavaScript admission scope drift: ' . $product );
        }
        $records = $census['records'] ?? array();
        if ( count( $records ) !== ( $census['classic_script_surfaces'] ?? null ) ) {
            throw new RuntimeException( 'Classic-script count drift: ' . $product );
        }
        $handles = array();
        $native  = 0;
        $php_js  = 0;
        foreach ( $records as $script ) {
            $handle = $script['handle'] ?? '';
            if ( ! preg_match( '/^[a-zA-Z0-9_-]+$/D', $handle ) || isset( $handles[ $handle ] ) ||
                ! in_array( $script['classification'] ?? null, $js_classifications, true ) || empty( $script['evidence'] ) ) {
                throw new RuntimeException( 'Invalid JavaScript census record: ' . $product . ':' . $handle );
            }
            pgr_admission_unique_strings( $script['evidence'], $product . ':' . $handle . ' evidence' );
            $handles[ $handle ] = true;
            if ( 'REGISTERED_AND_NATIVE_TRANSLATION_ATTACHED' === $script['classification'] ) {
                ++$native;
            }
            if ( array_key_exists( 'php_localized_js', $script ) ) {
                if ( ! in_array( $script['php_localized_js'], $php_js_classes, true ) ) {
                    throw new RuntimeException( 'Invalid PHP-localized-JS class: ' . $product . ':' . $handle );
                }
                ++$php_js;
            }
        }
        if ( $native !== ( $census['native_translation_attached'] ?? null ) ||
            count( $records ) - $native !== ( $census['registered_without_translation_attachment'] ?? null ) ||
            $php_js !== ( $census['php_localized_js'] ?? null ) ) {
            throw new RuntimeException( 'JavaScript census aggregate drift: ' . $product );
        }
    }

    $rtl_ids = $rtl['surface_ids'] ?? array();
    pgr_admission_unique_strings( $rtl_ids, 'RTL surface ids' );
    $registry_ids = array_keys( $surface_ids );
    $rtl_sorted   = $rtl_ids;
    $registry_sorted = $registry_ids;
    sort( $rtl_sorted, SORT_STRING );
    sort( $registry_sorted, SORT_STRING );
    $rtl_items = array( 'direction', 'label_alignment', 'text_input_behavior', 'native_selects', 'enhanced_or_custom_dropdowns', 'arrows_carets', 'menus', 'tabs', 'tables', 'pagination', 'breadcrumbs', 'modal_dialogs', 'numeric_fields', 'mixed_persian_ltr_content' );
    $bidi_risks = array( 'PERSIAN_PLUS_LATIN_TERM', 'URL_OR_EMAIL', 'RUNTIME_PLACEHOLDER', 'NUMERIC_IDENTIFIER', 'MIXED_PUNCTUATION', 'SLASH_HYPHEN_MIX' );
    if ( $rtl_sorted !== $registry_sorted || 0 !== ( $rtl['production_patches_added'] ?? null ) ||
        'NOT_EXECUTED_ENVIRONMENT_UNAVAILABLE' !== ( $rtl['runtime_execution'] ?? null ) ||
        $rtl_items !== ( $rtl['checklist_items'] ?? null ) || $bidi_risks !== ( $rtl['bidi_risk_classes'] ?? null ) ) {
        throw new RuntimeException( 'RTL/Bidi admission drift' );
    }

    $types   = array( 'HARD_TERM', 'PREFERRED_TERM', 'DO_NOT_TRANSLATE' );
    $reviews = array( 'PROVISIONAL', 'APPROVED' );
    if ( true === ( $glossary['entry_translation_hard_locked'] ?? null ) || array_keys( $admitted ) !== array_keys( $glossary['extensions'] ?? array() ) ) {
        throw new RuntimeException( 'Glossary scope/Entry-lock drift' );
    }
    $common_terms = array();
    foreach ( $glossary['common'] ?? array() as $term ) {
        if ( ! is_string( $term['term'] ?? null ) || '' === $term['term'] || isset( $common_terms[ $term['term'] ] ) ||
            ! in_array( $term['type'] ?? null, $types, true ) || ! in_array( $term['review'] ?? null, $reviews, true ) ) {
            throw new RuntimeException( 'Invalid common glossary state' );
        }
        $common_terms[ $term['term'] ] = true;
        foreach ( array_keys( $admitted ) as $product ) {
            if ( empty( $term['evidence'][ $product ] ) ) {
                throw new RuntimeException( 'Missing cross-product glossary evidence' );
            }
            pgr_admission_unique_strings( $term['evidence'][ $product ], $term['term'] . ':' . $product . ' evidence' );
            foreach ( $term['evidence'][ $product ] as $id ) {
                if ( ! isset( $indexes[ $product ][ $id ] ) ) {
                    throw new RuntimeException( 'Unknown common glossary evidence' );
                }
            }
        }
    }
    foreach ( $glossary['extensions'] as $product => $terms ) {
        $extension_terms = array();
        foreach ( $terms as $term ) {
            if ( ! is_string( $term['term'] ?? null ) || '' === $term['term'] || isset( $extension_terms[ $term['term'] ] ) ||
                ! in_array( $term['type'] ?? null, $types, true ) || ! in_array( $term['review'] ?? null, $reviews, true ) ) {
                throw new RuntimeException( 'Invalid product glossary state: ' . $product );
            }
            $extension_terms[ $term['term'] ] = true;
            pgr_admission_unique_strings( $term['evidence'] ?? null, $product . ':' . $term['term'] . ' evidence' );
            foreach ( $term['evidence'] as $id ) {
                if ( ! isset( $indexes[ $product ][ $id ] ) ) {
                    throw new RuntimeException( 'Unknown extension evidence: ' . $product );
                }
            }
        }
    }
    $protected = array();
    foreach ( $glossary['anti_glossary'] ?? array() as $term ) {
        if ( ! is_string( $term['term'] ?? null ) || '' === $term['term'] || isset( $protected[ $term['term'] ] ) ||
            'DO_NOT_TRANSLATE' !== ( $term['type'] ?? null ) || ! in_array( $term['review'] ?? null, $reviews, true ) ) {
            throw new RuntimeException( 'Invalid anti-glossary state' );
        }
        $protected[ $term['term'] ] = true;
    }

    return $admitted;
}
