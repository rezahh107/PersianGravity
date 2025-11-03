<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class PGR_Utils {

    /**
     * Normalize Persian/Arabic digits to English digits.
     */
    public static function normalize_digits( $str ) {
        if ( ! is_string( $str ) ) { return $str; }
        $find  = array('۰','۱','۲','۳','۴','۵','۶','۷','۸','۹','٠','١','٢','٣','٤','٥','٦','٧','٨','٩');
        $repl  = array('0','1','2','3','4','5','6','7','8','9','0','1','2','3','4','5','6','7','8','9');
        return str_replace( $find, $repl, $str );
    }

    /**
     * Validate Iranian National ID (10 digits, checksum mod 11)
     */
    public static function is_valid_iran_national_id( $nid ) {
        $nid = preg_replace( '/\D+/', '', self::normalize_digits( (string) $nid ) );
        if ( strlen( $nid ) !== 10 ) { return false; }
        if ( preg_match( '/^(\d)\1{9}$/', $nid ) ) { return false; } // reject all same digits

        $check = (int) substr( $nid, 9, 1 );
        $sum   = 0;
        for ( $i = 0; $i < 9; $i++ ) {
            $sum += (int) $nid[$i] * (10 - $i);
        }
        $rem = $sum % 11;
        $calc = ($rem < 2) ? $rem : 11 - $rem;
        return $calc === $check;
    }
}
