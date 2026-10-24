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

    public function test_unknown_or_malformed_document_evidence_fails_closed(): void {
        $this->expectException( RuntimeException::class );
        PGR_Upstream_Perks_Source_Review::digest( self::SPELLBOOK,
            '<html><h2>What is Spellbook?</h2><p>Installation and license only; missing ending.</p></html>'
        );
    }
}
