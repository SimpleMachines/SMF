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
use SMF\Lang;
use SMF\Utils;
use SMF\Uuid;

abstract class AntiSpam
{
	/*****************
	 * Class constants
	 *****************/

	// Force a refresh after this many failed attempts.
	// This helps prevent bots from solving via brute force attacks.
	public const MAX_ATTEMPTS = 3;

	/**
	 * @var int
	 *
	 *
	 */
	public const MAX_ERRORS = 3;

	/**
	 * @var string
	 *
	 * The directory containing our Agents we can use.
	 */
	public const APIS_FOLDER = __DIR__ . '/APIs';

	/**
	 * @var string
	 *
	 * The root namespace used by all our Agents.
	 */
	public const APIS_NAMESPACE = __NAMESPACE__ . '\\APIs\\';
	private const SESSION_SUFFIX = '_vv';

	/*********************
	 * Internal properties
	 *********************/

	protected string $form_id;

	protected Uuid $agent_id;

	/****************
	 * Public methods
	 ****************/

	public function __construct(string $form_id, Uuid $agent_id)
	{
		$this->form_id = $form_id;
		$this->agent_id = $agent_id;
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Create all the agents.
	 */
	final public static function create(string $form_id, ?array $options = []): array
	{
		$_SESSION[self::session($form_id)] ??= ['agents' => []];

		$data = [];

		foreach (self::parseConfiguredAgents() as $agent) {
			$agent_id = Uuid::create();
			$_SESSION[self::session($form_id)]['agents'][(string) $agent_id] = $agent;

			/**
			 * @var AntiSpamInterface|AntiSpam $agent_api
			 */
			$agent_api = self::loadAgent($agent, $form_id, $agent_id);

			if ($agent_api === null) {
				continue;
			}

			// Inform the agent we are requesting some protection.
			if ($agent_api->isConfigured() && $agent_api->create($options)) {
				$data[$agent] = [$agent_api, 'html'];
			}
		}

		return $data;
	}

	/**
	 * Validate all agents.
	 *
	 * @return array|true True if we passed, an array of errors if we failed.
	 */
	final public static function validate(string $form_id, ?array $options = []): array|bool
	{
		// Not in session, no verification will pass.
		if (empty($form_id) || !isset($_SESSION[self::session($form_id)], $_SESSION[self::session($form_id)]['agents'])) {
			return ['session_timeout'];
		}

		$errors = [];

		foreach ($_SESSION[self::session($form_id)]['agents'] as $agent_id => $agent) {
			$agent_id = Uuid::createFromString($agent_id);

			/**
			 * @var ?AntiSpamInterface|AntiSpamAgent $agent_api
			 */
			$agent_api = self::loadAgent($agent, $form_id, $agent_id);

			// Not in session, no verification will pass.
			if ($agent_api === null) {
				$errors[] = 'session_timeout';
				continue;
			}

			// Inform the agent we are asking to validate..
			if ($agent_api->isConfigured()) {
				$rt = $agent_api->validate($options);

				if ($rt !== true) {
					$errors = array_merge($errors, $rt);
				}
			}
		}

		if (empty($errors)) {
			// SMF 2.1 compatbility, why do we care?
			$_SESSION[self::session($form_id)]['did_pass'] = true;

			return true;
		}

		$_SESSION[self::session($form_id)]['errors'] ??= 0;
		$_SESSION[self::session($form_id)]['errors']++;

		// Ran out of tries, refresh things.
		if (self::MAX_ERRORS < $_SESSION[self::session($form_id)]['errors']) {
			self::refresh($form_id, false);
		}


		return $errors;
	}

	final public static function findCode(string $form_id, Uuid|string $agent_id): string
	{
		// Not in session, no verification will pass.
		if (empty($form_id) || !isset($_SESSION[self::session($form_id)])) {
			return '';
		}

		if (!empty($form_id) && \is_string($agent_id)) {
			$agent_id = Uuid::createFromString($agent_id);
		}

		// Not in session, no verification will pass.
		if (empty($agent_id) || !isset($_SESSION[self::session($form_id)]['agents'][$agent_id])) {
			return '';
		}


		$agent = $_SESSION[self::session($form_id)]['agents'][$agent_id];

		/**
		 * @var ?AntiSpamInterface|AntiSpamAgent $agent_api
		 */
		$agent_api = self::loadAgent($agent, $form_id, $agent_id);

		if ($agent_api !== null && $agent_api->isConfigured()) {
			return $agent_api->code();
		}

		return '';
	}

	final public static function showCode(string $form_id, Uuid|string $agent_id): void
	{
		// Not in session, no verification will pass.
		if (empty($form_id) || !isset($_SESSION[self::session($form_id)])) {
			return;
		}

		if (\is_string($agent_id)) {
			$agent_id = Uuid::createFromString($agent_id);
		}

		// Not in session, no verification will pass.
		if (empty($agent_id) || !isset($_SESSION[self::session($form_id)]['agents'][(string) $agent_id])) {
			return;
		}

		$agent = $_SESSION[self::session($form_id)]['agents'][(string) $agent_id];

		/**
		 * @var ?AntiSpamInterface|AntiSpamAgent $agent_api
		 */
		$agent_api = self::loadAgent($agent, $form_id, $agent_id);

		if ($agent_api !== null && $agent_api->isConfigured()) {
			$agent_api->showCode();
		}
	}

	final public static function setupTestCode(string $rand, string $agent): array
	{
		$api_classes = new \GlobIterator(self::APIS_FOLDER . '/*.php', \FilesystemIterator::NEW_CURRENT_AND_KEY);

		$found = false;

		foreach ($api_classes as $file_info) {
			if ($file_info->getBasename() !== 'index.php' && $file_info->getBasename('.php') === $agent) {
				$found = true;
				break;
			}
		}

		$form_id = 'admin';
		$agent_id = Uuid::create();
		$_SESSION[self::session($form_id)]['agents'][(string) $agent_id] = $agent;

		/**
		 * @var ?AntiSpamInterface|AntiSpamAgent $agent_api
		 */
		$agent_api = self::loadAgent($agent, $form_id, $agent_id);

		if ($agent_api !== null && $agent_api->isConfigured()) {
			// Call for the code, discard it to ensure we have generated a code.
			$agent_api->code();
		}

		return [$form_id, $agent_id];
	}

	/**
	 * Do we need to refresh this verification?
	 *
	 * @param bool $only_if_necessary If true, only refresh if we absolutely must.
	 * @param bool $do_test Whether we are checking their answers.
	 */
	final public static function refresh(string $form_id, bool $only_if_necessary = true, bool $do_test = false): void
	{
		$result = false;

		foreach ($_SESSION[self::session($form_id)]['agents'] as $agent_id => $agent) {
			$agent_id = Uuid::createFromString($agent_id);

			/**
			 * @var ?AntiSpamInterface|AntiSpamAgent $agent_api
			 */
			$agent_api = self::loadAgent($agent, $form_id, $agent_id);

			// Not in session, no verification will pass.
			if ($agentID === null) {
				continue;
			}

			// Inform the agent we are asking to validate.
			if ($agent_api->isConfigured()) {
				$result = $result || $agent_api->refresh($only_if_necessary, $do_test);
			}
		}

		// If we refreshed anything, reset it all.
		if ($result) {
			$_SESSION[self::session($form_id)]['count'] = 0;
			$_SESSION[self::session($form_id)]['errors'] = 0;
			$_SESSION[self::session($form_id)]['did_pass'] = false;
		}
	}

	final public static function getConfigVars(array &$config_vars, array &$agnets): void
	{
		$api_classes = new \GlobIterator(self::APIS_FOLDER . '/*.php', \FilesystemIterator::NEW_CURRENT_AND_KEY);

		foreach ($api_classes as $file_info) {
			if ($file_info->getBasename() === 'index.php') {
				continue;
			}
			$agent = $file_info->getBasename('.php');
			$fully_qualified_class_name = self::APIS_NAMESPACE . $agent;

			if (!class_exists($fully_qualified_class_name)) {
				continue;
			}

			/*
			 * @var ?AntiSpamInterface|AntiSpamAgent $fully_qualified_class_name
			 */
			$fully_qualified_class_name::getConfigVars(config_vars: $config_vars);
			$agnets[$agent] = Lang::txtExists('antispam_agent_' . $agent) ? Lang::getTxt('antispam_agent_' . $agent) : $agent;
		}
	}

	final public static function saveConfigVars(): void
	{
		$api_classes = new \GlobIterator(self::APIS_FOLDER . '/*.php', \FilesystemIterator::NEW_CURRENT_AND_KEY);

		foreach ($api_classes as $file_info) {
			if ($file_info->getBasename() === 'index.php') {
				continue;
			}
			$agent = $file_info->getBasename('.php');
			$fully_qualified_class_name = self::APIS_NAMESPACE . $agent;

			if (!class_exists($fully_qualified_class_name)) {
				continue;
			}

			/*
			 * @var ?AntiSpamInterface|AntiSpamAgent $fully_qualified_class_name
			 */
			$fully_qualified_class_name::saveConfigVars();
		}
	}

	/*************************
	 * Internal static methods
	 *************************/

	protected static function session(string $id): string
	{
		return $id . self::SESSION_SUFFIX;
	}

	private static function parseConfiguredAgents(): array
	{
		return Utils::jsonDecode(Config::$modSettings['antispam_agents'] ?? '') ?? [];
	}

	private static function loadAgent(string $agent, $id, $agent_id): ?AntiSpamInterface
	{
		$fully_qualified_class_name = self::APIS_NAMESPACE . $agent;

		if (!class_exists($fully_qualified_class_name)) {
			return null;
		}

		/**
		 * @var AntiSpamInterface|AntiSpamAgent $fully_qualified_class_name
		 * @var AntiSpamInterface|AntiSpamAgent $agent_api
		 */
		$agent_api = new $fully_qualified_class_name($id, $agent_id);

		// Has to be a valid agent.
		if (!($agent_api instanceof AntiSpamInterface) || !($agent_api instanceof AntiSpamAgent)) {
			return null;
		}

		return $agent_api;
	}
}
