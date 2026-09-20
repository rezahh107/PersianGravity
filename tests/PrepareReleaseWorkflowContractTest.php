<?php

use PHPUnit\Framework\TestCase;

final class PrepareReleaseWorkflowContractTest extends TestCase {

	public function test_prepare_release_blocks_existing_branch_and_only_open_prior_prs(): void {
		$workflow = file_get_contents( dirname( __DIR__ ) . '/.github/workflows/prepare-release.yml' );

		$this->assertStringContainsString(
			'git ls-remote --exit-code origin "refs/heads/$branch"',
			$workflow
		);
		$this->assertStringContainsString( '-f state=open', $workflow );
		$this->assertStringNotContainsString( '-f state=all', $workflow );
		$this->assertStringContainsString(
			'An open PR already uses $branch; refusing to silently replace it.',
			$workflow
		);
	}
}
