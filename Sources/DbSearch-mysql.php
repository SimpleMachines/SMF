<?php

/**
 * This file contains database functions specific to search related activity.
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2019 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 RC2
 */

if (!defined('SMF'))
	die('No direct access...');

/**
 *  Add the file functions to the $smcFunc array.
 */
function db_search_init()
{
	global $smcFunc;

	if (!isset($smcFunc['db_search_query']) || $smcFunc['db_search_query'] != 'smf_db_query')
		$smcFunc += array(
			'db_search_query' => 'smf_db_query',
			'db_search_support' => 'smf_db_search_support',
			'db_create_word_search' => 'smf_db_create_word_search',
			'db_support_ignore' => true,
		);
}

/**
 * This function will tell you whether this database type supports this search type.
 *
 * @param string $search_type The search type.
 * @return boolean Whether or not the specified search type is supported by this db system
 */
function smf_db_search_support($search_type)
{
	$supported_types = array('fulltext');

	return in_array($search_type, $supported_types);
}

/**
 * Highly specific function, to create the custom word index table.
 *
 * @param string $size The size of the desired index.
 */
function smf_db_create_word_search($size)
{
	global $smcFunc;

	if ($size == 'small')
		$size = 'smallint(5)';
	elseif ($size == 'medium')
		$size = 'mediumint(8)';
	else
		$size = 'int(10)';

	$smcFunc['db_query']('', '
		CREATE TABLE {db_prefix}log_search_words (
			id_word {raw:size} unsigned NOT NULL default {string:string_zero},
			id_msg int(10) unsigned NOT NULL default {string:string_zero},
			PRIMARY KEY (id_word, id_msg)
		) ENGINE=InnoDB',
		array(
			'string_zero' => '0',
			'size' => $size,
		)
	);
}

?>