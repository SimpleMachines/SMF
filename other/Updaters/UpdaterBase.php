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

	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * The name of a new Git branch to hold the changes.
	 */
	public string $new_branch;

	/**************************
	 * Public static properties
	 **************************/

	/**
	 * @var object
	 *
	 * Autoloader instance.
	 */
	public static object $loader;

	/****************************
	 * Internal static properties
	 ****************************/

	/**
	 * @var bool
	 *
	 * Whether we've already checked for a dirty working tree.
	 */
	private static bool $checked_working_tree = false;

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
		if (!self::$checked_working_tree && shell_exec('git status --porcelain') !== null) {
			throw new \Exception('Could not continue. Dirty working tree.');
		}

		self::$checked_working_tree = true;

		// Impersonate cron.php
		if (!\defined('SMF')) {
			\define('SMF', 'BACKGROUND');
		}

		if (!\defined('SMF_USER_AGENT')) {
			\define('SMF_USER_AGENT', 'SMF');
		}

		if (!\defined('TIME_START')) {
			\define('TIME_START', microtime(true));
		}

		// Set variables, load classes, etc.
		if (!isset(self::$loader)) {
			// Set a few variables that we'll need.
			$boarddir = trim((string) shell_exec('git rev-parse --show-toplevel'));
			$sourcedir = $boarddir . '/Sources';
			$vendordir = $boarddir . '/vendor';

			// Borrow a bit of stuff from index.php.
			$index_php_start = file_get_contents($boarddir . '/index.php', false, null, 0, 4096);

			foreach (['SMF_VERSION', 'SMF_SOFTWARE_YEAR'] as $const) {
				if (\defined($const)) {
					continue;
				}

				preg_match("/define\('{$const}', '([^)]+)'\);/", $index_php_start, $matches);

				if (empty($matches[1])) {
					throw new \Exception("Could not find value for {$const} in index.php");
				}

				\define($const, $matches[1]);
			}

			// Fire up the autoloader.
			self::$loader = require_once $vendordir . '/autoload.php';
			self::$loader->setPsr4('SMF\\', $sourcedir);
			self::$loader->setPsr4('SMF\\other\\', $boarddir . '/other');

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

		// Make sure we are working in the right directory.
		chdir(Config::$boarddir);
	}

	/**
	 * Checks out a new Git branch to hold our changes.
	 *
	 * This should always be called from within a concrete class's execute
	 * method.
	 */
	public function checkoutNewBranch(): void
	{
		// Are we already on the new branch?
		if (trim(shell_exec('git rev-parse --abbrev-ref HEAD')) === $this->new_branch) {
			return;
		}

		// Does the new branch already exist?
		exec('git for-each-ref --format "%(refname:short)" refs/heads/', $branches);

		if (\in_array($this->new_branch, $branches)) {
			@shell_exec('git checkout "' . $this->new_branch . '"');

			if (trim(shell_exec('git rev-parse --abbrev-ref HEAD')) === $this->new_branch) {
				return;
			}
		}

		// Need to create the new branch at this point, so first switch to the main branch.
		if (trim(shell_exec('git rev-parse --abbrev-ref HEAD')) !== self::MAIN_BRANCH) {
			@shell_exec('git checkout "' . self::MAIN_BRANCH . '"');

			if (trim(shell_exec('git rev-parse --abbrev-ref HEAD')) !== self::MAIN_BRANCH) {
				throw new \Exception('Could not continue. Wrong branch is checked out.');
			}
		}

		// Now create the new branch.
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
		return (bool) (shell_exec('git status --porcelain') !== null);
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
