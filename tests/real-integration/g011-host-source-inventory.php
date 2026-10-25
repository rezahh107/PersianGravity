<?php
/**
 * Exact licensed Gravity Forms 3.1.1.1 confirmation-path source inventory.
 *
 * This probe writes no vendor source, entry data, or credentials to output.
 * Execute only in the disposable, package-verified WordPress CI lane.
 */
if ( ! defined( 'ABSPATH' ) || ! class_exists( 'GFForms' ) || '3.1.1.1' !== GFForms::$version ) {
    fwrite( STDERR, "Exact authenticated Gravity Forms bootstrap required.\n" );
    exit( 1 );
}
$base = realpath( GFCommon::get_base_path() );
if ( ! is_string( $base ) || ! is_dir( $base ) ) {
    fwrite( STDERR, "Gravity Forms source unavailable.\n" );
    exit( 1 );
}

$terms = array(
    'gform_confirmation',
    'gform_pre_replace_merge_tags',
    'gform_replace_merge_tags',
    'gform_confirmation_loaded',
    'get_confirmation',
    'replace_variables',
    'replace_merge_tags',
    'date_created',
    'payment_date',
);
$inventory = array();
$files = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $base, FilesystemIterator::SKIP_DOTS ) );
foreach ( $files as $file ) {
    if ( ! $file->isFile() || 'php' !== strtolower( $file->getExtension() ) || $file->getSize() > 4000000 ) {
        continue;
    }
    $source = file_get_contents( $file->getPathname() );
    if ( ! is_string( $source ) ) {
        continue;
    }
    $lines = explode( "\n", $source );
    $relative = substr( $file->getPathname(), strlen( $base ) + 1 );
    $method = 'global';
    foreach ( $lines as $line_no => $line ) {
        if ( preg_match( '/(?:public|private|protected|static|final|\\s)*function\\s+([A-Za-z0-9_]+)\\s*\\(/', $line, $method_match ) ) {
            $method = $method_match[1];
        }
        foreach ( $terms as $term ) {
            if ( false === strpos( $line, $term ) ) {
                continue;
            }
            $inventory[ $term ][] = array(
                'file' => $relative,
                'line' => $line_no + 1,
                'nearest_function' => $method,
                'occurrence' => 1,
            );
        }
    }
}
$methods = array();
foreach ( array( 'GFFormDisplay', 'GFCommon', 'GF_Field_Date', 'GFFormsModel' ) as $class ) {
    if ( ! class_exists( $class ) ) {
        $methods[ $class ] = 'MISSING';
        continue;
    }
    $ref = new ReflectionClass( $class );
    foreach ( $ref->getMethods() as $m ) {
        if ( ! preg_match( '/confirm|replace|merge|date|redirect|format/i', $m->getName() ) ) {
            continue;
        }
        $parameters = array();
        foreach ( $m->getParameters() as $p ) {
            $parameters[] = '$' . $p->getName() . ( $p->isOptional() ? '?' : '' );
        }
        $methods[ $class ][] = array(
            'name' => $m->getName(),
            'public' => $m->isPublic(),
            'static' => $m->isStatic(),
            'parameters' => $parameters,
            'source_file' => $m->getFileName() ? substr( $m->getFileName(), strlen( $base ) + 1 ) : null,
            'first_line' => $m->getStartLine(),
            'last_line' => $m->getEndLine(),
        );
    }
}
$report = array(
    'vendor_version' => GFForms::$version,
    'wordpress' => get_bloginfo( 'version' ),
    'php' => PHP_VERSION,
    'source_occurrences' => $inventory,
    'method_signatures' => $methods,
);
$json = wp_json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
if ( ! is_string( $json ) ) {
    exit( 1 );
}
$out = getenv( 'G011_ARTIFACT_DIR' );
if ( ! is_string( $out ) || ! is_dir( $out ) ) {
    exit( 1 );
}
file_put_contents( $out . '/host-source-inventory.json', $json );
foreach ( $inventory as $key => $matches ) {
    printf( 'HOST_SOURCE %s count=%d contexts=%s\n', $key, count( $matches ), implode( ',', array_slice( array_unique( array_column( $matches, 'nearest_function' ) ), 0, 12 ) ) );
}
echo "G011_HOST_SOURCE_INVENTORY_PASS\n";
