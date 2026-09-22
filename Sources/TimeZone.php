<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2026 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 5-dev
 */

declare(strict_types=1);

namespace SMF;

/**
 * This class provides some methods to simplify working with time zones.
 */
class TimeZone extends \DateTimeZone
{
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

	/**
	 * @var array
	 *
	 * Links deprecated time zone identifiers to their canonical equivalents.
	 *
	 * Developers: Do not update the data in this array manually. Instead,
	 * run "php -f other/update_timezones.php" on the command line.
	 */
	public const CANONICAL_LINKS = [
		'Africa/Accra' => 'Africa/Abidjan',
		'Africa/Addis_Ababa' => 'Africa/Nairobi',
		'Africa/Asmara' => 'Africa/Nairobi',
		'Africa/Asmera' => 'Africa/Nairobi',
		'Africa/Bamako' => 'Africa/Abidjan',
		'Africa/Bangui' => 'Africa/Lagos',
		'Africa/Banjul' => 'Africa/Abidjan',
		'Africa/Blantyre' => 'Africa/Maputo',
		'Africa/Brazzaville' => 'Africa/Lagos',
		'Africa/Bujumbura' => 'Africa/Maputo',
		'Africa/Conakry' => 'Africa/Abidjan',
		'Africa/Dakar' => 'Africa/Abidjan',
		'Africa/Dar_es_Salaam' => 'Africa/Nairobi',
		'Africa/Djibouti' => 'Africa/Nairobi',
		'Africa/Douala' => 'Africa/Lagos',
		'Africa/Freetown' => 'Africa/Abidjan',
		'Africa/Gaborone' => 'Africa/Maputo',
		'Africa/Harare' => 'Africa/Maputo',
		'Africa/Kampala' => 'Africa/Nairobi',
		'Africa/Kigali' => 'Africa/Maputo',
		'Africa/Kinshasa' => 'Africa/Lagos',
		'Africa/Libreville' => 'Africa/Lagos',
		'Africa/Lome' => 'Africa/Abidjan',
		'Africa/Luanda' => 'Africa/Lagos',
		'Africa/Lubumbashi' => 'Africa/Maputo',
		'Africa/Lusaka' => 'Africa/Maputo',
		'Africa/Malabo' => 'Africa/Lagos',
		'Africa/Maseru' => 'Africa/Johannesburg',
		'Africa/Mbabane' => 'Africa/Johannesburg',
		'Africa/Mogadishu' => 'Africa/Nairobi',
		'Africa/Niamey' => 'Africa/Lagos',
		'Africa/Nouakchott' => 'Africa/Abidjan',
		'Africa/Ouagadougou' => 'Africa/Abidjan',
		'Africa/Porto-Novo' => 'Africa/Lagos',
		'Africa/Timbuktu' => 'Africa/Abidjan',
		'America/Anguilla' => 'America/Puerto_Rico',
		'America/Antigua' => 'America/Puerto_Rico',
		'America/Argentina/ComodRivadavia' => 'America/Argentina/Catamarca',
		'America/Aruba' => 'America/Puerto_Rico',
		'America/Atikokan' => 'America/Panama',
		'America/Atka' => 'America/Adak',
		'America/Blanc-Sablon' => 'America/Puerto_Rico',
		'America/Buenos_Aires' => 'America/Argentina/Buenos_Aires',
		'America/Catamarca' => 'America/Argentina/Catamarca',
		'America/Cayman' => 'America/Panama',
		'America/Coral_Harbour' => 'America/Panama',
		'America/Cordoba' => 'America/Argentina/Cordoba',
		'America/Creston' => 'America/Phoenix',
		'America/Curacao' => 'America/Puerto_Rico',
		'America/Dominica' => 'America/Puerto_Rico',
		'America/Ensenada' => 'America/Tijuana',
		'America/Fort_Wayne' => 'America/Indiana/Indianapolis',
		'America/Godthab' => 'America/Nuuk',
		'America/Grenada' => 'America/Puerto_Rico',
		'America/Guadeloupe' => 'America/Puerto_Rico',
		'America/Indianapolis' => 'America/Indiana/Indianapolis',
		'America/Jujuy' => 'America/Argentina/Jujuy',
		'America/Knox_IN' => 'America/Indiana/Knox',
		'America/Kralendijk' => 'America/Puerto_Rico',
		'America/Louisville' => 'America/Kentucky/Louisville',
		'America/Lower_Princes' => 'America/Puerto_Rico',
		'America/Marigot' => 'America/Puerto_Rico',
		'America/Mendoza' => 'America/Argentina/Mendoza',
		'America/Montreal' => 'America/Toronto',
		'America/Montserrat' => 'America/Puerto_Rico',
		'America/Nassau' => 'America/Toronto',
		'America/Nipigon' => 'America/Toronto',
		'America/Pangnirtung' => 'America/Iqaluit',
		'America/Port_of_Spain' => 'America/Puerto_Rico',
		'America/Porto_Acre' => 'America/Rio_Branco',
		'America/Rainy_River' => 'America/Winnipeg',
		'America/Rosario' => 'America/Argentina/Cordoba',
		'America/Santa_Isabel' => 'America/Tijuana',
		'America/Shiprock' => 'America/Denver',
		'America/St_Barthelemy' => 'America/Puerto_Rico',
		'America/St_Kitts' => 'America/Puerto_Rico',
		'America/St_Lucia' => 'America/Puerto_Rico',
		'America/St_Thomas' => 'America/Puerto_Rico',
		'America/St_Vincent' => 'America/Puerto_Rico',
		'America/Thunder_Bay' => 'America/Toronto',
		'America/Tortola' => 'America/Puerto_Rico',
		'America/Virgin' => 'America/Puerto_Rico',
		'America/Yellowknife' => 'America/Edmonton',
		'Antarctica/DumontDUrville' => 'Pacific/Port_Moresby',
		'Antarctica/McMurdo' => 'Pacific/Auckland',
		'Antarctica/South_Pole' => 'Pacific/Auckland',
		'Antarctica/Syowa' => 'Asia/Riyadh',
		'Arctic/Longyearbyen' => 'Europe/Berlin',
		'Asia/Aden' => 'Asia/Riyadh',
		'Asia/Ashkhabad' => 'Asia/Ashgabat',
		'Asia/Bahrain' => 'Asia/Qatar',
		'Asia/Brunei' => 'Asia/Kuching',
		'Asia/Calcutta' => 'Asia/Kolkata',
		'Asia/Choibalsan' => 'Asia/Ulaanbaatar',
		'Asia/Chongqing' => 'Asia/Shanghai',
		'Asia/Chungking' => 'Asia/Shanghai',
		'Asia/Dacca' => 'Asia/Dhaka',
		'Asia/Harbin' => 'Asia/Shanghai',
		'Asia/Istanbul' => 'Europe/Istanbul',
		'Asia/Kashgar' => 'Asia/Urumqi',
		'Asia/Katmandu' => 'Asia/Kathmandu',
		'Asia/Kuala_Lumpur' => 'Asia/Singapore',
		'Asia/Kuwait' => 'Asia/Riyadh',
		'Asia/Macao' => 'Asia/Macau',
		'Asia/Muscat' => 'Asia/Dubai',
		'Asia/Phnom_Penh' => 'Asia/Bangkok',
		'Asia/Rangoon' => 'Asia/Yangon',
		'Asia/Saigon' => 'Asia/Ho_Chi_Minh',
		'Asia/Tel_Aviv' => 'Asia/Jerusalem',
		'Asia/Thimbu' => 'Asia/Thimphu',
		'Asia/Ujung_Pandang' => 'Asia/Makassar',
		'Asia/Ulan_Bator' => 'Asia/Ulaanbaatar',
		'Asia/Vientiane' => 'Asia/Bangkok',
		'Atlantic/Faeroe' => 'Atlantic/Faroe',
		'Atlantic/Jan_Mayen' => 'Europe/Berlin',
		'Atlantic/Reykjavik' => 'Africa/Abidjan',
		'Atlantic/St_Helena' => 'Africa/Abidjan',
		'Australia/ACT' => 'Australia/Sydney',
		'Australia/Canberra' => 'Australia/Sydney',
		'Australia/Currie' => 'Australia/Hobart',
		'Australia/LHI' => 'Australia/Lord_Howe',
		'Australia/NSW' => 'Australia/Sydney',
		'Australia/North' => 'Australia/Darwin',
		'Australia/Queensland' => 'Australia/Brisbane',
		'Australia/South' => 'Australia/Adelaide',
		'Australia/Tasmania' => 'Australia/Hobart',
		'Australia/Victoria' => 'Australia/Melbourne',
		'Australia/West' => 'Australia/Perth',
		'Australia/Yancowinna' => 'Australia/Broken_Hill',
		'Brazil/Acre' => 'America/Rio_Branco',
		'Brazil/DeNoronha' => 'America/Noronha',
		'Brazil/East' => 'America/Sao_Paulo',
		'Brazil/West' => 'America/Manaus',
		'CET' => 'Europe/Brussels',
		'Canada/Atlantic' => 'America/Halifax',
		'Canada/Central' => 'America/Winnipeg',
		'Canada/Eastern' => 'America/Toronto',
		'Canada/Mountain' => 'America/Edmonton',
		'Canada/Newfoundland' => 'America/St_Johns',
		'Canada/Pacific' => 'America/Vancouver',
		'Canada/Saskatchewan' => 'America/Regina',
		'Canada/Yukon' => 'America/Whitehorse',
		'Chile/Continental' => 'America/Santiago',
		'Chile/EasterIsland' => 'Pacific/Easter',
		'Cuba' => 'America/Havana',
		'EET' => 'Europe/Athens',
		'EST' => 'America/Panama',
		'Egypt' => 'Africa/Cairo',
		'Eire' => 'Europe/Dublin',
		'Etc/GMT+0' => 'Etc/GMT',
		'Etc/GMT-0' => 'Etc/GMT',
		'Etc/GMT0' => 'Etc/GMT',
		'Etc/Greenwich' => 'Etc/GMT',
		'Etc/UCT' => 'Etc/UTC',
		'Etc/Universal' => 'Etc/UTC',
		'Etc/Zulu' => 'Etc/UTC',
		'Europe/Amsterdam' => 'Europe/Brussels',
		'Europe/Belfast' => 'Europe/London',
		'Europe/Bratislava' => 'Europe/Prague',
		'Europe/Busingen' => 'Europe/Zurich',
		'Europe/Copenhagen' => 'Europe/Berlin',
		'Europe/Guernsey' => 'Europe/London',
		'Europe/Isle_of_Man' => 'Europe/London',
		'Europe/Jersey' => 'Europe/London',
		'Europe/Kiev' => 'Europe/Kyiv',
		'Europe/Ljubljana' => 'Europe/Belgrade',
		'Europe/Luxembourg' => 'Europe/Brussels',
		'Europe/Mariehamn' => 'Europe/Helsinki',
		'Europe/Monaco' => 'Europe/Paris',
		'Europe/Nicosia' => 'Asia/Nicosia',
		'Europe/Oslo' => 'Europe/Berlin',
		'Europe/Podgorica' => 'Europe/Belgrade',
		'Europe/San_Marino' => 'Europe/Rome',
		'Europe/Sarajevo' => 'Europe/Belgrade',
		'Europe/Skopje' => 'Europe/Belgrade',
		'Europe/Stockholm' => 'Europe/Berlin',
		'Europe/Tiraspol' => 'Europe/Chisinau',
		'Europe/Uzhgorod' => 'Europe/Kyiv',
		'Europe/Vaduz' => 'Europe/Zurich',
		'Europe/Vatican' => 'Europe/Rome',
		'Europe/Zagreb' => 'Europe/Belgrade',
		'Europe/Zaporozhye' => 'Europe/Kyiv',
		'GB' => 'Europe/London',
		'GB-Eire' => 'Europe/London',
		'GMT' => 'Etc/GMT',
		'GMT+0' => 'Etc/GMT',
		'GMT-0' => 'Etc/GMT',
		'GMT0' => 'Etc/GMT',
		'Greenwich' => 'Etc/GMT',
		'HST' => 'Pacific/Honolulu',
		'Hongkong' => 'Asia/Hong_Kong',
		'Iceland' => 'Africa/Abidjan',
		'Indian/Antananarivo' => 'Africa/Nairobi',
		'Indian/Christmas' => 'Asia/Bangkok',
		'Indian/Cocos' => 'Asia/Yangon',
		'Indian/Comoro' => 'Africa/Nairobi',
		'Indian/Kerguelen' => 'Indian/Maldives',
		'Indian/Mahe' => 'Asia/Dubai',
		'Indian/Mayotte' => 'Africa/Nairobi',
		'Indian/Reunion' => 'Asia/Dubai',
		'Iran' => 'Asia/Tehran',
		'Israel' => 'Asia/Jerusalem',
		'Jamaica' => 'America/Jamaica',
		'Japan' => 'Asia/Tokyo',
		'Kwajalein' => 'Pacific/Kwajalein',
		'Libya' => 'Africa/Tripoli',
		'MET' => 'Europe/Brussels',
		'MST' => 'America/Phoenix',
		'Mexico/BajaNorte' => 'America/Tijuana',
		'Mexico/BajaSur' => 'America/Mazatlan',
		'Mexico/General' => 'America/Mexico_City',
		'NZ' => 'Pacific/Auckland',
		'NZ-CHAT' => 'Pacific/Chatham',
		'Navajo' => 'America/Denver',
		'PRC' => 'Asia/Shanghai',
		'Pacific/Chuuk' => 'Pacific/Port_Moresby',
		'Pacific/Enderbury' => 'Pacific/Kanton',
		'Pacific/Funafuti' => 'Pacific/Tarawa',
		'Pacific/Johnston' => 'Pacific/Honolulu',
		'Pacific/Majuro' => 'Pacific/Tarawa',
		'Pacific/Midway' => 'Pacific/Pago_Pago',
		'Pacific/Pohnpei' => 'Pacific/Guadalcanal',
		'Pacific/Ponape' => 'Pacific/Guadalcanal',
		'Pacific/Saipan' => 'Pacific/Guam',
		'Pacific/Samoa' => 'Pacific/Pago_Pago',
		'Pacific/Truk' => 'Pacific/Port_Moresby',
		'Pacific/Wake' => 'Pacific/Tarawa',
		'Pacific/Wallis' => 'Pacific/Tarawa',
		'Pacific/Yap' => 'Pacific/Port_Moresby',
		'Poland' => 'Europe/Warsaw',
		'Portugal' => 'Europe/Lisbon',
		'ROC' => 'Asia/Taipei',
		'ROK' => 'Asia/Seoul',
		'Singapore' => 'Asia/Singapore',
		'Turkey' => 'Europe/Istanbul',
		'UCT' => 'Etc/UTC',
		'US/Alaska' => 'America/Anchorage',
		'US/Aleutian' => 'America/Adak',
		'US/Arizona' => 'America/Phoenix',
		'US/Central' => 'America/Chicago',
		'US/East-Indiana' => 'America/Indiana/Indianapolis',
		'US/Eastern' => 'America/New_York',
		'US/Hawaii' => 'Pacific/Honolulu',
		'US/Indiana-Starke' => 'America/Indiana/Knox',
		'US/Michigan' => 'America/Detroit',
		'US/Mountain' => 'America/Denver',
		'US/Pacific' => 'America/Los_Angeles',
		'US/Samoa' => 'Pacific/Pago_Pago',
		'UTC' => 'Etc/UTC',
		'Universal' => 'Etc/UTC',
		'W-SU' => 'Europe/Moscow',
		'WET' => 'Europe/Lisbon',
		'Zulu' => 'Etc/UTC',
	];

	/****************************
	 * Internal static properties
	 ****************************/

	/**
	 * @var array
	 *
	 * This array lists a series of representative time zones and their
	 * corresponding metazone labels.
	 *
	 * The term "representative" here means that a given time zone can represent
	 * others that use exactly the same rules for DST transitions, UTC offsets,
	 * and abbreviations. For example, Europe/Paris can be representative for
	 * Europe/Berlin, Europe/Rome, etc., because these cities all use exactly
	 * the same time zone rules and values.
	 *
	 * Metazone labels are the user friendly strings shown to the end user, e.g.
	 * "Mountain Standard Time". The values of this array are keys of strings
	 * defined in the Timezones language file, which in turn are MessageFormat
	 * strings used to generate the final label text.
	 *
	 * This array is subdivided into sections for different countries. The '001'
	 * section gives the default preferred representative time zone for each
	 * metazone ('001' is the code for the entire world). The subsequent
	 * sections give each country's preferred time zones for certain metazones.
	 * These are used as overrides for the defaults in various situations.
	 *
	 * Developers: Do not update the data in this array manually. Instead,
	 * run "php -f other/update_timezones.php" on the command line.
	 */
	protected static array $preferred_zones = [
		'001' => [
			'Acre' => 'America/Rio_Branco',
			'Afghanistan' => 'Asia/Kabul',
			'Africa_Central' => 'Africa/Maputo',
			'Africa_Eastern' => 'Africa/Nairobi',
			'Africa_FarWestern' => 'Africa/El_Aaiun',
			'Africa_Southern' => 'Africa/Johannesburg',
			'Africa_Western' => 'Africa/Lagos',
			'Aktyubinsk' => 'Asia/Aqtobe',
			'Alaska' => 'America/Juneau',
			'Alaska_Hawaii' => 'America/Anchorage',
			'Almaty' => 'Asia/Almaty',
			'Amazon' => 'America/Manaus',
			'America_Central' => 'America/Chicago',
			'America_Eastern' => 'America/New_York',
			'America_Mountain' => 'America/Denver',
			'America_Pacific' => 'America/Los_Angeles',
			'Anadyr' => 'Asia/Anadyr',
			'Apia' => 'Pacific/Apia',
			'Aqtau' => 'Asia/Aqtau',
			'Aqtobe' => 'Asia/Aqtobe',
			'Arabian' => 'Asia/Riyadh',
			'Argentina' => 'America/Argentina/Buenos_Aires',
			'Argentina_Western' => 'America/Argentina/San_Luis',
			'Armenia' => 'Asia/Yerevan',
			'Ashkhabad' => 'Asia/Ashgabat',
			'Atlantic' => 'America/Halifax',
			'Australia_Central' => 'Australia/Adelaide',
			'Australia_CentralWestern' => 'Australia/Eucla',
			'Australia_Eastern' => 'Australia/Sydney',
			'Australia_Western' => 'Australia/Perth',
			'Azerbaijan' => 'Asia/Baku',
			'Azores' => 'Atlantic/Azores',
			'Baku' => 'Asia/Baku',
			'Bangladesh' => 'Asia/Dhaka',
			'Bering' => 'America/Adak',
			'Bhutan' => 'Asia/Thimphu',
			'Bolivia' => 'America/La_Paz',
			'Borneo' => 'Asia/Kuching',
			'Brasilia' => 'America/Sao_Paulo',
			'British' => 'Europe/London',
			'Brunei' => 'Asia/Brunei',
			'Cape_Verde' => 'Atlantic/Cape_Verde',
			'Casey' => 'Antarctica/Casey',
			'Chamorro' => 'Pacific/Saipan',
			'Chatham' => 'Pacific/Chatham',
			'Chile' => 'America/Santiago',
			'China' => 'Asia/Shanghai',
			'Christmas' => 'Indian/Christmas',
			'Cocos' => 'Indian/Cocos',
			'Colombia' => 'America/Bogota',
			'Cook' => 'Pacific/Rarotonga',
			'Cuba' => 'America/Havana',
			'Dacca' => 'Asia/Dhaka',
			'Davis' => 'Antarctica/Davis',
			'Dominican' => 'America/Santo_Domingo',
			'DumontDUrville' => 'Antarctica/DumontDUrville',
			'Dushanbe' => 'Asia/Dushanbe',
			'Dutch_Guiana' => 'America/Paramaribo',
			'East_Timor' => 'Asia/Dili',
			'Easter' => 'Pacific/Easter',
			'Ecuador' => 'America/Guayaquil',
			'Europe_Central' => 'Europe/Paris',
			'Europe_Eastern' => 'Europe/Bucharest',
			'Europe_Further_Eastern' => 'Europe/Minsk',
			'Europe_Western' => 'Atlantic/Canary',
			'Falkland' => 'Atlantic/Stanley',
			'Fiji' => 'Pacific/Fiji',
			'French_Guiana' => 'America/Cayenne',
			'French_Southern' => 'Indian/Kerguelen',
			'Frunze' => 'Asia/Bishkek',
			'GMT' => 'Atlantic/Reykjavik',
			'Galapagos' => 'Pacific/Galapagos',
			'Gambier' => 'Pacific/Gambier',
			'Georgia' => 'Asia/Tbilisi',
			'Gilbert_Islands' => 'Pacific/Tarawa',
			'Goose_Bay' => 'America/Goose_Bay',
			'Greenland' => 'America/Nuuk',
			'Greenland_Central' => 'America/Scoresbysund',
			'Greenland_Eastern' => 'America/Scoresbysund',
			'Greenland_Western' => 'America/Nuuk',
			'Guam' => 'Pacific/Guam',
			'Gulf' => 'Asia/Dubai',
			'Guyana' => 'America/Guyana',
			'Hawaii' => 'Pacific/Honolulu',
			'Hawaii_Aleutian' => 'America/Adak',
			'Hong_Kong' => 'Asia/Hong_Kong',
			'Hovd' => 'Asia/Hovd',
			'India' => 'Asia/Kolkata',
			'Indian_Ocean' => 'Indian/Chagos',
			'Indochina' => 'Asia/Bangkok',
			'Indonesia_Central' => 'Asia/Makassar',
			'Indonesia_Eastern' => 'Asia/Jayapura',
			'Indonesia_Western' => 'Asia/Jakarta',
			'Iran' => 'Asia/Tehran',
			'Irish' => 'Europe/Dublin',
			'Irkutsk' => 'Asia/Irkutsk',
			'Israel' => 'Asia/Jerusalem',
			'Japan' => 'Asia/Tokyo',
			'Kamchatka' => 'Asia/Kamchatka',
			'Karachi' => 'Asia/Karachi',
			'Kazakhstan' => 'Asia/Almaty',
			'Kazakhstan_Eastern' => 'Asia/Almaty',
			'Kazakhstan_Western' => 'Asia/Aqtobe',
			'Kizilorda' => 'Asia/Qyzylorda',
			'Korea' => 'Asia/Seoul',
			'Kosrae' => 'Pacific/Kosrae',
			'Krasnoyarsk' => 'Asia/Krasnoyarsk',
			'Kuybyshev' => 'Europe/Samara',
			'Kwajalein' => 'Pacific/Kwajalein',
			'Kyrgystan' => 'Asia/Bishkek',
			'Lanka' => 'Asia/Colombo',
			'Liberia' => 'Africa/Monrovia',
			'Line_Islands' => 'Pacific/Kiritimati',
			'Lord_Howe' => 'Australia/Lord_Howe',
			'Macau' => 'Asia/Macau',
			'Magadan' => 'Asia/Magadan',
			'Malaya' => 'Asia/Kuala_Lumpur',
			'Malaysia' => 'Asia/Kuching',
			'Maldives' => 'Indian/Maldives',
			'Marquesas' => 'Pacific/Marquesas',
			'Marshall_Islands' => 'Pacific/Majuro',
			'Mauritius' => 'Indian/Mauritius',
			'Mawson' => 'Antarctica/Mawson',
			'Mexico_Pacific' => 'America/Mazatlan',
			'Mongolia' => 'Asia/Ulaanbaatar',
			'Moscow' => 'Europe/Moscow',
			'Myanmar' => 'Asia/Yangon',
			'Nauru' => 'Pacific/Nauru',
			'Nepal' => 'Asia/Kathmandu',
			'New_Caledonia' => 'Pacific/Noumea',
			'New_Zealand' => 'Pacific/Auckland',
			'Newfoundland' => 'America/St_Johns',
			'Niue' => 'Pacific/Niue',
			'Norfolk' => 'Pacific/Norfolk',
			'Noronha' => 'America/Noronha',
			'North_Mariana' => 'Pacific/Saipan',
			'Novosibirsk' => 'Asia/Novosibirsk',
			'Omsk' => 'Asia/Omsk',
			'Oral' => 'Asia/Oral',
			'Pakistan' => 'Asia/Karachi',
			'Palau' => 'Pacific/Palau',
			'Papua_New_Guinea' => 'Pacific/Port_Moresby',
			'Paraguay' => 'America/Asuncion',
			'Peru' => 'America/Lima',
			'Philippines' => 'Asia/Manila',
			'Phoenix_Islands' => 'Pacific/Kanton',
			'Pierre_Miquelon' => 'America/Miquelon',
			'Pitcairn' => 'Pacific/Pitcairn',
			'Ponape' => 'Pacific/Guadalcanal',
			'Pyongyang' => 'Asia/Pyongyang',
			'Qyzylorda' => 'Asia/Qyzylorda',
			'Reunion' => 'Indian/Reunion',
			'Rothera' => 'Antarctica/Rothera',
			'Sakhalin' => 'Asia/Sakhalin',
			'Samara' => 'Europe/Samara',
			'Samarkand' => 'Asia/Samarkand',
			'Samoa' => 'Pacific/Pago_Pago',
			'Seychelles' => 'Indian/Mahe',
			'Shevchenko' => 'Asia/Aqtau',
			'Singapore' => 'Asia/Singapore',
			'Solomon' => 'Pacific/Guadalcanal',
			'South_Georgia' => 'Atlantic/South_Georgia',
			'Suriname' => 'America/Paramaribo',
			'Sverdlovsk' => 'Asia/Yekaterinburg',
			'Syowa' => 'Antarctica/Syowa',
			'Tahiti' => 'Pacific/Tahiti',
			'Taipei' => 'Asia/Taipei',
			'Tajikistan' => 'Asia/Dushanbe',
			'Tashkent' => 'Asia/Tashkent',
			'Tbilisi' => 'Asia/Tbilisi',
			'Tokelau' => 'Pacific/Fakaofo',
			'Tonga' => 'Pacific/Tongatapu',
			'Truk' => 'Pacific/Port_Moresby',
			'Turkey' => 'Europe/Istanbul',
			'Turkmenistan' => 'Asia/Ashgabat',
			'Tuvalu' => 'Pacific/Funafuti',
			'Uralsk' => 'Asia/Oral',
			'Uruguay' => 'America/Montevideo',
			'Urumqi' => 'Asia/Urumqi',
			'Uzbekistan' => 'Asia/Tashkent',
			'Vanuatu' => 'Pacific/Efate',
			'Venezuela' => 'America/Caracas',
			'Vladivostok' => 'Asia/Vladivostok',
			'Volgograd' => 'Europe/Volgograd',
			'Vostok' => 'Antarctica/Vostok',
			'Wake' => 'Pacific/Wake',
			'Wallis' => 'Pacific/Wallis',
			'Yakutsk' => 'Asia/Yakutsk',
			'Yekaterinburg' => 'Asia/Yekaterinburg',
			'Yerevan' => 'Asia/Yerevan',
			'Yukon' => 'America/Whitehorse',
		],
		'AD' => [
			'Europe_Central' => 'Europe/Andorra',
		],
		'AG' => [
			'Atlantic' => 'America/Antigua',
		],
		'AI' => [
			'Atlantic' => 'America/Anguilla',
		],
		'AL' => [
			'Europe_Central' => 'Europe/Tirane',
		],
		'AO' => [
			'Africa_Western' => 'Africa/Luanda',
		],
		'AQ' => [
			'New_Zealand' => 'Antarctica/McMurdo',
		],
		'AT' => [
			'Europe_Central' => 'Europe/Vienna',
		],
		'AW' => [
			'Atlantic' => 'America/Aruba',
		],
		'AX' => [
			'Europe_Eastern' => 'Europe/Mariehamn',
		],
		'BA' => [
			'Europe_Central' => 'Europe/Sarajevo',
		],
		'BB' => [
			'Atlantic' => 'America/Barbados',
		],
		'BE' => [
			'Europe_Central' => 'Europe/Brussels',
		],
		'BF' => [
			'GMT' => 'Africa/Ouagadougou',
		],
		'BG' => [
			'Europe_Eastern' => 'Europe/Sofia',
		],
		'BH' => [
			'Arabian' => 'Asia/Bahrain',
		],
		'BI' => [
			'Africa_Central' => 'Africa/Bujumbura',
		],
		'BJ' => [
			'Africa_Western' => 'Africa/Porto-Novo',
		],
		'BM' => [
			'Atlantic' => 'Atlantic/Bermuda',
		],
		'BQ' => [
			'Atlantic' => 'America/Kralendijk',
		],
		'BS' => [
			'America_Eastern' => 'America/Nassau',
		],
		'BW' => [
			'Africa_Central' => 'Africa/Gaborone',
		],
		'BZ' => [
			'America_Central' => 'America/Belize',
		],
		'CA' => [
			'America_Central' => 'America/Winnipeg',
			'America_Eastern' => 'America/Toronto',
			'America_Mountain' => 'America/Edmonton',
			'America_Pacific' => 'America/Vancouver',
		],
		'CD' => [
			'Africa_Central' => 'Africa/Lubumbashi',
			'Africa_Western' => 'Africa/Kinshasa',
		],
		'CF' => [
			'Africa_Western' => 'Africa/Bangui',
		],
		'CG' => [
			'Africa_Western' => 'Africa/Brazzaville',
		],
		'CH' => [
			'Europe_Central' => 'Europe/Zurich',
		],
		'CI' => [
			'GMT' => 'Africa/Abidjan',
		],
		'CM' => [
			'Africa_Western' => 'Africa/Douala',
		],
		'CR' => [
			'America_Central' => 'America/Costa_Rica',
		],
		'CW' => [
			'Atlantic' => 'America/Curacao',
		],
		'CY' => [
			'Europe_Eastern' => 'Asia/Nicosia',
		],
		'CZ' => [
			'Europe_Central' => 'Europe/Prague',
		],
		'DE' => [
			'Europe_Central' => 'Europe/Berlin',
		],
		'DJ' => [
			'Africa_Eastern' => 'Africa/Djibouti',
		],
		'DK' => [
			'Europe_Central' => 'Europe/Copenhagen',
		],
		'DM' => [
			'Atlantic' => 'America/Dominica',
		],
		'EG' => [
			'Europe_Eastern' => 'Africa/Cairo',
		],
		'ER' => [
			'Africa_Eastern' => 'Africa/Nairobi',
		],
		'ES' => [
			'Europe_Central' => 'Europe/Madrid',
		],
		'ET' => [
			'Africa_Eastern' => 'Africa/Addis_Ababa',
		],
		'FI' => [
			'Europe_Eastern' => 'Europe/Helsinki',
		],
		'FO' => [
			'Europe_Western' => 'Atlantic/Faroe',
		],
		'GA' => [
			'Africa_Western' => 'Africa/Libreville',
		],
		'GB' => [
			'GMT' => 'Europe/London',
		],
		'GD' => [
			'Atlantic' => 'America/Grenada',
		],
		'GH' => [
			'GMT' => 'Africa/Accra',
		],
		'GI' => [
			'Europe_Central' => 'Europe/Gibraltar',
		],
		'GL' => [
			'Atlantic' => 'America/Thule',
		],
		'GM' => [
			'GMT' => 'Africa/Banjul',
		],
		'GN' => [
			'GMT' => 'Africa/Conakry',
		],
		'GP' => [
			'Atlantic' => 'America/Guadeloupe',
		],
		'GQ' => [
			'Africa_Western' => 'Africa/Malabo',
		],
		'GR' => [
			'Europe_Eastern' => 'Europe/Athens',
		],
		'GT' => [
			'America_Central' => 'America/Guatemala',
		],
		'GU' => [
			'Chamorro' => 'Pacific/Guam',
		],
		'HN' => [
			'America_Central' => 'America/Tegucigalpa',
		],
		'HR' => [
			'Europe_Central' => 'Europe/Zagreb',
		],
		'HT' => [
			'America_Eastern' => 'America/Port-au-Prince',
		],
		'HU' => [
			'Europe_Central' => 'Europe/Budapest',
		],
		'IE' => [
			'GMT' => 'Europe/Dublin',
		],
		'IQ' => [
			'Arabian' => 'Asia/Baghdad',
		],
		'IT' => [
			'Europe_Central' => 'Europe/Rome',
		],
		'JM' => [
			'America_Eastern' => 'America/Jamaica',
		],
		'KH' => [
			'Indochina' => 'Asia/Phnom_Penh',
		],
		'KM' => [
			'Africa_Eastern' => 'Indian/Comoro',
		],
		'KN' => [
			'Atlantic' => 'America/St_Kitts',
		],
		'KW' => [
			'Arabian' => 'Asia/Kuwait',
		],
		'KY' => [
			'America_Eastern' => 'America/Cayman',
		],
		'LA' => [
			'Indochina' => 'Asia/Vientiane',
		],
		'LB' => [
			'Europe_Eastern' => 'Asia/Beirut',
		],
		'LC' => [
			'Atlantic' => 'America/St_Lucia',
		],
		'LI' => [
			'Europe_Central' => 'Europe/Vaduz',
		],
		'LK' => [
			'India' => 'Asia/Colombo',
		],
		'LS' => [
			'Africa_Southern' => 'Africa/Maseru',
		],
		'LU' => [
			'Europe_Central' => 'Europe/Luxembourg',
		],
		'MC' => [
			'Europe_Central' => 'Europe/Monaco',
		],
		'ME' => [
			'Europe_Central' => 'Europe/Podgorica',
		],
		'MF' => [
			'Atlantic' => 'America/Marigot',
		],
		'MG' => [
			'Africa_Eastern' => 'Indian/Antananarivo',
		],
		'MK' => [
			'Europe_Central' => 'Europe/Skopje',
		],
		'ML' => [
			'GMT' => 'Africa/Bamako',
		],
		'MQ' => [
			'Atlantic' => 'America/Martinique',
		],
		'MR' => [
			'GMT' => 'Africa/Nouakchott',
		],
		'MS' => [
			'Atlantic' => 'America/Montserrat',
		],
		'MT' => [
			'Europe_Central' => 'Europe/Malta',
		],
		'MW' => [
			'Africa_Central' => 'Africa/Blantyre',
		],
		'MX' => [
			'America_Central' => 'America/Mexico_City',
			'America_Pacific' => 'America/Tijuana',
		],
		'NE' => [
			'Africa_Western' => 'Africa/Niamey',
		],
		'NL' => [
			'Europe_Central' => 'Europe/Amsterdam',
		],
		'NO' => [
			'Europe_Central' => 'Europe/Oslo',
		],
		'OM' => [
			'Gulf' => 'Asia/Muscat',
		],
		'PA' => [
			'America_Eastern' => 'America/Panama',
		],
		'PL' => [
			'Europe_Central' => 'Europe/Warsaw',
		],
		'PR' => [
			'Atlantic' => 'America/Puerto_Rico',
		],
		'QA' => [
			'Arabian' => 'Asia/Qatar',
		],
		'RS' => [
			'Europe_Central' => 'Europe/Belgrade',
		],
		'RU' => [
			'Europe_Further_Eastern' => 'Europe/Kaliningrad',
		],
		'RW' => [
			'Africa_Central' => 'Africa/Kigali',
		],
		'SE' => [
			'Europe_Central' => 'Europe/Stockholm',
		],
		'SH' => [
			'GMT' => 'Atlantic/St_Helena',
		],
		'SI' => [
			'Europe_Central' => 'Europe/Ljubljana',
		],
		'SJ' => [
			'Europe_Central' => 'Arctic/Longyearbyen',
		],
		'SK' => [
			'Europe_Central' => 'Europe/Bratislava',
		],
		'SL' => [
			'GMT' => 'Africa/Freetown',
		],
		'SM' => [
			'Europe_Central' => 'Europe/San_Marino',
		],
		'SN' => [
			'GMT' => 'Africa/Dakar',
		],
		'SO' => [
			'Africa_Eastern' => 'Africa/Mogadishu',
		],
		'SV' => [
			'America_Central' => 'America/El_Salvador',
		],
		'SX' => [
			'Atlantic' => 'America/Lower_Princes',
		],
		'SZ' => [
			'Africa_Southern' => 'Africa/Mbabane',
		],
		'TD' => [
			'Africa_Western' => 'Africa/Ndjamena',
		],
		'TG' => [
			'GMT' => 'Africa/Lome',
		],
		'TN' => [
			'Europe_Central' => 'Africa/Tunis',
		],
		'TT' => [
			'Atlantic' => 'America/Port_of_Spain',
		],
		'TZ' => [
			'Africa_Eastern' => 'Africa/Dar_es_Salaam',
		],
		'UG' => [
			'Africa_Eastern' => 'Africa/Kampala',
		],
		'VA' => [
			'Europe_Central' => 'Europe/Vatican',
		],
		'VC' => [
			'Atlantic' => 'America/St_Vincent',
		],
		'VG' => [
			'Atlantic' => 'America/Tortola',
		],
		'VI' => [
			'Atlantic' => 'America/St_Thomas',
		],
		'XK' => [
			'Europe_Central' => 'Europe/Belgrade',
		],
		'YE' => [
			'Arabian' => 'Asia/Aden',
		],
		'YT' => [
			'Africa_Eastern' => 'Indian/Mayotte',
		],
		'ZM' => [
			'Africa_Central' => 'Africa/Lusaka',
		],
		'ZW' => [
			'Africa_Central' => 'Africa/Harare',
		],
	];

	/**
	 * @var array
	 *
	 * This array lists all the individual time zones in each country, sorted by
	 * population (as reported in statistics available on Wikipedia in November
	 * 2020). Sorting this way enables us to consistently select the most
	 * appropriate individual time zone to represent all others that share its
	 * DST transition rules and values. For example, this ensures that New York
	 * will be preferred over random small towns in Indiana.
	 *
	 * When future versions of the TZDB add new time zone identifiers beyond
	 * those included here, they should be added to this list as appropriate.
	 * However, SMF will gracefully handle unexpected new time zones, so nothing
	 * will break in the meantime.
	 */
	protected static array $sorted_tzids = [
		// '??' means international.
		'??' => [
			'Etc/UTC',
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
			'America/Coyhaique',
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
			'Asia/Yakutsk',
			'Asia/Chita',
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
	protected static array $fallbacks = [
		/*
		 * 1. Simple renames.
		 *
		 * PHP_INT_MIN because these are valid for all dates.
		 */
		'Pacific/Kanton' => [
			[
				'ts' => PHP_INT_MIN,
				'tzid' => 'Pacific/Enderbury',
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

		// Diverged from America/Santiago in version 2025b.
		'America/Coyhaique' => [
			[
				'ts' => PHP_INT_MIN,
				'tzid' => '',
			],
			[
				'ts' => '1890-01-01T04:48:16+0000',
				'tzid' => 'America/Punta_Arenas',
			],
			[
				'ts' => '1946-08-29T04:00:00+0000',
				'tzid' => 'Chile/Continental',
			],
			[
				'ts' => '2025-03-20T03:00:00+0000',
				'tzid' => 'America/Punta_Arenas',
			],
		],
	];

	/**
	 * @var array
	 *
	 * Multidimensional array containing compiled lists of selectable time zones
	 * for any given value of $when.
	 *
	 * Built by self::list()
	 */
	private static $timezones_when = [];

	/**
	 * @var array
	 *
	 * Time zone identifiers sorted into a prioritized list based on the country
	 * codes in Config::$modSettings['timezone_priority_countries'].
	 *
	 * Built by self::prioritizeTzids()
	 */
	private static array $prioritized_tzids = [];

	/**
	 * @var array
	 *
	 * Multidimensional array containing start and end timestamps for any given
	 * value of $when.
	 *
	 * Built by self::getTimeRange()
	 */
	private static array $ranges = [];

	/**
	 * @var array
	 *
	 * List of time zone transitions for all metazones starting from a given
	 * value of $when until one year later.
	 *
	 * Built by self::buildMetaZoneTransitions()
	 */
	private static array $metazone_transitions = [];

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
		if (Lang::txtExists($this->getName(), file: 'Timezones')) {
			return Lang::getTxt($this->getName(), file: 'Timezones');
		}

		// If there's no $txt string, just guess based on the tzid's name.
		$tzid_parts = explode('/', $this->getName());

		return str_replace(['St_', '_'], ['St. ', ' '], array_pop($tzid_parts));
	}

	/**
	 * Returns this time zone's abbreviations (if any).
	 *
	 * @param \DateTimeInterface|int|string $when The date/time we are
	 *    interested in. May be an instance of \DateTimeInterface, a Unix
	 *    timestamp, or any string that strtotime() can understand.
	 *    Default: 'now'.
	 * @return array The time zone's abbreviations.
	 */
	public function getAbbreviations(\DateTimeInterface|int|string $when = 'now'): array
	{
		list($when, $later) = self::getTimeRange($when);

		$abbrs = [];

		foreach ($this->getTransitions($when, $later) as $transition) {
			$abbrs[] = $transition['abbr'];
		}

		return $abbrs;
	}

	/**
	 * Returns the metazone for this time zone at the given timestamp.
	 *
	 * @param \DateTimeInterface|int|string $when The date/time we are
	 *    interested in. May be an instance of \DateTimeInterface, a Unix
	 *    timestamp, or any string that strtotime() can understand.
	 *    Default: 'now'.
	 * @param bool $allow_fallbacks Whether to allow fallbacks when trying to
	 *    find the metazone.
	 *    Default: true.
	 * @return ?string The $tztxt variable for this time zone's metazone, or
	 *    null if no match was found and $allow_fallbacks is false.
	 */
	public function getMetaZone(\DateTimeInterface|int|string $when = 'now', bool $allow_fallbacks = true): ?string
	{
		// A few time zones have their own special metazones.
		if (Lang::txtExists($this->getName(), var: 'tztxt')) {
			return $this->getName();
		}

		if ($when instanceof \DateTimeInterface) {
			$when->setTimezone($this);
		} elseif (ctype_digit((string) $when)) {
			$when = new Time('@' . $when, $this);
		} else {
			try {
				$when = new Time($when, $this);
			} catch (\Throwable $e) {
				$when = new Time('now', $this);
			}
		}

		// For dates since the Unix Epoch, look it up in the VTimeZone class for this time zone.
		if (
			$when->getTimestamp() >= 0
			&& Calendar\VTimeZone::exists($this->getName())
		) {
			$vtimezone = Calendar\VTimeZone::load($this->getName());

			foreach ($vtimezone->metazones as $entry) {
				if ($when < (new \DateTimeImmutable($entry['ts']))) {
					break;
				}

				$metazone = $entry['metazone'];
			}

			if (isset($metazone)) {
				return $metazone;
			}
		}

		// Can we find another tzid that behaves the same way as this one?
		list($when, $later) = self::getTimeRange($when);

		if (empty(self::$metazone_transitions[$when])) {
			self::buildMetaZoneTransitions($when);
		}

		$tzkey = serialize($this->getTransitions($when, $later));

		if (isset(self::$metazone_transitions[$when][$tzkey])) {
			return self::$metazone_transitions[$when][$tzkey];
		}

		// Doesn't match any existing metazone. Can we build a custom one?
		if (!$allow_fallbacks) {
			return null;
		}

		// Etc/* is straightforward.
		if (str_starts_with($this->getName(), 'Etc/')) {
			Lang::setTxt(
				$this->getName(),
				'UTC' . $this->getAbbreviations()[0],
				var: 'tztxt',
			);

			return $this->getName();
		}

		// If this is the only time zone it its country, call it that country's time.
		$tzgeo = $this->getLocation();
		$country_tzids = self::getSortedTzidsForCountry($tzgeo['country_code']);

		if ($country_tzids === [$this->getName()]) {
			Lang::setTxt(
				$tzgeo['country_code'],
				Lang::getTxt(
					'region_format',
					[
						Lang::getTxt(['iso3166', $tzgeo['country_code']], file: 'Timezones'),
					],
					var: 'tztxt',
				),
				var: 'tztxt',
			);

			return $tzgeo['country_code'];
		}

		// Otherwise, create a metazone just for this oddball time zone.
		if (!Lang::txtExists($this->getName(), var: 'tztxt')) {
			Lang::setTxt(
				$this->getName(),
				Lang::getTxt('region_format', [$this->getLabel()], var: 'tztxt'),
				var: 'tztxt',
			);
		}

		return $this->getName();
	}

	/**
	 * Returns the metazone label for this time zone at the given timestamp.
	 *
	 * This is the finalized string that will be shown to the end user.
	 *
	 * The $preferred_region argument is used to decide which time zone will be
	 * considered the exemplar when multiple time zones share the same metazone.
	 * The difference this makes is best understood by examples:
	 *
	 * 1) If $preferred_region is set to 'DE':
	 *
	 *        Europe/Berlin --> 'Central European Time'
	 *        Europe/Paris  --> 'Central European Time (France)'
	 *        Europs/Rome   --> 'Central European Time (Italy)'
	 *
	 * 2) If $preferred_region is set to 'FR':
	 *
	 *        Europe/Berlin --> 'Central European Time (Germany)'
	 *        Europe/Paris  --> 'Central European Time'
	 *        Europs/Rome   --> 'Central European Time (Italy)'
	 *
	 * @param \DateTimeInterface|int|string|null $when The date/time we are
	 *    interested in. May be an instance of \DateTimeInterface, a Unix
	 *    timestamp, any string that strtotime() can understand, or null
	 *    to get a metazone label that works for any date.
	 *    Default: null.
	 * @param string $preferred_region The region whose time zones are preferred
	 *    over other options. May be any key in self::$preferred_zones, or null
	 *    to use this time zone's own country code as the preferred region.
	 *    Default: null.
	 * @param bool $allow_fallbacks Whether to allow fallbacks when trying to
	 *    find the metazone.
	 *    Default: true.
	 * @return ?string The $tztxt value for this time zone's metazone, or
	 *    null if no match was found and $allow_fallbacks is false.
	 */
	public function getMetaZoneLabel(
		\DateTimeInterface|int|string|null $when = null,
		?string $preferred_region = null,
		bool $allow_fallbacks = true,
	): ?string {
		// Figure out the DST type.
		if ($when instanceof \DateTimeInterface) {
			$when->setTimezone($this);
		} elseif (ctype_digit((string) $when)) {
			$when = new Time('@' . $when, $this);
		} else {
			try {
				$when = new Time($when, $this);
			} catch (\Throwable $e) {
				$when = null;
			}
		}

		// The country code might become relevant.
		$cc = $this->getLocation()['country_code'];

		// A few time zones have special overrides.
		if (
			// Special exceptions given in the CLDR data.
			Lang::txtExists($this->getName(), var: 'tztxt')
			// Antarctic stations can be weird. To avoid confusion, just label
			// each as "<station name> Time" and be done with it.
			|| $cc === 'AQ'
		) {
			if ($when === null) {
				switch ($this->getName()) {
					case 'Europe/Dublin':
					case 'Europe/London':
						return Lang::formatText(
							Lang::getTxt('region_format', var: 'tztxt'),
							[
								Lang::getTxt(
									['iso3166', $this->getLocation()['country_code']],
									file: 'Timezones',
								),
							],
						);

					default:
						$metazone = $this->getName();
						break;
				}
			} else {
				switch ($this->getName()) {
					case 'Europe/Dublin':
						// Note that this is intentionally inverted. Ireland
						// defines its summer time, IST, as its standard time,
						// and its winter time, GMT, as a negative daylight
						// saving (daylight losing?) time. PHP follows Ireland's
						// legal definition and marks GMT as daylight saving
						// time and IST as standard time for Europe/Dublin.
						// However, in CLDR, from which our $tztxt strings are
						// derived, IST is categorized as daylight time and GMT
						// as standard time for Europe/Dublin. This discrepancy
						// means that we need to invert what PHP says in order
						// to find the right CLDR strings.
						$dst_type = $when->format('I') ? 'standard' : 'daylight';
						break;

					case 'Europe/London':
						$dst_type = $when->format('I') ? 'daylight' : 'standard';
						break;
				}

				switch ($this->getName()) {
					case 'Europe/Dublin':
					case 'Europe/London':
						$metazone = Lang::txtExists([$this->getName(), $dst_type], var: 'tztxt') ? $this->getName() : $this->getMetaZone($when, $allow_fallbacks);
						break;

					default:
						$metazone = $this->getName();
						break;
				}
			}
		}
		// This is the vast majority of cases.
		else {
			$metazone = $this->getMetaZone($when ?? 'now', $allow_fallbacks);
		}

		$dst_type ??= match ($this->getDstType()) {
			self::DST_NEVER => 'standard',
			self::DST_ALWAYS => 'daylight',
			default => match (true) {
				$when === null => 'generic',
				!$when->format('I') => 'standard',
				(bool) $when->format('I') => 'daylight',
			},
		};

		// If we found a valid metazone, choose the DST variant we need.
		if (isset($metazone)) {
			// Force the real DST type to be attempted first.
			$attempts = [$dst_type => [$metazone, $dst_type, 'long']];

			$attempts += [
				'generic'  => [$metazone, 'generic',  'long'],
				'standard' => [$metazone, 'standard', 'long'],
				'daylight' => [$metazone, 'daylight', 'long'],
				0 => $metazone,
			];

			foreach ($attempts as $attempt) {
				if (Lang::txtExists($attempt, var: 'tztxt')) {
					$metazone_dst_type = $attempt;
					break;
				}
			}
		}

		// If we don't have a predefined string for this metazone, choose one
		// from the generic formats.
		if (!isset($metazone_dst_type)) {
			$metazone_dst_type = match ($dst_type) {
				'daylight' => 'region_format_type_daylight',
				'standard' => 'region_format_type_standard',
				default => 'region_format',
			};
		}

		// We might need to supply the location name.
		if (\count(self::getSortedTzidsForCountry($cc)) === 1) {
			$location = Lang::getTxt(['iso3166', $cc], file: 'Timezones');
		} else {
			$location = $this->getLabel();
		}

		// The main event.
		$metazone_label = Lang::getTxt($metazone_dst_type, [$location], var: 'tztxt');

		// If this time zone isn't the preferred one for its metazone in the
		// region we are building this for, we need to state the location.
		$preferred_zones = array_merge(
			self::$preferred_zones['001'],
			self::$preferred_zones[$preferred_region ?? $cc] ?? [],
		);

		if (
			isset($metazone, $preferred_zones[$metazone])
			&& $preferred_zones[$metazone] !== $this->getName()
			&& (
				isset($preferred_region)
				|| \count(self::getSortedTzidsForCountry($cc)) > 1
			)
		) {
			$priority_countries = array_merge(
				isset($preferred_region) ? [$preferred_region] : [],
				isset(Config::$modSettings['timezone_priority_countries']) ? explode(',', Config::$modSettings['timezone_priority_countries']) : ['001'],
			);

			if (!\in_array($cc, $priority_countries)) {
				$fallback_location = Lang::getTxt(['iso3166', $cc], file: 'Timezones');
			} else {
				$fallback_location = Lang::getTxt($this->getName(), file: 'Timezones');
			}

			$metazone_label = Lang::getTxt(
				'fallback_format',
				[
					$fallback_location,
					$metazone_label,
				],
				var: 'tztxt',
			);
		}

		// Allow mods to customize the label.
		IntegrationHook::call('integrate_metazone_label', [&$metazone_label, $this, $when]);

		return $metazone_label;
	}

	/**
	 * Returns whether this time zone uses Daylight Saving Time.
	 *
	 * @param \DateTimeInterface|int|string $when The earliest date/time we are
	 *    interested in. May be an instance of \DateTimeInterface, a Unix
	 *    timestamp, or any string that strtotime() can understand.
	 *    Default: 'now'.
	 * @return int One of this class's three DST_* constants.
	 */
	public function getDstType(\DateTimeInterface|int|string $when = 'now'): int
	{
		list($when, $later) = self::getTimeRange($when);

		$tzinfo = $this->getTransitions($when, $later);

		if (\count($tzinfo) > 1) {
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
	 * @param \DateTimeInterface|int|string $when The earliest date/time we are
	 *    interested in. May be an instance of \DateTimeInterface, a Unix
	 *    timestamp, or any string that strtotime() can understand.
	 *    Default: 'now'.
	 * @return int This time zone's Standard Time offset from GMT.
	 */
	public function getStandardOffset(\DateTimeInterface|int|string $when = 'now'): int
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
	 * Creates a new instance of this class.
	 *
	 * @param string $timezone One of the supported time zone names, an offset
	 *    value (+0200), or a time zone abbreviation (BST).
	 * @return self Returns an instance of this class for the requested time
	 *    zone, or for the default time zone if the requested one is invalid.
	 */
	public static function create(string $timezone): self
	{
		try {
			return new self($timezone);
		} catch (\Throwable $e) {
			return new self(Config::$modSettings['default_timezone'] ?? date_default_timezone_get());
		}
	}

	/**
	 * Get a list of time zones.
	 *
	 * @param \DateTimeInterface|int|string $when The date/time for which to
	 *    calculate the time zone values. May be an instance of
	 *    \DateTimeInterface, a Unix timestamp, or any string that strtotime()
	 *    can understand.
	 *    Default: 'now'.
	 * @return array An array of time zone identifiers and label text.
	 */
	public static function list(\DateTimeInterface|int|string $when = 'now'): array
	{
		list($when, $later) = self::getTimeRange($when);

		// No point doing this over if we already did it once.
		if (isset(self::$timezones_when[$when])) {
			return self::$timezones_when[$when];
		}

		self::buildMetaZoneTransitions($when);

		// Should we put time zones from certain countries at the top of the list?
		self::prioritizeTzids();

		// Idea here is to get exactly one representative identifier for each
		// and every unique set of time zone rules.
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
					$zones[$tzkey]['dst_type'] = $tz->getDstType($when);
					$zones[$tzkey]['abbrs'] = $tz->getAbbreviations($when);

					$metazone_label = $tz->getMetaZoneLabel($when);

					if (!empty($metazone_label)) {
						$zones[$tzkey]['metazone'] = $metazone_label;
					}
				}

				$zones[$tzkey]['locations'][] = $tz->getLabel();

				// Keep track of the current and standard offsets for this tzid.
				$offsets[$tzkey] = $tzinfo[0]['offset'];
				$std_offsets[$tzkey] = $tz->getStandardOffset($when);

				$longitudes[$tzkey] = $tz->getLocation()['longitude'];

				$labels[$tzkey] = $metazone_label;
			}
		}

		// Sort by current offset, then standard offset, then DST type, then label.
		array_multisort($offsets, SORT_DESC, SORT_NUMERIC, $std_offsets, SORT_DESC, SORT_NUMERIC, $longitudes, SORT_DESC, $labels, SORT_ASC, $zones);

		$date_when = date_create('@' . $when);

		// Build the final array of formatted values
		$priority_timezones = [];
		$timezones = [];

		foreach ($zones as $tzkey => $tzvalue) {
			date_timezone_set($date_when, timezone_open($tzvalue['tzid']));

			$desc = '';

			// Use the human friendly time zone name, if there is one.
			if (!empty($tzvalue['metazone'])) {
				$desc = $tzvalue['metazone'];
			}
			// Otherwise, use the list of locations (max 5, so things don't get silly)
			else {
				$desc = implode(', ', \array_slice(array_unique($tzvalue['locations']), 0, 5)) . (\count($tzvalue['locations']) > 5 ? ', ' . Lang::getTxt('etc', file: 'General') : '');
			}

			// We don't want abbreviations like '+03' or '-11'.
			$abbrs = array_filter(
				$tzvalue['abbrs'],
				function ($abbr) {
					return !strspn($abbr, '+-');
				},
			);
			$abbrs = \count($abbrs) == \count($tzvalue['abbrs']) ? array_unique($abbrs) : [];

			// Show the UTC offset and abbreviation(s).
			$desc = '[UTC' . date_format($date_when, 'P') . '] - ' . str_replace('  ', ' ', $desc) . (!empty($abbrs) ? ' (' . implode('/', $abbrs) . ')' : '');

			if (\in_array($tzvalue['tzid'], self::$prioritized_tzids['high'])) {
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
			['UTC' => 'UTC' . (!empty(Lang::getTxt('UTC', var: 'tztxt')) ? ' - ' . Lang::getTxt('UTC', var: 'tztxt') : ''), '-----'],
			$timezones,
		);

		self::$timezones_when[$when] = $timezones;

		return self::$timezones_when[$when];
	}

	/**
	 * Returns an array that instructs SMF how to map representative time zones
	 * (e.g. "America/Denver") onto the user-friendly metazone labels that
	 * most people think of as time zones (e.g. "Mountain Time").
	 *
	 * @param \DateTimeInterface|int|string $when The date/time used to choose
	 *    fallback values. May be an instance of \DateTimeInterface, a Unix
	 *    timestamp, or any string that strtotime() can understand.
	 *    Default: 'now'.
	 * @return array An array relating time zones to metazones
	 */
	public static function getTzidMetazones(\DateTimeInterface|int|string $when = 'now'): array
	{
		list($when, $later) = self::getTimeRange($when);

		// IntegrationHook::call('integrate_metazones', [&self::$metazones, $when]);

		// Fallbacks in case the server has an old version of the TZDB.
		$tzids_to_check = [];

		foreach (self::$preferred_zones as $region => $preferred) {
			$tzids_to_check = array_merge($tzids_to_check, array_values($preferred));
		}

		$tzid_fallbacks = self::getTzidFallbacks(array_unique($tzids_to_check), $when);

		foreach ($tzid_fallbacks as $orig_tzid => $alt_tzid) {
			// Skip any that are unchanged.
			if ($orig_tzid == $alt_tzid) {
				continue;
			}

			// Use fallback where possible.
			foreach (self::$preferred_zones as $region => $preferred) {
				$metazone = array_search($orig_tzid, $preferred);

				if (!empty($alt_tzid) && !\in_array($alt_tzid, $preferred)) {
					self::$preferred_zones[$region][$metazone] = $alt_tzid;

					Lang::setTxt(
						$alt_tzid,
						Lang::getTxt($orig_tzid, file: 'Timezones'),
					);
				}
			}
		}

		return self::$preferred_zones['001'];
	}

	/**
	 * Gets an array of all the time zones in a country, ranked by population.
	 *
	 * @param string $country_code A country's two-character ISO-3166 code.
	 * @param \DateTimeInterface|int|string $when The date/time used to choose
	 *    fallback values. May be an instance of \DateTimeInterface, a Unix
	 *    timestamp, or any string that strtotime() can understand.
	 *    Default: 'now'.
	 * @return array A list of time zones in the given country.
	 */
	public static function getSortedTzidsForCountry(string $country_code, \DateTimeInterface|int|string $when = 'now'): array
	{
		// Just in case...
		$country_code = strtoupper(trim($country_code));

		return self::getSortedTzids($when)[$country_code] ?? [];
	}

	/**
	 * Gets an array of all known time zones, grouped by country and ranked by
	 * population.
	 *
	 * @param \DateTimeInterface|int|string $when The date/time used to choose
	 *    fallback values. May be an instance of \DateTimeInterface, a Unix
	 *    timestamp, or any string that strtotime() can understand.
	 *    Default: 'now'.
	 * @return array A list of time zones grouped by country.
	 */
	public static function getSortedTzids(\DateTimeInterface|int|string $when = 'now'): array
	{
		static $processed = false;

		// Avoid unnecessary repetition.
		if ($processed) {
			return self::$sorted_tzids;
		}

		list($when, $later) = self::getTimeRange($when);

		foreach (self::$sorted_tzids as $country_code => $tzids) {
			IntegrationHook::call('integrate_country_timezones', [&self::$sorted_tzids, $country_code, $when]);

			// If something goes wrong, we want an empty array, not false.
			$recognized_country_tzids = array_filter(
				(array) @\DateTimeZone::listIdentifiers(
					\DateTimeZone::PER_COUNTRY,
					$country_code,
				),
			);

			// Make sure that no time zones are missing.
			self::$sorted_tzids[$country_code] = array_unique(array_merge(
				self::$sorted_tzids[$country_code],
				array_intersect(
					$recognized_country_tzids,
					\DateTimeZone::listIdentifiers(),
				),
			));

			// Get fallbacks where necessary.
			self::$sorted_tzids[$country_code] = array_unique(array_values(
				self::getTzidFallbacks(
					self::$sorted_tzids[$country_code],
					$when,
				),
			));

			// Filter out any time zones that are still undefined.
			self::$sorted_tzids[$country_code] = array_intersect(
				array_filter(self::$sorted_tzids[$country_code]),
				\DateTimeZone::listIdentifiers(\DateTimeZone::ALL_WITH_BC),
			);
		}

		$processed = true;

		return self::$sorted_tzids;
	}

	/**
	 * Checks a list of time zone identifiers to make sure they are all defined
	 * in the installed version of the time zone database, and returns an array
	 * of key-value substitution pairs.
	 *
	 * For defined time zone identifiers, the substitution value will be
	 * identical to the original value. For undefined ones, the substitute will
	 * be a time zone identifier that was equivalent to the missing one at the
	 * specified time, or an empty string if there was no equivalent at that
	 * time.
	 *
	 * Note: These fallbacks do not need to include every new time zone ever.
	 * They only need to cover the ones used in self::$preferred_zones['001'].
	 *
	 * To find the date & time when a new time zone comes into effect, check
	 * the TZDB changelog at https://data.iana.org/time-zones/tzdb/NEWS
	 *
	 * @param array $tzids The time zone identifiers to check.
	 * @param \DateTimeInterface|int|string $when The date/time used to choose
	 *    substitute values. May be an instance of \DateTimeInterface, a Unix
	 *    timestamp, or any string that strtotime() can understand.
	 *    Default: 'now'.
	 * @return array Substitute values for any missing time zone identifiers.
	 */
	public static function getTzidFallbacks(array $tzids, \DateTimeInterface|int|string $when = 'now'): array
	{
		$tzids = (array) $tzids;

		list($when, $later) = self::getTimeRange($when);

		$missing = array_diff($tzids, \DateTimeZone::listIdentifiers(\DateTimeZone::ALL_WITH_BC));

		IntegrationHook::call('integrate_timezone_fallbacks', [&self::$fallbacks, &$missing, $tzids, $when]);

		$replacements = [];

		foreach ($tzids as $tzid) {
			// Not missing.
			if (!\in_array($tzid, $missing)) {
				$replacements[$tzid] = $tzid;
			}
			// Missing and we have no fallback.
			elseif (empty(self::$fallbacks[$tzid])) {
				$replacements[$tzid] = '';
			}
			// Missing, but we have a fallback.
			else {
				foreach (self::$fallbacks[$tzid] as &$alt) {
					$alt['ts'] = \is_int($alt['ts']) ? $alt['ts'] : strtotime($alt['ts']);
				}

				usort(self::$fallbacks[$tzid], fn($a, $b) => $a['ts'] > $b['ts']);

				foreach (self::$fallbacks[$tzid] as $alt) {
					if ($when < $alt['ts']) {
						break;
					}

					$replacements[$tzid] = $alt['tzid'];
				}

				// Replacement is already in use.
				if (\in_array($alt['tzid'], $replacements) || (\in_array($alt['tzid'], $tzids) && !str_contains($alt['tzid'], 'Etc/'))) {
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
		if (\is_string($country_codes)) {
			$country_codes = explode(',', $country_codes);
		} else {
			$country_codes = array_map('strval', (array) $country_codes);
		}

		foreach ($country_codes as $key => $country_code) {
			$country_code = strtoupper(trim($country_code));

			$country_tzids = \strlen($country_code) !== 2 ? null : @\DateTimeZone::listIdentifiers(\DateTimeZone::PER_COUNTRY, $country_code);

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
	 * @param \DateTimeInterface|int|string $when The date/time used to choose
	 *    substitute values. May be an instance of \DateTimeInterface, a Unix
	 *    timestamp, or any string that strtotime() can understand.
	 *    Default: 'now'.
	 * @return array The start and end timestamps, in that order.
	 */
	protected static function getTimeRange(\DateTimeInterface|int|string $when = 'now'): array
	{
		// \DateTimeInterface?
		if ($when instanceof \DateTimeInterface) {
			$start = $when->getTimestamp();
		}
		// A Unix timestamp?
		elseif (is_numeric($when)) {
			$start = \intval($when);
		}
		// Parseable datetime string?
		elseif (\is_int($timestamp = strtotime((string) $when))) {
			$start = $timestamp;
		}
		// Invalid value? Just get current Unix timestamp.
		else {
			$start = time();
		}

		if (isset(self::$ranges[$start])) {
			return self::$ranges[$start];
		}

		self::$ranges[$start] = [$start, strtotime('@' . $start . ' + 1 year')];

		return self::$ranges[$start];
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
		$low_priority_tzids = !\in_array('AQ', $priority_countries) ? timezone_identifiers_list(parent::ANTARCTICA) : [];

		$normal_priority_tzids = array_diff(array_unique(array_merge(array_keys(self::getTzidMetazones()), timezone_identifiers_list())), $high_priority_tzids, $low_priority_tzids);

		// Put them in order of importance.
		self::$prioritized_tzids = ['high' => $high_priority_tzids, 'normal' => $normal_priority_tzids, 'low' => $low_priority_tzids];
	}

	/**
	 * Builds a list of time zone transitions for all metazones starting from
	 * $when until one year later.
	 *
	 * @param \DateTimeInterface|int|string $when The date/time used to choose
	 *    substitute values. May be an instance of \DateTimeInterface, a Unix
	 *    timestamp, or any string that strtotime() can understand.
	 *    Default: 'now'.
	 */
	protected static function buildMetaZoneTransitions(\DateTimeInterface|int|string $when = 'now'): void
	{
		list($when, $later) = self::getTimeRange($when);

		self::getTzidMetazones($when);

		foreach (self::$preferred_zones['001'] as $tzid => $label) {
			$tz = @timezone_open($tzid);

			if ($tz == null) {
				continue;
			}

			self::$metazone_transitions[$when][serialize($tz->getTransitions($when, $later))] = $label;
		}
	}
}
