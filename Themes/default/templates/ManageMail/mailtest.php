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
 * Template for testing mail send.
 */
?><?php /* The results. */ ?><?php if (!empty(Utils::$context['result'])): ?><?php if (Utils::$context['result'] == 'failure'): ?><?php $result_txt = Lang::getTxt('mailtest_result_failure', ['url' => Config::$scripturl . '?action=admin;area=logs;sa=errorlog;desc'], file: 'ManageMail'); ?><?php else: ?><?php $result_txt = Lang::getTxt('mailtest_result_success', file: 'ManageMail'); ?><?php endif; ?>

					<div class="<?= Utils::$context['result'] == 'success' ? 'infobox' : 'errorbox' ?>"><?= $result_txt ?></div><?php endif; ?>

	<form id="admin_form_wrapper" action="<?= Utils::$context['post_url'] ?>" method="post">
		<div class="cat_bar">
			<h3 class="catbg"><?= Lang::getTxt('mailtest_header', file: 'ManageMail') ?></h3>
		</div>
		<div class="windowbg">
				<dl id="post_header">
					<dt><label for="subject" id="caption_subject"><?= Lang::getTxt('subject', file: 'General') ?></label></dt>
					<dd><input type="text" name="subject" id="subject" size="80" maxlength="80"></dd>
				</dl>
				<textarea class="editor" name="message" rows="5" cols="200"></textarea>
				<dl id="post_footer">
					<dd><input type="submit" value="<?= Lang::getTxt('send_message', file: 'General') ?>" class="button"></dd>
				</dl>
		</div>
	</form>