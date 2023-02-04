<?php

/**
 * This file contains those functions specific to the editing box and is
 * generally used for WYSIWYG type functionality.
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2023 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 1
 */

use SMF\BrowserDetector;
use SMF\BBCodeParser;
use SMF\Cache\CacheApi;

if (!defined('SMF'))
	die('No direct access...');

// Some functions have moved
class_exists('SMF\\BBCodeParser');

/**
 * Retrieves a list of message icons.
 * - Based on the settings, the array will either contain a list of default
 *   message icons or a list of custom message icons retrieved from the database.
 * - The board_id is needed for the custom message icons (which can be set for
 *   each board individually).
 *
 * @param int $board_id The ID of the board
 * @return array An array of info about available icons
 */
function getMessageIcons($board_id)
{
	global $modSettings, $txt, $settings, $smcFunc;

	if (empty($modSettings['messageIcons_enable']))
	{
		loadLanguage('Post');

		$icons = array(
			array('value' => 'xx', 'name' => $txt['standard']),
			array('value' => 'thumbup', 'name' => $txt['thumbs_up']),
			array('value' => 'thumbdown', 'name' => $txt['thumbs_down']),
			array('value' => 'exclamation', 'name' => $txt['exclamation_point']),
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
		if (($temp = CacheApi::get('posting_icons-' . $board_id, 480)) == null)
		{
			$request = $smcFunc['db_query']('', '
				SELECT title, filename
				FROM {db_prefix}message_icons
				WHERE id_board IN (0, {int:board_id})
				ORDER BY icon_order',
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

			CacheApi::put('posting_icons-' . $board_id, $icons, 480);
		}
		else
			$icons = $temp;
	}
	call_integration_hook('integrate_load_message_icons', array(&$icons));

	return array_values($icons);
}

/**
 * Creates a box that can be used for richedit stuff like BBC, Smileys etc.
 *
 * @param array $editorOptions Various options for the editor
 */
function create_control_richedit($editorOptions)
{
	global $txt, $modSettings, $options, $smcFunc, $editortxt;
	global $context, $settings, $user_info, $scripturl;

	// Load the Post language file... for the moment at least.
	loadLanguage('Post');
	loadLanguage('Editor');
	loadLanguage('Drafts');

	$context['richedit_buttons'] = array(
		'save_draft' => array(
			'type' => 'submit',
			'value' => $txt['draft_save'],
			'onclick' => !empty($context['drafts_pm_save']) ? 'submitThisOnce(this);' : (!empty($context['drafts_save']) ? 'return confirm(' . JavaScriptEscape($txt['draft_save_note']) . ') && submitThisOnce(this);' : ''),
			'accessKey' => 'd',
			'show' => !empty($context['drafts_pm_save']) || !empty($context['drafts_save'])
		),
		'id_pm_draft' => array(
			'type' => 'hidden',
			'value' => empty($context['id_pm_draft']) ? 0 : $context['id_pm_draft'],
			'show' => !empty($context['drafts_pm_save'])
		),
		'id_draft' => array(
			'type' => 'hidden',
			'value' => empty($context['id_draft']) ? 0 : $context['id_draft'],
			'show' => !empty($context['drafts_save'])
		),
		'spell_check' => array(
			'type' => 'submit',
			'value' => $txt['spell_check'],
			'show' => !empty($context['show_spellchecking'])
		),
		'preview' => array(
			'type' => 'submit',
			'value' => $txt['preview'],
			'accessKey' => 'p'
		)
	);

	// Every control must have a ID!
	assert(isset($editorOptions['id']));
	assert(isset($editorOptions['value']));

	// Is this the first richedit - if so we need to ensure some template stuff is initialised.
	if (empty($context['controls']['richedit']))
	{
		// Some general stuff.
		$settings['smileys_url'] = $modSettings['smileys_url'] . '/' . $user_info['smiley_set'];
		if (!empty($context['drafts_autosave']))
			$context['drafts_autosave_frequency'] = empty($modSettings['drafts_autosave_frequency']) ? 60000 : $modSettings['drafts_autosave_frequency'] * 1000;

		// This really has some WYSIWYG stuff.
		loadCSSFile('jquery.sceditor.css', array('default_theme' => true, 'validate' => true), 'smf_jquery_sceditor');
		loadTemplate('GenericControls');

		/*
		 *		THEME AUTHORS:
		 			If you want to change or tweak the CSS for the editor,
					include a file named 'jquery.sceditor.theme.css' in your theme.
		*/
		loadCSSFile('jquery.sceditor.theme.css', array('force_current' => true, 'validate' => true,), 'smf_jquery_sceditor_theme');

		// JS makes the editor go round
		loadJavaScriptFile('editor.js', array('minimize' => true), 'smf_editor');
		loadJavaScriptFile('jquery.sceditor.bbcode.min.js', array(), 'smf_sceditor_bbcode');
		loadJavaScriptFile('jquery.sceditor.smf.js', array('minimize' => true), 'smf_sceditor_smf');

		$scExtraLangs = '
		$.sceditor.locale["' . $txt['lang_dictionary'] . '"] = {
			"Width (optional):": "' . $editortxt['width'] . '",
			"Height (optional):": "' . $editortxt['height'] . '",
			"Insert": "' . $editortxt['insert'] . '",
			"Description (optional):": "' . $editortxt['description'] . '",
			"Rows:": "' . $editortxt['rows'] . '",
			"Cols:": "' . $editortxt['cols'] . '",
			"URL:": "' . $editortxt['url'] . '",
			"E-mail:": "' . $editortxt['email'] . '",
			"Video URL:": "' . $editortxt['video_url'] . '",
			"More": "' . $editortxt['more'] . '",
			"Close": "' . $editortxt['close'] . '",
			dateFormat: "' . $editortxt['dateformat'] . '"
		};';

		addInlineJavaScript($scExtraLangs, true);

		addInlineJavaScript('
		var smf_smileys_url = \'' . $settings['smileys_url'] . '\';
		var bbc_quote_from = \'' . addcslashes($txt['quote_from'], "'") . '\';
		var bbc_quote = \'' . addcslashes($txt['quote'], "'") . '\';
		var bbc_search_on = \'' . addcslashes($txt['search_on'], "'") . '\';');

		$context['shortcuts_text'] = $txt['shortcuts' . (!empty($context['drafts_save']) ? '_drafts' : '') . (stripos($_SERVER['HTTP_USER_AGENT'], 'Macintosh') !== false ? '_mac' : (BrowserDetector::isBrowser('is_firefox') ? '_firefox' : ''))];

		if ($context['show_spellchecking'])
		{
			loadJavaScriptFile('spellcheck.js', array('minimize' => true), 'smf_spellcheck');

			// Some hidden information is needed in order to make the spell checking work.
			if (!isset($_REQUEST['xml']))
				$context['insert_after_template'] .= '
		<form name="spell_form" id="spell_form" method="post" accept-charset="' . $context['character_set'] . '" target="spellWindow" action="' . $scripturl . '?action=spellcheck">
			<input type="hidden" name="spellstring" value="">
		</form>';
		}
	}

	// The [#] item code for creating list items causes issues with SCEditor, but [+] is a safe equivalent.
	$editorOptions['value'] = str_replace('[#]', '[+]', $editorOptions['value']);
	// Tabs are not shown in the SCEditor, replace with spaces.
	$editorOptions['value'] = str_replace("\t", '    ', $editorOptions['value']);

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
		'height' => isset($editorOptions['height']) ? $editorOptions['height'] : '175px',
		'form' => isset($editorOptions['form']) ? $editorOptions['form'] : 'postmodify',
		'bbc_level' => !empty($editorOptions['bbc_level']) ? $editorOptions['bbc_level'] : 'full',
		'preview_type' => isset($editorOptions['preview_type']) ? (int) $editorOptions['preview_type'] : 1,
		'labels' => !empty($editorOptions['labels']) ? $editorOptions['labels'] : array(),
		'locale' => !empty($txt['lang_dictionary']) && $txt['lang_dictionary'] != 'en' ? $txt['lang_dictionary'] : '',
		'required' => !empty($editorOptions['required']),
	);

	if (empty($context['bbc_tags']))
	{
		// The below array makes it dead easy to add images to this control. Add it to the array and everything else is done for you!
		// Note: 'before' and 'after' are deprecated as of SMF 2.1. Instead, use a separate JS file to configure the functionality of your toolbar buttons.
		/*
			array(
				'code' => 'b', // Required
				'description' => $editortxt['bold'], // Required
				'image' => 'bold', // Optional
				'before' => '[b]', // Deprecated
				'after' => '[/b]', // Deprecated
			),
		*/
		$context['bbc_tags'] = array();
		$context['bbc_tags'][] = array(
			array(
				'code' => 'bold',
				'description' => $editortxt['bold'],
			),
			array(
				'code' => 'italic',
				'description' => $editortxt['italic'],
			),
			array(
				'code' => 'underline',
				'description' => $editortxt['underline']
			),
			array(
				'code' => 'strike',
				'description' => $editortxt['strikethrough']
			),
			array(
				'code' => 'superscript',
				'description' => $editortxt['superscript']
			),
			array(
				'code' => 'subscript',
				'description' => $editortxt['subscript']
			),
			array(),
			array(
				'code' => 'pre',
				'description' => $editortxt['preformatted_text']
			),
			array(
				'code' => 'left',
				'description' => $editortxt['align_left']
			),
			array(
				'code' => 'center',
				'description' => $editortxt['center']
			),
			array(
				'code' => 'right',
				'description' => $editortxt['align_right']
			),
			array(
				'code' => 'justify',
				'description' => $editortxt['justify']
			),
			array(),
			array(
				'code' => 'font',
				'description' => $editortxt['font_name']
			),
			array(
				'code' => 'size',
				'description' => $editortxt['font_size']
			),
			array(
				'code' => 'color',
				'description' => $editortxt['font_color']
			),
		);
		if (empty($modSettings['disable_wysiwyg']))
		{
			$context['bbc_tags'][count($context['bbc_tags']) - 1][] = array(
				'code' => 'removeformat',
				'description' => $editortxt['remove_formatting'],
			);
		}
		$context['bbc_tags'][] = array(
			array(
				'code' => 'floatleft',
				'description' => $editortxt['float_left']
			),
			array(
				'code' => 'floatright',
				'description' => $editortxt['float_right']
			),
			array(),
			array(
				'code' => 'youtube',
				'description' => $editortxt['insert_youtube_video']
			),
			array(
				'code' => 'image',
				'description' => $editortxt['insert_image']
			),
			array(
				'code' => 'link',
				'description' => $editortxt['insert_link']
			),
			array(
				'code' => 'email',
				'description' => $editortxt['insert_email']
			),
			array(),
			array(
				'code' => 'table',
				'description' => $editortxt['insert_table']
			),
			array(
				'code' => 'code',
				'description' => $editortxt['code']
			),
			array(
				'code' => 'quote',
				'description' => $editortxt['insert_quote']
			),
			array(),
			array(
				'code' => 'bulletlist',
				'description' => $editortxt['bullet_list']
			),
			array(
				'code' => 'orderedlist',
				'description' => $editortxt['numbered_list']
			),
			array(
				'code' => 'horizontalrule',
				'description' => $editortxt['insert_horizontal_rule']
			),
			array(),
			array(
				'code' => 'maximize',
				'description' => $editortxt['maximize']
			),
		);
		if (empty($modSettings['disable_wysiwyg']))
		{
			$context['bbc_tags'][count($context['bbc_tags']) - 1][] = array(
				'code' => 'source',
				'description' => $editortxt['view_source'],
			);
		}

		$editor_tag_map = array(
			'b' => 'bold',
			'i' => 'italic',
			'u' => 'underline',
			's' => 'strike',
			'img' => 'image',
			'url' => 'link',
			'sup' => 'superscript',
			'sub' => 'subscript',
			'hr' => 'horizontalrule',
		);

		// Define this here so mods can add to it via the hook.
		$context['disabled_tags'] = array();

		// Allow mods to modify BBC buttons.
		// Note: passing the array here is not necessary and is deprecated, but it is kept for backward compatibility with 2.0
		call_integration_hook('integrate_bbc_buttons', array(&$context['bbc_tags'], &$editor_tag_map));

		// Generate a list of buttons that shouldn't be shown - this should be the fastest way to do this.
		$disabled_tags = array();
		if (!empty($modSettings['disabledBBC']))
			$disabled_tags = explode(',', $modSettings['disabledBBC']);

		foreach ($disabled_tags as $tag)
		{
			$tag = trim($tag);

			if ($tag === 'list')
			{
				$context['disabled_tags']['bulletlist'] = true;
				$context['disabled_tags']['orderedlist'] = true;
			}

			if ($tag === 'float')
			{
				$context['disabled_tags']['floatleft'] = true;
				$context['disabled_tags']['floatright'] = true;
			}

			foreach ($editor_tag_map as $thisTag => $tagNameBBC)
				if ($tag === $thisTag)
					$context['disabled_tags'][$tagNameBBC] = true;

			$context['disabled_tags'][$tag] = true;
		}

		$bbcodes_styles = '';
		$context['bbcodes_handlers'] = '';
		$context['bbc_toolbar'] = array();

		foreach ($context['bbc_tags'] as $row => $tagRow)
		{
			if (!isset($context['bbc_toolbar'][$row]))
				$context['bbc_toolbar'][$row] = array();

			$tagsRow = array();

			foreach ($tagRow as $tag)
			{
				if (empty($tag['code']))
				{
					$context['bbc_toolbar'][$row][] = implode(',', $tagsRow);
					$tagsRow = array();
				}
				elseif (empty($context['disabled_tags'][$tag['code']]))
				{
					$tagsRow[] = $tag['code'];

					// If we have a custom button image, set it now.
					if (isset($tag['image']))
					{
						$bbcodes_styles .= '
						.sceditor-button-' . $tag['code'] . ' div {
							background: url(\'' . $settings['default_theme_url'] . '/images/bbc/' . $tag['image'] . '.png\');
						}';
					}

					// Set the tooltip and possibly the command info
					$context['bbcodes_handlers'] .= '
						sceditor.command.set(' . JavaScriptEscape($tag['code']) . ', {
							tooltip: ' . JavaScriptEscape(isset($tag['description']) ? $tag['description'] : $tag['code']);

					// Legacy support for 2.0 BBC mods
					if (isset($tag['before']))
					{
						$context['bbcodes_handlers'] .= ',
							exec: function () {
								this.insertText(' . JavaScriptEscape($tag['before']) . (isset($tag['after']) ? ', ' . JavaScriptEscape($tag['after']) : '') . ');
							},
							txtExec: [' . JavaScriptEscape($tag['before']) . (isset($tag['after']) ? ', ' . JavaScriptEscape($tag['after']) : '') . ']';
					}

					$context['bbcodes_handlers'] .= '
						});';
				}
			}

			if (!empty($tagsRow))
				$context['bbc_toolbar'][$row][] = implode(',', $tagsRow);
		}

		if (!empty($bbcodes_styles))
			addInlineCss($bbcodes_styles);
	}

	// Initialize smiley array... if not loaded before.
	if (empty($context['smileys']) && empty($editorOptions['disable_smiley_box']))
	{
		$context['smileys'] = array(
			'postform' => array(),
			'popup' => array(),
		);

		if ($user_info['smiley_set'] != 'none')
		{
			// Cache for longer when customized smiley codes aren't enabled
			$cache_time = empty($modSettings['smiley_enable']) ? 7200 : 480;

			if (($temp = CacheApi::get('posting_smileys_' . $user_info['smiley_set'], $cache_time)) == null)
			{
				$request = $smcFunc['db_query']('', '
					SELECT s.code, f.filename, s.description, s.smiley_row, s.hidden
					FROM {db_prefix}smileys AS s
						JOIN {db_prefix}smiley_files AS f ON (s.id_smiley = f.id_smiley)
					WHERE s.hidden IN (0, 2)
						AND f.smiley_set = {string:smiley_set}' . (empty($modSettings['smiley_enable']) ? '
						AND s.code IN ({array_string:default_codes})' : '') . '
					ORDER BY s.smiley_row, s.smiley_order',
					array(
						'default_codes' => array('>:D', ':D', '::)', '>:(', ':))', ':)', ';)', ';D', ':(', ':o', '8)', ':P', '???', ':-[', ':-X', ':-*', ':\'(', ':-\\', '^-^', 'O0', 'C:-)', 'O:-)'),
						'smiley_set' => $user_info['smiley_set'],
					)
				);
				while ($row = $smcFunc['db_fetch_assoc']($request))
				{
					$row['description'] = !empty($txt['icon_' . strtolower($row['description'])]) ? $smcFunc['htmlspecialchars']($txt['icon_' . strtolower($row['description'])]) : $smcFunc['htmlspecialchars']($row['description']);

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

				CacheApi::put('posting_smileys_' . $user_info['smiley_set'], $context['smileys'], $cache_time);
			}
			else
				$context['smileys'] = $temp;
		}
	}

	// Set up the SCEditor options
	$sce_options = array(
		'width' => isset($editorOptions['width']) ? $editorOptions['width'] : '100%',
		'height' => isset($editorOptions['height']) ? $editorOptions['height'] : '175px',
		'style' => $settings[file_exists($settings['theme_dir'] . '/css/jquery.sceditor.default.css') ? 'theme_url' : 'default_theme_url'] . '/css/jquery.sceditor.default.css' . $context['browser_cache'],
		'emoticonsCompat' => true,
		'colors' => 'black,maroon,brown,green,navy,grey,red,orange,teal,blue,white,hotpink,yellow,limegreen,purple',
		'format' => 'bbcode',
		'plugins' => '',
		'bbcodeTrim' => false,
	);
	if (!empty($context['controls']['richedit'][$editorOptions['id']]['locale']))
		$sce_options['locale'] = $context['controls']['richedit'][$editorOptions['id']]['locale'];
	if (!empty($context['right_to_left']))
		$sce_options['rtl'] = true;
	if ($editorOptions['id'] != 'quickReply')
		$sce_options['autofocus'] = true;

	$sce_options['emoticons'] = array();
	$sce_options['emoticonsDescriptions'] = array();
	$sce_options['emoticonsEnabled'] = false;
	if ((!empty($context['smileys']['postform']) || !empty($context['smileys']['popup'])) && !$context['controls']['richedit'][$editorOptions['id']]['disable_smiley_box'])
	{
		$sce_options['emoticonsEnabled'] = true;
		$sce_options['emoticons']['dropdown'] = array();
		$sce_options['emoticons']['popup'] = array();

		$countLocations = count($context['smileys']);
		foreach ($context['smileys'] as $location => $smileyRows)
		{
			$countLocations--;

			unset($smiley_location);
			if ($location == 'postform')
				$smiley_location = &$sce_options['emoticons']['dropdown'];
			elseif ($location == 'popup')
				$smiley_location = &$sce_options['emoticons']['popup'];

			$numRows = count($smileyRows);

			// This is needed because otherwise the editor will remove all the duplicate (empty) keys and leave only 1 additional line
			$emptyPlaceholder = 0;
			foreach ($smileyRows as $smileyRow)
			{
				foreach ($smileyRow['smileys'] as $smiley)
				{
					$smiley_location[$smiley['code']] = $settings['smileys_url'] . '/' . $smiley['filename'];
					$sce_options['emoticonsDescriptions'][$smiley['code']] = $smiley['description'];
				}

				if (empty($smileyRow['isLast']) && $numRows != 1)
					$smiley_location['-' . $emptyPlaceholder++] = '';
			}
		}
	}

	$sce_options['toolbar'] = '';
	$sce_options['parserOptions']['txtVars'] = [
		'code' => $txt['code']
	];
	if (!empty($modSettings['enableBBC']))
	{
		$count_tags = count($context['bbc_tags']);
		foreach ($context['bbc_toolbar'] as $i => $buttonRow)
		{
			$sce_options['toolbar'] .= implode('|', $buttonRow);

			$count_tags--;

			if (!empty($count_tags))
				$sce_options['toolbar'] .= '||';
		}
	}

	// Allow mods to change $sce_options. Usful if, e.g., a mod wants to add an SCEditor plugin.
	call_integration_hook('integrate_sceditor_options', array(&$sce_options));

	$context['controls']['richedit'][$editorOptions['id']]['sce_options'] = $sce_options;
}

/**
 * Create a anti-bot verification control?
 *
 * @param array &$verificationOptions Options for the verification control
 * @param bool $do_test Whether to check to see if the user entered the code correctly
 * @return bool|array False if there's nothing to show, true if everything went well or an array containing error indicators if the test failed
 */
function create_control_verification(&$verificationOptions, $do_test = false)
{
	global $modSettings, $smcFunc;
	global $context, $user_info, $scripturl, $language;

	// First verification means we need to set up some bits...
	if (empty($context['controls']['verification']))
	{
		// The template
		loadTemplate('GenericControls');

		// Some javascript ma'am?
		if (!empty($verificationOptions['override_visual']) || (!empty($modSettings['visual_verification_type']) && !isset($verificationOptions['override_visual'])))
			loadJavaScriptFile('captcha.js', array('minimize' => true), 'smf_captcha');

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
			'empty_field' => empty($verificationOptions['no_empty_field']),
			'show_visual' => !empty($verificationOptions['override_visual']) || (!empty($modSettings['visual_verification_type']) && !isset($verificationOptions['override_visual'])),
			'number_questions' => isset($verificationOptions['override_qs']) ? $verificationOptions['override_qs'] : (!empty($modSettings['qa_verification_number']) ? $modSettings['qa_verification_number'] : 0),
			'max_errors' => isset($verificationOptions['max_errors']) ? $verificationOptions['max_errors'] : 3,
			'image_href' => $scripturl . '?action=verificationcode;vid=' . $verificationOptions['id'] . ';rand=' . md5(mt_rand()),
			'text_value' => '',
			'questions' => array(),
			'can_recaptcha' => !empty($modSettings['recaptcha_enabled']) && !empty($modSettings['recaptcha_site_key']) && !empty($modSettings['recaptcha_secret_key']),
		);
	$thisVerification = &$context['controls']['verification'][$verificationOptions['id']];

	// Add a verification hook, presetup.
	call_integration_hook('integrate_create_control_verification_pre', array(&$verificationOptions, $do_test));

	// Is there actually going to be anything?
	if (empty($thisVerification['show_visual']) && empty($thisVerification['number_questions']) && empty($thisVerification['can_recaptcha']))
		return false;
	elseif (!$isNew && !$do_test)
		return true;

	// Sanitize reCAPTCHA fields?
	if ($thisVerification['can_recaptcha'])
	{
		// Only allow 40 alphanumeric, underscore and dash characters.
		$thisVerification['recaptcha_site_key'] = preg_replace('/(0-9a-zA-Z_){40}/', '$1', $modSettings['recaptcha_site_key']);

		// Light or dark theme...
		$thisVerification['recaptcha_theme'] = preg_replace('/(light|dark)/', '$1', $modSettings['recaptcha_theme']);
	}

	// Add javascript for the object.
	if ($context['controls']['verification'][$verificationOptions['id']]['show_visual'])
		$context['insert_after_template'] .= '
			<script>
				var verification' . $verificationOptions['id'] . 'Handle = new smfCaptcha("' . $thisVerification['image_href'] . '", "' . $verificationOptions['id'] . '", ' . ($context['use_graphic_library'] ? 1 : 0) . ');
			</script>';

	// If we want questions do we have a cache of all the IDs?
	if (!empty($thisVerification['number_questions']) && empty($modSettings['question_id_cache']))
	{
		if (($modSettings['question_id_cache'] = CacheApi::get('verificationQuestions', 300)) == null)
		{
			$request = $smcFunc['db_query']('', '
				SELECT id_question, lngfile, question, answers
				FROM {db_prefix}qanda',
				array()
			);
			$modSettings['question_id_cache'] = array(
				'questions' => array(),
				'langs' => array(),
			);
			// This is like Captain Kirk climbing a mountain in some ways. This is L's fault, mkay? :P
			while ($row = $smcFunc['db_fetch_assoc']($request))
			{
				$id_question = $row['id_question'];
				unset ($row['id_question']);
				// Make them all lowercase. We can't directly use $smcFunc['strtolower'] with array_walk, so do it manually, eh?
				$row['answers'] = (array) $smcFunc['json_decode']($row['answers'], true);
				foreach ($row['answers'] as $k => $v)
					$row['answers'][$k] = $smcFunc['strtolower']($v);

				$modSettings['question_id_cache']['questions'][$id_question] = $row;
				$modSettings['question_id_cache']['langs'][$row['lngfile']][] = $id_question;
			}
			$smcFunc['db_free_result']($request);

			CacheApi::put('verificationQuestions', $modSettings['question_id_cache'], 300);
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
		// Hmm, it's requested but not actually declared. This shouldn't happen.
		if ($thisVerification['empty_field'] && empty($_SESSION[$verificationOptions['id'] . '_vv']['empty_field']))
			fatal_lang_error('no_access', false);
		// While we're here, did the user do something bad?
		if ($thisVerification['empty_field'] && !empty($_SESSION[$verificationOptions['id'] . '_vv']['empty_field']) && !empty($_REQUEST[$_SESSION[$verificationOptions['id'] . '_vv']['empty_field']]))
			$verification_errors[] = 'wrong_verification_answer';

		if ($thisVerification['can_recaptcha'])
		{
			$reCaptcha = new \ReCaptcha\ReCaptcha($modSettings['recaptcha_secret_key'], new \ReCaptcha\RequestMethod\SocketPost());

			// Was there a reCAPTCHA response?
			if (isset($_POST['g-recaptcha-response']))
			{
				$resp = $reCaptcha->verify($_POST['g-recaptcha-response'], $user_info['ip']);

				if (!$resp->isSuccess())
					$verification_errors[] = 'wrong_verification_recaptcha';
			}
			else
				$verification_errors[] = 'wrong_verification_code';
		}
		if ($thisVerification['show_visual'] && (empty($_REQUEST[$verificationOptions['id'] . '_vv']['code']) || empty($_SESSION[$verificationOptions['id'] . '_vv']['code']) || strtoupper($_REQUEST[$verificationOptions['id'] . '_vv']['code']) !== $_SESSION[$verificationOptions['id'] . '_vv']['code']))
			$verification_errors[] = 'wrong_verification_code';
		if ($thisVerification['number_questions'])
		{
			$incorrectQuestions = array();
			foreach ($_SESSION[$verificationOptions['id'] . '_vv']['q'] as $q)
			{
				// We don't have this question any more, thus no answers.
				if (!isset($modSettings['question_id_cache']['questions'][$q]))
					continue;
				// This is quite complex. We have our question but it might have multiple answers.
				// First, did they actually answer this question?
				if (!isset($_REQUEST[$verificationOptions['id'] . '_vv']['q'][$q]) || trim($_REQUEST[$verificationOptions['id'] . '_vv']['q'][$q]) == '')
				{
					$incorrectQuestions[] = $q;
					continue;
				}
				// Second, is their answer in the list of possible answers?
				else
				{
					$given_answer = trim($smcFunc['htmlspecialchars']($smcFunc['strtolower']($_REQUEST[$verificationOptions['id'] . '_vv']['q'][$q])));
					if (!in_array($given_answer, $modSettings['question_id_cache']['questions'][$q]['answers']))
						$incorrectQuestions[] = $q;
				}
			}

			if (!empty($incorrectQuestions))
				$verification_errors[] = 'wrong_verification_answer';
		}

		// Hooks got anything to say about this verification?
		call_integration_hook('integrate_create_control_verification_test', array($thisVerification, &$verification_errors));
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

		// Make our magic empty field.
		if ($thisVerification['empty_field'])
		{
			// We're building a field that lives in the template, that we hope to be empty later. But at least we give it a believable name.
			$terms = array('gadget', 'device', 'uid', 'gid', 'guid', 'uuid', 'unique', 'identifier');
			$second_terms = array('hash', 'cipher', 'code', 'key', 'unlock', 'bit', 'value');
			$start = mt_rand(0, 27);
			$hash = substr(md5(time()), $start, 4);
			$_SESSION[$verificationOptions['id'] . '_vv']['empty_field'] = $terms[array_rand($terms)] . '-' . $second_terms[array_rand($second_terms)] . '-' . $hash;
		}

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
			// Attempt to try the current page's language, followed by the user's preference, followed by the site default.
			$possible_langs = array();
			if (isset($_SESSION['language']))
				$possible_langs[] = strtr($_SESSION['language'], array('-utf8' => ''));
			if (!empty($user_info['language']))
				$possible_langs[] = $user_info['language'];

			$possible_langs[] = $language;

			$questionIDs = array();
			foreach ($possible_langs as $lang)
			{
				$lang = strtr($lang, array('-utf8' => ''));
				if (isset($modSettings['question_id_cache']['langs'][$lang]))
				{
					// If we find questions for this, grab the ids from this language's ones, randomize the array and take just the number we need.
					$questionIDs = $modSettings['question_id_cache']['langs'][$lang];
					shuffle($questionIDs);
					$questionIDs = array_slice($questionIDs, 0, $thisVerification['number_questions']);
					break;
				}
			}
		}

		// Hooks may need to know about this.
		call_integration_hook('integrate_create_control_verification_refresh', array($thisVerification));
	}
	else
	{
		// Same questions as before.
		$questionIDs = !empty($_SESSION[$verificationOptions['id'] . '_vv']['q']) ? $_SESSION[$verificationOptions['id'] . '_vv']['q'] : array();
		$thisVerification['text_value'] = !empty($_REQUEST[$verificationOptions['id'] . '_vv']['code']) ? $smcFunc['htmlspecialchars']($_REQUEST[$verificationOptions['id'] . '_vv']['code']) : '';
	}

	// If we do have an empty field, it would be nice to hide it from legitimate users who shouldn't be populating it anyway.
	if (!empty($_SESSION[$verificationOptions['id'] . '_vv']['empty_field']))
	{
		if (!isset($context['html_headers']))
			$context['html_headers'] = '';
		$context['html_headers'] .= '<style>.vv_special { display:none; }</style>';
	}

	// Have we got some questions to load?
	if (!empty($questionIDs))
	{
		$_SESSION[$verificationOptions['id'] . '_vv']['q'] = array();
		foreach ($questionIDs as $q)
		{
			// Bit of a shortcut this.
			$row = &$modSettings['question_id_cache']['questions'][$q];
			$thisVerification['questions'][] = array(
				'id' => $q,
				'q' => BBCodeParser::load()->parse($row['question']),
				'is_error' => !empty($incorrectQuestions) && in_array($q, $incorrectQuestions),
				// Remember a previous submission?
				'a' => isset($_REQUEST[$verificationOptions['id'] . '_vv'], $_REQUEST[$verificationOptions['id'] . '_vv']['q'], $_REQUEST[$verificationOptions['id'] . '_vv']['q'][$q]) ? $smcFunc['htmlspecialchars']($_REQUEST[$verificationOptions['id'] . '_vv']['q'][$q]) : '',
			);
			$_SESSION[$verificationOptions['id'] . '_vv']['q'][] = $q;
		}
	}

	$_SESSION[$verificationOptions['id'] . '_vv']['count'] = empty($_SESSION[$verificationOptions['id'] . '_vv']['count']) ? 1 : $_SESSION[$verificationOptions['id'] . '_vv']['count'] + 1;

	// Let our hooks know that we are done with the verification process.
	call_integration_hook('integrate_create_control_verification_post', array(&$verification_errors, $do_test));

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
 *
 * @param bool $checkRegistered If set to something other than null, checks whether the callback function is registered
 * @return void|bool Returns whether the callback function is registered if $checkRegistered isn't null
 */
function AutoSuggestHandler($checkRegistered = null)
{
	global $smcFunc, $context;

	// These are all registered types.
	$searchTypes = array(
		'member' => 'Member',
		'membergroups' => 'MemberGroups',
		'versions' => 'SMFVersions',
	);

	call_integration_hook('integrate_autosuggest', array(&$searchTypes));

	// If we're just checking the callback function is registered return true or false.
	if ($checkRegistered != null)
		return isset($searchTypes[$checkRegistered]) && function_exists('AutoSuggest_Search_' . $checkRegistered);

	checkSession('get');
	loadTemplate('Xml');

	// Any parameters?
	$context['search_param'] = isset($_REQUEST['search_param']) ? $smcFunc['json_decode'](base64_decode($_REQUEST['search_param']), true) : array();

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
 * @return array An array of information for displaying the suggestions
 */
function AutoSuggest_Search_Member()
{
	global $user_info, $smcFunc, $context;

	$_REQUEST['search'] = trim($smcFunc['strtolower']($_REQUEST['search'])) . '*';
	$_REQUEST['search'] = strtr($_REQUEST['search'], array('%' => '\%', '_' => '\_', '*' => '%', '?' => '_', '&#038;' => '&amp;'));

	// Find the member.
	$request = $smcFunc['db_query']('', '
		SELECT id_member, real_name
		FROM {db_prefix}members
		WHERE {raw:real_name} LIKE {string:search}' . (!empty($context['search_param']['buddies']) ? '
			AND id_member IN ({array_int:buddy_list})' : '') . '
			AND is_activated IN (1, 11)
		LIMIT ' . ($smcFunc['strlen']($_REQUEST['search']) <= 2 ? '100' : '800'),
		array(
			'real_name' => $smcFunc['db_case_sensitive'] ? 'LOWER(real_name)' : 'real_name',
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

/**
 * Search for a membergroup by name
 *
 * @return array An array of information for displaying the suggestions
 */
function AutoSuggest_Search_MemberGroups()
{
	global $smcFunc;

	$_REQUEST['search'] = trim($smcFunc['strtolower']($_REQUEST['search'])) . '*';
	$_REQUEST['search'] = strtr($_REQUEST['search'], array('%' => '\%', '_' => '\_', '*' => '%', '?' => '_', '&#038;' => '&amp;'));

	// Find the group.
	// Only return groups which are not post-based and not "Hidden", but not the "Administrators" or "Moderators" groups.
	$request = $smcFunc['db_query']('', '
		SELECT id_group, group_name
		FROM {db_prefix}membergroups
		WHERE {raw:group_name} LIKE {string:search}
			AND min_posts = {int:min_posts}
			AND id_group NOT IN ({array_int:invalid_groups})
			AND hidden != {int:hidden}',
		array(
			'group_name' => $smcFunc['db_case_sensitive'] ? 'LOWER(group_name)' : 'group_name',
			'min_posts' => -1,
			'invalid_groups' => array(1, 3),
			'hidden' => 2,
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
		$row['group_name'] = strtr($row['group_name'], array('&amp;' => '&#038;', '&lt;' => '&#060;', '&gt;' => '&#062;', '&quot;' => '&#034;'));

		$xml_data['items']['children'][] = array(
			'attributes' => array(
				'id' => $row['id_group'],
			),
			'value' => $row['group_name'],
		);
	}
	$smcFunc['db_free_result']($request);

	return $xml_data;
}

/**
 * Provides a list of possible SMF versions to use in emulation
 *
 * @return array An array of data for displaying the suggestions
 */
function AutoSuggest_Search_SMFVersions()
{
	global $smcFunc;

	$xml_data = array(
		'items' => array(
			'identifier' => 'item',
			'children' => array(),
		),
	);

	// First try and get it from the database.
	$versions = array();
	$request = $smcFunc['db_query']('', '
		SELECT data
		FROM {db_prefix}admin_info_files
		WHERE filename = {string:latest_versions}
			AND path = {string:path}',
		array(
			'latest_versions' => 'latest-versions.txt',
			'path' => '/smf/',
		)
	);
	if (($smcFunc['db_num_rows']($request) > 0) && ($row = $smcFunc['db_fetch_assoc']($request)) && !empty($row['data']))
	{
		// The file can be either Windows or Linux line endings, but let's ensure we clean it as best we can.
		$possible_versions = explode("\n", $row['data']);
		foreach ($possible_versions as $ver)
		{
			$ver = trim($ver);
			if (strpos($ver, 'SMF') === 0)
				$versions[] = $ver;
		}
	}
	$smcFunc['db_free_result']($request);

	// Just in case we don't have ANYthing.
	if (empty($versions))
		$versions = array(SMF_FULL_VERSION);

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