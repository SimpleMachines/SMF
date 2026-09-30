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
 * This little beauty shows questions and answer from the captcha type feature.
 */
?><?php foreach (Utils::$context['languages'] as $lang_id => $lang): ?><?php
$lang_id = strtr($lang_id, ['-utf8' => '']);
$lang['name'] = strtr($lang['name'], ['-utf8' => '']);
?>
						<dt id="qa_dt_<?= $lang_id ?>" class="qa_link">
							<a href="javascript:void(0);">[ <?= $lang['name'] ?> ]</a>
						</dt>
						<fieldset id="qa_fs_<?= $lang_id ?>" class="qa_fieldset">
							<legend><a href="javascript:void(0);"><?= $lang['name'] ?></a></legend>
							<dl class="settings">
								<dt>
									<strong><?= Lang::getTxt('setup_verification_question', file: 'ManageSettings') ?></strong>
								</dt>
								<dd>
									<strong><?= Lang::getTxt('setup_verification_answer', file: 'ManageSettings') ?></strong>
								</dd><?php if (!empty(Utils::$context['qa_by_lang'][$lang_id])): ?><?php foreach (Utils::$context['qa_by_lang'][$lang_id] as $q_id): ?><?php $question = Utils::$context['question_answers'][$q_id]; ?>
								<dt>
									<input type="text" name="question[<?= $lang_id ?>][<?= $q_id ?>]" value="<?= $question['question'] ?>" size="50" class="verification_question">
								</dt>
								<dd><?php foreach ($question['answers'] as $answer): ?>
									<input type="text" name="answer[<?= $lang_id ?>][<?= $q_id ?>][]" value="<?= $answer ?>" size="50" class="verification_answer"><?php endforeach; ?>
									<div class="qa_add_answer"><a href="javascript:void(0);">[ <?= Lang::getTxt('setup_verification_add_answer', file: 'ManageSettings') ?> ]</a></div>
								</dd><?php endforeach; ?><?php endif; ?>
								<dt class="qa_add_question"><a href="javascript:void(0);">[ <?= Lang::getTxt('setup_verification_add_more', file: 'ManageSettings') ?> ]</a></dt>
							</dl>
						</fieldset><?php endforeach; ?>
