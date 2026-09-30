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

use SMF\Lang;
use SMF\Utils;

if (!defined('SMF')) {
	die('No direct access...');
}

/*
 * Smiley set picker.
 */
?>
							<dt>
								<strong><label for="smiley_set"><?= Lang::getTxt('smileys_current', file: 'General') ?></label></strong>
							</dt>
							<dd>
								<select name="smiley_set" id="smiley_set">
<?php foreach (Utils::$context['smiley_sets'] as $set): ?>
									<option data-preview="<?= $set['preview'] ?>" value="<?= $set['id'] ?>"<?= $set['selected'] ? ' selected' : '' ?>><?= $set['name'] ?></option>
<?php endforeach; ?>
								</select>
								<img id="smileypr" class="centericon" src="<?= Utils::$context['member']['smiley_set']['preview'] ?>" alt=":)">
							</dd>