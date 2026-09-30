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
 * Shows a particular folder (eg inbox or outbox), all the PMs in it, etc.
 */
?><?php /* The every helpful javascript! */ ?>
		<script>
			var allLabels = {};
			var currentLabels = {};
			function loadLabelChoices()
			{
				var listing = document.forms.pmFolder.elements;
				var theSelect = document.forms.pmFolder.pm_action;
				var add, remove, toAdd = {length: 0}, toRemove = {length: 0};

				if (theSelect.childNodes.length == 0)
					return;

				// This is done this way for internationalization reasons.
				if (!('-1' in allLabels))
				{
					for (var o = 0; o < theSelect.options.length; o++)
						if (theSelect.options[o].value.substr(0, 4) == "rem_")
							allLabels[theSelect.options[o].value.substr(4)] = theSelect.options[o].text;
				}

				for (var i = 0; i < listing.length; i++)
				{
					if (listing[i].name != "pms[]" || !listing[i].checked)
						continue;

					var alreadyThere = [], x;
					for (x in currentLabels[listing[i].value])
					{
						if (!(x in toRemove))
						{
							toRemove[x] = allLabels[x];
							toRemove.length++;
						}
						alreadyThere[x] = allLabels[x];
					}

					for (x in allLabels)
					{
						if (!(x in alreadyThere))
						{
							toAdd[x] = allLabels[x];
							toAdd.length++;
						}
					}
				}

				while (theSelect.options.length > 2)
					theSelect.options[2] = null;

				if (toAdd.length != 0)
				{
					theSelect.options[theSelect.options.length] = new Option("<?= Lang::getTxt('pm_msg_label_apply', file: 'PersonalMessage') ?>", "");
					setInnerHTML(theSelect.options[theSelect.options.length - 1], "<?= Lang::getTxt('pm_msg_label_apply', file: 'PersonalMessage') ?>");
					theSelect.options[theSelect.options.length - 1].disabled = true;

					for (i in toAdd)
					{
						if (i != "length")
							theSelect.options[theSelect.options.length] = new Option(toAdd[i], "add_" + i);
					}
				}

				if (toRemove.length != 0)
				{
					theSelect.options[theSelect.options.length] = new Option("<?= Lang::getTxt('pm_msg_label_remove', file: 'PersonalMessage') ?>", "");
					setInnerHTML(theSelect.options[theSelect.options.length - 1], "<?= Lang::getTxt('pm_msg_label_remove', file: 'PersonalMessage') ?>");
					theSelect.options[theSelect.options.length - 1].disabled = true;

					for (i in toRemove)
					{
						if (i != "length")
							theSelect.options[theSelect.options.length] = new Option(toRemove[i], "rem_" + i);
					}
				}
			}
		</script>
		<form class="flow_hidden" action="<?= Config::$scripturl ?>?action=pm;sa=pmactions;<?= Utils::$context['display_mode'] == 2 ? 'conversation;' : '' ?>f=<?= Utils::$context['folder'] ?>;start=<?= Utils::$context['start'] ?><?= Utils::$context['current_label_id'] != -1 ? ';l=' . Utils::$context['current_label_id'] : '' ?>" method="post" accept-charset="UTF-8" name="pmFolder" id="pmFolder"><?php /* If we are not in single display mode show the subjects on the top! */ ?><?php if (Utils::$context['display_mode'] != 1): ?><?php $this->subTemplate('subject_list'); ?>
			<div class="clear_right"><br></div><?php endif; ?><?php /* Got some messages to display? */ ?><?php if (Utils::$context['get_pmessage']('message', true)): ?><?php /* Show a few buttons if we are in conversation mode and outputting the first message. */ ?><?php if (Utils::$context['display_mode'] == 2): ?><?php
// This bit uses info set in template_subject_list, so it's wrapped
// in an if just in case a mod or custom theme breaks it.
?><?php if (!empty(Utils::$context['current_pm_subject'])): ?>
			<div class="cat_bar">
				<h3 class="catbg">
					<span><?= Lang::getTxt('conversation', file: 'PersonalMessage') ?></span>
				</h3>
			</div>
			<div class="roundframe">
				<div class="display_title"><?= Utils::$context['current_pm_subject'] ?></div>
				<p><?= Lang::getTxt('started_by_member_time', ['member' => Utils::$context['current_pm_author'], 'time' => Utils::$context['current_pm_time']], file: 'General') ?></p><?php else: ?>
			<div class="roundframe"><?php endif; ?><?php /* Show the conversation buttons. */ ?><?php $this->subTemplate('button_strip', ['button_strip' => Utils::$context['conversation_buttons'], 'direction' => 'right']); ?>
			</div><?php endif; ?><?php while ($message = Utils::$context['get_pmessage']('message')): ?><?php $this->subTemplate('single_pm', ['message' => $message]); ?><?php endwhile; ?><?php if (empty(Utils::$context['display_mode'])): ?>
			<div class="pagesection">
				<div class="pagelinks"><?= Utils::$context['page_index'] ?></div>
				<div class="floatright">
					<input type="submit" name="del_selected" value="<?= Lang::getTxt('quickmod_delete_selected', file: 'General') ?>" data-confirm="<?= Lang::getTxt('delete_selected_confirm', file: 'PersonalMessage') ?>" class="button you_sure">
				</div>
			</div><?php /* Show a few buttons if we are in conversation mode and outputting the first message. */ ?><?php elseif (Utils::$context['display_mode'] == 2 && isset(Utils::$context['conversation_buttons'])): ?>
			<div class="pagesection"><?php $this->subTemplate('button_strip', ['button_strip' => Utils::$context['conversation_buttons'], 'direction' => 'right']); ?>
			</div><?php endif; ?>
			<br><?php endif; ?><?php /* Individual messages = button list! */ ?><?php if (Utils::$context['display_mode'] == 1): ?><?php $this->subTemplate('subject_list'); ?><br><?php endif; ?>
			<input type="hidden" name="<?= Utils::$context['session_var'] ?>" value="<?= Utils::$context['session_id'] ?>">
		</form>