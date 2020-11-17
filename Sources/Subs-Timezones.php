<?php

/**
 * This file provides some functions to simplify working with time zones.
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2020 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 RC3
 */

if (!defined('SMF'))
	die('No direct access...');

/**
 * Returns an array that instructs SMF how to map specific time zones
 * (e.g. "America/Denver") onto the user-friendly "meta-zone" labels that
 * most people think of as time zones (e.g. "Mountain Time").
 *
 * @return array An array relating time zones to "meta-zones"
 */
function get_tzid_metazones()
{
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
	 */
	$tzid_metazones =  array(
		// Africa_Central (no DST)
		'Africa/Maputo' => 'Africa_Central',

		// Africa_East (no DST)
		'Africa/Nairobi' => 'Africa_East',

		// Africa_Morocco (uses DST)
		'Africa/Casablanca' => 'Africa_Morocco',

		// Africa_South (no DST)
		'Africa/Johannesburg' => 'Africa_South',

		// Africa_West (no DST)
		'Africa/Lagos' => 'Africa_West',

		// Antarctica_Casey (no DST)
		'Antarctica/Casey' => 'Antarctica_Casey',

		// Antarctica_Davis (no DST)
		'Antarctica/Davis' => 'Antarctica_Davis',

		// Antarctica_DumontDUrville (no DST)
		'Antarctica/DumontDUrville' => 'Antarctica_DumontDUrville',

		// Antarctica_Macquarie (no DST)
		'Antarctica/Macquarie' => 'Antarctica_Macquarie',

		// Antarctica_Mawson (no DST)
		'Antarctica/Mawson' => 'Antarctica_Mawson',

		// Antarctica_McMurdo (uses DST)
		'Antarctica/McMurdo' => 'Antarctica_McMurdo',

		// Antarctica_Palmer (no DST)
		'Antarctica/Palmer' => 'Antarctica_Palmer',

		// Antarctica_Rothera (no DST)
		'Antarctica/Rothera' => 'Antarctica_Rothera',

		// Antarctica_Syowa (no DST)
		'Antarctica/Syowa' => 'Antarctica_Syowa',

		// Antarctica_Troll (uses DST)
		'Antarctica/Troll' => 'Antarctica_Troll',

		// Antarctica_Vostok (no DST)
		'Antarctica/Vostok' => 'Antarctica_Vostok',

		// Asia_Afghanistan (no DST)
		'Asia/Kabul' => 'Asia_Afghanistan',

		// Asia_Arabian (no DST)
		'Asia/Riyadh' => 'Asia_Arabian',

		// Asia_Armenia (no DST)
		'Asia/Yerevan' => 'Asia_Armenia',

		// Asia_Azerbaijan (no DST)
		'Asia/Baku' => 'Asia_Azerbaijan',

		// Asia_Bangladesh (no DST)
		'Asia/Dhaka' => 'Asia_Bangladesh',

		// Asia_Bhutan (no DST)
		'Asia/Thimphu' => 'Asia_Bhutan',

		// Asia_Brunei (no DST)
		'Asia/Brunei' => 'Asia_Brunei',

		// Asia_China (no DST)
		'Asia/Shanghai' => 'Asia_China',

		// Asia_Damascus (uses DST)
		'Asia/Damascus' => 'Asia_Damascus',

		// Asia_East_Timor (no DST)
		'Asia/Dili' => 'Asia_East_Timor',

		// Asia_Georgia (no DST)
		'Asia/Tbilisi' => 'Asia_Georgia',

		// Asia_Gulf (no DST)
		'Asia/Dubai' => 'Asia_Gulf',

		// Asia_Hong_Kong (no DST)
		'Asia/Hong_Kong' => 'Asia_Hong_Kong',

		// Asia_India (no DST)
		'Asia/Kolkata' => 'Asia_India',

		// Asia_Indonesia_Central (no DST)
		'Asia/Makassar' => 'Asia_Indonesia_Central',

		// Asia_Indonesia_Eastern (no DST)
		'Asia/Jayapura' => 'Asia_Indonesia_Eastern',

		// Asia_Indonesia_Western (no DST)
		'Asia/Jakarta' => 'Asia_Indonesia_Western',

		// Asia_Iran (uses DST)
		'Asia/Tehran' => 'Asia_Iran',

		// Asia_Irkutsk (no DST)
		'Asia/Irkutsk' => 'Asia_Irkutsk',

		// Asia_Israel (uses DST)
		'Asia/Jerusalem' => 'Asia_Israel',

		// Asia_Japan (no DST)
		'Asia/Tokyo' => 'Asia_Japan',

		// Asia_Jordan (uses DST)
		'Asia/Amman' => 'Asia_Jordan',

		// Asia_Kamchatka (no DST)
		'Asia/Kamchatka' => 'Asia_Kamchatka',

		// Asia_Kazakhstan_Eastern (no DST)
		'Asia/Almaty' => 'Asia_Kazakhstan_Eastern',

		// Asia_Kazakhstan_Western (no DST)
		'Asia/Aqtau' => 'Asia_Kazakhstan_Western',

		// Asia_Korea (no DST)
		'Asia/Seoul' => 'Asia_Korea',

		// Asia_Krasnoyarsk (no DST)
		'Asia/Krasnoyarsk' => 'Asia_Krasnoyarsk',

		// Asia_Kyrgystan (no DST)
		'Asia/Bishkek' => 'Asia_Kyrgystan',

		// Asia_Libya (uses DST)
		'Asia/Beirut' => 'Asia_Libya',

		// Asia_Magadan (no DST)
		'Asia/Magadan' => 'Asia_Magadan',

		// Asia_Malaysia (no DST)
		'Asia/Kuala_Lumpur' => 'Asia_Malaysia',

		// Asia_Mongolia_Western (no DST)
		'Asia/Hovd' => 'Asia_Mongolia_Western',

		// Asia_Mongolia_EAstern (no DST)
		'Asia/Ulaanbaatar' => 'Asia_Mongolia_Eastern',

		// Asia_Myanmar (no DST)
		'Asia/Yangon' => 'Asia_Myanmar',

		// Asia_Nepal (no DST)
		'Asia/Kathmandu' => 'Asia_Nepal',

		// Asia_Omsk (no DST)
		'Asia/Omsk' => 'Asia_Omsk',

		// Asia_Pakistan (no DST)
		'Asia/Karachi' => 'Asia_Pakistan',

		// Asia_Palestine (uses DST)
		'Asia/Hebron' => 'Asia_Palestine',

		// Asia_Philippines (no DST)
		'Asia/Manila' => 'Asia_Philippines',

		// Asia_Singapore (no DST)
		'Asia/Singapore' => 'Asia_Singapore',

		// Asia_Southeast (no DST)
		'Asia/Bangkok' => 'Asia_Southeast',

		// Asia_Taiwan (no DST)
		'Asia/Taipei' => 'Asia_Taiwan',

		// Asia_Tajikistan (no DST)
		'Asia/Dushanbe' => 'Asia_Tajikistan',

		// Asia_Turkey (no DST)
		'Europe/Istanbul' => 'Asia_Turkey',

		// Asia_Turkmenistan (no DST)
		'Asia/Ashgabat' => 'Asia_Turkmenistan',

		// Asia_Uzbekistan (no DST)
		'Asia/Tashkent' => 'Asia_Uzbekistan',

		// Asia_Vladivostok (no DST)
		'Asia/Vladivostok' => 'Asia_Vladivostok',

		// Asia_Yakutsk (no DST)
		'Asia/Yakutsk' => 'Asia_Yakutsk',

		// Asia_Yekaterinburg (no DST)
		'Asia/Yekaterinburg' => 'Asia_Yekaterinburg',

		// Atlantic_Azores (uses DST)
		'Atlantic/Azores' => 'Atlantic_Azores',

		// Atlantic_Cape_Verde (no DST)
		'Atlantic/Cape_Verde' => 'Atlantic_Cape_Verde',

		// Atlantic_Falkland (no DST)
		'Atlantic/Stanley' => 'Atlantic_Falkland',

		// Atlantic_South_Georgia (no DST)
		'Atlantic/South_Georgia' => 'Atlantic_South_Georgia',

		// Australia_Central (uses DST)
		'Australia/Adelaide' => 'Australia_Central',

		// Australia_Central (no DST)
		'Australia/Darwin' => 'Australia_Central',

		// Australia_CentralWestern (no DST)
		'Australia/Eucla' => 'Australia_CentralWestern',

		// Australia_Eastern (uses DST)
		'Australia/Melbourne' => 'Australia_Eastern',

		// Australia_Eastern (no DST)
		'Australia/Brisbane' => 'Australia_Eastern',

		// Australia_Lord_Howe (uses DST)
		'Australia/Lord_Howe' => 'Australia_Lord_Howe',

		// Australia_Western (no DST)
		'Australia/Perth' => 'Australia_Western',

		// Europe_Central (uses DST)
		'Europe/Berlin' => 'Europe_Central',

		// Europe_Central (no DST)
		'Africa/Algiers' => 'Europe_Central',

		// Europe_Eastern (uses DST)
		'Europe/Helsinki' => 'Europe_Eastern',

		// Europe_Eastern (no DST)
		'Europe/Kaliningrad' => 'Europe_Eastern',

		// Europe_Eire (uses DST)
		'Europe/Dublin' => 'Europe_Eire',

		// Europe_UK (uses DST)
		'Europe/London' => 'Europe_UK',

		// Europe_Minsk (no DST)
		'Europe/Minsk' => 'Europe_Minsk',

		// Europe_Moldova (uses DST)
		'Europe/Chisinau' => 'Europe_Moldova',

		// Europe_Moscow (no DST)
		'Europe/Moscow' => 'Europe_Moscow',

		// Europe_Samara (no DST)
		'Europe/Samara' => 'Europe_Samara',

		// Europe_Volgograd (no DST)
		'Europe/Volgograd' => 'Europe_Volgograd',

		// Europe_Western (uses DST)
		'Europe/Lisbon' => 'Europe_Western',

		// GMT (no DST)
		'Africa/Abidjan' => 'GMT',

		// Indian_Chagos (no DST)
		'Indian/Chagos' => 'Indian_Chagos',

		// Indian_Christmas (no DST)
		'Indian/Christmas' => 'Indian_Christmas',

		// Indian_Cocos (no DST)
		'Indian/Cocos' => 'Indian_Cocos',

		// Indian_Kerguelen (no DST)
		'Indian/Kerguelen' => 'Indian_Kerguelen',

		// Indian_Maldives (no DST)
		'Indian/Maldives' => 'Indian_Maldives',

		// Indian_Mauritius (no DST)
		'Indian/Mauritius' => 'Indian_Mauritius',

		// Indian_Reunion (no DST)
		'Indian/Reunion' => 'Indian_Reunion',

		// Indian_Seychelles (no DST)
		'Indian/Mahe' => 'Indian_Seychelles',

		// North_America_Alaska (uses DST)
		'America/Anchorage' => 'North_America_Alaska',

		// North_America_Atlantic (uses DST)
		'America/Halifax' => 'North_America_Atlantic',

		// North_America_Atlantic (no DST)
		'America/Port_of_Spain' => 'North_America_Atlantic',

		// North_America_Central (uses DST)
		'America/Chicago' => 'North_America_Central',

		// North_America_Central (no DST)
		'America/Belize' => 'North_America_Central',

		// North_America_Mexico_Central (uses DST)
		'America/Mexico_City' => 'North_America_Mexico_Central',

		// North_America_Cuba (uses DST)
		'America/Havana' => 'North_America_Cuba',

		// North_America_Eastern (uses DST)
		'America/New_York' => 'North_America_Eastern',

		// North_America_Eastern (no DST)
		'America/Jamaica' => 'North_America_Eastern',

		// North_America_Greenland_Eastern (uses DST)
		'America/Scoresbysund' => 'North_America_Greenland_Eastern',

		// North_America_Greenland_Western (uses DST)
		'America/Godthab' => 'North_America_Greenland_Western',

		// North_America_Hawaii_Aleutian (uses DST)
		'America/Adak' => 'North_America_Hawaii_Aleutian',

		// North_America_Mountain (uses DST)
		'America/Denver' => 'North_America_Mountain',

		// North_America_Mountain (no DST)
		'America/Phoenix' => 'North_America_Mountain',

		// North_America_Mexico_Pacific (uses DST)
		'America/Chihuahua' => 'North_America_Mexico_Pacific',

		// North_America_Newfoundland (uses DST)
		'America/St_Johns' => 'North_America_Newfoundland',

		// North_America_Pacific (uses DST)
		'America/Los_Angeles' => 'North_America_Pacific',

		// North_America_St_Pierre_Miquelon (uses DST)
		'America/Miquelon' => 'North_America_St_Pierre_Miquelon',

		// Pacific_Bougainville (no DST)
		'Pacific/Bougainville' => 'Pacific_Bougainville',

		// Pacific_Chamorro (no DST)
		'Pacific/Guam' => 'Pacific_Chamorro',

		// Pacific_Chatham (uses DST)
		'Pacific/Chatham' => 'Pacific_Chatham',

		// Pacific_Chuuk (no DST)
		'Pacific/Chuuk' => 'Pacific_Chuuk',

		// Pacific_Cook (no DST)
		'Pacific/Rarotonga' => 'Pacific_Cook',

		// Pacific_Easter (uses DST)
		'Pacific/Easter' => 'Pacific_Easter',

		// Pacific_Fiji (uses DST)
		'Pacific/Fiji' => 'Pacific_Fiji',

		// Pacific_Galapagos (no DST)
		'Pacific/Galapagos' => 'Pacific_Galapagos',

		// Pacific_Gambier (no DST)
		'Pacific/Gambier' => 'Pacific_Gambier',

		// Pacific_Gilbert (no DST)
		'Pacific/Tarawa' => 'Pacific_Gilbert',

		// Pacific_Hawaii (no DST)
		'Pacific/Honolulu' => 'Pacific_Hawaii',

		// Pacific_Line (no DST)
		'Pacific/Kiritimati' => 'Pacific_Line',

		// Pacific_Marquesas (no DST)
		'Pacific/Marquesas' => 'Pacific_Marquesas',

		// Pacific_Marshall (no DST)
		'Pacific/Kwajalein' => 'Pacific_Marshall',

		// Pacific_Nauru (no DST)
		'Pacific/Nauru' => 'Pacific_Nauru',

		// Pacific_New_Caledonia (no DST)
		'Pacific/Noumea' => 'Pacific_New_Caledonia',

		// Pacific_Apia (uses DST)
		'Pacific/Apia' => 'Pacific_Apia',

		// Pacific_New_Zealand (uses DST)
		'Pacific/Auckland' => 'Pacific_New_Zealand',

		// Pacific_Niue (no DST)
		'Pacific/Niue' => 'Pacific_Niue',

		// Pacific_Norfolk (no DST)
		'Pacific/Norfolk' => 'Pacific_Norfolk',

		// Pacific_Palau (no DST)
		'Pacific/Palau' => 'Pacific_Palau',

		// Pacific_Papua_New_Guinea (no DST)
		'Pacific/Port_Moresby' => 'Pacific_Papua_New_Guinea',

		// Pacific_Phoenix_Islands (no DST)
		'Pacific/Enderbury' => 'Pacific_Phoenix_Islands',

		// Pacific_Pitcairn (no DST)
		'Pacific/Pitcairn' => 'Pacific_Pitcairn',

		// Pacific_Pohnpei (no DST)
		'Pacific/Pohnpei' => 'Pacific_Pohnpei',

		// Pacific_American_Samoa (no DST)
		'Pacific/Pago_Pago' => 'Pacific_Samoa',

		// Pacific_Solomon (no DST)
		'Pacific/Guadalcanal' => 'Pacific_Solomon',

		// Pacific_Tahiti (no DST)
		'Pacific/Tahiti' => 'Pacific_Tahiti',

		// Pacific_Tokelau (no DST)
		'Pacific/Fakaofo' => 'Pacific_Tokelau',

		// Pacific_Tonga (no DST)
		'Pacific/Tongatapu' => 'Pacific_Tonga',

		// Pacific_Tuvalu (no DST)
		'Pacific/Funafuti' => 'Pacific_Tuvalu',

		// Pacific_Vanuatu (no DST)
		'Pacific/Efate' => 'Pacific_Vanuatu',

		// Pacific_Wake (no DST)
		'Pacific/Wake' => 'Pacific_Wake',

		// Pacific_Wallis (no DST)
		'Pacific/Wallis' => 'Pacific_Wallis',

		// South_America_Acre (no DST)
		'America/Rio_Branco' => 'South_America_Acre',

		// South_America_Amazon (no DST)
		'America/Manaus' => 'South_America_Amazon',

		// South_America_Argentina (no DST)
		'America/Argentina/Buenos_Aires' => 'South_America_Argentina',

		// South_America_Bolivia (no DST)
		'America/La_Paz' => 'South_America_Bolivia',

		// South_America_Brasilia (no DST)
		'America/Sao_Paulo' => 'South_America_Brasilia',

		// South_America_Chile (uses DST)
		'America/Santiago' => 'South_America_Chile',

		// South_America_Chile (no DST)
		'America/Punta_Arenas' => 'South_America_Chile',

		// South_America_Colombia (no DST)
		'America/Bogota' => 'South_America_Colombia',

		// South_America_Ecuador (no DST)
		'America/Guayaquil' => 'South_America_Ecuador',

		// South_America_French_Guiana (no DST)
		'America/Cayenne' => 'South_America_French_Guiana',

		// South_America_Guyana (no DST)
		'America/Guyana' => 'South_America_Guyana',

		// South_America_Noronha (no DST)
		'America/Noronha' => 'South_America_Noronha',

		// South_America_Paraguay (uses DST)
		'America/Asuncion' => 'South_America_Paraguay',

		// South_America_Peru (no DST)
		'America/Lima' => 'South_America_Peru',

		// South_America_Suriname (no DST)
		'America/Paramaribo' => 'South_America_Suriname',

		// South_America_Uruguay (no DST)
		'America/Montevideo' => 'South_America_Uruguay',

		// South_America_Venezuela (no DST)
		'America/Caracas' => 'South_America_Venezuela',
	);

	return $tzid_metazones;
}

/**
 * Returns an array of all the time zones in a country, ranked according
 * to population and/or politically significance.
 *
 * @param string $country_code The two-character ISO-3166 code for a country.
 * @return array An array relating time zones to "meta-zones"
 */
function get_sorted_tzids_for_country($country_code)
{
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
		list appropriate. However, SMF will gracefully handle unexpected new
		time zones, so nothing will break in the meantime.
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
			'America/Godthab',
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
			'Europe/Kiev',
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

	$country_tzids = $sorted_tzids[$country_code];

	// Ensure we haven't missed anything.
	$temp = @timezone_identifiers_list(DateTimeZone::PER_COUNTRY, $country_code);
	if (!empty($temp))
		$country_tzids = array_unique(array_merge($country_tzids, $temp));

	return $country_tzids;
}

?>