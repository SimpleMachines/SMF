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

namespace SMF;

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
	 * Determines whether this action allows access if guest access is disabled.
	 *
	 * @return bool True if access is allowed, false otherwise.
	 */
	public function isRestrictedGuestAccessAllowed(): bool;

	/**
	 * Determines whether this action allows access in maintenance mode.
	 *
	 * @return bool True if access is allowed, false otherwise.
	 */
	public function canShowInMaintenanceMode(): bool;

	/**
	 * Determines whether this action can be logged in the online log.
	 *
	 * @return bool
	 */
	public function canBeLogged(): bool;

	/**
	 * Determines whether this is a simple action.
	 *
	 * Simple actions don't require the index template at all.
	 *
	 * @return bool
	 */
	public function isSimpleAction(): bool;

	/**
	 * Gets the output type for this action.
	 *
	 * @return OutputTypeInterface
	 */
	public function getOutputType(): OutputTypeInterface;

	/**
	 * Determines whether this action can be accessed without accepting
	 * the registration agreement and privacy policy.
	 *
	 * @return bool
	 */
	public function isAgreementAction(): bool;

	/**
	 * Determines whether debugging info should be shown.
	 *
	 * @return bool
	 */
	public function canShowDebuggingInfo(): bool;

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
	 * @return static An instance of the class.
	 */
	public static function load(): static;

	/**
	 * Convenience method to load() and execute() an instance of the class.
	 */
	public static function call(): void;
}

?>