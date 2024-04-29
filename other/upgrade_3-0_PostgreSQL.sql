/* ATTENTION: You don't need to run or use this file!  The upgrade.php script does everything for you! */

/******************************************************************************/
--- Language Upgrade...
/******************************************************************************/

---{
$limit = 10000;
$statements = [];
$langs = [];
$args = ['defaultLang' => 'en_US'];
$members = [];

// Setup the case statement.
foreach (Lang::LANG_TO_LOCALE as $lang => $locale) {
    $statements[] = ' WHEN lngfile = {string:lang_' . $lang . '} THEN {string:locale_' . $locale . '}';
    $args['lang_' . $lang] = $lang;
    $args['locale_' . $locale] = $locale;
    $langs[] = $lang;
}

$is_done = false;
while (!$is_done)
{
    nextSubStep($substep);

    // Skip errors here so we don't croak if the columns don't exist...
    $request = Db::$db->query('', '
        SELECT id_member
        FROM {db_prefix}members
        WHERE lngfile IN ({array_string:possible_languages})
        ORDER BY id_member
        LIMIT {int:limit}',
        [
            'limit' => $limit,
            'possible_languages' => $langs
        ]
    );
	if (Db::$db->num_rows($request) == 0) {
        $is_done = true;
        break;
    } else {
		while ($row = Db::$db->fetch_assoc($request)) {
            $members[] = $row['id_member'];
		}
		Db::$db->free_result($request);
    }

    // Nobody to convert, woohoo!
    if (empty($members)) {
        $is_done = true;
        break;
    } else {
        $args['search_members'] = $members;
    }

    Db::$db->query('', '
        UPDATE {db_prefix}members
        SET lngfile = CASE
            ' . implode(' ', $statements) . '
            ELSE {string:defaultLang} END
        WHERE id_member IN ({array_int:search_members})',
        $args
	);
}

// Rename the privacy policy records.
foreach (Config::$modSettings as $variable => $value) {
	if (!str_starts_with($variable, 'policy_')) {
		continue;
	}

	if (str_starts_with($variable, 'policy_updated_')) {
		$locale = Lang::getLocaleFromLanguageName(substr($variable, 15));
		$new_variable = isset($locale) ? 'policy_updated_' . $locale : $variable;
	} else {
		$locale = 'policy_' . Lang::getLocaleFromLanguageName(substr($variable, 7));
		$new_variable = isset($locale) ? 'policy_' . $locale : $variable;
	}

	if ($variable !== $new_variable) {
		Config::updateModSettings([
			$new_variable => $value,
			$variable => null,
		]);

		unset($new_variable);
	}
}
---}
---#

/******************************************************************************/
--- Adding support for reactions
/******************************************************************************/
---{
// Make sure we haven't already done this...
$cols = Db::$db->list_columns('messages');
// If the reactions column exists in the messages table, there's nothing to do
if(!in_array($cols, 'reactions'))
{
    // Does the user_likes table exist?
    $table_exists = Db::$db->list_tables(false, '%user_likes');
    if (!empty($table_exists))
    {
        // It already exists. Rename it.
        upgrade_query("ALTER TABLE {db_prefix}user_likes RENAME TO {db_prefix}user_reacts");

        // Add the new column
        Db::$db->add_column('{db_prefix}user_reacts', ['name' => 'id_react', 'type' => 'smallint', 'null' => false, default => '0']);

        // Default react type is "like" for now...
        upgrade_query("UPDATE {db_prefix}user_reacts SET id_reaction=1");

        // Rename the like_time column
        Db::$db->change_column('{db_prefix}user_reacts', 'like_time', ['name' => 'react_time']);

        // Rename the index
        upgrade_query("ALTER INDEX idx_liker RENAME TO idx_reactor");

        // Rename the likes column in the messages table
        Db::$db->change_column('{db_prefix}messages', 'likes', ['name' => 'reactions']);

        // Update user alert prefs
        upgrade_query("UPDATE {db_prefix}user_alerts_prefs SET alert_pref='msg_react' WHERE alert_pref='msg_like'");
    }
    else
    {
        upgrade_query("
            CREATE TABLE {db_prefix}user_reacts (
                id_member mediumint default '0',
                id_reaction smallint default '0',
                content_type char(6) default '',
                content_id int default '0',
                react_time int unsigned not null default '0',
                PRIMARY KEY (content_id, content_type, id_member),
                INDEX idx_content (content_id, content_type),
                INDEX idx_reactor (id_member)
            )
        ");
    }

    // Either way we want to add the new table
    upgrade_query("CREATE SEQUENCE {db_prefix}reactions_seq");
    upgrade_query("
        CREATE TABLE {db_prefix}reactions (
            id_reaction smallint default nextval('{db_prefix}reactions_seq'),
            name varchar(255) not null default ''
        )
    ");
    // Default reaction is "like"
    upgrade_query("INSERT INTO {db_prefix}reactions (id_reaction, name) VALUES (1, 'like')");
}
---}