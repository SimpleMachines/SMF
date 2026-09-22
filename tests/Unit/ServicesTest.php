<?php

declare(strict_types=1);

namespace SMF\Tests\Unit;

use League\Container\Container;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SMF\Infrastructure\ServiceAccessException;
use SMF\Infrastructure\ServiceNotFoundException;
use SMF\Infrastructure\Services;

#[CoversClass(Services::class)]
class ServicesTest extends TestCase
{
	/****************
	 * Public methods
	 ****************/

	public function testItHandsOverAServiceThatWasGranted(): void
	{
		$services = Services::forPackage($this->container(), 'My Mod', ['test.granted']);

		$this->assertInstanceOf(\stdClass::class, $services->get('test.granted'));
	}

	public function testItRefusesAServiceThatWasNotGranted(): void
	{
		$services = Services::forPackage($this->container(), 'My Mod', ['test.granted']);

		$this->expectException(ServiceAccessException::class);
		$this->expectExceptionMessage('My Mod asked for the service test.other');

		$services->get('test.other');
	}

	/**
	 * The refusal has to come from the grants rather than from the container,
	 * or a package would learn what else is installed by asking for it.
	 */
	public function testItRefusesAnUngrantedServiceEvenWhenItIsRegistered(): void
	{
		$services = Services::forPackage($this->container(), 'My Mod', []);

		$this->assertFalse($services->has('test.granted'));
	}

	public function testItSaysNoToAServiceNobodyProvides(): void
	{
		$services = Services::forPackage($this->container(), 'My Mod', ['test.missing']);

		$this->assertFalse($services->has('test.missing'));
	}

	/**
	 * Which container SMF uses is nobody else's business, so its exceptions do
	 * not travel either.
	 */
	public function testItThrowsItsOwnExceptionWhenNothingProvidesTheService(): void
	{
		$services = Services::forCore($this->container());

		$this->expectException(ServiceNotFoundException::class);
		$this->expectExceptionMessage('Nothing provides the service test.missing');

		$services->get('test.missing');
	}

	public function testSmfItselfReachesEveryService(): void
	{
		$services = Services::forCore($this->container());

		$this->assertTrue($services->has('test.granted'));
		$this->assertTrue($services->has('test.other'));
		$this->assertSame('SMF', $services->getConsumer());
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Builds a container holding two services.
	 *
	 * @return Container The container.
	 */
	protected function container(): Container
	{
		$container = new Container();

		foreach (['test.granted', 'test.other'] as $id) {
			$container->addShared($id, static fn(): \stdClass => new \stdClass());
		}

		return $container;
	}
}
