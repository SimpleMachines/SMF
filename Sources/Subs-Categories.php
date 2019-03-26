<?php

/**
 * This file contains the functions to add, modify, remove, collapse and expand categories.
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
 * Edit the position and properties of a category.
 * general function to modify the settings and position of a category.
 * used by ManageBoards.php to change the settings of a category.
 *
 * @param int $category_id The ID of the category
 * @param array $catOptions An array containing data and options related to the category
 */
function modifyCategory($category_id, $catOptions)
{
	global $sourcedir, $smcFunc;

	$catUpdates = array();
	$catParameters = array();

	$cat_id = $category_id;
	call_integration_hook('integrate_pre_modify_category', array($cat_id, &$catOptions));

	// Wanna change the categories position?
	if (isset($catOptions['move_after']))
	{
		// Store all categories in the proper order.
		$cats = array();
		$cat_order = array();

		// Setting 'move_after' to '0' moves the category to the top.
		if ($catOptions['move_after'] == 0)
			$cats[] = $category_id;

		// Grab the categories sorted by cat_order.
		$request = $smcFunc['db_query']('', '
			SELECT id_cat, cat_order
			FROM {db_prefix}categories
			ORDER BY cat_order',
			array(
			)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			if ($row['id_cat'] != $category_id)
				$cats[] = $row['id_cat'];
			if ($row['id_cat'] == $catOptions['move_after'])
				$cats[] = $category_id;
			$cat_order[$row['id_cat']] = $row['cat_order'];
		}
		$smcFunc['db_free_result']($request);

		// Set the new order for the categories.
		foreach ($cats as $index => $cat)
			if ($index != $cat_order[$cat])
				$smcFunc['db_query']('', '
					UPDATE {db_prefix}categories
					SET cat_order = {int:new_order}
					WHERE id_cat = {int:current_category}',
					array(
						'new_order' => $index,
						'current_category' => $cat,
					)
				);

		// If the category order changed, so did the board order.
		require_once($sourcedir . '/Subs-Boards.php');
		reorderBoards();
	}

	if (isset($catOptions['cat_name']))
	{
		$catUpdates[] = 'name = {string:cat_name}';
		$catParameters['cat_name'] = $catOptions['cat_name'];
	}

	if (isset($catOptions['cat_desc']))
	{
		$catUpdates[] = 'description = {string:cat_desc}';
		$catParameters['cat_desc'] = $catOptions['cat_desc'];
	}

	// Can a user collapse this category or is it too important?
	if (isset($catOptions['is_collapsible']))
	{
		$catUpdates[] = 'can_collapse = {int:is_collapsible}';
		$catParameters['is_collapsible'] = $catOptions['is_collapsible'] ? 1 : 0;
	}

	$cat_id = $category_id;
	call_integration_hook('integrate_modify_category', array($cat_id, &$catUpdates, &$catParameters));

	// Do the updates (if any).
	if (!empty($catUpdates))
	{
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}categories
			SET
				' . implode(',
				', $catUpdates) . '
			WHERE id_cat = {int:current_category}',
			array_merge($catParameters, array(
				'current_category' => $category_id,
			))
		);

		if (empty($catOptions['dont_log']))
			logAction('edit_cat', array('catname' => isset($catOptions['cat_name']) ? $catOptions['cat_name'] : $category_id), 'admin');
	}
}

/**
 * Create a new category.
 * general function to create a new category and set its position.
 * allows (almost) the same options as the modifyCat() function.
 * returns the ID of the newly created category.
 *
 * @param array $catOptions An array of data and settings related to the new category. Should have at least 'cat_name' and can also have 'cat_desc', 'move_after' and 'is_collapsable'
 */
function createCategory($catOptions)
{
	global $smcFunc;

	// Check required values.
	if (!isset($catOptions['cat_name']) || trim($catOptions['cat_name']) == '')
		trigger_error('createCategory(): A category name is required', E_USER_ERROR);

	// Set default values.
	if (!isset($catOptions['cat_desc']))
		$catOptions['cat_desc'] = '';
	if (!isset($catOptions['move_after']))
		$catOptions['move_after'] = 0;
	if (!isset($catOptions['is_collapsible']))
		$catOptions['is_collapsible'] = true;
	// Don't log an edit right after.
	$catOptions['dont_log'] = true;

	$cat_columns = array(
		'name' => 'string-48',
		'description' => 'string',
	);
	$cat_parameters = array(
		$catOptions['cat_name'],
		$catOptions['cat_desc'],
	);

	call_integration_hook('integrate_create_category', array(&$catOptions, &$cat_columns, &$cat_parameters));

	// Add the category to the database.
	$category_id = $smcFunc['db_insert']('',
		'{db_prefix}categories',
		$cat_columns,
		$cat_parameters,
		array('id_cat'),
		1
	);

	// Set the given properties to the newly created category.
	modifyCategory($category_id, $catOptions);

	logAction('add_cat', array('catname' => $catOptions['cat_name']), 'admin');

	// Return the database ID of the category.
	return $category_id;
}

/**
 * Remove one or more categories.
 * general function to delete one or more categories.
 * allows to move all boards in the categories to a different category before deleting them.
 * if moveChildrenTo is set to null, all boards inside the given categories will be deleted.
 * deletes all information that's associated with the given categories.
 * updates the statistics to reflect the new situation.
 *
 * @param array $categories The IDs of the categories to delete
 * @param int $moveBoardsTo The ID of the category to move any boards to or null to delete the boards
 */
function deleteCategories($categories, $moveBoardsTo = null)
{
	global $sourcedir, $smcFunc, $cat_tree;

	require_once($sourcedir . '/Subs-Boards.php');

	getBoardTree();

	call_integration_hook('integrate_delete_category', array($categories, &$moveBoardsTo));

	// With no category set to move the boards to, delete them all.
	if ($moveBoardsTo === null)
	{
		$request = $smcFunc['db_query']('', '
			SELECT id_board
			FROM {db_prefix}boards
			WHERE id_cat IN ({array_int:category_list})',
			array(
				'category_list' => $categories,
			)
		);
		$boards_inside = array();
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$boards_inside[] = $row['id_board'];
		$smcFunc['db_free_result']($request);

		if (!empty($boards_inside))
			deleteBoards($boards_inside, null);
	}

	// Make sure the safe category is really safe.
	elseif (in_array($moveBoardsTo, $categories))
		trigger_error('deleteCategories(): You cannot move the boards to a category that\'s being deleted', E_USER_ERROR);

	// Move the boards inside the categories to a safe category.
	else
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}boards
			SET id_cat = {int:new_parent_cat}
			WHERE id_cat IN ({array_int:category_list})',
			array(
				'category_list' => $categories,
				'new_parent_cat' => $moveBoardsTo,
			)
		);

	// Do the deletion of the category itself
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}categories
		WHERE id_cat IN ({array_int:category_list})',
		array(
			'category_list' => $categories,
		)
	);

	// Log what we've done.
	foreach ($categories as $category)
		logAction('delete_cat', array('catname' => $cat_tree[$category]['node']['name']), 'admin');

	// Get all boards back into the right order.
	reorderBoards();
}

?>