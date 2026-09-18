<?php

class UserRepository
{
	/****************
	 * Public methods
	 ****************/

	public function __construct(public DatabaseConnection $db) {}
}
