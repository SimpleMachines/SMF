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
use SMF\Profile;
use SMF\Utils;

if (!defined('SMF')) {
	die('No direct access...');
}

/*
 * Template for initiating and retrieving profile data exports
 */
?>

<?php
$default_settings = ['included' => [], 'format' => ''];
$dltoken = '';
// The main containing header.
?>
		<div class="cat_bar">
			<h3 class="catbg profile_hd">
				<?= Lang::getTxt('export_profile_data', file: 'Profile') ?>
			</h3>
		</div>
		<div class="information"><?= Utils::$context['export_profile_data_desc'] ?></div>
<?php if (!empty(Utils::$context['completed_exports'])): ?>
		<div class="title_bar">
			<h3 class="titlebg"><?= Lang::getTxt('completed_exports', file: 'Profile') ?></h3>
		</div>
		<div class="windowbg noup">
<?php foreach (Utils::$context['completed_exports'] as $basehash_ext => $parts): ?>
			<form action="<?= Config::$scripturl ?>?action=profile;area=getprofiledata;u=<?= Utils::$context['id_member'] ?>" method="post" accept-charset="UTF-8" class="<?= count(Utils::$context['completed_exports']) > 1 ? 'descbox' : 'padding' ?>">
<?php if (!empty(Utils::$context['outdated_exports'][$basehash_ext])): ?>
				<div class="noticebox">
					<p><?= Lang::getTxt('export_outdated_warning', file: 'Profile') ?></p>
					<ul class="bbc_list">
<?php foreach (Utils::$context['outdated_exports'][$basehash_ext] as $datatype): ?>
						<li><?= Lang::getTxt($datatype, file: 'General+Profile') ?></li>
<?php endforeach; ?>
					</ul>
				</div>
<?php endif; ?>
				<p><?= Lang::getTxt(
				'export_file_desc',
				[
					'list' => $parts[1]['included_desc'],
					'format' => Utils::$context['export_formats'][$parts[1]['format']]['description'],
				],
				file: 'Profile',
			) ?></p>
<?php if (count($parts) > 10): ?>
				<details>
					<summary><?= Lang::getTxt('export_file_count', [count($parts)], file: 'Profile') ?></summary>
<?php endif; ?>
				<ul class="bbc_list" id="<?= $parts[1]['format'] ?>_export_files">
<?php foreach ($parts as $part => $file): ?>
<?php $dltoken = $file['dltoken']; ?>
<?php if (empty($default_settings['included'])): ?>
<?php $default_settings['included'] = $file['included']; ?>
<?php endif; ?>
<?php if (empty($default_settings['format'])): ?>
<?php $default_settings['format'] = $file['format']; ?>
<?php endif; ?>
					<li>
						<a href="<?= Config::$scripturl ?>?action=profile;area=download;u=<?= Utils::$context['id_member'] ?>;format=<?= $file['format'] ?>;part=<?= $part ?>;t=<?= $dltoken ?>" class="bbc_link" download><?= $file['dlbasename'] ?></a> (<?= $file['size'] ?>, <?= $file['mtime'] ?>)
					</li>
<?php endforeach; ?>
				</ul>
<?php if (count($parts) > 10): ?>
				</details>
<?php endif; ?>
				<div class="righttext">
					<input type="submit" name="delete" value="<?= Lang::getTxt('delete', file: 'General') ?>" class="button you_sure">
					<input type="hidden" name="format" value="<?= $parts[1]['format'] ?>">
					<input type="hidden" name="t" value="<?= $dltoken ?>">
					<button type="button" class="button export_download_all" style="display:none" onclick="export_download_all('<?= $parts[1]['format'] ?>');"><?= Lang::getTxt('export_download_all', file: 'Profile') ?></button>
				</div>
			</form>
<?php endforeach; ?>
		</div>
<?php endif; ?>
<?php if (!empty(Utils::$context['active_exports'])): ?>
		<div class="title_bar">
			<h3 class="titlebg"><?= Lang::getTxt('active_exports', file: 'Profile') ?></h3>
		</div>
		<div class="windowbg noup">
<?php foreach (Utils::$context['active_exports'] as $file): ?>
<?php $dltoken = $file['dltoken']; ?>
<?php if (empty($default_settings['included'])): ?>
<?php $default_settings['included'] = $file['included']; ?>
<?php endif; ?>
<?php if (empty($default_settings['format'])): ?>
<?php $default_settings['format'] = $file['format']; ?>
<?php endif; ?>
			<form action="<?= Config::$scripturl ?>?action=profile;area=getprofiledata;u=<?= Utils::$context['id_member'] ?>" method="post" accept-charset="UTF-8"<?= count(Utils::$context['active_exports']) > 1 ? ' class="descbox"' : '' ?>>
				<p class="padding"><?= Lang::getTxt(
				'export_file_desc',
				[
					'list' => $file['included_desc'],
					'format' => Utils::$context['export_formats'][$file['format']]['description'],
				],
				file: 'Profile',
			) ?></p>
				<div class="righttext">
					<input type="submit" name="delete" value="<?= Lang::getTxt('export_cancel', file: 'Profile') ?>" class="button you_sure">
					<input type="hidden" name="format" value="<?= $file['format'] ?>">
					<input type="hidden" name="t" value="<?= $dltoken ?>">
				</div>
			</form>
<?php endforeach; ?>
		</div>
<?php endif; ?>
		<div class="title_bar">
			<h3 class="titlebg"><?= Lang::getTxt('export_settings', file: 'Profile') ?></h3>
		</div>
		<div class="windowbg noup">
			<form action="<?= Config::$scripturl ?>?action=profile;area=getprofiledata;u=<?= Utils::$context['id_member'] ?>" method="post" accept-charset="UTF-8">
				<dl class="settings">
<?php foreach (Utils::$context['export_datatypes'] as $datatype => $datatype_settings): ?>
<?php if (!empty($datatype_settings['label'])): ?>
					<dt>
						<strong><label for="<?= $datatype ?>"><?= $datatype_settings['label'] ?></label></strong>
					</dt>
					<dd>
						<input type="checkbox" id="<?= $datatype ?>" name="<?= $datatype ?>"<?= in_array($datatype, $default_settings['included']) ? ' checked' : '' ?>>
					</dd>
<?php endif; ?>
<?php endforeach; ?>
				</dl>
				<dl class="settings">
					<dt>
						<strong><?= Lang::getTxt('export_format', file: 'Profile') ?></strong>
					</dt>
					<dd>
						<select id="export_format_select" name="format">
<?php foreach (Utils::$context['export_formats'] as $format => $format_settings): ?>
							<option value="<?= $format ?>"<?= $format == $default_settings['format'] ? ' selected' : '' ?>><?= $format_settings['description'] ?></option>
<?php endforeach; ?>
						</select>
					</dd>
				</dl>
				<div class="righttext">
<?php /* At least one active or completed export exists. */ ?>
<?php if (!empty($dltoken)): ?>
					<div id="export_begin" style="display:none">
						<input type="submit" name="export_begin" value="<?= Lang::getTxt('export_begin', file: 'Profile') ?>" class="button">
					</div>
					<div id="export_restart">
						<input type="submit" name="export_begin" value="<?= Lang::getTxt('export_restart', file: 'Profile') ?>" class="button you_sure" data-confirm="<?= Lang::getTxt('export_restart_confirm', file: 'Profile') ?>">
						<input type="hidden" name="delete">
						<input type="hidden" name="t" value="<?= $dltoken ?>">
					</div>
<?php /* No existing exports. */ ?>
<?php else: ?>
					<input type="submit" name="export_begin" value="<?= Lang::getTxt('export_begin', file: 'Profile') ?>" class="button">
<?php endif; ?>
					<input type="hidden" name="<?= Utils::$context[Utils::$context['token_check'] . '_token_var'] ?>" value="<?= Utils::$context[Utils::$context['token_check'] . '_token'] ?>">
					<input type="hidden" name="<?= Utils::$context['session_var'] ?>" value="<?= Utils::$context['session_id'] ?>">
				</div>
			</form>
		</div><!-- .windowbg -->