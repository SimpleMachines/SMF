<?php

/**
 * This is an internal development file. It should NOT be included in
 * any SMF distribution packages.
 *
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

namespace SMF\other\Updaters;

use SMF\Config;
use SMF\Lang;
use SMF\Uuid;

/**
 * Class UpdaterBase
 */
abstract class UpdaterBase
{
	/*****************
	 * Class constants
	 *****************/

	/**
	 * @var string
	 *
	 * The name of the main branch in Git.
	 */
	public const MAIN_BRANCH = 'release-3.0';

	/**
	 * @var string
	 *
	 * The name of a new Git branch to hold the changes.
	 */
	public string $new_branch;

	/****************
	 * Public methods
	 ****************/

	/**
	 * Constructor.
	 *
	 * @param string $new_branch Name of a new Git branch to hold the changes.
	 */
	public function __construct(string $new_branch)
	{
		// Set the name of our new Git branch.
		$this->new_branch = $new_branch;

		// Make sure we have access to the Git CLI tool.
		if (!\is_callable('exec')) {
			throw new \Exception('exec function is not callable');
		}

		if (!\is_callable('shell_exec')) {
			throw new \Exception('shell_exec function is not callable');
		}

		exec(PHP_OS_FAMILY === 'Windows' ? 'WHERE git' : 'which git', $output, $result_code);

		if ($result_code != 0) {
			throw new \Exception('Cannot call git via CLI');
		}

		// Do nothing if working tree is dirty.
		if (shell_exec('git status --porcelain') !== null) {
			throw new \Exception('Could not continue. Dirty working tree.');
		}

		// Set a few variables that we'll need.
		$boarddir = trim((string) shell_exec('git rev-parse --show-toplevel'));
		$sourcedir = $boarddir . '/Sources';
		$vendordir = $boarddir . '/vendor';

		// Make sure we are working in the right directory.
		chdir($boarddir);

		// Impersonate cron.php
		\define('SMF', 'BACKGROUND');
		\define('SMF_USER_AGENT', 'SMF');
		\define('TIME_START', microtime(true));

		// Borrow a bit of stuff from index.php.
		$index_php_start = file_get_contents($boarddir . '/index.php', false, null, 0, 4096);

		foreach (['SMF_VERSION', 'SMF_SOFTWARE_YEAR'] as $const) {
			preg_match("/define\('{$const}', '([^)]+)'\);/", $index_php_start, $matches);

			if (empty($matches[1])) {
				throw new \Exception("Could not find value for {$const} in index.php");
			}

			\define($const, $matches[1]);
		}

		// Fire up the autoloader.
		$loader = require_once $vendordir . '/autoload.php';
		$loader->setPsr4('SMF\\', $sourcedir);
		$loader->setPsr4('SMF\\other\\', $boarddir . '/other');

		// Set some more stuff we need.
		Config::$boarddir = $boarddir;
		Config::$sourcedir = $sourcedir;
		Config::$vendordir = $vendordir;
		Config::$languagesdir = Config::$boarddir . '/Languages';
		Config::$language = 'en_US';
		Config::$backward_compatibility = 0;
		Config::$scripturl = 'file:///foo/bar/index.php';
		Config::$modSettings = [
			'default_timezone' => 'UTC',
			'forum_uuid' => (string) Uuid::getNamespace(),
		];

		Lang::$default = Config::$language;
		Lang::addDirs(Config::$languagesdir);
	}

	/**
	 * Creates a new Git branch to hold our changes.
	 *
	 * This should always be called from within a concrete class's execute
	 * method.
	 */
	public function createBranch(): void
	{
		$current_branch = trim(shell_exec('git rev-parse --abbrev-ref HEAD'));

		if ($current_branch === $this->new_branch) {
			return;
		}

		if ($current_branch !== self::MAIN_BRANCH) {
			@shell_exec('git checkout "' . self::MAIN_BRANCH . '"');
			$current_branch = trim(shell_exec('git rev-parse --abbrev-ref HEAD'));
		}

		if ($current_branch !== self::MAIN_BRANCH) {
			throw new \Exception('Could not continue. Wrong branch is checked out.');
		}

		shell_exec('git checkout -b "' . $this->new_branch . '"');

		if (trim(shell_exec('git rev-parse --abbrev-ref HEAD')) !== $this->new_branch) {
			throw new \Exception('Failed to create branch "' . $this->new_branch . '"');
		}
	}

	/**
	 * Returns whether changes have been made.
	 */
	public function hasChanged(): bool
	{
		// Are there any committed changes?
		$new_branch_hash = trim(shell_exec('git rev-parse "' . $this->new_branch . '"'));
		$main_branch_hash = trim(shell_exec('git rev-parse "' . self::MAIN_BRANCH . '"'));

		if ($new_branch_hash !== $main_branch_hash) {
			return true;
		}

		// Are we currently on the new branch?
		$current_branch = trim(shell_exec('git rev-parse --abbrev-ref HEAD'));

		if ($current_branch !== $this->new_branch) {
			throw new \Exception('Could not continue. Wrong branch checked out.');
		}

		// Are there any uncommitted changes?
		if (shell_exec('git status --porcelain') !== null) {
			return true;
		}

		return false;
	}

	/**
	 * If no changes were made, removes the unnecessary Git branch we made before.
	 */
	public function removeUselessBranch(): void
	{
		// Never delete the main branch!
		if ($this->new_branch === self::MAIN_BRANCH) {
			return;
		}

		// Are we currently on the new branch?
		if (trim(shell_exec('git rev-parse --abbrev-ref HEAD')) !== $this->new_branch) {
			return;
		}

		if (!$this->hasChanged()) {
			shell_exec('git checkout "' . self::MAIN_BRANCH . '"');
			shell_exec('git branch -d "' . $this->new_branch . '"');
		}
	}
}
