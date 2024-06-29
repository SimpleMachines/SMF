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
 * Step container for a maintenance task.
 */
class Step
{
	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var int
	 *
	 * ID of the step.
	 * Typically this is one higher than the ID found in the array.
	 */
	private int $id;

	/**
	 * @var string
	 *
	 * Name of the step.
	 * If we are showing steps, this will be displayed in the step list.
	 */
	private string $name;

	/**
	 * @var null|string
	 *
	 * The page title to display for this step.
	 * If null, we will fall back to the value of $name.
	 */
	private ?string $title = null;

	/**
	 * @var string
	 *
	 * Function to call.
	 * This is actually the method inside the tool and must be public.
	 */
	private string $function;

	/**
	 * @var int
	 *
	 * The amount of progress to be made when this step completes.
	 */
	private int $progress;

	/****************
	 * Public methods
	 ****************/

	/**
	 * Constructor.
	 *
	 * @param int $id ID of the step.
	 * @param string $name Name of the step.
	 * @param string $function Function to call.
	 * @param int $progress The amount of progress to be made when this step completes.
	 * @param ?string $title The page title we will display for this step.
	 *    If null, defaults to $name.
	 */
	public function __construct(int $id, string $name, string $function, int $progress, ?string $title = null)
	{
		$this->id = $id;
		$this->name = $name;
		$this->title = $title;
		$this->function = $function;
		$this->progress = $progress;
	}

	/**
	 * Fetches the ID of this step.
	 *
	 * @return int ID of the step. Typically this is one higher than the ID
	 *    found in the array.
	 */
	public function getID(): int
	{
		return $this->id;
	}

	/**
	 * Fetches the name of this step.
	 *
	 * @return string Name of the step. If we are showing steps, this will be
	 *    displayed in the step list.
	 */
	public function getName(): string
	{
		return $this->name;
	}

	/**
	 * Fetches the title of this step.
	 *
	 * @see $title
	 * @return string The page title to display for this step.
	 */
	public function getTitle(): string
	{
		return $this->title ?? $this->name;
	}

	/**
	 * Fetches the function called by this step.
	 *
	 * @return string Function to call. This is actually the method inside the
	 *    tool and must be public.
	 */
	public function getFunction(): string
	{
		return $this->function;
	}

	/**
	 * Fetches the progress value of this step.
	 *
	 * @return int The amount of progress to be made when this step completes.
	 */
	public function getProgress(): int
	{
		return $this->progress;
	}
}

?>