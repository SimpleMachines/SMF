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

namespace SMF\Maintenance\Migration\v3_0;

use SMF\Db\DatabaseApi as Db;
use SMF\Maintenance\Migration\MigrationBase;
use SMF\Db\Schema\v3_0;
use SMF\Db\Schema\v3_0\UserReacts;

class UserReactions extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Converting likes to reactions';

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function execute(): bool
	{
		// Make sure we haven't already done this...
		$cols = Db::$db->list_columns('messages');
		
		// If the reactions column exists in the messages table, there's nothing to do
		if(!in_array('reactions', $cols))
		{
			// Does the user_likes table exist?
			$table_exists = Db::$db->list_tables(false, '%user_likes');
			if (!empty($table_exists))
			{
				// Rename the table
				Db::$db->rename_table('{db_prefix}user_likes', '{db_prefix}user_reacts', true);
				
				// Add the new column
				Db::$db->add_column('{db_prefix}user_reacts', ['name' => 'id_reaction', 'type' => 'smallint', 'not_null' => true, 'default' => '0']);

				// Default reaction is like for now
				Db::$db->query('', 'UPDATE {db_prefix}user_reacts SET id_react={int:one}', ['one' => 1]);

				// Rename the like_time column
				Db::$db->change_column('{db_prefix}user_reacts', 'like_time', ['name' => 'react_time']);

				// Rename the index
				Db::$db->rename_index('{db_prefix}user_reacts', 'idx_liker', 'idx_reactor');

				// Rename the likes column in the messages table
				Db::$db->remove_index('{db_prefix}messages', 'idx_likes');
				Db::$db->change_column('{db_prefix}messages', 'likes', ['name' => 'reactions']);
				Db::$db->add_index('{db_prefix}messages', ['name' => 'idx_reacts', 'columns' => ['reactions']]);

				// Update user alert prefs
				Db::$db->query('', 'UPDATE {db_prefix}user_alert_prefs SET alert_pref = {string:msg_react} WHERE alert_pref = {string:msg_like}', ['msg_react' => 'msg_react', 'msg_like' => 'msg_iike']);

				// Update permissions
				Db::$db->query('', 'UPDATE {db_prefix}permissions SET permission = {string:r_perm} WHERE permission = {string:l_perm}', ['r_perm' => 'reactions_react', 'l_perm' => 'likes_like']);

				// Finally, the settting
				Db::$db->query('', 'UPDATE {db_prefix}settings SET variable = {string:r_set} WHERE variable = {string:l_set}', ['r_set' => 'enable_reacts', 'l_set' => 'enable_likes']);
			}
		}
		
		// Create the table
		else
		{
			// Shortcuts are fun...
			$table = new UserReacts;
			$columns = [];
			$indexes = [];

			// Loop through the schema definition to set up our arrays for db->create_table()
			foreach($table->columns as $col)
			{
				$columns[$col->name] =
				[
					'name' => $col->name,
					'type' => $col->type,
					'unsigned' => $col->unsigned,
					'not_null' => $col->not_null,
					'default' => $col->default,
				];

				if (isset($col->size))
					$columns[$col->name]['size'] = $col->size;
			}

			foreach($table->indexes as $idx)
			{
				$indexes[$idx->name] =
				[
					'name' => $idx->name,
					'columns' => $idx->columns
				];
				
				if(isset($idx->type))
					$indexes[$idx->name]['type'] = $idx->type;
			}

			// Now we just create the table
			Db::$db->create_table('{db_prefix}user_reacts', $columns, $indexes);

			// Add the reactions column and related index to the messages table
			Db::$db->add_column('{db_prefix}messages', ['name' => 'reactions', 'type' => 'smallint', 'not_null' => true, 'default' => '0']);
			Db::$db->add_index('{db_prefix}messages', ['name' => 'idx_messages_reactions', 'columns' => ['reactions']]);
		}

		// Create the reactions table. Only two columns here so no need to save space by looping through the schema
		$reactions_cols = [
			'id_reaction' => [
				'name' => 'id_reaction',
				'type' => 'smallint',
				'unsigned' => true,
				'not_null' => true,
				'default' => 0,
			],
			'name' => [
				'name' => 'name',
				'type' => 'varchar',
				'size' => '255',
				'not_null' => true,
				'default' => '',
			]
		];

		$reactions_idxs = [
			'id_reaction' => [
				'name' => 'id_reaction',
				'columns' => ['id_reaction'],
				'type' => 'primary'
			]
		];

		Db::$db->create_table('{db_prefix}reactions', $reactions_cols, $reactions_idxs);

		// Add our default reaction
		Db::$db->insert('update', '{db_prefix}reactions', ['id_reaction', 'name'], [1, 'like'], []);

		return true;
	}
}