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

namespace SMF;

/**
 * Simplifies and standardizes implementing \ArrayAccess.
 *
 * This trait internally uses the SMF\DynamicPropertyHelper trait, so using
 * this trait also implies using that trait.
 */
trait ArrayAccessHelper
{
	use DynamicPropertyHelper;

	/****************
	 * Public methods
	 ****************/

	/**
	 * Sets properties when object is accessed as an array.
	 *
	 * @param mixed $prop The property name.
	 * @param mixed $value The value to set.
	 */
	public function offsetSet(mixed $prop, mixed $value): void
	{
		$this->__set($prop, $value);
	}

	/**
	 * Gets properties when object is accessed as an array.
	 *
	 * @param mixed $prop The property name.
	 */
	public function offsetGet(mixed $prop): mixed
	{
		return $this->__get($prop);
	}

	/**
	 * Checks whether a property has been set when object is accessed as an array.
	 *
	 * @param mixed $prop The property name.
	 */
	public function offsetExists(mixed $prop): bool
	{
		return $this->__isset($prop);
	}

	/**
	 * Unsets properties when object is accessed as an array.
	 *
	 * @param mixed $prop The property name.
	 */
	public function offsetUnset(mixed $prop): void
	{
		$this->__unset($prop);
	}
}

?>