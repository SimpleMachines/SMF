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

namespace SMF\Tests\Unit\Infrastructure;

use DatabaseConnection;
use League\Container\Container;
use PHPUnit\Framework\TestCase;
use SMF\Infrastructure\Services;
use UserRepository;

class ContainerFactoryTest extends TestCase
{
	/****************
	 * Public methods
	 ****************/

	/**
	 * A container factory resolves its dependencies through Services.
	 *
	 * Expected: getting UserRepository from the container calls its factory, which
	 *           gets the DatabaseConnection through Services and passes it on.
	 */
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

	/***********************
	 * Public static methods
	 ***********************/

	public static function setUpBeforeClass(): void
	{
		require_once TESTS_BOARDDIR . '/tests/fixtures/DatabaseConnection.php';

		require_once TESTS_BOARDDIR . '/tests/fixtures/UserRepository.php';
	}
}
