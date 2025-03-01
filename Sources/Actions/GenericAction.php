<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2025 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 2
 */

declare(strict_types=1);

namespace SMF\Actions;

use SMF\ActionInterface;
use SMF\ActionTrait;

/**
 * An action for executing arbitrary callables.
 *
 * This is intended for cases where old mods declare a bare function as the
 * callable for a custom action.
 */
class GenericAction implements ActionInterface
{
	use ActionTrait;

	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var callable
	 *
	 * The callable to be executed.
	 */
	private $current_action;

	/****************
	 * Public methods
	 ****************/

	/**
	 * Sets the callable to be executed in $this->execute().
	 *
	 * @param callable $current_action A callable.
	 */
	public function setCallable(callable $current_action): void
	{
		$this->current_action = $current_action;
	}

	/**
	 * Executes the indicated callable.
	 *
	 * @param callable $current_action A callable.
	 */
	public function execute(): void
	{
		call_user_func($this->current_action);
	}
}

?>