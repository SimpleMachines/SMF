<?php
// Version: 2.1 Beta 4; Time zone descriptions

/**
 * This file defines custom descriptions for certain time zones to be used in
 * the time zone select menu.
 *
 * It is not necessary to define a custom description for every time zone. By
 * default SMF will populate the description with a list of locations (usually
 * cities) that are in the given time zone, so a custom description is only
 * needed if the default description doesn't work well in a particular case.
 *
 * Translators do not need to use the same list of locations that the English
 * version uses. If you need to add, replace, or remove locations in order to
 * meet the needs of your language, SMF will handle your changes gracefully.
 *
 * However, you should only name ONE representative location for each time zone.
 * For example, if you use 'Europe/Berlin' to represent Central European Time,
 * you should not include entries for 'Europe/Paris', 'Europe/Amsterdam',
 * 'Europe/Rome', etc. Those places all use the same time zone, so only only
 * location is needed to represent them all. If two locations in the same time
 * zone are included in this list, the first one defined will be used and the
 * second will be ignored.
 *
 * If you want to use a certain location to represent a time zone, but do not
 * want to give it a custom description, you can leave the description empty. An
 * empty description will be populated with city names like usual. This can be
 * useful if you want to make sure that a certain city is used as the
 * representative of a certain time zone, but still want the description to be
 * filled dynamically for whatever reason.
 */

global $tztxt;

$tztxt['UTC'] = 'Coordinated Universal Time';
$tztxt['America/Adak'] = 'Aleutian Islands';
$tztxt['Pacific/Marquesas'] = 'Marquesas Islands';
$tztxt['Pacific/Gambier'] = 'Gambier Islands';
$tztxt['America/Anchorage'] = 'Alaska';
$tztxt['Pacific/Pitcairn'] = 'Pitcairn Islands';
$tztxt['America/Los_Angeles'] = 'Pacific Time (USA, Canada)';
$tztxt['America/Denver'] = 'Mountain Time (USA, Canada)';
$tztxt['America/Phoenix'] = 'Mountain Time (no DST)';
$tztxt['America/Chicago'] = 'Central Time (USA, Canada)';
$tztxt['America/Belize'] = 'Central Time (no DST)';
$tztxt['America/New_York'] = 'Eastern Time (USA, Canada)';
$tztxt['America/Jamaica'] = 'Eastern Time (no DST)';
$tztxt['America/Halifax'] = 'Atlantic Time (Canada)';
$tztxt['America/Anguilla'] = 'Atlantic Time (no DST)';
$tztxt['America/St_Johns'] = 'Newfoundland';
$tztxt['America/Chihuahua'] = 'Chihuahua, Mazatlan';
$tztxt['Pacific/Easter'] = 'Easter Island';
$tztxt['Atlantic/Stanley'] = 'Falkland Islands';
$tztxt['America/Miquelon'] = 'Saint Pierre and Miquelon';
$tztxt['America/Argentina/Buenos_Aires'] = 'Buenos Aires';
$tztxt['America/Sao_Paulo'] = 'Brasilia Time';
$tztxt['America/Araguaina'] = 'Brasilia Time (no DST)';
$tztxt['America/Godthab'] = 'Greenland';
$tztxt['America/Noronha'] = 'Fernando de Noronha';
$tztxt['Atlantic/Reykjavik'] = 'Greenwich Mean Time (no DST)';
$tztxt['Europe/London'] = '';
$tztxt['Europe/Berlin'] = 'Central European Time';
$tztxt['Europe/Helsinki'] = 'Eastern European Time';
$tztxt['Africa/Brazzaville'] = 'Brazzaville, Lagos, Porto-Novo';
$tztxt['Asia/Jerusalem'] = 'Jerusalem';
$tztxt['Europe/Moscow'] = '';
$tztxt['Africa/Khartoum'] = 'Eastern Africa Time';
$tztxt['Asia/Riyadh'] = 'Arabia Time';
$tztxt['Asia/Kolkata'] = 'India, Sri Lanka';
$tztxt['Asia/Yekaterinburg'] = 'Yekaterinburg, Tyumen';
$tztxt['Asia/Dhaka'] = 'Astana, Dhaka';
$tztxt['Asia/Rangoon'] = 'Yangon/Rangoon';
$tztxt['Indian/Christmas'] = 'Christmas Island';
$tztxt['Antarctica/DumontDUrville'] = 'Dumont D\'Urville Station';
$tztxt['Antarctica/Vostok'] = 'Vostok Station';
$tztxt['Australia/Lord_Howe'] = 'Lord Howe Island';
$tztxt['Pacific/Guadalcanal'] = 'Solomon Islands';
$tztxt['Pacific/Norfolk'] = 'Norfolk Island';
$tztxt['Pacific/Noumea'] = 'New Caledonia';
$tztxt['Pacific/Auckland'] = 'Auckland, McMurdo Station';
$tztxt['Pacific/Kwajalein'] = 'Marshall Islands';
$tztxt['Pacific/Chatham'] = 'Chatham Islands';

?>