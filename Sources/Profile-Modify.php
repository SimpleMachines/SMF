<?php

/**
 * Backward compatibility file.
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2024 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 1
 */

if (!defined('SMF')) {
	die('No direct access...');
}

class_exists('\\SMF\\Alert');
class_exists('\\SMF\\Profile');
class_exists('\\SMF\\Actions\\Profile\\Account');
class_exists('\\SMF\\Actions\\Profile\\BuddyIgnoreLists');
class_exists('\\SMF\\Actions\\Profile\\ForumProfile');
class_exists('\\SMF\\Actions\\Profile\\GroupMembership');
class_exists('\\SMF\\Actions\\Profile\\IgnoreBoards');
class_exists('\\SMF\\Actions\\Profile\\Notification');
class_exists('\\SMF\\Actions\\Profile\\TFADisable');
class_exists('\\SMF\\Actions\\Profile\\TFASetup');
class_exists('\\SMF\\Actions\\Profile\\ThemeOptions');

?>