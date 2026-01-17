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

namespace SMF\Maintenance\Migration;

use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\Lang;
use SMF\Maintenance\Maintenance;
use SMF\Maintenance\SubStepInterface;
use SMF\Sapi;

/**
 * Migration container for a maintenance task.
 */
class MigrationBase implements SubStepInterface
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * Name of the migration task.
	 */
	public string $name;

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function isCandidate(): bool
	{
		return true;
	}

	/**
	 *
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
	protected function handleTimeout(?int $start = null): void
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
	protected function query(string $db_string, array $db_values = [], ?object $connection = null, ?string $identifier = null): object|bool
	{
		if (!empty(Config::$modSettings['disableQueryCheck'])) {
			Config::$modSettings['disableQueryCheck'] = true;
		}

		if (!empty($db_values['unbuffered'])) {
			Db::$unbuffered = true;
		}

		$db_values += [
			'db_error_skip' => true,
		];

		$result = Db::$db->query($db_string, $db_values, $connection, $identifier);
		Db::$unbuffered = false;

		// Did it work?
		if ($result !== false) {
			return $result;
		}

		// Oh no! What happened?
		$db_error_message = Db::$db->error(Db::$db_connection);

		// Check whether we can fix this.
		$halt = Db::$db->processError($db_error_message, Db::$db->quote($db_string, $db_values, $connection));

		if ($halt === false) {
			return $result;
		}

		if (Sapi::isCLI()) {
			echo 'Unsuccessful!  Database error message:', "\n", $db_error_message, "\n";

			die;
		}

		// If this is JSON, we can throw it, modern code will catch this.
		if (Maintenance::isJson()) {
			$file = null;
			$line = null;

			foreach (debug_backtrace() as $step) {
				$file = $step['file'];
				$line = $step['line'];
				break;
			}

			throw new \ErrorException($db_error_message, 0, E_USER_ERROR, $file, $line);
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

		return false;
	}
}
