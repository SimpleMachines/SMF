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

namespace SMF\Maintenance\Migration\v1_1;

use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\Maintenance\Migration\MigrationBase;

class OldThemes extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Cleaning up after old themes';

	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var int
	 *
	 * ID of the classic theme.
	 */
	private int $id_theme;

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function isCandidate(): bool
	{
		$request = $this->query(
			'SELECT id_theme
			FROM {db_prefix}themes
			WHERE variable = {string:themedir}
				AND value = {string:classicdir}',
			[
				'themedir' => 'theme_dir',
				'classicdir' => Config::$boarddir . '/Themes/classic',
			],
		);

		if (Db::$db->num_rows($request) == 1) {
			list($this->id_theme) = array_map('intval', Db::$db->fetch_row($request));
		}

		Db::$db->free_result($request);

		return isset($this->id_theme);
	}

	/**
	 *
	 */
	public function execute(): bool
	{
		$known_themes = explode(',', Config::$modSettings['knownThemes']);

		// Remove this value...
		$known_themes = array_diff($known_themes, [$this->id_theme]);

		// Change back to a string...
		$known_themes = implode(',', $known_themes);

		// Update the database.
		Config::updateModSettings([
			'knownThemes' => $known_themes,
		]);

		// Delete any info about this theme
		$this->query(
			'DELETE FROM {db_prefix}themes
			WHERE ID_THEME = {int:id}',
			[
				'id' => $this->id_theme,
			],
		);

		// Set any members or boards using this theme to the default
		$this->query(
			'UPDATE {db_prefix}members
			SET ID_THEME = 0
			WHERE ID_THEME = {int:id}',
			[
				'id' => $this->id_theme,
			],
		);

		$this->query(
			'UPDATE {db_prefix}boards
			SET ID_THEME = 0
			WHERE ID_THEME = {int:id}',
			[
				'id' => $this->id_theme,
			],
		);

		if (Config::$modSettings['theme_guests'] == $this->id_theme) {
			Config::updateModSettings([
				'theme_guests' => 0,
			]);
		}

		$this->handleTimeout();

		return true;
	}
}
