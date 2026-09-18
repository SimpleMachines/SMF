<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2026 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 5-dev
 */

declare(strict_types=1);

namespace SMF\Infrastructure;

use League\Container\Container;
use SMF\Config;
use SMF\ErrorHandler;

/**
 * Holds the services SMF and its packages provide.
 *
 * SMF's own services come from ServicesList.php. A package's come from what it
 * declared in its package-info.xml, which the administrator saw when installing
 * it and can withdraw afterwards, so a package that has been refused is simply
 * not registered at all.
 *
 * Which container does the work is settled here and nowhere else. Everything
 * else is handed an SMF\Infrastructure\Services, which offers has() and get()
 * and nothing more.
 */
class ServiceRegistry
{
	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var Container
	 *
	 * The container doing the work.
	 */
	protected Container $container;

	/**
	 * @var array
	 *
	 * Who provided each registered service, keyed by service ID.
	 */
	protected array $providers = [];

	/**
	 * @var array
	 *
	 * Services that were declared but not registered, keyed by service ID, each
	 * with the package that wanted it and the reason it was left out.
	 */
	protected array $rejected = [];

	/****************
	 * Public methods
	 ****************/

	public function __construct()
	{
		$this->container = new Container();

		$this->addCoreServices();
		$this->addPackageServices();
	}

	/**
	 * Gets the accessor SMF itself uses, which reaches every service.
	 *
	 * @return Services The accessor.
	 */
	public function services(): Services
	{
		return Services::forCore($this->container);
	}

	/**
	 * Gets who provided each registered service.
	 *
	 * @return array Provider names, keyed by service ID.
	 */
	public function getProviders(): array
	{
		return $this->providers;
	}

	/**
	 * Gets the services that were declared but not registered.
	 *
	 * @return array The package and the reason, keyed by service ID.
	 */
	public function getRejected(): array
	{
		return $this->rejected;
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Registers the services that SMF itself provides.
	 */
	protected function addCoreServices(): void
	{
		foreach (require __DIR__ . '/ServicesList.php' as $id => $config) {
			$method = ($config['shared'] ?? false) ? 'addShared' : 'add';

			$this->container->$method($id)->addArguments($config['arguments'] ?? []);

			$this->providers[$id] = 'SMF';
		}
	}

	/**
	 * Registers the services that installed packages provide.
	 *
	 * Each package's factories are handed an accessor that reaches the services
	 * the package declared and no others, so a factory asking for something the
	 * administrator never approved fails where it happens.
	 */
	protected function addPackageServices(): void
	{
		foreach (PackageServices::all() as $package_id => $manifest) {
			$provided = array_column($manifest['provides'] ?? [], 'id');

			if (empty($manifest['granted'])) {
				foreach ($provided as $id) {
					$this->rejected[$id] = ['package' => $manifest['name'], 'reason' => 'revoked'];
				}

				continue;
			}

			// A package may always use what it provides itself.
			$services = Services::forPackage(
				$this->container,
				$manifest['name'],
				array_merge($manifest['uses'] ?? [], $provided),
			);

			foreach ($manifest['provides'] ?? [] as $service) {
				// Whoever got there first keeps the name, rather than a later
				// package quietly replacing a service other code is using.
				if (isset($this->providers[$service['id']])) {
					$this->rejected[$service['id']] = ['package' => $manifest['name'], 'reason' => 'taken'];

					$this->logProblem(\sprintf(
						'%s provides the service %s, which %s already provides.',
						$manifest['name'],
						$service['id'],
						$this->providers[$service['id']],
					));

					continue;
				}

				$this->container->addShared(
					$service['id'],
					fn(): object => $this->build($service, $services),
				);

				$this->providers[$service['id']] = $manifest['name'];
			}
		}
	}

	/**
	 * Builds one service from the factory a package declared for it.
	 *
	 * The accessor is passed to the factory as its argument rather than bound to
	 * it, so a factory can be any callable the package likes.
	 *
	 * @param array $service The service's ID, factory and file.
	 * @param Services $services The accessor the factory may use.
	 * @throws ServiceAccessException If the factory cannot be used.
	 * @return object The service.
	 */
	protected function build(array $service, Services $services): object
	{
		if (!empty($service['file'])) {
			$file = strtr($service['file'], [
				'$boarddir' => Config::$boarddir,
				'$sourcedir' => Config::$sourcedir,
			]);

			// A package's own files live in the forum; anything else is not ours to load.
			if (!str_starts_with(realpath($file) ?: '', realpath(Config::$boarddir) ?: Config::$boarddir)) {
				throw new ServiceAccessException(\sprintf(
					'The factory file for %s is outside the forum: %s',
					$service['id'],
					$service['file'],
				));
			}

			require_once $file;
		}

		if (!\is_callable($service['factory'])) {
			throw new ServiceAccessException(\sprintf(
				'The factory for %s cannot be called: %s',
				$service['id'],
				$service['factory'],
			));
		}

		$made = \call_user_func($service['factory'], $services);

		if (!\is_object($made)) {
			throw new ServiceAccessException(\sprintf(
				'The factory for %s did not return an object.',
				$service['id'],
			));
		}

		return $made;
	}

	/**
	 * Records a problem with a service without stopping the page.
	 *
	 * @param string $message What went wrong.
	 */
	protected function logProblem(string $message): void
	{
		ErrorHandler::log($message, 'general');
	}
}
