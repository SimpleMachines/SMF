<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2024 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 3
 */

declare(strict_types=1);

namespace SMF\Maintenance\Migration\v2_1;

use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\Maintenance\Maintenance;
use SMF\Maintenance\Migration\MigrationBase;
use SMF\Parser;
use SMF\Utils;

class BoardDescriptions extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Parsing board descriptions and names';

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function isCandidate(): bool
	{
		return version_compare(
			str_replace(' ', '.', strtolower(Config::$modSettings['smfVersion'] ?? '0.0.dev.0')),
			'2.1.dev.0',
			'<',
		);
	}

	/**
	 *
	 */
	public function execute(): bool
	{

		$request = $this->query(
			'',
			'SELECT name, description, id_board
			FROM {db_prefix}boards
			WHERE id_board > {int:start}',
			[
				'start' => Maintenance::getCurrentStart(),
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			$this->query(
				'',
				'UPDATE {db_prefix}boards
				SET name = {string:name}, description = {string:description}
				WHERE id = {int:id}',
				[
					'id' => $row['id_board'],
					'name' => Utils::htmlspecialchars(strip_tags(Parser::transform($row['name'], Parser::OUTPUT_BBC))),
					'description' => Utils::htmlspecialchars(strip_tags(Parser::transform($row['description'], Parser::OUTPUT_BBC))),
				],
			);

			Maintenance::setCurrentStart();
			$this->handleTimeout();
		}

		Db::$db->free_result($request);

		return true;
	}
}
