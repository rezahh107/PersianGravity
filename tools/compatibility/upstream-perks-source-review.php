<?php
/**
 * Bounded, source-owned digest of current Gravity Perks review-relevant content.
 * Decoration and navigation outside these exact sections do not change the digest.
 * Missing or ambiguous boundaries are a failure, never a silent acceptance.
 */
declare(strict_types=1);

final class PGR_Upstream_Perks_Source_Review {
    public static function digest(string $url, string $html): string {
        $html = preg_replace('~<(script|style)\\b[^>]*>.*?</\\1>~is', '', $html);
        if (!is_string($html) || strlen($html) < 100) {
            throw new RuntimeException('Gravity Perks source was missing or malformed');
        }
        if ($url === 'https://gravitywiz.com/gravity-wiz-weekly-269/') {
            $text = self::cleanText($html);
            $start = self::uniqueOffset($text, 'Gravity Perks (v2.3.17)');
            $end = self::uniqueOffset($text, 'GP eCommerce Fields (v1.2.34)');
            if ($end <= $start) {
                throw new RuntimeException('Gravity Perks release block boundaries are invalid');
            }
            $section = trim(substr($text, $start, $end - $start));
            if (!str_contains($section, 'Spellbook') || strlen($section) > 4000) {
                throw new RuntimeException('Gravity Perks terminal release evidence is ambiguous');
            }
            return hash('sha256', $section);
        }
        if ($url === 'https://gravitywiz.com/documentation/spellbook/') {
            preg_match_all('~<h([1-6])\\b[^>]*>.*?</h\\1>~is', $html, $headings, PREG_OFFSET_CAPTURE);
            $start = null;
            $final = null;
            $end = null;
            foreach ($headings[0] ?? array() as $item) {
                $heading = self::cleanText($item[0]);
                if ($heading === 'What is Spellbook?') {
                    if ($start !== null) {
                        throw new RuntimeException('duplicate Spellbook start heading');
                    }
                    $start = $item[1];
                } elseif ($heading === 'Final Note') {
                    if ($final !== null) {
                        throw new RuntimeException('duplicate Spellbook final heading');
                    }
                    $final = $item[1];
                } elseif ($heading === 'All Connections') {
                    if ($end !== null) {
                        throw new RuntimeException('duplicate Spellbook terminal boundary');
                    }
                    $end = $item[1];
                }
            }
            if ($start === null || $final === null || $end === null || !($start < $final && $final < $end)) {
                throw new RuntimeException('Spellbook documentation section identity is ambiguous');
            }
            $section = self::cleanText(substr($html, $start, $end - $start));
            foreach (array('licens', 'install', 'updat', 'deactivat', 'settings') as $required) {
                if (stripos($section, $required) === false) {
                    throw new RuntimeException('Spellbook reviewed migration/lifecycle evidence is missing: ' . $required);
                }
            }
            if (strlen($section) > 30000 || strlen($section) < 200) {
                throw new RuntimeException('Spellbook reviewed section is unexpectedly sized');
            }
            return hash('sha256', $section);
        }
        throw new RuntimeException('unapproved Gravity Perks review source identity: ' . $url);
    }

    private static function cleanText(string $html): string {
        $plain = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $normalized = preg_replace('/\\s+/u', ' ', trim($plain));
        if (!is_string($normalized) || $normalized === '') {
            throw new RuntimeException('unparseable official source text');
        }
        return $normalized;
    }

    private static function uniqueOffset(string $text, string $needle): int {
        $start = strpos($text, $needle);
        if ($start === false || strpos($text, $needle, $start + 1) !== false) {
            throw new RuntimeException('Gravity Perks named version boundary missing or ambiguous: ' . $needle);
        }
        return $start;
    }
}
