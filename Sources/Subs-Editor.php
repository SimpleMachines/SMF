<?php

/**
 * This file contains those functions specific to the editing box and is
 * generally used for WYSIWYG type functionality.
 * 
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2011 Simple Machines
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 Alpha 1
 */

if (!defined('SMF'))
	die('Hacking attempt...');

/**
 * Retrieves a list of message icons.
 * - Based on the settings, the array will either contain a list of default
 *   message icons or a list of custom message icons retrieved from the database.
 * - The board_id is needed for the custom message icons (which can be set for
 *   each board individually).
 * 
 * @param int $board_id
 * @return array
 */
function getMessageIcons($board_id)
{
	global $modSettings, $context, $txt, $settings, $smcFunc;

	if (empty($modSettings['messageIcons_enable']))
	{
		loadLanguage('Post');

		$icons = array(
			array('value' => 'xx', 'name' => $txt['standard']),
			array('value' => 'thumbup', 'name' => $txt['thumbs_up']),
			array('value' => 'thumbdown', 'name' => $txt['thumbs_down']),
			array('value' => 'exclamation', 'name' => $txt['excamation_point']),
			array('value' => 'question', 'name' => $txt['question_mark']),
			array('value' => 'lamp', 'name' => $txt['lamp']),
			array('value' => 'smiley', 'name' => $txt['icon_smiley']),
			array('value' => 'angry', 'name' => $txt['icon_angry']),
			array('value' => 'cheesy', 'name' => $txt['icon_cheesy']),
			array('value' => 'grin', 'name' => $txt['icon_grin']),
			array('value' => 'sad', 'name' => $txt['icon_sad']),
			array('value' => 'wink', 'name' => $txt['icon_wink']),
			array('value' => 'poll', 'name' => $txt['icon_poll']),
		);

		foreach ($icons as $k => $dummy)
		{
			$icons[$k]['url'] = $settings['images_url'] . '/post/' . $dummy['value'] . '.png';
			$icons[$k]['is_last'] = false;
		}
	}
	// Otherwise load the icons, and check we give the right image too...
	else
	{
		if (($temp = cache_get_data('posting_icons-' . $board_id, 480)) == null)
		{
			$request = $smcFunc['db_query']('select_message_icons', '
				SELECT title, filename
				FROM {db_prefix}message_icons
				WHERE id_board IN (0, {int:board_id})',
				array(
					'board_id' => $board_id,
				)
			);
			$icon_data = array();
			while ($row = $smcFunc['db_fetch_assoc']($request))
				$icon_data[] = $row;
			$smcFunc['db_free_result']($request);

			$icons = array();
			foreach ($icon_data as $icon)
			{
				$icons[$icon['filename']] = array(
					'value' => $icon['filename'],
					'name' => $icon['title'],
					'url' => $settings[file_exists($settings['theme_dir'] . '/images/post/' . $icon['filename'] . '.png') ? 'images_url' : 'default_images_url'] . '/post/' . $icon['filename'] . '.png',
					'is_last' => false,
				);
			}

			cache_put_data('posting_icons-' . $board_id, $icons, 480);
		}
		else
			$icons = $temp;
	}

	return array_values($icons);
}

/**
 * A help function for legalise_bbc for sorting arrays based on length.
 * @param string $a
 * @param string $b
 * @return int 1 or -1
 */
function sort_array_length($a, $b)
{
	return strlen($a) < strlen($b) ? 1 : -1;
}

/**
 * Compatibility function - used in 1.1 for showing a post box.
 * 
 * @param string $msg
 * @return string
 */
function theme_postbox($msg)
{
	global $context;

	return template_control_richedit($context['post_box_name']);
}

/**
 * Creates a box that can be used for richedit stuff like BBC, Smileys etc.
 * @param array $editorOptions
 */
function create_control_richedit($editorOptions)
{
	global $txt, $modSettings, $options, $smcFunc;
	global $context, $settings, $user_info, $sourcedir, $scripturl;

	// Load the Post language file... for the moment at least.
	loadLanguage('Post');

	// Every control must have a ID!
	assert(isset($editorOptions['id']));
	assert(isset($editorOptions['value']));

	// Is this the first richedit - if so we need to ensure some template stuff is initialised.
	if (empty($context['controls']['richedit']))
	{
		// Some general stuff.
		$settings['smileys_url'] = $modSettings['smileys_url'] . '/' . $user_info['smiley_set'];

		// This really has some WYSIWYG stuff.
		loadTemplate('GenericControls', isBrowser('ie') ? 'editor_ie' : 'editor');
		$context['html_headers'] .= '
		<script type="text/javascript"><!-- // --><![CDATA[
			var smf_smileys_url = \'' . $settings['smileys_url'] . '\';
			var oEditorStrings= {
				wont_work: \'' . addcslashes($txt['rich_edit_wont_work'], "'") . '\',
				func_disabled: \'' . addcslashes($txt['rich_edit_function_disabled'], "'") . '\',
				prompt_text_email: \'' . addcslashes($txt['prompt_text_email'], "'") . '\',
				prompt_text_ftp: \'' . addcslashes($txt['prompt_text_ftp'], "'") . '\',
				prompt_text_url: \'' . addcslashes($txt['prompt_text_url'], "'") . '\',
				prompt_text_img: \'' . addcslashes($txt['prompt_text_img'], "'") . '\'
			}
		// ]]></script>
		<script type="text/javascript" src="' . $settings['default_theme_url'] . '/scripts/editor.js?alp21"></script>
		<link rel="stylesheet" href="' . $settings['default_theme_url'] . '/css/jquery.sceditor.css" type="text/css" media="all" />
		<script type="text/javascript" src="' . $settings['default_theme_url'] . '/scripts/jquery.sceditor.js"></script>
		<script type="text/javascript" src="' . $settings['default_theme_url'] . '/scripts/jquery.sceditor.bbcode.js"></script>';

		$context['show_spellchecking'] = !empty($modSettings['enableSpellChecking']) && function_exists('pspell_new');
		if ($context['show_spellchecking'])
		{
			$context['html_headers'] .= '
		<script type="text/javascript" src="' . $settings['default_theme_url'] . '/scripts/spellcheck.js?alp21"></script>';

			// Some hidden information is needed in order to make the spell checking work.
			if (!isset($_REQUEST['xml']))
				$context['insert_after_template'] .= '
		<form name="spell_form" id="spell_form" method="post" accept-charset="' . $context['character_set'] . '" target="spellWindow" action="' . $scripturl . '?action=spellcheck">
			<input type="hidden" name="spellstring" value="" />
		</form>';
		}
	}

	// Start off the editor...
	$context['controls']['richedit'][$editorOptions['id']] = array(
		'id' => $editorOptions['id'],
		'value' => $editorOptions['value'],
		'rich_value' => $editorOptions['value'], // 2.0 editor compatibility
		'rich_active' => empty($modSettings['disable_wysiwyg']) && (!empty($options['wysiwyg_default']) || !empty($editorOptions['force_rich']) || !empty($_REQUEST[$editorOptions['id'] . '_mode'])),
		'disable_smiley_box' => !empty($editorOptions['disable_smiley_box']),
		'columns' => isset($editorOptions['columns']) ? $editorOptions['columns'] : 60,
		'rows' => isset($editorOptions['rows']) ? $editorOptions['rows'] : 18,
		'width' => isset($editorOptions['width']) ? $editorOptions['width'] : '70%',
		'height' => isset($editorOptions['height']) ? $editorOptions['height'] : '250px',
		'form' => isset($editorOptions['form']) ? $editorOptions['form'] : 'postmodify',
		'bbc_level' => !empty($editorOptions['bbc_level']) ? $editorOptions['bbc_level'] : 'full',
		'preview_type' => isset($editorOptions['preview_type']) ? (int) $editorOptions['preview_type'] : 1,
		'labels' => !empty($editorOptions['labels']) ? $editorOptions['labels'] : array(),
	);

	// Switch between default images and back... mostly in case you don't have an PersonalMessage template, but do have a Post template.
	if (isset($settings['use_default_images']) && $settings['use_default_images'] == 'defaults' && isset($settings['default_template']))
	{
		$temp1 = $settings['theme_url'];
		$settings['theme_url'] = $settings['default_theme_url'];

		$temp2 = $settings['images_url'];
		$settings['images_url'] = $settings['default_images_url'];

		$temp3 = $settings['theme_dir'];
		$settings['theme_dir'] = $settings['default_theme_dir'];
	}

	if (empty($context['bbc_tags']))
	{
		// The below array makes it dead easy to add images to this control. Add it to the array and everything else is done for you!
		/*
			array(
				'image' => 'bold',
				'code' => 'b',
				'before' => '[b]',
				'after' => '[/b]',
				'description' => $txt['bold'],
			),

		*/
		$context['bbc_tags'] = array();
		$context['bbc_tags'][] = array(
			array(
				'code' => 'bold',
				'description' => $txt['bold'],
			),
			array(
				'code' => 'italic',
				'description' => $txt['italic'],
			),
			array(
				'code' => 'underline',
				'description' => $txt['underline']
			),
			array(
				'code' => 'strike',
				'description' => $txt['strike']
			),
			array(),
			array(
				'code' => 'pre',
				'description' => $txt['preformatted']
			),
			array(
				'code' => 'left',
				'description' => $txt['left_align']
			),
			array(
				'code' => 'center',
				'description' => $txt['center']
			),
			array(
				'code' => 'right',
				'description' => $txt['right_align']
			),
		);
		$context['bbc_tags'][] = array(
			array(
				'code' => 'flash',
				'description' => $txt['flash']
			),
			array(
				'code' => 'image',
				'description' => $txt['image']
			),
			array(
				'code' => 'link',
				'description' => $txt['hyperlink']
			),
			array(
				'code' => 'email',
				'description' => $txt['insert_email']
			),
			array(
				'code' => 'ftp',
				'description' => $txt['ftp']
			),
			array(),
			array(
				'code' => 'glow',
				'description' => $txt['glow']
			),
			array(
				'code' => 'shadow',
				'description' => $txt['shadow']
			),
			array(
				'code' => 'move',
				'description' => $txt['marquee']
			),
			array(),
			array(
				'code' => 'superscript',
				'description' => $txt['superscript']
			),
			array(
				'code' => 'subscript',
				'description' => $txt['subscript']
			),
			array(
				'code' => 'tt',
				'description' => $txt['teletype']
			),
			array(),
			array(
				'code' => 'table',
				'description' => $txt['table']
			),
			array(
				'code' => 'code',
				'description' => $txt['bbc_code']
			),
			array(
				'code' => 'quote',
				'description' => $txt['bbc_quote']
			),
			array(),
			array(
				'code' => 'bulletlist',
				'description' => $txt['list_unordered']
			),
			array(
				'code' => 'orderedlist',
				'description' => $txt['list_ordered']
			),
			array(
				'code' => 'horizontalrule',
				'description' => $txt['horizontal_rule']
			),
		);

		// Allow mods to modify BBC buttons.
		call_integration_hook('integrate_bbc_buttons');

		// Show the toggle?
		if (empty($modSettings['disable_wysiwyg']))
		{
			$context['bbc_tags'][count($context['bbc_tags']) - 1][] = array();
			$context['bbc_tags'][count($context['bbc_tags']) - 1][] = array(
				'code' => 'unformat',
				'description' => $txt['unformat_text'],
			);
			$context['bbc_tags'][count($context['bbc_tags']) - 1][] = array(
				'code' => 'toggle',
				'description' => $txt['toggle_view'],
			);
		}

		// Generate a list of buttons that shouldn't be shown - this should be the fastest way to do this.
		$disabled_tags = array();
		if (!empty($modSettings['disabledBBC']))
			$disabled_tags = explode(',', $modSettings['disabledBBC']);
		if (empty($modSettings['enableEmbeddedFlash']))
			$disabled_tags[] = 'flash';

		foreach ($disabled_tags as $tag)
		{
			if ($tag == 'list')
			{
				$context['disabled_tags']['bulletlist'] = true;
				$context['disabled_tags']['orderedlist'] = true;
			}
			elseif ($tag == 'b')
				$context['disabled_tags']['bold'] = true;
			elseif ($tag == 'i')
				$context['disabled_tags']['italic'] = true;
			elseif ($tag == 'i')
				$context['disabled_tags']['underline'] = true;
			elseif ($tag == 'i')
				$context['disabled_tags']['strike'] = true;
			elseif ($tag == 'img')
				$context['disabled_tags']['image'] = true;
			elseif ($tag == 'url')
				$context['disabled_tags']['link'] = true;
			elseif ($tag == 'sup')
				$context['disabled_tags']['superscript'] = true;
			elseif ($tag == 'sub')
				$context['disabled_tags']['subscript'] = true;
			elseif ($tag == 'hr')
				$context['disabled_tags']['horizontalrule'] = true;

			$context['disabled_tags'][trim($tag)] = true;
		}

		$bbcodes_styles = '';
		$context['bbcodes_hanlders'] = '';
		$context['bbc_toolbar'] = array();
		foreach ($context['bbc_tags'] as $row => $tagRow)
		{
			if (!isset($context['bbc_toolbar'][$row]))
				$context['bbc_toolbar'][$row] = array();
			$tagsRow = array();
			foreach ($tagRow as $tag)
			{
			 if (!empty($tag))
			 {
					if (empty($context['disabled_tags'][$tag['code']]))
					{
						$tagsRow[] = $tag['code'];
						if (isset($tag['image']))
							$bbcodes_styles .= '
			.sceditor-button-' . $tag['code'] . ' div {
				background: url(\'' . $settings['default_theme_url'] . '/images/bbc/' . $tag['image'] . '.png\');
			}';
						if (isset($tag['before']))
						{
							$context['bbcodes_hanlders'] = '
				$.sceditor.setCommand(
					' . javaScriptEscape($tag['code']) . ',
					function () {
						this.wysiwygEditorInsertHtml(' . javaScriptEscape($tag['before']) . (isset($tag['after']) ? ', ' . javaScriptEscape($tag['after']) : '') . ');
					},
					' . javaScriptEscape($tag['description']) . ',
					null,
					[' . javaScriptEscape($tag['before']) . (isset($tag['after']) ? ', ' . javaScriptEscape($tag['after']) : '') . ']
				);';
						}
					}
				}
				else
				{
					$context['bbc_toolbar'][$row][] = implode(',', $tagsRow);
					$tagsRow = array();
				}
			}

			if ($row == 0)
			{
				$context['bbc_toolbar'][$row][] = implode(',', $tagsRow);
				$tagsRow = array();
				if (!isset($context['disabled_tags']['font']))
					$tagsRow[] = 'font';
				if (!isset($context['disabled_tags']['size']))
					$tagsRow[] = 'size';
				if (!isset($context['disabled_tags']['color']))
					$tagsRow[] = 'color';
			}
			elseif ($row == 1 && empty($modSettings['disable_wysiwyg']))
			{
				$tmp = array();
				$tagsRow[] = 'removeformat';
				$tagsRow[] = 'source';
				if (!empty($tmp))
				{
					$tagsRow[] = '|' . implode(',', $tmp);
				}
			}

			if (!empty($tagsRow))
				$context['bbc_toolbar'][$row][] = implode(',', $tagsRow);
		}
		if (!empty($bbcodes_styles))
			$context['html_headers'] .= '
		<style type="text/css">' . $bbcodes_styles . '
		</style>';
	}

	// Initialize smiley array... if not loaded before.
	if (empty($context['smileys']) && empty($editorOptions['disable_smiley_box']))
	{
		$context['smileys'] = array(
			'postform' => array(),
			'popup' => array(),
		);

		// Load smileys - don't bother to run a query if we're not using the database's ones anyhow.
		if (empty($modSettings['smiley_enable']) && $user_info['smiley_set'] != 'none')
			$context['smileys']['postform'][] = array(
				'smileys' => array(
					array(
						'code' => ':)',
						'filename' => 'smiley.gif',
						'description' => $txt['icon_smiley'],
					),
					array(
						'code' => ';)',
						'filename' => 'wink.gif',
						'description' => $txt['icon_wink'],
					),
					array(
						'code' => ':D',
						'filename' => 'cheesy.gif',
						'description' => $txt['icon_cheesy'],
					),
					array(
						'code' => ';D',
						'filename' => 'grin.gif',
						'description' => $txt['icon_grin']
					),
					array(
						'code' => '>:(',
						'filename' => 'angry.gif',
						'description' => $txt['icon_angry'],
					),
					array(
						'code' => ':(',
						'filename' => 'sad.gif',
						'description' => $txt['icon_sad'],
					),
					array(
						'code' => ':o',
						'filename' => 'shocked.gif',
						'description' => $txt['icon_shocked'],
					),
					array(
						'code' => '8)',
						'filename' => 'cool.gif',
						'description' => $txt['icon_cool'],
					),
					array(
						'code' => '???',
						'filename' => 'huh.gif',
						'description' => $txt['icon_huh'],
					),
					array(
						'code' => '::)',
						'filename' => 'rolleyes.gif',
						'description' => $txt['icon_rolleyes'],
					),
					array(
						'code' => ':P',
						'filename' => 'tongue.gif',
						'description' => $txt['icon_tongue'],
					),
					array(
						'code' => ':-[',
						'filename' => 'embarrassed.gif',
						'description' => $txt['icon_embarrassed'],
					),
					array(
						'code' => ':-X',
						'filename' => 'lipsrsealed.gif',
						'description' => $txt['icon_lips'],
					),
					array(
						'code' => ':-\\',
						'filename' => 'undecided.gif',
						'description' => $txt['icon_undecided'],
					),
					array(
						'code' => ':-*',
						'filename' => 'kiss.gif',
						'description' => $txt['icon_kiss'],
					),
					array(
						'code' => ':\'(',
						'filename' => 'cry.gif',
						'description' => $txt['icon_cry'],
						'isLast' => true,
					),
				),
				'isLast' => true,
			);
		elseif ($user_info['smiley_set'] != 'none')
		{
			if (($temp = cache_get_data('posting_smileys', 480)) == null)
			{
				$request = $smcFunc['db_query']('', '
					SELECT code, filename, description, smiley_row, hidden
					FROM {db_prefix}smileys
					WHERE hidden IN (0, 2)
					ORDER BY smiley_row, smiley_order',
					array(
					)
				);
				while ($row = $smcFunc['db_fetch_assoc']($request))
				{
					$row['filename'] = htmlspecialchars($row['filename']);
					$row['description'] = htmlspecialchars($row['description']);

					$context['smileys'][empty($row['hidden']) ? 'postform' : 'popup'][$row['smiley_row']]['smileys'][] = $row;
				}
				$smcFunc['db_free_result']($request);

				foreach ($context['smileys'] as $section => $smileyRows)
				{
					foreach ($smileyRows as $rowIndex => $smileys)
						$context['smileys'][$section][$rowIndex]['smileys'][count($smileys['smileys']) - 1]['isLast'] = true;

					if (!empty($smileyRows))
						$context['smileys'][$section][count($smileyRows) - 1]['isLast'] = true;
				}

				cache_put_data('posting_smileys', $context['smileys'], 480);
			}
			else
				$context['smileys'] = $temp;
		}
	}

	// Set a flag so the sub template knows what to do...
	$context['show_bbc'] = !empty($modSettings['enableBBC']) && !empty($settings['show_bbc']);

	// Switch the URLs back... now we're back to whatever the main sub template is.  (like folder in PersonalMessage.)
	if (isset($settings['use_default_images']) && $settings['use_default_images'] == 'defaults' && isset($settings['default_template']))
	{
		$settings['theme_url'] = $temp1;
		$settings['images_url'] = $temp2;
		$settings['theme_dir'] = $temp3;
	}
}

/**
 * Create a anti-bot verification control?
 * @param array &$verificationOptions
 * @param bool $do_test = false
 */
function create_control_verification(&$verificationOptions, $do_test = false)
{
	global $txt, $modSettings, $options, $smcFunc;
	global $context, $settings, $user_info, $sourcedir, $scripturl;

	// First verification means we need to set up some bits...
	if (empty($context['controls']['verification']))
	{
		// The template
		loadTemplate('GenericControls');

		// Some javascript ma'am?
		if (!empty($verificationOptions['override_visual']) || (!empty($modSettings['visual_verification_type']) && !isset($verificationOptions['override_visual'])))
			$context['html_headers'] .= '
		<script type="text/javascript" src="' . $settings['default_theme_url'] . '/scripts/captcha.js"></script>';

		$context['use_graphic_library'] = in_array('gd', get_loaded_extensions());

		// Skip I, J, L, O, Q, S and Z.
		$context['standard_captcha_range'] = array_merge(range('A', 'H'), array('K', 'M', 'N', 'P', 'R'), range('T', 'Y'));
	}

	// Always have an ID.
	assert(isset($verificationOptions['id']));
	$isNew = !isset($context['controls']['verification'][$verificationOptions['id']]);

	// Log this into our collection.
	if ($isNew)
		$context['controls']['verification'][$verificationOptions['id']] = array(
			'id' => $verificationOptions['id'],
			'show_visual' => !empty($verificationOptions['override_visual']) || (!empty($modSettings['visual_verification_type']) && !isset($verificationOptions['override_visual'])),
			'number_questions' => isset($verificationOptions['override_qs']) ? $verificationOptions['override_qs'] : (!empty($modSettings['qa_verification_number']) ? $modSettings['qa_verification_number'] : 0),
			'max_errors' => isset($verificationOptions['max_errors']) ? $verificationOptions['max_errors'] : 3,
			'image_href' => $scripturl . '?action=verificationcode;vid=' . $verificationOptions['id'] . ';rand=' . md5(mt_rand()),
			'text_value' => '',
			'questions' => array(),
		);
	$thisVerification = &$context['controls']['verification'][$verificationOptions['id']];

	// Add javascript for the object.
	if ($context['controls']['verification'][$verificationOptions['id']]['show_visual'] && !WIRELESS)
		$context['insert_after_template'] .= '
			<script type="text/javascript"><!-- // --><![CDATA[
				var verification' . $verificationOptions['id'] . 'Handle = new smfCaptcha("' . $thisVerification['image_href'] . '", "' . $verificationOptions['id'] . '", ' . ($context['use_graphic_library'] ? 1 : 0) . ');
			// ]]></script>';

	// Is there actually going to be anything?
	if (empty($thisVerification['show_visual']) && empty($thisVerification['number_questions']))
		return false;
	elseif (!$isNew && !$do_test)
		return true;

	// If we want questions do we have a cache of all the IDs?
	if (!empty($thisVerification['number_questions']) && empty($modSettings['question_id_cache']))
	{
		if (($modSettings['question_id_cache'] = cache_get_data('verificationQuestionIds', 300)) == null)
		{
			$request = $smcFunc['db_query']('', '
				SELECT id_comment
				FROM {db_prefix}log_comments
				WHERE comment_type = {string:ver_test}',
				array(
					'ver_test' => 'ver_test',
				)
			);
			$modSettings['question_id_cache'] = array();
			while ($row = $smcFunc['db_fetch_assoc']($request))
				$modSettings['question_id_cache'][] = $row['id_comment'];
			$smcFunc['db_free_result']($request);

			if (!empty($modSettings['cache_enable']))
				cache_put_data('verificationQuestionIds', $modSettings['question_id_cache'], 300);
		}
	}

	if (!isset($_SESSION[$verificationOptions['id'] . '_vv']))
		$_SESSION[$verificationOptions['id'] . '_vv'] = array();

	// Do we need to refresh the verification?
	if (!$do_test && (!empty($_SESSION[$verificationOptions['id'] . '_vv']['did_pass']) || empty($_SESSION[$verificationOptions['id'] . '_vv']['count']) || $_SESSION[$verificationOptions['id'] . '_vv']['count'] > 3) && empty($verificationOptions['dont_refresh']))
		$force_refresh = true;
	else
		$force_refresh = false;

	// This can also force a fresh, although unlikely.
	if (($thisVerification['show_visual'] && empty($_SESSION[$verificationOptions['id'] . '_vv']['code'])) || ($thisVerification['number_questions'] && empty($_SESSION[$verificationOptions['id'] . '_vv']['q'])))
		$force_refresh = true;

	$verification_errors = array();

	// Start with any testing.
	if ($do_test)
	{
		// This cannot happen!
		if (!isset($_SESSION[$verificationOptions['id'] . '_vv']['count']))
			fatal_lang_error('no_access', false);
		// ... nor this!
		if ($thisVerification['number_questions'] && (!isset($_SESSION[$verificationOptions['id'] . '_vv']['q']) || !isset($_REQUEST[$verificationOptions['id'] . '_vv']['q'])))
			fatal_lang_error('no_access', false);

		if ($thisVerification['show_visual'] && (empty($_REQUEST[$verificationOptions['id'] . '_vv']['code']) || empty($_SESSION[$verificationOptions['id'] . '_vv']['code']) || strtoupper($_REQUEST[$verificationOptions['id'] . '_vv']['code']) !== $_SESSION[$verificationOptions['id'] . '_vv']['code']))
			$verification_errors[] = 'wrong_verification_code';
		if ($thisVerification['number_questions'])
		{
			// Get the answers and see if they are all right!
			$request = $smcFunc['db_query']('', '
				SELECT id_comment, recipient_name AS answer
				FROM {db_prefix}log_comments
				WHERE comment_type = {string:ver_test}
					AND id_comment IN ({array_int:comment_ids})',
				array(
					'ver_test' => 'ver_test',
					'comment_ids' => $_SESSION[$verificationOptions['id'] . '_vv']['q'],
				)
			);
			$incorrectQuestions = array();
			while ($row = $smcFunc['db_fetch_assoc']($request))
			{
				if (!isset($_REQUEST[$verificationOptions['id'] . '_vv']['q'][$row['id_comment']]) || trim($_REQUEST[$verificationOptions['id'] . '_vv']['q'][$row['id_comment']]) == '' || trim($smcFunc['htmlspecialchars'](strtolower($_REQUEST[$verificationOptions['id'] . '_vv']['q'][$row['id_comment']]))) != strtolower($row['answer']))
					$incorrectQuestions[] = $row['id_comment'];
			}
			$smcFunc['db_free_result']($request);

			if (!empty($incorrectQuestions))
				$verification_errors[] = 'wrong_verification_answer';
		}
	}

	// Any errors means we refresh potentially.
	if (!empty($verification_errors))
	{
		if (empty($_SESSION[$verificationOptions['id'] . '_vv']['errors']))
			$_SESSION[$verificationOptions['id'] . '_vv']['errors'] = 0;
		// Too many errors?
		elseif ($_SESSION[$verificationOptions['id'] . '_vv']['errors'] > $thisVerification['max_errors'])
			$force_refresh = true;

		// Keep a track of these.
		$_SESSION[$verificationOptions['id'] . '_vv']['errors']++;
	}

	// Are we refreshing then?
	if ($force_refresh)
	{
		// Assume nothing went before.
		$_SESSION[$verificationOptions['id'] . '_vv']['count'] = 0;
		$_SESSION[$verificationOptions['id'] . '_vv']['errors'] = 0;
		$_SESSION[$verificationOptions['id'] . '_vv']['did_pass'] = false;
		$_SESSION[$verificationOptions['id'] . '_vv']['q'] = array();
		$_SESSION[$verificationOptions['id'] . '_vv']['code'] = '';

		// Generating a new image.
		if ($thisVerification['show_visual'])
		{
			// Are we overriding the range?
			$character_range = !empty($verificationOptions['override_range']) ? $verificationOptions['override_range'] : $context['standard_captcha_range'];

			for ($i = 0; $i < 6; $i++)
				$_SESSION[$verificationOptions['id'] . '_vv']['code'] .= $character_range[array_rand($character_range)];
		}

		// Getting some new questions?
		if ($thisVerification['number_questions'])
		{
			// Pick some random IDs
			$questionIDs = array();
			if ($thisVerification['number_questions'] == 1)
				$questionIDs[] = $modSettings['question_id_cache'][array_rand($modSettings['question_id_cache'], $thisVerification['number_questions'])];
			else
				foreach (array_rand($modSettings['question_id_cache'], $thisVerification['number_questions']) as $index)
					$questionIDs[] = $modSettings['question_id_cache'][$index];
		}
	}
	else
	{
		// Same questions as before.
		$questionIDs = !empty($_SESSION[$verificationOptions['id'] . '_vv']['q']) ? $_SESSION[$verificationOptions['id'] . '_vv']['q'] : array();
		$thisVerification['text_value'] = !empty($_REQUEST[$verificationOptions['id'] . '_vv']['code']) ? $smcFunc['htmlspecialchars']($_REQUEST[$verificationOptions['id'] . '_vv']['code']) : '';
	}

	// Have we got some questions to load?
	if (!empty($questionIDs))
	{
		$request = $smcFunc['db_query']('', '
			SELECT id_comment, body AS question
			FROM {db_prefix}log_comments
			WHERE comment_type = {string:ver_test}
				AND id_comment IN ({array_int:comment_ids})',
			array(
				'ver_test' => 'ver_test',
				'comment_ids' => $questionIDs,
			)
		);
		$_SESSION[$verificationOptions['id'] . '_vv']['q'] = array();
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			$thisVerification['questions'][] = array(
				'id' => $row['id_comment'],
				'q' => parse_bbc($row['question']),
				'is_error' => !empty($incorrectQuestions) && in_array($row['id_comment'], $incorrectQuestions),
				// Remember a previous submission?
				'a' => isset($_REQUEST[$verificationOptions['id'] . '_vv'], $_REQUEST[$verificationOptions['id'] . '_vv']['q'], $_REQUEST[$verificationOptions['id'] . '_vv']['q'][$row['id_comment']]) ? $smcFunc['htmlspecialchars']($_REQUEST[$verificationOptions['id'] . '_vv']['q'][$row['id_comment']]) : '',
			);
			$_SESSION[$verificationOptions['id'] . '_vv']['q'][] = $row['id_comment'];
		}
		$smcFunc['db_free_result']($request);
	}

	$_SESSION[$verificationOptions['id'] . '_vv']['count'] = empty($_SESSION[$verificationOptions['id'] . '_vv']['count']) ? 1 : $_SESSION[$verificationOptions['id'] . '_vv']['count'] + 1;

	// Return errors if we have them.
	if (!empty($verification_errors))
		return $verification_errors;
	// If we had a test that one, make a note.
	elseif ($do_test)
		$_SESSION[$verificationOptions['id'] . '_vv']['did_pass'] = true;

	// Say that everything went well chaps.
	return true;
}

/**
 * This keeps track of all registered handling functions for auto suggest functionality and passes execution to them.
 * @param bool $checkRegistered = null
 */
function AutoSuggestHandler($checkRegistered = null)
{
	global $context;

	// These are all registered types.
	$searchTypes = array(
		'member' => 'Member',
		'versions' => 'SMFVersions',
	);

	// If we're just checking the callback function is registered return true or false.
	if ($checkRegistered != null)
		return isset($searchTypes[$checkRegistered]) && function_exists('AutoSuggest_Search_' . $checkRegistered);

	checkSession('get');
	loadTemplate('Xml');

	// Any parameters?
	$context['search_param'] = isset($_REQUEST['search_param']) ? unserialize(base64_decode($_REQUEST['search_param'])) : array();

	if (isset($_REQUEST['suggest_type'], $_REQUEST['search']) && isset($searchTypes[$_REQUEST['suggest_type']]))
	{
		$function = 'AutoSuggest_Search_' . $searchTypes[$_REQUEST['suggest_type']];
		$context['sub_template'] = 'generic_xml';
		$context['xml_data'] = $function();
	}
}

/**
 * Search for a member - by real_name or member_name by default.
 * 
 * @return string
 */
function AutoSuggest_Search_Member()
{
	global $user_info, $txt, $smcFunc, $context;

	$_REQUEST['search'] = trim($smcFunc['strtolower']($_REQUEST['search'])) . '*';
	$_REQUEST['search'] = strtr($_REQUEST['search'], array('%' => '\%', '_' => '\_', '*' => '%', '?' => '_', '&#038;' => '&amp;'));

	// Find the member.
	$request = $smcFunc['db_query']('', '
		SELECT id_member, real_name
		FROM {db_prefix}members
		WHERE real_name LIKE {string:search}' . (!empty($context['search_param']['buddies']) ? '
			AND id_member IN ({array_int:buddy_list})' : '') . '
			AND is_activated IN (1, 11)
		LIMIT ' . ($smcFunc['strlen']($_REQUEST['search']) <= 2 ? '100' : '800'),
		array(
			'buddy_list' => $user_info['buddies'],
			'search' => $_REQUEST['search'],
		)
	);
	$xml_data = array(
		'items' => array(
			'identifier' => 'item',
			'children' => array(),
		),
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$row['real_name'] = strtr($row['real_name'], array('&amp;' => '&#038;', '&lt;' => '&#060;', '&gt;' => '&#062;', '&quot;' => '&#034;'));

		$xml_data['items']['children'][] = array(
			'attributes' => array(
				'id' => $row['id_member'],
			),
			'value' => $row['real_name'],
		);
	}
	$smcFunc['db_free_result']($request);

	return $xml_data;
}

function AutoSuggest_Search_SMFVersions()
{

	$xml_data = array(
		'items' => array(
			'identifier' => 'item',
			'children' => array(),
		),
	);

	$versions = array(
		'SMF 1.1',
		'SMF 1.1.1',
		'SMF 1.1.2',
		'SMF 1.1.3',
		'SMF 1.1.4',
		'SMF 1.1.5',
		'SMF 1.1.6',
		'SMF 1.1.7',
		'SMF 1.1.8',
		'SMF 1.1.9',
		'SMF 1.1.10',
		'SMF 1.1.11',
		'SMF 1.1.12',
		'SMF 1.1.13',
		'SMF 1.1.14',
		'SMF 1.1.15',
		'SMF 1.1.16',
		'SMF 2.0 beta 1',
		'SMF 2.0 beta 1.2',
		'SMF 2.0 beta 2',
		'SMF 2.0 beta 3',
		'SMF 2.0 RC 1',
		'SMF 2.0 RC 1.2',
		'SMF 2.0 RC 2',
		'SMF 2.0 RC 3',
		'SMF 2.0',
		'SMF 2.0.1',
		'SMF 2.0.2',
	);

	foreach ($versions as $id => $version)
		if (strpos($version, strtoupper($_REQUEST['search'])) !== false)
			$xml_data['items']['children'][] = array(
				'attributes' => array(
					'id' => $id,
				),
				'value' => $version,
			);

	return $xml_data;
}
?>