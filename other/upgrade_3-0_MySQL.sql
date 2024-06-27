/* ATTENTION: You don't need to run or use this file!  The upgrade.php script does everything for you! */

/******************************************************************************/
--- Adding SpoofDetector support
/******************************************************************************/

---# Adding a new column "spoofdetector_name" to members table
---{
Db::$db->add_column(
	'{db_prefix}members',
	[
		'name' => 'spoofdetector_name',
		'type' => 'varchar',
		'size' => 255,
		'null' => false,
		'default' => '',
	],
	[],
	'ignore',
);
Db::$db->add_index(
	'{db_prefix}members',
	[
		'name' => 'idx_spoofdetector_name',
		'columns' => ['spoofdetector_name'],
	],
	[],
	'ignore',
);
Db::$db->add_index(
	'{db_prefix}messages',
	[
		'name' => 'idx_spoofdetector_name_id',
		'columns' => ['spoofdetector_name', 'id_member'],
	],
	[],
	'ignore',
);
---}
---#

---# Adding new "spoofdetector_censor" setting
INSERT IGNORE INTO {$db_prefix}settings (variable, value) VALUES ('spoofdetector_censor', '1');
---#