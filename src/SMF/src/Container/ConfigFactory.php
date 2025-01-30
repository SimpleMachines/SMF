<?php

declare(strict_types=1);

namespace SMF\Container;

use Psr\Container\ContainerInterface;
use SMF\Config;
use SMF\Db\DatabaseApiInterface;

final class ConfigFactory
{
	public function __invoke(ContainerInterface $container): Config
	{
		$config = new Config();
		$config::set($container->get('config')[Config::class]);
		return $config;
	}
}
