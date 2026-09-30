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

use SMF\Config;
use SMF\Lang;
use SMF\User;
use SMF\Utils;

if (!defined('SMF')) {
	die('No direct access...');
}

/*
 * This shows the admin search form
 */
?><?php if (User::$me->is_admin): ?>
								<form action="<?= Config::$scripturl ?>?action=admin;area=search" method="post" accept-charset="UTF-8" class="admin_search">
									<span class="main_icons filter centericon"></span>
									<input type="search" name="search_term" placeholder="<?= Lang::getTxt('admin_search', file: 'Admin') ?>"<?= isset(Utils::$context['search_term']) ? ' value="' . Utils::$context['search_term'] . '"' : '' ?>>
									<select name="search_type">
										<option value="internal"<?= (empty(Utils::$context['admin_preferences']['sb']) || Utils::$context['admin_preferences']['sb'] == 'internal' ? ' selected' : '') ?>><?= Lang::getTxt('admin_search_type_internal', file: 'Admin') ?></option>
										<option value="member"<?= (!empty(Utils::$context['admin_preferences']['sb']) && Utils::$context['admin_preferences']['sb'] == 'member' ? ' selected' : '') ?>><?= Lang::getTxt('admin_search_type_member', file: 'Admin') ?></option>
										<option value="online"<?= (!empty(Utils::$context['admin_preferences']['sb']) && Utils::$context['admin_preferences']['sb'] == 'online' ? ' selected' : '') ?>><?= Lang::getTxt('admin_search_type_online', file: 'Admin') ?></option>
									</select>
									<input type="submit" name="search_go" id="search_go" value="<?= Lang::getTxt('admin_search_go', file: 'Admin') ?>" class="button">
								</form><?php endif; ?>
