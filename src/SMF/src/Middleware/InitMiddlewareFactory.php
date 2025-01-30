<?php

declare(strict_types=1);

namespace SMF\Middleware;

use Psr\Container\ContainerInterface;
use SMF\Config;
use SMF\Db\DatabaseApiInterface;

class InitMiddlewareFactory
{
    public function __invoke(ContainerInterface $container) : InitMiddleware
    {
        return new InitMiddleware(
			$container->get(Config::class),
			$container->get(DatabaseApiInterface::class)
		);
    }
}
