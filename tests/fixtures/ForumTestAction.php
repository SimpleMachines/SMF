<?php

use SMF\ActionInterface;
use SMF\ActionTrait;
use SMF\DependencyAwareActionInterface;

class ForumTestAction implements ActionInterface, DependencyAwareActionInterface
{
	use ActionTrait;

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

	public function execute(): void
	{
		// Stop execution before Utils::obExit().
		throw new ForumTestActionExecuted();
	}
}
