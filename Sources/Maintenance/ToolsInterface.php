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

declare(strict_types=1);

namespace SMF\Maintenance;

/**
 * Tools Interface, all tools have these methods.
 */
interface ToolsInterface
{
	/**
	 * Get the script name
	 *
	 * @return string Page Title
	 */
	public function getScriptName(): string;

	/**
	 * Page title for the tool.  The tool may override and just change.
	 *
	 * @return string
	 */
	public function getPageTitle(): string;

	/**
	 * If a tool does not contain steps, this should be false, true otherwise.
	 *
	 * @return bool Whether or not a tool has steps.
	 */
	public function hasSteps(): bool;

	/**
	 * The steps for a tool.  If a tool does not have steps, it should return an empty array.
	 *
	 * @return \SMF\Maintenance\Step[]
	 */
	public function getSteps(): array;

	/**
	 * Gets the title for the step we are performing
	 *
	 * @return string
	 */
	public function getStepTitle(): string;
}

?>