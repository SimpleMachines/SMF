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
use SMF\Utils;

if (!defined('SMF')) {
	die('No direct access...');
}

/*
 * Select the time format!
 */
?>
							<dt>
								<strong><label for="easyformat"><?= Lang::getTxt('time_format', file: 'Profile') ?></label></strong><br>
								<a href="<?= Config::$scripturl ?>?action=helpadmin;help=time_format" onclick="return reqOverlayDiv(this.href);" class="help"><span class="main_icons help" title="<?= Lang::getTxt('help', file: 'General') ?>"></span></a>
								<span class="smalltext">
									<label for="time_format"><?= Lang::getTxt('date_format', file: 'Profile') ?></label>
								</span>
							</dt>
							<dd>
								<select name="easyformat" id="easyformat" onchange="document.forms.creator.time_format.value = this.options[this.selectedIndex].value;">
<?php
// Help the user by showing a list of common time formats.
$found = false;
?>
<?php foreach (Utils::$context['easy_timeformats'] as $time_format): ?>
<?php $found = $found || $time_format['format'] == Utils::$context['member']['time_format']; ?>
									<option value="<?= $time_format['format'] ?>"<?= $time_format['format'] == Utils::$context['member']['time_format'] ? ' selected' : '' ?>><?= $time_format['title'] ?></option>
<?php endforeach; ?>
									<option value="other"<?= !$found ? ' selected' : '' ?> disabled><?= Lang::getTxt('other', file: 'General') ?></option>
								</select>
								<input type="text" name="time_format" id="time_format" value="<?= Utils::$context['member']['time_format'] ?>" onchange="document.forms.creator.easyformat.value = 'other'" size="30">
							</dd>