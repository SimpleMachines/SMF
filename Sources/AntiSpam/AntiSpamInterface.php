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

interface AntiSpamInterface
{
	/****************
	 * Public methods
	 ****************/

	/**
	 * Checks if the agent has been configured for usage.
	 *
	 * @return bool True if the agent is configured, false otherwise.
	 */
	public function isConfigured(): bool;

	/**
	 * Register the agent, setup and prepares for a form to be submitted.
	 * If false is returned, the agent is skipped.
	 *
	 * @param ?array $options Additional options to provide
	 * @return bool True if the agent is configured, false otherwise.
	 */
	public function create(?array $options = []): bool;

	/**
	 * Html to provide to the template.
	 */
	public function html(): void;

	/**
	 * Validates the agent.
	 * If false is returned, verification failed.
	 * If true is returned, verification passed.
	 * Null indicates that this was skipped.
	 *
	 * @param ?array $options Additional options to provide
	 * @return bool|array True if the agent is configured, false otherwise.
	 */
	public function validate(?array $options = []): array|bool;

	/**
	 * Reload the verification.
	 *
	 * @param bool $only_if_necessary If true, only refresh if we absolutely must.
	 * @param bool $do_test Whether we are checking their answers.
	 * @return bool True if we refreshed.
	 */
	public function refresh(bool $only_if_necessary, bool $do_test): bool;

	/**
	 * Gets the class identifier of the current agent implementation.
	 *
	 * @return string the unique identifier for the current class implementation.
	 */
	public function getImplementationClassKeyName(): string;

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Provides additional settings for the settings page.
	 *
	 * @param array $config_vars Current configuration settings, passed by reference.  Append to add more.
	 */
	public static function getConfigVars(array &$config_vars): void;
}
