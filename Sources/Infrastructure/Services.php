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

namespace SMF\Infrastructure;

use Psr\Container\ContainerInterface;

/**
 * Provides controlled access to registered SMF services.
 *
 * This class intentionally exposes only the service lookup operations needed
 * by service factories. Consumers should receive their dependencies through
 * dependency injection rather than using this class as a service locator.
 */
final class Services implements ContainerInterface
{
	/****************
	 * Public methods
	 ****************/

	/**
	 * Creates a service accessor.
	 *
	 * @param ContainerInterface $container The underlying service container.
	 */
	public function __construct(
		private readonly ContainerInterface $container,
	) {}

	/**
	 * Checks whether a service is registered.
	 *
	 * @param string $id The service identifier.
	 *
	 * @return bool True if the service is registered.
	 */
	public function has(string $id): bool
	{
		return $this->container->has($id);
	}

	/**
	 * Retrieves a registered service.
	 *
	 * @template T of object
	 *
	 * @param string $id The service identifier.
	 *
	 * @return T The resolved service.
	 */
	public function get(string $id): object
	{
		return $this->container->get($id);
	}
}
