<?php
use PHPUnit\Framework\TestCase;
require_once dirname(__DIR__) . '/tools/i18n/admission.php';
final class SourceAdmissionTest extends TestCase {
    public function test_metadata_only_source_admission_is_self_consistent() {
        $records=pgr_validate_admission(dirname(__DIR__));
        $this->assertSame(array('gravityforms','gravityflow','gravityview'),array_keys($records));
        $this->assertSame(4207,$records['gravityforms']['canonical_message_count']);
        $this->assertSame(1098,$records['gravityflow']['canonical_message_count']);
        $this->assertSame(3127,$records['gravityview']['canonical_message_count']);
        $this->assertNull($records['gravityview']['vendor_pot_sha256']);
    }
}
