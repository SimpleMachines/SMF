<?php

declare(strict_types=1);

namespace SMF\Handler\Container;

use Psr\Container\ContainerInterface;
use SMF\Handler\MessageIndexHandler;

final class MessageIndexHandlerFactory
{
	public function __invoke(ContainerInterface $container): MessageIndexHandler
	{
		return new MessageIndexHandler();
	}
}
