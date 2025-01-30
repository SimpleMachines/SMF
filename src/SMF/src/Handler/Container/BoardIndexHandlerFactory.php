<?php

declare(strict_types=1);

namespace SMF\Handler\Container;

use Psr\Container\ContainerInterface;
use SMF\Handler\BoardIndexHandler;
use SMF\Db\DatabaseApiInterface;
use SMF\Theme;

final class BoardIndexHandlerFactory
{
	public function __invoke(ContainerInterface $container): BoardIndexHandler
	{
		return new BoardIndexHandler(
			$container->get(Theme::class)
		);
	}
}
