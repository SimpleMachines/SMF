<?php

/**
 * This file contains database functions specific to search related activity.
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2017 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 Beta 3
 */

if (!defined('SMF'))
	die('No direct access...');

/**
 *  Add the file functions to the $smcFunc array.
 */
function db_search_init()
{
	global $smcFunc;

	if (!isset($smcFunc['db_search_query']) || $smcFunc['db_search_query'] != 'smf_db_search_query')
		$smcFunc += array(
			'db_search_query' => 'smf_db_search_query',
			'db_search_support' => 'smf_db_search_support',
			'db_create_word_search' => 'smf_db_create_word_search',
			'db_support_ignore' => false,
		);

	db_extend();

	//pg 9.5 got ignore support
	$version = $smcFunc['db_get_version']();
	// if we got a Beta Version
	if (stripos($version, 'beta') !== false)
		$version = substr($version, 0, stripos($version, 'beta')).'.0';
	// or RC
	if (stripos($version, 'rc') !== false)
		$version = substr($version, 0, stripos($version, 'rc')).'.0';

	if (version_compare($version,'9.5.0','>='))
		$smcFunc['db_support_ignore'] = true;
}

/**
 * This function will tell you whether this database type supports this search type.
 *
 * @param string $search_type The search type
 * @return boolean Whether or not the specified search type is supported by this DB system.
 */
function smf_db_search_support($search_type)
{
	$supported_types = array('custom','fulltext');

	return in_array($search_type, $supported_types);
}

/**
 * Returns the correct query for this search type.
 *
 * @param string $identifier A query identifier
 * @param string $db_string The query text
 * @param array $db_values An array of values to pass to $smcFunc['db_query']
 * @param resource $connection The current DB connection resource
 * @return resource The query result resource from $smcFunc['db_query']
 */
function smf_db_search_query($identifier, $db_string, $db_values = array(), $connection = null)
{
	global $smcFunc;

	$replacements = array(
		'create_tmp_log_search_topics' => array(
			'~mediumint\(\d\)~i' => 'int',
			'~unsigned~i' => '',
			'~ENGINE=MEMORY~i' => '',
		),
		'create_tmp_log_search_messages' => array(
			'~mediumint\(\d\)~i' => 'int',
			'~unsigned~i' => '',
			'~ENGINE=MEMORY~i' => '',
		),
		'drop_tmp_log_search_topics' => array(
			'~IF\sEXISTS~i' => '',
		),
		'drop_tmp_log_search_messages' => array(
			'~IF\sEXISTS~i' => '',
		),
		'insert_into_log_messages_fulltext' => array(
			'~LIKE~i' => 'iLIKE',
			'~NOT\sLIKE~i' => '~NOT iLIKE',
			'~NOT\sRLIKE~i' => '!~*',
			'~RLIKE~i' => '~*',
		),
		'insert_log_search_results_subject' => array(
			'~LIKE~i' => 'iLIKE',
			'~NOT\sLIKE~i' => 'NOT iLIKE',
			'~NOT\sRLIKE~i' => '!~*',
			'~RLIKE~i' => '~*',
		),
	);

	if (isset($replacements[$identifier]))
		$db_string = preg_replace(array_keys($replacements[$identifier]), array_values($replacements[$identifier]), $db_string);
	if (preg_match('~^\s*INSERT\sIGNORE~i', $db_string) != 0)
	{
		$db_string = preg_replace('~^\s*INSERT\sIGNORE~i', 'INSERT', $db_string);
		if ($smcFunc['db_support_ignore']){
			//pg style "INSERT INTO.... ON CONFLICT DO NOTHING"
			$db_string = $db_string.' ON CONFLICT DO NOTHING';
		}
		else
		{
			// Don't error on multi-insert.
			$db_values['db_error_skip'] = true;
		}
	}

	$return = $smcFunc['db_query']('', $db_string,
		$db_values, $connection
	);

	return $return;
}

/**
 * Highly specific function, to create the custom word index table.
 *
 * @param string $size The column size type (int, mediumint (8), etc.). Not used here.
 */
function smf_db_create_word_search($size)
{
	global $smcFunc;

	$size = 'int';

	$smcFunc['db_query']('', '
		CREATE TABLE {db_prefix}log_search_words (
			id_word {raw:size} NOT NULL default {string:string_zero},
			id_msg int NOT NULL default {string:string_zero},
			PRIMARY KEY (id_word, id_msg)
		)',
		array(
			'size' => $size,
			'string_zero' => '0',
		)
	);
}

?>