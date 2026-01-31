<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2026 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 4
 */

declare(strict_types=1);

namespace SMF\Maintenance;

/**
 * Used for substeps that don't have a dedicated class.
 */
class GenericSubStep implements SubStepInterface
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * Name of the substep.
	 */
	public string $name;

	/**
	 * @var array|string
	 *
	 * Optional callable to call in the isCandidate() method.
	 *
	 * If null, isCandidate() will return true.
	 */
	public array|string|null $test;

	/**
	 * @var array
	 *
	 * Arguments to pass to $this->test.
	 */
	public array $test_args = [];

	/**
	 * @var array|string
	 *
	 * A callable to call in the execute() method.
	 */
	public array|string $exec;

	/**
	 * @var array
	 *
	 * Arguments to pass to $this->exec.
	 */
	public array $exec_args = [];

	/****************
	 * Public methods
	 ****************/

	/**
	 * Constructor.
	 *
	 * @param string $name The name of this substep.
	 * @param array|string $exec A callable to call in the execute() method.
	 * @param array|string|null $test A callable to call in the isCandidate()
	 *    method. If null, isCandidate() will always return true. Default: null.
	 * @param array $exec_args Arguments to pass to $this->exec. Default: [].
	 * @param array $test_args Arguments to pass to $this->test. Default: [].
	 */
	public function __construct(
		string $name,
		array|string $exec,
		array|string|null $test = null,
		array $exec_args = [],
		array $test_args = [],
	) {
		$this->name = $name;
		$this->test = $test;
		$this->test_args = $test_args;
		$this->exec = $exec;
		$this->exec_args = $exec_args;
	}

	/**
	 * Checks if the substep should be performed or not.
	 *
	 * @return bool True if this substep needs to be run, false otherwise.
	 */
	public function isCandidate(): bool
	{
		return !\is_callable($this->test) ? true : \call_user_func($this->test, ...$this->test_args);
	}

	/**
	 * Runs the substep.
	 *
	 * @return bool True if successful (or skipped), false otherwise.
	 */
	public function execute(): bool
	{
		return !\is_callable($this->exec) ? false : \call_user_func($this->exec, ...$this->exec_args);
	}
}
