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
	 * Some private variables to help the static functions in this class.
	 */
	private static $export_details = array();
	private static $real_modSettings = array();
	private static $xslt_info = array();

	/**
	 * This is the main dispatcher for the class.
	 * It calls the correct private function based on the information stored in
	 * the task details.
	 *
	 * @return bool Always returns true
	 */
	public function execute()
	{
		global $sourcedir;

		if (!defined('EXPORTING'))
			define('EXPORTING', 1);

		// This could happen if the user manually changed the URL params of the export request.
		if ($this->_details['format'] == 'HTML' && (!class_exists('DOMDocument') || !class_exists('XSLTProcessor')))
		{
			require_once($sourcedir . DIRECTORY_SEPARATOR . 'Profile-Export.php');
			$export_formats = get_export_formats();

			$this->_details['format'] = 'XML_XSLT';
			$this->_details['format_settings'] = $export_formats['XML_XSLT'];
		}

		// Inform static functions of the export format, etc.
		self::$export_details = $this->_details;

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
		add_integration_function('integrate_bbc_codes', 'ExportProfileData_Background::bbc_codes', false);
		add_integration_function('integrate_post_parseAttachBBC', 'ExportProfileData_Background::post_parseAttachBBC', false);
		add_integration_function('integrate_attach_bbc_validate', 'ExportProfileData_Background::attach_bbc_validate', false);

		// We currently support exporting to XML and HTML
		if ($this->_details['format'] == 'XML')
			$this->exportXml($member_info);
		elseif ($this->_details['format'] == 'HTML')
			$this->exportHtml($member_info);
		elseif ($this->_details['format'] == 'XML_XSLT')
			$this->exportXmlXslt($member_info);

		return true;
	}

	/**
	 * The workhorse of this class. Compiles profile data to XML files.
	 *
	 * @param array $member_info Minimal $user_info about the relevant member.
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
		$context['user']['id'] = $uid;
		$context['user']['language'] = $lang;
		loadMemberData($uid);
		loadLanguage(implode('+', array_unique(array('index', 'Modifications', 'Stats', 'Profile', $included[$datatype]['langfile']))), $lang);

		// @todo Ask lawyers whether the GDPR requires us to include posts in the recycle bin.
		$query_this_board = '{query_see_board}' . (!empty($modSettings['recycle_enable']) && $modSettings['recycle_board'] > 0 ? ' AND b.id_board != ' . $modSettings['recycle_board'] : '');

		// We need a valid export directory.
		if (empty($modSettings['export_dir']) || !file_exists($modSettings['export_dir']))
		{
			require_once($sourcedir . DIRECTORY_SEPARATOR . 'Profile-Export.php');
			if (create_export_dir() === false)
				return;
		}

		$export_dir_slash = $modSettings['export_dir'] . DIRECTORY_SEPARATOR;

		$idhash = hash_hmac('sha1', $uid, get_auth_secret());
		$idhash_ext = $idhash . '.' . $this->_details['format_settings']['extension'];

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
		if (!file_exists($tempfile) || filesize($tempfile) == 0 || !file_exists($progressfile) || filesize($progressfile) == 0)
		{
			foreach (array_merge(array($tempfile, $progressfile), glob($export_dir_slash . '*_' . $idhash_ext)) as $fpath)
				@unlink($fpath);

			buildXmlFeed('smf', array(), $feed_meta, 'profile');
			file_put_contents($tempfile, implode('', array($context['feed']['header'], $context['feed']['footer'])));

			$progress = array_fill_keys($datatypes, 0);
			file_put_contents($progressfile, $smcFunc['json_encode']($progress));
		}
		else
			$progress = $smcFunc['json_decode'](file_get_contents($progressfile), true);

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
		{
			// For XML exports, things are easy.
			if (in_array($this->_details['format'], array('XML', 'XML_XSLT')))
				rename($tempfile, $realfile);

			// For other formats, keep a copy of tempfile in case we need to append more data later.
			else
			{
				copy($tempfile, $realfile);
				rename($tempfile, $tempfile . '.bak');
			}
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

			$data = $smcFunc['json_encode'](array(
				'format' => $this->_details['format'],
				'uid' => $uid,
				'lang' => $lang,
				'included' => $included,
				'start' => $start,
				'latest' => $latest,
				'datatype' => $datatype,
				'format_settings' => $this->_details['format_settings'],
			));

			$smcFunc['db_insert']('insert', '{db_prefix}background_tasks',
				array('task_file' => 'string-255', 'task_class' => 'string-255', 'task_data' => 'string', 'claimed_time' => 'int'),
				array('$sourcedir/tasks/ExportProfileData.php', 'ExportProfileData_Background', $data, time() - MAX_CLAIM_THRESHOLD + $delay),
				array()
			);
		}

		file_put_contents($progressfile, $smcFunc['json_encode']($progress));
	}

	/**
	 * Compiles profile data to HTML.
	 *
	 * Internally calls exportXml() and then uses an XSLT stylesheet to
	 * transform the XML files into HTML.
	 *
	 * @param array $member_info Minimal $user_info about the relevant member.
	 */
	protected function exportHtml($member_info)
	{
		global $modSettings, $context, $smcFunc, $sourcedir;

		// Perform the export to XML.
		$this->exportXml($member_info);

		// Determine which files, if any, are ready to be transformed.
		$export_dir_slash = $modSettings['export_dir'] . DIRECTORY_SEPARATOR;
		$idhash = hash_hmac('sha1', $this->_details['uid'], get_auth_secret());
		$idhash_ext = $idhash . '.' . $this->_details['format_settings']['extension'];

		$files_to_transform = array();
		foreach (glob($export_dir_slash . '*_' . $idhash_ext) as $completed_file)
		{
			if (file_get_contents($completed_file, false, null, 0, 6) == '<?xml ')
				$files_to_transform[] = $completed_file;
		}
		if (empty($files_to_transform))
			return;

		// We have work to do, so get the XSLT.
		require_once($sourcedir . DIRECTORY_SEPARATOR . 'Profile-Export.php');
		self::$xslt_info = get_xslt_stylesheet($this->_details['format'], $this->_details['uid']);

		// Set up the XSLT processor.
		$xslt = new DOMDocument();
		$xslt->loadXML(self::$xslt_info['stylesheet']);
		$xsltproc = new XSLTProcessor();
		$xsltproc->importStylesheet($xslt);

		// Transform the files to HTML.
		$xmldoc = new DOMDocument();
		foreach ($files_to_transform as $exportfilepath)
		{
			$xmldoc->load($exportfilepath);
			$xsltproc->transformToURI($xmldoc, $exportfilepath);
		}
	}

	/**
	 * Compiles profile data to XML with embedded XSLT.
	 *
	 * Internally calls exportXml() and then embeds an XSLT stylesheet into
	 * the XML so that it can be processed by the client.
	 *
	 * @param array $member_info Minimal $user_info about the relevant member.
	 */
	protected function exportXmlXslt($member_info)
	{
		global $modSettings, $context, $smcFunc, $sourcedir;

		// Embedded XSLT requires adding a special DTD and processing instruction in the main XML document.
		add_integration_function('integrate_xml_data', 'ExportProfileData_Background::add_dtd', false);

		// Perform the export to XML.
		$this->exportXml($member_info);

		// Find the completed files, if any.
		$export_dir_slash = $modSettings['export_dir'] . DIRECTORY_SEPARATOR;
		$idhash = hash_hmac('sha1', $this->_details['uid'], get_auth_secret());
		$idhash_ext = $idhash . '.' . $this->_details['format_settings']['extension'];

		$completed_files = glob($export_dir_slash . '*_' . $idhash_ext);
		if (empty($completed_files))
			return;

		// We have work to do, so get the XSLT.
		require_once($sourcedir . DIRECTORY_SEPARATOR . 'Profile-Export.php');
		self::$xslt_info = get_xslt_stylesheet($this->_details['format'], $this->_details['uid']);

		// Embedding the XSLT means writing to the file yet again.
		foreach ($completed_files as $exportfilepath)
		{
			$handle = fopen($exportfilepath, 'r+');
			if (is_resource($handle))
			{
				fseek($handle, strlen($context['feed']['footer']) * -1, SEEK_END);

				$bytes_written = fwrite($handle, self::$xslt_info['stylesheet'] . $context['feed']['footer']);

				// If we couldn't write everything, revert the changes.
				if ($bytes_written > 0 && $bytes_written < strlen(self::$xslt_info['stylesheet'] . $context['feed']['footer']))
				{
					fseek($handle, $bytes_written * -1, SEEK_END);
					$pointer_pos = ftell($handle);
					ftruncate($handle, $pointer_pos);
					rewind($handle);
					fseek($handle, 0, SEEK_END);
					fwrite($handle, $context['feed']['footer']);
				}

				fclose($handle);
			}
		}
	}

	/**
	 * Adds a custom DOCTYPE definition and an XSLT processing instruction to
	 * the main XML file's header.
	 */
	public static function add_dtd(&$xml_data, &$feed_meta, &$namespaces, &$extraFeedTags, &$forceCdataKeys, &$nsKeys, $xml_format, $subaction, &$dtd)
	{
		global $sourcedir;

		require_once($sourcedir . DIRECTORY_SEPARATOR . 'Profile-Export.php');
		self::$xslt_info = get_xslt_stylesheet(self::$export_details['format'], self::$export_details['uid']);

		$dtd = self::$xslt_info['dtd'];
	}

	/**
	 * Adjusts some parse_bbc() parameters for the special case of exports.
	 */
	public static function pre_parsebbc(&$message, &$smileys, &$cache_id, &$parse_tags)
	{
		global $modSettings, $context, $user_info;

		$cache_id = '';

		if (in_array(self::$export_details['format'], array('HTML', 'XML_XSLT')))
		{
			foreach (array('smileys_url', 'attachmentThumbnails') as $var)
				if (isset($modSettings[$var]))
					self::$real_modSettings[$var] = $modSettings[$var];

			$modSettings['smileys_url'] = '.';
			$modSettings['attachmentThumbnails'] = false;
		}
		else
		{
			$smileys = false;

			if (!isset($modSettings['disabledBBC']))
				$modSettings['disabledBBC'] = 'attach';
			else
			{
				self::$real_modSettings['disabledBBC'] = $modSettings['disabledBBC'];

				if (strpos($modSettings['disabledBBC'], 'attach') === false)
					$modSettings['disabledBBC'] = implode(',', array_merge(array_filter(explode(',', $modSettings['disabledBBC'])), array('attach')));
			}
		}
	}

	/**
	 * Reverses changes made by pre_parsebbc()
	 */
	public static function post_parsebbc(&$message, &$smileys, &$cache_id, &$parse_tags)
	{
		global $modSettings, $context;

		foreach (array('disabledBBC', 'smileys_url', 'attachmentThumbnails') as $var)
			if (isset(self::$real_modSettings[$var]))
				$modSettings[$var] = self::$real_modSettings[$var];
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
		global $scripturl, $context;
		static $dltokens;

		if (empty($dltokens[$context['xmlnews_uid']]))
		{
			$idhash = hash_hmac('sha1', $context['xmlnews_uid'], get_auth_secret());
			$dltokens[$context['xmlnews_uid']] = hash_hmac('sha1', $idhash, get_auth_secret());
		}

		$attachContext['orig_href'] = $scripturl . '?action=profile;area=dlattach;u=' . $context['xmlnews_uid'] . ';attach=' . $attachContext['id'] . ';t=' . $dltokens[$context['xmlnews_uid']];
		$attachContext['href'] = rawurlencode($attachContext['id'] . ' - ' . html_entity_decode($attachContext['name']));
	}

	/**
	 * Adjusts the format of the HTML produced by the attach BBCode.
	 */
	public static function attach_bbc_validate(&$returnContext, $currentAttachment, $tag, $data, $disabled, $params)
	{
		global $smcFunc, $txt;

		$orig_link = '<a href="' . $currentAttachment['orig_href'] . '" class="bbc_link">' . $txt['export_download_original'] . '</a>';
		$hidden_orig_link = ' <a href="' . $currentAttachment['orig_href'] . '" class="bbc_link dlattach_' . $currentAttachment['id'] . '" style="display:none; flex: 1 0 auto; margin: auto;">' . $txt['export_download_original'] . '</a>';

		if ($params['{display}'] == 'link')
		{
			$returnContext = ' (' . $orig_link . ')';
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