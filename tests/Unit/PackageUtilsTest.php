<?php

declare(strict_types=1);

namespace SMF\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SMF\PackageManager\PackageUtils;
use SMF\PackageManager\XmlArray;

#[CoversClass(PackageUtils::class)]
class PackageUtilsTest extends TestCase
{
	/****************
	 * Public methods
	 ****************/

	public function testItReadsTheHooksAPackageInstalls(): void
	{
		$hooks = PackageUtils::getPackageHooks($this->packageXml('
			<install for="3.0 - 3.0.99">
				<hook hook="integrate_load_theme" function="my_mod_load_theme" file="$sourcedir/MyMod.php" />
			</install>'));

		$this->assertSame(
			[['hook' => 'integrate_load_theme', 'call' => '$sourcedir/MyMod.php|my_mod_load_theme']],
			$hooks,
		);
	}

	public function testItBuildsTheEntryTheHookIsStoredAs(): void
	{
		$hooks = PackageUtils::getPackageHooks($this->packageXml('
			<install for="3.0 - 3.0.99">
				<hook hook="integrate_plain" function="plain_function" />
				<hook hook="integrate_method" function="MyMod\Integration::run" file="$sourcedir/MyMod.php" object="true" />
				<hook hook="integrate_pre_include" file="$sourcedir/MyMod.php" />
			</install>'));

		$this->assertSame(
			[
				'plain_function',
				'$sourcedir/MyMod.php|MyMod\Integration::run#',
				'$sourcedir/MyMod.php',
			],
			array_column($hooks, 'call'),
		);
	}

	public function testItLeavesOutHooksThatAPackageRemoves(): void
	{
		$hooks = PackageUtils::getPackageHooks($this->packageXml('
			<install for="3.0 - 3.0.99">
				<hook hook="integrate_added" function="added_function" />
				<hook hook="integrate_removed" function="removed_function" reverse="true" />
			</install>'));

		$this->assertSame(['integrate_added'], array_column($hooks, 'hook'));
	}

	public function testItCountsHooksAddedByAnUpgrade(): void
	{
		$hooks = PackageUtils::getPackageHooks($this->packageXml('
			<install for="3.0 - 3.0.99">
				<hook hook="integrate_first" function="first_function" />
			</install>
			<upgrade for="3.0 - 3.0.99" from="1.0">
				<hook hook="integrate_second" function="second_function" />
			</upgrade>'));

		$this->assertSame(['integrate_first', 'integrate_second'], array_column($hooks, 'hook'));
	}

	/**
	 * Packages written before a version of SMF existed keep a block per
	 * version, and which one ran depends on the forum it was installed on.
	 */
	public function testItReadsEveryInstallBlockWhicheverVersionItIsFor(): void
	{
		$hooks = PackageUtils::getPackageHooks($this->packageXml('
			<install for="2.1.*">
				<hook hook="integrate_old" function="old_function" />
			</install>
			<install for="3.0 - 3.0.99">
				<hook hook="integrate_current" function="current_function" />
			</install>'));

		$this->assertSame(['integrate_old', 'integrate_current'], array_column($hooks, 'hook'));
	}

	public function testItFindsNothingInAPackageThatHooksNothing(): void
	{
		$hooks = PackageUtils::getPackageHooks($this->packageXml('
			<install for="3.0 - 3.0.99">
				<require-file name="MyMod.php" destination="$sourcedir" />
			</install>'));

		$this->assertSame([], $hooks);
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Wraps package-info.xml content the way a real package file has it.
	 *
	 * @param string $content The install and upgrade blocks of the package.
	 * @return XmlArray The package-info element, as getPackageInfo() returns it.
	 */
	protected function packageXml(string $content): XmlArray
	{
		$xml = new XmlArray('<?xml version="1.0"?>
			<package-info xmlns="http://www.simplemachines.org/xml/package-info">
				<id>test:my_mod</id>
				<name>My Mod</name>
				<version>1.0</version>' . $content . '
			</package-info>');

		return $xml->path('package-info[0]');
	}
}
