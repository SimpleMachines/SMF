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
		global $context;

		if (!defined('EXPORTING'))
			define('EXPORTING', 1);

		$context['export_format'] = $this->_details['format'];

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

		// Make sure this has been loaded for use in News.php.
		if (!function_exists('cleanXml'))
			require_once($sourcedir . DIRECTORY_SEPARATOR . 'QueryString.php');

		// Setup.
		$done = false;
		$func = $included[$datatype]['func'];
		$context['xmlnews_uid'] = $uid;
		$context['xmlnews_limit'] = !empty($modSettings['export_rate']) ? $modSettings['export_rate'] : 250;
		$context['xmlnews_offset'] = 0;
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
			require_once($sourcedir . DIRECTORY_SEPARATOR . 'Profile-Actions.php');
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
		{
			// For plain XML exports, things are easy.
			if ($this->_details['format'] == 'XML')
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
	 * Internally calls exportXml() and then uses XSLT to transform the XML
	 * files into HTML.
	 *
	 * If the local PHP installation doesn't have XSLT support enabled, a
	 * fallback approach is used to embed the XSLT stylesheet directly into the
	 * XML file so that the user's browser can apply it locally when the export
	 * file is opened.
	 *
	 * @param array $member_info Minimal $user_info about the relevant member.
	 */
	protected function exportHtml($member_info)
	{
		global $modSettings, $context, $smcFunc, $sourcedir;

		if (!function_exists('JavaScriptEscape'))
			require_once($sourcedir . DIRECTORY_SEPARATOR . 'QueryString.php');

		$embedded = !class_exists('DOMDocument') || !class_exists('XSLTProcessor');

		// Embedded XSLT requires adding a special DTD in the main XML document.
		if ($embedded)
			add_integration_function('integrate_xml_data', 'ExportProfileData_Background::add_dtd', false);

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
		list($stylesheet, $dtd) = self::getXsltStylesheet($this->_details['format'], $embedded);

		// Transforming on the server is pretty straightforward.
		if (!$embedded)
		{
			// Set up the XSLT processor.
			$xslt = new DOMDocument();
			$xslt->loadXML($stylesheet);
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
		// Embedding the XSLT means writing to the file yet again.
		else
		{
			foreach ($files_to_transform as $exportfilepath)
			{
				$handle = fopen($exportfilepath, 'r+');
				if (is_resource($handle))
				{
					fseek($handle, strlen($context['feed']['footer']) * -1, SEEK_END);

					$bytes_written = fwrite($handle, $stylesheet . $context['feed']['footer']);

					// If we couldn't write everything, revert the changes and consider the write to have failed.
					if ($bytes_written > 0 && $bytes_written < strlen($stylesheet . $context['feed']['footer']))
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
			}
		}
	}

	/**
	 * Adds a DOCTYPE declaration and XSLT processing instruction to the main XML file header.
	 */
	public static function add_dtd(&$xml_data, &$feed_meta, &$namespaces, &$extraFeedTags, &$forceCdataKeys, &$nsKeys, $xml_format, $subaction, &$dtd)
	{
		list($stylesheet, $dtd) = self::getXsltStylesheet($this->_details['format'], true);
	}

	/**
	 * Adjusts some parse_bbc() parameters for the special case of exports.
	 */
	public static function pre_parsebbc(&$message, &$smileys, &$cache_id, &$parse_tags)
	{
		global $modSettings, $context, $user_info;

		$cache_id = '';

		if ($context['export_format'] == 'HTML')
		{
			foreach (array('smileys_url', 'attachmentThumbnails') as $var)
				if (isset($modSettings[$var]))
					$context['real_modSettings'][$var] = $modSettings[$var];

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
				$context['real_modSettings']['disabledBBC'] = $modSettings['disabledBBC'];

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
			if (isset($context['real_modSettings'][$var]))
				$modSettings[$var] = $context['real_modSettings'][$var];
	}

	/**
	 * Adjusts the behaviour of certain BBCodes for the special case of exports.
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

	/**
	 * Provides an XSLT stylesheet to transform an XML-based profile export file
	 * into the desired output format.
	 *
	 * @param string $format The desired output format. Currently only accepts 'HTML'.
	 * @param bool $embedded Whether the XSLT will be embedded in the output. Default false.
	 * @return array The XSLT stylesheet and possibly a DTD to insert into the source document.
	 */
	public static function getXsltStylesheet($format, $embedded = false)
	{
		global $context, $txt, $settings, $modSettings, $sourcedir, $forum_copyright;

		require_once($sourcedir . DIRECTORY_SEPARATOR . 'News.php');

		$stylesheet = array();

		$smf_ns = 'htt'.'p:/'.'/ww'.'w.simple'.'machines.o'.'rg/xml/profile';
		$xslt_ns = 'htt'.'p:/'.'/ww'.'w.w3.o'.'rg/1999/XSL/Transform';
		$html_ns = 'htt'.'p:/'.'/ww'.'w.w3.o'.'rg/1999/xhtml';

		if ($format == 'HTML')
		{
			/* Notes:
			 * 1. Values can be simple strings or raw XML, including other XSLT
			 *    statements or even calls to entire XSLT templates.
			 * 2. Always set 'no_cdata_parse' to true when the value is raw XML.
			 *
			 * A word to PHP coders: Do not let the term "variable" mislead you.
			 * XSLT variables are roughly equivalent to PHP constants rather
			 * than PHP variables; once the value has been set, it is immutable.
			 * Keeping this in mind may spare you from some confusion and
			 * frustration while working with XSLT.
			 */
			$xslt_variables = array(
				'scripturl' => array(
					'value' => '<xsl:value-of select="/*/@forum-url"/>',
					'no_cdata_parse' => true,
				),
				'themeurl' => array(
					'value' => $settings['default_theme_url'],
				),
				'member-id' => array(
					'value' => $context['xmlnews_uid'],
				),
				'copyright' => array(
					'value' => sprintf($forum_copyright, SMF_FULL_VERSION, SMF_SOFTWARE_YEAR),
				),
				'txt-summary-heading' => array(
					'value' => $txt['summary'],
				),
				'txt-posts-heading' => array(
					'value' => $txt['posts'],
				),
				'txt-personal-messages-heading' => array(
					'value' => $txt['personal_messages'],
				),
				'txt-view-source-button' => array(
					'value' => $txt['export_view_source_button'],
				),
				'txt-download-original' => array(
					'value' => $txt['export_download_original'],
				),
				'txt-help' => array(
					'value' => $txt['help'],
				),
				'txt-terms-rules' => array(
					'value' => $txt['terms_and_rules'],
				),
				'txt-go-up' => array(
					'value' => $txt['go_up'],
				),
			);

			// Let mods adjust the XSLT variables.
			call_integration_hook('integrate_export_xslt_variables', array(&$xslt_variables, $format, $embedded));

			$idhash = hash_hmac('sha1', $context['xmlnews_uid'], get_auth_secret());
			$xslt_variables['dltoken'] = array(
				'value' => hash_hmac('sha1', $idhash, get_auth_secret())
			);

			if ($embedded)
			{
				$dtd = implode("\n", array(
					'<!--',
					"\t" . $txt['export_open_in_browser'],
					'-->',
					'<?xml-stylesheet type="text/xsl" href="#stylesheet"?>',
					'<!DOCTYPE smf:xml-feed [',
					'<!ATTLIST xsl:stylesheet',
					'id ID #REQUIRED>',
					']>',
				));

				$stylesheet['header'] = implode("\n", array(
					'<xsl:stylesheet version="1.0" xmlns:xsl="' . $xslt_ns . '" xmlns:html="' . $html_ns . '" xmlns:smf="' . $smf_ns . '" exclude-result-prefixes="smf html" id="stylesheet">',
					'',
					"\t\t\t" . '<xsl:template match="xsl:stylesheet"/>',
					"\t\t\t" . '<xsl:template match="xsl:stylesheet" mode="detailedinfo"/>',
				));
			}
			else
			{
				$dtd = '';
				$stylesheet['header'] = implode("\n", array(
					'<?xml version="1.0" encoding="' . $context['character_set'] . '"?' . '>',
					'<xsl:stylesheet version="1.0" xmlns:xsl="' . $xslt_ns . '" xmlns:html="' . $html_ns . '" xmlns:smf="' . $smf_ns . '" exclude-result-prefixes="smf html">',
				));
			}

			// Output control settings.
			$stylesheet['output_control'] = '
			<xsl:output method="html" encoding="utf-8" indent="yes"/>
			<xsl:strip-space elements="*"/>';

			// Insert the XSLT variables.
			$stylesheet['variables'] = '';
			foreach ($xslt_variables as $name => $var)
			{
				$stylesheet['variables'] .= '
				<xsl:variable name="' . $name . '">' . (!empty($var['no_cdata_parse']) ? $var['value'] : cdata_parse($var['value'])) . '</xsl:variable>';
			}

			// The top-level template. Creates the shell of the HTML document.
			$stylesheet['html'] = '
			<xsl:template match="/*">
				<xsl:text disable-output-escaping="yes">&lt;!DOCTYPE html></xsl:text>
				<html>
					<head>
						<title>
							<xsl:value-of select="@title"/>
						</title>
						<xsl:call-template name="css_js"/>
					</head>
					<body>
						<div id="footerfix">
							<div id="header">
								<h1 class="forumtitle">
									<a id="top">
										<xsl:attribute name="href">
											<xsl:value-of select="$scripturl"/>
										</xsl:attribute>
										<xsl:value-of select="@forum-name"/>
									</a>
								</h1>
							</div>
							<div id="wrapper">
								<div id="upper_section">
									<div id="inner_section">
										<div id="inner_wrap">
											<div class="user">
												<time>
													<xsl:attribute name="datetime">
														<xsl:value-of select="@generated-date-UTC"/>
													</xsl:attribute>
													<xsl:value-of select="@generated-date-localized"/>
												</time>
											</div>
											<hr class="clear"/>
										</div>
									</div>
								</div>

								<xsl:call-template name="content_section"/>

							</div>
						</div>
						<div id="footer">
							<div class="inner_wrap">
								<ul>
									<li class="floatright">
										<a>
											<xsl:attribute name="href">
												<xsl:value-of select="concat($scripturl, \'?action=help\')"/>
											</xsl:attribute>
											<xsl:value-of select="$txt-help"/>
										</a>
										<xsl:text> | </xsl:text>
										<a>
											<xsl:attribute name="href">
												<xsl:value-of select="concat($scripturl, \'?action=help;sa=rules\')"/>
											</xsl:attribute>
											<xsl:value-of select="$txt-terms-rules"/>
										</a>
										<xsl:text> | </xsl:text>
										<a href="#top">
											<xsl:value-of select="$txt-go-up"/>
											<xsl:text> &#9650;</xsl:text>
										</a>
									</li>
									<li class="copyright">
										<xsl:value-of select="$copyright" disable-output-escaping="yes"/>
									</li>
								</ul>
							</div>
						</div>
					</body>
				</html>
			</xsl:template>';

			// Template to show the content of the export file.
			$stylesheet['content_section'] = '
			<xsl:template name="content_section">
				<div id="content_section">
					<div id="main_content_section">

						<div class="cat_bar">
							<h3 class="catbg">
								<xsl:value-of select="@title"/>
							</h3>
						</div>
						<div class="information">
							<h2 class="display_title">
								<xsl:value-of select="@description"/>
							</h2>
						</div>

						<xsl:if test="username">
							<div class="cat_bar">
								<h3 class="catbg">
									<xsl:value-of select="$txt-summary-heading"/>
								</h3>
							</div>
							<div id="profileview" class="roundframe flow_auto noup">
								<xsl:call-template name="summary"/>
							</div>
						</xsl:if>

						<xsl:if test="member-post">
							<div class="cat_bar">
								<h3 class="catbg">
									<xsl:value-of select="$txt-posts-heading"/>
								</h3>
							</div>
							<div id="posts" class="roundframe flow_auto noup">
								<xsl:apply-templates select="member-post" mode="posts"/>
							</div>
						</xsl:if>

						<xsl:if test="personal-message">
							<div class="cat_bar">
								<h3 class="catbg">
									<xsl:value-of select="$txt-personal-messages-heading"/>
								</h3>
							</div>
							<div id="personal_messages" class="roundframe flow_auto noup">
								<xsl:apply-templates select="personal-message" mode="pms"/>
							</div>
						</xsl:if>

					</div>
				</div>
			</xsl:template>';

			// Template for user profile summary
			$stylesheet['summary'] = '
			<xsl:template name="summary">
				<div id="basicinfo">
					<div class="username clear">
						<h4>
							<a>
								<xsl:attribute name="href">
									<xsl:value-of select="link"/>
								</xsl:attribute>
								<xsl:value-of select="name"/>
							</a>
							<xsl:text> </xsl:text>
							<span class="position">
								<xsl:choose>
									<xsl:when test="position">
										<xsl:value-of select="position"/>
									</xsl:when>
									<xsl:otherwise>
										<xsl:value-of select="post-group"/>
									</xsl:otherwise>
								</xsl:choose>
							</span>
						</h4>
					</div>
					<img class="avatar">
						<xsl:attribute name="src">
							<xsl:value-of select="avatar"/>
						</xsl:attribute>
					</img>
				</div>

				<div id="detailedinfo">
					<dl class="settings noborder">
						<xsl:apply-templates mode="detailedinfo"/>
					</dl>
				</div>
			</xsl:template>';

			// Some helper templates for details inside the summary.
			$stylesheet['detail_default'] = '
			<xsl:template match="*" mode="detailedinfo">
				<dt>
					<xsl:value-of select="concat(@label, \':\')"/>
				</dt>
				<dd>
					<xsl:value-of select="." disable-output-escaping="yes"/>
				</dd>
			</xsl:template>';

			$stylesheet['detail_email'] = '
			<xsl:template match="email" mode="detailedinfo">
				<dt>
					<xsl:value-of select="concat(@label, \':\')"/>
				</dt>
				<dd>
					<a>
						<xsl:attribute name="href">
							<xsl:text>mailto:</xsl:text>
							<xsl:value-of select="."/>
						</xsl:attribute>
						<xsl:value-of select="."/>
					</a>
				</dd>
			</xsl:template>';

			$stylesheet['detail_website'] = '
			<xsl:template match="website" mode="detailedinfo">
				<dt>
					<xsl:value-of select="concat(@label, \':\')"/>
				</dt>
				<dd>
					<a>
						<xsl:attribute name="href">
							<xsl:value-of select="link"/>
						</xsl:attribute>
						<xsl:value-of select="title"/>
					</a>
				</dd>
			</xsl:template>';

			$stylesheet['detail_ip'] = '
			<xsl:template match="ip_addresses" mode="detailedinfo">
				<dt>
					<xsl:value-of select="concat(@label, \':\')"/>
				</dt>
				<dd>
					<ul class="nolist">
						<xsl:apply-templates mode="ip_address"/>
					</ul>
				</dd>
			</xsl:template>
			<xsl:template match="*" mode="ip_address">
				<li>
					<xsl:value-of select="."/>
					<xsl:if test="@label and following-sibling">
						<xsl:text> </xsl:text>
						<span>(<xsl:value-of select="@label"/>)</span>
					</xsl:if>
				</li>
			</xsl:template>';

			$stylesheet['detail_not_included'] = '
			<xsl:template match="name|link|avatar|online|member-post|personal-message" mode="detailedinfo"/>';

			// Template for printing a single post
			$stylesheet['member_post'] = '
			<xsl:template match="member-post" mode="posts">
				<div>
					<xsl:attribute name="id">
						<xsl:value-of select="concat(\'member-post-\', id)"/>
					</xsl:attribute>
					<xsl:attribute name="class">
						<xsl:choose>
							<xsl:when test="approval-status = 1">
								<xsl:text>windowbg</xsl:text>
							</xsl:when>
							<xsl:otherwise>
								<xsl:text>approvebg</xsl:text>
							</xsl:otherwise>
						</xsl:choose>
					</xsl:attribute>

					<div class="post_wrapper">
						<div class="poster">
							<h4>
								<a>
									<xsl:attribute name="href">
										<xsl:value-of select="poster/link"/>
									</xsl:attribute>
									<xsl:value-of select="poster/name"/>
								</a>
							</h4>
							<ul class="user_info">
								<xsl:if test="poster/link = /*/@source">
									<xsl:call-template name="own-user-info"/>
								</xsl:if>
								<li>
									<xsl:value-of select="poster/email"/>
								</li>
								<li class="poster_ip">
									<xsl:value-of select="concat(poster/ip/@label, \': \')"/>
									<xsl:value-of select="poster/ip"/>
								</li>
							</ul>
						</div>

						<div class="postarea">
							<div class="flow_hidden">

								<div class="keyinfo">
									<h5>
										<strong>
											<a>
												<xsl:attribute name="href">
													<xsl:value-of select="board/link"/>
												</xsl:attribute>
												<xsl:value-of select="board/name"/>
											</a>
											<xsl:text> / </xsl:text>
											<a>
												<xsl:attribute name="href">
													<xsl:value-of select="link"/>
												</xsl:attribute>
												<xsl:value-of select="subject"/>
											</a>
										</strong>
									</h5>
									<span class="smalltext"><xsl:value-of select="time"/></span>
									<xsl:if test="modified_time">
										<span class="smalltext modified floatright mvisible em">
											<xsl:attribute name="id">
												<xsl:value-of select="concat(\'modified_\', id)"/>
											</xsl:attribute>
											<span class="lastedit">
												<xsl:value-of select="modified_time/@label"/>
											</span>
											<xsl:text>: </xsl:text>
											<xsl:value-of select="modified_time"/>
											<xsl:text>. </xsl:text>
											<xsl:value-of select="modified_by/@label"/>
											<xsl:text>: </xsl:text>
											<xsl:value-of select="modified_by"/>
											<xsl:text>. </xsl:text>
										</span>
									</xsl:if>
								</div>

								<div class="post">
									<div class="inner">
										<xsl:value-of select="body_html" disable-output-escaping="yes"/>
									</div>
									<div class="inner monospace" style="display:none;">
										<xsl:choose>
											<xsl:when test="contains(body/text(), \'[html]\')">
												<xsl:call-template name="bbc-html-splitter">
													<xsl:with-param name="bbc-string" select="body/text()"/>
													<xsl:with-param name="inside-outside" select="outside"/>
												</xsl:call-template>
											</xsl:when>
											<xsl:otherwise>
												<xsl:value-of select="body" disable-output-escaping="yes"/>
											</xsl:otherwise>
										</xsl:choose>
									</div>
								</div>

								<xsl:apply-templates select="attachments">
									<xsl:with-param name="post-id" select="id"/>
								</xsl:apply-templates>

								<div class="under_message">
									<ul class="floatleft">
										<xsl:if test="likes > 0">
											<li class="smflikebutton">
												<xsl:attribute name="id">
													<xsl:value-of select="concat(\'msg_\', id, \'_likes\')"/>
												</xsl:attribute>
												<span><span class="main_icons like"></span> <xsl:value-of select="likes"/></span>
											</li>
										</xsl:if>
									</ul>
									<xsl:call-template name="quickbuttons">
										<xsl:with-param name="toggle-target" select="concat(\'member-post-\', id)"/>
									</xsl:call-template>
								</div>

							</div>
						</div>

						<div class="moderatorbar">
							<xsl:if test="poster/link = /*/@source">
								<xsl:call-template name="signature"/>
							</xsl:if>
						</div>

					</div>
				</div>
			</xsl:template>';

			// Template for printing a single PM
			$stylesheet['personal_message'] = '
			<xsl:template match="personal-message" mode="pms">
				<div class="windowbg">
					<xsl:attribute name="id">
						<xsl:value-of select="concat(\'personal-message-\', id)"/>
					</xsl:attribute>

					<div class="post_wrapper">
						<div class="poster">
							<h4>
								<a>
									<xsl:attribute name="href">
										<xsl:value-of select="sender/link"/>
									</xsl:attribute>
									<xsl:value-of select="sender/name"/>
								</a>
							</h4>
							<ul class="user_info">
								<xsl:if test="sender/link = /*/@source">
									<xsl:call-template name="own-user-info"/>
								</xsl:if>
							</ul>
						</div>

						<div class="postarea">
							<div class="flow_hidden">

								<div class="keyinfo">
									<h5>
										<xsl:attribute name="id">
											<xsl:value-of select="concat(\'subject_\', id)"/>
										</xsl:attribute>
										<xsl:value-of select="subject"/>
									</h5>
									<span class="smalltext">
										<strong>
											<xsl:value-of select="concat(recipient[1]/@label, \': \')"/>
										</strong>
										<xsl:apply-templates select="recipient"/>
									</span>
									<br/>
									<span class="smalltext">
										<strong>
											<xsl:value-of select="concat(sent-date/@label, \': \')"/>
										</strong>
										<time>
											<xsl:attribute name="datetime">
												<xsl:value-of select="sent-date/@UTC"/>
											</xsl:attribute>
											<xsl:value-of select="normalize-space(sent-date)"/>
										</time>
									</span>
								</div>

								<div class="post">
									<div class="inner">
										<xsl:value-of select="body_html" disable-output-escaping="yes"/>
									</div>
									<div class="inner monospace" style="display:none;">
										<xsl:call-template name="bbc-html-splitter">
											<xsl:with-param name="bbc-string" select="body/text()"/>
											<xsl:with-param name="inside-outside" select="outside"/>
										</xsl:call-template>
									</div>
								</div>

								<div class="under_message">
									<xsl:call-template name="quickbuttons">
										<xsl:with-param name="toggle-target" select="concat(\'personal-message-\', id)"/>
									</xsl:call-template>
								</div>

							</div>
						</div>

						<div class="moderatorbar">
							<xsl:if test="sender/link = /*/@source">
								<xsl:call-template name="signature"/>
							</xsl:if>
						</div>

					</div>
				</div>
			</xsl:template>';

			// A couple of templates to handle attachments
			$stylesheet['attachments'] = implode('', array('
			<xsl:template match="attachments">
				<xsl:param name="post-id"/>
				<xsl:if test="attachment">
					<div class="attachments">
						<xsl:attribute name="id">
							<xsl:value-of select="concat(\'msg_\', $post-id, \'_footer\')"/>
						</xsl:attribute>
						<xsl:apply-templates/>
					</div>
				</xsl:if>
			</xsl:template>
			<xsl:template match="attachment">
				<div class="attached">
					<div class="attachments_bot">
						<a>
							<xsl:attribute name="href">
								<xsl:value-of select="concat(id, \' - \', name)"/>
							</xsl:attribute>
							<img class="centericon" alt="*">
								<xsl:attribute name="src">
									<xsl:value-of select="concat($themeurl, \'/images/icons/clip.png\')"/>
								</xsl:attribute>
							</img>
							<xsl:text> </xsl:text>
							<xsl:value-of select="name"/>
						</a>
						<br/>
						<xsl:text>(</xsl:text>
						<a class="bbc_link">
							<xsl:attribute name="href">
								<xsl:value-of select="concat($scripturl, \'?action=profile;area=dlattach;u=\', $member-id, \';attach=\', id, \';t=\', $dltoken)"/>
							</xsl:attribute>
							<xsl:value-of select="$txt-download-original"/>
						</a>
						<xsl:text>)</xsl:text>
						<br/>
						<xsl:value-of select="size/@label"/>
						<xsl:text>: </xsl:text>
						<xsl:value-of select="size"/>
						<br/>
						<xsl:value-of select="downloads/@label"/>
						<xsl:text>: </xsl:text>
						<xsl:value-of select="downloads"/>
					</div>
				</div>
			</xsl:template>',
			));

			// Helper template for printing the user's own info next to the post or personal message.
			$stylesheet['own_user_info'] = '
			<xsl:template name="own-user-info">
				<xsl:if test="/*/avatar">
					<li class="avatar">
						<a>
							<xsl:attribute name="href">
								<xsl:value-of select="/*/link"/>
							</xsl:attribute>
							<img class="avatar">
								<xsl:attribute name="src">
									<xsl:value-of select="/*/avatar"/>
								</xsl:attribute>
							</img>
						</a>
					</li>
				</xsl:if>
				<li class="membergroup">
					<xsl:value-of select="/*/position"/>
				</li>
				<xsl:if test="/*/title">
					<li class="title">
						<xsl:value-of select="/*/title"/>
					</li>
				</xsl:if>
				<li class="postgroup">
					<xsl:value-of select="/*/post-group"/>
				</li>
				<li class="postcount">
					<xsl:value-of select="concat(/*/posts/@label, \': \')"/>
					<xsl:value-of select="/*/posts"/>
				</li>
				<xsl:if test="/*/blurb">
					<li class="blurb">
						<xsl:value-of select="/*/blurb"/>
					</li>
				</xsl:if>
			</xsl:template>
			';

			// Helper template for printing the quickbuttons
			$stylesheet['quickbuttons'] = '
			<xsl:template name="quickbuttons">
				<xsl:param name="toggle-target"/>
				<ul class="quickbuttons quickbuttons_post sf-js-enabled sf-arrows" style="touch-action: pan-y;">
					<li>
						<a>
							<xsl:attribute name="onclick">
								<xsl:text>$(\'#</xsl:text>
								<xsl:value-of select="$toggle-target"/>
								<xsl:text> .inner\').toggle();</xsl:text>
							</xsl:attribute>
							<xsl:value-of select="$txt-view-source-button"/>
						</a>
					</li>
				</ul>
			</xsl:template>';

			// Helper template for printing a signature
			$stylesheet['signature'] = '
			<xsl:template name="signature">
				<xsl:if test="/*/signature">
					<div class="signature">
						<xsl:value-of select="/*/signature" disable-output-escaping="yes"/>
					</div>
				</xsl:if>
			</xsl:template>';

			// Helper template for printing a list of PM recipients
			$stylesheet['recipient'] = '
			<xsl:template match="recipient">
				<a>
					<xsl:attribute name="href">
						<xsl:value-of select="link"/>
					</xsl:attribute>
					<xsl:value-of select="name"/>
				</a>
				<xsl:choose>
					<xsl:when test="following-sibling::recipient">
						<xsl:text>, </xsl:text>
					</xsl:when>
					<xsl:otherwise>
						<xsl:text>. </xsl:text>
					</xsl:otherwise>
				</xsl:choose>
			</xsl:template>';

			// Helper template for special handling of the contents of the [html] BBCode
			$stylesheet['bbc_html'] = '
			<xsl:template name="bbc-html-splitter">
				<xsl:param name="bbc-string"/>
				<xsl:param name="inside-outside"/>
				<xsl:choose>
					<xsl:when test="$inside-outside = \'outside\'">
						<xsl:choose>
							<xsl:when test="contains($bbc-string, \'[html]\')">
								<xsl:variable name="following-string">
									<xsl:value-of select="substring-after($bbc-string, \'[html]\')" disable-output-escaping="yes"/>
								</xsl:variable>
								<xsl:value-of select="substring-before($bbc-string, \'[html]\')" disable-output-escaping="yes"/>
								<xsl:text>[html]</xsl:text>
								<xsl:call-template name="bbc-html-splitter">
									<xsl:with-param name="bbc-string" select="$following-string"/>
									<xsl:with-param name="inside-outside" select="inside"/>
								</xsl:call-template>
							</xsl:when>
							<xsl:otherwise>
								<xsl:value-of select="$bbc-string" disable-output-escaping="yes"/>
							</xsl:otherwise>
						</xsl:choose>
					</xsl:when>
					<xsl:otherwise>
						<xsl:choose>
							<xsl:when test="contains($bbc-string, \'[/html]\')">
								<xsl:variable name="following-string">
									<xsl:value-of select="substring-after($bbc-string, \'[/html]\')" disable-output-escaping="yes"/>
								</xsl:variable>
								<xsl:value-of select="substring-before($bbc-string, \'[/html]\')" disable-output-escaping="no"/>
								<xsl:text>[/html]</xsl:text>
								<xsl:call-template name="bbc-html-splitter">
									<xsl:with-param name="bbc-string" select="$following-string"/>
									<xsl:with-param name="inside-outside" select="outside"/>
								</xsl:call-template>
							</xsl:when>
							<xsl:otherwise>
								<xsl:value-of select="$bbc-string" disable-output-escaping="no"/>
							</xsl:otherwise>
						</xsl:choose>
					</xsl:otherwise>
				</xsl:choose>
			</xsl:template>';

			// Template to insert CSS and JavaScript
			$stylesheet['css_js'] = '
			<xsl:template name="css_js">';

			self::load_css_js();

			if (!empty($context['css_files']))
			{
				foreach ($context['css_files'] as $css_file)
				{
					$stylesheet['css_js'] .= '
					<link rel="stylesheet">
						<xsl:attribute name="href">
							<xsl:text>' . $css_file['fileUrl'] . '</xsl:text>
						</xsl:attribute>';

					if (!empty($css_file['options']['attributes']))
					{
						foreach ($css_file['options']['attributes'] as $key => $value)
							$stylesheet['css_js'] .= '
						<xsl:attribute name="' . $key . '">
							<xsl:text>' . (is_bool($value) ? $key : $value) . '</xsl:text>
						</xsl:attribute>';
					}

					$stylesheet['css_js'] .= '
					</link>';
				}
			}

			if (!empty($context['css_header']))
			{
				$stylesheet['css_js'] .=  '
				<style><![CDATA[' . "\n" . implode("\n", $context['css_header']) . "\n" . ']]>
				</style>';
			}

			if (!empty($context['javascript_vars']))
			{
				$stylesheet['css_js'] .=  '
				<script><![CDATA[';

				foreach ($context['javascript_vars'] as $var => $val)
					$stylesheet['css_js'] .= "\nvar " . $var . (!empty($val) ? ' = ' . $val : '') . ';';

				$stylesheet['css_js'] .= "\n" . ']]>
				</script>';
			}

			if (!empty($context['javascript_files']))
			{
				foreach ($context['javascript_files'] as $js_file)
				{
					$stylesheet['css_js'] .= '
					<script>
						<xsl:attribute name="src">
							<xsl:text>' . $js_file['fileUrl'] . '</xsl:text>
						</xsl:attribute>';

					if (!empty($js_file['options']['attributes']))
					{
						foreach ($js_file['options']['attributes'] as $key => $value)
							$stylesheet['css_js'] .= '
						<xsl:attribute name="' . $key . '">
							<xsl:text>' . (is_bool($value) ? $key : $value) . '</xsl:text>
						</xsl:attribute>';
					}

					$stylesheet['css_js'] .= '
					</script>';
				}
			}

			if (!empty($context['javascript_inline']['standard']))
			{
				$stylesheet['css_js'] .=  '
				<script><![CDATA[' . "\n" . implode("\n", $context['javascript_inline']['standard']) . "\n" . ']]>
				</script>';
			}

			if (!empty($context['javascript_inline']['defer']))
			{
				$stylesheet['css_js'] .= '
				<script><![CDATA[' . "\n" . 'window.addEventListener("DOMContentLoaded", function() {';

				$stylesheet['css_js'] .= "\n" . implode("\n", $context['javascript_inline']['defer']);

				$stylesheet['css_js'] .= "\n" . '});'. "\n" . ']]>
				</script>';
			}

			$stylesheet['css_js'] .= '
			</xsl:template>';

			// End of the XSLT stylesheet
			$stylesheet['footer'] = '</xsl:stylesheet>';
		}

		// Let mods adjust the XSLT stylesheet.
		call_integration_hook('integrate_export_xslt_stylesheet', array(&$stylesheet, $format, $embedded));

		return array(implode("\n", (array) $stylesheet), $dtd);
	}

	/**
	 * Loads and prepares CSS and JavaScript for insertion into an XSLT stylesheet.
	 */
	private static function load_css_js()
	{
		global $context, $modSettings, $sourcedir, $smcFunc, $user_info;

		// Autoloading is disabled in the command line interface, so we have to load this manually.
		$minimize_files = $modSettings['minimize_files'];
		if (!empty($minimize_files) && (!class_exists('MatthiasMullie\\Minify\\CSS') || !class_exists('MatthiasMullie\\Minify\\JS')))
		{
			require_once(implode(DIRECTORY_SEPARATOR, array($sourcedir, 'minify', 'src', 'Exception.php')));
			require_once(implode(DIRECTORY_SEPARATOR, array($sourcedir, 'minify', 'src', 'Exceptions', 'BasicException.php')));
			require_once(implode(DIRECTORY_SEPARATOR, array($sourcedir, 'minify', 'src', 'Exceptions', 'FileImportException.php')));
			require_once(implode(DIRECTORY_SEPARATOR, array($sourcedir, 'minify', 'src', 'Exceptions', 'IOException.php')));

			require_once(implode(DIRECTORY_SEPARATOR, array($sourcedir, 'minify', 'src', 'Minify.php')));
			require_once(implode(DIRECTORY_SEPARATOR, array($sourcedir, 'minify', 'path-converter', 'src', 'Converter.php')));

			require_once(implode(DIRECTORY_SEPARATOR, array($sourcedir, 'minify', 'src', 'CSS.php')));
			require_once(implode(DIRECTORY_SEPARATOR, array($sourcedir, 'minify', 'src', 'JS.php')));
		}
		if (!class_exists('MatthiasMullie\\Minify\\CSS') || !class_exists('MatthiasMullie\\Minify\\JS'))
			$minimize_files = false;

		// Load our standard CSS files.
		loadCSSFile('index.css', array('minimize' => true, 'order_pos' => 1), 'smf_index');
		loadCSSFile('responsive.css', array('force_current' => false, 'validate' => true, 'minimize' => true, 'order_pos' => 9000), 'smf_responsive');

		if ($context['right_to_left'])
			loadCSSFile('rtl.css', array('order_pos' => 4000), 'smf_rtl');

		// In case any mods added relevant CSS.
		// Suppress all errors in case a mod makes assumptions it shouldn't.
		$old_error_reporting = error_reporting(0);
		call_integration_hook('integrate_pre_css_output');
		error_reporting($old_error_reporting);

		// This next chunk mimics some of template_css()
		$css_to_minify = array();
		$normal_css_files = array();

		usort($context['css_files'], function ($a, $b)
		{
			return $a['options']['order_pos'] < $b['options']['order_pos'] ? -1 : ($a['options']['order_pos'] > $b['options']['order_pos'] ? 1 : 0);
		});
		foreach ($context['css_files'] as $css_file)
		{
			if (!isset($css_file['options']['minimize']))
				$css_file['options']['minimize'] = true;

			if (!empty($css_file['options']['minimize']) && !empty($minimize_files))
				$css_to_minify[] = $css_file;
			else
				$normal_css_files[] = $css_file;
		}

		$minified_css_files = !empty($css_to_minify) ? custMinify($css_to_minify, 'css') : array();

		$context['css_files'] = array();
		foreach (array_merge($minified_css_files, $normal_css_files) as $css_file)
		{
			// Embed the CSS in a <style> element if possible, since exports are supposed to be standalone files.
			if (file_exists($css_file['filePath']))
				$context['css_header'][] = file_get_contents($css_file['filePath']);

			elseif (!empty($css_file['fileUrl']))
				$context['css_files'][] = $css_file;
		}

		// Next, we need to do for JavaScript what we just did for CSS.
		loadJavaScriptFile('https://ajax.googleapis.com/ajax/libs/jquery/' . JQUERY_VERSION . '/jquery.min.js', array('external' => true), 'smf_jquery');

		$old_error_reporting = error_reporting(0);
		call_integration_hook('integrate_pre_javascript_output', array(false));
		call_integration_hook('integrate_pre_javascript_output', array(true));
		error_reporting($old_error_reporting);

		$js_to_minify = array();
		$all_js_files = array();

		foreach ($context['javascript_files'] as $js_file)
		{
			if (!empty($js_file['options']['minimize']) && !empty($minimize_files))
			{
				if (!empty($js_file['options']['async']))
					$js_to_minify['async'][] = $js_file;

				elseif (!empty($js_file['options']['defer']))
					$js_to_minify['defer'][] = $js_file;

				else
					$js_to_minify['standard'][] = $js_file;
			}
			else
				$all_js_files[] = $js_file;
		}

		$context['javascript_files'] = array();
		foreach ($js_to_minify as $type => $js_files)
		{
			if (!empty($js_files))
			{
				$minified_js_files = custMinify($js_files, 'js');
				$all_js_files = array_merge($all_js_files, $minified_js_files);
			}
		}

		foreach ($all_js_files as $js_file)
		{
			// As with the CSS, embed whatever JavaScript we can.
			if (file_exists($js_file['filePath']))
				$context['javascript_inline'][(!empty($js_file['options']['defer']) ? 'defer' : 'standard')][] = file_get_contents($js_file['filePath']);

			elseif (!empty($js_file['fileUrl']))
				$context['javascript_files'][] = $js_file;
		}

		// We need to embed the smiley images, too. To save space, we store the image data in JS variables.
		$smiley_mimetypes = array(
			'gif' => 'image/gif',
			'png' => 'image/png',
			'jpg' => 'image/jpeg',
			'jpeg' => 'image/jpeg',
			'tiff' => 'image/tiff',
			'svg' => 'image/svg+xml',
		);

		foreach (glob(implode(DIRECTORY_SEPARATOR, array($modSettings['smileys_dir'], $user_info['smiley_set'], '*.*'))) as $smiley_file)
		{
			$pathinfo = pathinfo($smiley_file);

			if (!isset($smiley_mimetypes[$pathinfo['extension']]))
				continue;

			$var = implode('_', array('smf', 'smiley', $pathinfo['filename'], $pathinfo['extension']));

			if (!isset($context['javascript_vars'][$var]))
				$context['javascript_vars'][$var] = '\'data:' . $smiley_mimetypes[$pathinfo['extension']] . ';base64,' . base64_encode(file_get_contents($smiley_file)) . '\'';
		}

		$context['javascript_inline']['defer'][] = implode("\n", array(
			'$("img.smiley").each(function() {',
			'	var data_uri_var = $(this).attr("src").replace(/.*\/(\w+)\.(\w+)$/, "smf_smiley_$1_$2");',
			'	$(this).attr("src", window[data_uri_var]);',
			'});',
		));
	}
}

?>