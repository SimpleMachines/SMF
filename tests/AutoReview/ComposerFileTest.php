<?php

declare(strict_types=1);

namespace SMF\Tests\AutoReview;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class ComposerFileTest extends TestCase
{
	public function testScriptsHaveDescriptions(): void
	{
		$composer_json = $this->readComposerJson();

		$this->assertArrayHasKey('scripts', $composer_json);
		$this->assertArrayHasKey('scripts-descriptions', $composer_json);

		$descriptions = array_keys($composer_json['scripts-descriptions']);
		$event_scripts = [
			'pre-install-cmd',
			'post-install-cmd',
			'pre-update-cmd',
			'post-update-cmd',
			'pre-status-cmd',
			'post-status-cmd',
			'pre-archive-cmd',
			'post-archive-cmd',
			'pre-autoload-dump',
			'post-autoload-dump',
			'post-root-package-install',
			'post-create-project-cmd',
			'pre-operations-exec',
			'pre-package-install',
			'post-package-install',
			'pre-package-update',
			'post-package-update',
			'pre-package-uninstall',
			'post-package-uninstall',
			'init',
			'command',
			'pre-file-download',
			'post-file-download',
			'pre-command-run',
			'pre-pool-create',
		];
		$scripts = array_diff(
			array_keys($composer_json['scripts']),
			$event_scripts,
		);

		$this->assertSame(
			[],
			array_diff($scripts, $descriptions),
			'There should be no scripts with missing descriptions.',
		); 

		$this->assertSame(
			[],
			array_diff($descriptions, $scripts),
			'There should be no superfluous descriptions for undefined scripts.',
		);
	}

	public function testPlatformPhpVersionSatisfiesPhpRequirement(): void
	{
		$composer_json = $this->readComposerJson();

		$this->assertArrayHasKey('require', $composer_json);
		$this->assertArrayHasKey('php', $composer_json['require']);
		$this->assertArrayHasKey('config', $composer_json);
		$this->assertArrayHasKey('platform', $composer_json['config']);
		$this->assertArrayHasKey('php', $composer_json['config']['platform']);

		$php_requirement = $composer_json['require']['php'];
		$platform_php = $composer_json['config']['platform']['php'];

		$this->assertTrue(
			\Composer\Semver\Semver::satisfies($platform_php, $php_requirement),
			"Platform PHP version '{$platform_php}' does not satisfy PHP requirement '{$php_requirement}'.",
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function readComposerJson(): array
	{
		$composer_json_content = (string) file_get_contents(__DIR__ . '/../../composer.json');

		return json_decode($composer_json_content, true, 512, JSON_THROW_ON_ERROR);
	}
}
