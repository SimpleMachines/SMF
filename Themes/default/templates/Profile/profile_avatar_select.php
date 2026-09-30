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
 * Template for selecting an avatar
 */
?>

<?php /* Start with the upper menu */ ?>
							<dt>
								<strong id="personal_picture">
									<label for="avatar_upload_box"><?= Lang::getTxt('personal_picture', file: 'Profile') ?></label>
								</strong>
<?php if (empty(Config::$modSettings['gravatarEnabled']) || empty(Config::$modSettings['gravatarOverride'])): ?>
								<input type="radio" name="avatar_choice" id="avatar_choice_none" value="none"<?= (Utils::$context['member']['avatar']['choice'] == 'none' ? ' checked="checked"' : '') ?>>
								<label for="avatar_choice_none"<?= (isset(Utils::$context['modify_error']['bad_avatar']) ? ' class="error"' : '') ?>>
									<?= Lang::getTxt('no_avatar', file: 'Profile') ?>
								</label><br>
<?php endif; ?>
<?php if (!empty(Utils::$context['member']['avatar']['allow_server_stored'])): ?>
								<input type="radio" name="avatar_choice" id="avatar_choice_server_stored" value="server_stored"<?= (Utils::$context['member']['avatar']['choice'] == 'server_stored' ? ' checked="checked"' : '') ?>>
								<label for="avatar_choice_server_stored"<?= (isset(Utils::$context['modify_error']['bad_avatar']) ? ' class="error"' : '') ?>>
									<?= Lang::getTxt('choose_avatar_gallery', file: 'Profile') ?>
								</label><br>
<?php endif; ?>
<?php if (!empty(Utils::$context['member']['avatar']['allow_external'])): ?>
								<input type="radio" name="avatar_choice" id="avatar_choice_external" value="external"<?= (Utils::$context['member']['avatar']['choice'] == 'external' ? ' checked="checked"' : '') ?>>
								<label for="avatar_choice_external"<?= (isset(Utils::$context['modify_error']['bad_avatar']) ? ' class="error"' : '') ?>>
									<?= Lang::getTxt('my_own_pic', file: 'Profile') ?>
								</label><br>
<?php endif; ?>
<?php if (!empty(Utils::$context['member']['avatar']['allow_upload'])): ?>
								<input type="radio" name="avatar_choice" id="avatar_choice_upload" value="upload"<?= (Utils::$context['member']['avatar']['choice'] == 'upload' ? ' checked="checked"' : '') ?>>
								<label for="avatar_choice_upload"<?= (isset(Utils::$context['modify_error']['bad_avatar']) ? ' class="error"' : '') ?>>
									<?= Lang::getTxt('avatar_will_upload', file: 'Profile') ?>
								</label><br>
<?php endif; ?>
<?php if (!empty(Utils::$context['member']['avatar']['allow_gravatar'])): ?>
								<input type="radio" name="avatar_choice" id="avatar_choice_gravatar" value="gravatar"<?= (Utils::$context['member']['avatar']['choice'] == 'gravatar' ? ' checked="checked"' : '') ?>>
								<label for="avatar_choice_gravatar"<?= (isset(Utils::$context['modify_error']['bad_avatar']) ? ' class="error"' : '') ?>><?= Lang::getTxt('use_gravatar', file: 'Profile') ?></label>
								<span class="smalltext"><a href="<?= Config::$scripturl ?>?action=helpadmin;help=gravatar" onclick="return reqOverlayDiv(this.href);"><span class="main_icons help"></span></a></span>
<?php endif; ?>
							</dt>
							<dd>
<?php /* If users are allowed to choose avatars stored on the server show selection boxes to choice them from. */ ?>
<?php if (!empty(Utils::$context['member']['avatar']['allow_server_stored'])): ?>
								<div id="avatar_server_stored" data-avatar-choice="server_stored">
									<div>
										<select name="cat" id="cat" size="10" data-avatardir="<?= Config::$modSettings['avatar_url'] ?>/">
<?php
// One entry per avatar, with the directories as groups. Nothing here is
// nested more than one deep, because that is all the picker can show.
?>
<?php foreach (Utils::$context['avatars'] as $avatar): ?>
<?php if (!empty($avatar['is_dir'])): ?>
											<optgroup label="<?= $avatar['name'] ?>">
<?php foreach ($avatar['files'] as $file): ?>
												<option value="<?= $avatar['filename'] ?>/<?= $file['filename'] ?>"<?= $file['checked'] ? ' selected' : '' ?>><?= $file['name'] ?></option>
<?php endforeach; ?>
											</optgroup>
<?php else: ?>
											<option value="<?= $avatar['filename'] ?>"<?= $avatar['checked'] ? ' selected' : '' ?>><?= $avatar['name'] ?></option>
<?php endif; ?>
<?php endforeach; ?>
										</select>
									</div>
									<div class="edit_avatar_img">
										<img id="avatar" src="<?= Utils::$context['member']['avatar']['choice'] == 'server_stored' ? Utils::$context['member']['avatar']['href'] : Config::$modSettings['avatar_url'] . '/blank.png' ?>" alt="">
									</div>
								</div><!-- #avatar_server_stored -->
<?php endif; ?>
<?php /* If the user can link to an off server avatar, show them a box to input the address. */ ?>
<?php if (!empty(Utils::$context['member']['avatar']['allow_external'])): ?>
								<div id="avatar_external" data-avatar-choice="external">
									<?= Utils::$context['member']['avatar']['choice'] == 'external' ? '<div class="edit_avatar_img"><img src="' . Utils::$context['member']['avatar']['href'] . '" alt="" class="avatar"></div>' : '' ?>
									<div class="smalltext"><?= Lang::getTxt('avatar_by_url', file: 'Profile') ?></div><?= !empty(Config::$modSettings['avatar_action_too_large']) && Config::$modSettings['avatar_action_too_large'] == 'option_download_and_resize' ? $this->fetchSubTemplate('max_size', ['type' => 'external']) : '' ?>
									<input type="text" name="userpicpersonal" size="45" value="<?= ((stristr(Utils::$context['member']['avatar']['external'], 'http://') || stristr(Utils::$context['member']['avatar']['external'], 'https://')) ? Utils::$context['member']['avatar']['external'] : 'http://') ?>"><br>
								</div>
<?php endif; ?>
<?php /* If the user is able to upload avatars to the server show them an upload box. */ ?>
<?php if (!empty(Utils::$context['member']['avatar']['allow_upload'])): ?>
								<div id="avatar_upload" data-avatar-choice="upload">
									<?= Utils::$context['member']['avatar']['choice'] == 'upload' ? '<div class="edit_avatar_img"><img src="' . Utils::$context['member']['avatar']['href'] . '" alt=""></div>' : '' ?>
									<input type="file" size="44" name="attachment" id="avatar_upload_box" value="" accept="image/gif, image/jpeg, image/jpg, image/png, image/svg+xml, image/webp"><?php $this->subTemplate('max_size', ['type' => 'upload']); ?>
									<?= (!empty(Utils::$context['member']['avatar']['id_attach']) ? '<br><input type="hidden" name="id_attach" value="' . Utils::$context['member']['avatar']['id_attach'] . '">' : '') ?>
								</div>
<?php endif; ?>
<?php /* if the user is able to use Gravatar avatars show then the image preview */ ?>
<?php if (!empty(Utils::$context['member']['avatar']['allow_gravatar'])): ?>
								<div id="avatar_gravatar" data-avatar-choice="gravatar"<?= !empty(Config::$modSettings['gravatarAllowExtraEmail']) && (Utils::$context['member']['avatar']['external'] == Utils::$context['member']['email'] || str_contains(Utils::$context['member']['avatar']['external'], 'http://') || str_contains(Utils::$context['member']['avatar']['external'], 'https://')) ? ' data-clear-email' : '' ?>>
									<?= Utils::$context['member']['avatar']['choice'] == 'gravatar' ? '<div class="edit_avatar_img"><img src="' . Utils::$context['member']['avatar']['href'] . '" alt=""></div>' : '' ?>

<?php if (empty(Config::$modSettings['gravatarAllowExtraEmail'])): ?>
									<div class="smalltext"><?= Lang::getTxt('gravatar_noAlternateEmail', file: 'Profile') ?></div>
<?php else: ?>
<?php /* Depending on other stuff, the stored value here might have some odd things in it from other areas. */ ?>
<?php if (Utils::$context['member']['avatar']['external'] == Utils::$context['member']['email']): ?>
<?php $textbox_value = ''; ?>
<?php else: ?>
<?php $textbox_value = Utils::$context['member']['avatar']['external']; ?>
<?php endif; ?>
									<div class="smalltext"><?= Lang::getTxt('gravatar_alternateEmail', file: 'Profile') ?></div>
									<input type="text" name="gravatarEmail" id="gravatarEmail" size="45" value="<?= $textbox_value ?>">
<?php endif; ?>
								</div><!-- #avatar_gravatar -->
<?php endif; ?>
							</dd>