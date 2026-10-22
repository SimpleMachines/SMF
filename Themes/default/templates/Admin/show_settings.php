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

use SMF\Actions\Admin\Permissions;
use SMF\Config;
use SMF\Lang;
use SMF\Theme;
use SMF\Utils;

if (!defined('SMF')) {
	die('No direct access...');
}

/*
 * Template for showing settings (Of any kind really!)
 */
?><?php if (!empty(Utils::$context['saved_successful'])): ?>
					<div class="infobox"><?= Lang::getTxt('settings_saved', file: 'Admin') ?></div><?php elseif (!empty(Utils::$context['saved_failed'])): ?>
					<div class="errorbox"><?= Lang::getTxt('settings_not_saved', ['reason' => Utils::$context['saved_failed']], file: 'Admin') ?></div><?php endif; ?><?php if (!empty(Utils::$context['settings_pre_javascript'])): ?>
					<script><?= Utils::$context['settings_pre_javascript'] ?></script><?php endif; ?><?php if (!empty(Utils::$context['settings_insert_above'])): ?><?= Utils::$context['settings_insert_above'] ?><?php endif; ?>
						<form id="admin_form_wrapper" action="<?= Utils::$context['post_url'] ?>" method="post" accept-charset="UTF-8"<?= !empty(Utils::$context['force_form_onsubmit']) ? ' onsubmit="' . Utils::$context['force_form_onsubmit'] . '"' : '' ?>><?php /* Is there a custom title? */ ?><?php if (isset(Utils::$context['settings_title'])): ?>
							<div class="cat_bar">
								<h3 class="catbg"><?= Utils::$context['settings_title'] ?></h3>
							</div><?php endif; ?><?php /* Have we got a message to display? */ ?><?php if (!empty(Utils::$context['settings_message'])): ?><?php $tag = !empty(Utils::$context['settings_message']['tag']) ? Utils::$context['settings_message']['tag'] : 'span'; ?>
							<div class="information noup"><?php if (is_array(Utils::$context['settings_message'])): ?>
								<<?= $tag ?><?= !empty(Utils::$context['settings_message']['class']) ? ' class="' . Utils::$context['settings_message']['class'] . '"' : '' ?>>
									<?= Utils::$context['settings_message']['label'] ?>
								</<?= $tag ?>><?php else: ?><?= Utils::$context['settings_message'] ?><?php endif; ?>
							</div><?php endif; ?><?php
// Filter out any redundant separators before we start the loop
Utils::$context['config_vars'] = array_filter(
	Utils::$context['config_vars'],
	function ($v) {
			static $config_vars, $prev;

			$at_start = is_null($config_vars);
			$config_vars = $at_start ? Utils::$context['config_vars'] : $config_vars;

			$next = next($config_vars);
			$at_end = key($config_vars) === null;

			if (!$at_start && !$at_end) {
				$div_types = ['title', 'desc'];
				$at_start = isset($prev['type']) && in_array($prev['type'], $div_types);
				$at_end = isset($next['type']) && in_array($next['type'], $div_types);
			}

			$prev = $v;

			return ($v === '' && ($at_start || $at_end || $v === $next)) ? false : true;
		},
);
// Now actually loop through all the variables.
$is_open = false;
?><?php foreach (Utils::$context['config_vars'] as $config_var): ?><?php /* Is it a title or a description? */ ?><?php if (is_array($config_var) && ($config_var['type'] == 'title' || $config_var['type'] == 'desc')): ?><?php /* Not a list yet? */ ?><?php if ($is_open): ?><?php $is_open = false; ?>
									</dl>
							</div><?php endif; ?><?php /* A title? */ ?><?php if ($config_var['type'] == 'title'): ?>
							<div class="cat_bar">
								<h3 class="<?= !empty($config_var['class']) ? $config_var['class'] : 'catbg' ?>"<?= !empty($config_var['force_div_id']) ? ' id="' . $config_var['force_div_id'] . '"' : '' ?>>
									<?= ($config_var['help'] ? '<a href="' . Config::$scripturl . '?action=helpadmin;help=' . $config_var['help'] . '" onclick="return reqOverlayDiv(this.href);" class="help"><span class="main_icons help" title="' . Lang::getTxt('help', file: 'General') . '"></span></a>' : '') ?>
									<?= $config_var['label'] ?>
								</h3>
							</div><?php /* A description? */ ?><?php else: ?>
							<div class="information noup">
								<?= $config_var['label'] ?>
							</div><?php endif; ?><?php continue; ?><?php endif; ?><?php /* Not a list yet? */ ?><?php if (!$is_open): ?><?php $is_open = true; ?>
							<div class="windowbg noup">
								<dl class="settings"><?php endif; ?><?php /* Hang about? Are you pulling my leg - a callback?! */ ?><?php if (is_array($config_var) && $config_var['type'] == 'callback'): ?><?php if ($this->hasSubTemplate('callback_' . $config_var['name'])): ?><?php $this->subTemplate('callback_' . $config_var['name']); ?><?php endif; ?><?php continue; ?><?php endif; ?><?php if (is_array($config_var)): ?><?php /* First off, is this a span like a message? */ ?><?php if (in_array($config_var['type'], ['message', 'warning'])): ?>
									<dd<?= $config_var['type'] == 'warning' ? ' class="alert"' : '' ?><?= (!empty($config_var['force_div_id']) ? ' id="' . $config_var['force_div_id'] . '_dd"' : '') ?>>
										<?= $config_var['label'] ?>
									</dd><?php /* Otherwise it's an input box of some kind. */ ?><?php else: ?>
									<dt<?= is_array($config_var) && !empty($config_var['force_div_id']) ? ' id="' . $config_var['force_div_id'] . '"' : '' ?>><?php
// Some quick helpers...
$javascript = $config_var['javascript'];
$disabled = !empty($config_var['disabled']) ? ' disabled' : '';
$subtext = !empty($config_var['subtext']) ? '<br><span class="smalltext"> ' . $config_var['subtext'] . '</span>' : '';
$placeholder = !empty($config_var['placeholder']) ? ' placeholder="' . $config_var['placeholder'] . '"' : '';
// Various HTML5 input types that are basically enhanced textboxes
$text_types = ['color', 'date', 'datetime', 'datetime-local', 'email', 'month', 'time'];
// Show the [?] button.
?><?php if ($config_var['help']): ?>
										<a id="setting_<?= $config_var['name'] ?>_help" href="<?= Config::$scripturl ?>?action=helpadmin;help=<?= $config_var['help'] ?>" onclick="return reqOverlayDiv(this.href);"><span class="main_icons help" title="<?= Lang::getTxt('help', file: 'General') ?>"></span></a> <?php endif; ?>
										<a id="setting_<?= $config_var['name'] ?>"></a> <span<?= ($config_var['disabled'] ? ' style="color: #777777;"' : ($config_var['invalid'] ? ' class="error"' : '')) ?>><label<?= ($config_var['type'] == 'boards' || $config_var['type'] == 'permissions' ? '' : ' for="' . $config_var['name'] . '"') ?>><?= $config_var['label'] ?></label><?= $subtext ?><?= ($config_var['type'] == 'password' ? '<br><em>' . Lang::getTxt('admin_confirm_password', file: 'Admin') . '</em>' : '') ?></span>
									</dt>
									<dd<?= (!empty($config_var['force_div_id']) ? ' id="' . $config_var['force_div_id'] . '_dd"' : '') ?>><?= $config_var['preinput'] ?><?php /* Show a check box. */ ?><?php if ($config_var['type'] == 'check'): ?>
										<input type="checkbox"<?= $javascript ?><?= $disabled ?> name="<?= $config_var['name'] ?>" id="<?= $config_var['name'] ?>"<?= ($config_var['value'] ? ' checked' : '') ?> value="1"><?php /* Escape (via htmlspecialchars.) the text box. */ ?><?php elseif ($config_var['type'] == 'password'): ?>
										<input type="password"<?= $disabled ?><?= $javascript ?> name="<?= $config_var['name'] ?>[0]"<?= ($config_var['size'] ? ' size="' . $config_var['size'] . '"' : '') ?> value="*#fakepass#*" onfocus="this.value = ''; this.form.<?= $config_var['name'] ?>.disabled = false;"><br>
										<input type="password" disabled id="<?= $config_var['name'] ?>" name="<?= $config_var['name'] ?>[1]"<?= ($config_var['size'] ? ' size="' . $config_var['size'] . '"' : '') ?>><?php /* Show a selection box. */ ?><?php elseif ($config_var['type'] == 'select'): ?><?php if ($config_var['name'] === 'default_timezone'): ?><?php $this->subTemplate('timezone_select', ['name' => $config_var['name'], 'selection' => $config_var['value'] ?? '', 'disabled' => (int) $disabled]); ?><?php else: ?>
										<select name="<?= $config_var['name'] ?>" id="<?= $config_var['name'] ?>" <?= $javascript ?><?= $disabled ?><?= (!empty($config_var['multiple']) ? ' multiple' : '') ?> size="<?= $config_var['size'] ?>"><?php foreach ($config_var['data'] as $option): ?>
											<option value="<?= $option[0] ?>"<?= (!empty($config_var['value']) && ($option[0] == $config_var['value'] || (!empty($config_var['multiple']) && in_array($option[0], $config_var['value']))) ? ' selected' : '') ?>><?= $option[1] ?></option><?php endforeach; ?>
										</select><?php endif; ?><?php /* Show a color box */ ?><?php elseif ($config_var['type'] == 'color'): ?>
										<input name="<?= $config_var['name'] ?>" id="<?= $config_var['name'] ?>" data-coloris value="<?= $config_var['value'] ?>"><?php /* List of boards? This requires getBoardList() having been run and the results in Utils::$context['board_list']. */ ?><?php elseif ($config_var['type'] == 'boards'): ?><?php $first = true; ?>
										<a href="#" class="board_selector">[ <?= Lang::getTxt('select_boards_from_list', file: 'ManageSettings') ?> ]</a>
										<fieldset>
											<legend class="board_selector">
												<a href="#"><?= Lang::getTxt('select_boards_from_list', file: 'ManageSettings') ?></a>
											</legend><?php foreach (Utils::$context['board_list'] as $id_cat => $cat): ?><?php if (!$first): ?>
											<hr><?php endif; ?>
											<strong><?= $cat['name'] ?></strong>
											<ul><?php foreach ($cat['boards'] as $id_board => $brd): ?>
												<li><label><input type="checkbox" name="<?= $config_var['name'] ?>[<?= $brd['id'] ?>]" value="1"<?= in_array($brd['id'], $config_var['value']) ? ' checked' : '' ?>> <?= $brd['child_level'] > 0 ? str_repeat('&nbsp; &nbsp;', $brd['child_level']) : '' ?><?= $brd['name'] ?></label></li><?php endforeach; ?>
											</ul><?php $first = false; ?><?php endforeach; ?>
											<hr />
											<input type="checkbox" onclick="invertAll(this, this.form, '<?= $config_var['name'] ?>[');">
											<span><?= Lang::getTxt('check_all', file: 'General') ?></span>
										</fieldset><?php /* Text area? */ ?><?php elseif ($config_var['type'] == 'large_text'): ?>
										<textarea rows="<?= (!empty($config_var['size']) ? $config_var['size'] : (!empty($config_var['rows']) ? $config_var['rows'] : 4)) ?>" cols="<?= (!empty($config_var['cols']) ? $config_var['cols'] : 30) ?>" <?= $javascript ?><?= $disabled ?> name="<?= $config_var['name'] ?>" id="<?= $config_var['name'] ?>"><?= $config_var['value'] ?></textarea><?php /* Permission group? */ ?><?php elseif ($config_var['type'] == 'permissions'): ?><?php
Permissions::theme_inline_permissions($config_var['name']);
// BBC selection?
?><?php elseif ($config_var['type'] == 'bbc'): ?>
										<fieldset id="<?= $config_var['name'] ?>">
											<legend><?= Utils::$context['bbc_sections'][$config_var['name']]['title'] ?></legend>
											<ul><?php foreach (Utils::$context['bbc_sections'][$config_var['name']]['columns'] as $bbcColumn): ?><?php foreach ($bbcColumn as $bbcTag): ?>
												<li class="list_bbc floatleft">
													<input type="checkbox" name="<?= $config_var['name'] ?>_enabledTags[]" id="tag_<?= $config_var['name'] ?>_<?= $bbcTag['tag'] ?>" value="<?= $bbcTag['tag'] ?>"<?= !in_array($bbcTag['tag'], Utils::$context['bbc_sections'][$config_var['name']]['disabled']) ? ' checked' : '' ?><?= in_array($bbcTag['tag'], Utils::$context['bbc_sections'][$config_var['name']]['forced']) ? ' disabled' : '' ?>> <label for="tag_<?= $config_var['name'] ?>_<?= $bbcTag['tag'] ?>"><?= $bbcTag['tag'] ?></label><?= $bbcTag['show_help'] ? ' <a href="' . Config::$scripturl . '?action=helpadmin;help=tag_' . $bbcTag['tag'] . '" onclick="return reqOverlayDiv(this.href);" class="main_icons help"></a>' : '' ?>
												</li><?php endforeach; ?><?php endforeach; ?>					</ul>
											<input type="checkbox" id="bbc_<?= $config_var['name'] ?>_select_all" onclick="invertAll(this, this.form, '<?= $config_var['name'] ?>_enabledTags');"<?= Utils::$context['bbc_sections'][$config_var['name']]['all_selected'] ? ' checked' : '' ?>> <label for="bbc_<?= $config_var['name'] ?>_select_all"><em><?= Lang::getTxt('enabled_bbc_select_all', file: 'Admin') ?></em></label>
										</fieldset><?php /* A simple message? */ ?><?php elseif ($config_var['type'] == 'var_message'): ?>
										<div<?= !empty($config_var['name']) ? ' id="' . $config_var['name'] . '"' : '' ?>>
											<?= $config_var['var_message'] ?>
										</div><?php /* Assume it must be a text box */ ?><?php else: ?><?php
// Figure out the exact type - use "number" for "float" and "int".
$type = in_array($config_var['type'], $text_types) ? $config_var['type'] : ($config_var['type'] == 'int' || $config_var['type'] == 'float' ? 'number' : 'text');
// Extra options for float/int values - how much to decrease/increase by, the min value and the max value
// The step - only set if incrementing by something other than 1 for int or 0.1 for float
$step = isset($config_var['step']) ? ' step="' . $config_var['step'] . '"' : ($config_var['type'] == 'float' ? ' step="0.1"' : '');
// Minimum allowed value for this setting. SMF forces a default of 0 if not specified in the settings
$min = isset($config_var['min']) ? ' min="' . $config_var['min'] . '"' : '';
// Maximum allowed value for this setting.
$max = isset($config_var['max']) ? ' max="' . $config_var['max'] . '"' : '';
// Some input fields allow multiple.
$multiple = $type === 'email' && (!empty($config_var['multiple']) ? ' multiple' : '');
?>
										<input type="<?= $type ?>"<?= $javascript ?><?= $disabled ?> name="<?= $config_var['name'] ?>" id="<?= $config_var['name'] ?>" value="<?= $config_var['value'] ?>"<?= ($config_var['size'] ? ' size="' . $config_var['size'] . '"' : '') ?><?= $min ?><?= $max ?><?= $step ?><?= $multiple ?><?= $placeholder ?>><?php endif; ?><?= isset($config_var['postinput']) ? '
											' . $config_var['postinput'] : '' ?>
									</dd><?php endif; ?><?php else: ?><?php /* Just show a separator. */ ?><?php if ($config_var == ''): ?>
								</dl>
								<hr>
								<dl class="settings"><?php else: ?>
									<dt>
										<strong><?= $config_var ?></strong>
									</dt>
									<dd></dd><?php endif; ?><?php endif; ?><?php endforeach; ?><?php if ($is_open): ?>
								</dl><?php endif; ?><?php if (empty(Utils::$context['settings_save_dont_show'])): ?>
								<input type="submit" value="<?= Lang::getTxt('save', file: 'General') ?>"<?= (!empty(Utils::$context['save_disabled']) ? ' disabled' : '') ?><?= (!empty(Utils::$context['settings_save_onclick']) ? ' onclick="' . Utils::$context['settings_save_onclick'] . '"' : '') ?> class="button"><?php endif; ?><?php if ($is_open): ?>
							</div><!-- .windowbg --><?php endif; ?><?php /* At least one token has to be used! */ ?><?php if (isset(Utils::$context['admin-ssc_token'])): ?>
							<input type="hidden" name="<?= Utils::$context['admin-ssc_token_var'] ?>" value="<?= Utils::$context['admin-ssc_token'] ?>"><?php endif; ?><?php if (isset(Utils::$context['admin-dbsc_token'])): ?>
							<input type="hidden" name="<?= Utils::$context['admin-dbsc_token_var'] ?>" value="<?= Utils::$context['admin-dbsc_token'] ?>"><?php endif; ?><?php if (isset(Utils::$context['admin-mp_token'])): ?>
							<input type="hidden" name="<?= Utils::$context['admin-mp_token_var'] ?>" value="<?= Utils::$context['admin-mp_token'] ?>"><?php endif; ?>
							<input type="hidden" name="<?= Utils::$context['session_var'] ?>" value="<?= Utils::$context['session_id'] ?>">
						</form><?php if (!empty(Utils::$context['settings_post_javascript'])): ?>
					<script>
						<?= Utils::$context['settings_post_javascript'] ?>

					</script><?php endif; ?><?php if (!empty(Utils::$context['settings_insert_below'])): ?><?= Utils::$context['settings_insert_below'] ?><?php endif; ?><?php
// We may have added a board listing. If we did, we need to make it work.
Theme::addInlineJavascript('
		$("legend.board_selector").closest("fieldset").hide();
		$("a.board_selector").click(function(e) {
			e.preventDefault();
			$(this).hide().next("fieldset").show();
		});
		$("fieldset legend.board_selector a").click(function(e) {
			e.preventDefault();
			$(this).closest("fieldset").hide().prev("a").show();
		});
	', true);
?>
