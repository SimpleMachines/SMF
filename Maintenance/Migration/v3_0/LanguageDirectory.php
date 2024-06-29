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

namespace SMF\Maintenance\Migration\v3_0;

use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\Lang;
use SMF\Maintenance;
use SMF\Maintenance\Migration\MigrationBase;

class LanguageDirectory extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * {@inheritDoc}
	 */
	public string $name = 'Language Upgrade';

	/****************
	 * Public methods
	 ****************/

	/**
	 * {@inheritDoc}
	 */
	public function execute(): bool
	{
		$limit = 10000;
		$statements = [];
		$langs = [];
		$args = ['defaultLang' => 'en_US'];
		$members = [];

		// Setup the case statement.
		foreach (Lang::LANG_TO_LOCALE as $lang => $locale) {
			$statements[] = ' WHEN lngfile = {string:lang_' . $lang . '} THEN {string:locale_' . $locale . '}';
			$args['lang_' . $lang] = $lang;
			$args['locale_' . $locale] = $locale;
			$langs[] = $lang;
		}

		$is_done = false;

		while (!$is_done) {
			// @@ TODO: Handle sub steps.
			$this->handleTimeout();

			// Skip errors here so we don't croak if the columns don't exist...
			$request = Db::$db->query(
				'',
				'SELECT id_member
                FROM {db_prefix}members
                WHERE lngfile IN ({array_string:possible_languages})
                ORDER BY id_member
                LIMIT {int:limit}',
				[
					'limit' => $limit,
					'possible_languages' => $langs,
				],
			);

			if (Db::$db->num_rows($request) == 0) {
				$is_done = true;
				break;
			}

			while ($row = Db::$db->fetch_assoc($request)) {
				$members[] = $row['id_member'];
			}

			Db::$db->free_result($request);


			// Nobody to convert, woohoo!
			if (empty($members)) {
				$is_done = true;
				break;
			}

			$args['search_members'] = $members;


			Db::$db->query(
				'',
				'UPDATE {db_prefix}members
                SET lngfile = CASE
                    ' . implode(' ', $statements) . '
                    ELSE {string:defaultLang} END
                WHERE id_member IN ({array_int:search_members})',
				$args,
			);

			Maintenance::setCurrentStart();
		}

		// Rename the privacy policy records.
		foreach (Config::$modSettings as $variable => $value) {
			if (!str_starts_with($variable, 'policy_')) {
				continue;
			}

			if (str_starts_with($variable, 'policy_updated_')) {
				$locale = Lang::getLocaleFromLanguageName(substr($variable, 15));
				$new_variable = isset($locale) ? 'policy_updated_' . $locale : $variable;
			} else {
				$locale = 'policy_' . Lang::getLocaleFromLanguageName(substr($variable, 7));
				$new_variable = isset($locale) ? 'policy_' . $locale : $variable;
			}

			if ($variable !== $new_variable) {
				Config::updateModSettings([
					$new_variable => $value,
					$variable => null,
				]);

				unset($new_variable);
			}
		}

		return true;
	}
}

?>