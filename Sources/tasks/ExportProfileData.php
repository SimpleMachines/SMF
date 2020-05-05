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

		// For exports only, members can always see their own posts, even in boards that they can no longer access.
		$member_info = $this->getMinUserInfo(array($this->_details['uid']));
		$member_info = array_merge($member_info[$this->_details['uid']], array(
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
			$this->exportXml($member_info);

		return true;
	}

	/**
	 * The workhorse of this class. Compiles profile data to XML files.
	 */
	protected function exportXml($member_info)
	{
		global $smcFunc, $sourcedir, $context, $modSettings, $settings, $user_info, $mbname;
		global $user_profile, $txt, $scripturl, $query_this_board;

		// For convenience...
		$uid = $this->_details['uid'];
		$lang = $this->_details['lang'];
		$included = $this->_details['included'];
		$start = $this->_details['start'];
		$latest = $this->_details['latest'];
		$datatype = $this->_details['datatype'];

		if (!isset($included[$datatype]['func']) || !isset($included[$datatype]['langfile']))
			return;

		require_once($sourcedir . DIRECTORY_SEPARATOR . 'News.php');
		require_once($sourcedir . DIRECTORY_SEPARATOR . 'ScheduledTasks.php');

		// Make sure this has been loaded for use in News.php.
		if (!function_exists('cleanXml'))
			require_once($sourcedir . DIRECTORY_SEPARATOR . 'QueryString.php');

		// Setup.
		$done = false;
		$func = $included[$datatype]['func'];
		$context['xmlnews_uid'] = $uid;
		$context['xmlnews_limit'] = !empty($modSettings['export_rate']) ? $modSettings['export_rate'] : 250;
		$context[$datatype . '_start'] = $start[$datatype];
		$datatypes = array_keys($included);

		// Fake a wee bit of $user_info so that loading the member data & language doesn't choke.
		$user_info = $member_info;

		loadEssentialThemeData();
		$settings['actual_theme_dir'] = $settings['theme_dir'];
		$context['user']['language'] = $lang;
		loadMemberData($uid);
		loadLanguage(implode('+', array_unique(array('index', 'Modifications', 'Stats', 'Profile', $included[$datatype]['langfile']))), $lang);

		// @todo Ask lawyers whether the GDPR requires us to include posts in the recycle bin.
		$query_this_board = '{query_see_board}' . (!empty($modSettings['recycle_enable']) && $modSettings['recycle_board'] > 0 ? ' AND b.id_board != ' . $modSettings['recycle_board'] : '');

		// We need a valid export directory.
		if (empty($modSettings['export_dir']) || !file_exists($modSettings['export_dir']))
		{
			require_once($sourcedir . DIRECTORY_SEPARATOR . 'Profile-Actions.php');
			if (create_export_dir() === false)
				return;
		}

		$export_dir_slash = $modSettings['export_dir'] . DIRECTORY_SEPARATOR;

		$idhash = hash_hmac('sha1', $uid, get_auth_secret());
		$idhash_ext = $idhash . '.xml';

		// Increment the file number until we reach one that doesn't exist.
		$filenum = 1;
		$realfile = $export_dir_slash . $filenum . '_' . $idhash_ext;
		while (file_exists($realfile))
			$realfile = $export_dir_slash . ++$filenum . '_' . $idhash_ext;

		$tempfile = $export_dir_slash . $idhash_ext . '.tmp';
		$progressfile = $export_dir_slash . $idhash_ext . '.progress.json';

		$feed_meta = array(
			'title' => sprintf($txt['profile_of_username'], $user_profile[$uid]['real_name']),
			'desc' => sentence_list(array_map(function ($datatype) use ($txt) { return $txt[$datatype]; }, array_keys($included))),
			'author' => $mbname,
			'source' => $scripturl . '?action=profile;u=' . $uid,
			'self' => $scripturl . '?action=profile;area=download;u=' . $uid . ';t=' . hash_hmac('sha1', $idhash, get_auth_secret()),
		);

		// If a necessary file is missing, we need to start over.
		if (!file_exists($progressfile) || !file_exists($tempfile))
		{
			foreach (array_merge(array($tempfile, $progressfile), glob($export_dir_slash . '*_' . $idhash_ext)) as $fpath)
				@unlink($fpath);

			buildXmlFeed('smf', array(), $feed_meta, 'profile');
			file_put_contents($tempfile, implode('', array($context['feed']['header'], $context['feed']['footer'])));
		}

		$progress = file_exists($progressfile) ? $smcFunc['json_decode'](file_get_contents($progressfile), true) : array_fill_keys($datatypes, 0);

		// Get the data, always in ascending order.
		$xml_data = call_user_func($included[$datatype]['func'], 'smf', true);

		// Build the XML string from the data.
		buildXmlFeed('smf', $xml_data, $feed_meta, $datatype);

		$last_item = end($xml_data);
		if (isset($last_item['content'][0]['content']) && $last_item['content'][0]['tag'] === 'id')
			$last_id = $last_item['content'][0]['content'];

		// Some paranoid hosts disable or hamstring the disk space functions in an attempt at security via obscurity.
		$diskspace = function_exists('disk_free_space') ? @disk_free_space($modSettings['export_dir']) : false;
		if (!is_int($diskspace))
			$diskspace = PHP_INT_MAX;

		if (empty($modSettings['export_min_diskspace_pct']))
			$minspace = 0;
		else
		{
			$totalspace = function_exists('disk_total_space') ? @disk_total_space($modSettings['export_dir']) : false;
			$minspace = intval($totalspace) < 1440 ? 0 : $totalspace * $modSettings['export_min_diskspace_pct'] / 100;
		}

		// Append the string (assuming there's enough disk space).
		if ($diskspace - $minspace > strlen($context['feed']['items']))
		{
			// If the temporary file has grown to 250MB, save it and start a new one.
			if (file_exists($tempfile) && (filesize($tempfile) + strlen($context['feed']['items'])) >= 1024 * 1024 * 250)
			{
				rename($tempfile, $realfile);
				$realfile = $export_dir_slash . ++$filenum . '_' . $idhash_ext;

				file_put_contents($tempfile, implode('', array($context['feed']['header'], $context['feed']['footer'])));
			}

			// Insert the new data before the feed footer.
			$handle = fopen($tempfile, 'r+');
			if (is_resource($handle))
			{
				fseek($handle, strlen($context['feed']['footer']) * -1, SEEK_END);

				$bytes_written = fwrite($handle, $context['feed']['items'] . $context['feed']['footer']);

				// If we couldn't write everything, revert the changes and consider the write to have failed.
				if ($bytes_written > 0 && $bytes_written < strlen($context['feed']['items'] . $context['feed']['footer']))
				{
					fseek($handle, $bytes_written * -1, SEEK_END);
					$pointer_pos = ftell($handle);
					ftruncate($handle, $pointer_pos);
					rewind($handle);
					fseek($handle, 0, SEEK_END);
					fwrite($handle, $context['feed']['footer']);

					$bytes_written = false;
				}

				fclose($handle);
			}

			// All went well.
			if (!empty($bytes_written))
			{
				// Track progress by ID where appropriate, and by time otherwise.
				$progress[$datatype] = !isset($last_id) ? time() : $last_id;

				// Decide what to do next.
				if (!isset($last_id) || $last_id >= $latest[$datatype])
				{
					$datatype_key = array_search($datatype, $datatypes);
					$done = !isset($datatypes[$datatype_key + 1]);

					if (!$done)
						$datatype = $datatypes[$datatype_key + 1];
				}

				$delay = 0;
			}
			// Write failed. We'll try again next time.
			else
				$delay = MAX_CLAIM_THRESHOLD;
		}
		// Not enough disk space, so pause for a day to give the admin a chance to fix it.
		else
			$delay = 86400;

		// Remove the .tmp extension so the system knows that the file is ready for download.
		if (!empty($done))
			rename($tempfile, $realfile);

		// Oops. Apparently some sneaky monkey cancelled the export while we weren't looking.
		elseif (!file_exists($progressfile))
		{
			@unlink($tempfile);
			return;
		}

		// We have more work to do again later.
		else
		{
			$start[$datatype] = $progress[$datatype];

			$data = $smcFunc['json_encode'](array(
				'format' => $this->_details['format'],
				'uid' => $uid,
				'lang' => $lang,
				'included' => $included,
				'start' => $start,
				'latest' => $latest,
				'datatype' => $datatype,
			));

			$smcFunc['db_insert']('insert', '{db_prefix}background_tasks',
				array('task_file' => 'string-255', 'task_class' => 'string-255', 'task_data' => 'string', 'claimed_time' => 'int'),
				array('$sourcedir/tasks/ExportProfileData.php', 'ExportProfileData_Background', $data, time() - MAX_CLAIM_THRESHOLD + $delay),
				array()
			);
		}

		file_put_contents($progressfile, $smcFunc['json_encode']($progress));
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