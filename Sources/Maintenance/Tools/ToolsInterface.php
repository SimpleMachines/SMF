<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2025 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 4
 */

declare(strict_types=1);

namespace SMF\Maintenance\Tools;

use SMF\Maintenance\Step;

/**
 * Tools Interface, all tools have these methods.
 */
interface ToolsInterface
{
	/****************
	 * Public methods
	 ****************/

	/**
	 * Get the localized script name.
	 *
	 * Example: "SMF Upgrade Utility"
	 *
	 * @return string Localized name of the script.
	 */
	public function getScriptName(): string;

	/**
	 * Page title for the tool.
	 *
	 * The tool may override and just change.
	 *
	 * @return string The title for the page.
	 */
	public function getPageTitle(): string;

	/**
	 * If a tool does not contain steps, this should be false, true otherwise.
	 *
	 * @return bool Whether or not a tool has steps.
	 */
	public function hasSteps(): bool;

	/**
	 * The steps for a tool.
	 *
	 * If a tool does not have steps, it should return an empty array.
	 *
	 * @return \SMF\Maintenance\Step[]
	 */
	public function getSteps(): array;

	/**
	 * Sets $this->current_step.
	 *
	 * Used to keep track of which step is being performed.
	 *
	 * @return ?Step The current step or null if no step is being performed.
	 */
	public function setStep(?Step $step = null): void;

	/**
	 * Gets $this->current_step.
	 *
	 * Used to keep track of which step is being performed.
	 *
	 * @return ?Step The value of $this->current_step.
	 */
	public function getStep(): ?Step;

	/**
	 * Gets the title for the step we are performing.
	 *
	 * @return ?string
	 */
	public function getStepTitle(): ?string;

	/**
	 * Used by various places to determine if the tool is in debug mode or not.
	 *
	 * @return bool
	 */
	public function isDebug(): bool;

	/**
	 * Updates the tool's log file with new info.
	 *
	 * @param mixed $message The message to append to the log.
	 *    If not a string, will be converted into one using print_r().
	 * @param bool $ongoing Whether this message indicates an incomplete action.
	 *    Default: false.
	 * @param bool $reset If true, wipes out the old contents of the log file.
	 *    Default: false.
	 */
	public function logProgress(mixed $message, bool $ongoing = false, bool $reset = false): void;

	/**
	 * Last chance to do anything before we exit.
	 *
	 * Some tools may call this to save their progress, etc.
	 */
	public function preExit(): void;

	/**
	 * Checks whether we can the tool's script file.
	 *
	 * @return bool
	 */
	public function canDeleteTool(): bool;

	/**
	 * Delete the tool's script file.
	 *
	 * This is typically called with a ?delete.
	 *
	 * No output is returned. Upon successful deletion, the browser is
	 * redirected to a blank file.
	 */
	public function deleteTool(): void;
}
