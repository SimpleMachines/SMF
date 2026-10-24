<?php

/**
 * The two things that only make sense on one engine.
 *
 * MySQL: give the baseline a couple of MyISAM tables. This sounds like
 * vandalism, and it is deliberate. SMF 3.0 has a ConvertToInnoDb migration, but
 * a 2.1 install performed on a server where InnoDB is available -- which is
 * every server now -- creates every single table as InnoDB, including the ones
 * whose DDL says {$memory}. So the migration would find nothing to convert and
 * the baseline would silently fail to test it. Two low-traffic tables are
 * enough, and MySQL 8.4 still supports the engine.
 *
 * PostgreSQL: nothing to create. 2.1's own install script already builds the
 * SQL functions, the two custom operators and the ~40 sequences that the
 * PostgreSql* migrations look at. What is worth doing is checking they are
 * really there, because if a future PostgreSQL release rejected any of that
 * DDL the install would still appear to succeed and the gap would only surface
 * much later, during the upgrade.
 *
 * Exercises: v3_0\ConvertToInnoDb, PostgreSqlFunctions, PostgreSqlFindInSet,
 * PostgreSqlSequences, PostgreSqlTime, PostgreSqlSchemaDiff.
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2026 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1.7
 */

if (!defined('SMF'))
	die('No direct access...');

$baseline_name = '70-engine-quirks';

if (baseline_applied($baseline_name) && empty($baseline_force))
{
	baseline_say($baseline_name . ': skipped');
}
elseif (substr($db_type, 0, 5) === 'mysql')
{
	global $smcFunc, $db_prefix;

	// Both are caches SMF rebuilds by itself, so converting them cannot cost
	// anything even if a baseline is used for something other than an upgrade.
	$tables = array('log_search_results', 'log_floodcontrol');

	foreach ($tables as $table)
		$smcFunc['db_query']('', '
			ALTER TABLE ' . $db_prefix . $table . ' ENGINE = MyISAM',
			array()
		);

	baseline_say(sprintf('%s: %d table(s) set to MyISAM', $baseline_name, count($tables)));

	baseline_mark_applied($baseline_name);
}
else
{
	global $smcFunc;

	$checks = array(
		'functions' => array(
			'SELECT COUNT(*) FROM pg_proc p JOIN pg_namespace n ON n.oid = p.pronamespace WHERE n.nspname = {string:schema}',
			10,
		),
		'operators' => array(
			'SELECT COUNT(*) FROM pg_operator o JOIN pg_namespace n ON n.oid = o.oprnamespace WHERE n.nspname = {string:schema}',
			2,
		),
		'sequences' => array(
			'SELECT COUNT(*) FROM information_schema.sequences WHERE sequence_schema = {string:schema}',
			35,
		),
	);

	$found = array();

	foreach ($checks as $what => $check)
	{
		list ($sql, $minimum) = $check;

		$request = $smcFunc['db_query']('', $sql, array('schema' => 'public'));
		list ($count) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);

		if ((int) $count < $minimum)
			baseline_fail(sprintf(
				'%s: expected at least %d %s in the public schema, found %d. ' .
				"2.1's install script did not finish building them, and the upgrade would have nothing to migrate.",
				$baseline_name,
				$minimum,
				$what,
				$count
			));

		$found[] = $count . ' ' . $what;
	}

	baseline_say($baseline_name . ': ' . implode(', ', $found) . ' present');

	baseline_mark_applied($baseline_name);
}

?>