<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2024 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 1
 */

declare(strict_types=1);

namespace SMF;

/**
 * A router and a dispatcher for sub-actions.
 *
 * Any action class that delegates tasks to different sub-actions should use
 * this trait and implement ProvidesSubActionInterface.  Use the public methods
 * defined in that interface to aad sub-actions.
 */
trait ProvidesSubActionTrait
{
	/**
	 * @var string Current working sub-action.
	 */
	protected string $sub_action;

	/**
	 * @var array Key-value pair of all available sub-actions.
	 */
	private array $sub_actions;

	/****************
	 * Public methods
	 ****************/

	/**
	 * Set a default sub-action.
	 *
	 * The default sub-action is the one executed if no sub-action was requested.
	 *
	 * @param string $sa
	 */
	public function setDefaultSubAction(string $sa): void
	{
		$this->sub_action = $sa;
	}

	/**
	 * Add a sub-action.
	 *
	 * A sub-action is another term for a route where a URL parameter, usually "sa", has
	 * its value mapped to some function.
	 *
	 * @param string $sa
	 * @param callable $cb
	 */
	public function addSubAction(string $sa, callable $cb): void
	{
		$this->sub_actions[$sa] = $cb;
	}

	/**
	 * Determines whether the provided sub-action exists.
	 *
	 * @param string $sa
	 *
	 * @return bool
	 */
	public function hasSubAction(string $sa): bool
	{
		return isset($this->sub_actions[$sa]);
	}

	/**
	 * Finds a sub-action that is associated with the given keyword.
	 *
	 * If no sub-actions match or nothing was provided, the internal variable is set to
	 * either the previously specified default or the first registered sub-action.
	 *
	 * @param string|null $sa
	 */
	public function findRequestedSubAction(?string $sa = null): void
	{
		if (isset($this->sub_actions[$sa])) {
			$this->sub_action = $sa;
		} else {
			$this->sub_action = $this->sub_action ?? array_key_first($this->sub_actions);
		}
	}

	/**
	 * @return string|null
	 */
	public function getSubAction(): ?string
	{
		return $this->sub_action;
	}

	/**
	 * Executes the sub-action.
	 *
	 * @uses findRequestedSubAction() if $sa is null.
	 *
	 * @param string|null $sa
	 *
	 * @return mixed
	 */
	public function callSubAction(?string $sa = null): mixed
	{
		$this->findRequestedSubAction($sa);

		return call_user_func($this->sub_actions[$this->sub_action]);
	}
}

?>