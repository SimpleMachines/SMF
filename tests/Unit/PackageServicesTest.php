<?php

declare(strict_types=1);

namespace SMF\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SMF\Config;
use SMF\Infrastructure\PackageServices;

#[CoversClass(PackageServices::class)]
class PackageServicesTest extends TestCase
{
	/****************
	 * Public methods
	 ****************/

	public function testItFindsNothingWhenNoPackageUsesServices(): void
	{
		$this->assertSame([], PackageServices::all());
	}

	public function testItReadsWhatAPackageDeclared(): void
	{
		Config::$modSettings['package_services'] = json_encode([
			'test:my_mod' => [
				'name' => 'My Mod',
				'provides' => [['id' => 'MyMod\Repository', 'factory' => 'MyMod\Factory::make', 'file' => '']],
				'uses' => ['SMF\Services\ErrorHandlerService'],
				'granted' => true,
			],
		]);

		$manifest = PackageServices::get('test:my_mod');

		$this->assertSame('My Mod', $manifest['name']);
		$this->assertSame(['SMF\Services\ErrorHandlerService'], $manifest['uses']);
		$this->assertTrue($manifest['granted']);
	}

	public function testItKnowsNothingOfAPackageThatIsNotThere(): void
	{
		Config::$modSettings['package_services'] = json_encode([
			'test:my_mod' => ['name' => 'My Mod', 'provides' => [], 'uses' => [], 'granted' => true],
		]);

		$this->assertSame([], PackageServices::get('test:other_mod'));
	}

	/**
	 * A setting that has been mangled by hand leaves every package without
	 * access, which is the safe way round.
	 */
	public function testItTreatsAnUnreadableSettingAsNothingDeclared(): void
	{
		Config::$modSettings['package_services'] = 'not json at all';

		$this->assertSame([], PackageServices::all());
	}

	public function testItHasNoAccessWhenThePackageWasRefused(): void
	{
		Config::$modSettings['package_services'] = json_encode([
			'test:my_mod' => ['name' => 'My Mod', 'provides' => [], 'uses' => [], 'granted' => false],
		]);

		$this->assertFalse(PackageServices::get('test:my_mod')['granted']);
	}

	/******************
	 * Internal methods
	 ******************/

	protected function tearDown(): void
	{
		// PHPUnit does not reset SMF's statics, so a key left here leaks.
		unset(Config::$modSettings['package_services']);
	}
}
