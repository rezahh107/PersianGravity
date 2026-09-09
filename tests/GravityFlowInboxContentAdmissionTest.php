<?php
use PHPUnit\Framework\TestCase;
require_once dirname(__DIR__) . '/tools/i18n/admission.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/tools/i18n/content-admission.php';
final class GravityFlowInboxContentAdmissionTest extends TestCase {
    public function test_inbox_partial_content_admission_is_hash_bound_and_sparse(): void {
        $root = dirname(__DIR__);
        $products = require $root . '/includes/localization/products.php';
        $source = pgr_validate_admission($root);
        $content = pgr_validate_content_admission($root, $source, $products);
        $this->assertSame(255, $content['gravityflow']['computed']['admitted_message_count']);
        $this->assertSame('446d961de3a5572fb8ff7ebce8a24ce3ed7d7372efcce7022967d319a20a147a', $content['gravityflow']['computed']['admitted_keyset_sha256']);
        $this->assertSame([], $products['gravityflow']['scripts']);
        $this->assertFileExists($root . '/languages/providers/gravityflow/gravityflow-fa_IR.mo');
        $this->assertFileExists($root . '/languages/providers/gravityflow/gravityflow-fa_IR.l10n.php');
        $this->assertFileDoesNotExist($root . '/languages/providers/gravityflow/gravityflow-fa_IR-gravityflow_inbox.json');
    }
}
