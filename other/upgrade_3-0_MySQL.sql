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
--- Adding version information to posts, polls, and personal messages
/******************************************************************************/

---# Adding a new column "version" to messages table
ALTER TABLE {$db_prefix}messages
ADD COLUMN version VARCHAR(5) NOT NULL DEFAULT '';
---#

---# Adding a new column "version" to personal_messages table
ALTER TABLE {$db_prefix}personal_messages
ADD COLUMN version VARCHAR(5) NOT NULL DEFAULT '';
---#