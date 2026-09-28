<?php

declare(strict_types=1);

/**
 * Registers services used by SMF.
 *
 * @param array<string, true|object|\Closure> &$factories
 *    A map of service identifiers to their registration definitions.
 *
 * A value of true registers the service using its identifier as the class name.
 * The container will instantiate the class without constructor arguments.
 *
 * A Closure registers a factory for the service. SMF binds an
 * SMF\Infrastructure\Services instance to the closure as $this. The factory
 * can use $this->get() to resolve other registered services.
 *
 * An object registers that object directly as the service instance.
 */
function my_integrated_service(array &$factories): void
{
	$factories[DatabaseConnection::class] = true;

	$factories[UserRepository::class] = function () {
		$db = $this->get(DatabaseConnection::class);

		return new UserRepository($db);
	};
}
