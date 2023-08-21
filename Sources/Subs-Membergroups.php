<?php

/**
 * This file contains functions regarding manipulation of and information about membergroups.
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

use SMF\Config;
use SMF\Db\DatabaseApi as Db;

if (!defined('SMF'))
	die('No direct access...');

class_exists('SMF\\User');

/**
 * Retrieve a list of (visible) membergroups used by the cache.
 *
 * @return array An array of information about the cache
 */
function cache_getMembergroupList()
{
	$request = Db::$db->query('', '
		SELECT id_group, group_name, online_color
		FROM {db_prefix}membergroups
		WHERE min_posts = {int:min_posts}
			AND hidden = {int:not_hidden}
			AND id_group != {int:mod_group}
		ORDER BY group_name',
		array(
			'min_posts' => -1,
			'not_hidden' => 0,
			'mod_group' => 3,
		)
	);
	$groupCache = array();
	$group = array();
	while ($row = Db::$db->fetch_assoc($request))
	{
		$group[$row['id_group']] = $row;
		$groupCache[$row['id_group']] = '<a href="' . Config::$scripturl . '?action=groups;sa=members;group=' . $row['id_group'] . '" ' . ($row['online_color'] ? 'style="color: ' . $row['online_color'] . '"' : '') . '>' . $row['group_name'] . '</a>';
	}
	Db::$db->free_result($request);

	call_integration_hook('integrate_getMembergroupList', array(&$groupCache, $group));

	return array(
		'data' => $groupCache,
		'expires' => time() + 3600,
		'refresh_eval' => 'return \SMF\Config::$modSettings[\'settings_updated\'] > ' . time() . ';',
	);
}

?>