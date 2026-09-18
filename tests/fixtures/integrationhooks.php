<?php

/**
 * @param array<string, true|object|\Closure> &$factories
 *
 * The array keys identify the services to register.
 *
 * A value of true registers the service by its fully qualified class name,
 * allowing the container to instantiate it automatically.
 *
 * A Closure registers a factory. SMF binds an SMF\Infrastructure\Services
 * instance to the closure as $this, allowing the factory to resolve other
 * registered services with $this->get().
 *
 * An object registers that object as the service instance.
 */
function my_integrated_service(array &$factories): void
{
	$factories[DatabaseConnection::class] = true;

	$factories[UserRepository::class] = function () {
		$db = $this->get(DatabaseConnection::class);

		return new UserRepository($db);
	};
}
