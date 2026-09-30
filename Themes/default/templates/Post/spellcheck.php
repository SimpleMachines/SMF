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
use SMF\Theme;
use SMF\Utils;

if (!defined('SMF')) {
	die('No direct access...');
}

/*
 * The template for the spellchecker.
 */
?><?php /* The style information that makes the spellchecker look... like the forum hopefully! */ ?><!DOCTYPE html>
<html<?= Utils::$context['right_to_left'] ? ' dir="rtl"' : '' ?>>
	<head>
		<meta charset="UTF-8">
		<title><?= Lang::getTxt('spell_check', file: 'General') ?></title>
		<link rel="stylesheet" href="<?= Theme::$current->settings['theme_url'] ?>/css/index<?= Utils::$context['theme_variant'] ?>.css<?= Utils::$context['browser_cache'] ?>">
		<style>
			body, td {
				font-size: small;
				margin: 0;
				background: #f0f0f0;
				color: #000;
				padding: 10px;
			}
			.highlight {
				color: red;
				font-weight: bold;
			}
			#spellview {
				border-style: outset;
				border: 1px solid black;
				padding: 5px;
				width: 95%;
				height: 314px;
				overflow: auto;
				background: #ffffff;
			}<?php /* As you may expect - we need a lot of javascript for this... load it from the separate files. */ ?>

		</style>
		<script>
			var spell_formname = window.opener.spell_formname;
			var spell_fieldname = window.opener.spell_fieldname;
		</script>
		<script src="<?= Theme::$current->settings['default_theme_url'] ?>/scripts/spellcheck.js<?= Utils::$context['browser_cache'] ?>"></script>
		<script src="<?= Theme::$current->settings['default_theme_url'] ?>/scripts/script.js<?= Utils::$context['browser_cache'] ?>"></script>
		<script>
			<?= Utils::$context['spell_js'] ?>

		</script>
	</head>
	<body onload="nextWord(false);">
		<form action="#" method="post" accept-charset="UTF-8" name="spellingForm" id="spellingForm" onsubmit="return false;">
			<div id="spellview">&nbsp;</div>
			<table width="100%">
				<tr class="windowbg">
					<td style="width: 50%; vertical-align: top">
						<?= Lang::getTxt('spellcheck_change_to', file: 'Post') ?><br>
						<input type="text" name="changeto" style="width: 98%;">
					</td>
					<td style="width: 50%">
						<?= Lang::getTxt('spellcheck_suggest', file: 'Post') ?><br>
						<select name="suggestions" style="width: 98%;" size="5" onclick="if (this.selectedIndex != -1) this.form.changeto.value = this.options[this.selectedIndex].text;" ondblclick="replaceWord();">
						</select>
					</td>
				</tr>
			</table>
			<div class="righttext" style="padding: 4px;">
				<input type="button" name="change" value="<?= Lang::getTxt('spellcheck_change', file: 'Post') ?>" onclick="replaceWord();" class="button">
				<input type="button" name="changeall" value="<?= Lang::getTxt('spellcheck_change_all', file: 'Post') ?>" onclick="replaceAll();" class="button">
				<input type="button" name="ignore" value="<?= Lang::getTxt('spellcheck_ignore', file: 'Post') ?>" onclick="nextWord(false);" class="button">
				<input type="button" name="ignoreall" value="<?= Lang::getTxt('spellcheck_ignore_all', file: 'Post') ?>" onclick="nextWord(true);" class="button">
			</div>
		</form>
	</body>
</html>