<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2024 Simple Machines and individual contributors
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
	protected function __construct()
	{
	}
}

?>