<?php

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/tools/i18n/content-admission.php';

final class ContentAdmissionTokenSemanticsTest extends TestCase {
	public function test_literal_percent_prose_is_not_treated_as_printf(): void {
		$this->assertSame( array(), pgr_content_tokens( 'Do not close this page until the upgrade is 100% complete.' ) );
		$this->assertSame( array( '%s' ), pgr_content_tokens( '%s%% complete.' ) );
		$this->assertSame( array( '%1$s', '%02d' ), pgr_content_tokens( '%1$s / %02d' ) );
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
}
