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

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use SMF\Infrastructure\Services;

class ServicesTest extends TestCase
{
	/****************
	 * Public methods
	 ****************/

	/**
	 * Services::has() delegates to the container.
	 *
	 * Expected: has('test') asks the container's has('test') once and returns its
	 *           result, true.
	 */
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

	/**
	 * Services::get() delegates to the container.
	 *
	 * Expected: get('test') asks the container's get('test') once and returns the
	 *           service it gave.
	 */
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
