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
 * Form for stopping people using naughty words, etc.
 */
?><?php if (!empty(Utils::$context['saved_successful'])): ?>
					<div class="infobox"><?= Lang::getTxt('settings_saved', file: 'Admin') ?></div><?php endif; ?><?php /* First section is for adding/removing words from the censored list. */ ?>
						<form id="admin_form_wrapper" action="<?= Config::$scripturl ?>?action=admin;area=postsettings;sa=censor" method="post" accept-charset="UTF-8">
							<div id="section_header" class="cat_bar">
								<h3 class="catbg">
									<?= Lang::getTxt('admin_censored_words', file: 'Admin') ?>
								</h3>
							</div>
							<div class="windowbg">
								<p><?= Lang::getTxt('admin_censored_where', file: 'Admin') ?></p><?php /* Show text boxes for censoring [bad   ] => [good  ]. */ ?><?php foreach (Utils::$context['censored_words'] as $vulgar => $proper): ?>
								<div class="block">
									<input type="text" name="censor_vulgar[]" value="<?= $vulgar ?>" size="30"> =&gt; <input type="text" name="censor_proper[]" value="<?= $proper ?>" size="30">
								</div><?php endforeach; ?><?php /* Now provide a way to censor more words. */ ?>
								<div class="block">
									<input type="text" name="censor_vulgar[]" size="30"> =&gt; <input type="text" name="censor_proper[]" size="30">
								</div>
								<div id="moreCensoredWords"></div>
								<div class="block hidden" id="moreCensoredWords_link">
									<a class="button" href="#" onclick="addNewWord(); return false;"><?= Lang::getTxt('censor_clickadd', file: 'Admin') ?></a><br>
								</div>
								<script>
									document.getElementById("moreCensoredWords_link").classList.remove('hidden');
								</script>
								<hr>
								<dl class="settings">
									<dt>
										<strong><label for="allow_no_censored"><?= Lang::getTxt('allow_no_censored', file: 'Themes') ?></label></strong>
									</dt>
									<dd>
										<input type="checkbox" name="allow_no_censored" value="1" id="allow_no_censored"<?= empty(Config::$modSettings['allow_no_censored']) ? '' : ' checked' ?>>
									</dd>
									<dt>
										<strong><label for="censorWholeWord_check"><?= Lang::getTxt('censor_whole_words', file: 'Admin') ?></label></strong>
									</dt>
									<dd>
										<input type="checkbox" name="censorWholeWord" value="1" id="censorWholeWord_check"<?= empty(Config::$modSettings['censorWholeWord']) ? '' : ' checked' ?>>
									</dd>
									<dt>
										<strong><label for="censorIgnoreCase_check"><?= Lang::getTxt('censor_case', file: 'Admin') ?></label></strong>
									</dt>
									<dd>
										<input type="checkbox" name="censorIgnoreCase" value="1" id="censorIgnoreCase_check"<?= empty(Config::$modSettings['censorIgnoreCase']) ? '' : ' checked' ?>>
									</dd>
									<dt>
										<a id="spoofdetector_censor_help" href="<?= Config::$scripturl ?>?action=helpadmin;help=spoofdetector_censor" onclick="return reqOverlayDiv(this.href);"><span class="main_icons help" title="<?= Lang::getTxt('help', file: 'General') ?>"></span></a>
										<strong><label for="spoofdetector_censor_check"><?= Lang::getTxt('spoofdetector_censor', file: 'Admin') ?></label></strong>
										<br>
										<span class="smalltext"><?= Lang::getTxt('spoofdetector_censor_desc', file: 'Admin') ?></span>

									</dt>
									<dd>
										<input type="checkbox" name="spoofdetector_censor" value="1" id="spoofdetector_censor_check"<?= empty(Config::$modSettings['spoofdetector_censor']) ? '' : ' checked' ?>>
									</dd>
								</dl>
								<input type="submit" name="save_censor" value="<?= Lang::getTxt('save', file: 'General') ?>" class="button">
							</div><!-- .windowbg --><?php /* This table lets you test out your filters by typing in rude words and seeing what comes out. */ ?>
							<div class="cat_bar">
								<h3 class="catbg">
									<?= Lang::getTxt('censor_test', file: 'Admin') ?>
								</h3>
							</div>
							<div class="windowbg">
								<p class="centertext">
									<input type="text" name="censortest" value="<?= empty(Utils::$context['censor_test']) ? '' : Utils::$context['censor_test'] ?>">
									<input type="submit" value="<?= Lang::getTxt('censor_test_save', file: 'Admin') ?>" class="button">
								</p>
							</div>

							<input type="hidden" name="<?= Utils::$context['session_var'] ?>" value="<?= Utils::$context['session_id'] ?>">
							<input type="hidden" name="<?= Utils::$context['admin-censor_token_var'] ?>" value="<?= Utils::$context['admin-censor_token'] ?>">
						</form>