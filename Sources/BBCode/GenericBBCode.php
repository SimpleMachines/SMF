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

namespace SMF\BBCode;

use SMF\Utils;

/**
 * Used for BBCodes that don't have dedicated classes.
 */
class GenericBBCode extends BBCode
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var mixed
	 *
	 * A callable to call in order to validate the tag and/or content.
	 *
	 * Will typically be set to the value of $definition['validate'], or to
	 * false if $definition['validate'] is not set.
	 *
	 * When this is set to something callable, it will be called from within
	 * $this->validate().
	 */
	public mixed $validation_callback = false;

	/**************************
	 * Public static properties
	 **************************/

	/**
	 * @var array
	 *
	 * The properties that can be given for BBCode parameters.
	 *
	 * Keys are property names. Values are lists of one or more functions to
	 * call to verify that the property value matches the expected type. When
	 * more than one function name is provided, the value will be accepted if
	 * any of the specified functions returns true.
	 *
	 * @see BBCode::$parameters for info on what each of these properties mean.
	 */
	public static array $parameter_properties = [
		'match' => ['is_string'],
		'quoted' => ['is_bool', 'is_string'],
		'validate' => ['is_callable'],
		'value' => ['is_string'],
		'optional' => ['is_bool'],
		'default' => ['is_scalar'],
	];

	/****************
	 * Public methods
	 ****************/

	/**
	 * Constructor.
	 *
	 * @param array $definition Array of key-value pairs, where keys are names
	 *    of properties and values are the values to set for those properties.
	 */
	public function __construct(array $definition)
	{
		if (
			!isset($definition['tag'])
			|| !\is_string($definition['tag'])
			|| $definition['tag'] === ''
		) {
			throw new \ValueError();
		}

		$this->tag = strtolower($definition['tag']);
		$this->block_level = !empty($definition['block_level']);

		if (
			\in_array(
				$definition['type'] ?? BBCode::TYPE_PARSED_CONTENT,
				[
					BBCode::TYPE_PARSED_CONTENT,
					BBCode::TYPE_PARSED_EQUALS,
					BBCode::TYPE_UNPARSED_EQUALS,
					BBCode::TYPE_UNPARSED_COMMAS,
					BBCode::TYPE_CLOSED,
					BBCode::TYPE_UNPARSED_CONTENT,
					BBCode::TYPE_UNPARSED_COMMAS_CONTENT,
					BBCode::TYPE_UNPARSED_EQUALS_CONTENT,
				],
			)
		) {
			$this->type = $definition['type'] ?? BBCode::TYPE_PARSED_CONTENT;
		}

		if (\is_array($definition['parameters'] ?? null)) {
			$this->parameters = $definition['parameters'];

			foreach ($this->parameters as $param => $properties) {
				$this->parameters[$param] = array_filter(
					(array) $this->parameters[$param],
					function ($v, $k) {
						if (!isset(self::$parameter_properties[$k])) {
							return false;
						}

						foreach (self::$parameter_properties[$k] as $func) {
							if ($func($v)) {
								return true;
							}
						}
					},
					ARRAY_FILTER_USE_BOTH,
				);
			 }
		}

		if (\is_string($definition['test'] ?? null)) {
			$this->test = $definition['test'];
		}

		if (
			\in_array(
				$this->type,
				[
					BBCode::TYPE_CLOSED,
					BBCode::TYPE_UNPARSED_CONTENT,
					BBCode::TYPE_UNPARSED_COMMAS_CONTENT,
					BBCode::TYPE_UNPARSED_EQUALS_CONTENT,
				],
			)
		) {
			$this->content = (string) ($definition['content'] ?? ($this->block_level ? '<div>$1</div>' : '$1'));
			$this->disabled_content = (string) ($definition['disabled_content'] ?? ($this->block_level ? '<div>$1</div>' : '$1'));
		} else {
			$this->before = (string) ($definition['before'] ?? '');
			$this->after = (string) ($definition['after'] ?? '');
			$this->disabled_before = (string) ($definition['disabled_before'] ?? ($this->block_level ? '<div>' : ''));
			$this->disabled_after = (string) ($definition['disabled_after'] ?? ($this->block_level ? '</div>' : ''));
		}

		if (\in_array($this->type, [BBCode::TYPE_UNPARSED_EQUALS, BBCode::TYPE_PARSED_EQUALS])) {
			$this->quoted = isset($definition['quoted']) ? (string) $definition['quoted'] : null;
		}

		if (\in_array($definition['trim'] ?? null, ['inside', 'outside', 'both'])) {
			$this->trim = $definition['trim'];
		}

		if (isset($definition['require_parents'])) {
			$this->require_parents = array_map('strval', (array) $definition['require_parents']);
		}

		if (isset($definition['require_children'])) {
			$this->require_children = array_map('strval', (array) $definition['require_children']);
		}

		if (isset($definition['disallow_children'])) {
			$this->disallow_children = array_map('strval', (array) $definition['disallow_children']);
		}

		if (isset($definition['validate'])) {
			$this->validation_callback = $definition['validate'];
		}
	}

	/**
	 *
	 */
	public function validate(BBCodeInterface &$bbc, array|string &$data, array $disabled, array $params): void
	{
		if (\is_string($this->validation_callback)) {
			$this->validation_callback = Utils::getCallable($this->validation_callback);
		}

		if (\is_callable($this->validation_callback)) {
			\call_user_func_array($this->validation_callback, [&$bbc, &$data, $disabled, $params]);
		}
	}
}
