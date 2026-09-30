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
use SMF\Theme;
use SMF\Utils;

if (!defined('SMF')) {
	die('No direct access...');
}

/*
 * The form for sending a new PM
 */
?><?php /* Show which messages were sent successfully and which failed. */ ?><?php if (!empty(Utils::$context['send_log'])): ?>

		<div class="cat_bar">
			<h3 class="catbg"><?= Lang::getTxt('pm_send_report', file: 'PersonalMessage') ?></h3>
		</div>
		<div class="windowbg noup"><?php if (!empty(Utils::$context['send_log']['sent'])): ?><?php foreach (Utils::$context['send_log']['sent'] as $log_entry): ?>

			<span class="error"><?= $log_entry ?></span><br><?php endforeach; ?><?php endif; ?><?php if (!empty(Utils::$context['send_log']['failed'])): ?><?php foreach (Utils::$context['send_log']['failed'] as $log_entry): ?>

			<span class="error"><?= $log_entry ?></span><br><?php endforeach; ?><?php endif; ?>

		</div>
		<br><?php endif; ?><?php /* Show the preview of the personal message. */ ?>

		<div id="preview_section"<?= isset(Utils::$context['preview_message']) ? '' : ' class="hidden"' ?>>
			<div class="cat_bar">
				<h3 class="catbg">
					<span id="preview_subject"><?= empty(Utils::$context['preview_subject']) ? '' : Utils::$context['preview_subject'] ?></span>
				</h3>
			</div>
			<div class="windowbg noup">
				<div class="post" id="preview_body">
					<?= empty(Utils::$context['preview_message']) ? '<br>' : Utils::adjustHeadingLevels(Utils::$context['preview_message'], 3) ?>

				</div>
			</div>
			<br class="clear">
		</div><?php /* Main message editing box. */ ?>

		<form action="<?= Config::$scripturl ?>?action=pm;sa=send2" method="post" accept-charset="UTF-8" name="postmodify" id="postmodify" class="flow_hidden" onsubmit="submitonce(this);">
			<div class="cat_bar">
				<h3 class="catbg">
					<span class="main_icons inbox icon" title="<?= Lang::getTxt('new_message', file: 'PersonalMessage') ?>"></span> <?= Lang::getTxt('new_message', file: 'PersonalMessage') ?>

				</h3>
			</div>
			<div class="roundframe noup"><?php /* If there were errors for sending the PM, show them. */ ?>

				<div class="<?= empty(Utils::$context['error_type']) || Utils::$context['error_type'] != 'serious' ? 'noticebox' : 'errorbox' ?><?= empty(Utils::$context['post_error']['messages']) ? ' hidden' : '' ?>" id="errors">
					<dl>
						<dt>
							<strong id="error_serious"><?= Lang::getTxt('error_while_submitting', file: 'General') ?></strong>
						</dt>
						<dd class="error" id="error_list">
							<?= empty(Utils::$context['post_error']['messages']) ? '' : implode('<br>', Utils::$context['post_error']['messages']) ?>

						</dd>
					</dl>
				</div><?php if (!empty(Config::$modSettings['drafts_pm_enabled'])): ?>

				<div id="draft_section" class="infobox"<?= isset(Utils::$context['draft_saved']) ? '' : ' style="display: none;"' ?>><?= Lang::getTxt('draft_pm_saved', ['url' => Config::$scripturl . '?action=pm;sa=showpmdrafts'], file: 'Drafts') ?>

					<?= (!empty(Config::$modSettings['drafts_keep_days']) ? ' <strong>' . Lang::getTxt('draft_save_warning', [Config::$modSettings['drafts_keep_days']], file: 'Drafts') . '</strong>' : '') ?>

				</div><?php endif; ?>

				<dl id="post_header"><?php /* To and bcc. Include a button to search for members. */ ?>

					<dt>
						<label<?= (isset(Utils::$context['post_error']['no_to']) || isset(Utils::$context['post_error']['bad_to']) ? ' class="error"' : '') ?> for="to_control" id="caption_to"><?= trim(Lang::getTxt('pm_to', ['list' => ''], file: 'PersonalMessage')) ?></label>
					</dt><?php /* Autosuggest will be added by the JavaScript later on. */ ?>

					<dd id="pm_to" class="clear_right">
						<input type="text" name="to" id="to_control" value="<?= Utils::$context['to_value'] ?>" size="20"><?php /* A link to add BCC, only visible with JavaScript enabled. */ ?>

						<span class="smalltext" id="bcc_link_container" style="display: none;"></span><?php /* A div that'll contain the items found by the autosuggest. */ ?>

						<div id="to_item_list_container"></div>
					</dd><?php /* This BCC row will be hidden by default if JavaScript is enabled. */ ?>

					<dt  class="clear_left" id="bcc_div">
						<label<?= (isset(Utils::$context['post_error']['no_to']) || isset(Utils::$context['post_error']['bad_bcc']) ? ' class="error"' : '') ?> for="bcc_control" id="caption_bbc"><?= trim(Lang::getTxt('pm_bcc', ['list' => ''], file: 'PersonalMessage')) ?></label>
					</dt>
					<dd id="bcc_div2">
						<input type="text" name="bcc" id="bcc_control" value="<?= Utils::$context['bcc_value'] ?>" size="20">
						<div id="bcc_item_list_container"></div>
					</dd><?php /* The subject of the PM. */ ?>

					<dt class="clear_left">
						<label<?= (isset(Utils::$context['post_error']['no_subject']) ? ' class="error"' : '') ?> for="subject" id="caption_subject"><?= Lang::getTxt('subject', file: 'General') ?></label>
					</dt>
					<dd id="pm_subject">
						<input type="text" name="subject" id="subject" value="<?= Utils::$context['subject'] ?>" size="80" maxlength="80"<?= isset(Utils::$context['post_error']['no_subject']) ? ' class="error"' : '' ?>>
					</dd>
				</dl><?php $this->subTemplate('control_richedit', ['editor_id' => Utils::$context['post_box_name'], 'smiley_container' => true, 'bbc_container' => true]); ?><?php /* If the admin enabled the pm drafts feature, show a draft selection box */ ?><?php if (!empty(Utils::$context['drafts_save']) && !empty(Utils::$context['drafts']) && !empty(Theme::$current->options['drafts_show_saved_enabled'])): ?>

				<div id="post_draft_options_header" class="title_bar">
					<h4 class="titlebg">
						<strong><a href="#" id="postDraftExpandLink"><?= Lang::getTxt('drafts_show', file: 'Drafts') ?></a></strong>
					</h4>
					<span id="postDraftExpand" class="toggle_up" style="display: none;"></span>
				</div>
				<div id="post_draft_options">
					<dl class="settings">
						<dt><strong><?= Lang::getTxt('subject', file: 'General') ?></strong></dt>
						<dd><strong><?= trim(Lang::getTxt('draft_saved_on', ['date' => ''], file: 'Drafts')) ?></strong></dd><?php foreach (Utils::$context['drafts'] as $draft): ?>

						<dt><?= $draft['link'] ?></dt>
						<dd><?= $draft['poster_time'] ?></dd><?php endforeach; ?>

					</dl>
				</div><?php endif; ?><?php /* Require an image to be typed to save spamming? */ ?><?php if (Utils::$context['require_verification']): ?>

				<div class="post_verification">
					<strong><?= Lang::getTxt('pm_visual_verification_label', file: 'PersonalMessage') ?></strong>
					<?php $this->subTemplate('control_verification', ['verify_id' => Utils::$context['visual_verification_id'], 'display_type' => 'all']); ?>

				</div><?php endif; ?><?php /* Send, Preview, spellcheck buttons. */ ?>

				<span id="post_confirm_buttons">
					<?php $this->subTemplate('control_richedit_buttons', ['editor_id' => Utils::$context['post_box_name']]); ?>

				</span>
				<input type="hidden" name="<?= Utils::$context['session_var'] ?>" value="<?= Utils::$context['session_id'] ?>">
				<input type="hidden" name="seqnum" value="<?= Utils::$context['form_sequence_number'] ?>">
				<input type="hidden" name="replied_to" value="<?= !empty(Utils::$context['quoted_message']['id']) ? Utils::$context['quoted_message']['id'] : 0 ?>">
				<input type="hidden" name="pm_head" value="<?= !empty(Utils::$context['quoted_message']['pm_head']) ? Utils::$context['quoted_message']['pm_head'] : 0 ?>">
				<input type="hidden" name="f" value="<?= Utils::$context['folder'] ?? '' ?>">
				<input type="hidden" name="l" value="<?= Utils::$context['current_label_id'] ?? -1 ?>">
				<br class="clear_right">
			</div><!-- .roundframe -->
		</form>
		<script><?php /* The functions used to preview a personal message without loading a new page. */ ?>

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
						var test = new XMLHttpRequest();
						if (!('setRequestHeader' in test))
							return submitThisOnce(document.forms.postmodify);
					}
					// @todo Currently not sending poll options and option checkboxes.
					var x = new Array();
					var textFields = ['subject', <?= Utils::escapeJavaScript(Utils::$context['post_box_name']) ?>, 'to', 'bcc'];
					var numericFields = ['recipient_to[]', 'recipient_bcc[]'];
					var checkboxFields = [];

					for (var i = 0, n = textFields.length; i < n; i++)
						if (textFields[i] in document.forms.postmodify)
						{
							// Handle the WYSIWYG editor.
							if (textFields[i] == <?= Utils::escapeJavaScript(Utils::$context['post_box_name']) ?> && <?= Utils::escapeJavaScript('oEditorHandle_' . Utils::$context['post_box_name']) ?> in window && oEditorHandle_<?= Utils::$context['post_box_name'] ?>.bRichTextEnabled)
								x[x.length] = 'message_mode=1&' + textFields[i] + '=' + oEditorHandle_<?= Utils::$context['post_box_name'] ?>.getText(false).php_to8bit().php_urlencode();
							else
								x[x.length] = textFields[i] + '=' + document.forms.postmodify[textFields[i]].value.php_to8bit().php_urlencode();
						}
					for (var i = 0, n = numericFields.length; i < n; i++)
						if (numericFields[i] in document.forms.postmodify && 'value' in document.forms.postmodify[numericFields[i]])
							x[x.length] = numericFields[i] + '=' + parseInt(document.forms.postmodify.elements[numericFields[i]].value);
					for (var i = 0, n = checkboxFields.length; i < n; i++)
						if (checkboxFields[i] in document.forms.postmodify && document.forms.postmodify.elements[checkboxFields[i]].checked)
							x[x.length] = checkboxFields[i] + '=' + document.forms.postmodify.elements[checkboxFields[i]].value;

					sendXMLDocument(smf_prepareScriptUrl(smf_scripturl) + 'action=pm;sa=send2;preview;xml', x.join('&'), onDocSent);

					document.getElementById('preview_section').style.display = '';
					setInnerHTML(document.getElementById('preview_subject'), txt_preview_title);
					setInnerHTML(document.getElementById('preview_body'), txt_preview_fetch);

					return false;
				}
				else
					return submitThisOnce(document.forms.postmodify);
			}
			function onDocSent(XMLDoc)
			{
				if (!XMLDoc)
				{
					document.forms.postmodify.preview.onclick = new function ()
					{
						return true;
					}
					document.forms.postmodify.preview.click();
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
					document.forms.postmodify.<?= Utils::$context['post_box_name'] ?>.style.border = '1px solid red';
				else if (document.forms.postmodify.<?= Utils::$context['post_box_name'] ?>.style.borderColor == 'red' || document.forms.postmodify.<?= Utils::$context['post_box_name'] ?>.style.borderColor == 'red red red red')
				{
					if ('runtimeStyle' in document.forms.postmodify.<?= Utils::$context['post_box_name'] ?>)
						document.forms.postmodify.<?= Utils::$context['post_box_name'] ?>.style.borderColor = '';
					else
						document.forms.postmodify.<?= Utils::$context['post_box_name'] ?>.style.border = null;
				}
				location.hash = '#' + 'preview_section';
			}<?php /* Code for showing and hiding drafts */ ?><?php if (!empty(Utils::$context['drafts'])): ?>

			var oSwapDraftOptions = new smc_Toggle({
				bToggleEnabled: true,
				bCurrentlyCollapsed: true,
				aSwappableContainers: [
					'post_draft_options',
				],
				aSwapImages: [
					{
						sId: 'postDraftExpand',
						altExpanded: '-',
						altCollapsed: '+'
					}
				],
				aSwapLinks: [
					{
						sId: 'postDraftExpandLink',
						msgExpanded: <?= Utils::escapeJavaScript(Lang::getTxt('draft_hide', file: 'Drafts')) ?>,
						msgCollapsed: <?= Utils::escapeJavaScript(Lang::getTxt('drafts_show', file: 'Drafts')) ?>

					}
				]
			});<?php endif; ?>

		</script><?php /* Show the message you're replying to. */ ?><?php if (Utils::$context['reply']): ?>

		<br><br>
		<div class="cat_bar">
			<h3 class="catbg"><?= Lang::getTxt('pm_subject', Utils::$context['quoted_message'], file: 'PersonalMessage') ?></h3>
		</div>
		<div class="windowbg">
			<div class="clear">
				<span class="smalltext floatright"><?= Utils::$context['quoted_message']['time'] ?></span>
				<?= Lang::getTxt('pm_from', ['member' => Utils::$context['quoted_message']['member']['link']], file: 'PersonalMessage') ?>

			</div>
			<hr>
			<?= Utils::adjustHeadingLevels(Utils::$context['quoted_message']['body'], 3) ?>

		</div>
		<br class="clear"><?php endif; ?>

		<script>
			var oPersonalMessageSend = new smf_PersonalMessageSend({
				sSessionId: smf_session_id,
				sSessionVar: smf_session_var,
				sTextDeleteItem: '<?= Lang::getTxt('autosuggest_delete_item', file: 'General') ?>',
				sToControlId: 'to_control',
				aToRecipients: [<?php foreach (Utils::$context['recipients']['to'] as $i => $member): ?>

					{
						sItemId: <?= Utils::escapeJavaScript($member['id']) ?>,
						sItemName: <?= Utils::escapeJavaScript($member['name']) ?>

					}<?= $i == count(Utils::$context['recipients']['to']) - 1 ? '' : ',' ?><?php endforeach; ?>

				],
				aBccRecipients: [<?php foreach (Utils::$context['recipients']['bcc'] as $i => $member): ?>

					{
						sItemId: <?= Utils::escapeJavaScript($member['id']) ?>,
						sItemName: <?= Utils::escapeJavaScript($member['name']) ?>

					}<?= $i == count(Utils::$context['recipients']['bcc']) - 1 ? '' : ',' ?><?php endforeach; ?>

				],
				sBccControlId: 'bcc_control',
				sBccDivId: 'bcc_div',
				sBccDivId2: 'bcc_div2',
				sBccLinkId: 'bcc_link',
				sBccLinkContainerId: 'bcc_link_container',
				bBccShowByDefault: <?= empty(Utils::$context['recipients']['bcc']) && empty(Utils::$context['bcc_value']) ? 'false' : 'true' ?>,
				sShowBccLinkTemplate: <?= Utils::escapeJavaScript(
		'
					<a href="#" id="bcc_link">' . Lang::getTxt('make_bcc', file: 'PersonalMessage') . '</a> <a href="' . Config::$scripturl . '?action=helpadmin;help=pm_bcc" onclick="return reqOverlayDiv(this.href);">(?)</a>',
	) ?>

			});
		</script>