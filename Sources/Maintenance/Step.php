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
	/**
	 * @var int
	 *
	 * ID of the step.  Typically this is one higher than the ID found in the array.
	 */
	private int $id;

	/**
	 * @var string
	 *
	 * Name of the step.  If we are showing steps, this will be displayed in the step list.
	 */
	private string $name;

	/**
	 * @var null|string
	 *
	 * The Page title we will display for this step.  If null, we will fall back to the $name or default for the maintenance task.
	 */
	private ?string $title = null;

	/**
	 * @var string
	 *
	 * Function to call.  This is actually the method inside the tool and must be public.
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


	public function __construct(int $id, string $name, string $function, int $progress, ?string $title = null)
	{
		$this->id = $id;
		$this->name = $name;
		$this->title = $title;
		$this->function = $function;
		$this->progress = $progress;
	}

	/**
	 * Fetches the ID of the Step
	 *
	 * @return int ID of the step.  Typically this is one higher than the ID found in the array.
	 */
	public function getID(): int
	{
		return $this->id;
	}

	/**
	 * Fetch the Name of the Step
	 *
	 * @return string Name of the step.  If we are showing steps, this will be displayed in the step list.
	 */
	public function getName(): string
	{
		return $this->name;
	}

	/**
	 * Fetch the title.
	 *
	 * @see $title
	 * @return null|string The Page title we will display for this step.  If null, we will fall back to the $name or default for the maintenance task.
	 */
	public function getTitle(): ?string
	{
		return $this->title;
	}

	/**
	 * Fetch the function.
	 *
	 * @return string Function to call.  This is actually the method inside the tool and must be public.
	 */
	public function getFunction(): string
	{
		return $this->function;
	}

	/**
	 * Fetch the progress.
	 *
	 * @return int The amount of progress to be made when this step completes.
	 */
	public function getProgress(): int
	{
		return $this->progress;
	}
}

?>