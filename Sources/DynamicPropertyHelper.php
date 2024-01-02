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
 * Simplifies and standardizes implementing dynamic properties.
 *
 * The set() method makes it easy to set a bunch of properties at once. It is
 * especially useful when creating an object from a row of data retrieved from
 * the database.
 *
 * The magic methods __set(), __get(), __isset(), and __unset() provided by this
 * trait call customPropertySet(), customPropertyGet(), customPropertyIsset(),
 * and customPropertyUnset(), respectively. This is so that classes that use
 * this trait but need to create their own magic methods can still use the
 * functionality that this trait provides.
 *
 * Classes that implement this trait can optionally define a protected property
 * named $prop_aliases, which sets alternative names for any arbitrary subset of
 * the class's properties. Then this trait will automatically map those aliases
 * to the properties' canonical names. This is useful if the names of database
 * columns do not match those of the related property names, or if old versions
 * of SMF used different names in different places for the same value.
 *
 * For the most part, $prop_aliases is just a simple set of key-value pairs that
 * take the form 'property_alias' => 'property_name'. However, there are three
 * special cases to be aware of:
 *
 *  - If '!' is prepended to the property_name, then the property_alias value
 *    will be treated like the boolean inverse of the real property's value.
 *
 *    For example, if $prop_aliases looks like this:
 *
 *        protected array $prop_aliases = array(
 *            'is_logged' => '!is_guest',
 *        );
 *
 *    ... then getting the value of the virtual $this->is_logged will get the
 *    opposite of the real $this->is_guest, and trying to set the value of
 *    $this->is_logged to true will actually just set $this->is_guest to false.
 *
 *  - Square brackets in the property_name indicate that the alias should point
 *    to the value of an element within a property that is an array.
 *
 *    For example, if $prop_aliases looks like this:
 *
 *        protected array $prop_aliases = array(
 *            'website_url' => 'website[url]',
 *        );
 *
 *    ... then $this->website_url becomes an alias for $this->website['url'].
 *
 *  - If property_name is the name of a callable, then getting the property's
 *    value will return the result of executing the callable. Attempts to set or
 *    unset a callable property are ignored. Checking whether the property is
 *    set will simply check whether the callable returns a non-null value.
 *
 *    For example, if $prop_aliases looks like this:
 *
 *        protected array $prop_aliases = array(
 *            'foo' => __CLASS__ . '::getFoo',
 *        );
 *
 *    ... then getting $this->foo will get the value returned by the class's
 *    getFoo() method. The callable must be a stand-alone function or a static
 *    method in order to keep PHP happy. The current object will be passed to
 *    the callable as its first argument.
 */
trait DynamicPropertyHelper
{
	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var array
	 *
	 * Arbitrary custom data about this object.
	 */
	protected array $custom = [];

	/****************
	 * Public methods
	 ****************/

	/**
	 * Sets an array of properties on this object.
	 *
	 * @param array $props Array of properties to set.
	 */
	public function set(array $props = []): void
	{
		// The magic method already has robust logic for this job, so use that.
		foreach ($props as $prop => $value) {
			$this->__set($prop, $value);
		}
	}

	/**
	 * Sets custom properties.
	 *
	 * @param string $prop The property name.
	 * @param mixed $value The value to set.
	 */
	public function __set(string $prop, mixed $value): void
	{
		$this->customPropertySet($prop, $value);
	}

	/**
	 * Gets custom property values.
	 *
	 * @param string $prop The property name.
	 */
	public function __get(string $prop): mixed
	{
		return $this->customPropertyGet($prop);
	}

	/**
	 * Checks whether a custom property has been set.
	 *
	 * @param string $prop The property name.
	 */
	public function __isset(string $prop): bool
	{
		return $this->customPropertyIsset($prop);
	}

	/**
	 * Unsets custom properties.
	 *
	 * @param string $prop The property name.
	 */
	public function __unset(string $prop): void
	{
		$this->customPropertyUnset($prop);
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Sets custom properties.
	 *
	 * @param mixed $prop The property name.
	 * @param mixed $value The value to set.
	 */
	protected function customPropertySet(mixed $prop, mixed $value): void
	{
		if (!empty($this->prop_aliases) && array_key_exists($prop, $this->prop_aliases)) {
			// Can't unset a virtual property.
			if (is_null($value)) {
				return;
			}

			$real_prop = $this->prop_aliases[$prop];

			// Callable properties can't be set.
			if (is_callable($real_prop)) {
				return;
			}

			if (strpos($real_prop, '!') === 0) {
				$real_prop = ltrim($real_prop, '!');
				$value = !$value;
			}

			if (strpos($real_prop, '[') !== false) {
				$real_prop = explode('[', rtrim($real_prop, ']'));

				if (is_object($this->{$real_prop[0]})) {
					$this->{$real_prop[0]}->{$real_prop[1]} = $value;
				} else {
					$this->{$real_prop[0]}[$real_prop[1]] = $value;
				}
			} else {
				$this->{$real_prop} = $value;
			}
		} elseif (property_exists($this, $prop)) {
			$this->{$prop} = $value;
		} else {
			$this->custom[$prop] = $value;
		}
	}

	/**
	 * Gets custom property values.
	 *
	 * @param mixed $prop The property name.
	 */
	protected function customPropertyGet(mixed $prop): mixed
	{
		if (!empty($this->prop_aliases) && array_key_exists($prop, $this->prop_aliases)) {
			$real_prop = $this->prop_aliases[$prop];

			// Callable properties are calculated dynamically.
			if (is_callable($real_prop)) {
				return call_user_func($real_prop, $this);
			}

			if (($not = strpos($real_prop, '!') === 0)) {
				$real_prop = ltrim($real_prop, '!');
			}

			if (strpos($real_prop, '[') !== false) {
				$real_prop = explode('[', rtrim($real_prop, ']'));

				if (is_object($this->{$real_prop[0]})) {
					$value = $this->{$real_prop[0]}->{$real_prop[1]};
				} else {
					$value = $this->{$real_prop[0]}[$real_prop[1]];
				}
			} else {
				$value = $this->{$real_prop};
			}

			return $not ? !$value : $value;
		}

		if (property_exists($this, $prop)) {
			return $this->{$prop} ?? null;
		}

		return $this->custom[$prop] ?? null;
	}

	/**
	 * Checks whether a custom property has been set.
	 *
	 * @param mixed $prop The property name.
	 */
	protected function customPropertyIsset(mixed $prop): bool
	{
		if (!empty($this->prop_aliases) && array_key_exists($prop, $this->prop_aliases)) {
			$real_prop = ltrim($this->prop_aliases[$prop], '!');

			// A callable property is set if it returns a non-null value.
			if (is_callable($real_prop)) {
				return call_user_func($real_prop, $this) !== null;
			}

			if (strpos($real_prop, '[') !== false) {
				$real_prop = explode('[', rtrim($real_prop, ']'));

				if (is_object($this->{$real_prop[0]})) {
					return isset($this->{$real_prop[0]}->{$real_prop[1]});
				}

				return isset($this->{$real_prop[0]}[$real_prop[1]]);
			}

			return isset($this->{$real_prop});
		}

		if (property_exists($this, $prop)) {
			return isset($this->{$prop});
		}

		return isset($this->custom[$prop]);
	}

	/**
	 * Unsets custom properties.
	 *
	 * @param mixed $prop The property name.
	 */
	protected function customPropertyUnset(mixed $prop): void
	{
		if (!empty($this->prop_aliases) && array_key_exists($prop, $this->prop_aliases)) {
			// Can't unset a virtual property.
			return;
		}

		if (property_exists($this, $prop)) {
			unset($this->{$prop});
		} else {
			unset($this->custom[$prop]);
		}
	}
}

?>