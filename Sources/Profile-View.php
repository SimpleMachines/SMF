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
 * @version 3.0 Alpha 2
 */

if (!defined('SMF')) {
	die('No direct access...');
}

class_exists('\\SMF\\Sources\\Alert');
class_exists('\\SMF\\Sources\\Actions\\Profile\\ShowAlerts');
class_exists('\\SMF\\Sources\\Actions\\Profile\\ShowPosts');
class_exists('\\SMF\\Sources\\Actions\\Profile\\StatPanel');
class_exists('\\SMF\\Sources\\Actions\\Profile\\Summary');
class_exists('\\SMF\\Sources\\Actions\\Profile\\Tracking');
class_exists('\\SMF\\Sources\\Actions\\Profile\\ViewWarning');
class_exists('\\SMF\\Sources\\Actions\\TrackIP');

?>