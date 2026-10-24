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
            // Preserve both the reviewed release wording AND any href belonging
            // to its versioned entry. The following product is an exclusive
            // boundary: its own href and everything after it are out of scope.
            $text = self::cleanTextWithLinks($html);
            $start = self::uniqueReleaseOffset($text, 'Gravity Perks', '2.3.17');
            $end = self::uniqueReleaseOffset($text, 'GP eCommerce Fields', '1.2.34');
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
            $section = self::cleanTextWithLinks(substr($html, $start, $end - $start));
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

    /**
     * Keep the destination, not just the label, of each reviewed content link.
     * Bound this transformation to the selected Spellbook chapter. Never include
     * navigation outside the chapter in its semantic fingerprint.
     */
    private static function cleanTextWithLinks(string $html): string {
        $linkPattern = '~<a\b([^>]*)>(.*?)</a\s*>~is';
        $openCount = preg_match_all('~<a\b~i', $html);
        $closeCount = preg_match_all('~</a\s*>~i', $html);
        $matchedCount = preg_match_all($linkPattern, $html);

        if ($openCount === false || $closeCount === false || $matchedCount === false
            || $openCount !== $closeCount || $openCount !== $matchedCount) {
            throw new RuntimeException('Spellbook reviewed links are malformed or ambiguous');
        }

        $annotated = preg_replace_callback($linkPattern, static function (array $link): string {
            $matches = array();
            $count = preg_match_all(
                '~(?:^|\s)href\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))~i',
                $link[1],
                $matches,
                PREG_SET_ORDER
            );
            if ($count !== 1) {
                throw new RuntimeException('Spellbook reviewed link has missing or duplicate href');
            }

            $parts = $matches[0];
            $value = ($parts[1] ?? '') !== '' ? $parts[1]
                : ((($parts[2] ?? '') !== '') ? $parts[2] : ($parts[3] ?? ''));
            $target = trim(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if ($target === '' || preg_match('/[\x00-\x1f\x7f<>]/', $target) === 1
                || preg_match('~^(?:https?://|//|/|#|mailto:|tel:)~i', $target) !== 1) {
                throw new RuntimeException('Spellbook reviewed link destination is missing or unsupported');
            }

            // URL-encode to prevent angle brackets/markup from being lost in strip_tags.
            return $link[2] . ' [href=' . rawurlencode($target) . ']';
        }, $html);
        if (!is_string($annotated)) {
            throw new RuntimeException('Spellbook reviewed link canonicalization failed');
        }

        return self::cleanText($annotated);
    }

    private static function cleanText(string $html): string {
        $plain = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $normalized = preg_replace('/\\s+/u', ' ', trim($plain));
        if (!is_string($normalized) || $normalized === '') {
            throw new RuntimeException('unparseable official source text');
        }
        return $normalized;
    }

    private static function uniqueReleaseOffset(string $text, string $product, string $version): int {
        $pattern = '~' . preg_quote($product, '~')
            . '(?: \[href=[^\]]+\])? \(v' . preg_quote($version, '~') . '\)~u';
        $count = preg_match_all($pattern, $text, $matches, PREG_OFFSET_CAPTURE);
        if ($count !== 1) {
            throw new RuntimeException('Gravity Perks named version boundary missing or ambiguous: ' . $product);
        }
        return $matches[0][0][1];
    }

    private static function uniqueOffset(string $text, string $needle): int {
        $start = strpos($text, $needle);
        if ($start === false || strpos($text, $needle, $start + 1) !== false) {
            throw new RuntimeException('Gravity Perks named version boundary missing or ambiguous: ' . $needle);
        }
        return $start;
    }
}
