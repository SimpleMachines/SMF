<?php

declare(strict_types=1);

namespace SMF\Container;

use Psr\Container\ContainerInterface;
use SMF\Theme;

final class ThemeFactory
{
	public function __invoke(ContainerInterface $container): Theme
	{
		return Theme::load();
	}
}
