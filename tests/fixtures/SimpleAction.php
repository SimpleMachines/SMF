<?php

declare(strict_types=1);

use SMF\ActionInterface;
use SMF\ActionTrait;

class SimpleAction implements ActionInterface
{
	use ActionTrait;

	/****************
	 * Public methods
	 ****************/

	public function execute(): void
	{
		// Stop execution before Utils::obExit().
		throw new SimpleActionExecuted();
	}

	/***********************
	 * Public static methods
	 ***********************/

	public static function clearStatcics(): void
	{
		self::$obj = null;
	}
}
