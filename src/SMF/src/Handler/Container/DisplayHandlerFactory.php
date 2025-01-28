<?php

declare(strict_types=1);

namespace SMF\Handler\Container;

use Psr\Container\ContainerInterface;
use SMF\Handler\DisplayHandler;

final class DisplayHandlerFactory
{
	public function __invoke(ContainerInterface $container): DisplayHandler
	{
		return new DisplayHandler();
	}
}
