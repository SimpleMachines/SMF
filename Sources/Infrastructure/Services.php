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

use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

/**
 * Hands out services to one consumer, and only the ones it may have.
 *
 * Every piece of code that obtains a service does so through one of these, and
 * each consumer gets its own: SMF itself may have everything, while a package
 * may have the services it declared in its package-info.xml and nothing else.
 * Asking for anything else throws rather than quietly handing it over.
 *
 * The container behind this is never exposed, so what a package can reach is
 * this pair of methods rather than whichever library SMF happens to use.
 */
final class Services implements ContainerInterface
{
	/****************
	 * Public methods
	 ****************/

	/**
	 * @param ContainerInterface $container The container holding the services.
	 * @param string $consumer Name of whoever these services are for, used when
	 *    saying who was refused.
	 * @param array $granted Service IDs this consumer may have, or ['*'] for all
	 *    of them.
	 */
	public function __construct(
		private readonly ContainerInterface $container,
		private readonly string $consumer,
		private readonly array $granted,
	) {}

	/**
	 * Whether a service is registered and this consumer may have it.
	 *
	 * @param string $id The service identifier.
	 * @return bool True if the service can be obtained here.
	 */
	public function has(string $id): bool
	{
		return $this->isGranted($id) && $this->container->has($id);
	}

	/**
	 * Gets a service.
	 *
	 * @param string $id The service identifier.
	 * @throws ServiceAccessException If this consumer has no access to it.
	 * @throws ServiceNotFoundException If nothing provides it.
	 * @return object The service.
	 */
	public function get(string $id): object
	{
		if (!$this->isGranted($id)) {
			throw new ServiceAccessException(\sprintf(
				'%s asked for the service %s, which it did not declare.',
				$this->consumer,
				$id,
			));
		}

		try {
			return $this->container->get($id);
		} catch (NotFoundExceptionInterface $e) {
			// Whichever container is underneath, what comes back out is ours.
			throw new ServiceNotFoundException(\sprintf(
				'Nothing provides the service %s.',
				$id,
			), 0, $e);
		}
	}

	/**
	 * Gets the name of whoever these services are for.
	 *
	 * @return string The consumer's name.
	 */
	public function getConsumer(): string
	{
		return $this->consumer;
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Creates the accessor that SMF itself uses.
	 *
	 * @param ContainerInterface $container The container holding the services.
	 * @return self An accessor with access to every service.
	 */
	public static function forCore(ContainerInterface $container): self
	{
		return new self($container, 'SMF', ['*']);
	}

	/**
	 * Creates the accessor for one package.
	 *
	 * @param ContainerInterface $container The container holding the services.
	 * @param string $package_name The package's name, as the admin knows it.
	 * @param array $granted The service IDs the package may use.
	 * @return self An accessor limited to those services.
	 */
	public static function forPackage(ContainerInterface $container, string $package_name, array $granted): self
	{
		return new self($container, $package_name, $granted);
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Whether this consumer may have a service.
	 *
	 * @param string $id The service identifier.
	 * @return bool True if it was granted.
	 */
	private function isGranted(string $id): bool
	{
		return \in_array('*', $this->granted, true) || \in_array($id, $this->granted, true);
	}
}
