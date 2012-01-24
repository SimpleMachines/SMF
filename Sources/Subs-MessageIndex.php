<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2011 Simple Machines
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.0
 */

if (!defined('SMF'))
	die('Hacking attempt...');

function getBoardList($boardListOptions = array())
{
	global $smcFunc, $user_info;

	if (isset($boardListOptions['excluded_boards']) && isset($boardListOptions['included_boards']))
		trigger_error('getBoardList(): Setting both excluded_boards and included_boards is not allowed.', E_USER_ERROR);

	$where = array();
	$where_parameters = array();
	if (isset($boardListOptions['excluded_boards']))
	{
		$where[] = 'b.id_board NOT IN ({array_int:excluded_boards})';
		$where_parameters['excluded_boards'] = $boardListOptions['excluded_boards'];
	}

	if (isset($boardListOptions['included_boards']))
	{
		$where[] = 'b.id_board IN ({array_int:included_boards})';
		$where_parameters['included_boards'] = $boardListOptions['included_boards'];
	}

	if (!empty($boardListOptions['ignore_boards']))
		$where[] = '{query_wanna_see_board}';

	elseif (!empty($boardListOptions['use_permissions']))
		$where[] = '{query_see_board}';

	if (!empty($boardListOptions['not_redirection']))
	{
		$where[] = 'b.redirect = {string:blank_redirect}';
		$where_parameters['blank_redirect'] = '';
	}

	$request = $smcFunc['db_query']('messageindex_fetch_boards', '
		SELECT c.name AS cat_name, c.id_cat, b.id_board, b.name AS board_name, b.child_level
		FROM {db_prefix}boards AS b
			LEFT JOIN {db_prefix}categories AS c ON (c.id_cat = b.id_cat)' . (empty($where) ? '' : '
		WHERE ' . implode('
			AND ', $where)),
		$where_parameters
	);

	$return_value = array();
	if ($smcFunc['db_num_rows']($request) !== 0)
	{
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			if (!isset($return_value[$row['id_cat']]))
				$return_value[$row['id_cat']] = array(
					'id' => $row['id_cat'],
					'name' => $row['cat_name'],
					'boards' => array(),
				);

			$return_value[$row['id_cat']]['boards'][] = array(
				'id' => $row['id_board'],
				'name' => $row['board_name'],
				'child_level' => $row['child_level'],
				'selected' => isset($boardListOptions['selected_board']) && $boardListOptions['selected_board'] == $row['id_board'],
			);
		}
	}
	$smcFunc['db_free_result']($request);

	return $return_value;
}

?>