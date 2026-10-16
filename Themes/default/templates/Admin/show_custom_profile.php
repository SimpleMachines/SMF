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
 * Template for showing custom profile fields.
 */
?><?php if (!empty(Utils::$context['saved_successful'])): ?>
					<div class="infobox"><?= Lang::getTxt('settings_saved', file: 'Admin') ?></div><?php endif; ?><?php /* Standard fields. */ ?><?php $this->subTemplate('show_list', ['list_id' => 'standard_profile_fields']); ?>
					<script>
						var iNumChecks = document.forms.standardProfileFields.length;
						for (var i = 0; i < iNumChecks; i++)
							if (document.forms.standardProfileFields[i].id.indexOf('reg_') == 0)
								document.forms.standardProfileFields[i].disabled = document.forms.standardProfileFields[i].disabled || !document.getElementById('active_' + document.forms.standardProfileFields[i].id.substr(4)).checked;
					</script>
					<br><?php /* Custom fields. */ ?><?php $this->subTemplate('show_list', ['list_id' => 'custom_profile_fields']); ?>
