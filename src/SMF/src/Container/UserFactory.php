<?php

declare(strict_types=1);

namespace SMF\Container;

use Psr\Container\ContainerInterface;
use SMF\User;

final class UserFactory
{
	public function __invoke(ContainerInterface $container): User
	{
		User::load();
		return User::$me;
	}
}
