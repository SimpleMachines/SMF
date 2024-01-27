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
foreach ((array) Lang::oldLanguageMap() as $lang => $locale) {
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
---}
---#
