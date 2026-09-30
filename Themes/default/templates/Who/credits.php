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

use SMF\Lang;
use SMF\Utils;

if (!defined('SMF')) {
	die('No direct access...');
}

/*
 * This displays a nice credits page
 */
?>

<?php /* The most important part - the credits :P. */ ?>
	<div class="main_section" id="credits">
		<div class="cat_bar">
			<h3 class="catbg"><?= Lang::getTxt('credits', file: 'Who') ?></h3>
		</div>
<?php foreach (Utils::$context['credits'] as $section): ?>
<?php if (isset($section['pretext'])): ?>
		<div class="windowbg">
			<p><?= $section['pretext'] ?></p>
		</div>
<?php endif; ?>
<?php if (isset($section['title'])): ?>
		<div class="cat_bar">
			<h3 class="catbg"><?= $section['title'] ?></h3>
		</div>
<?php endif; ?>
		<div class="windowbg">
			<dl>
<?php foreach ($section['groups'] as $group): ?>
				<dt>
					<?= isset($group['title']) ? '<strong>' . $group['title'] . '</strong>' : '' ?>

				</dt>
				<dd><?= Lang::getTxt('credits_list', ['names' => Lang::sentenceList($group['members'])], file: 'Who') ?>

				</dd>
<?php endforeach; ?>
			</dl>
<?php if (isset($section['posttext'])): ?>
				<p class="posttext"><?= $section['posttext'] ?></p>
<?php endif; ?>
		</div>
<?php endforeach; ?>
<?php /* Other software and graphics */ ?>
<?php if (!empty(Utils::$context['credits_software_graphics'])): ?>
		<div class="cat_bar">
			<h3 class="catbg"><?= Lang::getTxt('credits_software_graphics', file: 'Who') ?></h3>
		</div>
		<div class="windowbg">
<?php if (!empty(Utils::$context['credits_software_graphics']['graphics'])): ?>
			<dl>
				<dt><strong><?= Lang::getTxt('credits_graphics', file: 'Who') ?></strong></dt>
				<dd><?= implode('</dd><dd>', Utils::$context['credits_software_graphics']['graphics']) ?></dd>
			</dl>
<?php endif; ?>
<?php if (!empty(Utils::$context['credits_software_graphics']['software'])): ?>
			<dl>
				<dt><strong><?= Lang::getTxt('credits_software', file: 'Who') ?></strong></dt>
				<dd><?= implode('</dd><dd>', Utils::$context['credits_software_graphics']['software']) ?></dd>
			</dl>
<?php endif; ?>
<?php if (!empty(Utils::$context['credits_software_graphics']['fonts'])): ?>
			<dl>
				<dt><strong><?= Lang::getTxt('credits_fonts', file: 'Who') ?></strong></dt>
				<dd><?= implode('</dd><dd>', Utils::$context['credits_software_graphics']['fonts']) ?></dd>
			</dl>
<?php endif; ?>
		</div>
<?php endif; ?>
<?php /* How about Modifications, we all love em */ ?>
<?php if (!empty(Utils::$context['credits_modifications']) || !empty(Utils::$context['copyrights']['mods'])): ?>
		<div class="cat_bar">
			<h3 class="catbg"><?= Lang::getTxt('credits_modifications', file: 'Who') ?></h3>
		</div>
		<div class="windowbg">
			<ul>
<?php /* Display the credits. */ ?>
<?php if (!empty(Utils::$context['credits_modifications'])): ?>
				<li><?= implode('</li><li>', Utils::$context['credits_modifications']) ?></li>
<?php endif; ?>
<?php /* Legacy. */ ?>
<?php if (!empty(Utils::$context['copyrights']['mods'])): ?>
				<li><?= implode('</li><li>', Utils::$context['copyrights']['mods']) ?></li>
<?php endif; ?>
			</ul>
		</div>
<?php endif; ?>
<?php /* SMF itself */ ?>
		<div class="cat_bar">
			<h3 class="catbg"><?= Lang::getTxt('credits_forum', file: 'Who') ?></h3>
		</div>
		<div class="windowbg">
			<?= Utils::$context['copyrights']['smf'] ?>

		</div>
	</div><!-- #credits -->