<?php

/**
 * Forum maintenance. Important stuff.
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2015 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 Beta 2
 */

if (!defined('SMF'))
	die('No direct access...');

/**
 * Main dispatcher, the maintenance access point.
 * This, as usual, checks permissions, loads language files, and forwards to the actual workers.
 */
function ManageMaintenance()
{
	global $txt, $context;

	// You absolutely must be an admin by here!
	isAllowedTo('admin_forum');

	// Need something to talk about?
	loadLanguage('ManageMaintenance');
	loadTemplate('ManageMaintenance');

	// This uses admin tabs - as it should!
	$context[$context['admin_menu_name']]['tab_data'] = array(
		'title' => $txt['maintain_title'],
		'description' => $txt['maintain_info'],
		'tabs' => array(
			'routine' => array(),
			'database' => array(),
			'members' => array(),
			'topics' => array(),
		),
	);

	// So many things you can do - but frankly I won't let you - just these!
	$subActions = array(
		'routine' => array(
			'function' => 'MaintainRoutine',
			'template' => 'maintain_routine',
			'activities' => array(
				'version' => 'VersionDetail',
				'repair' => 'MaintainFindFixErrors',
				'recount' => 'AdminBoardRecount',
				'logs' => 'MaintainEmptyUnimportantLogs',
				'cleancache' => 'MaintainCleanCache',
			),
		),
		'database' => array(
			'function' => 'MaintainDatabase',
			'template' => 'maintain_database',
			'activities' => array(
				'optimize' => 'OptimizeTables',
				'convertentities' => 'ConvertEntities',
				'convertutf8' => 'ConvertUtf8',
				'convertmsgbody' => 'ConvertMsgBody',
			),
		),
		'members' => array(
			'function' => 'MaintainMembers',
			'template' => 'maintain_members',
			'activities' => array(
				'reattribute' => 'MaintainReattributePosts',
				'purgeinactive' => 'MaintainPurgeInactiveMembers',
				'recountposts' => 'MaintainRecountPosts',
			),
		),
		'topics' => array(
			'function' => 'MaintainTopics',
			'template' => 'maintain_topics',
			'activities' => array(
				'massmove' => 'MaintainMassMoveTopics',
				'pruneold' => 'MaintainRemoveOldPosts',
				'olddrafts' => 'MaintainRemoveOldDrafts',
			),
		),
		'hooks' => array(
			'function' => 'list_integration_hooks',
		),
		'destroy' => array(
			'function' => 'Destroy',
			'activities' => array(),
		),
	);

	call_integration_hook('integrate_manage_maintenance', array(&$subActions));

	// Yep, sub-action time!
	if (isset($_REQUEST['sa']) && isset($subActions[$_REQUEST['sa']]))
		$subAction = $_REQUEST['sa'];
	else
		$subAction = 'routine';

	// Doing something special?
	if (isset($_REQUEST['activity']) && isset($subActions[$subAction]['activities'][$_REQUEST['activity']]))
		$activity = $_REQUEST['activity'];

	// Set a few things.
	$context['page_title'] = $txt['maintain_title'];
	$context['sub_action'] = $subAction;
	$context['sub_template'] = !empty($subActions[$subAction]['template']) ? $subActions[$subAction]['template'] : '';

	// Finally fall through to what we are doing.
	call_helper($subActions[$subAction]['function']);

	// Any special activity?
	if (isset($activity))
		call_helper($subActions[$subAction]['activities'][$activity]);

	//converted to UTF-8? show a small maintenance info
	if (isset($_GET['done']) && $_GET['done'] == 'convertutf8')
		$context['maintenance_finished'] = $txt['utf8_title'];

	// Create a maintenance token.  Kinda hard to do it any other way.
	createToken('admin-maint');
}

/**
 * Supporting function for the database maintenance area.
 */
function MaintainDatabase()
{
	global $context, $db_type, $db_character_set, $modSettings, $smcFunc, $txt;

	// Show some conversion options?
	$context['convert_utf8'] = ($db_type == 'mysql' || $db_type == 'mysqli') && (!isset($db_character_set) || $db_character_set !== 'utf8' || empty($modSettings['global_character_set']) || $modSettings['global_character_set'] !== 'UTF-8') && version_compare('4.1.2', preg_replace('~\-.+?$~', '', $smcFunc['db_server_info']()), '<=');
	$context['convert_entities'] = ($db_type == 'mysql' || $db_type == 'mysqli') && isset($db_character_set, $modSettings['global_character_set']) && $db_character_set === 'utf8' && $modSettings['global_character_set'] === 'UTF-8';

	if ($db_type == 'mysql' || $db_type == 'mysqli')
	{
		db_extend('packages');

		$colData = $smcFunc['db_list_columns']('{db_prefix}messages', true);
		foreach ($colData as $column)
			if ($column['name'] == 'body')
				$body_type = $column['type'];

		$context['convert_to'] = $body_type == 'text' ? 'mediumtext' : 'text';
		$context['convert_to_suggest'] = ($body_type != 'text' && !empty($modSettings['max_messageLength']) && $modSettings['max_messageLength'] < 65536);
	}

	if (isset($_GET['done']) && $_GET['done'] == 'convertutf8')
		$context['maintenance_finished'] = $txt['utf8_title'];
	if (isset($_GET['done']) && $_GET['done'] == 'convertentities')
		$context['maintenance_finished'] = $txt['entity_convert_title'];
}

/**
 * Supporting function for the routine maintenance area.
 */
function MaintainRoutine()
{
	global $context, $txt;

	if (isset($_GET['done']) && $_GET['done'] == 'recount')
		$context['maintenance_finished'] = $txt['maintain_recount'];
}

/**
 * Supporting function for the members maintenance area.
 */
function MaintainMembers()
{
	global $context, $smcFunc, $txt;

	// Get membergroups - for deleting members and the like.
	$result = $smcFunc['db_query']('', '
		SELECT id_group, group_name
		FROM {db_prefix}membergroups',
		array(
		)
	);
	$context['membergroups'] = array(
		array(
			'id' => 0,
			'name' => $txt['maintain_members_ungrouped']
		),
	);
	while ($row = $smcFunc['db_fetch_assoc']($result))
	{
		$context['membergroups'][] = array(
			'id' => $row['id_group'],
			'name' => $row['group_name']
		);
	}
	$smcFunc['db_free_result']($result);

	if (isset($_GET['done']) && $_GET['done'] == 'recountposts')
		$context['maintenance_finished'] = $txt['maintain_recountposts'];

	loadJavascriptFile('suggest.js', array('default_theme' => true, 'defer' => false), 'smf_suggest');
}

/**
 * Supporting function for the topics maintenance area.
 */
function MaintainTopics()
{
	global $context, $smcFunc, $txt, $sourcedir;

	// Let's load up the boards in case they are useful.
	$result = $smcFunc['db_query']('order_by_board_order', '
		SELECT b.id_board, b.name, b.child_level, c.name AS cat_name, c.id_cat
		FROM {db_prefix}boards AS b
			LEFT JOIN {db_prefix}categories AS c ON (c.id_cat = b.id_cat)
		WHERE {query_see_board}
			AND redirect = {string:blank_redirect}',
		array(
			'blank_redirect' => '',
		)
	);
	$context['categories'] = array();
	while ($row = $smcFunc['db_fetch_assoc']($result))
	{
		if (!isset($context['categories'][$row['id_cat']]))
			$context['categories'][$row['id_cat']] = array(
				'name' => $row['cat_name'],
				'boards' => array()
			);

		$context['categories'][$row['id_cat']]['boards'][$row['id_board']] = array(
			'id' => $row['id_board'],
			'name' => $row['name'],
			'child_level' => $row['child_level']
		);
	}
	$smcFunc['db_free_result']($result);

	require_once($sourcedir . '/Subs-Boards.php');
	sortCategories($context['categories']);

	if (isset($_GET['done']) && $_GET['done'] == 'purgeold')
		$context['maintenance_finished'] = $txt['maintain_old'];
	elseif (isset($_GET['done']) && $_GET['done'] == 'massmove')
		$context['maintenance_finished'] = $txt['move_topics_maintenance'];
}

/**
 * Find and fix all errors on the forum.
 */
function MaintainFindFixErrors()
{
	global $sourcedir;

	// Honestly, this should be done in the sub function.
	validateToken('admin-maint');

	require_once($sourcedir . '/RepairBoards.php');
	RepairBoards();
}

/**
 * Wipes the whole cache directory.
 * This only applies to SMF's own cache directory, though.
 */
function MaintainCleanCache()
{
	global $context, $txt;

	checkSession();
	validateToken('admin-maint');

	// Just wipe the whole cache directory!
	clean_cache();

	$context['maintenance_finished'] = $txt['maintain_cache'];
}

/**
 * Empties all uninmportant logs
 */
function MaintainEmptyUnimportantLogs()
{
	global $context, $smcFunc, $txt;

	checkSession();
	validateToken('admin-maint');

	// No one's online now.... MUHAHAHAHA :P.
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}log_online');

	// Dump the banning logs.
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}log_banned');

	// Start id_error back at 0 and dump the error log.
	$smcFunc['db_query']('truncate_table', '
		TRUNCATE {db_prefix}log_errors');

	// Clear out the spam log.
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}log_floodcontrol');

	// Last but not least, the search logs!
	$smcFunc['db_query']('truncate_table', '
		TRUNCATE {db_prefix}log_search_topics');

	$smcFunc['db_query']('truncate_table', '
		TRUNCATE {db_prefix}log_search_messages');

	$smcFunc['db_query']('truncate_table', '
		TRUNCATE {db_prefix}log_search_results');

	updateSettings(array('search_pointer' => 0));

	$context['maintenance_finished'] = $txt['maintain_logs'];
}

/**
 * Oh noes! I'd document this but that would give it away
 */
function Destroy()
{
	global $context;

	echo '<!DOCTYPE html>
		<html', $context['right_to_left'] ? ' dir="rtl"' : '', '><head><title>', $context['forum_name_html_safe'], ' deleted!</title></head>
		<body style="background-color: orange; font-family: arial, sans-serif; text-align: center;">
		<div style="margin-top: 8%; font-size: 400%; color: black;">Oh my, you killed ', $context['forum_name_html_safe'], '!</div>
		<div style="margin-top: 7%; font-size: 500%; color: red;"><strong>You lazy bum!</strong></div>
		</body></html>';
	obExit(false);
}

/**
 * Convert both data and database tables to UTF-8 character set.
 * It requires the admin_forum permission.
 * This only works if UTF-8 is not the global character set.
 * It supports all character sets used by SMF's language files.
 * It redirects to ?action=admin;area=maintain after finishing.
 * This action is linked from the maintenance screen (if it's applicable).
 * Accessed by ?action=admin;area=maintain;sa=database;activity=convertutf8.
 *
 * @uses the convert_utf8 sub template of the Admin template.
 */
function ConvertUtf8()
{
	global $context, $txt, $language, $db_character_set;
	global $modSettings, $user_info, $sourcedir, $smcFunc, $db_prefix;

	// Show me your badge!
	isAllowedTo('admin_forum');

	// The character sets used in SMF's language files with their db equivalent.
	$charsets = array(
		// Chinese-traditional.
		'big5' => 'big5',
		// Chinese-simplified.
		'gbk' => 'gbk',
		// West European.
		'ISO-8859-1' => 'latin1',
		// Romanian.
		'ISO-8859-2' => 'latin2',
		// Turkish.
		'ISO-8859-9' => 'latin5',
		// West European with Euro sign.
		'ISO-8859-15' => 'latin9',
		// Thai.
		'tis-620' => 'tis620',
		// Persian, Chinese, etc.
		'UTF-8' => 'utf8',
		// Russian.
		'windows-1251' => 'cp1251',
		// Greek.
		'windows-1253' => 'utf8',
		// Hebrew.
		'windows-1255' => 'utf8',
		// Arabic.
		'windows-1256' => 'cp1256',
	);

	// Get a list of character sets supported by your MySQL server.
	$request = $smcFunc['db_query']('', '
		SHOW CHARACTER SET',
		array(
		)
	);
	$db_charsets = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$db_charsets[] = $row['Charset'];

	$smcFunc['db_free_result']($request);

	// Character sets supported by both MySQL and SMF's language files.
	$charsets = array_intersect($charsets, $db_charsets);

	// This is for the first screen telling backups is good.
	if (!isset($_POST['proceed']))
	{
		validateToken('admin-maint');

		// Character set conversions are only supported as of MySQL 4.1.2.
		if (version_compare('4.1.2', preg_replace('~\-.+?$~', '', $smcFunc['db_server_info']()), '>'))
			fatal_lang_error('utf8_db_version_too_low');

		// Use the messages.body column as indicator for the database charset.
		$request = $smcFunc['db_query']('', '
			SHOW FULL COLUMNS
			FROM {db_prefix}messages
			LIKE {string:body_like}',
			array(
				'body_like' => 'body',
			)
		);
		$column_info = $smcFunc['db_fetch_assoc']($request);
		$smcFunc['db_free_result']($request);

		// A collation looks like latin1_swedish. We only need the character set.
		list($context['database_charset']) = explode('_', $column_info['Collation']);
		$context['database_charset'] = in_array($context['database_charset'], $charsets) ? array_search($context['database_charset'], $charsets) : $context['database_charset'];

		// No need to convert to UTF-8 if it already is.
		if ($db_character_set === 'utf8' && !empty($modSettings['global_character_set']) && $modSettings['global_character_set'] === 'UTF-8')
			fatal_lang_error('utf8_already_utf8');

		// Detect whether a fulltext index is set.
		db_extend('search');
		if ($smcFunc['db_search_support']('fulltext'))
		{
			require_once($sourcedir . '/ManageSearch.php');
			detectFulltextIndex();
		}
		// Cannot do conversion if using a fulltext index
		if (!empty($modSettings['search_index']) && $modSettings['search_index'] == 'fulltext' || !empty($context['fulltext_index']))
			fatal_lang_error('utf8_cannot_convert_fulltext');

		// Grab the character set from the default language file.
		loadLanguage('index', $language, true);
		$context['charset_detected'] = $txt['lang_character_set'];
		$context['charset_about_detected'] = sprintf($txt['utf8_detected_charset'], $language, $context['charset_detected']);

		// Go back to your own language.
		loadLanguage('index', $user_info['language'], true);

		// Show a warning if the character set seems not to be supported.
		if (!isset($charsets[strtr(strtolower($context['charset_detected']), array('utf' => 'UTF', 'iso' => 'ISO'))]))
		{
			$context['charset_warning'] = sprintf($txt['utf8_charset_not_supported'], $txt['lang_character_set']);

			// Default to ISO-8859-1.
			$context['charset_detected'] = 'ISO-8859-1';
		}

		$context['charset_list'] = array_keys($charsets);

		$context['page_title'] = $txt['utf8_title'];
		$context['sub_template'] = 'convert_utf8';

		createToken('admin-maint');
		return;
	}

	// After this point we're starting the conversion. But first: session check.
	checkSession();
	validateToken('admin-maint');
	createToken('admin-maint');

	// Translation table for the character sets not native for MySQL.
	$translation_tables = array(
		'windows-1255' => array(
			'0x81' => '\'\'',		'0x8A' => '\'\'',		'0x8C' => '\'\'',
			'0x8D' => '\'\'',		'0x8E' => '\'\'',		'0x8F' => '\'\'',
			'0x90' => '\'\'',		'0x9A' => '\'\'',		'0x9C' => '\'\'',
			'0x9D' => '\'\'',		'0x9E' => '\'\'',		'0x9F' => '\'\'',
			'0xCA' => '\'\'',		'0xD9' => '\'\'',		'0xDA' => '\'\'',
			'0xDB' => '\'\'',		'0xDC' => '\'\'',		'0xDD' => '\'\'',
			'0xDE' => '\'\'',		'0xDF' => '\'\'',		'0xFB' => '\'\'',
			'0xFC' => '\'\'',		'0xFF' => '\'\'',		'0xC2' => '0xFF',
			'0x80' => '0xFC',		'0xE2' => '0xFB',		'0xA0' => '0xC2A0',
			'0xA1' => '0xC2A1',		'0xA2' => '0xC2A2',		'0xA3' => '0xC2A3',
			'0xA5' => '0xC2A5',		'0xA6' => '0xC2A6',		'0xA7' => '0xC2A7',
			'0xA8' => '0xC2A8',		'0xA9' => '0xC2A9',		'0xAB' => '0xC2AB',
			'0xAC' => '0xC2AC',		'0xAD' => '0xC2AD',		'0xAE' => '0xC2AE',
			'0xAF' => '0xC2AF',		'0xB0' => '0xC2B0',		'0xB1' => '0xC2B1',
			'0xB2' => '0xC2B2',		'0xB3' => '0xC2B3',		'0xB4' => '0xC2B4',
			'0xB5' => '0xC2B5',		'0xB6' => '0xC2B6',		'0xB7' => '0xC2B7',
			'0xB8' => '0xC2B8',		'0xB9' => '0xC2B9',		'0xBB' => '0xC2BB',
			'0xBC' => '0xC2BC',		'0xBD' => '0xC2BD',		'0xBE' => '0xC2BE',
			'0xBF' => '0xC2BF',		'0xD7' => '0xD7B3',		'0xD1' => '0xD781',
			'0xD4' => '0xD7B0',		'0xD5' => '0xD7B1',		'0xD6' => '0xD7B2',
			'0xE0' => '0xD790',		'0xEA' => '0xD79A',		'0xEC' => '0xD79C',
			'0xED' => '0xD79D',		'0xEE' => '0xD79E',		'0xEF' => '0xD79F',
			'0xF0' => '0xD7A0',		'0xF1' => '0xD7A1',		'0xF2' => '0xD7A2',
			'0xF3' => '0xD7A3',		'0xF5' => '0xD7A5',		'0xF6' => '0xD7A6',
			'0xF7' => '0xD7A7',		'0xF8' => '0xD7A8',		'0xF9' => '0xD7A9',
			'0x82' => '0xE2809A',	'0x84' => '0xE2809E',	'0x85' => '0xE280A6',
			'0x86' => '0xE280A0',	'0x87' => '0xE280A1',	'0x89' => '0xE280B0',
			'0x8B' => '0xE280B9',	'0x93' => '0xE2809C',	'0x94' => '0xE2809D',
			'0x95' => '0xE280A2',	'0x97' => '0xE28094',	'0x99' => '0xE284A2',
			'0xC0' => '0xD6B0',		'0xC1' => '0xD6B1',		'0xC3' => '0xD6B3',
			'0xC4' => '0xD6B4',		'0xC5' => '0xD6B5',		'0xC6' => '0xD6B6',
			'0xC7' => '0xD6B7',		'0xC8' => '0xD6B8',		'0xC9' => '0xD6B9',
			'0xCB' => '0xD6BB',		'0xCC' => '0xD6BC',		'0xCD' => '0xD6BD',
			'0xCE' => '0xD6BE',		'0xCF' => '0xD6BF',		'0xD0' => '0xD780',
			'0xD2' => '0xD782',		'0xE3' => '0xD793',		'0xE4' => '0xD794',
			'0xE5' => '0xD795',		'0xE7' => '0xD797',		'0xE9' => '0xD799',
			'0xFD' => '0xE2808E',	'0xFE' => '0xE2808F',	'0x92' => '0xE28099',
			'0x83' => '0xC692',		'0xD3' => '0xD783',		'0x88' => '0xCB86',
			'0x98' => '0xCB9C',		'0x91' => '0xE28098',	'0x96' => '0xE28093',
			'0xBA' => '0xC3B7',		'0x9B' => '0xE280BA',	'0xAA' => '0xC397',
			'0xA4' => '0xE282AA',	'0xE1' => '0xD791',		'0xE6' => '0xD796',
			'0xE8' => '0xD798',		'0xEB' => '0xD79B',		'0xF4' => '0xD7A4',
			'0xFA' => '0xD7AA',		'0xFF' => '0xD6B2',		'0xFC' => '0xE282AC',
			'0xFB' => '0xD792',
		),
		'windows-1253' => array(
			'0x81' => '\'\'',			'0x88' => '\'\'',			'0x8A' => '\'\'',
			'0x8C' => '\'\'',			'0x8D' => '\'\'',			'0x8E' => '\'\'',
			'0x8F' => '\'\'',			'0x90' => '\'\'',			'0x98' => '\'\'',
			'0x9A' => '\'\'',			'0x9C' => '\'\'',			'0x9D' => '\'\'',
			'0x9E' => '\'\'',			'0x9F' => '\'\'',			'0xAA' => '\'\'',
			'0xD2' => '\'\'',			'0xFF' => '\'\'',			'0xCE' => '0xCE9E',
			'0xB8' => '0xCE88',		'0xBA' => '0xCE8A',		'0xBC' => '0xCE8C',
			'0xBE' => '0xCE8E',		'0xBF' => '0xCE8F',		'0xC0' => '0xCE90',
			'0xC8' => '0xCE98',		'0xCA' => '0xCE9A',		'0xCC' => '0xCE9C',
			'0xCD' => '0xCE9D',		'0xCF' => '0xCE9F',		'0xDA' => '0xCEAA',
			'0xE8' => '0xCEB8',		'0xEA' => '0xCEBA',		'0xEC' => '0xCEBC',
			'0xEE' => '0xCEBE',		'0xEF' => '0xCEBF',		'0xC2' => '0xFF',
			'0xBD' => '0xC2BD',		'0xED' => '0xCEBD',		'0xB2' => '0xC2B2',
			'0xA0' => '0xC2A0',		'0xA3' => '0xC2A3',		'0xA4' => '0xC2A4',
			'0xA5' => '0xC2A5',		'0xA6' => '0xC2A6',		'0xA7' => '0xC2A7',
			'0xA8' => '0xC2A8',		'0xA9' => '0xC2A9',		'0xAB' => '0xC2AB',
			'0xAC' => '0xC2AC',		'0xAD' => '0xC2AD',		'0xAE' => '0xC2AE',
			'0xB0' => '0xC2B0',		'0xB1' => '0xC2B1',		'0xB3' => '0xC2B3',
			'0xB5' => '0xC2B5',		'0xB6' => '0xC2B6',		'0xB7' => '0xC2B7',
			'0xBB' => '0xC2BB',		'0xE2' => '0xCEB2',		'0x80' => '0xD2',
			'0x82' => '0xE2809A',	'0x84' => '0xE2809E',	'0x85' => '0xE280A6',
			'0x86' => '0xE280A0',	'0xA1' => '0xCE85',		'0xA2' => '0xCE86',
			'0x87' => '0xE280A1',	'0x89' => '0xE280B0',	'0xB9' => '0xCE89',
			'0x8B' => '0xE280B9',	'0x91' => '0xE28098',	'0x99' => '0xE284A2',
			'0x92' => '0xE28099',	'0x93' => '0xE2809C',	'0x94' => '0xE2809D',
			'0x95' => '0xE280A2',	'0x96' => '0xE28093',	'0x97' => '0xE28094',
			'0x9B' => '0xE280BA',	'0xAF' => '0xE28095',	'0xB4' => '0xCE84',
			'0xC1' => '0xCE91',		'0xC3' => '0xCE93',		'0xC4' => '0xCE94',
			'0xC5' => '0xCE95',		'0xC6' => '0xCE96',		'0x83' => '0xC692',
			'0xC7' => '0xCE97',		'0xC9' => '0xCE99',		'0xCB' => '0xCE9B',
			'0xD0' => '0xCEA0',		'0xD1' => '0xCEA1',		'0xD3' => '0xCEA3',
			'0xD4' => '0xCEA4',		'0xD5' => '0xCEA5',		'0xD6' => '0xCEA6',
			'0xD7' => '0xCEA7',		'0xD8' => '0xCEA8',		'0xD9' => '0xCEA9',
			'0xDB' => '0xCEAB',		'0xDC' => '0xCEAC',		'0xDD' => '0xCEAD',
			'0xDE' => '0xCEAE',		'0xDF' => '0xCEAF',		'0xE0' => '0xCEB0',
			'0xE1' => '0xCEB1',		'0xE3' => '0xCEB3',		'0xE4' => '0xCEB4',
			'0xE5' => '0xCEB5',		'0xE6' => '0xCEB6',		'0xE7' => '0xCEB7',
			'0xE9' => '0xCEB9',		'0xEB' => '0xCEBB',		'0xF0' => '0xCF80',
			'0xF1' => '0xCF81',		'0xF2' => '0xCF82',		'0xF3' => '0xCF83',
			'0xF4' => '0xCF84',		'0xF5' => '0xCF85',		'0xF6' => '0xCF86',
			'0xF7' => '0xCF87',		'0xF8' => '0xCF88',		'0xF9' => '0xCF89',
			'0xFA' => '0xCF8A',		'0xFB' => '0xCF8B',		'0xFC' => '0xCF8C',
			'0xFD' => '0xCF8D',		'0xFE' => '0xCF8E',		'0xFF' => '0xCE92',
			'0xD2' => '0xE282AC',
		),
	);

	// Make some preparations.
	if (isset($translation_tables[$_POST['src_charset']]))
	{
		$replace = '%field%';
		foreach ($translation_tables[$_POST['src_charset']] as $from => $to)
			$replace = 'REPLACE(' . $replace . ', ' . $from . ', ' . $to . ')';
	}

	// Grab a list of tables.
	if (preg_match('~^`(.+?)`\.(.+?)$~', $db_prefix, $match) === 1)
		$queryTables = $smcFunc['db_query']('', '
			SHOW TABLE STATUS
			FROM `' . strtr($match[1], array('`' => '')) . '`
			LIKE {string:table_name}',
			array(
				'table_name' => str_replace('_', '\_', $match[2]) . '%',
			)
		);
	else
		$queryTables = $smcFunc['db_query']('', '
			SHOW TABLE STATUS
			LIKE {string:table_name}',
			array(
				'table_name' => str_replace('_', '\_', $db_prefix) . '%',
			)
		);

	while ($table_info = $smcFunc['db_fetch_assoc']($queryTables))
	{
		// Just to make sure it doesn't time out.
		if (function_exists('apache_reset_timeout'))
			@apache_reset_timeout();

		$table_charsets = array();

		// Loop through each column.
		$queryColumns = $smcFunc['db_query']('', '
			SHOW FULL COLUMNS
			FROM ' . $table_info['Name'],
			array(
			)
		);
		while ($column_info = $smcFunc['db_fetch_assoc']($queryColumns))
		{
			// Only text'ish columns have a character set and need converting.
			if (strpos($column_info['Type'], 'text') !== false || strpos($column_info['Type'], 'char') !== false)
			{
				$collation = empty($column_info['Collation']) || $column_info['Collation'] === 'NULL' ? $table_info['Collation'] : $column_info['Collation'];
				if (!empty($collation) && $collation !== 'NULL')
				{
					list($charset) = explode('_', $collation);

					if (!isset($table_charsets[$charset]))
						$table_charsets[$charset] = array();

					$table_charsets[$charset][] = $column_info;
				}
			}
		}
		$smcFunc['db_free_result']($queryColumns);

		// Only change the column if the data doesn't match the current charset.
		if ((count($table_charsets) === 1 && key($table_charsets) !== $charsets[$_POST['src_charset']]) || count($table_charsets) > 1)
		{
			$updates_blob = '';
			$updates_text = '';
			foreach ($table_charsets as $charset => $columns)
			{
				if ($charset !== $charsets[$_POST['src_charset']])
				{
					foreach ($columns as $column)
					{
						$updates_blob .= '
							CHANGE COLUMN `' . $column['Field'] . '` `' . $column['Field'] . '` ' . strtr($column['Type'], array('text' => 'blob', 'char' => 'binary')) . ($column['Null'] === 'YES' ? ' NULL' : ' NOT NULL') . (strpos($column['Type'], 'char') === false ? '' : ' default \'' . $column['Default'] . '\'') . ',';
						$updates_text .= '
							CHANGE COLUMN `' . $column['Field'] . '` `' . $column['Field'] . '` ' . $column['Type'] . ' CHARACTER SET ' . $charsets[$_POST['src_charset']] . ($column['Null'] === 'YES' ? '' : ' NOT NULL') . (strpos($column['Type'], 'char') === false ? '' : ' default \'' . $column['Default'] . '\'') . ',';
					}
				}
			}

			// Change the columns to binary form.
			$smcFunc['db_query']('', '
				ALTER TABLE {raw:table_name}{raw:updates_blob}',
				array(
					'table_name' => $table_info['Name'],
					'updates_blob' => substr($updates_blob, 0, -1),
				)
			);

			// Convert the character set if MySQL has no native support for it.
			if (isset($translation_tables[$_POST['src_charset']]))
			{
				$update = '';
				foreach ($table_charsets as $charset => $columns)
					foreach ($columns as $column)
						$update .= '
							' . $column['Field'] . ' = ' . strtr($replace, array('%field%' => $column['Field'])) . ',';

				$smcFunc['db_query']('', '
					UPDATE {raw:table_name}
					SET {raw:updates}',
					array(
						'table_name' => $table_info['Name'],
						'updates' => substr($update, 0, -1),
					)
				);
			}

			// Change the columns back, but with the proper character set.
			$smcFunc['db_query']('', '
				ALTER TABLE {raw:table_name}{raw:updates_text}',
				array(
					'table_name' => $table_info['Name'],
					'updates_text' => substr($updates_text, 0, -1),
				)
			);
		}

		// Now do the actual conversion (if still needed).
		if ($charsets[$_POST['src_charset']] !== 'utf8')
			$smcFunc['db_query']('', '
				ALTER TABLE {raw:table_name}
				CONVERT TO CHARACTER SET utf8',
				array(
					'table_name' => $table_info['Name'],
				)
			);
	}
	$smcFunc['db_free_result']($queryTables);

	call_integration_hook('integrate_convert_utf8');

	// Let the settings know we have a new character set.
	updateSettings(array('global_character_set' => 'UTF-8', 'previousCharacterSet' => (empty($translation_tables[$_POST['src_charset']])) ? $charsets[$_POST['src_charset']] : $translation_tables[$_POST['src_charset']]));

	// Store it in Settings.php too because it's needed before db connection.
	require_once($sourcedir . '/Subs-Admin.php');
	updateSettingsFile(array('db_character_set' => '\'utf8\''));

	// The conversion might have messed up some serialized strings. Fix them!
	require_once($sourcedir . '/Subs-Charset.php');
	fix_serialized_columns();

	redirectexit('action=admin;area=maintain;done=convertutf8');
}

/**
 * Convert the column "body" of the table {db_prefix}messages from TEXT to MEDIUMTEXT and vice versa.
 * It requires the admin_forum permission.
 * This is needed only for MySQL.
 * During the conversion from MEDIUMTEXT to TEXT it check if any of the posts exceed the TEXT length and if so it aborts.
 * This action is linked from the maintenance screen (if it's applicable).
 * Accessed by ?action=admin;area=maintain;sa=database;activity=convertmsgbody.
 *
 * @uses the convert_msgbody sub template of the Admin template.
 */
function ConvertMsgBody()
{
	global $scripturl, $context, $txt, $db_type;
	global $modSettings, $smcFunc, $time_start;

	// Show me your badge!
	isAllowedTo('admin_forum');

	if ($db_type != 'mysql' && $db_type != 'mysqli')
		return;

	db_extend('packages');

	$colData = $smcFunc['db_list_columns']('{db_prefix}messages', true);
	foreach ($colData as $column)
		if ($column['name'] == 'body')
			$body_type = $column['type'];

	$context['convert_to'] = $body_type == 'text' ? 'mediumtext' : 'text';

	if ($body_type == 'text' || ($body_type != 'text' && isset($_POST['do_conversion'])))
	{
		checkSession();
		validateToken('admin-maint');

		// Make it longer so we can do their limit.
		if ($body_type == 'text')
			$smcFunc['db_change_column']('{db_prefix}messages', 'body', array('type' => 'mediumtext'));
		// Shorten the column so we can have a bit (literally per record) less space occupied
		else
			$smcFunc['db_change_column']('{db_prefix}messages', 'body', array('type' => 'text'));

		$colData = $smcFunc['db_list_columns']('{db_prefix}messages', true);
		foreach ($colData as $column)
			if ($column['name'] == 'body')
				$body_type = $column['type'];

		$context['maintenance_finished'] = $txt[$context['convert_to'] . '_title'];
		$context['convert_to'] = $body_type == 'text' ? 'mediumtext' : 'text';
		$context['convert_to_suggest'] = ($body_type != 'text' && !empty($modSettings['max_messageLength']) && $modSettings['max_messageLength'] < 65536);

		return;
		redirectexit('action=admin;area=maintain;sa=database');
	}
	elseif ($body_type != 'text' && (!isset($_POST['do_conversion']) || isset($_POST['cont'])))
	{
		checkSession();
		if (empty($_REQUEST['start']))
			validateToken('admin-maint');
		else
			validateToken('admin-convertMsg');

		$context['page_title'] = $txt['not_done_title'];
		$context['continue_post_data'] = '';
		$context['continue_countdown'] = 3;
		$context['sub_template'] = 'not_done';
		$increment = 500;
		$id_msg_exceeding = isset($_POST['id_msg_exceeding']) ? explode(',', $_POST['id_msg_exceeding']) : array();

		$request = $smcFunc['db_query']('', '
			SELECT COUNT(*) as count
			FROM {db_prefix}messages',
			array()
		);
		list($max_msgs) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);

		// Try for as much time as possible.
		@set_time_limit(600);

		while ($_REQUEST['start'] < $max_msgs)
		{
			$request = $smcFunc['db_query']('', '
				SELECT /*!40001 SQL_NO_CACHE */ id_msg
				FROM {db_prefix}messages
				WHERE id_msg BETWEEN {int:start} AND {int:start} + {int:increment}
					AND LENGTH(body) > 65535',
				array(
					'start' => $_REQUEST['start'],
					'increment' => $increment - 1,
				)
			);
			while ($row = $smcFunc['db_fetch_assoc']($request))
				$id_msg_exceeding[] = $row['id_msg'];
			$smcFunc['db_free_result']($request);

			$_REQUEST['start'] += $increment;

			if (array_sum(explode(' ', microtime())) - array_sum(explode(' ', $time_start)) > 3)
			{
				createToken('admin-convertMsg');
				$context['continue_post_data'] = '
					<input type="hidden" name="' . $context['admin-convertMsg_token_var'] . '" value="' . $context['admin-convertMsg_token'] . '">
					<input type="hidden" name="' . $context['session_var'] . '" value="' . $context['session_id'] . '">
					<input type="hidden" name="id_msg_exceeding" value="' . implode(',', $id_msg_exceeding) . '">';

				$context['continue_get_data'] = '?action=admin;area=maintain;sa=database;activity=convertmsgbody;start=' . $_REQUEST['start'];
				$context['continue_percent'] = round(100 * $_REQUEST['start'] / $max_msgs);

				return;
			}
		}
		createToken('admin-maint');
		$context['page_title'] = $txt[$context['convert_to'] . '_title'];
		$context['sub_template'] = 'convert_msgbody';

		if (!empty($id_msg_exceeding))
		{
			if (count($id_msg_exceeding) > 100)
			{
				$query_msg = array_slice($id_msg_exceeding, 0, 100);
				$context['exceeding_messages_morethan'] = sprintf($txt['exceeding_messages_morethan'], count($id_msg_exceeding));
			}
			else
				$query_msg = $id_msg_exceeding;

			$context['exceeding_messages'] = array();
			$request = $smcFunc['db_query']('', '
				SELECT id_msg, id_topic, subject
				FROM {db_prefix}messages
				WHERE id_msg IN ({array_int:messages})',
				array(
					'messages' => $query_msg,
				)
			);
			while ($row = $smcFunc['db_fetch_assoc']($request))
				$context['exceeding_messages'][] = '<a href="' . $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . '#msg' . $row['id_msg'] . '">' . $row['subject'] . '</a>';
			$smcFunc['db_free_result']($request);
		}
	}
}

/**
 * Converts HTML-entities to their UTF-8 character equivalents.
 * This requires the admin_forum permission.
 * Pre-condition: UTF-8 has been set as database and global character set.
 *
 * It is divided in steps of 10 seconds.
 * This action is linked from the maintenance screen (if applicable).
 * It is accessed by ?action=admin;area=maintain;sa=database;activity=convertentities.
 *
 * @uses Admin template, convert_entities sub-template.
 */
function ConvertEntities()
{
	global $db_character_set, $modSettings, $context, $sourcedir, $smcFunc;

	isAllowedTo('admin_forum');

	// Check to see if UTF-8 is currently the default character set.
	if ($modSettings['global_character_set'] !== 'UTF-8' || !isset($db_character_set) || $db_character_set !== 'utf8')
		fatal_lang_error('entity_convert_only_utf8');

	// Some starting values.
	$context['table'] = empty($_REQUEST['table']) ? 0 : (int) $_REQUEST['table'];
	$context['start'] = empty($_REQUEST['start']) ? 0 : (int) $_REQUEST['start'];

	$context['start_time'] = time();

	$context['first_step'] = !isset($_REQUEST[$context['session_var']]);
	$context['last_step'] = false;

	// The first step is just a text screen with some explanation.
	if ($context['first_step'])
	{
		validateToken('admin-maint');
		createToken('admin-maint');

		$context['sub_template'] = 'convert_entities';
		return;
	}
	// Otherwise use the generic "not done" template.
	$context['sub_template'] = 'not_done';
	$context['continue_post_data'] = '';
	$context['continue_countdown'] = 3;

	// Now we're actually going to convert...
	checkSession('request');
	validateToken('admin-maint');
	createToken('admin-maint');

	// A list of tables ready for conversion.
	$tables = array(
		'ban_groups',
		'ban_items',
		'boards',
		'calendar',
		'calendar_holidays',
		'categories',
		'log_errors',
		'log_search_subjects',
		'membergroups',
		'members',
		'message_icons',
		'messages',
		'package_servers',
		'personal_messages',
		'pm_recipients',
		'polls',
		'poll_choices',
		'smileys',
		'themes',
	);
	$context['num_tables'] = count($tables);

	// Loop through all tables that need converting.
	for (; $context['table'] < $context['num_tables']; $context['table']++)
	{
		$cur_table = $tables[$context['table']];
		$primary_key = '';
		// Make sure we keep stuff unique!
		$primary_keys = array();

		if (function_exists('apache_reset_timeout'))
			@apache_reset_timeout();

		// Get a list of text columns.
		$columns = array();
		$request = $smcFunc['db_query']('', '
			SHOW FULL COLUMNS
			FROM {db_prefix}' . $cur_table,
			array(
			)
		);
		while ($column_info = $smcFunc['db_fetch_assoc']($request))
			if (strpos($column_info['Type'], 'text') !== false || strpos($column_info['Type'], 'char') !== false)
				$columns[] = strtolower($column_info['Field']);

		// Get the column with the (first) primary key.
		$request = $smcFunc['db_query']('', '
			SHOW KEYS
			FROM {db_prefix}' . $cur_table,
			array(
			)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			if ($row['Key_name'] === 'PRIMARY')
			{
				if (empty($primary_key) || ($row['Seq_in_index'] == 1 && !in_array(strtolower($row['Column_name']), $columns)))
					$primary_key = $row['Column_name'];

				$primary_keys[] = $row['Column_name'];
			}
		}
		$smcFunc['db_free_result']($request);

		// No primary key, no glory.
		// Same for columns. Just to be sure we've work to do!
		if (empty($primary_key) || empty($columns))
			continue;

		// Get the maximum value for the primary key.
		$request = $smcFunc['db_query']('', '
			SELECT MAX(' . $primary_key . ')
			FROM {db_prefix}' . $cur_table,
			array(
			)
		);
		list($max_value) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);

		if (empty($max_value))
			continue;

		while ($context['start'] <= $max_value)
		{
			// Retrieve a list of rows that has at least one entity to convert.
			$request = $smcFunc['db_query']('', '
				SELECT {raw:primary_keys}, {raw:columns}
				FROM {db_prefix}{raw:cur_table}
				WHERE {raw:primary_key} BETWEEN {int:start} AND {int:start} + 499
					AND {raw:like_compare}
				LIMIT 500',
				array(
					'primary_keys' => implode(', ', $primary_keys),
					'columns' => implode(', ', $columns),
					'cur_table' => $cur_table,
					'primary_key' => $primary_key,
					'start' => $context['start'],
					'like_compare' => '(' . implode(' LIKE \'%&#%\' OR ', $columns) . ' LIKE \'%&#%\')',
				)
			);
			while ($row = $smcFunc['db_fetch_assoc']($request))
			{
				$insertion_variables = array();
				$changes = array();
				foreach ($row as $column_name => $column_value)
					if ($column_name !== $primary_key && strpos($column_value, '&#') !== false)
					{
						$changes[] = $column_name . ' = {string:changes_' . $column_name . '}';
						$insertion_variables['changes_' . $column_name] = preg_replace_callback('~&#(\d{1,7}|x[0-9a-fA-F]{1,6});~', 'fixchar__callback', $column_value);
					}

				$where = array();
				foreach ($primary_keys as $key)
				{
					$where[] = $key . ' = {string:where_' . $key . '}';
					$insertion_variables['where_' . $key] = $row[$key];
				}

				// Update the row.
				if (!empty($changes))
					$smcFunc['db_query']('', '
						UPDATE {db_prefix}' . $cur_table . '
						SET
							' . implode(',
							', $changes) . '
						WHERE ' . implode(' AND ', $where),
						$insertion_variables
					);
			}
			$smcFunc['db_free_result']($request);
			$context['start'] += 500;

			// After ten seconds interrupt.
			if (time() - $context['start_time'] > 10)
			{
				// Calculate an approximation of the percentage done.
				$context['continue_percent'] = round(100 * ($context['table'] + ($context['start'] / $max_value)) / $context['num_tables'], 1);
				$context['continue_get_data'] = '?action=admin;area=maintain;sa=database;activity=convertentities;table=' . $context['table'] . ';start=' . $context['start'] . ';' . $context['session_var'] . '=' . $context['session_id'];
				return;
			}
		}
		$context['start'] = 0;
	}

	// Make sure all serialized strings are all right.
	require_once($sourcedir . '/Subs-Charset.php');
	fix_serialized_columns();

	// If we're here, we must be done.
	$context['continue_percent'] = 100;
	$context['continue_get_data'] = '?action=admin;area=maintain;sa=database;done=convertentities';
	$context['last_step'] = true;
	$context['continue_countdown'] = -1;
}

/**
 * Optimizes all tables in the database and lists how much was saved.
 * It requires the admin_forum permission.
 * It shows as the maintain_forum admin area.
 * It is accessed from ?action=admin;area=maintain;sa=database;activity=optimize.
 * It also updates the optimize scheduled task such that the tables are not automatically optimized again too soon.

 * @uses the optimize sub template
 */
function OptimizeTables()
{
	global $db_prefix, $txt, $context, $smcFunc;

	isAllowedTo('admin_forum');

	checkSession();
	validateToken('admin-maint');

	ignore_user_abort(true);
	db_extend();

	// Start with no tables optimized.
	$opttab = 0;

	$context['page_title'] = $txt['database_optimize'];
	$context['sub_template'] = 'optimize';

	// Only optimize the tables related to this smf install, not all the tables in the db
	$real_prefix = preg_match('~^(`?)(.+?)\\1\\.(.*?)$~', $db_prefix, $match) === 1 ? $match[3] : $db_prefix;

	// Get a list of tables, as well as how many there are.
	$temp_tables = $smcFunc['db_list_tables'](false, $real_prefix . '%');
	$tables = array();
	foreach ($temp_tables as $table)
		$tables[] = array('table_name' => $table);

	// If there aren't any tables then I believe that would mean the world has exploded...
	$context['num_tables'] = count($tables);
	if ($context['num_tables'] == 0)
		fatal_error('You appear to be running SMF in a flat file mode... fantastic!', false);

	// For each table....
	$context['optimized_tables'] = array();
	foreach ($tables as $table)
	{
		// Optimize the table!  We use backticks here because it might be a custom table.
		$data_freed = $smcFunc['db_optimize_table']($table['table_name']);

		if ($data_freed > 0)
			$context['optimized_tables'][] = array(
				'name' => $table['table_name'],
				'data_freed' => $data_freed,
			);
	}

	// Number of tables, etc....
	$txt['database_numb_tables'] = sprintf($txt['database_numb_tables'], $context['num_tables']);
	$context['num_tables_optimized'] = count($context['optimized_tables']);
}

/**
 * Recount many forum totals that can be recounted automatically without harm.
 * it requires the admin_forum permission.
 * It shows the maintain_forum admin area.
 *
 * Totals recounted:
 * - fixes for topics with wrong num_replies.
 * - updates for num_posts and num_topics of all boards.
 * - recounts instant_messages but not unread_messages.
 * - repairs messages pointing to boards with topics pointing to other boards.
 * - updates the last message posted in boards and children.
 * - updates member count, latest member, topic count, and message count.
 *
 * The function redirects back to ?action=admin;area=maintain when complete.
 * It is accessed via ?action=admin;area=maintain;sa=database;activity=recount.
 */
function AdminBoardRecount()
{
	global $txt, $context, $modSettings, $sourcedir;
	global $time_start, $smcFunc;

	isAllowedTo('admin_forum');
	checkSession('request');

	// validate the request or the loop
	if (!isset($_REQUEST['step']))
		validateToken('admin-maint');
	else
		validateToken('admin-boardrecount');

	$context['page_title'] = $txt['not_done_title'];
	$context['continue_post_data'] = '';
	$context['continue_countdown'] = 3;
	$context['sub_template'] = 'not_done';

	// Try for as much time as possible.
	@set_time_limit(600);

	// Step the number of topics at a time so things don't time out...
	$request = $smcFunc['db_query']('', '
		SELECT MAX(id_topic)
		FROM {db_prefix}topics',
		array(
		)
	);
	list ($max_topics) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	$increment = min(max(50, ceil($max_topics / 4)), 2000);
	if (empty($_REQUEST['start']))
		$_REQUEST['start'] = 0;

	$total_steps = 8;

	// Get each topic with a wrong reply count and fix it - let's just do some at a time, though.
	if (empty($_REQUEST['step']))
	{
		$_REQUEST['step'] = 0;

		while ($_REQUEST['start'] < $max_topics)
		{
			// Recount approved messages
			$request = $smcFunc['db_query']('', '
				SELECT /*!40001 SQL_NO_CACHE */ t.id_topic, MAX(t.num_replies) AS num_replies,
					CASE WHEN COUNT(ma.id_msg) >= 1 THEN COUNT(ma.id_msg) - 1 ELSE 0 END AS real_num_replies
				FROM {db_prefix}topics AS t
					LEFT JOIN {db_prefix}messages AS ma ON (ma.id_topic = t.id_topic AND ma.approved = {int:is_approved})
				WHERE t.id_topic > {int:start}
					AND t.id_topic <= {int:max_id}
				GROUP BY t.id_topic
				HAVING CASE WHEN COUNT(ma.id_msg) >= 1 THEN COUNT(ma.id_msg) - 1 ELSE 0 END != MAX(t.num_replies)',
				array(
					'is_approved' => 1,
					'start' => $_REQUEST['start'],
					'max_id' => $_REQUEST['start'] + $increment,
				)
			);
			while ($row = $smcFunc['db_fetch_assoc']($request))
				$smcFunc['db_query']('', '
					UPDATE {db_prefix}topics
					SET num_replies = {int:num_replies}
					WHERE id_topic = {int:id_topic}',
					array(
						'num_replies' => $row['real_num_replies'],
						'id_topic' => $row['id_topic'],
					)
				);
			$smcFunc['db_free_result']($request);

			// Recount unapproved messages
			$request = $smcFunc['db_query']('', '
				SELECT /*!40001 SQL_NO_CACHE */ t.id_topic, MAX(t.unapproved_posts) AS unapproved_posts,
					COUNT(mu.id_msg) AS real_unapproved_posts
				FROM {db_prefix}topics AS t
					LEFT JOIN {db_prefix}messages AS mu ON (mu.id_topic = t.id_topic AND mu.approved = {int:not_approved})
				WHERE t.id_topic > {int:start}
					AND t.id_topic <= {int:max_id}
				GROUP BY t.id_topic
				HAVING COUNT(mu.id_msg) != MAX(t.unapproved_posts)',
				array(
					'not_approved' => 0,
					'start' => $_REQUEST['start'],
					'max_id' => $_REQUEST['start'] + $increment,
				)
			);
			while ($row = $smcFunc['db_fetch_assoc']($request))
				$smcFunc['db_query']('', '
					UPDATE {db_prefix}topics
					SET unapproved_posts = {int:unapproved_posts}
					WHERE id_topic = {int:id_topic}',
					array(
						'unapproved_posts' => $row['real_unapproved_posts'],
						'id_topic' => $row['id_topic'],
					)
				);
			$smcFunc['db_free_result']($request);

			$_REQUEST['start'] += $increment;

			if (array_sum(explode(' ', microtime())) - array_sum(explode(' ', $time_start)) > 3)
			{
				createToken('admin-boardrecount');
				$context['continue_post_data'] = '<input type="hidden" name="' . $context['admin-boardrecount_token_var'] . '" value="' . $context['admin-boardrecount_token'] . '">';

				$context['continue_get_data'] = '?action=admin;area=maintain;sa=routine;activity=recount;step=0;start=' . $_REQUEST['start'] . ';' . $context['session_var'] . '=' . $context['session_id'];
				$context['continue_percent'] = round((100 * $_REQUEST['start'] / $max_topics) / $total_steps);

				return;
			}
		}

		$_REQUEST['start'] = 0;
	}

	// Update the post count of each board.
	if ($_REQUEST['step'] <= 1)
	{
		if (empty($_REQUEST['start']))
			$smcFunc['db_query']('', '
				UPDATE {db_prefix}boards
				SET num_posts = {int:num_posts}
				WHERE redirect = {string:redirect}',
				array(
					'num_posts' => 0,
					'redirect' => '',
				)
			);

		while ($_REQUEST['start'] < $max_topics)
		{
			$request = $smcFunc['db_query']('', '
				SELECT /*!40001 SQL_NO_CACHE */ m.id_board, COUNT(*) AS real_num_posts
				FROM {db_prefix}messages AS m
				WHERE m.id_topic > {int:id_topic_min}
					AND m.id_topic <= {int:id_topic_max}
					AND m.approved = {int:is_approved}
				GROUP BY m.id_board',
				array(
					'id_topic_min' => $_REQUEST['start'],
					'id_topic_max' => $_REQUEST['start'] + $increment,
					'is_approved' => 1,
				)
			);
			while ($row = $smcFunc['db_fetch_assoc']($request))
				$smcFunc['db_query']('', '
					UPDATE {db_prefix}boards
					SET num_posts = num_posts + {int:real_num_posts}
					WHERE id_board = {int:id_board}',
					array(
						'id_board' => $row['id_board'],
						'real_num_posts' => $row['real_num_posts'],
					)
				);
			$smcFunc['db_free_result']($request);

			$_REQUEST['start'] += $increment;

			if (array_sum(explode(' ', microtime())) - array_sum(explode(' ', $time_start)) > 3)
			{
				createToken('admin-boardrecount');
				$context['continue_post_data'] = '<input type="hidden" name="' . $context['admin-boardrecount_token_var'] . '" value="' . $context['admin-boardrecount_token'] . '">';

				$context['continue_get_data'] = '?action=admin;area=maintain;sa=routine;activity=recount;step=1;start=' . $_REQUEST['start'] . ';' . $context['session_var'] . '=' . $context['session_id'];
				$context['continue_percent'] = round((200 + 100 * $_REQUEST['start'] / $max_topics) / $total_steps);

				return;
			}
		}

		$_REQUEST['start'] = 0;
	}

	// Update the topic count of each board.
	if ($_REQUEST['step'] <= 2)
	{
		if (empty($_REQUEST['start']))
			$smcFunc['db_query']('', '
				UPDATE {db_prefix}boards
				SET num_topics = {int:num_topics}',
				array(
					'num_topics' => 0,
				)
			);

		while ($_REQUEST['start'] < $max_topics)
		{
			$request = $smcFunc['db_query']('', '
				SELECT /*!40001 SQL_NO_CACHE */ t.id_board, COUNT(*) AS real_num_topics
				FROM {db_prefix}topics AS t
				WHERE t.approved = {int:is_approved}
					AND t.id_topic > {int:id_topic_min}
					AND t.id_topic <= {int:id_topic_max}
				GROUP BY t.id_board',
				array(
					'is_approved' => 1,
					'id_topic_min' => $_REQUEST['start'],
					'id_topic_max' => $_REQUEST['start'] + $increment,
				)
			);
			while ($row = $smcFunc['db_fetch_assoc']($request))
				$smcFunc['db_query']('', '
					UPDATE {db_prefix}boards
					SET num_topics = num_topics + {int:real_num_topics}
					WHERE id_board = {int:id_board}',
					array(
						'id_board' => $row['id_board'],
						'real_num_topics' => $row['real_num_topics'],
					)
				);
			$smcFunc['db_free_result']($request);

			$_REQUEST['start'] += $increment;

			if (array_sum(explode(' ', microtime())) - array_sum(explode(' ', $time_start)) > 3)
			{
				createToken('admin-boardrecount');
				$context['continue_post_data'] = '<input type="hidden" name="' . $context['admin-boardrecount_token_var'] . '" value="' . $context['admin-boardrecount_token'] . '">';

				$context['continue_get_data'] = '?action=admin;area=maintain;sa=routine;activity=recount;step=2;start=' . $_REQUEST['start'] . ';' . $context['session_var'] . '=' . $context['session_id'];
				$context['continue_percent'] = round((300 + 100 * $_REQUEST['start'] / $max_topics) / $total_steps);

				return;
			}
		}

		$_REQUEST['start'] = 0;
	}

	// Update the unapproved post count of each board.
	if ($_REQUEST['step'] <= 3)
	{
		if (empty($_REQUEST['start']))
			$smcFunc['db_query']('', '
				UPDATE {db_prefix}boards
				SET unapproved_posts = {int:unapproved_posts}',
				array(
					'unapproved_posts' => 0,
				)
			);

		while ($_REQUEST['start'] < $max_topics)
		{
			$request = $smcFunc['db_query']('', '
				SELECT /*!40001 SQL_NO_CACHE */ m.id_board, COUNT(*) AS real_unapproved_posts
				FROM {db_prefix}messages AS m
				WHERE m.id_topic > {int:id_topic_min}
					AND m.id_topic <= {int:id_topic_max}
					AND m.approved = {int:is_approved}
				GROUP BY m.id_board',
				array(
					'id_topic_min' => $_REQUEST['start'],
					'id_topic_max' => $_REQUEST['start'] + $increment,
					'is_approved' => 0,
				)
			);
			while ($row = $smcFunc['db_fetch_assoc']($request))
				$smcFunc['db_query']('', '
					UPDATE {db_prefix}boards
					SET unapproved_posts = unapproved_posts + {int:unapproved_posts}
					WHERE id_board = {int:id_board}',
					array(
						'id_board' => $row['id_board'],
						'unapproved_posts' => $row['real_unapproved_posts'],
					)
				);
			$smcFunc['db_free_result']($request);

			$_REQUEST['start'] += $increment;

			if (array_sum(explode(' ', microtime())) - array_sum(explode(' ', $time_start)) > 3)
			{
				createToken('admin-boardrecount');
				$context['continue_post_data'] = '<input type="hidden" name="' . $context['admin-boardrecount_token_var'] . '" value="' . $context['admin-boardrecount_token'] . '">';

				$context['continue_get_data'] = '?action=admin;area=maintain;sa=routine;activity=recount;step=3;start=' . $_REQUEST['start'] . ';' . $context['session_var'] . '=' . $context['session_id'];
				$context['continue_percent'] = round((400 + 100 * $_REQUEST['start'] / $max_topics) / $total_steps);

				return;
			}
		}

		$_REQUEST['start'] = 0;
	}

	// Update the unapproved topic count of each board.
	if ($_REQUEST['step'] <= 4)
	{
		if (empty($_REQUEST['start']))
			$smcFunc['db_query']('', '
				UPDATE {db_prefix}boards
				SET unapproved_topics = {int:unapproved_topics}',
				array(
					'unapproved_topics' => 0,
				)
			);

		while ($_REQUEST['start'] < $max_topics)
		{
			$request = $smcFunc['db_query']('', '
				SELECT /*!40001 SQL_NO_CACHE */ t.id_board, COUNT(*) AS real_unapproved_topics
				FROM {db_prefix}topics AS t
				WHERE t.approved = {int:is_approved}
					AND t.id_topic > {int:id_topic_min}
					AND t.id_topic <= {int:id_topic_max}
				GROUP BY t.id_board',
				array(
					'is_approved' => 0,
					'id_topic_min' => $_REQUEST['start'],
					'id_topic_max' => $_REQUEST['start'] + $increment,
				)
			);
			while ($row = $smcFunc['db_fetch_assoc']($request))
				$smcFunc['db_query']('', '
					UPDATE {db_prefix}boards
					SET unapproved_topics = unapproved_topics + {int:real_unapproved_topics}
					WHERE id_board = {int:id_board}',
					array(
						'id_board' => $row['id_board'],
						'real_unapproved_topics' => $row['real_unapproved_topics'],
					)
				);
			$smcFunc['db_free_result']($request);

			$_REQUEST['start'] += $increment;

			if (array_sum(explode(' ', microtime())) - array_sum(explode(' ', $time_start)) > 3)
			{
				createToken('admin-boardrecount');
				$context['continue_post_data'] = '<input type="hidden" name="' . $context['admin-boardrecount_token_var'] . '" value="' . $context['admin-boardrecount_token'] . '">';

				$context['continue_get_data'] = '?action=admin;area=maintain;sa=routine;activity=recount;step=4;start=' . $_REQUEST['start'] . ';' . $context['session_var'] . '=' . $context['session_id'];
				$context['continue_percent'] = round((500 + 100 * $_REQUEST['start'] / $max_topics) / $total_steps);

				return;
			}
		}

		$_REQUEST['start'] = 0;
	}

	// Get all members with wrong number of personal messages.
	if ($_REQUEST['step'] <= 5)
	{
		$request = $smcFunc['db_query']('', '
			SELECT /*!40001 SQL_NO_CACHE */ mem.id_member, COUNT(pmr.id_pm) AS real_num,
				MAX(mem.instant_messages) AS instant_messages
			FROM {db_prefix}members AS mem
				LEFT JOIN {db_prefix}pm_recipients AS pmr ON (mem.id_member = pmr.id_member AND pmr.deleted = {int:is_not_deleted})
			GROUP BY mem.id_member
			HAVING COUNT(pmr.id_pm) != MAX(mem.instant_messages)',
			array(
				'is_not_deleted' => 0,
			)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
			updateMemberData($row['id_member'], array('instant_messages' => $row['real_num']));
		$smcFunc['db_free_result']($request);

		$request = $smcFunc['db_query']('', '
			SELECT /*!40001 SQL_NO_CACHE */ mem.id_member, COUNT(pmr.id_pm) AS real_num,
				MAX(mem.unread_messages) AS unread_messages
			FROM {db_prefix}members AS mem
				LEFT JOIN {db_prefix}pm_recipients AS pmr ON (mem.id_member = pmr.id_member AND pmr.deleted = {int:is_not_deleted} AND pmr.is_read = {int:is_not_read})
			GROUP BY mem.id_member
			HAVING COUNT(pmr.id_pm) != MAX(mem.unread_messages)',
			array(
				'is_not_deleted' => 0,
				'is_not_read' => 0,
			)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
			updateMemberData($row['id_member'], array('unread_messages' => $row['real_num']));
		$smcFunc['db_free_result']($request);

		if (array_sum(explode(' ', microtime())) - array_sum(explode(' ', $time_start)) > 3)
		{
			createToken('admin-boardrecount');
			$context['continue_post_data'] = '<input type="hidden" name="' . $context['admin-boardrecount_token_var'] . '" value="' . $context['admin-boardrecount_token'] . '">';

			$context['continue_get_data'] = '?action=admin;area=maintain;sa=routine;activity=recount;step=6;start=0;' . $context['session_var'] . '=' . $context['session_id'];
			$context['continue_percent'] = round(700 / $total_steps);

			return;
		}
	}

	// Any messages pointing to the wrong board?
	if ($_REQUEST['step'] <= 6)
	{
		while ($_REQUEST['start'] < $modSettings['maxMsgID'])
		{
			$request = $smcFunc['db_query']('', '
				SELECT /*!40001 SQL_NO_CACHE */ t.id_board, m.id_msg
				FROM {db_prefix}messages AS m
					INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic AND t.id_board != m.id_board)
				WHERE m.id_msg > {int:id_msg_min}
					AND m.id_msg <= {int:id_msg_max}',
				array(
					'id_msg_min' => $_REQUEST['start'],
					'id_msg_max' => $_REQUEST['start'] + $increment,
				)
			);
			$boards = array();
			while ($row = $smcFunc['db_fetch_assoc']($request))
				$boards[$row['id_board']][] = $row['id_msg'];
			$smcFunc['db_free_result']($request);

			foreach ($boards as $board_id => $messages)
				$smcFunc['db_query']('', '
					UPDATE {db_prefix}messages
					SET id_board = {int:id_board}
					WHERE id_msg IN ({array_int:id_msg_array})',
					array(
						'id_msg_array' => $messages,
						'id_board' => $board_id,
					)
				);

			$_REQUEST['start'] += $increment;

			if (array_sum(explode(' ', microtime())) - array_sum(explode(' ', $time_start)) > 3)
			{
				createToken('admin-boardrecount');
				$context['continue_post_data'] = '<input type="hidden" name="' . $context['admin-boardrecount_token_var'] . '" value="' . $context['admin-boardrecount_token'] . '">';

				$context['continue_get_data'] = '?action=admin;area=maintain;sa=routine;activity=recount;step=6;start=' . $_REQUEST['start'] . ';' . $context['session_var'] . '=' . $context['session_id'];
				$context['continue_percent'] = round((700 + 100 * $_REQUEST['start'] / $modSettings['maxMsgID']) / $total_steps);

				return;
			}
		}

		$_REQUEST['start'] = 0;
	}

	// Update the latest message of each board.
	$request = $smcFunc['db_query']('', '
		SELECT m.id_board, MAX(m.id_msg) AS local_last_msg
		FROM {db_prefix}messages AS m
		WHERE m.approved = {int:is_approved}
		GROUP BY m.id_board',
		array(
			'is_approved' => 1,
		)
	);
	$realBoardCounts = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$realBoardCounts[$row['id_board']] = $row['local_last_msg'];
	$smcFunc['db_free_result']($request);

	$request = $smcFunc['db_query']('', '
		SELECT /*!40001 SQL_NO_CACHE */ id_board, id_parent, id_last_msg, child_level, id_msg_updated
		FROM {db_prefix}boards',
		array(
		)
	);
	$resort_me = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$row['local_last_msg'] = isset($realBoardCounts[$row['id_board']]) ? $realBoardCounts[$row['id_board']] : 0;
		$resort_me[$row['child_level']][] = $row;
	}
	$smcFunc['db_free_result']($request);

	krsort($resort_me);

	$lastModifiedMsg = array();
	foreach ($resort_me as $rows)
		foreach ($rows as $row)
		{
			// The latest message is the latest of the current board and its children.
			if (isset($lastModifiedMsg[$row['id_board']]))
				$curLastModifiedMsg = max($row['local_last_msg'], $lastModifiedMsg[$row['id_board']]);
			else
				$curLastModifiedMsg = $row['local_last_msg'];

			// If what is and what should be the latest message differ, an update is necessary.
			if ($row['local_last_msg'] != $row['id_last_msg'] || $curLastModifiedMsg != $row['id_msg_updated'])
				$smcFunc['db_query']('', '
					UPDATE {db_prefix}boards
					SET id_last_msg = {int:id_last_msg}, id_msg_updated = {int:id_msg_updated}
					WHERE id_board = {int:id_board}',
					array(
						'id_last_msg' => $row['local_last_msg'],
						'id_msg_updated' => $curLastModifiedMsg,
						'id_board' => $row['id_board'],
					)
				);

			// Parent boards inherit the latest modified message of their children.
			if (isset($lastModifiedMsg[$row['id_parent']]))
				$lastModifiedMsg[$row['id_parent']] = max($row['local_last_msg'], $lastModifiedMsg[$row['id_parent']]);
			else
				$lastModifiedMsg[$row['id_parent']] = $row['local_last_msg'];
		}

	// Update all the basic statistics.
	updateStats('member');
	updateStats('message');
	updateStats('topic');

	// Finally, update the latest event times.
	require_once($sourcedir . '/ScheduledTasks.php');
	CalculateNextTrigger();

	redirectexit('action=admin;area=maintain;sa=routine;done=recount');
}

/**
 * Perform a detailed version check.  A very good thing ;).
 * The function parses the comment headers in all files for their version information,
 * and outputs that for some javascript to check with simplemachines.org.
 * It does not connect directly with simplemachines.org, but rather expects the client to.
 *
 * It requires the admin_forum permission.
 * Uses the view_versions admin area.
 * Accessed through ?action=admin;area=maintain;sa=routine;activity=version.
 * @uses Admin template, view_versions sub-template.
 */
function VersionDetail()
{
	global $forum_version, $txt, $sourcedir, $context;

	isAllowedTo('admin_forum');

	// Call the function that'll get all the version info we need.
	require_once($sourcedir . '/Subs-Admin.php');
	$versionOptions = array(
		'include_ssi' => true,
		'include_subscriptions' => true,
		'include_tasks' => true,
		'sort_results' => true,
	);
	$version_info = getFileVersions($versionOptions);

	// Add the new info to the template context.
	$context += array(
		'file_versions' => $version_info['file_versions'],
		'default_template_versions' => $version_info['default_template_versions'],
		'template_versions' => $version_info['template_versions'],
		'default_language_versions' => $version_info['default_language_versions'],
		'default_known_languages' => array_keys($version_info['default_language_versions']),
		'tasks_versions' => $version_info['tasks_versions'],
	);

	// Make it easier to manage for the template.
	$context['forum_version'] = $forum_version;

	$context['sub_template'] = 'view_versions';
	$context['page_title'] = $txt['admin_version_check'];
}

/**
 * Re-attribute posts.
 */
function MaintainReattributePosts()
{
	global $sourcedir, $context, $txt;

	checkSession();

	// Find the member.
	require_once($sourcedir . '/Subs-Auth.php');
	$members = findMembers($_POST['to']);

	if (empty($members))
		fatal_lang_error('reattribute_cannot_find_member');

	$memID = array_shift($members);
	$memID = $memID['id'];

	$email = $_POST['type'] == 'email' ? $_POST['from_email'] : '';
	$membername = $_POST['type'] == 'name' ? $_POST['from_name'] : '';

	// Now call the reattribute function.
	require_once($sourcedir . '/Subs-Members.php');
	reattributePosts($memID, $email, $membername, !empty($_POST['posts']));

	$context['maintenance_finished'] = $txt['maintain_reattribute_posts'];
}

/**
 * Removing old members. Done and out!
 * @todo refactor
 */
function MaintainPurgeInactiveMembers()
{
	global $sourcedir, $context, $smcFunc, $txt;

	$_POST['maxdays'] = empty($_POST['maxdays']) ? 0 : (int) $_POST['maxdays'];
	if (!empty($_POST['groups']) && $_POST['maxdays'] > 0)
	{
		checkSession();
		validateToken('admin-maint');

		$groups = array();
		foreach ($_POST['groups'] as $id => $dummy)
			$groups[] = (int) $id;
		$time_limit = (time() - ($_POST['maxdays'] * 24 * 3600));
		$where_vars = array(
			'time_limit' => $time_limit,
		);
		if ($_POST['del_type'] == 'activated')
		{
			$where = 'mem.date_registered < {int:time_limit} AND mem.is_activated = {int:is_activated}';
			$where_vars['is_activated'] = 0;
		}
		else
			$where = 'mem.last_login < {int:time_limit} AND (mem.last_login != 0 OR mem.date_registered < {int:time_limit})';

		// Need to get *all* groups then work out which (if any) we avoid.
		$request = $smcFunc['db_query']('', '
			SELECT id_group, group_name, min_posts
			FROM {db_prefix}membergroups',
			array(
			)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			// Avoid this one?
			if (!in_array($row['id_group'], $groups))
			{
				// Post group?
				if ($row['min_posts'] != -1)
				{
					$where .= ' AND mem.id_post_group != {int:id_post_group_' . $row['id_group'] . '}';
					$where_vars['id_post_group_' . $row['id_group']] = $row['id_group'];
				}
				else
				{
					$where .= ' AND mem.id_group != {int:id_group_' . $row['id_group'] . '} AND FIND_IN_SET({int:id_group_' . $row['id_group'] . '}, mem.additional_groups) = 0';
					$where_vars['id_group_' . $row['id_group']] = $row['id_group'];
				}
			}
		}
		$smcFunc['db_free_result']($request);

		// If we have ungrouped unselected we need to avoid those guys.
		if (!in_array(0, $groups))
		{
			$where .= ' AND (mem.id_group != 0 OR mem.additional_groups != {string:blank_add_groups})';
			$where_vars['blank_add_groups'] = '';
		}

		// Select all the members we're about to murder/remove...
		$request = $smcFunc['db_query']('', '
			SELECT mem.id_member, IFNULL(m.id_member, 0) AS is_mod
			FROM {db_prefix}members AS mem
				LEFT JOIN {db_prefix}moderators AS m ON (m.id_member = mem.id_member)
			WHERE ' . $where,
			$where_vars
		);
		$members = array();
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			if (!$row['is_mod'] || !in_array(3, $groups))
				$members[] = $row['id_member'];
		}
		$smcFunc['db_free_result']($request);

		require_once($sourcedir . '/Subs-Members.php');
		deleteMembers($members);
	}

	$context['maintenance_finished'] = $txt['maintain_members'];
	createToken('admin-maint');
}

/**
 * Removing old posts doesn't take much as we really pass through.
 */
function MaintainRemoveOldPosts()
{
	global $sourcedir;

	validateToken('admin-maint');

	// Actually do what we're told!
	require_once($sourcedir . '/RemoveTopic.php');
	RemoveOldTopics2();
}

/**
 * Removing old drafts
 */
function MaintainRemoveOldDrafts()
{
	global $sourcedir, $smcFunc;

	validateToken('admin-maint');

	$drafts = array();

	// Find all of the old drafts
	$request = $smcFunc['db_query']('', '
		SELECT id_draft
		FROM {db_prefix}user_drafts
		WHERE poster_time <= {int:poster_time_old}',
		array(
			'poster_time_old' => time() - (86400 * $_POST['draftdays']),
		)
	);

	while ($row = $smcFunc['db_fetch_row']($request))
		$drafts[] = (int) $row[0];
	$smcFunc['db_free_result']($request);

	// If we have old drafts, remove them
	if (count($drafts) > 0)
	{
		require_once($sourcedir . '/Drafts.php');
		DeleteDraft($drafts, false);
	}
}

/**
 * Moves topics from one board to another.
 *
 * @uses not_done template to pause the process.
 */
function MaintainMassMoveTopics()
{
	global $smcFunc, $sourcedir, $context, $txt;

	// Only admins.
	isAllowedTo('admin_forum');

	checkSession('request');
	validateToken('admin-maint');

	// Set up to the context.
	$context['page_title'] = $txt['not_done_title'];
	$context['continue_countdown'] = 3;
	$context['continue_post_data'] = '';
	$context['continue_get_data'] = '';
	$context['sub_template'] = 'not_done';
	$context['start'] = empty($_REQUEST['start']) ? 0 : (int) $_REQUEST['start'];
	$context['start_time'] = time();

	// First time we do this?
	$id_board_from = isset($_REQUEST['id_board_from']) ? (int) $_REQUEST['id_board_from'] : 0;
	$id_board_to = isset($_REQUEST['id_board_to']) ? (int) $_REQUEST['id_board_to'] : 0;
	$max_days = isset($_REQUEST['maxdays']) ? (int) $_REQUEST['maxdays'] : 0;
	$locked = isset($_POST['move_type_locked']) || isset($_GET['locked']);
	$sticky = isset($_POST['move_type_sticky']) || isset($_GET['sticky']);

	// No boards then this is your stop.
	if (empty($id_board_from) || empty($id_board_to))
		return;

	// The big WHERE clause
	$conditions = 'WHERE t.id_board = {int:id_board_from}
		AND m.icon != {string:moved}';

	// DB parameters
	$params = array(
		'id_board_from' => $id_board_from,
		'moved' => 'moved',
	);

	// Only moving topics not posted in for x days?
	if (!empty($max_days))
	{
		$conditions .= '
			AND m.poster_time < {int:poster_time}';
		$params['poster_time'] = time() - 3600 * 24 * $max_days;
	}

	// Moving locked topics?
	if ($locked)
	{
		$conditions .= '
			AND t.locked = {int:locked}';
		$params['locked'] = 1;
	}

	// What about sticky topics?
	if ($sticky)
	{
		$conditions .= '
			AND t.is_sticky = {int:sticky}';
		$params['sticky'] = 1;
	}

	// How many topics are we converting?
	if (!isset($_REQUEST['totaltopics']))
	{
		$request = $smcFunc['db_query']('', '
			SELECT COUNT(*)
			FROM {db_prefix}topics AS t
				INNER JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_last_msg)' .
			$conditions,
			$params
		);
		list ($total_topics) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);
	}
	else
		$total_topics = (int) $_REQUEST['totaltopics'];

	// Seems like we need this here.
	$context['continue_get_data'] = '?action=admin;area=maintain;sa=topics;activity=massmove;id_board_from=' . $id_board_from . ';id_board_to=' . $id_board_to . ';totaltopics=' . $total_topics . ';max_days=' . $max_days;

	if ($locked)
		$context['continue_get_data'] .= ';locked';

	if ($sticky)
		$context['continue_get_data'] .= ';sticky';

	$context['continue_get_data'] .= ';start=' . $context['start'] . ';' . $context['session_var'] . '=' . $context['session_id'];

	// We have topics to move so start the process.
	if (!empty($total_topics))
	{
		while ($context['start'] <= $total_topics)
		{
			// Lets get the topics.
			$request = $smcFunc['db_query']('', '
				SELECT t.id_topic
				FROM {db_prefix}topics AS t
					INNER JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_last_msg)
				' . $conditions . '
				LIMIT 10',
				$params
			);

			// Get the ids.
			$topics = array();
			while ($row = $smcFunc['db_fetch_assoc']($request))
				$topics[] = $row['id_topic'];

			// Just return if we don't have any topics left to move.
			if (empty($topics))
			{
				cache_put_data('board-' . $id_board_from, null, 120);
				cache_put_data('board-' . $id_board_to, null, 120);
				redirectexit('action=admin;area=maintain;sa=topics;done=massmove');
			}

			// Lets move them.
			require_once($sourcedir . '/MoveTopic.php');
			moveTopics($topics, $id_board_to);

			// We've done at least ten more topics.
			$context['start'] += 10;

			// Lets wait a while.
			if (time() - $context['start_time'] > 3)
			{
				// What's the percent?
				$context['continue_percent'] = round(100 * ($context['start'] / $total_topics), 1);
				$context['continue_get_data'] = '?action=admin;area=maintain;sa=topics;activity=massmove;id_board_from=' . $id_board_from . ';id_board_to=' . $id_board_to . ';totaltopics=' . $total_topics . ';start=' . $context['start'] . ';' . $context['session_var'] . '=' . $context['session_id'];

				// Let the template system do it's thang.
				return;
			}
		}
	}

	// Don't confuse admins by having an out of date cache.
	cache_put_data('board-' . $id_board_from, null, 120);
	cache_put_data('board-' . $id_board_to, null, 120);

	redirectexit('action=admin;area=maintain;sa=topics;done=massmove');
}

/**
 * Recalculate all members post counts
 * it requires the admin_forum permission.
 *
 * - recounts all posts for members found in the message table
 * - updates the members post count record in the members table
 * - honors the boards post count flag
 * - does not count posts in the recycle bin
 * - zeros post counts for all members with no posts in the message table
 * - runs as a delayed loop to avoid server overload
 * - uses the not_done template in Admin.template
 *
 * The function redirects back to action=admin;area=maintain;sa=members when complete.
 * It is accessed via ?action=admin;area=maintain;sa=members;activity=recountposts
 */
function MaintainRecountPosts()
{
	global $txt, $context, $modSettings, $smcFunc;

	// You have to be allowed in here
	isAllowedTo('admin_forum');
	checkSession('request');

	// Set up to the context.
	$context['page_title'] = $txt['not_done_title'];
	$context['continue_countdown'] = 3;
	$context['continue_get_data'] = '';
	$context['sub_template'] = 'not_done';

	// init
	$increment = 200;
	$_REQUEST['start'] = !isset($_REQUEST['start']) ? 0 : (int) $_REQUEST['start'];

	// Ask for some extra time, on big boards this may take a bit
	@set_time_limit(600);

	// Only run this query if we don't have the total number of members that have posted
	if (!isset($_SESSION['total_members']))
	{
		validateToken('admin-maint');

		$request = $smcFunc['db_query']('', '
			SELECT COUNT(DISTINCT m.id_member)
			FROM ({db_prefix}messages AS m, {db_prefix}boards AS b)
			WHERE m.id_member != 0
				AND b.count_posts = 0
				AND m.id_board = b.id_board',
			array(
			)
		);

		// save it so we don't do this again for this task
		list ($_SESSION['total_members']) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);
	}
	else
		validateToken('admin-recountposts');

	// Lets get a group of members and determine their post count (from the boards that have post count enabled of course).
	$request = $smcFunc['db_query']('', '
		SELECT /*!40001 SQL_NO_CACHE */ m.id_member, COUNT(m.id_member) AS posts
		FROM ({db_prefix}messages AS m, {db_prefix}boards AS b)
		WHERE m.id_member != {int:zero}
			AND b.count_posts = {int:zero}
			AND m.id_board = b.id_board ' . (!empty($modSettings['recycle_enable']) ? '
			AND b.id_board != {int:recycle}' : '') . '
		GROUP BY m.id_member
		LIMIT {int:start}, {int:number}',
		array(
			'start' => $_REQUEST['start'],
			'number' => $increment,
			'recycle' => $modSettings['recycle_board'],
			'zero' => 0,
		)
	);
	$total_rows = $smcFunc['db_num_rows']($request);

	// Update the post count for this group
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}members
			SET posts = {int:posts}
			WHERE id_member = {int:row}',
			array(
				'row' => $row['id_member'],
				'posts' => $row['posts'],
			)
		);
	}
	$smcFunc['db_free_result']($request);

	// Continue?
	if ($total_rows == $increment)
	{
		$_REQUEST['start'] += $increment;
		$context['continue_get_data'] = '?action=admin;area=maintain;sa=members;activity=recountposts;start=' . $_REQUEST['start'] . ';' . $context['session_var'] . '=' . $context['session_id'];
		$context['continue_percent'] = round(100 * $_REQUEST['start'] / $_SESSION['total_members']);

		createToken('admin-recountposts');
		$context['continue_post_data'] = '<input type="hidden" name="' . $context['admin-recountposts_token_var'] . '" value="' . $context['admin-recountposts_token'] . '">';

		if (function_exists('apache_reset_timeout'))
			apache_reset_timeout();
		return;
	}

	// final steps ... made more difficult since we don't yet support sub-selects on joins
	// place all members who have posts in the message table in a temp table
	$createTemporary = $smcFunc['db_query']('', '
		CREATE TEMPORARY TABLE {db_prefix}tmp_maint_recountposts (
			id_member mediumint(8) unsigned NOT NULL default {string:string_zero},
			PRIMARY KEY (id_member)
		)
		SELECT m.id_member
		FROM ({db_prefix}messages AS m,{db_prefix}boards AS b)
		WHERE m.id_member != {int:zero}
			AND b.count_posts = {int:zero}
			AND m.id_board = b.id_board ' . (!empty($modSettings['recycle_enable']) ? '
			AND b.id_board != {int:recycle}' : '') . '
		GROUP BY m.id_member',
		array(
			'zero' => 0,
			'string_zero' => '0',
			'db_error_skip' => true,
			'recycle' => !empty($modSettings['recycle_board']) ? $modSettings['recycle_board'] : 0,
		)
	) !== false;

	if ($createTemporary)
	{
		// outer join the members table on the temporary table finding the members that have a post count but no posts in the message table
		$request = $smcFunc['db_query']('', '
			SELECT mem.id_member, mem.posts
			FROM {db_prefix}members AS mem
			LEFT OUTER JOIN {db_prefix}tmp_maint_recountposts AS res
			ON res.id_member = mem.id_member
			WHERE res.id_member IS null
				AND mem.posts != {int:zero}',
			array(
				'zero' => 0,
			)
		);

		// set the post count to zero for any delinquents we may have found
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			$smcFunc['db_query']('', '
				UPDATE {db_prefix}members
				SET posts = {int:zero}
				WHERE id_member = {int:row}',
				array(
					'row' => $row['id_member'],
					'zero' => 0,
				)
			);
		}
		$smcFunc['db_free_result']($request);
	}

	// all done
	unset($_SESSION['total_members']);
	$context['maintenance_finished'] = $txt['maintain_recountposts'];
	redirectexit('action=admin;area=maintain;sa=members;done=recountposts');
}

/**
 * Generates a list of integration hooks for display
 * Accessed through ?action=admin;area=maintain;sa=hooks;
 * Allows for removal or disabling of selected hooks
 */
function list_integration_hooks()
{
	global $sourcedir, $scripturl, $context, $txt;

	$context['filter_url'] = '';
	$context['current_filter'] = '';
	$currentHooks = get_integration_hooks();
	if (isset($_GET['filter']) && in_array($_GET['filter'], array_keys($currentHooks)))
	{
		$context['filter_url'] = ';filter=' . $_GET['filter'];
		$context['current_filter'] = $_GET['filter'];
	}

	if (!empty($_REQUEST['do']) && isset($_REQUEST['hook']) && isset($_REQUEST['function']))
	{
		checkSession('request');
		validateToken('admin-hook', 'request');

		if ($_REQUEST['do'] == 'remove')
			remove_integration_function($_REQUEST['hook'], urldecode($_REQUEST['function']));
		else
		{
			if ($_REQUEST['do'] == 'disable')
			{
				// It's a hack I know...but I'm way too lazy!!!
				$function_remove = $_REQUEST['function'];
				$function_add = $_REQUEST['function'] . ']';
			}
			else
			{
				$function_remove = $_REQUEST['function'] . ']';
				$function_add = $_REQUEST['function'];
			}
			$file = !empty($_REQUEST['includedfile']) ? urldecode($_REQUEST['includedfile']) : '';

			remove_integration_function($_REQUEST['hook'], $function_remove, $file);
			add_integration_function($_REQUEST['hook'], $function_add, $file);

			redirectexit('action=admin;area=maintain;sa=hooks' . $context['filter_url']);
		}
	}

	createToken('admin-hook', 'request');

	$list_options = array(
		'id' => 'list_integration_hooks',
		'title' => $txt['hooks_title_list'],
		'items_per_page' => 20,
		'base_href' => $scripturl . '?action=admin;area=maintain;sa=hooks' . $context['filter_url'] . ';' . $context['session_var'] . '=' . $context['session_id'],
		'default_sort_col' => 'hook_name',
		'get_items' => array(
			'function' => 'get_integration_hooks_data',
		),
		'get_count' => array(
			'function' => 'get_integration_hooks_count',
		),
		'no_items_label' => $txt['hooks_no_hooks'],
		'columns' => array(
			'hook_name' => array(
				'header' => array(
					'value' => $txt['hooks_field_hook_name'],
				),
				'data' => array(
					'db' => 'hook_name',
				),
				'sort' =>  array(
					'default' => 'hook_name',
					'reverse' => 'hook_name DESC',
				),
			),
			'function_name' => array(
				'header' => array(
					'value' => $txt['hooks_field_function_name'],
				),
				'data' => array(
					'function' => function ($data) use ($txt)
					{
						// Show a nice icon to indicate this is instance.
						$instance = (!empty($data['instance']) ? '<span class="generic_icons news" title="'. $txt['hooks_field_function_method'] .'"></span> ' : '');

						if (!empty($data['included_file']))
							return $instance . $txt['hooks_field_function'] . ': ' . $data['real_function'] . '<br>' . $txt['hooks_field_included_file'] . ': ' . $data['included_file'];
						else
							return $instance . $data['real_function'];
					},
				),
				'sort' =>  array(
					'default' => 'function_name',
					'reverse' => 'function_name DESC',
				),
			),
			'file_name' => array(
				'header' => array(
					'value' => $txt['hooks_field_file_name'],
				),
				'data' => array(
					'db' => 'file_name',
				),
				'sort' =>  array(
					'default' => 'file_name',
					'reverse' => 'file_name DESC',
				),
			),
			'status' => array(
				'header' => array(
					'value' => $txt['hooks_field_hook_exists'],
					'style' => 'width:3%;',
				),
				'data' => array(
					'function' => function ($data) use ($txt, $scripturl, $context)
					{
						$change_status = array('before' => '', 'after' => '');
						if ($data['can_be_disabled'] && $data['status'] != 'deny')
						{
							$change_status['before'] = '<a href="' . $scripturl . '?action=admin;area=maintain;sa=hooks;do=' . ($data['enabled'] ? 'disable' : 'enable') . ';hook=' . $data['hook_name'] . ';function=' . $data['real_function'] . (!empty($data['included_file']) ? ';includedfile=' . urlencode($data['included_file']) : '') . $context['filter_url'] . ';' . $context['admin-hook_token_var'] . '=' . $context['admin-hook_token'] . ';' . $context['session_var'] . '=' . $context['session_id'] . '" data-confirm="' . $txt['quickmod_confirm'] . '" class="you_sure">';
							$change_status['after'] = '</a>';
						}
						return $change_status['before'] . '<span class="generic_icons post_moderation_' . $data['status'] . '" title="' . $data['img_text'] . '"></span>';
					},
					'class' => 'centertext',
				),
				'sort' =>  array(
					'default' => 'status',
					'reverse' => 'status DESC',
				),
			),
		),
		'additional_rows' => array(
			array(
				'position' => 'after_title',
				'value' => $txt['hooks_disable_instructions'] . '<br>
					' . $txt['hooks_disable_legend'] . ':
				<ul style="list-style: none;">
					<li><span class="generic_icons post_moderation_allow"></span> ' . $txt['hooks_disable_legend_exists'] . '</li>
					<li><span class="generic_icons post_moderation_moderate"></span> ' . $txt['hooks_disable_legend_disabled'] . '</li>
					<li><span class="generic_icons post_moderation_deny"></span> ' . $txt['hooks_disable_legend_missing'] . '</li>
				</ul>'
			),
		),
	);

	$list_options['columns']['remove'] = array(
		'header' => array(
			'value' => $txt['hooks_button_remove'],
			'style' => 'width:3%',
		),
		'data' => array(
			'function' => function ($data) use ($txt, $scripturl, $context)
			{
				if (!$data['hook_exists'])
					return '
					<a href="' . $scripturl . '?action=admin;area=maintain;sa=hooks;do=remove;hook=' . $data['hook_name'] . ';function=' . urlencode($data['function_name']) . $context['filter_url'] . ';' . $context['admin-hook_token_var'] . '=' . $context['admin-hook_token'] . ';' . $context['session_var'] . '=' . $context['session_id'] . '" data-confirm="' . $txt['quickmod_confirm'] . '" class="you_sure">
						<span class="generic_icons delete" title="' . $txt['hooks_button_remove'] . '"></span>
					</a>';
			},
			'class' => 'centertext',
		),
	);
	$list_options['form'] = array(
		'href' => $scripturl . '?action=admin;area=maintain;sa=hooks' . $context['filter_url'] . ';' . $context['session_var'] . '=' . $context['session_id'],
		'name' => 'list_integration_hooks',
	);


	require_once($sourcedir . '/Subs-List.php');
	createList($list_options);

	$context['page_title'] = $txt['hooks_title_list'];
	$context['sub_template'] = 'show_list';
	$context['default_list'] = 'list_integration_hooks';
}

/**
 * Gets all of the files in a directory and its children directories
 *
 * @param type $dir_path
 * @return array
 */
function get_files_recursive($dir_path)
{
	$files = array();

	if ($dh = opendir($dir_path))
	{
		while (($file = readdir($dh)) !== false)
		{
			if ($file != '.' && $file != '..')
			{
				if (is_dir($dir_path . '/' . $file))
					$files = array_merge($files, get_files_recursive($dir_path . '/' . $file));
				else
					$files[] = array('dir' => $dir_path, 'name' => $file);
			}
		}
	}
	closedir($dh);

	return $files;
}

/**
 * Callback function for the integration hooks list (list_integration_hooks)
 * Gets all of the hooks in the system and their status
 * Would be better documented if Ema was not lazy
 *
 * @param type $start
 * @param type $per_page
 * @param type $sort
 * @return array
 */
function get_integration_hooks_data($start, $per_page, $sort)
{
	global $boarddir, $sourcedir, $settings, $txt, $context, $scripturl;

	$hooks = $temp_hooks = get_integration_hooks();
	$hooks_data = $temp_data = $hook_status = array();

	$files = get_files_recursive($sourcedir);
	if (!empty($files))
	{
		foreach ($files as $file)
		{
			if (is_file($file['dir'] . '/' . $file['name']) && substr($file['name'], -4) === '.php')
			{
				$fp = fopen($file['dir'] . '/' . $file['name'], 'rb');
				$fc = fread($fp, filesize($file['dir'] . '/' . $file['name']));
				fclose($fp);

				foreach ($temp_hooks as $hook => $functions)
				{
					foreach ($functions as $function_o)
					{
						$object = false;

						if (strpos($function_o, '#') !== false)
						{
							$function_o = str_replace('#', '', $function_o);
							$object = true;
						}

						$hook_name = str_replace(']', '', $function_o);
						if (strpos($hook_name, '::') !== false)
						{
							$function = explode('::', $hook_name);
							$function = $function[1];
						}
						else
							$function = $hook_name;

						$function = explode(':', $function);
						$function = $function[0];

						if (substr($hook, -8) === '_include')
						{
							$hook_status[$hook][$function]['exists'] = file_exists(strtr(trim($function), array('$boarddir' => $boarddir, '$sourcedir' => $sourcedir, '$themedir' => $settings['theme_dir'])));
							// I need to know if there is at least one function called in this file.
							$temp_data['include'][basename($function)] = array('hook' => $hook, 'function' => $function);
							unset($temp_hooks[$hook][$function_o]);
						}
						elseif (strpos(str_replace(' (', '(', $fc), 'function ' . trim($function) . '(') !== false)
						{
							$hook_status[$hook][$function]['exists'] = true;
							$hook_status[$hook][$function]['in_file'] = $file['name'];
							$hook_status[$hook][$function]['instance'] = $object;

							// I want to remember all the functions called within this file (to check later if they are enabled or disabled and decide if the integrare_*_include of that file can be disabled too)
							$temp_data['function'][$file['name']][] = $function_o;
							unset($temp_hooks[$hook][$function_o]);
						}
					}
				}
			}
		}
	}

	$sort_types = array(
		'hook_name' => array('hook', SORT_ASC),
		'hook_name DESC' => array('hook', SORT_DESC),
		'function_name' => array('function', SORT_ASC),
		'function_name DESC' => array('function', SORT_DESC),
		'file_name' => array('file_name', SORT_ASC),
		'file_name DESC' => array('file_name', SORT_DESC),
		'status' => array('status', SORT_ASC),
		'status DESC' => array('status', SORT_DESC),
	);

	$sort_options = $sort_types[$sort];
	$sort = array();
	$hooks_filters = array();

	foreach ($hooks as $hook => $functions)
	{
		$hooks_filters[] = '<option' . ($context['current_filter'] == $hook ? ' selected ' : '') . ' value="' . $hook . '">' . $hook . '</option>';
		foreach ($functions as $function)
		{
			$enabled = strstr($function, ']') === false;
			$function = str_replace(']', '', $function);

			// This is a not an include and the function is included in a certain file (if not it doesn't exists so don't care)
			if (substr($hook, -8) !== '_include' && isset($hook_status[$hook][$function]['in_file']))
			{
				$current_hook = isset($temp_data['include'][$hook_status[$hook][$function]['in_file']]) ? $temp_data['include'][$hook_status[$hook][$function]['in_file']] : '';
				$enabled = false;

				// Checking all the functions within this particular file
				// if any of them is enable then the file *must* be included and the integrate_*_include hook cannot be disabled
				foreach ($temp_data['function'][$hook_status[$hook][$function]['in_file']] as $func)
					$enabled = $enabled || strstr($func, ']') !== false;

				if (!$enabled &&  !empty($current_hook))
					$hook_status[$current_hook['hook']][$current_hook['function']]['enabled'] = true;
			}
		}
	}

	if (!empty($hooks_filters))
		$context['insert_after_template'] .= '
		<script><!-- // --><![CDATA[
			var hook_name_header = document.getElementById(\'header_list_integration_hooks_hook_name\');
			hook_name_header.innerHTML += ' . JavaScriptEscape('<select style="margin-left:15px;" onchange="window.location=(\'' . $scripturl . '?action=admin;area=maintain;sa=hooks\' + (this.value ? \';filter=\' + this.value : \'\'));"><option value="">' . $txt['hooks_reset_filter'] . '</option>' . implode('', $hooks_filters) . '</select>'). ';
		// ]]></script>';

	$temp_data = array();
	$id = 0;

	foreach ($hooks as $hook => $functions)
	{
		if (empty($context['filter']) || (!empty($context['filter']) && $context['filter'] == $hook))
		{
			foreach ($functions as $function)
			{
				// Gotta remove the # bit.
				if (strpos($function, '#') !== false)
					$function = str_replace('#', '', $function);

				$enabled = strstr($function, ']') === false;
				$function = str_replace(']', '', $function);

				if (strpos($function, '::') !== false)
				{
					$function = explode('::', $function);
					$function = $function[1];
				}
				$exploded = explode(':', $function);

				$hook_exists = !empty($hook_status[$hook][$exploded[0]]['exists']);
				$file_name = isset($hook_status[$hook][$exploded[0]]['in_file']) ? $hook_status[$hook][$exploded[0]]['in_file'] : ((substr($hook, -8) === '_include') ? 'zzzzzzzzz' : 'zzzzzzzza');
				$sort[] = $$sort_options[0];

				$temp_data[] = array(
					'id' => 'hookid_' . $id++,
					'hook_name' => $hook,
					'function_name' => $function,
					'real_function' => $exploded[0],
					'included_file' => isset($exploded[1]) ? strtr(trim($exploded[1]), array('$boarddir' => $boarddir, '$sourcedir' => $sourcedir, '$themedir' => $settings['theme_dir'])) : '',
					'file_name' => (isset($hook_status[$hook][$exploded[0]]['in_file']) ? $hook_status[$hook][$exploded[0]]['in_file'] : ''),
					'instance' => (isset($hook_status[$hook][$exploded[0]]['instance']) ? $hook_status[$hook][$exploded[0]]['instance'] : ''),
					'hook_exists' => $hook_exists,
					'status' => $hook_exists ? ($enabled ? 'allow' : 'moderate') : 'deny',
					'img_text' => $txt['hooks_' . ($hook_exists ? ($enabled ? 'active' : 'disabled') : 'missing')],
					'enabled' => $enabled,
					'can_be_disabled' => !isset($hook_status[$hook][$function]['enabled']),
				);
			}
		}
	}

	array_multisort($sort, $sort_options[1], $temp_data);

	$counter = 0;
	$start++;

	foreach ($temp_data as $data)
	{
		if (++$counter < $start)
			continue;
		elseif ($counter == $start + $per_page)
			break;

		$hooks_data[] = $data;
	}

	return $hooks_data;
}

/**
 * Simply returns the total count of integration hooks
 * Used by the integration hooks list function (list_integration_hooks)
 *
 * @global type $context
 * @return int
 */
function get_integration_hooks_count()
{
	global $context;

	$hooks = get_integration_hooks();
	$hooks_count = 0;

	$context['filter'] = false;
	if (isset($_GET['filter']))
		$context['filter'] = $_GET['filter'];

	foreach ($hooks as $hook => $functions)
	{
		if (empty($context['filter']) || (!empty($context['filter']) && $context['filter'] == $hook))
			$hooks_count += count($functions);
	}

	return $hooks_count;
}

/**
 * Parses modSettings to create integration hook array
 *
 * @staticvar type $integration_hooks
 * @return type
 */
function get_integration_hooks()
{
	global $modSettings;
	static $integration_hooks;

	if (!isset($integration_hooks))
	{
		$integration_hooks = array();
		foreach ($modSettings as $key => $value)
		{
			if (!empty($value) && substr($key, 0, 10) === 'integrate_')
				$integration_hooks[$key] = explode(',', $value);
		}
	}

	return $integration_hooks;
}

?>