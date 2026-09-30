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
 * The file permissions page.
 */
?><?php /* This will handle expanding the selection. */ ?>

	<script>
		var oRadioValues = {
			0: "read",
			1: "writable",
			2: "execute",
			3: "custom",
			4: "no_change"
		}
		function dynamicAddMore()
		{
			ajax_indicator(true);

			getXMLDocument(smf_prepareScriptUrl(smf_scripturl) + 'action=admin;area=packages;fileoffset=' + (parseInt(this.offset) + <?= Utils::$context['file_limit'] ?>) + ';onlyfind=' + escape(this.path) + ';sa=perms;xml;<?= Utils::$context['session_var'] ?>=<?= Utils::$context['session_id'] ?>', onNewFolderReceived);
		}

		// Getting something back?
		function onNewFolderReceived(oXMLDoc)
		{
			ajax_indicator(false);

			var fileItems = oXMLDoc.getElementsByTagName('folders')[0].getElementsByTagName('folder');

			// No folders, no longer worth going further.
			if (fileItems.length < 1)
			{
				if (oXMLDoc.getElementsByTagName('roots')[0].getElementsByTagName('root')[0])
				{
					var rootName = oXMLDoc.getElementsByTagName('roots')[0].getElementsByTagName('root')[0].firstChild.nodeValue;
					var itemLink = document.getElementById('link_' + rootName);

					// Move the children up.
					for (i = 0; i <= itemLink.childNodes.length; i++)
						itemLink.parentNode.insertBefore(itemLink.childNodes[0], itemLink);

					// And remove the link.
					itemLink.parentNode.removeChild(itemLink);
				}
				return false;
			}
			var tableHandle = false;
			var isMore = false;
			var ident = "";
			var my_ident = "";
			var curLevel = 0;

			for (var i = 0; i < fileItems.length; i++)
			{
				if (fileItems[i].getAttribute('more') == 1)
				{
					isMore = true;
					var curOffset = fileItems[i].getAttribute('offset');
				}

				if (fileItems[i].getAttribute('more') != 1 && document.getElementById("insert_div_loc_" + fileItems[i].getAttribute('ident')))
				{
					ident = fileItems[i].getAttribute('ident');
					my_ident = fileItems[i].getAttribute('my_ident');
					curLevel = fileItems[i].getAttribute('level') * 5;
					curPath = fileItems[i].getAttribute('path');

					// Get where we're putting it next to.
					tableHandle = document.getElementById("insert_div_loc_" + fileItems[i].getAttribute('ident'));

					var curRow = document.createElement("tr");
					curRow.className = "windowbg";
					curRow.id = "content_" + my_ident;
					curRow.style.display = "";
					var curCol = document.createElement("td");
					curCol.className = "smalltext";
					curCol.width = "40%";

					// This is the name.
					var fileName = document.createTextNode(fileItems[i].firstChild.nodeValue);

					// Start by wacking in the spaces.
					setInnerHTML(curCol, repeatString("&nbsp;", curLevel));

					// Create the actual text.
					if (fileItems[i].getAttribute('folder') == 1)
					{
						var linkData = document.createElement("a");
						linkData.name = "fol_" + my_ident;
						linkData.id = "link_" + my_ident;
						linkData.href = '#';
						linkData.path = curPath + "/" + fileItems[i].firstChild.nodeValue;
						linkData.ident = my_ident;
						linkData.onclick = dynamicExpandFolder;

						var folderImage = document.createElement("span");
						folderImage.className = "main_icons folder";
						linkData.appendChild(folderImage);

						linkData.appendChild(fileName);
						curCol.appendChild(linkData);
					}
					else
						curCol.appendChild(fileName);

					curRow.appendChild(curCol);

					// Right, the permissions.
					curCol = document.createElement("td");
					curCol.className = "smalltext";

					var writeSpan = document.createElement("span");
					writeSpan.className = fileItems[i].getAttribute('writable') ? "green" : "red";
					setInnerHTML(writeSpan, fileItems[i].getAttribute('writable') ? '<?= Lang::getTxt('package_file_perms_writable', file: 'Packages') ?>' : '<?= Lang::getTxt('package_file_perms_not_writable', file: 'Packages') ?>');
					curCol.appendChild(writeSpan);

					if (fileItems[i].getAttribute('permissions'))
					{
						var permData = document.createTextNode("\u00a0(<?= Lang::getTxt('package_file_perms_chmod', file: 'Packages') ?>: " + fileItems[i].getAttribute('permissions') + ")");
						curCol.appendChild(permData);
					}

					curRow.appendChild(curCol);

					// Now add the five radio buttons.
					for (j = 0; j < 5; j++)
					{
						curCol = document.createElement("td");
						curCol.className = "centertext perm_" + oRadioValues[j];
						curCol.align = "center";

						var curInput = createNamedElement("input", "permStatus[" + curPath + "/" + fileItems[i].firstChild.nodeValue + "]", j == 4 ? "checked" : "");
						curInput.type = "radio";
						curInput.checked = "checked";
						curInput.value = oRadioValues[j];

						curCol.appendChild(curInput);
						curRow.appendChild(curCol);
					}

					// Put the row in.
					tableHandle.parentNode.insertBefore(curRow, tableHandle);

					// Put in a new dummy section?
					if (fileItems[i].getAttribute('folder') == 1)
					{
						var newRow = document.createElement("tr");
						newRow.id = "insert_div_loc_" + my_ident;
						newRow.style.display = "none";
						tableHandle.parentNode.insertBefore(newRow, tableHandle);
						var newCol = document.createElement("td");
						newCol.colspan = 2;
						newRow.appendChild(newCol);
					}
				}
			}

			// Is there some more to remove?
			if (document.getElementById("content_" + ident + "_more"))
			{
				document.getElementById("content_" + ident + "_more").parentNode.removeChild(document.getElementById("content_" + ident + "_more"));
			}

			// Add more?
			if (isMore && tableHandle)
			{
				// Create the actual link.
				var linkData = document.createElement("a");
				linkData.href = '#fol_' + my_ident;
				linkData.path = curPath;
				linkData.offset = curOffset;
				linkData.onclick = dynamicAddMore;

				linkData.appendChild(document.createTextNode('<?= Lang::getTxt('package_file_perms_more_files', file: 'Packages') ?>'));

				curRow = document.createElement("tr");
				curRow.className = "windowbg";
				curRow.id = "content_" + ident + "_more";
				tableHandle.parentNode.insertBefore(curRow, tableHandle);
				curCol = document.createElement("td");
				curCol.className = "smalltext";
				curCol.width = "40%";

				setInnerHTML(curCol, repeatString("&nbsp;", curLevel));
				curCol.appendChild(document.createTextNode('\u00ab '));
				curCol.appendChild(linkData);
				curCol.appendChild(document.createTextNode(' \u00bb'));

				curRow.appendChild(curCol);
				curCol = document.createElement("td");
				curCol.className = "smalltext";
				curRow.appendChild(curCol);
			}

			// Keep track of it.
			var curInput = createNamedElement("input", "back_look[]");
			curInput.type = "hidden";
			curInput.value = curPath;

			curCol.appendChild(curInput);
		}
	</script>
	<div class="noticebox">
		<div>
			<strong><?= Lang::getTxt('package_file_perms_warning', file: 'Packages') ?></strong>
			<div class="smalltext">
				<ol style="margin-top: 2px; margin-bottom: 2px">
					<?= Lang::getTxt('package_file_perms_warning_desc', file: 'Packages') ?>

				</ol>
			</div>
		</div>
	</div>

	<form action="<?= Config::$scripturl ?>?action=admin;area=packages;sa=perms;<?= Utils::$context['session_var'] ?>=<?= Utils::$context['session_id'] ?>" method="post" accept-charset="UTF-8">
		<div class="cat_bar">
			<h3 class="catbg">
				<span class="floatleft"><?= Lang::getTxt('package_file_perms', file: 'Admin') ?></span><span class="perms_status floatright"><?= Lang::getTxt('package_file_perms_new_status', file: 'Packages') ?></span>
			</h3>
		</div>
		<table class="table_grid">
			<thead>
				<tr class="title_bar">
					<th class="lefttext" width="30%"><?= Lang::getTxt('package_file_perms_name', file: 'Packages') ?></th>
					<th width="30%" class="lefttext"><?= Lang::getTxt('package_file_perms_status', file: 'Packages') ?></th>
					<th width="8%"><span class="file_permissions"><?= Lang::getTxt('package_file_perms_status_read', file: 'Packages') ?></span></th>
					<th width="8%"><span class="file_permissions"><?= Lang::getTxt('package_file_perms_status_write', file: 'Packages') ?></span></th>
					<th width="8%"><span class="file_permissions"><?= Lang::getTxt('package_file_perms_status_execute', file: 'Packages') ?></span></th>
					<th width="8%"><span class="file_permissions"><?= Lang::getTxt('package_file_perms_status_custom', file: 'Packages') ?></span></th>
					<th width="8%"><span class="file_permissions"><?= Lang::getTxt('package_file_perms_status_no_change', file: 'Packages') ?></span></th>
				</tr>
			</thead>
			<tbody><?php foreach (Utils::$context['file_tree'] as $name => $dir): ?>

				<tr class="windowbg">
					<td width="30%">
						<strong><?php if (!empty($dir['type']) && ($dir['type'] == 'dir' || $dir['type'] == 'dir_recursive')): ?>

							<span class="main_icons folder"></span><?php endif; ?>

							<?= $name ?>

						</strong>
					</td>
					<td width="30%">
						<span style="color: <?= ($dir['perms']['chmod'] ? 'green' : 'red') ?>"><?= Lang::getTxt($dir['perms']['chmod'] ? 'package_file_perms_writable' : 'package_file_perms_not_writable', file: 'Packages') ?></span>
						<?= ($dir['perms']['perms'] ? ' (' . Lang::getTxt('package_file_perms_chmod', file: 'Packages') . ': ' . substr(sprintf('%o', $dir['perms']['perms']), -4) . ')' : '') ?>

					</td>
					<td class="centertext perm_read">
						<input type="radio" name="permStatus[<?= $name ?>]" value="read" class="centertext">
					</td>
					<td class="centertext perm_writable">
						<input type="radio" name="permStatus[<?= $name ?>]" value="writable" class="centertext">
					</td>
					<td class="centertext perm_execute">
						<input type="radio" name="permStatus[<?= $name ?>]" value="execute" class="centertext">
					</td>
					<td class="centertext perm_custom">
						<input type="radio" name="permStatus[<?= $name ?>]" value="custom" class="centertext">
					</td>
					<td class="centertext perm_no_change">
						<input type="radio" name="permStatus[<?= $name ?>]" value="no_change" checked class="centertext">
					</td>
				</tr><?php if (!empty($dir['contents'])): ?><?php $this->subTemplate('permission_show_contents', ['ident' => $name, 'contents' => $dir['contents'], 'level' => 1]); ?><?php endif; ?><?php endforeach; ?>

			</tbody>
		</table>
		<br>
		<div class="cat_bar">
			<h3 class="catbg"><?= Lang::getTxt('package_file_perms_change', file: 'Packages') ?></h3>
		</div>
		<div class="windowbg">
			<fieldset>
				<dl>
					<dt>
						<input type="radio" name="method" value="individual" checked id="method_individual">
						<label for="method_individual"><strong><?= Lang::getTxt('package_file_perms_apply', file: 'Packages') ?></strong></label>
					</dt>
					<dd>
						<em class="smalltext"><?= Lang::getTxt('package_file_perms_custom', file: 'Packages') ?> <input type="text" name="custom_value" value="0755" maxlength="4" size="5"> <a href="<?= Config::$scripturl ?>?action=helpadmin;help=chmod_flags" onclick="return reqOverlayDiv(this.href);" class="help">(?)</a></em>
					</dd>
					<dt>
						<input type="radio" name="method" value="predefined" id="method_predefined">
						<label for="method_predefined"><strong><?= Lang::getTxt('package_file_perms_predefined', file: 'Packages') ?></strong></label>
						<select name="predefined" onchange="document.getElementById('method_predefined').checked = 'checked';">
							<option value="restricted" selected><?= Lang::getTxt('package_file_perms_pre_restricted', file: 'Packages') ?></option>
							<option value="standard"><?= Lang::getTxt('package_file_perms_pre_standard', file: 'Packages') ?></option>
							<option value="free"><?= Lang::getTxt('package_file_perms_pre_free', file: 'Packages') ?></option>
						</select>
					</dt>
					<dd>
						<em class="smalltext"><?= Lang::getTxt('package_file_perms_predefined_note', file: 'Packages') ?></em>
					</dd>
				</dl>
			</fieldset><?php /* Likely to need FTP? */ ?><?php if (empty(Utils::$context['ftp_connected'])): ?>

			<p>
				<?= Lang::getTxt('package_file_perms_ftp_details', file: 'Packages') ?>

			</p>
			<?php $this->subTemplate('control_chmod'); ?>

			<div class="noticebox"><?= Lang::getTxt('package_file_perms_ftp_retain', file: 'Packages') ?></div><?php endif; ?>

			<span id="test_ftp_placeholder_full"></span>
			<input type="hidden" name="action_changes" value="1">
			<input type="submit" value="<?= Lang::getTxt('package_file_perms_go', file: 'Packages') ?>" name="go" class="button">
		</div><!-- .windowbg --><?php /* Any looks fors we've already done? */ ?><?php foreach (Utils::$context['look_for'] as $path): ?>

		<input type="hidden" name="back_look[]" value="<?= $path ?>"><?php endforeach; ?>

	</form>
	<br>