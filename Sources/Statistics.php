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

namespace SMF;

use SMF\Actions\Admin\ACP;
use SMF\Db\DatabaseApi as Db;

/**
 * Statistic collection
 *
 * All logic regarding stats collection is handled here, including enabling,
 * disabling and collection.
 *
 * For Developers:
 * You can use a Boardurl of "http://example.com/forum", this will return a UID.
 * However no collection events will be initiated by Simple Machines.  Issuing a
 * registration or collection will return success results always.
 *
 * @link https://www.simplemachines.org/about/stats.php for more info.
 */
class Statistics
{
	/**************************
	 * Public static properties
	 **************************/

	/**
	 * URL to send the stats collection data to.
	 * @var string
	 */
	public static string $collection_url = 'https://www.simplemachines.org/smf/stats/collect_stats.php';

	/**
	 * URL to send the registration to.
	 * @var string
	 */
	public static string $register_url = 'https://www.simplemachines.org/smf/stats/register_stats.php?site=';

	/**
	 * This is the referal check, SMF validates that a request for stats comes from this url.
	 * This is not a perfect check and can be spoofed, however SMF will still initiate a separate connection to
	 * the collector rather than presenting any data.
	 *
	 * @var string
	 */
	public static string $referer_check = '746cb59a1a0d5cf4bd240e5a67c73085';

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Everything that is collected to send home.
	 * This data is to be anonymous and should not contain any identifiable
	 * information.
	 *
	 * The UID sent is the remote ID for this forum.
	 *
	 * @return array
	 */
	public static function collect(): array
	{
		// Get some server versions.
		$checkFor = [
			'php',
			'db_server',
		];
		$serverVersions = ACP::getServerVersions($checkFor);

		// Get the actual stats.
		$stats_to_send = [
			'UID' => Config::$modSettings['sm_stats_key'],
			'time_added' => time(),
			'members' => Config::$modSettings['totalMembers'],
			'messages' => Config::$modSettings['totalMessages'],
			'topics' => Config::$modSettings['totalTopics'],
			'boards' => 0,
			'php_version' => $serverVersions['php']['version'],
			'database_type' => strtolower($serverVersions['db_engine']['version']),
			'database_version' => $serverVersions['db_server']['version'],
			'smf_version' => SMF_FULL_VERSION,
			'smfd_version' => Config::$modSettings['smfVersion'],
		];

		return $stats_to_send;
	}

	/**
	 * Registers the site with the Simple Machines Stat collection. This function
	 * purposely does not use Config::updateModSettings() as it will be called shortly after
	 * this process completes by the saveSettings() function.
	 *
	 * This will send a request home and grab the UID for this
	 * installation.  Only upon success will this return true.
	 *
	 * Lan based forums and those without DNS resolution will not register.
	 *
	 * @return bool Returns true if we are registered or successfully registered, otherwise false.
	 */
	public static function register(): bool
	{
		// Already have a key?  Can't register again.
		if (!empty(Config::$modSettings['sm_stats_key'])) {
			return true;
		}

		// If this is a local forum, registration will fail anyways.
		if (!(new Url(Config::$boardurl))->isFetchSafe(['https', 'http'])) {
			return false;
		}

		$data = WebFetchApi::fetch(static::$register_url . base64_encode(Config::$boardurl));

		// Try one more time, this time without https.
		if (empty($data)) {
			$data = WebFetchApi::fetch(str_replace('https://', 'http://', static::$register_url) . base64_encode(Config::$boardurl));
		}

		// Get the unique site ID.
		preg_match('~SITE-ID:\s(\w{10})~', $data, $ID);

		if (!empty($ID[1])) {
			Db::$db->insert(
				'replace',
				'{db_prefix}settings',
				[
					'variable' => 'string',
					'value' => 'string',
				],
				[
					[
						'sm_stats_key',
						$ID[1],
					],
				],
				['variable'],
			);

			return true;
		}

		return false;
	}

	/**
	 * Clears out the UID.
	 *
	 * No call home is made.
	 * Collection attempts will be made occastionally and
	 * will automatically stop after a period of time.
	 *
	 * @return bool True in all cases.
	 */
	public static function clear(): bool
	{
		Db::$db->query(
			'DELETE FROM {db_prefix}settings
			WHERE variable = {literal:sm_stats_key}',
		);

		return true;
	}
}
