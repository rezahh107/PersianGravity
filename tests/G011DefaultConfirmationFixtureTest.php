<?php
/**
 * G-011 browser fixture guard tests (not a stand-in for GFAPI/browser runs).
 */

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/real-integration/g011-default-confirmation.php';

final class G011DefaultConfirmationFixtureTest extends TestCase {

	private static function confirmation( string $id, bool $default = true, bool $active = true, string $type = 'message' ): array {
		return array(
			'id'                => $id,
			'isDefault'         => $default,
			'isActive'          => $active,
			'type'              => $type,
			'name'              => 'Native metadata remains intact',
			'message'           => 'Original message',
			'disableAutoformat' => true,
		);
	}

	public function test_non_default_gfapi_key_is_selected_without_losing_metadata(): void {
		$form = array(
			'confirmations' => array(
				'g011-default'     => self::confirmation( 'g011-default' ),
				'g011-conditional' => self::confirmation( 'g011-conditional', false ),
			),
		);
		$before = $form;
		$this->assertArrayNotHasKey( 'default', $form['confirmations'] ); // Reproduces old invalid-key lookup.
		$selected = pgr_g011_select_default_text_confirmation( $form );

		$this->assertSame( 'g011-default', $selected['key'] );
		$this->assertSame( $form['confirmations']['g011-default'], $selected['confirmation'] );
		$this->assertSame( $before, $form ); // Read-only selection.

		$message = '<strong>G011_BROWSER_OK</strong> {today:format:Y/m/d} | 2026-03-21 unchanged';
		$form['confirmations'][ $selected['key'] ]['message'] = $message;
		$this->assertSame( $message, $form['confirmations']['g011-default']['message'] );
		$this->assertSame( $before['confirmations']['g011-conditional'], $form['confirmations']['g011-conditional'] );
		$expected_metadata = $before['confirmations']['g011-default'];
		$expected_metadata['message'] = $message;
		$this->assertSame( $expected_metadata, $form['confirmations']['g011-default'] );
	}

	public static function invalid_default_cases(): array {
		return array(
			'confirmations absent' => array( array() ),
			'confirmations not array' => array( array( 'confirmations' => false ) ),
			'no default' => array(
				array( 'confirmations' => array( 'other' => self::confirmation( 'other', false ) ) ),
			),
			'inactive default' => array(
				array( 'confirmations' => array( 'inactive' => self::confirmation( 'inactive', true, false ) ) ),
			),
			'two active defaults' => array(
				array(
					'confirmations' => array(
						'a' => self::confirmation( 'a' ),
						'b' => self::confirmation( 'b' ),
					),
				),
			),
			'active default is redirect' => array(
				array( 'confirmations' => array( 'redirect' => self::confirmation( 'redirect', true, true, 'redirect' ) ) ),
			),
			'missing confirmation ID' => array(
				array( 'confirmations' => array( 'x' => array_diff_key( self::confirmation( 'x' ), array( 'id' => true ) ) ) ),
			),
			'key and ID mismatch' => array(
				array( 'confirmations' => array( 'old-default' => self::confirmation( 'new-id' ) ) ),
			),
			'missing message' => array(
				array( 'confirmations' => array( 'x' => array_diff_key( self::confirmation( 'x' ), array( 'message' => true ) ) ) ),
			),
		);
	}

	#[DataProvider( 'invalid_default_cases' )]
	public function test_missing_ambiguous_and_invalid_default_fails_closed( array $form ): void {
		$this->expectException( RuntimeException::class );
		pgr_g011_select_default_text_confirmation( $form );
	}
}
