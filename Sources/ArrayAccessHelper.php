<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2026 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 4
 */

declare(strict_types=1);

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
	 * @return mixed A reference to the property.
	 */
	public function &offsetGet(mixed $prop): mixed
	{
		if (property_exists($this, $prop)) {
			return $this->{$prop};
		}

		if (!empty($this->prop_aliases) && \array_key_exists($prop, $this->prop_aliases)) {
			$real_prop = $this->prop_aliases[$prop];

			// Callable properties are calculated dynamically.
			if (str_contains($real_prop, '::') && \is_callable($real_prop)) {
				$this->custom[$prop] = \call_user_func($real_prop, $this);

				return $this->custom[$prop];
			}

			if (str_starts_with($real_prop, '!')) {
				$real_prop = ltrim($real_prop, '!');

				if (str_contains($real_prop, '[')) {
					$real_prop = explode('[', rtrim($real_prop, ']'));

					if (\is_object($this->{$real_prop[0]})) {
						$this->custom[$prop] = !$this->{$real_prop[0]}->{$real_prop[1]};
					} else {
						$this->custom[$prop] = !$this->{$real_prop[0]}[$real_prop[1]];
					}
				} else {
					$this->custom[$prop] = !$this->{$real_prop};
				}

				return $this->custom[$prop];
			}

			if (str_contains($real_prop, '[')) {
				$real_prop = explode('[', rtrim($real_prop, ']'));

				if (\is_object($this->{$real_prop[0]})) {
					return $this->{$real_prop[0]}->{$real_prop[1]};
				}

				return $this->{$real_prop[0]}[$real_prop[1]];
			}

			return $this->{$real_prop};
		}

		return $this->custom[$prop];
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
