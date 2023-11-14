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

if (!defined('SMF'))
	die('No direct access...');


interface MailAgentInterface
{
	/**
	 * Checks if the requirements for the agent are available.
	 *
	 * @access public
	 * @return bool True if the agent is supported, false otherwise.
	 */
	public function isSupported(): bool;

	/**
	 * Checks if the agent has been configured for usage.
	 *
	 * @access public
	 * @return bool True if the agent is configured, false otherwise.
	 */
	public function isConfigured(): bool;

	/**
	 * Connects to the mail agent.  Not all agents will need to connect.
	 *
	 * @access public
	 * @return bool Whether or not the agent method was connected to.
	 */
	public function connect(): bool;

	/**
	 * Sends the email via the agent
	 *
	 * @access public
	 * @param string $to
	 * @param string $subject
	 * @param string $message Message should be formatted with html/plain text.
	 * @param array $headers Any additional headers.
	 */
	public function send(string $to, string $subject, string $message, string $headers): bool;

	/**
	 * Disconnects to the mail agent.  Not all agents will need to disconnect.
	 *
	 * @access public
	 * @return bool Whether or not the agent method was connected to.
	 */
	public function disconnect(): bool;

	/**
	 * Gets the Version of the Agent.
	 *
	 * @access public
	 * @return string the value of $key.
	 */
	public function getVersion(): string;

	/**
	 * Gets the class identifier of the current agent implementation.
	 *
	 * @access public
	 * @return string the unique identifier for the current class implementation.
	 */
	public function getImplementationClassKeyName(): string;
}

?>