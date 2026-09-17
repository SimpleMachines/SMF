<?php

declare(strict_types=1);

namespace SMF\Tests\Unit\Infrastructure;

use League\Container\Container;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use SMF\Infrastructure\Services;

class DatabaseConnection {}

class UserRepository
{
	/****************
	 * Public methods
	 ****************/

	public function __construct(public DatabaseConnection $db) {}
}

class ContainerFactoryTest extends TestCase
{
	/****************
	 * Public methods
	 ****************/

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
