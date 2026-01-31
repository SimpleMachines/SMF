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

namespace SMF\Tasks;

use SMF\Db\DatabaseApi as Db;
use SMF\Lang;
use SMF\Utils;
use SMF\Uuid;

/**
 * Anonymizes a member's data in the edit history of posts.
 */
class AnonymizeEditHistory extends BackgroundTask
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var int
	 *
	 * Maximum number of rows to update per execution of this task.
	 *
	 * If the total number of rows that need to be updated exceeds this amount,
	 * the task will respawn itself in order to continue the job.
	 */
	public int $limit = 500;

	/****************
	 * Public methods
	 ****************/

	/**
	 * Do the job.
	 *
	 * @return bool Always returns true.
	 * @todo PHP 8.2: This can be changed to return type: true.
	 */
	public function execute(): bool
	{
		// Set the anonymous name.
		$anonymous_name = Utils::strtolower(Lang::$txt['user']) . '_' . substr(Uuid::create(5, 'member=' . $this->_details['id'])->getShortForm(true), 0, 8);

		if (Db::$title === POSTGRE_TITLE) {
			$request = Db::$db->query(
				'',
				'SELECT id_msg, edit_history
				FROM {db_prefix}messages
				WHERE edit_history @? {string:jsonpath}
				ORDER BY id_msg DESC
				LIMIT {int:limit}',
				[
					'jsonpath' => '$[*][5] ? (@ == ' . $this->_details['id'] . ')',
					'limit' => $this->limit,
				],
			);
		} else {
			$request = Db::$db->query(
				'',
				'SELECT id_msg, edit_history
				FROM {db_prefix}messages
				WHERE {int:member} MEMBER OF (edit_history->{string:path})
				ORDER BY id_msg DESC
				LIMIT {int:limit}',
				[
					'member' => $this->_details['id'],
					'path' => '$[*][5]',
					'limit' => $this->limit,
				],
			);
		}

		$num_rows = Db::$db->num_rows($request);

		while ($row = Db::$db->fetch_assoc($request)) {
			$row['edit_history'] = Utils::jsonDecode($row['edit_history'], true);

			foreach ($row['edit_history'] as $key => $value) {
				if ((int) $value[5] === (int) $this->_details['id']) {
					$row['edit_history'][$key][5] = 0;
					$row['edit_history'][$key][6] = $anonymous_name;
				}
			}

			$row['edit_history'] = json_encode($row['edit_history']);

			Db::$db->query(
				'',
				'UPDATE {db_prefix}messages
				SET edit_history = {string:edit_history}
				WHERE id_msg = {int:id_msg}',
				$row,
			);
		}

		Db::$db->free_result($request);

		if ($num_rows === $this->limit) {
			$this->respawn($this->_details);
		}

		return true;
	}
}
