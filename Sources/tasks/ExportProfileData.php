<?php
/**
 * This file incrementally exports a member's profile data to a downloadable file.
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2020 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 RC2
 */

/**
 * Class ExportProfileData_Background
 */
class ExportProfileData_Background extends SMF_BackgroundTask
{
	/**
	 * This is the main dispatcher for the class.
	 * It calls the correct private function based on the information stored in
	 * the task details.
	 *
	 * @return bool Always returns true
	 */
	public function execute()
	{
		if (!defined('EXPORTING'))
			define('EXPORTING', 1);

		$uid = $this->_details['uid'];
		$lang = $this->_details['lang'];
		$included = $this->_details['included'];
		$start = $this->_details['start'];
		$latest = $this->_details['latest'];
		$datatype = $this->_details['datatype'];

		// For exports only, members can always see their own posts, even in boards that they can no longer access.
		$member_info = $this->getMinUserInfo(array($uid));
		$member_info = array_merge($member_info[$uid], array(
			'buddies' => array(),
			'query_see_board' => '1=1',
			'query_see_message_board' => '1=1',
			'query_see_topic_board' => '1=1',
			'query_wanna_see_board' => '1=1',
			'query_wanna_see_message_board' => '1=1',
			'query_wanna_see_topic_board' => '1=1',
		));

		// Use some temporary integration hooks to manipulate BBC parsing during export.
		add_integration_function('integrate_pre_parsebbc', 'ExportProfileData_Background::pre_parsebbc', false);
		add_integration_function('integrate_post_parsebbc', 'ExportProfileData_Background::post_parsebbc', false);

		// For now, XML is the only export format we support.
		if ($this->_details['format'] == 'XML')
			self::exportXml($uid, $lang, $included, $start, $latest, $datatype, $member_info);

		return true;
	}

	protected static function exportXml($uid, $lang, $included, $start, $latest, $datatype, $member_info)
	{
		global $smcFunc, $sourcedir, $context, $modSettings, $user_info, $settings;
		global $query_this_board;

		if (!isset($included[$datatype]['func']) || !isset($included[$datatype]['langfile']))
			return;

		require_once($sourcedir . '/News.php');
		require_once($sourcedir . '/ScheduledTasks.php');

		// Make sure this has been loaded for use in News.php.
		if (!function_exists('cleanXml'))
			require_once($sourcedir . '/QueryString.php');

		// Setup.
		$done = false;
		$func = $included[$datatype]['func'];
		$context['xmlnews_uid'] = $uid;
		$context['xmlnews_limit'] = !empty($modSettings['export_rate']) ? $modSettings['export_rate'] : 250;
		$context['xmlnews_offset'] = 0;
		$context[$datatype . '_start'] = $start[$datatype];
		$feed_meta = array_fill_keys(array('title', 'desc', 'source', 'self'), '');
		$datatypes = array_keys($included);

		// Fake a wee bit of $user_info so that loading the member data & language doesn't choke.
		$user_info = $member_info;

		loadEssentialThemeData();
		$settings['actual_theme_dir'] = $settings['theme_dir'];
		$context['user']['language'] = $lang;
		loadMemberData($uid);
		loadLanguage(implode('+', array('index', 'Modifications', 'Stats', $included[$datatype]['langfile'])), $lang);

		// @todo Ask lawyers whether the GDPR requires us to include posts in the recycle bin.
		$query_this_board = '{query_see_board}' . (!empty($modSettings['recycle_enable']) && $modSettings['recycle_board'] > 0 ? ' AND b.id_board != ' . $modSettings['recycle_board'] : '');

		// We need a valid export directory.
		if (empty($modSettings['export_dir']) || !file_exists($modSettings['export_dir']))
		{
			require_once($sourcedir . '/Profile-Actions.php');
			if (create_export_dir() === false)
				return;
		}

		$realfilename = hash_hmac('sha1', $uid, get_auth_secret());
		$realfilepath = $modSettings['export_dir'] . DIRECTORY_SEPARATOR . $realfilename . '.xml';
		$tempfilepath = $realfilepath. '.tmp';
		$progressfile = $realfilepath . '.progress.json';

		// If the file is missing, create one with just the header and footer.
		if (!file_exists($tempfilepath))
		{
			buildXmlFeed('smf', array(), $feed_meta, 'profile');
			file_put_contents($tempfilepath, implode('', array($context['feed']['header'], $context['feed']['footer'])));

			$progress = array_fill_keys($datatypes, 0);
			file_put_contents($progressfile, $smcFunc['json_encode']($progress));
		}

		// Get the data, always in ascending order.
		$xml_data = call_user_func($included[$datatype]['func'], 'smf', true);

		// Build the XML string from the data.
		buildXmlFeed('smf', $xml_data, $feed_meta, $datatype);

		$last_item = end($xml_data);
		if (isset($last_item['content'][0]['content']) && $last_item['content'][0]['tag'] === 'id')
			$last_id = $last_item['content'][0]['content'];

		// Append the string (assuming there's enough disk space).
		$diskspace = disk_free_space($modSettings['export_dir']);
		$minspace = empty($modSettings['export_min_diskspace_pct']) ? 0 : disk_total_space($modSettings['export_dir']) * $modSettings['export_min_diskspace_pct'] / 100;
		if ($diskspace > $minspace && $diskspace > strlen($context['feed']['items']))
		{
			$handle = fopen($tempfilepath, 'r+');

			// Insert the new data before the feed footer.
			fseek($handle, strlen($context['feed']['footer']) * -1, SEEK_END);
			fwrite($handle, $context['feed']['items']);
			fwrite($handle, $context['feed']['footer']);

			fclose($handle);

			// Track progress by ID where appropriate, and by time otherwise.
			if (!isset($progress))
				$progress = $smcFunc['json_decode'](file_get_contents($progressfile), true);

			$progress[$datatype] = !isset($last_id) ? time() : $last_id;
			$datatype_done = !isset($last_id) ? true : $last_id >= $latest[$datatype];

			file_put_contents($progressfile, $smcFunc['json_encode']($progress));

			// Decide what to do next.
			if ($datatype_done)
			{
				$datatype_key = array_search($datatype, $datatypes);
				$done = !isset($datatypes[$datatype_key + 1]);

				if (!$done)
				{
					$datatype = $datatypes[$datatype_key + 1];
					$context[$datatype . '_start'] = 0;
				}
			}
			else
				$context[$datatype . '_start'] = $progress[$datatype];
		}

		if (!empty($done))
			rename($tempfilepath, $realfilepath);
		else
		{
			$start[$datatype] = $context[$datatype . '_start'];

			$data = $smcFunc['json_encode'](array(
				'format' => 'XML',
				'uid' => $uid,
				'lang' => $lang,
				'included' => $included,
				'start' => $start,
				'latest' => $latest,
				'datatype' => $datatype,
			));

			$smcFunc['db_insert']('insert', '{db_prefix}background_tasks',
				array('task_file' => 'string-255', 'task_class' => 'string-255', 'task_data' => 'string', 'claimed_time' => 'int'),
				array('$sourcedir/tasks/ExportProfileData.php', 'ExportProfileData_Background', $data, time() - MAX_CLAIM_THRESHOLD),
				array()
			);
		}
	}

	public static function pre_parsebbc(&$message, &$smileys, &$cache_id, &$parse_tags)
	{
		global $modSettings, $context;

		$smileys = false;
		$cache_id = '';

		if (!isset($modSettings['disabledBBC']))
			$modSettings['disabledBBC'] = '';

		$context['real_disabledBBC'] = $modSettings['disabledBBC'];

		// "O, that way madness lies; let me shun that; No more of that."
		if (strpos($modSettings['disabledBBC'], 'attach') === false)
			$modSettings['disabledBBC'] = implode(',', array_merge(array_filter(explode(',', $modSettings['disabledBBC'])), array('attach')));
	}

	public static function post_parsebbc(&$message, &$smileys, &$cache_id, &$parse_tags)
	{
		global $modSettings, $context;

		if (isset($context['real_disabledBBC']))
			$modSettings['disabledBBC'] = $context['real_disabledBBC'];
	}
}

?>