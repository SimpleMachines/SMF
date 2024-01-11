<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2023 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 1
 */

namespace SMF\MailAgent;

use SMF\BackwardCompatibility;

use SMF\Config;
use SMF\ErrorHandler;
use SMF\IntegrationHook;
use SMF\Utils;

abstract class MailAgent
{
	/**
	 * @var string
	 *
	 * The directory containing our Agents we can use.
	 */
	const APIS_FOLDER = __DIR__ . '/APIs';

	/**
	 * @var string
	 *
	 * The root namespace used by all our Agents.
	 */
	const APIS_NAMESPACE = __NAMESPACE__ . '\\APIs\\';

	/**
	 * @var string
	 *
	 * Default Agent to use or to fallback to if the agent selected is not supported or configured correctly.
	 */
	const APIS_DEFAULT = 'SendMail';

	/**************************
	 * Public static properties
	 **************************/

	/**
	 * @var string
	 *
	 * Name of the selected agent.
	 *
	 * This is an copy of the $modSettings['mail_type'].
	 */
	public static string $agent;

	/**
	 * @var object|bool
	 *
	 * The loaded agent, or false on failure.
	 */
	public static $loaded_api;

	/**********************
	 * Protected properties
	 **********************/

	/**
	 * @var string The maximum SMF version that this will work with.
	 */
	protected $version_compatible = '3.0.999';

	/**
	 * @var string The minimum SMF version that this will work with.
	 */
	protected $min_smf_version = '3.0 Alpha 1';

	/****************
	 * Public methods
	 ****************/

	/**
	 * Checks if the requirements for the agent are available.
	 *
	 * @access public
	 * @return bool True if the agent is supported, false otherwise.
	 */
	public function isSupported(): bool
	{
		return true;
	}

	/**
	 * Checks if the agent has been configured for usage.
	 *
	 * @access public
	 * @return bool True if the agent is configured, false otherwise.
	 */
	public function isConfigured(): bool
	{
		return true;
	}

	/**
	 * Connects to the mail agent.  Not all agents will need to connect.
	 *
	 * @access public
	 * @return bool Whether or not the agent method was connected to.
	 */
	public function connect(): bool
	{
		return true;
	}

	/**
	 * Sends the email via the agent
	 *
	 * @access public
	 * @param string $to
	 * @param string $subject
	 * @param string $message Message should be formatted with html/plain text.
	 * @param array $headers Any additional headers.
	 */
	public function send(string $to, string $subject, string $message, string $headers): bool
	{
		return false;
	}

	/**
	 * Disconnects to the mail agent.  Not all agents will need to disconnect.
	 *
	 * @access public
	 * @return bool Whether or not the agent method was connected to.
	 */
	public function disconnect(): bool
	{
		return true;
	}

	/**
	 * Specify custom settings that the agent supports.
	 *
	 * @access public
	 * @param array $config_vars Additional config_vars, see ManageSettings.php for usage.
	 */
	public function agentSettings(array &$config_vars): void
	{
	}

	/**
	 * Is our SMF version supported with this Agent.
	 *
	 * @access public
	 * @param string $smfVersion
	 * @return string the value of $key.
	 */
	public function isCompatible(string $smfVersion): bool
	{
		return version_compare($this->min_smf_version, $smfVersion, '<=') && version_compare($smfVersion, $this->version_compatible, '>=');
	}

	/**
	 * Gets the min version that we support.
	 *
	 * @access public
	 * @return string the value of $key.
	 */
	public function getMinimumVersion(): string
	{
		return $this->min_smf_version;
	}

	/**
	 * Gets the Version of the Caching API.
	 *
	 * @access public
	 * @return string the value of $key.
	 */
	public function getVersion(): string
	{
		return $this->min_smf_version;
	}

	/**
	 * Gets the class identifier of the current agent implementation.
	 *
	 * @access public
	 * @return string the unique identifier for the current class implementation.
	 */
	public function getImplementationClassKeyName(): string
	{
		$class_name = get_class($this);

		if ($position = strrpos($class_name, '\\'))
			return substr($class_name, $position + 1);

		return $class_name;
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Try to load up a supported agent method.
	 * This is saved in $loaded_api if we are not overriding it.
	 *
	 * @todo Add a reference to Utils::$context['instances'] as well?
	 *
	 * @param bool $loadDefault Use the default SMF method if the selected agent fails.
	 * @return object|false An instance of a child class of this class, or false on failure.
	 */
	final public static function load(bool $loadDefault = false)
	{
		if (!$loadDefault && !isset(self::$agent))
		{
			self::$agent = empty(Config::$modSettings['mail_type']) || Config::$modSettings['smtp_host'] == '' ? 'SendMail' : Config::$modSettings['mail_type'];

			// Handle some other options.
			if (self::$agent === '1')
			{
				self::$agent = 'SMTP';
			}
			elseif (self::$agent === '2')
			{
				self::$agent = 'SMTPTLS';
			}
		}

		if (is_object(self::$loaded_api))
			return self::$loaded_api;

		if (is_null(self::$loaded_api))
			self::$loaded_api = false;

		// What agent we are going to try.
		$agent_class_name = !empty(self::$agent) ? self::$agent : self::APIS_DEFAULT;
		$fully_qualified_class_name = self::APIS_NAMESPACE . $agent_class_name;

		// Do some basic tests.
		$agent_api = false;
		if (class_exists($fully_qualified_class_name))
		{
			$agent_api = new $fully_qualified_class_name();

			// There are rules you know...
			if (!($agent_api instanceof MailAgentInterface) || !($agent_api instanceof MailAgent))
				$agent_api = false;

			// No Support?  NEXT!
			if ($agent_api && !$agent_api->isSupported() && !$agent_api->isConfigured())
			{
				// Can we save ourselves?
				if ($agent_class_name !== self::APIS_DEFAULT)
					return self::load(true);

				$agent_api = false;
			}

			// Connect up to the agent.
			if ($agent_api && $agent_api->connect() === false)
				$agent_api = false;
		}

		if (!$agent_api && $agent_class_name !== self::APIS_DEFAULT)
			$agent_api = self::load(self::APIS_NAMESPACE . self::APIS_DEFAULT, false);

		return $agent_api;
	}

	/**
	 * Get the installed Mail Agent implementations.
	 */
	final public static function detect()
	{
		$loaded_apis = array();

		$api_classes = new \GlobIterator(self::APIS_FOLDER . '/*.php', \FilesystemIterator::NEW_CURRENT_AND_KEY);

		foreach ($api_classes as $file_path => $file_info)
		{
			$class_name = $file_info->getBasename('.php');
			$fully_qualified_class_name = self::APIS_NAMESPACE . $class_name;

			if (!class_exists($fully_qualified_class_name))
				continue;

			/* @var MailAgentInterface $agent_api */
			$agent_api = new $fully_qualified_class_name();

			// Deal with it!
			if (!($agent_api instanceof MailAgentInterface) || !($agent_api instanceof MailAgent))
				continue;

			// No Support?  NEXT!
			if (!$agent_api->isSupported(true))
				continue;

			$loaded_apis[$class_name] = $agent_api;
		}

		IntegrationHook::call('integrate_load_mail_agents', array(&$loaded_apis));

		return $loaded_apis;
	}
}

?>