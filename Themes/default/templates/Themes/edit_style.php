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

use SMF\BrowserDetector;
use SMF\Config;
use SMF\Lang;
use SMF\Theme;
use SMF\Utils;

if (!defined('SMF')) {
	die('No direct access...');
}

/*
 * Wanna edit the stylesheet?
 */
?><?php if (Utils::$context['session_error']): ?>
	<div class="errorbox">
		<?= Lang::getTxt('error_session_timeout', file: 'Errors') ?>
	</div><?php endif; ?><?php /* From now on no one can complain that editing css is difficult. If you disagree, go to www.w3schools.com. */ ?>
		<script>
			var previewData = "";
			var previewTimeout;
			var editFilename = <?= Utils::escapeJavaScript(Utils::$context['edit_filename']) ?>;

			// Load up a page, but apply our stylesheet.
			function navigatePreview(url)
			{
				var myDoc = new XMLHttpRequest();
				myDoc.onreadystatechange = function ()
				{
					if (myDoc.readyState != 4)
						return;

					if (myDoc.responseText != null && myDoc.status == 200)
					{
						previewData = myDoc.responseText;
						document.getElementById("css_preview_box").style.display = "";

						// Revert to the theme they actually use ;).
						var tempImage = new Image();
						tempImage.src = smf_prepareScriptUrl(smf_scripturl) + "action=admin;area=theme;sa=edit;theme=<?= Theme::$current->settings['theme_id'] ?>;preview;" + (new Date().getTime());

						refreshPreviewCache = null;
						refreshPreview(false);
					}
				};

				var anchor = "";
				if (url.indexOf("#") != -1)
				{
					anchor = url.substr(url.indexOf("#"));
					url = url.substr(0, url.indexOf("#"));
				}

				myDoc.open("GET", url + (url.indexOf("?") == -1 ? "?" : ";") + "theme=<?= Utils::$context['theme_id'] ?>;normalcss" + anchor, true);
				myDoc.send(null);
			}
			navigatePreview(smf_scripturl);

			var refreshPreviewCache;
			function refreshPreview(check)
			{
				var identical = document.forms.stylesheetForm.entire_file.value == refreshPreviewCache;

				// Don't reflow the whole thing if nothing changed!!
				if (check && identical)
					return;
				refreshPreviewCache = document.forms.stylesheetForm.entire_file.value;
				// Replace the paths for images.
				refreshPreviewCache = refreshPreviewCache.replace(/url\(\.\.\/images/gi, "url(" + smf_images_url);

				// Try to do it without a complete reparse.
				if (identical)
				{
					try
					{
					<?php if (BrowserDetector::isBrowser('is_ie')): ?>

						var sheets = frames["css_preview_box"].document.styleSheets;
						for (var j = 0; j < sheets.length; j++)
						{
							if (sheets[j].id == "css_preview_box")
								sheets[j].cssText = document.forms.stylesheetForm.entire_file.value;
						}<?php else: ?>

						setInnerHTML(frames["css_preview_box"].document.getElementById("css_preview_sheet"), document.forms.stylesheetForm.entire_file.value);<?php endif; ?>

					}
					catch (e)
					{
						identical = false;
					}
				}

				// This will work most of the time... could be done with an after-apply, maybe.
				if (!identical)
				{
					var data = previewData + "";
					var preview_sheet = document.forms.stylesheetForm.entire_file.value;
					var stylesheetMatch = new RegExp('<link rel="stylesheet"[^>]+href="[^"]+' + editFilename + '[^>]*>');

					// Replace the paths for images.
					preview_sheet = preview_sheet.replace(/url\(\.\.\/images/gi, "url(" + smf_images_url);
					data = data.replace(stylesheetMatch, "<style type=\"text/css\" id=\"css_preview_sheet\">" + preview_sheet + "<" + "/style>");

					frames["css_preview_box"].document.open();
					frames["css_preview_box"].document.write(data);
					frames["css_preview_box"].document.close();

					// Next, fix all its links so we can handle them and reapply the new css!
					frames["css_preview_box"].onload = function ()
					{
						var fixLinks = frames["css_preview_box"].document.getElementsByTagName("a");
						for (var i = 0; i < fixLinks.length; i++)
						{
							if (fixLinks[i].onclick)
								continue;
							fixLinks[i].onclick = function ()
							{
								window.parent.navigatePreview(this.href);
								return false;
							};
						}
					};
				}
			}
		</script>
		<iframe id="css_preview_box" name="css_preview_box" src="about:blank" frameborder="0" style="display: none;"></iframe><?php /* Just show a big box.... gray out the Save button if it's not saveable... (ie. not 777.) */ ?>
		<form action="<?= Config::$scripturl ?>?action=admin;area=theme;th=<?= Utils::$context['theme_id'] ?>;sa=edit" method="post" accept-charset="UTF-8" name="stylesheetForm" id="stylesheetForm">
			<div class="cat_bar">
				<h3 class="catbg"><?= Lang::getTxt('theme_edit_file', ['filename' => Utils::$context['edit_filename']], file: 'Themes') ?></h3>
			</div>
			<div class="windowbg"><?php if (!Utils::$context['allow_save']): ?>
				<?= Lang::getTxt('theme_edit_no_save', ['filename' => Utils::$context['allow_save_filename']], file: 'Themes') ?><br><?php endif; ?>
				<textarea class="edit_file" name="entire_file" cols="80" rows="20" onkeyup="setPreviewTimeout();" onchange="refreshPreview(true);"><?= Utils::$context['entire_file'] ?></textarea>
				<br>
				<div class="padding righttext">
					<input type="submit" name="save" value="<?= Lang::getTxt('theme_edit_save', file: 'Themes') ?>"<?= Utils::$context['allow_save'] ? '' : ' disabled' ?> class="button">
					<input type="button" value="<?= Lang::getTxt('themeadmin_edit_preview', file: 'Themes') ?>" onclick="refreshPreview(false);" class="button">
				</div>
			</div>
			<input type="hidden" name="filename" value="<?= Utils::$context['edit_filename'] ?>">
			<input type="hidden" name="<?= Utils::$context['session_var'] ?>" value="<?= Utils::$context['session_id'] ?>"><?php /* Hopefully it exists. */ ?><?php if (isset(Utils::$context['admin-te-' . md5(Utils::$context['theme_id'] . '-' . Utils::$context['edit_filename']) . '_token'])): ?>
			<input type="hidden" name="<?= Utils::$context['admin-te-' . md5(Utils::$context['theme_id'] . '-' . Utils::$context['edit_filename']) . '_token_var'] ?>" value="<?= Utils::$context['admin-te-' . md5(Utils::$context['theme_id'] . '-' . Utils::$context['edit_filename']) . '_token'] ?>"><?php endif; ?>
		</form>