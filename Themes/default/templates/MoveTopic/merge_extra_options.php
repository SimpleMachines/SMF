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
 * Extra options related to merging topics.
 */
?>
	<div id="merge_topics">
		<form action="<?= Config::$scripturl ?>?action=mergetopics;sa=merge;" method="post" accept-charset="UTF-8">
			<div class="cat_bar">
				<h3 class="catbg"><?= Lang::getTxt('merge_topic_list', file: 'General') ?></h3>
			</div>
			<table class="bordercolor table_grid">
				<thead>
					<tr class="title_bar">
						<th scope="col" style="width:10px;"><?= Lang::getTxt('merge_check', file: 'General') ?></th>
						<th scope="col" class="lefttext"><?= Lang::getTxt('subject', file: 'General') ?></th>
						<th scope="col" class="lefttext"><?= Lang::getTxt('started_by', file: 'General') ?></th>
						<th scope="col" class="lefttext"><?= Lang::getTxt('last_post', file: 'General') ?></th>
						<th scope="col" style="width:20px;"><?= Lang::getTxt('merge_include_notifications', file: 'General') ?></th>
					</tr>
				</thead>
				<tbody><?php foreach (Utils::$context['topics'] as $topic): ?>
					<tr class="windowbg">
						<td>
							<input type="checkbox" name="topics[]" value="<?= $topic['id'] ?>" checked>
						</td>
						<td>
							<a href="<?= Config::$scripturl ?>?topic=<?= $topic['id'] ?>.0" target="_blank" rel="noopener"><?= $topic['subject'] ?></a>
						</td>
						<td>
							<?= $topic['started']['link'] ?><br>
							<span class="smalltext"><?= $topic['started']['time'] ?></span>
						</td>
						<td>
							<?= $topic['updated']['link'] ?><br>
							<span class="smalltext"><?= $topic['updated']['time'] ?></span>
						</td>
						<td>
							<input type="checkbox" name="notifications[]" value="<?= $topic['id'] ?>" checked>
						</td>
					</tr><?php endforeach; ?>
				</tbody>
			</table>
			<br>
			<div class="windowbg">
				<fieldset id="merge_subject" class="merge_options">
					<legend><?= Lang::getTxt('merge_select_subject', file: 'General') ?></legend>
					<select name="subject" onchange="this.form.custom_subject.style.display = (this.options[this.selectedIndex].value != 0) ? 'none': '' ;"><?php foreach (Utils::$context['topics'] as $topic): ?>
						<option value="<?= $topic['id'] ?>"<?= ($topic['selected'] ? ' selected' : '') ?>><?= $topic['subject'] ?></option><?php endforeach; ?>
						<option value="0"><?= Lang::getTxt('merge_custom_subject', file: 'General') ?></option>
					</select>
					<br>
					<input type="text" name="custom_subject" size="60" id="custom_subject" class="custom_subject" style="display: none;"><br>
					<label for="enforce_subject"><input type="checkbox" name="enforce_subject" id="enforce_subject" value="1"> <?= Lang::getTxt('movetopic_change_all_subjects', file: 'General') ?></label>
				</fieldset><?php /* Show an option to create a redirection topic as well... */ ?><?php $this->subTemplate('redirect_options', ['type' => 'merge']); ?><?php if (!empty(Utils::$context['boards']) && count(Utils::$context['boards']) > 1): ?>
				<fieldset id="merge_board" class="merge_options">
					<legend><?= Lang::getTxt('merge_select_target_board', file: 'General') ?></legend>
					<ul><?php foreach (Utils::$context['boards'] as $board): ?>
						<li>
							<input type="radio" name="board" value="<?= $board['id'] ?>"<?= ($board['selected'] ? ' checked' : '') ?>> <?= $board['name'] ?>
						</li><?php endforeach; ?>
					</ul>
				</fieldset><?php endif; ?><?php if (!empty(Utils::$context['polls'])): ?>
				<fieldset id="merge_poll" class="merge_options">
					<legend><?= Lang::getTxt('merge_select_poll', file: 'General') ?></legend>
					<ul><?php foreach (Utils::$context['polls'] as $poll): ?>
						<li>
							<input type="radio" name="poll" value="<?= $poll['id'] ?>"<?= ($poll['selected'] ? ' checked' : '') ?>> <?= $poll['question'] ?> (<?= Lang::getTxt('topic', file: 'General') ?>: <a href="<?= Config::$scripturl ?>?topic=<?= $poll['topic']['id'] ?>.0" target="_blank" rel="noopener"><?= $poll['topic']['subject'] ?></a>)
						</li><?php endforeach; ?>
						<li>
							<input type="radio" name="poll" value="-1"> (<?= Lang::getTxt('merge_no_poll', file: 'General') ?>)
						</li>
					</ul>
				</fieldset><?php endif; ?>
				<div class="auto_flow">
					<input type="submit" value="<?= Lang::getTxt('merge', file: 'General') ?>" class="button">
					<input type="hidden" name="<?= Utils::$context['session_var'] ?>" value="<?= Utils::$context['session_id'] ?>">
					<input type="hidden" name="sa" value="execute">
				</div>
			</div><!-- .windowbg -->
		</form>
	</div><!-- #merge_topics -->