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
use SMF\Lang;
use SMF\Maintenance;
use SMF\Maintenance\Database\Schema\Column;
use SMF\Maintenance\Migration\MigrationBase;

class Smileys extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * {@inheritDoc}
	 */
	public string $name = 'Update smileys';

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
		$start = Maintenance::getCurrentStart();

		// Adding the new `smiley_files` table
		if ($start <= 0) {
			$table = new \SMF\Maintenance\Database\Schema\v2_1\SmileyFiles();
			$existing_tables = Db::$db->list_tables();

			if (!in_array(Config::$db_prefix . $table->name, $existing_tables)) {
				$table->create();
			}

			$this->handleTimeout(++$start);
		}

		// Cleaning up unused smiley sets and adding the lovely new ones
		if ($start <= 1) {
			// Start with the prior values...
			$dirs = explode(',', Config::$modSettings['smiley_sets_known']);
			$setnames = explode("\n", Config::$modSettings['smiley_sets_names']);

			// Build combined pairs of folders and names
			$combined = [];

			foreach ($dirs as $ix => $dir) {
				if (!empty($setnames[$ix])) {
					$combined[$dir] = [$setnames[$ix], ''];
				}
			}

			// Add our lovely new 2.1 smiley sets if not already there
			$combined['fugue'] = [Lang::$txt['default_fugue_smileyset_name'], 'png'];
			$combined['alienine'] = [Lang::$txt['default_alienine_smileyset_name'], 'png'];

			// Add/fix our 2.0 sets (to correct past problems where these got corrupted)
			$combined['default'] = [Lang::$txt['default_legacy_smileyset_name'], 'gif'];
			$combined['aaron'] = [Lang::$txt['default_aaron_smileyset_name'], 'gif'];
			$combined['akyhne'] = [Lang::$txt['default_akyhne_smileyset_name'], 'gif'];

			// Confirm they exist in the filesystem
			$filtered = [];

			foreach ($combined as $dir => $attrs) {
				if (is_dir(Config::$modSettings['smileys_dir'] . '/' . $dir . '/')) {
					$filtered[$dir] = $attrs[0];
				}
			}

			// Update the Settings Table...
			Config::updateModSettings(['smiley_sets_known' => implode(',', array_keys($filtered))]);
			Config::updateModSettings(['smiley_sets_names' => implode("\n", $filtered)]);

			// Populate the smiley_files table
			$smileys_columns = Db::$db->list_columns('{db_prefix}smileys');

			if (in_array('filename', $smileys_columns)) {
				$inserts = [];

				$request = $this->query('', '
					SELECT id_smiley, filename
					FROM {db_prefix}smileys');

				while ($row = Db::$db->fetch_assoc($request)) {
					$pathinfo = pathinfo($row['filename']);

					foreach ($filtered as $set => $dummy) {
						$ext = $pathinfo['extension'];

						// If we have a default extension for this set, check if we can switch to it.
						if (isset($combined[$set]) && !empty($combined[$set][1])) {
							if (file_exists(Config::$modSettings['smileys_dir'] . '/' . $set . '/' . $pathinfo['filename'] . '.' . $combined[$set][1])) {
								$ext = $combined[$set][1];
							}
						}
						// In a custom set and no extension specified? Ugh...
						elseif (empty($ext)) {
							// Any files matching this name?
							$found = glob(Config::$modSettings['smileys_dir'] . '/' . $set . '/' . $pathinfo['filename'] . '.*');
							$ext = !empty($found) ? pathinfo($found[0], PATHINFO_EXTENSION) : 'gif';
						}

						$inserts[] = [$row['id_smiley'], $set, $pathinfo['filename'] . '.' . $ext];
					}
				}
				Db::$db->free_result($request);

				if (!empty($inserts)) {
					Db::$db->insert(
						'ignore',
						'{db_prefix}smiley_files',
						['id_smiley' => 'int', 'smiley_set' => 'string-48', 'filename' => 'string-48'],
						$inserts,
						['id_smiley', 'smiley_set'],
					);

					// Unless something went horrifically wrong, drop the defunct column
					if (count($inserts) == Db::$db->affected_rows()) {
						$table = new \SMF\Maintenance\Database\Schema\v2_1\Smileys();
						$existing_structure = $table->getCurrentStructure();

						if (isset($existing_structure['columns']['filename'])) {
							$oldColumn = new Column('filename', 'varchar');
							$table->dropColumn($oldColumn);
						}
					}
				}
			}

			// Set new default if the old one doesn't exist
			// If fugue exists, use that.  Otherwise, what the heck, just grab the first one...
			if (!array_key_exists(Config::$modSettings['smiley_sets_default'], $filtered)) {
				if (array_key_exists('fugue', $filtered)) {
					$newdefault = 'fugue';
				} elseif (!empty($filtered) && is_array($filtered)) {
					$newdefault = array_keys($filtered)[0];
				} else {
					$newdefault = '';
				}

				Config::updateModSettings(['smiley_sets_default' => $newdefault]);
			}


			$this->handleTimeout(++$start);
		}

		return true;
	}
}

?>