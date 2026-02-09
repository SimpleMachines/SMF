<?php
namespace SMF\Infrastructure;

use League\Container\ServiceProvider\AbstractServiceProvider;

class ServiceProvider extends AbstractServiceProvider
{
	private array $services;

	public function __construct(array $services = [])
	{
		$coreServices = require __DIR__ . '/ServicesList.php';
		$this->services = array_filter(array_merge($coreServices, $services));
	}
	public function provides(string $id): bool
	{
		return array_key_exists($id, $this->services);
	}

	public function register(): void
	{
		$container = $this->getContainer();
		foreach ($this->services as $id => $config) {
			$method = ($config['shared'] ?? false) ? 'addShared' : 'add';

			$container->$method($id)
				->addArguments($config['arguments'] ?? []);
		}
	}
}
