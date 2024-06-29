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

namespace SMF\Maintenance\Migration;

use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\Lang;
use SMF\Maintenance;
use SMF\Maintenance\Database\DatabaseInterface;
use SMF\Sapi;

/**
 * Migration container for a maintenance task.
 */
class MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * Name of the migration tasks.
	 */
	public string $name;

	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var DatabaseInterface
	 *
	 * The database type we are working with.
	 */
	protected ?DatabaseInterface $db;

	/****************
	 * Public methods
	 ****************/

	/**
	 * Check if the task should be performed or not.
	 *
	 * @return bool True if this task needs ran, false otherwise.
	 */
	public function isCandidate(): bool
	{
		return true;
	}

	/**
	 * Upgrade task we will execute.
	 *
	 * @return bool True if successful (or skipped), false otherwise.
	 */
	public function execute(): bool
	{
		return true;
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Wrapper for the tool to handle timeout protection.
	 *
	 * If a timeout needs to occur, it is handled, ensure that prior to this call, all variables are updated..
	 */
	protected function handleTimeout(?int $start = 0): void
	{
		if ($start !== null) {
			Maintenance::setCurrentStart($start);
		}

		Maintenance::$tool->checkAndHandleTimeout();
	}

	/**
	 * Wrapper for the database query.
	 *
	 * Ensures the query runs without handling errors, as we do not have that luxury.
	 */
	protected function query(string $identifier, string $db_string, array $db_values = [], ?object $connection = null)
	{
		if (!Config::$modSettings['disableQueryCheck']) {
			Config::$modSettings['disableQueryCheck'] = true;
		}

		if (!empty($db_values['unbuffered'])) {
			Db::$unbuffered = true;
		}

		$db_values += [
			'security_override' => true,
			'db_error_skip' => true,
		];

		$result = Db::$db->query($identifier, $db_string, $db_values, $connection);
		Db::$unbuffered = false;

		// Failure?!
		if ($result !== false) {
			return $result;
		}

		$db_error_message = Db::$db->error(Db::$db_connection);

		if ($this->db === null) {
			$this->db = Maintenance::$tool->loadMaintenanceDatabase(Config::$db_type);
		}

		// Checks if we can fix this.
		$result = $this->db->processError($db_error_message, Db::$db->quote($db_string, $db_values, $connection));

		if ($result !== false) {
			return $result;
		}

		if (Sapi::isCLI()) {
			echo 'Unsuccessful!  Database error message:', "\n", $db_error_message, "\n";

			die;
		}

		Maintenance::$context['try_again'] = true;
		Maintenance::$fatal_error = '
		<strong>' . Lang::$txt['upgrade_unsuccessful'] . '</strong><br>
		<div style="margin: 2ex;">
			' . Lang::getTxt(
			'query_failed',
			[
				'QUERY_STRING' => '<blockquote><pre>' . nl2br(htmlspecialchars(trim($db_string))) . ';</pre></blockquote>',
				'QUERY_ERROR' => '<blockquote>' . nl2br(htmlspecialchars($db_error_message)) . '</blockquote>',
			],
		) .
			'
		</div>';

		Maintenance::$tool->preExit();
		Maintenance::exit();
	}
}

?>