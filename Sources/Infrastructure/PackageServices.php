<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2026 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 5-dev
 */

declare(strict_types=1);

namespace SMF\Infrastructure;

use SMF\Config;
use SMF\Utils;

/**
 * What each installed package does with services.
 *
 * A package says in its package-info.xml which services it provides and which
 * it wants to use. That is recorded when the package is installed, so the
 * answer is a setting rather than a trip through the package files, and the
 * administrator can take the access away again without uninstalling the mod.
 */
class PackageServices
{
	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Gets what every installed package does with services.
	 *
	 * @return array Each package's name, the services it provides, the ones it
	 *    uses, and whether it is allowed to, keyed by package ID.
	 */
	public static function all(): array
	{
		if (empty(Config::$modSettings['package_services'])) {
			return [];
		}

		$manifests = Utils::jsonDecode(Config::$modSettings['package_services'], true);

		return \is_array($manifests) ? $manifests : [];
	}

	/**
	 * Gets what one package does with services.
	 *
	 * @param string $package_id The package's ID.
	 * @return array The package's entry, or an empty array if it has none.
	 */
	public static function get(string $package_id): array
	{
		return self::all()[$package_id] ?? [];
	}

	/**
	 * Records what a package does with services.
	 *
	 * Access is granted at this point, because this is where the administrator
	 * installed the package after being shown what it asked for.
	 *
	 * @param string $package_id The package's ID.
	 * @param string $name The package's name, as the admin knows it.
	 * @param array $provides Service IDs the package registers.
	 * @param array $uses Service IDs the package wants to use.
	 */
	public static function record(string $package_id, string $name, array $provides, array $uses): void
	{
		if ($provides === [] && $uses === []) {
			self::forget($package_id);

			return;
		}

		$manifests = self::all();

		$manifests[$package_id] = [
			'name' => $name,
			'provides' => array_values(array_unique($provides)),
			'uses' => array_values(array_unique($uses)),
			'granted' => true,
		];

		self::save($manifests);
	}

	/**
	 * Forgets a package, which is what uninstalling it means here.
	 *
	 * @param string $package_id The package's ID.
	 */
	public static function forget(string $package_id): void
	{
		$manifests = self::all();

		if (!isset($manifests[$package_id])) {
			return;
		}

		unset($manifests[$package_id]);

		self::save($manifests);
	}

	/**
	 * Allows or refuses a package the services it asked for.
	 *
	 * A package that has been refused keeps its entry, so the administrator can
	 * see what it wanted and can change their mind.
	 *
	 * @param string $package_id The package's ID.
	 * @param bool $granted Whether the package may use services.
	 */
	public static function setGranted(string $package_id, bool $granted): void
	{
		$manifests = self::all();

		if (!isset($manifests[$package_id])) {
			return;
		}

		$manifests[$package_id]['granted'] = $granted;

		self::save($manifests);
	}

	/*************************
	 * Internal static methods
	 *************************/

	/**
	 * Writes the manifests back to the settings.
	 *
	 * @param array $manifests The manifests of every package.
	 */
	protected static function save(array $manifests): void
	{
		Config::updateModSettings([
			'package_services' => $manifests === [] ? '' : Utils::jsonEncode($manifests),
		]);
	}
}
