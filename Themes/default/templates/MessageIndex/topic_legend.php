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
 * Shows a legend for topic icons.
 */
?>

	<div class="tborder" id="topic_icons">
		<div class="information">
			<p id="message_index_jump_to"></p>
<?php if (empty(Utils::$context['no_topic_listing'])): ?>
			<p class="floatleft"><?= !empty(Config::$modSettings['enableParticipation']) && !User::$me->is_guest ? '
				<span class="main_icons profile_sm"></span> ' . Lang::getTxt('participation_caption', file: 'General') . '<br>' : '' ?>

				<?= (Config::$modSettings['pollMode'] == '1' ? '<span class="main_icons poll"></span> ' . Lang::getTxt('poll', file: 'General') . '<br>' : '') ?>

				<span class="main_icons move"></span> <?= Lang::getTxt('moved_topic', file: 'General') ?><br>
			</p>
			<p>
				<span class="main_icons lock"></span> <?= Lang::getTxt('locked_topic', file: 'General') ?><br>
				<span class="main_icons sticky"></span> <?= Lang::getTxt('sticky_topic', file: 'General') ?><br>
				<span class="main_icons watch"></span> <?= Lang::getTxt('watching_topic', file: 'General') ?><br>
			</p>
<?php endif; ?>
<?php if (!empty(Utils::$context['jump_to'])): ?>
			<script>
				window.addEventListener("DOMContentLoaded", function() {
					new JumpTo({
						sContainerId: "message_index_jump_to",
						sJumpToTemplate: "<label class=\"smalltext jump_to\" for=\"%select_id%\"><?= Utils::$context['jump_to']['label'] ?><" + "/label> %dropdown_list%",
						iCurBoardId: <?= Utils::$context['current_board'] ?>,
						iCurBoardChildLevel: <?= Utils::$context['jump_to']['child_level'] ?>,
						sCurBoardName: "<?= Utils::$context['jump_to']['board_name'] ?>",
						sBoardChildLevelIndicator: "==",
						sBoardPrefix: "=> ",
						sCatSeparator: "-----------------------------",
						sCatPrefix: "",
						sGoButtonLabel: "<?= Lang::getTxt('quick_mod_go', file: 'General') ?>"
					});
				});
			</script>
<?php endif; ?>
		</div><!-- .information -->
	</div><!-- #topic_icons -->