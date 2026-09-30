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
 * This allows you to edit a file
 */
?><?php if (Utils::$context['session_error']): ?>

	<div class="errorbox">
		<?= Lang::getTxt('error_session_timeout', file: 'Errors') ?>

	</div><?php endif; ?><?php /* Is this file writeable? */ ?><?php if (!Utils::$context['allow_save']): ?>

	<div class="errorbox">
		<?= Lang::getTxt('theme_edit_no_save', ['filename' => Utils::$context['allow_save_filename']], file: 'Themes') ?>

	</div><?php endif; ?><?php /* Just show a big box.... gray out the Save button if it's not saveable... (ie. not 777.) */ ?>

		<form action="<?= Config::$scripturl ?>?action=admin;area=theme;th=<?= Utils::$context['theme_id'] ?>;sa=edit" method="post" accept-charset="UTF-8">
			<div class="cat_bar">
				<h3 class="catbg"><?= Lang::getTxt('theme_edit_file', ['filename' => Utils::$context['edit_filename']], file: 'Themes') ?></h3>
			</div>
			<div class="windowbg">
				<textarea name="entire_file" id="entire_file" cols="80" rows="20" class="edit_file"><?= Utils::$context['entire_file'] ?></textarea><br>
				<input type="submit" name="save" value="<?= Lang::getTxt('theme_edit_save', file: 'Themes') ?>"<?= Utils::$context['allow_save'] ? '' : ' disabled' ?> class="button">
				<input type="hidden" name="filename" value="<?= Utils::$context['edit_filename'] ?>">
				<input type="hidden" name="<?= Utils::$context['session_var'] ?>" value="<?= Utils::$context['session_id'] ?>"><?php /* Hopefully it exists. */ ?><?php if (isset(Utils::$context['admin-te-' . md5(Utils::$context['theme_id'] . '-' . Utils::$context['edit_filename']) . '_token'])): ?>

				<input type="hidden" name="<?= Utils::$context['admin-te-' . md5(Utils::$context['theme_id'] . '-' . Utils::$context['edit_filename']) . '_token_var'] ?>" value="<?= Utils::$context['admin-te-' . md5(Utils::$context['theme_id'] . '-' . Utils::$context['edit_filename']) . '_token'] ?>"><?php endif; ?>

			</div><!-- .windowbg -->
		</form>