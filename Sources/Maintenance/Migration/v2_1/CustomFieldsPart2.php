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

use SMF\Db\DatabaseApi as Db;
use SMF\Maintenance;
use SMF\Maintenance\Migration\MigrationBase;

class CustomFieldsPart2 extends MigrationBase
{
  	/*******************
	 * Public properties
	 *******************/

	/**
	 * {@inheritDoc}
	 */
	public string $name = 'Upgrade Custom Fields';
  
	private array $possible_columns = ['icq', 'msn', 'location', 'gender'];

	private int $limit = 10000;

	/****************
	 * Public methods
	 ****************/

	/**
	 * {@inheritDoc}
	 */
	public function isCandidate(): bool
	{
		// See which columns we have
		$results = Db::$db->list_columns('{db_prefix}members');

		return array_intersect($this->possible_columns, $results) !== [];
	}

	/**
	 * {@inheritDoc}
	 */
	public function execute(): bool
	{
		$start = Maintenance::getCurrentStart();

		$request = $this->query('', 'SELECT COUNT(*) FROM {db_prefix}members');
		list($maxMembers) = Db::$db->fetch_row($request);
		Db::$db->free_result($request);
		Maintenance::$total_items = (int) $maxMembers;

		$results = Db::$db->list_columns('{db_prefix}members');
		$select_columns = array_intersect($this->possible_columns, $results);

		$is_done = false;

		while (!$is_done)
		{
			$this->handleTimeout($start);
			$inserts = array();
	
			$request = $this->query('', '
				SELECT id_member, '. implode(',', $select_columns) .'
				FROM {db_prefix}members
				ORDER BY id_member
				LIMIT {int:start}, {int:limit}',
				array(
					'start' => $start,
					'limit' => $this->limit,
			));
	
			while ($row = Db::$db->fetch_assoc($request))
			{
				if (!empty($row['icq']))
					$inserts[] = array($row['id_member'], 1, 'cust_icq', $row['icq']);
	
				if (!empty($row['msn']))
					$inserts[] = array($row['id_member'], 1, 'cust_skype', $row['msn']);
	
				if (!empty($row['location']))
					$inserts[] = array($row['id_member'], 1, 'cust_loca', $row['location']);
	
				if (!empty($row['gender']))
					$inserts[] = array($row['id_member'], 1, 'cust_gender', '{gender_' . intval($row['gender']) . '}');
			}
			Db::$db->free_result($request);
	
			if (!empty($inserts))
				Db::$db->insert('replace',
					'{db_prefix}themes',
					array('id_member' => 'int', 'id_theme' => 'int', 'variable' => 'string', 'value' => 'string'),
					$inserts,
					array('id_theme', 'id_member', 'variable')
				);
	
			$start += $this->limit;
	
			if ($start >= $maxMembers)
				$is_done = true;
		}

        return true;
    }
}

?>