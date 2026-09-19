<?php

declare(strict_types=1);

use SMF\DependencyAwareActionInterface;

class SimpleDependencyAwareAction extends SimpleAction implements DependencyAwareActionInterface
{
	/*******************
	 * Public properties
	 *******************/

	public UserRepository $user_repository;

	public DatabaseConnection $database_connection;

	/****************
	 * Public methods
	 ****************/

	public function getDependencyList(): array
	{
		return [
			UserRepository::class,
			DatabaseConnection::class,
		];
	}

	public function setDependencies(array $dependencies): void
	{
		$this->user_repository = $dependencies[0];
		$this->database_connection = $dependencies[1];
	}
}
