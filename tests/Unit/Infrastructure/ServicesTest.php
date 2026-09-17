<?php

declare(strict_types=1);

namespace SMF\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use SMF\Infrastructure\Services;

class ServicesTest extends TestCase
{
	/****************
	 * Public methods
	 ****************/

	public function testHasDelegatesToContainer(): void
	{
		$container = $this->createMock(ContainerInterface::class);

		$container
			->expects($this->once())
			->method('has')
			->with('test')
			->willReturn(true);

		$services = new Services($container);

		$this->assertTrue($services->has('test'));
	}

	public function testGetDelegatesToContainer(): void
	{
		$container = $this->createMock(ContainerInterface::class);
		$service = new \stdClass();

		$container
			->expects($this->once())
			->method('get')
			->with('test')
			->willReturn($service);

		$services = new Services($container);

		$this->assertSame($service, $services->get('test'));
	}
}
