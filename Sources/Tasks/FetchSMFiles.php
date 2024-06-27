<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2024 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 2
 */

declare(strict_types=1);

namespace SMF\Tasks;

use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\ErrorHandler;
use SMF\Lang;
use SMF\Theme;
use SMF\Utils;
use SMF\WebFetch\WebFetchApi;

/**
 * Retrieves data (e.g. last version of SMF) from simplemachines.org.
 */
class FetchSMFiles extends ScheduledTask
{
	/**
	 * This executes the task.
	 *
	 * @return bool Always returns true.
	 * @todo PHP 8.2: This can be changed to return type: true.
	 */
	public function execute(): bool
	{
		// What files do we want to get?
		$js_files = [];

		$request = Db::$db->query(
			'',
			'SELECT id_file, filename, path, parameters
			FROM {db_prefix}admin_info_files',
			[
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			$js_files[$row['id_file']] = [
				'filename' => $row['filename'],
				'path' => $row['path'],
				'parameters' => sprintf($row['parameters'], Lang::$default, urlencode(Config::$modSettings['time_format']), urlencode(SMF_FULL_VERSION)),
			];
		}
		Db::$db->free_result($request);

		// Just in case we run into a problem.
		Theme::loadEssential();
		Lang::load('Errors', Lang::$default, false);

		foreach ($js_files as $id_file => $file) {
			// Create the url
			$server = empty($file['path']) || (!str_starts_with($file['path'], 'http://') && !str_starts_with($file['path'], 'https://')) ? 'https://www.simplemachines.org' : '';

			$url = $server . (!empty($file['path']) ? $file['path'] : $file['path']) . $file['filename'] . (!empty($file['parameters']) ? '?' . $file['parameters'] : '');

			// Get the file
			$file_data = WebFetchApi::fetch($url);

			// If we got an error - give up - the site might be down. And if we should happen to be coming from elsewhere, let's also make a note of it.
			if ($file_data === false) {
				Utils::$context['scheduled_errors']['fetchSMfiles'][] = Lang::getTxt('st_cannot_retrieve_file', [$url]);

				ErrorHandler::log(Lang::getTxt('st_cannot_retrieve_file', [$url]));

				return true;
			}

			// Save the file to the database.
			Db::$db->query(
				'substring',
				'UPDATE {db_prefix}admin_info_files
				SET data = SUBSTRING({string:file_data}, 1, 65534)
				WHERE id_file = {int:id_file}',
				[
					'id_file' => $id_file,
					'file_data' => $file_data,
				],
			);
		}

		return true;
	}
}

?>