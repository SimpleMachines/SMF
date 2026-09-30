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
 * This template allows for the selection of different themes ;)
 */
?>

	<form action="<?= Config::$scripturl ?>?action=themechooser" method="post" accept-charset="UTF-8">
<?php /* Just go through each theme and show its information - thumbnail, etc. */ ?>
<?php for ($i = 0; $i < 2; $i++): ?>
		<div class="cat_bar">
			<h3 class="catbg"><?= Lang::getTxt($i == 0 ? 'current_theme' : 'theme_pick', file: 'Themes') ?></h3>
		</div>
		<div class="windowbg">
<?php /* Just go through each theme and show its information - thumbnail, etc. */ ?>
<?php foreach (Utils::$context['available_themes'] as $theme): ?>
<?php if (($theme['selected'] && $i == 0) || (!$theme['selected'] && $i == 1)): ?>
			<div class="title_bar">
				<h3 class="titlebg">
					<?= $theme['name'] ?>

				</h3>
			</div>
			<div>
				<small><i><?= $theme['description'] ?></i></small>
<?php if (!empty($theme['variants'])): ?>
				<label><strong><?= $theme['pick_label'] ?></strong>
					<select data-theme-id="<?= $theme['id'] ?>" name="vrt[<?= $theme['id'] ?>]">
<?php foreach ($theme['variants'] as $key => $variant): ?>
						<option value="<?= $key ?>"<?= $theme['selected_variant'] == $key ? ' selected' : '' ?> data-url="<?= $variant['thumbnail'] ?>"><?= $variant['label'] ?></option>
<?php endforeach; ?>
					</select>
				</label>
<?php endif; ?>
				<div>
					<input type="submit" name="save[<?= $theme['id'] ?>]" value="<?= Lang::getTxt('theme_set', file: 'Themes') ?>" class="button">
					<a class="button" href="<?= Config::$scripturl ?>?action=theme;sa=pick;theme=<?= $theme['id'] ?>" id="theme_preview_<?= $theme['id'] ?>"><?= Lang::getTxt('theme_preview', file: 'Themes') ?></a>
				</div>
			</div>
			<div>
				<a href="<?= Config::$scripturl ?>?action=theme;sa=pick;u=<?= Utils::$context['current_member'] ?>;theme=<?= $theme['id'] ?>;<?= Utils::$context['session_var'] ?>=<?= Utils::$context['session_id'] ?>" id="theme_thumb_preview_<?= $theme['id'] ?>" title="<?= Lang::getTxt('theme_preview', file: 'Themes') ?>">
					<img src="<?= $theme['thumbnail_href'] ?>" id="theme_thumb_<?= $theme['id'] ?>" alt="" class="padding theme_thumbnail">
				</a>
			</div>
<?php endif; ?>
<?php endforeach; ?>
		</div>
<?php endfor; ?>
<?php /* One set of these for the form, not one per pass of the loop above. */ ?>
		<input type="hidden" name="<?= Utils::$context['session_var'] ?>" value="<?= Utils::$context['session_id'] ?>">
		<input type="hidden" name="<?= Utils::$context['pick-th_token_var'] ?>" value="<?= Utils::$context['pick-th_token'] ?>">
		<input type="hidden" name="u" value="<?= Utils::$context['current_member'] ?>">
	</form>