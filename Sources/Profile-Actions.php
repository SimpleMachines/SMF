<?php

/**
 * This file handles actions made on a user's profile.
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2023 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 1
 */

use SMF\BBCodeParser;
use SMF\Config;
use SMF\ItemList;
use SMF\Lang;
use SMF\Msg;
use SMF\Theme;
use SMF\User;
use SMF\Utils;
use SMF\Actions\Logout;
use SMF\Actions\Admin\Subscriptions;
use SMF\Db\DatabaseApi as Db;
use SMF\PersonalMessage\PM;

if (!defined('SMF'))
	die('No direct access...');

class_exists('\\SMF\\Actions\\Profile\\Delete');
class_exists('\\SMF\\Actions\\Profile\\IssueWarning');
class_exists('\\SMF\\Actions\\Profile\\PaidSubs');

/**
 * Activate an account.
 *
 * @param int $memID The ID of the member whose account we're activating
 */
function activateAccount($memID)
{
	isAllowedTo('moderate_forum');

	if (isset($_REQUEST['save']) && isset(User::$loaded[$memID]->is_activated) && User::$loaded[$memID]->is_activated != 1)
	{
		// If we are approving the deletion of an account, we do something special ;)
		if (User::$loaded[$memID]->is_activated == 4)
		{
			User::delete(Utils::$context['id_member']);
			redirectexit();
		}

		// Let the integrations know of the activation.
		call_integration_hook('integrate_activate', array(User::$loaded[$memID]->username));

		// Actually update this member now, as it guarantees the unapproved count can't get corrupted.
		User::updateMemberData(Utils::$context['id_member'], array('is_activated' => User::$loaded[$memID]->is_activated >= 10 ? 11 : 1, 'validation_code' => ''));

		// Log what we did?
		require_once(Config::$sourcedir . '/Logging.php');
		logAction('approve_member', array('member' => $memID), 'admin');

		// If we are doing approval, update the stats for the member just in case.
		if (in_array(User::$loaded[$memID]->is_activated, array(3, 4, 5, 13, 14, 15)))
			Config::updateModSettings(array('unapprovedMembers' => (Config::$modSettings['unapprovedMembers'] > 1 ? Config::$modSettings['unapprovedMembers'] - 1 : 0)));

		// Make sure we update the stats too.
		updateStats('member', false);
	}

	// Leave it be...
	redirectexit('action=profile;u=' . $memID . ';area=summary');
}

?>