<?php

declare(strict_types=1);

use SMF\Forum;

// Strip the Forum class down so that it can be easily tested.
class SimpleForum extends Forum
{
	/****************
	 * Public methods
	 ****************/

	public function __construct() {}

	/******************
	 * Internal methods
	 ******************/

	protected function init(): void {}

	protected function preflight(): void {}
}
