<?php

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/tools/compatibility/upstream-perks-source-review.php';

final class UpstreamPerksSourceReviewTest extends TestCase {
    private const WEEKLY = 'https://gravitywiz.com/gravity-wiz-weekly-269/';
    private const SPELLBOOK = 'https://gravitywiz.com/documentation/spellbook/';

    public function test_release_scope_is_unchanged_by_navigation_but_catches_changed_or_added_release_notes(): void {
        $entry = '<h3>Gravity Perks</h3><p>Gravity Perks (v2.3.17)</p>'
            . '<ul><li>Fixed issue where Spellbook would not be activated network-wide during migration process.</li></ul>'
            . '<p>GP eCommerce Fields (v1.2.34)</p><p>Later unrelated updates.</p>';
        $html = '<html><header>Old navigation</header>' . $entry . '<footer>Old links</footer></html>';
        $hash = PGR_Upstream_Perks_Source_Review::digest( self::WEEKLY, $html );
        $this->assertSame( $hash, PGR_Upstream_Perks_Source_Review::digest( self::WEEKLY,
            '<html><header>New navigation</header>' . $entry . '<footer>New links</footer></html>'
        ) );
        $this->assertNotSame( $hash, PGR_Upstream_Perks_Source_Review::digest( self::WEEKLY,
            str_replace( 'network-wide', 'on a single site', $html )
        ) );
        $this->assertNotSame( $hash, PGR_Upstream_Perks_Source_Review::digest( self::WEEKLY,
            str_replace( '</li></ul>', '</li><li>New migration rule.</li></ul>', $html )
        ) );
    }

    public function test_weekly_reviewed_release_link_destination_is_part_of_semantic_identity(): void {
        $entry = '<h3>Gravity Perks</h3><p><a href="https://gravitywiz.com/documentation/gravity-perks/">Gravity Perks</a> (v2.3.17)</p>'
            . '<ul><li>Fixed Spellbook network-wide activation during multisite migration.</li></ul>'
            . '<p><a href="https://gravitywiz.com/documentation/gp-ecommerce-fields/">GP eCommerce Fields</a> (v1.2.34)</p>';
        $html = '<html><nav><a href="/old-menu">Menu</a></nav>' . $entry
            . '<footer><a href="/old-footer">Footer</a></footer></html>';
        $digest = PGR_Upstream_Perks_Source_Review::digest(self::WEEKLY, $html);
        $this->assertSame($digest, PGR_Upstream_Perks_Source_Review::digest(self::WEEKLY, $html));
        $this->assertSame($digest, PGR_Upstream_Perks_Source_Review::digest(
            self::WEEKLY, str_replace('/old-menu', '/new-menu', $html)
        ));
        $this->assertSame($digest, PGR_Upstream_Perks_Source_Review::digest(
            self::WEEKLY, str_replace('/old-footer', '/new-footer', $html)
        ));
        $this->assertNotSame($digest, PGR_Upstream_Perks_Source_Review::digest(
            self::WEEKLY,
            str_replace('/documentation/gravity-perks/', '/documentation/another-product/', $html)
        ), 'The reviewed release-link destination changed without changing the visible Gravity Perks label.');
        $this->assertNotSame($digest, PGR_Upstream_Perks_Source_Review::digest(
            self::WEEKLY, str_replace('network-wide activation', 'single-site activation', $html)
        ));
    }

    public function test_weekly_release_boundaries_reject_missing_duplicates_and_malformed_source(): void {
        $good = '<html><p>Gravity Perks (v2.3.17)</p>'
            . '<ul><li>Spellbook network-wide reactivation during migration.</li></ul>'
            . '<p>GP eCommerce Fields (v1.2.34)</p><p>Next product.</p></html>';
        $cases = [
            str_replace('Gravity Perks (v2.3.17)', 'Gravity Perks (v2.3.17 preview)', $good),
            str_replace('GP eCommerce Fields (v1.2.34)', 'Different release marker', $good),
            str_replace('</html>', '<p>Gravity Perks (v2.3.17)</p></html>', $good),
        ];
        foreach ($cases as $malformed) {
            try {
                PGR_Upstream_Perks_Source_Review::digest(self::WEEKLY, $malformed);
                $this->fail('Malformed or ambiguous release identity was accepted.');
            } catch (RuntimeException $exception) {
                $this->assertNotSame('', $exception->getMessage());
            }
        }
        $this->expectException(RuntimeException::class);
        PGR_Upstream_Perks_Source_Review::digest('https://example.org/fake-release', $good);
    }

    public function test_weekly_ignores_following_product_links_but_rejects_invalid_reviewed_entry_href(): void {
        $entry = '<p><a href="https://gravitywiz.com/documentation/gravity-perks/">Gravity Perks</a> (v2.3.17)</p>'
            . '<ul><li>Fixed Spellbook multisite migration activation.</li></ul>'
            . '<p><a href="https://gravitywiz.com/docs/ecommerce/">GP eCommerce Fields</a> (v1.2.34)</p>';
        $html = '<html><nav><a href="/menu">Menu</a></nav>' . $entry . '<footer>Other updates</footer></html>';
        $digest = PGR_Upstream_Perks_Source_Review::digest(self::WEEKLY, $html);
        $this->assertSame($digest, PGR_Upstream_Perks_Source_Review::digest(
            self::WEEKLY, str_replace('/docs/ecommerce/', '/new-ecommerce-docs/', $html)
        ));
        foreach ([
            str_replace('href="https://gravitywiz.com/documentation/gravity-perks/"', '', $html),
            str_replace('href="https://gravitywiz.com/documentation/gravity-perks/"',
                'href="https://gravitywiz.com/documentation/gravity-perks/" href="https://example.org/other"', $html),
            str_replace('href="https://gravitywiz.com/documentation/gravity-perks/"',
                'href="javascript:alert(1)"', $html),
        ] as $invalid) {
            try {
                PGR_Upstream_Perks_Source_Review::digest(self::WEEKLY, $invalid);
                $this->fail('Malformed or untrusted Weekly release hyperlink was accepted.');
            } catch (RuntimeException $exception) {
                $this->assertStringContainsString('reviewed link', $exception->getMessage());
            }
        }
    }

    public function test_weekly_only_validates_links_inside_unique_reviewed_release_entry(): void {
        $entry = '<p><a href="https://gravitywiz.com/documentation/gravity-perks/">Gravity Perks</a> (v2.3.17)</p>'
            . '<ul><li>Fixed issue where Spellbook would not be activated network-wide during migration process.</li></ul>'
            . '<p><a href="https://gravitywiz.com/documentation/gp-ecommerce-fields/">GP eCommerce Fields</a> (v1.2.34)</p>';
        $plain = '<html><nav><a href="/nav">Menu</a></nav>' . $entry
            . '<footer><a href="/footer">Footer</a></footer></html>';
        $digest = PGR_Upstream_Perks_Source_Review::digest(self::WEEKLY, $plain);

        $outOfScope = [
            // A missing href in navigation cannot invalidate the release entry.
            str_replace('<a href="/nav">Menu</a>', '<a>Menu</a>', $plain),
            // An unsupported protocol in site navigation does not belong to the release.
            str_replace('href="/nav"', 'href="javascript:alert(1)"', $plain),
            // Footer markup is outside the release, even when its anchor is broken.
            str_replace('<a href="/footer">Footer</a>', '<a href="/footer">Footer', $plain),
            // Links in the *following* product are not part of Gravity Perks 2.3.17.
            str_replace('href="https://gravitywiz.com/documentation/gp-ecommerce-fields/"',
                'href="javascript:alert(1)"', $plain),
            str_replace('href="https://gravitywiz.com/documentation/gp-ecommerce-fields/"', '', $plain),
        ];
        foreach ($outOfScope as $html) {
            $this->assertSame($digest, PGR_Upstream_Perks_Source_Review::digest(self::WEEKLY, $html));
        }

        // Release-entry destinations and release notes are still protected.
        $this->assertNotSame($digest, PGR_Upstream_Perks_Source_Review::digest(
            self::WEEKLY, str_replace('/documentation/gravity-perks/', '/documentation/other/', $plain)
        ));
        $this->assertNotSame($digest, PGR_Upstream_Perks_Source_Review::digest(
            self::WEEKLY, str_replace('network-wide', 'single-site', $plain)
        ));
        foreach ([
            str_replace('href="https://gravitywiz.com/documentation/gravity-perks/"', '', $plain),
            str_replace('href="https://gravitywiz.com/documentation/gravity-perks/"',
                'href="ftp://untrusted.example/other"', $plain),
            str_replace('GP eCommerce Fields</a> (v1.2.34)', 'Other Product</a> (v1.2.34)', $plain),
            str_replace('Gravity Perks</a> (v2.3.17)',
                'Gravity Perks</a> (v2.3.17)<p>Gravity Perks (v2.3.17)', $plain),
        ] as $invalidReviewed) {
            try {
                PGR_Upstream_Perks_Source_Review::digest(self::WEEKLY, $invalidReviewed);
                $this->fail('Missing, malformed or ambiguous reviewed evidence was accepted');
            } catch (RuntimeException $exception) {
                $this->assertNotSame('', $exception->getMessage());
            }
        }
    }

    public function test_spellbook_scope_preserves_licensing_migration_and_update_changes(): void {
        $chapter = '<h2>What is Spellbook?</h2><p>Spellbook is a platform plugin centralizing installation, licensing, updates, and product discovery.</p>'
            . '<h2>Installation Instructions</h2><p>After installation, Gravity Perks will be deactivated during migration.</p>'
            . '<h2>Managing Licensing</h2><p>The license key transfers to Spellbook. Perk settings move to the Spellbook interface.</p>'
            . '<h2>Final Note</h2><p>Installed products can continue to receive updates.</p>';
        $html = '<html><nav>Old navigation</nav>' . $chapter . '<h3>All Connections</h3><footer>Unrelated data</footer></html>';
        $hash = PGR_Upstream_Perks_Source_Review::digest( self::SPELLBOOK, $html );
        $this->assertSame( $hash, PGR_Upstream_Perks_Source_Review::digest( self::SPELLBOOK,
            str_replace( 'Old navigation', 'New navigation', $html )
        ) );
        $this->assertNotSame( $hash, PGR_Upstream_Perks_Source_Review::digest( self::SPELLBOOK,
            str_replace( 'license key transfers', 'license key must be replaced', $html )
        ) );
        $this->assertNotSame( $hash, PGR_Upstream_Perks_Source_Review::digest( self::SPELLBOOK,
            str_replace( 'during migration', 'before migration', $html )
        ) );
    }

    public function test_spellbook_link_destinations_within_reviewed_scope_are_material_but_navigation_links_are_not(): void {
        $chapter = '<h2>What is Spellbook?</h2><p>Spellbook owns installation, licensing, updates and product discovery.</p>'
            . '<h2>Installation Instructions</h2><p>Download <a href="https://gravitywiz.com/downloads/spellbook/">Spellbook</a>.</p>'
            . '<h2>Managing Licensing</h2><p>License support: <a href="https://gravitywiz.com/documentation/licenses/">License FAQ</a>.</p>'
            . '<h2>Migration</h2><p>Gravity Perks will deactivate during migration; settings are preserved.</p>'
            . '<h2>Final Note</h2><p>Installed perks continue receiving updates.</p>';
        $html = '<html><nav><a href="/old-menu">Navigation</a></nav>' . $chapter
            . '<h3>All Connections</h3><footer><a href="/old-footer">Footer</a></footer></html>';
        $digest = PGR_Upstream_Perks_Source_Review::digest( self::SPELLBOOK, $html );

        $this->assertSame( $digest, PGR_Upstream_Perks_Source_Review::digest(
            self::SPELLBOOK, str_replace( '/old-menu', '/new-menu', $html )
        ) );
        $this->assertSame( $digest, PGR_Upstream_Perks_Source_Review::digest(
            self::SPELLBOOK, str_replace( '/old-footer', '/new-footer', $html )
        ) );
        $this->assertNotSame( $digest, PGR_Upstream_Perks_Source_Review::digest(
            self::SPELLBOOK, str_replace( '/downloads/spellbook/', '/downloads/different-package/', $html )
        ), 'Changed installation destination must change reviewed evidence even if link label is unchanged.' );
        $this->assertNotSame( $digest, PGR_Upstream_Perks_Source_Review::digest(
            self::SPELLBOOK, str_replace( '/documentation/licenses/', '/unrelated-license-page/', $html )
        ), 'Changed licensing destination must change reviewed evidence even if link label is unchanged.' );
    }

    public function test_link_targets_are_entity_normalized_and_link_attributes_are_ignored(): void {
        $chapter = '<h2>What is Spellbook?</h2><p>Spellbook handles installation, licensing and updates.</p>'
            . '<h2>Installation Instructions</h2><a class="doc" href="https://gravitywiz.com/download/spellbook">Download</a>'
            . '<h2>Managing Licensing</h2><a href="https://gravitywiz.com/documentation/license-faq/?foo=1&amp;bar=2">License FAQ</a>'
            . '<h2>Migration</h2><p>Perks deactivation preserves settings.</p>'
            . '<h2>Final Note</h2><p>Continue updates.</p>';
        $html = '<html>' . $chapter . '<h3>All Connections</h3></html>';
        $digest = PGR_Upstream_Perks_Source_Review::digest(self::SPELLBOOK, $html);
        $changedAttributes = str_replace(
            'class="doc" href="https://gravitywiz.com/download/spellbook"',
            'href="https://gravitywiz.com/download/spellbook" class="new-style"',
            $html
        );
        $changedAttributes = str_replace('foo=1&amp;bar=2', 'foo=1&bar=2', $changedAttributes);
        $this->assertSame($digest, PGR_Upstream_Perks_Source_Review::digest(self::SPELLBOOK, $changedAttributes));
        $this->assertNotSame($digest, PGR_Upstream_Perks_Source_Review::digest(
            self::SPELLBOOK, str_replace('foo=1&amp;bar=2', 'foo=1&amp;bar=3', $html)
        ));
    }

    public function test_missing_or_duplicate_href_in_reviewed_chapter_fails_closed(): void {
        $chapter = '<h2>What is Spellbook?</h2><p>Spellbook handles installation, licensing and updates.</p>'
            . '<h2>Installation Instructions</h2><a href="https://gravitywiz.com/download/spellbook">Download</a>'
            . '<h2>Managing Licensing</h2><p>Licensing requirements for existing customers.</p>'
            . '<h2>Migration</h2><p>Perks deactivation preserves settings.</p>'
            . '<h2>Final Note</h2><p>Continue updates.</p>';
        $html = '<html>' . $chapter . '<h3>All Connections</h3></html>';
        foreach ([
            str_replace('href="https://gravitywiz.com/download/spellbook"', '', $html),
            str_replace('href="https://gravitywiz.com/download/spellbook"',
                'href="https://gravitywiz.com/download/spellbook" href="https://example.org/other"', $html),
            str_replace('href="https://gravitywiz.com/download/spellbook"', 'href="javascript:alert(1)"', $html),
        ] as $malformed) {
            try {
                PGR_Upstream_Perks_Source_Review::digest(self::SPELLBOOK, $malformed);
                $this->fail('Malformed or untrusted reviewed hyperlink was accepted.');
            } catch (RuntimeException $exception) {
                $this->assertStringContainsString('Spellbook reviewed link', $exception->getMessage());
            }
        }
    }

    public function test_unknown_or_malformed_document_evidence_fails_closed(): void {
        $this->expectException( RuntimeException::class );
        PGR_Upstream_Perks_Source_Review::digest( self::SPELLBOOK,
            '<html><h2>What is Spellbook?</h2><p>Installation and license only; missing ending.</p></html>'
        );
    }
}
