<?php

declare(strict_types=1);

namespace SMF\Tests\Unit;

use League\Container\Container;
use PHPUnit\Framework\TestCase;
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

class DatabaseConnection {}

class UserRepository {
    // Requires a DatabaseConnection instance
    public function __construct(public DatabaseConnection $db) {}
}

class ContainerFactoryTest extends TestCase
{
	public function testFactoryReceivesContainerAndResolvesDependencies(): void
	{
		$container = new Container();
		$services = new Services($container);

		$container->add(DatabaseConnection::class, static function (): DatabaseConnection {
			return new DatabaseConnection();
		});

		$factory_called = false;

		$container->add(
			UserRepository::class,
			function () use (&$factory_called, $services): UserRepository {
				$factory_called = true;

				$db = $services->get(DatabaseConnection::class);

				$this->assertInstanceOf(DatabaseConnection::class, $db);

				return new UserRepository($db);
			},
		);

		$user_repository = $container->get(UserRepository::class);

		$this->assertTrue($factory_called);
		$this->assertInstanceOf(UserRepository::class, $user_repository);
		$this->assertInstanceOf(DatabaseConnection::class, $user_repository->db);
	}
}
