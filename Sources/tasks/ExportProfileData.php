<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2023 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 1
 */

namespace SMF\Tasks;

use SMF\Config;
use SMF\Lang;
use SMF\TaskRunner;
use SMF\Theme;
use SMF\User;
use SMF\Utils;
use SMF\Actions\Feed;
use SMF\Cache\CacheApi;
use SMF\Db\DatabaseApi as Db;

/**
 * @todo Find a way to throttle the export rate dynamically when dealing with
 * truly enormous amounts of data. Specifically, if the dataset contains lots
 * of posts that are ridiculously large, one or another part of the system
 * might choke.
 */

/**
 * This class contains code used to incrementally export a member's profile data
 * to one or more downloadable files.
 */
class ExportProfileData extends BackgroundTask
{
	/**
	 * @var array A copy of $this->_details for access by the static functions
	 * called from integration hooks.
	 *
	 * Even though this info is unique to a specific instance of this class, we
	 * can get away with making this variable static because only one instance
	 * of this class exists at a time.
	 */
	private static $export_details = array();

	/**
	 * @var array Temporary backup of the Config::$modSettings array
	 */
	private static $real_modSettings = array();

	/**
	 * @var array The XSLT stylesheet used to transform the XML into HTML and
	 * a (possibly empty) DOCTYPE declaration to insert into the source XML.
	 *
	 * Even though this info is unique to a specific instance of this class, we
	 * can get away with making this variable static because only one instance
	 * of this class exists at a time.
	 */
	private static $xslt_info = array('stylesheet' => '', 'doctype' => '');

	/**
	 * @var array Info to create a follow-up background task, if necessary.
	 */
	private $next_task = array();

	/**
	 * @var array Used to ensure we exit long running tasks cleanly.
	 */
	private $time_limit = 30;

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

		// Avoid leaving files in an inconsistent state.
		ignore_user_abort(true);

		$this->time_limit = (ini_get('safe_mode') === false && @set_time_limit(Taskrunner::MAX_CLAIM_THRESHOLD) !== false) ? Taskrunner::MAX_CLAIM_THRESHOLD : ini_get('max_execution_time');

		// This could happen if the user manually changed the URL params of the export request.
		if ($this->_details['format'] == 'HTML' && (!class_exists('DOMDocument') || !class_exists('XSLTProcessor')))
		{
			require_once(Config::$sourcedir . '/Actions/Profile/Export.php');
			$export_formats = get_export_formats();

			$this->_details['format'] = 'XML_XSLT';
			$this->_details['format_settings'] = $export_formats['XML_XSLT'];
		}

		// Inform static functions of the export format, etc.
		self::$export_details = $this->_details;

		// TaskRunner class doesn't create a User::$me, but this job needs one.
		User::load($this->_details['uid'], User::LOAD_BY_ID, 'profile');
		User::setMe($this->_details['uid']);

		// For exports only, members can always see their own posts, even in boards that they can no longer access.
		User::$me->buddies = array();
		User::$me->query_see_board = '1=1';
		User::$me->query_see_message_board = '1=1';
		User::$me->query_see_topic_board = '1=1';
		User::$me->query_wanna_see_board = '1=1';
		User::$me->query_wanna_see_message_board = '1=1';
		User::$me->query_wanna_see_topic_board = '1=1';

		// Use some temporary integration hooks to manipulate BBC parsing during export.
		foreach (array('pre_parsebbc', 'post_parsebbc', 'bbc_codes', 'post_parseAttachBBC', 'attach_bbc_validate') as $hook)
			add_integration_function('integrate_' . $hook, __CLASS__ . '::' . $hook, false);

		// Perform the export.
		if ($this->_details['format'] == 'XML')
			$this->exportXml();

		elseif ($this->_details['format'] == 'HTML')
			$this->exportHtml();

		elseif ($this->_details['format'] == 'XML_XSLT')
			$this->exportXmlXslt();

		// If necessary, create a new background task to continue the export process.
		if (!empty($this->next_task))
		{
			Db::$db->insert('insert', '{db_prefix}background_tasks',
				array('task_file' => 'string-255', 'task_class' => 'string-255', 'task_data' => 'string', 'claimed_time' => 'int'),
				$this->next_task,
				array()
			);
		}

		ignore_user_abort(false);

		return true;
	}

	/**
	 * The workhorse of this class. Compiles profile data to XML files.
	 */
	protected function exportXml()
	{
		// For convenience...
		$uid = $this->_details['uid'];
		$lang = $this->_details['lang'];
		$included = $this->_details['included'];
		$start = $this->_details['start'];
		$latest = $this->_details['latest'];
		$datatype = $this->_details['datatype'];

		if (!isset($included[$datatype]['func']) || !isset($included[$datatype]['langfile']))
			return;

		// Setup.
		$done = false;
		$delay = 0;
		$datatypes = array_keys($included);

		$feed = new Feed($datatype, $uid);
		$feed->format = 'smf';
		$feed->ascending = true;
		$feed->limit = !empty(Config::$modSettings['export_rate']) ? Config::$modSettings['export_rate'] : 250;
		$feed->start_after = $start[$datatype];

		Theme::loadEssential();
		Theme::$current->settings['actual_theme_dir'] = Theme::$current->settings['theme_dir'];
		User::$me->language = $lang;
		Lang::load(implode('+', array_unique(array('index', 'Modifications', 'Stats', 'Profile', $included[$datatype]['langfile']))), $lang);

		// @todo Ask lawyers whether the GDPR requires us to include posts in the recycle bin.
		$feed->query_this_board = '{query_see_message_board}' . (!empty(Config::$modSettings['recycle_enable']) && Config::$modSettings['recycle_board'] > 0 ? ' AND m.id_board != ' . Config::$modSettings['recycle_board'] : '');

		// We need a valid export directory.
		if (empty(Config::$modSettings['export_dir']) || !is_dir(Config::$modSettings['export_dir']) || !smf_chmod(Config::$modSettings['export_dir']))
		{
			require_once(Config::$sourcedir . '/Actions/Profile/Export.php');
			if (create_export_dir() === false)
				return;
		}

		$export_dir_slash = Config::$modSettings['export_dir'] . DIRECTORY_SEPARATOR;

		$idhash = hash_hmac('sha1', $uid, Config::getAuthSecret());
		$idhash_ext = $idhash . '.' . $this->_details['format_settings']['extension'];

		// Increment the file number until we reach one that doesn't exist.
		$filenum = 1;
		$realfile = $export_dir_slash . $filenum . '_' . $idhash_ext;
		while (file_exists($realfile))
			$realfile = $export_dir_slash . ++$filenum . '_' . $idhash_ext;

		$tempfile = $export_dir_slash . $idhash_ext . '.tmp';
		$progressfile = $export_dir_slash . $idhash_ext . '.progress.json';

		$feed->metadata = array(
			'title' => sprintf(Lang::$txt['profile_of_username'], User::$me->name),
			'desc' => Lang::sentenceList(array_map(
				function ($datatype)
				{
					return Lang::$txt[$datatype];
				},
				array_keys($included)
			)),
			'author' => Config::$mbname,
			'source' => Config::$scripturl . '?action=profile;u=' . $uid,
			'language' => !empty(Lang::$txt['lang_locale']) ? str_replace("_", "-", substr(Lang::$txt['lang_locale'], 0, strcspn(Lang::$txt['lang_locale'], "."))) : 'en',
			'self' => '', // Unused, but can't be null.
			'page' => &$filenum,
		);

		// Some paranoid hosts disable or hamstring the disk space functions in an attempt at security via obscurity.
		$check_diskspace = !empty(Config::$modSettings['export_min_diskspace_pct']) && function_exists('disk_free_space') && function_exists('disk_total_space') && intval(@disk_total_space(Config::$modSettings['export_dir']) >= 1440);
		$minspace = $check_diskspace ? ceil(disk_total_space(Config::$modSettings['export_dir']) * Config::$modSettings['export_min_diskspace_pct'] / 100) : 0;

		// If a necessary file is missing, we need to start over.
		if (!file_exists($tempfile) || !file_exists($progressfile) || filesize($progressfile) == 0)
		{
			foreach (array_merge(array($tempfile, $progressfile), glob($export_dir_slash . '*_' . $idhash_ext)) as $fpath)
				@unlink($fpath);

			$filenum = 1;
			$realfile = $export_dir_slash . $filenum . '_' . $idhash_ext;

			Feed::build('smf', array(), $feed->metadata, 'profile');
			file_put_contents($tempfile, implode('', Utils::$context['feed']), LOCK_EX);

			$progress = array_fill_keys($datatypes, 0);
			file_put_contents($progressfile, Utils::jsonEncode($progress));
		}
		else
			$progress = Utils::jsonDecode(file_get_contents($progressfile), true);

		// Get the data.
		$xml_data = call_user_func(array($feed, $included[$datatype]['func']));

		// No data retrieved? Just move on then.
		if (empty($xml_data))
		{
			$datatype_done = true;
		}
		// Basic profile data is quick and easy.
		elseif ($datatype == 'profile')
		{
			Feed::build('smf', $xml_data, $feed->metadata, 'profile');
			file_put_contents($tempfile, implode('', Utils::$context['feed']), LOCK_EX);

			$progress[$datatype] = time();
			$datatype_done = true;

			// Cache for subsequent reuse.
			$profile_basic_items = Utils::$context['feed']['items'];
			CacheApi::put('export_profile_basic-' . $uid, $profile_basic_items, Taskrunner::MAX_CLAIM_THRESHOLD);
		}
		// Posts and PMs...
		else
		{
			// We need the basic profile data in every export file.
			$profile_basic_items = CacheApi::get('export_profile_basic-' . $uid, Taskrunner::MAX_CLAIM_THRESHOLD);
			if (empty($profile_basic_items))
			{
				$profile_data = call_user_func(array($feed, $included['profile']['func']));
				Feed::build('smf', $profile_data, $feed->metadata, 'profile');
				$profile_basic_items = Utils::$context['feed']['items'];
				CacheApi::put('export_profile_basic-' . $uid, $profile_basic_items, Taskrunner::MAX_CLAIM_THRESHOLD);
				unset(Utils::$context['feed']);
			}

			$per_page = $this->_details['format_settings']['per_page'];
			$prev_item_count = empty($this->_details['item_count']) ? 0 : $this->_details['item_count'];

			// If the temp file has grown enormous, save it so we can start a new one.
			clearstatcache();
			if (file_exists($tempfile) && filesize($tempfile) >= 1024 * 1024 * 250)
			{
				rename($tempfile, $realfile);
				$realfile = $export_dir_slash . ++$filenum . '_' . $idhash_ext;

				if (empty(Utils::$context['feed']['header']))
					Feed::build('smf', array(), $feed->metadata, 'profile');

				file_put_contents($tempfile, implode('', array(Utils::$context['feed']['header'], $profile_basic_items, Utils::$context['feed']['footer'])), LOCK_EX);

				$prev_item_count = 0;
			}

			// Split $xml_data into reasonably sized chunks.
			if (empty($prev_item_count))
			{
				$xml_data = array_chunk($xml_data, $per_page);
			}
			else
			{
				$first_chunk = array_splice($xml_data, 0, $per_page - $prev_item_count);
				$xml_data = array_merge(array($first_chunk), array_chunk($xml_data, $per_page));
				unset($first_chunk);
			}

			foreach ($xml_data as $chunk => $items)
			{
				unset($new_item_count, $last_id);

				// Remember the last item so we know where to start next time.
				$last_item = end($items);
				if (isset($last_item['content'][0]['content']) && $last_item['content'][0]['tag'] === 'id')
					$last_id = $last_item['content'][0]['content'];

				// Build the XML string from the data.
				Feed::build('smf', $items, $feed->metadata, 'profile');

				// If disk space is insufficient, pause for a day so the admin can fix it.
				if ($check_diskspace && disk_free_space(Config::$modSettings['export_dir']) - $minspace <= strlen(implode('', Utils::$context['feed']) . self::$xslt_info['stylesheet']))
				{
					Lang::load('Errors');
					log_error(sprintf(Lang::$txt['export_low_diskspace'], Config::$modSettings['export_min_diskspace_pct']));

					$delay = 86400;
				}
				else
				{
					// We need a file to write to, of course.
					if (!file_exists($tempfile))
						file_put_contents($tempfile, implode('', array(Utils::$context['feed']['header'], $profile_basic_items, Utils::$context['feed']['footer'])), LOCK_EX);

					// Insert the new data before the feed footer.
					$handle = fopen($tempfile, 'r+');
					if (is_resource($handle))
					{
						flock($handle, LOCK_EX);

						fseek($handle, strlen(Utils::$context['feed']['footer']) * -1, SEEK_END);

						$bytes_written = fwrite($handle, Utils::$context['feed']['items'] . Utils::$context['feed']['footer']);

						// If we couldn't write everything, revert the changes and consider the write to have failed.
						if ($bytes_written > 0 && $bytes_written < strlen(Utils::$context['feed']['items'] . Utils::$context['feed']['footer']))
						{
							fseek($handle, $bytes_written * -1, SEEK_END);
							$pointer_pos = ftell($handle);
							ftruncate($handle, $pointer_pos);
							rewind($handle);
							fseek($handle, 0, SEEK_END);
							fwrite($handle, Utils::$context['feed']['footer']);

							$bytes_written = false;
						}

						flock($handle, LOCK_UN);
						fclose($handle);
					}

					// Write failed. We'll try again next time.
					if (empty($bytes_written))
					{
						$delay = Taskrunner::MAX_CLAIM_THRESHOLD;
						break;
					}

					// All went well.
					else
					{
						// Track progress by ID where appropriate, and by time otherwise.
						$progress[$datatype] = !isset($last_id) ? time() : $last_id;
						file_put_contents($progressfile, Utils::jsonEncode($progress));

						// Are we done with this datatype yet?
						if (!isset($last_id) || (count($items) < $per_page && $last_id >= $latest[$datatype]))
							$datatype_done = true;

						// Finished the file for this chunk, so move on to the next one.
						if (count($items) >= $per_page - $prev_item_count)
						{
							rename($tempfile, $realfile);
							$realfile = $export_dir_slash . ++$filenum . '_' . $idhash_ext;
							$prev_item_count = $new_item_count = 0;
						}
						// This was the last chunk.
						else
						{
							// Should we append more items to this file next time?
							$new_item_count = isset($last_id) ? $prev_item_count + count($items) : 0;
						}
					}
				}
			}
		}

		if (!empty($datatype_done))
		{
			$datatype_key = array_search($datatype, $datatypes);
			$done = !isset($datatypes[$datatype_key + 1]);

			if (!$done)
				$datatype = $datatypes[$datatype_key + 1];
		}

		// Remove the .tmp extension from the final tempfile so the system knows it's done.
		if (!empty($done))
		{
			rename($tempfile, $realfile);
		}

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

			$new_details = array(
				'format' => $this->_details['format'],
				'uid' => $uid,
				'lang' => $lang,
				'included' => $included,
				'start' => $start,
				'latest' => $latest,
				'datatype' => $datatype,
				'format_settings' => $this->_details['format_settings'],
				'last_page' => $this->_details['last_page'],
				'dlfilename' => $this->_details['dlfilename'],
			);
			if (!empty($new_item_count))
				$new_details['item_count'] = $new_item_count;

			$this->next_task = array(__FILE__, __CLASS__, Utils::jsonEncode($new_details), time() - Taskrunner::MAX_CLAIM_THRESHOLD + $delay);

			if (!file_exists($tempfile))
			{
				Feed::build('smf', array(), $feed->metadata, 'profile');
				file_put_contents($tempfile, implode('', array(Utils::$context['feed']['header'], !empty($profile_basic_items) ? $profile_basic_items : '', Utils::$context['feed']['footer'])), LOCK_EX);
			}
		}

		file_put_contents($progressfile, Utils::jsonEncode($progress));
	}

	/**
	 * Compiles profile data to HTML.
	 *
	 * Internally calls exportXml() and then uses an XSLT stylesheet to
	 * transform the XML files into HTML.
	 */
	protected function exportHtml()
	{
		Utils::$context['export_last_page'] = $this->_details['last_page'];
		Utils::$context['export_dlfilename'] = $this->_details['dlfilename'];

		// Perform the export to XML.
		$this->exportXml();

		// Determine which files, if any, are ready to be transformed.
		$export_dir_slash = Config::$modSettings['export_dir'] . DIRECTORY_SEPARATOR;
		$idhash = hash_hmac('sha1', $this->_details['uid'], Config::getAuthSecret());
		$idhash_ext = $idhash . '.' . $this->_details['format_settings']['extension'];

		$new_exportfiles = array();
		foreach (glob($export_dir_slash . '*_' . $idhash_ext) as $completed_file)
		{
			if (file_get_contents($completed_file, false, null, 0, 6) == '<?xml ')
				$new_exportfiles[] = $completed_file;
		}
		if (empty($new_exportfiles))
			return;

		// Get the XSLT stylesheet.
		require_once(Config::$sourcedir . '/Actions/Profile/Export.php');
		self::$xslt_info = get_xslt_stylesheet($this->_details['format'], $this->_details['uid']);

		// Set up the XSLT processor.
		$xslt = new DOMDocument();
		$xslt->loadXML(self::$xslt_info['stylesheet']);
		$xsltproc = new XSLTProcessor();
		$xsltproc->importStylesheet($xslt);

		$libxml_options = 0;
		foreach (array('LIBXML_COMPACT', 'LIBXML_PARSEHUGE', 'LIBXML_BIGLINES') as $libxml_option)
			if (defined($libxml_option))
				$libxml_options = $libxml_options | constant($libxml_option);

		// Transform the files to HTML.
		$i = 0;
		$num_files = count($new_exportfiles);
		$max_transform_time = 0;
		$xmldoc = new DOMDocument();
		foreach ($new_exportfiles as $exportfile)
		{
			if (function_exists('apache_reset_timeout'))
				@apache_reset_timeout();

			$started = microtime(true);
			$xmldoc->load($exportfile, $libxml_options);
			$xsltproc->transformToURI($xmldoc, $exportfile);
			$finished = microtime(true);

			$max_transform_time = max($max_transform_time, $finished - $started);

			// When deadlines loom, sometimes the best solution is procrastination.
			if (++$i < $num_files && TIME_START + $this->time_limit < $finished + $max_transform_time * 2)
			{
				// After all, there's always next time.
				if (empty($this->next_task))
				{
					$progressfile = $export_dir_slash . $idhash_ext . '.progress.json';

					$new_details = $this->_details;
					$new_details['start'] = Utils::jsonDecode(file_get_contents($progressfile), true);

					$this->next_task = array(__FILE__, __CLASS__, Utils::jsonEncode($new_details), time() - Taskrunner::MAX_CLAIM_THRESHOLD);
				}

				// So let's just relax and take a well deserved...
				break;
			}
		}
	}

	/**
	 * Compiles profile data to XML with embedded XSLT.
	 *
	 * Internally calls exportXml() and then embeds an XSLT stylesheet into
	 * the XML so that it can be processed by the client.
	 */
	protected function exportXmlXslt()
	{
		Utils::$context['export_last_page'] = $this->_details['last_page'];
		Utils::$context['export_dlfilename'] = $this->_details['dlfilename'];

		// Embedded XSLT requires adding a special DTD and processing instruction in the main XML document.
		add_integration_function('integrate_xml_data', __CLASS__ . '::add_dtd', false);

		// Perform the export to XML.
		$this->exportXml();

		// Make sure we have everything we need.
		if (empty(self::$xslt_info['stylesheet']))
		{
			require_once(Config::$sourcedir . '/Actions/Profile/Export.php');
			self::$xslt_info = get_xslt_stylesheet($this->_details['format'], $this->_details['uid']);
		}
		if (empty(Utils::$context['feed']['footer']))
		{
			Feed::build('smf', array(), array_fill_keys(array('title', 'desc', 'source', 'self'), ''), 'profile');
		}

		// Find any completed files that don't yet have the stylesheet embedded in them.
		$export_dir_slash = Config::$modSettings['export_dir'] . DIRECTORY_SEPARATOR;
		$idhash = hash_hmac('sha1', $this->_details['uid'], Config::getAuthSecret());
		$idhash_ext = $idhash . '.' . $this->_details['format_settings']['extension'];

		$test_length = strlen(self::$xslt_info['stylesheet'] . Utils::$context['feed']['footer']);

		$new_exportfiles = array();
		clearstatcache();
		foreach (glob($export_dir_slash . '*_' . $idhash_ext) as $completed_file)
		{
			if (filesize($completed_file) < $test_length || file_get_contents($completed_file, false, null, $test_length * -1) !== self::$xslt_info['stylesheet'] . Utils::$context['feed']['footer'])
				$new_exportfiles[] = $completed_file;
		}
		if (empty($new_exportfiles))
			return;

		// Embedding the XSLT means writing to the file yet again.
		foreach ($new_exportfiles as $exportfile)
		{
			$handle = fopen($exportfile, 'r+');
			if (is_resource($handle))
			{
				flock($handle, LOCK_EX);

				fseek($handle, strlen(Utils::$context['feed']['footer']) * -1, SEEK_END);

				$bytes_written = fwrite($handle, self::$xslt_info['stylesheet'] . Utils::$context['feed']['footer']);

				// If we couldn't write everything, revert the changes.
				if ($bytes_written > 0 && $bytes_written < strlen(self::$xslt_info['stylesheet'] . Utils::$context['feed']['footer']))
				{
					fseek($handle, $bytes_written * -1, SEEK_END);
					$pointer_pos = ftell($handle);
					ftruncate($handle, $pointer_pos);
					rewind($handle);
					fseek($handle, 0, SEEK_END);
					fwrite($handle, Utils::$context['feed']['footer']);
				}

				flock($handle, LOCK_UN);
				fclose($handle);
			}
		}
	}

	/**
	 * Adds a custom DOCTYPE definition and an XSLT processing instruction to
	 * the main XML file's header.
	 */
	public static function add_dtd(&$xml_data, &$metadata, &$namespaces, &$extraFeedTags, &$forceCdataKeys, &$nsKeys, $xml_format, $subaction, &$doctype)
	{
		require_once(Config::$sourcedir . '/Actions/Profile/Export.php');
		self::$xslt_info = get_xslt_stylesheet(self::$export_details['format'], self::$export_details['uid']);

		$doctype = self::$xslt_info['doctype'];
	}

	/**
	 * Adjusts some parse_bbc() parameters for the special case of exports.
	 */
	public static function pre_parsebbc(&$message, &$smileys, &$cache_id, &$parse_tags, &$cache_key_extras)
	{
		$cache_id = '';

		$cache_key_extras[__CLASS__] = 1;

		if (in_array(self::$export_details['format'], array('HTML', 'XML_XSLT')))
		{
			foreach (array('smileys_url', 'attachmentThumbnails') as $var)
				if (isset(Config::$modSettings[$var]))
					self::$real_modSettings[$var] = Config::$modSettings[$var];

			Config::$modSettings['smileys_url'] = '.';
			Config::$modSettings['attachmentThumbnails'] = false;
		}
		else
		{
			$smileys = false;

			if (!isset(Config::$modSettings['disabledBBC']))
				Config::$modSettings['disabledBBC'] = 'attach';
			else
			{
				self::$real_modSettings['disabledBBC'] = Config::$modSettings['disabledBBC'];

				if (strpos(Config::$modSettings['disabledBBC'], 'attach') === false)
					Config::$modSettings['disabledBBC'] = implode(',', array_merge(array_filter(explode(',', Config::$modSettings['disabledBBC'])), array('attach')));
			}
		}
	}

	/**
	 * Reverses changes made by pre_parsebbc()
	 */
	public static function post_parsebbc(&$message, &$smileys, &$cache_id, &$parse_tags)
	{
		foreach (array('disabledBBC', 'smileys_url', 'attachmentThumbnails') as $var)
			if (isset(self::$real_modSettings[$var]))
				Config::$modSettings[$var] = self::$real_modSettings[$var];
	}

	/**
	 * Adjusts certain BBCodes for the special case of exports.
	 */
	public static function bbc_codes(&$codes, &$no_autolink_tags)
	{
		foreach ($codes as &$code)
		{
			// To make the "Select" link work we'd need to embed a bunch more JS. Not worth it.
			if ($code['tag'] === 'code')
				$code['content'] = preg_replace('~<a class="codeoperation\b.*?</a>~', '', $code['content']);
		}
	}

	/**
	 * Adjusts the attachment download URL for the special case of exports.
	 */
	public static function post_parseAttachBBC(&$attachContext)
	{
		static $dltokens;

		if (empty($dltokens[Utils::$context['xmlnews_uid']]))
		{
			$idhash = hash_hmac('sha1', Utils::$context['xmlnews_uid'], Config::getAuthSecret());
			$dltokens[Utils::$context['xmlnews_uid']] = hash_hmac('sha1', $idhash, Config::getAuthSecret());
		}

		$attachContext['orig_href'] = Config::$scripturl . '?action=profile;area=dlattach;u=' . Utils::$context['xmlnews_uid'] . ';attach=' . $attachContext['id'] . ';t=' . $dltokens[Utils::$context['xmlnews_uid']];
		$attachContext['href'] = rawurlencode($attachContext['id'] . ' - ' . html_entity_decode($attachContext['name']));
	}

	/**
	 * Adjusts the format of the HTML produced by the attach BBCode.
	 */
	public static function attach_bbc_validate(&$returnContext, $currentAttachment, $tag, $data, $disabled, $params)
	{
		$orig_link = '<a href="' . $currentAttachment['orig_href'] . '" class="bbc_link">' . Lang::$txt['export_download_original'] . '</a>';
		$hidden_orig_link = ' <a href="' . $currentAttachment['orig_href'] . '" class="bbc_link dlattach_' . $currentAttachment['id'] . '" style="display:none; flex: 1 0 auto; margin: auto;">' . Lang::$txt['export_download_original'] . '</a>';

		if ($params['{display}'] == 'link')
		{
			$returnContext .= ' (' . $orig_link . ')';
		}
		elseif (!empty($currentAttachment['is_image']))
		{
			$returnContext = '<span style="display: inline-flex; justify-content: center; align-items: center; position: relative;">' . preg_replace(
				array(
					'thumbnail_toggle' => '~</?a\b[^>]*>~',
					'src' => '~src="' . preg_quote($currentAttachment['href'], '~') . ';image"~',
				),
				array(
					'thumbnail_toggle' => '',
					'src' => 'src="' . $currentAttachment['href'] . '" onerror="$(\'.dlattach_' . $currentAttachment['id'] . '\').show(); $(\'.dlattach_' . $currentAttachment['id'] . '\').css({\'position\': \'absolute\'});"',
				),
				$returnContext
			) . $hidden_orig_link . '</span>' ;
		}
		elseif (strpos($currentAttachment['mime_type'], 'video/') === 0)
		{
			$returnContext = preg_replace(
				array(
					'src' => '~src="' . preg_quote($currentAttachment['href'], '~') . '"~',
					'opening_tag' => '~^<div class="videocontainer"~',
					'closing_tag' => '~</div>$~',
				),
				array(
					'src' => '$0 onerror="$(this).fadeTo(0, 0.2); $(\'.dlattach_' . $currentAttachment['id'] . '\').show(); $(\'.dlattach_' . $currentAttachment['id'] . '\').css({\'position\': \'absolute\'});"',
					'opening_tag' => '<div class="videocontainer" style="display: flex; justify-content: center; align-items: center; position: relative;"',
					'closing_tag' =>  $hidden_orig_link . '</div>',
				),
				$returnContext
			);
		}
		elseif (strpos($currentAttachment['mime_type'], 'audio/') === 0)
		{
			$returnContext = '<span style="display: inline-flex; justify-content: center; align-items: center; position: relative;">' . preg_replace(
				array(
					'opening_tag' => '~^<audio\b~',
				),
				array(
					'opening_tag' => '<audio onerror="$(this).fadeTo(0, 0); $(\'.dlattach_' . $currentAttachment['id'] . '\').show(); $(\'.dlattach_' . $currentAttachment['id'] . '\').css({\'position\': \'absolute\'});"',
				),
				$returnContext
			) . $hidden_orig_link . '</span>';
		}
		else
		{
			$returnContext = '<span style="display: inline-flex; justify-content: center; align-items: center; position: relative;">' . preg_replace(
				array(
					'obj_opening' => '~^<object\b~',
					'link' => '~<a href="' . preg_quote($currentAttachment['href'], '~') . '" class="bbc_link">([^<]*)</a>~',
				),
				array(
					'obj_opening' => '<object onerror="$(this).fadeTo(0, 0.2); $(\'.dlattach_' . $currentAttachment['id'] . '\').show(); $(\'.dlattach_' . $currentAttachment['id'] . '\').css({\'position\': \'absolute\'});"~',
					'link' => '$0 (' . $orig_link . ')',
				),
				$returnContext
			) . $hidden_orig_link . '</span>';
		}
	}
}

?>