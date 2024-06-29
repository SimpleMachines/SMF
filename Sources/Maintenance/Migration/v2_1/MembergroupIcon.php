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

class MembergroupIcon extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * {@inheritDoc}
	 */
	public string $name = 'Altering the membergroup stars to icons';

	/****************
	 * Public methods
	 ****************/

	/**
	 * {@inheritDoc}
	 */
	public function isCandidate(): bool
	{
		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function execute(): bool
	{
		if (Maintenance::getCurrentStart() === 0) {
			$table = new \SMF\Db\Schema\v3_0\Membergroups();
			$existing_structure = $table->getCurrentStructure();

			if (isset($existing_structure['columns']['stars'])) {
				foreach ($table->columns as $column) {
					if ($column->name === 'icons') {
						$table->alterColumn($column, 'stars');
						break;
					}
				}
			}
		}

		// !! @@TODO Move this to the cleanup section.
		$request = $this->query(
			'',
			'SELECT icons
			FROM {db_prefix}membergroups
			WHERE icons != {string:blank}',
			[
				'blank' => '',
			],
		);

		$toMove = [];
		$toChange = [];

		while ($row = Db::$db->fetch_assoc($request)) {
			if (strpos($row['icons'], 'star.gif') !== false) {
				$toChange[] = [
					'old' => $row['icons'],
					'new' => str_replace('star.gif', 'icon.png', $row['icons']),
				];
			} elseif (strpos($row['icons'], 'starmod.gif') !== false) {
				$toChange[] = [
					'old' => $row['icons'],
					'new' => str_replace('starmod.gif', 'iconmod.png', $row['icons']),
				];
			} elseif (strpos($row['icons'], 'stargmod.gif') !== false) {
				$toChange[] = [
					'old' => $row['icons'],
					'new' => str_replace('stargmod.gif', 'icongmod.png', $row['icons']),
				];
			} elseif (strpos($row['icons'], 'staradmin.gif') !== false) {
				$toChange[] = [
					'old' => $row['icons'],
					'new' => str_replace('staradmin.gif', 'iconadmin.png', $row['icons']),
				];
			} else {
				$toMove[] = $row['icons'];
			}
		}
		Db::$db->free_result($request);

		foreach ($toChange as $change) {
			$this->query(
				'',
				'UPDATE {db_prefix}membergroups
				SET icons = {string:new}
				WHERE icons = {string:old}',
				[
					'new' => $change['new'],
					'old' => $change['old'],
				],
			);
		}

		// Attempt to move any custom uploaded icons.
		foreach ($toMove as $move) {
			// Get the actual image.
			$image = explode('#', $move);
			$image = $image[1];

			// PHP wont suppress errors when running things from shell, so make sure it exists first...
			if (file_exists(Config::$modSettings['theme_dir'] . '/images/' . $image)) {
				@rename(Config::$modSettings['theme_dir'] . '/images/' . $image, Config::$modSettings['theme_dir'] . '/images/membericons/' . $image);
			}
		}

		return true;
	}
}

?>