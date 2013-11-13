<?php

/**
 * Helper file for handing themes.
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines
 *
 * @copyright 2013 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 Alpha 1
 */

if (!defined('SMF'))
	die('No direct access...');


function get_single_theme($id)
{
	global $smcFunc, $context;

	// No data, no fun!
	if (empty($id))
		return false;

	$single = array();

	$request = $smcFunc['db_query']('', '
		SELECT id_theme, variable, value
		FROM {db_prefix}themes
		WHERE variable IN ({string:theme_dir}, {string:theme_url}, {string:images_url}, {string:name}, {string:theme_layers}, {string:theme_templates}, {string:version}, {string:install_for})
			AND id_theme = {int:id_theme}
			AND id_member = {int:no_member}',
		array(
			'id_theme' => $id,
			'no_member' => 0,
			'theme_dir' => 'theme_dir',
			'images_url' => 'images_url',
			'theme_url' => 'theme_url',
			'name' => 'name',
			'theme_layers' => 'theme_layers',
			'theme_templates' => 'theme_templates',
			'version' => 'version',
			'install_for' => 'install_for',
		)
	);

	while ($row = $smcFunc['db_fetch_assoc']($request))
		$single[$row['variable']] = $row['value'];

	return $single;
}

?>
