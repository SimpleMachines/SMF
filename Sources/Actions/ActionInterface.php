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

namespace SMF\Actions;

/**
 * Interface for all action classes.
 *
 * In general, constructors for classes implementing this interface should
 * be protected in order to force instantiation via load(). This is because
 * there should normally only ever be one instance of an action.
 */
interface ActionInterface
{
	/****************
	 * Public methods
	 ****************/

	/**
	 * This method should function as the dispatcher to whatever sub-action
	 * methods are necessary. It is also the place to do any heavy lifting
	 * needed to finalize setup before dispatching to a sub-action method.
	 */
	public function execute(): void;

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Static wrapper for constructor.
	 *
	 * @return object An instance of the class.
	 */
	public static function load(): object;

	/**
	 * Convenience method to load() and execute() an instance of the class.
	 */
	public static function call(): void;
}

?>