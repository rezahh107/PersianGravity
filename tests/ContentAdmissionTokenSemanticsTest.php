<?php

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/vendor/autoload.php';
require_once dirname( __DIR__ ) . '/tools/i18n/content-admission.php';

final class ContentAdmissionTokenSemanticsTest extends TestCase {
    public function test_literal_percent_prose_is_not_treated_as_printf(): void {
        $this->assertSame( array(), pgr_content_tokens( 'Do not close this page until the upgrade is 100% complete.' ) );
        $this->assertSame( array(), pgr_content_tokens( 'Save 20% off today.' ) );
        $this->assertSame( array(), pgr_content_tokens( 'Apply a 5% discount.' ) );
        $this->assertSame( array( '%s' ), pgr_content_tokens( '%s%% complete.' ) );
        $this->assertSame( array( '%02d', '%1$s' ), pgr_content_tokens( '%1$s / %02d' ) );
    }

    public function test_valid_printf_space_flag_formats_are_protected(): void {
        $this->assertSame(
            array( '% 5.2f', '% 5d', '% d', '%1$ d' ),
            pgr_content_tokens( '% d / % 5d / % 5.2f / %1$ d' )
        );
    }

    public function test_url_sentence_punctuation_is_not_part_of_the_protected_token(): void {
        $this->assertSame( array( 'https://' ), pgr_content_tokens( 'See https://).' ) );
        $this->assertSame( array( 'https://' ), pgr_content_tokens( 'ببینید https://)،' ) );
        $this->assertSame( array( 'https://example.test/path_(value)' ), pgr_content_tokens( 'See https://example.test/path_(value).' ) );
    }

    public function test_named_entities_can_be_localized_but_numeric_entities_remain_protected(): void {
        $this->assertSame( array(), pgr_content_tokens( 'Products &amp; Services Settings' ) );
        $this->assertSame( array( '&#160;', '&#x202F;' ), pgr_content_tokens( 'Keep &#160; and &#x202F;' ) );
        $this->assertSame( array( '</a>', '<a href="?x=1&amp;y=2">' ), pgr_content_tokens( '<a href="?x=1&amp;y=2">Link</a>' ) );
    }

    public function test_printf_space_placeholder_loss_fails_sparse_admission_validation(): void {
        $path = tempnam( sys_get_temp_dir(), 'pgr-space-printf-' );
        $po = <<<'PO'
msgid ""
msgstr ""
"Language: fa_IR\n"
"Plural-Forms: nplurals=2; plural=(n > 1);\n"
"X-Domain: gravityforms\n"

msgid "% 5d items"
msgstr "مورد"
PO;
        file_put_contents( $path, $po . "\n" );
        $this->expectException( RuntimeException::class );
        $this->expectExceptionMessage( 'Placeholder/markup/literal drift in admitted entry' );
        try {
            pgr_content_load_sparse_po( $path, 'gravityforms', 'fa_IR' );
        } finally {
            unlink( $path );
        }
    }

    public function test_pluralized_url_protected_literal_is_canonicalized(): void {
        $this->assertSame( array( 'URL' ), pgr_content_protected_literals( 'Links/URLs are not allowed.' ) );
        $this->assertSame( array( 'URL' ), pgr_content_protected_literals( 'پیوندها/URLها مجاز نیستند.' ) );
    }

}
