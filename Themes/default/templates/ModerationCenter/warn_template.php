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
 * Add or edit a warning template.
 */
?>
	<div id="modcenter">
		<form action="<?= Config::$scripturl ?>?action=moderate;area=warnings;sa=templateedit;tid=<?= Utils::$context['id_template'] ?>" method="post" accept-charset="UTF-8">
			<div class="cat_bar">
				<h3 class="catbg"><?= Utils::$context['page_title'] ?></h3>
			</div>
			<div class="information">
				<?= Lang::getTxt('mc_warning_template_desc', file: 'ModerationCenter') ?>
			</div>
			<div class="windowbg">
				<div class="errorbox"<?= empty(Utils::$context['warning_errors']) ? ' style="display: none"' : '' ?> id="errors">
					<dl>
						<dt>
							<strong id="error_serious"><?= Lang::getTxt('error_while_submitting', file: 'General') ?></strong>
						</dt>
						<dd class="error" id="error_list">
							<?= empty(Utils::$context['warning_errors']) ? '' : implode('<br>', Utils::$context['warning_errors']) ?>
						</dd>
					</dl>
				</div>
				<div id="box_preview"<?= !empty(Utils::$context['template_preview']) ? '' : ' style="display:none"' ?>>
					<dl class="settings">
						<dt>
							<strong><?= Lang::getTxt('preview', file: 'General') ?></strong>
						</dt>
						<dd id="template_preview">
							<?= !empty(Utils::$context['template_preview']) ? Utils::$context['template_preview'] : '' ?>
						</dd>
					</dl>
				</div>
				<dl class="settings">
					<dt>
						<strong><label for="template_title"><?= Lang::getTxt('mc_warning_template_title', file: 'ModerationCenter') ?></label></strong>
					</dt>
					<dd>
						<input type="text" id="template_title" name="template_title" value="<?= Utils::$context['template_data']['title'] ?>" size="30">
					</dd>
					<dt>
						<strong><label for="template_body"><?= Lang::getTxt('profile_warning_notify_body', file: 'Profile') ?></label></strong><br>
						<span class="smalltext"><?= Lang::getTxt('mc_warning_template_body_desc', file: 'ModerationCenter') ?></span>
					</dt>
					<dd>
						<textarea id="template_body" name="template_body" rows="10" cols="45" class="smalltext"><?= Utils::$context['template_data']['body'] ?></textarea>
					</dd>
				</dl>
<?php if (Utils::$context['template_data']['can_edit_personal']): ?>
				<input type="checkbox" name="make_personal" id="make_personal"<?= Utils::$context['template_data']['personal'] ? ' checked' : '' ?>>
					<label for="make_personal">
						<strong><?= Lang::getTxt('mc_warning_template_personal', file: 'ModerationCenter') ?></strong>
					</label>
					<p class="smalltext"><?= Lang::getTxt('mc_warning_template_personal_desc', file: 'ModerationCenter') ?></p>
<?php endif; ?>
				<input type="submit" name="preview" id="preview_button" value="<?= Lang::getTxt('preview', file: 'General') ?>" class="button">
				<input type="submit" name="save" value="<?= Utils::$context['page_title'] ?>" class="button">
			</div><!-- .windowbg -->
			<input type="hidden" name="<?= Utils::$context['session_var'] ?>" value="<?= Utils::$context['session_id'] ?>">
			<input type="hidden" name="<?= Utils::$context['mod-wt_token_var'] ?>" value="<?= Utils::$context['mod-wt_token'] ?>">
		</form>
	</div><!-- #modcenter -->

	<script>
		$(document).ready(function() {
			$("#preview_button").click(function() {
				return ajax_getTemplatePreview();
			});
		});

		function ajax_getTemplatePreview ()
		{
			$.ajax({
				type: "POST",
				headers: {
					"X-SMF-AJAX": 1
				},
				xhrFields: {
					withCredentials: typeof allow_xhjr_credentials !== "undefined" ? allow_xhjr_credentials : false
				},
				url: "<?= Config::$scripturl ?>?action=xmlhttp;sa=previews;xml",
				data: {item: "warning_preview", title: $("#template_title").val(), body: $("#template_body").val(), user: $('input[name="u"]').attr("value")},
				context: document.body,
				success: function(request){
					$("#box_preview").css({display:""});
					$("#template_preview").html($(request).find('body').text());
					if ($(request).find("error").text() != '')
					{
						$("#errors").css({display:""});
						var errors_html = '';
						var errors = $(request).find('error').each(function() {
							errors_html += $(this).text() + '<br>';
						});

						$(document).find("#error_list").html(errors_html);
					}
					else
					{
						$("#errors").css({display:"none"});
						$("#error_list").html('');
					}
				return false;
				},
			});
			return false;
		}
	</script>