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

namespace SMF\Maintenance\Migration\v2_1;

use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\Maintenance;
use SMF\Maintenance\Migration\MigrationBase;

class VerificationQuestions extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * {@inheritDoc}
	 */
	public string $name = 'Upgrading "verification questions" feature';

	/****************
	 * Public methods
	 ****************/

	/**
	 * {@inheritDoc}
	 */
	public function isCandidate(): bool
	{
		$tables = Db::$db->list_tables();

		return !in_array(Config::$db_prefix . 'qanda', $tables);
	}

	/**
	 * {@inheritDoc}
	 */
	public function execute(): bool
	{
		$start = Maintenance::getCurrentStart();

		$QandaTable = new \SMF\Db\Schema\v3_0\Qanda();

		$tables = Db::$db->list_tables();

		// Creating draft table.
		if ($start <= 0 && !in_array(Config::$db_prefix . 'qanda', $tables)) {
			$QandaTable->create();

			$this->handleTimeout(++$start);
		}

		$questions = [];

		$get_questions = $this->query(
			'',
			'SELECT body AS question, recipient_name AS answer
			FROM {db_prefix}log_comments
			WHERE comment_type = {literal:ver_test}',
		);

		while ($row = Db::$db->fetch_assoc($get_questions)) {
			$questions[] = [
				Maintenance::getRequestedLanguage(),
				$row['question'],
				serialize([$row['answer']]),
			];
		}

		Db::$db->free_result($get_questions);

		if (!empty($questions)) {
			Db::$db->insert(
				'',
				'{db_prefix}qanda',
				[
					'lngfile' => 'string',
					'question' => 'string',
					'answers' => 'string',
				],
				$questions,
				['id_question'],
			);

			// Delete the questions from log_comments now
			$this->query(
				'',
				'DELETE FROM {db_prefix}log_comments
				WHERE comment_type = {literal:ver_test}',
			);
		}

		$this->handleTimeout(++$start);

		return true;
	}
}

?>