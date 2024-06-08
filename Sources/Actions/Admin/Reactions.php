<?php

/**
 * This file takes care of managing reactions
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2024 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 1
 */

declare(strict_types = 1);

namespace SMF\Actions\Admin;

use SMF\ActionInterface;
use SMF\ActionTrait;
use SMF\ReactionTrait;
use SMF\Cache\CacheApi;
use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\ErrorHandler;
use SMF\IntegrationHook;
use SMF\ItemList;
use SMF\Lang;
use SMF\Logging;
use SMF\Menu;
use SMF\Theme;
use SMF\User;
use SMF\Utils;

class Reactions implements ActionInterface
{

	/**
	 * @inheritDoc
	 */
	public function execute(): void
	{
		// TODO: Implement execute() method.
	}

	/**
	 * @inheritDoc
	 */
	public static function load(): static
	{
		// TODO: Implement load() method.
	}

	/**
	 * @inheritDoc
	 */
	public static function call(): void
	{
		// TODO: Implement call() method.
	}
}