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

namespace SMF\Infrastructure;

use League\Container\Container as LeagueContainer;

/**
 * A wrapper for the League\Container dependency injection container.
 * Its meant to be a temporary wrapper until all global vars are moved to dependencies.
 */
class Container
{
	/****************************
	 * Internal static properties
	 ****************************/

	/**
	 * @var LeagueContainer
	 */
	private static LeagueContainer $instance;

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Initialize the container.
	 */
	public static function init(): void
	{
		if (!isset(self::$instance)) {
			self::$instance = new LeagueContainer();
			self::$instance->addServiceProvider(new ServiceProvider());
		}
	}

	/**
	 * Get the container instance.
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

	/**
	 * Proxy static calls to the container instance.
	 *
	 * @param string $name
	 * @param array $arguments
	 * @return mixed
	 */
	public static function __callStatic(string $name, array $arguments)
	{
		return self::getInstance()->$name(...$arguments);
	}
}
