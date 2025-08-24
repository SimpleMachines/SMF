<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2025 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 3
 */

declare(strict_types=1);

namespace SMF\AntiSpam;

use SMF\ArrayAccessHelper;
use SMF\IntegrationHook;
use SMF\Theme;
use SMF\Utils;

/**
 * Sets up the anti-spam control that tries to verify the user's humanity.
 *
 * Supports old-fashioned CAPTCHA, reCAPTCHA, and verification questions.
 */
class Verification implements \ArrayAccess
{
	use ArrayAccessHelper;

	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * This editor's ID string.
	 */
	public string $id;

	/**
	 * @var array
	 *
	 * Error messages about any problems encountered during setup.
	 */
	public array $errors = [];

	/**
	 * @var bool
	 *
	 * Whether there is anything to show.
	 * Assume true until proven otherwise.
	 */
	public bool $result = true;

	/**************************
	 * Public static properties
	 **************************/

	/**
	 * @var array
	 *
	 * All loaded instances of this class.
	 */
	public static array $loaded = [];

	/****************
	 * Public methods
	 ****************/

	/**
	 * Constructor.
	 *
	 * @todo BEFORE COMMITTING: Can't return errors from constructor.
	 *
	 * @param array $options Options for the verification control.
	 * @param bool $do_test Whether to check to see if the user entered the code correctly.
	 */
	public function __construct(array $options, bool $do_test = false)
	{
		// Add a verification hook, pre-setup.
		IntegrationHook::call('integrate_create_control_verification_pre', [&$options, $do_test]);

		// Always need an ID. If someone forgot to provide it, fall back to the
		// current action (but trim off any trailing '2' in the action name).
		$this->id = $options['id'] ?? rtrim(($_REQUEST['action'] ?? 'post'), '2');

		$this->init();

		// Testing.
		if ($do_test) {
			$results = AntiSpam::validate($this->id, $options);

			// Do ay hooks have something to say about this verification?
			IntegrationHook::call('integrate_create_control_verification_test', [$this, &$this->errors]);

			if ($results === true) {
				$this->result = true;
			} else {
				$this->errors = $results;

				// Hooks may need to know about this.
				IntegrationHook::call('integrate_create_control_verification_refresh', [$this]);
			}
		}

		// Let our hooks know that we are done with the verification process.
		IntegrationHook::call('integrate_create_control_verification_post', [&$this->errors, $do_test]);

		// Return errors if we have them.
		if (!empty($this->errors)) {
			// Backward compatibility.
			Utils::$context['require_verification'] = $this->errors;
			Utils::$context['visual_verification'] = $this->result;
			Utils::$context['visual_verification_id'] = $this->id;

			$this->result = true;
		} else {
			// Say that everything went well, chaps.
			$this->result = true;
		}

		// Setup a new one.
		self::$loaded[$this->id] = AntiSpam::create($this->id, $options);

		if (empty($this->errors)) {
			Utils::$context['require_verification'] = $this->result;
			Utils::$context['visual_verification'] = $this->result;
			Utils::$context['visual_verification_id'] = $this->id;
		}
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Static wrapper for constructor that returns result (or error indicators).
	 *
	 * @param array &$options Options for the verification control.
	 * @param bool $do_test Whether to check to see if the user entered the code correctly.
	 * @return bool|array False if there's nothing to show, true if everything went well, or an array containing error indicators if the test failed.
	 */
	public static function create(array &$options, bool $do_test = false): bool|array
	{
		$obj = new self($options, $do_test);

		foreach ($options as $key => $value) {
			$options[$key] = $obj->$key;
		}

		return !empty($obj->errors) ? $obj->errors : $obj->result;
	}

	/**
	 * Static wrapper for constructor that returns result (or error indicators).
	 *
	 * @param array &$options Options for the verification control.
	 * @return bool|array False if there's nothing to show, true if everything went well, or an array containing error indicators if the test failed.
	 */
	public static function verify(array &$options): bool|array
	{
		$obj = new self($options, true);

		foreach ($options as $key => $value) {
			$options[$key] = $obj->$key;
		}

		return !empty($obj->errors) ? $obj->errors : $obj->result;
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Initializes some required template stuff.
	 */
	protected function init(): void
	{
		// The template
		Theme::loadTemplate('GenericControls');

		// Backward compatibility.
		Utils::$context['controls']['verification'] = &self::$loaded;
	}
}
