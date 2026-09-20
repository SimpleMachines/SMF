<?php

declare(strict_types=1);

use SMF\Forum;

// Strip the Forum class down so that it can be easily tested.
class SimpleForum extends Forum
{
	/****************
	 * Public methods
	 ****************/

	public function __construct()
	{
		$this->initContainer();
		$this->registerIntegratedServices();
	}

	/***********************
	 * Public static methods
	 ***********************/

	public static function clearStatcics(): void
	{
		self::$current_action = null;
	}

	/******************
	 * Internal methods
	 ******************/

	protected function init(): void {}

	protected function preflight(): void {}
}
