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

trait ActionTrait
{
	/****************************
	 * Internal static properties
	 ****************************/

	/**
	 * @var static
	 *
	 * An instance of this class.
	 * This is used by the load() method to prevent multiple instantiations.
	 */
	protected static self $obj;

	/****************
	 * Public methods
	 ****************/

	/**
	 * Determines whether this action allows access if guest access is restricted.
	 *
	 * @return bool True if access is allowed, false otherwise.
	 */
	public function isRestrictedGuestAccessAllowed(): bool
	{
		return false;
	}

	/**
	 * Determines whether this action allows access in maintenance mode.
	 *
	 * @return bool True if access is allowed, false otherwise.
	 */
	public function canShowInMaintenanceMode(): bool
	{
		return false;
	}

	/**
	 * Determines whether this action can be logged in the online log.
	 *
	 * @return bool
	 */
	public function canBeLogged(): bool
	{
		return true;
	}

	/**
	 * Determines whether this is a simple action.
	 *
	 * @return bool
	 */
	public function isSimpleAction(): bool
	{
		return false;
	}

	/**
	 * Gets the output type for this action.
	 *
	 * @return OutputTypeInterface
	 */
	public function getOutputType(): OutputTypeInterface
	{
		return new OutputTypes\Html();
	}

	/**
	 * Determines whether this action can be accessed without accepting
	 * the registration agreement and privacy policy.
	 *
	 * @return bool
	 */
	public function isAgreementAction(): bool
	{
		return false;
	}

	/**
	 * Determines whether debugging info should be shown.
	 *
	 * @return bool
	 */
	public function canShowDebuggingInfo(): bool
	{
		return true;
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Static wrapper for constructor.
	 *
	 * @return static An instance of this class.
	 */
	public static function load(): static
	{
		if (!isset(static::$obj)) {
			static::$obj = new static();
		}

		return static::$obj;
	}

	/**
	 * Convenience method to load() and execute() an instance of this class.
	 */
	public static function call(): void
	{
		self::load()->execute();
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Constructor. Protected to force instantiation via self::load().
	 */
	protected function __construct() {}
}

?>