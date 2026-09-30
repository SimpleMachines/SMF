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
 * The form for composing a newsletter
 */
?>

	<div id="preview_section"<?= isset(Utils::$context['preview_message']) ? '' : ' class="hidden"' ?>>
		<div class="cat_bar">
			<h3 class="catbg">
				<span id="preview_subject"><?= empty(Utils::$context['preview_subject']) ? '' : Utils::$context['preview_subject'] ?></span>
			</h3>
		</div>
		<div class="windowbg">
			<div class="post" id="preview_body">
				<?= empty(Utils::$context['preview_message']) ? '<br>' : Utils::$context['preview_message'] ?>

			</div>
		</div>
	</div>
	<br>
		<form name="newsmodify" action="<?= Config::$scripturl ?>?action=admin;area=news;sa=mailingsend" method="post" accept-charset="UTF-8">
			<div class="cat_bar">
				<h3 class="catbg">
					<a href="<?= Config::$scripturl ?>?action=helpadmin;help=email_members" onclick="return reqOverlayDiv(this.href);" class="help"><span class="main_icons help" title="<?= Lang::getTxt('help', file: 'General') ?>"></span></a> <?= Lang::getTxt('admin_newsletters', file: 'Admin') ?>

				</h3>
			</div>
			<div class="information noup">
				<?= Lang::getTxt('email_variables', ['scripturl' => Config::$scripturl], file: 'Admin') ?>

			</div>
			<div class="windowbg noup">
				<div class="<?= empty(Utils::$context['error_type']) || Utils::$context['error_type'] != 'serious' ? 'noticebox' : 'errorbox' ?>"<?= empty(Utils::$context['post_error']['messages']) ? ' style="display: none"' : '' ?> id="errors">
					<dl>
						<dt>
							<strong id="error_serious"><?= Lang::getTxt('error_while_submitting', file: 'General') ?></strong>
						</dt>
						<dd class="error" id="error_list">
							<?= empty(Utils::$context['post_error']['messages']) ? '' : implode('<br>', Utils::$context['post_error']['messages']) ?>

						</dd>
					</dl>
				</div>
				<dl id="post_header">
					<dt>
						<label<?= (isset(Utils::$context['post_error']['no_subject']) ? ' class="error"' : '') ?> for="subject" id="caption_subject"><?= Lang::getTxt('subject', file: 'General') ?></label>
					</dt>
					<dd id="pm_subject">
						<input type="text" id="subject" name="subject" value="<?= Utils::$context['subject'] ?>" size="80" maxlength="84"<?= isset(Utils::$context['post_error']['no_subject']) ? ' class="error"' : '' ?>>
					</dd>
				</dl>
				<div id="bbcBox_message"></div>
<?php /* What about smileys? */ ?>
<?php if (!empty(Utils::$context['smileys']['postform']) || !empty(Utils::$context['smileys']['popup'])): ?>
				<div id="smileyBox_message"></div>
<?php endif; ?>
<?php /* Show BBC buttons, smileys and textbox. */ ?>
				<?php $this->subTemplate('control_richedit', ['editor_id' => Utils::$context['post_box_name'], 'smiley_container' => 'smileyBox_message', 'bbc_container' => 'bbcBox_message']); ?>

				<ul>
					<li><label for="send_pm"><input type="checkbox" name="send_pm" id="send_pm"<?= !empty(Utils::$context['send_pm']) ? ' checked' : '' ?> onclick="checkboxes_status(this);"> <?= Lang::getTxt('email_as_pms', file: 'Admin') ?></label></li>
					<li><label for="send_html"><input type="checkbox" name="send_html" id="send_html"<?= !empty(Utils::$context['send_html']) ? ' checked' : '' ?> onclick="checkboxes_status(this);"> <?= Lang::getTxt('email_as_html', file: 'Admin') ?></label></li>
					<li><label for="parse_html"><input type="checkbox" name="parse_html" id="parse_html" checked disabled> <?= Lang::getTxt('email_parsed_html', file: 'Admin') ?></label></li>
				</ul>
				<span id="post_confirm_buttons">
					<?php $this->subTemplate('control_richedit_buttons', ['editor_id' => Utils::$context['post_box_name']]); ?>

				</span>
			</div><!-- .windowbg -->
			<input type="hidden" name="<?= Utils::$context['session_var'] ?>" value="<?= Utils::$context['session_id'] ?>">
			<input type="hidden" name="email_force" value="<?= Utils::$context['email_force'] ?>">
			<input type="hidden" name="total_emails" value="<?= Utils::$context['total_emails'] ?>">
<?php foreach (Utils::$context['recipients'] as $key => $values): ?>
			<input type="hidden" name="<?= $key ?>" value="<?= implode(($key == 'emails' ? ';' : ','), $values) ?>">
<?php endforeach; ?>
			<script>
<?php /* The functions used to preview a posts without loading a new page. */ ?>
				var txt_preview_title = "<?= Lang::getTxt('preview_title', file: 'General') ?>";
				var txt_preview_fetch = "<?= Lang::getTxt('preview_fetch', file: 'General') ?>";
				function previewPost()
				{
					if (window.XMLHttpRequest)
					{
						// Opera didn't support setRequestHeader() before 8.01.
						// @todo Remove support for old browsers
						if ('opera' in window)
						{
							// Handle the WYSIWYG editor.
							if (textFields[i] == <?= Utils::escapeJavaScript(Utils::$context['post_box_name']) ?> && <?= Utils::escapeJavaScript('oEditorHandle_' . Utils::$context['post_box_name']) ?> in window && oEditorHandle_<?= Utils::$context['post_box_name'] ?>.bRichTextEnabled)
								x[x.length] = 'message_mode=1&' + textFields[i] + '=' + oEditorHandle_<?= Utils::$context['post_box_name'] ?>.getText(false).php_to8bit().php_urlencode();
							else
								x[x.length] = textFields[i] + '=' + document.forms.newsmodify[textFields[i]].value.php_to8bit().php_urlencode();
						}
						// @todo Currently not sending poll options and option checkboxes.
						var x = new Array();
						var textFields = ['subject', <?= Utils::escapeJavaScript(Utils::$context['post_box_name']) ?>];
						var checkboxFields = ['send_html', 'send_pm'];

						for (var i = 0, n = textFields.length; i < n; i++)
							if (textFields[i] in document.forms.newsmodify)
							{
								// Handle the WYSIWYG editor.
								if (textFields[i] == <?= Utils::escapeJavaScript(Utils::$context['post_box_name']) ?> && <?= Utils::escapeJavaScript('oEditorHandle_' . Utils::$context['post_box_name']) ?> in window && oEditorHandle_<?= Utils::$context['post_box_name'] ?>.bRichTextEnabled)
									x[x.length] = 'message_mode=1&' + textFields[i] + '=' + oEditorHandle_<?= Utils::$context['post_box_name'] ?>.getText(false).replace(/&#/g, '&#38;#').php_to8bit().php_urlencode();
								else
									x[x.length] = textFields[i] + '=' + document.forms.newsmodify[textFields[i]].value.replace(/&#/g, '&#38;#').php_to8bit().php_urlencode();
							}
						for (var i = 0, n = checkboxFields.length; i < n; i++)
							if (checkboxFields[i] in document.forms.newsmodify && document.forms.newsmodify.elements[checkboxFields[i]].checked)
								x[x.length] = checkboxFields[i] + '=' + document.forms.newsmodify.elements[checkboxFields[i]].value;

						x[x.length] = 'item=newsletterpreview';

						sendXMLDocument(smf_prepareScriptUrl(smf_scripturl) + 'action=xmlhttp;sa=previews;xml', x.join('&'), onDocSent);

						document.getElementById('preview_section').style.display = '';
						setInnerHTML(document.getElementById('preview_subject'), txt_preview_title);
						setInnerHTML(document.getElementById('preview_body'), txt_preview_fetch);

						return false;
					}
					else
						return submitThisOnce(document.forms.newsmodify);
				}
				function onDocSent(XMLDoc)
				{
					if (!XMLDoc)
					{
						document.forms.newsmodify.preview.onclick = new function ()
						{
							return true;
						}
						document.forms.newsmodify.preview.click();
					}

					// Show the preview section.
					var preview = XMLDoc.getElementsByTagName('smf')[0].getElementsByTagName('preview')[0];
					setInnerHTML(document.getElementById('preview_subject'), preview.getElementsByTagName('subject')[0].firstChild.nodeValue);

					var bodyText = '';
					for (var i = 0, n = preview.getElementsByTagName('body')[0].childNodes.length; i < n; i++)
						bodyText += preview.getElementsByTagName('body')[0].childNodes[i].nodeValue;

					setInnerHTML(document.getElementById('preview_body'), bodyText);
					document.getElementById('preview_body').className = 'post';

					// Show a list of errors (if any).
					var errors = XMLDoc.getElementsByTagName('smf')[0].getElementsByTagName('errors')[0];
					var errorList = new Array();
					for (var i = 0, numErrors = errors.getElementsByTagName('error').length; i < numErrors; i++)
						errorList[errorList.length] = errors.getElementsByTagName('error')[i].firstChild.nodeValue;
					document.getElementById('errors').style.display = numErrors == 0 ? 'none' : '';
					setInnerHTML(document.getElementById('error_list'), numErrors == 0 ? '' : errorList.join('<br>'));

					// Adjust the color of captions if the given data is erroneous.
					var captions = errors.getElementsByTagName('caption');
					for (var i = 0, numCaptions = errors.getElementsByTagName('caption').length; i < numCaptions; i++)
						if (document.getElementById('caption_' + captions[i].getAttribute('name')))
							document.getElementById('caption_' + captions[i].getAttribute('name')).className = captions[i].getAttribute('class');

					if (errors.getElementsByTagName('post_error').length == 1)
						document.forms.newsmodify.<?= Utils::$context['post_box_name'] ?>.style.border = '1px solid red';
					else if (document.forms.newsmodify.<?= Utils::$context['post_box_name'] ?>.style.borderColor == 'red' || document.forms.newsmodify.<?= Utils::$context['post_box_name'] ?>.style.borderColor == 'red red red red')
					{
						if ('runtimeStyle' in document.forms.newsmodify.<?= Utils::$context['post_box_name'] ?>)
							document.forms.newsmodify.<?= Utils::$context['post_box_name'] ?>.style.borderColor = '';
						else
							document.forms.newsmodify.<?= Utils::$context['post_box_name'] ?>.style.border = null;
					}
					location.hash = '#' + 'preview_section';
				}
			</script>
			<script>
				function checkboxes_status (item)
				{
					if (item.id == 'send_html')
						document.getElementById('parse_html').disabled = !document.getElementById('parse_html').disabled;
					if (item.id == 'send_pm')
					{
						if (!document.getElementById('send_html').checked)
							document.getElementById('parse_html').disabled = true;
						else
							document.getElementById('parse_html').disabled = false;
						document.getElementById('send_html').disabled = !document.getElementById('send_html').disabled;
					}
				}
			</script>
		</form>