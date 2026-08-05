<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2026 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 4
 */

declare(strict_types=1);

namespace SMF;

use League\Container\Container as LeagueContainer;
use League\Container\ReflectionContainer;
use SMF\Services\DatabaseService;
use SMF\Services\DatabaseServiceInterface;

/**
 * A wrapper for the dependency injection container.
 */
class Container
{
	/**
	 * @var LeagueContainer
	 */
	private static LeagueContainer $instance;

	/**
	 * Initializes the container.
	 *
	 * @return LeagueContainer
	 */
	public static function init(): LeagueContainer
	{
		$container = new LeagueContainer();

		// Enable auto-wiring
		$container->delegate(
			new ReflectionContainer()
		);

		// Register core services, this can and should be moved to its own container service provider
		// as more and more global variables are migrated to services
		$container->add(DatabaseServiceInterface::class, DatabaseService::class)->setShared(true);

		self::$instance = $container;

		return $container;
	}

	/**
	 * Gets the container instance.
	 *
	 * @return LeagueContainer
	 */
	public static function getInstance(): LeagueContainer
	{
		if (!isset(self::$instance)) {
			self::init();
		}

		return self::$instance;
	}
}
