<?php

/**
 * This file provides some functions to simplify working with time zones.
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2022 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1.3
 */

if (!defined('SMF'))
	die('No direct access...');

if (!defined('PHP_INT_MIN'))
	define('PHP_INT_MIN', ~PHP_INT_MAX);

/**
 * Returns an array that instructs SMF how to map specific time zones
 * (e.g. "America/Denver") onto the user-friendly "meta-zone" labels that
 * most people think of as time zones (e.g. "Mountain Time").
 *
 * @param string $when The date/time used to determine fallback values.
 *		May be a Unix timestamp or any string that strtotime() can understand.
 *		Defaults to 'now'.
 * @return array An array relating time zones to "meta-zones"
 */
function get_tzid_metazones($when = 'now')
{
	global $txt, $tztxt;

	// This should already have been loaded, but just in case...
	loadLanguage('Timezones');

	/*
		This array lists a series of representative time zones and their
		corresponding "meta-zone" labels.

		The term "representative" here means that a given time zone can
		represent others that use exactly the same rules for DST
		transitions, UTC offsets, and abbreviations. For example,
		Europe/Berlin can be representative for Europe/Rome,
		Europe/Paris, etc., because these cities all use exactly the
		same time zone rules and values.

		Meta-zone labels are the user friendly strings shown to the end
		user, e.g. "Mountain Standard Time". The values of this array
		are keys of strings defined in Timezones.{language}.php, which
		in turn are sprintf format strings used to generate the final
		label text.

		Sometimes several representative time zones will map onto the
		same meta-zone label. This usually happens when there are
		different rules for Daylight Saving time in locations that are
		otherwise the same. For example, both America/Denver and
		America/Phoenix map to North_America_Mountain, but the ultimate
		output will be 'Mountain Time (MST/MDT)' for America/Denver vs.
		'Mountain Standard Time (MST)' for America/Phoenix.

		If you are adding a new meta-zone to this list because the TZDB
		added a new time zone that doesn't fit any existing meta-zone,
		please also add a fallback in the get_tzid_fallbacks() function.
		This helps support SMF installs on servers using outdated
		versions of the TZDB.
	 */
	$tzid_metazones = array(
		// No DST
		'Africa/Abidjan' => 'GMT',

		// No DST
		'Africa/Algiers' => 'Europe_Central',

		// Uses DST
		'Africa/Casablanca' => 'Africa_Morocco',

		// No DST
		'Africa/Johannesburg' => 'Africa_South',

		// No DST
		'Africa/Lagos' => 'Africa_West',

		// No DST
		'Africa/Maputo' => 'Africa_Central',

		// No DST
		'Africa/Nairobi' => 'Africa_East',

		// Uses DST
		'America/Adak' => 'North_America_Hawaii_Aleutian',

		// Uses DST
		'America/Anchorage' => 'North_America_Alaska',

		// No DST
		'America/Argentina/Buenos_Aires' => 'South_America_Argentina',

		// Uses DST
		'America/Asuncion' => 'South_America_Paraguay',

		// No DST
		'America/Belize' => 'North_America_Central',

		// No DST
		'America/Bogota' => 'South_America_Colombia',

		// No DST
		'America/Caracas' => 'South_America_Venezuela',

		// No DST
		'America/Cayenne' => 'South_America_French_Guiana',

		// Uses DST
		'America/Chicago' => 'North_America_Central',

		// Uses DST
		'America/Chihuahua' => 'North_America_Mexico_Pacific',

		// Uses DST
		'America/Denver' => 'North_America_Mountain',

		// Uses DST
		'America/Nuuk' => 'North_America_Greenland_Western',

		// No DST
		'America/Guayaquil' => 'South_America_Ecuador',

		// No DST
		'America/Guyana' => 'South_America_Guyana',

		// Uses DST
		'America/Halifax' => 'North_America_Atlantic',

		// Uses DST
		'America/Havana' => 'North_America_Cuba',

		// No DST
		'America/Jamaica' => 'North_America_Eastern',

		// No DST
		'America/La_Paz' => 'South_America_Bolivia',

		// No DST
		'America/Lima' => 'South_America_Peru',

		// Uses DST
		'America/Los_Angeles' => 'North_America_Pacific',

		// No DST
		'America/Manaus' => 'South_America_Amazon',

		// Uses DST
		'America/Mexico_City' => 'North_America_Mexico_Central',

		// Uses DST
		'America/Miquelon' => 'North_America_St_Pierre_Miquelon',

		// No DST
		'America/Montevideo' => 'South_America_Uruguay',

		// Uses DST
		'America/New_York' => 'North_America_Eastern',

		// No DST
		'America/Noronha' => 'South_America_Noronha',

		// No DST
		'America/Paramaribo' => 'South_America_Suriname',

		// No DST
		'America/Phoenix' => 'North_America_Mountain',

		// No DST
		'America/Port_of_Spain' => 'North_America_Atlantic',

		// No DST
		'America/Punta_Arenas' => 'South_America_Chile_Magallanes',

		// No DST
		'America/Rio_Branco' => 'South_America_Acre',

		// Uses DST
		'America/Santiago' => 'South_America_Chile',

		// No DST
		'America/Sao_Paulo' => 'South_America_Brasilia',

		// Uses DST
		'America/Scoresbysund' => 'North_America_Greenland_Eastern',

		// Uses DST
		'America/St_Johns' => 'North_America_Newfoundland',

		// No DST
		'Antarctica/Casey' => 'Antarctica_Casey',

		// No DST
		'Antarctica/Davis' => 'Antarctica_Davis',

		// No DST
		'Antarctica/DumontDUrville' => 'Antarctica_DumontDUrville',

		// No DST
		'Antarctica/Macquarie' => 'Antarctica_Macquarie',

		// No DST
		'Antarctica/Mawson' => 'Antarctica_Mawson',

		// Uses DST
		'Antarctica/McMurdo' => 'Antarctica_McMurdo',

		// No DST
		'Antarctica/Palmer' => 'Antarctica_Palmer',

		// No DST
		'Antarctica/Rothera' => 'Antarctica_Rothera',

		// No DST
		'Antarctica/Syowa' => 'Antarctica_Syowa',

		// Uses DST
		'Antarctica/Troll' => 'Antarctica_Troll',

		// No DST
		'Antarctica/Vostok' => 'Antarctica_Vostok',

		// No DST
		'Asia/Almaty' => 'Asia_Kazakhstan_Eastern',

		// Uses DST
		'Asia/Amman' => 'Asia_Jordan',

		// No DST
		'Asia/Aqtau' => 'Asia_Kazakhstan_Western',

		// No DST
		'Asia/Ashgabat' => 'Asia_Turkmenistan',

		// No DST
		'Asia/Baku' => 'Asia_Azerbaijan',

		// No DST
		'Asia/Bangkok' => 'Asia_Southeast',

		// Uses DST
		'Asia/Beirut' => 'Asia_Libya',

		// No DST
		'Asia/Bishkek' => 'Asia_Kyrgystan',

		// No DST
		'Asia/Brunei' => 'Asia_Brunei',

		// Uses DST
		'Asia/Damascus' => 'Asia_Damascus',

		// No DST
		'Asia/Dhaka' => 'Asia_Bangladesh',

		// No DST
		'Asia/Dili' => 'Asia_East_Timor',

		// No DST
		'Asia/Dubai' => 'Asia_Gulf',

		// No DST
		'Asia/Dushanbe' => 'Asia_Tajikistan',

		// Uses DST
		'Asia/Gaza' => 'Asia_Palestine',

		// No DST
		'Asia/Hong_Kong' => 'Asia_Hong_Kong',

		// No DST
		'Asia/Hovd' => 'Asia_Mongolia_Western',

		// No DST
		'Asia/Irkutsk' => 'Asia_Irkutsk',

		// No DST
		'Asia/Jakarta' => 'Asia_Indonesia_Western',

		// No DST
		'Asia/Jayapura' => 'Asia_Indonesia_Eastern',

		// Uses DST
		'Asia/Jerusalem' => 'Asia_Israel',

		// No DST
		'Asia/Kabul' => 'Asia_Afghanistan',

		// No DST
		'Asia/Kamchatka' => 'Asia_Kamchatka',

		// No DST
		'Asia/Karachi' => 'Asia_Pakistan',

		// No DST
		'Asia/Kathmandu' => 'Asia_Nepal',

		// No DST
		'Asia/Kolkata' => 'Asia_India',

		// No DST
		'Asia/Krasnoyarsk' => 'Asia_Krasnoyarsk',

		// No DST
		'Asia/Kuala_Lumpur' => 'Asia_Malaysia',

		// No DST
		'Asia/Magadan' => 'Asia_Magadan',

		// No DST
		'Asia/Makassar' => 'Asia_Indonesia_Central',

		// No DST
		'Asia/Manila' => 'Asia_Philippines',

		// No DST
		'Asia/Omsk' => 'Asia_Omsk',

		// No DST
		'Asia/Riyadh' => 'Asia_Arabia',

		// No DST
		'Asia/Seoul' => 'Asia_Korea',

		// No DST
		'Asia/Shanghai' => 'Asia_China',

		// No DST
		'Asia/Singapore' => 'Asia_Singapore',

		// No DST
		'Asia/Taipei' => 'Asia_Taiwan',

		// No DST
		'Asia/Tashkent' => 'Asia_Uzbekistan',

		// No DST
		'Asia/Tbilisi' => 'Asia_Georgia',

		// Uses DST
		'Asia/Tehran' => 'Asia_Iran',

		// No DST
		'Asia/Thimphu' => 'Asia_Bhutan',

		// No DST
		'Asia/Tokyo' => 'Asia_Japan',

		// No DST
		'Asia/Ulaanbaatar' => 'Asia_Mongolia_Eastern',

		// No DST
		'Asia/Vladivostok' => 'Asia_Vladivostok',

		// No DST
		'Asia/Yakutsk' => 'Asia_Yakutsk',

		// No DST
		'Asia/Yangon' => 'Asia_Myanmar',

		// No DST
		'Asia/Yekaterinburg' => 'Asia_Yekaterinburg',

		// No DST
		'Asia/Yerevan' => 'Asia_Armenia',

		// Uses DST
		'Atlantic/Azores' => 'Atlantic_Azores',

		// No DST
		'Atlantic/Cape_Verde' => 'Atlantic_Cape_Verde',

		// No DST
		'Atlantic/South_Georgia' => 'Atlantic_South_Georgia',

		// No DST
		'Atlantic/Stanley' => 'Atlantic_Falkland',

		// Uses DST
		'Australia/Adelaide' => 'Australia_Central',

		// No DST
		'Australia/Brisbane' => 'Australia_Eastern',

		// No DST
		'Australia/Darwin' => 'Australia_Central',

		// No DST
		'Australia/Eucla' => 'Australia_CentralWestern',

		// Uses DST
		'Australia/Lord_Howe' => 'Australia_Lord_Howe',

		// Uses DST
		'Australia/Melbourne' => 'Australia_Eastern',

		// No DST
		'Australia/Perth' => 'Australia_Western',

		// Uses DST
		'Europe/Berlin' => 'Europe_Central',

		// Uses DST
		'Europe/Chisinau' => 'Europe_Moldova',

		// Uses DST
		'Europe/Dublin' => 'Europe_Eire',

		// Uses DST
		'Europe/Helsinki' => 'Europe_Eastern',

		// No DST
		'Europe/Istanbul' => 'Asia_Turkey',

		// No DST
		'Europe/Kaliningrad' => 'Europe_Eastern',

		// Uses DST
		'Europe/Lisbon' => 'Europe_Western',

		// Uses DST
		'Europe/London' => 'Europe_UK',

		// No DST
		'Europe/Minsk' => 'Europe_Minsk',

		// No DST
		'Europe/Moscow' => 'Europe_Moscow',

		// No DST
		'Europe/Samara' => 'Europe_Samara',

		// No DST
		'Europe/Volgograd' => 'Europe_Volgograd',

		// No DST
		'Indian/Chagos' => 'Indian_Chagos',

		// No DST
		'Indian/Christmas' => 'Indian_Christmas',

		// No DST
		'Indian/Cocos' => 'Indian_Cocos',

		// No DST
		'Indian/Kerguelen' => 'Indian_Kerguelen',

		// No DST
		'Indian/Mahe' => 'Indian_Seychelles',

		// No DST
		'Indian/Maldives' => 'Indian_Maldives',

		// No DST
		'Indian/Mauritius' => 'Indian_Mauritius',

		// No DST
		'Indian/Reunion' => 'Indian_Reunion',

		// Uses DST
		'Pacific/Apia' => 'Pacific_Apia',

		// Uses DST
		'Pacific/Auckland' => 'Pacific_New_Zealand',

		// No DST
		'Pacific/Bougainville' => 'Pacific_Bougainville',

		// Uses DST
		'Pacific/Chatham' => 'Pacific_Chatham',

		// No DST
		'Pacific/Chuuk' => 'Pacific_Chuuk',

		// Uses DST
		'Pacific/Easter' => 'Pacific_Easter',

		// No DST
		'Pacific/Efate' => 'Pacific_Vanuatu',

		// No DST
		'Pacific/Kanton' => 'Pacific_Phoenix_Islands',

		// No DST
		'Pacific/Fakaofo' => 'Pacific_Tokelau',

		// Uses DST
		'Pacific/Fiji' => 'Pacific_Fiji',

		// No DST
		'Pacific/Funafuti' => 'Pacific_Tuvalu',

		// No DST
		'Pacific/Galapagos' => 'Pacific_Galapagos',

		// No DST
		'Pacific/Gambier' => 'Pacific_Gambier',

		// No DST
		'Pacific/Guadalcanal' => 'Pacific_Solomon',

		// No DST
		'Pacific/Guam' => 'Pacific_Chamorro',

		// No DST
		'Pacific/Honolulu' => 'Pacific_Hawaii',

		// No DST
		'Pacific/Kiritimati' => 'Pacific_Line',

		// No DST
		'Pacific/Kwajalein' => 'Pacific_Marshall',

		// No DST
		'Pacific/Marquesas' => 'Pacific_Marquesas',

		// No DST
		'Pacific/Nauru' => 'Pacific_Nauru',

		// No DST
		'Pacific/Niue' => 'Pacific_Niue',

		// No DST
		'Pacific/Norfolk' => 'Pacific_Norfolk',

		// No DST
		'Pacific/Noumea' => 'Pacific_New_Caledonia',

		// No DST
		'Pacific/Pago_Pago' => 'Pacific_Samoa',

		// No DST
		'Pacific/Palau' => 'Pacific_Palau',

		// No DST
		'Pacific/Pitcairn' => 'Pacific_Pitcairn',

		// No DST
		'Pacific/Pohnpei' => 'Pacific_Pohnpei',

		// No DST
		'Pacific/Port_Moresby' => 'Pacific_Papua_New_Guinea',

		// No DST
		'Pacific/Rarotonga' => 'Pacific_Cook',

		// No DST
		'Pacific/Tahiti' => 'Pacific_Tahiti',

		// No DST
		'Pacific/Tarawa' => 'Pacific_Gilbert',

		// No DST
		'Pacific/Tongatapu' => 'Pacific_Tonga',

		// No DST
		'Pacific/Wake' => 'Pacific_Wake',

		// No DST
		'Pacific/Wallis' => 'Pacific_Wallis',
	);

	call_integration_hook('integrate_metazones', array(&$tzid_metazones, $when));

	// Fallbacks in case the server has an old version of the TZDB.
	$tzids = array_keys($tzid_metazones);
	$tzid_fallbacks = get_tzid_fallbacks($tzids, $when);
	foreach ($tzid_fallbacks as $orig_tzid => $alt_tzid)
	{
		// Skip any that are unchanged.
		if ($orig_tzid == $alt_tzid)
			continue;

		// Use fallback where possible.
		if (!empty($alt_tzid) && empty($tzid_metazones[$alt_tzid]))
		{
			$tzid_metazones[$alt_tzid] = $tzid_metazones[$orig_tzid];
			$txt[$alt_tzid] = $txt[$orig_tzid];
		}

		// Either way, get rid of the unknown time zone.
		unset($tzid_metazones[$orig_tzid]);
	}

	return $tzid_metazones;
}

/**
 * Returns an array of all the time zones in a country, ranked according
 * to population and/or political significance.
 *
 * @param string $country_code The two-character ISO-3166 code for a country.
 * @param string $when The date/time used to determine fallback values.
 *		May be a Unix timestamp or any string that strtotime() can understand.
 *		Defaults to 'now'.
 * @return array An array relating time zones to "meta-zones"
 */
function get_sorted_tzids_for_country($country_code, $when = 'now')
{
	static $country_tzids = array();

	/*
		This array lists all the individual time zones in each country,
		sorted by population (as reported in statistics available on
		Wikipedia in November 2020). Sorting this way enables us to
		consistently select the most appropriate individual time zone to
		represent all others that share its DST transition rules and values.
		For example, this ensures that New York will be preferred over
		random small towns in Indiana.

		If future versions of the time zone database add new time zone
		identifiers beyond those included here, they should be added to this
		list as appropriate. However, SMF will gracefully handle unexpected
		new time zones, so nothing will break in the meantime.
	 */
	$sorted_tzids = array(
		// '??' means international.
		'??' => array(
			'UTC',
		),
		'AD' => array(
			'Europe/Andorra',
		),
		'AE' => array(
			'Asia/Dubai',
		),
		'AF' => array(
			'Asia/Kabul',
		),
		'AG' => array(
			'America/Antigua',
		),
		'AI' => array(
			'America/Anguilla',
		),
		'AL' => array(
			'Europe/Tirane',
		),
		'AM' => array(
			'Asia/Yerevan',
		),
		'AO' => array(
			'Africa/Luanda',
		),
		'AQ' => array(
			// Sorted based on summer population.
			'Antarctica/McMurdo',
			'Antarctica/Casey',
			'Antarctica/Davis',
			'Antarctica/Mawson',
			'Antarctica/Rothera',
			'Antarctica/Syowa',
			'Antarctica/Palmer',
			'Antarctica/Troll',
			'Antarctica/DumontDUrville',
			'Antarctica/Vostok',
		),
		'AR' => array(
			'America/Argentina/Buenos_Aires',
			'America/Argentina/Cordoba',
			'America/Argentina/Tucuman',
			'America/Argentina/Salta',
			'America/Argentina/Jujuy',
			'America/Argentina/La_Rioja',
			'America/Argentina/San_Luis',
			'America/Argentina/Catamarca',
			'America/Argentina/Mendoza',
			'America/Argentina/San_Juan',
			'America/Argentina/Rio_Gallegos',
			'America/Argentina/Ushuaia',
		),
		'AS' => array(
			'Pacific/Pago_Pago',
		),
		'AT' => array(
			'Europe/Vienna',
		),
		'AU' => array(
			'Australia/Sydney',
			'Australia/Melbourne',
			'Australia/Brisbane',
			'Australia/Perth',
			'Australia/Adelaide',
			'Australia/Hobart',
			'Australia/Darwin',
			'Australia/Broken_Hill',
			'Australia/Currie',
			'Australia/Lord_Howe',
			'Australia/Eucla',
			'Australia/Lindeman',
			'Antarctica/Macquarie',
		),
		'AW' => array(
			'America/Aruba',
		),
		'AX' => array(
			'Europe/Mariehamn',
		),
		'AZ' => array(
			'Asia/Baku',
		),
		'BA' => array(
			'Europe/Sarajevo',
		),
		'BB' => array(
			'America/Barbados',
		),
		'BD' => array(
			'Asia/Dhaka',
		),
		'BE' => array(
			'Europe/Brussels',
		),
		'BF' => array(
			'Africa/Ouagadougou',
		),
		'BG' => array(
			'Europe/Sofia',
		),
		'BH' => array(
			'Asia/Bahrain',
		),
		'BI' => array(
			'Africa/Bujumbura',
		),
		'BJ' => array(
			'Africa/Porto-Novo',
		),
		'BL' => array(
			'America/St_Barthelemy',
		),
		'BM' => array(
			'Atlantic/Bermuda',
		),
		'BN' => array(
			'Asia/Brunei',
		),
		'BO' => array(
			'America/La_Paz',
		),
		'BQ' => array(
			'America/Kralendijk',
		),
		'BR' => array(
			'America/Sao_Paulo',
			'America/Bahia',
			'America/Fortaleza',
			'America/Manaus',
			'America/Recife',
			'America/Belem',
			'America/Maceio',
			'America/Campo_Grande',
			'America/Cuiaba',
			'America/Porto_Velho',
			'America/Rio_Branco',
			'America/Boa_Vista',
			'America/Santarem',
			'America/Araguaina',
			'America/Eirunepe',
			'America/Noronha',
		),
		'BS' => array(
			'America/Nassau',
		),
		'BT' => array(
			'Asia/Thimphu',
		),
		'BW' => array(
			'Africa/Gaborone',
		),
		'BY' => array(
			'Europe/Minsk',
		),
		'BZ' => array(
			'America/Belize',
		),
		'CA' => array(
			'America/Toronto',
			'America/Vancouver',
			'America/Edmonton',
			'America/Winnipeg',
			'America/Halifax',
			'America/Regina',
			'America/St_Johns',
			'America/Moncton',
			'America/Thunder_Bay',
			'America/Whitehorse',
			'America/Glace_Bay',
			'America/Yellowknife',
			'America/Swift_Current',
			'America/Dawson_Creek',
			'America/Goose_Bay',
			'America/Iqaluit',
			'America/Creston',
			'America/Fort_Nelson',
			'America/Inuvik',
			'America/Atikokan',
			'America/Rankin_Inlet',
			'America/Nipigon',
			'America/Cambridge_Bay',
			'America/Pangnirtung',
			'America/Dawson',
			'America/Blanc-Sablon',
			'America/Rainy_River',
			'America/Resolute',
		),
		'CC' => array(
			'Indian/Cocos',
		),
		'CD' => array(
			'Africa/Kinshasa',
			'Africa/Lubumbashi',
		),
		'CF' => array(
			'Africa/Bangui',
		),
		'CG' => array(
			'Africa/Brazzaville',
		),
		'CH' => array(
			'Europe/Zurich',
		),
		'CI' => array(
			'Africa/Abidjan',
		),
		'CK' => array(
			'Pacific/Rarotonga',
		),
		'CL' => array(
			'America/Santiago',
			'America/Punta_Arenas',
			'Pacific/Easter',
		),
		'CM' => array(
			'Africa/Douala',
		),
		'CN' => array(
			'Asia/Shanghai',
			'Asia/Urumqi',
		),
		'CO' => array(
			'America/Bogota',
		),
		'CR' => array(
			'America/Costa_Rica',
		),
		'CU' => array(
			'America/Havana',
		),
		'CV' => array(
			'Atlantic/Cape_Verde',
		),
		'CW' => array(
			'America/Curacao',
		),
		'CX' => array(
			'Indian/Christmas',
		),
		'CY' => array(
			'Asia/Nicosia',
			'Asia/Famagusta',
		),
		'CZ' => array(
			'Europe/Prague',
		),
		'DE' => array(
			'Europe/Berlin',
			'Europe/Busingen',
		),
		'DJ' => array(
			'Africa/Djibouti',
		),
		'DK' => array(
			'Europe/Copenhagen',
		),
		'DM' => array(
			'America/Dominica',
		),
		'DO' => array(
			'America/Santo_Domingo',
		),
		'DZ' => array(
			'Africa/Algiers',
		),
		'EC' => array(
			'America/Guayaquil',
			'Pacific/Galapagos',
		),
		'EE' => array(
			'Europe/Tallinn',
		),
		'EG' => array(
			'Africa/Cairo',
		),
		'EH' => array(
			'Africa/El_Aaiun',
		),
		'ER' => array(
			'Africa/Asmara',
		),
		'ES' => array(
			'Europe/Madrid',
			'Atlantic/Canary',
			'Africa/Ceuta',
		),
		'ET' => array(
			'Africa/Addis_Ababa',
		),
		'FI' => array(
			'Europe/Helsinki',
		),
		'FJ' => array(
			'Pacific/Fiji',
		),
		'FK' => array(
			'Atlantic/Stanley',
		),
		'FM' => array(
			'Pacific/Chuuk',
			'Pacific/Kosrae',
			'Pacific/Pohnpei',
		),
		'FO' => array(
			'Atlantic/Faroe',
		),
		'FR' => array(
			'Europe/Paris',
		),
		'GA' => array(
			'Africa/Libreville',
		),
		'GB' => array(
			'Europe/London',
		),
		'GD' => array(
			'America/Grenada',
		),
		'GE' => array(
			'Asia/Tbilisi',
		),
		'GF' => array(
			'America/Cayenne',
		),
		'GG' => array(
			'Europe/Guernsey',
		),
		'GH' => array(
			'Africa/Accra',
		),
		'GI' => array(
			'Europe/Gibraltar',
		),
		'GL' => array(
			'America/Nuuk',
			'America/Thule',
			'America/Scoresbysund',
			'America/Danmarkshavn',
		),
		'GM' => array(
			'Africa/Banjul',
		),
		'GN' => array(
			'Africa/Conakry',
		),
		'GP' => array(
			'America/Guadeloupe',
		),
		'GQ' => array(
			'Africa/Malabo',
		),
		'GR' => array(
			'Europe/Athens',
		),
		'GS' => array(
			'Atlantic/South_Georgia',
		),
		'GT' => array(
			'America/Guatemala',
		),
		'GU' => array(
			'Pacific/Guam',
		),
		'GW' => array(
			'Africa/Bissau',
		),
		'GY' => array(
			'America/Guyana',
		),
		'HK' => array(
			'Asia/Hong_Kong',
		),
		'HN' => array(
			'America/Tegucigalpa',
		),
		'HR' => array(
			'Europe/Zagreb',
		),
		'HT' => array(
			'America/Port-au-Prince',
		),
		'HU' => array(
			'Europe/Budapest',
		),
		'ID' => array(
			'Asia/Jakarta',
			'Asia/Makassar',
			'Asia/Pontianak',
			'Asia/Jayapura',
		),
		'IE' => array(
			'Europe/Dublin',
		),
		'IL' => array(
			'Asia/Jerusalem',
		),
		'IM' => array(
			'Europe/Isle_of_Man',
		),
		'IN' => array(
			'Asia/Kolkata',
		),
		'IO' => array(
			'Indian/Chagos',
		),
		'IQ' => array(
			'Asia/Baghdad',
		),
		'IR' => array(
			'Asia/Tehran',
		),
		'IS' => array(
			'Atlantic/Reykjavik',
		),
		'IT' => array(
			'Europe/Rome',
		),
		'JE' => array(
			'Europe/Jersey',
		),
		'JM' => array(
			'America/Jamaica',
		),
		'JO' => array(
			'Asia/Amman',
		),
		'JP' => array(
			'Asia/Tokyo',
		),
		'KE' => array(
			'Africa/Nairobi',
		),
		'KG' => array(
			'Asia/Bishkek',
		),
		'KH' => array(
			'Asia/Phnom_Penh',
		),
		'KI' => array(
			'Pacific/Tarawa',
			'Pacific/Kiritimati',
			'Pacific/Kanton',
			'Pacific/Enderbury',
		),
		'KM' => array(
			'Indian/Comoro',
		),
		'KN' => array(
			'America/St_Kitts',
		),
		'KP' => array(
			'Asia/Pyongyang',
		),
		'KR' => array(
			'Asia/Seoul',
		),
		'KW' => array(
			'Asia/Kuwait',
		),
		'KY' => array(
			'America/Cayman',
		),
		'KZ' => array(
			'Asia/Almaty',
			'Asia/Aqtobe',
			'Asia/Atyrau',
			'Asia/Qostanay',
			'Asia/Qyzylorda',
			'Asia/Aqtau',
			'Asia/Oral',
		),
		'LA' => array(
			'Asia/Vientiane',
		),
		'LB' => array(
			'Asia/Beirut',
		),
		'LC' => array(
			'America/St_Lucia',
		),
		'LI' => array(
			'Europe/Vaduz',
		),
		'LK' => array(
			'Asia/Colombo',
		),
		'LR' => array(
			'Africa/Monrovia',
		),
		'LS' => array(
			'Africa/Maseru',
		),
		'LT' => array(
			'Europe/Vilnius',
		),
		'LU' => array(
			'Europe/Luxembourg',
		),
		'LV' => array(
			'Europe/Riga',
		),
		'LY' => array(
			'Africa/Tripoli',
		),
		'MA' => array(
			'Africa/Casablanca',
		),
		'MC' => array(
			'Europe/Monaco',
		),
		'MD' => array(
			'Europe/Chisinau',
		),
		'ME' => array(
			'Europe/Podgorica',
		),
		'MF' => array(
			'America/Marigot',
		),
		'MG' => array(
			'Indian/Antananarivo',
		),
		'MH' => array(
			'Pacific/Majuro',
			'Pacific/Kwajalein',
		),
		'MK' => array(
			'Europe/Skopje',
		),
		'ML' => array(
			'Africa/Bamako',
		),
		'MM' => array(
			'Asia/Yangon',
		),
		'MN' => array(
			'Asia/Ulaanbaatar',
			'Asia/Choibalsan',
			'Asia/Hovd',
		),
		'MO' => array(
			'Asia/Macau',
		),
		'MP' => array(
			'Pacific/Saipan',
		),
		'MQ' => array(
			'America/Martinique',
		),
		'MR' => array(
			'Africa/Nouakchott',
		),
		'MS' => array(
			'America/Montserrat',
		),
		'MT' => array(
			'Europe/Malta',
		),
		'MU' => array(
			'Indian/Mauritius',
		),
		'MV' => array(
			'Indian/Maldives',
		),
		'MW' => array(
			'Africa/Blantyre',
		),
		'MX' => array(
			'America/Mexico_City',
			'America/Tijuana',
			'America/Monterrey',
			'America/Chihuahua',
			'America/Merida',
			'America/Hermosillo',
			'America/Cancun',
			'America/Matamoros',
			'America/Mazatlan',
			'America/Bahia_Banderas',
			'America/Ojinaga',
		),
		'MY' => array(
			'Asia/Kuala_Lumpur',
			'Asia/Kuching',
		),
		'MZ' => array(
			'Africa/Maputo',
		),
		'NA' => array(
			'Africa/Windhoek',
		),
		'NC' => array(
			'Pacific/Noumea',
		),
		'NE' => array(
			'Africa/Niamey',
		),
		'NF' => array(
			'Pacific/Norfolk',
		),
		'NG' => array(
			'Africa/Lagos',
		),
		'NI' => array(
			'America/Managua',
		),
		'NL' => array(
			'Europe/Amsterdam',
		),
		'NO' => array(
			'Europe/Oslo',
		),
		'NP' => array(
			'Asia/Kathmandu',
		),
		'NR' => array(
			'Pacific/Nauru',
		),
		'NU' => array(
			'Pacific/Niue',
		),
		'NZ' => array(
			'Pacific/Auckland',
			'Pacific/Chatham',
		),
		'OM' => array(
			'Asia/Muscat',
		),
		'PA' => array(
			'America/Panama',
		),
		'PE' => array(
			'America/Lima',
		),
		'PF' => array(
			'Pacific/Tahiti',
			'Pacific/Marquesas',
			'Pacific/Gambier',
		),
		'PG' => array(
			'Pacific/Port_Moresby',
			'Pacific/Bougainville',
		),
		'PH' => array(
			'Asia/Manila',
		),
		'PK' => array(
			'Asia/Karachi',
		),
		'PL' => array(
			'Europe/Warsaw',
		),
		'PM' => array(
			'America/Miquelon',
		),
		'PN' => array(
			'Pacific/Pitcairn',
		),
		'PR' => array(
			'America/Puerto_Rico',
		),
		'PS' => array(
			'Asia/Gaza',
			'Asia/Hebron',
		),
		'PT' => array(
			'Europe/Lisbon',
			'Atlantic/Madeira',
			'Atlantic/Azores',
		),
		'PW' => array(
			'Pacific/Palau',
		),
		'PY' => array(
			'America/Asuncion',
		),
		'QA' => array(
			'Asia/Qatar',
		),
		'RE' => array(
			'Indian/Reunion',
		),
		'RO' => array(
			'Europe/Bucharest',
		),
		'RS' => array(
			'Europe/Belgrade',
		),
		'RU' => array(
			'Europe/Moscow',
			'Asia/Novosibirsk',
			'Asia/Yekaterinburg',
			'Europe/Samara',
			'Asia/Omsk',
			'Asia/Krasnoyarsk',
			'Europe/Volgograd',
			'Europe/Saratov',
			'Asia/Barnaul',
			'Europe/Ulyanovsk',
			'Asia/Irkutsk',
			'Asia/Vladivostok',
			'Asia/Tomsk',
			'Asia/Novokuznetsk',
			'Europe/Astrakhan',
			'Europe/Kirov',
			'Europe/Kaliningrad',
			'Asia/Chita',
			'Asia/Yakutsk',
			'Asia/Sakhalin',
			'Asia/Kamchatka',
			'Asia/Magadan',
			'Asia/Anadyr',
			'Asia/Khandyga',
			'Asia/Ust-Nera',
			'Asia/Srednekolymsk',
		),
		'RW' => array(
			'Africa/Kigali',
		),
		'SA' => array(
			'Asia/Riyadh',
		),
		'SB' => array(
			'Pacific/Guadalcanal',
		),
		'SC' => array(
			'Indian/Mahe',
		),
		'SD' => array(
			'Africa/Khartoum',
		),
		'SE' => array(
			'Europe/Stockholm',
		),
		'SG' => array(
			'Asia/Singapore',
		),
		'SH' => array(
			'Atlantic/St_Helena',
		),
		'SI' => array(
			'Europe/Ljubljana',
		),
		'SJ' => array(
			'Arctic/Longyearbyen',
		),
		'SK' => array(
			'Europe/Bratislava',
		),
		'SL' => array(
			'Africa/Freetown',
		),
		'SM' => array(
			'Europe/San_Marino',
		),
		'SN' => array(
			'Africa/Dakar',
		),
		'SO' => array(
			'Africa/Mogadishu',
		),
		'SR' => array(
			'America/Paramaribo',
		),
		'SS' => array(
			'Africa/Juba',
		),
		'ST' => array(
			'Africa/Sao_Tome',
		),
		'SV' => array(
			'America/El_Salvador',
		),
		'SX' => array(
			'America/Lower_Princes',
		),
		'SY' => array(
			'Asia/Damascus',
		),
		'SZ' => array(
			'Africa/Mbabane',
		),
		'TC' => array(
			'America/Grand_Turk',
		),
		'TD' => array(
			'Africa/Ndjamena',
		),
		'TF' => array(
			'Indian/Kerguelen',
		),
		'TG' => array(
			'Africa/Lome',
		),
		'TH' => array(
			'Asia/Bangkok',
		),
		'TJ' => array(
			'Asia/Dushanbe',
		),
		'TK' => array(
			'Pacific/Fakaofo',
		),
		'TL' => array(
			'Asia/Dili',
		),
		'TM' => array(
			'Asia/Ashgabat',
		),
		'TN' => array(
			'Africa/Tunis',
		),
		'TO' => array(
			'Pacific/Tongatapu',
		),
		'TR' => array(
			'Europe/Istanbul',
		),
		'TT' => array(
			'America/Port_of_Spain',
		),
		'TV' => array(
			'Pacific/Funafuti',
		),
		'TW' => array(
			'Asia/Taipei',
		),
		'TZ' => array(
			'Africa/Dar_es_Salaam',
		),
		'UA' => array(
			'Europe/Kyiv',
			'Europe/Zaporozhye',
			'Europe/Simferopol',
			'Europe/Uzhgorod',
		),
		'UG' => array(
			'Africa/Kampala',
		),
		'UM' => array(
			'Pacific/Midway',
			'Pacific/Wake',
		),
		'US' => array(
			'America/New_York',
			'America/Los_Angeles',
			'America/Chicago',
			'America/Denver',
			'America/Phoenix',
			'America/Indiana/Indianapolis',
			'America/Detroit',
			'America/Kentucky/Louisville',
			'Pacific/Honolulu',
			'America/Anchorage',
			'America/Boise',
			'America/Juneau',
			'America/Indiana/Vincennes',
			'America/Sitka',
			'America/Menominee',
			'America/Indiana/Tell_City',
			'America/Kentucky/Monticello',
			'America/Nome',
			'America/Indiana/Knox',
			'America/North_Dakota/Beulah',
			'America/Indiana/Winamac',
			'America/Indiana/Petersburg',
			'America/Indiana/Vevay',
			'America/Metlakatla',
			'America/North_Dakota/New_Salem',
			'America/Indiana/Marengo',
			'America/Yakutat',
			'America/North_Dakota/Center',
			'America/Adak',
		),
		'UY' => array(
			'America/Montevideo',
		),
		'UZ' => array(
			'Asia/Tashkent',
			'Asia/Samarkand',
		),
		'VA' => array(
			'Europe/Vatican',
		),
		'VC' => array(
			'America/St_Vincent',
		),
		'VE' => array(
			'America/Caracas',
		),
		'VG' => array(
			'America/Tortola',
		),
		'VI' => array(
			'America/St_Thomas',
		),
		'VN' => array(
			'Asia/Ho_Chi_Minh',
		),
		'VU' => array(
			'Pacific/Efate',
		),
		'WF' => array(
			'Pacific/Wallis',
		),
		'WS' => array(
			'Pacific/Apia',
		),
		'YE' => array(
			'Asia/Aden',
		),
		'YT' => array(
			'Indian/Mayotte',
		),
		'ZA' => array(
			'Africa/Johannesburg',
		),
		'ZM' => array(
			'Africa/Lusaka',
		),
		'ZW' => array(
			'Africa/Harare',
		),
	);

	// Just in case...
	$country_code = strtoupper(trim($country_code));

	// Avoid unnecessary repetition.
	if (!isset($country_tzids[$country_code]))
	{
		call_integration_hook('integrate_country_timezones', array(&$sorted_tzids, $country_code, $when));

		$country_tzids[$country_code] = isset($sorted_tzids[$country_code]) ? $sorted_tzids[$country_code] : array();

		// If something goes wrong, we want an empty array, not false.
		$recognized_country_tzids = array_filter((array) @timezone_identifiers_list(DateTimeZone::PER_COUNTRY, $country_code));

		// Make sure that no time zones are missing.
		$country_tzids[$country_code] = array_unique(array_merge($country_tzids[$country_code], array_intersect($recognized_country_tzids, timezone_identifiers_list())));

		// Get fallbacks where necessary.
		$country_tzids[$country_code] = array_unique(array_values(get_tzid_fallbacks($country_tzids[$country_code], $when)));

		// Filter out any time zones that are still undefined.
		$country_tzids[$country_code] = array_intersect(array_filter($country_tzids[$country_code]), timezone_identifiers_list(DateTimeZone::ALL_WITH_BC));
	}

	return $country_tzids[$country_code];
}

/**
 * Checks a list of time zone identifiers to make sure they are all defined in
 * the installed version of the time zone database, and returns an array of
 * key-value substitution pairs.
 *
 * For defined time zone identifiers, the substitution value will be identical
 * to the original value. For undefined ones, the substitute will be a time zone
 * identifier that was equivalent to the missing one at the specified time, or
 * an empty string if there was no equivalent at that time.
 *
 * Note: These fallbacks do not need to include every new time zone ever. They
 * only need to cover any that are used in $tzid_metazones.
 *
 * To find the date & time when a new time zone comes into effect, check
 * the TZDB changelog at https://data.iana.org/time-zones/tzdb/NEWS
 *
 * @param array $tzids The time zone identifiers to check.
 * @param string $when The date/time used to determine substitute values.
 *		May be a Unix timestamp or any string that strtotime() can understand.
 *		Defaults to 'now'.
 * @return array Substitute values for any missing time zone identifiers.
 */
function get_tzid_fallbacks($tzids, $when = 'now')
{
	$tzids = (array) $tzids;

	$when = is_numeric($when) ? intval($when) : (is_int(@strtotime($when)) ? strtotime($when) : time());

	// 'ts' is the timestamp when the substitution first becomes valid.
	// 'tzid' is the alternative time zone identifier to use.
	$fallbacks = array(
		// 1. Simple renames. PHP_INT_MIN because these are valid for all dates.
		'Asia/Kolkata' => array(
			array(
				'ts' => PHP_INT_MIN,
				'tzid' => 'Asia/Calcutta',
			),
		),
		'Pacific/Chuuk' => array(
			array(
				'ts' => PHP_INT_MIN,
				'tzid' => 'Pacific/Truk',
			),
		),
		'Pacific/Kanton' => array(
			array(
				'ts' => PHP_INT_MIN,
				'tzid' => 'Pacific/Enderbury',
			),
		),
		'Pacific/Pohnpei' => array(
			array(
				'ts' => PHP_INT_MIN,
				'tzid' => 'Pacific/Ponape',
			),
		),
		'Asia/Yangon' => array(
			array(
				'ts' => PHP_INT_MIN,
				'tzid' => 'Asia/Rangoon',
			),
		),
		'America/Nuuk' => array(
			array(
				'ts' => PHP_INT_MIN,
				'tzid' => 'America/Godthab',
			),
		),
		'Europe/Busingen' => array(
			array(
				'ts' => PHP_INT_MIN,
				'tzid' => 'Europe/Zurich',
			),
		),
		'Europe/Kyiv' => array(
			array(
				'ts' => PHP_INT_MIN,
				'tzid' => 'Europe/Kiev',
			),
		),

		// 2. Newly created time zones.

		// The initial entry in many of the following zones is set to '' because
		// the records go back to eras before the adoption of standardized time
		// zones, which means no substitutes are possible then.

		// The same as Tasmania, except it stayed on DST all year in 2010.
		// Australia/Tasmania is an otherwise unused backwards compatibility
		// link to Australia/Hobart, so we can borrow it here without conflict.
		'Antarctica/Macquarie' => array(
			array(
				'ts' => PHP_INT_MIN,
				'tzid' => 'Australia/Tasmania',
			),
			array(
				'ts' => strtotime('2010-04-03T16:00:00+0000'),
				'tzid' => 'Etc/GMT-11',
			),
			array(
				'ts' => strtotime('2011-04-07T17:00:00+0000'),
				'tzid' => 'Australia/Tasmania',
			),
		),

		// Added in version 2013a.
		'Asia/Khandyga' => array(
			array(
				'ts' => PHP_INT_MIN,
				'tzid' => '',
			),
			array(
				'ts' => strtotime('1919-12-14T14:57:47+0000'),
				'tzid' => 'Etc/GMT-8',
			),
			array(
				'ts' => strtotime('1930-06-20T16:00:00+0000'),
				'tzid' => 'Asia/Yakutsk',
			),
			array(
				'ts' => strtotime('2003-12-31T15:00:00+0000'),
				'tzid' => 'Asia/Vladivostok',
			),
			array(
				'ts' => strtotime('2011-09-12T13:00:00+0000'),
				'tzid' => 'Asia/Yakutsk',
			),
		),

		// Added in version 2013a.
		'Asia/Ust-Nera' => array(
			array(
				'ts' => PHP_INT_MIN,
				'tzid' => '',
			),
			array(
				'ts' => strtotime('1919-12-14T14:27:06+0000'),
				'tzid' => 'Etc/GMT-8',
			),
			array(
				'ts' => strtotime('1930-06-20T16:00:00+0000'),
				'tzid' => 'Asia/Yakutsk',
			),
			array(
				'ts' => strtotime('1981-03-31T15:00:00+0000'),
				'tzid' => 'Asia/Magadan',
			),
			array(
				'ts' => strtotime('2011-09-12T12:00:00+0000'),
				'tzid' => 'Asia/Vladivostok',
			),
		),

		// Created in version 2014b.
		// This place uses two hours for DST. No substitutes are possible.
		'Antarctica/Troll' => array(
			array(
				'ts' => PHP_INT_MIN,
				'tzid' => '',
			),
		),

		// Diverged from Asia/Yakustsk in version 2014f.
		'Asia/Chita' => array(
			array(
				'ts' => PHP_INT_MIN,
				'tzid' => '',
			),
			array(
				'ts' => strtotime('1919-12-14T16:26:08+0000'),
				'tzid' => 'Asia/Yakutsk',
			),
			array(
				'ts' => strtotime('2014-10-25T16:00:00+0000'),
				'tzid' => 'Etc/GMT-8',
			),
			array(
				'ts' => strtotime('2016-03-26T18:00:00+0000'),
				'tzid' => 'Asia/Yakutsk',
			),
		),

		// Diverged from Asia/Magadan in version 2014f.
		'Asia/Srednekolymsk' => array(
			array(
				'ts' => PHP_INT_MIN,
				'tzid' => '',
			),
			array(
				'ts' => strtotime('1924-05-01T13:45:08+0000'),
				'tzid' => 'Etc/GMT-10',
			),
			array(
				'ts' => strtotime('1930-06-20T14:00:00+0000'),
				'tzid' => 'Asia/Magadan',
			),
			array(
				'ts' => strtotime('2014-10-25T14:00:00+0000'),
				'tzid' => 'Etc/GMT-11',
			),
		),

		// Diverged from Pacific/Port_Moresby in version 2014i.
		'Pacific/Bougainville' => array(
			array(
				'ts' => PHP_INT_MIN,
				'tzid' => '',
			),
			// Pacific/Yap is an unused link to Pacific/Port_Moresby.
			array(
				'ts' => strtotime('1879-12-31T14:11:20+0000'),
				'tzid' => 'Pacific/Yap',
			),
			// Apparently this was different for a while in World War II.
			array(
				'ts' => strtotime('1942-06-30T14:00:00+0000'),
				'tzid' => 'Singapore',
			),
			array(
				'ts' => strtotime('1945-08-20T15:00:00+0000'),
				'tzid' => 'Pacific/Yap',
			),
			// For dates after divergence, it is the same as Pacific/Kosrae.
			// If this ever ceases to be true, add another entry.
			array(
				'ts' => strtotime('2014-12-27T16:00:00+0000'),
				'tzid' => 'Pacific/Kosrae',
			),
		),

		// Added in version 2015g.
		'America/Fort_Nelson' => array(
			array(
				'ts' => PHP_INT_MIN,
				'tzid' => '',
			),
			array(
				'ts' => strtotime('1884-01-01T08:12:28+0000'),
				'tzid' => 'Canada/Pacific',
			),
			array(
				'ts' => strtotime('1946-01-01T08:00:00+0000'),
				'tzid' => 'Etc/GMT+8',
			),
			array(
				'ts' => strtotime('1947-01-01T08:00:00+0000'),
				'tzid' => 'Canada/Pacific',
			),
			array(
				'ts' => strtotime('2015-03-08T10:00:00+0000'),
				'tzid' => 'MST',
			),
		),

		// Created in version 2016b.
		'Europe/Astrakhan' => array(
			array(
				'ts' => PHP_INT_MIN,
				'tzid' => '',
			),
			array(
				'ts' => strtotime('1935-01-26T20:00:00+0000'),
				'tzid' => 'Europe/Samara',
			),
			array(
				'ts' => strtotime('1989-03-25T22:00:00+0000'),
				'tzid' => 'Europe/Volgograd',
			),
			array(
				'ts' => strtotime('2016-03-26T23:00:00+0000'),
				'tzid' => 'Europe/Samara',
			),
		),

		// Created in version 2016b.
		'Europe/Ulyanovsk' => array(
			array(
				'ts' => PHP_INT_MIN,
				'tzid' => '',
			),
			array(
				'ts' => strtotime('1935-01-26T20:00:00+0000'),
				'tzid' => 'Europe/Samara',
			),
			array(
				'ts' => strtotime('1989-03-25T22:00:00+0000'),
				'tzid' => 'W-SU',
			),
			array(
				'ts' => strtotime('2016-03-26T23:00:00+0000'),
				'tzid' => 'Europe/Samara',
			),
		),

		// Created in version 2016b.
		'Asia/Barnaul' => array(
			array(
				'ts' => PHP_INT_MIN,
				'tzid' => '',
			),
			array(
				'ts' => strtotime('1919-12-09T18:25:00+0000'),
				'tzid' => 'Etc/GMT-6',
			),
			array(
				'ts' => strtotime('1930-06-20T18:00:00+0000'),
				'tzid' => 'Asia/Novokuznetsk',
			),
			array(
				'ts' => strtotime('1995-05-27T17:00:00+0000'),
				'tzid' => 'Asia/Novosibirsk',
			),
			array(
				'ts' => strtotime('2016-03-26T20:00:00+0000'),
				'tzid' => 'Asia/Novokuznetsk',
			),
		),

		// Created in version 2016b.
		'Asia/Tomsk' => array(
			array(
				'ts' => PHP_INT_MIN,
				'tzid' => '',
			),
			array(
				'ts' => strtotime('1919-12-21T18:20:09+0000'),
				'tzid' => 'Asia/Novosibirsk',
			),
			array(
				'ts' => strtotime('1930-06-20T18:00:00+0000'),
				'tzid' => 'Asia/Novokuznetsk',
			),
			array(
				'ts' => strtotime('2002-04-30T20:00:00+0000'),
				'tzid' => 'Asia/Novosibirsk',
			),
			array(
				'ts' => strtotime('2016-05-28T20:00:00+0000'),
				'tzid' => 'Asia/Novokuznetsk',
			),
		),

		// Created in version 2016d.
		'Europe/Kirov' => array(
			array(
				'ts' => PHP_INT_MIN,
				'tzid' => '',
			),
			array(
				'ts' => strtotime('1935-01-26T20:00:00+0000'),
				'tzid' => 'Europe/Samara',
			),
			array(
				'ts' => strtotime('1989-03-25T22:00:00+0000'),
				'tzid' => 'Europe/Volgograd',
			),
			array(
				'ts' => strtotime('1992-03-28T22:00:00+0000'),
				'tzid' => 'W-SU',
			),
		),

		// Diverged from Asia/Nicosia in version 2016i.
		'Asia/Famagusta' => array(
			array(
				'ts' => PHP_INT_MIN,
				'tzid' => '',
			),
			// Europe/Nicosia is an otherwise unused link to Asia/Nicosia.
			array(
				'ts' => strtotime('1921-11-13T21:46:32+0000'),
				'tzid' => 'Europe/Nicosia',
			),
			// Became same as Europe/Istanbul.
			// Turkey is an otherwise unused link to Europe/Istanbul.
			array(
				'ts' => strtotime('2016-09-07T21:00:00+0000'),
				'tzid' => 'Turkey',
			),
			// Became same as Asia/Nicosia again.
			array(
				'ts' => strtotime('2017-10-29T01:00:00+0000'),
				'tzid' => 'Europe/Nicosia',
			),
		),

		// Created in version 2016j.
		'Asia/Atyrau' => array(
			array(
				'ts' => PHP_INT_MIN,
				'tzid' => '',
			),
			array(
				'ts' => strtotime('1924-05-01T20:32:16+0000'),
				'tzid' => 'Etc/GMT-3',
			),
			array(
				'ts' => strtotime('1930-06-20T21:00:00+0000'),
				'tzid' => 'Asia/Aqtau',
			),
			array(
				'ts' => strtotime('1981-09-30T19:00:00+0000'),
				'tzid' => 'Asia/Aqtobe',
			),
			array(
				'tz' => strtotime('1999-03-27T21:00:00+0000'),
				'tzid' => 'Etc/GMT-5'
			),
		),

		// Diverged from Europe/Volgograd in version 2016j.
		'Europe/Saratov' => array(
			array(
				'ts' => PHP_INT_MIN,
				'tzid' => '',
			),
			array(
				'ts' => strtotime('1935-01-26T20:00:00+0000'),
				'tzid' => 'Europe/Samara',
			),
			array(
				'ts' => strtotime('1988-03-26T22:00:00+0000'),
				'tzid' => 'Europe/Volgograd',
			),
			array(
				'ts' => strtotime('2016-12-03T23:00:00+0000'),
				'tzid' => 'Europe/Samara',
			),
		),

		// Diverged from America/Santiago in version 2017a.
		'America/Punta_Arenas' => array(
			array(
				'ts' => PHP_INT_MIN,
				'tzid' => '',
			),
			// Chile/Continental is an otherwise unused link to America/Santiago.
			array(
				'ts' => strtotime('1890-01-01T04:43:40+0000'),
				'tzid' => 'Chile/Continental',
			),
			array(
				'ts' => strtotime('1942-08-01T05:00:00+0000'),
				'tzid' => 'Etc/GMT+4',
			),
			array(
				'ts' => strtotime('1946-08-29T04:00:00+0000'),
				'tzid' => 'Chile/Continental',
			),
			// America/Mendoza is an otherwise unused link to America/Argentina/Mendoza.
			array(
				'ts' => strtotime('2016-12-04T03:00:00+0000'),
				'tzid' => 'America/Mendoza',
			),
		),

		// Diverged from Asia/Qyzylorda in version 2018h.
		'Asia/Qostanay' => array(
			array(
				'ts' => PHP_INT_MIN,
				'tzid' => '',
			),
			array(
				'ts' => strtotime('1924-05-01T19:45:32+0000'),
				'tzid' => 'Asia/Qyzylorda',
			),
			array(
				'ts' => strtotime('1930-06-20T20:00:00+0000'),
				'tzid' => 'Asia/Aqtobe',
			),
			array(
				'ts' => strtotime('2004-10-30T21:00:00+0000'),
				'tzid' => 'Asia/Almaty',
			),
		),
	);

	$missing = array_diff($tzids, timezone_identifiers_list(DateTimeZone::ALL_WITH_BC));

	call_integration_hook('integrate_timezone_fallbacks', array(&$fallbacks, &$missing, $tzids, $when));

	$replacements = array();

	foreach ($tzids as $tzid)
	{
		// Not missing.
		if (!in_array($tzid, $missing))
			$replacements[$tzid] = $tzid;

		// Missing and we have no fallback.
		elseif (empty($fallbacks[$tzid]))
			$replacements[$tzid] = '';

		// Missing, but we have a fallback.
		else
		{
			usort(
				$fallbacks[$tzid],
				function ($a, $b)
				{
					return $a['ts'] > $b['ts'];
				}
			);

			foreach ($fallbacks[$tzid] as $alt)
			{
				if ($when < $alt['ts'])
					break;

				$replacements[$tzid] = $alt['tzid'];
			}

			// Replacement is already in use.
			if (in_array($alt['tzid'], $replacements) || (in_array($alt['tzid'], $tzids) && strpos($alt['tzid'], 'Etc/') === false))
				$replacements[$tzid] = '';

			if (empty($replacements[$tzid]))
				$replacements[$tzid] = '';
		}
	}

	return $replacements;
}

/**
 * Validates a set of two-character ISO 3166-1 country codes.
 *
 * @param array|string $country_codes Array or CSV string of country codes.
 * @param bool $as_csv If true, return CSV string instead of array.
 * @return array|string Array or CSV string of valid country codes.
 */
function validate_iso_country_codes($country_codes, $as_csv = false)
{
	if (is_string($country_codes))
		$country_codes = explode(',', $country_codes);
	else
		$country_codes = array_map('strval', (array) $country_codes);

	foreach ($country_codes as $key => $country_code)
	{
		$country_code = strtoupper(trim($country_code));
		$country_tzids = strlen($country_code) !== 2 ? null : @timezone_identifiers_list(DateTimeZone::PER_COUNTRY, $country_code);
		$country_codes[$key] = empty($country_tzids) ? null : $country_code;
	}

	$country_codes = array_filter($country_codes);

	if (!empty($as_csv))
		$country_codes = implode(',', $country_codes);

	return $country_codes;
}

?>