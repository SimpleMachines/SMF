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
