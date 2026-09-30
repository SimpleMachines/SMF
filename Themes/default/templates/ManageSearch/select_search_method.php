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
 * Select the search method.
 */
?>

	<div class="cat_bar">
		<h3 class="catbg"><?= Lang::getTxt('search_method', file: 'Admin') ?></h3>
	</div>
	<div class="information">
		<div class="smalltext">
			<a href="<?= Config::$scripturl ?>?action=helpadmin;help=search_why_use_index" onclick="return reqOverlayDiv(this.href);"><?= Lang::getTxt('search_create_index_why', file: 'Search') ?></a>
		</div>
	</div>
	<form id="admin_form_wrapper" action="<?= Config::$scripturl ?>?action=admin;area=managesearch;sa=method" method="post" accept-charset="UTF-8">
		<div class="cat_bar">
			<h3 class="catbg"><?= Lang::getTxt('search_method', file: 'Admin') ?></h3>
		</div>
		<div class="windowbg">
			<dl class="settings"><?php if (!empty(Utils::$context['table_info'])): ?>

				<dt>
					<strong><?= Lang::getTxt('search_method_messages_table_space', file: 'Search') ?></strong>
				</dt>
				<dd>
					<?= Utils::$context['table_info']['data_length'] ?>

				</dd>
				<dt>
					<strong><?= Lang::getTxt('search_method_messages_index_space', file: 'Search') ?></strong>
				</dt>
				<dd>
					<?= Utils::$context['table_info']['index_length'] ?>

				</dd><?php endif; ?>

			</dl>
			<?= Utils::$context['double_index'] ? '<div class="noticebox">
			' . Lang::getTxt('search_double_index', file: 'Search') . '</div>' : '' ?>

			<fieldset class="search_settings floatleft">
				<legend><?= Lang::getTxt('search_index', file: 'Search') ?></legend>
				<dl>
					<dt>
						<label>
							<input type="radio" name="search_index" value=""<?= empty(Config::$modSettings['search_index']) ? ' checked' : '' ?>>
							<?= Lang::getTxt('search_index_none', file: 'Search') ?>

						</label>
					</dt><?php foreach (Utils::$context['search_apis'] as $api): ?><?php if ($api['has_template'] || $api['instance']->getStatus() === 'hidden'): ?><?php continue; ?><?php endif; ?>

					<hr>
					<dt>
						<label>
							<input type="radio" name="search_index" value="<?= $api['setting_index'] ?>"<?= !empty(Config::$modSettings['search_index']) && Config::$modSettings['search_index'] == $api['setting_index'] ? ' checked' : '' ?><?= !in_array($api['instance']->getStatus(), [null, 'exists']) ? ' onclick="alert(\'' . Lang::getTxt('search_index_custom_warning', file: 'Search') . '\'); return false;"' : '' ?>>
							<?= Lang::txtExists($api['instance']->getLabel(), file: 'Search') ? Lang::getTxt($api['instance']->getLabel(), file: 'Search') : Lang::getTxt('search_index_generic', ['index' => substr(strrchr($api['class'], '\\'), 1)], file: 'Search') ?>

						</label>
					</dt>
					<dd>
						<span class="smalltext"><?php if (Lang::txtExists($api['instance']->getDescription(), file: 'Search')): ?><?= Lang::getTxt($api['instance']->getDescription(), file: 'Search') ?><?php endif; ?><?php if ($api['instance']->getStatus() !== null): ?><?php if (Lang::txtExists($api['instance']->getDescription(), file: 'Search')): ?>

						<br><?php endif; ?><?php
$admin_subactions = $api['instance']->getAdminSubactions();

switch ($api['instance']->getStatus()) {
				case 'exists':
					$index_status = 'search_method_index_already_exists';
					$buttons = [];

					$sa = $admin_subactions['remove']['sa'];

					foreach ($admin_subactions['remove']['extra_params'] as $key => $value) {
						$sa .= ';' . (is_int($key) ? '' : $key . '=') . $value;
					}

					$buttons[$sa] = 'search_index_remove';
					break;

				case 'partial':
					$index_status = 'search_method_index_partial';
					$buttons = [];

					foreach (['resume', 'remove'] as $type) {
						$sa = $admin_subactions[$type]['sa'];

						foreach ($admin_subactions[$type]['extra_params'] as $key => $value) {
							$sa .= ';' . (is_int($key) ? '' : $key . '=') . $value;
						}

						$buttons[$sa] = 'search_index_' . $type;
					}
					break;

				default:
					$index_status = 'search_method_no_index_exists';
					$buttons = [];

					$sa = $admin_subactions['build']['sa'];

					foreach ($admin_subactions['build']['extra_params'] as $key => $value) {
						$sa .= ';' . (is_int($key) ? '' : $key . '=') . $value;
					}

					$buttons[$sa] = 'search_create_index_start';
					break;
			}
?><?= Lang::getTxt(
	$index_status,
	[
		'index' => Lang::getTxt(
			Lang::txtExists($api['instance']->getLabel(), file: 'Search') ? $api['instance']->getLabel() : 'search_index_generic',
			[
				'index' => substr(strrchr($api['instance']::class, '\\'), 1),
			],
			file: 'Search',
		),
	],
	file: 'Search',
) ?><?php foreach ($buttons as $sa => $label): ?> <a href="<?= Config::$scripturl ?>?action=admin;area=managesearch;sa=<?= $sa ?>" class="button"><?= Lang::getTxt($label, file: 'Search') ?></a><?php endforeach; ?><?php endif; ?><?php
if (
			in_array($api['instance']->getStatus(), ['exists', null])
			&& !empty($api['instance']->getSize())
		):
?>

						<br>
						<strong><?= Lang::getTxt('search_index_size', file: 'Search') ?></strong> <?= Lang::getTxt('size_kilobyte', [$api['instance']->getSize() / 1024], file: 'General') ?><?php endif; ?>

						</span>
					</dd><?php endforeach; ?>

				</dl>
			</fieldset>
			<fieldset class="search_settings floatright">
			<legend><?= Lang::getTxt('search_method', file: 'Admin') ?></legend>
				<input type="checkbox" name="search_force_index" id="search_force_index_check" value="1"<?= empty(Config::$modSettings['search_force_index']) ? '' : ' checked' ?>><label for="search_force_index_check"><?= Lang::getTxt('search_force_index', file: 'Search') ?></label><br>
				<input type="checkbox" name="search_match_words" id="search_match_words_check" value="1"<?= empty(Config::$modSettings['search_match_words']) ? '' : ' checked' ?>><label for="search_match_words_check"><?= Lang::getTxt('search_match_words', file: 'Search') ?></label>
			</fieldset>
			<br class="clear">
			<input type="submit" name="save" value="<?= Lang::getTxt('search_method_save', file: 'Search') ?>" class="button">
			<input type="hidden" name="<?= Utils::$context['session_var'] ?>" value="<?= Utils::$context['session_id'] ?>">
			<input type="hidden" name="<?= Utils::$context['admin-msmpost_token_var'] ?>" value="<?= Utils::$context['admin-msmpost_token'] ?>">
		</div><!-- .windowbg -->
	</form>