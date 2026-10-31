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
 * Displays information about file versions installed, and compares them to current version.
 */
?>
						<div id="section_header" class="cat_bar">
							<h3 class="catbg">
								<?= Lang::getTxt('admin_version_check', file: 'Admin') ?>
							</h3>
						</div>
						<div class="information"><?= Lang::getTxt('version_check_desc', file: 'Admin') ?></div>
						<div id="versions">
							<table class="table_grid">
								<thead>
									<tr class="title_bar">
										<th class="half_table">
											<strong><?= Lang::getTxt('admin_smffile', file: 'Admin') ?></strong>
										</th>
										<th class="quarter_table">
											<strong><?= Lang::getTxt('dvc_your', file: 'Admin') ?></strong>
										</th>
										<th class="quarter_table">
											<strong><?= Lang::getTxt('dvc_current', file: 'Admin') ?></strong>
										</th>
									</tr>
								</thead>
								<tbody>
<?php /* The current version of the core SMF package. */ ?>
									<tr class="windowbg">
										<td class="half_table">
											<?= Lang::getTxt('admin_smfpackage', file: 'Admin') ?>
										</td>
										<td class="quarter_table">
											<em id="yourSMF"><?= Utils::$context['forum_version'] ?></em>
										</td>
										<td class="quarter_table">
											<em id="currentSMF">??</em>
										</td>
									</tr>
<?php /* Now list all the root file versions, starting with the overall version (if all match!). */ ?>
									<tr class="windowbg">
										<td class="half_table">
											<a href="#" id="Root-link"><?= Lang::getTxt('dvc_root', file: 'Admin') ?></a>
										</td>
										<td class="quarter_table">
											<em id="yourRoot">??</em>
										</td>
										<td class="quarter_table">
											<em id="currentRoot">??</em>
										</td>
									</tr>
								</tbody>
							</table>

							<table id="Root" class="table_grid">
								<tbody>
<?php /* Loop through every source file displaying its version - using javascript. */ ?>
<?php foreach (Utils::$context['root_versions'] as $filename => $version): ?>
									<tr class="windowbg">
										<td class="half_table">
											<?= $filename ?>
										</td>
										<td class="quarter_table">
											<em id="yourRoot<?= $filename ?>"><?= $version ?></em>
										</td>
										<td class="quarter_table">
											<em id="currentRoot<?= $filename ?>">??</em>
										</td>
									</tr>
<?php endforeach; ?>
<?php /* Now list all the source file versions, starting with the overall version (if all match!). */ ?>
								</tbody>
							</table>
							<table id="Root" class="table_grid">
								<tbody>
									<tr class="windowbg">
										<td class="half_table">
											<a href="#" id="Sources-link"><?= Lang::getTxt('dvc_sources', file: 'Admin') ?></a>
										</td>
										<td class="quarter_table">
											<em id="yourSources">??</em>
										</td>
										<td class="quarter_table">
											<em id="currentSources">??</em>
										</td>
									</tr>
								</tbody>
							</table>

							<table id="Sources" class="table_grid">
								<tbody>
<?php /* Loop through every source file displaying its version - using javascript. */ ?>
<?php foreach (Utils::$context['file_versions'] as $filename => $version): ?>
									<tr class="windowbg">
										<td class="half_table">
											<?= $filename ?>
										</td>
										<td class="quarter_table">
											<em id="yourSources<?= $filename ?>"><?= $version ?></em>
										</td>
										<td class="quarter_table">
											<em id="currentSources<?= $filename ?>">??</em>
										</td>
									</tr>
<?php endforeach; ?>
<?php /* Default template files. */ ?>
								</tbody>
							</table>

							<table class="table_grid">
								<tbody>
									<tr class="windowbg">
										<td class="half_table">
											<a href="#" id="Default-link"><?= Lang::getTxt('dvc_default', file: 'Admin') ?></a>
										</td>
										<td class="quarter_table">
											<em id="yourDefault">??</em>
										</td>
										<td class="quarter_table">
											<em id="currentDefault">??</em>
										</td>
									</tr>
								</tbody>
							</table>

							<table id="Default" class="table_grid">
								<tbody>
<?php foreach (Utils::$context['default_template_versions'] as $filename => $version): ?>
									<tr class="windowbg">
										<td class="half_table">
											<?= $filename ?>
										</td>
										<td class="quarter_table">
											<em id="yourDefault<?= $filename ?>"><?= $version ?></em>
										</td>
										<td class="quarter_table">
											<em id="currentDefault<?= $filename ?>">??</em>
										</td>
									</tr>
<?php endforeach; ?>
<?php /* Now the language files... */ ?>
								</tbody>
							</table>

							<table class="table_grid">
								<tbody>
									<tr class="windowbg">
										<td class="half_table">
											<a href="#" id="Languages-link"><?= Lang::getTxt('dvc_languages', file: 'Admin') ?></a>
										</td>
										<td class="quarter_table">
											<em id="yourLanguages">??</em>
										</td>
										<td class="quarter_table">
											<em id="currentLanguages">??</em>
										</td>
									</tr>
								</tbody>
							</table>

							<table id="Languages" class="table_grid">
								<tbody>
<?php foreach (Utils::$context['default_language_versions'] as $language => $files): ?>
<?php foreach ($files as $filename => $version): ?>
									<tr class="windowbg">
										<td class="half_table">
											<em><?= $language ?></em>/<?= $filename ?>
										</td>
										<td class="quarter_table">
											<em id="yourLanguage_<?= $language ?>_<?= $filename ?>"><?= $version ?></em>
										</td>
										<td class="quarter_table">
											<em id="currentLanguage_<?= $language ?>_<?= $filename ?>">??</em>
										</td>
									</tr>
<?php endforeach; ?>
<?php endforeach; ?>
								</tbody>
							</table>
<?php /* Display the version information for the currently selected theme - if it is not the default one. */ ?>
<?php if (!empty(Utils::$context['template_versions'])): ?>
							<table class="table_grid">
								<tbody>
									<tr class="windowbg">
										<td class="half_table">
											<a href="#" id="Templates-link"><?= Lang::getTxt('dvc_templates', file: 'Admin') ?></a>
										</td>
										<td class="quarter_table">
											<em id="yourTemplates">??</em>
										</td>
										<td class="quarter_table">
											<em id="currentTemplates">??</em>
										</td>
									</tr>
								</tbody>
							</table>

							<table id="Templates" class="table_grid">
								<tbody>
<?php foreach (Utils::$context['template_versions'] as $filename => $version): ?>
									<tr class="windowbg">
										<td class="half_table">
											<?= $filename ?>
										</td>
										<td class="quarter_table">
											<em id="yourTemplates<?= $filename ?>"><?= $version ?></em>
										</td>
										<td class="quarter_table">
											<em id="currentTemplates<?= $filename ?>">??</em>
										</td>
									</tr>
<?php endforeach; ?>
								</tbody>
							</table>
<?php endif; ?>
<?php /* Display the tasks files version. */ ?>
<?php if (!empty(Utils::$context['tasks_versions'])): ?>
							<table class="table_grid">
								<tbody>
									<tr class="windowbg">
										<td class="half_table">
											<a href="#" id="Tasks-link"><?= Lang::getTxt('dvc_tasks', file: 'Admin') ?></a>
										</td>
										<td class="quarter_table">
											<em id="yourTasks">??</em>
										</td>
										<td class="quarter_table">
											<em id="currentTasks">??</em>
										</td>
									</tr>
								</tbody>
							</table>

							<table id="Tasks" class="table_grid">
								<tbody>
<?php foreach (Utils::$context['tasks_versions'] as $filename => $version): ?>
									<tr class="windowbg">
										<td class="half_table">
											<?= $filename ?>
										</td>
										<td class="quarter_table">
											<em id="yourTasks<?= $filename ?>"><?= $version ?></em>
										</td>
										<td class="quarter_table">
											<em id="currentTasks<?= $filename ?>">??</em>
										</td>
									</tr>
<?php endforeach; ?>
								</tbody>
							</table>
<?php endif; ?>
						</div><!-- #versions -->
<?php
/* Below is the hefty javascript for this. Upon opening the page it checks the current file versions with ones
	   held at simplemachines.org and works out if they are up to date. If they aren't it colors that files number
	   red. It also contains the function, swapOption, that toggles showing the detailed information for each of the
	   file categories. (sources, languages, and templates.) */
?>
					<script src="<?= Config::$scripturl ?>?action=viewsmfile;filename=detailed-version.js"></script>
					<script>
						var oViewVersions = new smf_ViewVersions({
							aKnownLanguages: [
								'<?= implode('\',
								\'', Utils::$context['default_known_languages']) ?>'
							],
							oSectionContainerIds: {
								Sources: 'Sources',
								Default: 'Default',
								Languages: 'Languages',
								Templates: 'Templates',
								Tasks: 'Tasks'
							}
						});
					</script>