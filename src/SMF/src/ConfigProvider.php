<?php

declare(strict_types=1);

namespace SMF;

use Mezzio\Application;
use Mezzio\Container\ApplicationConfigInjectionDelegator;

/**
 * The configuration provider for the App module
 *
 * @see https://docs.laminas.dev/laminas-component-installer/
 */
class ConfigProvider
{
	/**
	 * Returns the configuration array
	 *
	 * To add a bit of a structure, each section is defined in a separate
	 * method which returns an array with its configuration.
	 */
	public function __invoke(): array
	{
		return [
			'dependencies' => $this->getDependencies(),
			'templates'    => $this->getTemplates(),
			'routes'       => $this->getRoutes(),
		];
	}

	/**
	 * Returns the container dependencies
	 */
	public function getDependencies(): array
	{
		return [
			'delegators' => [
				Application::class => [ // allow routes and pipeline definitions from configuration
					ApplicationConfigInjectionDelegator::class,
				],
			],
			'factories'  => [
				Handler\HomePageHandler::class => Handler\HomePageHandlerFactory::class,
				User::class                    => Container\UserFactory::class,
			],
			'invokables' => [
				Handler\PingHandler::class => Handler\PingHandler::class,
				Handler\BoardIndexHandler::class => Handler\BoardIndexHandler::class,
				Handler\MessageIndexHandler::class => Handler\MessageIndexHandler::class,
				Handler\DisplayHandler::class => Handler\DisplayHandler::class,
				Theme::class => Theme::class,
			],
		];
	}

	public function getRoutes(): array
	{
		return [
			[
				'path'       => '/',
				'middleware' => Handler\BoardIndexHandler::class,
				'name'       => 'board.index',
				'allowed_methods' => ['GET'],
			],
			[
				'path'       => '/board/{slug:[a-zA-Z0-9_-]+}[/{start:[0-9]+}]',
				'middleware' => Handler\MessageIndexHandler::class,
				'name'       => 'board',
				'allowed_methods' => ['GET'],
			],
			[
				'path'       => '/topic/{slug:[a-zA-Z0-9_-]+}[/{msg:[a-zA-Z0-9_-]+}]',
				'middleware' => Handler\DisplayHandler::class,
				'name'       => 'display',
				'allowed_methods' => ['GET'],
			],
			[
				'path'       => '/ping',
				'middleware' => Handler\PingHandler::class,
				'name'       => 'api.ping',
				'allowed_methods' => ['GET'],
			],
		];
	}

	/**
	 * Returns the templates configuration
	 */
	public function getTemplates(): array
	{
		return [
			'paths' => [
				'smf'    => [__DIR__ . '/../templates/smf'],
				'error'  => [__DIR__ . '/../templates/error'],
				'layout' => [__DIR__ . '/../templates/layout'],
			],
		];
	}
}
