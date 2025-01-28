<?php

declare(strict_types=1);

namespace SMF\Handler\Container;

use Psr\Container\ContainerInterface;
use SMF\Handler\BoardIndexHandler;

final class BoardIndexHandlerFactory
{
	public function __invoke(ContainerInterface $container): BoardIndexHandler
	{
		return new BoardIndexHandler();
	}
}
