<?php

declare(strict_types=1);

class UserRepository
{
	/****************
	 * Public methods
	 ****************/

	public function __construct(public DatabaseConnection $db) {}
}
