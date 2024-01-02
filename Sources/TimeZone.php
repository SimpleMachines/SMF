<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2024 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 1
 */

namespace SMF;

/**
 * This class provides some methods to simplify working with time zones.
 */
class TimeZone extends \DateTimeZone
{
	use BackwardCompatibility;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = [
		'func_names' => [
			'list' => 'smf_list_timezones',
			'getTzidMetazones' => 'get_tzid_metazones',
			'getSortedTzidsForCountry' => 'get_sorted_tzids_for_country',
			'getTzidFallbacks' => 'get_tzid_fallbacks',
			'validateIsoCountryCodes' => 'validate_iso_country_codes',
		],
	];

	/*****************
	 * Class constants
	 *****************/

	/**
	 * Never uses DST.
	 */
	public const DST_NEVER = 0;

	/**
	 * Uses DST for some parts of the year, and not for other parts.
	 */
	public const DST_SWITCHES = 1;

	/**
	 * Uses DST throughout the entire year.
	 */
	public const DST_ALWAYS = 2;

	/**************************
	 * Public static properties
	 **************************/

	/**
	 * @var array
	 *
	 * This array lists a series of representative time zones and their
	 * corresponding "meta-zone" labels.
	 *
	 * The term "representative" here means that a given time zone can
	 * represent others that use exactly the same rules for DST
	 * transitions, UTC offsets, and abbreviations. For example,
	 * Europe/Berlin can be representative for Europe/Rome,
	 * Europe/Paris, etc., because these cities all use exactly the
	 * same time zone rules and values.
	 *
	 * Meta-zone labels are the user friendly strings shown to the end
	 * user, e.g. "Mountain Standard Time". The values of this array
	 * are keys of strings defined in Timezones.{language}.php, which
	 * in turn are sprintf format strings used to generate the final
	 * label text.
	 *
	 * Sometimes several representative time zones will map onto the
	 * same meta-zone label. This usually happens when there are
	 * different rules for Daylight Saving time in locations that are
	 * otherwise the same. For example, both America/Denver and
	 * America/Phoenix map to North_America_Mountain, but the ultimate
	 * output will be 'Mountain Time (MST/MDT)' for America/Denver vs.
	 * 'Mountain Standard Time (MST)' for America/Phoenix.
	 *
	 * If you are adding a new meta-zone to this list because the TZDB
	 * added a new time zone that doesn't fit any existing meta-zone,
	 * please also add a fallback in the get_tzid_fallbacks() function.
	 * This helps support SMF installs on servers using outdated
	 * versions of the TZDB.
	 */
	public static array $metazones = [
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
	];

	/**
	 * @var array
	 *
	 * This array lists all the individual time zones in each country,
	 * sorted by population (as reported in statistics available on
	 * Wikipedia in November 2020). Sorting this way enables us to
	 * consistently select the most appropriate individual time zone to
	 * represent all others that share its DST transition rules and values.
	 * For example, this ensures that New York will be preferred over
	 * random small towns in Indiana.
	 *
	 * If future versions of the time zone database add new time zone
	 * identifiers beyond those included here, they should be added to this
	 * list as appropriate. However, SMF will gracefully handle unexpected
	 * new time zones, so nothing will break in the meantime.
	 */
	public static array $sorted_tzids = [
		// '??' means international.
		'??' => [
			'UTC',
		],
		'AD' => [
			'Europe/Andorra',
		],
		'AE' => [
			'Asia/Dubai',
		],
		'AF' => [
			'Asia/Kabul',
		],
		'AG' => [
			'America/Antigua',
		],
		'AI' => [
			'America/Anguilla',
		],
		'AL' => [
			'Europe/Tirane',
		],
		'AM' => [
			'Asia/Yerevan',
		],
		'AO' => [
			'Africa/Luanda',
		],
		'AQ' => [
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
		],
		'AR' => [
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
		],
		'AS' => [
			'Pacific/Pago_Pago',
		],
		'AT' => [
			'Europe/Vienna',
		],
		'AU' => [
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
		],
		'AW' => [
			'America/Aruba',
		],
		'AX' => [
			'Europe/Mariehamn',
		],
		'AZ' => [
			'Asia/Baku',
		],
		'BA' => [
			'Europe/Sarajevo',
		],
		'BB' => [
			'America/Barbados',
		],
		'BD' => [
			'Asia/Dhaka',
		],
		'BE' => [
			'Europe/Brussels',
		],
		'BF' => [
			'Africa/Ouagadougou',
		],
		'BG' => [
			'Europe/Sofia',
		],
		'BH' => [
			'Asia/Bahrain',
		],
		'BI' => [
			'Africa/Bujumbura',
		],
		'BJ' => [
			'Africa/Porto-Novo',
		],
		'BL' => [
			'America/St_Barthelemy',
		],
		'BM' => [
			'Atlantic/Bermuda',
		],
		'BN' => [
			'Asia/Brunei',
		],
		'BO' => [
			'America/La_Paz',
		],
		'BQ' => [
			'America/Kralendijk',
		],
		'BR' => [
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
		],
		'BS' => [
			'America/Nassau',
		],
		'BT' => [
			'Asia/Thimphu',
		],
		'BW' => [
			'Africa/Gaborone',
		],
		'BY' => [
			'Europe/Minsk',
		],
		'BZ' => [
			'America/Belize',
		],
		'CA' => [
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
		],
		'CC' => [
			'Indian/Cocos',
		],
		'CD' => [
			'Africa/Kinshasa',
			'Africa/Lubumbashi',
		],
		'CF' => [
			'Africa/Bangui',
		],
		'CG' => [
			'Africa/Brazzaville',
		],
		'CH' => [
			'Europe/Zurich',
		],
		'CI' => [
			'Africa/Abidjan',
		],
		'CK' => [
			'Pacific/Rarotonga',
		],
		'CL' => [
			'America/Santiago',
			'America/Punta_Arenas',
			'Pacific/Easter',
		],
		'CM' => [
			'Africa/Douala',
		],
		'CN' => [
			'Asia/Shanghai',
			'Asia/Urumqi',
		],
		'CO' => [
			'America/Bogota',
		],
		'CR' => [
			'America/Costa_Rica',
		],
		'CU' => [
			'America/Havana',
		],
		'CV' => [
			'Atlantic/Cape_Verde',
		],
		'CW' => [
			'America/Curacao',
		],
		'CX' => [
			'Indian/Christmas',
		],
		'CY' => [
			'Asia/Nicosia',
			'Asia/Famagusta',
		],
		'CZ' => [
			'Europe/Prague',
		],
		'DE' => [
			'Europe/Berlin',
			'Europe/Busingen',
		],
		'DJ' => [
			'Africa/Djibouti',
		],
		'DK' => [
			'Europe/Copenhagen',
		],
		'DM' => [
			'America/Dominica',
		],
		'DO' => [
			'America/Santo_Domingo',
		],
		'DZ' => [
			'Africa/Algiers',
		],
		'EC' => [
			'America/Guayaquil',
			'Pacific/Galapagos',
		],
		'EE' => [
			'Europe/Tallinn',
		],
		'EG' => [
			'Africa/Cairo',
		],
		'EH' => [
			'Africa/El_Aaiun',
		],
		'ER' => [
			'Africa/Asmara',
		],
		'ES' => [
			'Europe/Madrid',
			'Atlantic/Canary',
			'Africa/Ceuta',
		],
		'ET' => [
			'Africa/Addis_Ababa',
		],
		'FI' => [
			'Europe/Helsinki',
		],
		'FJ' => [
			'Pacific/Fiji',
		],
		'FK' => [
			'Atlantic/Stanley',
		],
		'FM' => [
			'Pacific/Chuuk',
			'Pacific/Kosrae',
			'Pacific/Pohnpei',
		],
		'FO' => [
			'Atlantic/Faroe',
		],
		'FR' => [
			'Europe/Paris',
		],
		'GA' => [
			'Africa/Libreville',
		],
		'GB' => [
			'Europe/London',
		],
		'GD' => [
			'America/Grenada',
		],
		'GE' => [
			'Asia/Tbilisi',
		],
		'GF' => [
			'America/Cayenne',
		],
		'GG' => [
			'Europe/Guernsey',
		],
		'GH' => [
			'Africa/Accra',
		],
		'GI' => [
			'Europe/Gibraltar',
		],
		'GL' => [
			'America/Nuuk',
			'America/Thule',
			'America/Scoresbysund',
			'America/Danmarkshavn',
		],
		'GM' => [
			'Africa/Banjul',
		],
		'GN' => [
			'Africa/Conakry',
		],
		'GP' => [
			'America/Guadeloupe',
		],
		'GQ' => [
			'Africa/Malabo',
		],
		'GR' => [
			'Europe/Athens',
		],
		'GS' => [
			'Atlantic/South_Georgia',
		],
		'GT' => [
			'America/Guatemala',
		],
		'GU' => [
			'Pacific/Guam',
		],
		'GW' => [
			'Africa/Bissau',
		],
		'GY' => [
			'America/Guyana',
		],
		'HK' => [
			'Asia/Hong_Kong',
		],
		'HN' => [
			'America/Tegucigalpa',
		],
		'HR' => [
			'Europe/Zagreb',
		],
		'HT' => [
			'America/Port-au-Prince',
		],
		'HU' => [
			'Europe/Budapest',
		],
		'ID' => [
			'Asia/Jakarta',
			'Asia/Makassar',
			'Asia/Pontianak',
			'Asia/Jayapura',
		],
		'IE' => [
			'Europe/Dublin',
		],
		'IL' => [
			'Asia/Jerusalem',
		],
		'IM' => [
			'Europe/Isle_of_Man',
		],
		'IN' => [
			'Asia/Kolkata',
		],
		'IO' => [
			'Indian/Chagos',
		],
		'IQ' => [
			'Asia/Baghdad',
		],
		'IR' => [
			'Asia/Tehran',
		],
		'IS' => [
			'Atlantic/Reykjavik',
		],
		'IT' => [
			'Europe/Rome',
		],
		'JE' => [
			'Europe/Jersey',
		],
		'JM' => [
			'America/Jamaica',
		],
		'JO' => [
			'Asia/Amman',
		],
		'JP' => [
			'Asia/Tokyo',
		],
		'KE' => [
			'Africa/Nairobi',
		],
		'KG' => [
			'Asia/Bishkek',
		],
		'KH' => [
			'Asia/Phnom_Penh',
		],
		'KI' => [
			'Pacific/Tarawa',
			'Pacific/Kiritimati',
			'Pacific/Kanton',
			'Pacific/Enderbury',
		],
		'KM' => [
			'Indian/Comoro',
		],
		'KN' => [
			'America/St_Kitts',
		],
		'KP' => [
			'Asia/Pyongyang',
		],
		'KR' => [
			'Asia/Seoul',
		],
		'KW' => [
			'Asia/Kuwait',
		],
		'KY' => [
			'America/Cayman',
		],
		'KZ' => [
			'Asia/Almaty',
			'Asia/Aqtobe',
			'Asia/Atyrau',
			'Asia/Qostanay',
			'Asia/Qyzylorda',
			'Asia/Aqtau',
			'Asia/Oral',
		],
		'LA' => [
			'Asia/Vientiane',
		],
		'LB' => [
			'Asia/Beirut',
		],
		'LC' => [
			'America/St_Lucia',
		],
		'LI' => [
			'Europe/Vaduz',
		],
		'LK' => [
			'Asia/Colombo',
		],
		'LR' => [
			'Africa/Monrovia',
		],
		'LS' => [
			'Africa/Maseru',
		],
		'LT' => [
			'Europe/Vilnius',
		],
		'LU' => [
			'Europe/Luxembourg',
		],
		'LV' => [
			'Europe/Riga',
		],
		'LY' => [
			'Africa/Tripoli',
		],
		'MA' => [
			'Africa/Casablanca',
		],
		'MC' => [
			'Europe/Monaco',
		],
		'MD' => [
			'Europe/Chisinau',
		],
		'ME' => [
			'Europe/Podgorica',
		],
		'MF' => [
			'America/Marigot',
		],
		'MG' => [
			'Indian/Antananarivo',
		],
		'MH' => [
			'Pacific/Majuro',
			'Pacific/Kwajalein',
		],
		'MK' => [
			'Europe/Skopje',
		],
		'ML' => [
			'Africa/Bamako',
		],
		'MM' => [
			'Asia/Yangon',
		],
		'MN' => [
			'Asia/Ulaanbaatar',
			'Asia/Choibalsan',
			'Asia/Hovd',
		],
		'MO' => [
			'Asia/Macau',
		],
		'MP' => [
			'Pacific/Saipan',
		],
		'MQ' => [
			'America/Martinique',
		],
		'MR' => [
			'Africa/Nouakchott',
		],
		'MS' => [
			'America/Montserrat',
		],
		'MT' => [
			'Europe/Malta',
		],
		'MU' => [
			'Indian/Mauritius',
		],
		'MV' => [
			'Indian/Maldives',
		],
		'MW' => [
			'Africa/Blantyre',
		],
		'MX' => [
			'America/Mexico_City',
			'America/Tijuana',
			'America/Monterrey',
			'America/Ciudad_Juarez',
			'America/Chihuahua',
			'America/Merida',
			'America/Hermosillo',
			'America/Cancun',
			'America/Matamoros',
			'America/Mazatlan',
			'America/Bahia_Banderas',
			'America/Ojinaga',
		],
		'MY' => [
			'Asia/Kuala_Lumpur',
			'Asia/Kuching',
		],
		'MZ' => [
			'Africa/Maputo',
		],
		'NA' => [
			'Africa/Windhoek',
		],
		'NC' => [
			'Pacific/Noumea',
		],
		'NE' => [
			'Africa/Niamey',
		],
		'NF' => [
			'Pacific/Norfolk',
		],
		'NG' => [
			'Africa/Lagos',
		],
		'NI' => [
			'America/Managua',
		],
		'NL' => [
			'Europe/Amsterdam',
		],
		'NO' => [
			'Europe/Oslo',
		],
		'NP' => [
			'Asia/Kathmandu',
		],
		'NR' => [
			'Pacific/Nauru',
		],
		'NU' => [
			'Pacific/Niue',
		],
		'NZ' => [
			'Pacific/Auckland',
			'Pacific/Chatham',
		],
		'OM' => [
			'Asia/Muscat',
		],
		'PA' => [
			'America/Panama',
		],
		'PE' => [
			'America/Lima',
		],
		'PF' => [
			'Pacific/Tahiti',
			'Pacific/Marquesas',
			'Pacific/Gambier',
		],
		'PG' => [
			'Pacific/Port_Moresby',
			'Pacific/Bougainville',
		],
		'PH' => [
			'Asia/Manila',
		],
		'PK' => [
			'Asia/Karachi',
		],
		'PL' => [
			'Europe/Warsaw',
		],
		'PM' => [
			'America/Miquelon',
		],
		'PN' => [
			'Pacific/Pitcairn',
		],
		'PR' => [
			'America/Puerto_Rico',
		],
		'PS' => [
			'Asia/Gaza',
			'Asia/Hebron',
		],
		'PT' => [
			'Europe/Lisbon',
			'Atlantic/Madeira',
			'Atlantic/Azores',
		],
		'PW' => [
			'Pacific/Palau',
		],
		'PY' => [
			'America/Asuncion',
		],
		'QA' => [
			'Asia/Qatar',
		],
		'RE' => [
			'Indian/Reunion',
		],
		'RO' => [
			'Europe/Bucharest',
		],
		'RS' => [
			'Europe/Belgrade',
		],
		'RU' => [
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
		],
		'RW' => [
			'Africa/Kigali',
		],
		'SA' => [
			'Asia/Riyadh',
		],
		'SB' => [
			'Pacific/Guadalcanal',
		],
		'SC' => [
			'Indian/Mahe',
		],
		'SD' => [
			'Africa/Khartoum',
		],
		'SE' => [
			'Europe/Stockholm',
		],
		'SG' => [
			'Asia/Singapore',
		],
		'SH' => [
			'Atlantic/St_Helena',
		],
		'SI' => [
			'Europe/Ljubljana',
		],
		'SJ' => [
			'Arctic/Longyearbyen',
		],
		'SK' => [
			'Europe/Bratislava',
		],
		'SL' => [
			'Africa/Freetown',
		],
		'SM' => [
			'Europe/San_Marino',
		],
		'SN' => [
			'Africa/Dakar',
		],
		'SO' => [
			'Africa/Mogadishu',
		],
		'SR' => [
			'America/Paramaribo',
		],
		'SS' => [
			'Africa/Juba',
		],
		'ST' => [
			'Africa/Sao_Tome',
		],
		'SV' => [
			'America/El_Salvador',
		],
		'SX' => [
			'America/Lower_Princes',
		],
		'SY' => [
			'Asia/Damascus',
		],
		'SZ' => [
			'Africa/Mbabane',
		],
		'TC' => [
			'America/Grand_Turk',
		],
		'TD' => [
			'Africa/Ndjamena',
		],
		'TF' => [
			'Indian/Kerguelen',
		],
		'TG' => [
			'Africa/Lome',
		],
		'TH' => [
			'Asia/Bangkok',
		],
		'TJ' => [
			'Asia/Dushanbe',
		],
		'TK' => [
			'Pacific/Fakaofo',
		],
		'TL' => [
			'Asia/Dili',
		],
		'TM' => [
			'Asia/Ashgabat',
		],
		'TN' => [
			'Africa/Tunis',
		],
		'TO' => [
			'Pacific/Tongatapu',
		],
		'TR' => [
			'Europe/Istanbul',
		],
		'TT' => [
			'America/Port_of_Spain',
		],
		'TV' => [
			'Pacific/Funafuti',
		],
		'TW' => [
			'Asia/Taipei',
		],
		'TZ' => [
			'Africa/Dar_es_Salaam',
		],
		'UA' => [
			'Europe/Kyiv',
			'Europe/Zaporozhye',
			'Europe/Simferopol',
			'Europe/Uzhgorod',
		],
		'UG' => [
			'Africa/Kampala',
		],
		'UM' => [
			'Pacific/Midway',
			'Pacific/Wake',
		],
		'US' => [
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
		],
		'UY' => [
			'America/Montevideo',
		],
		'UZ' => [
			'Asia/Tashkent',
			'Asia/Samarkand',
		],
		'VA' => [
			'Europe/Vatican',
		],
		'VC' => [
			'America/St_Vincent',
		],
		'VE' => [
			'America/Caracas',
		],
		'VG' => [
			'America/Tortola',
		],
		'VI' => [
			'America/St_Thomas',
		],
		'VN' => [
			'Asia/Ho_Chi_Minh',
		],
		'VU' => [
			'Pacific/Efate',
		],
		'WF' => [
			'Pacific/Wallis',
		],
		'WS' => [
			'Pacific/Apia',
		],
		'YE' => [
			'Asia/Aden',
		],
		'YT' => [
			'Indian/Mayotte',
		],
		'ZA' => [
			'Africa/Johannesburg',
		],
		'ZM' => [
			'Africa/Lusaka',
		],
		'ZW' => [
			'Africa/Harare',
		],
	];

	/**
	 * @var array
	 *
	 * Time zone fallbacks to use when PHP has an outdated copy of the time zone
	 * database.
	 *
	 * 'ts' is the timestamp when the substitution first becomes valid.
	 * 'tzid' is the alternative time zone identifier to use.
	 */
	public static array $fallbacks = [
		/*
		 * 1. Simple renames.
		 *
		 * PHP_INT_MIN because these are valid for all dates.
		 */
		'Asia/Kolkata' => [
			[
				'ts' => PHP_INT_MIN,
				'tzid' => 'Asia/Calcutta',
			],
		],
		'Pacific/Chuuk' => [
			[
				'ts' => PHP_INT_MIN,
				'tzid' => 'Pacific/Truk',
			],
		],
		'Pacific/Kanton' => [
			[
				'ts' => PHP_INT_MIN,
				'tzid' => 'Pacific/Enderbury',
			],
		],
		'Pacific/Pohnpei' => [
			[
				'ts' => PHP_INT_MIN,
				'tzid' => 'Pacific/Ponape',
			],
		],
		'Asia/Yangon' => [
			[
				'ts' => PHP_INT_MIN,
				'tzid' => 'Asia/Rangoon',
			],
		],
		'America/Nuuk' => [
			[
				'ts' => PHP_INT_MIN,
				'tzid' => 'America/Godthab',
			],
		],
		'Europe/Busingen' => [
			[
				'ts' => PHP_INT_MIN,
				'tzid' => 'Europe/Zurich',
			],
		],
		'Europe/Kyiv' => [
			[
				'ts' => PHP_INT_MIN,
				'tzid' => 'Europe/Kiev',
			],
		],

		/*
		 * 2. Newly created time zones.
		 *
		 * The initial entry in many of the following zones is set to '' because
		 * the records go back to eras before the adoption of standardized time
		 * zones, which means no substitutes are possible then.
		 */

		// The same as Tasmania, except it stayed on DST all year in 2010.
		// Australia/Tasmania is an otherwise unused backward compatibility
		// link to Australia/Hobart, so we can borrow it here without conflict.
		'Antarctica/Macquarie' => [
			[
				'ts' => PHP_INT_MIN,
				'tzid' => 'Australia/Tasmania',
			],
			[
				'ts' => '2010-04-03T16:00:00+0000',
				'tzid' => 'Etc/GMT-11',
			],
			[
				'ts' => '2011-04-07T17:00:00+0000',
				'tzid' => 'Australia/Tasmania',
			],
		],

		// Added in version 2013a.
		'Asia/Khandyga' => [
			[
				'ts' => PHP_INT_MIN,
				'tzid' => '',
			],
			[
				'ts' => '1919-12-14T14:57:47+0000',
				'tzid' => 'Etc/GMT-8',
			],
			[
				'ts' => '1930-06-20T16:00:00+0000',
				'tzid' => 'Asia/Yakutsk',
			],
			[
				'ts' => '2003-12-31T15:00:00+0000',
				'tzid' => 'Asia/Vladivostok',
			],
			[
				'ts' => '2011-09-12T13:00:00+0000',
				'tzid' => 'Asia/Yakutsk',
			],
		],

		// Added in version 2013a.
		'Asia/Ust-Nera' => [
			[
				'ts' => PHP_INT_MIN,
				'tzid' => '',
			],
			[
				'ts' => '1919-12-14T14:27:06+0000',
				'tzid' => 'Etc/GMT-8',
			],
			[
				'ts' => '1930-06-20T16:00:00+0000',
				'tzid' => 'Asia/Yakutsk',
			],
			[
				'ts' => '1981-03-31T15:00:00+0000',
				'tzid' => 'Asia/Magadan',
			],
			[
				'ts' => '2011-09-12T12:00:00+0000',
				'tzid' => 'Asia/Vladivostok',
			],
		],

		// Created in version 2014b.
		// This place uses two hours for DST. No substitutes are possible.
		'Antarctica/Troll' => [
			[
				'ts' => PHP_INT_MIN,
				'tzid' => '',
			],
		],

		// Diverged from Asia/Yakustsk in version 2014f.
		'Asia/Chita' => [
			[
				'ts' => PHP_INT_MIN,
				'tzid' => '',
			],
			[
				'ts' => '1919-12-14T16:26:08+0000',
				'tzid' => 'Asia/Yakutsk',
			],
			[
				'ts' => '2014-10-25T16:00:00+0000',
				'tzid' => 'Etc/GMT-8',
			],
			[
				'ts' => '2016-03-26T18:00:00+0000',
				'tzid' => 'Asia/Yakutsk',
			],
		],

		// Diverged from Asia/Magadan in version 2014f.
		'Asia/Srednekolymsk' => [
			[
				'ts' => PHP_INT_MIN,
				'tzid' => '',
			],
			[
				'ts' => '1924-05-01T13:45:08+0000',
				'tzid' => 'Etc/GMT-10',
			],
			[
				'ts' => '1930-06-20T14:00:00+0000',
				'tzid' => 'Asia/Magadan',
			],
			[
				'ts' => '2014-10-25T14:00:00+0000',
				'tzid' => 'Etc/GMT-11',
			],
		],

		// Diverged from Pacific/Port_Moresby in version 2014i.
		'Pacific/Bougainville' => [
			[
				'ts' => PHP_INT_MIN,
				'tzid' => '',
			],
			// Pacific/Yap is an unused link to Pacific/Port_Moresby.
			[
				'ts' => '1879-12-31T14:11:20+0000',
				'tzid' => 'Pacific/Yap',
			],
			// Apparently this was different for a while in World War II.
			[
				'ts' => '1942-06-30T14:00:00+0000',
				'tzid' => 'Singapore',
			],
			[
				'ts' => '1945-08-20T15:00:00+0000',
				'tzid' => 'Pacific/Yap',
			],
			// For dates after divergence, it is the same as Pacific/Kosrae.
			// If this ever ceases to be true, add another entry.
			[
				'ts' => '2014-12-27T16:00:00+0000',
				'tzid' => 'Pacific/Kosrae',
			],
		],

		// Added in version 2015g.
		'America/Fort_Nelson' => [
			[
				'ts' => PHP_INT_MIN,
				'tzid' => '',
			],
			[
				'ts' => '1884-01-01T08:12:28+0000',
				'tzid' => 'Canada/Pacific',
			],
			[
				'ts' => '1946-01-01T08:00:00+0000',
				'tzid' => 'Etc/GMT+8',
			],
			[
				'ts' => '1947-01-01T08:00:00+0000',
				'tzid' => 'Canada/Pacific',
			],
			[
				'ts' => '2015-03-08T10:00:00+0000',
				'tzid' => 'MST',
			],
		],

		// Created in version 2016b.
		'Europe/Astrakhan' => [
			[
				'ts' => PHP_INT_MIN,
				'tzid' => '',
			],
			[
				'ts' => '1935-01-26T20:00:00+0000',
				'tzid' => 'Europe/Samara',
			],
			[
				'ts' => '1989-03-25T22:00:00+0000',
				'tzid' => 'Europe/Volgograd',
			],
			[
				'ts' => '2016-03-26T23:00:00+0000',
				'tzid' => 'Europe/Samara',
			],
		],

		// Created in version 2016b.
		'Europe/Ulyanovsk' => [
			[
				'ts' => PHP_INT_MIN,
				'tzid' => '',
			],
			[
				'ts' => '1935-01-26T20:00:00+0000',
				'tzid' => 'Europe/Samara',
			],
			[
				'ts' => '1989-03-25T22:00:00+0000',
				'tzid' => 'W-SU',
			],
			[
				'ts' => '2016-03-26T23:00:00+0000',
				'tzid' => 'Europe/Samara',
			],
		],

		// Created in version 2016b.
		'Asia/Barnaul' => [
			[
				'ts' => PHP_INT_MIN,
				'tzid' => '',
			],
			[
				'ts' => '1919-12-09T18:25:00+0000',
				'tzid' => 'Etc/GMT-6',
			],
			[
				'ts' => '1930-06-20T18:00:00+0000',
				'tzid' => 'Asia/Novokuznetsk',
			],
			[
				'ts' => '1995-05-27T16:00:00+0000',
				'tzid' => 'Asia/Novosibirsk',
			],
			[
				'ts' => '2016-03-26T20:00:00+0000',
				'tzid' => 'Asia/Novokuznetsk',
			],
		],

		// Created in version 2016b.
		'Asia/Tomsk' => [
			[
				'ts' => PHP_INT_MIN,
				'tzid' => '',
			],
			[
				'ts' => '1919-12-21T18:20:09+0000',
				'tzid' => 'Asia/Novosibirsk',
			],
			[
				'ts' => '1930-06-20T18:00:00+0000',
				'tzid' => 'Asia/Novokuznetsk',
			],
			[
				'ts' => '2002-04-30T19:00:00+0000',
				'tzid' => 'Asia/Novosibirsk',
			],
			[
				'ts' => '2016-05-28T20:00:00+0000',
				'tzid' => 'Asia/Novokuznetsk',
			],
		],

		// Created in version 2016d.
		'Europe/Kirov' => [
			[
				'ts' => PHP_INT_MIN,
				'tzid' => '',
			],
			[
				'ts' => '1935-01-26T20:00:00+0000',
				'tzid' => 'Europe/Samara',
			],
			[
				'ts' => '1989-03-25T22:00:00+0000',
				'tzid' => 'Europe/Volgograd',
			],
			[
				'ts' => '1992-03-28T22:00:00+0000',
				'tzid' => 'W-SU',
			],
		],

		// Diverged from Asia/Nicosia in version 2016i.
		'Asia/Famagusta' => [
			[
				'ts' => PHP_INT_MIN,
				'tzid' => '',
			],
			// Europe/Nicosia is an otherwise unused link to Asia/Nicosia.
			[
				'ts' => '1921-11-13T21:46:32+0000',
				'tzid' => 'Europe/Nicosia',
			],
			// Became same as Europe/Istanbul.
			// Turkey is an otherwise unused link to Europe/Istanbul.
			[
				'ts' => '2016-09-07T21:00:00+0000',
				'tzid' => 'Turkey',
			],
			// Became same as Asia/Nicosia again.
			[
				'ts' => '2017-10-29T01:00:00+0000',
				'tzid' => 'Europe/Nicosia',
			],
		],

		// Created in version 2016j.
		'Asia/Atyrau' => [
			[
				'ts' => PHP_INT_MIN,
				'tzid' => '',
			],
			[
				'ts' => '1924-05-01T20:32:16+0000',
				'tzid' => 'Etc/GMT-3',
			],
			[
				'ts' => '1930-06-20T21:00:00+0000',
				'tzid' => 'Asia/Aqtau',
			],
			[
				'ts' => '1981-09-30T19:00:00+0000',
				'tzid' => 'Asia/Aqtobe',
			],
			[
				'tz' => '1999-03-27T21:00:00+0000',
				'tzid' => 'Etc/GMT-5',
			],
		],

		// Diverged from Europe/Volgograd in version 2016j.
		'Europe/Saratov' => [
			[
				'ts' => PHP_INT_MIN,
				'tzid' => '',
			],
			[
				'ts' => '1935-01-26T20:00:00+0000',
				'tzid' => 'Europe/Samara',
			],
			[
				'ts' => '1988-03-26T22:00:00+0000',
				'tzid' => 'Europe/Volgograd',
			],
			[
				'ts' => '2016-12-03T23:00:00+0000',
				'tzid' => 'Europe/Samara',
			],
		],

		// Diverged from America/Santiago in version 2017a.
		'America/Punta_Arenas' => [
			[
				'ts' => PHP_INT_MIN,
				'tzid' => '',
			],
			// Chile/Continental is an otherwise unused link to America/Santiago.
			[
				'ts' => '1890-01-01T04:43:40+0000',
				'tzid' => 'Chile/Continental',
			],
			[
				'ts' => '1942-08-01T05:00:00+0000',
				'tzid' => 'Etc/GMT+4',
			],
			[
				'ts' => '1946-08-29T04:00:00+0000',
				'tzid' => 'Chile/Continental',
			],
			// America/Mendoza is an otherwise unused link to America/Argentina/Mendoza.
			[
				'ts' => '2016-12-04T03:00:00+0000',
				'tzid' => 'America/Mendoza',
			],
		],

		// Diverged from Asia/Qyzylorda in version 2018h.
		'Asia/Qostanay' => [
			[
				'ts' => PHP_INT_MIN,
				'tzid' => '',
			],
			[
				'ts' => '1924-05-01T19:45:32+0000',
				'tzid' => 'Asia/Qyzylorda',
			],
			[
				'ts' => '1930-06-20T20:00:00+0000',
				'tzid' => 'Asia/Aqtobe',
			],
			[
				'ts' => '2004-10-30T21:00:00+0000',
				'tzid' => 'Asia/Almaty',
			],
		],

		// Diverged from America/Ojinaga in version 2022g.
		'America/Ciudad_Juarez' => [
			[
				'ts' => PHP_INT_MIN,
				'tzid' => '',
			],
			[
				'ts' => '1922-01-01T07:00:00+0000',
				'tzid' => 'America/Ojinaga',
			],
			[
				'ts' => '2022-11-30T06:00:00+0000',
				'tzid' => 'America/Denver',
			],
		],
	];

	/****************************
	 * Internal static properties
	 ****************************/

	/**
	 * @var array
	 *
	 * Multidimensional array containing compiled lists of selectable time zones
	 * for any given value of $when.
	 *
	 * Built by self::list()
	 */
	protected static $timezones_when = [];

	/**
	 * @var array
	 *
	 * Time zone identifiers sorted into a prioritized list based on the country
	 * codes in Config::$modSettings['timezone_priority_countries'].
	 *
	 * Built by self::prioritizeTzids()
	 */
	protected static array $prioritized_tzids = [];

	/**
	 * @var array
	 *
	 * Multidimensional array containing start and end timestamps for any given
	 * value of $when.
	 *
	 * Built by self::getTimeRange()
	 */
	protected static array $ranges = [];

	/**
	 * @var array
	 *
	 * List of time zone transitions for all "meta-zones" starting from a given
	 * value of $when until one year later.
	 *
	 * Built by self::buildMetaZoneTransitions()
	 */
	protected static array $metazone_transitions = [];

	/****************
	 * Public methods
	 ****************/

	/**
	 * Returns the localized name of this time zone's location.
	 *
	 * This method typically just returns the $txt string for this time zone.
	 * If there is no $txt string, guesses based on the time zone's raw name.
	 *
	 * @return string Localized name of this time zone's location.
	 */
	public function getLabel(): string
	{
		Lang::load('Timezones');

		if (!empty(Lang::$txt[$this->getName()])) {
			return Lang::$txt[$this->getName()];
		}

		// If there's no $txt string, just guess based on the tzid's name.
		$tzid_parts = explode('/', $this->getName());

		return str_replace(['St_', '_'], ['St. ', ' '], array_pop($tzid_parts));
	}

	/**
	 * Returns this time zone's abbreviations (if any).
	 *
	 * @param string $when The date/time we are interested in.
	 *    May be a Unix timestamp or any string that strtotime() can understand.
	 *    Defaults to 'now'.
	 * @return array The time zone's abbreviations.
	 */
	public function getAbbreviations(int|string $when = 'now'): array
	{
		list($when, $later) = self::getTimeRange($when);

		$abbrs = [];

		foreach ($this->getTransitions($when, $later) as $transition) {
			$abbrs[] = $transition['abbr'];
		}

		return $abbrs;
	}

	/**
	 * Returns the "meta-zone" for this time zone at the given timestamp.
	 *
	 * @param string $when The date/time we are interested in.
	 *    May be a Unix timestamp or any string that strtotime() can understand.
	 *    Defaults to 'now'.
	 * @return string The $tztxt variable for this time zone's "meta-zone".
	 */
	public function getMetaZone(int|string $when = 'now'): string
	{
		list($when, $later) = self::getTimeRange($when);

		if (empty(self::$metazone_transitions[$when])) {
			self::buildMetaZoneTransitions($when);
		}

		$tzkey = serialize($this->getTransitions($when, $later));

		if (isset(self::$metazone_transitions[$when][$tzkey])) {
			return self::$metazone_transitions[$when][$tzkey];
		}

		// Doesn't match any existing metazone. Can we build a custom one?
		$tzgeo = $this->getLocation();
		$country_tzids = self::getSortedTzidsForCountry($tzgeo['country_code']);

		if (count($country_tzids) === 1) {
			Lang::load('Timezones');

			Lang::$tztxt[$tzgeo['country_code']] = sprintf(Lang::$tztxt['generic_timezone'], Lang::$txt['iso3166'][$tzgeo['country_code']], '%1$s');

			return $tzgeo['country_code'];
		}

		return '';
	}

	/**
	 * Returns the "meta-zone" label for this time zone at the given timestamp.
	 *
	 * @param string $when The date/time we are interested in.
	 *    May be a Unix timestamp or any string that strtotime() can understand.
	 *    Defaults to 'now'.
	 * @return string The $tztxt value for this time zone's "meta-zone".
	 */
	public function getMetaZoneLabel(int|string $when = 'now'): string
	{
		Lang::load('Timezones');

		$metazone = $this->getMetaZone($when);

		return Lang::$tztxt[$metazone] ?? $metazone;
	}

	/**
	 * Returns whether this time zone uses Daylight Saving Time.
	 *
	 * @param int|string $when The earliest date/time we are interested in.
	 *    May be a Unix timestamp or any string that strtotime() can understand.
	 *    Defaults to 'now'.
	 * @return int One of this class's three DST_* constants.
	 */
	public function getDstType(int|string $when = 'now'): int
	{
		list($when, $later) = self::getTimeRange($when);

		$tzinfo = $this->getTransitions($when, $later);

		if (count($tzinfo) > 1) {
			return self::DST_SWITCHES;
		}

		if ($tzinfo[0]['isdst']) {
			return self::DST_ALWAYS;
		}

		return self::DST_NEVER;
	}

	/**
	 * Returns the Standard Time offset from GMT, ignoring any Daylight Saving
	 * Time that might be in effect.
	 *
	 * @param int|string $when The earliest date/time we are interested in.
	 *    May be a Unix timestamp or any string that strtotime() can understand.
	 *    Defaults to 'now'.
	 * @return int This time zone's Standard Time offset from GMT.
	 */
	public function getStandardOffset(int|string $when = 'now'): int
	{
		list($when, $later) = self::getTimeRange($when);

		$tzinfo = $this->getTransitions($when, $later);

		foreach ($tzinfo as $transition) {
			if (!$transition['isdst']) {
				return $transition['offset'];
			}
		}

		// If it uses DST all the time, just return the first offset.
		return $tzinfo[0]['offset'];
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Get a list of time zones.
	 *
	 * @param int|string $when The date/time for which to calculate the time
	 *    zone values. May be a Unix timestamp or any string that strtotime()
	 *    can understand. Defaults to 'now'.
	 * @return array An array of time zone identifiers and label text.
	 */
	public static function list(int|string $when = 'now'): array
	{
		list($when, $later) = self::getTimeRange($when);

		// No point doing this over if we already did it once.
		if (isset(self::$timezones_when[$when])) {
			return self::$timezones_when[$when];
		}

		// Load up any custom time zone descriptions we might have
		Lang::load('Timezones');

		self::buildMetaZoneTransitions($when);

		// Should we put time zones from certain countries at the top of the list?
		self::prioritizeTzids();

		// Idea here is to get exactly one representative identifier for each and every unique set of time zone rules.
		$zones = [];
		$dst_types = [];
		$labels = [];
		$offsets = [];

		foreach (self::$prioritized_tzids as $priority_level => $tzids) {
			foreach ($tzids as $tzid) {
				// We don't want UTC right now.
				if ($tzid == 'UTC') {
					continue;
				}

				$tz = new self($tzid);

				$tzinfo = $tz->getTransitions($when, $later);
				$tzkey = serialize($tzinfo);

				// Don't overwrite our preferred tzids
				if (empty($zones[$tzkey]['tzid'])) {
					$zones[$tzkey]['tzid'] = $tzid;
					$zones[$tzkey]['dst_type'] = $tz->getDstType();
					$zones[$tzkey]['abbrs'] = $tz->getAbbreviations($when);

					$metazone_label = $tz->getMetaZoneLabel();

					if (!empty($metazone_label)) {
						$zones[$tzkey]['metazone'] = $metazone_label;
					}
				}

				$zones[$tzkey]['locations'][] = $tz->getLabel();

				// Keep track of the current and standard offsets for this tzid.
				$offsets[$tzkey] = $tzinfo[0]['offset'];
				$std_offsets[$tzkey] = $tz->getStandardOffset($when);

				switch ($tz->getDstType()) {
					case self::DST_SWITCHES:
						$dst_types[$tzkey] = 'c';
						break;

					case self::DST_ALWAYS:
						$dst_types[$tzkey] = 't';
						break;

					default:
						$dst_types[$tzkey] = 'f';
						break;
				}

				$labels[$tzkey] = $metazone_label;
			}
		}

		// Sort by current offset, then standard offset, then DST type, then label.
		array_multisort($offsets, SORT_DESC, SORT_NUMERIC, $std_offsets, SORT_DESC, SORT_NUMERIC, $dst_types, SORT_ASC, $labels, SORT_ASC, $zones);

		$date_when = date_create('@' . $when);

		// Build the final array of formatted values
		$priority_timezones = [];
		$timezones = [];

		foreach ($zones as $tzkey => $tzvalue) {
			date_timezone_set($date_when, timezone_open($tzvalue['tzid']));

			// Use the human friendly time zone name, if there is one.
			$desc = '';

			if (!empty($tzvalue['metazone'])) {
				switch ($tzvalue['dst_type']) {
					case 0:
						$desc = sprintf($tzvalue['metazone'], Lang::$tztxt['daylight_saving_time_false']);
						break;

					case 1:
						$desc = sprintf($tzvalue['metazone'], '');
						break;

					case 2:
						$desc = sprintf($tzvalue['metazone'], Lang::$tztxt['daylight_saving_time_true']);
						break;
				}
			}
			// Otherwise, use the list of locations (max 5, so things don't get silly)
			else {
				$desc = implode(', ', array_slice(array_unique($tzvalue['locations']), 0, 5)) . (count($tzvalue['locations']) > 5 ? ', ' . Lang::$txt['etc'] : '');
			}

			// We don't want abbreviations like '+03' or '-11'.
			$abbrs = array_filter(
				$tzvalue['abbrs'],
				function ($abbr) {
					return !strspn($abbr, '+-');
				},
			);
			$abbrs = count($abbrs) == count($tzvalue['abbrs']) ? array_unique($abbrs) : [];

			// Show the UTC offset and abbreviation(s).
			$desc = '[UTC' . date_format($date_when, 'P') . '] - ' . str_replace('  ', ' ', $desc) . (!empty($abbrs) ? ' (' . implode('/', $abbrs) . ')' : '');

			if (in_array($tzvalue['tzid'], self::$prioritized_tzids['high'])) {
				$priority_timezones[$tzvalue['tzid']] = $desc;
			} else {
				$timezones[$tzvalue['tzid']] = $desc;
			}
		}

		if (!empty($priority_timezones)) {
			$priority_timezones[] = '-----';
		}

		$timezones = array_merge(
			$priority_timezones,
			['UTC' => 'UTC' . (!empty(Lang::$tztxt['UTC']) ? ' - ' . Lang::$tztxt['UTC'] : ''), '-----'],
			$timezones,
		);

		self::$timezones_when[$when] = $timezones;

		return self::$timezones_when[$when];
	}

	/**
	 * Returns an array that instructs SMF how to map specific time zones
	 * (e.g. "America/Denver") onto the user-friendly "meta-zone" labels that
	 * most people think of as time zones (e.g. "Mountain Time").
	 *
	 * @param int|string $when The date/time used to determine fallback values.
	 *    May be a Unix timestamp or any string that strtotime() can understand.
	 *    Defaults to 'now'.
	 * @return array An array relating time zones to "meta-zones"
	 */
	public static function getTzidMetazones(int|string $when = 'now'): array
	{
		Lang::load('Timezones');

		list($when, $later) = self::getTimeRange($when);

		IntegrationHook::call('integrate_metazones', [&self::$metazones, $when]);

		// Fallbacks in case the server has an old version of the TZDB.
		$tzid_fallbacks = self::getTzidFallbacks(array_keys(self::$metazones), $when);

		foreach ($tzid_fallbacks as $orig_tzid => $alt_tzid) {
			// Skip any that are unchanged.
			if ($orig_tzid == $alt_tzid) {
				continue;
			}

			// Use fallback where possible.
			if (!empty($alt_tzid) && empty(self::$metazones[$alt_tzid])) {
				self::$metazones[$alt_tzid] = self::$metazones[$orig_tzid];
				Lang::$txt[$alt_tzid] = Lang::$txt[$orig_tzid];
			}

			// Either way, get rid of the unknown time zone.
			unset(self::$metazones[$orig_tzid]);
		}

		return self::$metazones;
	}

	/**
	 * Returns an array of all the time zones in a country, ranked according
	 * to population and/or political significance.
	 *
	 * @param string $country_code The two-character ISO-3166 code for a country.
	 * @param int|string $when The date/time used to determine fallback values.
	 *    May be a Unix timestamp or any string that strtotime() can understand.
	 *    Defaults to 'now'.
	 * @return array An array relating time zones to "meta-zones"
	 */
	public static function getSortedTzidsForCountry(string $country_code, int|string $when = 'now'): array
	{
		static $country_tzids = [];

		list($when, $later) = self::getTimeRange($when);

		// Just in case...
		$country_code = strtoupper(trim($country_code));

		// Avoid unnecessary repetition.
		if (!isset($country_tzids[$country_code])) {
			IntegrationHook::call('integrate_country_timezones', [&self::$sorted_tzids, $country_code, $when]);

			$country_tzids[$country_code] = self::$sorted_tzids[$country_code] ?? [];

			// If something goes wrong, we want an empty array, not false.
			$recognized_country_tzids = array_filter((array) @timezone_identifiers_list(\DateTimeZone::PER_COUNTRY, $country_code));

			// Make sure that no time zones are missing.
			$country_tzids[$country_code] = array_unique(array_merge($country_tzids[$country_code], array_intersect($recognized_country_tzids, timezone_identifiers_list())));

			// Get fallbacks where necessary.
			$country_tzids[$country_code] = array_unique(array_values(self::getTzidFallbacks($country_tzids[$country_code], $when)));

			// Filter out any time zones that are still undefined.
			$country_tzids[$country_code] = array_intersect(array_filter($country_tzids[$country_code]), timezone_identifiers_list(\DateTimeZone::ALL_WITH_BC));
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
	 * only need to cover any that are used in self::$metazones.
	 *
	 * To find the date & time when a new time zone comes into effect, check
	 * the TZDB changelog at https://data.iana.org/time-zones/tzdb/NEWS
	 *
	 * @param array $tzids The time zone identifiers to check.
	 * @param int|string $when The date/time used to determine substitute values.
	 *    May be a Unix timestamp or any string that strtotime() can understand.
	 *    Defaults to 'now'.
	 * @return array Substitute values for any missing time zone identifiers.
	 */
	public static function getTzidFallbacks(array $tzids, int|string $when = 'now'): array
	{
		$tzids = (array) $tzids;

		list($when, $later) = self::getTimeRange($when);

		$missing = array_diff($tzids, timezone_identifiers_list(\DateTimeZone::ALL_WITH_BC));

		IntegrationHook::call('integrate_timezone_fallbacks', [&self::$fallbacks, &$missing, $tzids, $when]);

		$replacements = [];

		foreach ($tzids as $tzid) {
			// Not missing.
			if (!in_array($tzid, $missing)) {
				$replacements[$tzid] = $tzid;
			}
			// Missing and we have no fallback.
			elseif (empty(self::$fallbacks[$tzid])) {
				$replacements[$tzid] = '';
			}
			// Missing, but we have a fallback.
			else {
				foreach (self::$fallbacks[$tzid] as &$alt) {
					$alt['ts'] = is_int($alt['ts']) ? $alt['ts'] : strtotime($alt['ts']);
				}

				usort(self::$fallbacks[$tzid], fn ($a, $b) => $a['ts'] > $b['ts']);

				foreach (self::$fallbacks[$tzid] as $alt) {
					if ($when < $alt['ts']) {
						break;
					}

					$replacements[$tzid] = $alt['tzid'];
				}

				// Replacement is already in use.
				if (in_array($alt['tzid'], $replacements) || (in_array($alt['tzid'], $tzids) && strpos($alt['tzid'], 'Etc/') === false)) {
					$replacements[$tzid] = '';
				}

				if (empty($replacements[$tzid])) {
					$replacements[$tzid] = '';
				}
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
	public static function validateIsoCountryCodes(array|string $country_codes, bool $as_csv = false): array|string
	{
		if (is_string($country_codes)) {
			$country_codes = explode(',', $country_codes);
		} else {
			$country_codes = array_map('strval', (array) $country_codes);
		}

		foreach ($country_codes as $key => $country_code) {
			$country_code = strtoupper(trim($country_code));

			$country_tzids = strlen($country_code) !== 2 ? null : @timezone_identifiers_list(\DateTimeZone::PER_COUNTRY, $country_code);

			$country_codes[$key] = empty($country_tzids) ? null : $country_code;
		}

		$country_codes = array_filter($country_codes);

		if (!empty($as_csv)) {
			$country_codes = implode(',', $country_codes);
		}

		return $country_codes;
	}

	/*************************
	 * Internal static methods
	 *************************/

	/**
	 * Given a start time in any format that strtotime can understand, gets the
	 * Unix timestamps for a date range starting then and ending one year later.
	 *
	 * @param string $when The date/time used to determine substitute values.
	 *    May be a Unix timestamp or any string that strtotime() can understand.
	 *    Defaults to 'now'.
	 * @return array The start and end timestamps, in that order.
	 */
	protected static function getTimeRange(int|string $when = 'now'): array
	{
		if (isset(self::$ranges[$when])) {
			return self::$ranges[$when];
		}

		// Parseable datetime string?
		if (is_int($timestamp = strtotime($when))) {
			$start = $timestamp;
		}
		// A Unix timestamp?
		elseif (is_numeric($when)) {
			$start = intval($when);
		}
		// Invalid value? Just get current Unix timestamp.
		else {
			$start = time();
		}

		self::$ranges[$when] = [$start, strtotime('@' . $start . ' + 1 year')];

		return self::$ranges[$when];
	}

	/**
	 * Sorts time zone identifiers into a prioritized list based on the country
	 * codes in Config::$modSettings['timezone_priority_countries'].
	 *
	 * Result is saved in self::$prioritized_tzids.
	 */
	protected static function prioritizeTzids(): void
	{
		// No need to do this twice.
		if (!empty(self::$prioritized_tzids)) {
			return;
		}

		// Should we put time zones from certain countries at the top of the list?
		$priority_countries = !empty(Config::$modSettings['timezone_priority_countries']) ? explode(',', Config::$modSettings['timezone_priority_countries']) : [];

		$high_priority_tzids = [];

		foreach ($priority_countries as $country) {
			$country_tzids = self::getSortedTzidsForCountry($country);

			if (!empty($country_tzids)) {
				$high_priority_tzids = array_merge($high_priority_tzids, $country_tzids);
			}
		}

		// Antarctic research stations should be listed last, unless you're running a penguin forum
		$low_priority_tzids = !in_array('AQ', $priority_countries) ? timezone_identifiers_list(parent::ANTARCTICA) : [];

		$normal_priority_tzids = array_diff(array_unique(array_merge(array_keys(self::getTzidMetazones()), timezone_identifiers_list())), $high_priority_tzids, $low_priority_tzids);

		// Put them in order of importance.
		self::$prioritized_tzids = ['high' => $high_priority_tzids, 'normal' => $normal_priority_tzids, 'low' => $low_priority_tzids];
	}

	/**
	 * Builds a list of time zone transitions for all "meta-zones" starting from
	 * $when until one year later.
	 *
	 * @param string $when The date/time used to determine substitute values.
	 *    May be a Unix timestamp or any string that strtotime() can understand.
	 *    Defaults to 'now'.
	 */
	protected static function buildMetaZoneTransitions(int|string $when = 'now'): void
	{
		list($when, $later) = self::getTimeRange($when);

		self::getTzidMetazones($when);

		foreach (self::$metazones as $tzid => $label) {
			$tz = @timezone_open($tzid);

			if ($tz == null) {
				continue;
			}

			self::$metazone_transitions[$when][serialize($tz->getTransitions($when, $later))] = $label;
		}
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\\TimeZone::exportStatic')) {
	TimeZone::exportStatic();
}

?>