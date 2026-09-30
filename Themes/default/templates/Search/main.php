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
 * The main search form
 */
?>

	<form action="<?= Config::$scripturl ?>?action=search2" method="post" accept-charset="UTF-8" name="searchform" id="searchform">
<?php if (!empty(Utils::$context['search_errors'])): ?>
		<div class="errorbox">
			<?= implode('<br>', Utils::$context['search_errors']['messages']) ?>

		</div>
<?php endif; ?>
<?php if (!empty(Utils::$context['search_ignored'])): ?>
		<div class="noticebox">
			<?= Lang::getTxt(
			'search_warning_ignored',
			[
				'number_of_terms' => count(Utils::$context['search_ignored']),
				'list' => implode(Lang::getTxt('sentence_list_separator', file: 'General') . ' ', Utils::$context['search_ignored']),
			],
			file: 'Search',
		) ?>

		</div>
<?php endif; ?>
		<div class="cat_bar">
			<h3 class="catbg">
				<span class="main_icons filter"></span><?= Lang::getTxt('set_parameters', file: 'Search') ?>

			</h3>
		</div>
		<div id="advanced_search" class="roundframe">
			<dl class="settings" id="search_options">
				<dt>
					<strong><label for="searchfor"><?= Lang::getTxt('search_for', file: 'General') ?></label></strong>
				</dt>
				<dd>
					<input type="search" name="search" id="searchfor" <?= !empty(Utils::$context['search_params']['search']) ? ' value="' . Utils::$context['search_params']['search'] . '"' : '' ?> maxlength="<?= Utils::$context['search_string_limit'] ?>" size="40">
<?php if (empty(Config::$modSettings['search_simple_fulltext'])): ?>
					<br><em class="smalltext"><?= Lang::getTxt('search_example', file: 'Search') ?></em>
<?php endif; ?>
				</dd>

				<dt>
					<label for="searchtype"><?= Lang::getTxt('search_match', file: 'General') ?></label>
				</dt>
				<dd>
					<select name="searchtype" id="searchtype">
						<option value="1"<?= empty(Utils::$context['search_params']['searchtype']) ? ' selected' : '' ?>><?= Lang::getTxt('all_words', file: 'Search') ?></option>
						<option value="2"<?= !empty(Utils::$context['search_params']['searchtype']) ? ' selected' : '' ?>><?= Lang::getTxt('any_words', file: 'Search') ?></option>
					</select>
				</dd>
				<dt>
					<label for="userspec"><?= Lang::getTxt('by_user', file: 'Search') ?></label>
				</dt>
				<dd>
					<input id="userspec" type="text" name="userspec" value="<?= empty(Utils::$context['search_params']['userspec']) ? '*' : Utils::$context['search_params']['userspec'] ?>" size="40">
				</dd>
				<dt>
					<label for="sort"><?= Lang::getTxt('search_order', file: 'Search') ?></label>
				</dt>
				<dd>
					<select id="sort" name="sort">
						<option value="relevance|desc"><?= Lang::getTxt('search_orderby_relevant_first', file: 'Search') ?></option>
						<option value="num_replies|desc"><?= Lang::getTxt('search_orderby_large_first', file: 'Search') ?></option>
						<option value="num_replies|asc"><?= Lang::getTxt('search_orderby_small_first', file: 'Search') ?></option>
						<option value="id_msg|desc"><?= Lang::getTxt('search_orderby_recent_first', file: 'Search') ?></option>
						<option value="id_msg|asc"><?= Lang::getTxt('search_orderby_old_first', file: 'Search') ?></option>
					</select>
				</dd>
				<dt class="righttext options"><?= Lang::getTxt('search_options', file: 'Search') ?>

				</dt>
				<dd class="options">
					<ul>
<?php foreach (Utils::$context['search_options'] as $option): ?>
						<li>
							<label>
								<?= $option['html'] ?>

								<span><?= Lang::getTxt($option['label'], file: 'Search') ?></span>
							</label>
						</li>
<?php endforeach; ?>
					</ul>
				</dd>
				<dt class="between"><?= Lang::getTxt('search_post_age', file: 'Search') ?>

				</dt>
				<dd>
					<?= Lang::getTxt(
						'search_age_range',
						[
							'min' => '<input type="number" name="minage" id="minage" min="0" max="9999" value="' . (empty(Utils::$context['search_params']['minage']) ? '0' : Utils::$context['search_params']['minage']) . '">',
							'max' => '<input type="number" name="maxage" id="maxage" min="0" max="9999" value="' . (empty(Utils::$context['search_params']['maxage']) ? '9999' : Utils::$context['search_params']['maxage']) . '">',
						],
						file: 'Search',
					) ?>

				</dd>
			</dl>
			<script>
				window.addEventListener("load", initSearch, false);
			</script>
			<input type="hidden" name="advanced" value="1">
<?php /* Require an image to be typed to save spamming? */ ?>
<?php if (Utils::$context['require_verification']): ?>
			<p>
				<strong><?= Lang::getTxt('verification', file: 'General') ?></strong>
				<?php $this->subTemplate('control_verification', ['verify_id' => Utils::$context['visual_verification_id'], 'display_type' => 'all']); ?>

			</p>
<?php endif; ?>
<?php /* If Utils::$context['search_params']['topic'] is set, that means we're searching just one topic. */ ?>
<?php if (!empty(Utils::$context['search_params']['topic'])): ?>
			<p>
				<?= Lang::getTxt('search_specific_topic', ['topic' => Utils::$context['search_topic']['link']], file: 'Search') ?>

			</p>
			<input type="hidden" name="topic" value="<?= Utils::$context['search_topic']['id'] ?>">
			<input type="submit" name="b_search" value="<?= Lang::getTxt('search', file: 'General') ?>" class="button">
<?php endif; ?>
		</div>
<?php if (empty(Utils::$context['search_params']['topic'])): ?>
		<fieldset class="flow_hidden">
			<div class="roundframe alt">
				<details id="advanced_panel"<?= !empty(Utils::$context['boards_check_all']) ? '' : ' open' ?>>
					<summary class="title_bar titlebg"><?= Lang::getTxt('choose_board', file: 'Search') ?></summary>
					<div class="flow_auto boardslist">
						<ul>
<?php foreach (Utils::$context['categories'] as $category): ?>
						<li>
							<a href="javascript:void(0);" onclick="selectBoards([<?= !empty($category['child_ids']) ? implode(', ', $category['child_ids']) : '' ?>], 'searchform'); return false;"><?= $category['name'] ?></a>
							<ul>
<?php $cat_boards = array_values($category['children']); ?>
<?php foreach ($cat_boards as $key => $board): ?>
								<li>
									<label for="brd<?= $board['id'] ?>">
										<input type="checkbox" id="brd<?= $board['id'] ?>" name="brd[<?= $board['id'] ?>]" value="<?= $board['id'] ?>"<?= $board['selected'] ? ' checked' : '' ?>>
										<?= $board['name'] ?>

									</label>
<?php
// Nest child boards inside another list.
$curr_child_level = $board['child_level'];
$next_child_level = $cat_boards[$key + 1]['child_level'] ?? 0;
?>
<?php if ($next_child_level > $curr_child_level): ?>
									<ul style="margin-inline-start: 2.5ch;">
<?php else: ?>
<?php
// Close child board lists until we reach a common level
// with the next board.
?>
<?php while ($next_child_level < $curr_child_level--): ?>
										</li>
									</ul>
<?php endwhile; ?>
								</li>
<?php endif; ?>
<?php endforeach; ?>
							</ul>
						</li>
<?php endforeach; ?>
						</ul>
					</div>
				</details>
				<br class="clear">
				<div class="padding">
					<input type="checkbox" name="all" id="check_all" value=""<?= !empty(Utils::$context['boards_check_all']) ? ' checked' : '' ?> onclick="invertAll(this, this.form, 'brd');">
					<label for="check_all"><em><?= Lang::getTxt('check_all', file: 'General') ?></em></label>
					<input type="submit" name="b_search" value="<?= Lang::getTxt('search', file: 'General') ?>" class="button floatright">
				</div>
			</div><!-- .roundframe -->
		</fieldset>
<?php endif; ?>
	</form>
	<script>
		var oAddMemberSuggest = new smc_AutoSuggest({
			sSessionId: smf_session_id,
			sSessionVar: smf_session_var,
			sControlId: 'userspec',
			sSearchType: 'member'
		});
	</script>