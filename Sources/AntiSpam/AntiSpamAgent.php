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

namespace SMF\AntiSpam;

use SMF\Config;
use SMF\Uuid;

abstract class AntiSpamAgent
{
	/*****************
	 * Class constants
	 *****************/

	public const BLANK_IMAGE = "\x47\x49\x46\x38\x39\x61\x01\x00\x01\x00\x80\x00\x00\x00\x00\x00\x00\x00\x00\x21\xF9\x04\x01\x00\x00\x00\x00\x2C\x00\x00\x00\x00\x01\x00\x01\x00\x00\x02\x02\x44\x01\x00\x3B";

	// Force a refresh after this many failed attempts.
	// This helps prevent bots from solving via brute force attacks.
	public const MAX_ATTEMPTS = 3;
	protected const SESSION_SUFFIX = 'vv_';

	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var int
	 *
	 *
	 */
	public int $max_errors;

	/**************************
	 * Public static properties
	 **************************/

	/**
	 * @var array
	 *
	 * All loaded instances of this class.
	 */
	public static array $loaded = [];

	/*********************
	 * Internal properties
	 *********************/

	protected string $form_id;

	protected Uuid $agent_id;

	/****************
	 * Public methods
	 ****************/

	/**
	 * Checks if the agent has been configured for usage.
	 *
	 * @return bool True if the agent is configured, false otherwise.
	 */
	public function isConfigured(): bool
	{
		return true;
	}

	/**
	 * Gets the class identifier of the current agent implementation.
	 *
	 * @return string the unique identifier for the current class implementation.
	 */
	public function getImplementationClassKeyName(): string
	{
		$class_name = \get_class($this);

		if ($position = strrpos($class_name, '\\')) {
			return substr($class_name, $position + 1);
		}

		return $class_name;
	}

	/**
	 * Provide a code, such as a image, or sound.
	 *
	 * @param Uuid $uuid Unique ID to be provided to the form.
	 */
	public function showCode(): void {}

	/**
	 * Provide a code, such as a image, or sound.
	 *
	 * @param Uuid $uuid Unique ID to be provided to the form.
	 */
	public function code(): string
	{
		return '';
	}

	public function __construct(string $form_id, Uuid $agent_id)
	{
		$this->form_id = $form_id;
		$this->agent_id = $agent_id;
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Specify custom settings that the agent supports.
	 *
	 * @param array $config_vars Additional config_vars, see ManageSettings.php for usage.
	 */
	public static function getConfigVars(array &$config_vars): void {}

	/**
	 * Specify custom settings that the agent supports.
	 *
	 * @param array $config_vars Additional config_vars, see ManageSettings.php for usage.
	 */
	public static function saveConfigVars(): void {}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Do we need to refresh this verification?
	 *
	 * @param bool $only_if_necessary If true, only refresh if we absolutely must.
	 * @param bool $do_test Whether we are checking their answers.
	 * @return bool Whether we should refresh this verification.
	 */
	protected function shouldRefresh(bool $only_if_necessary, bool $do_test): bool
	{
		// This means:
		// 1. If we weren't asked to avoid refreshing, refresh.
		// 2. If we didn't check their answers, refresh.
		// 3. If they previously passed a verification in this session (which
		//    means they need a new one), or haven't tried yet, or tried too
		//    many times, refresh.
		$should_refresh = !$only_if_necessary && !$do_test && (!empty($_SESSION[$this->sessionID()]['did_pass']) || empty($_SESSION[$this->sessionID()]['count']) || $_SESSION[$this->sessionID()]['count'] > self::MAX_ATTEMPTS);

		// Any errors means we refresh potentially.
		if (!empty($this->errors)) {
			if (empty($_SESSION[$this->sessionID()]['errors'])) {
				$_SESSION[$this->sessionID()]['errors'] = 0;
			}
			// Too many errors?
			elseif ($_SESSION[$this->sessionID()]['errors'] > $this->max_errors) {
				$should_refresh = true;
			}

			// Keep track of these.
			$_SESSION[$this->sessionID()]['errors']++;
		}

		return $should_refresh;
	}

	protected function sessionID(): string
	{
		return (string) $this->form_id . self::SESSION_SUFFIX;
	}
}
