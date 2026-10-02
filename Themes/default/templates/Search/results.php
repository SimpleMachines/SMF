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
 * The search results page.
 */
?><?php if (isset(Utils::$context['did_you_mean']) || empty(Utils::$context['topics']) || !empty(Utils::$context['search_ignored'])): ?>
	<div id="search_results">
		<div class="cat_bar">
			<h3 class="catbg">
				<?= Lang::getTxt('search_adjust_query', file: 'Search') ?>
			</h3>
		</div>
		<div class="roundframe"><?php /* Did they make any typos or mistakes, perhaps? */ ?><?php if (isset(Utils::$context['did_you_mean'])): ?>
			<p>
				<?= Lang::getTxt('search_did_you_mean', ['suggested_query' => '<a href="' . Config::$scripturl . '?action=search2;params=' . Utils::$context['did_you_mean_params'] . '">' . Utils::$context['did_you_mean'] . '</a>'], file: 'Search') ?>
			</p><?php endif; ?><?php if (!empty(Utils::$context['search_ignored'])): ?>
			<p>
				<?= Lang::getTxt(
				'search_warning_ignored',
				[
					'number_of_terms' => count(Utils::$context['search_ignored']),
					'list' => implode(Lang::getTxt('sentence_list_separator', file: 'General') . ' ', Utils::$context['search_ignored']),
				],
				file: 'Search',
			) ?>
			</p><?php endif; ?>
			<form action="<?= Config::$scripturl ?>?action=search2" method="post" accept-charset="UTF-8">
				<strong><?= Lang::getTxt('search_for', file: 'General') ?></strong>
				<input type="text" name="search"<?= !empty(Utils::$context['search_params']['search']) ? ' value="' . Utils::$context['search_params']['search'] . '"' : '' ?> maxlength="<?= Utils::$context['search_string_limit'] ?>" size="40">
				<input type="submit" name="edit_search" value="<?= Lang::getTxt('search_adjust_submit', file: 'Search') ?>" class="button"><?php foreach (Utils::$context['hidden_inputs'] as $input): ?><?= "\n\t\t\t\t" ?><?= $input ?><?php endforeach; ?>
			</form>
		</div><!-- .roundframe -->
	</div><!-- #search_results --><?php endif; ?><?php if (Utils::$context['compact']): ?>
	<form id="new_search" name="new_search" action="<?= Config::$scripturl ?>?action=search2" method="post" accept-charset="UTF-8">
		<input type="hidden" name="search"<?= !empty(Utils::$context['search_params']['search']) ? ' value="' . Utils::$context['search_params']['search'] . '"' : '' ?> maxlength="<?= Utils::$context['search_string_limit'] ?>" size="40"><?php foreach (Utils::$context['hidden_inputs'] as $input): ?><?= "\n\t\t" ?><?= $input ?><?php endforeach; ?>
	</form>
		<div id="display_head" class="information">
			<h2 class="display_title">
				<span><?= Lang::getTxt('search_results', ['params' => Utils::$context['search_params']['search']], file: 'General') ?></span>
			</h2>
			<div class="floatleft">
				<a class="button" href="<?= Config::$scripturl ?>?action=search;params=<?= Utils::$context['params'] ?>"><?= Lang::getTxt('search_adjust_query', file: 'Search') ?></a>
			</div><?php /* Was anything even found? */ ?><?php if (!empty(Utils::$context['topics'])): ?>
			<div class="floatright">
				<span class="padding"><?= Lang::getTxt('search_order', file: 'Search') ?></span>
				<select name="sort" class="floatright" form="new_search" onchange="document.forms.new_search.submit()"><?php foreach (Utils::$context['sort_options'] as $option): ?>
					<option value="<?= $option['value'] ?>"<?= ($option['selected'] ? ' selected' : '') ?>><?= Lang::getTxt($option['label'], file: 'Search') ?></option><?php endforeach; ?>
				</select>
			</div>
		</div>
		<div class="pagesection">
			<div class="pagelinks"><?= Utils::$context['page_index'] ?></div>
		</div><?php else: ?>
		</div>
		<div class="roundframe noup"><?= Lang::getTxt('search_no_results', file: 'General') ?></div><?php endif; ?><?php /* While we have results to show ... */ ?><?php while ($topic = Utils::$context['get_topics']()): ?>
		<div class="<?= $topic['css_class'] ?>"><?php foreach ($topic['matches'] as $message): ?>
			<div class="block">
				<div class="page_number floatright"> #<?= $message['counter'] ?></div>
				<div class="half_content">
					<div class="topic_details">
						<h5><?= $topic['board']['link'] ?> / <a href="<?= Config::$scripturl ?>?topic=<?= $topic['id'] ?>.msg<?= $message['id'] ?>#msg<?= $message['id'] ?>"><?= $message['subject_highlighted'] ?></a></h5>
						<span class="smalltext"><?= str_replace('<br>', ' ', Lang::getTxt('last_post_updated', ['time' => $message['time'], 'member_link' => '<strong>' . $message['member']['link'] . '</strong>'], file: 'General')) ?></span>
					</div>
				</div>
			</div><!-- .block --><?php if ($message['body_highlighted'] != ''): ?>
			<div class="list_posts word_break"><?= $message['body_highlighted'] ?></div><?php endif; ?><?php endforeach; ?>
		</div><!-- $topic[css_class] --><?php endwhile; ?><?php else: ?>
	<div id="display_head" class="information">
		<h2 class="display_title">
			<span><?= Lang::getTxt('search_results', ['params' => Utils::$context['search_params']['search']], file: 'General') ?></span>
		</h2>
		<div class="floatleft">
			<a class="button" href="<?= Config::$scripturl ?>?action=search;params=<?= Utils::$context['params'] ?>"><?= Lang::getTxt('search_adjust_query', file: 'Search') ?></a>
		</div><?php /* Was anything even found? */ ?><?php if (!empty(Utils::$context['topics'])): ?>
		<div class="floatright">
			<span class="padding"><?= Lang::getTxt('search_order', file: 'Search') ?></span>
			<select name="sort" class="floatright" form="new_search" onchange="document.forms.new_search.submit()"><?php foreach (Utils::$context['sort_options'] as $option): ?>
				<option value="<?= $option['value'] ?>"<?= ($option['selected'] ? ' selected' : '') ?>><?= Lang::getTxt($option['label'], file: 'Search') ?></option><?php endforeach; ?>
			</select>
		</div>
	</div>
	<div class="pagesection">
		<div class="pagelinks"><?= Utils::$context['page_index'] ?></div>
	</div><?php else: ?>
	</div>
	<div class="roundframe noup"><?= Lang::getTxt('search_no_results', file: 'General') ?></div><?php endif; ?><?php while ($topic = Utils::$context['get_topics']()): ?><?php foreach ($topic['matches'] as $message): ?>
	<div class="<?= $topic['css_class'] ?>">
		<div class="page_number floatright"> #<?= $message['counter'] ?></div>
		<div class="topic_details">
			<h5>
				<?= $topic['board']['link'] ?> / <a href="<?= Config::$scripturl ?>?topic=<?= $topic['id'] ?>.<?= $message['start'] ?>;topicseen#msg<?= $message['id'] ?>"><?= $message['subject_highlighted'] ?></a>
			</h5>
			<span class="smalltext"><?= str_replace('<br>', ' ', Lang::getTxt('last_post_topic', ['post_link' => $message['time'], 'member_link' => '<strong>' . $message['member']['link'] . '</strong>'], file: 'General')) ?></span>
		</div>
		<div class="list_posts"><?= $message['body_highlighted'] ?></div>
		<br class="clear">
	</div><!-- $topic[css_class] --><?php endforeach; ?><?php endwhile; ?><?php endif; ?>
	<div class="pagesection"><?php if (!empty(Utils::$context['topics'])): ?>
		<div class="pagelinks"><?= Utils::$context['page_index'] ?></div><?php endif; ?><?php /* Show a jump to box for easy navigation. */ ?>
		<div class="smalltext pagelinks floatright" id="search_jump_to"></div>
		<script>
			window.addEventListener("DOMContentLoaded", function() {
				new JumpTo({
					sContainerId: "search_jump_to",
					sJumpToTemplate: "<label class=\"smalltext jump_to\" for=\"%select_id%\"><?= Utils::$context['jump_to']['label'] ?><" + "/label> %dropdown_list%",
					iCurBoardId: 0,
					iCurBoardChildLevel: 0,
					sCurBoardName: "<?= Utils::$context['jump_to']['board_name'] ?>",
					sBoardChildLevelIndicator: "==",
					sBoardPrefix: "=> ",
					sCatSeparator: "-----------------------------",
					sCatPrefix: "",
					sGoButtonLabel: "<?= Lang::getTxt('quick_mod_go', file: 'General') ?>"
				});
			});
		</script>
	</div>