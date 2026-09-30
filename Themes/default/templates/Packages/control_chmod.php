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
use SMF\Sapi;
use SMF\Utils;

if (!defined('SMF')) {
	die('No direct access...');
}

/*
 * CHMOD control form
 *
 * @return bool False if nothing to do.
 */
?><?php /* Nothing to do? Brilliant! */ ?><?php if (empty(Utils::$context['package_ftp'])): ?><?php return false; ?><?php endif; ?><?php if (empty(Utils::$context['package_ftp']['form_elements_only'])): ?>

				<?= Lang::getTxt('package_ftp_why', ['onclick' => 'document.getElementById(\'need_writable_list\').style.display = \'\'; return false;'], file: 'Packages') ?><br>
				<div id="need_writable_list" class="smalltext">
					<?= Lang::getTxt('package_ftp_why_file_list', file: 'Packages') ?>

					<ul style="display: inline;"><?php if (!empty(Utils::$context['notwritable_files'])): ?><?php foreach (Utils::$context['notwritable_files'] as $file): ?>

						<li><?= $file ?></li><?php endforeach; ?><?php endif; ?>

					</ul><?php if (!Sapi::isOS(Sapi::OS_WINDOWS)): ?>

					<hr>
					<?= Lang::getTxt('package_chmod_linux', file: 'Packages') ?><br>
					<samp># chmod a+w <?= implode(' ', Utils::$context['notwritable_files']) ?></samp><?php endif; ?>

				</div><!-- #need_writable_list --><?php endif; ?>

				<div class="bordercolor" id="ftp_error_div" style="<?= (!empty(Utils::$context['package_ftp']['error']) ? '' : 'display:none;') ?>padding: 1px; margin: 1ex;">
					<div class="windowbg" id="ftp_error_innerdiv" style="padding: 1ex;">
						<samp id="ftp_error_message"><?= !empty(Utils::$context['package_ftp']['error']) ? Utils::$context['package_ftp']['error'] : '' ?></samp>
					</div>
				</div><?php if (!empty(Utils::$context['package_ftp']['destination'])): ?>

				<form action="<?= Utils::$context['package_ftp']['destination'] ?>" method="post" accept-charset="UTF-8"><?php endif; ?>

					<fieldset>
					<dl class="settings">
						<dt>
							<label for="ftp_server"><?= Lang::getTxt('package_ftp_server', file: 'Packages') ?></label>
						</dt>
						<dd>
							<input type="text" size="30" name="ftp_server" id="ftp_server" value="<?= Utils::$context['package_ftp']['server'] ?>">
							<label for="ftp_port"><?= Lang::getTxt('package_ftp_port', file: 'Packages') ?></label>
							<input type="text" size="3" name="ftp_port" id="ftp_port" value="<?= Utils::$context['package_ftp']['port'] ?>">
						</dd>
						<dt>
							<label for="ftp_username"><?= Lang::getTxt('package_ftp_username', file: 'Packages') ?></label>
						</dt>
						<dd>
							<input type="text" size="50" name="ftp_username" id="ftp_username" value="<?= Utils::$context['package_ftp']['username'] ?>">
						</dd>
						<dt>
							<label for="ftp_password"><?= Lang::getTxt('package_ftp_password', file: 'Packages') ?></label>
						</dt>
						<dd>
							<input type="password" size="50" name="ftp_password" id="ftp_password">
						</dd>
						<dt>
							<label for="ftp_path"><?= Lang::getTxt('package_ftp_path', file: 'Packages') ?></label>
						</dt>
						<dd>
							<input type="text" size="50" name="ftp_path" id="ftp_path" value="<?= Utils::$context['package_ftp']['path'] ?>">
						</dd>
					</dl>
					</fieldset><?php if (empty(Utils::$context['package_ftp']['form_elements_only'])): ?>

					<div class="righttext" style="margin: 1ex;">
						<span id="test_ftp_placeholder_full"></span>
						<input type="submit" value="<?= Lang::getTxt('package_proceed', file: 'Packages') ?>" class="button">
					</div><?php endif; ?><?php if (!empty(Utils::$context['package_ftp']['destination'])): ?>

					<input type="hidden" name="<?= Utils::$context['session_var'] ?>" value="<?= Utils::$context['session_id'] ?>">
				</form><?php endif; ?><?php /* Hide the details of the list. */ ?><?php if (empty(Utils::$context['package_ftp']['form_elements_only'])): ?>

				<script>
					document.getElementById('need_writable_list').style.display = 'none';
				</script><?php endif; ?><?php /* Quick generate the test button. */ ?>

				<script>
					// Generate a "test ftp" button.
					var generatedButton = false;
					function generateFTPTest()
					{
						// Don't ever call this twice!
						if (generatedButton)
							return false;
						generatedButton = true;

						// No XML?
						if (!window.XMLHttpRequest || (!document.getElementById("test_ftp_placeholder") && !document.getElementById("test_ftp_placeholder_full")))
							return false;

						var ftpTest = document.createElement("input");
						ftpTest.type = "button";
						ftpTest.onclick = testFTP;

						if (document.getElementById("test_ftp_placeholder"))
						{
							ftpTest.value = "<?= Lang::getTxt('package_ftp_test', file: 'Packages') ?>";
							document.getElementById("test_ftp_placeholder").appendChild(ftpTest);
						}
						else
						{
							ftpTest.value = "<?= Lang::getTxt('package_ftp_test_connection', file: 'Packages') ?>";
							document.getElementById("test_ftp_placeholder_full").appendChild(ftpTest);
						}
					}
					function testFTPResults(oXMLDoc)
					{
						ajax_indicator(false);

						// This assumes it went wrong!
						var wasSuccess = false;
						var message = "<?= addcslashes(Lang::getTxt('package_ftp_test_failed', file: 'Packages'), "'") ?>";

						var results = oXMLDoc.getElementsByTagName('results')[0].getElementsByTagName('result');
						if (results.length > 0)
						{
							if (results[0].getAttribute('success') == 1)
								wasSuccess = true;
							message = results[0].firstChild.nodeValue;
						}

						document.getElementById("ftp_error_div").style.display = "";
						document.getElementById("ftp_error_div").style.backgroundColor = wasSuccess ? "green" : "red";
						document.getElementById("ftp_error_innerdiv").style.backgroundColor = wasSuccess ? "#DBFDC7" : "#FDBDBD";

						setInnerHTML(document.getElementById("ftp_error_message"), message);
					}
					generateFTPTest();
				</script><?php
// Make sure the button gets generated last.
Utils::$context['insert_after_template'] .= '
				<script>
					generateFTPTest();
				</script>';
?>
