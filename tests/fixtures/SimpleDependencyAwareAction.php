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
