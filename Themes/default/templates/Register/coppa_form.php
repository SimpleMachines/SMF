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
 * An easily printable form for giving permission to access the forum for a minor.
 */
?>

<?php /* Show the form (As best we can) */ ?>
		<table style="width: 100%; padding: 3px; border: 0" class="tborder">
			<tr>
				<td><?= Utils::$context['forum_contacts'] ?></td>
			</tr>
			<tr>
				<td class="righttext">
					<em><?= Lang::getTxt('coppa_form_address', file: 'Login') ?></em>: <?= Utils::$context['ul'] ?><br>
					<?= Utils::$context['ul'] ?><br>
					<?= Utils::$context['ul'] ?><br>
					<?= Utils::$context['ul'] ?>

				</td>
			</tr>
			<tr>
				<td class="righttext">
					<em><?= Lang::getTxt('coppa_form_date', file: 'Login') ?></em>: <?= Utils::$context['ul'] ?>

					<br><br>
				</td>
			</tr>
			<tr>
				<td>
					<?= Utils::$context['coppa_body'] ?>

				</td>
			</tr>
		</table>
		<br>