<?php

declare(strict_types=1);

namespace SMF\Container;

use Psr\Container\ContainerInterface;
use SMF\Db\DatabaseApi;
use SMF\Db\DatabaseApiInterface;

final class DatabaseApiFactory
{
	public function __invoke(ContainerInterface $container): DatabaseApiInterface
	{
		return DatabaseApi::load();
	}
}
