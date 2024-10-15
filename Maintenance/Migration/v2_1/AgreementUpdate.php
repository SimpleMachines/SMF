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

namespace SMF\Maintenance\Migration\v2_1;

use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\Maintenance\Maintenance;
use SMF\Maintenance\Migration\MigrationBase;
use SMF\Utils;

class AgreementUpdate extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * {@inheritDoc}
	 */
	public string $name = 'Update policy & agreement settings';

	private int $limit = 10000;

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
		// Strip -utf8 from policy settings
		$newSettings = [];

		foreach(Config::$modSettings as $k => $v) {
			if ((substr($k, 0, 7) === 'policy_') && (substr($k, -5) === '-utf8')) {
				$utf8_policy_settings[$k] = $v;
			}
		}

		foreach($utf8_policy_settings as $var => $val) {
			// Note this works on the policy_updated_ strings as well...
			$language = substr($var, 7, strlen($var) - 12);

			if (!array_key_exists('policy_' . $language, Config::$modSettings)) {
				$newSettings['policy_' . $language] = $val;
				$newSettings[$var] = null;
			}
		}

		if (!empty($newSettings)) {
			Config::updateModSettings($newSettings);
		}

		// Strip -utf8 from agreement file names
		$files = glob(Config::$boarddir . '/agreement.*-utf8.txt');

		foreach($files as $filename) {
			$newfile = substr($filename, 0, strlen($filename) - 9) . '.txt';

			// Do not overwrite existing files
			if (!file_exists($newfile)) {
				@rename($filename, $newfile);
			}
		}

		// Setup progress bar
		$request = $this->query(
			'',
			'
			SELECT COUNT(*)
			FROM {db_prefix}log_actions
			WHERE action IN ({array_string:target_actions})',
			[
				'target_actions' => ['policy_accepted', 'agreement_accepted'],
			],
		);
		list($maxActions) = Db::$db->fetch_row($request);
		Db::$db->free_result($request);
		Maintenance::$total_items = (int) $maxActions;

		// Main process loop
		$is_done = false;
		$start = Maintenance::getCurrentStart();

		while (!$is_done) {
			// Keep looping at the current step.
			$this->handleTimeout($start);

			$extras = [];
			$request = Db::$db->query(
				'',
				'
				SELECT id_action, extra
					FROM {db_prefix}log_actions
					WHERE id_member = {int:blank_id}
					AND action IN ({array_string:target_actions})
					AND id_action >  {int:last}
					ORDER BY id_action
					LIMIT {int:limit}',
				[
					'blank_id' => 0,
					'target_actions' => ['policy_accepted', 'agreement_accepted'],
					'last' => $start,
					'limit' => $this->limit,
				],
			);

			while ($row = Db::$db->fetch_assoc($request)) {
				$extras[$row['id_action']] = $row['extra'];
			}
			Db::$db->free_result($request);

			if (empty($extras)) {
				$is_done = true;
			} else {
				$start = max(array_keys($extras));
			}

			foreach ($extras as $id => $extra_ser) {
				$extra = $this->upgrade_unserialize($extra_ser);

				if ($extra === false) {
					continue;
				}

				if (!empty($extra['applicator'])) {
					$request = Db::$db->query(
						'',
						'
						UPDATE {db_prefix}log_actions
							SET id_member = {int:id_member}
							WHERE id_action = {int:id_action}',
						[
							'id_member' => $extra['applicator'],
							'id_action' => $id,
						],
					);
				}
			}
		}


		return true;
	}

	/**
	 * Wrapper for unserialize that attempts to repair corrupted serialized data strings
	 *
	 * @param string $string Serialized data that may or may not have been corrupted
	 * @return string|bool The unserialized data, or false if the repair failed
	 */
	private function upgrade_unserialize($string)
	{
		if (!is_string($string)) {
			$data = false;
		}
		// Might be JSON already.
		elseif (str_starts_with($string, '{')) {
			$data = @json_decode($string, true);

			if (is_null($data)) {
				$data = false;
			}
		} elseif (in_array(substr($string, 0, 2), ['b:', 'i:', 'd:', 's:', 'a:', 'N;'])) {
			$data = @Utils::safeUnserialize($string);

			// The serialized data is broken.
			if ($data === false) {
				// This bit fixes incorrect string lengths, which can happen if the character encoding was changed (e.g. conversion to UTF-8)
				$new_string = preg_replace_callback(
					'~\bs:(\d+):"(.*?)";(?=$|[bidsaO]:|[{}}]|N;)~s',
					function ($matches) {
						return 's:' . strlen($matches[2]) . ':"' . $matches[2] . '";';
					},
					$string,
				);

				// @todo Add more possible fixes here. For example, fix incorrect array lengths, try to handle truncated strings gracefully, etc.

				// Did it work?
				$data = @Utils::safeUnserialize($string);
			}
		}
		// Just a plain string, then.
		else {
			$data = false;
		}

		return $data;
	}

}

?>