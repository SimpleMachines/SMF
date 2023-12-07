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
--- Adding support for recurring events...
/******************************************************************************/

---# Add duration, rrule, rdates, and exdates columns to calendar table
ALTER TABLE {$db_prefix}calendar
MODIFY COLUMN start_date DATE AFTER id_member;
ADD COLUMN duration VARCHAR(32) NOT NULL DEFAULT '';
ADD COLUMN rrule VARCHAR(1024) NOT NULL DEFAULT 'FREQ=YEARLY;COUNT=1';
ADD COLUMN rdates TEXT NOT NULL;
ADD COLUMN exdates TEXT NOT NULL;
ADD COLUMN adjustments JSON DEFAULT NULL;
ADD COLUMN sequence SMALLINT UNSIGNED NOT NULL DEFAULT '0';
ADD COLUMN uid VARCHAR(255) NOT NULL DEFAULT '',
ADD COLUMN type TINYINT UNSIGNED NOT NULL DEFAULT '0';
---#

---# Set duration and rrule values and change end_date
---{
	$updates = [];

	$request = Db::$db->query(
		'',
		'SELECT id_event, start_date, end_date, start_time, end_time, timezone
		FROM {db_prefix}calendar',
		[]
	);

	while ($row = Db::$db->fetch_assoc($request)) {
		$row = array_diff($row, array_filter($row, 'is_null'));

		$allday = !isset($row['start_time']) || !isset($row['end_time']) || !isset($row['timezone']) || !in_array($row['timezone'], timezone_identifiers_list(\DateTimeZone::ALL_WITH_BC));

		$start = new \DateTime($row['start_date'] . (!$allday ? ' ' . $row['start_time'] . ' ' . $row['timezone'] : ''));
		$end = new \DateTime($row['end_date'] . (!$allday ? ' ' . $row['end_time'] . ' ' . $row['timezone'] : ''));

		if ($allday) {
			$end->modify('+1 day');
		}

		$duration = date_diff($start, $end);

		$format = '';
		foreach (['y', 'm', 'd', 'h', 'i', 's'] as $part) {
			if ($part === 'h') {
				$format .= 'T';
			}

			if (!empty($duration->{$part})) {
				$format .= '%' . $part . ($part === 'i' ? 'M' : strtoupper($part));
			}
		}
		$format = rtrim('P' . $format, 'PT');

		$updates[$row['id_event']] = [
			'id_event' => $row['id_event'],
			'duration' => $duration->format($format),
			'end_date' => $end->format('Y-m-d'),
			'rrule' => 'FREQ=YEARLY;COUNT=1',
		];
	}
	Db::$db->free_result($request);

	foreach ($updates as $id_event => $changes) {
		Db::$db->query(
			'',
			'UPDATE {db_prefix}calendar
			SET duration = {string:duration}, end_date = {date:end_date}, rrule = {string:rrule}
			WHERE id_event = {int:id_event}',
			$changes
		);
	}
---}
---#