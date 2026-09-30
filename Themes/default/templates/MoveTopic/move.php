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
 * Show an interface for selecting which board to move a post to.
 */
?>

	<div id="move_topic" class="lower_padding">
		<form action="<?= Config::$scripturl ?>?action=movetopic2;current_board=<?= Utils::$context['current_board'] ?>;topic=<?= Utils::$context['current_topic'] ?>.0" method="post" accept-charset="UTF-8" onsubmit="submitonce(this);">
			<div class="cat_bar">
				<h3 class="catbg"><?= Lang::getTxt('move_topic', file: 'General') ?></h3>
			</div>
			<div class="windowbg centertext">
				<div class="move_topic">
					<dl class="settings">
						<dt>
							<strong><?= Lang::getTxt('move_to', file: 'General') ?></strong>
						</dt>
						<dd>
							<select name="toboard"><?php foreach (Utils::$context['categories'] as $category): ?>

								<optgroup label="<?= $category['name'] ?>"><?php foreach ($category['boards'] as $board): ?>

									<option value="<?= $board['id'] ?>"<?= $board['selected'] ? ' selected' : '' ?><?= $board['id'] == Utils::$context['current_board'] ? ' disabled' : '' ?>><?= $board['child_level'] > 0 ? str_repeat('==', $board['child_level'] - 1) . '=&gt; ' : '' ?><?= $board['name'] ?></option><?php endforeach; ?>

								</optgroup><?php endforeach; ?>

							</select>
						</dd><?php /* Disable the reason textarea when the postRedirect checkbox is unchecked... */ ?>

					</dl>
					<label for="reset_subject">
						<input type="checkbox" name="reset_subject" id="reset_subject" onclick="document.getElementById('subjectArea').classList.toggle('hidden');"> <?= Lang::getTxt('movetopic_change_subject', file: 'General') ?>

					</label><br>
					<fieldset id="subjectArea" class="hidden">
						<dl class="settings">
							<dt><strong><?= Lang::getTxt('movetopic_new_subject', file: 'General') ?></strong></dt>
							<dd><input type="text" name="custom_subject" size="30" value="<?= Utils::$context['subject'] ?>"></dd>
						</dl>
						<label for="enforce_subject"><input type="checkbox" name="enforce_subject" id="enforce_subject"> <?= Lang::getTxt('movetopic_change_all_subjects', file: 'General') ?></label>
					</fieldset><?php /* Stick our "create a redirection topic" template in here... */ ?><?php $this->subTemplate('redirect_options', ['type' => 'move']); ?>

					<input type="submit" value="<?= Lang::getTxt('move_topic', file: 'General') ?>" onclick="return submitThisOnce(this);" accesskey="s" class="button">
				</div><!-- .move_topic -->
			</div><!-- .windowbg --><?php if (Utils::$context['back_to_topic']): ?>

			<input type="hidden" name="goback" value="1"><?php endif; ?>

			<input type="hidden" name="<?= Utils::$context['session_var'] ?>" value="<?= Utils::$context['session_id'] ?>">
			<input type="hidden" name="seqnum" value="<?= Utils::$context['form_sequence_number'] ?>">
		</form>
	</div><!-- #move_topic -->